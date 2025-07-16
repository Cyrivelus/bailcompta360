<?php
// admin/profils/assigner_utilisateurs.php

require_once '../../includes/header.php';
require_once '../../includes/navigation.php';
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_profils.php';
require_once '../../fonctions/gestion_utilisateurs.php';

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur
if (!estAdminConnecte()) {
    header('Location: ../index.php');
    exit();
}

// Vérifier si l'ID du profil est présent dans l'URL et est un nombre
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $errorMessage = "ID de profil invalide.";
} else {
    $profilId = $_GET['id'];

    // Récupérer les informations du profil
    $profil = getProfilParId($pdo, $profilId);
    if (!$profil) {
        $errorMessage = "Profil non trouvé.";
    }

    // Récupérer la liste de tous les utilisateurs
    $tousLesUtilisateurs = getTousLesUtilisateurs($pdo);

    // Récupérer les utilisateurs actuellement assignés à ce profil
    $utilisateursAssignes = getUtilisateursParProfilId($pdo, $profilId);
    $listeIdsUtilisateursAssignes = array_column($utilisateursAssignes, 'ID_Utilisateur');
}

// Traitement de l'assignation des utilisateurs
if (isset($_POST['assigner_utilisateurs'])) {
    // Vérifier le jeton CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Erreur CSRF.");
    }

    $utilisateursAAssigner = isset($_POST['utilisateurs']) ? $_POST['utilisateurs'] : [];

    if (mettreAJourUtilisateursProfil($pdo, $profilId, $utilisateursAAssigner)) {
        $successMessage = "Les utilisateurs ont été assignés au profil '{$profil['Nom_Profil']}' avec succès.";
        // Récupérer à nouveau la liste des utilisateurs assignés
        $utilisateursAssignes = getUtilisateursParProfilId($pdo, $profilId);
        $listeIdsUtilisateursAssignes = array_column($utilisateursAssignes, 'ID_Utilisateur');
    } else {
        $errorMessage = "Erreur lors de l'assignation des utilisateurs au profil.";
    }
}

// Générer un jeton CSRF
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Administration - Modifier le rôle</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css" integrity="sha384-HSMxcRTRxnN+Bdg0JdbxYKrThecOKuH5zCYotlSAcp1+c8xmyTe9GYg1l9a69psu" crossorigin="anonymous">
    <link rel="stylesheet" href="../../css/monstyle.css">
    <?php if (isset($admin_style) && $admin_style): ?>
        <link rel="stylesheet" href="../../css/admin_style.css">
    <?php endif; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>

<?php require_once '../../templates/admin_navigation.php'; // Inclure la navigation spécifique à l'admin ?>

<div class="container mt-5">
    <h2>Assigner des utilisateurs au profil : <?php if (isset($profil)) echo htmlspecialchars($profil['Nom_Profil']); ?></h2>

    <?php if (isset($errorMessage)): ?>
        <div class="alert alert-danger"><?= $errorMessage ?></div>
    <?php endif; ?>

    <?php if (isset($successMessage)): ?>
        <div class="alert alert-success"><?= $successMessage ?></div>
    <?php endif; ?>

    <?php if (isset($profil)): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="form-group">
                <label>Sélectionner les utilisateurs à assigner à ce profil :</label>
                <?php if (!empty($tousLesUtilisateurs)): ?>
                    <?php foreach ($tousLesUtilisateurs as $utilisateur): ?>
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="utilisateurs[]" value="<?= htmlspecialchars($utilisateur['ID_Utilisateur']) ?>"
                                    <?= in_array($utilisateur['ID_Utilisateur'], $listeIdsUtilisateursAssignes) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($utilisateur['Nom']) ?> (<?= htmlspecialchars($utilisateur['Login_Utilisateur']) ?>)
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="alert alert-info">Aucun utilisateur n'est actuellement enregistré dans le système.</p>
                <?php endif; ?>
            </div>
            <button type="submit" name="assigner_utilisateurs" class="btn btn-primary">
                <span class="glyphicon glyphicon-user"></span> Assigner les utilisateurs
            </button>
            <a href="index.php" class="btn btn-secondary">
                <span class="glyphicon glyphicon-remove"></span> Annuler
            </a>
        </form>
    <?php elseif (!isset($errorMessage)): ?>
        <p>Veuillez sélectionner un profil depuis la liste pour assigner des utilisateurs.</p>
        <a href="index.php" class="btn btn-info">
            <span class="glyphicon glyphicon-arrow-left"></span> Retour à la liste des profils
        </a>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>

</body>
</html>

<?php
require_once '../../includes/footer.php';

// Fonctions à implémenter (dans fonctions/gestion_profils.php et gestion_utilisateurs.php) :
// - estAdminConnecte() : Vérifie si l'utilisateur connecté est un administrateur.
// - getProfilParId($pdo, $id) : Récupère les informations d'un profil par son ID.
// - getTousLesUtilisateurs($pdo) : Récupère la liste de tous les utilisateurs.
// - getUtilisateursParProfilId($pdo, $profilId) : Récupère les utilisateurs assignés à un profil.
// - mettreAJourUtilisateursProfil($pdo, $profilId, array $listeUtilisateurs) : Met à jour les assignations d'utilisateurs pour un profil.
?>