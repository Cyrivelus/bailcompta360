<?php
// pages/admin/utilisateurs/enregistrer_utilisateur.php

session_start();

// Vérification d'accès
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../../../index.php?error=Accès non autorisé");
    exit();
}

// Connexion base de données
// Assurez-vous que le chemin vers votre fichier database.php est correct.
// Il doit contenir l'initialisation de $pdo.
require_once '../../../fonctions/database.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['admin_message_error'] = "Erreur critique: Connexion à la base de données non disponible.";
    header("Location: ajouter.php");
    exit();
}

// Récupération des données POST
$nom = trim($_POST['nom'] ?? '');
$login = trim($_POST['login'] ?? '');
$password_plain = $_POST['password'] ?? ''; // Renommé pour clarté: c'est le mot de passe en clair du formulaire
$role = $_POST['role'] ?? '';

// Validation du rôle
$roles_valides = ['Admin', 'Comptable', 'Invité', 'super_admin', 'Caissiere']; // Ajout de 'super_admin' si ce rôle est supporté
if (!in_array($role, $roles_valides)) {
    $_SESSION['admin_message_error'] = "Rôle invalide sélectionné.";
    header("Location: ajouter.php");
    exit();
}

// Validation des champs requis
if (empty($nom) || empty($login) || empty($password_plain) || empty($role)) {
    $_SESSION['admin_message_error'] = "Tous les champs sont requis.";
    header("Location: ajouter.php");
    exit();
}

// Vérification unicité du login
$sqlCheck = "SELECT COUNT(*) FROM Utilisateurs WHERE Login_Utilisateur = :login";
try {
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->bindParam(':login', $login);
    $stmtCheck->execute();
    if ($stmtCheck->fetchColumn() > 0) {
        $_SESSION['admin_message_error'] = "Ce login est déjà utilisé. Veuillez en choisir un autre.";
        header("Location: ajouter.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['admin_message_error'] = "Erreur lors de la vérification de l'unicité du login.";
    error_log("Erreur vérification login : " . $e->getMessage()); // Loggez l'erreur pour le débogage
    header("Location: ajouter.php");
    exit();
}

// --- PARTIE SÉCURITÉ CRUCIALE : HASHAGE DU MOT DE PASSE ---
// password_hash() génère un sel aléatoire et effectue plusieurs itérations.
// Le résultat est une chaîne de caractères qui contient le sel et le hachage.
$hashedPassword = password_hash($password_plain, PASSWORD_DEFAULT);

// Vérification que le hachage a bien fonctionné (bien que rare)
if ($hashedPassword === false) {
    $_SESSION['admin_message_error'] = "Une erreur est survenue lors du hachage du mot de passe.";
    header("Location: ajouter.php");
    exit();
}
// Le type VARCHAR(255) de votre colonne 'Mot_de_Passe' est suffisant pour stocker ce hachage.

// Insertion de l'utilisateur avec le mot de passe HACHÉ
$sqlInsert = "INSERT INTO Utilisateurs (Nom, Login_Utilisateur, Mot_de_Passe, Role, Date_Creation)
              VALUES (:nom, :login, :motdepasse, :role, NOW())"; // Corrigé : Utilisation de NOW() à la place de GETDATE()

try {
    $stmtInsert = $pdo->prepare($sqlInsert);
    $stmtInsert->bindParam(':nom', $nom);
    $stmtInsert->bindParam(':login', $login);
    $stmtInsert->bindParam(':motdepasse', $hashedPassword);
    $stmtInsert->bindParam(':role', $role);
    $stmtInsert->execute();

    $_SESSION['admin_message_success'] = "Utilisateur '" . htmlspecialchars($login) . "' ajouté avec succès.";
    header("Location: index.php");
    exit();
} catch (PDOException $e) {
    $_SESSION['admin_message_error'] = "Erreur lors de l'ajout de l'utilisateur : " . htmlspecialchars($e->getMessage());
    error_log("Erreur insertion utilisateur : " . $e->getMessage());
    header("Location: ajouter.php");
    exit();
}

?>