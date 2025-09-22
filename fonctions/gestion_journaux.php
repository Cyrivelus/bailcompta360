<?php
// gestion_journaux.php
require_once 'database.php'; // Assurez-vous que le chemin est correct

/**
 * Récupère le nombre total de journaux depuis la base de données.
 */
function getNombreTotalJournaux(PDO $pdo, string $recherche = ''): int
{
    $sql = "SELECT COUNT(*) FROM jal WHERE 1=1";
    $params = [];

    if (!empty($recherche)) {
        $sql .= " AND (Lib LIKE :recherche OR Cde LIKE :recherche)";
        $params[':recherche'] = "%" . $recherche . "%";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn(); // Cast to int for type safety
}

/**
 * Récupère la liste des journaux avec pagination.
 */
function getJournaux(PDO $pdo, string $recherche = '', int $limit = 25, int $offset = 0): array
{
    $sql = "SELECT * FROM jal WHERE 1=1";
    $params = [];

    if (!empty($recherche)) {
        $sql .= " AND (Lib LIKE :recherche OR Cde LIKE :recherche)";
        $params[':recherche'] = "%" . $recherche . "%";
    }

    // Correction pour MySQL: Utiliser la syntaxe LIMIT
    $sql .= " ORDER BY Cde ASC LIMIT :limit OFFSET :offset";
    $params[':limit'] = $limit;
    $params[':offset'] = $offset;

    $stmt = $pdo->prepare($sql);

    // Utilisation de bindValue pour s'assurer que les types sont corrects
    foreach ($params as $key => &$val) {
        if ($key === ':limit' || $key === ':offset') {
            $stmt->bindValue($key, $val, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère la liste de tous les journaux.
 */
function getListeJournaux(PDO $pdo): array
{
    try {
        $sql = "SELECT Cde, Lib, Typ, Cpt, NumeroAgenceSCE FROM JAL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $journaux = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $journaux;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la liste des journaux : " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère la liste des fournisseurs.
 */
function getListeFournisseursPLN(PDO $pdo): array {
    // Note: 'implode' est correct, mais la requête pourrait être plus sécurisée avec des paramètres liés si les préfixes sont variables.
    $placeholders = rtrim(str_repeat('?,', count(PREFIXES_COMPTE_FOURNISSEUR)), ',');
    $query = "SELECT Cpt, Lib FROM PLN WHERE LEFT(Cpt, 3) IN ({$placeholders}) ORDER BY Cpt";
    $stmt = $pdo->prepare($query);
    if (!$stmt) {
        throw new Exception("Erreur lors de la préparation de la requête SQL pour récupérer les fournisseurs.");
    }
    // Bind des valeurs
    foreach (PREFIXES_COMPTE_FOURNISSEUR as $i => $prefix) {
        $stmt->bindValue($i + 1, $prefix);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les détails d'un journal spécifique par son code.
 */
function getJournal(PDO $pdo, int $codeJournal): ?array
{
    try {
        $sql = "SELECT Cde, Lib, Typ, Cpt, NumeroAgenceSCE FROM JAL WHERE Cde = :codeJournal";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':codeJournal', $codeJournal, PDO::PARAM_INT);
        $stmt->execute();
        $journal = $stmt->fetch(PDO::FETCH_ASSOC);
        return $journal ?: null;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du journal : " . $e->getMessage());
        return null;
    }
}

/**
 * Récupère un journal par son code, en s'assurant qu'il n'y a qu'un résultat.
 */
function getJournalByCde(PDO $pdo, int $cde)
{
    // Correction pour MySQL: Utiliser "LIMIT 1"
    $sql = "SELECT * FROM jal WHERE Cde = :cde LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':cde', $cde, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Insère un nouveau journal dans la base de données.
 */
function ajouterJournal(PDO $pdo, array $data): bool
{
    try {
        $sql = "INSERT INTO jal (Cde, Lib, Typ, Cpt, NumeroAgenceSCE) VALUES (:Cde, :Lib, :Typ, :Cpt, :NumeroAgenceSCE)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($data);
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout d'un journal : " . $e->getMessage());
        return false;
    }
}

/**
 * Modifie un journal existant.
 */
function modifierJournal(PDO $pdo, int $cde, array $data): bool
{
    try {
        $sql = "UPDATE jal SET Lib = :Lib, Typ = :Typ, Cpt = :Cpt, NumeroAgenceSCE = :NumeroAgenceSCE WHERE Cde = :cde";
        
        $data['cde'] = $cde; // Utilisez 'cde' au lieu de 'cde_initial' pour la cohérence
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($data);
    } catch (PDOException $e) {
        error_log("Erreur lors de la modification du journal : " . $e->getMessage());
        return false;
    }
}

/**
 * Supprime un journal de la base de données.
 */
function supprimerJournal(PDO $pdo, int $codeJournal): bool
{
    try {
        $sql = "DELETE FROM JAL WHERE Cde = :codeJournal";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':codeJournal', $codeJournal, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression du journal : " . $e->getMessage());
        return false;
    }
}