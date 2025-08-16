<?php
// pages/comptes/details_compte.php

/**
 * Ce fichier affiche les détails complets d'un compte bancaire, y compris
 * le solde, les informations du titulaire et l'historique des transactions.
 */

require_once '../../database.php';
require_once '../../fonctions/gestion_comptes.php';
require_once '../../fonctions/gestion_clients.php';
require_once '../../fonctions/gestion_operations_caisse.php';

// Vérifier si un ID de compte est passé dans l'URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: liste_comptes.php"); // Rediriger si l'ID est manquant ou invalide
    exit();
}

$id_compte = intval($_GET['id']);
$message = '';
$message_type = '';

try {
    // Récupérer les informations du compte
    $compte = getCompteById($pdo, $id_compte);

    if (!$compte) {
        $message = "Compte introuvable.";
        $message_type = 'danger';
    } else {
        // Récupérer les informations du client titulaire
        $client = getClientById($pdo, $compte['id_client']);
        
        // Récupérer l'historique des transactions pour ce compte
        $transactions = getTransactionsByCompte($pdo, $id_compte);
    }
} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
    $compte = null;
    $client = null;
    $transactions = [];
}

// Inclure le header de la page
include '../../templates/header.php'; 
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Détails du Compte #<?php echo htmlspecialchars($compte['numero_compte'] ?? ''); ?></h2>
        <a href="liste_comptes.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour à la liste
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($compte && $client): ?>
        <div class="row">
            <div class="col-md-6">
                <div class="card p-4 shadow-sm mb-4">
                    <div class="card-body">
                        <h4 class="card-title text-primary"><i class="fas fa-info-circle"></i> Informations du Compte</h4>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item"><strong>Type :</strong> <?php echo htmlspecialchars($compte['nom_produit']); ?></li>
                            <li class="list-group-item"><strong>Numéro de compte :</strong> <?php echo htmlspecialchars($compte['numero_compte']); ?></li>
                            <li class="list-group-item"><strong>Solde actuel :</strong> <span class="badge bg-success fs-5"><?php echo number_format($compte['solde'], 2, ',', ' '); ?></span></li>
                            <li class="list-group-item"><strong>Date d'ouverture :</strong> <?php echo htmlspecialchars($compte['date_ouverture']); ?></li>
                            <li class="list-group-item"><strong>Statut :</strong> 
                                <span class="badge bg-<?php echo ($compte['statut'] == 'actif') ? 'success' : 'danger'; ?>">
                                    <?php echo htmlspecialchars(ucfirst($compte['statut'])); ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card p-4 shadow-sm mb-4">
                    <div class="card-body">
                        <h4 class="card-title text-primary"><i class="fas fa-user"></i> Titulaire du Compte</h4>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item"><strong>Nom :</strong> <?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenoms']); ?></li>
                            <li class="list-group-item"><strong>Date de naissance :</strong> <?php echo htmlspecialchars($client['date_naissance']); ?></li>
                            <li class="list-group-item"><strong>Téléphone :</strong> <?php echo htmlspecialchars($client['telephone']); ?></li>
                            <li class="list-group-item"><strong>Adresse :</strong> <?php echo htmlspecialchars($client['adresse']); ?></li>
                        </ul>
                        <div class="text-end mt-3">
                            <a href="../clients/details_client.php?id=<?php echo urlencode($client['id_client']); ?>" class="btn btn-sm btn-info">
                                <i class="fas fa-id-card"></i> Voir le profil complet
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <h4 class="mt-5 text-primary"><i class="fas fa-history"></i> Historique des Transactions</h4>
        <?php if (!empty($transactions)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Débit</th>
                            <th>Crédit</th>
                            <th>Nouveau Solde</th>
                            <th>Type d'opération</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $solde_courant = $compte['solde']; // Pour le calcul du solde
                        // On doit inverser le tableau pour afficher les transactions de la plus ancienne à la plus récente
                        $transactions_ordonnees = array_reverse($transactions);
                        ?>
                        <?php foreach ($transactions_ordonnees as $transaction): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($transaction['date_transaction']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                <td class="text-danger"><?php echo ($transaction['type'] == 'debit') ? number_format($transaction['montant'], 2, ',', ' ') : ''; ?></td>
                                <td class="text-success"><?php echo ($transaction['type'] == 'credit') ? number_format($transaction['montant'], 2, ',', ' ') : ''; ?></td>
                                <td><?php echo number_format($solde_courant, 2, ',', ' '); ?></td>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars(ucfirst($transaction['type_operation'])); ?></span></td>
                            </tr>
                            <?php 
                            // Mettre à jour le solde pour la ligne suivante
                            if ($transaction['type'] == 'debit') {
                                $solde_courant += $transaction['montant']; // On ajoute car on remonte dans le temps
                            } else {
                                $solde_courant -= $transaction['montant']; // On soustrait car on remonte dans le temps
                            }
                            ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center" role="alert">
                Ce compte n'a pas encore de transactions enregistrées.
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php 
// Fonctions à implémenter pour ce module
function getCompteById(PDO $pdo, int $id_compte): ?array {
    // Stub: Récupère les détails du compte (solde, statut, produit, etc.)
    return [
        'id_compte' => 1,
        'numero_compte' => '001-A-00001',
        'id_client' => 123,
        'solde' => 500000.00,
        'statut' => 'actif',
        'date_ouverture' => '2025-01-15',
        'nom_produit' => 'Compte Courant'
    ]; 
}

function getTransactionsByCompte(PDO $pdo, int $id_compte): array {
    // Stub: Récupère l'historique des transactions (crédits/débits) d'un compte.
    // Cette fonction devrait interroger la table des transactions ou des écritures.
    return [
        ['date_transaction' => '2025-08-14', 'description' => 'Retrait au guichet', 'montant' => 10000.00, 'type' => 'debit', 'type_operation' => 'retrait'],
        ['date_transaction' => '2025-08-13', 'description' => 'Virement de salaire', 'montant' => 500000.00, 'type' => 'credit', 'type_operation' => 'virement'],
        ['date_transaction' => '2025-08-12', 'description' => 'Paiement facture', 'montant' => 20000.00, 'type' => 'debit', 'type_operation' => 'paiement'],
    ]; 
}

// Inclure le footer de la page (fin du HTML)
include '../../templates/footer.php';
?>