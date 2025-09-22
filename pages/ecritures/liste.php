<?php
// pages/ecritures/liste.php

$titre = 'Liste des ecritures comptables';
$current_page = basename($_SERVER['PHP_SELF']);

require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_ecritures.php'; // Ce fichier doit contenir la fonction getListeEcritures et supprimerEcriture
// require_once '../../fonctions/gestion_emprunts.php'; // Gardé pour les autres usages s'il y en a, mais peut-être pas nécessaire ici

// Messages
$successMessage = isset($_GET['success']) && $_GET['success'] === '1' ? "Ecriture reussie !" : null;
$deleteMessage = isset($_GET['delete_success']) && $_GET['delete_success'] === '1' ? "L'ecriture a ete supprimee avec succes." : null;
$bulkDeleteMessage = isset($_GET['bulk_delete_success']) && $_GET['bulk_delete_success'] === '1' ? "Les ecritures selectionnees ont ete supprimees avec succes." : null;
$errorMessage = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : null;

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Liste des &eacutecritures</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/formulaire.css">
    <link rel="stylesheet" href="../../css/tableau.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
    /*
     * Styles globaux du corps de la page
     * Ces styles s'appliquent à l'ensemble de la page pour définir une base visuelle.
     */
    body {
        /* Définit une couleur de fond gris très clair pour l'ensemble du document,
           offrant un aspect plus doux et moderne. */
        background-color: #f9f9f9;
    }

    /*
     * Styles des conteneurs de recherche
     * Ces styles gèrent l'espacement et la disposition des éléments de recherche.
     */
    .search-container {
        /* Ajoute une marge de 20 pixels en dessous du conteneur de recherche
           pour le séparer des éléments suivants. */
        margin-bottom: 20px;
    }

    .search-bar {
        /* Centre la barre de recherche horizontalement et limite sa largeur maximale
           pour une meilleure présentation sur de grands écrans. */
        margin: 20px auto;
        max-width: 600px;
    }

    /*
     * Styles de la table des écritures
     * Ces règles CSS définissent l'apparence de la table affichant les écritures comptables,
     * y compris le surlignage des lignes et les indicateurs de tri.
     */
    .highlight {
        /* Couleur de fond utilisée pour surligner les lignes, par exemple, lors d'une recherche.
           Une teinte de jaune pâle pour attirer l'attention sans être agressive. */
        background-color: #fff3cd;
    }

    .table-hover tbody tr {
        /* Applique une transition douce de 0.3 secondes à toutes les propriétés CSS
           qui changent lors du survol des lignes, pour une expérience utilisateur fluide. */
        transition: all 0.3s ease;
    }

    /* Styles pour les indicateurs de tri (flèches haut/bas) dans les en-têtes de tableau. */
    .current-sort-asc:after {
        /* Insère un caractère unicode de triangle pointant vers le haut (?)
           pour indiquer un tri ascendant. */
        content: "\25B2";
        /* Ajoute un petit espace à gauche de l'icône de tri. */
        margin-left: 5px;
        /* Réduit la taille de l'icône par rapport à la taille de la police de base. */
        font-size: 0.8em;
        /* Aligne verticalement l'icône avec le texte de l'en-tête. */
        vertical-align: middle;
    }
    .current-sort-desc:after {
        /* Insère un caractère unicode de triangle pointant vers le bas (?)
           pour indiquer un tri descendant. */
        content: "\25BC";
        /* Ajoute un petit espace à gauche de l'icône de tri. */
        margin-left: 5px;
        /* Réduit la taille de l'icône par rapport à la taille de la police de base. */
        font-size: 0.8em;
        /* Aligne verticalement l'icône avec le texte de l'en-tête. */
        vertical-align: middle;
    }

    th {
        /* Ajoute un rembourrage supplémentaire à droite des en-têtes de colonne
           pour augmenter la zone cliquable et améliorer l'expérience de tri.
           `!important` est utilisé pour s'assurer que cette règle prend le pas
           sur les styles Bootstrap par défaut si nécessaire. */
        padding-right: 20px !important;
    }

    /*
     * Styles pour l'affichage en mode "carte" (potentiellement pour mobile ou vues alternatives)
     * Ces styles définissent l'apparence des éléments affichés comme des cartes individuelles.
     */
    .card {
        /* Fond blanc, bords arrondis et une ombre légère pour créer un effet de carte. */
        background: #fff;
        border-radius: 6px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        /* Marge en bas pour espacer les cartes. */
        margin-bottom: 15px;
        /* Rembourrage interne pour le contenu de la carte. */
        padding: 15px;
    }
    .card-header {
        /* Styles pour l'en-tête des cartes. */
        font-weight: bold;
        font-size: 16px;
        margin-bottom: 8px;
        color: #333;
    }
    .card-date {
        /* Styles pour la date affichée dans les cartes. */
        color: #888;
        font-size: 13px;
        margin-bottom: 10px;
    }
    .card-actions .btn {
        /* Marge à droite pour espacer les boutons d'action dans les cartes. */
        margin-right: 5px;
    }

    /*
     * Styles des messages de feedback et boutons d'action spécifiques
     * Ces règles gèrent l'apparence des messages d'information ou d'erreur
     * et du bouton de suppression groupée.
     */
    #no-results {
        /* Cache initialement le message "aucun résultat". Il sera affiché via JavaScript. */
        display: none;
        /* Marge verticale pour un bon espacement. */
        margin: 20px 0;
        /* Couleur du texte (rouge foncé) et gras pour mettre en évidence le message. */
        color: #a94442; /* Couleur danger de Bootstrap */
        font-weight: bold;
        /* Rembourrage, alignement et fond léger pour le bloc de message. */
        padding: 15px;
        text-align: center;
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
    }

    #deleteSelectedBtn {
        /* Cache le bouton de suppression groupée par défaut. Il sera affiché via JavaScript
           lorsque des éléments sont sélectionnés. */
        display: none;
        /* Marge en bas pour le séparer des autres éléments. */
        margin-bottom: 15px;
        /* Ajout de la marge supérieure issue du style original pour uniformité */
        margin-top: 15px;
    }
