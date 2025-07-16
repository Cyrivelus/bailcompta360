<?php
// pages/admin/utilisateurs/suspendre.php

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $suspensionEndDateStr = filter_input(INPUT_POST, 'suspension_end_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if ($userId === false || $userId <= 0 || empty($suspensionEndDateStr)) {
        $_SESSION['flash_message'] = "Données invalides pour la suspension de l'utilisateur.";
        $_SESSION['flash_type'] = 'error';
        header("Location: index.php");
        exit();
    }

    try {
        // Validate date format and ensure it's in the future
        $suspensionEndDate = new DateTime($suspensionEndDateStr);
        $now = new DateTime();

        if ($suspensionEndDate <= $now) {
            $_SESSION['flash_message'] = "La date de fin de suspension doit être future.";
            $_SESSION['flash_type'] = 'error';
            header("Location: index.php");
            exit();
        }

        // Format for database
        $formattedSuspensionEndDate = $suspensionEndDate->format('Y-m-d H:i:s');

        // Suspend the user
        if (suspendreUtilisateur($pdo, $userId, $formattedSuspensionEndDate)) {
            // Also force logout if the user is currently logged in (clear session/remember me)
            // This is crucial for immediate effect
            $sql_force_logout = "UPDATE Utilisateurs 
                                 SET remember_token = NULL, remember_expiry = NULL, Derniere_Connexion = NULL 
                                 WHERE ID_Utilisateur = :userId";
            $stmt_force_logout = $pdo->prepare($sql_force_logout);
            $stmt_force_logout->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt_force_logout->execute();


            $_SESSION['flash_message'] = "Utilisateur (ID: {$userId}) suspendu avec succès jusqu'au {$suspensionEndDate->format('d/m/Y H:i:s')}.";
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = "Erreur lors de la suspension de l'utilisateur (ID: {$userId}).";
            $_SESSION['flash_type'] = 'error';
        }
    } catch (Exception $e) {
        error_log("Erreur lors du traitement de la suspension: " . $e->getMessage());
        $_SESSION['flash_message'] = "Une erreur est survenue lors de la suspension. " . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
    }
    header("Location: index.php");
    exit();
} else {
    $_SESSION['flash_message'] = "Accès invalide.";
    $_SESSION['flash_type'] = 'error';
    header("Location: index.php");
    exit();
}
?>