<?php
// traitement.php (pour l'import de factures - script appelé par index.php)

require_once '../../fonctions/database.php'; // Pour la connexion à la base de données (factures)
require_once '../../fonctions/gestion_factures.php'; // Supposons un fichier pour gérer les factures

// *** Configuration ***
$cheminDossierUpload = '/chemin/vers/votre/dossier/upload/'; // DOIT CORRESPONDRE À index.php
$separateurCSV = ';'; // Séparateur par défaut pour les fichiers CSV
// ***/!\*** IMPORTANT : Sécuriser l'accès à ce dossier et valider les chemins. ***/!\***

// *** Gestion des erreurs et des succès ***
$messages = [];

// *** Récupération des paramètres du fichier uploadé ***
if (isset($_GET['fichier']) && !empty($_GET['fichier']) && isset($_GET['type']) && !empty($_GET['type'])) {
    $nomFichier = basename($_GET['fichier']); // Sécurité : Récupérer uniquement le nom du fichier
    $typeFichier = strtolower($_GET['type']);
    $cheminFichierComplet = $cheminDossierUpload . $nomFichier;

    // *** Vérification de l'existence du fichier ***
    if (file_exists($cheminFichierComplet)) {
        $messages[] = "<div class='alert alert-info'>Traitement du fichier : " . htmlspecialchars($nomFichier) . " (type: " . htmlspecialchars($typeFichier) . ")</div>";
        $donneesImportees = null;

        // *** Logique de lecture du fichier en fonction du type ***
        switch ($typeFichier) {
            case 'csv':
                $donneesImportees = lireFacturesCSV($cheminFichierComplet, $separateurCSV);
                break;
            case 'xml':
                $donneesImportees = lireFacturesXML($cheminFichierComplet); // Implémentez cette fonction
                break;
            case 'txt':
                $donneesImportees = lireFacturesTXT($cheminFichierComplet); // Implémentez cette fonction
                break;
            default:
                $messages[] = "<div class='alert alert-danger'>Type de fichier non supporté pour le traitement : " . htmlspecialchars($typeFichier) . "</div>";
        }

        // *** Traitement des données importées ***
        if ($donneesImportees !== null) {
            $succesImport = 0;
            $erreursImport = [];

            if (!empty($donneesImportees)) {
                $messages[] = "<div class='alert alert-info'>Début de l'import de " . count($donneesImportees) . " factures.</div>";

                foreach ($donneesImportees as $index => $factureData) {
                    // *** Adaptez cette logique d'insertion à votre table de factures ***
                    // *** Assurez-vous de valider et de nettoyer les données avant l'insertion ***
                    if (ajouterFacture($db, $factureData)) { // Supposons une fonction ajouterFacture dans gestion_factures.php
                        $succesImport++;
                    } else {
                        $erreursImport[] = "Erreur lors de l'import de la facture à la ligne/élément " . ($index + 1) . ": " . (isset($factureData['numero_facture']) ? htmlspecialchars($factureData['numero_facture']) : 'N/A');
                    }
                }

                $messages[] = "<div class='alert alert-success'>Import terminé. " . $succesImport . " factures importées avec succès.</div>";
                if (!empty($erreursImport)) {
                    $messages[] = "<div class='alert alert-warning'>Erreurs lors de l'import des factures suivantes :<ul><li>" . implode("</li><li>", $erreursImport) . "</li></ul></div>";
                }

                // *** Optionnellement, supprimer le fichier uploadé après le traitement ***
                // if (unlink($cheminFichierComplet)) {
                //     $messages[] = "<div class='alert alert-info'>Fichier uploadé supprimé après traitement.</div>";
                // } else {
                //     $messages[] = "<div class='alert alert-warning'>Erreur lors de la suppression du fichier uploadé.</div>";
                // }

            } else {
                $messages[] = "<div class='alert alert-warning'>Aucune donnée de facture trouvée dans le fichier.</div>";
            }
        }

    } else {
        $messages[] = "<div class='alert alert-danger'>Le fichier spécifié n'existe plus sur le serveur.</div>";
    }
} else {
    $messages[] = "<div class='alert alert-danger'>Paramètres de fichier manquants pour le traitement.</div>";
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Traitement de l'Import de Factures</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css" integrity="sha384-HSMxcRTRxnN+Bdg0JdbxYKrThecOKuH5zCYotlSAcp1+c8xmyTe9GYg1l9a69psu" crossorigin="anonymous">
    <link rel="stylesheet" href="../../css/monstyle.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <div class="container">
        <h2 class="page-header">Traitement de l'Import de Factures</h2>

        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $message): ?>
                <?= $message ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <p><a href="index.php" class="btn btn-default">Retour à la page d'import</a></p>
        <p><a href="../factures/index.php" class="btn btn-info">Aller à la gestion des factures</a></p>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js" integrity="sha384-aJ21OjlMXNLJ5ywYOIDjxxyTwCypxSoOO3FxyYr4fccRoP1h0IWcAukj0jz9uNNs" crossorigin="anonymous"></script>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="js/script.js"></script>
