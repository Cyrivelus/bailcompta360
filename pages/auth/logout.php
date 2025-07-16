<?php
// auth/logout.php

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Détruire toutes les variables de session
$_SESSION = array();

// Si vous voulez détruire complètement la session, effacez également
// le cookie de session. Note : cela détruira la session, et pas seulement
// les données de session !
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalement, détruire la session
session_destroy();

// Rediriger l'utilisateur vers la page de connexion (ou une autre page de votre choix)
header("Location: ../index.php"); // Assurez-vous que le chemin vers login.php est correct
exit();
?>