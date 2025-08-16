<?php
// pages/dossiers/index.php
session_start();

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur
// Cette vérification est essentielle pour protéger la page
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Comptable') {
    header("Location: ../../index.php?error=Accès non autorisé");
    exit();
}

// Configuration et inclusions
$titre = 'Gestion des Immobilisations';
$current_page = basename(__FILE__);

// Assurez-vous que les chemins sont corrects pour votre arborescence
require_once '../../fonctions/database.php';
require_once '../../fonctions/immobilisations/gestion_immobilisations.php'; // Ce fichier doit contenir les fonctions CRUD

$message = null;
$message_type = null;

// Gestion des messages de retour
if (isset($_GET['success'])) {
    $message = "L'opération a été effectuée avec succès.";
    $message_type = 'success';
} elseif (isset($_GET['error'])) {
    $message = "Une erreur est survenue lors de l'opération.";
    $message_type = 'danger';
}

// Récupération de toutes les immobilisations
try {
    // Supposition de la table et des colonnes.
    // Adaptez cette requête si vos tables ont des noms différents.
    $sql = "SELECT ID_Immobilisation, Designation, Date_Acquisition, Montant_HT, Numero_Facture FROM Immobilisations ORDER BY Date_Acquisition DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $immobilisations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Erreur lors de la récupération des immobilisations : " . $e->getMessage();
    $message_type = 'danger';
    error_log("Erreur SQL: " . $e->getMessage());
    $immobilisations = [];
}

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
    <style>
        .table-responsive { margin-top: 20px; }
        .btn-actions { margin-top: 20px; }
    </style>
</head>
<body>
<div class="container">
    <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="btn-actions">
        <a href="saisie.php" class="btn btn-primary">
            <span class="glyphicon glyphicon-plus"></span> Nouvelle Immobilisation
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
                        <th>Montant HT</th>
                        <th>Numéro de facture</th>
                        <th style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($immobilisations as $immobilisation): ?>
                        <tr>
                            <td><?= htmlspecialchars($immobilisation['ID_Immobilisation']) ?></td>
                            <td><?= htmlspecialchars($immobilisation['Designation']) ?></td>
                            <td><?= date('d/m/Y', strtotime($immobilisation['Date_Acquisition'])) ?></td>
                            <td><?= number_format($immobilisation['Montant_HT'], 2, ',', ' ') ?></td>
                            <td><?= htmlspecialchars($immobilisation['Numero_Facture']) ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="details.php?id=<?= $immobilisation['ID_Immobilisation'] ?>" class="btn btn-info" title="Détails">
                                        <span class="glyphicon glyphicon-eye-open"></span>
                                    </a>
                                    <a href="modifier.php?id=<?= $immobilisation['ID_Immobilisation'] ?>" class="btn btn-warning" title="Modifier">
                                        <span class="glyphicon glyphicon-pencil"></span>
                                    </a>
                                    <a href="supprimer.php?id=<?= $immobilisation['ID_Immobilisation'] ?>"
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