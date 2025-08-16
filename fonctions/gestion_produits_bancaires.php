<?php
/**
 * Fichier de fonctions pour la gestion des produits bancaires (comptes, prêts).
 *
 * Ce module permet de définir les règles et les caractéristiques de chaque type de produit financier,
 * telles que les taux d'intérêt, les frais de gestion, les durées de prêts, etc.
 */

require_once 'database.php'; // Assurez-vous que le chemin est correct

/**
 * Crée un nouveau produit bancaire dans la base de données.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param array $data Les données du produit (Nom, Type, Taux, Frais, etc.).
 * @return bool Retourne true si la création a réussi, false sinon.
 * @throws Exception En cas d'erreur lors de l'insertion.
 */
function creerProduitBancaire(PDO $pdo, array $data): bool
{
    // Valider les données minimales
    if (empty($data['nom_produit']) || empty($data['type_produit'])) {
        throw new Exception("Le nom et le type du produit sont obligatoires.");
    }

    try {
        $pdo->beginTransaction();

        $query = "INSERT INTO Produits_Bancaires (
                      nom_produit,
                      type_produit,
                      taux_interet,
                      frais_gestion,
                      duree_max,
                      montant_min,
                      montant_max,
                      date_creation,
                      statut
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)";

        $stmt = $pdo->prepare($query);

        $params = [
            $data['nom_produit'],
            $data['type_produit'],
            $data['taux_interet'] ?? 0.0,
            $data['frais_gestion'] ?? 0.0,
            $data['duree_max'] ?? null,
            $data['montant_min'] ?? 0.0,
            $data['montant_max'] ?? 0.0,
            $data['statut'] ?? 'actif'
        ];

        $success = $stmt->execute($params);

        if (!$success) {
            throw new Exception("Échec de l'insertion du produit dans la base de données.");
        }

        $pdo->commit();
        return true;

    } catch (PDOException $e) {
        $pdo->rollBack();
        throw new Exception("Erreur de base de données : " . $e->getMessage());
    }
}

/**
 * Récupère les détails d'un produit bancaire par son ID.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id_produit L'identifiant du produit.
 * @return array|false Retourne les données du produit ou false si non trouvé.
 */
function getProduitBancaire(PDO $pdo, int $id_produit)
{
    $query = "SELECT * FROM Produits_Bancaires WHERE id_produit = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$id_produit]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Met à jour un produit bancaire existant.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id_produit L'identifiant du produit à mettre à jour.
 * @param array $data Les nouvelles données du produit.
 * @return bool Retourne true si la mise à jour a réussi, false sinon.
 * @throws Exception En cas d'erreur.
 */
function mettreAJourProduitBancaire(PDO $pdo, int $id_produit, array $data): bool
{
    if (empty($id_produit)) {
        throw new Exception("L'identifiant du produit est requis pour la mise à jour.");
    }

    try {
        $pdo->beginTransaction();

        $updates = [];
        $params = [];

        foreach ($data as $key => $value) {
            $updates[] = "$key = ?";
            $params[] = $value;
        }

        if (empty($updates)) {
            return true; // Rien à mettre à jour
        }

        $query = "UPDATE Produits_Bancaires SET " . implode(', ', $updates) . " WHERE id_produit = ?";
        $params[] = $id_produit;

        $stmt = $pdo->prepare($query);
        $success = $stmt->execute($params);

        if (!$success) {
            throw new Exception("Échec de la mise à jour du produit.");
        }

        $pdo->commit();
        return true;

    } catch (PDOException $e) {
        $pdo->rollBack();
        throw new Exception("Erreur de base de données : " . $e->getMessage());
    }
}

/**
 * Supprime un produit bancaire.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id_produit L'identifiant du produit à supprimer.
 * @return bool
 * @throws Exception
 */
function supprimerProduitBancaire(PDO $pdo, int $id_produit): bool
{
    try {
        $pdo->beginTransaction();

        // Vérifiez s'il y a des comptes ou des prêts existants liés à ce produit
        $checkQuery = "SELECT COUNT(*) FROM Comptes WHERE id_produit = ?";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute([$id_produit]);
        if ($checkStmt->fetchColumn() > 0) {
            throw new Exception("Impossible de supprimer ce produit car il est lié à des comptes ou des prêts existants.");
        }

        $query = "DELETE FROM Produits_Bancaires WHERE id_produit = ?";
        $stmt = $pdo->prepare($query);
        $success = $stmt->execute([$id_produit]);

        if (!$success) {
            throw new Exception("Échec de la suppression du produit.");
        }

        $pdo->commit();
        return true;

    } catch (PDOException $e) {
        $pdo->rollBack();
        throw new Exception("Erreur de base de données : " . $e->getMessage());
    }
}

/**
 * Récupère la liste de tous les produits bancaires.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @return array Retourne un tableau de produits.
 */
function listerProduitsBancaires(PDO $pdo): array
{
    $query = "SELECT * FROM Produits_Bancaires ORDER BY type_produit, nom_produit";
    $stmt = $pdo->query($query);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}