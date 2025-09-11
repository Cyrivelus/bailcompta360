<?php
/**
 * pages/analyse/performance.php
 *
 * Page d'analyse des performances.
 * Affiche la comparaison entre les budgets et les dépenses réelles.
 */

$titre = 'Analyse de la Performance';
$current_page = basename($_SERVER['PHP_SELF']);

require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_performance.php'; // Ce fichier doit contenir les fonctions d'analyse

// Messages de l'utilisateur
$errorMessage = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : null;

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Analyse de la Performance</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/tableau.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
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
        th {
            padding-right: 20px !important;
        }
    </style>
</head>

<body>
<div class="container">
    <h2 class="page-header">Analyse de la Performance Financière</h2>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger"><?= $errorMessage ?></div>
    <?php endif; ?>

    <div class="search-container">
        <div class="form-group">
            <input type="text" id="search-input" class="form-control"
                   placeholder="Rechercher (compte, année...)">
            <small class="form-text text-muted">La recherche s'effectue en temps réel</small>
        </div>
        <a href="performance.php" class="btn btn-link">Réinitialiser</a>
    </div>

    <div class="table-responsive">
        <table id="performance-table" class="table table-striped table-bordered table-hover">
            <thead>
                <tr>
                    <th data-sort="Annee">Année</th>
                    <th data-sort="ID_Compte">ID Compte</th>
                    <th data-sort="Nom_Compte">Nom Compte</th>
                    <th data-sort="Montant_Budgetise">Budget</th>
                    <th data-sort="Montant_Reel">Réel</th>
                    <th data-sort="Ecart">Écart</th>
                </tr>
            </thead>
            <tbody id="performance-body">
            </tbody>
        </table>
        <div id="no-results" class="no-results">Aucune donnée de performance trouvée pour cette recherche.</div>
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function() {
    // Initial load of performance data
    loadPerformanceData();

    // Function to load performance data
    function loadPerformanceData(search = '', sort = 'Annee', order = 'DESC') {
        $.ajax({
            url: 'ajax_get_performance.php', // Nous devrons créer ce fichier
            method: 'GET',
            data: {
                search: search,
                sort: sort,
                order: order
            },
            dataType: 'json',
            success: function(data) {
                console.log("Données de performance reçues :", data);
                updateTable(data);
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error loading performance data:");
                console.error("Status:", status);
                console.error("Error message:", error);
                let userErrorMessage = "Erreur lors du chargement des données de performance.";
                $('#no-results').text(userErrorMessage).show();
                $('#performance-body').empty();
            }
        });
    }

    // Function to update the table with fetched data
    function updateTable(performanceData) {
        const $tbody = $('#performance-body');
        $tbody.empty();

        if (performanceData.length === 0) {
            $('#no-results').show();
            return;
        }

        $('#no-results').hide();

        performanceData.forEach(function(item) {
            const ecart = parseFloat(item.Montant_Budgetise) - parseFloat(item.Montant_Reel);
            const ecartClass = ecart >= 0 ? 'text-success' : 'text-danger';
            const row = `
                <tr>
                    <td>${item.Annee}</td>
                    <td>${item.ID_Compte}</td>
                    <td>${item.Nom_Compte}</td>
                    <td>${formatNumber(item.Montant_Budgetise)}</td>
                    <td>${formatNumber(item.Montant_Reel)}</td>
                    <td class="${ecartClass}">${formatNumber(ecart)}</td>
                </tr>
            `;
            $tbody.append(row);
        });
    }

    // Helper function to format numbers
    function formatNumber(number) {
        return new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(parseFloat(number) || 0);
    }

    // Real-time search
    $('#search-input').on('input', function() {
        const searchTerm = $(this).val().trim();
        loadPerformanceData(searchTerm);
    });

    // Column sorting logic
    $('#performance-table').on('click', 'th[data-sort]', function() {
        const sortField = $(this).data('sort');
        let sortOrder = 'DESC';

        if ($(this).hasClass('current-sort-asc')) {
            sortOrder = 'DESC';
            $(this).removeClass('current-sort-asc').addClass('current-sort-desc');
        } else if ($(this).hasClass('current-sort-desc')) {
            sortOrder = 'ASC';
            $(this).removeClass('current-sort-desc').addClass('current-sort-asc');
        } else {
            $('#performance-table th[data-sort]').removeClass('current-sort-asc current-sort-desc');
            sortOrder = 'DESC';
            $(this).addClass('current-sort-desc');
        }

        loadPerformanceData($('#search-input').val().trim(), sortField, sortOrder);
    });

    $('#search-input').focus();
});
</script>

<?php require_once('../../templates/footer.php'); ?>
