<?php
// pages/factures/supprimer_facture.php

// --- Configuration et Includes ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once('../../fonctions/database.php'); // For $pdo connection
require_once('../../fonctions/gestion_factures.php'); // For deleteFacture function (you'll need to create this)

// --- Verification de la connexion PDO ---
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("ERREUR FATALE: \$pdo n'a pas été initialisé par database.php dans supprimer_facture.php");
    $_SESSION['error_message'] = "Une erreur critique de configuration de la base de données est survenue.";
    header("Location: listes_factures.php?error=" . urlencode($_SESSION['error_message']));
    exit;
}

// --- Traitement de la requête POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Erreur de sécurité : Jeton CSRF invalide. Veuillez réessayer.";
        header("Location: listes_factures.php?error=" . urlencode($_SESSION['error_message']));
        exit;
    }

    // 2. Validate input: facture_id
    $factureId = filter_input(INPUT_POST, 'facture_id', FILTER_VALIDATE_INT);

    if ($factureId === false || $factureId <= 0) {
        $_SESSION['error_message'] = "ID de facture invalide.";
        header("Location: listes_factures.php?error=" . urlencode($_SESSION['error_message']));
        exit;
    }

    // 3. Attempt to delete the invoice
    try {
        // Ensure the deleteFacture function exists in gestion_factures.php
        if (function_exists('deleteFacture')) {
            $deleted = deleteFacture($pdo, $factureId);

            if ($deleted) {
                $_SESSION['success_message'] = "Facture (ID: " . htmlspecialchars($factureId) . ") supprimée avec succès.";
                header("Location: listes_factures.php?success=" . urlencode($_SESSION['success_message']));
                exit;
            } else {
                $_SESSION['error_message'] = "Échec de la suppression de la facture (ID: " . htmlspecialchars($factureId) . "). Elle est peut-être déjà liée à d'autres enregistrements (écritures comptables, paiements...).";
                header("Location: listes_factures.php?error=" . urlencode($_SESSION['error_message']));
                exit;
            }
        } else {
            throw new Exception("La fonction 'deleteFacture' est manquante. Vérifiez 'fonctions/gestion_factures.php'.");
        }
    } catch (PDOException $e) {
        // Log the actual database error for debugging, but provide a generic message to the user
        error_log("Erreur PDO lors de la suppression de la facture (ID: $factureId): " . $e->getMessage());
        $_SESSION['error_message'] = "Erreur de base de données lors de la suppression de la facture. Veuillez réessayer ou contacter l'administrateur.";
        header("Location: listes_factures.php?error=" . urlencode($_SESSION['error_message']));
        exit;
    } catch (Exception $e) {
        error_log("Erreur lors de la suppression de la facture (ID: $factureId): " . $e->getMessage());
        $_SESSION['error_message'] = "Une erreur inattendue est survenue lors de la suppression : " . htmlspecialchars($e->getMessage());
        header("Location: listes_factures.php?error=" . urlencode($_SESSION['error_message']));
        exit;
    }

} else {
    // If not a POST request, redirect to the list page with an error
    $_SESSION['error_message'] = "Accès non autorisé à cette page.";
    header("Location: listes_factures.php?error=" . urlencode($_SESSION['error_message']));
    exit;
}
?>