<?php
session_start();

// Check user authentication and authorization
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: ../../login.php');
    exit;
}

require_once '../../templates/header.php';
require_once '../../templates/navigation.php';
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_logs.php'; // For logging

$titre = 'Importer un Relevé Bancaire (CSV)';
$pdo = getPDO();

$message = '';
$messageType = ''; // 'success', 'danger', 'info', 'warning'

// Get list of accounting accounts for dropdown
$comptesCompta = [];
try {
    $stmt = $pdo->query("SELECT ID_Compte, Libelle_Compte FROM Comptes_compta ORDER BY Libelle_Compte ASC");
    $comptesCompta = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logApplicationError("Erreur PDO lors de la récupération des comptes comptables: " . $e->getMessage());
    $message = "Erreur lors du chargement des comptes comptables. Impossible de procéder à l'importation.";
    $messageType = 'danger';
}


// --- Handle File Upload and Processing ---
if (isset($_POST['submit_import'])) {
    $selectedCompteId = $_POST['id_compte'] ?? null;

    if (empty($selectedCompteId) || !is_numeric($selectedCompteId)) {
        $message = "Veuillez sélectionner un compte comptable valide.";
        $messageType = 'danger';
    } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $message = "Erreur lors du téléchargement du fichier. Code d'erreur: " . ($_FILES['csv_file']['error'] ?? 'N/A') . ".";
        $messageType = 'danger';
    } elseif ($_FILES['csv_file']['type'] !== 'text/csv' && $_FILES['csv_file']['type'] !== 'application/vnd.ms-excel') { // Common CSV MIME types
        $message = "Type de fichier invalide. Veuillez télécharger un fichier CSV.";
        $messageType = 'danger';
    } else {
        $tempFilePath = $_FILES['csv_file']['tmp_name']; // PHP's temporary upload path

        // Define CSV parsing parameters
        $delimiter = ';'; // Common delimiter for French CSVs
        $enclosure = '"';
        $escape = '\\';

        $importedRows = 0;
        $failedRows = 0;
        $processedTransactions = [];

        if (($handle = fopen($tempFilePath, 'r')) !== FALSE) {
            // Read the header row (and ignore it, or use it for mapping if more complex)
            $header = fgetcsv($handle, 0, $delimiter, $enclosure, $escape);

            // Assuming CSV columns are: Date, Description, Debit, Credit
            // Adjust these indices based on your actual CSV file structure
            $dateCol = 0;
            $descCol = 1;
            $debitCol = 2;
            $creditCol = 3;

            $pdo->beginTransaction(); // Start transaction for atomicity

            try {
                while (($data = fgetcsv($handle, 0, $delimiter, $enclosure, $escape)) !== FALSE) {
                    // Skip empty rows
                    if (empty(array_filter($data))) {
                        continue;
                    }

                    // Basic validation of row length
                    if (count($data) < max($dateCol, $descCol, $debitCol, $creditCol) + 1) {
                        $failedRows++;
                        logApplicationError("Ligne CSV invalide (colonnes manquantes): " . implode(', ', $data));
                        continue;
                    }

                    $dateTransaction = trim($data[$dateCol]);
                    $descriptionTransaction = trim($data[$descCol]);
                    $debitAmount = (float)str_replace(',', '.', trim($data[$debitCol])); // Handle comma as decimal separator
                    $creditAmount = (float)str_replace(',', '.', trim($data[$creditCol]));

                    // Basic data cleaning and conversion
                    $parsedDate = DateTime::createFromFormat('d/m/Y', $dateTransaction); // Adjust date format if needed (e.g., Y-m-d)
                    if (!$parsedDate) {
                        // Try another common format if the first fails
                        $parsedDate = DateTime::createFromFormat('Y-m-d', $dateTransaction);
                    }
                    if (!$parsedDate) {
                        $failedRows++;
                        logApplicationError("Date invalide dans le CSV: '{$dateTransaction}'");
                        continue;
                    }
                    $dbDate = $parsedDate->format('Y-m-d');
                    $dbMonth = $parsedDate->format('Y-m');

                    $montant = 0;
                    $sens = '';

                    if ($debitAmount > 0 && $creditAmount == 0) {
                        $montant = $debitAmount;
                        $sens = 'D'; // Débit
                    } elseif ($creditAmount > 0 && $debitAmount == 0) {
                        $montant = $creditAmount;
                        $sens = 'C'; // Crédit
                    } else {
                        // Invalid: both debit and credit, or neither
                        $failedRows++;
                        logApplicationError("Montant invalide (débit/crédit) dans le CSV: Débit={$debitAmount}, Crédit={$creditAmount}");
                        continue;
                    }

                    if ($montant <= 0) {
                        $failedRows++;
                        logApplicationError("Montant nul ou négatif détecté: " . $montant);
                        continue;
                    }

                    // 1. Insert into Ecritures (Main Transaction Header)
                    // Note: 'Cde' and 'NumeroAgenceSCE' are optional or might be fixed for bank imports.
                    // 'Numero_Piece' can be unique ID from bank, or left NULL for now.
                    $stmtEcriture = $pdo->prepare("
                        INSERT INTO Ecritures (Date_Saisie, Description, Montant_Total, ID_Journal, NomUtilisateur, Mois, Numero_Piece)
                        VALUES (GETDATE(), :description, :montant_total, :id_journal, :nom_utilisateur, :mois, :numero_piece)
                    ");
                    // Assuming a default ID_Journal for bank transactions (e.g., 5 for Bank Journal)
                    // You might need a dropdown for ID_Journal if it's dynamic
                    $idJournal = 5; // Placeholder: Adjust based on your JAL table Cde for 'Banque'
                    $nomUtilisateur = $_SESSION['username'] ?? 'ImportSystem'; // Log current user

                    $stmtEcriture->execute([
                        ':description' => "Relevé Bancaire: " . $descriptionTransaction,
                        ':montant_total' => $montant,
                        ':id_journal' => $idJournal,
                        ':nom_utilisateur' => $nomUtilisateur,
                        ':mois' => $dbMonth,
                        ':numero_piece' => null // Or a unique bank transaction ID if available
                    ]);
                    $idEcriture = $pdo->lastInsertId();

                    // 2. Insert into Lignes_Ecritures (Transaction Line Item)
                    $stmtLigne = $pdo->prepare("
                        INSERT INTO Lignes_Ecritures (ID_Ecriture, ID_Compte, Montant, Sens, Libelle_Ligne, is_reconciled)
                        VALUES (:id_ecriture, :id_compte, :montant, :sens, :libelle_ligne, :is_reconciled)
                    ");
                    $stmtLigne->execute([
                        ':id_ecriture' => $idEcriture,
                        ':id_compte' => $selectedCompteId,
                        ':montant' => $montant,
                        ':sens' => $sens,
                        ':libelle_ligne' => $descriptionTransaction,
                        ':is_reconciled' => 0 // Not reconciled yet
                    ]);

                    $importedRows++;
                }

                $pdo->commit(); // All inserts successful
                $message = "Importation terminée. <strong>{$importedRows}</strong> transactions importées avec succès. <strong>{$failedRows}</strong> lignes ignorées/échouées.";
                $messageType = 'success';
                logUserActivity("Importation de relevé bancaire par l'utilisateur ID: " . $_SESSION['user_id'] . ". Importé: {$importedRows}, Échoué: {$failedRows}. Compte: {$selectedCompteId}.");

            } catch (PDOException $e) {
                $pdo->rollBack(); // Rollback on any database error
                $message = "Erreur de base de données lors de l'importation. Aucune transaction n'a été enregistrée. Détails: " . $e->getMessage();
                $messageType = 'danger';
                logApplicationError("Erreur PDO lors de l'importation du relevé bancaire par l'utilisateur ID: " . $_SESSION['user_id'] . ". Erreur: " . $e->getMessage());
            } finally {
                fclose($handle); // Close the file handle
            }
        } else {
            $message = "Impossible d'ouvrir le fichier CSV temporaire.";
            $messageType = 'danger';
            logApplicationError("Impossible d'ouvrir le fichier CSV temporaire pour l'importation: " . $tempFilePath);
        }
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?= htmlspecialchars($titre) ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/tableau.css">
    <style>
        .csv-format-example pre {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 10px;
            border-radius: 4px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Instructions d'Importation</h3>
            </div>
            <div class="panel-body">
                <p>Veuillez préparer votre fichier CSV avec le format suivant. Le séparateur doit être un **point-virgule (`;`)**.</p>
                <div class="csv-format-example">
                    <h5>Format CSV attendu :</h5>
                    <pre>Date;Description;Débit;Crédit
20/05/2025;Achat fournitures;150.00;0.00
21/05/2025;Virement client X;0.00;1200.50
22/05/2025;Loyer bureau;800.00;0.00</pre>
                    <p>
                        **Colonnes :**
                        <ul>
                            <li>**Date :** Format JJ/MM/AAAA (ex: 20/05/2025)</li>
                            <li>**Description :** Texte libre décrivant la transaction.</li>
                            <li>**Débit :** Montant de la dépense/retrait. Utilisez `0.00` si c'est un crédit. Le séparateur décimal doit être un point.</li>
                            <li>**Crédit :** Montant du revenu/dépôt. Utilisez `0.00` si c'est un débit. Le séparateur décimal doit être un point.</li>
                        </ul>
                    </p>
                </div>
            </div>
        </div>

        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title">Télécharger le Fichier de Relevé Bancaire (.csv)</h3>
            </div>
            <div class="panel-body">
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="id_compte">Compte Comptable Cible :</label>
                        <select name="id_compte" id="id_compte" class="form-control" required>
                            <option value="">-- Sélectionner un compte --</option>
                            <?php foreach ($comptesCompta as $compte): ?>
                                <option value="<?= htmlspecialchars($compte['ID_Compte']) ?>">
                                    <?= htmlspecialchars($compte['Libelle_Compte']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="help-block">Sélectionnez le compte bancaire interne auquel ces transactions appartiennent.</p>
                    </div>

                    <div class="form-group">
                        <label for="csv_file">Sélectionner le fichier CSV :</label>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required class="form-control">
                        <p class="help-block">Taille maximale : <?php echo ini_get('upload_max_filesize'); ?></p>
                    </div>

                    <button type="submit" name="submit_import" class="btn btn-success">
                        <span class="glyphicon glyphicon-upload"></span> Importer le relevé
                    </button>
                </form>
            </div>
        </div>

    </div>
    <?php require_once '../../templates/footer.php'; ?>
</body>
</html>