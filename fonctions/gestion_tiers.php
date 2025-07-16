<?php

// fonctions/gestion_tiers.php

/**
 * Ajoute un nouveau tiers (client ou fournisseur) dans la base de données.
 *
 * @param PDO    $db                       Instance de connexion à la base de données.
 * @param string $typeTiers              Type de tiers ('Client' ou 'Fournisseur', requis).
 * @param string $nomCommercial          Nom commercial du tiers (requis).
 * @param string $nomLegal             Nom légal du tiers.
 * @param string $adresse                Adresse du tiers.
 * @param string $codePostal             Code postal du tiers.
 * @param string $ville                  Ville du tiers.
 * @param string $pays                   Pays du tiers.
 * @param string $numeroTelephone        Numéro de téléphone du tiers.
 * @param string $adresseEmail           Adresse email du tiers.
 * @param string $numeroIdentificationFiscale Numéro d'identification fiscale du tiers.
 * @param string $codeComptable          Code comptable du tiers.
 * @return int|bool L'ID du tiers inséré en cas de succès, false en cas d'erreur.
 */
 
function getIdTiersFromCompte(PDO $pdo, string $compteFournisseur): ?int
{
    try {
        $sql = "SELECT ID_Tiers FROM Tiers WHERE Code_Comptable = :compteFournisseur"; // Changed Numero_Compte to Code_Comptable
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':compteFournisseur', $compteFournisseur, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && isset($result['ID_Tiers'])) {
            return (int)$result['ID_Tiers'];
        } else {
            return null; // Aucun Tiers trouvé pour ce compte fournisseur
        }
    } catch (PDOException $e) {
        // Log de l'erreur
        error_log("Erreur lors de la récupération de l'ID Tiers pour le compte fournisseur $compteFournisseur : " . $e->getMessage());
        throw $e; // Relancer l'exception pour une gestion centralisée
    }
}
 
