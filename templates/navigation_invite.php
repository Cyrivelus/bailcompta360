<?php
// templates/navigation.php

// Gestion de la session et de l'inactivité
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérification de l'inactivité
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > 1800) { // 1800 secondes = 30 minutes
    session_unset(); // unset $_SESSION variable for the run-time
    session_destroy(); // destroy session data in storage
    header('Location: /index.php'); // Rediriger vers la page de connexion
    exit();
}

$_SESSION['LAST_ACTIVITY'] = time(); // Mettre à jour le dernier moment d'activité

// Vérifier si l'utilisateur est connecté (pour afficher la navigation)
$estConnecte = isset($_SESSION['utilisateur_id']);

// Fonction pour générer l'URL absolue
function generateUrl($path) {
    return '/bailcompta360/' . ltrim($path, '/');
}

// Assurez-vous que le chemin de base est correctement configuré pour la comparaison 'active'
// Définir la page courante pour la classe 'active'
$current_page = basename($_SERVER['PHP_SELF']);
$current_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_path = '/bailcompta360/';
$relative_uri = str_replace($base_path, '', $current_uri);

// Fonction améliorée pour vérifier si un lien est actif, en tenant compte des paramètres GET si nécessaire
function isActive($link_path, $current_uri_relative, $current_page_basename) {
    $link_basename = basename($link_path);
    $link_uri = parse_url(generateUrl($link_path), PHP_URL_PATH);
    $link_uri_relative = str_replace('/bailcompta360/', '', $link_uri);

    // Vérifier si l'URI relative correspond
    if ($current_uri_relative === $link_uri_relative) {
        return true;
    }

    // Vérifier si le nom de fichier de base correspond (pour les cas sans paramètres GET spécifiques)
    if ($current_page_basename === $link_basename) {
        return false; // Préférer la correspondance d'URI complète pour l'exactitude
    }

    return false;
}

// Inclure les fichiers de fonctions et de configuration
require_once(__DIR__ . '../../fonctions/database.php'); // Assurez-vous que ce fichier gère la connexion à la BD
require_once(__DIR__ . '../../fonctions/gestion_habilitations.php'); // Inclut maintenant les fonctions getAllProfils, getAllUtilisateurs et getPotentialPermissionObjects

