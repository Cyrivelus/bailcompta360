<?php
// pages/ecritures/ajax_get_ecritures.php

require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_ecritures.php';

header('Content-Type: application/json');

$compteId = filter_input(INPUT_GET, 'compte_id', FILTER_VALIDATE_INT);
$dateDebut = filter_input(INPUT_GET, 'date_debut', FILTER_UNSAFE_RAW);
$dateFin = filter_input(INPUT_GET, 'date_fin', FILTER_UNSAFE_RAW);
$search = filter_input(INPUT_GET, 'search', FILTER_UNSAFE_RAW);
$sort = filter_input(INPUT_GET, 'sort', FILTER_UNSAFE_RAW);
$order = filter_input(INPUT_GET, 'order', FILTER_UNSAFE_RAW);

if (!$compteId || !$dateDebut || !$dateFin) {
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

try {
    $ecritures = getEcrituress($pdo, $compteId, $dateDebut, $dateFin, $search, $sort, $order);
    echo json_encode($ecritures);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
