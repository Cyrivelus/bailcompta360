#!/usr/bin/php
<?php
// scripts/process_recurring_entries.php

// This script should NOT be web-accessible.
// It's meant to be run by a cron job or scheduled task.

require_once __DIR__ . '/../fonctions/database.php';
require_once __DIR__ . '/../fonctions/gestion_recurring_entries.php';
require_once __DIR__ . '/../fonctions/gestion_logs.php'; // Ensure logging is configured

$pdo = getPDO(); // Get your PDO connection

// Define a system user ID for automated entries (e.g., user ID 0 or a specific system user)
$systemUserId = 1; // IMPORTANT: Replace with actual system user ID or a default admin ID

$processUpToDate = date('Y-m-d'); // Process all entries due up to today

logActivity("Lancement du traitement des écritures récurrentes pour le {$processUpToDate}.");

$results = processDueRecurringEntries($pdo, $processUpToDate, $systemUserId);

foreach ($results as $result) {
    if ($result['success']) {
        logActivity($result['message']);
    } else {
        logError($result['message']);
    }
}

logActivity("Traitement des écritures récurrentes terminé.");

?>