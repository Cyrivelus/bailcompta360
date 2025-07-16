<?php
session_start();

// Check user authentication and authorization
// Only 'Admin' and 'Super_Admin' roles should ideally access this section
if (!isset($_SESSION['utilisateur_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'super_admin')) {
    header('Location: ../../login.php'); // Redirect to login or an unauthorized access page
    exit;
}

// Include necessary templates (header, navigation)
require_once('../../../templates/header.php');
require_once('../../../templates/navigation.php');

$pageTitle = 'Gestion des Données'; // Title for the page
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BailCompta 360 | <?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <style>
        .card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,.05);
            text-align: center;
        }
        .card h3 {
            margin-top: 0;
            color: #337ab7;
        }
        .card p {
            color: #555;
            margin-bottom: 15px;
        }
        .card .btn {
            width: 100%;
            padding: 10px 0;
        }
        .card-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            margin-top: 30px;
        }
        .card-item {
            flex: 1 1 calc(33% - 40px); /* 3 cards per row, accounting for gap */
            max-width: calc(33% - 40px);
            min-width: 280px; /* Minimum width for small screens */
        }
        @media (max-width: 992px) {
            .card-item {
                flex: 1 1 calc(50% - 40px); /* 2 cards per row on medium screens */
                max-width: calc(50% - 40px);
            }
        }
        @media (max-width: 768px) {
            .card-item {
                flex: 1 1 90%; /* 1 card per row on small screens */
                max-width: 90%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="page-header"><?= htmlspecialchars($pageTitle) ?></h2>

        <div class="card-container">
            <div class="card-item">
                <div class="card">
                    <h3>Sauvegarde & Restauration</h3>
                    <p>Gérez les sauvegardes de votre base de données et restaurez-les si nécessaire.</p>
                    <a href="backup_restore.php" class="btn btn-primary">Accéder</a>
                </div>
            </div>

            <div class="card-item">
                <div class="card">
                    <h3>Vérification d'Intégrité des Données</h3>
                    <p>Exécutez des vérifications pour assurer la cohérence et l'intégrité de vos données comptables.</p>
                    <a href="data_integrity_check.php" class="btn btn-info">Accéder</a>
                </div>
            </div>

            <div class="card-item">
                <div class="card">
                    <h3>Purge des Anciennes Données</h3>
                    <p>Supprimez les données historiques ou non pertinentes pour optimiser les performances de la base de données.</p>
                    <a href="purge_old_data.php" class="btn btn-warning">Accéder</a>
                </div>
            </div>
        </div>

    </div>
    <?php require_once('../../../templates/footer.php'); ?>
</body>
</html>