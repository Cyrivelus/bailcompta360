
<?php
// pages/emprunts/mark_echeance_paid.php
// Handles AJAX request to mark an echeance as paid and create accounting entry

// --- Start Session if not already started ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Inclusions ---
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_emprunts.php';

// --- Configuration ---
date_default_timezone_set('Africa/Douala'); // Set your local timezone for DateTime objects

// --- Logging Function (Basic) ---
function log_error($message) {
    error_log(date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL, 3, __DIR__ . '/payment_errors.log');
}

header('Content-Type: application/json'); // Indicate a JSON response

// Initialize the response array
$response = [
    'status' => 'error',
    'message' => 'Une erreur inconnue est survenue.',
    'id_ecriture' => null, // Initialize to null
    'accounting_error' => false // Initialize accounting_error flag
];
$accounting_error_details = []; // Array to collect specific accounting error messages

// --- PDO Connection Check ---
// Assuming $pdo is correctly initialized in '../../fonctions/database.php'
if (!isset($pdo) || !$pdo instanceof PDO) {
    $errorMessage = "Erreur de configuration de la base de données : La connexion PDO n'a pas été correctement initialisée.";
    log_error($errorMessage);
    $response['message'] = $errorMessage;
    echo json_encode($response);
    exit;
}

// --- Validate and Sanitize POST Data ---
// 'journal_cde' removed from required_params as per user request to ignore validation
$required_params = ['id_echeance', 'id_emprunt', 'compte_principal_id', 'compte_interet_id', 'compte_banque_id'];
$missing_params = [];
foreach ($required_params as $param) {
    // Check if parameter is not set OR if it's an empty string after trimming whitespace
    if (!isset($_POST[$param]) || (is_string($_POST[$param]) && trim($_POST[$param]) === '')) {
        $missing_params[] = $param;
    }
}

if (!empty($missing_params)) {
    $errorMessage = 'Paramètres POST manquants ou vides: ' . implode(', ', $missing_params) . ' sont requis.';
    log_error($errorMessage . ' Received POST data: ' . json_encode($_POST));
    $response['message'] = $errorMessage;
    echo json_encode($response);
    exit;
}

// Helper function to normalize and validate integer IDs
function normalizeAccountId($accountId) {
    // Use FILTER_VALIDATE_INT directly for robust integer validation
    $normalized = filter_var($accountId, FILTER_VALIDATE_INT);
    // Return null if not a valid integer or false (e.g., empty string)
    if ($normalized === false) {
        return null;
    }
    return $normalized;
}

// Helper function to filter lines by Sens (already good)
function filterBySens($lines, $sens) {
    return array_filter($lines, function($line) use ($sens) {
        return $line['Sens'] === $sens;
    });
}

// --- Validate and Sanitize Account IDs more strictly ---
$id_echeance = normalizeAccountId($_POST['id_echeance'] ?? '');
$id_emprunt = normalizeAccountId($_POST['id_emprunt'] ?? ''); // Correct variable name
$compte_dette_emprunt = normalizeAccountId($_POST['compte_principal_id'] ?? '');
$compte_charge_interet = normalizeAccountId($_POST['compte_interet_id'] ?? '');
$compte_banque = normalizeAccountId($_POST['compte_banque_id'] ?? '');
// For optional account, '0' is a valid value from the combobox if 'Aucun' is selected
$compte_charge_taxes = normalizeAccountId($_POST['compte_taxes_frais_id'] ?? '0');

// Validate that all required IDs are valid integers and positive
$invalid_id_values = [];
if ($id_echeance === null || $id_echeance <= 0) $invalid_id_values[] = 'ID Échéance';
if ($id_emprunt === null || $id_emprunt <= 0) $invalid_id_values[] = 'ID Emprunt';
if ($compte_dette_emprunt === null || $compte_dette_emprunt <= 0) $invalid_id_values[] = 'Compte Principal';
if ($compte_charge_interet === null || $compte_charge_interet <= 0) $invalid_id_values[] = 'Compte Intérêt';
if ($compte_banque === null || $compte_banque <= 0) $invalid_id_values[] = 'Compte Banque';

