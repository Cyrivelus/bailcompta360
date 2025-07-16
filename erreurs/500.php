<?php
// erreurs/500.php

// Définir le statut HTTP pour "Internal Server Error"
http_response_code(500);

// Définir le titre de la page
$titre = 'Erreur serveur (500)';

// Inclure l'en-tête commun
require_once('../templates/header.php');
?>

<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-6 text-center">
            <h1 class="display-1 fw-bold">500</h1>
            <p class="lead">Oops! Une erreur interne du serveur s'est produite.</p>
            <p class="mb-4">Notre équipe a été notifiée et travaille à la résolution de ce problème. Veuillez réessayer ultérieurement.</p>
            <a href="../index.php" class="btn btn-secondary">Retour à l'accueil</a>
        </div>
    </div>
</div>

<?php
// Inclure le pied de page commun
require_once('../templates/footer.php');
?>