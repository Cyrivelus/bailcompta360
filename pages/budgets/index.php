<?php
// pages/budget/index.php

// Initialisation et configuration
header('Content-Type: text/html; charset=utf-8');
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
session_start(); // Start session for user authentication/authorization

// Inclusions des fichiers nécessaires
require_once '../../fonctions/database.php'; // This connects to the DB and sets $pdo
require_once '../../fonctions/gestion_utilisateurs.php'; 
// require_once '../../fonctions/budget_functions.php'; // You might want to create this later

// Vérification de l'authentification de l'utilisateur (exemple)
// if (!est_utilisateur_connecte()) { // Assuming this function exists in gestion_utilisateurs.php
//     header('Location: ../../login.php'); // Redirect to login page
//     exit();
// }

// Définition des variables pour le template
$titre = 'Gestion des Budgets';
$current_page = basename(__FILE__); // Used for navigation highlighting

// --- Récupération de Données pour l'Aperçu (Optionnel) ---
$current_year = date('Y');
$total_budget_current_year = 0;
$budget_error = ''; // Initialize error message for budget preview

try {
    // For MySQL/MariaDB, use IFNULL or COALESCE instead of ISNULL
    $stmt = $pdo->prepare("SELECT IFNULL(SUM(Montant_Budgetise), 0) AS total_budget FROM budget WHERE Annee_Budgetaire = :current_year");
    $stmt->bindParam(':current_year', $current_year, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $total_budget_current_year = (float)$result['total_budget'];
    }
} catch (PDOException $e) {
    error_log("Erreur PDO lors de la récupération du budget annuel dans pages/budget/index.php : " . $e->getMessage());
    $budget_error = "Impossible de charger l'aperçu budgétaire. Erreur: " . $e->getMessage(); // More detailed error for debugging
    // You might also set a generic error message for the user:
    // $budget_error_display = "Impossible de charger l'aperçu budgétaire."; 
}

// -----------------------------------------------------------
// 6. Affichage de la Vue
// -----------------------------------------------------------
require_once('../../templates/header.php'); // Path corrected
require_once('../../templates/navigation.php'); // Path corrected (and now $pdo should be available)
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?= htmlspecialchars($titre) ?></title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <style>
        .budget-dashboard-container { padding: 20px 0; }
        .page-header { border-bottom: 1px solid #eee; padding-bottom: 9px; margin: 20px 0 30px; }
        .panel { margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 8px; }
        .panel-heading { padding: 15px; border-bottom: 1px solid transparent; border-top-left-radius: 7px; border-top-right-radius: 7px; }
        .panel-title { margin: 0; font-size: 18px; font-weight: bold; color: #333; }
        .panel-body { padding: 20px; text-align: center; }
        .quick-link-btn { font-size: 1.2em; padding: 15px 25px; margin: 10px; border-radius: 5px; }
        .kpi-box {
            background-color: #f0f8ff; /* Light blue background */
            border: 1px solid #d4edda;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .kpi-value { font-size: 2.5em; font-weight: bold; color: #007bff; margin-bottom: 5px; }
        .kpi-label { font-size: 1.1em; color: #555; text-transform: uppercase; }
    </style>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>

<div class="container budget-dashboard-container">
    <h2 class="page-header">Module de Gestion Budgétaire</h2>

    <?php if (isset($db_error)): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($db_error) ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6 col-md-offset-3">
            <div class="kpi-box">
                <div class="kpi-value"><?= number_format($total_budget_current_year, 2, ',', ' ') ?> XAF</div>
                <div class="kpi-label">Budget Global bailcompta pour <?= $current_year ?></div>
                <?php if (!empty($budget_error)): ?>
                    <p class="text-danger small mt-2"><?= htmlspecialchars($budget_error) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <h3 class="page-header">Fonctionnalités Budgétaires</h3>
    <div class="row text-center">
        <div class="col-md-4 col-sm-6">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">Créer un Nouveau Budget</h3>
                </div>
                <div class="panel-body">
                    <p>Définissez les objectifs financiers pour vos revenus et dépenses.</p>
                    <a href="create_budget.php" class="btn btn-primary quick-link-btn">
                        <span class="glyphicon glyphicon-plus"></span> Créer un Budget
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h3 class="panel-title">Suivi et Performance Budgétaire</h3>
                </div>
                <div class="panel-body">
                    <p>Comparez les prévisions aux réalisations et analysez les écarts.</p>
                    <a href="view_budget_performance.php" class="btn btn-success quick-link-btn">
                        <span class="glyphicon glyphicon-stats"></span> Voir la Performance
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">Prévisions et Simulations</h3>
                </div>
                <div class="panel-body">
                    <p>Explorez les tendances futures et simulez différents scénarios.</p>
                    <a href="forecasts.php" class="btn btn-info quick-link-btn">
                        <span class="glyphicon glyphicon-cloud"></span> Prévisions
                    </a>
                </div>
            </div>
        </div>
    </div>
    
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<?php
require_once('../../templates/footer.php'); // Path corrected
?>
</body>
</html>