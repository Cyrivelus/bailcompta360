<?php
// pages/ecritures/supprimer_ecritures_groupe.php

require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_ecritures.php'; // Contient la fonction supprimerEcriture



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_ecritures']) && is_array($_POST['selected_ecritures'])) {
    $selectedEcritures = $_POST['selected_ecritures'];
    $deletedCount = 0;
    $errors = [];

    try {
        $pdo->beginTransaction();

        foreach ($selectedEcritures as $ecritureId) {
            if (is_numeric($ecritureId)) {
                // Appeler la fonction de suppression pour chaque �criture
                // Assurez-vous que supprimerEcriture est d�finie dans fonctions/gestion_ecritures.php
                // et qu'elle g�re aussi la suppression des lignes d'�criture associ�es si n�cessaire.
                if (supprimerEcriture($pdo, (int)$ecritureId)) {
                    $deletedCount++;
                } else {
                    $errors[] = "ID " . htmlspecialchars($ecritureId);
                }
            } else {
                $errors[] = "ID invalide '" . htmlspecialchars($ecritureId) . "'";
            }
        }

        if (empty($errors)) {
            $pdo->commit();
            header('Location: liste.php?bulk_delete_success=1');
            exit();
        } else {
            $pdo->rollBack();
            header('Location: liste.php?error=' . urlencode("Impossible de supprimer certaines �critures : " . implode(", ", $errors)));
            exit();
        }

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erreur PDO lors de la suppression de groupe : " . $e->getMessage());
        header('Location: liste.php?error=' . urlencode("Erreur syst�me de base de donn�es lors de la suppression de groupe."));
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur inattendue lors de la suppression de groupe : " . $e->getMessage());
        header('Location: liste.php?error=' . urlencode("Une erreur inattendue est survenue lors de la suppression de groupe."));
        exit();
    }

} else {
    header('Location: liste.php?error=' . urlencode("Aucune �criture s�lectionn�e pour la suppression ou requ�te invalide."));
    exit();
}
?>