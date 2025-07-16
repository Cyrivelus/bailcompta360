<?php

require_once 'database.php'; // Ensure your database connection is accessible
require_once 'gestion_ecritures.php'; // Assuming functions for creating/modifying entries are here
require_once 'gestion_logs.php'; // Assuming a logging function is available

/**
 * Retrieves un-reconciled bank statement lines for a specific bank account.
 * These lines would typically come from an import process (e.g., from gestion_import_statements).
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $bankAccountId The ID of the bank account.
 * @param string|null $startDate Optional: start date for filtering (YYYY-MM-DD).
 * @param string|null $endDate Optional: end date for filtering (YYYY-MM-DD).
 * @return array An array of un-reconciled bank statement lines.
 */
function getUnreconciledBankLines(PDO $pdo, int $bankAccountId, ?string $startDate = null, ?string $endDate = null): array {
    $sql = "
        SELECT
            id_bank_statement_line, -- Assuming a table for imported bank statement lines
            date_transaction,
            description_transaction,
            montant_debit,
            montant_credit,
            balance_bank,
            is_reconciled,
            compte_bancaire_id
        FROM
            Bank_Statement_Lines -- This table needs to exist and store imported lines
        WHERE
            compte_bancaire_id = :bank_account_id
            AND is_reconciled = 0
    ";

    $params = [':bank_account_id' => $bankAccountId];

    if ($startDate) {
        $sql .= " AND date_transaction >= :start_date";
        $params[':start_date'] = $startDate;
    }
    if ($endDate) {
        $sql .= " AND date_transaction <= :end_date";
        $params[':end_date'] = $endDate;
    }

    $sql .= " ORDER BY date_transaction ASC, id_bank_statement_line ASC;";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Erreur PDO lors de la récupération des lignes de relevé non rapprochées: " . $e->getMessage());
        return [];
    }
}

/**
 * Retrieves un-reconciled ledger entries for a specific bank account within a period.
 * These are entries in your internal accounting system that haven't been matched.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $bankAccountId The ID of the bank account (your internal account).
 * @param string|null $startDate Optional: start date for filtering (YYYY-MM-DD).
 * @param string|null $endDate Optional: end date for filtering (YYYY-MM-DD).
 * @return array An array of un-reconciled ledger entries.
 */
function getUnreconciledLedgerEntries(PDO $pdo, int $bankAccountId, ?string $startDate = null, ?string $endDate = null): array {
    $sql = "
        SELECT
            le.ID_Ligne AS id_ligne_ecriture,
            e.ID_Ecriture AS id_ecriture,
            e.Date_Saisie AS date_saisie,
            e.Description,
            le.Montant,
            le.Sens,
            le.is_reconciled -- Assuming a flag 'is_reconciled' on Lignes_Ecritures
        FROM
            Lignes_Ecritures le
        JOIN
            Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
        WHERE
            le.ID_Compte = :bank_account_id
            AND le.is_reconciled = 0
    ";

    $params = [':bank_account_id' => $bankAccountId];

    if ($startDate) {
        $sql .= " AND e.Date_Saisie >= :start_date";
        $params[':start_date'] = $startDate;
    }
    if ($endDate) {
        $sql .= " AND e.Date_Saisie <= :end_date";
        $params[':end_date'] = $endDate;
    }

    $sql .= " ORDER BY e.Date_Saisie ASC, le.ID_Ligne ASC;";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Erreur PDO lors de la récupération des écritures du grand livre non rapprochées: " . $e->getMessage());
        return [];
    }
}

/**
 * Attempts to automatically suggest matches between bank lines and ledger entries.
 * This is a heuristic approach and needs refinement based on specific business rules.
 *
 * @param array $bankLines Array of bank statement lines.
 * @param array $ledgerEntries Array of ledger entries.
 * @param float $tolerance Amount tolerance for matching (e.g., 0.01 for 1 cent difference).
 * @param int $dayTolerance Number of days tolerance for date matching.
 * @return array An array of suggested matches. Each match contains 'bank_line' and 'ledger_entry'.
 */
