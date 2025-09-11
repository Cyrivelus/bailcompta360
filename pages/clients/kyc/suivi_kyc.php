<?php
// pages/clients/kyc/suivi_kyc.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../database.php';
require_once '../../../fonctions/gestion_kyc.php';
require_once '../../../fonctions/gestion_clients.php';

$statut_filtre = isset($_GET['statut']) ? $_GET['statut'] : 'EN_ATTENTE';
$dossiers = [];
$message = '';
$message_type = '';

try {
    $dossiers = getDossiersKycByStatut($pdo, $statut_filtre);

    if (empty($dossiers)) {
        $message = "Aucun dossier trouvé avec le statut '" . htmlspecialchars(str_replace('_', ' ', $statut_filtre)) . "'.";
        $message_type = 'info';
    }
} catch (Exception $e) {
    $message = "Erreur lors de la récupération des dossiers : " . $e->getMessage();
    $message_type = 'danger';
}

include '../../../templates/navigation.php';
include '../../../templates/header.php'; 
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Suivi des Dossiers KYC</h2>
        <div class="btn-group" role="group" aria-label="Filtre par statut">
            <a href="suivi_kyc.php?statut=EN_ATTENTE" class="btn btn-<?= ($statut_filtre == 'EN_ATTENTE') ? 'primary' : 'outline-primary'; ?>">En attente</a>
            <a href="suivi_kyc.php?statut=VALIDE" class="btn btn-<?= ($statut_filtre == 'VALIDE') ? 'primary' : 'outline-primary'; ?>">Validé</a>
            <a href="suivi_kyc.php?statut=REJETE" class="btn btn-<?= ($statut_filtre == 'REJETE') ? 'primary' : 'outline-primary'; ?>">Rejeté</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type; ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($dossiers)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID Client</th>
                        <th>Nom du Client</th>
                        <th>Type de Pièce</th>
                        <th>Numéro de Pièce</th>
                        <th>Statut</th>
                        <th>Date de Vérification</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dossiers as $dossier): ?>
                        <tr>
                            <td><?= htmlspecialchars($dossier['id_client']); ?></td>
                            <td>
                                <!-- La requête SQL dans getDossiersKycByStatut renvoie ces deux champs -->
                                <!-- Ce code affichera le nom complet pour les particuliers et le nom abrégé ou raison sociale pour les entreprises -->
                                <?= htmlspecialchars($dossier['nom_client'] . ' ' . $dossier['prenoms_client']); ?>
                            </td>
                            <td><?= htmlspecialchars(ucfirst($dossier['type_piece_identite'])); ?></td>
                            <td><?= htmlspecialchars($dossier['numero_piece']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    if ($dossier['statut_verification'] == 'VALIDE') echo 'success';
                                    else if ($dossier['statut_verification'] == 'REJETE') echo 'danger';
                                    else echo 'warning';
                                ?>">
                                    <?= htmlspecialchars(str_replace('_', ' ', $dossier['statut_verification'])); ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($dossier['date_verification']); ?></td>
                            <td>
                                <a href="traiter_dossier.php?id=<?= urlencode($dossier['id']); ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> Voir détails
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
include '../../../templates/footer.php';
?>
