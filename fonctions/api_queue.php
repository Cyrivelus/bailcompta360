<?php
// pages/ecritures/saisie.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Configuration d'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
setlocale(LC_TIME, 'fr_FR', 'fr_FR.utf8', 'fr');
$titre = 'Saisie des Écritures Comptables';
$current_page = basename($_SERVER['PHP_SELF']);

require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_comptes.php';
require_once '../../fonctions/gestion_journaux.php';
require_once '../../fonctions/gestion_ecritures.php';
require_once '../../fonctions/gestion_agences.php'; // Include the agencies functions
require_once '../../fonctions/api_queue.php'; // New file for API queue functions

// Ensure $pdo is initialized
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("ERREUR FATALE: \$pdo n'a pas été initialisé par database.php dans saisie.php");
    die("Une erreur critique de configuration de la base de données est survenue. Veuillez contacter l'administrateur.");
}

$journaux = getListeJournaux($pdo);
$GLOBALS['allComptesPLN'] = getAllComptesComptac($pdo);
$agences = getAllAgences($pdo);

// Initialisation des variables pour pré-remplir le formulaire
$selected_journal_cde = $_POST['code_journal'] ?? '';
$selected_periode_mois = $_POST['periode_mois'] ?? date('m');
$selected_periode_annee = $_POST['periode_annee'] ?? date('Y');
$date_piece = $_POST['date_piece'] ?? date('Y-m-d');
$numero_piece = $_POST['numero_piece'] ?? '';
$libelle_general = $_POST['libelle_general'] ?? '';
$lignes_post = []; // To re-display lines in case of validation error
$erreur = null;
$_SESSION['success_message'] = $_SESSION['success_message'] ?? ''; // Initialize for concatenation
$_SESSION['error_message'] = $_SESSION['error_message'] ?? '';    // Initialize for concatenation
$libelle2 = '';

// --- Début de la Vérification de la connectivité API ---
// Centralize API base URL for easier management
$api_url_base = 'http://localhost/sce-fintech-api/rest/';
$api_status = [];

// Check connectivity to the transaction service
$api_transaction_url = $api_url_base . 'operation/transaction';
$ch_check_transaction = curl_init($api_transaction_url);
curl_setopt($ch_check_transaction, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch_check_transaction, CURLOPT_TIMEOUT, 5); // Quick timeout for connection check
curl_setopt($ch_check_transaction, CURLOPT_NOBODY, true); // Only get headers (for a HEAD request)
curl_setopt($ch_check_transaction, CURLOPT_CUSTOMREQUEST, 'GET'); // Use GET for connectivity check (or HEAD if the API supports it)

curl_exec($ch_check_transaction);
$http_code_transaction = curl_getinfo($ch_check_transaction, CURLINFO_HTTP_CODE);
$curl_error_transaction = curl_error($ch_check_transaction);
curl_close($ch_check_transaction);

if ($http_code_transaction >= 200 && $http_code_transaction < 400) { // 2xx or 3xx indicate success
    $api_status['Transaction API'] = ['status' => 'Connectée', 'code' => $http_code_transaction];
} else {
    $api_status['Transaction API'] = ['status' => 'Non connectée', 'code' => $http_code_transaction, 'error' => $curl_error_transaction];
}
// --- Fin de la Vérification de la connectivité API ---


