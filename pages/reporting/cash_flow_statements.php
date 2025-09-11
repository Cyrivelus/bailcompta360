<?php
session_start();

// Check user authentication and authorization
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../login.php');
    exit;
}

require_once '../../templates/header.php';
require_once '../../templates/navigation.php';
require_once '../../fonctions/database.php'; // Make sure $pdo is initialized here
require_once '../../fonctions/gestion_logs.php'; // For logging

$titre = 'Tableau des Flux de Trésorerie';

$message = '';
$messageType = '';

$startDate = $_POST['start_date'] ?? date('Y-01-01'); // Default to start of current year
$endDate = $_POST['end_date'] ?? date('Y-12-31');     // Default to end of current year

$cashFlowData = [
    'operating_activities' => [
        'cash_from_customers' => 0,
        'cash_paid_to_suppliers' => 0,
        'cash_paid_to_employees' => 0,
        'other_operating_cash_in' => 0,
        'other_operating_cash_out' => 0,
    ],
    'investing_activities' => [
        'purchase_of_assets' => 0,
        'sale_of_assets' => 0,
    ],
    'financing_activities' => [
        'equity_issued' => 0,
        'dividends_paid' => 0,
        'debt_incurred' => 0,
        'debt_repaid' => 0,
    ],
    'net_change_in_cash' => 0,
    'beginning_cash_balance' => 0,
    'ending_cash_balance' => 0,
];

/**
 * Helper to determine the main cash/bank accounts based on their Numero_Compte
 * and Type_Compte.
 *
 * This function is crucial. It needs to accurately identify your cash/bank accounts.
 * If your chart of accounts uses a different convention, you must adjust this query.
 * For SYSCOHADA, cash and bank accounts typically start with '5' (e.g., 52 for banks, 57 for cash).
 * Given you have 'Mixte', 'Débit', 'Crédit' for Type_Compte, cash accounts are usually 'Mixte'.
 */
