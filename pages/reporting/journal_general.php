<?php
session_start();

// Check user authentication and authorization
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../login.php');
    exit;
}

require_once '../../templates/header.php';
require_once '../../templates/navigation.php';
require_once '../../fonctions/database.php'; // Assurez-vous que cette fonction connecte et retourne $pdo
require_once '../../fonctions/gestion_logs.php'; // For logging

$titre = 'Journal Général';

$message = '';
$messageType = '';

// 🔌 CONNEXION DB
if (!isset($pdo) || !$pdo instanceof PDO) {
    if (function_exists('getPdoConnection')) {
        $pdo = getPdoConnection();
    }
    if (!$pdo || !$pdo instanceof PDO) {
        header('Location: ../../index.php?error=' . urlencode('Erreur de configuration du serveur: connexion DB manquante.'));
        exit();
    }
}

// Default filter values. Ensure proper default dates.
// Use filter_input for safer POST data access.
// Deprecated FILTER_SANITIZE_STRING is replaced by FILTER_UNSAFE_RAW
$startDate = filter_input(INPUT_POST, 'start_date', FILTER_UNSAFE_RAW) ?: date('Y-01-01');
$endDate = filter_input(INPUT_POST, 'end_date', FILTER_UNSAFE_RAW) ?: date('Y-12-31');
$selectedJournal = filter_input(INPUT_POST, 'id_journal', FILTER_SANITIZE_NUMBER_INT);
$selectedAccount = filter_input(INPUT_POST, 'id_compte', FILTER_SANITIZE_NUMBER_INT);
$minAmount = filter_input(INPUT_POST, 'min_amount', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
$maxAmount = filter_input(INPUT_POST, 'max_amount', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
$descriptionKeyword = filter_input(INPUT_POST, 'description_keyword', FILTER_UNSAFE_RAW);
$numeroPiece = filter_input(INPUT_POST, 'numero_piece', FILTER_UNSAFE_RAW);


// --- Get list of all Journals for filter dropdown ---
$journals = [];
try {
    $stmt = $pdo->query("SELECT Cde, Lib AS Nom_Journal FROM JAL ORDER BY Lib ASC");
    $journals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logApplicationError("Erreur PDO lors de la récupération des journaux pour le filtre: " . $e->getMessage());
    $message = "Erreur lors du chargement des journaux.";
    $messageType = 'danger';
}

// Get list of all Accounts for filter dropdown
$comptes = [];
try {
    $stmt = $pdo->query("SELECT ID_Compte, Numero_Compte, Nom_Compte FROM Comptes_compta ORDER BY Numero_Compte ASC");
    $comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logApplicationError("Erreur PDO lors de la récupération des comptes pour le filtre: " . $e->getMessage());
    $message = "Erreur lors du chargement des comptes.";
    $messageType = 'danger';
}

$journalEntries = [];
$initialBalanceDebit = 0;
$initialBalanceCredit = 0;
$finalBalanceDebit = 0;
$finalBalanceCredit = 0;

// Only attempt to fetch data if the form has been submitted or it's initial load with defaults
if (isset($_POST['generate_report']) || (!isset($_POST['generate_report']) && empty($messageType))) {

    // --- Validate and Format Dates Explicitly ---
    $dateStartObj = DateTime::createFromFormat('Y-m-d', $startDate);
    $dateEndObj = DateTime::createFromFormat('Y-m-d', $endDate);

    if (!$dateStartObj || $dateStartObj->format('Y-m-d') !== $startDate ||
        !$dateEndObj || $dateEndObj->format('Y-m-d') !== $endDate) {
        $message = "Veuillez saisir des dates valides (AAAA-MM-JJ).";
        $messageType = 'danger';
    } elseif ($dateStartObj > $dateEndObj) {
        $message = "La date de début ne peut pas être postérieure à la date de fin.";
        $messageType = 'danger';
    } else {
        // Use the validated and formatted dates for queries
        $formattedStartDate = $dateStartObj->format('Y-m-d');
        $formattedEndDate = $dateEndObj->format('Y-m-d');

        try {
            // --- Calculate Initial Balances (Première Borne) ---
            // Use CAST for MariaDB/MySQL. Ensure 'Montant' is numeric or clean it beforehand.
            $sqlInitialBalance = "
    SELECT
        SUM(CASE WHEN le.Sens = 'D' THEN CAST(le.Montant AS DECIMAL(18, 2)) ELSE 0 END) AS TotalDebit,
        SUM(CASE WHEN le.Sens = 'C' THEN CAST(le.Montant AS DECIMAL(18, 2)) ELSE 0 END) AS TotalCredit
    FROM Ecritures e
    JOIN Lignes_Ecritures le ON e.ID_Ecriture = le.ID_Ecriture
    WHERE
        e.Date_Saisie < :start_date
";


            $paramsInitialBalance = [':start_date' => $formattedStartDate]; // Use formatted date here

            if ($selectedAccount !== null && is_numeric($selectedAccount)) { // Check for null before is_numeric
                $sqlInitialBalance .= " AND le.ID_Compte = :id_compte";
                $paramsInitialBalance[':id_compte'] = $selectedAccount;
            }

            $stmtInitialBalance = $pdo->prepare($sqlInitialBalance);
            $stmtInitialBalance->execute($paramsInitialBalance);
            $initialBalances = $stmtInitialBalance->fetch(PDO::FETCH_ASSOC);
            $initialBalanceDebit = (float)($initialBalances['TotalDebit'] ?? 0);
            $initialBalanceCredit = (float)($initialBalances['TotalCredit'] ?? 0);


            // --- Fetch Journal Entries for the selected period ---
            // Use CAST for MariaDB/MySQL.
            $sql = "
    SELECT
        e.ID_Ecriture,
        e.Date_Saisie,
        e.Description AS Ecriture_Description,
        e.Montant_Total,
        e.Numero_Piece,
        e.NomUtilisateur,
        jal.Lib AS Nom_Journal,
        le.ID_Ligne,
        CAST(le.Montant AS DECIMAL(18, 2)) AS Montant, -- Use CAST here
        le.Sens,
        le.Libelle_Ligne,
        cc.Numero_Compte,
        cc.Nom_Compte
    FROM
        Ecritures e
    JOIN
        Lignes_Ecritures le ON e.ID_Ecriture = le.ID_Ecriture
    LEFT JOIN
        JAL jal ON e.Cde = jal.Cde
    LEFT JOIN
        Comptes_compta cc ON le.ID_Compte = cc.ID_Compte
    WHERE
        e.Date_Saisie BETWEEN :start_date AND :end_date
";


            $params = [
                ':start_date' => $formattedStartDate, // Use formatted date here
                ':end_date' => $formattedEndDate      // Use formatted date here
            ];

            // Add filters
            if ($selectedJournal !== null && is_numeric($selectedJournal)) {
                $sql .= " AND e.Cde = :id_journal";
                $params[':id_journal'] = $selectedJournal;
            }
            if ($selectedAccount !== null && is_numeric($selectedAccount)) {
                $sql .= " AND le.ID_Compte = :id_compte";
                $params[':id_compte'] = $selectedAccount;
            }
            if ($minAmount !== null) { // Check for null instead of empty string
                $sql .= " AND CAST(le.Montant AS DECIMAL(18, 2)) IS NOT NULL AND CAST(le.Montant AS DECIMAL(18, 2)) >= :min_amount"; // Explicit cast here
                $params[':min_amount'] = $minAmount; // Already float
            }
            if ($maxAmount !== null) { // Check for null instead of empty string
                $sql .= " AND CAST(le.Montant AS DECIMAL(18, 2)) <= :max_amount"; // Explicit cast here
                $params[':max_amount'] = $maxAmount; // Already float
            }
            if (!empty($descriptionKeyword)) {
                $sql .= " AND (e.Description LIKE :keyword OR le.Libelle_Ligne LIKE :keyword)";
                $params[':keyword'] = '%' . $descriptionKeyword . '%';
            }
            if (!empty($numeroPiece)) {
                $sql .= " AND e.Numero_Piece = :numero_piece";
                $params[':numero_piece'] = $numeroPiece;
            }

            $sql .= " ORDER BY e.Date_Saisie ASC, e.ID_Ecriture ASC, le.Sens DESC, le.Montant DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rawEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $currentRunningDebit = $initialBalanceDebit;
            $currentRunningCredit = $initialBalanceCredit;

            // Group lines by Ecriture (main entry) and calculate running totals
            foreach ($rawEntries as $row) {
                // Ensure 'Montant' is treated as a float, even if CAST resulted in NULL (which PHP treats as 0 in numeric contexts for this usage)
                $montant = (float)($row['Montant'] ?? 0);

                $ecritureId = $row['ID_Ecriture'];
                if (!isset($journalEntries[$ecritureId])) {
                    $journalEntries[$ecritureId] = [
                        'ID_Ecriture' => $row['ID_Ecriture'],
                        'Date_Saisie' => $row['Date_Saisie'],
                        'Ecriture_Description' => $row['Ecriture_Description'],
                        'Montant_Total' => $row['Montant_Total'],
                        'Numero_Piece' => $row['Numero_Piece'],
                        'NomUtilisateur' => $row['NomUtilisateur'],
                        'Nom_Journal' => $row['Nom_Journal'],
                        'lignes' => [],
                        'total_debit' => 0,
                        'total_credit' => 0
                    ];
                }
                $journalEntries[$ecritureId]['lignes'][] = [
                    'ID_Ligne' => $row['ID_Ligne'],
                    'Montant' => $montant, // Use the float value here
                    'Sens' => $row['Sens'],
                    'Libelle_Ligne' => $row['Libelle_Ligne'],
                    'Numero_Compte' => $row['Numero_Compte'],
                    'Nom_Compte' => $row['Nom_Compte']
                ];
                if ($row['Sens'] === 'D') {
                    $journalEntries[$ecritureId]['total_debit'] += $montant;
                    $currentRunningDebit += $montant;
                } else {
                    $journalEntries[$ecritureId]['total_credit'] += $montant;
                    $currentRunningCredit += $montant;
                }
            }

            $finalBalanceDebit = $currentRunningDebit;
            $finalBalanceCredit = $currentRunningCredit;

            logUserActivity("Génération du Journal Général par l'utilisateur ID: " . ($_SESSION['utilisateur_id'] ?? 'N/A') . " pour la période du {$startDate} au {$endDate}.");

        } catch (PDOException $e) {
            logApplicationError("Erreur PDO lors de la génération du Journal Général: " . $e->getMessage());
            $message = "Erreur lors de la récupération des données pour le journal: " . $e->getMessage();
            $messageType = 'danger';
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
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <style>
        .journal-table thead th { background-color: #f2f2f2; }
        .journal-entry-header { background-color: #e6f7ff; font-weight: bold; }
        .journal-entry-line { border-top: 1px dashed #ddd; }
        .journal-entry-totals { background-color: #fcf8e3; font-weight: bold; }
        .text-debit { color: #d9534f; } /* Red */
        .text-credit { color: #5cb85c; } /* Green */
        .balance-row { font-weight: bold; background-color: #dff0d8; } /* Light green for balances */
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
                <h3 class="panel-title">Filtres du Journal Général</h3>
            </div>
            <div class="panel-body">
                <form action="" method="POST" class="form-horizontal">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="start_date" class="col-sm-4 control-label">Date Début :</label>
                                <div class="col-sm-8">
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="end_date" class="col-sm-4 control-label">Date Fin :</label>
                                <div class="col-sm-8">
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="id_journal" class="col-sm-4 control-label">Journal :</label>
                                <div class="col-sm-8">
                                    <select name="id_journal" id="id_journal" class="form-control">
                                        <option value="">Tous les journaux</option>
                                        <?php foreach ($journals as $journal): ?>
                                            <option value="<?= htmlspecialchars($journal['Cde']) ?>"
                                                <?= ($selectedJournal == $journal['Cde']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($journal['Nom_Journal']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="id_compte" class="col-sm-4 control-label">Compte :</label>
                                <div class="col-sm-8">
                                    <select name="id_compte" id="id_compte" class="form-control">
                                        <option value="">Tous les comptes</option>
                                        <?php foreach ($comptes as $compte): ?>
                                            <option value="<?= htmlspecialchars($compte['ID_Compte']) ?>"
                                                <?= ($selectedAccount == $compte['ID_Compte']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($compte['Numero_Compte']) ?> - <?= htmlspecialchars($compte['Nom_Compte']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="min_amount" class="col-sm-4 control-label">Montant Min :</label>
                                <div class="col-sm-8">
                                    <input type="number" step="0.01" class="form-control" id="min_amount" name="min_amount" value="<?= htmlspecialchars($minAmount) ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="max_amount" class="col-sm-4 control-label">Montant Max :</label>
                                <div class="col-sm-8">
                                    <input type="number" step="0.01" class="form-control" id="max_amount" name="max_amount" value="<?= htmlspecialchars($maxAmount) ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="description_keyword" class="col-sm-4 control-label">Mot-clé Description :</label>
                                <div class="col-sm-8">
                                    <input type="text" class="form-control" id="description_keyword" name="description_keyword" value="<?= htmlspecialchars($descriptionKeyword) ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="numero_piece" class="col-sm-4 control-label">Numéro Pièce :</label>
                                <div class="col-sm-8">
                                    <input type="text" class="form-control" id="numero_piece" name="numero_piece" value="<?= htmlspecialchars($numeroPiece) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-sm-offset-2 col-sm-10">
                            <button type="submit" name="generate_report" class="btn btn-primary">
                                <span class="glyphicon glyphicon-list-alt"></span> Afficher le Journal
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (isset($_POST['generate_report']) && empty($message)): ?>
            <div class="panel panel-info">
                <div class="panel-footer text-right">
                    <a href="../exports/export_reports.php?report_type=journal_general&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&id_journal=<?= urlencode($selectedJournal ?? '') ?>&id_compte=<?= urlencode($selectedAccount ?? '') ?>&min_amount=<?= urlencode($minAmount) ?>&max_amount=<?= urlencode($maxAmount) ?>&description_keyword=<?= urlencode($descriptionKeyword) ?>&numero_piece=<?= urlencode($numeroPiece) ?>" class="btn btn-success">
                        <span class="glyphicon glyphicon-download-alt"></span> Exporter en CSV
                    </a>
                </div>
            </div>

            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">Transactions du Journal Général</h3>
                    <p class="text-muted small">Période du <?= htmlspecialchars(date('d/m/Y', strtotime($startDate))) ?> au <?= htmlspecialchars(date('d/m/Y', strtotime($endDate))) ?></p>
                </div>
                <div class="panel-body">
                    <?php if (empty($journalEntries) && ($initialBalanceDebit == 0 && $initialBalanceCredit == 0)): ?>
                        <div class="alert alert-info">Aucune écriture trouvée pour la période et les filtres sélectionnés.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped journal-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>N° Pièce</th>
                                        <th>Description Écriture</th>
                                        <th>Journal</th>
                                        <th>Compte N°</th>
                                        <th>Libellé Ligne</th>
                                        <th class="text-right">Débit</th>
                                        <th class="text-right">Crédit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="balance-row">
                                        <td colspan="5"></td>
                                        <td>Solde d'ouverture (Avant <?= htmlspecialchars(date('d/m/Y', strtotime($startDate))) ?>)</td>
                                        <td class="text-right text-debit"><?= htmlspecialchars(number_format($initialBalanceDebit, 2, ',', ' ')) ?></td>
                                        <td class="text-right text-credit"><?= htmlspecialchars(number_format($initialBalanceCredit, 2, ',', ' ')) ?></td>
                                    </tr>

                                    <?php foreach ($journalEntries as $entry): ?>
                                        <tr class="journal-entry-header">
                                            <td><?= htmlspecialchars(date('d/m/Y', strtotime($entry['Date_Saisie']))) ?></td>
                                            <td><?= htmlspecialchars($entry['Numero_Piece'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($entry['Ecriture_Description']) ?></td>
                                            <td><?= htmlspecialchars($entry['Nom_Journal'] ?? 'N/A') ?></td>
                                            <td colspan="2"></td>
                                            <td class="text-right text-debit"><?= htmlspecialchars(number_format($entry['total_debit'], 2, ',', ' ')) ?></td>
                                            <td class="text-right text-credit"><?= htmlspecialchars(number_format($entry['total_credit'], 2, ',', ' ')) ?></td>
                                        </tr>
                                        <?php foreach ($entry['lignes'] as $line): ?>
                                            <tr class="journal-entry-line">
                                                <td colspan="4"></td>
                                                <td><?= htmlspecialchars($line['Numero_Compte']) ?><br><small><?= htmlspecialchars($line['Nom_Compte']) ?></small></td>
                                                <td><?= htmlspecialchars($line['Libelle_Ligne']) ?></td>
                                                <td class="text-right <?= $line['Sens'] === 'D' ? 'text-debit' : '' ?>">
                                                    <?= $line['Sens'] === 'D' ? htmlspecialchars(number_format($line['Montant'], 2, ',', ' ')) : '' ?>
                                                </td>
                                                <td class="text-right <?= $line['Sens'] === 'C' ? 'text-credit' : '' ?>">
                                                    <?= $line['Sens'] === 'C' ? htmlspecialchars(number_format($line['Montant'], 2, ',', ' ')) : '' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>

                                    <tr class="balance-row">
                                        <td colspan="5"></td>
                                        <td>Solde de clôture (Au <?= htmlspecialchars(date('d/m/Y', strtotime($endDate))) ?>)</td>
                                        <td class="text-right text-debit"><?= htmlspecialchars(number_format($finalBalanceDebit, 2, ',', ' ')) ?></td>
                                        <td class="text-right text-credit"><?= htmlspecialchars(number_format($finalBalanceCredit, 2, ',', ' ')) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif (isset($_POST['generate_report']) && !empty($message)): ?>
            <?php else: ?>
            <div class="alert alert-info">Sélectionnez une période et les filtres souhaités, puis cliquez sur "Afficher le Journal" pour générer le rapport.</div>
        <?php endif; ?>

    </div>
    <?php require_once '../../templates/footer.php'; ?>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script src="js/jquery-3.7.1.js"></script>
     <script src="../js/bootstrap.min.js"></script>
</body>
</html>