// Check optional account if it was provided and is not null (can be 0 if 'Aucun' was chosen)
if ($compte_charge_taxes === null || $compte_charge_taxes < 0) {
    $invalid_id_values[] = 'Compte Taxes/Frais (optionnel)';
}

if (!empty($invalid_id_values)) {
    $errorMessage = 'Les IDs fournis sont invalides ou non numériques (doivent être des entiers positifs). Problèmes avec: ' . implode(', ', $invalid_id_values);
    log_error($errorMessage . ' Received IDs: ' . json_encode($_POST));
    $response['message'] = $errorMessage;
    echo json_encode($response);
    exit;
}

// Ensure account IDs are within a typical range for account numbers (e.g., max 10 digits)
$max_account_id_length = 10;
$account_ids_to_check_length = [];

// Add only relevant, positive account IDs to this check (0 is allowed for optional but not checked for length)
if ($compte_dette_emprunt > 0) $account_ids_to_check_length['Compte Principal'] = (string)$compte_dette_emprunt;
if ($compte_charge_interet > 0) $account_ids_to_check_length['Compte Intérêt'] = (string)$compte_charge_interet;
if ($compte_banque > 0) $account_ids_to_check_length['Compte Banque'] = (string)$compte_banque;
if ($compte_charge_taxes > 0) { // Only check length if a specific tax account is chosen (ID > 0)
    $account_ids_to_check_length['Compte Taxes/Frais (optionnel)'] = (string)$compte_charge_taxes;
}

foreach ($account_ids_to_check_length as $label => $id_str) {
    if (strlen($id_str) > $max_account_id_length) {
        $invalid_id_values[] = "$label (numéro trop long)";
    }
}

if (!empty($invalid_id_values)) {
    $errorMessage = "Les IDs de compte comptables fournis sont trop longs. Problèmes avec: " . implode(', ', $invalid_id_values) . ". La longueur maximale attendue est de $max_account_id_length chiffres.";
    log_error($errorMessage . ' Received IDs: ' . json_encode($_POST));
    $response['message'] = $errorMessage;
    echo json_encode($response);
    exit;
}

// --- Verify Account IDs against Comptes_compta table (exact match for ID_Compte) ---
$account_ids_to_validate_in_db = [
    $compte_dette_emprunt,
    $compte_charge_interet,
    $compte_banque
];
// Only add optional account if it was actually provided and is not 0 (meaning 'Aucun')
if ($compte_charge_taxes !== null && $compte_charge_taxes > 0) {
    $account_ids_to_validate_in_db[] = $compte_charge_taxes;
}

$invalid_accounts_in_db = [];
foreach ($account_ids_to_validate_in_db as $acc_id) {
    try {
        // Corrected: Use exact match (=) for ID_Compte, as it's an INT primary key
        $stmtCheckAccount = $pdo->prepare("SELECT COUNT(*) FROM Comptes_compta WHERE ID_Compte = ?");
        $stmtCheckAccount->bindParam(1, $acc_id, PDO::PARAM_INT);
        $stmtCheckAccount->execute();
        if ($stmtCheckAccount->fetchColumn() == 0) {
            $invalid_accounts_in_db[] = $acc_id;
        }
    } catch (PDOException $e) {
        $errorMessage = "Erreur PDO lors de la vérification des comptes comptables: " . $e->getMessage();
        log_error($errorMessage);
        $response['message'] = $errorMessage;
        echo json_encode($response);
        exit;
    }
}

if (!empty($invalid_accounts_in_db)) {
    $errorMessage = "Erreur: Un ou plusieurs numéros de compte comptables fournis sont introuvables dans la base de données (IDs: " . implode(', ', $invalid_accounts_in_db) . ").";
    log_error($errorMessage);
    $response['message'] = $errorMessage;
    echo json_encode($response);
    exit;
}

