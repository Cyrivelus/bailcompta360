<?php
// C:\xampp\htdocs\bailcompta360\pages\admin\utilisatseurs\deconnecter_force.php
// Force logout script for admin

session_start();

// Include the database connection function file
require_once('../../../fonctions/database.php');


// Check if the user is logged in and is an admin (security)
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Admin') {
    $_SESSION['flash_message'] = "Accès non autorisé.";
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../../index.php");
    exit();
}

try {
    // Check if user ID is provided via GET request
    if (isset($_GET['id'])) {
        // Sanitize the user ID
        $userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if ($userId === false || $userId <= 0) {
            // Invalid user ID format
            $_SESSION['flash_message'] = "Invalid user ID provided.";
            $_SESSION['flash_type'] = 'error';
            header("Location: index.php"); // Or your user list page
            exit();
        }

        try {
            // Prepare SQL to clear remember token, expiry, AND set Derniere_Connexion to NULL (force logout)
            $sql = "UPDATE Utilisateurs 
                    SET remember_token = NULL, remember_expiry = NULL, Derniere_Connexion = NULL 
                    WHERE ID_Utilisateur = :userId";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $rowCount = $stmt->rowCount(); // Check if any row was affected

            // If forcing logout of the currently logged-in admin themselves
            if (isset($_SESSION['utilisateur_id']) && $_SESSION['utilisateur_id'] == $userId) {
                session_unset();    // Unset all session variables
                session_destroy();  // Destroy the session
                
                // Redirect to login page as the current admin has logged themselves out
                header("Location: ../../../index.php?msg=logout_forced_self");
                exit();
            }
            
            // Set success or information message
            if ($rowCount > 0) {
                $_SESSION['flash_message'] = "L'utilisateur (ID: {$userId}) a été déconnecté de force avec succès. Sa session sera invalidée à sa prochaine requête.";
                $_SESSION['flash_type'] = 'success';
            } else {
                $_SESSION['flash_message'] = "Utilisateur (ID: {$userId}) non trouvé ou aucune modification effectuée.";
                $_SESSION['flash_type'] = 'info';
            }
            header("Location: index.php"); // Redirect to your user list page
            exit();

        } catch (PDOException $e) {
            // Log the detailed error for the admin/developer
            error_log("Error forcing logout for user ID {$userId}: " . $e->getMessage());
            
            // Provide a generic error message to the user
            $_SESSION['flash_message'] = "An error occurred while trying to force logout. Please try again or contact support.";
            $_SESSION['flash_type'] = 'error';
            header("Location: index.php");
            exit();
        }
    } else {
        // No user ID provided in the GET request
        $_SESSION['flash_message'] = "No user ID specified for forced logout.";
        $_SESSION['flash_type'] = 'error';
        header("Location: index.php");
        exit();
    }
} catch (Exception $e) {
    // Catch any other exceptions that might occur
    error_log("General error in deconnecter_force.php: " . $e->getMessage());
    $_SESSION['flash_message'] = "A system error occurred. Please contact support.";
    $_SESSION['flash_type'] = 'error';
    header("Location: ../../index.php");
    exit();
}
?>