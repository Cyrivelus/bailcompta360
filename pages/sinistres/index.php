<?php
// pages/sinistres/index.php

/**
 * Ce fichier est l'index du module de gestion des sinistres.
 * Il affiche la liste de tous les sinistres en cours avec des options de gestion.
 */

// Démarrer la session en tout premier lieu
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Inclure les fonctions de base et de connexion
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_sinistres.php';

// Connexion à la base de données
// NOTE: Vous devez ajouter cette ligne pour que $pdo soit défini


$message = '';
$message_type = '';
$sinistres_en_cours = [];

try {
    // Récupérer tous les sinistres qui ne sont pas encore réglés
    $sinistres_en_cours = getSinistresEnCours($pdo);

} catch (Exception $e) {
    $message = "Erreur lors de la récupération des sinistres : " . $e->getMessage();
    $message_type = 'danger';
}

// Inclure le header et la navigation
// Ces fichiers peuvent maintenant démarrer la session sans problème
include '../../templates/navigation.php';
include '../../templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Gestion des Sinistres</h2>
        <a href="declarer_sinistre.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Déclarer un sinistre
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-warning text-dark">
            <i class="fas fa-exclamation-triangle"></i> Sinistres en Cours (déclarés & en évaluation)
        </div>
        <div class="card-body">
            <?php if (!empty($sinistres_en_cours)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID Sinistre</th>
                                <th>Date du Sinistre</th>
                                <th>Contrat n°</th>
                                <th>Client</th>
                                <th>Montant Réclamé</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sinistres_en_cours as $sinistre): ?>
                                <tr>
                                    <td><?= htmlspecialchars($sinistre['ID_Sinistre']) ?></td>
                                    <td><?= htmlspecialchars($sinistre['Date_Sinistre']) ?></td>
                                    <td><?= htmlspecialchars($sinistre['Numero_Police']) ?></td>
                                    <td><?= htmlspecialchars($sinistre['Nom_Client'] . ' ' . $sinistre['Prenoms_Client']) ?></td>
                                    <td><?= number_format($sinistre['Montant_Reclame'], 2, ',', ' ') ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($sinistre['Statut_Sinistre'])) ?></span>
                                    </td>
                                    <td>
                                        <a href="details_sinistre.php?id=<?= urlencode($sinistre['ID_Sinistre']) ?>" class="btn btn-sm btn-info me-2">
                                            <i class="fas fa-eye"></i> Voir
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-success text-center" role="alert">
                    <i class="fas fa-check-circle"></i> Aucun sinistre en cours.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
// Inclure le footer de la page
include '../../templates/footer.php';
?>