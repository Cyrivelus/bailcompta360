// js/formulaire.js

$(document).ready(function() {
    // Sélection de tous les formulaires avec la classe 'needs-validation'
    var forms = document.querySelectorAll('.needs-validation');

    // Boucle sur chaque formulaire et empêche la soumission si le formulaire est invalide
    Array.prototype.slice.call(forms)
        .forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });

    // Gestion des messages d'erreur personnalisés (exemple pour un champ email)
    $('#adresse_email').on('input', function() {
        if (!this.validity.valid) {
            if (this.validity.valueMissing) {
                this.setCustomValidity('Veuillez saisir votre adresse email.');
            } else if (this.validity.typeMismatch) {
                this.setCustomValidity('Veuillez saisir une adresse email valide.');
            } else {
                this.setCustomValidity(''); // Réinitialiser le message
            }
        } else {
            this.setCustomValidity(''); // Réinitialiser le message si valide
        }
        this.reportValidity(); // Afficher le message (si invalide)
    });

    // Exemple de validation en temps réel pour la confirmation du mot de passe
    $('#mot_de_passe, #confirmation_mot_de_passe').on('input', function() {
        var motDePasse = $('#mot_de_passe').val();
        var confirmation = $('#confirmation_mot_de_passe').val();
        var confirmationChamp = document.getElementById('confirmation_mot_de_passe');

        if (confirmation !== motDePasse) {
            confirmationChamp.setCustomValidity('Les mots de passe ne correspondent pas.');
        } else {
            confirmationChamp.setCustomValidity('');
        }
        confirmationChamp.reportValidity();
    });

    // Exemple de gestion d'un champ numérique avec des contraintes
    $('#montant').on('input', function() {
        if (this.validity.rangeUnderflow) {
            this.setCustomValidity('Le montant doit être supérieur à ' + this.min + '.');
        } else if (this.validity.rangeOverflow) {
            this.setCustomValidity('Le montant doit être inférieur à ' + this.max + '.');
        } else if (this.validity.stepMismatch) {
            this.setCustomValidity('Veuillez saisir un multiple de ' + this.step + '.');
        } else if (this.validity.valueMissing) {
            this.setCustomValidity('Veuillez saisir un montant.');
        } else {
            this.setCustomValidity('');
        }
        this.reportValidity();
    });

    // Exemple de soumission de formulaire via AJAX (à adapter à votre backend)
    $('form.ajax-form').on('submit', function(event) {
        event.preventDefault(); // Empêche la soumission normale

        if (this.checkValidity()) {
            var $form = $(this);
            $.ajax({
                url: $form.attr('action'),
                method: $form.attr('method'),
                data: $form.serialize(),
                dataType: 'json', // S'attend à une réponse JSON (à adapter)
                success: function(response) {
                    console.log('Succès AJAX:', response);
                    // Gérer la réponse (afficher un message de succès, redirection, etc.)
                    if (response.success) {
                        alert('Formulaire soumis avec succès!');
                        // éventuellement rediriger : window.location.href = response.redirectUrl;
                    } else {
                        alert('Erreur lors de la soumission du formulaire: ' + response.message);
                        // Afficher les erreurs spécifiques du formulaire si elles sont renvoyées
                        if (response.errors) {
                            $.each(response.errors, function(field, message) {
                                $form.find('[name="' + field + '"]').addClass('is-invalid').next('.invalid-feedback').text(message);
                            });
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erreur AJAX:', error);
                    alert('Une erreur s\'est produite lors de l\'envoi du formulaire.');
                }
            });
        } else {
            $(this).addClass('was-validated'); // Afficher les erreurs de validation standard
        }
    });

    // Autres initialisations et fonctions liées aux formulaires
    console.log('Le fichier formulaire.js a été chargé.');
});

// Fonctions utilitaires pour la validation (peuvent être appelées depuis d'autres scripts si nécessaire)
function validerChampVide(champ) {
    if (champ.value.trim() === '') {
        champ.setCustomValidity('Ce champ est obligatoire.');
        return false;
    } else {
        champ.setCustomValidity('');
        return true;
    }
}

function validerEmail(champ) {
    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(champ.value)) {
        champ.setCustomValidity('Veuillez saisir une adresse email valide.');
        return false;
    } else {
        champ.setCustomValidity('');
        return true;
    }
}

// Vous pouvez ajouter d'autres fonctions de validation personnalisées ici