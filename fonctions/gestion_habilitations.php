<?php
// fonctions/gestion_habilitations.php
// Fonctions pour la gestion des habilitations (permissions)

/**
 * Récupère toutes les habilitations depuis la base de données.
 *
 * @param PDO $db Instance de connexion à la base de données.
 * @return array Un tableau associatif contenant toutes les habilitations, ou un tableau vide en cas d'erreur.
 */
function getAllHabilitations(PDO $pdo) {
    try {
        $stmt = $pdo->query("SELECT id_habilitation, nom_habilitation, description FROM habilitations ORDER BY nom_habilitation");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des habilitations : " . $e->getMessage());
        return [];
    }
}

function getHabilitationsParUtilisateurId(PDO $pdo, int $utilisateurId): array
{
    try {
        $stmt = $pdo->prepare("SELECT Objet FROM Habilitations_Utilisateur WHERE ID_Utilisateur = :utilisateur_id");
        $stmt->bindParam(':utilisateur_id', $utilisateurId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des habilitations pour l'utilisateur {$utilisateurId} : " . $e->getMessage());
        return [];
    }
}

function getHabilitationsUtilisateur(PDO $pdo) {
        $sql = "SELECT
                    HU.ID_Habilitation_Utilisateur,
                    HU.ID_Utilisateur,
                    HU.Objet,
                    U.Nom AS Nom_Utilisateur,
                    U.Login_Utilisateur
                FROM Habilitations_Utilisateur AS HU
                LEFT JOIN Utilisateurs AS U ON HU.ID_Utilisateur = U.ID_Utilisateur
                ORDER BY U.Nom ASC, U.Login_Utilisateur ASC, HU.Objet ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

function getHabilitationsProfils(PDO $pdo) {
        $sql = "SELECT
                    HP.ID_Habilitation_Profil,
                    HP.ID_Profil,
                    HP.Objet,
                    P.Nom_Profil
                FROM Habilitations_Profil AS HP
                LEFT JOIN Profils AS P ON HP.ID_Profil = P.ID_Profil
                ORDER BY P.Nom_Profil ASC, HP.Objet ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

function getHabilitationsParProfilId(PDO $pdo, int $profilId): array
{
    try {
        $stmt = $pdo->prepare("SELECT Objet FROM Habilitations_Profil WHERE ID_Profil = :profil_id");
        $stmt->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des habilitations pour le profil {$profilId} : " . $e->getMessage());
        return [];
    }
}


/**
 * Récupère une habilitation spécifique par son ID.
 *
 * @param PDO $db Instance de connexion à la base de données.
 * @param int $habilitationId L'ID de l'habilitation à récupérer.
 * @return array|false Un tableau associatif contenant les informations de l'habilitation, ou false si non trouvée ou en cas d'erreur.
 */
function getHabilitationById(PDO $pdo, $habilitationId) {
    try {
        $stmt = $pdo->prepare("SELECT id_habilitation, nom_habilitation, description FROM habilitations WHERE id_habilitation = :id");
        $stmt->bindParam(':id', $habilitationId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de l'habilitation par ID : " . $e->getMessage());
        return false;
    }
}


function getHabilitationsProfilsAvecDetails(PDO $pdo): array {
    $sql = "SELECT hp.ID_Habilitation_Profil, hp.Objet, p.Nom_Profil 
            FROM Habilitations_Profil hp
            LEFT JOIN Profils p ON hp.ID_Profil = p.ID_Profil";

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



/**
 * Récupère toutes les habilitations par utilisateur avec le nom de l'utilisateur.
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @return array|false Un tableau d'habilitations utilisateur ou false en cas d'erreur.
 */
 
 function getPotentialPermissionObjects(): array {
    // Cette liste est définie manuellement en fonction de votre arborescence
    // et des actions/pages que vous souhaitez pouvoir permissionner.
    // Adaptez cette liste à vos besoins précis.
    return [
        'dashboard_voir',
        'admin_dashboard_voir',
        'admin_configuration_gerer',
        'admin_configuration_bd_voir',
        // 'admin_configuration_bd_modifier', // Si applicable
        'admin_configuration_logs_voir',
        'admin_configuration_parametre_modifier',
        'admin_habilitations_gerer',
        'admin_habilitations_ajouter',
        'admin_habilitations_modifier',
        'admin_habilitations_supprimer', // Action
        'admin_habilitations_details',
        'admin_profils_gerer',
        'admin_profils_ajouter',
        'admin_profils_modifier',
        'admin_profils_supprimer', // Action
        'admin_profils_assigner_utilisateurs',
        'admin_profils_dupliquer',
        'admin_utilisateurs_gerer',
        'admin_utilisateurs_ajouter',
        'admin_utilisateurs_modifier',
        'admin_utilisateurs_supprimer', // Action
        'admin_utilisateurs_modifier_role',
        'admin_utilisateurs_reinitialiser_mdp', // Action
        'admin_utilisateurs_deconnecter_force', // Action
        'aide_voir',
        'analyse_voir',
        'comptes_gerer',
        'comptes_liste_voir',
        'ecritures_gerer',
        'ecritures_liste_voir',
        'ecritures_saisie_ajouter',
        'ecritures_modifier',
        'ecritures_supprimer', // Action
        'ecritures_details_voir',
        'ecritures_valider_remboursement', // Action
        'emprunts_gerer',
        'emprunts_ajouter',
        'emprunts_modifier',
        'emprunts_supprimer', // Action
        'emprunts_details_voir',
        'emprunts_valider_remboursement', // Action
        'factures_gerer',
        'factures_liste_voir',
        'factures_integrer',
        'factures_modifier',
        'factures_supprimer', // Action
        'factures_details_voir',
        'generation_gerer',
        'generation_csv_generer', // Action
        'generation_fichier_telecharger', // Action
        'import_gerer',
        'import_fichier_traiter', // Action
        // Ajoutez d'autres objets si nécessaire
    ];
}

function getHabilitationsUtilisateursAvecDetails(PDO $pdo): array|false
{
    try {
        $sql = "SELECT
                    hu.ID_Habilitation_Utilisateur,
                    hu.Objet,
                    u.Nom -- Corrected column name: Use 'u.Nom' from the Utilisateurs table
                FROM
                    Habilitations_Utilisateur hu
                JOIN
                    Utilisateurs u ON hu.ID_Utilisateur = u.ID_Utilisateur";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des habilitations par utilisateur : " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère tous les profils disponibles.
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @return array|false Un tableau de profils ou false en cas d'erreur.
 */
function getAllProfils(PDO $pdo): array|false
{
    try {
        $sql = "SELECT ID_Profil, Nom_Profil FROM Profils ORDER BY Nom_Profil"; // Assurez-vous que la table Profils et les colonnes existent
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de tous les profils : " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère tous les utilisateurs disponibles.
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @return array|false Un tableau d'utilisateurs ou false en cas d'erreur.
 */
function getAllUtilisateurs(PDO $pdo): array|false
{
    try {
        // Corrected column name: Nom
        $sql = "SELECT ID_Utilisateur, Nom FROM Utilisateurs ORDER BY Nom"; // Use 'Nom' instead of 'Nom_Utilisateur'
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de tous les utilisateurs : " . $e->getMessage());
        return false;
    }
}


/**
 * Ajoute une nouvelle habilitation pour un profil.
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @param int $id_profil L'ID du profil.
 * @param string $objet L'objet (permission) à assigner.
 * @return bool True si l'insertion est réussie, false sinon.
 */
function addHabilitationProfil(PDO $pdo, int $id_profil, string $objet): bool
{
    try {
        $sql = "INSERT INTO Habilitations_Profil (ID_Profil, Objet) VALUES (:id_profil, :objet)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_profil', $id_profil, PDO::PARAM_INT);
        $stmt->bindParam(':objet', $objet, PDO::PARAM_STR);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout de l'habilitation pour le profil " . $id_profil . " : " . $e->getMessage());
        return false;
    }
}

/**
 * Ajoute une nouvelle habilitation pour un utilisateur spécifique.
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @param int $id_utilisateur L'ID de l'utilisateur.
 * @param string $objet L'objet (permission) à assigner.
 * @return bool True si l'insertion est réussie, false sinon.
 */
function addHabilitationUtilisateur(PDO $pdo, int $id_utilisateur, string $objet): bool
{
    try {
        $sql = "INSERT INTO Habilitations_Utilisateur (ID_Utilisateur, Objet) VALUES (:id_utilisateur, :objet)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_utilisateur', $id_utilisateur, PDO::PARAM_INT);
        $stmt->bindParam(':objet', $objet, PDO::PARAM_STR);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout de l'habilitation pour l'utilisateur " . $id_utilisateur . " : " . $e->getMessage());
        return false;
    }
}




/**
 * Récupère toutes les habilitations spécifiques par utilisateur avec le nom de l'utilisateur.
 *
 * NOTE : Vous devrez l'adapter pour qu'elle fonctionne avec votre table Utilisateurs.
 *
 * @return array Un tableau d'habilitations d'utilisateurs avec les détails, ou un tableau vide.
 */

/**
 * Récupère toutes les habilitations spécifiques par utilisateur avec le nom de l'utilisateur.
 *
 * NOTE : Cette fonction est un placeholder basé sur la structure de getHabilitationsProfilsAvecDetails.
 * Vous devrez l'adapter pour qu'elle fonctionne avec votre table Utilisateurs.
 *
 * @return array Un tableau d'habilitations d'utilisateurs avec les détails, ou un tableau vide.
 */

function ajouterHabilitation(PDO $pdo, $nomHabilitation, $description = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO habilitations (nom_habilitation, description) VALUES (:nom, :description)");
        $stmt->bindParam(':nom', $nomHabilitation);
        $stmt->bindParam(':description', $description);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout de l'habilitation : " . $e->getMessage());
        return false;
    }
}

/**
 * Modifie une habilitation existante.
 *
 * @param PDO $db Instance de connexion à la base de données.
 * @param int $habilitationId L'ID de l'habilitation à modifier.
 * @param string $nouveauNom Le nouveau nom de l'habilitation.
 * @param string $nouvelleDescription (Optionnel) La nouvelle description de l'habilitation.
 * @return bool True en cas de succès, false en cas d'erreur.
 */
function modifierHabilitation(PDO $pdo, $habilitationId, $nouveauNom, $nouvelleDescription = null) {
    try {
        $stmt = $pdo->prepare("UPDATE habilitations SET nom_habilitation = :nom, description = :description WHERE id_habilitation = :id");
        $stmt->bindParam(':nom', $nouveauNom);
        $stmt->bindParam(':description', $nouvelleDescription);
        $stmt->bindParam(':id', $habilitationId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la modification de l'habilitation : " . $e->getMessage());
        return false;
    }
}

/**
 * Supprime une habilitation.
 * **Attention :** Assurez-vous qu'aucune relation (par exemple, dans une table de liaison profils_habilitations) n'existe avant de supprimer.
 *
 * @param PDO $db Instance de connexion à la base de données.
 * @param int $habilitationId L'ID de l'habilitation à supprimer.
 * @return bool True en cas de succès, false en cas d'erreur.
 */
function supprimerHabilitation(PDO $pdo, $habilitationId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM habilitations WHERE id_habilitation = :id");
        $stmt->bindParam(':id', $habilitationId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression de l'habilitation : " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifie si un utilisateur (ou un profil) possède une habilitation spécifique.
 * **Note :** Cette fonction suppose que vous avez une table de liaison (par exemple, `profils_habilitations` ou `utilisateurs_habilitations`)
 * qui associe les ID des profils (ou utilisateurs) aux ID des habilitations. Adaptez la requête en conséquence.
 *
 * @param PDO $db Instance de connexion à la base de données.
 * @param int $profilId L'ID du profil à vérifier.
 * @param string $nomHabilitation Le nom de l'habilitation à rechercher.
 * @return bool True si le profil possède l'habilitation, false sinon.
 */
function profilAHabilitation(PDO $pdo, $profilId, $nomHabilitation) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM profils_habilitations ph
            INNER JOIN habilitations h ON ph.habilitation_id = h.id_habilitation
            WHERE ph.profil_id = :profil_id
            AND h.nom_habilitation = :nom_habilitation
        ");
        $stmt->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
        $stmt->bindParam(':nom_habilitation', $nomHabilitation);
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification de l'habilitation du profil : " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère toutes les habilitations associées à un profil spécifique.
 *
 * @param PDO $db Instance de connexion à la base de données.
 * @param int $profilId L'ID du profil.
 * @return array Un tableau associatif des habilitations associées au profil, ou un tableau vide en cas d'erreur.
 */
	
	


/**
 * Associe une habilitation à un profil.
 *
 * @param PDO $db Instance de connexion à la base de données.
 * @param int $profilId L'ID du profil.
 * @param int $habilitationId L'ID de l'habilitation à associer.
 * @return bool True en cas de succès, false en cas d'erreur (ou si l'association existe déjà).
 */
function associerHabilitationProfil(PDO $pdo, $profilId, $habilitationId) {
    try {
        // Vérifier si l'association existe déjà
        $stmtVerif = $db->prepare("SELECT COUNT(*) FROM profils_habilitations WHERE profil_id = :profil_id AND habilitation_id = :habilitation_id");
        $stmtVerif->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
        $stmtVerif->bindParam(':habilitation_id', $habilitationId, PDO::PARAM_INT);
        $stmtVerif->execute();
        if ($stmtVerif->fetchColumn() > 0) {
            return true; // L'association existe déjà, considérons cela comme un "succès" pour éviter les doublons
        }

        $stmtInsert = $pdo->prepare("INSERT INTO profils_habilitations (profil_id, habilitation_id) VALUES (:profil_id, :habilitation_id)");
        $stmtInsert->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
        $stmtInsert->bindParam(':habilitation_id', $habilitationId, PDO::PARAM_INT);
        return $stmtInsert->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de l'association de l'habilitation au profil : " . $e->getMessage());
        return false;
    }
}



/**
 * Dissocie une habilitation d'un profil.
 *
 * @param PDO $db Instance de connexion à la base de données.
 * @param int $profilId L'ID du profil.
 * @param int $habilitationId L'ID de l'habilitation à dissocier.
 * @return bool True en cas de succès, false en cas d'erreur.
 */
function dissociierHabilitationProfil(PDO $pdo, $profilId, $habilitationId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM profils_habilitations WHERE profil_id = :profil_id AND habilitation_id = :habilitation_id");
        $stmt->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
        $stmt->bindParam(':habilitation_id', $habilitationId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la dissociation de l'habilitation du profil : " . $e->getMessage());
        return false;
    }
}





// Vous pouvez ajouter d'autres fonctions de gestion des habilitations ici,
// par exemple pour gérer les habilitations directement au niveau des utilisateurs.

?>