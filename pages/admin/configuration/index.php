<?php
// pages/admin/habilitations/index.php

// Démarrer la session pour la gestion de l'authentification
session_start();

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Admin') {
    // Rediriger si non autorisé
    header("Location: ../../../index.php?error=Accès non autorisé");
    exit();
}

// Inclure les fichiers nécessaires
require_once('../../../fonctions/database.php');
require_once('../../../fonctions/gestion_habilitations.php');

// Définir le titre de la page
$title = "Gestion des Habilitations";
$admin_style = true;

// Inclure l'en-tête
include('../../../templates/header.php');

// Inclure la navigation
include('../../../templates/navigation.php');

// Lire la configuration depuis le fichier config.ini
$configFile = '../../../fonctions/config/config.ini';
$config = parse_ini_file($configFile, true);

// Vérifier si le fichier de configuration a été lu correctement
if ($config === false) {
    // Gérer l'erreur de lecture du fichier de configuration
    $errorMessage = "Erreur lors de la lecture du fichier de configuration.";
    // Vous pouvez choisir d'afficher un message à l'utilisateur ou de logger l'erreur
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?= htmlspecialchars($titre) ?></title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Configuration Générale de l'Application</h1>
    </div>

    <?php
    // Afficher les messages d'erreur ou de succès s'il y en a
    if (isset($_GET['error'])) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($_GET['error']) . '</div>';
    }
    if (isset($_GET['success'])) {
        echo '<div class="alert alert-success">' . htmlspecialchars($_GET['success']) . '</div>';
    }

    // Afficher un message d'erreur si la lecture du fichier de configuration a échoué
    if (isset($errorMessage)) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($errorMessage) . '</div>';
    }
    ?>

    <h2>Paramètres Généraux</h2>
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    Nom de l'Application
                </div>
                <div class="card-body">
                    <p class="card-text"><?php echo isset($config['app']['name']) ? htmlspecialchars($config['app']['name']) : 'Non défini'; ?></p>
                    <a href="modifier_parametre.php?param=app_name" class="btn btn-sm btn-outline-secondary">Modifier</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    Version de l'Application
                </div>
                <div class="card-body">
                    <p class="card-text"><?php echo isset($config['app']['version']) ? htmlspecialchars($config['app']['version']) : 'Non défini'; ?></p>
                </div>
            </div>
        </div>
    </div>

    <hr class="my-4">

    <h2>Configuration de la Base de Données</h2>
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    Serveur de la Base de Données
                </div>
                <div class="card-body">
                    <p class="card-text"><?php echo isset($config['database']['host']) ? htmlspecialchars($config['database']['host']) : 'Non défini'; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    Nom de la Base de Données
                </div>
                <div class="card-body">
                    <p class="card-text"><?php echo isset($config['database']['dbname']) ? htmlspecialchars($config['database']['dbname']) : 'Non défini'; ?></p>
                </div>
            </div>
        </div>
    </div>

    </main>

<?php 
// Inclure le pied de page
include('../../../templates/footer.php'); 
?>