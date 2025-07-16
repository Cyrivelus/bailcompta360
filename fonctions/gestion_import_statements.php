<?php

require_once 'database.php'; // Ensure your database connection is accessible
require_once 'gestion_ecritures.php'; // Assuming you have functions to create/manage entries

/**
 * Handles the upload of a bank statement file.
 *
 * @param array $file_info The $_FILES array entry for the uploaded file.
 * @param string $upload_dir The directory where the file should be moved.
 * @return array An array with 'success' (bool) and 'message' (string), and 'file_path' if successful.
 */
function handleBankStatementUpload(array $file_info, string $upload_dir): array {
    if (!isset($file_info['name']) || $file_info['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Erreur lors de l\'upload du fichier : ' . $file_info['error']];
    }

    $allowed_extensions = ['csv', 'ofx', 'qfx', 'txt']; // Add other formats as needed
    $file_extension = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));

    if (!in_array($file_extension, $allowed_extensions)) {
        return ['success' => false, 'message' => 'Type de fichier non autorisé. Extensions supportées : ' . implode(', ', $allowed_extensions)];
    }

    // Generate a unique file name to prevent conflicts
    $fileName = uniqid('bank_statement_') . '.' . $file_extension;
    $filePath = $upload_dir . $fileName;

    if (!move_uploaded_file($file_info['tmp_name'], $filePath)) {
        return ['success' => false, 'message' => 'Impossible de déplacer le fichier uploadé.'];
    }

    return ['success' => true, 'message' => 'Fichier uploadé avec succès.', 'file_path' => $filePath, 'file_type' => $file_extension];
}

/**
 * Parses a CSV bank statement file.
 * Assumes a common CSV structure (e.g., Date, Description, Debit, Credit, Balance).
 * You WILL need to customize this heavily based on the actual CSV format from your bank.
 *
 * @param string $filePath The path to the CSV file.
 * @param int $bankAccountId The ID of the bank account this statement belongs to.
 * @return array An array of parsed transactions, or an empty array on failure.
 */
function parseCsvBankStatement(string $filePath, int $bankAccountId): array {
    $transactions = [];
    if (!file_exists($filePath) || !is_readable($filePath)) {
        error_log("Fichier CSV non trouvé ou illisible : " . $filePath);
        return [];
    }

    // Open the CSV file
    if (($handle = fopen($filePath, 'r')) !== FALSE) {
        $row_number = 0;
        // Optionally skip header row
        $header = fgetcsv($handle, 1000, ';'); // Assuming semicolon delimiter

        while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
            $row_number++;
            // Basic validation: ensure row has expected number of columns
            // This example assumes 4 columns: Date, Description, Debit, Credit
            if (count($data) < 4) {
                error_log("Ligne CSV ignorée (colonnes insuffisantes) dans " . $filePath . " à la ligne " . $row_number);
                continue;
            }

            // --- CUSTOMIZATION REQUIRED HERE ---
            // Map CSV columns to your desired fields.
            // Example mapping:
            // $data[0] = Date (e.g., 'DD/MM/YYYY' or 'YYYY-MM-DD')
            // $data[1] = Description
            // $data[2] = Debit (e.g., '100.00' or '100,00')
            // $data[3] = Credit (e.g., '50.00' or '50,00')
            // --- END CUSTOMIZATION ---

            $transactionDate = DateTime::createFromFormat('d/m/Y', trim($data[0])); // Adjust format as needed
            if (!$transactionDate) {
                 $transactionDate = DateTime::createFromFormat('Y-m-d', trim($data[0])); // Try another format
                 if (!$transactionDate) {
                    error_log("Date invalide dans CSV : " . $data[0] . " à la ligne " . $row_number);
                    continue;
                 }
            }

            $description = htmlspecialchars(trim($data[1]));
            $debit = (float)str_replace(',', '.', trim($data[2])); // Convert comma decimal to dot decimal
            $credit = (float)str_replace(',', '.', trim($data[3]));

            // Only add if there's a monetary value
            if ($debit > 0 || $credit > 0) {
                $transactions[] = [
                    'bank_account_id' => $bankAccountId,
                    'date' => $transactionDate->format('Y-m-d'),
                    'description' => $description,
                    'debit' => $debit,
                    'credit' => $credit,
                    'original_line' => implode(';', $data), // Keep original line for debugging
                    // Add other fields as needed, e.g., 'reference', 'check_number'
                ];
            }
        }
        fclose($handle);
    } else {
        error_log("Impossible d'ouvrir le fichier CSV : " . $filePath);
    }

    return $transactions;
}

/**
 * Placeholder for parsing OFX/QFX files.
 * This would typically involve a dedicated OFX parsing library.
 *
 * @param string $filePath The path to the OFX/QFX file.
 * @param int $bankAccountId The ID of the bank account.
 * @return array An array of parsed transactions.
 */
