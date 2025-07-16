<?php
session_start();
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_reports.php'; // Include your new functions file

$titre = 'Bilan';

// Assume $pdo is initialized from database.php

$asOfDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d'); // Default to today

// Get the data using your function
$balanceData = getBalanceSheetData($pdo, $asOfDate);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?= htmlspecialchars($titre) ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/tableau.css">
    <link rel="stylesheet" href="../../css/print.css" media="print">
	<link rel="stylesheet" href="../../css/bootstrap.min.css" media="print">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <div class="container">
        <h2 class="page-header"><?= htmlspecialchars($titre) ?> au <?= htmlspecialchars(date('d/m/Y', strtotime($asOfDate))) ?></h2>

        <form action="" method="GET" class="form-inline mb-4">
            <div class="form-group">
                <label for="date">Date du Bilan :</label>
                <input type="date" class="form-control" id="date" name="date" value="<?= htmlspecialchars($asOfDate) ?>">
            </div>
            <button type="submit" class="btn btn-primary ml-2">Afficher</button>
            <button type="button" class="btn btn-info ml-2" onclick="window.print()">Imprimer</button>
        </form>

        <h3>Actifs</h3>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Numéro de Compte</th>
                    <th>Nom du Compte</th>
                    <th class="text-right">Solde</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($balanceData['actifs'])): ?>
                    <tr><td colspan="3" class="text-center">Aucun actif trouvé.</td></tr>
                <?php else: ?>
                    <?php foreach ($balanceData['actifs'] as $actif): ?>
                        <tr>
                            <td><?= htmlspecialchars($actif['numero']) ?></td>
                            <td><?= htmlspecialchars($actif['nom']) ?></td>
                            <td class="text-right"><?= htmlspecialchars($actif['solde']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2" class="text-right">Total Actifs</th>
                    <th class="text-right"><?= htmlspecialchars($balanceData['total_actifs']) ?></th>
                </tr>
            </tfoot>
        </table>

        <h3>Passifs et Capitaux Propres</h3>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Numéro de Compte</th>
                    <th>Nom du Compte</th>
                    <th class="text-right">Solde</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($balanceData['passifs']) && empty($balanceData['capitaux_propres'])): ?>
                    <tr><td colspan="3" class="text-center">Aucun passif ou capitaux propres trouvés.</td></tr>
                <?php else: ?>
                    <tr><td colspan="3"><strong>Passifs</strong></td></tr>
                    <?php foreach ($balanceData['passifs'] as $passif): ?>
                        <tr>
                            <td><?= htmlspecialchars($passif['numero']) ?></td>
                            <td><?= htmlspecialchars($passif['nom']) ?></td>
                            <td class="text-right"><?= htmlspecialchars($passif['solde']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr><td colspan="3"><strong>Capitaux Propres</strong></td></tr>
                    <?php foreach ($balanceData['capitaux_propres'] as $capitaux): ?>
                        <tr>
                            <td><?= htmlspecialchars($capitaux['numero']) ?></td>
                            <td><?= htmlspecialchars($capitaux['nom']) ?></td>
                            <td class="text-right"><?= htmlspecialchars($capitaux['solde']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2" class="text-right">Total Passifs et Capitaux Propres</th>
                    <th class="text-right"><?= htmlspecialchars($balanceData['total_passifs_capitaux_propres']) ?></th>
                </tr>
            </tfoot>
        </table>

        <div class="alert alert-info text-center mt-4">
            <p><strong>Bilan équilibré :</strong> <?= ($balanceData['total_actifs'] === $balanceData['total_passifs_capitaux_propres']) ? 'Oui' : 'Non' ?></p>
        </div>

    </div>
    <?php require_once('../../templates/footer.php'); ?>
</body>
</html>