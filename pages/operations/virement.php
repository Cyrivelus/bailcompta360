<?php
// pages/operations/transactions/virement.php

/**
 * Cette page gère l'interface et la logique pour les opérations de virement.
 * Elle permet d'enregistrer le transfert de fonds d'un compte source vers un compte de destination.
 */

require_once '../../../database.php';
require_once '../../../fonctions/gestion_operations_caisse.php';
require_once '../../../fonctions/gestion_comptes.php';
require_once '../../../fonctions/gestion_clients.php';

// Assurez-vous que l'utilisateur est authentifié et que la caisse est ouverte
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['id_agence'])) {
    header("Location: ../../index.php"); // Redirection si non authentifié
    exit();
}

$id_caissier = $_SESSION['user_id'];
$id_agence = $_SESSION['id_agence'];

$message = '';
$message_type = '';
$comptes_actifs = [];

try {
    // Récupérer la liste des comptes actifs pour les listes de sélection
    $comptes_actifs = listerComptesActifs($pdo);

    // Traitement du formulaire de virement
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id_compte_source = intval($_POST['id_compte_source']);
        $id_compte_destination = intval($_POST['id_compte_destination']);
        $montant = floatval($_POST['montant']);
        $description = trim($_POST['description']);
        
        // Appeler la fonction métier pour le virement
        if (effectuerTransfert($pdo, $id_compte_source, $id_compte_destination, $montant, $description, $id_agence)) {
            $message = "Le virement de " . number_format($montant, 2, ',', ' ') . " a été effectué avec succès.";
            $message_type = 'success';
        } else {
            throw new Exception("Échec de l'opération de virement.");
        }
    }

} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
}

// Inclure le header de la page
include '../../../templates/header.php'; 
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Opération de Virement</h2>
        <a href="../operations.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour aux opérations
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card p-4 shadow-sm">
        <form action="virement.php" method="post">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="id_compte_source" class="form-label">Compte Source <span class="text-danger">*</span></label>
                    <select class="form-select" id="id_compte_source" name="id_compte_source" required>
                        <option value="">Sélectionnez le compte à débiter</option>
                        <?php foreach ($comptes_actifs as $compte): ?>
                            <option value="<?php echo htmlspecialchars($compte['id_compte']); ?>">
                                <?php echo htmlspecialchars($compte['numero_compte'] . ' - ' . $compte['nom_client'] . ' ' . $compte['prenoms_client']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="id_compte_destination" class="form-label">Compte de Destination <span class="text-danger">*</span></label>
                    <select class="form-select" id="id_compte_destination" name="id_compte_destination" required>
                        <option value="">Sélectionnez le compte à créditer</option>
                        <?php foreach ($comptes_actifs as $compte): ?>
                            <option value="<?php echo htmlspecialchars($compte['id_compte']); ?>">
                                <?php echo htmlspecialchars($compte['numero_compte'] . ' - ' . $compte['nom_client'] . ' ' . $compte['prenoms_client']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="montant" class="form-label">Montant du virement <span class="text-danger">*</span></label>
                <input type="number" step="0.01" class="form-control" id="montant" name="montant" required min="0">
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">Description (facultatif)</label>
                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
            </div>
            
            <button type="submit" class="btn btn-warning mt-3">
                <i class="fas fa-exchange-alt"></i> Effectuer le virement
            </button>
        </form>
    </div>
</div>

<?php 
// NOTE: La fonction listerComptesActifs est la même que pour depot.php et retrait.php.

function listerComptesActifs(PDO $pdo): array {
    // Stub: Récupère une liste de comptes actifs pour l'utilisateur
    return [
        ['id_compte' => 1, 'numero_compte' => '001-C-00001', 'nom_client' => 'DIOUF', 'prenoms_client' => 'Bintou'],
        ['id_compte' => 2, 'numero_compte' => '001-C-00002', 'nom_client' => 'TRAORÉ', 'prenoms_client' => 'Moussa'],
        ['id_compte' => 3, 'numero_compte' => '001-C-00003', 'nom_client' => 'DIALLO', 'prenoms_client' => 'Fatou'],
    ];
}

// Inclure le footer de la page
include '../../../templates/footer.php';
?>