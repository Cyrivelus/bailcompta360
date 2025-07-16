<?php

require_once 'database.php'; // Ensure your database connection is accessible
require_once 'gestion_logs.php'; // For logging actions and errors

/**
 * Adds a new budget entry to the database.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $accountId The ID of the accounting account (Comptes_compta) for which the budget is set.
 * @param string $budgetPeriodType The type of period (e.g., 'monthly', 'quarterly', 'annually').
 * @param string $startDate The start date of the budget period (YYYY-MM-DD).
 * @param string $endDate The end date of the budget period (YYYY-MM-DD).
 * @param float $budgetAmount The budgeted amount for the period.
 * @param int $userId The ID of the user creating the budget.
 * @return int|false The ID of the newly created budget entry, or false on failure.
 */
function addBudget(
    PDO $pdo,
    int $accountId,
    string $budgetPeriodType,
    string $startDate,
    string $endDate,
    float $budgetAmount,
    int $userId
): int|false {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO Budgets
            (id_compte, period_type, start_date, end_date, budget_amount, created_by, created_at)
            VALUES (:id_compte, :period_type, :start_date, :end_date, :budget_amount, :created_by, NOW())
        ");
        $stmt->bindParam(':id_compte', $accountId, PDO::PARAM_INT);
        $stmt->bindParam(':period_type', $budgetPeriodType, PDO::PARAM_STR);
        $stmt->bindParam(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->bindParam(':end_date', $endDate, PDO::PARAM_STR);
        $stmt->bindParam(':budget_amount', $budgetAmount, PDO::PARAM_STR); // Store as string for precision
        $stmt->bindParam(':created_by', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $budgetId = $pdo->lastInsertId();

        $pdo->commit();
        logActivity("Budget ID {$budgetId} ajouté pour le compte ID {$accountId} par Utilisateur ID {$userId}.");
        return (int)$budgetId;

    } catch (PDOException $e) {
        $pdo->rollBack();
        logError("Erreur PDO lors de l'ajout du budget: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        $pdo->rollBack();
        logError("Erreur lors de l'ajout du budget: " . $e->getMessage());
        return false;
    }
}

/**
 * Retrieves budget entries based on criteria.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int|null $budgetId Optional: ID of a specific budget to retrieve.
 * @param int|null $accountId Optional: Filter by specific accounting account ID.
 * @param string|null $periodType Optional: Filter by period type.
 * @param string|null $dateWithinPeriod Optional: Date to check if it falls within budget period (YYYY-MM-DD).
 * @return array An array of budget entries.
 */
function getBudgets(
    PDO $pdo,
    ?int $budgetId = null,
    ?int $accountId = null,
    ?string $periodType = null,
    ?string $dateWithinPeriod = null
): array {
    $sql = "
        SELECT
            b.id_budget,
            b.id_compte,
            cc.Numero_Compte,
            cc.Nom_Compte,
            b.period_type,
            b.start_date,
            b.end_date,
            b.budget_amount,
            b.is_active,
            b.created_by,
            u.Nom_Utilisateur AS created_by_name
        FROM
            Budgets b
        JOIN
            Comptes_compta cc ON b.id_compte = cc.ID_Compte
        JOIN
            Utilisateurs u ON b.created_by = u.ID_Utilisateur
    ";
    $params = [];
    $where = [];

    if ($budgetId !== null) {
        $where[] = "b.id_budget = :id_budget";
        $params[':id_budget'] = $budgetId;
    }
    if ($accountId !== null) {
        $where[] = "b.id_compte = :id_compte";
        $params[':id_compte'] = $accountId;
    }
    if ($periodType !== null) {
        $where[] = "b.period_type = :period_type";
        $params[':period_type'] = $periodType;
    }
    if ($dateWithinPeriod !== null) {
        $where[] = "b.start_date <= :date_within_period AND b.end_date >= :date_within_period";
        $params[':date_within_period'] = $dateWithinPeriod;
    }

    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY b.start_date DESC, cc.Numero_Compte ASC;";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Erreur PDO lors de la récupération des budgets: " . $e->getMessage());
        return [];
    }
}

/**
 * Updates an existing budget entry.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $budgetId The ID of the budget to update.
 * @param int $accountId The new account ID.
 * @param string $budgetPeriodType The new period type.
 * @param string $startDate The new start date.
 * @param string $endDate The new end date.
 * @param float $budgetAmount The new budgeted amount.
 * @param int $userId The ID of the user performing the update.
 * @param bool $isActive Whether the budget is active.
 * @return bool True on success, false on failure.
 */
