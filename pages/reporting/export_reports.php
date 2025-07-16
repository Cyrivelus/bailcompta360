<?php
session_start();

// Check user authentication and authorization
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../login.php');
    exit;
}

require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_logs.php'; // For logging


$reportType = $_GET['report_type'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-01-01');
$endDate = $_GET['end_date'] ?? date('Y-12-31');
$selectedJournal = $_GET['id_journal'] ?? null; // For Journal Général
$selectedAccount = $_GET['id_compte'] ?? null; // For Journal Général and Grand Livre
$minAmount = $_GET['min_amount'] ?? ''; // For Journal Général
$maxAmount = $_GET['max_amount'] ?? ''; // For Journal Général
$descriptionKeyword = $_GET['description_keyword'] ?? ''; // For Journal Général
$numeroPiece = $_GET['numero_piece'] ?? ''; // For Journal Général

// Basic validation for dates
if (!DateTime::createFromFormat('Y-m-d', $startDate) || !DateTime::createFromFormat('Y-m-d', $endDate) || $startDate > $endDate) {
    die("Erreur: Dates invalides.");
}

// Set CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $reportType . '_' . date('Ymd') . '.csv"');

$output = fopen('php://output', 'w'); // Open output stream for writing CSV

// Write BOM for UTF-8 compatibility in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

$logAction = "Exportation du rapport: " . $reportType . " par l'utilisateur ID: " . $_SESSION['utilisateur_id'] . ". Période: {$startDate} à {$endDate}.";
try {
    switch ($reportType) {
        case 'profit_loss':
            fputcsv($output, ['Compte de Résultat']);
            fputcsv($output, ['Période du ' . date('d/m/Y', strtotime($startDate)) . ' au ' . date('d/m/Y', strtotime($endDate))]);
            fputcsv($output, []); // Empty row for spacing

            // --- Fetch Revenues ---
            $stmtRevenues = $pdo->prepare("
                SELECT
                    cc.Nom_Compte,
                    SUM(CASE WHEN le.Sens = 'C' THEN le.Montant ELSE 0 END) -
                    SUM(CASE WHEN le.Sens = 'D' THEN le.Montant ELSE 0 END) AS Total_Amount
                FROM
                    Lignes_Ecritures le
                JOIN
                    Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
                JOIN
                    Comptes_compta cc ON le.ID_Compte = cc.ID_Compte
                WHERE
                    cc.Type_Compte = 'Revenu'
                    AND e.Date_Saisie >= :start_date
                    AND e.Date_Saisie <= :end_date
                GROUP BY
                    cc.Nom_Compte
                ORDER BY
                    cc.Nom_Compte;
            ");
            $stmtRevenues->bindParam(':start_date', $startDate);
            $stmtRevenues->bindParam(':end_date', $endDate);
            $stmtRevenues->execute();
            $revenues = $stmtRevenues->fetchAll(PDO::FETCH_ASSOC);

            fputcsv($output, ['Produits (Revenus)']);
            fputcsv($output, ['Compte', 'Montant']);
            $totalRevenues = 0;
            foreach ($revenues as $revenue) {
                fputcsv($output, [
                    $revenue['Nom_Compte'],
                    str_replace('.', ',', round($revenue['Total_Amount'], 2)) // Use comma for decimals
                ]);
                $totalRevenues += $revenue['Total_Amount'];
            }
            fputcsv($output, ['Total Produits', str_replace('.', ',', round($totalRevenues, 2))]);
            fputcsv($output, []); // Empty row for spacing

            // --- Fetch Expenses ---
            $stmtExpenses = $pdo->prepare("
                SELECT
                    cc.Nom_Compte,
                    SUM(CASE WHEN le.Sens = 'D' THEN le.Montant ELSE 0 END) -
                    SUM(CASE WHEN le.Sens = 'C' THEN le.Montant ELSE 0 END) AS Total_Amount
                FROM
                    Lignes_Ecritures le
                JOIN
                    Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
                JOIN
                    Comptes_compta cc ON le.ID_Compte = cc.ID_Compte
                WHERE
                    cc.Type_Compte = 'Depense'
                    AND e.Date_Saisie >= :start_date
                    AND e.Date_Saisie <= :end_date
                GROUP BY
                    cc.Nom_Compte
                ORDER BY
                    cc.Nom_Compte;
            ");
            $stmtExpenses->bindParam(':start_date', $startDate);
            $stmtExpenses->bindParam(':end_date', $endDate);
            $stmtExpenses->execute();
            $expenses = $stmtExpenses->fetchAll(PDO::FETCH_ASSOC);

            fputcsv($output, ['Charges (Dépenses)']);
            fputcsv($output, ['Compte', 'Montant']);
            $totalExpenses = 0;
            foreach ($expenses as $expense) {
                fputcsv($output, [
                    $expense['Nom_Compte'],
                    str_replace('.', ',', round($expense['Total_Amount'], 2))
                ]);
                $totalExpenses += $expense['Total_Amount'];
            }
            fputcsv($output, ['Total Charges', str_replace('.', ',', round($totalExpenses, 2))]);
            fputcsv($output, []); // Empty row for spacing

            $netProfitLoss = $totalRevenues - $totalExpenses;
            fputcsv($output, ['Résultat Net', str_replace('.', ',', round($netProfitLoss, 2))]);

            break;

        case 'journal_general':
            fputcsv($output, ['Journal Général']);
            fputcsv($output, ['Période du ' . date('d/m/Y', strtotime($startDate)) . ' au ' . date('d/m/Y', strtotime($endDate))]);
            fputcsv($output, ['Filtres: Journal=' . ($selectedJournal ?? 'Tous') . ', Compte=' . ($selectedAccount ?? 'Tous') . ', Montant=' . ($minAmount . '-' . $maxAmount) . ', Mot-clé="' . $descriptionKeyword . '", N° Pièce="' . $numeroPiece . '"']);
            fputcsv($output, []); // Empty row for spacing

            fputcsv($output, ['Date', 'N° Pièce', 'Description Écriture', 'Journal', 'Compte N°', 'Libellé Ligne', 'Débit', 'Crédit']);

            $sql = "
                SELECT
                    e.Date_Saisie,
                    e.Description AS Ecriture_Description,
                    e.Numero_Piece,
                    jal.Nom_Journal,
                    le.Montant,
                    le.Sens,
                    le.Libelle_Ligne,
                    cc.Numero_Compte,
                    cc.Nom_Compte
                FROM
                    Ecritures e
                JOIN
                    Lignes_Ecritures le ON e.ID_Ecriture = le.ID_Ecriture
                LEFT JOIN
                    JAL jal ON e.Cde = jal.Cde
                LEFT JOIN
                    Comptes_compta cc ON le.ID_Compte = cc.ID_Compte
                WHERE
                    e.Date_Saisie BETWEEN :start_date AND :end_date
            ";

            $params = [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ];

            // Add filters
            if ($selectedJournal && is_numeric($selectedJournal)) {
                $sql .= " AND e.Cde = :id_journal";
                $params[':id_journal'] = $selectedJournal;
            }
            if ($selectedAccount && is_numeric($selectedAccount)) {
                $sql .= " AND le.ID_Compte = :id_compte";
                $params[':id_compte'] = $selectedAccount;
            }
            if ($minAmount !== '' && is_numeric($minAmount)) {
                $sql .= " AND le.Montant >= :min_amount";
                $params[':min_amount'] = (float)$minAmount;
            }
            if ($maxAmount !== '' && is_numeric($maxAmount)) {
                $sql .= " AND le.Montant <= :max_amount";
                $params[':max_amount'] = (float)$maxAmount;
            }
            if (!empty($descriptionKeyword)) {
                $sql .= " AND (e.Description LIKE :keyword OR le.Libelle_Ligne LIKE :keyword)";
                $params[':keyword'] = '%' . $descriptionKeyword . '%';
            }
            if (!empty($numeroPiece)) {
                $sql .= " AND e.Numero_Piece = :numero_piece";
                $params[':numero_piece'] = $numeroPiece;
            }

            $sql .= " ORDER BY e.Date_Saisie ASC, e.ID_Ecriture ASC, le.Sens DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    date('d/m/Y', strtotime($row['Date_Saisie'])),
                    $row['Numero_Piece'],
                    $row['Ecriture_Description'],
                    $row['Nom_Journal'],
                    $row['Numero_Compte'] . ' - ' . $row['Nom_Compte'],
                    $row['Libelle_Ligne'],
                    $row['Sens'] === 'D' ? str_replace('.', ',', round($row['Montant'], 2)) : '',
                    $row['Sens'] === 'C' ? str_replace('.', ',', round($row['Montant'], 2)) : ''
                ]);
            }
            break;

        case 'ledger_accounts':
            if (!$selectedAccount || !is_numeric($selectedAccount)) {
                die("Erreur: Compte comptable non spécifié.");
            }

            // Fetch selected account details
            $stmtAccount = $pdo->prepare("SELECT Numero_Compte, Nom_Compte, Type_Compte FROM Comptes_compta WHERE ID_Compte = :id_compte");
            $stmtAccount->bindParam(':id_compte', $selectedAccount, PDO::PARAM_INT);
            $stmtAccount->execute();
            $selectedAccountDetails = $stmtAccount->fetch(PDO::FETCH_ASSOC);

            if (!$selectedAccountDetails) {
                die("Erreur: Compte comptable introuvable.");
            }

            fputcsv($output, ['Grand Livre du Compte']);
            fputcsv($output, ['Compte: ' . $selectedAccountDetails['Numero_Compte'] . ' - ' . $selectedAccountDetails['Nom_Compte']]);
            fputcsv($output, ['Période du ' . date('d/m/Y', strtotime($startDate)) . ' au ' . date('d/m/Y', strtotime($endDate))]);
            fputcsv($output, []); // Empty row for spacing

            fputcsv($output, ['Date', 'N° Pièce', 'Journal', 'Description Écriture', 'Libellé Ligne', 'Débit', 'Crédit', 'Solde']);

            // Determine normal balance type (same logic as ledger_accounts.php)
            $accountNormalBalance = 'Debit';
            if (in_array($selectedAccountDetails['Type_Compte'], ['Passif', 'Capitaux Propres', 'Revenu'])) {
                $accountNormalBalance = 'Credit';
            }

            // 1. Calculate Initial Balance
            $stmtInitialBalance = $pdo->prepare("
                SELECT
                    SUM(CASE WHEN le.Sens = 'D' THEN le.Montant ELSE 0 END) AS Total_Debit,
                    SUM(CASE WHEN le.Sens = 'C' THEN le.Montant ELSE 0 END) AS Total_Credit
                FROM
                    Lignes_Ecritures le
                JOIN
                    Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
                WHERE
                    le.ID_Compte = :id_compte
                    AND e.Date_Saisie < :start_date;
            ");
            $stmtInitialBalance->bindParam(':id_compte', $selectedAccount, PDO::PARAM_INT);
            $stmtInitialBalance->bindParam(':start_date', $startDate);
            $stmtInitialBalance->execute();
            $balanceData = $stmtInitialBalance->fetch(PDO::FETCH_ASSOC);

            $debitBefore = $balanceData['Total_Debit'] ?? 0;
            $creditBefore = $balanceData['Total_Credit'] ?? 0;

            if ($accountNormalBalance === 'Debit') {
                $initialBalance = $debitBefore - $creditBefore;
            } else {
                $initialBalance = $creditBefore - $debitBefore;
            }
            $runningBalance = $initialBalance;

            fputcsv($output, [
                date('d/m/Y', strtotime($startDate . ' - 1 day')),
                '', '', '', 'Solde Initial', '', '',
                str_replace('.', ',', round($initialBalance, 2))
            ]);

            // 2. Fetch Transactions within the period
            $stmtTransactions = $pdo->prepare("
                SELECT
                    e.Date_Saisie,
                    e.Numero_Piece,
                    e.Description AS Ecriture_Description,
                    le.Montant,
                    le.Sens,
                    le.Libelle_Ligne,
                    jal.Nom_Journal
                FROM
                    Lignes_Ecritures le
                JOIN
                    Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
                LEFT JOIN
                    JAL jal ON e.Cde = jal.Cde
                WHERE
                    le.ID_Compte = :id_compte
                    AND e.Date_Saisie BETWEEN :start_date AND :end_date
                ORDER BY
                    e.Date_Saisie ASC, e.ID_Ecriture ASC, le.Sens DESC;
            ");
            $stmtTransactions->bindParam(':id_compte', $selectedAccount, PDO::PARAM_INT);
            $stmtTransactions->bindParam(':start_date', $startDate);
            $stmtTransactions->bindParam(':end_date', $endDate);
            $stmtTransactions->execute();

            while ($transaction = $stmtTransactions->fetch(PDO::FETCH_ASSOC)) {
                if ($accountNormalBalance === 'Debit') {
                    if ($transaction['Sens'] === 'D') {
                        $runningBalance += $transaction['Montant'];
                    } else {
                        $runningBalance -= $transaction['Montant'];
                    }
                } else {
                    if ($transaction['Sens'] === 'C') {
                        $runningBalance += $transaction['Montant'];
                    } else {
                        $runningBalance -= $transaction['Montant'];
                    }
                }

                fputcsv($output, [
                    date('d/m/Y', strtotime($transaction['Date_Saisie'])),
                    $transaction['Numero_Piece'],
                    $transaction['Nom_Journal'],
                    $transaction['Ecriture_Description'],
                    $transaction['Libelle_Ligne'],
                    $transaction['Sens'] === 'D' ? str_replace('.', ',', round($transaction['Montant'], 2)) : '',
                    $transaction['Sens'] === 'C' ? str_replace('.', ',', round($transaction['Montant'], 2)) : '',
                    str_replace('.', ',', round($runningBalance, 2))
                ]);
            }

            fputcsv($output, [
                date('d/m/Y', strtotime($endDate)),
                '', '', '', 'Solde Final', '', '',
                str_replace('.', ',', round($runningBalance, 2))
            ]);
            break;

        default:
            die("Type de rapport non valide.");
    }
    logUserActivity($logAction); // Log success
} catch (PDOException $e) {
    logApplicationError("Erreur PDO lors de l'exportation du rapport '{$reportType}': " . $e->getMessage());
    die("Erreur de base de données lors de l'exportation: " . $e->getMessage());
} catch (Exception $e) {
    logApplicationError("Erreur lors de l'exportation du rapport '{$reportType}': " . $e->getMessage());
    die("Erreur lors de l'exportation: " . $e->getMessage());
} finally {
    fclose($output);
    exit; // Ensure nothing else is outputted
}
?>