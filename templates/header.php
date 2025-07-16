<?php
// templates/header.php

// Démarrer la session si ce n'est pas déjà fait
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
$estConnecte = isset($_SESSION['utilisateur_id']);
$nomUtilisateur = (isset($_SESSION['utilisateur_id']) && isset($_SESSION['nom_utilisateur'])) ? $_SESSION['nom_utilisateur'] : '';

// Définir le titre de la page (peut être dynamique)
$titrePage = isset($titre) ? $titre : 'BailCompta 360';

// Couleur de l'entreprise (bordeaux)
$couleurPrincipale = '#6d071a';
$couleurTextePrimaire = '#ffffff'; // Texte clair sur fond bordeaux

// Définir les variables de page (pour la navigation active)
$version = "1.0";
$current_page = basename($_SERVER['PHP_SELF']);
// Note: $date_actuelle et $heure_actuelle ne sont pas utilisés dans l'affichage actuel du header
// $date_actuelle = date('d/m/Y');
// $heure_actuelle = date('H:i:s');

// Récupérer la dernière activité depuis la session, si elle existe
$derniereActivite = isset($_SESSION['LAST_ACTIVITY']) ? $_SESSION['LAST_ACTIVITY'] : null; // Utilisez 'LAST_ACTIVITY' pour correspondre au script de navigation
$formatDerniereActivite = '';

if ($estConnecte && $derniereActivite) {
    // Si l'utilisateur est connecté et qu'une dernière activité existe, la formater
    // Soustraction de 3600 secondes (1 heure)
    $formatDerniereActivite = '  ' . date('d/m/Y H:i:s', $derniereActivite - 3600);
} elseif (!$estConnecte) {
    // Si l'utilisateur n'est pas connecté (Invité), afficher la date et l'heure actuelles comme "dernière connexion"
    // Soustraction de 3600 secondes (1 heure)
    $formatDerniereActivite = 'Dernière connexion : ' . date('d/m/Y H:i:s', time() - 3600);
}


// Gestion de la déconnexion
if (isset($_POST['logout'])) {
    session_unset(); // unset $_SESSION variable for the run-time
    session_destroy(); // destroy session data in storage
    header('location:/bailcompta360/index.php'); // Rediriger vers la page de connexion
    exit();
}

?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="application de facilitaion comptable" content="BailCompta 360">
<meta name="TAMBOUG Cyrille Steve" >

    <title><?php echo htmlspecialchars($titrePage); ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/formulaire.css">
    <link rel="stylesheet" href="../css/tableau.css">
	  <link rel="stylesheet" href="../css/bootstrap.min.css">
	<link rel="shortcut icon" href="../images/LOGO_SCE.ico" type="image/x-icon">
  

    <style>
        /* Styles de base améliorés */
        body {
            font-family: 'Times New Roman', sans-serif;
            background-color: #f5f5f5;
            /* padding-left est géré par la navigation verticale */
            /* Ajouter padding-top pour laisser de l'espace au header fixe */
            padding-top: 80px; /* Ajustez cette valeur en fonction de la hauteur réelle de votre header */
        }

        /* Style du header fixe et décalé */
        header {
            background-color: <?php echo $couleurPrincipale; ?> !important;
            color: <?php echo $couleurTextePrimaire; ?> !important;
            padding: 5px 20px; /* Ajustez le padding si nécessaire */
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);

            /* Styles pour la fixation et le décalage */
            position: fixed;
            top: 0;
            left: 215px; /* Décalé à droite de la largeur de la navigation verticale */
            right: 0; /* S'étend jusqu'au bord droit */
            z-index: 1030; /* Assure que le header est au-dessus du contenu */
        }

        .logo-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            /* Padding interne géré par le padding du header */
        }

        .logo {
            text-align: center;
            background-color: rgba(255,255,255,0.2);
            padding: 10px 15px;
            border-radius: 8px;
        }

        .logo h1 {
            margin: 0;
            font-size: 1.5em;
            font-family: 'Arial Black', Gadget, sans-serif;
            font-weight: bold;
            color: white;
        }

        .logo span {
            color: #B22222; /* Couleur spécifique pour "Compta" */
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            color: white;
        }

        .user-name {
            font-weight: bold;
            font-size: 1.1em;
        }

        .last-activity {
            font-size: 0.8em;
            opacity: 0.8;
        }

        .logout-btn {
            background-color: transparent;
            border: 1px solid white;
            color: white;
            padding: 5px 15px;
            border-radius: 4px;
            transition: all 0.3s;
            text-decoration: none; /* Enlever le soulignement par défaut du lien */
            display: inline-block; /* Permettre l'application de margin-top */
        }

        .logout-btn:hover {
            background-color: white;
            color: <?php echo $couleurPrincipale; ?>;
        }

        /* Ajoutez une classe pour le conteneur principal du contenu si vous n'utilisez pas la structure body padding */
        /* Par exemple, si vous avez <div class="main-content">...</div> après l'inclusion du header et de la nav */
        /* .main-content { margin-left: 200px; padding-top: 80px; } */

    </style>
</head>
<body>
   

<header>
    <div class="logo-container">
        <div class="logo">
            <h1>Bail<span>Compta</span> 360</h1>
        </div>

        <div class="user-info">
    <div class="user-name">
    <?php if ($estConnecte): ?>
        <span class="glyphicon glyphicon-user" aria-hidden="true"></span> <?php echo htmlspecialchars($nomUtilisateur); ?>
    <?php else: ?>
        <span class="glyphicon glyphicon-eye-open" aria-hidden="true"></span> <?php echo htmlspecialchars($nomUtilisateur); ?>
    <?php endif; ?>

    <?php if (!empty($formatDerniereActivite)): ?>
        <small class="last-activity">
            <span class="glyphicon glyphicon-time" aria-hidden="true"></span>&nbsp;<?php echo htmlspecialchars($formatDerniereActivite); ?>
        </small>
    <?php endif; ?>
</div>
    <?php if ($estConnecte): ?>
    <form method="post" action="" class="mt-2">
	
        <button type="submit" name="logout" class="logout-btn">
            <span class="glyphicon glyphicon-log-out" aria-hidden="true"></span> Déconnexion
        </button>
		
    </form>
<?php else: ?>
    <a href="<?= generateUrl('index.php') ?>" class="login-btn mt-2">
        <span class="glyphicon glyphicon-log-in" aria-hidden="true"></span> Connexion
    </a>
<?php endif; ?>
</div>

    </div>
</header>