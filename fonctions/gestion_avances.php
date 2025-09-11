<?php
// fonctions/gestion_avances.php

/**
 * Fonctions de gestion des avances client.
 * Elles permettent d'interagir avec la table `avances_client`.
 */

require_once 'database.php'; // S'assure que la connexion $pdo est disponible.

/**
 * Récupère toutes les avances client de la base de données.
 *
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @return array Un tableau d'avances client ou un tableau vide en cas d'erreur ou d'absence de données.
 */
function getAvancesClient(PDO $pdo): array
{
    // Remarque : cette fonction est générale et ne filtre pas par client.
    $sql = "SELECT ac.*, c.nom_client AS nom, c.prenom_client AS prenom 
            FROM avances_client ac
            JOIN clients c ON ac.client_id = c.id_client
            ORDER BY ac.date_avance DESC";
    
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur de récupération des avances client : " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les avances pour un client spécifique.
 *
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @param int $clientId L'ID du client.
 * @return array Un tableau d'avances pour le client ou un tableau vide.
 */
function listerAvancesParClient(PDO $pdo, int $clientId): array
{
    $sql = "SELECT id_avance, montant_avance AS montant, date_avance AS date_emission, statut
            FROM avances_client 
            WHERE client_id = ? 
            ORDER BY date_avance DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur de récupération des avances par client : " . $e->getMessage());
        return [];
    }
}

/**
 * Ajoute une nouvelle avance client dans la base de données.
 *
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @param int $clientId L'ID du client associé.
 * @param float $montant Le montant de l'avance.
 * @param string $dateAvance La date de l'avance (format 'YYYY-MM-DD').
 * @param string $statut Le statut de l'avance.
 * @return bool Vrai si l'ajout a réussi, faux sinon.
 */
function ajouterAvanceClient(PDO $pdo, int $clientId, float $montant, string $dateAvance, string $statut): bool
{
    $sql = "INSERT INTO avances_client (client_id, montant_avance, date_avance, statut) VALUES (?, ?, ?, ?)";
    
    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$clientId, $montant, $dateAvance, $statut]);
    } catch (PDOException $e) {
        error_log("Erreur d'ajout d'une avance client : " . $e->getMessage());
        return false;
    }
}

/**
 * Met à jour une avance client existante.
 *
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @param int $idAvance L'ID de l'avance à mettre à jour.
 * @param int $clientId L'ID du client associé.
 * @param float $montant Le nouveau montant.
 * @param string $dateAvance La nouvelle date.
 * @param string $statut Le nouveau statut.
 * @return bool Vrai si la mise à jour a réussi, faux sinon.
 */
function modifierAvanceClient(PDO $pdo, int $idAvance, int $clientId, float $montant, string $dateAvance, string $statut): bool
{
    $sql = "UPDATE avances_client SET client_id = ?, montant_avance = ?, date_avance = ?, statut = ? WHERE id_avance = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$clientId, $montant, $dateAvance, $statut, $idAvance]);
    } catch (PDOException $e) {
        error_log("Erreur de modification d'une avance client : " . $e->getMessage());
        return false;
    }
}

/**
 * Supprime une avance client de la base de données.
 *
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @param int $idAvance L'ID de l'avance à supprimer.
 * @return bool Vrai si la suppression a réussi, faux sinon.
 */
function supprimerAvanceClient(PDO $pdo, int $idAvance): bool
{
    $sql = "DELETE FROM avances_client WHERE id_avance = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$idAvance]);
    } catch (PDOException $e) {
        error_log("Erreur de suppression d'une avance client : " . $e->getMessage());
        return false;
    }
}