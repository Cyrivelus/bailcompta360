<?php
// fonctions/gestion_droits.php

require_once("database.php"); // Inclure le fichier de connexion à la base de données
require_once("gestion_utilisateurs.php"); // Pour récupérer les informations de l'utilisateur
require_once("gestion_profils.php"); // Pour récupérer les informations du profil

/**
 * Vérifie si un utilisateur a la permission d'accéder à un objet spécifique.
 *
 * La vérification se fait d'abord au niveau des habilitations spécifiques de l'utilisateur,
 * puis au niveau des habilitations de son profil.
 *
 * @param PDO   $db          L'objet de connexion à la base de données.
 * @param int   $utilisateurId L'ID de l'utilisateur à vérifier.
 * @param string $objet       Le nom de l'objet protégé (ex: 'ecritures_saisie', 'comptes_liste').
 * @return bool True si l'utilisateur a la permission, false sinon.
 */
function hasPermission(PDO $pdo, int $utilisateurId, string $objet): bool
{
    // Vérifier les habilitations spécifiques de l'utilisateur
    $sqlUserHabilitation = "SELECT COUNT(*)
                            FROM Habilitations_Utilisateur
                            WHERE ID_Utilisateur = :utilisateur_id AND Objet = :objet";
    $stmtUserHabilitation = $pdo->prepare($sqlUserHabilitation);
    $stmtUserHabilitation->bindParam(':utilisateur_id', $utilisateurId, PDO::PARAM_INT);
    $stmtUserHabilitation->bindParam(':objet', $objet);
    $stmtUserHabilitation->execute();

    if ($stmtUserHabilitation->fetchColumn() > 0) {
        return true; // L'utilisateur a une habilitation spécifique
    }

    // Récupérer l'ID du profil de l'utilisateur
    $utilisateur = getDetailsUtilisateur($pdo, $utilisateurId);
    if ($utilisateur && $utilisateur['ID_Profil']) {
        $profilId = $utilisateur['ID_Profil'];

        // Vérifier les habilitations du profil de l'utilisateur
        $sqlProfilHabilitation = "SELECT COUNT(*)
                                FROM Habilitations_Profil
                                WHERE ID_Profil = :profil_id AND Objet = :objet";
        $stmtProfilHabilitation = $pdo->prepare($sqlProfilHabilitation);
        $stmtProfilHabilitation->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
        $stmtProfilHabilitation->bindParam(':objet', $objet);
        $stmtProfilHabilitation->execute();

        if ($stmtProfilHabilitation->fetchColumn() > 0) {
            return true; // Le profil de l'utilisateur a l'habilitation
        }
    }

    return false; // L'utilisateur et son profil n'ont pas la permission
}

/**
 * Vérifie si un profil a la permission d'accéder à un objet spécifique.
 *
 * @param PDO   $db      L'objet de connexion à la base de données.
 * @param int   $profilId L'ID du profil à vérifier.
 * @param string $objet   Le nom de l'objet protégé.
 * @return bool True si le profil a la permission, false sinon.
 */
function profilHasPermission(PDO $pdo, int $profilId, string $objet): bool
{
    $sqlProfilHabilitation = "SELECT COUNT(*)
                            FROM Habilitations_Profil
                            WHERE ID_Profil = :profil_id AND Objet = :objet";
    $stmtProfilHabilitation = $pdo->prepare($sqlProfilHabilitation);
    $stmtProfilHabilitation->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
    $stmtProfilHabilitation->bindParam(':objet', $objet);
    $stmtProfilHabilitation->execute();

    return (bool) $stmtProfilHabilitation->fetchColumn();
}

/**
 * Récupère la liste des objets protégés pour un profil donné.
 *
 * @param PDO $db      L'objet de connexion à la base de données.
 * @param int $profilId L'ID du profil.
 * @return array Un tableau contenant les noms des objets protégés auxquels le profil a accès.
 */
function getPermissionsForProfil(PDO $pdo, int $profilId): array
{
    $sql = "SELECT Objet FROM Habilitations_Profil WHERE ID_Profil = :profil_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
    $stmt->execute();
    $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return $permissions;
}

