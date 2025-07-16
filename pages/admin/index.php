<?php
// pages/admin/index.php

// Démarrer la session pour gérer l'authentification
session_start();

// Vérifier si l'utilisateur est connecté en tant qu'administrateur
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Admin') {
    // Si l'utilisateur n'est pas connecté ou n'est pas un administrateur,
    // le rediriger vers la page de connexion ou une page d'erreur.
    header("Location: ../index.php?error=Accès non autorisé");
    exit();
}

// Inclure les fichiers nécessaires
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_utilisateurs.php';

$titre = 'Tableau de Bord de l\'Administration';
$current_page = basename($_SERVER['PHP_SELF']);

require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Tableau de bord administrateur</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/tableau.css">
    <style>
        .dashboard-container {
            padding: 20px 0;
        }
        .panel {
            height: 100%;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            border-radius: 5px;
        }
        .panel:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .panel-heading {
            padding: 15px;
            border-radius: 5px 5px 0 0 !important;
        }
        .panel-body {
            padding: 20px;
            height: calc(100% - 60px);
            display: flex;
            flex-direction: column;
        }
        .panel-body p {
            flex-grow: 1;
            margin-bottom: 15px;
            color: #555;
        }
        .panel-title {
            font-size: 16px;
            font-weight: bold;
            color: white;
        }
        .glyphicon {
            margin-right: 8px;
        }
        .btn-block {
            border-radius: 4px;
            padding: 8px 0;
        }
        .row {
            margin-bottom: 20px;
        }
    </style>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<div class="container dashboard-container">
    <h2 class="page-header">Tableau de bord administrateur</h2>

    <div class="row">
        <!-- Première ligne -->
        <div class="col-md-3 col-sm-6">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <span class="glyphicon glyphicon-user"></span> Gestion des Utilisateurs
                    </h3>
                </div>
                <div class="panel-body">
                    <p>Accédez à la gestion des comptes utilisateurs, création, modification, suppression.</p>
                    <a href="utilisateurs/" class="btn btn-info btn-block">
                        <span class="glyphicon glyphicon-cog"></span> Gérer les Utilisateurs
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <span class="glyphicon glyphicon-briefcase"></span> Gestion des Profils
                    </h3>
                </div>
                <div class="panel-body">
                    <p>Gérez les différents profils d'accès et leurs permissions au sein de l'application.</p>
                    <a href="profils/" class="btn btn-success btn-block">
                        <span class="glyphicon glyphicon-cog"></span> Gérer les Profils
                    </a>
                </div>
            </div>
        </div>
		
		 <div class="col-md-3 col-sm-6">
    <div class="panel panel-info">
        <div class="panel-heading">
            <h3 class="panel-title">
                <span class="glyphicon glyphicon-lock"></span> Gestion des Habilitations
            </h3>
        </div>
        <div class="panel-body">
            <p>Gérez les différents profils d'accès et leurs permissions au sein de l'application.</p>
            <a href="habilitations/" class="btn btn-info btn-block">
                <span class="glyphicon glyphicon-wrench"></span> Gérer les Habilitations
            </a>
        </div>
    </div>
</div>

<div class="col-md-3 col-sm-6">
    <div class="panel panel-warning">
        <div class="panel-heading">
            <h3 class="panel-title">
                <span class="glyphicon glyphicon-cog"></span> Configuration
            </h3>
        </div>
        <div class="panel-body">
            <p>Gérez les différentes configurations au sein de l'application.</p>
            <a href="configuration/" class="btn btn-warning btn-block">
                <span class="glyphicon glyphicon-cog"></span> Accéder à la Configuration
            </a>
        </div>
    </div>
</div>

       

       
    </div>

  
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script>
    $(document).ready(function() {
        // Animation au survol des panels
        $('.panel').hover(
            function() {
                $(this).css('cursor', 'pointer');
            },
            function() {
                $(this).css('cursor', 'default');
            }
        );
    });
</script>

<?php
require_once('../../templates/footer.php');
?>
