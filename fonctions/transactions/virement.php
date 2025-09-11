<?php
// pages/transactions/virement.php

/**
 * Interface pour effectuer un virement bancaire entre deux comptes.
 * Gère le formulaire, les vérifications et l'enregistrement de la transaction.
 */

require_once '../../database.php';
require_once '../../fonctions/transactions/gestion_transactions.php';
require_once '../../fonctions/comptes/gestion_comptes.php';
require_once '../../fonctions/lcb/gestion_lcb.php';

// Initialiser les objets de gestion
$gestionComptes = new GestionComptes($pdo);
$gestionTransactions = new GestionTransactions($pdo);
$gestionLCB = new GestionLCB($pdo);

$comptes = $gestionComptes->getTousComptes();
$message = '';
$message_type = '';

// Traitement du formulaire si la méthode est POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation et récupération des données du formulaire
        $compte_source_id = filter_input(INPUT_POST, 'compte_source', FILTER_VALIDATE_INT);
        $compte_destination_id = filter_input(INPUT_POST, 'compte_destination', FILTER_VALIDATE_INT);
        $montant = filter_input(INPUT_POST, 'montant', FILTER_VALIDATE_FLOAT);
        $motif = filter_input(INPUT_POST, 'motif', FILTER_SANITIZE_STRING);

        // Vérification des champs obligatoires
        if ($compte_source_id === false || $compte_destination_id === false || $montant === false || $montant <= 0 || empty($motif)) {
            throw new Exception("Veuillez remplir tous les champs avec des valeurs valides.");
        }

        // Vérification que les comptes sont différents
        if ($compte_source_id === $compte_destination_id) {
            throw new Exception("Le compte source et le compte de destination doivent être différents.");
        }

        // Récupérer les informations des comptes
        $compte_source = $gestionComptes->getCompteById($compte_source_id);
        $compte_destination = $gestionComptes->getCompteById($compte_destination_id);

        if (!$compte_source || !$compte_destination) {
            throw new Exception("Compte source ou destination introuvable.");
        }

        // Exécuter le virement
        $succes = $gestionTransactions->effectuerVirement(
            $compte_source_id,
            $compte_destination_id,
            $montant,
            $motif
        );

        if ($succes) {
            $message = "Virement de " . number_format($montant, 2, ',', ' ') . "$ du compte " . htmlspecialchars($compte_source['numero_compte']) . " vers le compte " . htmlspecialchars($compte_destination['numero_compte']) . " effectué avec succès.";
            $message_type = 'success';
            
            // Déclenchement de la détection LCB après une transaction
            $gestionLCB->analyserTransaction($compte_source_id, $montant, $motif);

        } else {
            throw new Exception("Une erreur est survenue lors de l'exécution du virement. Le solde est peut-être insuffisant.");
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
        <h2>Effectuer un Virement</h2>
        <a href="../dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour au tableau de bord
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
            <h4 class="mb-0">Détails du Virement</h4>
        </div>
        <div class="card-body">
            <form action="virement.php" method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="compte_source" class="form-label">Compte Source</label>
                        <select class="form-select" id="compte_source" name="compte_source" required>
                            <option value="">Sélectionner un compte...</option>
                            <?php foreach ($comptes as $compte): ?>
                                <option value="<?php echo htmlspecialchars($compte['id_compte']); ?>">
                                    <?php echo htmlspecialchars($compte['numero_compte'] . ' - ' . $compte['nom'] . ' ' . $compte['prenoms']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="compte_destination" class="form-label">Compte de Destination</label>
                        <select class="form-select" id="compte_destination" name="compte_destination" required>
                            <option value="">Sélectionner un compte...</option>
                            <?php foreach ($comptes as $compte): ?>
                                <option value="<?php echo htmlspecialchars($compte['id_compte']); ?>">
                                    <?php echo htmlspecialchars($compte['numero_compte'] . ' - ' . $compte['nom'] . ' ' . $compte['prenoms']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="montant" class="form-label">Montant du virement ($)</label>
                    <input type="number" step="0.01" class="form-control" id="montant" name="montant" required>
                </div>
                <div class="mb-3">
                    <label for="motif" class="form-label">Motif du virement</label>
                    <input type="text" class="form-control" id="motif" name="motif" required>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg mt-3">Effectuer le Virement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
// Inclure le footer de la page
include '../../templates/footer.php';
?>