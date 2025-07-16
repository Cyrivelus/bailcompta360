<?php
// pages/tableau_bord.php

// -----------------------------------------------------------
// 1. Initialisation et Configuration
// -----------------------------------------------------------
header('Content-Type: text/html; charset=utf-8');
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
session_start(); // Start session if not already started, often needed for user management

// -----------------------------------------------------------
// 2. Inclusions des Fichiers Nécessaires
// -----------------------------------------------------------
require_once '../fonctions/database.php'; // Contains PDO connection setup (e.g., getDatabaseConnection() function)
require_once '../fonctions/gestion_utilisateurs.php'; // For user-related functions, if any are used here

// -----------------------------------------------------------
// 3. Connexion à la Base de Données
// -----------------------------------------------------------
// Assurez-vous que getDatabaseConnection() est bien définie dans fonctions/database.php
// et qu'elle retourne un objet PDO configuré pour MySQL.



// -----------------------------------------------------------
// 4. Définition des Variables et Initialisation des Données
// -----------------------------------------------------------
$titre = 'Tableau de Bord';
$current_page = basename(__FILE__); // Used for navigation highlighting if applicable

// Initialize KPIs with default zero values
$kpis = [
    'total_loans' => 0,
    'total_loan_amount' => 0,
    'total_invoices' => 0,
    'total_invoice_amount_ttc' => 0,
    'total_ecritures' => 0,
    'sum_montant_total_ecritures' => 0,
    'total_budget_revenu' => 0,
    'total_budget_depense' => 0,
];

// Initialize chart data structures
$chart_data = [
    'loans_by_type' => ['labels' => [], 'data' => []],
    'loans_by_bank' => ['labels' => [], 'data' => []],
    'invoices_by_status' => ['labels' => [], 'data' => []],
    'monthly_invoices_ttc' => ['labels' => [], 'data' => []],
    'monthly_ecritures_count' => ['labels' => [], 'data' => []],
    'monthly_ecritures_sum' => ['labels' => [], 'data' => []],
    'ecritures_by_journal' => ['labels' => [], 'data' => []],
    'budget_vs_actual_revenue' => ['labels' => [], 'budget' => 0, 'actual' => 0],
    'budget_vs_actual_expense' => ['labels' => [], 'budget' => 0, 'actual' => 0],
];

