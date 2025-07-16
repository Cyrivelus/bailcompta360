<?php
session_start();

// Check user authentication and authorization
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') { // Only admins can manage recurring entries
    header('Location: ../../login.php');
    exit;
}

require_once '../../templates/header.php';
require_once '../../templates/navigation.php';
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_logs.php'; // For logging

$titre = 'Gérer les Écritures Récurrentes';
$pdo = getPDO();

$message = '';
$messageType = ''; // 'success', 'danger', 'info', 'warning'

$editMode = false;
$recurringEntryData = [];
$recurringEntryLines = [];

// --- Fetch necessary dropdown data ---
$comptesCompta = [];
try {
    $stmt = $pdo->query("SELECT ID_Compte, Libelle_Compte FROM Comptes_compta ORDER BY Libelle_Compte ASC");
    $comptesCompta = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logApplicationError("Erreur PDO lors de la récupération des comptes comptables: " . $e->getMessage());
    $message = "Erreur lors du chargement des comptes. Impossible de gérer les écritures récurrentes.";
    $messageType = 'danger';
}

$journals = [];
try {
    $stmt = $pdo->query("SELECT Cde, Nom_Journal FROM JAL ORDER BY Nom_Journal ASC");
    $journals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logApplicationError("Erreur PDO lors de la récupération des journaux: " . $e->getMessage());
    $message = "Erreur lors du chargement des journaux. Impossible de gérer les écritures récurrentes.";
    $messageType = 'danger';
}

