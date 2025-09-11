<?php
// pages/tresorerie/position_tresorerie.php

// Define page title and current page for navigation highlighting
$titre = 'Position de trésorerie en temps réel';
$current_page = basename($_SERVER['PHP_SELF']);

// Correct the include paths using __DIR__ for reliability
require_once __DIR__ . '/../../fonctions/database.php';
require_once __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/../../templates/navigation.php';
require_once __DIR__ . '/../../fonctions/gestion_tresorerie.php'; // The new treasury functions file
require_once __DIR__ . '/../../fonctions/gestion_factures.php'; // Assuming you have functions for invoices


// Get data for the dashboard
$currentBalance = getCurrentCashBalance($pdo);
$cashFlowData = getCashFlowByPeriod($pdo, 'month'); // Get data for the last month
$upcomingInvoices = getUpcomingInvoices($pdo, 'customer');
$upcomingPayments = getUpcomingInvoices($pdo, 'supplier');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Position de Trésorerie</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/formulaire.css">
    <link rel="stylesheet" href="../../css/tableau.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-card {
            background-color: #ffffff;
            border-left: 5px solid #007bff;
            border-radius: 5px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .dashboard-card-title {
            font-size: 1.2em;
            color: #555;
            margin-top: 0;
        }
        .dashboard-card-amount {
            font-size: 2.5em;
            font-weight: bold;
        }
        .text-green { color: #28a745; }
        .text-red { color: #dc3545; }
        .text-blue { color: #007bff; }
        .chart-container {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
<div class="container">
    <h2 class="page-header">Tableau de bord de la trésorerie <span class="glyphicon glyphicon-stats"></span></h2>

    <div class="row">
        <div class="col-md-4">
            <div class="dashboard-card">
                <h3 class="dashboard-card-title">Solde de Trésorerie Actuel</h3>
                <p class="dashboard-card-amount text-blue"><?= number_format($currentBalance, 2, ',', ' ') ?> €</p>
                <small>Ce solde reflète les comptes de banque et de caisse.</small>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="dashboard-card">
                <h3 class="dashboard-card-title">Encaissements du mois</h3>
                <p class="dashboard-card-amount text-green"><?= number_format($cashFlowData['inflows'], 2, ',', ' ') ?> €</p>
                <small>Total des recettes pour le mois en cours.</small>
            </div>
        </div>

        <div class="col-md-4">
            <div class="dashboard-card">
                <h3 class="dashboard-card-title">Décaissements du mois</h3>
                <p class="dashboard-card-amount text-red"><?= number_format($cashFlowData['outflows'], 2, ',', ' ') ?> €</p>
                <small>Total des paiements pour le mois en cours.</small>
            </div>
        </div>
    </div>
    
    <hr>
    
    <div class="row">
        <div class="col-md-12">
            <div class="chart-container">
                <h3 class="page-header">Flux de Trésorerie du Mois</h3>
                <canvas id="cashFlowChart"></canvas>
            </div>
        </div>
    </div>
    
    <hr>

    <div class="row">
        <div class="col-md-6">
            <h3 class="page-header">Factures Clients à Venir <span class="glyphicon glyphicon-arrow-down"></span></h3>
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Date d'échéance</th>
                            <th>Client</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($upcomingInvoices)): ?>
                            <tr><td colspan="3" class="text-center">Aucune facture à recevoir dans les 30 jours.</td></tr>
                        <?php else: ?>
                            <?php foreach ($upcomingInvoices as $invoice): ?>
                                <tr>
                                    <td><?= htmlspecialchars($invoice['due_date']) ?></td>
                                    <td><?= htmlspecialchars($invoice['customer_name']) ?></td>
                                    <td><?= number_format($invoice['amount'], 2, ',', ' ') ?> €</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-md-6">
            <h3 class="page-header">Factures Fournisseurs à Payer <span class="glyphicon glyphicon-arrow-up"></span></h3>
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Date d'échéance</th>
                            <th>Fournisseur</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($upcomingPayments)): ?>
                            <tr><td colspan="3" class="text-center">Aucune facture à payer dans les 30 jours.</td></tr>
                        <?php else: ?>
                            <?php foreach ($upcomingPayments as $payment): ?>
                                <tr>
                                    <td><?= htmlspecialchars($payment['due_date']) ?></td>
                                    <td><?= htmlspecialchars($payment['supplier_name']) ?></td>
                                    <td><?= number_format($payment['amount'], 2, ',', ' ') ?> €</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // Data for the cash flow chart
    const cashFlowData = {
        labels: ['Encaissements', 'Décaissements'],
        datasets: [{
            label: 'Flux de Trésorerie du mois',
            data: [<?= $cashFlowData['inflows'] ?>, <?= $cashFlowData['outflows'] ?>],
            backgroundColor: [
                'rgba(40, 167, 69, 0.6)', // Green for Inflows
                'rgba(220, 53, 69, 0.6)'  // Red for Outflows
            ],
            borderColor: [
                'rgba(40, 167, 69, 1)',
                'rgba(220, 53, 69, 1)'
            ],
            borderWidth: 1
        }]
    };

    // Configuration options for the chart
    const config = {
        type: 'bar',
        data: cashFlowData,
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    };

    // Create the chart
    const cashFlowChart = new Chart(
        document.getElementById('cashFlowChart'),
        config
    );
</script>

<?php require_once(__DIR__ . '/../../templates/footer.php'); ?>