<?php
// fonctions/gestion_clients.php
// fonctions/clients/gestion_clients.php

require_once(__DIR__ . '/../database.php'); // Correct path

/**
 * Récupère la liste des clients sans pagination.
 * Cette fonction est utile pour les menus déroulants et les listes complètes.
 *
 * @param PDO $pdo L'objet de connexion à la base de données.
 * @return array Un tableau d'objets clients ou un tableau vide en cas d'erreur.
 */
function getAllClients(PDO $pdo): array {
    try {
        $stmt = $pdo->prepare("SELECT id_client, nom, prenoms, telephone, email, statut FROM clients ORDER BY nom ASC, prenoms ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Vous pouvez logger l'erreur avant de la relancer
        throw new Exception("Erreur lors de la récupération des clients : " . $e->getMessage());
    }
}

/**
 * Récupère les détails d'un client spécifique par son ID.
 *
 * @param PDO $pdo L'objet de connexion à la base de données.
 * @param int $id L'ID du client à récupérer.
 * @return array|bool Un tableau associatif contenant les détails du client, ou false si le client n'est pas trouvé.
 */
function trouverClientParId(PDO $pdo, int $id): array|bool
{
    $sql = "SELECT * FROM clients WHERE id_client = ?";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    } catch (PDOException $e) {
        error_log("Erreur de récupération d'un client : " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les détails d'un client par son nom ou son prénom.
 *
 * @param PDO $pdo L'objet de connexion à la base de données.
 * @param string $searchTerm Le nom ou prénom à rechercher.
 * @return array|bool Un tableau associatif contenant les détails du client,
 * ou false si le client n'est pas trouvé.
 */
function trouverClientParNomOuPrenom(PDO $pdo, string $searchTerm): array|bool
{
    $sql = "SELECT id_client, nom, prenoms, email, telephone FROM clients WHERE nom LIKE ? OR prenoms LIKE ?";
    try {
        $stmt = $pdo->prepare($sql);
        $searchPattern = '%' . $searchTerm . '%';
        $stmt->execute([$searchPattern, $searchPattern]);
        // Tente de récupérer le premier résultat.
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    } catch (PDOException $e) {
        error_log("Erreur de recherche d'un client : " . $e->getMessage());
        return false;
    }
}


// Note : La fonction `listerClients` et les autres fonctions de gestion de clients (ajouter, modifier, supprimer)
// ne sont pas incluses ici car votre code d'origine les avait déjà. Je vous fournis les fonctions clés
// pour le bon fonctionnement de la page `avance_client.php`.

?>