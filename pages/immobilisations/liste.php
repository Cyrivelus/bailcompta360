<?php
// pages/immobilisations/liste.php
session_start();

// Vérifier si l'utilisateur est connecté en tant qu'administrateur
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Comptable') {
    header("Location: ../../index.php?error=Accès non autorisé");
    exit();
}

// Configuration et inclusions
$titre = 'Liste des Immobilisations';
$current_page = basename(__FILE__);

require_once '../../fonctions/database.php';
require_once '../../fonctions/immobilisations/gestion_immobilisations.php';

$immobilisations = get_all_immobilisations();

require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($titre) ?> | BailCompta 360</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
</head>
<body>
<div class="container">
    <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

    <div class="btn-actions" style="margin-bottom: 20px;">
        <a href="saisie.php" class="btn btn-primary">
            <span class="glyphicon glyphicon-plus"></span> Nouvelle Immobilisation
        </a>
        <a href="index.php" class="btn btn-info">
            <span class="glyphicon glyphicon-home"></span> Retour à l'accueil
        </a>
    </div>

    <?php if (!empty($immobilisations)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Désignation</th>
                        <th>Date d'acquisition</th>
                        <th>Valeur d'origine</th>
                        <th>Taux d'amortissement</th>
                        <th style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($immobilisations as $immo): ?>
                        <tr>
                            <td><?= htmlspecialchars($immo['ID_Immobilisation']) ?></td>
                            <td><?= htmlspecialchars($immo['Designation']) ?></td>
                            <td><?= date('d/m/Y', strtotime($immo['Date_Acquisition'])) ?></td>
                            <td><?= number_format($immo['Montant_HT'], 2, ',', ' ') ?></td>
                            <td>
                                <?php
                                // Pour l'exemple, nous supposons une durée d'amortissement de 5 ans (soit 20%).
                                // Dans une application réelle, le taux serait stocké dans la base de données.
                                $taux_amortissement = '20 %';
                                echo $taux_amortissement;
                                ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="details.php?id=<?= $immo['ID_Immobilisation'] ?>" class="btn btn-info" title="Détails">
                                        <span class="glyphicon glyphicon-eye-open"></span>
                                    </a>
                                    <a href="modifier.php?id=<?= $immo['ID_Immobilisation'] ?>" class="btn btn-warning" title="Modifier">
                                        <span class="glyphicon glyphicon-pencil"></span>
                                    </a>
                                    <a href="supprimer.php?id=<?= $immo['ID_Immobilisation'] ?>"
                                       class="btn btn-danger"
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette immobilisation ?')"
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
        <div class="alert alert-info">Aucune immobilisation trouvée dans la base de données.</div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<?php require_once('../../templates/footer.php'); ?>