<?php
// pages/utilisateurs/index.php
// Cette page sert maintenant de portail vers la section d'administration principale.

// Configuration de la page
$titre = 'Accès à l\'Administration'; // Nouveau titre
$current_page = basename(__FILE__);

// Inclusion des fichiers nécessaires pour la structure de la page
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
// require_once('../../fonctions/database.php'); // Plus nécessaire ici si la page ne fait que rediriger
// require_once('../../fonctions/gestion_utilisateurs.php'); // Plus nécessaire ici

// --- Vérification des droits d'accès (Exemple simple, à adapter) ---
// Si l'accès à cette page de redirection doit être protégé, décommentez et adaptez.
/*
session_start(); // Assurez-vous que la session est démarrée si ce n'est pas fait dans header.php
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Admin') { // Adapter le rôle requis
    // Rediriger vers la page de connexion ou une page d'accès refusé
    header("Location: ../../auth/login.php?error=access_denied_admin_portal"); // Utilisez un chemin et un message d'erreur appropriés
    exit();
}
*/
// --- Fin de la vérification des droits ---

// --- Gestion des messages (si cette page de portail peut en afficher) ---
// En général, pour une simple page de lien, ce n'est pas nécessaire,
// sauf si des erreurs d'accès spécifiques à ce portail sont gérées ici.
$messageErreur = null;
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'access_denied_admin_portal') {
        $messageErreur = "Accès refusé. Vous n'avez pas les autorisations nécessaires pour accéder à cette section.";
    } else {
        $messageErreur = "Une erreur est survenue : " . htmlspecialchars(urldecode($_GET['error']));
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?= htmlspecialchars($titre) ?></title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css" integrity="sha384-HSMxcRTRxnN+Bdg0JdbxYKrThecOKuH5zCYotlSAcp1+c8xmyTe9GYg1l9a69psu" crossorigin="anonymous">
    <link rel="stylesheet" href="../../css/monstyle.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>

<?php
// Le header.php et navigation.php sont inclus au début du script PHP.
// Si la navigation doit s'adapter au contexte (par exemple, ne pas montrer "Gestion des utilisateurs" comme actif),
// la logique dans navigation.php devrait gérer cela.
?>

    <div class="container">
        <div class="page-header">
            <h1><?= htmlspecialchars($titre) ?></h1>
        </div>

        <?php if (isset($messageErreur)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($messageErreur) ?></div>
        <?php endif; ?>

        <div class="jumbotron text-center">
            <p>Cette section vous permet d'accéder au panneau d'administration principal de l'application.</p>
           
            <p style="margin-top: 30px;">
                <a href="../admin/index.php" class="btn btn-primary btn-lg">
                    <span class="glyphicon glyphicon-cog" aria-hidden="true"></span> Accéder à l'Administration
                </a>
            </p>
        </div>

        <?php
        // Si vous souhaitez conserver un lien de "retour" générique ou un autre lien pertinent
        // Par exemple, un retour au tableau de bord principal si l'utilisateur n'est pas admin
        /*
        if (isset($_SESSION['role']) && $_SESSION['role'] !== 'Admin') {
            echo '<p><a href="../dashboard.php" class="btn btn-default">Retour au tableau de bord</a></p>';
        }
        */
        ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js" integrity="sha384-aJ21OjlMXNLJ5ywYOIDjxxyTwCypxSoOO3FxyYr4fccRoP1h0IWcAukj0jz9uNNs" crossorigin="anonymous"></script>
</body>
</html>

<?php
// Inclure le footer si vous en avez un
// S'il est inclus dans le body de la page HTML principale, déplacez-le avant </body>
require_once('../../templates/footer.php');
?>