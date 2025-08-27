<?php
// pages/operations/transactions/virement.php

/**
 * Cette page gère l'interface et la logique pour les opérations de virement.
 * Elle permet d'enregistrer le transfert de fonds d'un compte source vers un compte de destination.
 */

session_start();

// 1. Inclure la connexion à la base de données
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_comptes.php';
require_once '../../fonctions/gestion_operations_caisse.php'; // Pour la gestion de caisse

// Vérification de l'authentification et du rôle
if (!isset($_SESSION['utilisateur_id'])) {
    header("Location: ../../auth/login.php"); 
    exit();
}

// Récupérer les informations de la session
$id_utilisateur = $_SESSION['utilisateur_id'];
$id_agence = $_SESSION['id_agence'] ?? null; // Assurez-vous que l'ID de l'agence est stocké en session

$message = '';
$message_type = '';
$comptes_actifs = [];

try {
    // Récupérer la liste des comptes actifs pour les listes de sélection
    $comptes_actifs = listerComptesActifs($pdo);

    // Traitement du formulaire de virement
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['effectuer_virement'])) {
        $id_compte_source = intval($_POST['id_compte_source']);
        $id_compte_destination = intval($_POST['id_compte_destination']);
        $montant = floatval($_POST['montant']);
        $description = trim($_POST['description']);
        
        // Validation basique
        if ($id_compte_source === $id_compte_destination) {
            throw new Exception("Le compte source et le compte de destination ne peuvent pas être les mêmes.");
        }
        if ($montant <= 0) {
            throw new Exception("Le montant du virement doit être supérieur à zéro.");
        }

        // Appeler la fonction métier pour le virement
        effectuerTransfert($pdo, $id_compte_source, $id_compte_destination, $montant, $description, $id_agence, $id_utilisateur);
        
        $_SESSION['admin_message_success'] = "Le virement de " . number_format($montant, 2, ',', ' ') . " F CFA a été effectué avec succès.";
        header("Location: virement.php");
        exit();
    }

} catch (Exception $e) {
    $_SESSION['admin_message_error'] = "Erreur : " . $e->getMessage();
    header("Location: virement.php");
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
        <form action="virement.php" method="post">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="id_compte_source" class="form-label">Compte Source <span class="text-danger">*</span></label>
                    <select class="form-select" id="id_compte_source" name="id_compte_source" required>
                        <option value="">Sélectionnez le compte à débiter</option>
                        <?php foreach ($comptes_actifs as $compte): ?>
                            <option value="<?php echo htmlspecialchars($compte['id_compte']); ?>">
                                <?php echo htmlspecialchars($compte['numero_compte'] . ' - ' . $compte['nom'] . ' ' . $compte['prenoms']); ?>
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
                                <?php echo htmlspecialchars($compte['numero_compte'] . ' - ' . $compte['nom'] . ' ' . $compte['prenoms']); ?>
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
            
            <button type="submit" name="effectuer_virement" class="btn btn-warning mt-3">
                <i class="fas fa-exchange-alt"></i> Effectuer le virement
            </button>
        </form>
    </div>
</div>

<?php 
include '../../templates/footer.php';
?>