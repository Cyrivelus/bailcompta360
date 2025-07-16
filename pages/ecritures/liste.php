<?php
// pages/ecritures/liste.php

$titre = 'Liste des ecritures comptables';
$current_page = basename($_SERVER['PHP_SELF']);

require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_ecritures.php'; // Ce fichier doit contenir la fonction getListeEcritures et supprimerEcriture
// require_once '../../fonctions/gestion_emprunts.php'; // Gard� pour les autres usages s'il y en a, mais peut-�tre pas n�cessaire ici

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
        /* Styles from previous version, unchanged */
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

    <p>
        <a href="saisie.php" class="btn btn-primary">
            <span class="glyphicon glyphicon-plus"></span> Ajouter une nouvelle &eacutecriture
        </a>
    </p>

    <form action="supprimer_ecritures_groupe.php" method="POST" id="bulkDeleteForm">
        <div class="table-responsive">
            <table id="ecritures-table" class="table table-striped table-bordered table-hover">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAllEcritures"></th>
                        <th data-sort="ID_Ecriture">ID</th>
                        <th data-sort="Date_Saisie">Date de Saisie</th>
                        <th data-sort="Description">Description</th>
                        <th data-sort="Montant_Total">Montant Total</th>
                        <th data-sort="Cde">Code Journal</th>
                        <th>Actions</th>
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
    // Initial load of entries
    loadEcritures();

    // Function to load entries
    function loadEcritures(search = '', sort = 'ID_Ecriture', order = 'DESC') {
        $.ajax({
            url: 'ajax_get_ecritures.php',
            method: 'GET',
            data: {
                search: search,
                sort: sort,
                order: order
            },
            dataType: 'json', // Expect JSON response
            success: function(data) {
                // Console log for debugging - keep this during development
                console.log("Donnees recues (succes) :", data);
                updateTable(data);
                // After updating the table, reset "Select All" checkbox and button visibility
                $('#selectAllEcritures').prop('checked', false);
                toggleDeleteButtonVisibility();
            },
            error: function(xhr, status, error) {
                // Enhanced error logging for debugging
                console.error("AJAX Error loading entries:");
                console.error("Status:", status);
                console.error("Error message:", error);
                console.error("responseText:", xhr.responseText);
                console.error("statusText:", xhr.statusText);
                console.error("statusCode:", xhr.status);

                // Provide a user-friendly error message
                let userErrorMessage = "Erreur lors du chargement des �critures. ";
                if (status === "parsererror") {
                    userErrorMessage += "Le serveur n'a pas renvoy� du JSON valide. V�rifiez le fichier `ajax_get_ecritures.php` pour des erreurs PHP avant l'output JSON. R�ponse du serveur (d�but) : " + (xhr.responseText ? xhr.responseText.substring(0, 200) + "..." : "Vide");
                } else if (xhr.status === 404) {
                    userErrorMessage += "Le fichier `ajax_get_ecritures.php` n'a pas �t� trouv�. V�rifiez le chemin.";
                } else if (xhr.status === 500) {
                    userErrorMessage += "Erreur interne du serveur. V�rifiez les logs d'erreurs de votre serveur pour plus de d�tails.";
                } else {
                    userErrorMessage += `D�tails techniques : ${status} - ${error}.`;
                }

                $('#no-results').text(userErrorMessage).show();
                // Clear the table body in case of error
                $('#ecritures-body').empty();
            }
        });
    }

    // Function to update the table with fetched entries
    function updateTable(ecritures) {
        const $tbody = $('#ecritures-body');
        $tbody.empty(); // Clear existing rows

        if (ecritures.length === 0) {
            $('#no-results').text("Aucune �criture trouv�e pour cette recherche.").show();
            return;
        }

        $('#no-results').hide(); // Hide "no results" message if there are entries

        ecritures.forEach(function(ecriture) {
            const row = `
                <tr>
                    <td><input type="checkbox" name="selected_ecritures[]" value="${ecriture.ID_Ecriture}" class="ecriture-checkbox"></td>
                    <td>${ecriture.ID_Ecriture}</td>
                    <td>${formatDate(ecriture.Date_Saisie)}</td>
                    <td>${escapeHtml(ecriture.Description)}</td>
                    <td>${formatNumber(ecriture.Montant_Total)}</td>
                    <td>${escapeHtml(ecriture.Cde)}</td>
                    <td>
                        <a href="modifier.php?id=${ecriture.ID_Ecriture}" class="btn btn-sm btn-warning" title="Consulter/Modifier">
                            <span class="glyphicon glyphicon-folder-open"></span> Consulter
                        </a>
						
                        <a href="supprimer.php?id=${ecriture.ID_Ecriture}" class="btn btn-sm btn-danger" onclick="return confirm('Etes-vous sur de vouloir supprimer cette ecriture ?')">
                            <span class="glyphicon glyphicon-trash"></span> Supprimer
                        </a>
                        <a href="details.php?id=${ecriture.ID_Ecriture}" class="btn btn-sm btn-info" title="Voir les details">
                            <span class="glyphicon glyphicon-eye-open"></span> D&eacutetails
                        </a>
                    </td>
                </tr>
            `;
            $tbody.append(row);
        });
    }

    // Helper function to format date
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return new Intl.DateTimeFormat('fr-FR', {
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        }).format(date);
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
        loadEcritures(searchTerm);
    });

    // Column sorting logic
    $('#ecritures-table').on('click', 'th[data-sort]', function() {
        const sortField = $(this).data('sort');
        let sortOrder = 'DESC';

        // Toggle sort order and update class
        if ($(this).hasClass('current-sort-asc')) {
            sortOrder = 'DESC';
            $(this).removeClass('current-sort-asc').addClass('current-sort-desc');
        } else if ($(this).hasClass('current-sort-desc')) {
            sortOrder = 'ASC';
            $(this).removeClass('current-sort-desc').addClass('current-sort-asc');
        } else {
            // New sort column, default to descending
            $('#ecritures-table th[data-sort]').removeClass('current-sort-asc current-sort-desc'); // Clear other sorts
            sortOrder = 'DESC';
            $(this).addClass('current-sort-desc');
        }

        loadEcritures($('#search-input').val().trim(), sortField, sortOrder);
    });

    // Focus on search input on page load
    $('#search-input').focus();

    // --- Bulk Delete JavaScript Logic ---

    // 1. "Select All" checkbox logic
    $('#selectAllEcritures').on('change', function() {
        $('.ecriture-checkbox:visible').prop('checked', $(this).prop('checked'));
        toggleDeleteButtonVisibility();
    });

    // 2. Individual checkbox logic (event delegation)
    $('#ecritures-body').on('change', '.ecriture-checkbox', function() {
        const totalVisibleCheckboxes = $('.ecriture-checkbox:visible').length;
        const checkedVisibleCheckboxes = $('.ecriture-checkbox:visible:checked').length;

        if (totalVisibleCheckboxes > 0 && checkedVisibleCheckboxes === totalVisibleCheckboxes) {
            $('#selectAllEcritures').prop('checked', true);
        } else {
            $('#selectAllEcritures').prop('checked', false);
        }
        toggleDeleteButtonVisibility();
    });

    // 3. Function to show/hide bulk delete button
    function toggleDeleteButtonVisibility() {
        if ($('.ecriture-checkbox:checked').length > 0) {
            $('#deleteSelectedBtn').show();
        } else {
            $('#deleteSelectedBtn').hide();
        }
    }

    // Initialize button state on page load
    toggleDeleteButtonVisibility();
});
</script>

<?php require_once('../../templates/footer.php'); ?>