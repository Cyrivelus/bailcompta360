<?php
// pages/comptes/lettrage_action.php
session_start();

// Vérification de l'accès et des données
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Comptable' || !isset($_POST['lignes_ids'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé ou données manquantes.']);
    exit();
}

require_once '../../fonctions/database.php';
require_once '../../fonctions/lettrage_fonctions.php';

$lignesIds = $_POST['lignes_ids'];

if (lettrerEcritures($pdo, $lignesIds)) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Écritures lettrées avec succès.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors du lettrage.']);
}