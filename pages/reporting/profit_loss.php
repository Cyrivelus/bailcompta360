<?php
session_start();

// Check user authentication and authorization
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../login.php');
    exit;
}

require_once '../../templates/header.php';
require_once '../../templates/navigation.php';
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_logs.php'; // For logging

$titre = 'Compte de Résultat (Profit & Loss)';

$message = '';
$messageType = '';

$startDate = $_POST['start_date'] ?? date('Y-01-01'); // Default to start of current year
$endDate = $_POST['end_date'] ?? date('Y-12-31');     // Default to end of current year

$revenues = [];
$expenses = [];
$netProfitLoss = 0;

if (isset($_POST['generate_report'])) {
    // Validate dates
    if (!DateTime::createFromFormat('Y-m-d', $startDate) || !DateTime::createFromFormat('Y-m-d', $endDate)) {
        $message = "Veuillez saisir des dates valides (AAAA-MM-JJ).";
        $messageType = 'danger';
    } elseif ($startDate > $endDate) {
        $message = "La date de début ne peut pas être postérieure à la date de fin.";
        $messageType = 'danger';
    } else {
        try {
            // Ensure $pdo is initialized by database.php
            if (!isset($pdo) || !$pdo instanceof PDO) {
                // Fallback or explicit connection if database.php doesn't guarantee $pdo
                // For example: $pdo = new PDO(...)
                throw new Exception("La connexion à la base de données n'est pas initialisée.");
            }

            // --- Fetch Revenues (Produits) ---
            // Sum of Credit amounts for Revenue accounts, minus Debit amounts for Revenue accounts
            $stmtRevenues = $pdo->prepare("
                SELECT
                    cc.Nom_Compte,
                    SUM(CASE WHEN le.Sens = 'C' THEN le.Montant ELSE 0 END) -
                    SUM(CASE WHEN le.Sens = 'D' THEN le.Montant ELSE 0 END) AS Total_Amount
                FROM
                    Lignes_Ecritures le
                JOIN
                    Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
                JOIN
                    Comptes_compta cc ON le.ID_Compte = cc.ID_Compte
                WHERE
                    cc.Type_Compte = 'Revenu'
                    -- Corrected for MariaDB/MySQL: Use DATE() for comparison
                    AND DATE(e.Date_Saisie) >= :start_date
                    AND DATE(e.Date_Saisie) <= :end_date
                GROUP BY
                    cc.Nom_Compte
                ORDER BY
                    cc.Nom_Compte;
            ");
            $stmtRevenues->bindParam(':start_date', $startDate);
            $stmtRevenues->bindParam(':end_date', $endDate);
            $stmtRevenues->execute();
            $revenues = $stmtRevenues->fetchAll(PDO::FETCH_ASSOC);

            // --- Fetch Expenses (Charges) ---
            // Sum of Debit amounts for Expense accounts, minus Credit amounts for Expense accounts (for returns/reductions)
            $stmtExpenses = $pdo->prepare("
                SELECT
                    cc.Nom_Compte,
                    SUM(CASE WHEN le.Sens = 'D' THEN le.Montant ELSE 0 END) -
                    SUM(CASE WHEN le.Sens = 'C' THEN le.Montant ELSE 0 END) AS Total_Amount
                FROM
                    Lignes_Ecritures le
                JOIN
                    Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
                JOIN
                    Comptes_compta cc ON le.ID_Compte = cc.ID_Compte
                WHERE
                    cc.Type_Compte = 'Depense'
                    -- Corrected for MariaDB/MySQL: Use DATE() for comparison
                    AND DATE(e.Date_Saisie) >= :start_date
                    AND DATE(e.Date_Saisie) <= :end_date
                GROUP BY
                    cc.Nom_Compte
                ORDER BY
                    cc.Nom_Compte;
            ");
            $stmtExpenses->bindParam(':start_date', $startDate);
            $stmtExpenses->bindParam(':end_date', $endDate);
            $stmtExpenses->execute();
            $expenses = $stmtExpenses->fetchAll(PDO::FETCH_ASSOC);

            // Calculate Net Profit/Loss
            $totalRevenues = array_sum(array_column($revenues, 'Total_Amount'));
            $totalExpenses = array_sum(array_column($expenses, 'Total_Amount'));
            $netProfitLoss = $totalRevenues - $totalExpenses;

            logUserActivity("Génération du Compte de Résultat par l'utilisateur ID: " . $_SESSION['utilisateur_id'] . " pour la période du {$startDate} au {$endDate}.");

        } catch (PDOException $e) {
            logApplicationError("Erreur PDO lors de la génération du Compte de Résultat: " . $e->getMessage());
            $message = "Erreur lors de la récupération des données pour le rapport: " . htmlspecialchars($e->getMessage());
            $messageType = 'danger';
        } catch (Exception $e) {
            logApplicationError("Erreur générale lors de la génération du Compte de Résultat: " . $e->getMessage());
            $message = "Une erreur inattendue est survenue: " . htmlspecialchars($e->getMessage());
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
        .report-section h4 { border-bottom: 1px solid #eee; padding-bottom: 5px; margin-top: 20px; }
        .report-total { font-weight: bold; font-size: 1.1em; }
        .net-profit { color: #28a745; } /* Green for profit */
        .net-loss { color: #dc3545; }   /* Red for loss */
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
                <h3 class="panel-title">Sélectionner la Période</h3>
            </div>
            <div class="panel-body">
                <form action="" method="POST" class="form-inline">
                    <div class="form-group mr-2">
                        <label for="start_date">Date de Début :</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" required>
                    </div>
                    <div class="form-group mr-2">
                        <label for="end_date">Date de Fin :</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" required>
                    </div>
                    <button type="submit" name="generate_report" class="btn btn-primary">
                        <span class="glyphicon glyphicon-calendar"></span> Générer le Rapport
                    </button>
                </form>
            </div>
        </div>

        <?php if (isset($_POST['generate_report']) && empty($message)): ?>
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">Résultats du Compte de Résultat</h3>
                    <p class="text-muted small">Période du <?= htmlspecialchars(date('d/m/Y', strtotime($startDate))) ?> au <?= htmlspecialchars(date('d/m/Y', strtotime($endDate))) ?></p>
                </div>
                <div class="panel-body">
                    <div class="report-section">
                        <h4>Produits (Revenus)</h4>
                        <?php if (empty($revenues)): ?>
                            <p>Aucun revenu enregistré pour cette période.</p>
                        <?php else: ?>
                            <table class="table table-striped table-condensed">
                                <thead>
                                    <tr>
                                        <th>Compte</th>
                                        <th class="text-right">Montant</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $currentTotalRevenues = 0;
                                    foreach ($revenues as $revenue):
                                        $currentTotalRevenues += $revenue['Total_Amount'];
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($revenue['Nom_Compte']) ?></td>
                                            <td class="text-right"><?= htmlspecialchars(number_format($revenue['Total_Amount'], 2, ',', ' ')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="report-total">
                                        <td>Total Produits</td>
                                        <td class="text-right"><?= htmlspecialchars(number_format($currentTotalRevenues, 2, ',', ' ')) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        <?php endif; ?>
                    </div>

                    <div class="report-section">
                        <h4>Charges (Dépenses)</h4>
                        <?php if (empty($expenses)): ?>
                            <p>Aucune charge enregistrée pour cette période.</p>
                        <?php else: ?>
                            <table class="table table-striped table-condensed">
                                <thead>
                                    <tr>
                                        <th>Compte</th>
                                        <th class="text-right">Montant</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $currentTotalExpenses = 0;
                                    foreach ($expenses as $expense):
                                        $currentTotalExpenses += $expense['Total_Amount'];
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($expense['Nom_Compte']) ?></td>
                                            <td class="text-right"><?= htmlspecialchars(number_format($expense['Total_Amount'], 2, ',', ' ')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="report-total">
                                        <td>Total Charges</td>
                                        <td class="text-right"><?= htmlspecialchars(number_format($currentTotalExpenses, 2, ',', ' ')) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        <?php endif; ?>
                    </div>

                    <hr>
                    <div class="row">
                        <div class="col-xs-offset-6 col-xs-6 text-right">
                            <h3 class="report-total <?= $netProfitLoss >= 0 ? 'net-profit' : 'net-loss' ?>">
                                Résultat Net : <?= htmlspecialchars(number_format($netProfitLoss, 2, ',', ' ')) ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel panel-info">
                <div class="panel-footer text-right">
                    <a href="../exports/export_reports.php?report_type=profit_loss&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="btn btn-success">
                        <span class="glyphicon glyphicon-download-alt"></span> Exporter en CSV
                    </a>
                </div>
            </div>

        <?php elseif (isset($_POST['generate_report']) && !empty($message)): ?>
            <?php else: ?>
            <div class="alert alert-info">Sélectionnez une période et cliquez sur "Générer le Rapport" pour afficher le Compte de Résultat.</div>
        <?php endif; ?>

    </div>
    <?php require_once '../../templates/footer.php'; ?>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script src="js/jquery-3.7.1.js"></script>
    <script src="../js/bootstrap.min.js"></script>
</body>
</html>