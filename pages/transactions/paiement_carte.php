<?php
// pages/transactions/paiement_carte.php

/**
 * Interface pour enregistrer un paiement par carte.
 * Gère le formulaire, les vérifications et l'enregistrement de la transaction.
 */

require_once '../../database.php';
require_once '../../fonctions/transactions/gestion_transactions.php';
require_once '../../fonctions/comptes/gestion_comptes.php';
require_once '../../fonctions/cartes/gestion_cartes.php';

// Initialiser les objets de gestion
$gestionTransactions = new GestionTransactions($pdo);
$gestionComptes = new GestionComptes($pdo);
$gestionCartes = new GestionCartes($pdo);

$message = '';
$message_type = '';

// Traitement du formulaire si la méthode est POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation et récupération des données du formulaire
        $numero_carte = filter_input(INPUT_POST, 'numero_carte', FILTER_SANITIZE_STRING);
        $montant = filter_input(INPUT_POST, 'montant', FILTER_VALIDATE_FLOAT);
        $motif = filter_input(INPUT_POST, 'motif', FILTER_SANITIZE_STRING);

        // Vérification des champs obligatoires
        if (empty($numero_carte) || $montant === false || $montant <= 0 || empty($motif)) {
            throw new Exception("Veuillez remplir tous les champs avec des valeurs valides.");
        }

        // 1. Récupérer la carte et le compte associé
        $carte = $gestionCartes->getCarteByNumero($numero_carte);
        if (!$carte) {
            throw new Exception("La carte spécifiée n'existe pas ou est invalide.");
        }

        // 2. Vérifier le statut de la carte (optionnel mais recommandé)
        if ($carte['statut'] !== 'active') {
             throw new Exception("Cette carte est inactive. Le paiement ne peut pas être effectué.");
        }
        
        $compte = $gestionComptes->getCompteById($carte['id_compte']);
        if (!$compte) {
            throw new Exception("Aucun compte bancaire associé à cette carte.");
        }
        
        // 3. Effectuer le paiement (débit du compte)
        // La fonction `effectuerPaiement` pourrait être une méthode générique de transaction
        $succes = $gestionTransactions->effectuerDebit(
            $compte['id_compte'],
            $montant,
            $motif . " (paiement par carte)"
        );

        if ($succes) {
            $message = "Paiement de " . number_format($montant, 2, ',', ' ') . "$ effectué avec succès sur le compte " . htmlspecialchars($compte['numero_compte']) . ".";
            $message_type = 'success';
        } else {
            throw new Exception("Une erreur est survenue lors de l'exécution du paiement. Le solde est peut-être insuffisant.");
        }

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
        <h2>Paiement par Carte</h2>
        <a href="depot.php" class="btn btn-secondary">
            <i class="fas fa-search"></i> Historique des paiements
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card p-4 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Détails du Paiement</h4>
        </div>
        <div class="card-body">
            <form action="paiement_carte.php" method="POST">
                <div class="mb-3">
                    <label for="numero_carte" class="form-label">Numéro de Carte</label>
                    <input type="text" class="form-control" id="numero_carte" name="numero_carte" required placeholder="XXXX-XXXX-XXXX-XXXX">
                    <div class="form-text">Saisir le numéro de carte à débiter.</div>
                </div>
                <div class="mb-3">
                    <label for="montant" class="form-label">Montant du Paiement ($)</label>
                    <input type="number" step="0.01" class="form-control" id="montant" name="montant" required>
                </div>
                <div class="mb-3">
                    <label for="motif" class="form-label">Motif du Paiement</label>
                    <input type="text" class="form-control" id="motif" name="motif" required>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg mt-3">Enregistrer le Paiement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
// Inclure le footer de la page
include '../../templates/footer.php';
?>