</body>
</html>

<?php
// Fonctions de lecture de fichiers (à implémenter selon vos formats)

function lireFacturesCSV($cheminFichier, $separateur) {
    $factures = [];
    if (($handle = fopen($cheminFichier, "r")) !== false) {
        $headers = fgetcsv($handle, 0, $separateur);
        if ($headers) {
            while (($data = fgetcsv($handle, 0, $separateur)) !== false) {
                if (count($headers) === count($data)) {
                    $factures[] = array_combine($headers, $data);
                } else {
                    error_log("Nombre de colonnes incorrect dans le CSV : " . $cheminFichier);
                }
            }
        }
        fclose($handle);
    }
    return $factures;
}

function lireFacturesXML($cheminFichier) {
    // *** Implémentez la logique pour lire un fichier XML de factures ***
    // *** Retournez un tableau de tableaux associatifs représentant les factures ***
    $factures = [];
    if (file_exists($cheminFichier)) {
        if ($xml = simplexml_load_file($cheminFichier)) {
            foreach ($xml->facture as $factureXml) {
                $facture = [];
                if (isset($factureXml->numero_facture)) $facture['numero_facture'] = (string) $factureXml->numero_facture;
                if (isset($factureXml->date_emission)) $facture['date_emission'] = (string) $factureXml->date_emission;
                if (isset($factureXml->montant_ht)) $facture['montant_ht'] = (float) $factureXml->montant_ht;
                if (isset($factureXml->taux_tva)) $facture['taux_tva'] = (float) $factureXml->taux_tva;
                if (isset($factureXml->montant_ttc)) $facture['montant_ttc'] = (float) $factureXml->montant_ttc;
                if (isset($factureXml->id_client)) $facture['id_client'] = (int) $factureXml->id_client;
                // ... autres champs
                if (!empty($facture)) {
                    $factures[] = $facture;
                }
            }
        } else {
            error_log("Erreur lors de la lecture du fichier XML : " . $cheminFichier);
        }
    }
    return $factures;
}

function lireFacturesTXT($cheminFichier) {
    // *** Implémentez la logique pour lire un fichier TXT de factures ***
    // *** Le format TXT peut varier considérablement, vous devrez l'adapter à votre structure ***
    // *** Retournez un tableau de tableaux associatifs représentant les factures ***
    $factures = [];
    if (($handle = fopen($cheminFichier, "r")) !== false) {
        while (($line = fgets($handle)) !== false) {
            // *** Exemple très basique : supposons les valeurs séparées par des virgules ***
            $data = explode(',', trim($line));
            if (count($data) >= 3) { // Exemple : numéro, date, montant
                $factures[] = [
                    'numero_facture' => trim($data[0]),
                    'date_emission' => trim($data[1]),
                    'montant_ttc' => floatval(trim($data[2])),
                    // ... autres champs potentiels
                ];
            } else {
                error_log("Ligne TXT ignorée (nombre de champs incorrect) : " . trim($line) . " dans " . $cheminFichier);
            }
        }
        fclose($handle);
    }
    return $factures;
}

function ajouterFacture($db, $dataFacture) {
    // *** Implémentez la logique pour insérer une facture dans votre table de factures ***
    // *** Utilisez $db (l'objet PDO) pour exécuter la requête INSERT ***
    // *** Assurez-vous de bien mapper les clés de $dataFacture aux colonnes de votre table ***
    try {
        $sql = "INSERT INTO Factures (
            numero_facture,
            date_emission,
            montant_ht,
            taux_tva,
            montant_ttc,
            id_client,
            // ... autres colonnes
            date_creation
        ) VALUES (
            :numero_facture,
            :date_emission,
            :montant_ht,
            :taux_tva,
            :montant_ttc,
            :id_client,
            // ... autres valeurs
            NOW()
        )";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':numero_facture', $dataFacture['numero_facture']);
        $stmt->bindParam(':date_emission', $dataFacture['date_emission']);
        $stmt->bindParam(':montant_ht', $dataFacture['montant_ht'] ?? 0); // Valeur par défaut si non défini
        $stmt->bindParam(':taux_tva', $dataFacture['taux_tva'] ?? 0);
        $stmt->bindParam(':montant_ttc', $dataFacture['montant_ttc']);
        $stmt->bindParam(':id_client', $dataFacture['id_client']);
        // ... liez les autres paramètres

        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout de la facture " . ($dataFacture['numero_facture'] ?? 'N/A') . " : " . $e->getMessage());
        return false;
    }
}
?>
<?php
session_start();
// Check user authentication and permissions
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_import_statements.php'; // Your import functions
require_once '../../fonctions/gestion_ecritures.php'; // For any entry creation logic, if needed directly

