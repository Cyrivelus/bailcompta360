<?php
// pages/admin/profils/supprimer.php

// Démarrer la session pour la gestion de l'authentification
session_start();

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Admin') {
    // Rediriger si non autorisé
    header("Location: ../../index.php?error=Accès non autorisé");
    exit();
}

// Inclure le fichier de connexion à la base de données
require_once('../../../fonctions/database.php');

// Vérifier si l'ID du profil à supprimer est présent dans l'URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php?error=ID profil invalide");
    exit();
}

$profil_id = $_GET['id'];

try {
    // Préparer et exécuter la requête de suppression
    $sql = "DELETE FROM Profils WHERE ID_Profil = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $profil_id, PDO::PARAM_INT);
    $stmt->execute();

    // Vérifier si une ligne a été affectée (suppression réussie)
    if ($stmt->rowCount() > 0) {
        header("Location: index.php?success=Profil supprimé avec succès");
    } else {
        header("Location: index.php?error=Profil non trouvé");
    }

} catch (PDOException $e) {
    // En cas d'erreur lors de la suppression (par exemple, contrainte de clé étrangère)
    header("Location: index.php?error=Erreur lors de la suppression du profil: " . $e->getMessage());
}

exit();
?>