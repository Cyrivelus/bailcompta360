<?php
// pages/accueil_invite.php

// Démarrer la session pour accéder aux variables de session et pour la gestion de la déconnexion
session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'samesite' => 'Strict',
]);
session_start();

// Optionnel : Vérifier si l'utilisateur a un rôle spécifique 'Invité' ou est simplement non identifié autrement
// Pour cet exemple, nous supposerons que si on arrive ici, c'est l'accueil invité.

$nomUtilisateur = $_SESSION['nom_utilisateur'] ?? 'Invité'; // Utiliser le nom si disponible, sinon "Invité"

// Définir le titre de la page (peut être utilisé dans header.php)
$titre = "Accueil Invité - BailCompta 360";
$current_page = basename($_SERVER['PHP_SELF']); // Pour la classe 'active' dans la navigation

// Inclure l'en-tête de la page
// Adaptez le chemin si votre structure de dossier est différente
require_once('../templates/header.php');

// Inclure la navigation (peut-être une version simplifiée pour les invités)
// Adaptez le chemin si votre structure de dossier est différente
// Si vous n'avez pas de navigation spécifique pour les invités, vous pouvez omettre ceci
// ou créer un menu simple ici.
require_once('../templates/navigation_invite.php'); // Exemple: navigation_invite.php
                                                 // ou utilisez votre navigation.php standard
                                                 // qui pourrait adapter son contenu en fonction du rôle.
?>

<div class="container">
    <div class="page-header">
        <h1>Bienvenue, <?php echo htmlspecialchars($nomUtilisateur); ?> !</h1>
    </div>

    <div class="alert alert-info" role="alert">
        <p>Vous êtes actuellement connecté avec un accès limité à <strong>BailCompta 360</strong>.</p>
    </div>

    <h2>Que pouvez-vous faire ?</h2>
    <p>En tant qu'invité, vous pouvez généralement :</p>
    <ul>
        <li>Consulter les informations publiques (si applicable).</li>
        <li>Vous familiariser avec l'interface de l'application.</li>
        </ul>

    <h2>Accéder à plus de fonctionnalités</h2>
    <p>Pour bénéficier de toutes les fonctionnalités de <strong>BailCompta 360</strong>, veuillez vous connecter avec un compte utilisateur enregistré ou créer un nouveau compte si vous n'en possédez pas.</p>
    <p>
        <a href="../index.php" class="btn btn-primary">
            <span class="glyphicon glyphicon-log-in" aria-hidden="true"></span> Se connecter
        </a>
        <?php
        // Optionnel : Afficher un lien d'inscription si cette fonctionnalité existe
        // Par exemple :
        // if (fonction_inscription_active()) {
        //     echo '<a href="inscription.php" class="btn btn-success" style="margin-left: 10px;">';
        //     echo '    <span class="glyphicon glyphicon-user" aria-hidden="true"></span> Créer un compte';
        //     echo '</a>';
        // }
        ?>
    </p>

    <hr>

    <p>Si vous souhaitez mettre fin à votre session invité :</p>
    <p>
        <a href="../auth/logout.php" class="btn btn-warning">
            <span class="glyphicon glyphicon-off" aria-hidden="true"></span> Quitter la session invité
        </a>
    </p>

</div><?php
// Inclure le pied de page
// Adaptez le chemin si votre structure de dossier est différente
require_once('../templates/footer.php');
?>