$titre = "Validation des écritures importées";
$message = '';
$suggestedEntries = [];
$uploadedFilePath = $_SESSION['temp_uploaded_file'] ?? null;
$uploadedFileType = $_SESSION['temp_file_type'] ?? null;
$bankAccountId = $_SESSION['temp_bank_account_id'] ?? null;

$pdo = getPDO(); // Assuming getPDO() is in database.php

// Ensure file path and bank account ID are present
if (!$uploadedFilePath || !$bankAccountId) {
    $message = '<div class="alert alert-danger">Aucun fichier à traiter ou compte bancaire non spécifié. Veuillez retourner à la page d\'importation.</div>';
} else {
    // Determine parser based on file type
    if ($uploadedFileType === 'csv') {
        $parsedTransactions = parseCsvBankStatement($uploadedFilePath, $bankAccountId);
    } elseif (in_array($uploadedFileType, ['ofx', 'qfx'])) {
        $parsedTransactions = parseOfxBankStatement($uploadedFilePath, $bankAccountId);
    } elseif ($uploadedFileType === 'txt' && /* check if it's MT940 */ true) { // More robust check for MT940 required
        $parsedTransactions = parseMt940BankStatement($uploadedFilePath, $bankAccountId);
    } else {
        $message = '<div class="alert alert-warning">Type de fichier non supporté pour le traitement.</div>';
        $parsedTransactions = [];
    }

    if (!empty($parsedTransactions)) {
        $suggestedEntries = suggestAccountingEntries($parsedTransactions, $pdo);
        if (empty($suggestedEntries)) {
            $message = '<div class="alert alert-info">Aucune écriture suggérée à partir du relevé.</div>';
        }
    } else {
        $message = '<div class="alert alert-warning">Aucune transaction valide n\'a pu être extraite du fichier.</div>';
    }
}

// Handle confirmation POST (if the user clicks "Confirmer et Enregistrer")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    $journalId = 1; // Example: Assuming journal ID 1 is for 'Bank Operations'
    $userId = $_SESSION['user_id'] ?? 1; // Get current user ID, default to 1 for example

    // In a real application, you'd re-process the POSTed data to get the final entries
    // or pass the original $suggestedEntries array through a hidden field or session.
    // For simplicity, we'll re-use the $suggestedEntries from the initial parse.
    // **IMPORTANT:** For production, POSTed data should be validated and sanitized.
    $saveResults = saveSuggestedEntries($suggestedEntries, $pdo, $journalId, $userId);

    // Clean up temporary file and session data after processing
    if (file_exists($uploadedFilePath)) {
        unlink($uploadedFilePath);
    }
    unset($_SESSION['temp_uploaded_file']);
    unset($_SESSION['temp_file_type']);
    unset($_SESSION['temp_bank_account_id']);

    $_SESSION['import_feedback'] = $saveResults; // Store feedback for redirect
    header('Location: confirmation.php'); // Redirect to a confirmation page
    exit;
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
</head>
<body>
    <div class="container">
        <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

        <?= $message ?>

        <?php if (!empty($suggestedEntries)): ?>
            <p>Voici les écritures suggérées à partir de votre relevé bancaire. Veuillez les vérifier avant de les confirmer.</p>
            <form action="traitement.php" method="POST">
                <input type="hidden" name="confirm_import" value="1">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Montant</th>
                            <th>Compte Débit suggéré</th>
                            <th>Compte Crédit suggéré</th>
                            </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suggestedEntries as $entry): ?>
                            <tr>
                                <td><?= htmlspecialchars($entry['date_saisie']) ?></td>
                                <td><?= htmlspecialchars($entry['description']) ?></td>
                                <td><?= htmlspecialchars(number_format($entry['montant_total'], 2, ',', ' ')) ?></td>
                                <td>
                                    <?php foreach ($entry['lignes_ecriture'] as $ligne): ?>
                                        <?php if ($ligne['sens'] === 'D'): ?>
                                            <?= htmlspecialchars($ligne['compte']['numero'] ?? 'N/A') ?> - <?= htmlspecialchars($ligne['compte']['nom'] ?? 'À définir') ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <?php foreach ($entry['lignes_ecriture'] as $ligne): ?>
                                        <?php if ($ligne['sens'] === 'C'): ?>
                                            <?= htmlspecialchars($ligne['compte']['numero'] ?? 'N/A') ?> - <?= htmlspecialchars($ligne['compte']['nom'] ?? 'À définir') ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <button type="submit" class="btn btn-success">Confirmer et Enregistrer les écritures</button>
                <a href="index.php" class="btn btn-default">Annuler</a>
            </form>
        <?php elseif (!$message): ?>
            <div class="alert alert-info">Veuillez importer un fichier depuis la page précédente.</div>
            <a href="index.php" class="btn btn-primary">Retourner à l'importation</a>
        <?php endif; ?>

    </div>
    <?php require_once('../../templates/footer.php'); ?>
</body>
</html>