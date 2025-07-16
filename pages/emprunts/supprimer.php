<?php
// pages/emprunts/supprimer.php

// Inclure le fichier de connexion � la base de donn�es
require_once('../../fonctions/database.php'); // Assurez-vous que ce fichier initialise $pdo

// Inclure les fonctions de gestion des emprunts si n�cessaire (par exemple, pour une suppression en cascade)
// require_once('../../fonctions/gestion_emprunts.php'); // Peut contenir une fonction deleteEmprunt

// Rediriger en cas d'acc�s direct sans ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php?error=no_id'); // Rediriger vers la liste des emprunts avec un message d'erreur
    exit();
}

// R�cup�rer l'identifiant de l'emprunt � supprimer depuis l'URL
$idEmprunt = $_GET['id'];

// Validation basique de l'ID (s'assurer que c'est un nombre entier positif)
if (!filter_var($idEmprunt, FILTER_VALIDATE_INT) || $idEmprunt <= 0) {
    header('Location: index.php?error=invalid_id'); // Rediriger avec un message d'erreur
    exit();
}

// Connexion � la base de donn�es
try {
    // Correction : Suppression du mot-cl� 'CharacterSet' de la cha�ne DSN
   $pdo = new PDO("mysql:host=localhost;dbname=BD_AD_SCE;charset=utf8", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Commencer une transaction pour assurer l'int�grit� des donn�es
    $pdo->beginTransaction();

    // --- Suppression des lignes du plan d'amortissement associ�es ---
    // Il est crucial de supprimer d'abord les lignes d�pendantes (Echeances_Amortissement)
    // avant de supprimer l'emprunt principal (Emprunts_Bancaires).
    // Utilisation du nom de table correct bas� sur le sch�ma fourni.
    $stmtPlan = $pdo->prepare("DELETE FROM Echeances_Amortissement WHERE ID_Emprunt = :id");
    $stmtPlan->execute([':id' => $idEmprunt]);

    // --- Suppression de l'emprunt principal ---
    // Utilisation du nom de table et de la cl� primaire corrects bas�s sur le sch�ma fourni.
    $stmtEmprunt = $pdo->prepare("DELETE FROM Emprunts_Bancaires WHERE ID_Emprunt = :id");
    $stmtEmprunt->execute([':id' => $idEmprunt]);

    // V�rifier si un emprunt a �t� supprim� (facultatif mais recommand�)
    if ($stmtEmprunt->rowCount() > 0) {
        // Si la suppression r�ussit, valider la transaction
        $pdo->commit();
        // Rediriger vers la page de liste des emprunts avec un message de succ�s
        header('Location: index.php?success=delete');
        exit();
    } else {
        // Si aucun emprunt n'a �t� trouv� avec cet ID, annuler la transaction
        $pdo->rollBack();
        // Rediriger vers la page de liste des emprunts avec un message d'erreur (ID non trouv�)
        header('Location: index.php?error=not_found');
        exit();
    }

} catch (PDOException $e) {
    // En cas d'erreur, annuler la transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // --- D�BOGAGE TEMPORAIRE : Afficher le message d'erreur de la DB dans l'URL ---
    // ** ATTENTION : NE PAS FAIRE CECI EN PRODUCTION POUR DES RAISONS DE S�CURIT� **
    // On garde l'affichage de l'erreur pour le moment pour voir si une autre erreur survient
    $errorMessage = urlencode("Erreur DB: " . $e->getMessage()); // Encoder le message pour l'URL
    error_log("Erreur de suppression de l'emprunt " . $idEmprunt . " : " . $e->getMessage()); // Journaliser l'erreur (toujours utile)
    header('Location: index.php?error=' . $errorMessage); // Rediriger avec le message d'erreur d�taill�
    exit();
    // --- FIN DU D�BOGAGE TEMPORAIRE ---

} catch (Exception $e) {
    // G�rer d'autres types d'exceptions
     if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erreur inattendue lors de la suppression de l'emprunt " . $idEmprunt . " : " . $e->getMessage()); // Log l'erreur
    header('Location: index.php?error=unexpected_error'); // Rediriger avec une erreur g�n�rique
    exit();
}
?>
