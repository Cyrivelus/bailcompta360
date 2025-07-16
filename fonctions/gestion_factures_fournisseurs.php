<?php
// fonctions/gestion_factures_fournisseurs.php

require_once 'database.php'; // Assurez-vous que ce fichier contient la connexion à la base de données

/**
 * Récupère les informations d'un fournisseur à partir de son ID.
 *
 * @param PDO $pdo L'objet PDO représentant la connexion à la base de données.
 * @param int $fournisseurId L'ID du fournisseur à récupérer.
 * @return array|false Un tableau associatif contenant les informations du fournisseur, ou false si le fournisseur n'est pas trouvé ou en cas d'erreur.
 */
 function getInfosSaisieFactureFournisseur(PDO $pdo): array
{
    try {
        // Préparer la requête SQL pour récupérer les fournisseurs
        $sql = "SELECT ID_Tiers, Nom_Commercial FROM Tiers WHERE Type_Tiers = 'Fournisseur' ORDER BY Nom_Commercial";
        $stmt = $pdo->prepare($sql);

        // Exécuter la requête
        $stmt->execute();

        // Récupérer tous les fournisseurs sous forme de tableau associatif
        $fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Retourner les données
        return ['fournisseurs' => $fournisseurs];
    } catch (PDOException $e) {
        // En cas d'erreur, enregistrer l'erreur et retourner un tableau vide
        error_log("Erreur lors de la récupération des fournisseurs : " . $e->getMessage());
        return [];
    }
}
 
 
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

/**
 * Récupère toutes les factures des fournisseurs.
 *
 * @param PDO $pdo L'objet PDO représentant la connexion à la base de données.
 * @return array|false Un tableau contenant les informations de toutes les factures des fournisseurs,
 * ou false en cas d'erreur.
 */