/**
 * Récupère la liste des objets protégés pour un utilisateur donné.
 *
 * @param PDO $db          L'objet de connexion à la base de données.
 * @param int $utilisateurId L'ID de l'utilisateur.
 * @return array Un tableau contenant les noms des objets protégés auxquels l'utilisateur a accès (spécifiquement).
 */
function getPermissionsForUtilisateur(PDO $db, int $utilisateurId): array
{
    $sql = "SELECT Objet FROM Habilitations_Utilisateur WHERE ID_Utilisateur = :utilisateur_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':utilisateur_id', $utilisateurId, PDO::PARAM_INT);
    $stmt->execute();
    $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return $permissions;
}

/**
 * Ajoute une permission (accès à un objet) à un profil.
 *
 * @param PDO   $db       L'objet de connexion à la base de données.
 * @param int   $profilId L'ID du profil.
 * @param string $objet    Le nom de l'objet à autoriser.
 * @return bool True en cas de succès, false en cas d'erreur (ou si la permission existe déjà).
 */
function ajouterPermissionProfil(PDO $pdo, int $profilId, string $objet): bool
{
    if (profilHasPermission($pdo, $profilId, $objet)) {
        return false; // La permission existe déjà
    }
    $sql = "INSERT INTO Habilitations_Profil (ID_Profil, Objet) VALUES (:profil_id, :objet)";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
    $stmt->bindParam(':objet', $objet);
    return $stmt->execute();
}

/**
 * Supprime une permission (accès à un objet) d'un profil.
 *
 * @param PDO   $db       L'objet de connexion à la base de données.
 * @param int   $profilId L'ID du profil.
 * @param string $objet    Le nom de l'objet à retirer.
 * @return bool True en cas de succès, false en cas d'erreur.
 */
function supprimerPermissionProfil(PDO $pdo, int $profilId, string $objet): bool
{
    $sql = "DELETE FROM Habilitations_Profil WHERE ID_Profil = :profil_id AND Objet = :objet";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
    $stmt->bindParam(':objet', $objet);
    return $stmt->execute();
}

/**
 * Ajoute une permission (accès à un objet) à un utilisateur spécifique.
 *
 * @param PDO   $db          L'objet de connexion à la base de données.
 * @param int   $utilisateurId L'ID de l'utilisateur.
 * @param string $objet       Le nom de l'objet à autoriser.
 * @return bool True en cas de succès, false en cas d'erreur (ou si la permission existe déjà).
 */
function ajouterPermissionUtilisateur(PDO $db, int $utilisateurId, string $objet): bool
{
    // Vérifier si l'utilisateur a déjà cette permission spécifique
    $sqlCheck = "SELECT COUNT(*) FROM Habilitations_Utilisateur WHERE ID_Utilisateur = :utilisateur_id AND Objet = :objet";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->bindParam(':utilisateur_id', $utilisateurId, PDO::PARAM_INT);
    $stmtCheck->bindParam(':objet', $objet);
    $stmtCheck->execute();
    if ($stmtCheck->fetchColumn() > 0) {
        return false; // La permission existe déjà pour cet utilisateur
    }

    $sql = "INSERT INTO Habilitations_Utilisateur (ID_Utilisateur, Objet) VALUES (:utilisateur_id, :objet)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':utilisateur_id', $utilisateurId, PDO::PARAM_INT);
    $stmt->bindParam(':objet', $objet);
    return $stmt->execute();
}

/**
 * Supprime une permission (accès à un objet) d'un utilisateur spécifique.
 *
 * @param PDO   $db          L'objet de connexion à la base de données.
 * @param int   $utilisateurId L'ID de l'utilisateur.
 * @param string $objet       Le nom de l'objet à retirer.
 * @return bool True en cas de succès, false en cas d'erreur.
 */
function supprimerPermissionUtilisateur(PDO $pdo, int $utilisateurId, string $objet): bool
{
    $sql = "DELETE FROM Habilitations_Utilisateur WHERE ID_Utilisateur = :utilisateur_id AND Objet = :objet";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':utilisateur_id', $utilisateurId, PDO::PARAM_INT);
    $stmt->bindParam(':objet', $objet);
    return $stmt->execute();
}

