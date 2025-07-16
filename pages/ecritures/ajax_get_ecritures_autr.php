<?php
// pages/ecritures/ajax_get_ecritures.php

require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_ecritures.php';

// Connexion � la base de donn�es
$pdo = connect_db();
if (!$pdo) {
    echo json_encode(['error' => 'Erreur de connexion � la base de donn�es.']);
    exit();
}

// R�cup�rer les param�tres de recherche et de tri
$searchTerm = $_GET['search'] ?? '';
$sortField = $_GET['sort'] ?? 'ID_Ecriture';
$sortOrder = strtoupper($_GET['order'] ?? 'DESC'); // 'ASC' ou 'DESC'

// Assurez-vous que les champs de tri sont valides pour �viter les injections SQL
$allowedSortFields = ['ID_Ecriture', 'Date_Saisie', 'Description', 'Montant_Total', 'Cde'];
if (!in_array($sortField, $allowedSortFields)) {
    $sortField = 'ID_Ecriture'; // Valeur par d�faut si champ invalide
}
if (!in_array($sortOrder, ['ASC', 'DESC'])) {
    $sortOrder = 'DESC'; // Valeur par d�faut si ordre invalide
}

try {
    // R�cup�rer les �critures avec la recherche et le tri
    $ecritures = getFilteredAndSortedEcritures($pdo, $searchTerm, $sortField, $sortOrder);
    echo json_encode($ecritures);
} catch (Exception $e) {
    error_log("Erreur lors de la r�cup�ration des �critures via AJAX: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur lors du chargement des donn�es.']);
}

// Fonction pour r�cup�rer les �critures filtr�es et tri�es (� placer dans gestion_ecritures.php)
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

    // Assurez-vous que $sortField est bien un nom de colonne valide pour �viter les injections SQL
    // (cela doit �tre g�r� dans ajax_get_ecritures.php avant d'appeler cette fonction)
    $sql .= " ORDER BY $sortField $sortOrder";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
*/

?>