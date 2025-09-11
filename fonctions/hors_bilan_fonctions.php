<?php
// fonctions/hors_bilan_fonctions.php

/**
 * Fonctions de gestion pour les avances client et les hypothèques.
 * Nécessite une connexion PDO active.
 */

// Inclure la configuration de la base de données.
require_once 'database.php';

/* ========================================================================= */
/* FONCTIONS AVANCES CLIENT                      */
/* ========================================================================= */

/**
 * Ajoute une nouvelle avance pour un client.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id_client L'ID du client.
 * @param float $montant Le montant de l'avance.
 * @param string $date La date de l'avance (format 'YYYY-MM-DD').
 * @param string $description Une description de l'avance.
 * @return bool Vrai si l'ajout a réussi.
 */
function ajouterAvanceClient(PDO $pdo, int $id_client, float $montant, string $date, string $description): bool
{
    $sql = "INSERT INTO avances_client (id_client, montant, date_avance, description, statut) 
            VALUES (:id_client, :montant, :date_avance, :description, 'en cours')";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_client', $id_client, PDO::PARAM_INT);
        $stmt->bindParam(':montant', $montant, PDO::PARAM_STR);
        $stmt->bindParam(':date_avance', $date, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur de base de données (ajouterAvanceClient) : " . $e->getMessage());
        throw new Exception("Impossible d'ajouter l'avance client.");
    }
}

/**
 * Récupère toutes les avances client, avec le nom et prénom du client.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @return array Un tableau d'avances client.
 */
function getAvancesClient(PDO $pdo): array
{
    $sql = "SELECT a.*, c.nom AS nom_client, c.prenom AS prenom_client
            FROM avances_client a
            JOIN clients c ON a.id_client = c.id_client
            ORDER BY a.date_avance DESC";

    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur de base de données (getAvancesClient) : " . $e->getMessage());
        throw new Exception("Impossible de récupérer la liste des avances client.");
    }
}

/**
 * Met à jour une avance client existante.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id_avance L'ID de l'avance à modifier.
 * @param array $donnees Les nouvelles données de l'avance (montant, date, description, statut).
 * @return bool Vrai si la mise à jour a réussi.
 */
function modifierAvanceClient(PDO $pdo, int $id_avance, array $donnees): bool
{
    $sql = "UPDATE avances_client 
            SET montant = :montant, date_avance = :date_avance, description = :description, statut = :statut
            WHERE id_avance = :id_avance";
            
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_avance', $id_avance, PDO::PARAM_INT);
        $stmt->bindParam(':montant', $donnees['montant'], PDO::PARAM_STR);
        $stmt->bindParam(':date_avance', $donnees['date_avance'], PDO::PARAM_STR);
        $stmt->bindParam(':description', $donnees['description'], PDO::PARAM_STR);
        $stmt->bindParam(':statut', $donnees['statut'], PDO::PARAM_STR);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur de base de données (modifierAvanceClient) : " . $e->getMessage());
        throw new Exception("Impossible de modifier l'avance client.");
    }
}

/**
 * Supprime une avance client.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id_avance L'ID de l'avance à supprimer.
 * @return bool Vrai si la suppression a réussi.
 */
function supprimerAvanceClient(PDO $pdo, int $id_avance): bool
{
    $sql = "DELETE FROM avances_client WHERE id_avance = :id_avance";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_avance', $id_avance, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erreur de base de données (supprimerAvanceClient) : " . $e->getMessage());
        throw new Exception("Impossible de supprimer l'avance client.");
    }
}

/* ========================================================================= */
/* FONCTIONS HYPOTHEQUES                        */
/* ========================================================================= */

/**
 * Ajoute une nouvelle hypothèque.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id_client L'ID du client.
 * @param float $valeur_bien La valeur du bien.
 * @param float $montant_emprunte Le montant emprunté.
 * @param string $date_emprunt La date de l'emprunt.
 * @param int $duree_emprunt La durée de l'emprunt en mois ou années.
 * @param string $description Une description de l'hypothèque.
 * @return bool Vrai si l'ajout a réussi.
 */
