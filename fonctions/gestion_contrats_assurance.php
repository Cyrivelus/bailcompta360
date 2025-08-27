<?php
// fonctions/gestion_contrats_assurance.php

/**
 * Récupère tous les contrats d'assurance associés à un client donné.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $idClient L'ID du client.
 * @return array Un tableau d'objets ou de tableaux associatifs représentant les contrats.
 */
function getContratsAssuranceByClientId(PDO $pdo, int $idClient): array
{
    try {
        $sql = "SELECT * FROM contrats_assurance WHERE ID_Client = :id_client ORDER BY Date_Debut DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id_client', $idClient, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des contrats d'assurance : " . $e->getMessage());
        return [];
    }
}