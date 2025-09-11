<?php
// pages/analyse/budgets.php

$titre = 'Analyse des budgets';
$current_page = basename($_SERVER['PHP_SELF']);

require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
require_once '../../fonctions/database.php';
// Ce fichier doit contenir les fonctions pour interagir avec les budgets
require_once '../../fonctions/gestion_budgets.php'; 

// Messages
$successMessage = isset($_GET['success']) && $_GET['success'] === '1' ? "Budget reussi !" : null;
$deleteMessage = isset($_GET['delete_success']) && $_GET['delete_success'] === '1' ? "Le budget a ete supprime avec succes." : null;
$bulkDeleteMessage = isset($_GET['bulk_delete_success']) && $_GET['bulk_delete_success'] === '1' ? "Les budgets selectionnes ont ete supprimes avec succes." : null;
$errorMessage = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : null;

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Analyse des Budgets</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/formulaire.css">
    <link rel="stylesheet" href="../../css/tableau.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .highlight {
            background-color: #fff3cd;
        }
        .no-results {
            display: none;
            padding: 15px;
            text-align: center;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        .search-container {
            margin-bottom: 20px;
        }
        .table-hover tbody tr {
            transition: all 0.3s ease;
        }
        #deleteSelectedBtn {
            margin-top: 15px;
            margin-bottom: 15px;
        }
        /* Styles for sort indicators */
        .current-sort-asc:after {
            content: "\25B2"; /* Triangle up */
            margin-left: 5px;
            font-size: 0.8em;
            vertical-align: middle;
        }
        .current-sort-desc:after {
            content: "\25BC"; /* Triangle down */
            margin-left: 5px;
            font-size: 0.8em;
            vertical-align: middle;
        }
        /* Add some padding to table headers for better click target */
        th {
            padding-right: 20px !important; /* Adjust as needed */
        }
    </style>
</head>

