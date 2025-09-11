<?php
// fonctions/gestion_hypotheques.php

/**
 * Fonctions de gestion des hypothèques.
 * Elles permettent d'interagir avec la table `hypotheques`.
 */

require_once 'database.php'; // S'assure que la connexion $pdo est disponible.

/**
 * Récupère toutes les hypothèques de la base de données.
 *
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @return array Un tableau d'hypothèques ou un tableau vide en cas d'erreur ou d'absence de données.
 */
function getHypotheques(PDO $pdo): array
{
    $sql = "SELECT h.*, c.nom_client, c.prenom_client 
            FROM hypotheques h
            JOIN clients c ON h.client_id = c.id_client
            ORDER BY h.date_creation DESC";
    
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Enregistrement de l'erreur pour le débogage.
        error_log("Erreur de récupération des hypothèques : " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les hypothèques pour un client spécifique.
 *
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @param int $clientId L'ID du client.
 * @return array Un tableau d'hypothèques pour le client ou un tableau vide.
 */
function listerHypothequesParClient(PDO $pdo, int $clientId): array
{
    $sql = "SELECT id_hypotheque, montant_principal, date_creation, statut
            FROM hypotheques 
            WHERE client_id = ? 
            ORDER BY date_creation DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur de récupération des hypothèques par client : " . $e->getMessage());
        return [];
    }
}

/**
 * Ajoute une nouvelle hypothèque dans la base de données.
 *
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @param int $clientId L'ID du client associé.
 * @param float $montant Le montant principal de l'hypothèque.
 * @param string $dateCreation La date de création de l'hypothèque (format 'YYYY-MM-DD').
 * @param string $statut Le statut de l'hypothèque.
 * @return bool Vrai si l'ajout a réussi, faux sinon.
 */
function ajouterHypotheque(PDO $pdo, int $clientId, float $montant, string $dateCreation, string $statut): bool
{
    $sql = "INSERT INTO hypotheques (client_id, montant_principal, date_creation, statut) VALUES (?, ?, ?, ?)";
    
    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$clientId, $montant, $dateCreation, $statut]);
    } catch (PDOException $e) {
        error_log("Erreur d'ajout d'une hypothèque : " . $e->getMessage());
        return false;
    }
}

/**
 * Met à jour une hypothèque existante.
 *
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @param int $idHypotheque L'ID de l'hypothèque à mettre à jour.
 * @param int $clientId L'ID du client associé.
 * @param float $montant Le nouveau montant.
 * @param string $dateCreation La nouvelle date de création.
 * @param string $statut Le nouveau statut.
 * @return bool Vrai si la mise à jour a réussi, faux sinon.
 */
function modifierHypotheque(PDO $pdo, int $idHypotheque, int $clientId, float $montant, string $dateCreation, string $statut): bool
{
    $sql = "UPDATE hypotheques SET client_id = ?, montant_principal = ?, date_creation = ?, statut = ? WHERE id_hypotheque = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$clientId, $montant, $dateCreation, $statut, $idHypotheque]);
    } catch (PDOException $e) {
        error_log("Erreur de modification d'une hypothèque : " . $e->getMessage());
        return false;
    }
}

/**
 * Supprime une hypothèque de la base de données.
 *
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @param int $idHypotheque L'ID de l'hypothèque à supprimer.
 * @return bool Vrai si la suppression a réussi, faux sinon.
 */
function supprimerHypotheque(PDO $pdo, int $idHypotheque): bool
{
    $sql = "DELETE FROM hypotheques WHERE id_hypotheque = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$idHypotheque]);
    } catch (PDOException $e) {
        error_log("Erreur de suppression d'une hypothèque : " . $e->getMessage());
        return false;
    }
}