function getFacturesFournisseurs(PDO $pdo): array|false
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
            t.Nom_Commercial  -- Jointure pour obtenir le nom du fournisseur
        FROM 
            Factures_Fournisseurs f
        JOIN 
            Tiers t ON f.Tiers_ID = t.ID_Tiers
        ORDER BY 
            f.Date_Emission DESC"; // Tri par date d'émission
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $factures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($factures === false) {
            error_log("Erreur lors de la récupération des factures des fournisseurs : " . print_r($stmt->errorInfo(), true));
            return false;
        }
        
        return $factures;
    } catch (PDOException $e) {
        error_log("Erreur PDO : " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les factures d'un fournisseur spécifique à partir de la base de données.
 *
 * @param PDO $pdo L'objet PDO représentant la connexion à la base de données.
 * @param int $fournisseurId L'ID du fournisseur dont on veut récupérer les factures.
 * @return array Un tableau contenant les informations des factures du fournisseur, ou un tableau vide si aucune facture n'est trouvée.
 * Retourne false en cas d'erreur.
 */
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

/**
 * Récupère les informations d'une facture fournisseur spécifique pour modification.
 *
 * @param PDO $pdo L'objet PDO représentant la connexion à la base de données.
 * @param int $idFacture L'ID de la facture à récupérer.
 * @return array|false Un tableau contenant les informations de la facture et la liste des fournisseurs,
 * ou false si la facture n'est pas trouvée ou en cas d'erreur.
 */
function getInfosModificationFactureFournisseur(PDO $pdo, int $idFacture): array|false {
    try {
        // Récupérer la facture
        $sqlFacture = "SELECT ID_Facture, Numero_Facture, Type_Facture, Date_Emission, Date_Reception, Date_Echeance, Tiers_ID, Montant_TTC, Statut_Facture
                       FROM Factures_Fournisseurs
                       WHERE ID_Facture = :idFacture";
        $stmtFacture = $pdo->prepare($sqlFacture);
        $stmtFacture->bindParam(':idFacture', $idFacture, PDO::PARAM_INT);
        $stmtFacture->execute();
        $facture = $stmtFacture->fetch(PDO::FETCH_ASSOC);

        if (!$facture) {
            error_log("Facture non trouvée : ID_Facture = $idFacture");
            return false;
        }

        // Récupérer les fournisseurs
        $sqlFournisseurs = "SELECT ID_Tiers, Nom_Commercial FROM Tiers";
        $stmtFournisseurs = $pdo->query($sqlFournisseurs);
        $fournisseurs = $stmtFournisseurs->fetchAll(PDO::FETCH_ASSOC);

        if (!$fournisseurs)
        {
             error_log("Erreur lors de la récupération des fournisseurs");
             return false;
        }

        return ['facture' => $facture, 'fournisseurs' => $fournisseurs];

    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération des informations de la facture : " . $e->getMessage());
        return false;
    }
}

/**
 * Modifie une facture fournisseur dans la base de données.
 *
 * @param PDO $pdo L'objet PDO représentant la connexion à la base de données.
 * @param int $idFacture L'ID de la facture à modifier.
 * @param string $numeroFacture Le nouveau numéro de facture.
 * @param string $typeFacture Le nouveau type de facture.
 * @param string $dateEmission La nouvelle date d'émission.
 * @param string $dateReception La nouvelle date de réception.
 * @param string $dateEcheance La nouvelle date d'échéance.
 * @param int $tiersId Le nouvel ID du fournisseur.
 * @param float $montantTTC Le nouveau montant TTC.
 * @param string $statutFacture Le nouveau statut de la facture.
 * @return bool True si la modification réussit, false sinon.
 */
function modifierFactureFournisseur(
    PDO $pdo,
    int $idFacture,
    string $numeroFacture,
    string $typeFacture,
    string $dateEmission,
    string $dateReception,
    string $dateEcheance,
    int $tiersId,
    float $montantTTC,
    string $statutFacture
): bool {
    try {
        $sql = "UPDATE Factures_Fournisseurs SET 
                    Numero_Facture = :numeroFacture,
                    Type_Facture = :typeFacture,
                    Date_Emission = :dateEmission,
                    Date_Reception = :dateReception,
                    Date_Echeance = :dateEcheance,
                    Tiers_ID = :tiersId,
                    Montant_TTC = :montantTTC,
                    Statut_Facture = :statutFacture
                WHERE ID_Facture = :idFacture";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':numeroFacture', $numeroFacture);
        $stmt->bindParam(':typeFacture', $typeFacture);
        $stmt->bindParam(':dateEmission', $dateEmission);
        $stmt->bindParam(':dateReception', $dateReception);
        $stmt->bindParam(':dateEcheance', $dateEcheance);
        $stmt->bindParam(':tiersId', $tiersId, PDO::PARAM_INT);
        $stmt->bindParam(':montantTTC', $montantTTC);
        $stmt->bindParam(':statutFacture', $statutFacture);
        $stmt->bindParam(':idFacture', $idFacture, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0; // Retourne true si au moins une ligne a été affectée
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la modification de la facture : " . $e->getMessage());
        return false;
    }
}

/**
 * Ajoute une nouvelle facture fournisseur à la base de données.
 *
 * @param PDO $pdo L'objet PDO représentant la connexion à la base de données.
 * @param string $numeroFacture
 * @param string $typeFacture
 * @param string $dateEmission
 * @param string $dateReception
 * @param string $dateEcheance
 * @param int $tiersId
 * @param float $montantTTC
 * @param string $statutFacture
 * @return bool|string Retourne l'ID de la nouvelle facture si l'ajout réussit, false sinon.
 */
function ajouterFactureFournisseur(
    PDO $pdo,
    string $numeroFacture,
    string $typeFacture,
    string $dateEmission,
    string $dateReception,
    string $dateEcheance,
    int $tiersId,
    float $montantTTC,
    string $statutFacture
): bool|string {
    try {
        $sql = "INSERT INTO Factures_Fournisseurs (Numero_Facture, Type_Facture, Date_Emission, Date_Reception, Date_Echeance, Tiers_ID, Montant_TTC, Statut_Facture)
                VALUES (:numeroFacture, :typeFacture, :dateEmission, :dateReception, :dateEcheance, :tiersId, :montantTTC, :statutFacture)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':numeroFacture', $numeroFacture);
        $stmt->bindParam(':typeFacture', $typeFacture);
        $stmt->bindParam(':dateEmission', $dateEmission);
        $stmt->bindParam(':dateReception', $dateReception);
        $stmt->bindParam(':dateEcheance', $dateEcheance);
        $stmt->bindParam(':tiersId', $tiersId, PDO::PARAM_INT);
        $stmt->bindParam(':montantTTC', $montantTTC);
        $stmt->bindParam(':statutFacture', $statutFacture);
        $stmt->execute();

        $idNouvelleFacture = $pdo->lastInsertId();
        if ($idNouvelleFacture) {
            return $idNouvelleFacture;
        } else {
            return false;
        }

    } catch (PDOException $e) {
        error_log("Erreur PDO lors de l'ajout de la facture : " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère le détail d'une facture fournisseur spécifique.
 *
 * @param PDO $pdo
 * @param int $idFacture
 * @return array|false
 */
function getDetailFactureFournisseur(PDO $pdo, int $idFacture): array|false
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
            t.ID_Tiers,
            t.Nom_Commercial,
            t.Nom_Legal,
            t.Adresse,
            t.Ville,
            t.Pays
        FROM 
            Factures_Fournisseurs f
        JOIN 
            Tiers t ON f.Tiers_ID = t.ID_Tiers
        WHERE 
            f.ID_Facture = :idFacture";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':idFacture', $idFacture, PDO::PARAM_INT);
        $stmt->execute();
        $facture = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$facture) {
            error_log("Facture fournisseur non trouvée (ID: $idFacture)");
            return false;
        }

        return $facture;

    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération du détail de la facture fournisseur : " . $e->getMessage());
        return false;
    }
}
?>
