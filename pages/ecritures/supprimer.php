<?php
// pages/ecritures/supprimer.php

// Inclure le fichier de connexion � la base de donn�es
require_once('../../fonctions/database.php'); // Assurez-vous que ce fichier initialise $pdo

// Inclure les fonctions de gestion des �critures si n�cessaire (par exemple, pour une suppression en cascade)
// require_once('../../fonctions/gestion_ecritures.php'); // Doit contenir une fonction deleteEcriture

// Rediriger en cas d'acc�s direct sans ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: liste.php?error=no_id'); // Rediriger vers la liste avec un message d'erreur
    exit();
}

// R�cup�rer l'identifiant de l'�criture � supprimer depuis l'URL
$idEcriture = $_GET['id'];

// Validation basique de l'ID (s'assurer que c'est un nombre entier positif)
if (!filter_var($idEcriture, FILTER_VALIDATE_INT) || $idEcriture <= 0) {
    header('Location: liste.php?error=invalid_id'); // Rediriger avec un message d'erreur
    exit();
}

// Connexion � la base de donn�es
try {
    // Correction : Suppression du mot-cl� 'CharacterSet' de la cha�ne DSN
    $pdo = new PDO("mysql:host=localhost;dbname=BD_AD_SCE;charset=utf8", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Commencer une transaction pour assurer l'int�grit� des donn�es
    $pdo->beginTransaction();

    // --- Suppression des lignes d'�criture associ�es ---
    // Il est crucial de supprimer d'abord les lignes d�pendantes (Lignes_Ecritures)
    // avant de supprimer l'�criture principale (Ecritures).
    // Utilisation du nom de table et de la colonne de liaison corrects bas�s sur le sch�ma fourni.
    $stmtLignes = $pdo->prepare("DELETE FROM Lignes_Ecritures WHERE ID_Ecriture = :id");
    $stmtLignes->execute([':id' => $idEcriture]);

    // --- Suppression de l'�criture principale ---
    // Utilisation du nom de table et de la cl� primaire corrects bas�s sur le sch�ma fourni.
    // Correction : Utilisation de 'ID_Ecriture' au lieu de 'Id'
    $stmtEcriture = $pdo->prepare("DELETE FROM Ecritures WHERE ID_Ecriture = :id");
    $stmtEcriture->execute([':id' => $idEcriture]);

    // V�rifier si une �criture a �t� supprim�e (facultatif mais recommand�)
    if ($stmtEcriture->rowCount() > 0) {
        // Si la suppression r�ussit, valider la transaction
        $pdo->commit();
        // Rediriger vers la page de liste avec un message de succ�s
        header('Location: liste.php?success=delete');
        exit();
    } else {
        // Si aucune �criture n'a �t� trouv�e avec cet ID, annuler la transaction
        $pdo->rollBack();
        // Rediriger vers la page de liste avec un message d'erreur (ID non trouv�)
        header('Location: liste.php?error=not_found');
        exit();
    }

} catch (PDOException $e) {
    // En cas d'erreur, annuler la transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // --- D�BOGAGE TEMPORAIRE : Afficher le message d'erreur de la DB dans l'URL ---
    // ** ATTENTION : NE PAS FAIRE CECI EN PRODUCTION POUR DES RAISONS DE S�CURIT� **
    // On affiche le message d�taill� de la DB dans l'URL pour le d�bogage
    $errorMessage = urlencode("Erreur DB: " . $e->getMessage()); // Encoder le message pour l'URL
    error_log("Erreur de suppression de l'�criture " . $idEcriture . " : " . $e->getMessage()); // Journaliser l'erreur (si le log fonctionne un jour)
    header('Location: liste.php?error=' . $errorMessage); // Rediriger avec le message d'erreur d�taill�
    exit();
    // --- FIN DU D�BOGAGE TEMPORAIRE ---

} catch (Exception $e) {
    // G�rer d'autres types d'exceptions
     if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erreur inattendue lors de la suppression de l'�criture " . $idEcriture . " : " . $e->getMessage()); // Journaliser l'erreur
    header('Location: liste.php?error=unexpected_error'); // Rediriger avec une erreur g�n�rique
    exit();
}
?>
