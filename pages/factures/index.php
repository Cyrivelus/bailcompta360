<?php
// pages/ecritures/index.php
session_start();

// Configuration et inclusions
$titre = 'Gestion des factures';
$current_page = basename($_SERVER['PHP_SELF']);

require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_utilisateurs.php';
require_once '../../fonctions/gestion_ecritures.php';

// Connexion à la base de données (assuming this is part of database.php or is missing)
// If it's missing, you need to add it here, or ensure database.php establishes $pdo.



// Récupérer les écritures avec une jointure pour le journal
$sql = "SELECT TOP 10   -- TOP 10 needs to be right after SELECT
            f.ID_Facture,
            f.Date_Emission,
            f.Commentaire,
            f.Montant_TTC,
            f.Numero_Bon_Commande,
            f.ID_Journal AS ID_Journal
        FROM
            Factures f
        LEFT JOIN
            Ecritures e ON f.ID_Ecriture_Comptable = e.ID_Ecriture
        ORDER BY
            f.Date_Emission DESC"; // ORDER BY comes after FROM/JOIN clauses

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $factures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = "Erreur lors de la récupération des factures: " . $e->getMessage();
}

// Gestion des messages
$successMessage = null;
if (isset($_GET['success_facture'])) $successMessage = "La facture a été enregistrée avec succès.";
if (isset($_GET['success_facture'])) $successMessage = "La facture a été mise à jour avec succès.";
if (isset($_GET['delete_success'])) $successMessage = "La facture a été supprimée avec succès.";

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
<div class="container">
    <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

    <?php if (isset($successMessage)): ?>
        <div class="alert alert-success"><?= $successMessage ?></div>
    <?php endif; ?>

    <?php if (isset($errorMessage)): ?>
        <div class="alert alert-danger"><?= $errorMessage ?></div>
    <?php endif; ?>

    <div class="action-buttons" style="margin-bottom: 20px;">
        <a href="integration.php" class="btn btn-primary">
            <span class="glyphicon glyphicon-plus"></span> Nouvelle facture
        </a>
        <a href="listes_factures.php" class="btn btn-info">
            <span class="glyphicon glyphicon-list"></span> Liste complète
        </a>
    </div>

    <h3>10 dernières factures</h3>
    
    <?php if (!empty($factures)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Journal</th>
                        <th>Numero_Bon_Commande</th>
                        <th>Commentaire</th>
                        <th class="text-right">Montant_TTC</th>
                        <th style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($factures as $facture): ?>
                        <tr>
                            <td><?= $facture['ID_Facture'] ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($facture['Date_Emission'])) ?></td>
                            <td><?= htmlspecialchars($facture['ID_Journal'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($facture['Numero_Bon_Commande'] ?? '') ?></td>
                            <td><?= htmlspecialchars($facture['Commentaire']) ?></td>
                            <td class="text-right"><?= number_format($facture['Montant_TTC'], 2, ',', ' ') ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="listes_factures.php?id=<?= $facture['ID_Facture'] ?>" class="btn btn-info" title="Détails">
                                        <span class="glyphicon glyphicon-eye-open"></span>
                                    </a>
                                    <a href="modifier_facture.php?id=<?= $facture['ID_Facture']  ?>" class="btn btn-warning" title="Modifier">
                                        <span class="glyphicon glyphicon-pencil"></span>
                                    </a>
                                    <a href="supprimer_facture.php?id=<?= $facture['ID_Facture'] ?>" 
                                       class="btn btn-danger" 
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette facture (ID: <?= $facture['ID_Facture'] ?>) ?')"
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
        <div class="alert alert-info">Aucune facture trouvée dans la base de données.</div>
    <?php endif; ?>
</div>

<?php
require_once('../../templates/footer.php');
?>