// All accounts are valid, proceed with the rest of the logic
try {
    $pdo->beginTransaction();

    // 1. Fetch Echeance Details
    $stmtEcheance = $pdo->prepare("
        SELECT
            E.*,
            S.Code_Statut,
            EB.Numero_Pret,
            EB.ID_Emprunt
        FROM Echeances_Amortissement E
        LEFT JOIN Statuts S ON E.ID_Statut = S.ID_Statut
        LEFT JOIN Emprunts_Bancaires EB ON E.ID_Emprunt = EB.ID_Emprunt
        WHERE E.ID_Echeance = ?
    ");
    $stmtEcheance->execute([$id_echeance]);
    $echeance = $stmtEcheance->fetch(PDO::FETCH_ASSOC);

    if (!$echeance) {
        $pdo->rollBack();
        $errorMessage = "Échéance non trouvée (ID: " . $id_echeance . ").";
        log_error($errorMessage);
        $response['message'] = $errorMessage;
        echo json_encode($response);
        exit;
    }

    // Check if the installment is already marked as paid (comptabilisée)
    if (($echeance['Code_Statut'] ?? '') === 'COMP') {
        $pdo->rollBack();
        $response['status'] = 'success'; // Treat as success if already comptabilisée
        $response['message'] = "L'échéance est déjà marquée comme comptabilisée.";
        $response['id_ecriture'] = $echeance['ID_Ecriture_Comptable'] ?? null;
        log_error("Échéance ID " . $id_echeance . " déjà comptabilisée. ID_Ecriture: " . $response['id_ecriture']);
        echo json_encode($response);
        exit;
    }

    // 2. Get the ID for the 'Comptabilisée' status
    $stmtStatutComp = $pdo->prepare("SELECT ID_Statut FROM Statuts WHERE Code_Statut = 'COMP'");
    $stmtStatutComp->execute();
    $id_statut_comptabilisee = $stmtStatutComp->fetchColumn();

    if (!$id_statut_comptabilisee) {
        $pdo->rollBack();
        $errorMessage = "Erreur de configuration : Statut 'Comptabilisée' (COMP) non trouvé dans la table Statuts.";
        log_error($errorMessage);
        $response['message'] = $errorMessage;
        echo json_encode($response);
        exit;
    }

    // --- Journal Code (Cde) ---
    $journal_cde = 1; // Assuming 1 is the Cde for your bank journal, please confirm

    // --- Set Agency and User from Session ---
    $numero_agence_sce = $_SESSION['numero_agence_sce'] ?? '009'; // Default if not in session
    $nom_utilisateur = $_SESSION['nom_utilisateur'] ?? 'Utilisateur Inconnu';

    // 3. Create the Accounting Entry (Ecriture)
    $id_ecriture = null;
    $numero_piece = 'ECHEANCE_' . $id_echeance;

    $date_saisie = new DateTime();
    // Use the ISO 8601 format 'Y-m-d\TH:i:s.v' for SQL Server DATETIME2 precision
    $date_saisie_formatted = $date_saisie->format('Y-m-d\TH:i:s.v'); 

    $description_ecriture = "Paiement échéance " . htmlspecialchars($echeance['Numero_Echeance'] ?? 'N/A') . " - Emprunt ID " . htmlspecialchars($echeance['ID_Emprunt'] ?? 'N/A') . " (" . htmlspecialchars($echeance['Numero_Pret'] ?? 'N/A') . ")";
    $montant_total_echeance_db = (float)($echeance['Montant_Echeance'] ?? 0); // Renamed for clarity (from DB)

    $stmtInsertEcriture = $pdo->prepare("
        INSERT INTO Ecritures (Date_Saisie, Description, Montant_Total, Cde, Numero_Piece, NumeroAgenceSCE, NomUtilisateur, Mois)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    try {
        $stmtInsertEcriture->execute([
            $date_saisie_formatted, // Fixed date format being passed
            $description_ecriture,
            round($montant_total_echeance_db, 2), // Rounding Montant_Total to 2 decimal places before insertion
            $journal_cde,
            $numero_piece,
            $numero_agence_sce,
            $nom_utilisateur,
            $date_saisie->format('Y-m') // This is only YYYY-MM, should be fine
        ]);
        $id_ecriture = $pdo->lastInsertId();
    } catch (PDOException $e) {
        $errorMsg = "Erreur lors de la création de l'écriture principale (Ecritures) : " . $e->getMessage() . " (Date format used: " . $date_saisie_formatted . ")";
        log_error($errorMsg);
        $accounting_error_details[] = $errorMsg;
        $response['message'] = "Erreur lors de la création de l'écriture principale.";
        $response['accounting_error'] = true;
        $id_ecriture = null; // Ensure ID is null if insert fails
    }

    // 4. Create Accounting Entry Lines (Lignes_Ecritures) - Only if main entry created successfully
    if ($id_ecriture && !$response['accounting_error']) {
        $lines_to_insert = [];

        // Amounts extracted from the fetched Echeance record from DB
        $amortissement_db = (float)($echeance['Amortissement'] ?? 0);
        $interet_sp_db = (float)($echeance['Interet_SP'] ?? 0);
        
        // Sum of all *other* fees/taxes from the Echeances_Amortissement table (excluding Interet_SP itself)
        $autres_frais_fixes_db = (float)($echeance['Taxes_Interet_SP'] ?? 0) +
                                 (float)($echeance['Comm_Engagement'] ?? 0) +
                                 (float)($echeance['Comm_Deblocage'] ?? 0) +
                                 (float)($echeance['Taxe_Comm_E'] ?? 0) +
                                 (float)($echeance['Taxe_Comm_D'] ?? 0) +
                                 (float)($echeance['Frais_Etude'] ?? 0) +
                                 (float)($echeance['Taxe_Frais_Etude'] ?? 0) +
                                 (float)($echeance['Taxe_Capital'] ?? 0);

        // --- Calculate amounts for each accounting line ---
        $montant_debit_capital = round(max(0, $amortissement_db), 2);
        
        $montant_debit_taxe_sur_interets = 0.0;
        $montant_debit_interet_principal = 0.0;

        // Apply the 19.25% rule for taxes if a tax account is provided
        if ($compte_charge_taxes > 0) {
            $montant_debit_taxe_sur_interets = round(max(0, $interet_sp_db * 0.1925), 2);
            // The remaining interest portion (80.75%) plus all other fixed fees go to the main interest account
            $montant_debit_interet_principal = round(max(0, $interet_sp_db * (1 - 0.1925) + $autres_frais_fixes_db), 2);
        } else {
            // If no specific tax account is provided, all interest and all other fees go to the main interest account
            $montant_debit_interet_principal = round(max(0, $interet_sp_db + $autres_frais_fixes_db), 2);
        }

        $montant_credit_banque = round(max(0, $montant_total_echeance_db), 2);

        // --- Balancing Adjustment ---
        $current_total_debits = $montant_debit_capital + $montant_debit_interet_principal + $montant_debit_taxe_sur_interets;
        $tolerance = 0.01; // Allow for small floating-point inaccuracies (e.g., 1 cent)

        $difference_to_balance = $montant_credit_banque - $current_total_debits;

        if (abs($difference_to_balance) > $tolerance) {
            // Adjust on the interest account, as it's typically the most flexible for small rounding differences
            $montant_debit_interet_principal = round($montant_debit_interet_principal + $difference_to_balance, 2);
            $warningMsg = "Avertissement: Ajustement d'arrondi sur le compte d'intérêt (" . number_format($difference_to_balance, 2) . ") pour l'échéance " . ($echeance['Numero_Echeance'] ?? 'N/A') . " de l'emprunt ID " . $id_emprunt . ".";
            log_error($warningMsg);
            $accounting_error_details[] = $warningMsg;
        }

        // Re-verify after adjustment for logging/debug, not strictly for control flow
        $final_total_debits_check = $montant_debit_capital + $montant_debit_interet_principal + $montant_debit_taxe_sur_interets;
        $final_difference_check = round($final_total_debits_check - $montant_credit_banque, 2);


        // --- Prepare lines for insertion ---

        // Debit: Principal (Amortissement)
        // Insert if amount > 0, or if 0 amort is expected (e.g., during deferred period)
        if ($montant_debit_capital > 0 || (strtolower($echeance['Type_Amortissement'] ?? '') === 'differe' && $montant_debit_capital == 0)) {
            $lines_to_insert[] = [
                'ID_Ecriture' => $id_ecriture,
                'ID_Compte' => $compte_dette_emprunt,
                'Montant' => $montant_debit_capital,
                'Sens' => 'D', // Débit: Réduction du passif (dette)
                'Libelle_Ligne' => 'Amortissement Capital Emprunt ' . htmlspecialchars($echeance['Numero_Pret'] ?? '')
            ];
        }

        // Debit: Interest (Intérêt principal + autres frais si compte taxes/frais non utilisé pour eux)
        // Insert if amount > 0 (or if it received an adjustment that made it > 0)
        if ($montant_debit_interet_principal > 0) {
            $lines_to_insert[] = [
                'ID_Ecriture' => $id_ecriture,
                'ID_Compte' => $compte_charge_interet,
                'Montant' => $montant_debit_interet_principal,
                'Sens' => 'D', // Débit: Enregistrement de charge
                'Libelle_Ligne' => 'Intérêts & Frais divers Emprunt ' . htmlspecialchars($echeance['Numero_Pret'] ?? '')
            ];
        }

        // Debit: Taxes/Frais (19.25% of interest) - Optional account
        // Insert this line ONLY if the account is explicitly provided (>0), even if the calculated amount is 0.
        // This ensures the line appears if the account is configured, fulfilling the user's request.
        if ($compte_charge_taxes > 0) {
            $lines_to_insert[] = [
                'ID_Ecriture' => $id_ecriture,
                'ID_Compte' => $compte_charge_taxes,
                'Montant' => $montant_debit_taxe_sur_interets, // Can be 0 if interet_sp_db was 0
                'Sens' => 'D', // Débit: Enregistrement de charge
                'Libelle_Ligne' => 'Taxe sur Intérêts (19.25%) Emprunt ' . htmlspecialchars($echeance['Numero_Pret'] ?? '')
            ];
        }


        // Credit: Total Payment (Montant_Echeance) - Bank Account
        if ($montant_credit_banque > 0) {
            $lines_to_insert[] = [
                'ID_Ecriture' => $id_ecriture,
                'ID_Compte' => $compte_banque,
                'Montant' => $montant_credit_banque,
                'Sens' => 'C', // Crédit: Diminution d'actif (trésorerie)
                'Libelle_Ligne' => 'Règlement Échéance ' . htmlspecialchars($echeance['Numero_Echeance'] ?? '') . ' Emprunt ' . htmlspecialchars($echeance['Numero_Pret'] ?? '')
            ];
        } else {
            $warningMsg = "Avertissement: Le Montant_Echeance est nul ou négatif (" . $montant_total_echeance_db . ") pour l'échéance " . ($echeance['Numero_Echeance'] ?? 'N/A') . " de l'emprunt ID " . $id_emprunt . ". Aucune écriture de crédit banque créée.";
            log_error($warningMsg);
            $accounting_error_details[] = $warningMsg;
        }

        // --- Final Balance Verification after all lines are prepared ---
        $total_debit_final = array_sum(array_column(filterBySens($lines_to_insert, 'D'), 'Montant'));
        $total_credit_final = array_sum(array_column(filterBySens($lines_to_insert, 'C'), 'Montant'));

        // Check for balance again with the final, rounded amounts
        if (abs($total_debit_final - $total_credit_final) > $tolerance) {
            $errorMsg = "Erreur de calcul comptable finale : Débits (" . number_format($total_debit_final, 2) . ") et Crédits (" . number_format($total_credit_final, 2) . ") ne s'équilibrent pas. Différence: " . number_format($total_debit_final - $total_credit_final, 2) . ".";
            log_error($errorMsg . " Details: " . json_encode($lines_to_insert));
            $response['accounting_error'] = true;
            $accounting_error_details[] = $errorMsg;
        }

        if (!empty($lines_to_insert) && !$response['accounting_error']) {
            $stmtInsertLigne = $pdo->prepare("
                INSERT INTO Lignes_Ecritures (ID_Ecriture, ID_Compte, Montant, Sens, Libelle_Ligne)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($lines_to_insert as $line) {
                try {
                    $stmtInsertLigne->execute([
                        $line['ID_Ecriture'],
                        $line['ID_Compte'],
                        round($line['Montant'], 2), // CRITICAL FIX: Round amount before binding to prevent conversion errors with tiny floats
                        $line['Sens'],
                        $line['Libelle_Ligne']
                    ]);
                } catch (PDOException $e) {
                    $errorMsg = "Erreur PDO lors de l'insertion d'une ligne d'écriture (Compte ID: " . ($line['ID_Compte'] ?? 'N/A') . ", Sens: " . ($line['Sens'] ?? 'N/A') . ", Montant: " . ($line['Montant'] ?? 'N/A') . ") : " . $e->getMessage();
                    log_error($errorMsg);
                    $response['accounting_error'] = true;
                    $accounting_error_details[] = $errorMsg;
                }
            }
        } else if (empty($lines_to_insert) && !$response['accounting_error']) { // Check if no lines to insert, but no other accounting error
            $warningMsg = "Aucune ligne d'écriture comptable n'a été générée pour l'échéance " . $id_echeance . " car tous les montants des composants étaient nuls.";
            log_error($warningMsg);
            $accounting_error_details[] = $warningMsg;
            // Decide if this should be an error or a success. If a zero-value installment is 'paid', it might be a success from a system perspective.
            // For now, setting it as an accounting error for attention.
            $response['accounting_error'] = true;
        }
    } else {
        if (empty($accounting_error_details)) { // Only add if no previous error message
            $accounting_error_details[] = "Lignes d'écriture non créées car l'écriture principale n'a pas été générée ou une erreur comptable est survenue.";
        }
    }

    // 5. Update the Echeance with the Accounting Entry ID AND the Status ID
    if (!empty($accounting_error_details)) {
        // If there were any errors/warnings collected during accounting, rollback
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            log_error("Transaction annulée en raison d'erreurs comptables pour échéance ID: " . $id_echeance);
        }
        $response['status'] = 'error';
        // Concatenate all accounting error details to the response message
        $response['message'] = "Échec de la comptabilisation de l'échéance en raison d'erreurs comptables. " . implode('; ', array_unique($accounting_error_details));
        if ($id_ecriture) {
             // Corrected: Using $id_ecriture as it's the variable holding the last inserted ID
            $response['message'] .= " Une écriture comptable partielle (ID: " . $id_ecriture . ") a pu être créée et sera annulée.";
        }
    } else {
        // If all accounting steps were successful, proceed with updating echeance and committing
        $stmtUpdateEcheance = $pdo->prepare("
            UPDATE Echeances_Amortissement
            SET ID_Ecriture_Comptable = ?, ID_Statut = ?
            WHERE ID_Echeance = ?
        ");

        try {
            $stmtUpdateEcheance->execute([$id_ecriture, $id_statut_comptabilisee, $id_echeance]);
            $rows_updated = $stmtUpdateEcheance->rowCount();

            if ($rows_updated === 0) {
                $pdo->rollBack();
                $errorMessage = "Erreur critique : L'échéance " . $id_echeance . " n'a pas été mise à jour (probablement non trouvée à nouveau ou déjà mise à jour).";
                log_error($errorMessage);
                $response['status'] = 'error';
                $response['message'] = $errorMessage;
            } else {
                $pdo->commit(); // Commit only if all steps were successful
                $response['status'] = 'success';
                $response['message'] = 'Échéance marquée comme comptabilisée.';
                $response['id_ecriture'] = $id_ecriture;
                if ($id_ecriture) {
                    $response['message'] .= ' Écriture comptable créée (ID: ' . $id_ecriture . ').';
                }
                log_error("Échéance ID " . $id_echeance . " comptabilisée avec succès. ID_Ecriture: " . $id_ecriture); // Log success
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessage = "Erreur critique lors de la mise à jour du statut de l'échéance : " . $e->getMessage();
            log_error($errorMessage);
            $response['status'] = 'error';
            $response['message'] = $errorMessage;
        }
    }

} catch (Exception $e) {
    // Catch any unexpected exceptions not caught by PDOException
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errorMessage = "Une erreur inattendue est survenue : " . $e->getMessage();
    log_error($errorMessage);
    $response['status'] = 'error';
    $response['message'] = $errorMessage;
}

echo json_encode($response);
?>
