<?php
// pages/hors_bilan/index.php

/**
 * Cette page gère l'affichage et la suppression des hypothèques.
 */

// Démarrer la session pour accéder aux variables de session.
session_start();

// Inclure la configuration de la base de données et les fonctions métier.
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_hypotheques.php';

// Initialiser les variables pour les messages d'erreur ou de succès.
$message = '';
$message_type = '';
$hypotheques = [];

try {
    // Vérifier si un message de succès ou d'erreur est passé via l'URL (après une redirection).
    if (isset($_GET['message']) && isset($_GET['type'])) {
        $message = htmlspecialchars($_GET['message']);
        $message_type = htmlspecialchars($_GET['type']);
    }

    // Gérer la suppression d'une hypothèque si le formulaire POST est soumis.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
        $id_hypotheque_a_supprimer = filter_input(INPUT_POST, 'id_hypotheque', FILTER_VALIDATE_INT);
        
        if ($id_hypotheque_a_supprimer) {
            if (supprimerHypotheque($pdo, $id_hypotheque_a_supprimer)) {
                $message = "L'hypothèque a été supprimée avec succès.";
                $message_type = 'success';
            } else {
                throw new Exception("Échec de la suppression de l'hypothèque.");
            }
        } else {
            throw new Exception("ID d'hypothèque invalide.");
        }
    }
    
    // Récupérer la liste mise à jour des hypothèques après toute opération.
    $hypotheques = getHypotheques($pdo);

} catch (Exception $e) {
    // Capturer les exceptions et afficher un message d'erreur.
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
                <th>Date de création</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($hypotheques)): ?>
                <?php foreach ($hypotheques as $hypotheque): ?>
                    <tr>
                        <td><?= htmlspecialchars($hypotheque['id_hypotheque']); ?></td>
                        <td><?= htmlspecialchars($hypotheque['prenom_client'] . ' ' . $hypotheque['nom_client']); ?></td>
                        <td><?= number_format($hypotheque['montant_principal'], 2, ',', ' '); ?> €</td>
                        <td><?= htmlspecialchars($hypotheque['date_creation']); ?></td>
                        <td><?= htmlspecialchars($hypotheque['statut']); ?></td>
                        <td>
                            <a href="modifier_hypotheque.php?id=<?= htmlspecialchars($hypotheque['id_hypotheque']); ?>" class="btn btn-warning btn-sm">Modifier</a>
                            <form action="index.php" method="POST" class="d-inline">
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