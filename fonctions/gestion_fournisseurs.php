<?php

// fonctions/gestion_fournisseurs.php

/**
 * Ajoute un nouveau fournisseur dans la base de données.
 *
 * @param PDO   $db                       Instance de connexion à la base de données.
 * @param string $nomCommercial          Nom commercial du fournisseur (requis).
 * @param string $nomLegal             Nom légal du fournisseur.
 * @param string $adresse                Adresse du fournisseur.
 * @param string $codePostal             Code postal du fournisseur.
 * @param string $ville                  Ville du fournisseur.
 * @param string $pays                   Pays du fournisseur.
 * @param string $numeroTelephone        Numéro de téléphone du fournisseur.
 * @param string $adresseEmail           Adresse email du fournisseur.
 * @param string $numeroIdentificationFiscale Numéro d'identification fiscale du fournisseur.
 * @param string $codeComptable          Code comptable du fournisseur.
 * @return int|bool L'ID du fournisseur inséré en cas de succès, false en cas d'erreur.
 */
 
 function getComptesFournisseursPLN(PDO $pdo, string $prefix = '401'): array {
    try {
        // Sélectionne le numéro de compte (Cpt) et le libellé (Lib)
        // Filtre les comptes commençant par le préfixe fourni
        // ATTENTION: Adaptez le critère WHERE si nécessaire (ex: utiliser la colonne Aux, Sns, etc.)
        $sql = "SELECT Cpt, Lib FROM PLN WHERE Cpt LIKE :prefix ORDER BY Lib ASC";
        $stmt = $pdo->prepare($sql);
        // Le '%' est ajouté ici pour le LIKE
        $stmt->bindValue(':prefix', $prefix . '%');

        if ($stmt->execute()) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            throw new Exception("Erreur lors de la récupération des comptes fournisseurs PLN : " . implode(" - ", $stmt->errorInfo()));
        }
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getComptesFournisseursPLN: " . $e->getMessage());
        throw new Exception("Erreur de base de données lors de la récupération des comptes fournisseurs.");
    } catch (Exception $e) {
        error_log("Erreur générale dans getComptesFournisseursPLN: " . $e->getMessage());
        throw $e;
    }
}
 
 
 function getFacturesFournisseur(PDO $pdo, int $fournisseurId): array|false
{
    try {
        $sql = "SELECT 
            f.ID_Facture,
            f.Numero_Facture,
            f.Type_Facture,
            f.Date_Emission,
            f.Date_Reception,
            f.Date_Echeance,
            f.Montant_TTC,
            f.Statut_Facture,
            t.Nom_Commercial
        FROM 
            Factures_Fournisseurs f
        JOIN 
            Tiers t ON f.Tiers_ID = t.ID_Tiers
        WHERE 
            f.Tiers_ID = :fournisseurId
        ORDER BY 
            f.Date_Emission DESC"; // Tri par date d'émission, plus récent en premier

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':fournisseurId', $fournisseurId, PDO::PARAM_INT);
        $stmt->execute();
        $factures = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($factures === false) {
            error_log("Erreur lors de la récupération des factures du fournisseur : " . print_r($stmt->errorInfo(), true));
            return false; // Retourne false en cas d'erreur d'exécution de la requête
        }

        return $factures;
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération des factures du fournisseur : " . $e->getMessage());
        return false; // Retourne false en cas d'exception PDO
    }
}

function ajouterFournisseur(
    PDO $pdo,
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

        $typeTiers = 'Fournisseur';
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
        error_log("Erreur lors de l'ajout du fournisseur : " . $e->getMessage());
        return false;
    }
}


/**
 * Récupère la liste de tous les fournisseurs depuis la base de données.
 *
 * @param PDO $db Instance de connexion à la base de données.
 * @return array Un tableau associatif contenant les informations de chaque fournisseur, ou un tableau vide en cas d'erreur.
 */
/**
 * Récupère les informations d'un fournisseur spécifique par son ID.
 *
 * @param PDO $db Instance de connexion à la base de données.
 * @param int $idFournisseur L'ID du fournisseur à récupérer.
 * @return array|bool Un tableau associatif contenant les informations du fournisseur si trouvé, false sinon.
 */
 

 
 function getFournisseur(PDO $pdo, int $fournisseurId): array|false
{
    try {
        $sql = "SELECT 
                    ID_Tiers,
                    Nom_Commercial,
                    Nom_Legal,
                    Adresse,
                    Ville,
                    Pays
                FROM 
                    Tiers
                WHERE 
                    ID_Tiers = :fournisseurId";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':fournisseurId', $fournisseurId, PDO::PARAM_INT);
        $stmt->execute();
        $fournisseur = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($fournisseur === false) {
            error_log("Erreur lors de la récupération du fournisseur : " . print_r($stmt->errorInfo(), true));
            return false; // Retourne false si aucune ligne n'est trouvée ou en cas d'erreur d'exécution
        }

        return $fournisseur;
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération du fournisseur : " . $e->getMessage());
        return false; // Retourne false en cas d'exception PDO
    }
}

