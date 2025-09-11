<?php
// pages/credits/suivi_credit.php

/**
 * Interface de suivi de l'état des demandes de crédit.
 * Permet aux agents de voir, de filtrer et de traiter les demandes.
 */

// Utilisez __DIR__ pour des chemins d'inclusion fiables.

session_start();
require_once __DIR__ . '/../../fonctions/database.php';
require_once __DIR__ . '/../../fonctions/credits/gestion_credits.php';



// Initialiser l'objet de gestion des crédits, en lui passant l'objet PDO
$gestionCredits = new GestionCredits($pdo);

// Récupérer le statut à filtrer depuis l'URL, par défaut 'EN_COURS'
$statut_filtre = isset($_GET['statut']) ? $_GET['statut'] : 'EN_COURS';
$demandes = [];
$message = '';
$message_type = '';

try {
    // Récupérer toutes les demandes de crédit en fonction du statut
    $demandes = $gestionCredits->getDemandesByStatut($statut_filtre);

    if (empty($demandes)) {
        $message = "Aucune demande de crédit trouvée avec le statut '" . htmlspecialchars(str_replace('_', ' ', $statut_filtre)) . "'.";
        $message_type = 'info';
    }
} catch (Exception $e) {
    $message = "Erreur lors de la récupération des demandes : " . $e->getMessage();
    $message_type = 'danger';
}

// Inclure le header et la navigation
include __DIR__ . '/../../templates/navigation.php';
include __DIR__ . '/../../templates/header.php'; 
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Suivi des Demandes de Crédit</h2>
        <div class="btn-group" role="group" aria-label="Filtre par statut">
            <a href="suivi_credit.php?statut=EN_COURS" class="btn btn-<?php echo ($statut_filtre == 'EN_COURS') ? 'primary' : 'outline-primary'; ?>">En cours</a>
            <a href="suivi_credit.php?statut=APPROUVE" class="btn btn-<?php echo ($statut_filtre == 'APPROUVE') ? 'success' : 'outline-success'; ?>">Approuvé</a>
            <a href="suivi_credit.php?statut=REJETE" class="btn btn-<?php echo ($statut_filtre == 'REJETE') ? 'danger' : 'outline-danger'; ?>">Rejeté</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($demandes)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID Demande</th>
                        <th>Client</th>
                        <th>Type de crédit</th>
                        <th>Montant</th>
                        <th>Durée (mois)</th>
                        <th>Statut</th>
                        <th>Date de demande</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($demandes as $demande): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($demande['id']); ?></td>
                            <td><?php echo htmlspecialchars($demande['nom_client'] . ' ' . $demande['prenoms_client']); ?></td>
                            <td><?php echo htmlspecialchars($demande['type_credit']); ?></td>
                            <td><?php echo number_format($demande['montant'], 2, ',', ' '); ?></td>
                            <td><?php echo htmlspecialchars($demande['duree_mois']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    if ($demande['statut'] == 'APPROUVE') echo 'success';
                                    else if ($demande['statut'] == 'REJETE') echo 'danger';
                                    else echo 'warning';
                                ?>">
                                    <?php echo htmlspecialchars(str_replace('_', ' ', $demande['statut'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($demande['date_demande']); ?></td>
                            <td>
                                <a href="traiter_credit.php?id=<?php echo urlencode($demande['id']); ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-search-plus"></i> Traiter
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php 
// Inclure le footer de la page
include __DIR__ . '/../../templates/footer.php';
?>