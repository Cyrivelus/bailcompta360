<?php
// fonctions/gestion_tresorerie.php

/**
 * Gets the current cash and bank balance.
 * This assumes your `Comptes` table has a field to identify treasury accounts (e.g., account numbers starting with '5').
 *
 * @param PDO $pdo The PDO database connection instance.
 * @return float The total balance.
 */
function getCurrentCashBalance(PDO $pdo): float
{
    try {
        // This query sums the final balance of all treasury accounts (class 5 accounts)
        // Adjust the WHERE clause to match your chart of accounts (`ID_Compte` or `Numero_Compte`).
        // It's more efficient to calculate the balance directly from the latest movements.
        $sql = "SELECT SUM(Montant_Debit - Montant_Credit) AS balance
                FROM Ecritures_Comptables
                WHERE ID_Compte IN (SELECT ID_Compte FROM Comptes_compta WHERE Numero_Compte LIKE '5%')";
        
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (float)($result['balance'] ?? 0.0);
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getCurrentCashBalance: " . $e->getMessage());
        return 0.0;
    }
}

/**
 * Retrieves total cash inflows and outflows for a given period.
 *
 * @param PDO $pdo The PDO database connection instance.
 * @param string $period The period ('month' or 'day').
 * @return array An associative array with keys 'inflows' and 'outflows'.
 */
function getCashFlowByPeriod(PDO $pdo, string $period = 'month'): array
{
    // Define the start date based on the period
    $startDate = ($period === 'month') ? date('Y-m-01') : date('Y-m-d');
    $endDate = date('Y-m-d');

    try {
        // Query to get total inflows (credit entries for treasury accounts)
        $sqlInflows = "SELECT SUM(Montant_Credit) AS inflows
                       FROM Ecritures_Comptables
                       WHERE Date_Ecriture BETWEEN :start_date AND :end_date
                       AND ID_Compte IN (SELECT ID_Compte FROM Comptes_compta WHERE Numero_Compte LIKE '5%')";
        
        $stmtInflows = $pdo->prepare($sqlInflows);
        $stmtInflows->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        $inflows = (float)($stmtInflows->fetchColumn() ?? 0.0);

        // Query to get total outflows (debit entries for treasury accounts)
        $sqlOutflows = "SELECT SUM(Montant_Debit) AS outflows
                        FROM Ecritures_Comptables
                        WHERE Date_Ecriture BETWEEN :start_date AND :end_date
                        AND ID_Compte IN (SELECT ID_Compte FROM Comptes_compta WHERE Numero_Compte LIKE '5%')";
        
        $stmtOutflows = $pdo->prepare($sqlOutflows);
        $stmtOutflows->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        $outflows = (float)($stmtOutflows->fetchColumn() ?? 0.0);

        return ['inflows' => $inflows, 'outflows' => $outflows];
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getCashFlowByPeriod: " . $e->getMessage());
        return ['inflows' => 0.0, 'outflows' => 0.0];
    }
}

/**
 * Retrieves upcoming invoices (customer or supplier) based on due date.
 *
 * @param PDO $pdo The PDO database connection instance.
 * @param string $type The invoice type ('customer' or 'supplier').
 * @param int $days The number of days to look ahead (default is 30).
 * @return array A list of upcoming invoices.
 */
function getUpcomingInvoices(PDO $pdo, string $type, int $days = 30): array
{
    $tableName = ($type === 'customer') ? 'Factures_Clients' : 'Factures_Fournisseurs';
    $dueDateColumn = 'Date_Echeance';
    $amountColumn = 'Montant_Total';
    $nameColumn = ($type === 'customer') ? 'Nom_Client' : 'Nom_Fournisseur';

    $sql = "SELECT " . $dueDateColumn . " as due_date, " . $amountColumn . " as amount, " . $nameColumn . " as name
            FROM " . $tableName . "
            WHERE " . $dueDateColumn . " BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
            ORDER BY " . $dueDateColumn . " ASC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        
        // This is a placeholder; you'll need to join with your client/supplier table to get the name.
        // For simplicity, I've used a hardcoded 'name' column. You should replace this with a proper join.
        // For example:
        // $sql = "SELECT f.Date_Echeance as due_date, f.Montant_Total as amount, c.Nom_Client as name
        //         FROM Factures_Clients f JOIN Clients c ON f.ID_Client = c.ID_Client ...";

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Erreur PDO dans getUpcomingInvoices pour le type " . $type . ": " . $e->getMessage());
        return [];
    }
}