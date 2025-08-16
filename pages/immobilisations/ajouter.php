<?php
// pages/immobilisations/ajouter.php
session_start();

// Vérifier si l'utilisateur est connecté en tant qu'administrateur
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Comptable') {
    header("Location: ../../index.php?error=Accès non autorisé");
    exit();
}

// Configuration et inclusions
$titre = 'Ajouter une Immobilisation';
$current_page = basename(__FILE__);

require_once '../../fonctions/database.php';
require_once '../../fonctions/immobilisations/gestion_immobilisations.php';

$message = null;
$message_type = null;

// Gérer la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'designation' => $_POST['designation'] ?? '',
        'numero_facture' => $_POST['numero_facture'] ?? '',
        'date_acquisition' => $_POST['date_acquisition'] ?? '',
        'montant_ht' => $_POST['montant_ht'] ?? ''
    ];

    // Validation simple des données
    if (empty($data['designation']) || empty($data['date_acquisition']) || empty($data['montant_ht'])) {
        $message = "Veuillez remplir tous les champs obligatoires.";
        $message_type = 'danger';
    } else {
        // Appeler la fonction d'ajout
        $resultat = ajouter_immobilisation($data);

        if ($resultat['success']) {
            // Redirection en cas de succès
            header("Location: index.php?success=" . urlencode($resultat['message']));
            exit();
        } else {
            $message = $resultat['message'];
            $message_type = 'danger';
        }
    }
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
</head>
<body>
<div class="container">
    <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

    <?php if ($message): ?>
        <div class="alert alert-<?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="post" action="ajouter.php">
        <div class="panel panel-default">
            <div class="panel-heading">Détails de l'immobilisation</div>
            <div class="panel-body">
                <div class="form-group">
                    <label for="designation">Désignation</label>
                    <input type="text" class="form-control" id="designation" name="designation" required>
                </div>
                <div class="form-group">
                    <label for="numero_facture">Numéro de facture</label>
                    <input type="text" class="form-control" id="numero_facture" name="numero_facture">
                </div>
                <div class="form-group">
                    <label for="date_acquisition">Date d'acquisition</label>
                    <input type="date" class="form-control" id="date_acquisition" name="date_acquisition" required>
                </div>
                <div class="form-group">
                    <label for="montant_ht">Montant HT</label>
                    <input type="number" class="form-control" id="montant_ht" name="montant_ht" step="0.01" required>
                </div>
            </div>
        </div>
        
        <div class="form-group text-center">
            <button type="submit" class="btn btn-success"><span class="glyphicon glyphicon-plus"></span> Enregistrer l'immobilisation</button>
            <a href="index.php" class="btn btn-default">Annuler</a>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<?php require_once('../../templates/footer.php'); ?>