/**
 * Vérifie si un utilisateur n'est pas autorisé à un chapitre comptable spécifique
 * pour un type d'opération et une devise donnés (au niveau utilisateur).
 *
 * @param PDO   $db          L'objet de connexion à la base de données.
 * @param int   $utilisateurId L'ID de l'utilisateur.
 * @param string $typeOperation Le type d'opération (ex: 'Saisie', 'Modification').
 * @param string $deviseExp     La devise de l'opération.
 * @param string $chapitre      Le chapitre comptable.
 * @return bool True si l'utilisateur n'est pas autorisé, false sinon.
 */
function isChapitreNonAutoriseUtilisateur(PDO $pdo, int $utilisateurId, string $typeOperation, string $deviseExp, string $chapitre): bool
{
    $sql = "SELECT COUNT(*)
            FROM Chapitres_Non_Autorises_Utilisateur
            WHERE ID_Utilisateur = :utilisateur_id
              AND Type_Operation = :type_operation
              AND Devise_Exp = :devise_exp
              AND Chapitre = :chapitre";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':utilisateur_id', $utilisateurId, PDO::PARAM_INT);
    $stmt->bindParam(':type_operation', $typeOperation);
    $stmt->bindParam(':devise_exp', $deviseExp);
    $stmt->bindParam(':chapitre', $chapitre);
    $stmt->execute();
    return (bool) $stmt->fetchColumn();
}

/**
 * Ajoute une restriction de chapitre comptable pour un utilisateur.
 *
 * @param PDO   $db          L'objet de connexion à la base de données.
 * @param int   $utilisateurId L'ID de l'utilisateur.
 * @param string $typeOperation Le type d'opération.
 * @param string $deviseExp     La devise.
 * @param string $chapitre      Le chapitre à interdire.
 * @return bool True en cas de succès, false en cas d'erreur (ou si la restriction existe déjà).
 */
function ajouterChapitreNonAutoriseUtilisateur(PDO $pdo, int $utilisateurId, string $typeOperation, string $deviseExp, string $chapitre): bool
{
    $sqlCheck = "SELECT COUNT(*)
                 FROM Chapitres_Non_Autorises_Utilisateur
                 WHERE ID_Utilisateur = :utilisateur_id
                   AND Type_Operation = :type_operation
                   AND Devise_Exp = :devise_exp
                   AND Chapitre = :chapitre";
    $stmtCheck = $db->prepare($sqlCheck);
    $stmtCheck->bindParam(':utilisateur_id', $utilisateurId, PDO::PARAM_INT);
    $stmtCheck->bindParam(':type_operation', $typeOperation);
    $stmtCheck->bindParam(':devise_exp', $deviseExp);
    $stmtCheck->bindParam(':chapitre', $chapitre);
    $stmtCheck->execute();
    if ($stmtCheck->fetchColumn() > 0) {
        return false; // La restriction existe déjà
    }

    $sql = "INSERT INTO Chapitres_Non_Autorises_Utilisateur (ID_Utilisateur, Type_Operation, Devise_Exp, Chapitre)
            VALUES (:utilisateur_id, :type_operation, :devise_exp, :chapitre)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':utilisateur_id', $utilisateurId, PDO::PARAM_INT);
    $stmt->bindParam(':type_operation', $typeOperation);
    $stmt->bindParam(':devise_exp', $deviseExp);
    $stmt->bindParam(':chapitre', $chapitre);
    return $stmt->execute();
}

/**
 * Supprime une restriction de chapitre comptable pour un utilisateur.
 *
 * @param PDO   $db          L'objet de connexion à la base de données.
 * @param int   $utilisateurId L'ID de l'utilisateur.
 * @param string $typeOperation Le type d'opération.
 * @param string $deviseExp     La devise.
 * @param string $chapitre      Le chapitre à autoriser.
 * @return bool True en cas de succès, false en cas d'erreur.
 */
function supprimerChapitreNonAutoriseUtilisateur(PDO $pdo, int $utilisateurId, string $typeOperation, string $deviseExp, string $chapitre): bool
{
    $sql = "DELETE FROM Chapitres_Non_Autorises_Utilisateur
            WHERE ID_Utilisateur = :utilisateur_id
              AND Type_Operation = :type_operation
              AND Devise_Exp = :devise_exp
              AND Chapitre = :chapitre";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':utilisateur_id', $utilisateurId, PDO::PARAM_INT);
    $stmt->bindParam(':type_operation', $typeOperation);
    $stmt->bindParam(':devise_exp', $deviseExp);
    $stmt->bindParam(':chapitre', $chapitre);
    return $stmt->execute();
}