// --- Handle Add/Edit Form Submission ---
if (isset($_POST['submit_recurring_entry'])) {
    $idRecurringEcriture = $_POST['id_recurring_ecriture'] ?? null;
    $libelle = trim($_POST['libelle'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $montantTotal = filter_var($_POST['montant_total'] ?? 0, FILTER_VALIDATE_FLOAT);
    $sensPrincipal = $_POST['sens_principal'] ?? '';
    $idComptePrincipal = $_POST['id_compte_principal'] ?? null;
    $idJournal = $_POST['id_journal'] ?? null;
    $frequence = $_POST['frequence'] ?? '';
    $jourDuMois = $_POST['jour_du_mois'] ?? null;
    $dateDebut = $_POST['date_debut'] ?? '';
    $dateFin = $_POST['date_fin'] ?? null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $lines = $_POST['lines'] ?? []; // Array of line data

    // Basic validation
    if (empty($libelle) || $montantTotal <= 0 || !in_array($sensPrincipal, ['D', 'C']) || empty($idComptePrincipal) ||
        empty($idJournal) || !in_array($frequence, ['monthly', 'quarterly', 'annually']) || empty($dateDebut)) {
        $message = "Veuillez remplir tous les champs obligatoires correctement.";
        $messageType = 'danger';
    } elseif ($frequence === 'monthly' && (empty($jourDuMois) || $jourDuMois < 1 || $jourDuMois > 31)) {
        $message = "Pour une fréquence mensuelle, le 'Jour du mois' est requis et doit être entre 1 et 31.";
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();

            if ($idRecurringEcriture) { // Update existing
                $stmt = $pdo->prepare("
                    UPDATE Recurring_Ecritures
                    SET Libelle = :libelle, Description_Entree = :description, Montant_Total = :montant_total,
                        Sens_Principal = :sens_principal, ID_Compte_Principal = :id_compte_principal,
                        ID_Journal = :id_journal, Frequence = :frequence, Jour_du_Mois = :jour_du_mois,
                        Date_Debut = :date_debut, Date_Fin = :date_fin, is_active = :is_active
                    WHERE ID_Recurring_Ecriture = :id_recurring_ecriture
                ");
                $stmt->bindParam(':id_recurring_ecriture', $idRecurringEcriture, PDO::PARAM_INT);
                $logAction = "Modification de l'écriture récurrente ID: {$idRecurringEcriture}";
            } else { // Insert new
                $stmt = $pdo->prepare("
                    INSERT INTO Recurring_Ecritures
                    (Libelle, Description_Entree, Montant_Total, Sens_Principal, ID_Compte_Principal, ID_Journal,
                    Frequence, Jour_du_Mois, Date_Debut, Date_Fin, Created_By, is_active)
                    VALUES (:libelle, :description, :montant_total, :sens_principal, :id_compte_principal, :id_journal,
                    :frequence, :jour_du_mois, :date_debut, :date_fin, :created_by, :is_active)
                ");
                $stmt->bindParam(':created_by', $_SESSION['user_id'], PDO::PARAM_INT);
                $logAction = "Création d'une nouvelle écriture récurrente: {$libelle}";
            }

            $stmt->bindParam(':libelle', $libelle);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':montant_total', $montantTotal);
            $stmt->bindParam(':sens_principal', $sensPrincipal);
            $stmt->bindParam(':id_compte_principal', $idComptePrincipal, PDO::PARAM_INT);
            $stmt->bindParam(':id_journal', $idJournal, PDO::PARAM_INT);
            $stmt->bindParam(':frequence', $frequence);
            $stmt->bindParam(':jour_du_mois', $jourDuMois, PDO::PARAM_INT);
            $stmt->bindParam(':date_debut', $dateDebut);
            $stmt->bindParam(':date_fin', $dateFin);
            $stmt->bindParam(':is_active', $isActive, PDO::PARAM_BOOL);
            $stmt->execute();

            if (!$idRecurringEcriture) {
                $idRecurringEcriture = $pdo->lastInsertId();
            }

            // --- Handle Lines ---
            // First, delete existing lines for update operation
            $deleteLinesStmt = $pdo->prepare("DELETE FROM Recurring_Ecriture_Lignes WHERE ID_Recurring_Ecriture = :id_recurring_ecriture");
            $deleteLinesStmt->bindParam(':id_recurring_ecriture', $idRecurringEcriture, PDO::PARAM_INT);
            $deleteLinesStmt->execute();

            $totalLineAmount = 0; // To check if sum of lines matches Montant_Total

            foreach ($lines as $line) {
                if (empty($line['id_compte_ligne']) || empty($line['montant_ligne']) || !in_array($line['sens_ligne'], ['D', 'C'])) {
                    throw new Exception("Ligne de détail invalide détectée.");
                }

                $montantLigne = filter_var($line['montant_ligne'], FILTER_VALIDATE_FLOAT);
                if ($montantLigne <= 0) {
                     throw new Exception("Montant de ligne invalide (doit être positif).");
                }
                $totalLineAmount += $montantLigne;

                $stmtLine = $pdo->prepare("
                    INSERT INTO Recurring_Ecriture_Lignes
                    (ID_Recurring_Ecriture, ID_Compte, Montant, Sens, Libelle_Ligne)
                    VALUES (:id_recurring_ecriture, :id_compte, :montant, :sens, :libelle_ligne)
                ");
                $stmtLine->execute([
                    ':id_recurring_ecriture' => $idRecurringEcriture,
                    ':id_compte' => $line['id_compte_ligne'],
                    ':montant' => $montantLigne,
                    ':sens' => $line['sens_ligne'],
                    ':libelle_ligne' => trim($line['libelle_ligne'] ?? '')
                ]);
            }

            // Basic check if sum of line amounts roughly matches total amount (optional, but good for validation)
            if (abs($totalLineAmount - $montantTotal) > 0.01) { // Allow for float precision
                throw new Exception("La somme des montants des lignes ne correspond pas au montant total de l'écriture récurrente.");
            }


            $pdo->commit();
            $message = "Écriture récurrente enregistrée avec succès.";
            $messageType = 'success';
            logUserActivity($logAction . " par l'utilisateur ID: " . $_SESSION['user_id'] . ".");

            // Clear POST to prevent resubmission and redirect for clean state
            unset($_POST);
            header("Location: recurring_entries.php?status=success&msg=" . urlencode($message));
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Erreur de base de données lors de l'enregistrement de l'écriture récurrente: " . $e->getMessage();
            $messageType = 'danger';
            logApplicationError("Erreur PDO lors de la gestion des écritures récurrentes par l'utilisateur ID: " . $_SESSION['user_id'] . ". Erreur: " . $e->getMessage());
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Erreur: " . $e->getMessage();
            $messageType = 'danger';
            logApplicationError("Erreur logique lors de la gestion des écritures récurrentes par l'utilisateur ID: " . $_SESSION['user_id'] . ". Erreur: " . $e->getMessage());
        }
    }
}

// --- Handle Delete Action ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $idToDelete = (int)$_GET['id'];
    try {
        $pdo->beginTransaction();
        // Delete lines first (due to foreign key)
        $stmtLines = $pdo->prepare("DELETE FROM Recurring_Ecriture_Lignes WHERE ID_Recurring_Ecriture = :id");
        $stmtLines->bindParam(':id', $idToDelete, PDO::PARAM_INT);
        $stmtLines->execute();

        $stmt = $pdo->prepare("DELETE FROM Recurring_Ecritures WHERE ID_Recurring_Ecriture = :id");
        $stmt->bindParam(':id', $idToDelete, PDO::PARAM_INT);
        $stmt->execute();
        $pdo->commit();

        $message = "Écriture récurrente supprimée avec succès.";
        $messageType = 'success';
        logUserActivity("Suppression de l'écriture récurrente ID: {$idToDelete} par l'utilisateur ID: " . $_SESSION['user_id'] . ".");

        header("Location: recurring_entries.php?status=success&msg=" . urlencode($message));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Erreur lors de la suppression: " . $e->getMessage();
        $messageType = 'danger';
        logApplicationError("Erreur PDO lors de la suppression de l'écriture récurrente ID: {$idToDelete} par l'utilisateur ID: " . $_SESSION['user_id'] . ". Erreur: " . $e->getMessage());
    }
}

// --- Load data for editing ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editMode = true;
    $idToEdit = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM Recurring_Ecritures WHERE ID_Recurring_Ecriture = :id");
        $stmt->bindParam(':id', $idToEdit, PDO::PARAM_INT);
        $stmt->execute();
        $recurringEntryData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$recurringEntryData) {
            $message = "Écriture récurrente non trouvée.";
            $messageType = 'danger';
            $editMode = false;
        } else {
            // Fetch associated lines
            $stmtLines = $pdo->prepare("SELECT * FROM Recurring_Ecriture_Lignes WHERE ID_Recurring_Ecriture = :id ORDER BY ID_Recurring_Ligne ASC");
            $stmtLines->bindParam(':id', $idToEdit, PDO::PARAM_INT);
            $stmtLines->execute();
            $recurringEntryLines = $stmtLines->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        logApplicationError("Erreur PDO lors du chargement de l'écriture récurrente pour édition ID: {$idToEdit}. Erreur: " . $e->getMessage());
        $message = "Erreur lors du chargement de l'écriture pour édition: " . $e->getMessage();
        $messageType = 'danger';
        $editMode = false;
    }
}