// -----------------------------------------------------------
// 5. Récupération des Données du Tableau de Bord (KPIs et Graphiques)
// -----------------------------------------------------------
// Exécuter les requêtes uniquement si $pdo est valide
if ($pdo instanceof PDO) { // Only proceed if PDO object is valid
    try {
        // KPIs Emprunts
        $stmt = $pdo->query("SELECT COUNT(ID_Emprunt) as total, IFNULL(SUM(Montant_Pret), 0) as total_montant FROM Emprunts_Bancaires");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res) {
            $kpis['total_loans'] = (int)$res['total'];
            $kpis['total_loan_amount'] = (float)$res['total_montant'];
        }

        // KPIs Factures
        $stmt = $pdo->query("SELECT COUNT(ID_Facture) as total, IFNULL(SUM(Montant_TTC), 0) as total_montant FROM Factures");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res) {
            $kpis['total_invoices'] = (int)$res['total'];
            $kpis['total_invoice_amount_ttc'] = (float)$res['total_montant'];
        }

        // KPIs Écritures
        $stmt = $pdo->query("SELECT COUNT(ID_Ecriture) as total, IFNULL(SUM(Montant_Total), 0) as total_montant FROM ecritures");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res) {
            $kpis['total_ecritures'] = (int)$res['total'];
            $kpis['sum_montant_total_ecritures'] = (float)$res['total_montant'];
        }

        // KPIs Budget
        $stmt = $pdo->query("SELECT
            IFNULL(SUM(CASE WHEN Type_Budget = 'Revenu' THEN Montant_Budgetise ELSE 0 END), 0) AS total_budget_revenu,
            IFNULL(SUM(CASE WHEN Type_Budget = 'Depense' THEN Montant_Budgetise ELSE 0 END), 0) AS total_budget_depense
            FROM Budget");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res) {
            $kpis['total_budget_revenu'] = (float)$res['total_budget_revenu'];
            $kpis['total_budget_depense'] = (float)$res['total_budget_depense'];
        }

        // Graphique: Prêts par type
        $stmt = $pdo->query("SELECT Type_Pret, COUNT(*) AS count FROM Emprunts_Bancaires WHERE Type_Pret IS NOT NULL AND Type_Pret != '' GROUP BY Type_Pret");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $chart_data['loans_by_type']['labels'][] = $row['Type_Pret'];
            $chart_data['loans_by_type']['data'][] = (int)$row['count'];
        }

        // Graphique: Top 5 Banques
        $stmt = $pdo->query("SELECT Banque, COUNT(*) AS count FROM Emprunts_Bancaires WHERE Banque IS NOT NULL AND Banque != '' GROUP BY Banque ORDER BY count DESC LIMIT 5");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $chart_data['loans_by_bank']['labels'][] = $row['Banque'];
            $chart_data['loans_by_bank']['data'][] = (int)$row['count'];
        }

        // Graphique: Factures par statut
        $stmt = $pdo->query("SELECT Statut_Facture, COUNT(*) AS count FROM Factures WHERE Statut_Facture IS NOT NULL AND Statut_Facture != '' GROUP BY Statut_Facture");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $chart_data['invoices_by_status']['labels'][] = $row['Statut_Facture'];
            $chart_data['invoices_by_status']['data'][] = (int)$row['count'];
        }

        // Define the period for time-series data (e.g., last 12 months)
        $oneYearAgo = date('Y-m-01', strtotime('-11 months')); // Start from the 1st of the month, 11 months ago, to get 12 months data

        // Graphique: Évolution mensuelle factures TTC
        $sqlMonthlyInvoices = "
            SELECT
                DATE_FORMAT(Date_Emission, '%Y-%m') AS annee_mois,
                IFNULL(SUM(Montant_TTC), 0) AS total_mensuel_ttc
            FROM Factures
            WHERE Date_Emission IS NOT NULL AND Date_Emission >= :one_year_ago
            GROUP BY DATE_FORMAT(Date_Emission, '%Y-%m')
            ORDER BY annee_mois ASC
        ";
        $stmt = $pdo->prepare($sqlMonthlyInvoices);
        $stmt->bindParam(':one_year_ago', $oneYearAgo);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $chart_data['monthly_invoices_ttc']['labels'][] = $row['annee_mois'];
            $chart_data['monthly_invoices_ttc']['data'][] = (float)$row['total_mensuel_ttc'];
        }

        // Graphique: Écritures par mois (count and sum on separate charts for clarity)
        $sqlMonthlyEcritures = "
            SELECT
                DATE_FORMAT(Date_Saisie, '%Y-%m') AS annee_mois,
                COUNT(ID_Ecriture) AS count_ecritures,
                IFNULL(SUM(Montant_Total), 0) AS sum_montant
            FROM Ecritures
            WHERE Date_Saisie IS NOT NULL AND Date_Saisie >= :one_year_ago
            GROUP BY DATE_FORMAT(Date_Saisie, '%Y-%m')
            ORDER BY annee_mois ASC
        ";
        $stmt = $pdo->prepare($sqlMonthlyEcritures);
        $stmt->bindParam(':one_year_ago', $oneYearAgo);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $chart_data['monthly_ecritures_count']['labels'][] = $row['annee_mois'];
            $chart_data['monthly_ecritures_count']['data'][] = (int)$row['count_ecritures'];
            $chart_data['monthly_ecritures_sum']['labels'][] = $row['annee_mois'];
            $chart_data['monthly_ecritures_sum']['data'][] = (float)$row['sum_montant'];
        }

        // Graphique: Écritures par journal (Top 5)
        $stmt = $pdo->query("SELECT Cde, COUNT(ID_Ecriture) AS count_ecritures
                             FROM Ecritures
                             WHERE Cde IS NOT NULL
                             GROUP BY Cde
                             ORDER BY count_ecritures DESC LIMIT 5");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $chart_data['ecritures_by_journal']['labels'][] = 'Journal ' . htmlspecialchars($row['Cde']);
            $chart_data['ecritures_by_journal']['data'][] = (int)$row['count_ecritures'];
        }

        // New: Budget vs Actual Revenue/Expense (Example for current year)
        $currentYear = date('Y');
        $sqlBudgetActual = "
            SELECT
                b.Annee_Budgetaire,
                b.Type_Budget,
                SUM(b.Montant_Budgetise) AS Montant_Budgetise,
                IFNULL(SUM(CASE
                    WHEN b.Type_Budget = 'Revenu' AND le.Sens = 'C' THEN le.Montant
                    WHEN b.Type_Budget = 'Depense' AND le.Sens = 'D' THEN le.Montant
                    ELSE 0 END), 0) AS Montant_Reel
            FROM Budget b
            LEFT JOIN Lignes_Ecritures le ON b.ID_Compte = le.ID_Compte
            LEFT JOIN Ecritures e ON le.ID_Ecriture = e.ID_Ecriture AND YEAR(e.Date_Saisie) = b.Annee_Budgetaire
            WHERE b.Annee_Budgetaire = :current_year
            GROUP BY b.Annee_Budgetaire, b.Type_Budget
            ORDER BY b.Type_Budget;
        ";
        $stmt = $pdo->prepare($sqlBudgetActual);
        $stmt->bindParam(':current_year', $currentYear, PDO::PARAM_INT);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['Type_Budget'] == 'Revenu') {
                $chart_data['budget_vs_actual_revenue']['labels'] = ['Budget', 'Réel'];
                $chart_data['budget_vs_actual_revenue']['budget'] = (float)$row['Montant_Budgetise'];
                $chart_data['budget_vs_actual_revenue']['actual'] = (float)$row['Montant_Reel'];
            } elseif ($row['Type_Budget'] == 'Depense') {
                $chart_data['budget_vs_actual_expense']['labels'] = ['Budget', 'Réel'];
                $chart_data['budget_vs_actual_expense']['budget'] = (float)$row['Montant_Budgetise'];
                $chart_data['budget_vs_actual_expense']['actual'] = (float)$row['Montant_Reel'];
            }
        }

    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération des données du tableau de bord: " . $e->getMessage());
        // Définir une erreur pour l'affichage sur la page
        $db_error = "Erreur lors du chargement des données des statistiques : " . htmlspecialchars($e->getMessage());
    }
} else {
    $db_error = "La connexion à la base de données n'a pas pu être établie. Vérifiez votre configuration.";
}
// -----------------------------------------------------------
// 6. Affichage de la Vue
// -----------------------------------------------------------
require_once('../templates/header.php');
require_once('../templates/navigation.php');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?= htmlspecialchars($titre) ?></title>
    <link rel="shortcut icon" href="../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">

    <script src="../js/chart.min.js"></script>

    <style>
        /* General Dashboard Styling */
        .dashboard-container { padding: 20px 0; }
        .page-header { border-bottom: 1px solid #eee; padding-bottom: 9px; margin: 20px 0 30px; }

        /* Panel Styling */
        .panel {
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-radius: 8px; /* Slightly rounded corners */
            overflow: hidden; /* Ensures child elements respect border-radius */
        }
        .panel-heading {
            padding: 15px;
            border-bottom: 1px solid transparent; /* Defined by Bootstrap themes */
            border-top-left-radius: 7px;
            border-top-right-radius: 7px;
        }
        .panel-title {
            margin: 0;
            font-size: 18px; /* Slightly larger title */
            font-weight: bold;
            color: #333; /* Darker title color */
        }
        .panel-body { padding: 15px; }

        /* KPI Specific Styling */
        .kpi-value {
            font-size: 2.2em; /* Larger KPI values */
            font-weight: bold;
            margin-bottom: 5px;
        }
        .kpi-label {
            font-size: 1em; /* Slightly larger label */
            color: #555;
            text-transform: uppercase; /* Uppercase for labels */
        }
        /* Specific KPI panel colors for better visual distinction */
        .panel-primary .panel-body { background-color: #e6f2ff; } /* Light blue */
        .panel-success .panel-body { background-color: #e6ffe6; } /* Light green */
        .panel-info .panel-body { background-color: #e0f7fa; } /* Light cyan */
        .panel-warning .panel-body { background-color: #fff8e1; } /* Light yellow */
        .panel-danger .panel-body { background-color: #ffe6e6; } /* Light red */
        .panel-default .panel-body { background-color: #f8f9fa; } /* Light grey */
        /* New KPI styles for budget */
        .panel-secondary .panel-body { background-color: #eceff1; } /* Even lighter grey */


        /* Chart Specific Styling */
        .chart-container {
            position: relative; /* For responsive sizing with Chart.js */
            height: 300px;
            width: 100%;
            margin: auto; /* Center the canvas */
        }
        canvas {
            max-width: 100%; /* Ensure canvas doesn't overflow */
            height: 100%;
        }

        /* Responsive adjustments for smaller screens */
        @media (max-width: 767px) {
            .col-xs-6 { margin-bottom: 15px; } /* Add spacing for small KPI columns */
            .chart-container { height: 250px; } /* Adjust chart height for smaller screens */
        }
    </style>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>

<div class="container dashboard-container">
<br>
&nbsp;
    <h2 class="page-header">Tableau de Bord</h2>

    <?php if (isset($db_error)): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($db_error) ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="panel panel-primary text-center">
                <div class="panel-body">
                    <div class="kpi-value"><?= number_format($kpis['total_loans'], 0, ',', ' ') ?></div>
                    <div class="kpi-label">Total Prêts</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="panel panel-success text-center">
                <div class="panel-body">
                    <div class="kpi-value"><?= number_format($kpis['total_loan_amount'], 2, ',', ' ') ?> XAF</div>
                    <div class="kpi-label">Montant Prêts</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="panel panel-info text-center">
                <div class="panel-body">
                    <div class="kpi-value"><?= number_format($kpis['total_invoices'], 0, ',', ' ') ?></div>
                    <div class="kpi-label">Total Factures</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="panel panel-warning text-center">
                <div class="panel-body">
                    <div class="kpi-value"><?= number_format($kpis['total_invoice_amount_ttc'], 2, ',', ' ') ?> XAF</div>
                    <div class="kpi-label">Montant Factures</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="panel panel-danger text-center">
                <div class="panel-body">
                    <div class="kpi-value"><?= number_format($kpis['total_ecritures'], 0, ',', ' ') ?></div>
                    <div class="kpi-label">Total Écritures</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="panel panel-default text-center">
                <div class="panel-body">
                    <div class="kpi-value"><?= number_format($kpis['sum_montant_total_ecritures'], 2, ',', ' ') ?> XAF</div>
                    <div class="kpi-label">Montant Écritures</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="panel panel-info text-center">
                <div class="panel-body">
                    <div class="kpi-value"><?= number_format($kpis['total_budget_revenu'], 2, ',', ' ') ?> XAF</div>
                    <div class="kpi-label">Budget Revenu</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-xs-6">
            <div class="panel panel-danger text-center">
                <div class="panel-body">
                    <div class="kpi-value"><?= number_format($kpis['total_budget_depense'], 2, ',', ' ') ?> XAF</div>
                    <div class="kpi-label">Budget Dépense</div>
                </div>
            </div>
        </div>
    </div>



    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Prêts par Type</h3>
                </div>
                <div class="panel-body">
                    <div class="chart-container">
                        <canvas id="loansByTypeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Top 5 Banques par Nombre de Prêts</h3>
                </div>
                <div class="panel-body">
                    <div class="chart-container">
                        <canvas id="loansByBankChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Répartition des Factures par Statut</h3>
                </div>
                <div class="panel-body">
                    <div class="chart-container">
                        <canvas id="invoicesByStatusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Évolution Mensuelle des Factures TTC (12 derniers mois)</h3>
                </div>
                <div class="panel-body">
                    <div class="chart-container">
                        <canvas id="monthlyInvoicesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Nombre d'Écritures Comptables par Mois (12 derniers mois)</h3>
                </div>
                <div class="panel-body">
                    <div class="chart-container">
                        <canvas id="monthlyEcrituresCountChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Top 5 Journaux par Volume d'Écritures</h3>
                </div>
                <div class="panel-body">
                    <div class="chart-container">
                        <canvas id="ecrituresByJournalChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Budget vs Réel - Revenus (Année Actuelle)</h3>
                </div>
                <div class="panel-body">
                    <div class="chart-container">
                        <canvas id="budgetVsActualRevenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Budget vs Réel - Dépenses (Année Actuelle)</h3>
                </div>
                <div class="panel-body">
                    <div class="chart-container">
                        <canvas id="budgetVsActualExpenseChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <h3 class="page-header">Accès Rapides</h3>
    <div class="row">
        <div class="col-md-4 col-sm-6">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">Gestion Emprunts</h3>
                </div>
                <div class="panel-body text-center">
                    <a href="../pages/emprunts/index.php" class="btn btn-primary btn-lg">Accéder</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h3 class="panel-title">Gestion Factures</h3>
                </div>
                <div class="panel-body text-center">
                    <a href="../pages/factures/listes_factures.php" class="btn btn-success btn-lg">Accéder</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">Gestion Écritures</h3>
                </div>
                <div class="panel-body text-center">
                    <a href="../pages/ecritures/index.php" class="btn btn-info btn-lg">Accéder</a>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="panel panel-warning">
                <div class="panel-heading">
                    <h3 class="panel-title">Gestion Budgets</h3>
                </div>
                <div class="panel-body text-center">
                    <a href="../pages/budgets/index.php" class="btn btn-warning btn-lg">Accéder</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../js/jquery-3.7.1.js"></script>
<script src="../js/bootstrap.min.js"></script>
<script src="../js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // PHP variables are encoded as JSON and passed to JavaScript
    const chartData = <?= json_encode($chart_data) ?>;

    // Define a consistent color palette
    const primaryColors = [
        '#007bff', '#28a745', '#dc3545', '#ffc107', '#17a2b8', '#6610f2', '#6f42c1', '#e83e8c', '#fd7e14', '#20c997'
    ];
    const secondaryColors = [
        '#6c757d', '#343a40', '#adb5bd', '#ced4da', '#dee2e6'
    ];

    function getBackgroundColors(count) {
        return primaryColors.slice(0, count);
    }

    function getBorderColors(count) {
        return primaryColors.map(color => lightenDarkenColor(color, -20)).slice(0, count); // Darker border
    }

    // Helper to lighten/darken colors (simplified)
    function lightenDarkenColor(col, amt) {
        var usePound = false;
        if (col[0] == "#") {
            col = col.slice(1);
            usePound = true;
        }
        var num = parseInt(col, 16);
        var r = (num >> 16) + amt;
        if (r > 255) r = 255;
        else if (r < 0) r = 0;
        var b = ((num >> 8) & 0x00FF) + amt;
        if (b > 255) b = 255;
        else if (b < 0) b = 0;
        var g = (num & 0x0000FF) + amt;
        if (g > 255) g = 255;
        else if (g < 0) g = 0;
        return (usePound ? "#" : "") + (g | (b << 8) | (r << 16)).toString(16).padStart(6, '0');
    }

    // Configuration des graphiques
    function renderChart(id, type, dataConfig, title, options = {}) {
        const ctx = document.getElementById(id);
        if (!ctx) { // Check if canvas element exists
            console.warn(`Canvas element with ID '${id}' not found. Skipping chart rendering.`);
            return;
        }
        const context = ctx.getContext('2d');
        const datasets = [];

        if (Array.isArray(dataConfig)) { // For multiple datasets (e.g., budget vs actual)
            dataConfig.forEach((config, index) => {
                datasets.push({
                    label: config.label,
                    data: config.data,
                    backgroundColor: config.backgroundColor || (index === 0 ? primaryColors[0] : secondaryColors[0]),
                    borderColor: config.borderColor || (index === 0 ? lightenDarkenColor(primaryColors[0], -20) : lightenDarkenColor(secondaryColors[0], -20)),
                    borderWidth: config.borderWidth || 1,
                    fill: config.fill || false,
                    tension: config.tension || 0.4 // For smoother lines
                });
            });
        } else { // For single dataset
            // Check if data exists for the given dataConfig key
            if (!chartData[dataConfig] || !chartData[dataConfig].data || chartData[dataConfig].data.length === 0) {
                console.warn(`No data found for chart '${dataConfig}'. Skipping rendering.`);
                return; // Exit if no data
            }

            datasets.push({
                label: title, // Use title for label if it's a single dataset
                data: chartData[dataConfig].data,
                backgroundColor: (type === 'pie' || type === 'doughnut') ? getBackgroundColors(chartData[dataConfig].data.length) : primaryColors[0],
                borderColor: (type === 'pie' || type === 'doughnut') ? getBorderColors(chartData[dataConfig].data.length) : lightenDarkenColor(primaryColors[0], -20),
                borderWidth: 1,
                fill: false, // Default to no fill for line charts
                tension: 0.4 // Default tension for line charts
            });
        }

        new Chart(context, {
            type: type,
            data: {
                labels: Array.isArray(dataConfig) ? dataConfig[0].labels : chartData[dataConfig].labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: {
                                size: 14
                            }
                        }
                    },
                    title: {
                        display: true,
                        text: title,
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    if (id.includes('Amount') || id.includes('TTC') || id.includes('sum') || id.includes('budget')) {
                                        label += new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XAF' }).format(context.parsed.y);
                                    } else {
                                        label += new Intl.NumberFormat('fr-FR').format(context.parsed.y);
                                    }
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: options.scales || {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (id.includes('Amount') || id.includes('TTC') || id.includes('sum') || id.includes('budget')) {
                                    return new Intl.NumberFormat('fr-FR').format(value) + ' XAF';
                                }
                                return new Intl.NumberFormat('fr-FR').format(value);
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    // Initialisation des graphiques
    renderChart('loansByTypeChart', 'doughnut', 'loans_by_type', 'Prêts par Type');
    renderChart('loansByBankChart', 'bar', 'loans_by_bank', 'Top 5 Banques par Nombre de Prêts', {
        scales: {
            x: { beginAtZero: true, ticks: { callback: function(value){ return new Intl.NumberFormat('fr-FR').format(value); } } },
            y: { grid: { display: false } } // For horizontal bars, x-axis usually has count
        }
    });

    renderChart('invoicesByStatusChart', 'pie', 'invoices_by_status', 'Répartition des Factures par Statut');
    renderChart('monthlyInvoicesChart', 'line', 'monthly_invoices_ttc', 'Évolution Mensuelle des Factures TTC (12 derniers mois)');
    renderChart('monthlyEcrituresCountChart', 'bar', 'monthly_ecritures_count', 'Nombre d\'Écritures Comptables par Mois (12 derniers mois)');
    renderChart('ecrituresByJournalChart', 'doughnut', 'ecritures_by_journal', 'Top 5 Journaux par Volume d\'Écritures');

    // Special handling for Budget vs Actual charts (stacked bar or regular bar)
    renderChart('budgetVsActualRevenueChart', 'bar', [
        { label: 'Budget', data: [chartData.budget_vs_actual_revenue.budget], backgroundColor: primaryColors[1], borderColor: lightenDarkenColor(primaryColors[1], -20) }, // Green
        { label: 'Réel', data: [chartData.budget_vs_actual_revenue.actual], backgroundColor: primaryColors[0], borderColor: lightenDarkenColor(primaryColors[0], -20) }  // Blue
    ], 'Budget vs Réel - Revenus (Année Actuelle)', {
        scales: {
            x: { beginAtZero: true, ticks: { callback: function(value){ return ''; } } }, // No labels on X-axis for single bar
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return new Intl.NumberFormat('fr-FR').format(value) + ' XAF';
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    title: function() { return ''; } // No title for this specific bar chart tooltip
                }
            }
        }
    });

    renderChart('budgetVsActualExpenseChart', 'bar', [
        { label: 'Budget', data: [chartData.budget_vs_actual_expense.budget], backgroundColor: primaryColors[3], borderColor: lightenDarkenColor(primaryColors[3], -20) }, // Yellow
        { label: 'Réel', data: [chartData.budget_vs_actual_expense.actual], backgroundColor: primaryColors[2], borderColor: lightenDarkenColor(primaryColors[2], -20) }  // Red
    ], 'Budget vs Réel - Dépenses (Année Actuelle)', {
        scales: {
            x: { beginAtZero: true, ticks: { callback: function(value){ return ''; } } }, // No labels on X-axis for single bar
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return new Intl.NumberFormat('fr-FR').format(value) + ' XAF';
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    title: function() { return ''; }
                }
            }
        }
    });

});
</script>
</body>
</html>