/**
 * Vérifie si un profil n'est pas autorisé à un chapitre comptable spécifique
 * pour un type d'opération et une devise donnés (au niveau profil).
 *
 * @param PDO   $db      L'objet de connexion à la base de données.
 * @param int   $profilId L'ID du profil.
 * @param string $typeOperation Le type d'opération.
 * @param string $deviseExp     La devise de l'opération.
 * @param string $chapitre      Le chapitre comptable.
 * @return bool True si le profil n'est pas autorisé, false sinon.
 */
function isChapitreNonAutoriseProfil(PDO $pdo, int $profilId, string $typeOperation, string $deviseExp, string $chapitre): bool
{
    $sql = "SELECT COUNT(*)
            FROM Chapitres_Non_Autorises_Profil
            WHERE ID_Profil = :profil_id
              AND Type_Operation = :type_operation
              AND Devise_Exp = :devise_exp
              AND Chapitre = :chapitre";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
    $stmt->bindParam(':type_operation', $typeOperation);
    $stmt->bindParam(':devise_exp', $deviseExp);
    $stmt->bindParam(':chapitre', $chapitre);
    $stmt->execute();
    return (bool) $stmt->fetchColumn();
}

/**
 * Ajoute une restriction de chapitre comptable pour un profil.
 *
 * @param PDO   $db      L'objet de connexion à la base de données.
 * @param int   $profilId L'ID du profil.
 * @param string $typeOperation Le type d'opération.
 * @param string $deviseExp     La devise.
 * @param string $chapitre      Le chapitre à interdire.
 * @return bool True en cas de succès, false en cas d'erreur (ou si la restriction existe déjà).
 */
function ajouterChapitreNonAutoriseProfil(PDO $pdo, int $profilId, string $typeOperation, string $deviseExp, string $chapitre): bool
{
    $sqlCheck = "SELECT COUNT(*)
                 FROM Chapitres_Non_Autorises_Profil
                 WHERE ID_Profil = :profil_id
                   AND Type_Operation = :type_operation
                   AND Devise_Exp = :devise_exp
                   AND Chapitre = :chapitre";
    $stmtCheck = $pdo->prepare($sqlCheck);
$stmtCheck->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
$stmtCheck->bindParam(':type_operation', $typeOperation);
$stmtCheck->bindParam(':devise_exp', $deviseExp);
$stmtCheck->bindParam(':chapitre', $chapitre);
$stmtCheck->execute();

if ($stmtCheck->fetchColumn() > 0) {
    return false; // La restriction existe déjà
}

    $sql = "INSERT INTO Chapitres_Non_Autorises_Profil (ID_Profil, Type_Operation, Devise_Exp, Chapitre)
            VALUES (:profil_id, :type_operation, :devise_exp, :chapitre)";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
    $stmt->bindParam(':type_operation', $typeOperation);
    $stmt->bindParam(':devise_exp', $deviseExp);
    $stmt->bindParam(':chapitre', $chapitre);
    return $stmt->execute();
}

/**
 * Supprime une restriction de chapitre comptable pour un profil.
 *
 * @param PDO   $db      L'objet de connexion à la base de données.
 * @param int   $profilId L'ID du profil.
 * @param string $typeOperation Le type d'opération.
 * @param string $deviseExp     La devise.
 * @param string $chapitre      Le chapitre à autoriser.
 * @return bool True en cas de succès, false en cas d'erreur.
 */
function supprimerChapitreNonAutoriseProfil(PDO $pdo, int $profilId, string $typeOperation, string $deviseExp, string $chapitre): bool
{
    $sql = "DELETE FROM Chapitres_Non_Autorises_Profil
            WHERE ID_Profil = :profil_id
              AND Type_Operation = :type_operation
              AND Devise_Exp = :devise_exp
              AND Chapitre = :chapitre";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
    $stmt->bindParam(':type_operation', $typeOperation);
    $stmt->bindParam(':devise_exp', $deviseExp);
    $stmt->bindParam(':chapitre', $chapitre);
    return $stmt->execute();
}

// Vous pouvez ajouter d'autres fonctions spécifiques à la gestion des droits ici