function parseOfxBankStatement(string $filePath, int $bankAccountId): array {
    // You'd integrate an OFX parser library here, e.g.:
    // require_once __DIR__ . '/../librairies/ofx_parser/OfxParser.php';
    // $parser = new OfxParser($filePath);
    // return $parser->getTransactions();
    error_log("Parsing OFX/QFX n'est pas encore implémenté.");
    return [];
}

/**
 * Placeholder for parsing MT940 files.
 * This would typically involve a dedicated MT940 parsing library.
 *
 * @param string $filePath The path to the MT940 file.
 * @param int $bankAccountId The ID of the bank account.
 * @return array An array of parsed transactions.
 */
function parseMt940BankStatement(string $filePath, int $bankAccountId): array {
    // Integrate an MT940 parser library here.
    error_log("Parsing MT940 n'est pas encore implémenté.");
    return [];
}

/**
 * Suggests accounting entries based on parsed bank transactions.
 * This is where the "intelligence" of the system comes in.
 *
 * @param array $bankTransactions An array of parsed bank transaction data.
 * @param PDO $pdo The PDO database connection object.
 * @return array An array of suggested journal entries.
 */
function suggestAccountingEntries(array $bankTransactions, PDO $pdo): array {
    $suggestedEntries = [];
    $compteBancaireId = null; // Will be set from the first transaction's bank_account_id

    if (empty($bankTransactions)) {
        return [];
    }

    $compteBancaireId = $bankTransactions[0]['bank_account_id'];
    
    // Get the account number for the bank account
    $stmt = $pdo->prepare("SELECT Numero_Compte FROM Comptes_compta WHERE ID_Compte = :id");
    $stmt->bindParam(':id', $compteBancaireId, PDO::PARAM_INT);
    $stmt->execute();
    $bankAccountNum = $stmt->fetchColumn();


    foreach ($bankTransactions as $trans) {
        $description = $trans['description'];
        $debit = $trans['debit'];
        $credit = $trans['credit'];
        $suggestedDebitAccount = null;
        $suggestedCreditAccount = null;
        $amount = 0.00;

        // Determine the amount and the primary account (bank account)
        if ($debit > 0) {
            $amount = $debit;
            // Bank account is credited (money leaves the bank)
            $suggestedCreditAccount = ['id' => $compteBancaireId, 'numero' => $bankAccountNum];
        } elseif ($credit > 0) {
            $amount = $credit;
            // Bank account is debited (money enters the bank)
            $suggestedDebitAccount = ['id' => $compteBancaireId, 'numero' => $bankAccountNum];
        } else {
            continue; // Skip transactions with no amount
        }

        // --- INTELLIGENT SUGGESTION LOGIC (CUSTOMIZATION REQUIRED) ---
        // This is where you implement rules to suggest contra-accounts.
        // Examples:
        // 1. Keyword matching:
        //    - If description contains "salaire", suggest "421000 - Salaires" (employee account or expense)
        //    - If description contains "loyer", suggest "613000 - Loyers et Charges Locatives"
        //    - If description contains "fournisseur X", suggest the corresponding supplier account (e.g., from Tiers table)
        // 2. Machine Learning: More advanced systems might learn from past classifications.
        // 3. User-defined rules: Allow users to define their own rules (e.g., "Any transaction from 'PAYPAL *' -> Account 627000").
        // 4. Pattern recognition: Recurring transactions.

        $isDebitEntry = ($debit > 0); // Is this transaction a debit from the bank's perspective?

        // Basic keyword matching examples (adjust account numbers to your chart of accounts)
        if (stripos($description, 'loyer') !== false || stripos($description, 'rent') !== false) {
            if ($isDebitEntry) { // Bank debit -> expense
                $suggestedDebitAccount = ['id' => null, 'numero' => '613000', 'nom' => 'Loyers et charges locatives'];
            } else { // Bank credit -> income (e.g., rent received)
                $suggestedCreditAccount = ['id' => null, 'numero' => '706000', 'nom' => 'Produits des services']; // Example
            }
        } elseif (stripos($description, 'salaire') !== false || stripos($description, 'payroll') !== false) {
            if ($isDebitEntry) { // Bank debit -> expense
                $suggestedDebitAccount = ['id' => null, 'numero' => '641000', 'nom' => 'Charges de personnel'];
            }
        } elseif (stripos($description, 'edf') !== false || stripos($description, 'électricité') !== false) {
            if ($isDebitEntry) {
                $suggestedDebitAccount = ['id' => null, 'numero' => '606100', 'nom' => 'Fournitures non stockables (Eau, Gaz, Électricité)'];
            }
        } elseif (stripos($description, 'client') !== false || stripos($description, 'facture') !== false) {
            if ($isDebitEntry) { // Bank debit -> payment to supplier (e.g., Accounts Payable)
                 $suggestedDebitAccount = ['id' => null, 'numero' => '401000', 'nom' => 'Fournisseurs'];
            } else { // Bank credit -> payment from client (e.g., Accounts Receivable)
                $suggestedCreditAccount = ['id' => null, 'numero' => '411000', 'nom' => 'Clients'];
            }
        } else {
            // Default "waiting" account if no specific rule matches
            if ($isDebitEntry) {
                 $suggestedDebitAccount = ['id' => null, 'numero' => '471000', 'nom' => 'Comptes d\'attente (débit)']; // To be reviewed
            } else {
                $suggestedCreditAccount = ['id' => null, 'numero' => '472000', 'nom' => 'Comptes d\'attente (crédit)']; // To be reviewed
            }
        }

        // Ensure the bank account is always included and correctly sens'd
        // If bank is debited, the other side is a credit. If bank is credited, the other side is a debit.
        $lignes = [];
        if ($isDebitEntry) { // Money leaves bank (Bank Credit, Other Debit)
            $lignes[] = ['compte' => $suggestedDebitAccount, 'sens' => 'D', 'montant' => $amount];
            $lignes[] = ['compte' => ['id' => $compteBancaireId, 'numero' => $bankAccountNum], 'sens' => 'C', 'montant' => $amount];
        } else { // Money enters bank (Bank Debit, Other Credit)
            $lignes[] = ['compte' => ['id' => $compteBancaireId, 'numero' => $bankAccountNum], 'sens' => 'D', 'montant' => $amount];
            $lignes[] = ['compte' => $suggestedCreditAccount, 'sens' => 'C', 'montant' => $amount];
        }


        $suggestedEntries[] = [
            'date_saisie' => $trans['date'],
            'description' => $trans['description'],
            'montant_total' => $amount,
            'lignes_ecriture' => $lignes,
            'original_transaction_data' => $trans // Keep original data for reference
        ];
    }

    return $suggestedEntries;
}