function suggestReconciliationMatches(array $bankLines, array $ledgerEntries, float $tolerance = 0.00, int $dayTolerance = 3): array {
    $suggestedMatches = [];
    $matchedBankLineIds = [];
    $matchedLedgerEntryIds = [];

    // Create a copy of arrays to modify as we match
    $tempBankLines = $bankLines;
    $tempLedgerEntries = $ledgerEntries;

    foreach ($tempBankLines as $bankKey => $bankLine) {
        if (in_array($bankLine['id_bank_statement_line'], $matchedBankLineIds)) continue;

        $bankAmount = $bankLine['montant_credit'] - $bankLine['montant_debit']; // Net effect on bank balance
        $bankDate = new DateTime($bankLine['date_transaction']);

        foreach ($tempLedgerEntries as $ledgerKey => $ledgerEntry) {
            if (in_array($ledgerEntry['id_ligne_ecriture'], $matchedLedgerEntryIds)) continue;

            $ledgerAmount = ($ledgerEntry['Sens'] === 'D') ? -$ledgerEntry['Montant'] : $ledgerEntry['Montant']; // Net effect on ledger balance
            $ledgerDate = new DateTime($ledgerEntry['date_saisie']);

            // Match condition 1: Amounts are close (within tolerance)
            $amountMatch = abs($bankAmount - $ledgerAmount) <= $tolerance;

            // Match condition 2: Dates are close (within dayTolerance)
            $dateDiff = abs($bankDate->diff($ledgerDate)->days);
            $dateMatch = $dateDiff <= $dayTolerance;

            // Match condition 3: Descriptions might contain keywords (more complex, consider later)
            // $descriptionMatch = (stripos($bankLine['description_transaction'], $ledgerEntry['Description']) !== false) || ...;

            if ($amountMatch && $dateMatch) { // Add description matching as needed
                $suggestedMatches[] = [
                    'bank_line' => $bankLine,
                    'ledger_entry' => $ledgerEntry
                ];
                // Mark as matched to avoid re-matching
                $matchedBankLineIds[] = $bankLine['id_bank_statement_line'];
                $matchedLedgerEntryIds[] = $ledgerEntry['id_ligne_ecriture'];
                break; // Move to the next bank line
            }
        }
    }

    return $suggestedMatches;
}

/**
 * Records a confirmed reconciliation match in the database.
 * This marks both the bank statement line and the ledger entry as reconciled.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $bankStatementLineId The ID of the bank statement line.
 * @param int $ledgerEntryLineId The ID of the Lignes_Ecritures entry.
 * @param int $userId The ID of the user performing the reconciliation.
 * @return bool True on success, false on failure.
 */
function confirmReconciliationMatch(PDO $pdo, int $bankStatementLineId, int $ledgerEntryLineId, int $userId): bool {
    try {
        $pdo->beginTransaction();

        // Mark bank statement line as reconciled
        $stmtBank = $pdo->prepare("UPDATE Bank_Statement_Lines SET is_reconciled = 1, reconciled_at = NOW(), reconciled_by = :user_id WHERE id_bank_statement_line = :id_line");
        $stmtBank->bindParam(':id_line', $bankStatementLineId, PDO::PARAM_INT);
        $stmtBank->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtBank->execute();

        // Mark ledger entry line as reconciled
        $stmtLedger = $pdo->prepare("UPDATE Lignes_Ecritures SET is_reconciled = 1, reconciled_at = NOW(), reconciled_by = :user_id WHERE ID_Ligne = :id_ligne");
        $stmtLedger->bindParam(':id_ligne', $ledgerEntryLineId, PDO::PARAM_INT);
        $stmtLedger->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmtLedger->execute();

        $pdo->commit();
        logActivity("Rapprochement confirmé: Ligne relevé ID {$bankStatementLineId}, Ligne écriture ID {$ledgerEntryLineId} par Utilisateur ID {$userId}");
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        logError("Erreur PDO lors de la confirmation du rapprochement: " . $e->getMessage() . " - Ligne relevé ID {$bankStatementLineId}, Ligne écriture ID {$ledgerEntryLineId}");
        return false;
    }
}

