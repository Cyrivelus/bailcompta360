// js/tableau_dynamique.js

$(document).ready(function() {
    // Fonction pour initialiser un tableau dynamique
    function initialiserTableauDynamique(tableSelector, options = {}) {
        var $table = $(tableSelector);

        if ($table.length === 0) {
            console.warn('Tableau dynamique non trouvé pour le sélecteur:', tableSelector);
            return;
        }

        var defaultOptions = {
            // Options par défaut
            selection: false, // Autoriser la sélection de lignes
            pagination: false, // Activer la pagination
            rowsPerPage: 10,    // Nombre de lignes par page
            sortable: true,     // Rendre les colonnes triables
            searchable: false,  // Ajouter une barre de recherche
            onRowClick: null,   // Fonction à exécuter au clic sur une ligne
            onSelectionChange: null // Fonction à exécuter lors du changement de sélection
        };

        var settings = $.extend({}, defaultOptions, options);

        // Gestion de la sélection des lignes
        if (settings.selection) {
            $table.addClass('table-selectable');
            $table.find('tbody').on('click', 'tr', function() {
                $(this).toggleClass('selected');
                if (typeof settings.onRowClick === 'function') {
                    settings.onRowClick($(this));
                }
                if (typeof settings.onSelectionChange === 'function') {
                    settings.onSelectionChange($table.find('tbody tr.selected'));
                }
            });
        } else {
            $table.removeClass('table-selectable');
            $table.find('tbody').off('click', 'tr'); // Supprimer les gestionnaires de clic si la sélection est désactivée
        }

        // Gestion du tri des colonnes
        if (settings.sortable) {
            $table.addClass('table-sortable');
            $table.find('thead th').on('click', function() {
                var columnIndex = $(this).index();
                var sortDirection = $(this).data('sort') || 'asc';

                $table.find('thead th').data('sort', null).removeClass('sorted-asc sorted-desc');
                $(this).data('sort', sortDirection === 'asc' ? 'desc' : 'asc').addClass('sorted-' + (sortDirection === 'asc' ? 'asc' : 'desc'));

                var sortedRows = $table.find('tbody tr').sort(function(a, b) {
                    var aValue = $(a).find('td').eq(columnIndex).text().toLowerCase();
                    var bValue = $(b).find('td').eq(columnIndex).text().toLowerCase();

                    if (!isNaN(parseFloat(aValue)) && isFinite(aValue) && !isNaN(parseFloat(bValue)) && isFinite(bValue)) {
                        aValue = parseFloat(aValue);
                        bValue = parseFloat(bValue);
                    }

                    if (sortDirection === 'asc') {
                        return (aValue > bValue) ? 1 : (aValue < bValue) ? -1 : 0;
                    } else {
                        return (bValue > aValue) ? 1 : (bValue < aValue) ? -1 : 0;
                    }
                });

                $table.find('tbody').empty().append(sortedRows);
            });
        } else {
            $table.removeClass('table-sortable');
            $table.find('thead th').off('click'); // Supprimer les gestionnaires de clic de tri
        }

        // Gestion de la pagination
        if (settings.pagination) {
            $table.addClass('table-paginated');
            var rows = $table.find('tbody tr');
            var numRows = rows.length;
            var numPages = Math.ceil(numRows / settings.rowsPerPage);
            var currentPage = 1;

            function afficherPage(page) {
                rows.hide();
                var startIndex = (page - 1) * settings.rowsPerPage;
                var endIndex = Math.min(startIndex + settings.rowsPerPage, numRows);
                rows.slice(startIndex, endIndex).show();
                mettreAJourPagination(page, numPages);
                currentPage = page;
            }

            function mettreAJourPagination(page, totalPages) {
                var $paginationContainer = $table.next('.pagination-container');
                if ($paginationContainer.length === 0) {
                    $paginationContainer = $('<div class="pagination-container"></div>');
                    $table.after($paginationContainer);
                }
                $paginationContainer.empty();

                if (totalPages > 1) {
                    var prevButton = $('<button class="pagination-prev" data-page="' + (page - 1) + '">&laquo; Précédent</button>');
                    prevButton.prop('disabled', page === 1);
                    $paginationContainer.append(prevButton);

                    var startPage = Math.max(1, page - 2);
                    var endPage = Math.min(totalPages, page + 2);

                    if (startPage > 1) {
                        $paginationContainer.append('<span>...</span>');
                    }

                    for (var i = startPage; i <= endPage; i++) {
                        var pageButton = $('<button class="pagination-page" data-page="' + i + '">' + i + '</button>');
                        if (i === page) {
                            pageButton.addClass('active');
                        }
                        $paginationContainer.append(pageButton);
                    }

                    if (endPage < totalPages) {
                        $paginationContainer.append('<span>...</span>');
                    }

                    var nextButton = $('<button class="pagination-next" data-page="' + (page + 1) + '">Suivant &raquo;</button>');
                    nextButton.prop('disabled', page === totalPages);
                    $paginationContainer.append(nextButton);

                    $paginationContainer.off('click', 'button.pagination-page, button.pagination-prev, button.pagination-next').on('click', 'button.pagination-page, button.pagination-prev, button.pagination-next', function() {
                        var pageNumber = parseInt($(this).data('page'));
                        if (!isNaN(pageNumber) && pageNumber >= 1 && pageNumber <= totalPages) {
                            afficherPage(pageNumber);
                        }
                    });
                }
            }

            afficherPage(currentPage);
        } else {
            $table.removeClass('table-paginated');
            $table.next('.pagination-container').remove(); // Supprimer la pagination si désactivée
            $table.find('tbody tr').show(); // Afficher toutes les lignes
        }

        // Gestion de la barre de recherche
        if (settings.searchable) {
            $table.addClass('table-searchable');
            var $searchContainer = $table.prev('.search-container');
            if ($searchContainer.length === 0) {
                $searchContainer = $('<div class="search-container"><input type="text" class="table-search-input" placeholder="Rechercher..."></input></div>');
                $table.before($searchContainer);
            }

            $searchContainer.find('.table-search-input').on('input', function() {
                var searchTerm = $(this).val().toLowerCase();
                $table.find('tbody tr').each(function() {
                    var rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.indexOf(searchTerm) > -1);
                });
                // Si la pagination est active, revenir à la première page après la recherche
                if (settings.pagination) {
                    afficherPage(1);
                }
            });
        } else {
            $table.removeClass('table-searchable');
            $table.prev('.search-container').remove(); // Supprimer la barre de recherche si désactivée
            if (settings.pagination) {
                var currentPage = $table.next('.pagination-container').find('button.active').data('page') || 1;
                var numRows = $table.find('tbody tr').length;
                var numPages = Math.ceil(numRows / settings.rowsPerPage);
                mettreAJourPagination(currentPage, numPages);
            } else {
                $table.find('tbody tr').show(); // Afficher toutes les lignes si pas de pagination
            }
        }
    }

    // Exemple d'initialisation pour un tableau avec l'ID 'monTableauDynamique' avec la sélection activée
    initialiserTableauDynamique('#monTableauDynamique', {
        selection: true,
        onRowClick: function($row) {
            console.log('Ligne cliquée:', $row.data('id')); // Exemple de récupération d'un attribut data
        },
        onSelectionChange: function($selectedRows) {
            console.log('Nombre de lignes sélectionnées:', $selectedRows.length);
            // Faire quelque chose avec les lignes sélectionnées
        }
    });

    // Exemple d'initialisation pour un tableau avec la pagination et le tri activés
    initialiserTableauDynamique('#monTableauPagine', {
        pagination: true,
        rowsPerPage: 5,
        sortable: true
    });

    // Exemple d'initialisation pour un tableau avec la recherche activée
    initialiserTableauDynamique('#monTableauRecherche', {
        searchable: true
    });

    // Exemple d'initialisation pour un tableau avec toutes les fonctionnalités
    initialiserTableauDynamique('#monTableauComplet', {
        selection: true,
        pagination: true,
        rowsPerPage: 7,
        sortable: true,
        searchable: true,
        onRowClick: function($row) {
            console.log('Ligne cliquée (complet):', $row.find('td:first-child').text());
        }
    });

    console.log('Le fichier tableau_dynamique.js a été chargé.');
});