// Connexion à la base de données (utiliser la connexion gérée par database.php si possible,
// sinon gardez la connexion directe si c'est votre choix actuel)
try {
    // Si database.php retourne l'objet PDO :
    // $pdo = getPdoConnection(); // Exemple si database.php a une fonction getPdoConnection()

    // Sinon, utilisez votre code actuel :
    $pdo = new PDO("sqlsrv:Server=192.168.100.226;Database=BD_AD_SCE");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Simuler une fonction hasPermission (à remplacer par votre logique d'autorisation réelle)
// Cette fonction est définie ici pour être accessible dans le template.
function hasPermission($pdo, $userId, $permission) {
    // Ici, vous devez implémenter votre logique réelle de vérification de permissions.
    // Par exemple, interroger votre base de données.
    // Pour cet exemple, nous retournons toujours true, comme dans le code original.
    return true;
}

if ($estConnecte): // Afficher la navigation si l'utilisateur est connecté (quel que soit son rôle)
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    /* Styles personnalisés pour la navigation verticale */
    .vertical-nav {
        min-width: 200px; /* Largeur minimale de la barre latérale */
        max-width: 200px; /* Largeur maximale */
        background-color: #f8f9fa; /* Couleur de fond légère */
        height: 100vh; /* Hauteur à 100% de la hauteur de la vue */
        position: fixed; /* Position fixe */
        top: 0;
        left: 0;
        padding-top: 1rem; /* Espacement en haut */
        box-shadow: 2px 0 5px rgba(0,0,0,.1); /* Ombre légère */
    }

    .vertical-nav .nav-link {
        color: #495057; /* Couleur de texte par défaut */
        padding: 0.75rem 1rem; /* Espacement interne */
        border-radius: 0; /* Pas de bords ovales pour une liste verticale typique */
        margin-bottom: 0.25rem; /* Espacement entre les éléments */
    }

    .vertical-nav .nav-link:hover,
    .vertical-nav .nav-link.active {
        background-color: #dc3545; /* Bordeau pour l'effet hover et actif */
        color: white; /* Texte blanc pour l'effet hover et actif */
    }

    .vertical-nav .dropdown-toggle::after {
        float: right; /* Fléche du dropdown à droite */
        margin-top: 0.5em;
    }

    .vertical-nav .dropdown-menu {
        position: static; /* Positionnement statique dans le flux */
        float: none; /* Annuler le float */
        width: auto; /* Largeur automatique */
        margin-top: 0;
        border: none; /* Pas de bordure */
        box-shadow: none; /* Pas d'ombre */
        background-color: transparent; /* Fond transparent */
        padding-left: 1.5rem; /* Indentation pour les sous-éléments */
    }

    .vertical-nav .dropdown-menu .dropdown-item {
        padding: 0.5rem 1rem; /* Espacement interne */
        color: #495057; /* Couleur de texte par défaut */
    }

    .vertical-nav .dropdown-menu .dropdown-item:hover,
    .vertical-nav .dropdown-menu .dropdown-item.active {
        background-color: #f8d7da; /* Bordeau clair pour les sous-éléments actifs/hover */
        color: #495057;
    }

    /* Ajouter une marge au contenu principal pour ne pas qu'il soit caché par la nav fixe */
    body {
        padding-left: 200px; /* Doit correspondre à la largeur de .vertical-nav */
    }
</style>

<div class="vertical-nav">
    <nav class="nav flex-column">
        <a class="nav-link active" href="<?= generateUrl('pages/accueil_invite.php') ?>">Accueil Invité</a>
        <a class="nav-link <?php if (isActive('pages/ecritures/saisie.php', $relative_uri, $current_page)) echo 'active'; ?>" href="<?= generateUrl('pages/ecritures/saisie.php') ?>">
            Saisie Écritures
            <style>
                .progress-container {
                    display: inline-block;
                    width: 100px;
                    height: 10px;
                    background-color: #eee;
                    border-radius: 5px;
                    overflow: hidden;
                    margin-left: 5px;
                    vertical-align: middle;
                }

                .progress-bar {
                    height: 100%;
                    width: 70%;
                    background-color: orange;
                    animation: grow 2s ease-out forwards;
                }

                @keyframes grow {
                    from {
                        width: 0%;
                    }
                    to {
                        width: 70%;
                    }
                }

                .status-text {
                    font-size: 0.7em;
                    color: orange;
                    margin-left: 8px;
                    vertical-align: middle;
                }
            </style>
        </a>

        <a class="nav-link <?php if (isActive('pages/comptes/index.php', $relative_uri, $current_page)) echo 'active'; ?>" href="<?= generateUrl('pages/comptes/index.php') ?>">
            Consulter Comptes
        </a>

        <a class="nav-link <?php if (isActive('pages/ecritures/liste.php', $relative_uri, $current_page)) echo 'active'; ?>" href="<?= generateUrl('pages/ecritures/liste.php') ?>">Liste Écritures</a>
        <a class="nav-link <?php if (isActive('pages/analyse/index.php', $relative_uri, $current_page)) echo 'active'; ?>" href="<?= generateUrl('pages/analyse/index.php') ?>">
            Analyse
        </a>

        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownEmprunts" role="button" data-bs-toggle="collapse" data-bs-target="#collapseEmprunts" aria-expanded="false" aria-controls="collapseEmprunts">
            Emprunts
        </a>

        <div class="collapse <?php if (strpos($relative_uri, 'pages/emprunts/') === 0) echo 'show'; ?>" id="collapseEmprunts">
            <ul class="nav flex-column ms-3">
                <li class="nav-item">
                    <a class="dropdown-item <?php if (isActive('pages/emprunts/index.php', $relative_uri, $current_page) || (basename($_SERVER['PHP_SELF']) == 'index.php' && isset($_GET['section']) && $_GET['section'] == 'emprunts')) echo 'active'; ?>" href="<?= generateUrl('pages/emprunts/index.php') ?>">Liste Emprunts</a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?php if (isActive('pages/emprunts/ajouter.php', $relative_uri, $current_page) || (basename($_SERVER['PHP_SELF']) == 'ajouter.php' && isset($_GET['section']) && $_GET['section'] == 'emprunts')) echo 'active'; ?>" href="<?= generateUrl('pages/emprunts/ajouter.php') ?>">Ajouter Emprunt</a>
                </li>
            </ul>
        </div>

        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownFactures" role="button" data-bs-toggle="collapse" data-bs-target="#collapseFactures" aria-expanded="false" aria-controls="collapseFactures">
            Factures
        </a>
        <div class="collapse <?php if (strpos($relative_uri, 'pages/factures/') === 0) echo 'show'; ?>" id="collapseFactures">
            <ul class="nav flex-column ms-3">
                <li class="nav-item">
                    <a class="dropdown-item <?php if (isActive('pages/factures/integration.php', $relative_uri, $current_page)) echo 'active'; ?>" href="<?= generateUrl('pages/factures/integration.php') ?>">Intégration</a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?php if (isActive('pages/factures/listes_factures.php', $relative_uri, $current_page)) echo 'active'; ?>" href="<?= generateUrl('pages/factures/listes_factures.php') ?>">Liste</a>
                </li>
            </ul>
        </div>

        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownImport" role="button" data-bs-toggle="collapse" data-bs-target="#collapseImport" aria-expanded="false" aria-controls="collapseImport">
            Importation
        </a>
        <div class="collapse <?php if (strpos($relative_uri, 'pages/import/') === 0) echo 'show'; ?>" id="collapseImport">
            <ul class="nav flex-column ms-3">
                <li class="nav-item">
                    <a class="dropdown-item <?php if (isActive('pages/import/index.php', $relative_uri, $current_page) || (basename($_SERVER['PHP_SELF']) == 'index.php' && isset($_GET['section']) && $_GET['section'] == 'import')) echo 'active'; ?>" href="<?= generateUrl('pages/import/index.php') ?>">Intégration</a>
                </li>
            </ul>
        </div>

        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownGeneration" role="button" data-bs-toggle="collapse" data-bs-target="#collapseGeneration" aria-expanded="false" aria-controls="collapseGeneration">
            Génération ABS 2000
        </a>
        <div class="collapse <?php if (strpos($relative_uri, 'pages/generation/') === 0) echo 'show'; ?>" id="collapseGeneration">
            <ul class="nav flex-column ms-3">
                <li class="nav-item">
                    <a class="dropdown-item <?php if (isActive('pages/generation/GenFacture.php', $relative_uri, $current_page) || (basename($_SERVER['PHP_SELF']) == 'GenFacture.php' && isset($_GET['section']) && $_GET['section'] == 'generation')) echo 'active'; ?>" href="<?= generateUrl('pages/generation/GenFacture.php') ?>">Generation facture</a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?php if (isActive('pages/generation/GenEmprunt.php', $relative_uri, $current_page) || (basename($_SERVER['PHP_SELF']) == 'GenEmprunt.php' && isset($_GET['section']) && $_GET['section'] == 'generation')) echo 'active'; ?>" href="<?= generateUrl('pages/generation/GenEmprunt.php') ?>">Generation emprunt</a>
                </li>
            </ul>
        </div>

        <?php if (hasPermission($pdo, $_SESSION['utilisateur_id'] ?? 0, 'gestion_habilitations_menu')): ?>
        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownHabilitations" role="button" data-bs-toggle="collapse" data-bs-target="#collapseHabilitations" aria-expanded="false" aria-controls="collapseHabilitations">
            Habilitations
        </a>
        <div class="collapse <?php if (strpos($relative_uri, 'pages/habilitations/') === 0) echo 'show'; ?>" id="collapseHabilitations">
            <ul class="nav flex-column ms-3">
                <li class="nav-item">
                    <a class="dropdown-item <?php if (isActive('pages/habilitations/profils.php', $relative_uri, $current_page)) echo 'active'; ?>" href="<?= generateUrl('pages/habilitations/profils.php') ?>">Gestion des Profils</a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?php if (isActive('pages/habilitations/utilisateurs.php', $relative_uri, $current_page) || (basename($_SERVER['PHP_SELF']) == 'utilisateurs.php' && isset($_GET['section']) && $_GET['section'] == 'habilitations')) echo 'active'; ?>" href="<?= generateUrl('pages/habilitations/utilisateurs.php') ?>">Gestion des Utilisateurs</a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?php if (isActive('pages/habilitations/gestion_droits.php', $relative_uri, $current_page)) echo 'active'; ?>" href="<?= generateUrl('pages/habilitations/gestion_droits.php') ?>">Gestion des Droits</a>
                </li>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (hasPermission($pdo, $_SESSION['utilisateur_id'] ?? 0, 'gestion_utilisateurs_menu')): ?>
        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownUtilisateursGestion" role="button" data-bs-toggle="collapse" data-bs-target="#collapseUtilisateurs" aria-expanded="false" aria-controls="collapseUtilisateurs">
            Utilisateurs
        </a>
        <div class="collapse <?php if (strpos($relative_uri, 'pages/utilisateurs/') === 0) echo 'show'; ?>" id="collapseUtilisateurs">
            <ul class="nav flex-column ms-3">
                <li class="nav-item">
                    <a class="dropdown-item <?php if (isActive('pages/utilisateurs/index.php', $relative_uri, $current_page) || (basename($_SERVER['PHP_SELF']) == 'index.php' && isset($_GET['section']) && $_GET['section'] == 'utilisateurs')) echo 'active'; ?>" href="<?= generateUrl('pages/utilisateurs/index.php') ?>">Liste Utilisateurs</a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?php if (isActive('pages/utilisateurs/ajouter.php', $relative_uri, $current_page) || (basename($_SERVER['PHP_SELF']) == 'ajouter.php' && isset($_GET['section']) && $_GET['section'] == 'utilisateurs')) echo 'active'; ?>" href="<?= generateUrl('pages/utilisateurs/ajouter.php') ?>">Ajouter Utilisateur</a>
                </li>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (hasPermission($pdo, $_SESSION['utilisateur_id'] ?? 0, 'gestion_profils_menu')): ?>
        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownProfilsGestion" role="button" data-bs-toggle="collapse" data-bs-target="#collapseProfils" aria-expanded="false" aria-controls="collapseProfils">
            Profils
        </a>
        <div class="collapse <?php if (strpos($relative_uri, 'pages/profils/') === 0) echo 'show'; ?>" id="collapseProfils">
            <ul class="nav flex-column ms-3">
                <li class="nav-item">
                    <a class="dropdown-item <?php if (isActive('pages/profils/index.php', $relative_uri, $current_page) || (basename($_SERVER['PHP_SELF']) == 'index.php' && isset($_GET['section']) && $_GET['section'] == 'profils')) echo 'active'; ?>" href="<?= generateUrl('pages/profils/index.php') ?>">Liste Profils</a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?php if (isActive('pages/profils/ajouter.php', $relative_uri, $current_page) || (basename($_SERVER['PHP_SELF']) == 'ajouter.php' && isset($_GET['section']) && $_GET['section'] == 'profils')) echo 'active'; ?>" href="<?= generateUrl('pages/profils/ajouter.php') ?>">Ajouter Profil</a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </nav>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

        dropdownToggles.forEach(toggle => {
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('data-bs-target');
                const targetElement = document.querySelector(targetId);

                if (targetElement.classList.contains('show')) {
                    targetElement.classList.remove('show');
                } else {
                    targetElement.classList.add('show');
                }
            });
        });
    });
</script>

<?php
endif;
?>
