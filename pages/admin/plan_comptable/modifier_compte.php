<?php
// pages/admin/plan_comptable/modifier_compte.php

// Démarrer la session pour gérer l'authentification
session_start();

// Vérifier si l'utilisateur est connecté en tant qu'administrateur
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../../index.php?error=Accès non autorisé");
    exit();
}

// Inclure les fichiers nécessaires
require_once '../../../fonctions/database.php';
require_once '../../../fonctions/plan_comptable/gestion_plan_comptable.php';

$titre = 'Modifier un Compte Comptable';
$current_page = 'plan_comptable';

$message_success = '';
$message_erreur = '';
$compte_a_modifier = null;

// Gérer la soumission du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ancien_numero = $_POST['ancien_numero'] ?? '';
    $nouveau_numero = $_POST['nouveau_numero'] ?? '';
    $libelle = $_POST['libelle'] ?? '';

    // Valider les données du formulaire
    if (!empty($ancien_numero) && !empty($nouveau_numero) && !empty($libelle)) {
        // Appeler la fonction de mise à jour du compte
        $resultat = modifier_compte_comptable($ancien_numero, $nouveau_numero, $libelle);
        
        if ($resultat['success']) {
            $message_success = "Le compte comptable a été mis à jour avec succès.";
            // Si le numéro de compte a changé, on met à jour la variable pour l'affichage
            $ancien_numero = $nouveau_numero;
        } else {
            $message_erreur = "Erreur lors de la modification du compte : " . $resultat['message'];
        }
    } else {
        $message_erreur = "Veuillez remplir tous les champs.";
    }
}

// Récupérer le compte à modifier pour pré-remplir le formulaire
// On vérifie le paramètre 'numero' dans l'URL (GET)
$numero_compte = $_GET['numero'] ?? ($ancien_numero ?? null);
if ($numero_compte) {
    $compte_a_modifier = get_compte_by_numero($numero_compte);
    if (!$compte_a_modifier) {
        $message_erreur = "Aucun compte trouvé avec ce numéro.";
    }
} else {
    $message_erreur = "Numéro de compte non spécifié.";
}

require_once('../../../templates/header.php');
require_once('../../../templates/navigation.php');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?php echo $titre; ?></title>
    <link rel="shortcut icon" href="../../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../css/style.css">
    <link rel="stylesheet" href="../../../css/tableau.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<div class="container">
    <h2 class="page-header"><?php echo $titre; ?></h2>

    <?php if ($message_success): ?>
        <div class="alert alert-success" role="alert">
            <span class="glyphicon glyphicon-ok" aria-hidden="true"></span>
            <?php echo htmlspecialchars($message_success); ?>
        </div>
    <?php endif; ?>

    <?php if ($message_erreur): ?>
        <div class="alert alert-danger" role="alert">
            <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
            <?php echo htmlspecialchars($message_erreur); ?>
        </div>
    <?php endif; ?>

    <?php if ($compte_a_modifier): ?>
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Informations du Compte</h3>
            </div>
            <div class="panel-body">
                <form action="modifier_compte.php" method="POST">
                    <input type="hidden" name="ancien_numero" value="<?php echo htmlspecialchars($compte_a_modifier['numero_compte']); ?>">
                    
                    <div class="form-group">
                        <label for="nouveau_numero">Numéro du Compte</label>
                        <input type="text" class="form-control" id="nouveau_numero" name="nouveau_numero" value="<?php echo htmlspecialchars($compte_a_modifier['numero_compte']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="libelle">Libellé du Compte</label>
                        <input type="text" class="form-control" id="libelle" name="libelle" value="<?php echo htmlspecialchars($compte_a_modifier['libelle_compte']); ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <span class="glyphicon glyphicon-save"></span> Enregistrer les modifications
                    </button>
                    <a href="index.php" class="btn btn-default">
                        <span class="glyphicon glyphicon-chevron-left"></span> Annuler
                    </a>
                </form>
            </div>
        </div>
    <?php else: ?>
        <p>Veuillez retourner à la <a href="index.php">liste des comptes</a> pour sélectionner un compte à modifier.</p>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>

<?php
require_once('../../../templates/footer.php');
?>