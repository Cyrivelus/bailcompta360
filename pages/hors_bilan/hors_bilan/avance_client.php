<?php
// pages/hors_bilan/avance_client.php

/**
 * Cette page gère l'affichage et la suppression des avances client.
 */

// Démarrer la session pour accéder aux variables de session.
session_start();

// Inclure la configuration de la base de données et les fonctions métier.
require_once '../../fonctions/database.php';
require_once '../../fonctions/hors_bilan_fonctions.php';

// Initialiser les variables pour les messages d'erreur ou de succès.
$message = '';
$message_type = '';
$avances = [];

try {
    // Gérer la suppression d'une avance si le formulaire POST est soumis.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
        $id_avance_a_supprimer = intval($_POST['id_avance']);
        
        if (supprimerAvanceClient($pdo, $id_avance_a_supprimer)) {
            $message = "L'avance a été supprimée avec succès.";
            $message_type = 'success';
        } else {
            // Lancer une exception si la suppression échoue.
            throw new Exception("Échec de la suppression de l'avance.");
        }
    }
    
    // Récupérer la liste mise à jour des avances après toute opération.
    $avances = getAvancesClient($pdo);

} catch (Exception $e) {
    // Capturer les exceptions et afficher un message d'erreur à l'utilisateur.
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
    $avances = []; // Assurez-vous que le tableau est vide en cas d'erreur.
}

// Inclure les fichiers de gabarit pour l'interface utilisateur.
include '../../templates/header.php'; 
include '../../templates/navigation.php'; 
?>

<div class="container-fluid mt-4">
    <h2>Gestion des Avances Client</h2>
    <hr>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type; ?>" role="alert">
            <?= htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <a href="ajouter_avance.php" class="btn btn-primary mb-3">Ajouter une nouvelle avance</a>

    <table class="table table-striped table-bordered">
        <thead class="thead-dark">
            <tr>
                <th>ID Avance</th>
                <th>Client</th>
                <th>Montant</th>
                <th>Date</th>
                <th>Statut</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($avances)): ?>
                <?php foreach ($avances as $avance): ?>
                    <tr>
                        <td><?= htmlspecialchars($avance['id_avance']); ?></td>
                        <td><?= htmlspecialchars($avance['nom_client'] . ' ' . $avance['prenom_client']); ?></td>
                        <td><?= number_format($avance['montant'], 2, ',', ' '); ?> €</td>
                        <td><?= htmlspecialchars($avance['date_avance']); ?></td>
                        <td><?= htmlspecialchars($avance['statut']); ?></td>
                        <td><?= htmlspecialchars($avance['description']); ?></td>
                        <td>
                            <a href="modifier_avance.php?id=<?= htmlspecialchars($avance['id_avance']); ?>" class="btn btn-warning btn-sm">Modifier</a>
                            <form action="avance_client.php" method="POST" class="d-inline">
                                <input type="hidden" name="action" value="supprimer">
                                <input type="hidden" name="id_avance" value="<?= htmlspecialchars($avance['id_avance']); ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette avance ?');">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center">Aucune avance trouvée.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php 
// Inclure le footer de la page.
include '../../templates/footer.php';
?>