function updateBudget(
    PDO $pdo,
    int $budgetId,
    int $accountId,
    string $budgetPeriodType,
    string $startDate,
    string $endDate,
    float $budgetAmount,
    int $userId,
    bool $isActive
): bool {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE Budgets SET
                id_compte = :id_compte,
                period_type = :period_type,
                start_date = :start_date,
                end_date = :end_date,
                budget_amount = :budget_amount,
                is_active = :is_active,
                updated_by = :updated_by,
                updated_at = NOW()
            WHERE id_budget = :id_budget
        ");
        $stmt->bindParam(':id_compte', $accountId, PDO::PARAM_INT);
        $stmt->bindParam(':period_type', $budgetPeriodType, PDO::PARAM_STR);
        $stmt->bindParam(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->bindParam(':end_date', $endDate, PDO::PARAM_STR);
        $stmt->bindParam(':budget_amount', $budgetAmount, PDO::PARAM_STR);
        $stmt->bindParam(':is_active', $isActive, PDO::PARAM_BOOL);
        $stmt->bindParam(':updated_by', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':id_budget', $budgetId, PDO::PARAM_INT);
        $stmt->execute();

        $pdo->commit();
        logActivity("Budget ID {$budgetId} mis à jour pour le compte ID {$accountId} par Utilisateur ID {$userId}.");
        return true;

    } catch (PDOException $e) {
        $pdo->rollBack();
        logError("Erreur PDO lors de la mise à jour du budget: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        $pdo->rollBack();
        logError("Erreur lors de la mise à jour du budget: " . $e->getMessage());
        return false;
    }
}

/**
 * Deletes a budget entry.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $budgetId The ID of the budget to delete.
 * @return bool True on success, false on failure.
 */
function deleteBudget(PDO $pdo, int $budgetId): bool {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM Budgets WHERE id_budget = :id_budget");
        $stmt->bindParam(':id_budget', $budgetId, PDO::PARAM_INT);
        $stmt->execute();

        $pdo->commit();
        logActivity("Budget ID {$budgetId} supprimé.");
        return true;

    } catch (PDOException $e) {
        $pdo->rollBack();
        logError("Erreur PDO lors de la suppression du budget: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        $pdo->rollBack();
        logError("Erreur lors de la suppression du budget: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculates the actual expenses/revenues for a given account within a specific period.
 * This function depends on your Lignes_Ecritures table structure and the nature of the account.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $accountId The ID of the accounting account.
 * @param string $startDate The start date of the period (YYYY-MM-DD).
 * @param string $endDate The end date of the period (YYYY-MM-DD).
 * @return float The total actual amount (positive for revenues, negative for expenses).
 */
function getActualAmountForAccount(PDO $pdo, int $accountId, string $startDate, string $endDate): float {
    // Determine the nature of the account (revenue or expense) to correctly sum debits/credits.
    // This assumes you have a way to identify account types, e.g., from Numero_Compte or Type_Compte.
    // For simplicity, we'll assume:
    // - Revenue accounts (e.g., 7xxx) increase with Credit, decrease with Debit.
    // - Expense accounts (e.g., 6xxx) increase with Debit, decrease with Credit.

    $accountDetails = getAccountDetails($pdo, $accountId); // Assuming a function to get account details
    if (!$accountDetails) {
        logError("Compte ID {$accountId} introuvable pour le calcul du montant réel.");
        return 0.00;
    }

    $numeroComptePrefix = substr($accountDetails['Numero_Compte'], 0, 1);
    $isRevenueAccount = ($numeroComptePrefix === '7'); // Assuming 7xxx for Revenue
    $isExpenseAccount = ($numeroComptePrefix === '6'); // Assuming 6xxx for Expenses

    $sql = "
        SELECT
            SUM(CASE WHEN le.Sens = 'D' THEN le.Montant ELSE 0 END) AS total_debit,
            SUM(CASE WHEN le.Sens = 'C' THEN le.Montant ELSE 0 END) AS total_credit
        FROM
            Lignes_Ecritures le
        JOIN
            Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
        WHERE
            le.ID_Compte = :id_compte
            AND e.Date_Saisie >= :start_date
            AND e.Date_Saisie <= :end_date
    ";
    $params = [
        ':id_compte' => $accountId,
        ':start_date' => $startDate,
        ':end_date' => $endDate
    ];

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalDebit = (float)($result['total_debit'] ?? 0);
        $totalCredit = (float)($result['total_credit'] ?? 0);

        if ($isRevenueAccount) {
            // For revenue, credit is revenue, debit is reduction
            return $totalCredit - $totalDebit;
        } elseif ($isExpenseAccount) {
            // For expense, debit is expense, credit is reduction
            return $totalDebit - $totalCredit;
        } else {
            // For other types (e.g., asset/liability), you need to define what "actual" means
            // For a budget, it's usually income/expense.
            logWarning("Le compte {$accountDetails['Numero_Compte']} ({$accountDetails['Nom_Compte']}) n'est ni un compte de produit ni de charge, le calcul du montant réel pourrait être ambigu.");
            return 0.00; // Or return total net change, depending on requirement
        }

    } catch (PDOException $e) {
        logError("Erreur PDO lors du calcul du montant réel pour le compte {$accountId}: " . $e->getMessage());
        return 0.00;
    }
}

/**
 * Generates a budget vs actual performance report for a given period.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param string $reportStartDate The start date of the reporting period (YYYY-MM-DD).
 * @param string $reportEndDate The end date of the reporting period (YYYY-MM-DD).
 * @return array A structured array with budget vs actual data for each budgeted account.
 */
function getBudgetPerformanceReport(PDO $pdo, string $reportStartDate, string $reportEndDate): array {
    $report = [];

    // Get all active budgets that overlap with the reporting period
    $budgets = getBudgets($pdo, null, null, null, $reportStartDate); // Get budgets active around start date

    foreach ($budgets as $budget) {
        // Ensure the budget period overlaps with the report period
        if (new DateTime($budget['start_date']) > new DateTime($reportEndDate) ||
            new DateTime($budget['end_date']) < new DateTime($reportStartDate)) {
            continue; // Budget period does not overlap with report period
        }

        // Determine the actual period for data extraction
        $actualPeriodStartDate = max($budget['start_date'], $reportStartDate);
        $actualPeriodEndDate = min($budget['end_date'], $reportEndDate);

        $actualAmount = getActualAmountForAccount($pdo, $budget['id_compte'], $actualPeriodStartDate, $actualPeriodEndDate);

        // Calculate variance
        $variance = $actualAmount - $budget['budget_amount'];
        $variancePercentage = ($budget['budget_amount'] != 0) ? ($variance / $budget['budget_amount'] * 100) : 0;

        $report[] = [
            'id_budget' => $budget['id_budget'],
            'id_compte' => $budget['id_compte'],
            'numero_compte' => $budget['Numero_Compte'],
            'nom_compte' => $budget['Nom_Compte'],
            'period_type' => $budget['period_type'],
            'budget_start_date' => $budget['start_date'],
            'budget_end_date' => $budget['end_date'],
            'budget_amount' => $budget['budget_amount'],
            'actual_amount' => $actualAmount,
            'variance' => $variance,
            'variance_percentage' => $variancePercentage,
            'report_period_start' => $actualPeriodStartDate,
            'report_period_end' => $actualPeriodEndDate,
        ];
    }
    return $report;
}

/**
 * Helper function to get account details (Numero_Compte, Nom_Compte).
 * Assumed to exist or to be implemented elsewhere.
 * @param PDO $pdo
 * @param int $accountId
 * @return array|false
 */
function getAccountDetails(PDO $pdo, int $accountId): array|false {
    $stmt = $pdo->prepare("SELECT Numero_Compte, Nom_Compte FROM Comptes_compta WHERE ID_Compte = :id_compte");
    $stmt->bindParam(':id_compte', $accountId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


// Helper for logging errors (assuming gestion_logs.php has logApplicationError)
function logError(string $message) {
    if (function_exists('logApplicationError')) {
        logApplicationError($message);
    } else {
        error_log($message);
    }
}

// Helper for logging warnings (can be added to gestion_logs.php)
function logWarning(string $message) {
    if (function_exists('logApplicationWarning')) { // Or adapt your existing log function
        logApplicationWarning($message);
    } else {
        error_log("WARNING: " . $message);
    }
}



?>