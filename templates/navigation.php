<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navigation avec Glyphicons</title>
    <link rel="stylesheet" href="<?= generateUrl('css/bootstrap.min.css') ?>">
     <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/select2.min.css">
    <link rel="stylesheet" href="../css/select2-bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
   
    <style>
        /* Styles personnalisés pour la navigation verticale */
        .vertical-nav {
            min-width: 200px; /* Largeur minimale de la barre latérale */
            max-width: 300px; /* Largeur maximale */
            background-color: #f8f9fa; /* Couleur de fond légère */
            height: 100vh; /* Hauteur à 100% de la hauteur de la vue */
            position: fixed; /* Position fixe */
            top: 0;
            left: 0;
            padding-top: 1rem; /* Espacement en haut */
            box-shadow: 2px 0 5px rgba(0,0,0,.1); /* Ombre légère */
            overflow-y: auto; /* Ajout du défilement vertical */
        }

        .vertical-nav .nav-link {
            color: #495057; /* Couleur de texte par défaut */
            padding: 0.75rem 1rem; /* Espacement interne */
            border-radius: 0; /* Pas de bords ovales pour une liste verticale typique */
            margin-bottom: 0.25rem; /* Espacement entre les éléments */
            display: flex;
            align-items: center;
        }

        .vertical-nav .nav-link .glyphicon {
            margin-right: 0.5rem; /* Espacement entre l'icône et le texte */
        }

        .vertical-nav .nav-link:hover,
        .vertical-nav .nav-link.active {
            background-color: #dc3545; /* Bordeau pour l'effet hover et actif */
            color: white; /* Texte blanc pour l'effet hover et actif */
        }

        /* Les dropdown-toggle::after sont gérés différemment en B3 - Pas besoin de les redéfinir ici */

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

        /* Pour Bootstrap 3, les éléments de menu déroulant sont des 'a' à l'intérieur de 'li' */
        .vertical-nav .dropdown-menu > li > a {
            padding: 0.5rem 1rem; /* Espacement interne */
            color: #495057; /* Couleur de texte par défaut */
            display: flex;
            align-items: center;
        }

        .vertical-nav .dropdown-menu > li > a .glyphicon {
            margin-right: 0.5rem; /* Espacement entre l'icône et le texte */
        }

        .vertical-nav .dropdown-menu > li > a:hover,
        .vertical-nav .dropdown-menu > li > a.active {
            background-color: #f8d7da; /* Bordeau clair pour les sous-éléments actifs/hover */
            color: #495057;
        }

        /* Ajouter une marge au contenu principal pour ne pas qu'il soit caché par la nav fixe */
        body {
            padding-left: 200px; /* Doit correspondre à la largeur de .vertical-nav */
        }

        /* Styles pour les petits écrans */
        @media (max-width: 768px) {
            .vertical-nav {
                min-width: 60px; /* Réduire la largeur minimale pour les petits écrans */
                max-width: 60px; /* Réduire la largeur maximale pour les petits écrans */
                overflow-y: auto; /* Ajout du défilement vertical */
            }

            .vertical-nav .nav-link span,
            .vertical-nav .dropdown-menu > li > a span { /* Ajusté pour B3 */
                display: none; /* Masquer le texte sur les petits écrans */
            }

            .vertical-nav .nav-link .glyphicon,
            .vertical-nav .dropdown-menu > li > a .glyphicon { /* Ajusté pour B3 */
                margin-right: 0; /* Supprimer l'espacement entre l'icône et le texte */
            }

            body {
                padding-left: 60px; /* Ajuster la marge du contenu principal */
            }
        }

        /* Styles pour la barre de progression (inchangés) */
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

        /* Styles pour le bouton de modification du mot de passe */
        .change-password-btn {
            color: #495057;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
        }

        .change-password-btn:hover {
            background-color: #f8d7da;
            color: #495057;
        }

        .change-password-btn .glyphicon {
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
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
    header('Location: ' . generateUrl('index.php')); // Rediriger vers la page de connexion
    exit();
}

$_SESSION['LAST_ACTIVITY'] = time(); // Mettre à jour le dernier moment d'activité

