<?php
// pages/tresorerie/gestion_liquidite.php

$titre = 'Outils de gestion de la liquidité';
$current_page = basename($_SERVER['PHP_SELF']);

// Inclusion des fichiers nécessaires
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_liquidite.php'; // Fichier contenant les fonctions de calcul

// Initialisation des dates de la période de liquidité (par défaut, le mois en cours)
$dateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-01');
$dateFin = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-t');

// Données pour le tableau de bord
$soldes = getSoldesActuels();
$previsions = getPrevisionsTresorerie($dateDebut, $dateFin);
$creances = getCreancesClient($dateFin);
$dettes = getDettesFournisseur($dateFin);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Gestion de la Liquidité</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/formulaire.css">
    <link rel="stylesheet" href="../../css/tableau.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .dashboard-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        .dashboard-card h3 {
            margin-top: 0;
            font-size: 1.5em;
            color: #555;
        }
        .dashboard-card .amount {
            font-size: 2.5em;
            font-weight: bold;
            margin: 10px 0 0;
        }
        .amount.inflow { color: #28a745; }
        .amount.outflow { color: #dc3545; }
        .amount.balance { color: #007bff; }
        .table-section {
            margin-top: 30px;
        }
    </style>
</head>

<body>
<div class="container">
    <h2 class="page-header">Gestion de la Liquidité</h2>

    <form class="form-inline" method="get" action="gestion_liquidite.php">
        <div class="form-group">
            <label for="date_debut">Période du</label>
            <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?= htmlspecialchars($dateDebut) ?>">
        </div>
        <div class="form-group">
            <label for="date_fin">au</label>
            <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?= htmlspecialchars($dateFin) ?>">
        </div>
        <button type="submit" class="btn btn-primary">
            <span class="glyphicon glyphicon-filter"></span> Filtrer
        </button>
    </form>
    
    <hr>

    <div class="row">
        <div class="col-md-4">
            <div class="dashboard-card">
                <h3>Solde de départ</h3>
                <p class="amount balance"><?= number_format($previsions['solde_initial'], 2, ',', ' ') ?> €</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dashboard-card">
                <h3>Total des encaissements</h3>
                <p class="amount inflow"><?= number_format($previsions['total_encaissements'], 2, ',', ' ') ?> €</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dashboard-card">
                <h3>Total des décaissements</h3>
                <p class="amount outflow"><?= number_format($previsions['total_decaissements'], 2, ',', ' ') ?> €</p>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12 text-center">
            <div class="dashboard-card">
                <h3>Solde de fin de période</h3>
                <p class="amount balance"><?= number_format($previsions['solde_final'], 2, ',', ' ') ?> €</p>
            </div>
        </div>
    </div>
    
    <hr>
    
    <div class="row">
        <div class="col-md-6 table-section">
            <h3 class="page-header">Créances Clients à Recevoir <small>(<?= date('d/m/Y') ?> au <?= date('d/m/Y', strtotime($dateFin)) ?>)</small></h3>
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Date d'échéance</th>
                            <th>Description</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($creances)): ?>
                        <tr><td colspan="3" class="text-center">Aucune créance client à l'échéance.</td></tr>
                    <?php else: ?>
                        <?php foreach ($creances as $creance): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($creance['Date_Echeance'])) ?></td>
                                <td><?= htmlspecialchars($creance['Description']) ?></td>
                                <td><?= number_format($creance['Montant_Facture'], 2, ',', ' ') ?> €</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-md-6 table-section">
            <h3 class="page-header">Dettes Fournisseurs à Payer <small>(<?= date('d/m/Y') ?> au <?= date('d/m/Y', strtotime($dateFin)) ?>)</small></h3>
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Date d'échéance</th>
                            <th>Description</th>
                            <th>Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($dettes)): ?>
                        <tr><td colspan="3" class="text-center">Aucune dette fournisseur à l'échéance.</td></tr>
                    <?php else: ?>
                        <?php foreach ($dettes as $dette): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($dette['Date_Echeance'])) ?></td>
                                <td><?= htmlspecialchars($dette['Description']) ?></td>
                                <td><?= number_format($dette['Montant_Facture'], 2, ',', ' ') ?> €</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php require_once('../../templates/footer.php'); ?>