/**
 * Saves suggested accounting entries into the database.
 * This function should ideally be called after user review/confirmation.
 *
 * @param array $suggestedEntries An array of suggested entries from suggestAccountingEntries.
 * @param PDO $pdo The PDO database connection object.
 * @param int $journalId The ID of the journal (e.g., 'BQ' for bank) where entries should be posted.
 * @param int $userId The ID of the user performing the import.
 * @return array An array of results, indicating success/failure for each entry.
 */
function saveSuggestedEntries(array $suggestedEntries, PDO $pdo, int $journalId, int $userId): array {
    $results = [];
    foreach ($suggestedEntries as $entryData) {
        try {
            // Start a transaction for atomicity
            $pdo->beginTransaction();

            // Insert into Ecritures table
            $stmt = $pdo->prepare("INSERT INTO Ecritures (Date_Saisie, Description, ID_Journal, ID_Utilisateur) VALUES (:date_saisie, :description, :id_journal, :id_utilisateur)");
            $stmt->bindParam(':date_saisie', $entryData['date_saisie'], PDO::PARAM_STR);
            $stmt->bindParam(':description', $entryData['description'], PDO::PARAM_STR);
            $stmt->bindParam(':id_journal', $journalId, PDO::PARAM_INT);
            $stmt->bindParam(':id_utilisateur', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $ecritureId = $pdo->lastInsertId();

            // Insert into Lignes_Ecritures table for each line
            $stmt_ligne = $pdo->prepare("INSERT INTO Lignes_Ecritures (ID_Ecriture, ID_Compte, Sens, Montant) VALUES (:id_ecriture, :id_compte, :sens, :montant)");

            foreach ($entryData['lignes_ecriture'] as $ligne) {
                // You might need to fetch ID_Compte from Numero_Compte if ID is null
                $actualCompteId = $ligne['compte']['id'];
                if (empty($actualCompteId) && isset($ligne['compte']['numero'])) {
                    $stmt_fetch_id = $pdo->prepare("SELECT ID_Compte FROM Comptes_compta WHERE Numero_Compte = :numero_compte");
                    $stmt_fetch_id->bindParam(':numero_compte', $ligne['compte']['numero'], PDO::PARAM_STR);
                    $stmt_fetch_id->execute();
                    $actualCompteId = $stmt_fetch_id->fetchColumn();
                }

                if (!$actualCompteId) {
                    throw new Exception("Compte introuvable pour le numéro: " . ($ligne['compte']['numero'] ?? 'N/A'));
                }

                $stmt_ligne->bindParam(':id_ecriture', $ecritureId, PDO::PARAM_INT);
                $stmt_ligne->bindParam(':id_compte', $actualCompteId, PDO::PARAM_INT);
                $stmt_ligne->bindParam(':sens', $ligne['sens'], PDO::PARAM_STR);
                $stmt_ligne->bindParam(':montant', $ligne['montant'], PDO::PARAM_STR); // Store as string for precision
                $stmt_ligne->execute();
            }

            $pdo->commit();
            $results[] = ['success' => true, 'message' => 'Écriture importée avec succès pour: ' . $entryData['description']];

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erreur lors de l'enregistrement de l'écriture importée: " . $e->getMessage() . " - Transaction: " . json_encode($entryData['original_transaction_data']));
            $results[] = ['success' => false, 'message' => 'Échec de l\'importation pour: ' . $entryData['description'] . ' - Erreur: ' . $e->getMessage()];
        }
    }
    return $results;
}