// Vérifier si l'utilisateur est connecté (pour afficher la navigation)
$estConnecte = isset($_SESSION['utilisateur_id']);

// Fonction pour générer l'URL absolue
function generateUrl($path) {
    // This assumes your base application directory is '/bailcompta360/'
    // and the $path is relative to that base (e.g., 'css/bootstrap.min.css')
    return '/bailcompta360/' . ltrim($path, '/');
}

// Assurez-vous que le chemin de base est correctement configuré pour la comparaison 'active'
// Définir la page courante pour la classe 'active'
$current_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_path = '/bailcompta360/';
$relative_uri = str_replace($base_path, '', $current_uri);
$current_page_basename = basename($_SERVER['PHP_SELF']);


// Fonction améliorée pour vérifier si un lien est actif
function isActive($link_path, $current_uri_relative, $current_page_basename) {
    // Standardize link_path to a relative URI similar to current_uri_relative
    $link_uri = parse_url(generateUrl($link_path), PHP_URL_PATH);
    $link_uri_relative = str_replace('/bailcompta360/', '', $link_uri);

    // If the full relative URI matches, it's active
    if ($current_uri_relative === $link_uri_relative) {
        return true;
    }

    // Special handling for sub-pages that share a common base path,
    // where the dropdown should be open ("in" class)
    // This is more about keeping dropdowns open, not just activating the link itself.
    // Example: pages/emprunts/ajouter.php should keep "Emprunts" dropdown open.
    // You might need more sophisticated logic here if you want to highlight the parent link as active.
    $parent_folder = dirname($link_path);
    if (strpos($current_uri_relative, $parent_folder . '/') === 0 && !empty($parent_folder) && $parent_folder !== '.') {
        return true; // This will mark parent link as active if any child page is active.
    }

    // For cases where page names are unique, but full path doesn't match
    // (e.g., index.php for different sections)
    // Removed the problematic second condition that always returned false for basename match.
    return false;
}


// Inclure les fichiers de fonctions et de configuration
// THE FIX IS HERE: Correcting the path to `database.php` and other function files.
// From `templates/`, `../` goes up one level to `bailcompta360/`.
// Then, `fonctions/` is the next directory.
require_once(__DIR__ . '/../fonctions/database.php'); // Corrected path
require_once(__DIR__ . '/../fonctions/gestion_habilitations.php');
require_once(__DIR__ . '/../fonctions/gestion_reports.php');
require_once(__DIR__ . '/../fonctions/gestion_import_statements.php');
require_once(__DIR__ . '/../fonctions/gestion_reconciliation.php');
require_once(__DIR__ . '/../fonctions/gestion_recurring_entries.php');
require_once(__DIR__ . '/../fonctions/gestion_budgets.php');
require_once(__DIR__ . '/../fonctions/gestion_notification.php');

// Simuler une fonction hasPermission (à remplacer par votre logique d'autorisation réelle)
function hasPermission($pdo, $userId, $permission) {
    // Here, you should implement your actual permission checking logic.
    // For this example, we return true as in the original code.
    // You'll need to pass the PDO connection object ($pdo) from database.php.
    // For now, if $pdo is not yet defined here, you might need to
    // remove it from the function signature if hasPermission doesn't
    // directly interact with the database in this mock version.
    return true;
}

// Définition d'une variable $version pour éviter une erreur Undefined variable si elle n'est pas ailleurs
$version = "1.0.0"; // Replace with your actual version number if applicable

if ($estConnecte): // Afficher la navigation si l'utilisateur est connecté (quel que soit son rôle)
?>

