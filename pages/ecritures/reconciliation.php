<?php
session_start();

// Check user authentication and authorization
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: ../../login.php');
    exit;
}

require_once '../../templates/header.php';
require_once '../../templates/navigation.php';
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_logs.php'; // For logging

$titre = 'Réconciliation Bancaire';
$pdo = getPDO();

$message = '';
$messageType = ''; // 'success', 'danger', 'info', 'warning'

$selectedCompteId = $_GET['id_compte'] ?? null;
$viewMode = $_GET['view_mode'] ?? 'unreconciled'; // 'unreconciled' or 'reconciled'

// Get list of accounting accounts for dropdown
$comptesCompta = [];
try {
    $stmt = $pdo->query("SELECT ID_Compte, Libelle_Compte FROM Comptes_compta ORDER BY Libelle_Compte ASC");
    $comptesCompta = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logApplicationError("Erreur PDO lors de la récupération des comptes comptables: " . $e->getMessage());
    $message = "Erreur lors du chargement des comptes comptables. Impossible de procéder à la réconciliation.";
    $messageType = 'danger';
}

$transactions = [];
if ($selectedCompteId && is_numeric($selectedCompteId)) {
    try {
        $sql = "
            SELECT
                le.ID_Ligne,
                le.Montant,
                le.Sens,
                le.Libelle_Ligne,
                e.Date_Saisie,
                e.Description AS Ecriture_Description,
                e.Numero_Piece,
                le.is_reconciled,
                le.reconciled_at,
                u.Nom_Utilisateur AS reconciled_by_user
            FROM
                Lignes_Ecritures le
            JOIN
                Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
            LEFT JOIN
                Utilisateurs u ON le.reconciled_by = u.ID_Utilisateur
            WHERE
                le.ID_Compte = :id_compte
        ";

        if ($viewMode === 'unreconciled') {
            $sql .= " AND le.is_reconciled = 0";
        } elseif ($viewMode === 'reconciled') {
            $sql .= " AND le.is_reconciled = 1";
        }

        $sql .= " ORDER BY e.Date_Saisie DESC, le.ID_Ligne DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_compte', $selectedCompteId, PDO::PARAM_INT);
        $stmt->execute();
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        logApplicationError("Erreur PDO lors de la récupération des transactions pour la réconciliation: " . $e->getMessage());
        $message = "Erreur lors du chargement des transactions.";
        $messageType = 'danger';
    }
}

// --- Handle Reconciliation Action ---
if (isset($_POST['action']) && $_POST['action'] === 'reconcile_transactions') {
    $transactionIds = $_POST['transaction_ids'] ?? [];
    $compteIdForReconciliation = $_POST['reconcile_compte_id'] ?? null;

    if (empty($transactionIds) || !is_array($transactionIds)) {
        $message = "Aucune transaction sélectionnée pour la réconciliation.";
        $messageType = 'warning';
    } elseif (empty($compteIdForReconciliation) || $compteIdForReconciliation != $selectedCompteId) {
        $message = "Erreur de compte lors de la réconciliation. Veuillez recharger la page.";
        $messageType = 'danger';
    } else {
        $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
        $currentUserId = $_SESSION['user_id'];
        $reconciledAt = date('Y-m-d H:i:s');

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE Lignes_Ecritures
                SET is_reconciled = 1, reconciled_at = :reconciled_at, reconciled_by = :reconciled_by
                WHERE ID_Ligne IN ({$placeholders}) AND ID_Compte = :id_compte AND is_reconciled = 0
            ");

            $stmt->bindParam(':reconciled_at', $reconciledAt);
            $stmt->bindParam(':reconciled_by', $currentUserId, PDO::PARAM_INT);
            $stmt->bindParam(':id_compte', $compteIdForReconciliation, PDO::PARAM_INT);

            // Bind each ID in the array
            foreach ($transactionIds as $k => $id) {
                $stmt->bindValue(($k + 1), $id, PDO::PARAM_INT);
            }

            $stmt->execute();
            $rowCount = $stmt->rowCount();
            $pdo->commit();

            $message = "<strong>{$rowCount}</strong> transactions ont été réconciliées avec succès pour le compte sélectionné.";
            $messageType = 'success';
            logUserActivity("Réconciliation de {$rowCount} transactions effectuée par l'utilisateur ID: " . $_SESSION['user_id'] . " pour le compte ID: " . $compteIdForReconciliation . ".");

            // Redirect to refresh the list (stay on the same account and view mode)
            header("Location: reconciliation.php?id_compte={$selectedCompteId}&view_mode={$viewMode}&status=success&msg=" . urlencode("{$rowCount} transactions réconciliées."));
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Erreur de base de données lors de la réconciliation: " . $e->getMessage();
            $messageType = 'danger';
            logApplicationError("Erreur PDO lors de la réconciliation par l'utilisateur ID: " . $_SESSION['user_id'] . ". Erreur: " . $e->getMessage());
        }
    }
}

