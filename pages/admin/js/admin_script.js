// admin/js/admin_script.js

(function () {
  'use strict'

  // Gestion de l'ouverture/fermeture de la sidebar sur les petits écrans
  const sidebarToggle = document.querySelector('.navbar-toggler');
  const sidebar = document.getElementById('sidebarMenu');
  const mainContent = document.querySelector('main');

  if (sidebarToggle && sidebar && mainContent) {
    sidebarToggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      mainContent.classList.toggle('open');
    });
  }

  // Exemple d'écouteur d'événement pour un bouton de suppression (nécessite des boutons avec la classe 'delete-btn' et un attribut 'data-id')
  const deleteButtons = document.querySelectorAll('.delete-btn');
  if (deleteButtons) {
    deleteButtons.forEach(button => {
      button.addEventListener('click', function (event) {
        event.preventDefault();
        const itemId = this.dataset.id;
        if (confirm(`Êtes-vous sûr de vouloir supprimer l'élément avec l'ID : ${itemId} ?`)) {
          // Ici, vous pouvez ajouter la logique AJAX pour envoyer une requête de suppression au serveur
          console.log(`Suppression de l'élément avec l'ID : ${itemId}`);
          // Exemple de redirection après confirmation (à adapter à votre logique)
          // window.location.href = `supprimer.php?id=${itemId}`;
        }
      });
    });
  }

  // Exemple d'écouteur d'événement pour la soumission de formulaires avec confirmation (nécessite un formulaire avec la classe 'confirm-submit')
  const confirmSubmitForms = document.querySelectorAll('.confirm-submit');
  if (confirmSubmitForms) {
    confirmSubmitForms.forEach(form => {
      form.addEventListener('submit', function (event) {
        if (!confirm('Êtes-vous sûr de vouloir soumettre ce formulaire ?')) {
          event.preventDefault();
        }
      });
    });
  }

  // Vous pouvez ajouter d'autres scripts spécifiques à votre interface d'administration ici,
  // comme la validation de formulaires côté client, des interactions AJAX,
  // des manipulations de DOM spécifiques, etc.

})();