<?php
// pages/immobilisations/modifier.php
session_start();

// Vérifier si l'utilisateur est connecté en tant qu'administrateur
// J'ai remis 'Admin' car c'était la convention du code initial.
// Si vous utilisez 'Comptable', assurez-vous que c'est le bon rôle.
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../../index.php?error=Accès non autorisé");
    exit();
}

// Configuration et inclusions
$titre = 'Modifier une Immobilisation';
$current_page = basename(__FILE__);

require_once '../../fonctions/database.php';
require_once '../../fonctions/immobilisations/gestion_immobilisations.php';

$message = null;
$message_type = null;
$immobilisation = null;

// Gérer la soumission du formulaire (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $data = [
        'designation' => $_POST['designation'] ?? '',
        'numero_facture' => $_POST['numero_facture'] ?? '',
        'date_acquisition' => $_POST['date_acquisition'] ?? '',
        'montant_ht' => $_POST['montant_ht'] ?? ''
    ];

    if ($id && !empty($data['designation']) && !empty($data['date_acquisition']) && !empty($data['montant_ht'])) {
        $resultat = modifier_immobilisation($id, $data);
        if ($resultat['success']) {
            // Redirection vers l'index avec un message de succès
            header("Location: index.php?success=1");
            exit();
        } else {
            // Afficher le message d'erreur détaillé sur la page actuelle
            $message = $resultat['message'];
            $message_type = 'danger';
        }
    } else {
        $message = "Données invalides pour la mise à jour. Veuillez remplir tous les champs obligatoires.";
        $message_type = 'danger';
    }

    // Après l'échec, nous devons recharger les données de l'immobilisation
    // pour que le formulaire reste pré-rempli.
    if ($id) {
        $immobilisation = get_immobilisation_by_id($id);
    }

} else { // Gérer l'affichage du formulaire (GET)
    $id = $_GET['id'] ?? null;
    if ($id) {
        $immobilisation = get_immobilisation_by_id($id);
        if (!$immobilisation) {
            header("Location: index.php?error=" . urlencode("Immobilisation non trouvée."));
            exit();
        }
    } else {
        header("Location: index.php?error=" . urlencode("Identifiant de l'immobilisation manquant."));
        exit();
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

    <?php if ($immobilisation): ?>
    <form method="post" action="modifier.php">
        <input type="hidden" name="id" value="<?= htmlspecialchars($immobilisation['ID_Immobilisation']) ?>">
        
        <div class="panel panel-default">
            <div class="panel-heading">Détails de l'immobilisation</div>
            <div class="panel-body">
                <div class="form-group">
                    <label for="designation">Désignation</label>
                    <input type="text" class="form-control" id="designation" name="designation" value="<?= htmlspecialchars($immobilisation['Designation']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="numero_facture">Numéro de facture</label>
                    <input type="text" class="form-control" id="numero_facture" name="numero_facture" value="<?= htmlspecialchars($immobilisation['Numero_Facture']) ?>">
                </div>
                <div class="form-group">
                    <label for="date_acquisition">Date d'acquisition</label>
                    <input type="date" class="form-control" id="date_acquisition" name="date_acquisition" value="<?= htmlspecialchars($immobilisation['Date_Acquisition']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="montant_ht">Montant HT</label>
                    <input type="number" class="form-control" id="montant_ht" name="montant_ht" step="0.01" value="<?= htmlspecialchars($immobilisation['Montant_HT']) ?>" required>
                </div>
            </div>
        </div>
        
        <div class="form-group text-center">
            <button type="submit" class="btn btn-warning"><span class="glyphicon glyphicon-pencil"></span> Mettre à jour</button>
            <a href="index.php" class="btn btn-default">Annuler</a>
        </div>
    </form>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<?php require_once('../../templates/footer.php'); ?>