function getCashAccountIds($pdo) {
    // Corrected query: Identify cash/bank accounts by Numero_Compte starting with '5'
    // AND ensure they are of Type_Compte 'Mixte' (as cash accounts can be debited or credited)
    $stmt = $pdo->query("SELECT ID_Compte FROM Comptes_compta WHERE Numero_Compte LIKE '5%' AND Type_Compte = 'Mixte'");
    
    // You might need to refine the 'Numero_Compte LIKE' clause based on your specific chart of accounts.
    // For example, if you know specific account numbers:
    // $stmt = $pdo->query("SELECT ID_Compte FROM Comptes_compta WHERE Numero_Compte IN ('521000', '571000') AND Type_Compte = 'Mixte'");
    
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

if (isset($_POST['generate_report'])) {
    // Validate dates
    $validStartDate = DateTime::createFromFormat('Y-m-d', $startDate);
    $validEndDate = DateTime::createFromFormat('Y-m-d', $endDate);

    if (!$validStartDate || $validStartDate->format('Y-m-d') !== $startDate || !$validEndDate || $validEndDate->format('Y-m-d') !== $endDate) {
        $message = "Veuillez saisir des dates valides (AAAA-MM-JJ).";
        $messageType = 'danger';
    } elseif ($startDate > $endDate) {
        $message = "La date de début ne peut pas être postérieure à la date de fin.";
        $messageType = 'danger';
    } else {
        try {
            $cashAccountIds = getCashAccountIds($pdo);
            if (empty($cashAccountIds)) {
                // This is the error message you received.
                // It means getCashAccountIds returned an empty array.
                throw new Exception("Aucun compte de trésorerie (Banque/Caisse) défini dans votre plan comptable. Veuillez configurer les 'Type_Compte' de vos comptes de trésorerie ou vérifier leurs numéros.");
            }
            $cashAccountPlaceholders = implode(',', array_fill(0, count($cashAccountIds), '?'));

            // 1. Get Beginning Cash Balance
            // Removed CONVERT(date, ?, 120) for MariaDB compatibility
            $sqlBeginningCash = "
                SELECT
                    SUM(CASE WHEN le.Sens = 'D' THEN le.Montant ELSE 0 END) -
                    SUM(CASE WHEN le.Sens = 'C' THEN le.Montant ELSE 0 END) AS Balance
                FROM
                    Lignes_Ecritures le
                JOIN
                    Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
                WHERE
                    le.ID_Compte IN ({$cashAccountPlaceholders})
                    AND e.Date_Saisie < ?; -- MariaDB compatible date comparison
            ";
            $stmtBeginningCash = $pdo->prepare($sqlBeginningCash);
            // Combine cash account IDs and the start date for positional binding
            $paramsBeginningCash = array_merge($cashAccountIds, [$startDate]);
            $stmtBeginningCash->execute($paramsBeginningCash);
            $cashFlowData['beginning_cash_balance'] = $stmtBeginningCash->fetchColumn();

            // 2. Fetch all cash transactions within the period
            // Removed CONVERT(date, ?, 120) for MariaDB compatibility
            $sqlCashTransactions = "
                SELECT
                    e.ID_Ecriture,
                    e.Date_Saisie,
                    e.Description AS Ecriture_Description,
                    le_cash.Montant AS Cash_Amount,
                    le_cash.Sens AS Cash_Sens, -- 'D' if cash account debited, 'C' if credited
                    le_other.ID_Compte AS Other_Compte_ID,
                    cc_other.Numero_Compte AS Other_Compte_Numero,
                    cc_other.Nom_Compte AS Other_Compte_Nom,
                    cc_other.Type_Compte AS Other_Compte_Type,
                    le_other.Montant AS Other_Amount,
                    le_other.Sens AS Other_Sens,
                    le_other.Libelle_Ligne
                FROM
                    Ecritures e
                JOIN
                    Lignes_Ecritures le_cash ON e.ID_Ecriture = le_cash.ID_Ecriture
                JOIN
                    Lignes_Ecritures le_other ON e.ID_Ecriture = le_other.ID_Ecriture AND le_cash.ID_Ligne <> le_other.ID_Ligne
                JOIN
                    Comptes_compta cc_other ON le_other.ID_Compte = cc_other.ID_Compte
                WHERE
                    le_cash.ID_Compte IN ({$cashAccountPlaceholders})
                    AND e.Date_Saisie BETWEEN ? AND ? -- MariaDB compatible date comparison
                ORDER BY
                    e.Date_Saisie ASC, e.ID_Ecriture ASC;
            ";
            $stmtCashTransactions = $pdo->prepare($sqlCashTransactions);
            // Combine cash account IDs, start date, and end date for positional binding
            $paramsCashTransactions = array_merge($cashAccountIds, [$startDate, $endDate]);
            $stmtCashTransactions->execute($paramsCashTransactions);
            $transactions = $stmtCashTransactions->fetchAll(PDO::FETCH_ASSOC);

            // Process transactions to classify them into cash flow sections
            $processedEntries = []; // To avoid processing the same entry multiple times if it has multiple non-cash lines
            foreach ($transactions as $transaction) {
                $ecritureId = $transaction['ID_Ecriture'];
                if (isset($processedEntries[$ecritureId])) {
                    continue; // Already processed this main entry
                }

                $cashSens = $transaction['Cash_Sens'];
                $otherAccountNumero = $transaction['Other_Compte_Numero']; // Use Numero_Compte for classification
                $cashAmount = $transaction['Cash_Amount'];

                // Cash Inflow: Cash Account Credited (e.g., Bank + | Revenue - )
                // Note: The logic for cash flow relies heavily on your chart of accounts structure and how transactions are recorded.
                // The current implementation makes assumptions based on common accounting practices (e.g., revenue is typically credit, expenses debit).
                // Review and adjust 'Other_Compte_Numero' conditions to match your specific chart of accounts.
                if ($cashSens === 'C') { // Cash account was credited (received cash)
                    // Operating Activities - Cash from customers (e.g., Sales revenue - accounts like 7xxxx)
                    if (strpos($otherAccountNumero, '7') === 0) { // Sales Revenue (SYSCOHADA Class 7)
                        $cashFlowData['operating_activities']['cash_from_customers'] += $cashAmount;
                    } 
                    // Investing Activities - Sale of assets (e.g., Fixed assets - accounts like 2xxxx)
                    elseif (strpos($otherAccountNumero, '2') === 0) { // Fixed Assets (SYSCOHADA Class 2)
                        $cashFlowData['investing_activities']['sale_of_assets'] += $cashAmount;
                    } 
                    // Financing Activities - Equity issued, debt incurred
                    // Equity (SYSCOHADA Class 10/11)
                    elseif (strpos($otherAccountNumero, '10') === 0 || strpos($otherAccountNumero, '11') === 0) {
                        $cashFlowData['financing_activities']['equity_issued'] += $cashAmount;
                    } 
                    // Debt Incurred (SYSCOHADA Class 16 or certain 4xxxx for long-term debt)
                    elseif (strpos($otherAccountNumero, '16') === 0 || strpos($otherAccountNumero, '4') === 0) { 
                        $cashFlowData['financing_activities']['debt_incurred'] += $cashAmount;
                    }
                    else { // Other operating cash in (e.g., decrease in receivables - accounts like 41xxx debited)
                        // This might need more specific rules depending on your chart of accounts for operating vs. non-operating.
                        // For example, if it's a decrease in "Clients" (411), it's operating cash in.
                         if (strpos($otherAccountNumero, '41') === 0) { // Receivables from customers
                             $cashFlowData['operating_activities']['cash_from_customers'] += $cashAmount; // Assuming this is primarily customer payments
                         } else {
                            $cashFlowData['operating_activities']['other_operating_cash_in'] += $cashAmount; // Fallback for other operating inflows
                         }
                    }
                }
                // Cash Outflow: Cash Account Debited (e.g., Bank - | Expense + )
                elseif ($cashSens === 'D') { // Cash account was debited (paid out cash)
                    // Operating Activities - Payments to suppliers, employees, other operating expenses
                    // Payments to Suppliers (e.g., Purchases of goods/services - accounts like 60xxxx, 61xxxx)
                    if (strpos($otherAccountNumero, '60') === 0 || strpos($otherAccountNumero, '61') === 0) {
                        $cashFlowData['operating_activities']['cash_paid_to_suppliers'] += $cashAmount;
                    } 
                    // Payments to Employees (e.g., Salaries - accounts like 64xxxx)
                    elseif (strpos($otherAccountNumero, '64') === 0) {
                        $cashFlowData['operating_activities']['cash_paid_to_employees'] += $cashAmount;
                    } 
                    // Investing Activities - Purchase of assets (e.g., Fixed assets - accounts like 2xxxx)
                    elseif (strpos($otherAccountNumero, '2') === 0) {
                        $cashFlowData['investing_activities']['purchase_of_assets'] -= $cashAmount; // Show as negative outflow
                    } 
                    // Financing Activities - Dividends paid, debt repaid
                    // Dividends Paid (e.g., Profit distribution - accounts like 106xxxx, 12xxxx)
                    elseif (strpos($otherAccountNumero, '106') === 0 || strpos($otherAccountNumero, '12') === 0) {
                        $cashFlowData['financing_activities']['dividends_paid'] -= $cashAmount;
                    } 
                    // Debt Repaid (e.g., Loan repayments - accounts like 16xxxx or certain 4xxxx for long-term debt)
                    elseif (strpos($otherAccountNumero, '16') === 0 || strpos($otherAccountNumero, '4') === 0) { 
                        $cashFlowData['financing_activities']['debt_repaid'] -= $cashAmount;
                    }
                    else { // Other operating cash out (e.g., increase in inventory - accounts like 3xxxx debited)
                        // Similar to cash in, this might need more specific rules.
                        $cashFlowData['operating_activities']['other_operating_cash_out'] += $cashAmount; // Fallback for other operating outflows
                    }
                }
                $processedEntries[$ecritureId] = true; // Mark this entry as processed
            }

            // Calculate Net Change in Cash
            $totalOperating = array_sum($cashFlowData['operating_activities']);
            $totalInvesting = array_sum($cashFlowData['investing_activities']);
            $totalFinancing = array_sum($cashFlowData['financing_activities']);

            $cashFlowData['net_change_in_cash'] = $totalOperating + $totalInvesting + $totalFinancing;

            // Calculate Ending Cash Balance
            $cashFlowData['ending_cash_balance'] = $cashFlowData['beginning_cash_balance'] + $cashFlowData['net_change_in_cash'];

            logUserActivity("Génération du Tableau des Flux de Trésorerie par l'utilisateur ID: " . $_SESSION['user_id'] . " pour la période du {$startDate} au {$endDate}.");

        } catch (Exception $e) {
            logApplicationError("Erreur lors de la génération du Tableau des Flux de Trésorerie: " . $e->getMessage());
            $message = "Erreur lors de la génération du rapport: " . $e->getMessage();
            $messageType = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?= htmlspecialchars($titre) ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/tableau.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <style>
        .cash-flow-section h4 { border-bottom: 1px solid #eee; padding-bottom: 5px; margin-top: 20px; }
        .cash-flow-item { padding-left: 20px; }
        .cash-flow-subtotal { font-weight: bold; background-color: #f9f9f9; padding: 5px 0; }
        .cash-flow-final-total { font-weight: bold; font-size: 1.2em; background-color: #e6f7ff; padding: 10px; }
        .amount-inflow { color: #28a745; } /* Green */
        .amount-outflow { color: #dc3545; } /* Red */
    </style>
</head>
<body>
    <div class="container">
        <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Sélectionner la Période</h3>
            </div>
            <div class="panel-body">
                <form action="" method="POST" class="form-inline">
                    <div class="form-group mr-2">
                        <label for="start_date">Date de Début :</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" required>
                    </div>
                    <div class="form-group mr-2">
                        <label for="end_date">Date de Fin :</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" required>
                    </div>
                    <button type="submit" name="generate_report" class="btn btn-primary">
                        <span class="glyphicon glyphicon-stats"></span> Générer le Rapport
                    </button>
                </form>
            </div>
        </div>

        <?php if (isset($_POST['generate_report']) && empty($message)): ?>
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">Tableau des Flux de Trésorerie</h3>
                    <p class="text-muted small">Période du <?= htmlspecialchars(date('d/m/Y', strtotime($startDate))) ?> au <?= htmlspecialchars(date('d/m/Y', strtotime($endDate))) ?></p>
                </div>
                <div class="panel-body">

                    <div class="row">
                        <div class="col-xs-offset-6 col-xs-6 text-right">
                            <p>Solde de trésorerie au début de la période:</p>
                            <h4 class="amount-<?= $cashFlowData['beginning_cash_balance'] >= 0 ? 'inflow' : 'outflow' ?>">
                                <?= htmlspecialchars(number_format($cashFlowData['beginning_cash_balance'], 2, ',', ' ')) ?>
                            </h4>
                        </div>
                    </div>
                    <hr>

                    <div class="cash-flow-section">
                        <h4>Flux de trésorerie liés aux activités d'exploitation</h4>
                        <div class="row cash-flow-item">
                            <div class="col-xs-6">Encaissements des clients</div>
                            <div class="col-xs-6 text-right amount-inflow"><?= htmlspecialchars(number_format($cashFlowData['operating_activities']['cash_from_customers'], 2, ',', ' ')) ?></div>
                        </div>
                        <div class="row cash-flow-item">
                            <div class="col-xs-6">Paiements aux fournisseurs</div>
                            <div class="col-xs-6 text-right amount-outflow"><?= htmlspecialchars(number_format($cashFlowData['operating_activities']['cash_paid_to_suppliers'] * -1, 2, ',', ' ')) ?></div>
                        </div>
                        <div class="row cash-flow-item">
                            <div class="col-xs-6">Paiements aux employés</div>
                            <div class="col-xs-6 text-right amount-outflow"><?= htmlspecialchars(number_format($cashFlowData['operating_activities']['cash_paid_to_employees'] * -1, 2, ',', ' ')) ?></div>
                        </div>
                        <div class="row cash-flow-item">
                            <div class="col-xs-6">Autres encaissements liés à l'exploitation</div>
                            <div class="col-xs-6 text-right amount-inflow"><?= htmlspecialchars(number_format($cashFlowData['operating_activities']['other_operating_cash_in'], 2, ',', ' ')) ?></div>
                        </div>
                        <div class="row cash-flow-item">
                            <div class="col-xs-6">Autres paiements liés à l'exploitation</div>
                            <div class="col-xs-6 text-right amount-outflow"><?= htmlspecialchars(number_format($cashFlowData['operating_activities']['other_operating_cash_out'] * -1, 2, ',', ' ')) ?></div>
                        </div>
                        <div class="row cash-flow-subtotal mt-2">
                            <div class="col-xs-6">Trésorerie nette des activités d'exploitation</div>
                            <div class="col-xs-6 text-right amount-<?= array_sum($cashFlowData['operating_activities']) >= 0 ? 'inflow' : 'outflow' ?>">
                                <?= htmlspecialchars(number_format(array_sum($cashFlowData['operating_activities']), 2, ',', ' ')) ?>
                            </div>
                        </div>
                    </div>

                    <div class="cash-flow-section">
                        <h4>Flux de trésorerie liés aux activités d'investissement</h4>
                        <div class="row cash-flow-item">
                            <div class="col-xs-6">Acquisitions d'immobilisations</div>
                            <div class="col-xs-6 text-right amount-outflow"><?= htmlspecialchars(number_format($cashFlowData['investing_activities']['purchase_of_assets'] * -1, 2, ',', ' ')) ?></div>
                        </div>
                        <div class="row cash-flow-item">
                            <div class="col-xs-6">Produits de la vente d'immobilisations</div>
                            <div class="col-xs-6 text-right amount-inflow"><?= htmlspecialchars(number_format($cashFlowData['investing_activities']['sale_of_assets'], 2, ',', ' ')) ?></div>
                        </div>
                        <div class="row cash-flow-subtotal mt-2">
                            <div class="col-xs-6">Trésorerie nette des activités d'investissement</div>
                            <div class="col-xs-6 text-right amount-<?= array_sum($cashFlowData['investing_activities']) >= 0 ? 'inflow' : 'outflow' ?>">
                                <?= htmlspecialchars(number_format(array_sum($cashFlowData['investing_activities']), 2, ',', ' ')) ?>
                            </div>
                        </div>
                    </div>

                    <div class="cash-flow-section">
                        <h4>Flux de trésorerie liés aux activités de financement</h4>
                        <div class="row cash-flow-item">
                            <div class="col-xs-6">Produits d'émission de capitaux propres</div>
                            <div class="col-xs-6 text-right amount-inflow"><?= htmlspecialchars(number_format($cashFlowData['financing_activities']['equity_issued'], 2, ',', ' ')) ?></div>
                        </div>
                        <div class="row cash-flow-item">
                            <div class="col-xs-6">Dividendes versés</div>
                            <div class="col-xs-6 text-right amount-outflow"><?= htmlspecialchars(number_format($cashFlowData['financing_activities']['dividends_paid'] * -1, 2, ',', ' ')) ?></div>
                        </div>
                        <div class="row cash-flow-item">
                            <div class="col-xs-6">Produits d'emprunts</div>
                            <div class="col-xs-6 text-right amount-inflow"><?= htmlspecialchars(number_format($cashFlowData['financing_activities']['debt_incurred'], 2, ',', ' ')) ?></div>
                        </div>
                        <div class="row cash-flow-item">
                            <div class="col-xs-6">Remboursements d'emprunts</div>
                            <div class="col-xs-6 text-right amount-outflow"><?= htmlspecialchars(number_format($cashFlowData['financing_activities']['debt_repaid'] * -1, 2, ',', ' ')) ?></div>
                        </div>
                        <div class="row cash-flow-subtotal mt-2">
                            <div class="col-xs-6">Trésorerie nette des activités de financement</div>
                            <div class="col-xs-6 text-right amount-<?= array_sum($cashFlowData['financing_activities']) >= 0 ? 'inflow' : 'outflow' ?>">
                                <?= htmlspecialchars(number_format(array_sum($cashFlowData['financing_activities']), 2, ',', ' ')) ?>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <div class="row cash-flow-final-total">
                        <div class="col-xs-6">Variation nette de la trésorerie</div>
                        <div class="col-xs-6 text-right amount-<?= $cashFlowData['net_change_in_cash'] >= 0 ? 'inflow' : 'outflow' ?>">
                            <?= htmlspecialchars(number_format($cashFlowData['net_change_in_cash'], 2, ',', ' ')) ?>
                        </div>
                    </div>
                    <hr>
                    <div class="row cash-flow-final-total">
                        <div class="col-xs-6">Solde de trésorerie à la fin de la période</div>
                        <div class="col-xs-6 text-right amount-<?= $cashFlowData['ending_cash_balance'] >= 0 ? 'inflow' : 'outflow' ?>">
                            <?= htmlspecialchars(number_format($cashFlowData['ending_cash_balance'], 2, ',', ' ')) ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif (isset($_POST['generate_report']) && !empty($message)): ?>
            <?php else: ?>
            <div class="alert alert-info">Sélectionnez une période et cliquez sur "Générer le Rapport" pour afficher le Tableau des Flux de Trésorerie.</div>
        <?php endif; ?>

    </div>
    <?php require_once '../../templates/footer.php'; ?>
</body>
</html>