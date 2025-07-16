<?php
session_start();
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_budgets.php'; // Include your budget functions

$titre = 'Rapport Budget vs Réel';

$pdo = getPDO();

$reportStartDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$reportEndDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

$budgetReport = [];
if ($reportStartDate && $reportEndDate) {
    $budgetReport = getBudgetPerformanceReport($pdo, $reportStartDate, $reportEndDate);
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

        <form action="" method="GET" class="form-inline mb-4">
            <div class="form-group">
                <label for="start_date">Du :</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($reportStartDate) ?>">
            </div>
            <div class="form-group">
                <label for="end_date">Au :</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($reportEndDate) ?>">
            </div>
            <button type="submit" class="btn btn-primary ml-2">Afficher le rapport</button>
        </form>

        <?php if (empty($budgetReport)): ?>
            <div class="alert alert-info">Aucune donnée budgétaire ou réelle trouvée pour la période sélectionnée.</div>
        <?php else: ?>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Compte</th>
                        <th>Période Budget</th>
                        <th class="text-right">Montant Budgeté</th>
                        <th class="text-right">Montant Réel</th>
                        <th class="text-right">Écart</th>
                        <th class="text-right">% Écart</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalBudget = 0;
                    $totalActual = 0;
                    $totalVariance = 0;
                    ?>
                    <?php foreach ($budgetReport as $item): ?>
                        <?php
                        $totalBudget += $item['budget_amount'];
                        $totalActual += $item['actual_amount'];
                        $totalVariance += $item['variance'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($item['numero_compte'] . ' - ' . $item['nom_compte']) ?></td>
                            <td><?= htmlspecialchars(date('d/m/Y', strtotime($item['budget_start_date']))) ?> au <?= htmlspecialchars(date('d/m/Y', strtotime($item['budget_end_date']))) ?></td>
                            <td class="text-right"><?= htmlspecialchars(number_format($item['budget_amount'], 2, ',', ' ')) ?></td>
                            <td class="text-right"><?= htmlspecialchars(number_format($item['actual_amount'], 2, ',', ' ')) ?></td>
                            <td class="text-right" style="color: <?= $item['variance'] < 0 ? 'red' : 'green' ?>;">
                                <?= htmlspecialchars(number_format($item['variance'], 2, ',', ' ')) ?>
                            </td>
                            <td class="text-right" style="color: <?= $item['variance_percentage'] < 0 ? 'red' : 'green' ?>;">
                                <?= htmlspecialchars(number_format($item['variance_percentage'], 2, ',', ' ')) ?>%
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2" class="text-right">Totaux</th>
                        <th class="text-right"><?= htmlspecialchars(number_format($totalBudget, 2, ',', ' ')) ?></th>
                        <th class="text-right"><?= htmlspecialchars(number_format($totalActual, 2, ',', ' ')) ?></th>
                        <th class="text-right" style="color: <?= $totalVariance < 0 ? 'red' : 'green' ?>;">
                            <?= htmlspecialchars(number_format($totalVariance, 2, ',', ' ')) ?>
                        </th>
                        <th class="text-right">
                            <?php
                            $totalVariancePercentage = ($totalBudget != 0) ? ($totalVariance / $totalBudget * 100) : 0;
                            ?>
                            <span style="color: <?= $totalVariancePercentage < 0 ? 'red' : 'green' ?>;">
                                <?= htmlspecialchars(number_format($totalVariancePercentage, 2, ',', ' ')) ?>%
                            </span>
                        </th>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>

    </div>
    <?php require_once('../../templates/footer.php'); ?>
</body>
</html>