/**
 * Récupère la liste de tous les fournisseurs depuis la base de données.
 *
 * @param PDO $pdo L'objet PDO représentant la connexion à la base de données.
 * @return array Un tableau contenant les informations de tous les fournisseurs, ou un tableau vide si aucun fournisseur n'est trouvé.
 * Retourne false en cas d'erreur.
 */
function getListeFournisseurs(PDO $pdo): array|false
{
    try {
        $sql = "SELECT 
                    ID_Tiers,
                    Nom_Commercial,
                    Nom_Legal,
                    Adresse,
                    Ville,
                    Pays
                FROM 
                    Tiers
                ORDER BY 
                    Nom_Commercial"; // Tri alphabétique par nom commercial

        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            error_log("Erreur lors de la récupération de la liste des fournisseurs : " . print_r($pdo->errorInfo(), true));
            return false;
        }
        $fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $fournisseurs;
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération de la liste des fournisseurs : " . $e->getMessage());
        return false;
    }
}



function getFournisseurParId(PDO $pdo, int $idFournisseur): array|bool {
    try {
        $stmt = $pdo->prepare("SELECT ID_Tiers, Nom_Commercial, Nom_Legal, Adresse, Code_Postal, Ville, Pays, Numero_Telephone, Adresse_Email, Numero_Identification_Fiscale, Code_Comptable FROM Tiers WHERE ID_Tiers = :id AND Type_Tiers = 'Fournisseur'");
        $stmt->bindParam(':id', $idFournisseur, PDO::PARAM_INT);
        $stmt->execute();
        $fournisseur = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fournisseur ?: false;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du fournisseur avec l'ID $idFournisseur : " . $e->getMessage());
        return false;
    }
}

/**
 * Modifie les informations d'un fournisseur existant dans la base de données.
 *
 * @param PDO   $pdo                       Instance de connexion à la base de données.
 * @param int   $idFournisseur            L'ID du fournisseur à modifier (requis).
 * @param string $nomCommercial          Nom commercial du fournisseur (requis).
 * @param string $nomLegal             Nom légal du fournisseur.
 * @param string $adresse                Adresse du fournisseur.
 * @param string $codePostal             Code postal du fournisseur.
 * @param string $ville                  Ville du fournisseur.
 * @param string $pays                   Pays du fournisseur.
 * @param string $numeroTelephone        Numéro de téléphone du fournisseur.
 * @param string $adresseEmail           Adresse email du fournisseur.
 * @param string $numeroIdentificationFiscale Numéro d'identification fiscale du fournisseur.
 * @param string $codeComptable          Code comptable du fournisseur.
 * @return bool True en cas de succès, false en cas d'erreur.
 */
function modifierFournisseur(
    PDO $pdo,
    int $idFournisseur,
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
        WHERE ID_Tiers = :id AND Type_Tiers = 'Fournisseur'");

        $stmt->bindParam(':id', $idFournisseur, PDO::PARAM_INT);
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
        error_log("Erreur lors de la modification du fournisseur avec l'ID $idFournisseur : " . $e->getMessage());
        return false;
    }
}

/**
 * Supprime un fournisseur de la base de données par son ID.
 *
 * @param PDO $pdo Instance de connexion à la base de données.
 * @param int $idFournisseur L'ID du fournisseur à supprimer.
 * @return bool True en cas de succès, false en cas d'erreur.
 */
function supprimerFournisseur(PDO $pdo, int $idFournisseur): bool {
    try {
        $stmt = $pdo->prepare("DELETE FROM Tiers WHERE ID_Tiers = :id AND Type_Tiers = 'Fournisseur'");
        $stmt->bindParam(':id', $idFournisseur, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression du fournisseur avec l'ID $idFournisseur : " . $e->getMessage());
        return false;
    }
}

// Vous pouvez ajouter d'autres fonctions spécifiques à la gestion des fournisseurs ici,
// comme la récupération des fournisseurs par ville, par pays, etc.

?>