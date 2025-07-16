<?php

// fonctions/gestion_profils.php

/**
 * Ajoute un nouveau profil d'utilisateur dans la base de données.
 *
 * @param PDO   $db  Instance de connexion à la base de données.
 * @param string $nom Nom du profil (requis, unique).
 * @return int|bool L'ID du profil inséré en cas de succès, false en cas d'erreur (ou si le nom existe déjà).
 */

function getTousLesProfils(PDO $pdo) {
        $sql = "SELECT
                    P.ID_Profil,
                    P.Nom_Profil,
                    P.Description_Profil,
                    P.ID_Utilisateur,
                    U.Nom AS Nom_Utilisateur,      -- Récupère le nom de l'utilisateur
                    U.Login_Utilisateur             -- Récupère le login de l'utilisateur
                FROM Profils AS P
                LEFT JOIN Utilisateurs AS U ON P.ID_Utilisateur = U.ID_Utilisateur -- Jointure pour lier Profil et Utilisateur
                ORDER BY P.Nom_Profil ASC"; // Ou un autre ordre
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


function mettreAJourUtilisateursProfil(PDO $pdo, int $profilId, array $listeUtilisateurs): bool
{
    try {
        // Désactiver les assignations actuelles pour ce profil
        $stmtDelete = $pdo->prepare("DELETE FROM Profils_Utilisateurs WHERE ID_Profil = :profil_id");
        $stmtDelete->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
        $stmtDelete->execute();

        // Ajouter les nouvelles assignations
        if (!empty($listeUtilisateurs)) {
            $sqlInsert = "INSERT INTO Profils_Utilisateurs (ID_Profil, ID_Utilisateur) VALUES (:profil_id, :utilisateur_id)";
            $stmtInsert = $pdo->prepare($sqlInsert);
            $pdo->beginTransaction(); // Pour une meilleure cohérence transactionnelle

            foreach ($listeUtilisateurs as $utilisateurId) {
                $stmtInsert->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
                $stmtInsert->bindParam(':utilisateur_id', $utilisateurId, PDO::PARAM_INT);
                $stmtInsert->execute();
            }

            $pdo->commit();
        }

        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erreur lors de la mise à jour des utilisateurs pour le profil {$profilId} : " . $e->getMessage());
        return false;
    }
}

function getUtilisateursParProfilId(PDO $pdo, int $profilId): array
{
    try {
        $stmt = $pdo->prepare("SELECT u.ID_Utilisateur, u.Nom, u.Login_Utilisateur
                               FROM Utilisateurs u
                               INNER JOIN Profils_Utilisateurs pu ON u.ID_Utilisateur = pu.ID_Utilisateur
                               WHERE pu.ID_Profil = :profil_id
                               ORDER BY u.Nom ASC");
        $stmt->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des utilisateurs pour le profil {$profilId} : " . $e->getMessage());
        return [];
    }
}

function dupliquerProfilEtHabilitations(PDO $pdo, int $profilIdSource, string $nouveauNomProfil)
{
    try {
        // 1. Créer le nouveau profil
        $stmtInsertProfil = $pdo->prepare("INSERT INTO Profils (Nom_Profil) VALUES (:nom_profil)");
        $stmtInsertProfil->bindParam(':nom_profil', $nouveauNomProfil, PDO::PARAM_STR);
        $stmtInsertProfil->execute();
        $nouveauProfilId = $pdo->lastInsertId();

        if ($nouveauProfilId) {
            // 2. Récupérer les habilitations du profil source
            $stmtSelectHabilitations = $pdo->prepare("SELECT Objet FROM Habilitations_Profil WHERE ID_Profil = :profil_id");
            $stmtSelectHabilitations->bindParam(':profil_id', $profilIdSource, PDO::PARAM_INT);
            $stmtSelectHabilitations->execute();
            $habilitationsSource = $stmtSelectHabilitations->fetchAll(PDO::FETCH_COLUMN);

            // 3. Insérer les habilitations pour le nouveau profil
            if (!empty($habilitationsSource)) {
                $stmtInsertHabilitation = $pdo->prepare("INSERT INTO Habilitations_Profil (ID_Profil, Objet) VALUES (:profil_id, :objet)");
                foreach ($habilitationsSource as $objet) {
                    $stmtInsertHabilitation->bindParam(':profil_id', $nouveauProfilId, PDO::PARAM_INT);
                    $stmtInsertHabilitation->bindParam(':objet', $objet, PDO::PARAM_STR);
                    $stmtInsertHabilitation->execute();
                }
            }
            return $nouveauProfilId;
        } else {
            return false;
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la duplication du profil {$profilIdSource} : " . $e->getMessage());
        return false;
    }
}


function ajouterProfil(PDO $pdo, string $nom): int|bool
{
    try {
        // Vérifier si le nom du profil existe déjà
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Profils WHERE Nom = :nom");
        $stmtCheck->bindParam(':nom', $nom);
        $stmtCheck->execute();
        if ($stmtCheck->fetchColumn() > 0) {
            return false; // Le nom du profil existe déjà
        }

        $stmt = $pdo->prepare("INSERT INTO Profils (Nom, Date_creation) VALUES (:nom, NOW())");
        $stmt->bindParam(':nom', $nom);
        $stmt->execute();

        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout du profil : " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère la liste de tous les profils d'utilisateurs.
 *
 * @param PDO $db Instance de connexion à la base de données.
 * @return array Un tableau associatif contenant les informations de chaque profil, ou un tableau vide en cas d'erreur.
 */


/**
 * Récupère les informations d'un profil spécifique par son ID.
 *
 * @param PDO $db     Instance de connexion à la base de données.
 * @param int $profilId L'ID du profil à récupérer.
 * @return array|bool Un tableau associatif contenant les informations du profil si trouvé, false sinon.
 */


/**
 * Modifie le nom d'un profil d'utilisateur existant dans la base de données.
 *
 * @param PDO   $db       Instance de connexion à la base de données.
 * @param int   $profilId L'ID du profil à modifier (requis).
 * @param string $nouveauNom Nouveau nom du profil (requis, unique).
 * @return bool True en cas de succès, false en cas d'erreur (ou si le nouveau nom existe déjà pour un autre profil).
 */
function modifierProfil(PDO $pdo, int $profilId, string $nouveauNom): bool
{
    try {
        // Vérifier si le nouveau nom du profil existe déjà pour un autre profil
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Profils WHERE Nom = :nom AND ID_Profil != :id");
        $stmtCheck->bindParam(':nom', $nouveauNom);
        $stmtCheck->bindParam(':id', $profilId, PDO::PARAM_INT);
        $stmtCheck->execute();
        if ($stmtCheck->fetchColumn() > 0) {
            return false; // Le nouveau nom du profil existe déjà
        }

        $stmt = $pdo->prepare("UPDATE Profils SET Nom = :nom WHERE ID_Profil = :id");
        $stmt->bindParam(':nom', $nouveauNom);
        $stmt->bindParam(':id', $profilId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la modification du profil avec l'ID $profilId : " . $e->getMessage());
        return false;
    }
}

/**
 * Supprime un profil d'utilisateur de la base de données par son ID.
 *
 * @param PDO $db     Instance de connexion à la base de données.
 * @param int $profilId L'ID du profil à supprimer.
 * @return bool True en cas de succès, false en cas d'erreur (ou si des utilisateurs sont encore associés à ce profil).
 */
function supprimerProfil(PDO $pdo, int $profilId): bool
{
    try {
        // Vérifier si des utilisateurs sont associés à ce profil
        // NOTE : Votre table Utilisateurs_Profils gère l'association, pas Utilisateurs.
        // La vérification devrait peut-être être sur Profils_Utilisateurs ?
        // Si un profil n'est pas censé être supprimé s'il est attribué à un utilisateur via Profils_Utilisateurs :
         $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Profils_Utilisateurs WHERE ID_Profil = :id");
         $stmtCheck->bindParam(':id', $profilId, PDO::PARAM_INT);
         $stmtCheck->execute();
         if ($stmtCheck->fetchColumn() > 0) {
             // Alternativement, vous pourriez vouloir supprimer les associations d'abord
             // DELETE FROM Profils_Utilisateurs WHERE ID_Profil = :id
             return false; // Des utilisateurs sont encore associés à ce profil via Profils_Utilisateurs
         }
        // Si la vérification sur Utilisateurs.Profil_ID est correcte selon votre schéma, laissez-la.
        // Assurez-vous que votre schéma de base de données correspond à votre code.


        $stmt = $pdo->prepare("DELETE FROM Profils WHERE ID_Profil = :id");
        $stmt->bindParam(':id', $profilId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression du profil avec l'ID $profilId : " . $e->getMessage());
        return false;
    }
}


/**
 * Récupère la liste de toutes les habilitations disponibles.
 * Assumes a table named 'Habilitations' with a column 'Objet'.
 * Adaptez la requête si votre schéma de base de données est différent (par exemple, table 'Droits', colonne 'Code').
 *
 * @param PDO $pdo Instance de connexion à la base de données.
 * @return array Un tableau associatif contenant les habilitations (par exemple, un tableau de tableaux avec la clé 'Objet'), ou un tableau vide en cas d'erreur.
 */
function getListeHabilitations(PDO $pdo): array
{
    try {
        // Requête par défaut assumant une table 'Habilitations' avec une colonne 'Objet'.
        // Si vous avez une colonne 'Description' pour un nom lisible, vous pourriez la récupérer aussi :
        // $stmt = $pdo->prepare("SELECT Objet, Description FROM Habilitations ORDER BY Description ASC");
        $stmt = $pdo->prepare("SELECT Objet FROM Habilitations ORDER BY Objet ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Retourne un tableau de tableaux associatifs
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la liste des habilitations : " . $e->getMessage());
        // En cas d'erreur, retourne un tableau vide
        return [];
    }
}


// Vous pouvez ajouter d'autres fonctions spécifiques à la gestion des profils ici,
// comme la gestion des droits associés à chaque profil (cela pourrait se trouver dans un autre fichier dédié aux droits).

?>