<?php

require_once 'database.php'; // Ensure your database connection is accessible

/**
 * Generates data for a Balance Sheet (Bilan) for a specified date.
 * Assets = Liabilities + Equity
 *
 * @param PDO $pdo The PDO database connection object.
 * @param string $asOfDate The date for which to generate the balance sheet (YYYY-MM-DD).
 * @return array A structured array containing assets, liabilities, and equity data.
 */
function getBalanceSheetData(PDO $pdo, string $asOfDate): array {
    $balanceSheet = [
        'actifs' => [],
        'passifs' => [],
        'capitaux_propres' => [],
        'total_actifs' => 0.00,
        'total_passifs_capitaux_propres' => 0.00,
    ];

    try {
        // --- Calculate balances for all accounts up to the asOfDate ---
        // This query sums debits and credits for each account up to the specified date.
        $sql = "
            SELECT
                cc.ID_Compte,
                cc.Numero_Compte,
                cc.Nom_Compte,
                cc.Type_Compte, -- Assuming Type_Compte helps classify as asset, liability, equity, etc.
                SUM(CASE WHEN le.Sens = 'D' THEN le.Montant ELSE 0 END) AS total_debit,
                SUM(CASE WHEN le.Sens = 'C' THEN le.Montant ELSE 0 END) AS total_credit
            FROM
                Lignes_Ecritures le
            JOIN
                Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
            JOIN
                Comptes_compta cc ON le.ID_Compte = cc.ID_Compte
            WHERE
                e.Date_Saisie <= :as_of_date
            GROUP BY
                cc.ID_Compte, cc.Numero_Compte, cc.Nom_Compte, cc.Type_Compte
            ORDER BY
                cc.Numero_Compte ASC;
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':as_of_date', $asOfDate, PDO::PARAM_STR);
        $stmt->execute();
        $accountBalances = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Classify accounts and calculate final balances ---
        foreach ($accountBalances as $account) {
            $balance = $account['total_debit'] - $account['total_credit']; // For assets/expenses
            // You might need more sophisticated logic here based on your chart of accounts
            // For simplicity, assuming Type_Compte broadly categorizes accounts
            // Example: 1xxx for assets, 2xxx for liabilities, 3xxx for equity, 4xxx for revenues, 5xxx for expenses

            $accountNumberPrefix = substr($account['Numero_Compte'], 0, 1); // Get first digit of account number

            $formattedBalance = number_format($balance, 2, ',', ' ');

            switch ($accountNumberPrefix) {
                case '1': // Assets (e.g., 1000-Cash, 1200-Accounts Receivable)
                    $balanceSheet['actifs'][] = [
                        'numero' => $account['Numero_Compte'],
                        'nom' => $account['Nom_Compte'],
                        'solde' => $formattedBalance,
                        'valeur_brute' => $balance // Store raw value for calculations
                    ];
                    $balanceSheet['total_actifs'] += $balance;
                    break;
                case '2': // Liabilities (e.g., 2000-Accounts Payable, 2500-Loans Payable)
                    // Liabilities typically have a credit balance. So, debit - credit will be negative.
                    // We want to display them as positive amounts on the liabilities side.
                    $balanceSheet['passifs'][] = [
                        'numero' => $account['Numero_Compte'],
                        'nom' => $account['Nom_Compte'],
                        'solde' => number_format(abs($balance), 2, ',', ' '), // Display as positive
                        'valeur_brute' => abs($balance) // Store raw positive value for calculations
                    ];
                    $balanceSheet['total_passifs_capitaux_propres'] += abs($balance);
                    break;
                case '3': // Equity (e.g., 3000-Capital, 3500-Retained Earnings)
                    // Equity typically has a credit balance.
                    $balanceSheet['capitaux_propres'][] = [
                        'numero' => $account['Numero_Compte'],
                        'nom' => $account['Nom_Compte'],
                        'solde' => number_format(abs($balance), 2, ',', ' '),
                        'valeur_brute' => abs($balance)
                    ];
                    $balanceSheet['total_passifs_capitaux_propres'] += abs($balance);
                    break;
                // You might need to handle other account types (revenue/expense) for P&L separately
                // or ensure they zero out in the balance sheet if they are temporary accounts.
            }
        }

        // Format totals
        $balanceSheet['total_actifs'] = number_format($balanceSheet['total_actifs'], 2, ',', ' ');
        $balanceSheet['total_passifs_capitaux_propres'] = number_format($balanceSheet['total_passifs_capitaux_propres'], 2, ',', ' ');


    } catch (PDOException $e) {
        error_log("Erreur PDO dans getBalanceSheetData: " . $e->getMessage());
        // In a real application, you might throw the exception or return a specific error state.
    }

    return $balanceSheet;
}

