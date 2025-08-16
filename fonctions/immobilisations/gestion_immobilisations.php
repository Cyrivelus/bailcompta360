<?php
// fonctions/immobilisations/gestion_immobilisations.php

/**
 * Ce fichier contient les fonctions backend pour la gestion des immobilisations.
 * Il gère les interactions avec la table 'Immobilisations' de la base de données via PDO.
 */

// Inclure la connexion à la base de données.
// Ce fichier doit initialiser la variable globale $pdo (objet PDO).
require_once(__DIR__ . '/../database.php');

/**
 * Ajoute une nouvelle immobilisation à la base de données.
 * @param array $data Un tableau associatif contenant les données de l'immobilisation.
 * @return array Un tableau avec 'success' (bool) et 'message' (string).
 */
function ajouter_immobilisation(array $data): array {
    global $pdo;
    try {
        $sql = "INSERT INTO Immobilisations (Designation, Numero_Facture, Date_Acquisition, Montant_HT) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['designation'],
            $data['numero_facture'],
            $data['date_acquisition'],
            $data['montant_ht']
        ]);
        return ['success' => true, 'message' => 'Immobilisation ajoutée avec succès.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => "Erreur lors de l'ajout de l'immobilisation : " . $e->getMessage()];
    }
}

/**
 * Récupère toutes les immobilisations de la base de données.
 * @return array Une liste d'immobilisations ou un tableau vide en cas d'erreur.
 */
function get_all_immobilisations(): array {
    global $pdo;
    try {
        $sql = "SELECT * FROM Immobilisations ORDER BY Date_Acquisition DESC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération des immobilisations: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère une immobilisation par son ID.
 * @param int $id L'ID de l'immobilisation à récupérer.
 * @return array|null L'immobilisation ou null si elle n'est pas trouvée.
 */
function get_immobilisation_by_id(int $id): ?array {
    global $pdo;
    try {
        $sql = "SELECT * FROM Immobilisations WHERE ID_Immobilisation = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $immobilisation = $stmt->fetch(PDO::FETCH_ASSOC);
        return $immobilisation ?: null;
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération d'une immobilisation par ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Met à jour une immobilisation existante.
 * @param int $id L'ID de l'immobilisation à modifier.
 * @param array $data Un tableau associatif avec les nouvelles données.
 * @return array Un tableau avec 'success' (bool) et 'message' (string).
 */

function modifier_immobilisation(int $id, array $data): array {
    global $pdo;
    try {
        $sql = "UPDATE Immobilisations SET Designation = ?, Numero_Facture = ?, Date_Acquisition = ?, Montant_HT = ? WHERE ID_Immobilisation = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['designation'],
            $data['numero_facture'],
            $data['date_acquisition'],
            $data['montant_ht'],
            $id
        ]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Immobilisation mise à jour avec succès.'];
        } else {
            return ['success' => false, 'message' => "Aucune modification effectuée ou immobilisation non trouvée. Vérifiez que les données sont différentes de celles existantes."];
        }
    } catch (PDOException $e) {
        // Retourne le message d'erreur exact de la base de données pour un diagnostic facile
        return ['success' => false, 'message' => "Erreur de base de données : " . $e->getMessage()];
    }
}
/**
 * Supprime une immobilisation de la base de données.
 * @param int $id L'ID de l'immobilisation à supprimer.
 * @return array Un tableau avec 'success' (bool) et 'message' (string).
 */
function supprimer_immobilisation(int $id): array {
    global $pdo;
    try {
        $sql = "DELETE FROM Immobilisations WHERE ID_Immobilisation = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Immobilisation supprimée avec succès.'];
        } else {
            return ['success' => false, 'message' => "Aucune immobilisation trouvée avec cet ID."];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'message' => "Erreur lors de la suppression de l'immobilisation : " . $e->getMessage()];
    }
}

?>