</style>
</head>

<body>
<div class="container">
</BR>
</BR>
    <h2 class="page-header">Liste des &eacutecritures Comptables</h2>

    <?php if ($successMessage): ?>
        <div class="alert alert-success"><?= $successMessage ?></div>
    <?php endif; ?>
    <?php if ($deleteMessage): ?>
        <div class="alert alert-warning"><?= $deleteMessage ?></div>
    <?php endif; ?>
    <?php if ($bulkDeleteMessage): ?>
        <div class="alert alert-warning"><?= $bulkDeleteMessage ?></div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <div class="alert alert-danger"><?= $errorMessage ?></div>
    <?php endif; ?>

    <div class="search-container">
        <div class="form-group">
            <input type="text" id="search-input" class="form-control"
                   placeholder="Rechercher (ID, description, code journal...)">
            <small class="form-text text-muted">La recherche s'effectue en temps r&eacuteel</small>
        </div>
        <a href="liste.php" class="btn btn-link">R&eacuteinitialiser</a>
    </div>

   <div style="margin-bottom: 20px;">
    <a href="saisie.php" class="btn btn-primary">
        <span class="glyphicon glyphicon-plus"></span> Ajouter une nouvelle &eacutecriture
    </a>
    <a href="brouillard_ecriture.php" class="btn btn-primary" data-toggle="tooltip" data-placement="top" title="Affichage des &eacutecritures du brouillard par journal, date, type et compte.">
        <span class="glyphicon glyphicon-folder-open"></span> Consultation des brouillards d'&eacutecritures
    </a>
</div>

