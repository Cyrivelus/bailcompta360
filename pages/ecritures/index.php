<?php
// pages/ecritures/index.php
session_start();

// Configuration et inclusions
$titre = 'Gestion des écritures Comptables';
$current_page = basename($_SERVER['PHP_SELF']);

require_once '../../fonctions/database.php'; // This should now establish the $pdo connection
require_once '../../fonctions/gestion_utilisateurs.php';
require_once '../../fonctions/gestion_ecritures.php';

// Check if $pdo is available from database.php
if (!isset($pdo) || !$pdo instanceof PDO) {
    // This block should ideally be handled by database.php, but serves as a fallback.
    error_log("Erreur critique: La connexion PDO n'est pas disponible dans ecritures/index.php.");
    header("Location: ../../index.php?error=" . urlencode("Une erreur interne est survenue. Connexion BD manquante."));
    exit();
}

// Récupérer les écritures avec une jointure pour le journal
// MySQL uses LIMIT instead of TOP
$sql = "SELECT
            e.ID_Ecriture,
            e.Date_Saisie,
            e.Description,
            e.Montant_Total,
            e.Numero_Piece,
            j.Lib AS Journal_Libelle
        FROM
            Ecritures e
        LEFT JOIN
            JAL j ON e.Cde = j.Cde
        ORDER BY
            e.Date_Saisie ASC
        LIMIT 10"; // Changed TOP 10 to LIMIT 10 for MySQL

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $ecritures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = "Erreur lors de la récupération des écritures : " . $e->getMessage();
    error_log("Erreur SQL dans ecritures/index.php: " . $e->getMessage()); // Log the specific SQL error
}

// Gestion des messages
$successMessage = null;
if (isset($_GET['success_saisie'])) $successMessage = "L'écriture a été enregistrée avec succès.";
if (isset($_GET['success_update'])) $successMessage = "L'écriture a été mise à jour avec succès.";
if (isset($_GET['delete_success'])) $successMessage = "L'écriture a été supprimée avec succès.";

require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <br>
    &nbsp;
    <title><?= htmlspecialchars($titre) ?> | BailCompta 360</title> <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
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
<div class="container">
    <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

    <?php if (isset($successMessage)): ?>
        <div class="alert alert-success"><?= $successMessage ?></div>
    <?php endif; ?>

    <?php if (isset($errorMessage)): ?>
        <div class="alert alert-danger"><?= $errorMessage ?></div>
    <?php endif; ?>

    <div class="action-buttons" style="margin-bottom: 20px;">
        <a href="saisie.php" class="btn btn-primary">
            <span class="glyphicon glyphicon-plus"></span> Nouvelle écriture
        </a>
        <a href="liste.php" class="btn btn-info">
            <span class="glyphicon glyphicon-list"></span> Liste complète
        </a>
    </div>

    <h3>10 dernières écritures</h3>

    <?php if (!empty($ecritures)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Journal</th>
                        <th>N° Pièce</th>
                        <th>Description</th>
                     
                        <th style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ecritures as $ecriture): ?>
                        <tr>
                            <td><?= $ecriture['ID_Ecriture'] ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($ecriture['Date_Saisie'])) ?></td>
                            <td><?= htmlspecialchars($ecriture['Journal_Libelle'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($ecriture['Numero_Piece'] ?? '') ?></td>
                            <td><?= htmlspecialchars($ecriture['Description']) ?></td>
                           
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="details.php?id=<?= $ecriture['ID_Ecriture'] ?>" class="btn btn-info" title="Détails">
                                        <span class="glyphicon glyphicon-eye-open"></span>
                                    </a>
                                    <a href="modifier.php?id=<?= $ecriture['ID_Ecriture'] ?>" class="btn btn-warning" title="Modifier">
                                       <span class="glyphicon glyphicon-folder-open"></span>
                                    </a>
                                    <a href="supprimer.php?id=<?= $ecriture['ID_Ecriture'] ?>"
                                       class="btn btn-danger"
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette écriture (ID: <?= $ecriture['ID_Ecriture'] ?>) ?')"
                                       title="Supprimer">
                                        <span class="glyphicon glyphicon-trash"></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">Aucune écriture trouvée dans la base de données.</div>
    <?php endif; ?>
</div>

<?php
require_once('../../templates/footer.php');
?>