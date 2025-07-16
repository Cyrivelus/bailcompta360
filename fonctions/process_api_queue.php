<?php
// scripts/process_api_queue.php (This would be run by a cron job or similar scheduler)

// Set up error reporting for the script
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/fonctions/database.php';
require_once dirname(__DIR__) . '/fonctions/api_queue.php';

// Ensure $pdo is initialized
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("ERREUR FATALE: \$pdo n'a pas été initialisé par database.php dans process_api_queue.php");
    die("Une erreur critique de configuration de la base de données est survenue. Veuillez contacter l'administrateur.");
}

$api_url_base = 'http://localhost/sce-fintech-api/rest/';
$max_attempts = 5; // Maximum number of times to retry a transaction

echo "Starting API queue processing...\n";

// Check API connectivity first
$api_transaction_url = $api_url_base . 'operation/transaction';
$ch_check_transaction = curl_init($api_transaction_url);
curl_setopt($ch_check_transaction, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch_check_transaction, CURLOPT_TIMEOUT, 5);
curl_setopt($ch_check_transaction, CURLOPT_NOBODY, true);
curl_setopt($ch_check_transaction, CURLOPT_CUSTOMREQUEST, 'GET');

curl_exec($ch_check_transaction);
$http_code_transaction = curl_getinfo($ch_check_transaction, CURLINFO_HTTP_CODE);
$curl_error_transaction = curl_error($ch_check_transaction);
curl_close($ch_check_transaction);

$api_connected = ($http_code_transaction >= 200 && $http_code_transaction < 400);

if (!$api_connected) {
    echo "API de transaction non connectée. Code HTTP: " . $http_code_transaction . ", Erreur cURL: " . ($curl_error_transaction ?: 'Aucune') . "\n";
    echo "Processing aborted. Will try again later.\n";
    exit; // Exit if API is not available
}

echo "API de transaction connectée. Code HTTP: " . $http_code_transaction . "\n";

$pending_transactions = getPendingApiTransactions($pdo, 20); // Process a batch of 20 at a time

if (empty($pending_transactions)) {
    echo "Aucune transaction API en attente.\n";
} else {
    echo "Found " . count($pending_transactions) . " pending transactions.\n";
    foreach ($pending_transactions as $transaction) {
        $queue_id = $transaction['id'];
        $ecriture_id = $transaction['ecriture_id'];
        $api_data = json_decode($transaction['api_data'], true);
        $attempts = $transaction['attempts'] + 1;
        $current_error_message = '';

        echo "Processing queue ID: " . $queue_id . " (Ecriture ID: " . $ecriture_id . ") - Attempt: " . $attempts . "\n";

        if ($attempts > $max_attempts) {
            updateApiQueueStatus($pdo, $queue_id, 'failed_permanently', 'Nombre maximal de tentatives atteint.', $attempts);
            error_log("Transaction API queue ID " . $queue_id . " marked as failed permanently after " . $attempts . " attempts.");
            echo "Max attempts reached for queue ID " . $queue_id . ". Marking as failed permanently.\n";
            continue; // Move to the next transaction
        }

        try {
            $ch = curl_init($api_transaction_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($api_data))
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Standard timeout for actual API call

            $api_response = curl_exec($ch);
            $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            $response_decoded = json_decode($api_response, true);

            if ($http_status == 200 && isset($response_decoded['status']) && $response_decoded['status'] === 'SUCCESS') {
                updateApiQueueStatus($pdo, $queue_id, 'sent', 'Transaction API réussie.', $attempts);
                echo "Transaction API réussie pour l'ID de file " . $queue_id . ".\n";
            } else {
                $current_error_message = "Échec de l'API (HTTP " . $http_status . "): ";
                if ($curl_error) {
                    $current_error_message .= " Erreur cURL: " . $curl_error . ".";
                }
                if (isset($response_decoded['message'])) {
                    $current_error_message .= " Détail API: " . htmlspecialchars($response_decoded['message']);
                } elseif ($api_response) {
                    $current_error_message .= " Réponse brute: " . htmlspecialchars($api_response);
                }
                updateApiQueueStatus($pdo, $queue_id, 'pending', $current_error_message, $attempts); // Still pending, but updated attempts and error
                error_log("Retrying API Call for queue ID " . $queue_id . " - Error: " . $current_error_message);
                echo "Transaction API échouée pour l'ID de file " . $queue_id . ". Message: " . $current_error_message . " Tentative n°" . $attempts . "\n";
            }
        } catch (Exception $e) {
            $current_error_message = "Erreur inattendue lors de l'appel API: " . $e->getMessage();
            updateApiQueueStatus($pdo, $queue_id, 'pending', $current_error_message, $attempts); // Still pending, but updated attempts and error
            error_log("Exception during API Call for queue ID " . $queue_id . " - Error: " . $current_error_message);
            echo "Erreur lors de l'appel API pour l'ID de file " . $queue_id . ". Message: " . $current_error_message . " Tentative n°" . $attempts . "\n";
        }
    }
}

echo "API queue processing finished.\n";
?>