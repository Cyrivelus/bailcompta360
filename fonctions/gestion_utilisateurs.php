<?php

// fonctions/gestion_utilisateurs.php

/**
 * Fonctions de gestion des utilisateurs pour la table MySQL "Utilisateurs".
 * Compatible avec les colonnes : ID_Utilisateur, Nom, Login_Utilisateur, Mot_de_Passe, Role, Date_Creation, Derniere_Connexion, login_attempts, lockout_until
 */

// ---

## Fonctions principales de gestion des utilisateurs

/**
 * Crée un nouvel utilisateur dans la base de données.
 *
 * @param PDO    $pdo Instance de connexion à la base de données.
 * @param string $nom Nom de l'utilisateur.
 * @param string $login Login de l'utilisateur (doit être unique).
 * @param string $hashedPassword Mot de passe haché de l'utilisateur.
 * @param string $role Rôle de l'utilisateur (Admin, Comptable, Caissiere, etc.).
 * @return bool True en cas de succès, false en cas d'échec (ex: login déjà existant).
 */

function creerUtilisateur(PDO $pdo, string $nom, string $login, string $hashedPassword, string $role): bool
{
    try {
        // Optionnel : Vous pourriez ajouter une vérification de l'existence du login ici pour éviter les doublons.
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Utilisateurs WHERE Login_Utilisateur = :login");
        $stmtCheck->bindParam(':login', $login);
        $stmtCheck->execute();
        if ($stmtCheck->fetchColumn() > 0) {
            error_log("Tentative d'ajout d'un utilisateur avec un login existant: {$login}");
            return false;
        }

        // Préparation et exécution de la requête d'insertion
        // Correction : Utilisation de NOW() à la place de GETDATE()
        $sql = "INSERT INTO Utilisateurs (Nom, Login_Utilisateur, Mot_de_Passe, Role, Date_Creation) 
                VALUES (:nom, :login, :password, :role, NOW())";
        
        $stmt = $pdo->prepare($sql);
        
        $stmt->bindValue(':nom', $nom);
        $stmt->bindValue(':login', $login);
        $stmt->bindValue(':password', $hashedPassword);
        $stmt->bindValue(':role', $role);
        
        return $stmt->execute();
        
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la création de l'utilisateur: " . $e->getMessage());
        return false;
    }
}
/**
 * Met à jour les informations d'un utilisateur existant.
 *
 * @param PDO    $pdo Instance de connexion à la base de données.
 * @param int    $utilisateurId L'ID de l'utilisateur à modifier.
 * @param string $nom Nouveau nom de l'utilisateur.
 * @param string $login Nouveau login de l'utilisateur.
 * @param string $nouveauRole Nouveau rôle de l'utilisateur.
 * @return bool True en cas de succès, false en cas d'échec.
 */
function modifierUtilisateur(PDO $pdo, int $utilisateurId, string $nom, string $login, string $nouveauRole): bool
{
    try {
        // Optionnel : vérifier si le nouveau login n'est pas déjà pris par un autre utilisateur
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Utilisateurs WHERE Login_Utilisateur = :login AND ID_Utilisateur != :id");
        $stmtCheck->bindParam(':login', $login);
        $stmtCheck->bindParam(':id', $utilisateurId, PDO::PARAM_INT);
        $stmtCheck->execute();
        if ($stmtCheck->fetchColumn() > 0) {
            error_log("Tentative de modification avec un login existant pour l'utilisateur ID: {$utilisateurId}");
            return false;
        }
        
        $sql = "UPDATE Utilisateurs SET Nom = :nom, Login_Utilisateur = :login, Role = :role WHERE ID_Utilisateur = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':nom', $nom);
        $stmt->bindParam(':login', $login);
        $stmt->bindParam(':role', $nouveauRole, PDO::PARAM_STR);
        $stmt->bindParam(':id', $utilisateurId, PDO::PARAM_INT);
        return $stmt->execute();

    } catch (PDOException $e) {
        error_log("Erreur lors de la modification de l'utilisateur ID {$utilisateurId} : " . $e->getMessage());
        return false;
    }
}

/**
 * Met à jour le mot de passe d'un utilisateur existant.
 *
 * @param PDO    $pdo Instance de connexion à la base de données.
 * @param int    $userId L'ID de l'utilisateur à modifier.
 * @param string $newPassword Le nouveau mot de passe en clair.
 * @return bool True en cas de succès, false sinon.
 */
function mettreAJourMotDePasse(PDO $pdo, int $userId, string $newPassword): bool
{
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    if ($hashedPassword === false) {
        error_log("Erreur lors du hachage du mot de passe pour l'utilisateur ID: " . $userId);
        return false;
    }

    try {
        $stmt = $pdo->prepare("UPDATE Utilisateurs SET Mot_de_Passe = :hashedPassword WHERE ID_Utilisateur = :userId");
        $stmt->bindParam(':hashedPassword', $hashedPassword, PDO::PARAM_STR);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur BD lors de la mise à jour du mot de passe pour l'utilisateur ID: " . $userId . " - " . $e->getMessage());
        return false;
    }
}

