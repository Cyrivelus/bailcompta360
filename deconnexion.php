<?php
// utilisateurs/deconnexion.php

// Démarre la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Détruit toutes les variables de session
session_unset();

// Détruit la session
session_destroy();

// Redirige l'utilisateur vers la page d'accueil ou une autre page de connexion
header("Location: ../index.php"); // Ajustez le chemin si nécessaire
exit();
?>