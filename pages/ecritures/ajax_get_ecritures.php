<?php
// pages/ecritures/ajax_get_ecritures.php
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_ecritures.php';

header('Content-Type: application/json');

try {
    // Paramètres de recherche et tri
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'ID_Ecriture';
    $order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

    // Validation des paramètres de tri
    $allowedSorts = ['ID_Ecriture', 'Date_Saisie', 'Description', 'Montant_Total', 'Cde'];
    $sort = in_array($sort, $allowedSorts) ? $sort : 'ID_Ecriture';
    $order = $order === 'ASC' ? 'ASC' : 'DESC';

    // Requête filtrée et triée
    $ecritures = getListeEcritures($pdo, $search, $sort, $order);

    echo json_encode($ecritures);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}