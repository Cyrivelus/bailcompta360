<?php
session_start();

// Check user authentication and authorization
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_logs.php'; // For logging

$titre = 'Prévisions Financières';

$message = '';
$messageType = '';

// Default filter values
$basePeriodType = $_POST['base_period_type'] ?? 'last_year'; // 'last_year', 'last_quarter', 'last_month', 'current_year_to_date'
$forecastHorizon = $_POST['forecast_horizon'] ?? 12; // Number of periods to forecast
$forecastPeriodType = $_POST['forecast_period_type'] ?? 'month'; // 'month', 'quarter'
$annualGrowthRate = $_POST['annual_growth_rate'] ?? 0; // % growth rate

$forecastData = [];
$totalForecastedRevenue = 0;
$totalForecastedExpense = 0;
$netForecastedProfit = 0;

// Get available years for base period selection
$availableYears = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT YEAR(Date_Saisie) AS Annee FROM Ecritures ORDER BY Annee DESC");
    $availableYears = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($availableYears)) {
        $availableYears[] = date('Y'); // Add current year if no data
    }
} catch (PDOException $e) {
    logApplicationError("Erreur PDO lors du chargement des années disponibles: " . $e->getMessage());
    $message = "Erreur lors du chargement des années disponibles.";
    $messageType = 'danger';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Input validation
    if (!is_numeric($forecastHorizon) || $forecastHorizon <= 0 || $forecastHorizon > 60) { // Max 5 years of monthly forecast
        $message .= "Le nombre de périodes de prévision doit être un nombre positif (max 60 mois/trimestres).<br>";
    }
    if (!in_array($forecastPeriodType, ['month', 'quarter'])) {
        $message .= "Le type de période de prévision est invalide.<br>";
    }
    if (!is_numeric($annualGrowthRate)) {
        $message .= "Le taux de croissance annuel doit être un nombre.<br>";
    }

    // New flag to indicate if base period type is valid
    $isBasePeriodTypeValid = true;

    if (empty($message)) {
        try {
            $baseStartDate = '';
            $baseEndDate = '';

            // Determine base period dates
            switch ($basePeriodType) {
                case 'last_year':
                    $baseStartDate = (date('Y') - 1) . '-01-01';
                    $baseEndDate = (date('Y') - 1) . '-12-31';
                    break;
                case 'current_year_to_date':
                    $baseStartDate = date('Y') . '-01-01';
                    $baseEndDate = date('Y-m-d'); // Up to today
                    break;
                case 'last_quarter':
                    $currentMonth = date('m');
                    $currentYear = date('Y');
                    $quarter = ceil($currentMonth / 3);
                    $lastQuarter = $quarter - 1;
                    $lastQuarterYear = $currentYear;
                    if ($lastQuarter <= 0) {
                        $lastQuarter = 4;
                        $lastQuarterYear--;
                    }
                    $baseStartDate = date('Y-m-d', strtotime("{$lastQuarterYear}-" . (($lastQuarter - 1) * 3 + 1) . "-01"));
                    $baseEndDate = date('Y-m-d', strtotime("{$lastQuarterYear}-" . ($lastQuarter * 3) . "-01 +1 month -1 day"));
                    break;
                case 'last_month':
                    $baseStartDate = date('Y-m-d', strtotime('first day of last month'));
                    $baseEndDate = date('Y-m-d', strtotime('last day of last month'));
                    break;
                default:
                    $message = "Type de période de base invalide.";
                    $messageType = 'danger';
                    $isBasePeriodTypeValid = false; // Set flag to false
                    break;
            }

            // Only proceed with forecast generation if base period type is valid
            if ($isBasePeriodTypeValid) {
                // Fetch actual amounts for the base period for Revenue and Expense accounts
                // Corrected SQL: Removed CONVERT(DATETIME, ..., 120) as it's SQL Server syntax
                $stmtBaseActuals = $pdo->prepare("
                    SELECT
                        cc.ID_Compte,
                        cc.Numero_Compte,
                        cc.Nom_Compte,
                        cc.Type_Compte,
                        SUM(CASE WHEN cc.Type_Compte = 'Revenu' THEN
                                    (CASE WHEN le.Sens = 'C' THEN le.Montant ELSE -le.Montant END)
                                WHEN cc.Type_Compte = 'Depense' THEN
                                    (CASE WHEN le.Sens = 'D' THEN le.Montant ELSE -le.Montant END)
                                ELSE 0 END) AS Base_Amount
                    FROM
                        Lignes_Ecritures le
                    JOIN
                        Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
                    JOIN
                        Comptes_compta cc ON le.ID_Compte = cc.ID_Compte
                    WHERE
                        e.Date_Saisie BETWEEN :base_start_date AND :base_end_date
                        AND cc.Type_Compte IN ('Revenu', 'Depense')
                    GROUP BY
                        cc.ID_Compte, cc.Numero_Compte, cc.Nom_Compte, cc.Type_Compte
                    ORDER BY
                        cc.Numero_Compte;
                ");
                $stmtBaseActuals->bindParam(':base_start_date', $baseStartDate);
                $stmtBaseActuals->bindParam(':base_end_date', $baseEndDate);
                $stmtBaseActuals->execute();
                $baseActuals = $stmtBaseActuals->fetchAll(PDO::FETCH_ASSOC);

                // Calculate the effective growth rate per forecast period
                // Annual rate applied over X periods. Simple compounding for now.
                $effectiveGrowthRate = pow(1 + ($annualGrowthRate / 100), 1 / ($forecastPeriodType === 'month' ? 12 : 4)) - 1;

                // Generate forecasts
                $currentForecastDate = new DateTime();
                // Ensure the currentForecastDate starts correctly for the next period
                if ($forecastPeriodType === 'month') {
                    $currentForecastDate->modify('first day of next month');
                } else { // quarter
                    // Calculate the start of the next quarter
                    $currentMonth = (int)$currentForecastDate->format('m');
                    $currentYear = (int)$currentForecastDate->format('Y');
                    $currentQuarter = ceil($currentMonth / 3);
                    $nextQuarter = $currentQuarter + 1;
                    $nextQuarterYear = $currentYear;
                    if ($nextQuarter > 4) {
                        $nextQuarter = 1;
                        $nextQuarterYear++;
                    }
                    $startMonthOfNextQuarter = (($nextQuarter - 1) * 3) + 1;
                    $currentForecastDate->setDate($nextQuarterYear, $startMonthOfNextQuarter, 1);
                }


                for ($i = 0; $i < $forecastHorizon; $i++) {
                    $periodKey = $currentForecastDate->format($forecastPeriodType === 'month' ? 'Y-m' : 'Y-\QQ');
                    $forecastPeriodData = ['period' => $periodKey, 'revenues' => 0, 'expenses' => 0, 'net_profit' => 0, 'details' => []];

                    foreach ($baseActuals as $accountData) {
                        $baseAmount = $accountData['Base_Amount'];
                        $forecastedAmount = $baseAmount * (1 + $effectiveGrowthRate * ($i + 1)); // Simple cumulative growth

                        $forecastPeriodData['details'][] = [
                            'Numero_Compte' => $accountData['Numero_Compte'],
                            'Nom_Compte' => $accountData['Nom_Compte'],
                            'Type_Compte' => $accountData['Type_Compte'],
                            'Forecasted_Amount' => $forecastedAmount
                        ];

                        if ($accountData['Type_Compte'] === 'Revenu') {
                            $forecastPeriodData['revenues'] += $forecastedAmount;
                        } else { // Expense
                            $forecastPeriodData['expenses'] += $forecastedAmount;
                        }
                    }
                    $forecastPeriodData['net_profit'] = $forecastPeriodData['revenues'] - $forecastPeriodData['expenses'];
                    $forecastData[] = $forecastPeriodData;

                    $totalForecastedRevenue += $forecastPeriodData['revenues'];
                    $totalForecastedExpense += $forecastPeriodData['expenses'];
                    $netForecastedProfit += $forecastPeriodData['net_profit'];

                    // Advance to the next forecast period
                    // Modified this line to use a safer increment for quarters
                    if ($forecastPeriodType === 'month') {
                        $currentForecastDate->modify('+1 month');
                    } else { // quarter
                        // For quarter, explicitly calculate next quarter's start
                        $currentMonth = (int)$currentForecastDate->format('m');
                        $currentYear = (int)$currentForecastDate->format('Y');
                        $currentQuarter = ceil($currentMonth / 3);
                        $nextQuarter = $currentQuarter + 1;
                        $nextQuarterYear = $currentYear;

                        if ($nextQuarter > 4) {
                            $nextQuarter = 1;
                            $nextQuarterYear++;
                        }
                        $startMonthOfNextQuarter = (($nextQuarter - 1) * 3) + 1;
                        $currentForecastDate->setDate($nextQuarterYear, $startMonthOfNextQuarter, 1);
                    }
                }

                logUserActivity("Génération des prévisions financières (base: {$basePeriodType}, horizon: {$forecastHorizon} {$forecastPeriodType}s, croissance: {$annualGrowthRate}%) par l'utilisateur ID: " . $_SESSION['utilisateur_id'] . ".");
            }
        } catch (PDOException $e) {
            logApplicationError("Erreur PDO lors de la génération des prévisions: " . $e->getMessage());
            $message = "Erreur de base de données lors de la récupération des données: " . $e->getMessage();
            $messageType = 'danger';
        } catch (Exception $e) {
            logApplicationError("Erreur lors de la génération des prévisions: " . $e->getMessage());
            $message = "Erreur lors de la génération des prévisions: " . $e->getMessage();
            $messageType = 'danger';
        }
    } else {
        $messageType = 'danger';
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
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        .forecast-summary-table th, .forecast-summary-table td { text-align: right; }
        .forecast-summary-table th:first-child, .forecast-summary-table td:first-child { text-align: left; }
        .forecast-section-title { font-size: 1.1em; margin-top: 20px; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .forecast-detail-table { margin-top: 15px; }
        .amount-positive { color: #28a745; }
        .amount-negative { color: #dc3545; }
        .total-row { font-weight: bold; background-color: #f0f8ff; }
        .grand-total-row { font-weight: bold; background-color: #e6f7ff; font-size: 1.2em;}
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
                <h3 class="panel-title">Paramètres de Prévision</h3>
            </div>
            <div class="panel-body">
                <form action="" method="POST" class="form-inline">
                    <div class="form-group mr-2">
                        <label for="base_period_type">Base de Calcul :</label>
                        <select name="base_period_type" id="base_period_type" class="form-control" required>
                            <option value="last_year" <?= ($basePeriodType == 'last_year') ? 'selected' : '' ?>>Actuels Année Dernière (<?= date('Y') - 1 ?>)</option>
                            <option value="current_year_to_date" <?= ($basePeriodType == 'current_year_to_date') ? 'selected' : '' ?>>Actuels Année en cours (YTD)</option>
                            <option value="last_quarter" <?= ($basePeriodType == 'last_quarter') ? 'selected' : '' ?>>Actuels Trimestre Dernier</option>
                            <option value="last_month" <?= ($basePeriodType == 'last_month') ? 'selected' : '' ?>>Actuels Mois Dernier</option>
                        </select>
                    </div>
                    <div class="form-group mr-2">
                        <label for="forecast_horizon">Périodes à Prévoir :</label>
                        <input type="number" class="form-control" id="forecast_horizon" name="forecast_horizon"
                               value="<?= htmlspecialchars($forecastHorizon) ?>" min="1" max="60" required>
                    </div>
                    <div class="form-group mr-2">
                        <label for="forecast_period_type">Type de Période :</label>
                        <select name="forecast_period_type" id="forecast_period_type" class="form-control" required>
                            <option value="month" <?= ($forecastPeriodType == 'month') ? 'selected' : '' ?>>Mois</option>
                            <option value="quarter" <?= ($forecastPeriodType == 'quarter') ? 'selected' : '' ?>>Trimestre</option>
                        </select>
                    </div>
                    <div class="form-group mr-2">
                        <label for="annual_growth_rate">Taux de Croissance Annuel (%) :</label>
                        <input type="number" step="0.1" class="form-control" id="annual_growth_rate" name="annual_growth_rate"
                               value="<?= htmlspecialchars($annualGrowthRate) ?>" placeholder="Ex: 5.0">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <span class="glyphicon glyphicon-stats"></span> Générer les Prévisions
                    </button>
                </form>
            </div>
        </div>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($message)): ?>
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">Prévisions Générales</h3>
                    <p class="text-muted small">Basées sur les actuels du
                        <?php
                            switch ($basePeriodType) {
                                case 'last_year': echo "dernière année (". (date('Y') - 1) .")"; break;
                                case 'current_year_to_date': echo "année en cours (YTD)"; break;
                                case 'last_quarter': echo "dernier trimestre"; break;
                                case 'last_month': echo "dernier mois"; break;
                            }
                        ?>
                            avec un taux de croissance annuel de <?= htmlspecialchars($annualGrowthRate) ?>%
                    </p>
                </div>
                <div class="panel-body">
                    <?php if (empty($forecastData)): ?>
                        <div class="alert alert-info">Aucune donnée trouvée pour générer les prévisions.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped forecast-summary-table">
                                <thead>
                                    <tr>
                                        <th>Période</th>
                                        <th>Revenus Prévisionnels</th>
                                        <th>Dépenses Prévisionnelles</th>
                                        <th>Profit Net Prévisionnel</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($forecastData as $periodForecast): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($periodForecast['period']) ?></td>
                                            <td class="amount-positive"><?= htmlspecialchars(number_format($periodForecast['revenues'], 2, ',', ' ')) ?></td>
                                            <td class="amount-negative"><?= htmlspecialchars(number_format($periodForecast['expenses'] * -1, 2, ',', ' ')) ?></td>
                                            <td class="amount-<?= $periodForecast['net_profit'] >= 0 ? 'positive' : 'negative' ?>">
                                                <?= htmlspecialchars(number_format($periodForecast['net_profit'], 2, ',', ' ')) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="grand-total-row">
                                        <td>Total Prévisions</td>
                                        <td class="amount-positive"><?= htmlspecialchars(number_format($totalForecastedRevenue, 2, ',', ' ')) ?></td>
                                        <td class="amount-negative"><?= htmlspecialchars(number_format($totalForecastedExpense * -1, 2, ',', ' ')) ?></td>
                                        <td class="amount-<?= $netForecastedProfit >= 0 ? 'positive' : 'negative' ?>">
                                            <?= htmlspecialchars(number_format($netForecastedProfit, 2, ',', ' ')) ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <h4 class="forecast-section-title">Détails des Prévisions par Compte</h4>
                        <?php
                            // Display detailed forecasts for each period
                            foreach ($forecastData as $periodForecast):
                        ?>
                            <h5>Période : <?= htmlspecialchars($periodForecast['period']) ?></h5>
                            <div class="table-responsive">
                                <table class="table table-striped table-condensed forecast-detail-table">
                                    <thead>
                                        <tr>
                                            <th>Compte N°</th>
                                            <th>Nom du Compte</th>
                                            <th>Type</th>
                                            <th class="text-right">Montant Prévisionnel</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                            usort($periodForecast['details'], function($a, $b) {
                                                return $a['Numero_Compte'] <=> $b['Numero_Compte'];
                                            });
                                            foreach ($periodForecast['details'] as $detail):
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($detail['Numero_Compte']) ?></td>
                                                <td><?= htmlspecialchars($detail['Nom_Compte']) ?></td>
                                                <td><?= htmlspecialchars($detail['Type_Compte']) ?></td>
                                                <td class="text-right amount-<?= ($detail['Type_Compte'] === 'Revenu' && $detail['Forecasted_Amount'] >= 0) || ($detail['Type_Compte'] === 'Depense' && $detail['Forecasted_Amount'] <= 0) ? 'positive' : 'negative' ?>">
                                                    <?= htmlspecialchars(number_format($detail['Forecasted_Amount'], 2, ',', ' ')) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>

                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">Sélectionnez les paramètres de prévision et cliquez sur "Générer les Prévisions" pour voir les résultats.</div>
        <?php endif; ?>

    </div>
    
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script src="js/jquery-3.7.1.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    
</body>
</html>