<body>
<div class="container">
    <h2 class="page-header">Analyse des Budgets</h2>

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
                   placeholder="Rechercher (ID, nom, année, mois...)">
            <small class="form-text text-muted">La recherche s'effectue en temps r&eacuteel</small>
        </div>
        <a href="budgets.php" class="btn btn-link">R&eacuteinitialiser</a>
    </div>

    <p>
        <a href="saisie_budget.php" class="btn btn-primary">
            <span class="glyphicon glyphicon-plus"></span> Ajouter un nouveau budget
        </a>
    </p>

    <form action="supprimer_budgets_groupe.php" method="POST" id="bulkDeleteForm">
        <div class="table-responsive">
            <table id="budgets-table" class="table table-striped table-bordered table-hover">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAllBudgets"></th>
                        <th data-sort="ID_Budget">ID</th>
                        <th data-sort="Annee">Ann&eacutee</th>
                        <th data-sort="Mois">Mois</th>
                        <th data-sort="Nom_Budget">Nom du Budget</th>
                        <th data-sort="Montant_Prev">Montant Pr&eacutevu</th>
                        <th data-sort="Montant_Reel">Montant R&eacuteel</th>
                        <th data-sort="Ecart">Ecart</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="budgets-body">
                </tbody>
            </table>
            <div id="no-results" class="no-results">Aucun budget trouv&eacute pour cette recherche.</div>
        </div>

        <button type="submit" id="deleteSelectedBtn" class="btn btn-danger" style="display: none;" onclick="return confirm('Etes-vous s&ucircr de vouloir supprimer les budgets s&eacutelectionn&eacutes ? Cette action est irr&eacuteversible.')">
            <span class="glyphicon glyphicon-trash"></span> Supprimer les budgets s&eacutelectionn&eacutes
        </button>
    </form>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Initial load of budgets
    loadBudgets();

    // Function to load budgets
    function loadBudgets(search = '', sort = 'ID_Budget', order = 'DESC') {
        $.ajax({
            url: '../../fonctions/ajax_get_budgets.php',
            method: 'GET',
            data: {
                search: search,
                sort: sort,
                order: order
            },
            dataType: 'json', 
            success: function(data) {
                console.log("Donnees recues (succes) :", data);
                updateTable(data);
                $('#selectAllBudgets').prop('checked', false);
                toggleDeleteButtonVisibility();
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error loading budgets:");
                console.error("Status:", status);
                console.error("Error message:", error);
                console.error("responseText:", xhr.responseText);
                console.error("statusText:", xhr.statusText);
                console.error("statusCode:", xhr.status);

                let userErrorMessage = "Erreur lors du chargement des budgets. ";
                if (status === "parsererror") {
                    userErrorMessage += "Le serveur n'a pas renvoy&eacute du JSON valide. V&eacuterifiez le fichier `ajax_get_budgets.php` pour des erreurs PHP avant l'output JSON. R&eacuteponse du serveur (d&eacutebut) : " + (xhr.responseText ? xhr.responseText.substring(0, 200) + "..." : "Vide");
                } else if (xhr.status === 404) {
                    userErrorMessage += "Le fichier `ajax_get_budgets.php` n'a pas &eacutet&eacute trouv&eacute. V&eacuterifiez le chemin.";
                } else if (xhr.status === 500) {
                    userErrorMessage += "Erreur interne du serveur. V&eacuterifiez les logs d'erreurs de votre serveur pour plus de d&eacutetails.";
                } else {
                    userErrorMessage += `D&eacutetails techniques : ${status} - ${error}.`;
                }

                $('#no-results').text(userErrorMessage).show();
                $('#budgets-body').empty();
            }
        });
    }

    // Function to update the table with fetched budgets
    function updateTable(budgets) {
        const $tbody = $('#budgets-body');
        $tbody.empty();

        if (budgets.length === 0) {
            $('#no-results').text("Aucun budget trouv&eacute pour cette recherche.").show();
            return;
        }

        $('#no-results').hide();

        budgets.forEach(function(budget) {
            const row = `
                <tr>
                    <td><input type="checkbox" name="selected_budgets[]" value="${budget.ID_Budget}" class="budget-checkbox"></td>
                    <td>${budget.ID_Budget}</td>
                    <td>${budget.Annee}</td>
                    <td>${formatMois(budget.Mois)}</td>
                    <td>${escapeHtml(budget.Nom_Budget)}</td>
                    <td>${formatNumber(budget.Montant_Prev)}</td>
                    <td>${formatNumber(budget.Montant_Reel)}</td>
                    <td>${formatNumber(budget.Ecart)}</td>
                    <td>
                        <a href="modifier_budget.php?id=${budget.ID_Budget}" class="btn btn-sm btn-warning" title="Consulter/Modifier">
                            <span class="glyphicon glyphicon-folder-open"></span> Consulter
                        </a>
                        
                        <a href="supprimer_budget.php?id=${budget.ID_Budget}" class="btn btn-sm btn-danger" onclick="return confirm('Etes-vous sur de vouloir supprimer ce budget ?')">
                            <span class="glyphicon glyphicon-trash"></span> Supprimer
                        </a>
                    </td>
                </tr>
            `;
            $tbody.append(row);
        });
    }

    // Helper function to format month names
    function formatMois(mois) {
        const moisNoms = ["Janvier", "F&eacutevrier", "Mars", "Avril", "Mai", "Juin", "Juillet", "Ao&ucirct", "Septembre", "Octobre", "Novembre", "D&eacutecembre"];
        return moisNoms[mois - 1] || 'N/A';
    }

    // Helper function to format numbers
    function formatNumber(number) {
        return new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(parseFloat(number) || 0);
    }

    // Helper function to escape HTML characters
    function escapeHtml(text) {
        if (text === null || typeof text === 'undefined') {
            return '';
        }
        return String(text).replace(/&/g, "&amp;")
                           .replace(/</g, "&lt;")
                           .replace(/>/g, "&gt;")
                           .replace(/"/g, "&quot;")
                           .replace(/'/g, "&#039;");
    }

    // Real-time search
    $('#search-input').on('input', function() {
        const searchTerm = $(this).val().trim();
        loadBudgets(searchTerm);
    });

    // Column sorting logic
    $('#budgets-table').on('click', 'th[data-sort]', function() {
        const sortField = $(this).data('sort');
        let sortOrder = 'DESC';

        if ($(this).hasClass('current-sort-asc')) {
            sortOrder = 'DESC';
            $(this).removeClass('current-sort-asc').addClass('current-sort-desc');
        } else if ($(this).hasClass('current-sort-desc')) {
            sortOrder = 'ASC';
            $(this).removeClass('current-sort-desc').addClass('current-sort-asc');
        } else {
            $('#budgets-table th[data-sort]').removeClass('current-sort-asc current-sort-desc');
            sortOrder = 'DESC';
            $(this).addClass('current-sort-desc');
        }

        loadBudgets($('#search-input').val().trim(), sortField, sortOrder);
    });

    // Focus on search input on page load
    $('#search-input').focus();

    // --- Bulk Delete JavaScript Logic ---
    $('#selectAllBudgets').on('change', function() {
        $('.budget-checkbox:visible').prop('checked', $(this).prop('checked'));
        toggleDeleteButtonVisibility();
    });

    $('#budgets-body').on('change', '.budget-checkbox', function() {
        const totalVisibleCheckboxes = $('.budget-checkbox:visible').length;
        const checkedVisibleCheckboxes = $('.budget-checkbox:visible:checked').length;

        if (totalVisibleCheckboxes > 0 && checkedVisibleCheckboxes === totalVisibleCheckboxes) {
            $('#selectAllBudgets').prop('checked', true);
        } else {
            $('#selectAllBudgets').prop('checked', false);
        }
        toggleDeleteButtonVisibility();
    });

    function toggleDeleteButtonVisibility() {
        if ($('.budget-checkbox:checked').length > 0) {
            $('#deleteSelectedBtn').show();
        } else {
            $('#deleteSelectedBtn').hide();
        }
    }

    toggleDeleteButtonVisibility();
});
</script>

<?php require_once('../../templates/footer.php'); ?>
