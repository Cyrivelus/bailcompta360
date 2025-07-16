<?php
// pages/admin/utilisateurs/supprimer.php

// Démarrer la session pour la gestion de l'authentification
session_start();

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Admin') {
    // Rediriger si non autorisé
    header("Location: ../../index.php?error=Accès non autorisé");
    exit();
}

// Inclure le fichier de connexion à la base de données
require_once('../../../fonctions/database.php'); // Assurez-vous que $pdo est initialisé ici

// Vérifier si l'ID de l'utilisateur à supprimer est présent dans l'URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php?error=ID utilisateur invalide");
    exit();
}

$utilisateur_id = $_GET['id'];

// Début de la transaction
try {
    $pdo->beginTransaction();

    // 1. Supprimer les enregistrements liés dans Habilitations_Utilisateur
    //    Assurez-vous que le nom de la table et de la colonne correspondent exactement
    //    à votre base de données (dbo.Habilitations_Utilisateur et ID_Utilisateur)
    $sql_delete_habilitations = "DELETE FROM dbo.Habilitations_Utilisateur WHERE ID_Utilisateur = :id";
    $stmt_habilitations = $pdo->prepare($sql_delete_habilitations);
    $stmt_habilitations->bindParam(':id', $utilisateur_id, PDO::PARAM_INT);
    $stmt_habilitations->execute();

    // 2. Supprimer l'utilisateur de la table Utilisateurs
    $sql_delete_utilisateur = "DELETE FROM Utilisateurs WHERE ID_Utilisateur = :id";
    $stmt_utilisateur = $pdo->prepare($sql_delete_utilisateur);
    $stmt_utilisateur->bindParam(':id', $utilisateur_id, PDO::PARAM_INT);
    $stmt_utilisateur->execute();

    // Valider la transaction
    $pdo->commit();

    // Vérifier si l'utilisateur a été effectivement supprimé (rowCount sur la dernière requête principale)
    if ($stmt_utilisateur->rowCount() > 0) {
        header("Location: index.php?success=Utilisateur et ses habilitations supprimés avec succès");
    } else {
        // Cela peut arriver si l'utilisateur a été supprimé entre-temps ou si l'ID n'existait pas initialement.
        // Ou si les habilitations existaient mais l'utilisateur lui-même n'existait pas (cas étrange).
        header("Location: index.php?error=Utilisateur non trouvé ou déjà supprimé. Les habilitations potentielles ont été nettoyées.");
    }

} catch (PDOException $e) {
    // En cas d'erreur, annuler la transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Fournir un message d'erreur plus détaillé ou générique selon les besoins
    // Le message $e->getMessage() peut exposer des détails de la base de données.
    // Pour la production, vous pourriez vouloir un message plus générique.
    $errorMessage = "Erreur lors de la suppression de l'utilisateur: ";
    // Vous pouvez choisir d'afficher le message d'erreur SQL spécifique pour le débogage
    // ou un message plus convivial pour l'utilisateur final.
    // Pour le développement :
    $errorMessage .= $e->getMessage();
    // Pour la production :
    // $errorMessage .= "Une erreur technique est survenue. Veuillez réessayer plus tard.";

    // Log l'erreur réelle pour les administrateurs
    error_log("Erreur de suppression utilisateur (ID: " . $utilisateur_id . "): " . $e->getMessage());

    header("Location: index.php?error=" . urlencode($errorMessage));
}

exit();
?>