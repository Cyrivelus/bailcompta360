<?php
// gestion_journaux.php
require_once 'database.php'; // Assurez-vous que le chemin est correct

/**
 * Récupère la liste de tous les journaux depuis la base de données.
 *
 * @param PDO $pdo L'objet de connexion à la base de données.
 * @return array Un tableau associatif contenant les informations de chaque journal.
 * Retourne un tableau vide en cas d'erreur ou si aucun journal n'est trouvé.
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
        // Gestion de l'erreur : log, message à l'utilisateur, etc.
        error_log("Erreur lors de la récupération de la liste des journaux : " . $e->getMessage());
        return []; // Retourne un tableau vide pour indiquer une erreur
    }
}

function getListeFournisseursPLN(PDO $pdo): array {
    $query = "SELECT Cpt, Lib FROM PLN WHERE LEFT(Cpt, 3) IN ('" . implode("','", PREFIXES_COMPTE_FOURNISSEUR) . "') ORDER BY Cpt";
    $stmt = $pdo->prepare($query);
    if (!$stmt) {
        throw new Exception("Erreur lors de la préparation de la requête SQL pour récupérer les fournisseurs.");
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les détails d'un journal spécifique à partir de la base de données.
 *
 * @param PDO $pdo L'objet de connexion à la base de données.
 * @param int $codeJournal Le code du journal à récupérer.
 * @return array|null Un tableau associatif contenant les informations du journal, ou null si non trouvé.
 */
function getJournal(PDO $pdo, int $codeJournal): ?array
{
    try {
        $sql = "SELECT Cde, Lib, Typ, Cpt, NumeroAgenceSCE FROM JAL WHERE Cde = :codeJournal";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':codeJournal', $codeJournal, PDO::PARAM_INT);
        $stmt->execute();
        $journal = $stmt->fetch(PDO::FETCH_ASSOC);
        return $journal ?: null; // Retourne null si aucun résultat n'est trouvé
    } catch (PDOException $e) {
        // Gestion de l'erreur
        error_log("Erreur lors de la récupération du journal : " . $e->getMessage());
        return null;
    }
}

/**
 * Insère un nouveau journal dans la base de données.
 *
 * @param PDO $pdo L'objet de connexion à la base de données.
 * @param string $libelle Le libellé du journal.
 * @param string $type Le type d'écriture du journal.
 * @param string $compte Le compte associé au journal.
 * @param string $numeroAgenceSCE Le numéro d'agence SCE.
 * @return int|false Le code du journal inséré, ou false en cas d'erreur.
 */
function ajouterJournal(PDO $pdo, string $libelle, string $type, string $compte, string $numeroAgenceSCE): int|false
{
    try {
        $sql = "INSERT INTO JAL (Lib, Typ, Cpt, NumeroAgenceSCE) VALUES (:libelle, :type, :compte, :numeroAgenceSCE)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':libelle', $libelle, PDO::PARAM_STR);
        $stmt->bindParam(':type', $type, PDO::PARAM_STR);
        $stmt->bindParam(':compte', $compte, PDO::PARAM_STR);
        $stmt->bindParam(':numeroAgenceSCE', $numeroAgenceSCE, PDO::PARAM_STR);
        $stmt->execute();
        return $pdo->lastInsertId(); // Retourne le code du journal inséré
    } catch (PDOException $e) {
        // Gestion de l'erreur
        error_log("Erreur lors de l'ajout du journal : " . $e->getMessage());
        return false;
    }
}

/**
 * Modifie un journal existant dans la base de données.
 *
 * @param PDO $pdo L'objet de connexion à la base de données.
 * @param int $codeJournal Le code du journal à modifier.
 * @param string $libelle Le nouveau libellé du journal.
 * @param string $type Le nouveau type d'écriture du journal.
 * @param string $compte Le nouveau compte associé au journal.
 * @param string $numeroAgenceSCE Le nouveau numéro d'agence SCE.
 * @return bool True en cas de succès, false en cas d'erreur.
 */
function modifierJournal(PDO $pdo, int $codeJournal, string $libelle, string $type, string $compte, string $numeroAgenceSCE): bool
{
    try {
        $sql = "UPDATE JAL SET Lib = :libelle, Typ = :type, Cpt = :compte, NumeroAgenceSCE = :numeroAgenceSCE WHERE Cde = :codeJournal";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':codeJournal', $codeJournal, PDO::PARAM_INT);
        $stmt->bindParam(':libelle', $libelle, PDO::PARAM_STR);
        $stmt->bindParam(':type', $type, PDO::PARAM_STR);
        $stmt->bindParam(':compte', $compte, PDO::PARAM_STR);
        $stmt->bindParam(':numeroAgenceSCE', $numeroAgenceSCE, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount() > 0; // Retourne true si au moins une ligne a été affectée
    } catch (PDOException $e) {
        // Gestion de l'erreur
        error_log("Erreur lors de la modification du journal : " . $e->getMessage());
        return false;
    }
}

/**
 * Supprime un journal de la base de données.
 *
 * @param PDO $pdo L'objet de connexion à la base de données.
 * @param int $codeJournal Le code du journal à supprimer.
 * @return bool True en cas de succès, false en cas d'erreur.
 */
function supprimerJournal(PDO $pdo, int $codeJournal): bool
{
    try {
        $sql = "DELETE FROM JAL WHERE Cde = :codeJournal";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':codeJournal', $codeJournal, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0; // Retourne true si au moins une ligne a été affectée
    } catch (PDOException $e) {
        // Gestion de l'erreur
        error_log("Erreur lors de la suppression du journal : " . $e->getMessage());
        return false;
    }
}

?>
