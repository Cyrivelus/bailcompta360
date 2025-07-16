<?php
// pages/admin/habilitations/traitement_ajout_habilitation.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si la requête est bien POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_message'] = "Méthode non autorisée.";
    $_SESSION['flash_type'] = 'error';
    header('Location: index.php'); // Rediriger vers la page d'index en cas de mauvais appel
    exit;
}

// Inclure les fichiers de fonctions et de configuration
require_once('../../../fonctions/database.php'); // Assurez-vous que ce fichier gère la connexion à la BD
require_once('../../../fonctions/gestion_habilitations.php'); // Contient les fonctions d'ajout

// Connexion à la base de données
try {
    // Si database.php retourne l'objet PDO :
    // $pdo = getPdoConnection(); // Exemple si database.php a une fonction getPdoConnection()

    // Sinon, utilisez votre code actuel :
    $pdo = new PDO("mysql:host=localhost;dbname=BD_AD_SCE;charset=utf8", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $_SESSION['flash_message'] = "Erreur de connexion à la base de données lors de l'ajout.";
    $_SESSION['flash_type'] = 'error';
    error_log("Erreur de connexion : " . $e->getMessage());
    header('Location: ajouter.php'); // Rediriger vers le formulaire d'ajout
    exit;
}

// Récupérer et valider les données du formulaire
$assign_type = $_POST['assign_type'] ?? '';
$objet = trim($_POST['objet'] ?? '');
$id_profil = $_POST['id_profil'] ?? null;
$id_utilisateur = $_POST['id_utilisateur'] ?? null;

// Basic validation
if (empty($objet)) {
    $_SESSION['flash_message'] = "L'objet (permission) est requis.";
    $_SESSION['flash_type'] = 'error';
    header('Location: ajouter.php');
    exit;
}

$success = false;

if ($assign_type === 'profil' && $id_profil !== null && $id_profil !== '') {
    // Valider que l'ID profil existe (optionnel mais recommandé)
    if (filter_var($id_profil, FILTER_VALIDATE_INT)) {
        // Assurez-vous que le profil existe réellement dans la table Profils
        // (Ajouter une fonction de vérification dans gestion_profils.php ou ici)
        // Exemple simple sans vérification d'existence pour l'instant :
        $success = addHabilitationProfil($pdo, (int)$id_profil, $objet);
        $target_name = "profil (ID: " . htmlspecialchars($id_profil) . ")"; // Afficher l'ID si le nom n'est pas facilement dispo
    } else {
         $_SESSION['flash_message'] = "ID profil invalide.";
         $_SESSION['flash_type'] = 'error';
         header('Location: ajouter.php');
         exit;
    }

} elseif ($assign_type === 'utilisateur' && $id_utilisateur !== null && $id_utilisateur !== '') {
     // Valider que l'ID utilisateur existe (optionnel mais recommandé)
     if (filter_var($id_utilisateur, FILTER_VALIDATE_INT)) {
         // Assurez-vous que l'utilisateur existe réellement dans la table Utilisateurs
         // (Ajouter une fonction de vérification dans gestion_utilisateurs.php ou ici)
         // Exemple simple sans vérification d'existence pour l'instant :
        $success = addHabilitationUtilisateur($pdo, (int)$id_utilisateur, $objet);
        $target_name = "utilisateur (ID: " . htmlspecialchars($id_utilisateur) . ")"; // Afficher l'ID
     } else {
         $_SESSION['flash_message'] = "ID utilisateur invalide.";
         $_SESSION['flash_type'] = 'error';
         header('Location: ajouter.php');
         exit;
     }

} else {
    $_SESSION['flash_message'] = "Veuillez sélectionner un type d'assignation (profil ou utilisateur) et la cible.";
    $_SESSION['flash_type'] = 'error';
     header('Location: ajouter.php');
     exit;
}

// Définir le message flash et rediriger
if ($success) {
    $_SESSION['flash_message'] = "Habilitation '" . htmlspecialchars($objet) . "' ajoutée avec succès pour " . ($target_name ?? 'la cible sélectionnée') . ".";
    $_SESSION['flash_type'] = 'success';
    header('Location: index.php'); // Rediriger vers la liste
    exit;
} else {
    $_SESSION['flash_message'] = "Erreur lors de l'ajout de l'habilitation.";
    $_SESSION['flash_type'] = 'error';
    header('Location: ajouter.php'); // Rediriger vers le formulaire d'ajout pour corriger
    exit;
}