/**
 * Handles an unmatched bank statement line by creating a new ledger entry for it.
 * This is useful for bank charges, interest, etc., that appear on the statement but not yet in your books.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param array $bankLine The bank statement line data.
 * @param int $bankAccountId The ID of the internal bank account.
 * @param int $journalId The ID of the journal (e.g., 'BQ' for bank).
 * @param int $userId The ID of the user creating the entry.
 * @param int $contraAccountId The ID of the contra account (e.g., bank charges expense account).
 * @return bool True on success, false on failure.
 */
function createLedgerEntryForBankLine(PDO $pdo, array $bankLine, int $bankAccountId, int $journalId, int $userId, int $contraAccountId): bool {
    try {
        $pdo->beginTransaction();

        $amount = $bankLine['montant_debit'] > 0 ? $bankLine['montant_debit'] : $bankLine['montant_credit'];
        $description = "Relevé bancaire: " . $bankLine['description_transaction'];

        // Determine debit/credit for the new entry
        // If bank line is a debit (money leaving bank), then bank account is credited, contra account debited.
        // If bank line is a credit (money entering bank), then bank account is debited, contra account credited.
        $lignes_ecriture = [];
        if ($bankLine['montant_debit'] > 0) { // Bank debit (e.g., bank charges)
            $lignes_ecriture[] = ['ID_Compte' => $contraAccountId, 'Sens' => 'D', 'Montant' => $amount];
            $lignes_ecriture[] = ['ID_Compte' => $bankAccountId, 'Sens' => 'C', 'Montant' => $amount];
        } else { // Bank credit (e.g., interest received)
            $lignes_ecriture[] = ['ID_Compte' => $bankAccountId, 'Sens' => 'D', 'Montant' => $amount];
            $lignes_ecriture[] = ['ID_Compte' => $contraAccountId, 'Sens' => 'C', 'Montant' => $amount];
        }

        // Call a function from gestion_ecritures.php to create the entry
        // This assumes addJournalEntry is capable of handling multiple lines.
        $entryAdded = addJournalEntry($pdo, $bankLine['date_transaction'], $description, $journalId, $userId, $lignes_ecriture);

        if ($entryAdded) {
            // Mark the bank statement line as reconciled
            $stmtBank = $pdo->prepare("UPDATE Bank_Statement_Lines SET is_reconciled = 1, reconciled_at = NOW(), reconciled_by = :user_id WHERE id_bank_statement_line = :id_line");
            $stmtBank->bindParam(':id_line', $bankLine['id_bank_statement_line'], PDO::PARAM_INT);
            $stmtBank->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmtBank->execute();

            $pdo->commit();
            logActivity("Nouvelle écriture créée pour ligne relevé ID {$bankLine['id_bank_statement_line']} par Utilisateur ID {$userId}");
            return true;
        } else {
            $pdo->rollBack();
            return false; // addJournalEntry failed
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        logError("Erreur lors de la création d'écriture pour ligne relevé non rapprochée: " . $e->getMessage() . " - Ligne relevé ID {$bankLine['id_bank_statement_line']}");
        return false;
    }
}

/**
 * Handles an unmatched ledger entry by creating a new bank statement line equivalent.
 * This is useful for transactions recorded in your books that haven't yet appeared on the bank statement.
 * (Less common in practice for direct reconciliation, often handled by waiting for next statement or flagging as "outstanding").
 *
 * @param PDO $pdo The PDO database connection object.
 * @param array $ledgerEntry The ledger entry data.
 * @param int $bankAccountId The ID of the internal bank account.
 * @param int $userId The ID of the user.
 * @return bool True on success, false on failure.
 */
function createBankLineForLedgerEntry(PDO $pdo, array $ledgerEntry, int $bankAccountId, int $userId): bool {
    // This function is less common for direct "creation" during reconciliation.
    // Usually, unmatched ledger entries are considered "outstanding" and wait for the next statement.
    // If you need to create a dummy bank line for a specific purpose (e.g., manual adjustment),
    // you would insert into Bank_Statement_Lines table.
    // For now, let's just log and return false as this isn't standard reconciliation flow.
    logWarning("Tentative de création de ligne de relevé pour écriture du grand livre non rapprochée - Non implémenté comme solution standard.");
    return false;
}




?>