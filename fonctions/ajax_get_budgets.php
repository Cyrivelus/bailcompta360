<?php
/**
 * Ce script est appel par AJAX pour rcuprer la liste des budgets.
 * Il utilise la fonction getListeBudgets pour interroger la base de donnes
 * et retourne les rsultats au format JSON.
 */

header('Content-Type: application/json');

// Inclure le fichier de fonctions de gestion des budgets
require_once('gestion_budgets.php');

// Rcuprer les paramtres de recherche et de tri depuis la requte GET
// Utilisation de l'oprateur de coalescence nul (??) pour dfinir des valeurs par dfaut
$searchTerm = $_GET['search'] ?? '';
$sortField = $_GET['sort'] ?? 'ID_Budget';
$sortOrder = $_GET['order'] ?? 'DESC';

// Appeler la fonction qui rcupre les donnes des budgets
$budgets = getListeBudgets($searchTerm, $sortField, $sortOrder);

// Encoder les donnes au format JSON et les envoyer au client
echo json_encode($budgets);
