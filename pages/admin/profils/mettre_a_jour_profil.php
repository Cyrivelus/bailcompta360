<?php
// pages/admin/profils/mettre_a_jour_profil.php

// Démarrer la session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}




// --- Configuration et Includes ---
// Inclure le fichier de connexion à la base de données
// ASSUREZ-VOUS QUE CE CHEMIN EST CORRECT et cohérent
require_once('../../../fonctions/database.php'); // Chemin ajusté pour cohérence

// Vérifier si $pdo est bien initialisé
if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['admin_message_error'] = "Erreur critique : La connexion à la base de données n'est pas disponible.";
    header('Location: index.php'); // Rediriger vers la liste des profils en cas d'erreur BD
    exit();
}

// S'assurer que la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['admin_message_error'] = "Méthode de requête invalide pour la mise à jour.";
    header('Location: index.php'); // Rediriger vers la liste des profils
    exit();
}

// --- Jeton CSRF ---
// Valider le jeton CSRF pour protéger contre les attaques
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['admin_message_error'] = "Erreur de sécurité (CSRF). La requête de mise à jour a été bloquée.";
    // Optionnel : régénérer le jeton
    // unset($_SESSION['csrf_token']); $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header('Location: index.php'); // Rediriger vers la liste en cas d'erreur CSRF
    exit();
}
// Optionnel : unset($_SESSION['csrf_token']); // Invalider le jeton après usage (si vous ne permettez qu'une soumission par jeton)


// --- Récupérer et Valider les Données du Formulaire ---

// Récupérer et valider l'ID du profil (obligatoire)
$profil_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if ($profil_id === false || $profil_id === null || $profil_id <= 0) {
    $_SESSION['admin_message_error'] = "ID profil invalide pour la mise à jour.";
    // Si l'ID est invalide, on ne peut pas revenir à la page de modification spécifique, on va à l'index
    header('Location: index.php');
    exit();
}

// Récupérer et valider le nom du profil (obligatoire)
$nom_profil = trim(filter_input(INPUT_POST, 'nom_profil', FILTER_UNSAFE_RAW));
if (empty($nom_profil)) {
    $_SESSION['admin_message_error'] = "Le nom du profil est requis pour la mise à jour.";
    // Rediriger vers la page de modification du profil concerné
    header('Location: modifier.php?id=' . urlencode($profil_id));
    exit();
}

// Récupérer la description du profil (peut être vide)
$description = trim(filter_input(INPUT_POST, 'description', FILTER_UNSAFE_RAW));
// Convertir la chaîne vide en NULL si votre colonne Description_Profil peut être NULL et que vous préférez stocker NULL plutôt qu'une chaîne vide
if ($description === '') {
    $description = null;
}


// --- Validations Métier ---

// Vérifier si un AUTRE profil avec le même nom existe déjà
// Ceci assure que le nom du profil reste unique (si c'est la règle)
// On exclut le profil actuel de la vérification
try {
    $sqlCheck = "SELECT COUNT(*) FROM Profils WHERE Nom_Profil = :nom_profil AND ID_Profil != :id";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->bindParam(':nom_profil', $nom_profil, PDO::PARAM_STR);
    $stmtCheck->bindParam(':id', $profil_id, PDO::PARAM_INT);
    $stmtCheck->execute();

    if ($stmtCheck->fetchColumn() > 0) {
        $_SESSION['admin_message_error'] = "Un autre profil avec le nom '".htmlspecialchars($nom_profil)."' existe déjà.";
        // Rediriger vers la page de modification du profil concerné
        header('Location: modifier.php?id=' . urlencode($profil_id));
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['admin_message_error'] = "Erreur lors de la vérification du nom de profil pour la mise à jour : " . htmlspecialchars($e->getMessage());
    error_log("Erreur PDO (check nom_profil update) dans mettre_a_jour_profil.php: " . $e->getMessage());
    // Rediriger vers la page de modification du profil concerné
    header('Location: modifier.php?id=' . urlencode($profil_id));
    exit();
}


// --- Mise à jour dans la base de données ---
// On met à jour Nom_Profil et Description_Profil pour le profil avec l'ID donné
$sqlUpdate = "UPDATE Profils
              SET Nom_Profil = :nom_profil,
                  Description_Profil = :description
              WHERE ID_Profil = :id";

try {
    $stmtUpdate = $pdo->prepare($sqlUpdate);

    // Lier les paramètres avec les valeurs récupérées et validées
    $stmtUpdate->bindParam(':nom_profil', $nom_profil, PDO::PARAM_STR);
    // Utiliser PARAM_STR pour la description (même si NULL)
    // Si vous stockez NULL pour vide, PDO gère correctement le type si $description est null
    $stmtUpdate->bindParam(':description', $description, PDO::PARAM_STR);
    $stmtUpdate->bindParam(':id', $profil_id, PDO::PARAM_INT);

    $stmtUpdate->execute();

    // Vérifier si une ligne a été affectée (si le profil existait bien et a été modifié)
    // Bien que la vérification d'existence préalable soit faite, c'est une sécurité supplémentaire
    if ($stmtUpdate->rowCount() > 0) {
        $_SESSION['admin_message_success'] = "Le profil (ID: " . htmlspecialchars($profil_id) . ") a été mis à jour avec succès.";
    } else {
        // Cela pourrait arriver si l'ID était valide lors du chargement, mais le profil a été supprimé entre temps
        // Ou si les données soumises sont identiques aux données existantes (aucun changement effectué)
        $_SESSION['admin_message_warning'] = "Aucune modification apportée au profil (ID: " . htmlspecialchars($profil_id) . "), ou profil introuvable.";
         // Vous pouvez choisir 'error' si vous considérez qu'aucun changement est une erreur
        // $_SESSION['admin_message_error'] = "Impossible de trouver le profil (ID: " . htmlspecialchars($profil_id) . ") à mettre à jour.";
    }


    // Redirection en cas de succès ou d'absence de modification
    header('Location: index.php'); // Rediriger vers la liste des profils
    exit();

} catch (PDOException $e) {
    // Gérer les erreurs de mise à jour
    $_SESSION['admin_message_error'] = "Erreur lors de la mise à jour du profil : " . htmlspecialchars($e->getMessage());
    // Loggez l'erreur complète pour le débogage serveur
    error_log("Erreur PDO (UPDATE Profil) dans mettre_a_jour_profil.php: " . $e->getMessage() . " - SQL: " . $sqlUpdate . " - Data: id=" . $profil_id . ", nom_profil=" . $nom_profil . ", description=" . ($description ?? 'NULL')); // Afficher NULL si $description est null

    // Rediriger vers la page de modification du profil concerné avec le message d'erreur
    header('Location: modifier.php?id=' . urlencode($profil_id));
    exit();
}
?>