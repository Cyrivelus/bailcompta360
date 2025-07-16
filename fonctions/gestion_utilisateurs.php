<?php

// fonctions/gestion_utilisateurs.php

/**
 * Ajoute un nouvel utilisateur dans la base de données.
 *
 * @param PDO    $db          Instance de connexion à la base de données.
 * @param string $nom         Nom de l'utilisateur (requis).
 * @param string $prenom      Prénom de l'utilisateur (requis).
 * @param string $email       Adresse email de l'utilisateur (requis, unique).
 * @param string $motDePasse  Mot de passe de l'utilisateur (requis).
 * @param int    $profilId    ID du profil de l'utilisateur (clé étrangère vers la table des profils).
 * @return int|bool L'ID de l'utilisateur inséré en cas de succès, false en cas d'erreur.
 */

// fonctions/gestion_utilisateurs.php

/**
 * Vérifie si l'utilisateur connecté est un administrateur.
 * Cette fonction suppose que l'ID de l'utilisateur est stocké dans $_SESSION['utilisateur_id']
 * et que le rôle de l'utilisateur est stocké dans $_SESSION['utilisateur_role'].
 * Vous devrez adapter cette fonction à votre système d'authentification spécifique.
 *
 * @return bool True si l'utilisateur est un administrateur, false sinon.
 */

 function unsuspendreUtilisateur(PDO $pdo, int $userId): bool {
    try {
        $sql = "UPDATE Utilisateurs SET login_attempts = 0, lockout_until = NULL WHERE ID_Utilisateur = :userId";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (\PDOException $e) {
        error_log("Erreur PDO lors de la levée de suspension de l'utilisateur ID {$userId}: " . $e->getMessage());
        return false;
    }
}

// You might want to update getTousLesUtilisateurs and getUtilisateursConnectesRecemment
// to also fetch 'login_attempts' and 'lockout_until' for display purposes in index.php.
// Let's add them to getTousLesUtilisateurs and getUtilisateursConnectesRecemment functions.

function suspendreUtilisateur(PDO $pdo, int $userId, string $suspensionEndDate): bool {
    try {
        // Set login_attempts to a value higher than maxLoginAttempts to trigger lockout logic
        // Use a value like 999 to clearly distinguish from regular failed attempts
        $highAttempts = 999;
        $sql = "UPDATE Utilisateurs SET login_attempts = :attempts, lockout_until = :lockout_until WHERE ID_Utilisateur = :userId";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':attempts', $highAttempts, PDO::PARAM_INT);
        $stmt->bindParam(':lockout_until', $suspensionEndDate);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (\PDOException $e) {
        error_log("Erreur PDO lors de la suspension de l'utilisateur ID {$userId}: " . $e->getMessage());
        return false;
    }
}


 function updateDerniereConnexion(PDO $pdo, int $utilisateurId): bool {
    try {
        $stmt = $pdo->prepare("UPDATE Utilisateurs SET Derniere_Connexion = NOW() WHERE ID_Utilisateur = :id");
        $stmt->bindParam(':id', $utilisateurId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (\PDOException $e) {
        error_log("Erreur lors de la mise à jour de la dernière connexion : " . $e->getMessage());
        return false;
    }
}
 
function getUtilisateursConnectesRecemment(PDO $pdo, int $minutes = 5): array {
    // CORRECTED SQL FOR MYSQL: Use DATE_SUB and INTERVAL with NOW()
    $sql = "SELECT * FROM utilisateurs WHERE Derniere_Connexion >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE) ORDER BY Derniere_Connexion DESC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':minutes', $minutes, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // It's good practice to log such errors for debugging in production
        error_log("Erreur SQL dans getUtilisateursConnectesRecemment: " . $e->getMessage());
        return []; // Return an empty array to prevent fatal errors on the page
    }
}
 
 
 function getUtilisateurParId(PDO $pdo, int $id): ?array {
    $sql = "SELECT ID_Utilisateur, Nom, Login_Utilisateur, Role, Date_Creation, Derniere_Connexion 
            FROM Utilisateurs 
            WHERE ID_Utilisateur = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);
        return $utilisateur ?: null;
    } else {
        return null;
    }
}

