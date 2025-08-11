<?php
// fonctions/plan_comptable/gestion_plan_comptable.php

/**
 * Ce fichier contient les fonctions backend pour la gestion du plan comptable.
 * Toutes les interactions avec la base de données pour les comptes comptables se font ici.
 * Utilise PDO pour la connexion à la base de données.
 */

// Inclure la connexion à la base de données.
// Ce fichier est censé initialiser la variable globale $pdo (objet PDO).
require_once(__DIR__ . '/../database.php');

/**
 * Récupère tous les comptes comptables de la base de données.
 * @return array Une liste de comptes ou un tableau vide en cas d'erreur ou d'absence de comptes.
 */
function get_all_comptes(): array {
    // Rendre la variable $pdo accessible dans le contexte de la fonction
    global $pdo; 
    $comptes = [];
    try {
        $sql = "SELECT Numero_Compte, Nom_Compte FROM Comptes ORDER BY Numero_Compte ASC";
        // Exécuter la requête directement avec PDO::query() pour les SELECT simples
        $stmt = $pdo->query($sql);
        // Récupérer tous les résultats sous forme de tableau associatif
        $comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération des comptes: " . $e->getMessage());
        // En cas d'erreur, retourner un tableau vide
        return [];
    }
    return $comptes;
}

/**
 * Récupère un compte comptable par son numéro.
 * @param string $numero Le numéro du compte à récupérer.
 * @return array|null Le compte comptable ou null s'il n'est pas trouvé.
 */
function get_compte_by_numero(string $numero): ?array {
    // Rendre la variable $pdo accessible
    global $pdo;
    $compte = null;
    try {
        $sql = "SELECT Numero_Compte, Nom_Compte FROM Comptes WHERE Numero_Compte = ?";
        // Préparer la requête pour éviter les injections SQL
        $stmt = $pdo->prepare($sql);
        // Exécuter la requête avec les paramètres
        $stmt->execute([$numero]);
        // Récupérer la première ligne de résultat
        $compte = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération d'un compte par numéro: " . $e->getMessage());
    }
    return $compte; // Retourne null si non trouvé ou en cas d'erreur
}

/**
 * Ajoute un nouveau compte comptable à la base de données.
 * @param string $numero Le numéro du nouveau compte.
 * @param string $libelle Le libellé du nouveau compte.
 * @return array Un tableau avec 'success' (bool) et 'message' (string).
 */
function ajouter_compte_comptable(string $numero, string $libelle): array {
    // Rendre la variable $pdo accessible
    global $pdo;
    try {
        // Optionnel: Vérifier si le compte existe déjà avant d'insérer (bonne pratique)
        $checkSql = "SELECT COUNT(*) FROM Comptes WHERE Numero_Compte = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$numero]);
        if ($checkStmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => "Le compte avec le numéro '{$numero}' existe déjà."];
        }

        $sql = "INSERT INTO Comptes (Numero_Compte, Nom_Compte) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$numero, $libelle]);
        return ['success' => true, 'message' => 'Compte ajouté avec succès.'];
    } catch (PDOException $e) {
        // Gérer les erreurs, y compris les contraintes d'unicité de la base de données
        return ['success' => false, 'message' => "Erreur lors de l'ajout du compte : " . $e->getMessage()];
    }
}

/**
 * Met à jour un compte comptable existant.
 * @param string $ancien_numero Le numéro de compte d'origine.
 * @param string $nouveau_numero Le nouveau numéro de compte.
 * @param string $libelle Le nouveau libellé.
 * @return array Un tableau avec 'success' (bool) et 'message' (string).
 */
function modifier_compte_comptable(string $ancien_numero, string $nouveau_numero, string $libelle): array {
    // Rendre la variable $pdo accessible
    global $pdo;
    try {
        // Optionnel: Vérifier si le nouveau numéro de compte est déjà utilisé par un autre compte
        if ($ancien_numero !== $nouveau_numero) {
            $checkSql = "SELECT COUNT(*) FROM Comptes WHERE Numero_Compte = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$nouveau_numero]);
            if ($checkStmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => "Le nouveau numéro de compte '{$nouveau_numero}' est déjà utilisé par un autre compte."];
            }
        }

        $sql = "UPDATE Comptes SET Numero_Compte = ?, Nom_Compte = ? WHERE Numero_Compte = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nouveau_numero, $libelle, $ancien_numero]);
        
        // rowCount() retourne le nombre de lignes affectées par la dernière requête
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Compte mis à jour avec succès.'];
        } else {
            // Si rowCount() est 0, c'est soit que le compte n'existe pas, soit qu'aucune modification n'a été apportée
            return ['success' => false, 'message' => "Aucun compte trouvé avec l'ancien numéro ou aucune modification effectuée."];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'message' => "Erreur lors de la mise à jour du compte : " . $e->getMessage()];
    }
}

/**
 * Supprime un compte comptable de la base de données.
 * @param string $numero Le numéro du compte à supprimer.
 * @return array Un tableau avec 'success' (bool) et 'message' (string).
 */
function supprimer_compte_comptable(string $numero): array {
    // Rendre la variable $pdo accessible
    global $pdo;
    try {
        // TRÈS IMPORTANT: Vérifier les dépendances avant de supprimer un compte.
        // Un compte ayant des écritures liées ne devrait pas être supprimé pour des raisons d'intégrité comptable.
        // L'ID_Compte dans Lignes_Ecritures doit correspondre à ID_Compte dans Comptes.
        $checkDependenciesSql = "SELECT COUNT(*) FROM Lignes_Ecritures WHERE ID_Compte IN (SELECT ID_Compte FROM Comptes WHERE Numero_Compte = ?)";
        $checkStmt = $pdo->prepare($checkDependenciesSql);
        $checkStmt->execute([$numero]);
        if ($checkStmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => "Impossible de supprimer le compte '{$numero}'. Il est lié à des écritures existantes."];
        }

        $sql = "DELETE FROM Comptes WHERE Numero_Compte = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$numero]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Compte supprimé avec succès.'];
        } else {
            return ['success' => false, 'message' => "Aucun compte trouvé avec le numéro '{$numero}' à supprimer."];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'message' => "Erreur lors de la suppression du compte : " . $e->getMessage()];
    }
}