<div class="vertical-nav">
    <nav class="nav flex-column">
        <a class="nav-link <?= isActive('pages/tableau_bord.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/tableau_bord.php') ?>">
            <span class="glyphicon glyphicon-dashboard"></span><span>Tableau de Bord</span>
        </a>
        <a class="nav-link <?= isActive('pages/ecritures/saisie.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/ecritures/saisie.php') ?>">
            <span class="glyphicon glyphicon-pencil"></span><span>Saisie Écritures</span>
        </a>
        <a class="nav-link <?= isActive('pages/comptes/index.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/comptes/index.php') ?>">
            <span class="glyphicon glyphicon-list-alt"></span><span>Consul Comptes</span>
        </a>
        <a class="nav-link <?= isActive('pages/ecritures/liste.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/ecritures/liste.php') ?>">
            <span class="glyphicon glyphicon-list"></span><span>Liste Écritures</span>
        </a>
        <a class="nav-link <?= isActive('pages/analyse/index.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/analyse/index.php') ?>">
            <span class="glyphicon glyphicon-stats"></span><span>Analyse</span>
        </a>

        <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/emprunts/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownEmprunts" role="button" data-toggle="collapse" data-target="#collapseEmprunts" aria-expanded="<?= strpos($relative_uri, 'pages/emprunts/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseEmprunts">
            <span class="glyphicon glyphicon-usd"></span><span>Emprunts</span>
        </a>
        <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/emprunts/') === 0 ? 'in' : '' ?>" id="collapseEmprunts">
            <ul class="nav flex-column" style="padding-left: 1.5rem;">
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/emprunts/index.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/emprunts/index.php') ?>">
                        <span class="glyphicon glyphicon-th-list"></span><span>Liste Emprunts</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/emprunts/ajouter.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/emprunts/ajouter.php') ?>">
                        <span class="glyphicon glyphicon-plus-sign"></span><span>Ajouter Emprunt</span>
                    </a>
                </li>
            </ul>
        </div>

        <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/factures/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownFactures" role="button" data-toggle="collapse" data-target="#collapseFactures" aria-expanded="<?= strpos($relative_uri, 'pages/factures/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseFactures">
            <span class="glyphicon glyphicon-duplicate"></span><span>Factures</span>
        </a>
        <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/factures/') === 0 ? 'in' : '' ?>" id="collapseFactures">
            <ul class="nav flex-column" style="padding-left: 1.5rem;">
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/factures/integration.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/factures/integration.php') ?>">
                        <span class="glyphicon glyphicon-upload"></span><span>Intégration</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/factures/listes_factures.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/factures/listes_factures.php') ?>">
                        <span class="glyphicon glyphicon-list"></span><span>Liste</span>
                    </a>
                </li>
            </ul>
        </div>

        <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/generation/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownGeneration" role="button" data-toggle="collapse" data-target="#collapseGeneration" aria-expanded="<?= strpos($relative_uri, 'pages/generation/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseGeneration">
            <span class="glyphicon glyphicon-file"></span><span>Génération ABS 2000</span>
        </a>
        <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/generation/') === 0 ? 'in' : '' ?>" id="collapseGeneration">
            <ul class="nav flex-column" style="padding-left: 1.5rem;">
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/generation/GenEcriture.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/generation/GenEcriture.php') ?>">
                        <span class="glyphicon glyphicon-file"></span><span>Génération écriture</span>
                    </a>
                </li>
            </ul>
        </div>

        <?php if (hasPermission($pdo, isset($_SESSION['utilisateur_id']) ? $_SESSION['utilisateur_id'] : 0, 'gestion_habilitations_menu')): ?>
        <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/admin/habilitations/') === 0 || strpos($relative_uri, 'pages/admin/profils/') === 0 || strpos($relative_uri, 'pages/admin/utilisateurs/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownHabilitations" role="button" data-toggle="collapse" data-target="#collapseHabilitations" aria-expanded="<?= strpos($relative_uri, 'pages/admin/habilitations/') === 0 || strpos($relative_uri, 'pages/admin/profils/') === 0 || strpos($relative_uri, 'pages/admin/utilisateurs/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseHabilitations">
            <span class="glyphicon glyphicon-lock"></span><span>Habilitations</span>
        </a>
        <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/admin/habilitations/') === 0 || strpos($relative_uri, 'pages/admin/profils/') === 0 || strpos($relative_uri, 'pages/admin/utilisateurs/') === 0 ? 'in' : '' ?>" id="collapseHabilitations">
            <ul class="nav flex-column" style="padding-left: 1.5rem;">
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/admin/profils/index.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/profils/index.php') ?>">
                        <span class="glyphicon glyphicon-user"></span><span>Gestion des Profils</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/admin/utilisateurs/index.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/utilisateurs/index.php') ?>">
                        <span class="glyphicon glyphicon-user"></span><span>Gestion des Utilisateurs</span>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (hasPermission($pdo, isset($_SESSION['utilisateur_id']) ? $_SESSION['utilisateur_id'] : 0, 'gestion_utilisateurs_menu')): ?>
        <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/utilisateurs/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownUtilisateursGestion" role="button" data-toggle="collapse" data-target="#collapseUtilisateurs" aria-expanded="<?= strpos($relative_uri, 'pages/utilisateurs/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseUtilisateurs">
            <span class="glyphicon glyphicon-group"></span><span>Utilisateurs</span>
        </a>
        <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/utilisateurs/') === 0 ? 'in' : '' ?>" id="collapseUtilisateurs">
            <ul class="nav flex-column" style="padding-left: 1.5rem;">
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/admin/utilisateurs/index.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/utilisateurs/index.php') ?>">
                        <span class="glyphicon glyphicon-list"></span><span>Liste Utilisateurs</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/admin/utilisateurs/ajouter.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/utilisateurs/ajouter.php') ?>">
                        <span class="glyphicon glyphicon-plus"></span><span>Ajouter Utilisateur</span>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (hasPermission($pdo, isset($_SESSION['utilisateur_id']) ? $_SESSION['utilisateur_id'] : 0, 'gestion_profils_menu')): ?>
        <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/profils/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownProfilsGestion" role="button" data-toggle="collapse" data-target="#collapseProfils" aria-expanded="<?= strpos($relative_uri, 'pages/profils/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseProfils">
            <span class="glyphicon glyphicon-tower"></span><span>Profils</span>
        </a>
        <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/profils/') === 0 ? 'in' : '' ?>" id="collapseProfils">
            <ul class="nav flex-column" style="padding-left: 1.5rem;">
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/admin/profils/index.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/profils/index.php') ?>">
                        <span class="glyphicon glyphicon-list"></span><span>Liste Profils</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/admin/profils/ajouter.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/profils/ajouter.php') ?>">
                        <span class="glyphicon glyphicon-plus"></span><span>Ajouter Profil</span>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (hasPermission($pdo, isset($_SESSION['utilisateur_id']) ? $_SESSION['utilisateur_id'] : 0, 'gestion_budgets_menu')): ?>
        <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/budgets/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownBudgets" role="button" data-toggle="collapse" data-target="#collapseBudgets" aria-expanded="<?= strpos($relative_uri, 'pages/budgets/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseBudgets">
            <span class="glyphicon glyphicon-piggy-bank"></span><span>Budgets</span>
        </a>
        <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/budgets/') === 0 ? 'in' : '' ?>" id="collapseBudgets">
            <ul class="nav flex-column" style="padding-left: 1.5rem;">
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/budgets/create_budget.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/budgets/create_budget.php') ?>">
                        <span class="glyphicon glyphicon-plus-sign"></span><span>Créer Budget</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/budgets/view_budget_performance.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/budgets/view_budget_performance.php') ?>">
                        <span class="glyphicon glyphicon-stats"></span><span>Performance Budget</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/budgets/forecasts.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/budgets/forecasts.php') ?>">
                        <span class="glyphicon glyphicon-calendar"></span><span>Prévisions</span>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (hasPermission($pdo, isset($_SESSION['utilisateur_id']) ? $_SESSION['utilisateur_id'] : 0, 'gestion_reporting_menu')): ?>
        <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/reporting/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownReporting" role="button" data-toggle="collapse" data-target="#collapseReporting" aria-expanded="<?= strpos($relative_uri, 'pages/reporting/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseReporting">
            <span class="glyphicon glyphicon-usd"></span><span>Reporting</span>
        </a>
        <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/reporting/') === 0 ? 'in' : '' ?>" id="collapseReporting">
            <ul class="nav flex-column" style="padding-left: 1.5rem;">
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/reporting/balance.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/reporting/balance.php') ?>">
                        <span class="glyphicon glyphicon-align-left"></span><span>Bilan</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/reporting/profit_loss.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/reporting/profit_loss.php') ?>">
                        <span class="glyphicon glyphicon-fire"></span><span>Compte de Résultat</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/reporting/journal_general.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/reporting/journal_general.php') ?>">
                        <span class="glyphicon glyphicon-book"></span><span>Journal Général</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/reporting/ledger_accounts.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/reporting/ledger_accounts.php') ?>">
                        <span class="glyphicon glyphicon-folder-open"></span><span>Comptes de Grand Livre</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/reporting/cash_flow_statement.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/reporting/cash_flow_statement.php') ?>">
                        <span class="glyphicon glyphicon-transfer"></span><span>Tableau de Flux de Trésorerie</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/reporting/export_reports.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/reporting/export_reports.php') ?>">
                        <span class="glyphicon glyphicon-download-alt"></span><span>Exporter Rapports</span>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (hasPermission($pdo, isset($_SESSION['utilisateur_id']) ? $_SESSION['utilisateur_id'] : 0, 'gestion_audit_menu')): ?>
        <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/admin/audit_trail/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownAudit" role="button" data-toggle="collapse" data-target="#collapseAudit" aria-expanded="<?= strpos($relative_uri, 'pages/admin/audit_trail/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseAudit">
            <span class="glyphicon glyphicon-eye-open"></span><span>Audit</span>
        </a>
        <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/admin/audit_trail/') === 0 ? 'in' : '' ?>" id="collapseAudit">
            <ul class="nav flex-column" style="padding-left: 1.5rem;">
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/admin/audit_trail/view_activity_log.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/audit_trail/view_activity_log.php') ?>">
                        <span class="glyphicon glyphicon-file"></span><span>Journal d'Activité</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/admin/audit_trail/view_login_history.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/audit_trail/view_login_history.php') ?>">
                        <span class="glyphicon glyphicon-log-in"></span><span>Historique de Connexion</span>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (hasPermission($pdo, isset($_SESSION['utilisateur_id']) ? $_SESSION['utilisateur_id'] : 0, 'gestion_data_management_menu')): ?>
        <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/admin/data_management/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownDataManagement" role="button" data-toggle="collapse" data-target="#collapseDataManagement" aria-expanded="<?= strpos($relative_uri, 'pages/admin/data_management/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseDataManagement">
            <span class="glyphicon glyphicon-hdd"></span><span>Gestion des Données</span>
        </a>
        <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/admin/data_management/') === 0 ? 'in' : '' ?>" id="collapseDataManagement">
            <ul class="nav flex-column" style="padding-left: 1.5rem;">
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/admin/data_management/backup_restore.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/data_management/backup_restore.php') ?>">
                        <span class="glyphicon glyphicon-refresh"></span><span>Sauvegarde/Restauration</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/admin/data_management/data_integrity_check.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/data_management/data_integrity_check.php') ?>">
                        <span class="glyphicon glyphicon-ok-circle"></span><span>Vérification d'Intégrité</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/admin/data_management/purge_old_data.php', $relative_uri, $current_page_basename) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/data_management/purge_old_data.php') ?>">
                        <span class="glyphicon glyphicon-trash"></span><span>Purger les Anciennes Données</span>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </nav>
    <div>
        <ul class="nav flex-column" style="padding-left: 1.5rem;">
            <li class="nav-item">
                <a href="<?= generateUrl('pages/utilisateurs/modifier_mot_de_passe.php') ?>" class="change-password-btn">
                    <span class="glyphicon glyphicon-lock"></span><span>Modifier Mot de Passe</span>
                </a>
            </li>
            <li class="nav-item mt-3">
                <small class="text-muted">Version: <?php echo htmlspecialchars($version); ?></small>
            </li>
        </ul>
    </div>
</div>

<script src="<?= generateUrl('js/jquery-3.7.1.js') ?>"></script>
<script src="<?= generateUrl('js/bootstrap.min.js') ?>"></script>

<script>
    // The behavior of collapses is handled directly by Bootstrap 3 with data-toggle="collapse"
    // and data-target. No additional JavaScript logic is needed here for collapses.
    // Your original JavaScript code is no longer relevant for this Bootstrap 3 use case.
</script>

<?php
endif;
?>
</body>
</html>