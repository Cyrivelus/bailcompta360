<?php
// pages/operations/transactions/retrait.php

/**
 * Cette page gère l'interface et la logique pour les opérations de retrait.
 * Elle permet à un caissier de sélectionner un compte client et d'enregistrer
 * un retrait, en vérifiant que le solde est suffisant.
 */

session_start();

// 1. Inclure la connexion à la base de données en premier pour définir $pdo
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_comptes.php';
require_once '../../fonctions/gestion_operations_caisse.php';

// Assurez-vous que l'utilisateur est authentifié et que la caisse est ouverte
if (!isset($_SESSION['utilisateur_id'])) {
    header("Location: ../../auth/login.php"); 
    exit();
}

// Récupérer les informations de la session
$id_utilisateur = $_SESSION['utilisateur_id'];
$id_agence = $_SESSION['id_agence'] ?? null; // Assurez-vous que l'ID de l'agence est stocké en session

if (!$id_agence) {
    die("Agence non définie. Merci de vous reconnecter.");
}

$message = '';
$message_type = '';
$comptes_actifs = [];
$compte_selectionne = null;

try {
    // Récupérer la liste des comptes actifs
    $comptes_actifs = listerComptesActifs($pdo);

    // Si un compte est passé en paramètre, le présélectionner
    if (isset($_GET['id_compte']) && is_numeric($_GET['id_compte'])) {
        $id_compte_preselect = intval($_GET['id_compte']);
        $compte_selectionne = getCompteById($pdo, $id_compte_preselect);
    }

    // Traitement du formulaire de retrait
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['effectuer_retrait'])) {
        $id_compte = intval($_POST['id_compte']);
        $montant = floatval($_POST['montant']);
        $description = trim($_POST['description']);
        
        if ($montant <= 0) {
            throw new Exception("Le montant du retrait doit être supérieur à zéro.");
        }

        effectuerRetrait($pdo, $id_compte, $montant, $description, $id_agence, $id_utilisateur);
        
        $_SESSION['admin_message_success'] = "Le retrait de " . number_format($montant, 2, ',', ' ') . " F CFA a été effectué avec succès.";
        header("Location: retrait.php");
        exit();
    }
} catch (Exception $e) {
    $_SESSION['admin_message_error'] = "Erreur : " . $e->getMessage();
    header("Location: retrait.php");
    exit();
}

// Récupérer les messages de session s'ils existent
$message = $_SESSION['admin_message_success'] ?? ($_SESSION['admin_message_error'] ?? '');
$message_type = isset($_SESSION['admin_message_success']) ? 'success' : (isset($_SESSION['admin_message_error']) ? 'danger' : '');
unset($_SESSION['admin_message_success'], $_SESSION['admin_message_error']);

// Inclure les fichiers de vue
include '../../templates/header.php'; 
include '../../templates/navigation.php'; 
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Opération de Dépôt</h2>
        <a href="operations.php" class="btn btn-secondary">
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
                            <?php echo htmlspecialchars($compte['numero_compte'] . ' - ' . $compte['nom'] . ' ' . $compte['prenoms']); ?>
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

            <button type="submit" name="effectuer_retrait" class="btn btn-danger mt-3">
                <i class="fas fa-minus-circle"></i> Enregistrer le retrait
            </button>
        </form>
    </div>
</div>

<?php
// Inclure le footer de la page
include '../../templates/footer.php';
?>