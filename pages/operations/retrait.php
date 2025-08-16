<?php
// pages/operations/transactions/retrait.php

/**
 * Cette page gère l'interface et la logique pour les opérations de retrait.
 * Elle permet à un caissier de sélectionner un compte client et d'enregistrer
 * un retrait, en vérifiant que le solde est suffisant.
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
$compte_selectionne = null;

try {
    // Récupérer la liste des comptes actifs pour la sélection
    $comptes_actifs = listerComptesActifs($pdo);

    // Si un compte est passé en paramètre, le présélectionner
    if (isset($_GET['id_compte']) && is_numeric($_GET['id_compte'])) {
        $id_compte_preselect = intval($_GET['id_compte']);
        $compte_selectionne = getCompteById($pdo, $id_compte_preselect);
    }
    
    // Traitement du formulaire de retrait
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id_compte = intval($_POST['id_compte']);
        $montant = floatval($_POST['montant']);
        $description = trim($_POST['description']);

        // Appeler la fonction métier pour le retrait
        if (effectuerRetrait($pdo, $id_compte, $montant, $description, $id_agence)) {
            $message = "Le retrait de " . number_format($montant, 2, ',', ' ') . " a été effectué avec succès.";
            $message_type = 'success';
            // Réinitialiser le formulaire après le succès
            $compte_selectionne = null;
        } else {
            // La fonction `effectuerRetrait` lève une exception en cas d'erreur
            // donc cette partie du code ne sera atteinte que si la fonction retourne false,
            // ce qui ne devrait pas arriver avec une bonne gestion des exceptions.
            throw new Exception("Échec de l'opération de retrait.");
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
        <h2>Opération de Retrait</h2>
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
        <form action="retrait.php" method="post">
            <div class="mb-3">
                <label for="id_compte" class="form-label">Compte client <span class="text-danger">*</span></label>
                <select class="form-select" id="id_compte" name="id_compte" required>
                    <option value="">Sélectionnez un compte</option>
                    <?php foreach ($comptes_actifs as $compte): ?>
                        <option value="<?php echo htmlspecialchars($compte['id_compte']); ?>"
                            <?php echo ($compte_selectionne && $compte_selectionne['id_compte'] == $compte['id_compte']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($compte['numero_compte'] . ' - ' . $compte['nom_client'] . ' ' . $compte['prenoms_client']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="montant" class="form-label">Montant du retrait <span class="text-danger">*</span></label>
                <input type="number" step="0.01" class="form-control" id="montant" name="montant" required min="0">
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">Description (facultatif)</label>
                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
            </div>
            
            <button type="submit" class="btn btn-danger mt-3">
                <i class="fas fa-minus-circle"></i> Enregistrer le retrait
            </button>
        </form>
    </div>
</div>

<?php 
// NOTE: Les fonctions listerComptesActifs et getCompteById sont les mêmes que pour depot.php.
// Assurez-vous qu'elles sont correctement implémentées dans leurs modules respectifs.

function listerComptesActifs(PDO $pdo): array {
    // Stub: Récupère une liste de comptes actifs pour l'utilisateur
    return [
        ['id_compte' => 1, 'numero_compte' => '001-C-00001', 'nom_client' => 'DIOUF', 'prenoms_client' => 'Bintou'],
        ['id_compte' => 2, 'numero_compte' => '001-C-00002', 'nom_client' => 'TRAORÉ', 'prenoms_client' => 'Moussa'],
    ];
}

function getCompteById(PDO $pdo, int $id_compte): ?array {
    // Stub: Récupère les détails d'un compte
    return ['id_compte' => $id_compte, 'numero_compte' => '001-C-00001'];
}

// Inclure le footer de la page
include '../../../templates/footer.php';
?>