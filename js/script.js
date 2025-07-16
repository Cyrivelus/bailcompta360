// js/script.js

$(document).ready(function() {
    // Exemple d'écouteur d'événement pour un bouton avec la classe 'ma-classe'
    $('.ma-classe').on('click', function() {
        console.log('Le bouton avec la classe "ma-classe" a été cliqué !');
        // Vous pouvez ajouter ici d'autres actions à effectuer au clic
    });

    // Exemple de gestion de soumission de formulaire (empêche la soumission par défaut)
    $('form.mon-formulaire').on('submit', function(event) {
        event.preventDefault(); // Empêche la soumission normale du formulaire
        console.log('Le formulaire avec la classe "mon-formulaire" a été soumis via JavaScript.');

        // Vous pouvez ici récupérer les données du formulaire et les envoyer via AJAX
        var formData = $(this).serialize();
        console.log('Données du formulaire:', formData);

        // Exemple d'envoi AJAX (à adapter à votre backend)
        /*
        $.ajax({
            url: $(this).attr('action'), // Récupère l'URL de l'attribut 'action' du formulaire
            method: $(this).attr('method'), // Récupère la méthode HTTP ('post' ou 'get')
            data: formData,
            dataType: 'json', // S'attend à une réponse JSON (à adapter)
            success: function(response) {
                console.log('Réponse du serveur:', response);
                // Traiter la réponse (afficher un message de succès, erreurs, etc.)
            },
            error: function(xhr, status, error) {
                console.error('Erreur lors de l\'envoi du formulaire:', error);
                // Afficher un message d'erreur à l'utilisateur
            }
        });
        */
    });

    // Exemple de fonctionnalité pour un tableau dynamique (si vous en avez un)
    if ($('.tableau-dynamique').length > 0) {
        console.log('Le tableau dynamique est présent sur cette page.');

        // Exemple d'ajout d'une classe 'selected' au clic sur une ligne du tableau
        $('.tableau-dynamique tbody').on('click', 'tr', function() {
            $(this).toggleClass('selected');
        });

        // Exemple de récupération des données de la ligne sélectionnée
        $('#obtenir-selection').on('click', function() {
            var selection = $('.tableau-dynamique tbody tr.selected').map(function() {
                return $(this).find('td').map(function() {
                    return $(this).text();
                }).get();
            }).get();

            if (selection.length > 0) {
                console.log('Lignes sélectionnées:', selection);
                // Faire quelque chose avec les données sélectionnées
            } else {
                console.log('Aucune ligne sélectionnée.');
            }
        });
    }

    // Exemple de gestion d'un champ de recherche avec autocomplétion (nécessite une implémentation backend)
    $('#champ-recherche').on('input', function() {
        var query = $(this).val();
        if (query.length >= 3) {
            // Simuler une requête AJAX pour l'autocomplétion
            // Remplacez ceci par votre véritable appel AJAX
            setTimeout(function() {
                var results = ['Résultat 1 pour "' + query + '"', 'Résultat 2 pour "' + query + '"', 'Résultat 3 pour "' + query + '"'];
                $('#suggestions-recherche').empty();
                $.each(results, function(index, value) {
                    $('#suggestions-recherche').append('<div class="suggestion">' + value + '</div>');
                });
                $('#suggestions-recherche').show();
            }, 200);
        } else {
            $('#suggestions-recherche').hide();
            $('#suggestions-recherche').empty();
        }
    });

    // Gérer la sélection d'une suggestion
    $('#suggestions-recherche').on('click', '.suggestion', function() {
        $('#champ-recherche').val($(this).text());
        $('#suggestions-recherche').hide();
        $('#suggestions-recherche').empty();
    });

    // Exemple de confirmation avant une action (suppression par exemple)
    $('.bouton-supprimer').on('click', function(event) {
        event.preventDefault(); // Empêche l'action par défaut du bouton
        var confirmation = confirm('Êtes-vous sûr de vouloir supprimer cet élément ?');
        if (confirmation) {
            // Si l'utilisateur confirme, rediriger vers l'URL de suppression ou soumettre un formulaire
            window.location.href = $(this).attr('href'); // Exemple de redirection
            // Ou $(this).closest('form').submit(); si le bouton est dans un formulaire
        } else {
            console.log('Suppression annulée.');
        }
    });

    // Autres initialisations et fonctions JavaScript pour votre application
    console.log('Le fichier script.js a été chargé.');
});

// Vous pouvez définir des fonctions globales ici si nécessaire
function maFonctionGlobale(param) {
    console.log('Ma fonction globale a été appelée avec le paramètre:', param);
    // Faire quelque chose avec le paramètre
}