// Display messages from redirect (after successful reconciliation)
if (isset($_GET['status']) && $_GET['status'] === 'success' && isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
    $messageType = 'success';
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
    <style>
        .reco-table th, .reco-table td { vertical-align: middle; font-size: 0.9em; }
        .reco-table .amount-debit { color: #d9534f; } /* Red for Debit */
        .reco-table .amount-credit { color: #5cb85c; } /* Green for Credit */
        .reco-table .status-reconciled { color: #337ab7; font-weight: bold; } /* Blue */
        .reco-table .status-unreconciled { color: #f0ad4e; font-weight: bold; } /* Orange */
        .table-responsive { max-height: 600px; overflow-y: auto; } /* Scrollable table body */
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
                <h3 class="panel-title">Options de Réconciliation</h3>
            </div>
            <div class="panel-body">
                <form action="" method="GET" class="form-inline">
                    <div class="form-group">
                        <label for="id_compte">Sélectionner un Compte Bancaire :</label>
                        <select name="id_compte" id="id_compte" class="form-control" onchange="this.form.submit()">
                            <option value="">-- Choisir un compte --</option>
                            <?php foreach ($comptesCompta as $compte): ?>
                                <option value="<?= htmlspecialchars($compte['ID_Compte']) ?>"
                                    <?= ($selectedCompteId == $compte['ID_Compte']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($compte['Libelle_Compte']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group ml-3">
                        <label for="view_mode">Afficher :</label>
                        <select name="view_mode" id="view_mode" class="form-control" onchange="this.form.submit()">
                            <option value="unreconciled" <?= ($viewMode === 'unreconciled') ? 'selected' : '' ?>>Non réconciliées</option>
                            <option value="reconciled" <?= ($viewMode === 'reconciled') ? 'selected' : '' ?>>Réconciliées</option>
                        </select>
                    </div>
                    <?php if ($selectedCompteId): ?>
                        <a href="reconciliation.php" class="btn btn-default ml-2">Réinitialiser</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($selectedCompteId): ?>
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">Transactions
                        <?php if ($viewMode === 'unreconciled'): ?>
                            Non Réconciliées
                        <?php else: ?>
                            Réconciliées
                        <?php endif; ?>
                        pour <?= htmlspecialchars($comptesCompta[array_search($selectedCompteId, array_column($comptesCompta, 'ID_Compte'))]['Libelle_Compte'] ?? 'Compte Inconnu') ?>
                    </h3>
                </div>
                <div class="panel-body">
                    <?php if (empty($transactions)): ?>
                        <div class="alert alert-info">
                            <?php if ($viewMode === 'unreconciled'): ?>
                                Aucune transaction non réconciliée trouvée pour ce compte.
                            <?php else: ?>
                                Aucune transaction réconciliée trouvée pour ce compte.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="" onsubmit="return confirm('Confirmez-vous la réconciliation des transactions sélectionnées?');">
                            <input type="hidden" name="action" value="reconcile_transactions">
                            <input type="hidden" name="reconcile_compte_id" value="<?= htmlspecialchars($selectedCompteId) ?>">

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped reco-table">
                                    <thead>
                                        <tr>
                                            <?php if ($viewMode === 'unreconciled'): ?>
                                                <th><input type="checkbox" id="select_all_transactions"></th>
                                            <?php endif; ?>
                                            <th>Date</th>
                                            <th>Description Écriture</th>
                                            <th>Libellé Ligne</th>
                                            <th>Montant</th>
                                            <th>Sens</th>
                                            <th>Numéro Pièce</th>
                                            <?php if ($viewMode === 'reconciled'): ?>
                                                <th>Réconcilié le</th>
                                                <th>Par</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <?php if ($viewMode === 'unreconciled'): ?>
                                                    <td>
                                                        <input type="checkbox" name="transaction_ids[]" value="<?= htmlspecialchars($transaction['ID_Ligne']) ?>">
                                                    </td>
                                                <?php endif; ?>
                                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($transaction['Date_Saisie']))) ?></td>
                                                <td><?= htmlspecialchars($transaction['Ecriture_Description']) ?></td>
                                                <td><?= htmlspecialchars($transaction['Libelle_Ligne']) ?></td>
                                                <td class="<?= $transaction['Sens'] === 'D' ? 'amount-debit' : 'amount-credit' ?>">
                                                    <?= htmlspecialchars(number_format($transaction['Montant'], 2, ',', ' ')) ?>
                                                </td>
                                                <td><?= htmlspecialchars($transaction['Sens']) ?></td>
                                                <td><?= htmlspecialchars($transaction['Numero_Piece'] ?? 'N/A') ?></td>
                                                <?php if ($viewMode === 'reconciled'): ?>
                                                    <td><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($transaction['reconciled_at']))) ?></td>
                                                    <td><?= htmlspecialchars($transaction['reconciled_by_user'] ?? 'N/A') ?></td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($viewMode === 'unreconciled'): ?>
                                <button type="submit" class="btn btn-primary mt-3">
                                    <span class="glyphicon glyphicon-check"></span> Réconcilier les transactions sélectionnées
                                </button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">Veuillez sélectionner un compte bancaire pour commencer la réconciliation.</div>
        <?php endif; ?>

    </div>

    <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#select_all_transactions').change(function() {
                $('input[name="transaction_ids[]"]').prop('checked', $(this).prop('checked'));
            });
        });
    </script>
    <?php require_once '../../templates/footer.php'; ?>
</body>
</html>