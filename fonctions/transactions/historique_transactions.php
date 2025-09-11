<?php
// pages/transactions/historique_transactions.php

/**
 * Vue détaillée de l'historique des transactions pour un compte client.
 */

require_once '../../database.php';
require_once '../../fonctions/transactions/gestion_transactions.php';
require_once '../../fonctions/comptes/gestion_comptes.php';
require_once '../../fonctions/clients/gestion_clients.php';

// Initialiser les objets de gestion
$gestionTransactions = new GestionTransactions($pdo);
$gestionComptes = new GestionComptes($pdo);
$gestionClients = new GestionClients($pdo);

// Récupérer l'ID du compte depuis l'URL
$id_compte = isset($_GET['id_compte']) ? intval($_GET['id_compte']) : null;
$compte = null;
$transactions = [];
$solde_initial = 0;
$solde_final = 0;
$client = null;
$message = '';
$message_type = '';

if (!$id_compte) {
    $message = "Veuillez spécifier un compte pour afficher l'historique.";
    $message_type = 'warning';
} else {
    try {
        // Récupérer les informations du compte et du client
        $compte = $gestionComptes->getCompteCompletById($id_compte);
        if (!$compte) {
            throw new Exception("Compte introuvable.");
        }
        $client = $gestionClients->getClientById($compte['id_client']);

        // Définir les dates de début et de fin pour le filtre
        $date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-01');
        $date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-t');

        // Récupérer les transactions
        $transactions = $gestionTransactions->getTransactionsByCompte(
            $id_compte,
            $date_debut,
            $date_fin
        );

        // Calculer les soldes initial et final
        $solde_initial = $gestionTransactions->getSoldeAvantDate($id_compte, $date_debut);
        $solde_final = $compte['solde']; // Le solde final est le solde actuel

    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Inclure le header et la navigation
include '../../templates/navigation.php';
include '../../templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Historique des Transactions</h2>
        <a href="../comptes/details_compte.php?id=<?php echo htmlspecialchars($id_compte); ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour au compte
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($compte): ?>
        <div class="card p-4 shadow-sm mb-4">
            <div class="card-header bg-dark text-white">
                <h4 class="mb-0">Relevé de compte : <?php echo htmlspecialchars($compte['numero_compte']); ?></h4>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Titulaire :</strong> <?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenoms']); ?>
                    </div>
                    <div class="col-md-6 text-end">
                        <strong>Solde actuel :</strong> <span class="badge bg-<?php echo ($solde_final >= 0) ? 'success' : 'danger'; ?> fs-5">
                            <?php echo number_format($solde_final, 2, ',', ' '); ?> $
                        </span>
                    </div>
                </div>

                <form action="historique_transactions.php" method="GET" class="mb-4">
                    <input type="hidden" name="id_compte" value="<?php echo htmlspecialchars($id_compte); ?>">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="date_debut" class="form-label">Date de début</label>
                            <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?php echo htmlspecialchars($date_debut); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="date_fin" class="form-label">Date de fin</label>
                            <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?php echo htmlspecialchars($date_fin); ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> Filtrer
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($transactions)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr class="table-primary">
                            <th>Date</th>
                            <th>Description</th>
                            <th>Débit</th>
                            <th>Crédit</th>
                            <th>Solde après Opération</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="table-light">
                            <td></td>
                            <td class="text-end"><strong>Solde initial au <?php echo htmlspecialchars($date_debut); ?></strong></td>
                            <td></td>
                            <td></td>
                            <td><strong><?php echo number_format($solde_initial, 2, ',', ' '); ?> $</strong></td>
                        </tr>
                        <?php
                        $solde_cumule = $solde_initial;
                        foreach ($transactions as $transaction):
                            $montant = $transaction['montant_total'];
                            $is_credit = ($transaction['type_transaction'] == 'CREDIT' || $transaction['type_transaction'] == 'VIREMENT_IN');
                            $solde_cumule += $is_credit ? $montant : -$montant;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($transaction['Date_Saisie']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars(str_replace('_', ' ', $transaction['type_transaction'])); ?></strong>
                                    : <?php echo htmlspecialchars($transaction['libelle']); ?>
                                </td>
                                <td class="text-danger"><?php echo !$is_credit ? number_format($montant, 2, ',', ' ') . ' $' : ''; ?></td>
                                <td class="text-success"><?php echo $is_credit ? number_format($montant, 2, ',', ' ') . ' $' : ''; ?></td>
                                <td><?php echo number_format($solde_cumule, 2, ',', ' '); ?> $</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">
                Aucune transaction trouvée pour ce compte entre le <?php echo htmlspecialchars($date_debut); ?> et le <?php echo htmlspecialchars($date_fin); ?>.
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// Inclure le footer de la page
include '../../templates/footer.php';
?>