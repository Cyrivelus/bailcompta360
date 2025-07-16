<?php
// pages/ecritures/ajax_ecritures.php

require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_ecritures.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = 'ID_Ecriture';
$order = 'DESC';

$ecritures = getListeEcritures($pdo, $search, $sort, $order);

header('Content-Type: application/json');
echo json_encode($ecritures);
exit;
