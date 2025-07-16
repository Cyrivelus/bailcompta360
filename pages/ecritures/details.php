<?php
// pages/ecritures/details.php

// Initialisation
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

header('Content-Type: text/html; charset=utf-8');

// Titre de la page
$titre = "Details de l'ecriture comptable";
$current_page = basename(__FILE__);

// Inclusions
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_ecritures.php';

// V�rification de l'ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: liste.php?error=" . urlencode("ID d'�criture non valide ou manquant."));
    exit;
}

$idEcriture = (int) $_GET['id'];
$erreur = null;
$pdo = null; // Initialisation de $pdo

try {
    // Connexion � la base de donn�es
if (!isset($pdo) || !$pdo instanceof PDO) {
    // MySQL connection parameters for localhost
    $host = 'localhost'; // Usually 'localhost' for local development
    $dbName = 'BD_AD_SCE'; // Replace with your actual MySQL database name
    $username = 'root'; // Your MySQL username (e.g., 'root' for XAMPP/WAMP)
    $password = ''; // Your MySQL password (often empty for XAMPP/WAMP 'root' user)
    $charset = 'utf8mb4'; // Recommended charset for broad character support

    $dsn = "mysql:host=$host;dbname=$dbName;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Essential: Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch results as associative arrays by default
        PDO::ATTR_EMULATE_PREPARES   => false,                  // For better security and performance with prepared statements
    ];

    try {
        $pdo = new PDO($dsn, $username, $password, $options);
    } catch (PDOException $e) {
        $erreur = "Erreur de connexion à la base de données : " . $e->getMessage();
        error_log("Erreur de connexion (details.php) : " . $e->getMessage());

        // Display error message and exit cleanly
        require_once('../../templates/header.php');
        require_once('../../templates/navigation.php');
        echo "<div class='container'><div class='alert alert-danger'>$erreur</div></div>";
        require_once('../../templates/footer.php');
        exit();
    }
}

    // R�cup�ration de l'�criture avec tous les champs
    $sql = "SELECT * FROM Ecritures WHERE ID_Ecriture = " . $idEcriture; // Injection possible
    $stmt = $pdo->query($sql); // Pas de prepare
    if ($stmt === false) {
        $erreur = "Erreur lors de la r�cup�ration de l'�criture : " . print_r($pdo->errorInfo(), true);
        error_log("Erreur SQL (details.php) : " . $erreur);
        header("Location: liste.php?error=" . urlencode("Erreur lors de la r�cup�ration de l'�criture."));
        exit;
    }
    $ecriture = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ecriture) {
        header("Location: liste.php?error=" . urlencode("�criture comptable non trouv�e."));
        exit;
    }

} catch (Exception $e) {
    $erreur = "Erreur lors de la r�cup�ration des d�tails : " . $e->getMessage();
    error_log("Erreur (details.php) : " . $e->getMessage());
}

// Affichage du template
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
	<br>
	&nbsp;
    <title><?= htmlspecialchars($TITRE_PAGE) ?> | BailCompta 360</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
	<link rel="stylesheet" href="../../css/select2.min.css">
	<link rel="stylesheet" href="../../css/select2-bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-theme/0.1.0-beta.10/select2-bootstrap.min.css" rel="stylesheet" />
    <style>
        .select2-container--bootstrap .select2-selection--single {
            height: 34px;
            padding: 6px 12px;
        }

        .select2-container--bootstrap .select2-selection--single .select2-selection__rendered {
            line-height: 20px;
        }

        .select2-container--bootstrap .select2-selection--single .select2-selection__arrow {
            height: 32px;
        }

        .select2-container {
            width: 100% !important;
        }

        .panel {
            margin-bottom: 20px;
        }

        .page-header {
            margin-top: 0;
        }

        .btn-actions {
            margin-top: 20px;
        }

        .total-field-display {
            font-weight: bold;
            background-color: #eee;
        }

        .form-control[readonly] {
            background-color: #eee;
            opacity: 1;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h1 class="mb-4"><?= htmlspecialchars($titre) ?></h1>

    <?php if ($erreur): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div>
    <?php else: ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4>D&eacutetails complets de l'&eacutecriture #<?= htmlspecialchars($ecriture['ID_Ecriture']) ?></h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="thead-dark">
                            <tr>
                                <th>Champ</th>
                                <th>Valeur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>ID_Ecriture</strong></td>
                                <td><?= htmlspecialchars($ecriture['ID_Ecriture']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Date_Saisie</strong></td>
                                <td><?= !empty($ecriture['Date_Saisie']) ? date('d/m/Y H:i:s', strtotime($ecriture['Date_Saisie'])) : 'N/A' ?></td>
                            </tr>
                            <tr>
                                <td><strong>Description</strong></td>
                                <td><?= htmlspecialchars($ecriture['Description'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Montant_Total</strong></td>
                                 <td><?= isset($ecriture['Montant_Total']) ? number_format($ecriture['Montant_Total'], 2, ',', ' ') : '0,00' ?></td>
                            </tr>
                            <tr>
                                <td><strong>ID_Journal</strong></td>
                                <td><?= htmlspecialchars($ecriture['ID_Journal'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Cde</strong></td>
                                <td><?= htmlspecialchars($ecriture['Cde'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                 <td><strong>NumeroAgenceSCE</strong></td>
                                 <td><?= htmlspecialchars($ecriture['NumeroAgenceSCE'] ?? 'N/A') ?></td>
                             </tr>
                            <tr>
                                <td><strong>libelle2</strong></td>
                                <td><?= htmlspecialchars($ecriture['libelle2'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td><strong>NomUtilisateur</strong></td>
                                <td><?= htmlspecialchars($ecriture['NomUtilisateur'] ?? 'N/A') ?></td>
                            </tr>
                            <tr>
                                <td><strong>Mois</strong></td>
                                <td><?= htmlspecialchars($ecriture['Mois'] ?? 'N/A') ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php endif; ?>
<div class="text-center mb-4">
    <button type="button" class="btn btn-info mr-2" onclick="history.back()">
        <i class="fas fa-undo"></i> Retour
    </button>

    <a href="liste.php" class="btn btn-secondary mr-2">
        <i class="fas fa-arrow-left"></i> Liste des &eacutecritures
    </a>
    <a href="modifier.php?id=<?= $idEcriture ?>" class="btn btn-primary">
        <i class="fas fa-edit"></i> Consulter cette &eacutecriture
    </a>
</div>
</div>

<?php require_once('../../templates/footer.php'); ?>
