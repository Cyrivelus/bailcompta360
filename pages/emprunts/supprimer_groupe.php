<?php
// pages/ecritures/supprimer_groupe.php

// Inclure les fichiers nécessaires pour la connexion à la base de données et les fonctions de gestion
require_once '../../fonctions/database.php'; // Ce fichier doit établir la connexion $pdo
require_once '../../fonctions/gestion_emprunts.php'; // Ce fichier doit contenir la fonction supprimerEmprunt

// Vérifier si la connexion PDO est disponible
// Si votre 'database.php' ne retourne pas $pdo ou ne le rend pas global,
// vous devrez appeler votre fonction de connexion ici.
if (!isset($pdo) || !$pdo instanceof PDO) {
    // Supposons que connect_db() est la fonction qui établit et retourne la connexion PDO
    // Adaptez ce nom de fonction à votre implémentation réelle.
    $pdo = connect_db();
    if (!$pdo) {
        header('Location: index.php?error=' . urlencode("Erreur de connexion à la base de données pour la suppression de groupe."));
        exit();
    }
}

// Vérifier si la requête est de type POST et si des emprunts ont été sélectionnés
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_emprunts']) && is_array($_POST['selected_emprunts'])) {
    $selectedEmprunts = $_POST['selected_emprunts'];
    $deletedCount = 0;
    $errors = [];

    // Lancer une transaction pour assurer l'atomicité de l'opération
    // Soit tous sont supprimés, soit aucun n'est supprimé.
    try {
        $pdo->beginTransaction();

        foreach ($selectedEmprunts as $empruntId) {
            // Valider chaque ID pour s'assurer qu'il s'agit d'une valeur numérique valide
            if (is_numeric($empruntId)) {
                // Appeler la fonction de suppression pour chaque emprunt
                // Assurez-vous que supprimerEmprunt accepte $pdo comme premier argument
                if (supprimerEmprunt($pdo, (int)$empruntId)) {
                    $deletedCount++;
                } else {
                    $errors[] = "ID " . htmlspecialchars($empruntId); // Collecter les IDs qui n'ont pas pu être supprimés
                }
            } else {
                $errors[] = "ID invalide '" . htmlspecialchars($empruntId) . "'";
            }
        }

        // Vérifier s'il y a eu des erreurs pendant la boucle
        if (empty($errors)) {
            $pdo->commit(); // Confirmer la transaction si tout s'est bien passé
            header('Location: index.php?bulk_delete_success=1');
            exit();
        } else {
            $pdo->rollBack(); // Annuler la transaction en cas d'erreur
            header('Location: index.php?error=' . urlencode("Impossible de supprimer certains emprunts : " . implode(", ", $errors)));
            exit();
        }

    } catch (PDOException $e) {
        $pdo->rollBack(); // Assurez-vous de faire un rollback en cas d'exception PDO
        error_log("Erreur PDO lors de la suppression de groupe : " . $e->getMessage());
        header('Location: index.php?error=' . urlencode("Erreur système de base de données lors de la suppression de groupe. Veuillez contacter l'administrateur."));
        exit();
    } catch (Exception $e) {
        $pdo->rollBack(); // Rollback pour toute autre exception
        error_log("Erreur inattendue lors de la suppression de groupe : " . $e->getMessage());
        header('Location: index.php?error=' . urlencode("Une erreur inattendue est survenue lors de la suppression de groupe."));
        exit();
    }

} else {
    // Rediriger si aucun emprunt n'a été sélectionné ou si la méthode de requête n'est pas POST
    header('Location: index.php?error=' . urlencode("Aucun emprunt sélectionné pour la suppression ou requête invalide."));
    exit();
}
?>