/**
 * Generates data for a Profit & Loss Statement (Compte de Résultat) for a specified period.
 * Revenues - Expenses = Net Income
 *
 * @param PDO $pdo The PDO database connection object.
 * @param string $startDate The start date of the period (YYYY-MM-DD).
 * @param string $endDate The end date of the period (YYYY-MM-DD).
 * @return array A structured array containing revenues, expenses, and net income.
 */
function getProfitLossData(PDO $pdo, string $startDate, string $endDate): array {
    $profitLoss = [
        'revenus' => [],
        'depenses' => [],
        'total_revenus' => 0.00,
        'total_depenses' => 0.00,
        'resultat_net' => 0.00,
    ];

    try {
        // Query to sum debits/credits for revenue and expense accounts within the period
        $sql = "
            SELECT
                cc.ID_Compte,
                cc.Numero_Compte,
                cc.Nom_Compte,
                cc.Type_Compte, -- To classify as revenue or expense
                SUM(CASE WHEN le.Sens = 'D' THEN le.Montant ELSE 0 END) AS total_debit,
                SUM(CASE WHEN le.Sens = 'C' THEN le.Montant ELSE 0 END) AS total_credit
            FROM
                Lignes_Ecritures le
            JOIN
                Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
            JOIN
                Comptes_compta cc ON le.ID_Compte = cc.ID_Compte
            WHERE
                e.Date_Saisie >= :start_date AND e.Date_Saisie <= :end_date
                AND (SUBSTR(cc.Numero_Compte, 1, 1) = '4' OR SUBSTR(cc.Numero_Compte, 1, 1) = '5') -- Assuming 4xxx for Revenue, 5xxx for Expenses
            GROUP BY
                cc.ID_Compte, cc.Numero_Compte, cc.Nom_Compte, cc.Type_Compte
            ORDER BY
                cc.Numero_Compte ASC;
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->bindParam(':end_date', $endDate, PDO::PARAM_STR);
        $stmt->execute();
        $accountActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($accountActivity as $account) {
            $accountNumberPrefix = substr($account['Numero_Compte'], 0, 1);
            $balance = $account['total_credit'] - $account['total_debit']; // For revenues (credit balance) - expenses (debit balance)

            if ($accountNumberPrefix === '4') { // Revenues
                $profitLoss['revenus'][] = [
                    'numero' => $account['Numero_Compte'],
                    'nom' => $account['Nom_Compte'],
                    'montant' => number_format($balance, 2, ',', ' '),
                    'valeur_brute' => $balance
                ];
                $profitLoss['total_revenus'] += $balance;
            } elseif ($accountNumberPrefix === '5') { // Expenses
                // Expenses are typically debit balances, so we want to show them as positive expenses
                $expenseAmount = $account['total_debit'] - $account['total_credit'];
                $profitLoss['depenses'][] = [
                    'numero' => $account['Numero_Compte'],
                    'nom' => $account['Nom_Compte'],
                    'montant' => number_format($expenseAmount, 2, ',', ' '),
                    'valeur_brute' => $expenseAmount
                ];
                $profitLoss['total_depenses'] += $expenseAmount;
            }
        }

        $profitLoss['resultat_net'] = $profitLoss['total_revenus'] - $profitLoss['total_depenses'];
        
        // Format totals for display
        $profitLoss['total_revenus'] = number_format($profitLoss['total_revenus'], 2, ',', ' ');
        $profitLoss['total_depenses'] = number_format($profitLoss['total_depenses'], 2, ',', ' ');
        $profitLoss['resultat_net'] = number_format($profitLoss['resultat_net'], 2, ',', ' ');

    } catch (PDOException $e) {
        error_log("Erreur PDO dans getProfitLossData: " . $e->getMessage());
    }

    return $profitLoss;
}