function genererMotDePasseAleatoire(): string {
    // Mot de passe par défaut défini dans la consigne
    return 'aaaAAA123';
}

 function getListeClientsPLN(PDO $pdo): array {
    try {
        // Requête pour sélectionner les comptes PLN qui commencent par '411'
        // Ceci est une hypothèse basée sur des conventions comptables courantes pour les comptes clients.
        // Adaptez la clause WHERE si vos comptes clients ont une autre structure ou sont identifiés différemment.
        $sql = "SELECT Cpt, Lib FROM PLN WHERE Cpt LIKE '411%' ORDER BY Cpt";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        // Récupérer tous les résultats
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $clients; // Retourne le tableau des clients (peut être vide)

    } catch (PDOException $e) {
        // En cas d'erreur de base de données, journaliser l'erreur
        error_log("Erreur lors du chargement des clients depuis PLN: " . $e->getMessage());
        // Retourner un tableau vide pour indiquer qu'aucune donnée n'a pu être récupérée
        return [];
    }
}
 
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

 function modifierRoleUtilisateur(PDO $pdo, int $utilisateurId, string $nouveauRole): bool
{
    try {
        $stmt = $pdo->prepare("UPDATE Utilisateurs SET Role = :role WHERE ID_Utilisateur = :id");
        $stmt->bindParam(':role', $nouveauRole, PDO::PARAM_STR);
        $stmt->bindParam(':id', $utilisateurId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la mise à jour du rôle de l'utilisateur {$utilisateurId} : " . $e->getMessage());
        return false;
    }
}

function estAdminConnecte() {
    if (isset($_SESSION['utilisateur_role']) && $_SESSION['utilisateur_role'] === 'Admin') {
        return true;
    }
    return false;
}

/**
 * Récupère les informations d'un utilisateur à partir de la base de données en fonction de son ID.
 *
 * @param PDO $pdo L'objet de connexion PDO à la base de données.
 * @param int $id L'ID de l'utilisateur à récupérer.
 * @return array|bool Les informations de l'utilisateur sous forme de tableau associatif,
 * ou false si l'utilisateur n'est pas trouvé ou en cas d'erreur.
 */

/**
 * Met à jour le mot de passe haché d'un utilisateur dans la base de données.
 *
 * @param PDO $pdo L'objet de connexion PDO à la base de données.
 * @param int $utilisateurId L'ID de l'utilisateur dont le mot de passe doit être mis à jour.
 * @param string $motDePasseHache Le nouveau mot de passe haché.
 * @return bool True si la mise à jour a réussi, false sinon.
 */
function mettreAJourMotDePasse(PDO $pdo, int $userId, string $newPassword): bool {
    // Hasher le mot de passe avant de le stocker !
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    if ($hashedPassword === false) {
        // Gérer l'erreur de hachage si nécessaire
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
 * Génère un mot de passe aléatoire sécurisé.
 *
 * @param int $longueur La longueur du mot de passe à générer (par défaut 12 caractères).
 * @return string Le mot de passe aléatoire généré.
 */


// Dans fonctions/gestion_habilitations.php

function hasHabilitation($pdo, $userId = null, $profilId = null, $objetProtege) {
    if ($userId) {
        // Vérifier si l'utilisateur a directement cette habilitation
        $stmtUser = $pdo->prepare("SELECT COUNT(*) FROM Habilitations_Utilisateur WHERE ID_Utilisateur = :user_id AND Objet = :objet");
        $stmtUser->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtUser->bindParam(':objet', $objetProtege, PDO::PARAM_STR);
        $stmtUser->execute();
        if ($stmtUser->fetchColumn() > 0) {
            return true;
        }

        // Vérifier le rôle de l'utilisateur
        $stmtRole = $pdo->prepare("SELECT Role FROM Utilisateurs WHERE ID_Utilisateur = :user_id");
        $stmtRole->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtRole->execute();
        $role = $stmtRole->fetchColumn();

        // Autoriser l'invité à certains objets spécifiques
        if ($role === 'Invité' && ($objetProtege === 'gestion_tiers' /* Ajoutez ici d'autres objets autorisés pour l'invité */)) {
            return true;
        }

        // Vérifier les habilitations via le profil de l'utilisateur
        $stmtProfilId = $pdo->prepare("SELECT ID_Profil FROM Utilisateurs WHERE ID_Utilisateur = :user_id");
        $stmtProfilId->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtProfilId->execute();
        $profilId = $stmtProfilId->fetchColumn();
    }

    if ($profilId) {
        // Vérifier si le profil a cette habilitation
        $stmtProfil = $pdo->prepare("SELECT COUNT(*) FROM Habilitations_Profil WHERE ID_Profil = :profil_id AND Objet = :objet");
        $stmtProfil->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
        $stmtProfil->bindParam(':objet', $objetProtege, PDO::PARAM_STR);
        $stmtProfil->execute();
        return $stmtProfil->fetchColumn() > 0;
    }

    return false; // Aucune information d'utilisateur ou de profil fournie
}
/**
 * Génère le mot de passe par défaut : aaaAAA123 et le hache.
 *
 * @return string Le mot de passe par défaut haché.
 */

function genererMotDePasseDefaut(): string {
    $motDePasseDefaut = 'aaaAAA123';
    // Le hachage a été enlevé. La fonction retourne maintenant le mot de passe en clair.
    return $motDePasseDefaut;
}

function ajouterUtilisateur(PDO $pdo, string $nom, string $prenom, string $email, string $motDePasse, int $profilId): int|bool
{
    try {
        // Vérifier si l'email existe déjà
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Utilisateurs WHERE Email = :email");
        $stmtCheck->bindParam(':email', $email);
        $stmtCheck->execute();
        if ($stmtCheck->fetchColumn() > 0) {
            // L'email existe déjà, vous pourriez vouloir retourner un code d'erreur spécifique ou lancer une exception
            // Par exemple : throw new Exception("L'adresse e-mail est déjà utilisée.");
            return false; // L'email existe déjà
        }

        // Ligne de hachage du mot de passe supprimée :
        // $hashedPassword = password_hash($motDePasse, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO Utilisateurs (Nom, Prenom, Email, Mot_de_passe, Profil_ID, Date_creation) VALUES (:nom, :prenom, :email, :mot_de_passe, :profil_id, NOW())");
        $stmt->bindParam(':nom', $nom);
        $stmt->bindParam(':prenom', $prenom);
        $stmt->bindParam(':email', $email);
        // Liaison directe du mot de passe en clair :
        $stmt->bindParam(':mot_de_passe', $motDePasse);
        $stmt->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
        $stmt->execute();

        return (int)$pdo->lastInsertId(); // Assurer que lastInsertId retourne un entier
    } catch (PDOException $e) {
        // Enregistrement de l'erreur pour le débogage
        error_log("Erreur PDO lors de l'ajout de l'utilisateur : " . $e->getMessage());
        return false; // Échec de l'ajout
    } catch (Exception $e) { // Optionnel: si vous décidez de lancer des exceptions personnalisées
        error_log("Erreur lors de l'ajout de l'utilisateur : " . $e->getMessage());
        return false;
    }
}


/**
 * Récupère la liste de tous les utilisateurs avec leurs informations de profil.
 *
 * @param PDO $db Instance de connexion à la base de données.
 * @return array Un tableau associatif contenant les informations de chaque utilisateur (y compris le nom du profil), ou un tableau vide en cas d'erreur.
 */
function getListeUtilisateurs(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT u.ID_Utilisateur, u.Nom, u.Prenom, u.Email, p.Nom AS Nom_Profil
                             FROM Utilisateurs u
                             INNER JOIN Profils p ON u.Profil_ID = p.ID_Profil
                             ORDER BY u.Nom, u.Prenom");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la liste des utilisateurs : " . $e->getMessage());
        return [];
    }
}

// fonctions/gestion_utilisateurs.php

/**
 * Supprime la session d'un utilisateur en fonction de son ID utilisateur.
 *
 * Cette fonction suppose que vous stockez l'ID de l'utilisateur dans votre mécanisme de session.
 * L'implémentation spécifique dépend de la façon dont vous gérez les sessions (fichiers, base de données, etc.).
 *
 * @param PDO $pdo L'objet de connexion PDO à la base de données (si vous stockez les sessions en base de données).
 * @param int $utilisateurId L'ID de l'utilisateur dont la session doit être supprimée.
 * @return bool True si la suppression de la session a réussi (ou si aucune session n'a été trouvée), false en cas d'erreur.
 */
function supprimerSessionUtilisateur(PDO $pdo, int $utilisateurId): bool
{
    // *** IMPORTANT : ADAPTEZ CETTE FONCTION À VOTRE SYSTÈME DE GESTION DES SESSIONS ***

    // Exemple si vous stockez l'ID de l'utilisateur dans $_SESSION
    if (isset($_SESSION['utilisateur_id']) && $_SESSION['utilisateur_id'] == $utilisateurId) {
        // Détruire toutes les variables de session
        $_SESSION = array();

        // Si vous utilisez des cookies de session, supprimez-les
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Finalement, détruire la session
        session_destroy();
        return true;
    }

    // Si vous stockez les sessions dans une table de base de données (recommandé pour la déconnexion forcée)
    // Assurez-vous d'avoir une table 'sessions' (ou un nom similaire) avec une colonne 'utilisateur_id'
    try {
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE utilisateur_id = :utilisateur_id");
        $stmt->bindParam(':utilisateur_id', $utilisateurId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression de la session de l'utilisateur {$utilisateurId} dans la base de données : " . $e->getMessage());
        return false;
    }

    // Si vous utilisez un autre système de gestion de sessions (Redis, Memcached, etc.),
    // vous devrez utiliser les fonctions spécifiques de ce système pour supprimer la session
    // en vous basant sur l'ID de l'utilisateur (si vous le stockez).

    // Si aucune session active n'est trouvée pour cet utilisateur, considérez cela comme un succès
    return true;
}

/**
 * Récupère les informations d'un utilisateur spécifique par son ID, y compris le nom de son profil.
 *
 * @param PDO $db            Instance de connexion à la base de données.
 * @param int $utilisateurId L'ID de l'utilisateur à récupérer.
 * @return array|bool Un tableau associatif contenant les informations de l'utilisateur (et le nom du profil) si trouvé, false sinon.
 */


/**
 * Modifie les informations d'un utilisateur existant dans la base de données.
 *
 * @param PDO    $db          Instance de connexion à la base de données.
 * @param int    $utilisateurId L'ID de l'utilisateur à modifier (requis).
 * @param string $nom         Nouveau nom de l'utilisateur (requis).
 * @param string $prenom      Nouveau prénom de l'utilisateur (requis).
 * @param string $email       Nouvelle adresse email de l'utilisateur (requis, unique).
 * @param int    $profilId    Nouvel ID du profil de l'utilisateur.
 * @param string|null $motDePasse Nouveau mot de passe de l'utilisateur (laisser null pour ne pas changer).
 * @return bool True en cas de succès, false en cas d'erreur (ou si l'email existe déjà pour un autre utilisateur).
 */
function modifierUtilisateur(PDO $pdo, int $utilisateurId, string $nom, string $prenom, string $email, int $profilId, ?string $motDePasse = null): bool
{
    try {
        // Vérifier si l'email existe déjà pour un autre utilisateur
        // Assuming 'Email' column exists in your Utilisateurs table based on the check below
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Utilisateurs WHERE Email = :email AND ID_Utilisateur != :id");
        $stmtCheck->bindParam(':email', $email);
        $stmtCheck->bindParam(':id', $utilisateurId, PDO::PARAM_INT);
        $stmtCheck->execute();
        if ($stmtCheck->fetchColumn() > 0) {
            // L'email existe déjà pour un autre utilisateur
            // Vous pourriez ajouter un message d'erreur flash ici si vous le souhaitez
            return false;
        }

        // Construction de la requête SQL de mise à jour
        $sql = "UPDATE Utilisateurs SET Nom = :nom, Prenom = :prenom, Email = :email, Profil_ID = :profil_id"; // Base query without password
        if ($motDePasse !== null) {
            // If a new password is provided, add it to the query
            // NOTE: Password is NOT hashed in this version as requested.
            // This is generally NOT recommended for security reasons.
            $sql .= ", Mot_de_passe = :mot_de_passe";
        }
        $sql .= " WHERE ID_Utilisateur = :id"; // Add the WHERE clause

        $stmt = $pdo->prepare($sql);

        // Bind parameters common to both cases
        $stmt->bindParam(':nom', $nom);
        $stmt->bindParam(':prenom', $prenom);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
        $stmt->bindParam(':id', $utilisateurId, PDO::PARAM_INT);

        // Bind password parameter only if it's being updated
        if ($motDePasse !== null) {
            // Binding the raw password directly
            $stmt->bindParam(':mot_de_passe', $motDePasse);
        }

        // Execute the statement
        return $stmt->execute();

    } catch (PDOException $e) {
        // Log the error for debugging
        error_log("Erreur lors de la modification de l'utilisateur avec l'ID $utilisateurId : " . $e->getMessage());
        // Return false to indicate failure
        return false;
    }
}
function supprimerUtilisateur(PDO $pdo, int $utilisateurId): bool
{
    try {
        $stmt = $pdo->prepare("DELETE FROM Utilisateurs WHERE ID_Utilisateur = :id");
        $stmt->bindParam(':id', $utilisateurId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression de l'utilisateur avec l'ID $utilisateurId : " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère la liste de tous les profils d'utilisateurs.
 *
 * @param PDO $db Instance de connexion à la base de données.
 * @return array Un tableau associatif contenant les informations de chaque profil, ou un tableau vide en cas d'erreur.
 */
function getListeProfils(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("SELECT ID_Profil, Nom FROM Profils ORDER BY Nom");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la liste des profils : " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les informations d'un profil spécifique par son ID.
 *
 * @param PDO $db      Instance de connexion à la base de données.
 * @param int $profilId L'ID du profil à récupérer.
 * @return array|bool Un tableau associatif contenant les informations du profil si trouvé, false sinon.
 */
function getProfilParId(PDO $pdo, int $profilId): array|bool
{
    try {
        $stmt = $pdo->prepare("SELECT ID_Profil, Nom FROM Profils WHERE ID_Profil = :id");
        $stmt->bindParam(':id', $profilId, PDO::PARAM_INT);
        $stmt->execute();
        $profil = $stmt->fetch(PDO::FETCH_ASSOC);
        return $profil ?: false;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du profil avec l'ID $profilId : " . $e->getMessage());
        return false;
    }
}

// Vous pouvez ajouter d'autres fonctions spécifiques à la gestion des utilisateurs et des profils ici.

?>