<?php
// pages/hors_bilan/hypotheque.php

/**
 * Cette page gère l'affichage et la suppression des hypothèques.
 */

// Démarrer la session pour accéder aux variables de session.
session_start();

// Inclure la configuration de la base de données et les fonctions métier.
require_once '../../fonctions/database.php';
require_once '../../fonctions/hors_bilan_fonctions.php';

// Initialiser les variables pour les messages d'erreur ou de succès.
$message = '';
$message_type = '';
$hypotheques = [];

try {
    // Gérer la suppression d'une hypothèque si le formulaire POST est soumis.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
        $id_hypotheque_a_supprimer = intval($_POST['id_hypotheque']);
        
        if (supprimerHypotheque($pdo, $id_hypotheque_a_supprimer)) {
            $message = "L'hypothèque a été supprimée avec succès.";
            $message_type = 'success';
        } else {
            // Lancer une exception si la suppression échoue.
            throw new Exception("Échec de la suppression de l'hypothèque.");
        }
    }
    
    // Récupérer la liste mise à jour des hypothèques après toute opération.
    $hypotheques = getHypotheques($pdo);

} catch (Exception $e) {
    // Capturer les exceptions et afficher un message d'erreur à l'utilisateur.
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
    $hypotheques = []; // Assurez-vous que le tableau est vide en cas d'erreur.
}

// Inclure les fichiers de gabarit pour l'interface utilisateur.
include '../../templates/header.php'; 
include '../../templates/navigation.php'; 
?>

<div class="container-fluid mt-4">
    <h2>Gestion des Hypothèques</h2>
    <hr>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type; ?>" role="alert">
            <?= htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <a href="ajouter_hypotheque.php" class="btn btn-primary mb-3">Ajouter une nouvelle hypothèque</a>

    <table class="table table-striped table-bordered">
        <thead class="thead-dark">
            <tr>
                <th>ID Hypothèque</th>
                <th>Client</th>
                <th>Montant</th>
                <th>Durée</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($hypotheques)): ?>
                <?php foreach ($hypotheques as $hypotheque): ?>
                    <tr>
                        <td><?= htmlspecialchars($hypotheque['id_hypotheque']); ?></td>
                        <td><?= htmlspecialchars($hypotheque['nom_client'] . ' ' . $hypotheque['prenom_client']); ?></td>
                        <td><?= number_format($hypotheque['montant_emprunte'], 2, ',', ' '); ?> €</td>
                        <td><?= htmlspecialchars($hypotheque['duree_emprunt']); ?></td>
                        <td><?= htmlspecialchars($hypotheque['statut']); ?></td>
                        <td>
                            <a href="modifier_hypotheque.php?id=<?= htmlspecialchars($hypotheque['id_hypotheque']); ?>" class="btn btn-warning btn-sm">Modifier</a>
                            <form action="hypotheque.php" method="POST" class="d-inline">
                                <input type="hidden" name="action" value="supprimer">
                                <input type="hidden" name="id_hypotheque" value="<?= htmlspecialchars($hypotheque['id_hypotheque']); ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette hypothèque ?');">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center">Aucune hypothèque trouvée.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php 
// Inclure le footer de la page.
include '../../templates/footer.php';
?>