/**
 * Generates data for a General Ledger (Grand Livre) for all accounts or a specific one,
 * for a specified period.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param string $startDate The start date of the period (YYYY-MM-DD).
 * @param string $endDate The end date of the period (YYYY-MM-DD).
 * @param int|null $compteId Optional: Specific account ID to filter by.
 * @return array A structured array containing ledger entries per account.
 */
function getGeneralLedgerData(PDO $pdo, string $startDate, string $endDate, ?int $compteId = null): array {
    $ledger = [];

    try {
        $sql = "
            SELECT
                cc.ID_Compte,
                cc.Numero_Compte,
                cc.Nom_Compte,
                e.Date_Saisie,
                e.Description,
                le.Sens,
                le.Montant
            FROM
                Lignes_Ecritures le
            JOIN
                Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
            JOIN
                Comptes_compta cc ON le.ID_Compte = cc.ID_Compte
            WHERE
                e.Date_Saisie >= :start_date AND e.Date_Saisie <= :end_date
        ";

        $params = [
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ];

        if ($compteId !== null) {
            $sql .= " AND cc.ID_Compte = :compte_id";
            $params[':compte_id'] = $compteId;
        }

        $sql .= " ORDER BY cc.Numero_Compte ASC, e.Date_Saisie ASC, le.ID_Ligne ASC;";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $currentBalance = []; // To track running balance for each account

        foreach ($entries as $entry) {
            $accId = $entry['ID_Compte'];
            if (!isset($ledger[$accId])) {
                // Initialize account data and get its opening balance if applicable
                $anteriorBalance = getSoldeAnterieur($pdo, $accId, $startDate);
                $initialBalance = $anteriorBalance['total_credit'] - $anteriorBalance['total_debit'];
                $currentBalance[$accId] = $initialBalance;

                $ledger[$accId] = [
                    'numero' => $entry['Numero_Compte'],
                    'nom' => $entry['Nom_Compte'],
                    'solde_ouverture' => number_format($initialBalance, 2, ',', ' '),
                    'details' => [],
                    'total_debit_periode' => 0.00,
                    'total_credit_periode' => 0.00,
                    'solde_fermeture' => 0.00,
                ];
            }

            $debit = ($entry['Sens'] === 'D') ? (float)$entry['Montant'] : 0.00;
            $credit = ($entry['Sens'] === 'C') ? (float)$entry['Montant'] : 0.00;

            $currentBalance[$accId] += ($credit - $debit);

            $ledger[$accId]['details'][] = [
                'date' => $entry['Date_Saisie'],
                'description' => $entry['Description'],
                'debit' => number_format($debit, 2, ',', ' '),
                'credit' => number_format($credit, 2, ',', ' '),
                'solde_cumule' => number_format($currentBalance[$accId], 2, ',', ' ')
            ];
            $ledger[$accId]['total_debit_periode'] += $debit;
            $ledger[$accId]['total_credit_periode'] += $credit;
            $ledger[$accId]['solde_fermeture'] = $currentBalance[$accId]; // Store raw for calculation
        }

        // Format final totals for display
        foreach ($ledger as $accId => $data) {
            $ledger[$accId]['total_debit_periode'] = number_format($data['total_debit_periode'], 2, ',', ' ');
            $ledger[$accId]['total_credit_periode'] = number_format($data['total_credit_periode'], 2, ',', ' ');
            $ledger[$accId]['solde_fermeture'] = number_format($data['solde_fermeture'], 2, ',', ' ');
        }

    } catch (PDOException $e) {
        error_log("Erreur PDO dans getGeneralLedgerData: " . $e->getMessage());
    }

    return $ledger;
}

// You can add more functions here, e.g., for Cash Flow Statement, Trial Balance, etc.

?>