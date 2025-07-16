<?php
// pages/factures/ajax_get_factures.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('../../fonctions/database.php');
require_once('../../fonctions/gestion_factures.php'); // Make sure this file contains getFilteredFactures

$pdo = connect_db();
if (!$pdo) {
    echo json_encode(['error' => 'Database connection error']);
    exit();
}

// Get filter and sort parameters from AJAX request
$statusFilter = $_GET['status'] ?? '';
$searchTerm = $_GET['search'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'ID_Facture';
$sortDirection = $_GET['sort_direction'] ?? 'DESC';

// Sanitize and validate sort parameters to prevent SQL injection
$allowedSortBy = [
    'ID_Facture', 'Numero_Facture', 'Type_Facture', 'Date_Emission',
    'Date_Reception', 'Date_Echeance', 'Montant_TTC', 'Statut_Facture',
    'Nom_Fournisseur'
];
$allowedSortDirection = ['ASC', 'DESC'];

if (!in_array($sortBy, $allowedSortBy)) {
    $sortBy = 'ID_Facture';
}
if (!in_array(strtoupper($sortDirection), $allowedSortDirection)) {
    $sortDirection = 'DESC';
}

// Fetch filtered and sorted invoices
$factures = getFilteredFactures($pdo, $statusFilter, $searchTerm, $sortBy, $sortDirection);

// Output as JSON
header('Content-Type: application/json');
echo json_encode(['factures' => $factures]);
?>