function ajouterTiers(
    PDO $pdo,
    string $typeTiers,
    string $nomCommercial,
    string $nomLegal = '',
    string $adresse = '',
    string $codePostal = '',
    string $ville = '',
    string $pays = '',
    string $numeroTelephone = '',
    string $adresseEmail = '',
    string $numeroIdentificationFiscale = '',
    string $codeComptable = ''
): int|bool {
    try {
        $stmt = $pdo->prepare("INSERT INTO Tiers (
            Type_Tiers, Nom_Commercial, Nom_Legal, Adresse, Code_Postal, Ville, Pays,
            Numero_Telephone, Adresse_Email, Numero_Identification_Fiscale, Code_Comptable
        ) VALUES (
            :type_tiers, :nom_commercial, :nom_legal, :adresse, :code_postal, :ville, :pays,
            :numero_telephone, :adresse_email, :nif, :code_comptable
        )");

        $stmt->bindParam(':type_tiers', $typeTiers);
        $stmt->bindParam(':nom_commercial', $nomCommercial);
        $stmt->bindParam(':nom_legal', $nomLegal);
        $stmt->bindParam(':adresse', $adresse);
        $stmt->bindParam(':code_postal', $codePostal);
        $stmt->bindParam(':ville', $ville);
        $stmt->bindParam(':pays', $pays);
        $stmt->bindParam(':numero_telephone', $numeroTelephone);
        $stmt->bindParam(':adresse_email', $adresseEmail);
        $stmt->bindParam(':nif', $numeroIdentificationFiscale);
        $stmt->bindParam(':code_comptable', $codeComptable);

        $stmt->execute();
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout du tiers ($typeTiers) : " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère la liste de tous les tiers (clients et fournisseurs) depuis la base de données.
 *
 * @param PDO $db Instance de connexion à la base de données.
 * @return array Un tableau associatif contenant les informations de chaque tiers, ou un tableau vide en cas d'erreur.
 */
function getListeTiers(PDO $pdo): array {
    try {
        $stmt = $pdo->query("SELECT ID_Tiers, Type_Tiers, Nom_Commercial, Nom_Legal, Adresse, Code_Postal, Ville, Pays, Numero_Telephone, Adresse_Email, Numero_Identification_Fiscale, Code_Comptable FROM Tiers ORDER BY Nom_Commercial");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la liste des tiers : " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère la liste des tiers d'un type spécifique (client ou fournisseur).
 *
 * @param PDO    $db        Instance de connexion à la base de données.
 * @param string $typeTiers Le type de tiers à récupérer ('Client' ou 'Fournisseur').
 * @return array Un tableau associatif contenant les informations des tiers du type spécifié, ou un tableau vide en cas d'erreur.
 */
function getListeTiersByType(PDO $pdo, string $typeTiers): array {
    try {
        $stmt = $db->prepare("SELECT ID_Tiers, Nom_Commercial, Nom_Legal, Adresse, Code_Postal, Ville, Pays, Numero_Telephone, Adresse_Email, Numero_Identification_Fiscale, Code_Comptable FROM Tiers WHERE Type_Tiers = :type_tiers ORDER BY Nom_Commercial");
        $stmt->bindParam(':type_tiers', $typeTiers);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la liste des tiers de type $typeTiers : " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les informations d'un tiers spécifique par son ID.
 *
 * @param PDO $db      Instance de connexion à la base de données.
 * @param int $idTiers L'ID du tiers à récupérer.
 * @return array|bool Un tableau associatif contenant les informations du tiers si trouvé, false sinon.
 */
function getTiersParId(PDO $pdo, int $idTiers): array|bool {
    try {
        $stmt = $db->prepare("SELECT ID_Tiers, Type_Tiers, Nom_Commercial, Nom_Legal, Adresse, Code_Postal, Ville, Pays, Numero_Telephone, Adresse_Email, Numero_Identification_Fiscale, Code_Comptable FROM Tiers WHERE ID_Tiers = :id");
        $stmt->bindParam(':id', $idTiers, PDO::PARAM_INT);
        $stmt->execute();
        $tiers = $stmt->fetch(PDO::FETCH_ASSOC);
        return $tiers ?: false;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du tiers avec l'ID $idTiers : " . $e->getMessage());
        return false;
    }
}

/**
 * Modifie les informations d'un tiers existant dans la base de données.
 *
 * @param PDO    $db                       Instance de connexion à la base de données.
 * @param int    $idTiers                  L'ID du tiers à modifier (requis).
 * @param string $nomCommercial          Nom commercial du tiers (requis).
 * @param string $nomLegal             Nom légal du tiers.
 * @param string $adresse                Adresse du tiers.
 * @param string $codePostal             Code postal du tiers.
 * @param string $ville                  Ville du tiers.
 * @param string $pays                   Pays du tiers.
 * @param string $numeroTelephone        Numéro de téléphone du tiers.
 * @param string $adresseEmail           Adresse email du tiers.
 * @param string $numeroIdentificationFiscale Numéro d'identification fiscale du tiers.
 * @param string $codeComptable          Code comptable du tiers.
 * @return bool True en cas de succès, false en cas d'erreur.
 */
function modifierTiers(
    PDO $db,
    int $idTiers,
    string $nomCommercial,
    string $nomLegal = '',
    string $adresse = '',
    string $codePostal = '',
    string $ville = '',
    string $pays = '',
    string $numeroTelephone = '',
    string $adresseEmail = '',
    string $numeroIdentificationFiscale = '',
    string $codeComptable = ''
): bool {
    try {
        $stmt = $pdo->prepare("UPDATE Tiers SET
            Nom_Commercial = :nom_commercial,
            Nom_Legal = :nom_legal,
            Adresse = :adresse,
            Code_Postal = :code_postal,
            Ville = :ville,
            Pays = :pays,
            Numero_Telephone = :numero_telephone,
            Adresse_Email = :adresse_email,
            Numero_Identification_Fiscale = :nif,
            Code_Comptable = :code_comptable
        WHERE ID_Tiers = :id");

        $stmt->bindParam(':id', $idTiers, PDO::PARAM_INT);
        $stmt->bindParam(':nom_commercial', $nomCommercial);
        $stmt->bindParam(':nom_legal', $nomLegal);
        $stmt->bindParam(':adresse', $adresse);
        $stmt->bindParam(':code_postal', $codePostal);
        $stmt->bindParam(':ville', $ville);
        $stmt->bindParam(':pays', $pays);
        $stmt->bindParam(':numero_telephone', $numeroTelephone);
        $stmt->bindParam(':adresse_email', $adresseEmail);
        $stmt->bindParam(':nif', $numeroIdentificationFiscale);
        $stmt->bindParam(':code_comptable', $codeComptable);

        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la modification du tiers avec l'ID $idTiers : " . $e->getMessage());
        return false;
    }
}

/**
 * Supprime un tiers de la base de données par son ID.
 *
 * @param PDO $db      Instance de connexion à la base de données.
 * @param int $idTiers L'ID du tiers à supprimer.
 * @return bool True en cas de succès, false en cas d'erreur.
 */
function supprimerTiers(PDO $pdo, int $idTiers): bool {
    try {
        $stmt = $pdo->prepare("DELETE FROM Tiers WHERE ID_Tiers = :id");
        $stmt->bindParam(':id', $idTiers, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression du tiers avec l'ID $idTiers : " . $e->getMessage());
        return false;
    }
}

// Vous pouvez ajouter d'autres fonctions spécifiques à la gestion des tiers ici,
// comme la recherche de tiers par nom, ville, etc.

?>