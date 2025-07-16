<?php
session_start();
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');

$titre = "Résultat de l'importation";
$feedback = $_SESSION['import_feedback'] ?? [];
unset($_SESSION['import_feedback']); // Clear feedback after displaying

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?= htmlspecialchars($titre) ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
</head>
<body>
    <div class="container">
        <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

        <?php if (!empty($feedback)): ?>
            <?php foreach ($feedback as $result): ?>
                <?php if ($result['success']): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($result['message']) ?></div>
                <?php else: ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($result['message']) ?></div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">Aucun résultat d'importation à afficher.</div>
        <?php endif; ?>

        <a href="index.php" class="btn btn-primary">Nouvelle importation</a>
        <a href="../ecritures/liste.php" class="btn btn-info">Voir les écritures</a>
    </div>
    <?php require_once('../../templates/footer.php'); ?>
</body>
</html>