<script>
    // This script is required to initialize the tooltip from Bootstrap
    $(document).ready(function(){
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>
<script>
    $(document).ready(function(){
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>

    <form action="supprimer_ecritures_groupe.php" method="POST" id="bulkDeleteForm">
        <div class="table-responsive">
            <table id="ecritures-table" class="table table-striped table-bordered table-hover">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAllEcritures"></th>
                      
                    </tr>
                </thead>
                <tbody id="ecritures-body">
                    </tbody>
            </table>
            <div id="no-results" class="no-results">Aucune ecriture trouvee pour cette recherche.</div>
        </div>

        <button type="submit" id="deleteSelectedBtn" class="btn btn-danger" style="display: none;" onclick="return confirm('Etes-vous sur de vouloir supprimer les ecritures selectionnees ? Cette action est irreversible.')">
            <span class="glyphicon glyphicon-trash"></span> Supprimer les &eacutecritures s&eacutelectionn&eacutees
        </button>
    </form>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

	 <script src="../js/jquery-3.7.1.js"></script>
	 
<script>
$(document).ready(function() {
    loadEcritures();

    function loadEcritures(search = '', sort = 'ID_Ecriture', order = 'DESC') {
        $.ajax({
            url: 'ajax_get_ecritures.php',
            method: 'GET',
            data: { search: search, sort: sort, order: order },
            dataType: 'json',
            success: function(data) {
                console.log("Donnees recues :", data);
                updateCards(data);
                $('#selectAllEcritures').prop('checked', false);
                toggleDeleteButtonVisibility();
            },
            error: function(xhr, status, error) {
                console.error("Erreur AJAX:", error);
                $('#no-results').text("? Erreur de chargement : " + error).show();
                $('#ecritures-body').empty();
            }
        });
    }

    function updateCards(ecritures) {
        const $body = $('#ecritures-body');
        $body.empty();

        if (!ecritures || ecritures.length === 0) {
            $('#no-results').text("?? Aucune ecriture trouvee.").show();
            return;
        }

        $('#no-results').hide();

        ecritures.forEach(function(e) {
            const cdeDescription = `${escapeHtml(e.Cde)} - ${escapeHtml(e.Description)}`;
            const dateMonthYear = formatDate(e.Date_Saisie);

            const card = `
                <div class="card">
                    <div class="checkbox pull-right">
                        <label>
                            <input type="checkbox" name="selected_ecritures[]" value="${e.ID_Ecriture}" class="ecriture-checkbox">
                        </label>
                    </div>
                    <div class="card-header">${cdeDescription}</div>
                    <div class="card-date">${dateMonthYear}</div>
                    <div class="card-actions">
                        <a href="modifier.php?id=${e.ID_Ecriture}" class="btn btn-warning btn-sm">
                            <span class="glyphicon glyphicon-folder-open"></span> Consulter
                        </a>
                        <a href="supprimer.php?id=${e.ID_Ecriture}" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer cette ecriture ?')">
                            <span class="glyphicon glyphicon-trash"></span> Supprimer
                        </a>
                        <a href="details.php?id=${e.ID_Ecriture}" class="btn btn-info btn-sm">
                            <span class="glyphicon glyphicon-eye-open"></span> D&eacutetails
                        </a>
                    </div>
                </div>
            `;
            $body.append(card);
        });
    }

    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return new Intl.DateTimeFormat('fr-FR', { year: 'numeric', month: 'long' }).format(date);
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    $('#search-input').on('input', function() {
        loadEcritures($(this).val().trim());
    });

    $('#selectAllEcritures').on('change', function() {
        $('.ecriture-checkbox:visible').prop('checked', $(this).prop('checked'));
        toggleDeleteButtonVisibility();
    });

    $('#ecritures-body').on('change', '.ecriture-checkbox', function() {
        const all = $('.ecriture-checkbox:visible').length;
        const checked = $('.ecriture-checkbox:visible:checked').length;
        $('#selectAllEcritures').prop('checked', all > 0 && checked === all);
        toggleDeleteButtonVisibility();
    });

    function toggleDeleteButtonVisibility() {
        $('#deleteSelectedBtn').toggle($('.ecriture-checkbox:checked').length > 0);
    }

    toggleDeleteButtonVisibility();
});
</script>
</script>

<?php require_once('../../templates/footer.php'); ?>