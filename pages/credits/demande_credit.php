<?php
// pages/credits/demande_credit.php

/**
 * Interface de soumission d'une nouvelle demande de crédit.
 * Le formulaire collecte les informations nécessaires et les soumet à la base de données.
 */

// Start the session as the very first thing
session_start();

// Use __DIR__ for reliable inclusion paths, no matter where the script is called from.
// Go up two levels to reach the bailcompta360/ root directory.
require_once __DIR__ . '/../../fonctions/database.php';
require_once __DIR__ . '/../../fonctions/credits/gestion_credits.php';
require_once __DIR__ . '/../../fonctions/clients/gestion_clients.php';

// Initialize variables

$id_client = isset($_GET['id_client']) ? intval($_GET['id_client']) : null;
$message = '';
$message_type = '';
$client = null;

// Check if a client ID is provided and if it's valid
if ($id_client) {
    // Classes must be instantiated AFTER their files have been included
    $gestionClients = new GestionClients($pdo);
    $client = $gestionClients->getClientById($id_client);
    if (!$client) {
        $message = "Client introuvable.";
        $message_type = 'danger';
        $id_client = null;
    }
}

// Form processing if the method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id_client) {
    try {
        // Classes must be instantiated AFTER their files have been included
        $gestionCredits = new GestionCredits($pdo);
        
        // Validation and retrieval of form data
        $montant = filter_input(INPUT_POST, 'montant', FILTER_VALIDATE_FLOAT);
        $duree_mois = filter_input(INPUT_POST, 'duree_mois', FILTER_VALIDATE_INT);
        $taux_interet = filter_input(INPUT_POST, 'taux_interet', FILTER_VALIDATE_FLOAT);
        $type_credit = filter_input(INPUT_POST, 'type_credit', FILTER_SANITIZE_STRING);
        $motif = filter_input(INPUT_POST, 'motif', FILTER_SANITIZE_STRING);
        
        // Check that all data is valid
        if ($montant === false || $montant <= 0 || $duree_mois === false || $duree_mois <= 0 || $taux_interet === false || $taux_interet <= 0 || empty($type_credit) || empty($motif)) {
            throw new Exception("Veuillez remplir tous les champs du formulaire avec des valeurs valides.");
        }

        // Create the credit request via the credit manager
        $succes = $gestionCredits->creerDemande(
            $id_client,
            $montant,
            $duree_mois,
            $taux_interet,
            $type_credit,
            $motif
        );

        if ($succes) {
            $message = "La demande de crédit a été soumise avec succès. Elle est en cours de traitement.";
            $message_type = 'success';
            // Clear fields after success
            $_POST = [];
        } else {
            throw new Exception("Une erreur est survenue lors de la soumission de la demande. Veuillez réessayer.");
        }
    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Include the header and navigation
include __DIR__ . '/../../templates/navigation.php';
include __DIR__ . '/../../templates/header.php'; 
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Demande de Crédit</h2>
        <a href="../clients/details_client.php?id=<?php echo htmlspecialchars($id_client); ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour au profil client
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($client): ?>
        <div class="card p-4 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Formulaire de Demande pour <?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenoms']); ?></h4>
            </div>
            <div class="card-body">
                <form action="demande_credit.php?id_client=<?php echo htmlspecialchars($id_client); ?>" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="montant" class="form-label">Montant du crédit ($)</label>
                            <input type="number" step="0.01" class="form-control" id="montant" name="montant" required value="<?php echo isset($_POST['montant']) ? htmlspecialchars($_POST['montant']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="duree_mois" class="form-label">Durée du prêt (en mois)</label>
                            <input type="number" class="form-control" id="duree_mois" name="duree_mois" required value="<?php echo isset($_POST['duree_mois']) ? htmlspecialchars($_POST['duree_mois']) : ''; ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="taux_interet" class="form-label">Taux d'intérêt annuel (%)</label>
                            <input type="number" step="0.01" class="form-control" id="taux_interet" name="taux_interet" required value="<?php echo isset($_POST['taux_interet']) ? htmlspecialchars($_POST['taux_interet']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="type_credit" class="form-label">Type de crédit</label>
                            <select class="form-select" id="type_credit" name="type_credit" required>
                                <option value="">-- Sélectionner --</option>
                                <option value="Personnel" <?php echo (isset($_POST['type_credit']) && $_POST['type_credit'] == 'Personnel') ? 'selected' : ''; ?>>Personnel</option>
                                <option value="Hypothecaire" <?php echo (isset($_POST['type_credit']) && $_POST['type_credit'] == 'Hypothecaire') ? 'selected' : ''; ?>>Hypothécaire</option>
                                <option value="Automobile" <?php echo (isset($_POST['type_credit']) && $_POST['type_credit'] == 'Automobile') ? 'selected' : ''; ?>>Automobile</option>
                                <option value="Entreprise" <?php echo (isset($_POST['type_credit']) && $_POST['type_credit'] == 'Entreprise') ? 'selected' : ''; ?>>Entreprise</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="motif" class="form-label">Motif de la demande</label>
                        <textarea class="form-control" id="motif" name="motif" rows="3" required><?php echo isset($_POST['motif']) ? htmlspecialchars($_POST['motif']) : ''; ?></textarea>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg mt-3">Soumettre la Demande</button>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning text-center">
            Veuillez sélectionner un client pour effectuer une demande de crédit.
            <a href="../clients/liste_clients.php" class="alert-link">Aller à la liste des clients</a>.
        </div>
    <?php endif; ?>
</div>

<?php 
// Include the page footer
include __DIR__ . '/../../templates/footer.php';
?>