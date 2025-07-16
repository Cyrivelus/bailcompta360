<?php
session_start();

// Check user authentication and authorization
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_logs.php'; // For logging

$titre = 'Performance Budgétaire';

$message = '';
$messageType = '';

// Default filter values
$selectedYear = $_POST['annee_budgetaire'] ?? date('Y');
$showOnlyVariances = isset($_POST['show_only_variances']);
$varianceThreshold = $_POST['variance_threshold'] ?? 5; // Default 5% threshold

$budgetPerformanceData = [];
$totalBudgetedRevenue = 0;
$totalActualRevenue = 0;
$totalBudgetedExpense = 0;
$totalActualExpense = 0;
$netBudgetedProfit = 0;
$netActualProfit = 0;

// Fetch accounts that have budget entries or are of type Revenue/Expense
$comptes = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            cc.ID_Compte, cc.Numero_Compte, cc.Nom_Compte, cc.Type_Compte
        FROM
            Comptes_compta cc
        WHERE
            cc.Type_Compte IN ('Revenu', 'Depense') -- Only consider P&L relevant accounts for budget performance
        ORDER BY
            cc.Numero_Compte ASC
    ");
    $stmt->execute();
    $comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logApplicationError("Erreur PDO lors du chargement des comptes pour la performance budgétaire: " . $e->getMessage());
    $message = "Erreur lors du chargement des comptes comptables.";
    $messageType = 'danger';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate year
    if (!is_numeric($selectedYear) || $selectedYear < 2000 || $selectedYear > 2100) {
        $message = "Veuillez saisir une année budgétaire valide.";
        $messageType = 'danger';
    } elseif (!is_numeric($varianceThreshold) || $varianceThreshold < 0) {
        $message = "Le seuil d'écart doit être un nombre positif.";
        $messageType = 'danger';
    }
    else {
        try {
            // Determine the start and end dates for the selected year
            // These are now just used for the log, not directly in the SQL query for binding as strings
            $startDate = $selectedYear . '-01-01';
            $endDate = $selectedYear . '-12-31';

            // Fetch budgeted amounts for the year
            $stmtBudget = $pdo->prepare("
                SELECT
                    b.ID_Compte,
                    b.Montant_Budgetise,
                    b.Type_Budget,
                    cc.Numero_Compte,
                    cc.Nom_Compte,
                    cc.Type_Compte AS Compte_Type_Compta
                FROM
                    Budget b
                JOIN
                    Comptes_compta cc ON b.ID_Compte = cc.ID_Compte
                WHERE
                    b.Annee_Budgetaire = :annee_budgetaire
                    AND cc.Type_Compte IN ('Revenu', 'Depense');
            ");
            $stmtBudget->bindParam(':annee_budgetaire', $selectedYear, PDO::PARAM_INT);
            $stmtBudget->execute();
            $budgetItems = $stmtBudget->fetchAll(PDO::FETCH_ASSOC);

            // Fetch actual amounts for the year from financial entries
            // *** CORRECTION HERE ***
            $stmtActual = $pdo->prepare("
                SELECT
                    le.ID_Compte,
                    SUM(CASE WHEN cc.Type_Compte = 'Revenu' THEN
                                    (CASE WHEN le.Sens = 'C' THEN le.Montant ELSE -le.Montant END)
                                  WHEN cc.Type_Compte = 'Depense' THEN
                                    (CASE WHEN le.Sens = 'D' THEN le.Montant ELSE -le.Montant END)
                                  ELSE 0 END) AS Actual_Amount
                FROM
                    Lignes_Ecritures le
                JOIN
                    Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
                JOIN
                    Comptes_compta cc ON le.ID_Compte = cc.ID_Compte
                WHERE
                    YEAR(e.Date_Saisie) = :annee_budgetaire_actual_query -- Use YEAR() function
                    AND cc.Type_Compte IN ('Revenu', 'Depense')
                GROUP BY
                    le.ID_Compte;
            ");
            $stmtActual->bindParam(':annee_budgetaire_actual_query', $selectedYear, PDO::PARAM_INT); // Bind the year as an integer
            $stmtActual->execute();
            $actualItems = $stmtActual->fetchAll(PDO::FETCH_KEY_PAIR); // ID_Compte => Actual_Amount

            // Combine Budgeted and Actual data
            foreach ($comptes as $compte) {
                if (!in_array($compte['Type_Compte'], ['Revenu', 'Depense'])) continue; // Only P&L relevant accounts

                $budgetedAmount = 0;
                $actualAmount = $actualItems[$compte['ID_Compte']] ?? 0;

                // Find the budgeted amount for this account
                foreach ($budgetItems as $budgetItem) {
                    if ($budgetItem['ID_Compte'] == $compte['ID_Compte']) {
                        $budgetedAmount = $budgetItem['Montant_Budgetise'];
                        break;
                    }
                }

                $variance = $actualAmount - $budgetedAmount;
                $variancePercentage = ($budgetedAmount != 0) ? ($variance / $budgetedAmount) * 100 : ($variance != 0 ? 100 : 0);

                $includeRow = true;
                if ($showOnlyVariances && abs($variancePercentage) <= $varianceThreshold) {
                    $includeRow = false;
                }

                if ($includeRow) {
                    $budgetPerformanceData[] = [
                        'ID_Compte' => $compte['ID_Compte'],
                        'Numero_Compte' => $compte['Numero_Compte'],
                        'Nom_Compte' => $compte['Nom_Compte'],
                        'Type_Compte' => $compte['Type_Compte'],
                        'Budgeted_Amount' => $budgetedAmount,
                        'Actual_Amount' => $actualAmount,
                        'Variance' => $variance,
                        'Variance_Percentage' => $variancePercentage,
                    ];
                }

                // Sum for totals
                if ($compte['Type_Compte'] === 'Revenu') {
                    $totalBudgetedRevenue += $budgetedAmount;
                    $totalActualRevenue += $actualAmount;
                } elseif ($compte['Type_Compte'] === 'Depense') {
                    $totalBudgetedExpense += $budgetedAmount;
                    $totalActualExpense += $actualAmount;
                }
            }

            $netBudgetedProfit = $totalBudgetedRevenue - $totalBudgetedExpense;
            $netActualProfit = $totalActualRevenue - $totalActualExpense;

            // Corrected line 165
            logUserActivity("Consultation de la Performance Budgétaire pour l'année: " . $selectedYear . " par l'utilisateur ID: " . $_SESSION['utilisateur_id'] . ".");

        } catch (PDOException $e) {
            logApplicationError("Erreur PDO lors de la génération de la performance budgétaire: " . $e->getMessage());
            $message = "Erreur de base de données lors de la récupération des données: " . $e->getMessage();
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
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/tableau.css">
    <style>
        .variance-good { color: #28a745; font-weight: bold; } /* Green for favorable variance */
        .variance-bad { color: #dc3545; font-weight: bold; }  /* Red for unfavorable variance */
        .variance-neutral { color: #555; } /* Grey/black for close to budget */
        .total-row { font-weight: bold; background-color: #f0f8ff; }
        .net-profit-summary { font-size: 1.1em; margin-top: 20px; padding-top: 10px; border-top: 1px solid #eee; }
    </style>
</head>
<body>
<?php require_once '../../templates/header.php'; ?>
    <?php require_once '../../templates/navigation.php'; ?>
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
                <h3 class="panel-title">Filtres de Performance Budgétaire</h3>
            </div>
            <div class="panel-body">
                <form action="" method="POST" class="form-inline">
                    <div class="form-group mr-2">
                        <label for="annee_budgetaire">Année Budgétaire :</label>
                        <input type="number" class="form-control" id="annee_budgetaire" name="annee_budgetaire"
                               value="<?= htmlspecialchars($selectedYear) ?>" min="2000" max="2100" required>
                    </div>
                    <div class="form-group mr-2">
                        <label for="variance_threshold">Seuil d'Écart (%) :</label>
                        <input type="number" step="0.1" class="form-control" id="variance_threshold" name="variance_threshold"
                               value="<?= htmlspecialchars($varianceThreshold) ?>" min="0" max="100">
                    </div>
                    <div class="checkbox mr-4" style="margin-left: 20px;">
                        <label>
                            <input type="checkbox" name="show_only_variances" <?= $showOnlyVariances ? 'checked' : '' ?>> Afficher seulement les écarts > Seuil
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <span class="glyphicon glyphicon-eye-open"></span> Afficher la Performance
                    </button>
                </form>
            </div>
        </div>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($message)): ?>
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">Performance Budgétaire pour l'Année <?= htmlspecialchars($selectedYear) ?></h3>
                </div>
                <div class="panel-body">
                    <?php if (empty($budgetPerformanceData) && !$showOnlyVariances): ?>
                        <div class="alert alert-info">Aucune donnée budgétaire ou réelle trouvée pour l'année sélectionnée, ou tous les écarts sont en dessous du seuil.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped budget-performance-table">
                                <thead>
                                    <tr>
                                        <th>Compte N°</th>
                                        <th>Nom du Compte</th>
                                        <th>Type</th>
                                        <th class="text-right">Budget</th>
                                        <th class="text-right">Réel</th>
                                        <th class="text-right">Écart ($)</th>
                                        <th class="text-right">Écart (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="7" class="info">Produits (Revenus)</td></tr>
                                    <?php
                                    $hasRevenueData = false;
                                    foreach ($budgetPerformanceData as $data) {
                                        if ($data['Type_Compte'] === 'Revenu') {
                                            $hasRevenueData = true;
                                            $varianceClass = 'variance-neutral';
                                            if ($data['Budgeted_Amount'] != 0) {
                                                // For Revenue: Actual > Budget is good
                                                if ($data['Variance_Percentage'] > $varianceThreshold) $varianceClass = 'variance-good';
                                                if ($data['Variance_Percentage'] < -$varianceThreshold) $varianceClass = 'variance-bad';
                                            } else if ($data['Actual_Amount'] > 0) { // If budgeted was 0 but actual is positive
                                                $varianceClass = 'variance-good';
                                            }
                                    ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($data['Numero_Compte']) ?></td>
                                                    <td><?= htmlspecialchars($data['Nom_Compte']) ?></td>
                                                    <td><?= htmlspecialchars($data['Type_Compte']) ?></td>
                                                    <td class="text-right"><?= htmlspecialchars(number_format($data['Budgeted_Amount'], 2, ',', ' ')) ?></td>
                                                    <td class="text-right"><?= htmlspecialchars(number_format($data['Actual_Amount'], 2, ',', ' ')) ?></td>
                                                    <td class="text-right <?= $varianceClass ?>"><?= htmlspecialchars(number_format($data['Variance'], 2, ',', ' ')) ?></td>
                                                    <td class="text-right <?= $varianceClass ?>"><?= htmlspecialchars(number_format($data['Variance_Percentage'], 2, ',', ' ')) ?>%</td>
                                                </tr>
                                    <?php
                                        }
                                    }
                                    if (!$hasRevenueData):
                                    ?>
                                        <tr><td colspan="7">Aucune donnée de revenu.</td></tr>
                                    <?php endif; ?>
                                    <tr class="total-row">
                                        <td colspan="3">Total Produits</td>
                                        <td class="text-right"><?= htmlspecialchars(number_format($totalBudgetedRevenue, 2, ',', ' ')) ?></td>
                                        <td class="text-right"><?= htmlspecialchars(number_format($totalActualRevenue, 2, ',', ' ')) ?></td>
                                        <td class="text-right">
                                            <?php
                                                $totalRevenueVariance = $totalActualRevenue - $totalBudgetedRevenue;
                                                $totalRevenueVariancePct = ($totalBudgetedRevenue != 0) ? ($totalRevenueVariance / $totalBudgetedRevenue) * 100 : ($totalRevenueVariance != 0 ? 100 : 0);
                                                $varianceClass = 'variance-neutral';
                                                if ($totalBudgetedRevenue != 0) {
                                                    if ($totalRevenueVariancePct > $varianceThreshold) $varianceClass = 'variance-good';
                                                    if ($totalRevenueVariancePct < -$varianceThreshold) $varianceClass = 'variance-bad';
                                                } else if ($totalActualRevenue > 0) {
                                                    $varianceClass = 'variance-good';
                                                }
                                                echo '<span class="' . $varianceClass . '">' . htmlspecialchars(number_format($totalRevenueVariance, 2, ',', ' ')) . '</span>';
                                            ?>
                                        </td>
                                        <td class="text-right">
                                            <span class="<?= $varianceClass ?>"><?= htmlspecialchars(number_format($totalRevenueVariancePct, 2, ',', ' ')) ?>%</span>
                                        </td>
                                    </tr>

                                    <tr><td colspan="7" class="info">Charges (Dépenses)</td></tr>
                                    <?php
                                    $hasExpenseData = false;
                                    foreach ($budgetPerformanceData as $data) {
                                        if ($data['Type_Compte'] === 'Depense') {
                                            $hasExpenseData = true;
                                            $varianceClass = 'variance-neutral';
                                            if ($data['Budgeted_Amount'] != 0) {
                                                // For Expense: Actual < Budget is good
                                                if ($data['Variance_Percentage'] < -$varianceThreshold) $varianceClass = 'variance-good';
                                                if ($data['Variance_Percentage'] > $varianceThreshold) $varianceClass = 'variance-bad';
                                            } else if ($data['Actual_Amount'] > 0) { // If budgeted was 0 but actual is positive
                                                $varianceClass = 'variance-bad';
                                            }
                                    ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($data['Numero_Compte']) ?></td>
                                                    <td><?= htmlspecialchars($data['Nom_Compte']) ?></td>
                                                    <td><?= htmlspecialchars($data['Type_Compte']) ?></td>
                                                    <td class="text-right"><?= htmlspecialchars(number_format($data['Budgeted_Amount'], 2, ',', ' ')) ?></td>
                                                    <td class="text-right"><?= htmlspecialchars(number_format($data['Actual_Amount'], 2, ',', ' ')) ?></td>
                                                    <td class="text-right <?= $varianceClass ?>"><?= htmlspecialchars(number_format($data['Variance'], 2, ',', ' ')) ?></td>
                                                    <td class="text-right <?= $varianceClass ?>"><?= htmlspecialchars(number_format($data['Variance_Percentage'], 2, ',', ' ')) ?>%</td>
                                                </tr>
                                    <?php
                                        }
                                    }
                                    if (!$hasExpenseData):
                                    ?>
                                        <tr><td colspan="7">Aucune donnée de charge.</td></tr>
                                    <?php endif; ?>
                                    <tr class="total-row">
                                        <td colspan="3">Total Charges</td>
                                        <td class="text-right"><?= htmlspecialchars(number_format($totalBudgetedExpense, 2, ',', ' ')) ?></td>
                                        <td class="text-right"><?= htmlspecialchars(number_format($totalActualExpense, 2, ',', ' ')) ?></td>
                                        <td class="text-right">
                                            <?php
                                                $totalExpenseVariance = $totalActualExpense - $totalBudgetedExpense;
                                                $totalExpenseVariancePct = ($totalBudgetedExpense != 0) ? ($totalExpenseVariance / $totalBudgetedExpense) * 100 : ($totalExpenseVariance != 0 ? 100 : 0);
                                                $varianceClass = 'variance-neutral';
                                                if ($totalBudgetedExpense != 0) {
                                                    if ($totalExpenseVariancePct < -$varianceThreshold) $varianceClass = 'variance-good';
                                                    if ($totalExpenseVariancePct > $varianceThreshold) $varianceClass = 'variance-bad';
                                                } else if ($totalActualExpense > 0) {
                                                    $varianceClass = 'variance-bad';
                                                }
                                                echo '<span class="' . $varianceClass . '">' . htmlspecialchars(number_format($totalExpenseVariance, 2, ',', ' ')) . '</span>';
                                            ?>
                                        </td>
                                        <td class="text-right">
                                            <span class="<?= $varianceClass ?>"><?= htmlspecialchars(number_format($totalExpenseVariancePct, 2, ',', ' ')) ?>%</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="net-profit-summary">
                            <div class="row">
                                <div class="col-xs-6">Profit Net Budgétisé:</div>
                                <div class="col-xs-6 text-right amount-<?= $netBudgetedProfit >= 0 ? 'inflow' : 'outflow' ?>">
                                    <?= htmlspecialchars(number_format($netBudgetedProfit, 2, ',', ' ')) ?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-xs-6">Profit Net Réel:</div>
                                <div class="col-xs-6 text-right amount-<?= $netActualProfit >= 0 ? 'inflow' : 'outflow' ?>">
                                    <?= htmlspecialchars(number_format($netActualProfit, 2, ',', ' ')) ?>
                                </div>
                            </div>
                            <div class="row total-row">
                                <div class="col-xs-6">Écart du Profit Net:</div>
                                <div class="col-xs-6 text-right">
                                    <?php
                                        $netProfitVariance = $netActualProfit - $netBudgetedProfit;
                                        $netProfitVariancePct = ($netBudgetedProfit != 0) ? ($netProfitVariance / $netBudgetedProfit) * 100 : ($netProfitVariance != 0 ? 100 : 0);
                                        $varianceClass = 'variance-neutral';
                                        if ($netBudgetedProfit != 0) {
                                            // For Net Profit: Actual > Budget is good
                                            if ($netProfitVariancePct > $varianceThreshold) $varianceClass = 'variance-good';
                                            if ($netProfitVariancePct < -$varianceThreshold) $varianceClass = 'variance-bad';
                                        } else if ($netActualProfit > 0) {
                                            $varianceClass = 'variance-good';
                                        } else if ($netActualProfit < 0) {
                                            $varianceClass = 'variance-bad';
                                        }
                                        echo '<span class="' . $varianceClass . '">' . htmlspecialchars(number_format($netProfitVariance, 2, ',', ' ')) . '</span>';
                                    ?>
                                    <br>
                                    <small class="<?= $varianceClass ?>"><?= htmlspecialchars(number_format($netProfitVariancePct, 2, ',', ' ')) ?>%</small>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">Sélectionnez une année et cliquez sur "Afficher la Performance" pour générer le rapport.</div>
        <?php endif; ?>

    </div>
    <?php require_once '../../templates/footer.php'; ?>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>
</html>