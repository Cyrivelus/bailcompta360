<?php
// pages/ecritures/ajax_get_ecritures.php

require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_ecritures.php';

// Connexion à la base de données
$pdo = connect_db();
if (!$pdo) {
    echo json_encode(['error' => 'Erreur de connexion à la base de données.']);
    exit();
}

// Récupérer les paramètres de recherche et de tri
$searchTerm = $_GET['search'] ?? '';
$sortField = $_GET['sort'] ?? 'ID_Ecriture';
$sortOrder = strtoupper($_GET['order'] ?? 'DESC'); // 'ASC' ou 'DESC'

// Assurez-vous que les champs de tri sont valides pour éviter les injections SQL
$allowedSortFields = ['ID_Ecriture', 'Date_Saisie', 'Description', 'Montant_Total', 'Cde'];
if (!in_array($sortField, $allowedSortFields)) {
    $sortField = 'ID_Ecriture'; // Valeur par défaut si champ invalide
}
if (!in_array($sortOrder, ['ASC', 'DESC'])) {
    $sortOrder = 'DESC'; // Valeur par défaut si ordre invalide
}

try {
    // Récupérer les écritures avec la recherche et le tri
    $ecritures = getFilteredAndSortedEcritures($pdo, $searchTerm, $sortField, $sortOrder);
    echo json_encode($ecritures);
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des écritures via AJAX: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur lors du chargement des données.']);
}

// Fonction pour récupérer les écritures filtrées et triées (à placer dans gestion_ecritures.php)
// Vous devrez ajouter cette fonction dans votre fichier `fonctions/gestion_ecritures.php`
/*
function getFilteredAndSortedEcritures($pdo, $searchTerm = '', $sortField = 'ID_Ecriture', $sortOrder = 'DESC') {
    $sql = "SELECT ID_Ecriture, Date_Saisie, Description, Montant_Total, Cde
            FROM Ecritures_Comptables";

    $conditions = [];
    $params = [];

    if (!empty($searchTerm)) {
        $conditions[] = "(ID_Ecriture LIKE :search OR Description LIKE :search OR Cde LIKE :search)";
        $params[':search'] = '%' . $searchTerm . '%';
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }

    // Assurez-vous que $sortField est bien un nom de colonne valide pour éviter les injections SQL
    // (cela doit être géré dans ajax_get_ecritures.php avant d'appeler cette fonction)
    $sql .= " ORDER BY $sortField $sortOrder";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
*/

?>