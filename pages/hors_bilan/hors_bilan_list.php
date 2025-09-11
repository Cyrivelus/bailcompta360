<?php
// pages/hors-bilan/list.php

/**
 * Cette page gère l'affichage des engagements hors bilan.
 */

// Démarrer la session pour accéder aux variables de session.
session_start();

// Inclure la configuration de la base de données et les fonctions métier.
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_hors_bilan.php';

// Initialiser les variables pour les messages d'erreur ou de succès.
$message = '';
$message_type = '';
$engagements = [];

try {
    // Récupérer la liste des engagements hors bilan.
    $engagements = listerEngagementsHorsBilan($pdo);

} catch (Exception $e) {
    // Capturer les exceptions et afficher un message d'erreur.
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
    $engagements = [];
}

// Inclure les fichiers de gabarit pour l'interface utilisateur.
include '../../templates/header.php'; 
include '../../templates/navigation.php'; 
?>

<div class="container-fluid mt-4">
    <h2>Engagements Hors Bilan</h2>
    <hr>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type; ?>" role="alert">
            <?= htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <a href="ajouter_engagement_hors_bilan.php" class="btn btn-primary mb-3">Ajouter un engagement</a>

    <table class="table table-striped table-bordered">
        <thead class="thead-dark">
            <tr>
                <th>ID</th>
                <th>Type d'engagement</th>
                <th>Client</th>
                <th>Montant</th>
                <th>Date d'émission</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($engagements)): ?>
                <?php foreach ($engagements as $engagement): ?>
                    <tr>
                        <td><?= htmlspecialchars($engagement['id_engagement']); ?></td>
                        <td><?= htmlspecialchars($engagement['type_engagement']); ?></td>
                        <td><?= htmlspecialchars($engagement['nom_client'] . ' ' . $engagement['prenom_client']); ?></td>
                        <td><?= number_format($engagement['montant'], 2, ',', ' '); ?> €</td>
                        <td><?= htmlspecialchars($engagement['date_emission']); ?></td>
                        <td>
                            <a href="modifier_engagement.php?id=<?= htmlspecialchars($engagement['id_engagement']); ?>" class="btn btn-warning btn-sm">Modifier</a>
                            <form action="list.php" method="POST" class="d-inline">
                                <input type="hidden" name="action" value="supprimer">
                                <input type="hidden" name="id_engagement" value="<?= htmlspecialchars($engagement['id_engagement']); ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet engagement ?');">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center">Aucun engagement hors bilan trouvé.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php 
// Inclure le footer de la page.
include '../../templates/footer.php';
?>