/**
 * Gestion de la liste des écritures comptables
 * Fonctionnalités :
 * - Chargement asynchrone des données
 * - Recherche en temps réel
 * - Tri des colonnes
 * - Sélection multiple
 * - Pagination
 * - Export des données
 */

$(document).ready(function() {
    // Configuration
    const config = {
        itemsPerPage: 20,
        currentPage: 1,
        currentSort: 'ID_Ecriture',
        currentOrder: 'DESC'
    };

    // Initialisation
    init();

    function init() {
        loadEcritures();
        setupEventListeners();
    }

    function setupEventListeners() {
        // Recherche
        $('#search-input').on('input', debounce(function() {
            config.currentPage = 1;
            loadEcritures();
        }, 300));

        // Réinitialisation recherche
        $('#reset-search').click(function() {
            $('#search-input').val('').trigger('input');
        });

        // Tri des colonnes
        $('#ecritures-table').on('click', '.sortable-header', function() {
            const sortField = $(this).data('sort');
            
            if ($(this).hasClass('current-sort-asc')) {
                config.currentOrder = 'DESC';
                $(this).removeClass('current-sort-asc').addClass('current-sort-desc');
            } else {
                config.currentOrder = 'ASC';
                $('.sortable-header').removeClass('current-sort-asc current-sort-desc');
                $(this).addClass('current-sort-' + config.currentOrder.toLowerCase());
            }
            
            config.currentSort = sortField;
            loadEcritures();
        });

        // Sélection multiple
        $('#selectAllEcritures').change(function() {
            $('.ecriture-checkbox:visible').prop('checked', $(this).prop('checked'));
            toggleDeleteButton();
        });

        $(document).on('change', '.ecriture-checkbox', function() {
            const allChecked = $('.ecriture-checkbox:visible').length === $('.ecriture-checkbox:visible:checked').length;
            $('#selectAllEcritures').prop('checked', allChecked);
            toggleDeleteButton();
        });

        // Export
        $('#exportBtn').click(exportData);
    }

    function loadEcritures() {
        showLoading(true);
        
        $.ajax({
            url: 'ajax_get_ecritures.php',
            method: 'GET',
            data: {
                search: $('#search-input').val().trim(),
                sort: config.currentSort,
                order: config.currentOrder,
                page: config.currentPage,
                limit: config.itemsPerPage
            },
            dataType: 'json'
        })
        .done(function(response) {
            if (response.success) {
                updateTable(response.data.ecritures);
                updatePagination(response.data.total, response.data.page);
            } else {
                showError(response.message || "Erreur lors du chargement des données.");
            }
        })
        .fail(handleAjaxError)
        .always(function() {
            showLoading(false);
        });
    }

    function updateTable(ecritures) {
        const $tbody = $('#ecritures-body');
        $tbody.empty();

        if (!ecritures || ecritures.length === 0) {
            $('#no-results').show();
            return;
        }

        $('#no-results').hide();

        ecritures.forEach(ecriture => {
            $tbody.append(`
                <tr>
                    <td><input type="checkbox" name="selected_ecritures[]" value="${ecriture.ID_Ecriture}" class="ecriture-checkbox"></td>
                    <td>${ecriture.ID_Ecriture}</td>
                    <td>${formatDate(ecriture.Date_Saisie)}</td>
                    <td>${escapeHtml(ecriture.Description)}</td>
                    <td class="text-right">${formatNumber(ecriture.Montant_Total)}</td>
                    <td>${escapeHtml(ecriture.Cde)}</td>
                    <td class="actions">
                        <div class="btn-group">
                            <a href="modifier.php?id=${ecriture.ID_Ecriture}" class="btn btn-xs btn-warning" title="Modifier">
                                <span class="glyphicon glyphicon-pencil"></span>
                            </a>
                            <a href="supprimer.php?id=${ecriture.ID_Ecriture}" class="btn btn-xs btn-danger" 
                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette écriture ?')" title="Supprimer">
                                <span class="glyphicon glyphicon-trash"></span>
                            </a>
                            <a href="details.php?id=${ecriture.ID_Ecriture}" class="btn btn-xs btn-info" title="Détails">
                                <span class="glyphicon glyphicon-eye-open"></span>
                            </a>
                        </div>
                    </td>
                </tr>
            `);
        });
    }

    function updatePagination(totalItems, currentPage) {
        const $pagination = $('#pagination');
        $pagination.empty();
        
        if (totalItems <= config.itemsPerPage) return;
        
        const totalPages = Math.ceil(totalItems / config.itemsPerPage);
        currentPage = parseInt(currentPage) || 1;
        
        // Previous button
        $pagination.append(`
            <li class="${currentPage === 1 ? 'disabled' : ''}">
                <a href="#" aria-label="Previous" data-page="${currentPage - 1}">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
        `);
        
        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            $pagination.append(`
                <li class="${i === currentPage ? 'active' : ''}">
                    <a href="#" data-page="${i}">${i}</a>
                </li>
            `);
        }
        
        // Next button
        $pagination.append(`
            <li class="${currentPage === totalPages ? 'disabled' : ''}">
                <a href="#" aria-label="Next" data-page="${currentPage + 1}">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        `);
        
        // Page click handler
        $pagination.on('click', 'a', function(e) {
            e.preventDefault();
            const page = $(this).data('page');
            if (page >= 1 && page <= totalPages && page !== currentPage) {
                config.currentPage = page;
                loadEcritures();
            }
        });
    }

    function toggleDeleteButton() {
        $('#deleteSelectedBtn').toggle($('.ecriture-checkbox:checked').length > 0);
    }

    function exportData() {
        // Implémentation de l'export (CSV, Excel, etc.)
        alert("Fonctionnalité d'export à implémenter");
    }

    function showLoading(show) {
        if (show) {
            $('#ecritures-body').html('<tr><td colspan="7" class="text-center"><div class="loading-spinner"></div></td></tr>');
        }
    }

    function showError(message) {
        $('#no-results').text(message).show();
        $('#ecritures-body').empty();
    }

    function handleAjaxError(xhr) {
        let message = "Erreur lors du chargement des données.";
        
        if (xhr.status === 404) {
            message = "Fichier de données introuvable.";
        } else if (xhr.status === 500) {
            message = "Erreur serveur. Veuillez contacter l'administrateur.";
        } else if (xhr.responseJSON && xhr.responseJSON.message) {
            message = xhr.responseJSON.message;
        }
        
        showError(message);
    }

    // Helper functions
    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }

    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('fr-FR') + ' ' + date.toLocaleTimeString('fr-FR');
    }

    function formatNumber(num) {
        return parseFloat(num || 0).toLocaleString('fr-FR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text.toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});