// --- DéBUT DU TRAITEMENT DU FORMULAIRE POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Recuperation des donnees d'en-tête
    $code_journal_cde = trim($_POST['code_journal'] ?? '');
    $periode_mois = trim($_POST['periode_mois'] ?? date('m'));
    $periode_annee = trim($_POST['periode_annee'] ?? date('Y'));
    $date_piece = trim($_POST['date_piece'] ?? '');
    $numero_piece = trim($_POST['numero_piece'] ?? '');
    $libelle_general = trim($_POST['libelle_general'] ?? '');
    $selected_an_array = is_array($_POST['an'] ?? []) ? $_POST['an'] ?? [] : [];
    $selected_an = isset($selected_an_array[0]) ? trim($selected_an_array[0]) : '';
    $nom_utilisateur = $_SESSION['utilisateur']['nom'] ?? 'SYSTEM';

    $mois_comptable = $periode_annee . '-' . str_pad($periode_mois, 2, '0', STR_PAD_LEFT);

    // Recuperation des donnees des lignes
    $comptes = $_POST['compte'] ?? [];
    $libelles_ligne = $_POST['libelle_ligne'] ?? [];
    $debits = $_POST['debit'] ?? [];
    $credits = $_POST['credit'] ?? [];
    $ans = $_POST['an'] ?? []; // Agence codes for each line
    $contreparties = $_POST['contrepartie'] ?? [];

    $lignes_a_enregistrer = [];
    $total_debit_calc = 0;
    $total_credit_calc = 0;

    // Validation des champs d'en-tête obligatoires
    $erreurs_entete_details = [];
    if ($code_journal_cde === '') {
        $erreurs_entete_details[] = "Journal";
    }
    if (empty($date_piece)) {
        $erreurs_entete_details[] = "Date Pièce";
    }
    if ($numero_piece === '') {
        $erreurs_entete_details[] = "Numéro Pièce";
    }
    if (empty($libelle_general)) {
        $erreurs_entete_details[] = "Libellé Général";
    }

    if (!empty($erreurs_entete_details)) {
        $erreur = "Les informations générales suivantes sont obligatoires : " . implode(", ", $erreurs_entete_details) . ".";
    } else {
        // Processing and validation of entry lines
        for ($i = 0; $i < count($comptes); $i++) {
            $compte_cpt = trim($comptes[$i] ?? '');
            $libelle_ligne_item = trim($libelles_ligne[$i] ?? '');
            $debit_str = isset($debits[$i]) ? trim($debits[$i]) : '';
            $credit_str = isset($credits[$i]) ? trim($credits[$i]) : '';
            $an = isset($ans[$i]) ? trim($ans[$i]) : ''; // Get agency code for the line
            $contrepartie = isset($contreparties[$i]) ? trim($contreparties[$i]) : '';

            $debit_item = !empty($debit_str) ? (float)str_replace(',', '.', $debit_str) : 0;
            $credit_item = !empty($credit_str) ? (float)str_replace(',', '.', $credit_str) : 0;

            // To re-display data in case of error on another line or on balance
            $lignes_post[] = [
                'compte' => $compte_cpt,
                'libelle_ligne' => $libelle_ligne_item,
                'debit' => $debit_str,
                'credit' => $credit_str,
                'an' => $an,
                'contrepartie' => $contrepartie
            ];

            if (!empty($compte_cpt) || !empty($libelle_ligne_item) || $debit_item > 0 || $credit_item > 0) {
                if (empty($compte_cpt)) {
                    $erreur = "Erreur sur la ligne " . ($i + 1) . " : Le numéro de compte est manquant.";
                    break;
                }
                if (empty($libelle_ligne_item)) {
                    $erreur = "Erreur sur la ligne " . ($i + 1) . " : Le libellé de ligne est manquant pour le compte " . htmlspecialchars($compte_cpt) . ".";
                    break;
                }
                if ($debit_item == 0 && $credit_item == 0) {
                    $erreur = "Erreur sur la ligne " . ($i + 1) . " : Le montant (débit ou crédit) est manquant ou nul pour le compte " . htmlspecialchars($compte_cpt) . ".";
                    break;
                }
                if ($debit_item > 0 && $credit_item > 0) {
                    $erreur = "Erreur sur la ligne " . ($i + 1) . " : Vous ne pouvez pas saisir un montant au débit ET au crédit pour la même ligne.";
                    break;
                }

                if ($debit_item > 0) {
                    $lignes_a_enregistrer[] = [
                        'compte_cpt' => $compte_cpt,
                        'libelle_ligne' => $libelle_ligne_item,
                        'montant' => $debit_item,
                        'sens' => 'D',
                        'an' => $an, // Add agency code for the line
                        'contrepartie' => $contrepartie
                    ];
                    $total_debit_calc += $debit_item;
                } elseif ($credit_item > 0) {
                    $lignes_a_enregistrer[] = [
                        'compte_cpt' => $compte_cpt,
                        'libelle_ligne' => $libelle_ligne_item,
                        'montant' => $credit_item,
                        'sens' => 'C',
                        'an' => $an, // Add agency code for the line
                        'contrepartie' => $contrepartie
                    ];
                    $total_credit_calc += $credit_item;
                }
            }
        }
    }

    if (!$erreur) {
        if (empty($lignes_a_enregistrer)) {
            $erreur = "Aucune ligne d'écriture valide n'a été saisie.";
        } elseif (abs($total_debit_calc - $total_credit_calc) > 0.001) {
            $erreur = "La pièce n'est pas équilibrée. Total Débit: " . number_format($total_debit_calc, 2, ',', ' ') . ", Total Crédit: " . number_format($total_credit_calc, 2, ',', ' ') . ".";
        } else {
            try {
                // We will *always* try to save the accounting entry first.
                // The API call will be handled separately.

                // Start a database transaction for the accounting entry itself
                $pdo->beginTransaction();

                $resultatEnregistrement = enregistrerEcriture(
                    $pdo,
                    $date_piece,
                    $libelle_general,
                    $total_debit_calc,
                    $code_journal_cde,
                    $code_journal_cde,
                    $numero_piece,
                    $mois_comptable,
                    $nom_utilisateur,
                    $libelle2,
                    $selected_an
                );

                if (isset($resultatEnregistrement['status']) && $resultatEnregistrement['status'] === true && !empty($resultatEnregistrement['id']) && is_numeric($resultatEnregistrement['id'])) {

                    $idEcritureNumerique = (int)$resultatEnregistrement['id'];

                    foreach ($lignes_a_enregistrer as $ligne) {
                        enregistrerLigneEcriture(
                            $pdo,
                            $idEcritureNumerique,
                            (int)$ligne['compte_cpt'],
                            (string)$ligne['libelle_ligne'],
                            (float)$ligne['montant'],
                            (string)$ligne['sens'],
                            (string)$ligne['an'],
                            (string)$ligne['contrepartie']
                        );
                    }

                    // Commit the database transaction for the accounting entry.
                    // This ensures the entry is saved regardless of API status.
                    $pdo->commit();
                    $_SESSION['success_message'] = "L'écriture Numéro " . htmlspecialchars($numero_piece) . " (ID: " . $idEcritureNumerique . ") a été enregistrée avec succès.";

                    // --- DÉBUT DE L'APPEL À L'API DE PAIEMENT OU MISE EN FILE ---
                    $sender_line = null;
                    $receiver_line = null;

                    // Find the credit line (sender, e.g., bank) and debit line (receiver)
                    foreach ($lignes_a_enregistrer as $ligne) {
                        if ($ligne['sens'] === 'C') { // Credit (money leaving sender's account)
                            $sender_line = $ligne;
                        } elseif ($ligne['sens'] === 'D') { // Debit (money entering receiver's account)
                            $receiver_line = $ligne;
                        }
                        if ($sender_line && $receiver_line) {
                            break; // Found both, exit loop
                        }
                    }

                    // If a potential banking transaction is identified (sender and receiver with a positive amount)
                    if ($sender_line && $receiver_line && (float)$sender_line['montant'] > 0) {
                        $attempt_api_call = false;
                        $api_transaction_data = null;
                        $error_api_call = null;

                        try {
                            $sender_compte_numero_obj = getCompteNumeroById($pdo, (int)$sender_line['compte_cpt']);
                            $receiver_compte_numero_obj = getCompteNumeroById($pdo, (int)$receiver_line['compte_cpt']);

                            if ($sender_compte_numero_obj && $receiver_compte_numero_obj &&
                                isset($sender_compte_numero_obj['Numero_Compte']) && isset($receiver_compte_numero_obj['Numero_Compte'])) {

                                // Construct API account numbers
                                $sender_account_number_api = (string)$sender_line['an'] . (string)$sender_compte_numero_obj['Numero_Compte'];
                                $receiver_account_number_api = (string)$receiver_line['an'] . (string)$receiver_compte_numero_obj['Numero_Compte'];

                                $transaction_time = date('Y-m-d\TH:i:s\Z');
                                $transaction_id = uniqid('TX_') . '_' . $idEcritureNumerique; // Unique ID
                                $amount = (float)$sender_line['montant']; // Transaction amount
                                $reason = "Paiement de facture: " . htmlspecialchars($numero_piece) . " - " . htmlspecialchars($libelle_general);

                                $api_transaction_data = [
                                    'transactiontime' => $transaction_time,
                                    'transactionid' => $transaction_id,
                                    'amount' => $amount,
                                    'senderaccountnumber' => $sender_account_number_api,
                                    'receiveraccountnumber' => $receiver_account_number_api,
                                    'reason' => $reason
                                ];
                                $attempt_api_call = true;

                            } else {
                                $error_api_call = "Les numéros de compte pour l'API de paiement n'ont pas pu être récupérés.";
                            }
                        } catch (Exception $e) {
                            $error_api_call = "Erreur lors de la préparation des données API: " . $e->getMessage();
                        }


                        if ($attempt_api_call && $api_transaction_data) {
                             if (isset($api_status['Transaction API']) && $api_status['Transaction API']['status'] === 'Connectée') {
                                try {
                                    $api_url_transaction_specific = $api_url_base . 'operation/transaction';

                                    $ch = curl_init($api_url_transaction_specific);
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($ch, CURLOPT_POST, true);
                                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_transaction_data));
                                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                        'Content-Type: application/json',
                                        'Content-Length: ' . strlen(json_encode($api_transaction_data))
                                    ]);
                                    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Standard timeout for actual API call

                                    $api_response = curl_exec($ch);
                                    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                    $curl_error = curl_error($ch);
                                    curl_close($ch);

                                    $response_decoded = json_decode($api_response, true);

                                    if ($http_status == 200 && isset($response_decoded['status']) && $response_decoded['status'] === 'SUCCESS') {
                                        $_SESSION['success_message'] .= " Transaction bancaire via API réussie ! (API TX ID: " . htmlspecialchars($response_decoded['transactionid']) . ").";
                                        // Mark as sent in queue if it was there (optional, for robustness)
                                        // e.g., updateApiQueueStatus($pdo, $idEcritureNumerique, 'sent');
                                    } else {
                                        $error_msg_api = "Échec de l'API (HTTP " . $http_status . "): ";
                                        if ($curl_error) {
                                            $error_msg_api .= " Erreur cURL: " . $curl_error . ".";
                                        }
                                        if (isset($response_decoded['message'])) {
                                            $error_msg_api .= " Détail API: " . htmlspecialchars($response_decoded['message']);
                                        } elseif ($api_response) {
                                            $error_msg_api .= " Réponse brute: " . htmlspecialchars($api_response);
                                        }
                                        throw new Exception($error_msg_api); // Throw to be caught and queued
                                    }
                                } catch (Exception $e) {
                                    // API call failed, queue the transaction
                                    $_SESSION['error_message'] .= "ATTENTION: " . htmlspecialchars($e->getMessage()) . " La transaction bancaire a été mise en file d'attente pour un envoi ultérieur.";
                                    error_log("API Call Failed for Ecriture ID: " . $idEcritureNumerique . " - " . $e->getMessage());
                                    
                                    // Store in the queue
                                    queueApiTransaction($pdo, $idEcritureNumerique, $api_transaction_data, $e->getMessage());
                                }
                            } else {
                                // API not connected, queue the transaction directly
                                $_SESSION['error_message'] .= "ATTENTION: L'API de transaction n'est pas connectée. La transaction bancaire a été mise en file d'attente pour un envoi ultérieur.";
                                error_log("API Transaction not connected for Ecriture ID: " . $idEcritureNumerique);
                                queueApiTransaction($pdo, $idEcritureNumerique, $api_transaction_data, "API non connectée.");
                            }
                        } else if ($error_api_call) {
                            $_SESSION['error_message'] .= "ATTENTION: Impossible de préparer les données pour la transaction bancaire API. " . htmlspecialchars($error_api_call);
                            error_log("API Data Preparation Error for Ecriture ID: " . $idEcritureNumerique . " - " . $error_api_call);
                            // Do not queue if data itself cannot be prepared
                        }
                    } else {
                        // No identifiable banking transaction in this entry, or zero amount. No API call needed.
                        $_SESSION['success_message'] .= " Aucune transaction bancaire identifiable dans cette écriture, pas d'appel API nécessaire.";
                    }
                    // --- FIN DE L'APPEL À L'API DE PAIEMENT OU MISE EN FILE ---

                    // Redirect after processing, regardless of API status
                    header('Location: liste.php?success=1&id=' . $idEcritureNumerique);
                    exit();

                } else {
                    // Database header insert failed, rollback only the DB changes
                    $pdo->rollBack();
                    $erreur = "Erreur système lors de l'enregistrement de l'en-tête de l'écriture. ";
                    if (!empty($resultatEnregistrement['error'])) {
                        $erreur .= "Détail : " . $resultatEnregistrement['error'];
                    } else {
                        $erreur .= "L'ID d'écriture n'a pas été retourné ou est invalide, et aucun détail d'erreur spécifique n'est disponible.";
                    }
                    if (!empty($resultatEnregistrement['debug_info'])) {
                        $erreur .= "<br><br><strong>--- Informations de Débogage (de enregistrerEcriture) ---</strong>";
                        $erreur .= "<pre style='background-color:#f5f5f5; border:1px solid #ddd; padding:10px; text-align:left; font-size:0.9em;'>";
                        if(is_array($resultatEnregistrement['debug_info'])) {
                            foreach ($resultatEnregistrement['debug_info'] as $msg) {
                                $erreur .= htmlspecialchars($msg) . "\n";
                            }
                        } else {
                            $erreur .= htmlspecialchars(print_r($resultatEnregistrement['debug_info'], true));
                        }
                        if (!empty($resultatEnregistrement['debug_info_exception'])) {
                            $erreur .= "\nInformations Exception PDO (errorInfo) :\n" . htmlspecialchars(print_r($resultatEnregistrement['debug_info_exception'], true));
                        }
                        $erreur .= "</pre>";
                    }
                }
            } catch (PDOException $e) {
                // Catch any PDO exceptions (DB errors) during the initial accounting entry save
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $erreur = "Erreur PDO (interceptée dans saisie.php) lors de l'enregistrement de l'écriture comptable : " . htmlspecialchars($e->getMessage());
                error_log("PDO Exception in saisie.php during accounting entry save: " . $e->getMessage());
            } catch (Exception $e) {
                // Catch any other general exceptions during the initial accounting entry save
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $erreur = "Erreur Générale (interceptée dans saisie.php) lors de l'enregistrement de l'écriture comptable : " . htmlspecialchars($e->getMessage());
                error_log("General Exception in saisie.php during accounting entry save: " . $e->getMessage());
            }
        }
    }
} // --- FIN DU TRAITEMENT DU FORMULAIRE POST ---

require_once('../../templates/header.php');
require_once('../../templates/navigation.php');

// Display general error message if it exists
if ($erreur) {
    echo '<div class="container mt-4"><div class="alert alert-danger">' . $erreur . '</div></div>';
}

// Display API connectivity status
// Your existing display logic for API status can remain here.
?>