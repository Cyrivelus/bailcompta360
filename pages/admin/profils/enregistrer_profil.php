<?php
// pages/admin/profils/enregistrer_profil.php

// Démarrer la session si ce n'est pas déjà fait
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}



// Inclure le fichier de connexion à la base de données
// Assurez-vous que ce chemin est correct et que le fichier définit bien $pdo
require_once('../../../fonctions/database.php'); // <-- ASSUREZ-VOUS QUE CE CHEMIN EST CORRECT ET LE FICHIER EXISTE ET DÉFINIT $pdo

// Vérifier si $pdo est bien initialisé
if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['admin_message_error'] = "Erreur critique : La connexion à la base de données n'est pas disponible.";
    header('Location: ajouter.php'); // Rediriger vers le formulaire d'ajout
    exit();
}

// S'assurer que la requête est de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['admin_message_error'] = "Méthode de requête invalide.";
    header('Location: ajouter.php');
    exit();
}

// Jeton CSRF (Décommentez et utilisez si vous l'implémentez dans ajouter.php - Fortement recommandé)
// ASSUREZ-VOUS D'AVOIR BIEN MIS LE CHAMP HIDDEN DANS ajouter.php POUR QUE CELA FONCTIONNE
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['admin_message_error'] = "Erreur de sécurité (CSRF). La requête a été bloquée.";
    header('Location: ajouter.php');
    exit();
}
// Optionnel : unset($_SESSION['csrf_token']); // Invalider le jeton après usage, si vous ne permettez qu'une soumission par jeton


// Récupérer les données du formulaire
// Utiliser filter_input pour une première sanitisation et validation. trim() pour enlever les espaces inutiles.

// Récupérer l'ID de l'utilisateur (doit être un entier)
$utilisateur_id = filter_input(INPUT_POST, 'utilisateur_id', FILTER_VALIDATE_INT); // Valide et filtre en INT

// Récupérer le nom du profil
// Utilise FILTER_UNSAFE_RAW et trim() pour la chaîne, se fie à PDO::PARAM_STR pour la sécurité
$nom_profil = trim(filter_input(INPUT_POST, 'nom_profil', FILTER_UNSAFE_RAW));

// Récupérer la description du profil
// Utilise FILTER_UNSAFE_RAW et trim() pour la chaîne, se fie à PDO::PARAM_STR pour la sécurité
$description = trim(filter_input(INPUT_POST, 'description', FILTER_UNSAFE_RAW));


// --- Validations ---

// Valider l'ID de l'utilisateur
if ($utilisateur_id === false || $utilisateur_id === null || $utilisateur_id <= 0) {
     $_SESSION['admin_message_error'] = "ID utilisateur invalide sélectionné.";
     header('Location: ajouter.php');
     exit();
}

// Valider le nom du profil
if (empty($nom_profil)) {
    $_SESSION['admin_message_error'] = "Le nom du profil est requis.";
    header('Location: ajouter.php');
    exit();
}

// Validation facultative pour la description si elle est requise
// if (empty($description)) {
//     $_SESSION['admin_message_error'] = "La description est requise.";
//     header('Location: ajouter.php');
//     exit();
// }


// Vérifier si l'utilisateur sélectionné existe réellement (bonne pratique)
try {
    $sqlUserCheck = "SELECT COUNT(*) FROM Utilisateurs WHERE ID_Utilisateur = :utilisateur_id";
    $stmtUserCheck = $pdo->prepare($sqlUserCheck);
    $stmtUserCheck->bindParam(':utilisateur_id', $utilisateur_id, PDO::PARAM_INT);
    $stmtUserCheck->execute();
    if ($stmtUserCheck->fetchColumn() == 0) {
        $_SESSION['admin_message_error'] = "L'utilisateur sélectionné n'existe pas.";
        header('Location: ajouter.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['admin_message_error'] = "Erreur lors de la vérification de l'utilisateur : " . htmlspecialchars($e->getMessage());
    error_log("Erreur PDO (check user) dans enregistrer_profil.php: " . $e->getMessage());
    header('Location: ajouter.php');
    exit();
}


// Vérifier si un profil avec le même nom n'existe pas déjà
// NOTE : Cette vérification est basée sur 'Nom_Profil' étant globalement unique.
// Si votre table est censée stocker UN profil PAR utilisateur (malgré la colonne ID_Utilisateur NULLABLE et non-UNIQUE),
// il faudrait plutôt vérifier si un profil existe déjà pour cet ID_Utilisateur spécifique.
// Par exemple : "SELECT COUNT(*) FROM Profils WHERE ID_Utilisateur = :utilisateur_id"
// Adaptez cette logique si nécessaire selon le rôle exact de cette table.
try {
    $sqlCheck = "SELECT COUNT(*) FROM Profils WHERE Nom_Profil = :nom_profil";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->bindParam(':nom_profil', $nom_profil, PDO::PARAM_STR);
    $stmtCheck->execute();
    if ($stmtCheck->fetchColumn() > 0) {
        // Vous pourriez vouloir vider les champs nom/description si vous redirigez vers le formulaire d'ajout
        // pour que l'utilisateur n'ait qu'à corriger. Cela nécessite de passer ces valeurs via la session ou GET.
        $_SESSION['admin_message_error'] = "Un profil avec le nom '".htmlspecialchars($nom_profil)."' existe déjà.";
        header('Location: ajouter.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['admin_message_error'] = "Erreur lors de la vérification du nom du profil : " . htmlspecialchars($e->getMessage());
    error_log("Erreur PDO (check nom_profil) dans enregistrer_profil.php: " . $e->getMessage());
    header('Location: ajouter.php');
    exit();
}


// --- Insertion dans la base de données ---
// La table Profils a ID_Profil (IDENTITY), Nom_Profil, Description_Profil, et ID_Utilisateur (NULLable).
// On insère Nom_Profil, Description_Profil et ID_Utilisateur.
$sqlInsert = "INSERT INTO Profils (Nom_Profil, Description_Profil, ID_Utilisateur)
              VALUES (:nom_profil, :description, :utilisateur_id)";

try {
    $stmtInsert = $pdo->prepare($sqlInsert);

    // Lier les paramètres avec les valeurs récupérées et validées
    $stmtInsert->bindParam(':nom_profil', $nom_profil, PDO::PARAM_STR);
    $stmtInsert->bindParam(':description', $description, PDO::PARAM_STR); // Utilise PARAM_STR pour VARCHAR(MAX)
    $stmtInsert->bindParam(':utilisateur_id', $utilisateur_id, PDO::PARAM_INT); // Utilise PARAM_INT pour INT

    $stmtInsert->execute();

    // Redirection en cas de succès
    $_SESSION['admin_message_success'] = "Le profil '".htmlspecialchars($nom_profil)."' a été ajouté avec succès pour l'utilisateur ID " . htmlspecialchars($utilisateur_id) . ".";
    header('Location: index.php'); // Rediriger vers la liste des profils ou une page de confirmation
    exit();

} catch (PDOException $e) {
    // Gérer les erreurs d'insertion
    $_SESSION['admin_message_error'] = "Erreur lors de l'enregistrement du profil : " . htmlspecialchars($e->getMessage());
    // Loggez l'erreur complète pour le débogage serveur
    error_log("Erreur PDO (INSERT Profil) dans enregistrer_profil.php: " . $e->getMessage() . " - SQL: " . $sqlInsert . " - Data: nom_profil=" . $nom_profil . ", description=" . $description . ", utilisateur_id=" . $utilisateur_id);
    header('Location: ajouter.php'); // Retourner au formulaire d'ajout en cas d'erreur
    exit();
}
?>