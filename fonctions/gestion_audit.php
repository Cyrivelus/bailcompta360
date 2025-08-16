<?php
// fonctions/gestion_audit.php

/**
 * Ce fichier gère la journalisation des actions importantes
 * des utilisateurs pour des besoins d'audit et de traçabilité.
 */

require_once 'database.php'; // Pour la connexion PDO

/**
 * Enregistre une action de l'utilisateur dans la table des logs d'audit.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id_utilisateur L'ID de l'utilisateur qui a effectué l'action.
 * @param string $action Une description claire de l'action effectuée.
 * @param string $details Des détails supplémentaires sur l'action (par exemple, les valeurs modifiées).
 * @return bool True si l'enregistrement a réussi, false sinon.
 */
function getNombreTotalLogs(PDO $pdo): int {
    $stmt = $pdo->query("SELECT COUNT(*) FROM Logs_Audit");
    return $stmt->fetchColumn();
}

/**
 * Récupère les logs d'audit avec pagination.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $limit Le nombre de logs par page.
 * @param int $offset Le décalage.
 * @return array Un tableau contenant les logs.
 */
function getLogsAudit(PDO $pdo, int $limit, int $offset): array {
    // Assurez-vous que votre table d'utilisateurs s'appelle bien Utilisateurs
    // et que le nom de la colonne est nom_utilisateur ou autre.
    $sql = "SELECT LA.ID_Log, LA.Date_Heure_Action, LA.Action, LA.Details, U.Nom
            FROM Logs_Audit AS LA
            LEFT JOIN Utilisateurs AS U ON LA.ID_Utilisateur = U.ID_Utilisateur
            ORDER BY LA.Date_Heure_Action DESC
            LIMIT :limit OFFSET :offset";
            
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function enregistrerLogAudit(PDO $pdo, int $id_utilisateur, string $action, string $details = ''): bool
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO Logs_Audit (ID_Utilisateur, Action, Details, Date_Heure_Action)
            VALUES (:id_utilisateur, :action, :details, NOW())
        ");
        $stmt->bindParam(':id_utilisateur', $id_utilisateur, PDO::PARAM_INT);
        $stmt->bindParam(':action', $action, PDO::PARAM_STR);
        $stmt->bindParam(':details', $details, PDO::PARAM_STR);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        // En cas d'erreur, on la logue mais on ne bloque pas l'exécution de l'application
        error_log("Erreur lors de l'enregistrement du log d'audit: " . $e->getMessage());
        return false;
    }
}

/**
 * Exemples de fonctions d'aide pour des actions spécifiques.
 */
function logConnexionReussie(PDO $pdo, int $id_utilisateur)
{
    enregistrerLogAudit($pdo, $id_utilisateur, 'Connexion réussie');
}

function logDeconnexion(PDO $pdo, int $id_utilisateur)
{
    enregistrerLogAudit($pdo, $id_utilisateur, 'Déconnexion');
}

function logModificationCompteClient(PDO $pdo, int $id_utilisateur, int $id_compte_modifie, string $anciennes_valeurs, string $nouvelles_valeurs)
{
    $action = "Modification du compte client ID " . $id_compte_modifie;
    $details = "Anciennes valeurs : " . $anciennes_valeurs . " | Nouvelles valeurs : " . $nouvelles_valeurs;
    enregistrerLogAudit($pdo, $id_utilisateur, $action, $details);
}