/**
 * Supprime un utilisateur de la base de données.
 *
 * @param PDO $pdo Instance de connexion à la base de données.
 * @param int $utilisateurId L'ID de l'utilisateur à supprimer.
 * @return bool True si la suppression a réussi, false sinon.
 */
function supprimerUtilisateur(PDO $pdo, int $utilisateurId): bool
{
    try {
        $stmt = $pdo->prepare("DELETE FROM Utilisateurs WHERE ID_Utilisateur = :id");
        $stmt->bindParam(':id', $utilisateurId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression de l'utilisateur ID {$utilisateurId} : " . $e->getMessage());
        return false;
    }
}

// ---

## Fonctions de récupération et de vérification des données

/**
 * Récupère les informations d'un utilisateur par son ID.
 *
 * @param PDO $pdo Instance de connexion à la base de données.
 * @param int $id L'ID de l'utilisateur à récupérer.
 * @return array|null Un tableau associatif des informations de l'utilisateur, ou null si non trouvé.
 */
function getUtilisateurParId(PDO $pdo, int $id): ?array
{
    $sql = "SELECT ID_Utilisateur, Nom, Login_Utilisateur, Role, Date_Creation, Derniere_Connexion FROM Utilisateurs WHERE ID_Utilisateur = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Récupère la liste de tous les utilisateurs.
 *
 * @param PDO $pdo Instance de connexion à la base de données.
 * @return array Un tableau associatif contenant les informations de chaque utilisateur.
 */
function getTousLesUtilisateurs(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT ID_Utilisateur, Nom, Login_Utilisateur, Role, Derniere_Connexion FROM Utilisateurs ORDER BY Nom ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de tous les utilisateurs : " . $e->getMessage());
        return [];
    }
}

/**
 * Vérifie si l'utilisateur connecté a le rôle 'Admin'.
 *
 * @return bool True si l'utilisateur est un administrateur, false sinon.
 */
function estAdminConnecte(): bool
{
    return isset($_SESSION['utilisateur_role']) && $_SESSION['utilisateur_role'] === 'Admin';
}

// ---

## Fonctions de sécurité

/**
 * Met à jour la date et l'heure de la dernière connexion de l'utilisateur.
 *
 * @param PDO $pdo Instance de connexion à la base de données.
 * @param int $utilisateurId ID de l'utilisateur.
 * @return bool True en cas de succès, false sinon.
 */
function updateDerniereConnexion(PDO $pdo, int $utilisateurId): bool
{
    try {
        $stmt = $pdo->prepare("UPDATE Utilisateurs SET Derniere_Connexion = NOW() WHERE ID_Utilisateur = :id");
        $stmt->bindParam(':id', $utilisateurId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la mise à jour de la dernière connexion : " . $e->getMessage());
        return false;
    }
}

/**
 * Suspend temporairement un utilisateur en bloquant ses tentatives de connexion.
 *
 * @param PDO    $pdo Instance de connexion à la base de données.
 * @param int    $userId ID de l'utilisateur.
 * @param string $suspensionEndDate Date de fin de la suspension au format 'Y-m-d H:i:s'.
 * @return bool True en cas de succès, false sinon.
 */
function suspendreUtilisateur(PDO $pdo, int $userId, string $suspensionEndDate): bool
{
    try {
        $highAttempts = 999;
        $sql = "UPDATE Utilisateurs SET login_attempts = :attempts, lockout_until = :lockout_until WHERE ID_Utilisateur = :userId";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':attempts', $highAttempts, PDO::PARAM_INT);
        $stmt->bindParam(':lockout_until', $suspensionEndDate);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la suspension de l'utilisateur ID {$userId}: " . $e->getMessage());
        return false;
    }
}

/**
 * Lève la suspension d'un utilisateur.
 *
 * @param PDO $pdo Instance de connexion à la base de données.
 * @param int $userId ID de l'utilisateur.
 * @return bool True en cas de succès, false sinon.
 */
function unsuspendreUtilisateur(PDO $pdo, int $userId): bool
{
    try {
        $sql = "UPDATE Utilisateurs SET login_attempts = 0, lockout_until = NULL WHERE ID_Utilisateur = :userId";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la levée de suspension de l'utilisateur ID {$userId}: " . $e->getMessage());
        return false;
    }
}

// ---

## Fonctions dépréciées (à supprimer ou adapter)

/**
 * Fonctions de gestion des habilitations (ancien schéma de base de données).
 * Vous devez les adapter si vous utilisez ce concept dans votre nouveau schéma.
 *
 * NOTE: Ces fonctions ne correspondent pas à la table "Utilisateurs" que vous avez fournie.
 * Vous devriez les supprimer ou les réécrire pour correspondre à votre nouveau modèle de données.
 */
function hasHabilitation($pdo, $userId = null, $profilId = null, $objetProtege) {
    // ... code à adapter ou supprimer ...
}
function getListeClientsPLN(PDO $pdo): array {
    // ... code à adapter ou supprimer ...
}
function supprimerSessionUtilisateur(PDO $pdo, int $utilisateurId): bool {
    // ... code à adapter ou supprimer ...
}
// etc...