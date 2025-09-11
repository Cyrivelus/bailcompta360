<?php
// pages/credits/gestion_hypotheques.php

/**
 * Interface de gestion des garanties hypothécaires.
 * Permet d'enregistrer et de suivre les hypothèques liées aux prêts.
 */
session_start();
require_once '../../database.php';
require_once '../../fonctions/credits/gestion_credits.php';
require_once '../../fonctions/clients/gestion_clients.php';

// Initialize variables
$id_client = isset($_GET['id_client']) ? intval($_GET['id_client']) : null;
$message = '';
$message_type = '';
$client = null;
$hypotheques = [];
$gestionCredits = new GestionCredits($pdo);

// Check if a valid client ID is provided
if ($id_client) {
    $gestionClients = new GestionClients($pdo);
    $client = $gestionClients->getClientById($id_client);
    if (!$client) {
        $message = "Client introuvable.";
        $message_type = 'danger';
        $id_client = null;
    } else {
        // Get all mortgages for this client
        $hypotheques = $gestionCredits->getHypothequesByClient($id_client);
    }
}

// Handle form submission for creating a new mortgage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id_client) {
    try {
        $valeur_bien = filter_input(INPUT_POST, 'valeur_bien', FILTER_VALIDATE_FLOAT);
        $montant_emprunte = filter_input(INPUT_POST, 'montant_emprunte', FILTER_VALIDATE_FLOAT);
        $date_emprunt = $_POST['date_emprunt'];
        $duree_emprunt = filter_input(INPUT_POST, 'duree_emprunt', FILTER_VALIDATE_INT);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);

        if ($valeur_bien === false || $valeur_bien <= 0 ||
            $montant_emprunte === false || $montant_emprunte <= 0 ||
            empty($date_emprunt) || $duree_emprunt === false || $duree_emprunt <= 0) {
            throw new Exception("Veuillez remplir tous les champs obligatoires avec des valeurs valides.");
        }

        $succes = $gestionCredits->creerHypotheque(
            $id_client,
            $valeur_bien,
            $montant_emprunte,
            $date_emprunt,
            $duree_emprunt,
            $description
        );

        if ($succes) {
            $message = "L'hypothèque a été enregistrée avec succès.";
            $message_type = 'success';
            header("Location: gestion_hypotheques.php?id_client=$id_client&msg=" . urlencode($message) . "&type=" . $message_type);
            exit();
        } else {
            throw new Exception("Une erreur est survenue lors de l'enregistrement de l'hypothèque.");
        }

    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $message_type = 'danger';
    }
}

// Handle message from redirection
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = isset($_GET['type']) ? $_GET['type'] : 'info';
}

// Include header and navigation
include '../../templates/navigation.php';
include '../../templates/header.php'; 
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Gestion des Garanties Hypothécaires</h2>
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
        <div class="card p-4 shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Enregistrer une nouvelle hypothèque pour <?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenoms']); ?></h4>
            </div>
            <div class="card-body">
                <form action="gestion_hypotheques.php?id_client=<?php echo htmlspecialchars($id_client); ?>" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="valeur_bien" class="form-label">Valeur du bien ($)</label>
                            <input type="number" step="0.01" class="form-control" id="valeur_bien" name="valeur_bien" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="montant_emprunte" class="form-label">Montant du prêt ($)</label>
                            <input type="number" step="0.01" class="form-control" id="montant_emprunte" name="montant_emprunte" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_emprunt" class="form-label">Date d'emprunt</label>
                            <input type="date" class="form-control" id="date_emprunt" name="date_emprunt" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="duree_emprunt" class="form-label">Durée de l'emprunt (mois)</label>
                            <input type="number" class="form-control" id="duree_emprunt" name="duree_emprunt" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description du bien</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg mt-3">
                            <i class="fas fa-plus-circle"></i> Enregistrer l'hypothèque
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <h4 class="mt-5"><i class="fas fa-list-alt"></i> Hypothèques existantes</h4>
        <?php if (!empty($hypotheques)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Valeur du bien</th>
                            <th>Montant emprunté</th>
                            <th>Date d'emprunt</th>
                            <th>Durée (mois)</th>
                            <th>Statut</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hypotheques as $hypotheque): ?>
                            <tr>
                                <td><?php echo number_format($hypotheque['valeur_bien'], 2, ',', ' '); ?></td>
                                <td><?php echo number_format($hypotheque['montant_emprunte'], 2, ',', ' '); ?></td>
                                <td><?php echo htmlspecialchars($hypotheque['date_emprunt']); ?></td>
                                <td><?php echo htmlspecialchars($hypotheque['duree_emprunt']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo ($hypotheque['statut'] == 'actif') ? 'success' : 'secondary'; ?>">
                                        <?php echo htmlspecialchars(ucfirst($hypotheque['statut'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($hypotheque['description']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">
                Ce client n'a pas d'hypothèque enregistrée.
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-warning text-center">
            Veuillez sélectionner un client pour gérer ses hypothèques.
            <a href="../clients/liste_clients.php" class="alert-link">Aller à la liste des clients</a>.
        </div>
    <?php endif; ?>
</div>

<?php 
// Include footer
include '../../templates/footer.php';
?>