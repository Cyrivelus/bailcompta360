<?php
// pages/admin/utilisateurs/unsuspendre.php

session_start();

require_once(__DIR__ . '/../../../fonctions/database.php');
require_once(__DIR__ . '/../../../fonctions/gestion_utilisateurs.php');

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Admin') {
    $_SESSION['flash_message'] = "Accès non autorisé.";
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../../index.php");
    exit();
}

$pdo = getDatabaseConnection();

if (isset($_GET['id'])) {
    $userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($userId === false || $userId <= 0) {
        $_SESSION['flash_message'] = "ID utilisateur invalide fourni.";
        $_SESSION['flash_type'] = 'error';
        header("Location: index.php");
        exit();
    }

    if (unsuspendreUtilisateur($pdo, $userId)) {
        $_SESSION['flash_message'] = "Suspension de l'utilisateur (ID: {$userId}) levée avec succès.";
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = "Erreur lors de la levée de suspension de l'utilisateur (ID: {$userId}).";
        $_SESSION['flash_type'] = 'error';
    }
    header("Location: index.php");
    exit();
} else {
    $_SESSION['flash_message'] = "Aucun ID utilisateur spécifié pour la levée de suspension.";
    $_SESSION['flash_type'] = 'error';
    header("Location: index.php");
    exit();
}
?>