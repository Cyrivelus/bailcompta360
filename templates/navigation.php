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
            min-width: 200px;
            max-width: 300px;
            background-color: #f8f9fa;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding-top: 1rem;
            box-shadow: 2px 0 5px rgba(0,0,0,.1);
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .vertical-nav.collapsed {
            min-width: 60px;
            max-width: 60px;
            overflow: hidden;
        }

        .vertical-nav .nav-link {
            color: #495057;
            padding: 0.75rem 1rem;
            border-radius: 0;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
        }

        .vertical-nav .nav-link .glyphicon {
            margin-right: 0.5rem;
        }

        .vertical-nav .nav-link:hover,
        .vertical-nav .nav-link.active {
            background-color: #dc3545;
            color: white;
        }

        .vertical-nav .dropdown-menu {
            position: static;
            float: none;
            width: auto;
            margin-top: 0;
            border: none;
            box-shadow: none;
            background-color: transparent;
            padding-left: 1.5rem;
            display: none;
        }
        
        .vertical-nav .dropdown-menu.in {
            display: block;
        }

        .vertical-nav .dropdown-menu > li > a {
            padding: 0.5rem 1rem;
            color: #495057;
            display: flex;
            align-items: center;
        }

        .vertical-nav .dropdown-menu > li > a .glyphicon {
            margin-right: 0.5rem;
        }

        .vertical-nav .dropdown-menu > li > a:hover,
        .vertical-nav .dropdown-menu > li > a.active {
            background-color: #f8d7da;
            color: #495057;
        }

        body {
            padding-left: 200px;
        }

        /* Styles pour les petits écrans */
        @media (max-width: 768px) {
            .vertical-nav {
                min-width: 60px;
                max-width: 60px;
                overflow-y: auto;
            }

            .vertical-nav .nav-link span,
            .vertical-nav .dropdown-menu > li > a span {
                display: none;
            }

            .vertical-nav .nav-link .glyphicon,
            .vertical-nav .dropdown-menu > li > a .glyphicon {
                margin-right: 0;
            }

            body {
                padding-left: 60px;
            }
        }

        /* Styles pour le bouton de bascule */
        .vertical-nav.collapsed .nav-link span,
        .vertical-nav.collapsed .dropdown-menu > li > a span,
        .vertical-nav.collapsed .dropdown-toggle::after {
            display: none;
        }

        .vertical-nav.collapsed #toggle-btn .glyphicon-chevron-left {
            display: none;
        }

        .vertical-nav.collapsed #toggle-btn .glyphicon-chevron-right {
            display: inline-block;
        }

        #toggle-btn .glyphicon-chevron-right {
            display: none;
        }

        body.collapsed-sidebar {
            padding-left: 60px;
        }

        .vertical-nav.collapsed .dropdown-menu {
            position: absolute;
            left: 100%;
            top: 0;
            min-width: 200px;
            z-index: 999;
            background-color: #f8f9fa;
        }

        /* Styles pour la barre de progression et le bouton de mot de passe (inchangés) */
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
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > 1800) {
    session_unset();
    session_destroy();
    header('Location: ' . generateUrl('index.php'));
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

$estConnecte = isset($_SESSION['utilisateur_id']);

function generateUrl($path) {
    return '/bailcompta360/' . ltrim($path, '/');
}

$current_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_path = '/bailcompta360/';
$relative_uri = str_replace($base_path, '', $current_uri);
$current_page_basename = basename($_SERVER['PHP_SELF']);

function isActive($link_path, $current_uri_relative) {
    $link_uri = parse_url(generateUrl($link_path), PHP_URL_PATH);
    $link_uri_relative = str_replace('/bailcompta360/', '', $link_uri);

    if ($current_uri_relative === $link_uri_relative) {
        return true;
    }

    $parent_folder = dirname($link_path);
    if (strpos($current_uri_relative, $parent_folder . '/') === 0 && !empty($parent_folder) && $parent_folder !== '.') {
        return true;
    }

    return false;
}

require_once(__DIR__ . '/../fonctions/database.php');
require_once(__DIR__ . '/../fonctions/gestion_habilitations.php');
require_once(__DIR__ . '/../fonctions/gestion_reports.php');
require_once(__DIR__ . '/../fonctions/gestion_import_statements.php');
require_once(__DIR__ . '/../fonctions/gestion_reconciliation.php');
require_once(__DIR__ . '/../fonctions/gestion_recurring_entries.php');
require_once(__DIR__ . '/../fonctions/gestion_budgets.php');
require_once(__DIR__ . '/../fonctions/gestion_notification.php');

function hasPermission($pdo, $userId, $permission) {
    return true;
}

$version = "1.0.0";

if ($estConnecte):
?>
<div class="vertical-nav" id="sidebar">
    <button id="toggle-btn" class="btn btn-default" style="position: absolute; top: 10px; right: -40px; z-index: 1000;">
        <span class="glyphicon glyphicon-chevron-left"></span>
        <span class="glyphicon glyphicon-chevron-right"></span>
    </button>
    <nav class="nav flex-column">
        <a class="nav-link <?= isActive('pages/tableau_bord.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/tableau_bord.php') ?>">
            <span class="glyphicon glyphicon-dashboard"></span><span>Tableau de Bord</span>
        </a>

        <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/ecritures/') === 0 || strpos($relative_uri, 'pages/comptes/') === 0 || strpos($relative_uri, 'pages/journaux/') === 0 || strpos($relative_uri, 'pages/lettrage/') === 0 || strpos($relative_uri, 'pages/reconciliation/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownCompta" role="button" data-toggle="collapse" data-target="#collapseCompta" aria-expanded="<?= strpos($relative_uri, 'pages/ecritures/') === 0 || strpos($relative_uri, 'pages/comptes/') === 0 || strpos($relative_uri, 'pages/journaux/') === 0 || strpos($relative_uri, 'pages/lettrage/') === 0 || strpos($relative_uri, 'pages/reconciliation/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseCompta">
            <span class="glyphicon glyphicon-book"></span><span>Comptabilité</span>
        </a>
        <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/ecritures/') === 0 || strpos($relative_uri, 'pages/comptes/') === 0 || strpos($relative_uri, 'pages/journaux/') === 0 || strpos($relative_uri, 'pages/lettrage/') === 0 || strpos($relative_uri, 'pages/reconciliation/') === 0 ? 'in' : '' ?>" id="collapseCompta">
            <ul class="nav flex-column" style="padding-left: 1.5rem;">
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/ecritures/saisie.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/ecritures/saisie.php') ?>">
                        <span class="glyphicon glyphicon-pencil"></span><span>Saisie Écritures</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/ecritures/liste.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/ecritures/liste.php') ?>">
                        <span class="glyphicon glyphicon-list"></span><span>Liste Écritures</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/comptes/index.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/comptes/index.php') ?>">
                        <span class="glyphicon glyphicon-list-alt"></span><span>Consul Comptes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/journaux/index.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/journaux/index.php') ?>">
                        <span class="glyphicon glyphicon-book"></span><span>Journaux</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/lettrage/index.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/lettrage/index.php') ?>">
                        <span class="glyphicon glyphicon-ok"></span><span>Lettrage</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/reconciliation/index.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/reconciliation/index.php') ?>">
                        <span class="glyphicon glyphicon-retweet"></span><span>Réconciliation</span>
                    </a>
                </li>
            </ul>
        </div>

        <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/clients/') === 0 || strpos($relative_uri, 'pages/fournisseurs/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownTiers" role="button" data-toggle="collapse" data-target="#collapseTiers" aria-expanded="<?= strpos($relative_uri, 'pages/clients/') === 0 || strpos($relative_uri, 'pages/fournisseurs/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseTiers">
            <span class="glyphicon glyphicon-user"></span><span>Tiers</span>
        </a>
        <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/clients/') === 0 || strpos($relative_uri, 'pages/fournisseurs/') === 0 ? 'in' : '' ?>" id="collapseTiers">
            <ul class="nav flex-column" style="padding-left: 1.5rem;">
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/clients/index.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/clients/index.php') ?>">
                        <span class="glyphicon glyphicon-user"></span><span>Gestion Clients</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/fournisseurs/index.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/fournisseurs/index.php') ?>">
                        <span class="glyphicon glyphicon-briefcase"></span><span>Gestion Fournisseurs</span>
                    </a>
                </li>
            </ul>
        </div>
       <a class="nav-link <?= isActive('pages/analyse/index.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/analyse/index.php') ?>">
    <span class="glyphicon glyphicon-equalizer"></span><span>Analyse</span>
</a>
        <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/operations/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownOperations" role="button" data-toggle="collapse" data-target="#collapseOperations" aria-expanded="<?= strpos($relative_uri, 'pages/operations/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseOperations">
            <span class="glyphicon glyphicon-usd"></span><span>Opérations Caisse</span>
        </a>
        <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/operations/') === 0 ? 'in' : '' ?>" id="collapseOperations">
            <ul class="nav flex-column" style="padding-left: 1.5rem;">
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/operations/depot.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/operations/depot.php') ?>">
                        <span class="glyphicon glyphicon-plus"></span><span>Dépôt</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/operations/retrait.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/operations/retrait.php') ?>">
                        <span class="glyphicon glyphicon-minus"></span><span>Retrait</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/operations/virement.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/operations/virement.php') ?>">
                        <span class="glyphicon glyphicon-transfer"></span><span>Virement</span>
                    </a>
                </li>
            </ul>
        </div>

        <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/produits/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownProduits" role="button" data-toggle="collapse" data-target="#collapseProduits" aria-expanded="<?= strpos($relative_uri, 'pages/produits/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseProduits">
            <span class="glyphicon glyphicon-tags"></span><span>Produits Bancaires</span>
        </a>
        <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/produits/') === 0 ? 'in' : '' ?>" id="collapseProduits">
            <ul class="nav flex-column" style="padding-left: 1.5rem;">
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/produits/produits_epargne.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/produits/produits_epargne.php') ?>">
                        <span class="glyphicon glyphicon-piggy-bank"></span><span>Produits Épargne</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/produits/produits_credits.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/produits/produits_credits.php') ?>">
                        <span class="glyphicon glyphicon-euro"></span><span>Produits Crédits</span>
                    </a>
                </li>
            </ul>
        </div>

        <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/emprunts/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownEmprunts" role="button" data-toggle="collapse" data-target="#collapseEmprunts" aria-expanded="<?= strpos($relative_uri, 'pages/emprunts/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseEmprunts">
            <span class="glyphicon glyphicon-usd"></span><span>Emprunts</span>
        </a>
        <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/emprunts/') === 0 ? 'in' : '' ?>" id="collapseEmprunts">
            <ul class="nav flex-column" style="padding-left: 1.5rem;">
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/emprunts/index.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/emprunts/index.php') ?>">
                        <span class="glyphicon glyphicon-th-list"></span><span>Liste Emprunts</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/emprunts/ajouter.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/emprunts/ajouter.php') ?>">
                        <span class="glyphicon glyphicon-plus-sign"></span><span>Ajouter Emprunt</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/immobilisations/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownImmobilisations" role="button" data-toggle="collapse" data-target="#collapseImmobilisations" aria-expanded="<?= strpos($relative_uri, 'pages/immobilisations/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseImmobilisations">
            <span class="glyphicon glyphicon-home"></span><span>Immobilisations</span>
        </a>
        <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/immobilisations/') === 0 ? 'in' : '' ?>" id="collapseImmobilisations">
            <ul class="nav flex-column" style="padding-left: 1.5rem;">
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/immobilisations/liste.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/immobilisations/liste.php') ?>">
                        <span class="glyphicon glyphicon-list-alt"></span><span>Liste Immobilisations</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/immobilisations/ajouter.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/immobilisations/ajouter.php') ?>">
                        <span class="glyphicon glyphicon-plus"></span><span>Ajouter une Immobilisation</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/immobilisations/calcul_amortissement.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/immobilisations/calcul_amortissement.php') ?>">
                        <span class="glyphicon glyphicon-fire"></span><span>Calcul Amortissement</span>
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
                    <a class="dropdown-item <?= isActive('pages/factures/integration.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/factures/integration.php') ?>">
                        <span class="glyphicon glyphicon-upload"></span><span>Intégration</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/factures/listes_factures.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/factures/listes_factures.php') ?>">
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
                    <a class="dropdown-item <?= isActive('pages/generation/GenEcriture.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/generation/GenEcriture.php') ?>">
                        <span class="glyphicon glyphicon-file"></span><span>Génération écriture</span>
                    </a>
                </li>
            </ul>
        </div>

        <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/budgets/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownBudgets" role="button" data-toggle="collapse" data-target="#collapseBudgets" aria-expanded="<?= strpos($relative_uri, 'pages/budgets/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseBudgets">
            <span class="glyphicon glyphicon-piggy-bank"></span><span>Budgets</span>
        </a>
        <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/budgets/') === 0 ? 'in' : '' ?>" id="collapseBudgets">
            <ul class="nav flex-column" style="padding-left: 1.5rem;">
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/budgets/create_budget.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/budgets/create_budget.php') ?>">
                        <span class="glyphicon glyphicon-plus-sign"></span><span>Créer Budget</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/budgets/view_budget_performance.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/budgets/view_budget_performance.php') ?>">
                        <span class="glyphicon glyphicon-stats"></span><span>Performance Budget</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/budgets/forecasts.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/budgets/forecasts.php') ?>">
                        <span class="glyphicon glyphicon-calendar"></span><span>Prévisions</span>
                    </a>
                </li>
            </ul>
        </div>

        <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/reporting/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownReporting" role="button" data-toggle="collapse" data-target="#collapseReporting" aria-expanded="<?= strpos($relative_uri, 'pages/reporting/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseReporting">
            <span class="glyphicon glyphicon-usd"></span><span>Reporting</span>
        </a>
        <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/reporting/') === 0 ? 'in' : '' ?>" id="collapseReporting">
            <ul class="nav flex-column" style="padding-left: 1.5rem;">
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/reporting/balance.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/reporting/balance.php') ?>">
                        <span class="glyphicon glyphicon-align-left"></span><span>Bilan</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/reporting/profit_loss.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/reporting/profit_loss.php') ?>">
                        <span class="glyphicon glyphicon-fire"></span><span>Compte de Résultat</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/reporting/journal_general.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/reporting/journal_general.php') ?>">
                        <span class="glyphicon glyphicon-book"></span><span>Journal Général</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/reporting/balance_generale.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/reporting/balance_generale.php') ?>">
                        <span class="glyphicon glyphicon-stats"></span><span>Balance Générale</span>
                    </a>
                </li>
                <li class="nav-item">
    <a class="dropdown-item <?= isActive('pages/reporting/ledger_accounts.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/reporting/ledger_accounts.php') ?>">
        <span class="glyphicon glyphicon-book"></span><span>Grand Livre</span>
    </a>
</li>
<li class="nav-item">
    <a class="dropdown-item <?= isActive('pages/reporting/cash_flow_statements.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/reporting/cash_flow_statement.php') ?>">
        <span class="glyphicon glyphicon-transfer"></span><span>Flux de trésorerie</span>
    </a>
</li>
                <li class="nav-item">
                    <a class="dropdown-item <?= isActive('pages/reporting/liasse_fiscale.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/reporting/liasse_fiscale.php') ?>">
                        <span class="glyphicon glyphicon-list-alt"></span><span>Liasse Fiscale</span>
                    </a>
                </li>
            </ul>
        </div>
        
       <?php if (hasPermission($pdo, isset($_SESSION['utilisateur_id']) ? $_SESSION['utilisateur_id'] : 0, 'gestion_habilitations_menu') || hasPermission($pdo, isset($_SESSION['utilisateur_id']) ? $_SESSION['utilisateur_id'] : 0, 'gestion_utilisateurs_menu') || hasPermission($pdo, isset($_SESSION['utilisateur_id']) ? $_SESSION['utilisateur_id'] : 0, 'gestion_profils_menu') || strpos($relative_uri, 'pages/agences/') === 0 || strpos($relative_uri, 'pages/import/') === 0 || strpos($relative_uri, 'pages/admin/') === 0): ?>
    <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/admin/') === 0 || strpos($relative_uri, 'pages/agences/') === 0 || strpos($relative_uri, 'pages/import/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownAdmin" role="button" data-toggle="collapse" data-target="#collapseAdmin" aria-expanded="<?= strpos($relative_uri, 'pages/admin/') === 0 || strpos($relative_uri, 'pages/agences/') === 0 || strpos($relative_uri, 'pages/import/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseAdmin">
        <span class="glyphicon glyphicon-cog"></span><span>Administration & Sécurité</span>
    </a>
    <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/admin/') === 0 || strpos($relative_uri, 'pages/agences/') === 0 || strpos($relative_uri, 'pages/import/') === 0 ? 'in' : '' ?>" id="collapseAdmin">
        <ul class="nav flex-column" style="padding-left: 1.5rem;">
            
            <?php if (hasPermission($pdo, isset($_SESSION['utilisateur_id']) ? $_SESSION['utilisateur_id'] : 0, 'gestion_habilitations_menu') || hasPermission($pdo, isset($_SESSION['utilisateur_id']) ? $_SESSION['utilisateur_id'] : 0, 'gestion_profils_menu') || hasPermission($pdo, isset($_SESSION['utilisateur_id']) ? $_SESSION['utilisateur_id'] : 0, 'gestion_utilisateurs_menu')): ?>
           <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/admin/profils/') === 0 || strpos($relative_uri, 'pages/admin/utilisateurs/') === 0 || strpos($relative_uri, 'pages/admin/habilitations/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownHabilitations" role="button" data-toggle="collapse" data-target="#collapseHabilitations" aria-expanded="<?= strpos($relative_uri, 'pages/admin/profils/') === 0 || strpos($relative_uri, 'pages/admin/utilisateurs/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseHabilitations">
                <span class="glyphicon glyphicon-lock"></span><span>Gestion des accès</span>
            </a>
            <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/admin/profils/') === 0 || strpos($relative_uri, 'pages/admin/utilisateurs/') === 0 || strpos($relative_uri, 'pages/admin/habilitations/') === 0 ? 'in' : '' ?>" id="collapseHabilitations">
                <ul class="nav flex-column" style="padding-left: 1.5rem;">
                    <li class="nav-item">
                        <a class="dropdown-item <?= isActive('pages/admin/profils/index.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/profils/index.php') ?>">
                            <span class="glyphicon glyphicon-briefcase"></span><span>Gestion des Profils</span>
                        </a>
                    </li>
					<li class="nav-item">
                        <a class="dropdown-item <?= isActive('pages/admin/habilitations/index.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/habilitations/index.php') ?>">
                           <span class="glyphicon glyphicon-lock"></span><span>Gestion des habilitations</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="dropdown-item <?= isActive('pages/admin/utilisateurs/index.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/utilisateurs/index.php') ?>">
                            <span class="glyphicon glyphicon-user"></span><span>Gestion des Utilisateurs</span>
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
            
            <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/admin/plan_comptable/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownPCG" role="button" data-toggle="collapse" data-target="#collapsePCG" aria-expanded="<?= strpos($relative_uri, 'pages/admin/plan_comptable/') === 0 ? 'true' : 'false' ?>" aria-controls="collapsePCG">
                <span class="glyphicon glyphicon-book"></span><span>Plan Comptable</span>
            </a>
            <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/admin/plan_comptable/') === 0 ? 'in' : '' ?>" id="collapsePCG">
                <ul class="nav flex-column" style="padding-left: 1.5rem;">
                    <li class="nav-item">
                        <a class="dropdown-item <?= isActive('pages/admin/plan_comptable/index.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/plan_comptable/index.php') ?>">
                            <span class="glyphicon glyphicon-list-alt"></span><span>Liste des Comptes</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="dropdown-item <?= isActive('pages/admin/plan_comptable/importer_pcg.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/plan_comptable/importer_pcg.php') ?>">
                            <span class="glyphicon glyphicon-upload"></span><span>Importer PCG</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="dropdown-item <?= isActive('pages/admin/plan_comptable/modifier_compte.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/plan_comptable/modifier_compte.php') ?>">
                            <span class="glyphicon glyphicon-edit"></span><span>Modifier un Compte</span>
                        </a>
                    </li>
                </ul>
            </div>

            <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/admin/data_management/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownData" role="button" data-toggle="collapse" data-target="#collapseData" aria-expanded="<?= strpos($relative_uri, 'pages/admin/data_management/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseData">
                <span class="glyphicon glyphicon-hdd"></span><span>Gestion de Données</span>
            </a>
            <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/admin/data_management/') === 0 ? 'in' : '' ?>" id="collapseData">
                <ul class="nav flex-column" style="padding-left: 1.5rem;">
                    <li class="nav-item">
                        <a class="dropdown-item <?= isActive('pages/admin/data_management/backup_restore.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/data_management/backup_restore.php') ?>">
                            <span class="glyphicon glyphicon-floppy-disk"></span><span>Sauvegarde & Restauration</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="dropdown-item <?= isActive('pages/admin/data_management/data_integrity_check.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/data_management/data_integrity_check.php') ?>">
                            <span class="glyphicon glyphicon-ok-circle"></span><span>Vérification d'Intégrité</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="dropdown-item <?= isActive('pages/admin/data_management/index.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/data_management/index.php') ?>">
                            <span class="glyphicon glyphicon-cog"></span><span>Outils de Gestion</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="dropdown-item <?= isActive('pages/admin/data_management/purge_old_data.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/data_management/purge_old_data.php') ?>">
                            <span class="glyphicon glyphicon-trash"></span><span>Purge des Données</span>
                        </a>
                    </li>
                </ul>
            </div>

            <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/admin/audit/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownAudit" role="button" data-toggle="collapse" data-target="#collapseAudit" aria-expanded="<?= strpos($relative_uri, 'pages/admin/audit/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseAudit">
                <span class="glyphicon glyphicon-check"></span><span>Audit</span>
            </a>
            <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/admin/audit/') === 0 ? 'in' : '' ?>" id="collapseAudit">
                <ul class="nav flex-column" style="padding-left: 1.5rem;">
                    <li class="nav-item">
                        <a class="dropdown-item <?= isActive('pages/admin/audit/view_activity_log.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/audit/view_activity_log.php') ?>">
                            <span class="glyphicon glyphicon-list"></span><span>Journal d'Activités</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="dropdown-item <?= isActive('pages/admin/audit/view_login_history.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/admin/audit/view_login_history.php') ?>">
                            <span class="glyphicon glyphicon-time"></span><span>Historique des Connexions</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/import/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownImport" role="button" data-toggle="collapse" data-target="#collapseImport" aria-expanded="<?= strpos($relative_uri, 'pages/import/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseImport">
                <span class="glyphicon glyphicon-import"></span><span>Import de Données</span>
            </a>
            <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/import/') === 0 ? 'in' : '' ?>" id="collapseImport">
                <ul class="nav flex-column" style="padding-left: 1.5rem;">
                    <li class="nav-item">
                        <a class="dropdown-item <?= isActive('pages/import/releves.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/import/releves.php') ?>">
                            <span class="glyphicon glyphicon-file"></span><span>Relevés Bancaires</span>
                        </a>
                    </li>
                </ul>
            </div>

            <a class="nav-link dropdown-toggle <?= strpos($relative_uri, 'pages/agences/') === 0 ? 'active' : '' ?>" href="#" id="navbarDropdownAgences" role="button" data-toggle="collapse" data-target="#collapseAgences" aria-expanded="<?= strpos($relative_uri, 'pages/agences/') === 0 ? 'true' : 'false' ?>" aria-controls="collapseAgences">
                <span class="glyphicon glyphicon-map-marker"></span><span>Agences</span>
            </a>
            <div class="panel-collapse collapse <?= strpos($relative_uri, 'pages/agences/') === 0 ? 'in' : '' ?>" id="collapseAgences">
                <ul class="nav flex-column" style="padding-left: 1.5rem;">
                    <li class="nav-item">
                        <a class="dropdown-item <?= isActive('pages/agences/index.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/agences/index.php') ?>">
                            <span class="glyphicon glyphicon-list"></span><span>Liste Agences</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="dropdown-item <?= isActive('pages/agences/gestion_caisses.php', $relative_uri) ? 'active' : '' ?>" href="<?= generateUrl('pages/agences/gestion_caisses.php') ?>">
                            <span class="glyphicon glyphicon-piggy-bank"></span><span>Gestion Caisse</span>
                        </a>
                    </li>
                </ul>
            </div>
        </ul>
    </div>
<?php endif; ?>
<a class="nav-link" href="#" style="font-size: 0.9em; color: #6c757d; padding-top: 20px;">
        <span class="glyphicon glyphicon-tag"></span><span>Version 1.0</span>
    </a>
    </nav>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

<script>
$(document).ready(function () {
    $("#toggle-btn").on("click", function () {
        $("#sidebar").toggleClass("collapsed");
        $("body").toggleClass("collapsed-sidebar");
    });
});
</script>

<?php else: ?>
<div class="vertical-nav" id="sidebar">
    <nav class="nav flex-column">
        <a class="nav-link" href="<?= generateUrl('index.php') ?>">
            <span class="glyphicon glyphicon-home"></span><span>Accueil</span>
        </a>
    </nav>
</div>
<?php endif; ?>

</body>
</html>