function ajouterHypotheque(PDO $pdo, int $id_client, float $valeur_bien, float $montant_emprunte, string $date_emprunt, int $duree_emprunt, string $description): bool
{
    $sql = "INSERT INTO hypotheques (id_client, valeur_bien, montant_emprunte, date_emprunt, duree_emprunt, statut, description) 
            VALUES (:id_client, :valeur_bien, :montant_emprunte, :date_emprunt, :duree_emprunt, 'actif', :description)";
            
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_client', $id_client, PDO::PARAM_INT);
        $stmt->bindParam(':valeur_bien', $valeur_bien, PDO::PARAM_STR);
        $stmt->bindParam(':montant_emprunte', $montant_emprunte, PDO::PARAM_STR);
        $stmt->bindParam(':date_emprunt', $date_emprunt, PDO::PARAM_STR);
        $stmt->bindParam(':duree_emprunt', $duree_emprunt, PDO::PARAM_INT);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur de base de données (ajouterHypotheque) : " . $e->getMessage());
        throw new Exception("Impossible d'ajouter l'hypothèque.");
    }
}

/**
 * Récupère toutes les hypothèques, avec le nom et prénom du client.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @return array Un tableau d'hypothèques.
 */
function getHypotheques(PDO $pdo): array
{
    $sql = "SELECT h.*, c.nom AS nom_client, c.prenom AS prenom_client
            FROM hypotheques h
            JOIN clients c ON h.id_client = c.id_client
            ORDER BY h.date_emprunt DESC";
            
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur de base de données (getHypotheques) : " . $e->getMessage());
        throw new Exception("Impossible de récupérer la liste des hypothèques.");
    }
}

/**
 * Met à jour une hypothèque existante.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id_hypotheque L'ID de l'hypothèque à modifier.
 * @param array $donnees Les nouvelles données de l'hypothèque.
 * @return bool Vrai si la mise à jour a réussi.
 */
function modifierHypotheque(PDO $pdo, int $id_hypotheque, array $donnees): bool
{
    $sql = "UPDATE hypotheques 
            SET valeur_bien = :valeur_bien, montant_emprunte = :montant_emprunte, date_emprunt = :date_emprunt, 
                duree_emprunt = :duree_emprunt, statut = :statut, description = :description
            WHERE id_hypotheque = :id_hypotheque";
            
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_hypotheque', $id_hypotheque, PDO::PARAM_INT);
        $stmt->bindParam(':valeur_bien', $donnees['valeur_bien'], PDO::PARAM_STR);
        $stmt->bindParam(':montant_emprunte', $donnees['montant_emprunte'], PDO::PARAM_STR);
        $stmt->bindParam(':date_emprunt', $donnees['date_emprunt'], PDO::PARAM_STR);
        $stmt->bindParam(':duree_emprunt', $donnees['duree_emprunt'], PDO::PARAM_INT);
        $stmt->bindParam(':statut', $donnees['statut'], PDO::PARAM_STR);
        $stmt->bindParam(':description', $donnees['description'], PDO::PARAM_STR);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur de base de données (modifierHypotheque) : " . $e->getMessage());
        throw new Exception("Impossible de modifier l'hypothèque.");
    }
}

/**
 * Supprime une hypothèque.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id_hypotheque L'ID de l'hypothèque à supprimer.
 * @return bool Vrai si la suppression a réussi.
 */
function supprimerHypotheque(PDO $pdo, int $id_hypotheque): bool
{
    $sql = "DELETE FROM hypotheques WHERE id_hypotheque = :id_hypotheque";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_hypotheque', $id_hypotheque, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erreur de base de données (supprimerHypotheque) : " . $e->getMessage());
        throw new Exception("Impossible de supprimer l'hypothèque.");
    }
}