// Display messages from redirect (after successful submission/deletion)
if (isset($_GET['status']) && isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
    $messageType = htmlspecialchars($_GET['status']);
}

// --- Fetch all recurring entries for display ---
$allRecurringEntries = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            re.*,
            cc.Libelle_Compte AS Compte_Principal_Libelle,
            jal.Nom_Journal
        FROM
            Recurring_Ecritures re
        JOIN
            Comptes_compta cc ON re.ID_Compte_Principal = cc.ID_Compte
        LEFT JOIN
            JAL jal ON re.ID_Journal = jal.Cde
        ORDER BY re.Libelle ASC
    ");
    $stmt->execute();
    $allRecurringEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logApplicationError("Erreur PDO lors de la récupération de toutes les écritures récurrentes: " . $e->getMessage());
    $message = "Erreur lors du chargement des écritures récurrentes.";
    $messageType = 'danger';
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
        .recurring-table th, .recurring-table td { vertical-align: middle; font-size: 0.9em; }
        .line-item-container {
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 10px;
            background-color: #f9f9f9;
        }
        .line-item-container:last-child { margin-bottom: 0; }
        .remove-line-btn { margin-top: 25px; }
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

        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title"><?= $editMode ? 'Modifier' : 'Ajouter' ?> une Écriture Récurrente</h3>
            </div>
            <div class="panel-body">
                <form action="" method="POST">
                    <?php if ($editMode): ?>
                        <input type="hidden" name="id_recurring_ecriture" value="<?= htmlspecialchars($recurringEntryData['ID_Recurring_Ecriture']) ?>">
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="libelle">Libellé de l'Écriture Récurrente :</label>
                                <input type="text" class="form-control" id="libelle" name="libelle" value="<?= htmlspecialchars($recurringEntryData['Libelle'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="description">Description (pour l'écriture principale) :</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($recurringEntryData['Description_Entree'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="montant_total">Montant Total :</label>
                                <input type="number" step="0.01" class="form-control" id="montant_total" name="montant_total" value="<?= htmlspecialchars($recurringEntryData['Montant_Total'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="sens_principal">Sens du Compte Principal :</label>
                                <select name="sens_principal" id="sens_principal" class="form-control" required>
                                    <option value="">-- Choisir --</option>
                                    <option value="D" <?= (isset($recurringEntryData['Sens_Principal']) && $recurringEntryData['Sens_Principal'] === 'D') ? 'selected' : '' ?>>Débit</option>
                                    <option value="C" <?= (isset($recurringEntryData['Sens_Principal']) && $recurringEntryData['Sens_Principal'] === 'C') ? 'selected' : '' ?>>Crédit</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="id_compte_principal">Compte Principal (Bancaire / Caisse) :</label>
                                <select name="id_compte_principal" id="id_compte_principal" class="form-control" required>
                                    <option value="">-- Sélectionner un compte --</option>
                                    <?php foreach ($comptesCompta as $compte): ?>
                                        <option value="<?= htmlspecialchars($compte['ID_Compte']) ?>"
                                            <?= (isset($recurringEntryData['ID_Compte_Principal']) && $recurringEntryData['ID_Compte_Principal'] == $compte['ID_Compte']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($compte['Libelle_Compte']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="help-block">Le compte bancaire ou de caisse affecté par cette transaction.</p>
                            </div>
                            <div class="form-group">
                                <label for="id_journal">Journal Comptable :</label>
                                <select name="id_journal" id="id_journal" class="form-control" required>
                                    <option value="">-- Sélectionner un journal --</option>
                                    <?php foreach ($journals as $journal): ?>
                                        <option value="<?= htmlspecialchars($journal['Cde']) ?>"
                                            <?= (isset($recurringEntryData['ID_Journal']) && $recurringEntryData['ID_Journal'] == $journal['Cde']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($journal['Nom_Journal']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="frequence">Fréquence :</label>
                                <select name="frequence" id="frequence" class="form-control" required>
                                    <option value="">-- Choisir une fréquence --</option>
                                    <option value="monthly" <?= (isset($recurringEntryData['Frequence']) && $recurringEntryData['Frequence'] === 'monthly') ? 'selected' : '' ?>>Mensuelle</option>
                                    <option value="quarterly" <?= (isset($recurringEntryData['Frequence']) && $recurringEntryData['Frequence'] === 'quarterly') ? 'selected' : '' ?>>Trimestrielle</option>
                                    <option value="annually" <?= (isset($recurringEntryData['Frequence']) && $recurringEntryData['Frequence'] === 'annually') ? 'selected' : '' ?>>Annuelle</option>
                                </select>
                            </div>
                            <div class="form-group" id="jour_du_mois_group" style="<?= (isset($recurringEntryData['Frequence']) && $recurringEntryData['Frequence'] === 'monthly') ? '' : 'display:none;' ?>">
                                <label for="jour_du_mois">Jour du mois (1-31) :</label>
                                <input type="number" class="form-control" id="jour_du_mois" name="jour_du_mois" min="1" max="31" value="<?= htmlspecialchars($recurringEntryData['Jour_du_Mois'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="date_debut">Date de Début :</label>
                                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?= htmlspecialchars($recurringEntryData['Date_Debut'] ?? date('Y-m-d')) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="date_fin">Date de Fin (Optionnel) :</label>
                                <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?= htmlspecialchars($recurringEntryData['Date_Fin'] ?? '') ?>">
                            </div>
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="is_active" value="1" <?= (isset($recurringEntryData['is_active']) && $recurringEntryData['is_active']) || !isset($recurringEntryData['is_active']) ? 'checked' : '' ?>> Actif
                                </label>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <h4>Détails des Lignes d'Écriture (Lignes de Contrepartie)</h4>
                    <div id="recurring_lines_container">
                        <?php if (empty($recurringEntryLines)): ?>
                            <div class="row line-item-container">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Compte :</label>
                                        <select name="lines[0][id_compte_ligne]" class="form-control line-compte" required>
                                            <option value="">-- Sélectionner un compte --</option>
                                            <?php foreach ($comptesCompta as $compte): ?>
                                                <option value="<?= htmlspecialchars($compte['ID_Compte']) ?>">
                                                    <?= htmlspecialchars($compte['Libelle_Compte']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Montant :</label>
                                        <input type="number" step="0.01" name="lines[0][montant_ligne]" class="form-control line-montant" required>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Sens :</label>
                                        <select name="lines[0][sens_ligne]" class="form-control line-sens" required>
                                            <option value="">--</option>
                                            <option value="D">D</option>
                                            <option value="C">C</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Libellé Ligne :</label>
                                        <input type="text" name="lines[0][libelle_ligne]" class="form-control line-libelle">
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-danger remove-line-btn">X</button>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recurringEntryLines as $index => $line): ?>
                                <div class="row line-item-container">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Compte :</label>
                                            <select name="lines[<?= $index ?>][id_compte_ligne]" class="form-control line-compte" required>
                                                <option value="">-- Sélectionner un compte --</option>
                                                <?php foreach ($comptesCompta as $compte): ?>
                                                    <option value="<?= htmlspecialchars($compte['ID_Compte']) ?>"
                                                        <?= ($line['ID_Compte'] == $compte['ID_Compte']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($compte['Libelle_Compte']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Montant :</label>
                                            <input type="number" step="0.01" name="lines[<?= $index ?>][montant_ligne]" class="form-control line-montant" value="<?= htmlspecialchars($line['Montant']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Sens :</label>
                                            <select name="lines[<?= $index ?>][sens_ligne]" class="form-control line-sens" required>
                                                <option value="">--</option>
                                                <option value="D" <?= ($line['Sens'] === 'D') ? 'selected' : '' ?>>D</option>
                                                <option value="C" <?= ($line['Sens'] === 'C') ? 'selected' : '' ?>>C</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Libellé Ligne :</label>
                                            <input type="text" name="lines[<?= $index ?>][libelle_ligne]" class="form-control line-libelle" value="<?= htmlspecialchars($line['Libelle_Ligne']) ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-danger remove-line-btn">X</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-info mt-2" id="add_line_btn">
                        <span class="glyphicon glyphicon-plus"></span> Ajouter une Ligne
                    </button>

                    <hr>
                    <button type="submit" name="submit_recurring_entry" class="btn btn-success btn-lg">
                        <span class="glyphicon glyphicon-floppy-disk"></span> Enregistrer l'Écriture Récurrente
                    </button>
                    <?php if ($editMode): ?>
                        <a href="recurring_entries.php" class="btn btn-default ml-2">Annuler l'édition</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title">Liste des Écritures Récurrentes</h3>
            </div>
            <div class="panel-body">
                <?php if (empty($allRecurringEntries)): ?>
                    <div class="alert alert-info">Aucune écriture récurrente n'est définie pour le moment.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped recurring-table">
                            <thead>
                                <tr>
                                    <th>Libellé</th>
                                    <th>Montant Total</th>
                                    <th>Compte Principal</th>
                                    <th>Journal</th>
                                    <th>Fréquence</th>
                                    <th>Jour du Mois</th>
                                    <th>Début</th>
                                    <th>Fin</th>
                                    <th>Dernière Saisie</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allRecurringEntries as $entry): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($entry['Libelle']) ?></td>
                                        <td><?= htmlspecialchars(number_format($entry['Montant_Total'], 2, ',', ' ')) ?></td>
                                        <td><?= htmlspecialchars($entry['Compte_Principal_Libelle']) ?> (<?= htmlspecialchars($entry['Sens_Principal']) ?>)</td>
                                        <td><?= htmlspecialchars($entry['Nom_Journal'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($entry['Frequence']) ?></td>
                                        <td><?= htmlspecialchars($entry['Jour_du_Mois'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($entry['Date_Debut']))) ?></td>
                                        <td><?= $entry['Date_Fin'] ? htmlspecialchars(date('d/m/Y', strtotime($entry['Date_Fin']))) : 'Indéfinie' ?></td>
                                        <td><?= $entry['Last_Posted_Date'] ? htmlspecialchars(date('d/m/Y', strtotime($entry['Last_Posted_Date']))) : 'Jamais' ?></td>
                                        <td>
                                            <span class="label label-<?= $entry['is_active'] ? 'success' : 'warning' ?>">
                                                <?= $entry['is_active'] ? 'Actif' : 'Inactif' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?action=edit&id=<?= htmlspecialchars($entry['ID_Recurring_Ecriture']) ?>" class="btn btn-xs btn-warning" title="Modifier">
                                                <span class="glyphicon glyphicon-edit"></span>
                                            </a>
                                            <a href="?action=delete&id=<?= htmlspecialchars($entry['ID_Recurring_Ecriture']) ?>" class="btn btn-xs btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette écriture récurrente ?')" title="Supprimer">
                                                <span class="glyphicon glyphicon-trash"></span>
                                            </a>
                                            </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
    <script>
        $(document).ready(function() {
            // Toggle Jour du mois field based on frequency
            $('#frequence').change(function() {
                if ($(this).val() === 'monthly') {
                    $('#jour_du_mois_group').slideDown();
                    $('#jour_du_mois').prop('required', true);
                } else {
                    $('#jour_du_mois_group').slideUp();
                    $('#jour_du_mois').prop('required', false);
                }
            });

            // Add new line item
            var lineIndex = <?= count($recurringEntryLines) > 0 ? count($recurringEntryLines) : 1 ?>;
            $('#add_line_btn').click(function() {
                var newLine = `
                    <div class="row line-item-container">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Compte :</label>
                                <select name="lines[${lineIndex}][id_compte_ligne]" class="form-control line-compte" required>
                                    <option value="">-- Sélectionner un compte --</option>
                                    <?php foreach ($comptesCompta as $compte): ?>
                                        <option value="<?= htmlspecialchars($compte['ID_Compte']) ?>">
                                            <?= htmlspecialchars($compte['Libelle_Compte']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Montant :</label>
                                <input type="number" step="0.01" name="lines[${lineIndex}][montant_ligne]" class="form-control line-montant" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Sens :</label>
                                <select name="lines[${lineIndex}][sens_ligne]" class="form-control line-sens" required>
                                    <option value="">--</option>
                                    <option value="D">D</option>
                                    <option value="C">C</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Libellé Ligne :</label>
                                <input type="text" name="lines[${lineIndex}][libelle_ligne]" class="form-control line-libelle">
                            </div>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-danger remove-line-btn">X</button>
                        </div>
                    </div>
                `;
                $('#recurring_lines_container').append(newLine);
                lineIndex++;
            });

            // Remove line item
            $(document).on('click', '.remove-line-btn', function() {
                if ($('#recurring_lines_container .line-item-container').length > 1) { // Ensure at least one line remains
                    $(this).closest('.line-item-container').remove();
                } else {
                    alert('Au moins une ligne de détail est requise.');
                }
            });
        });
    </script>
    <?php require_once '../../templates/footer.php'; ?>
</body>
</html>