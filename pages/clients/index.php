<?php
// pages/clients/index.php

/**
 * Cette page gère l'affichage et la suppression des clients.
 */

// Démarrer la session pour accéder aux variables de session.
session_start();

// Inclure la configuration de la base de données et les fonctions métier.
// Le problème "could not find driver" vient de database.php ou de la configuration PHP.
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_clients.php';

// Initialiser les variables pour les messages d'erreur ou de succès.
$message = '';
$message_type = '';
$clients = [];

try {
    // Gérer la suppression d'un client si le formulaire POST est soumis.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
        $id_client_a_supprimer = intval($_POST['id_client']);
        
        if (supprimerClient($pdo, $id_client_a_supprimer)) {
            $message = "Le client a été supprimé avec succès.";
            $message_type = 'success';
        } else {
            // Lancer une exception si la suppression échoue.
            throw new Exception("Échec de la suppression du client. Il peut avoir des comptes ou des prêts associés.");
        }
    }
    
    // Récupérer la liste mise à jour des clients après toute opération.
    $clients = listerClients($pdo);

} catch (Exception $e) {
    // Capturer les exceptions et afficher un message d'erreur à l'utilisateur.
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
    $clients = []; // Assurez-vous que le tableau est vide en cas d'erreur.
}

// Inclure les fichiers de gabarit pour l'interface utilisateur.
include '../../templates/header.php'; 
include '../../templates/navigation.php'; 
?>

<div class="container-fluid mt-4">
    <h2>Gestion des clients</h2>
    <hr>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type; ?>" role="alert">
            <?= htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <a href="ajouter_client.php" class="btn btn-primary mb-3">Ajouter un nouveau client</a>

    <table class="table table-striped table-bordered">
        <thead class="thead-dark">
            <tr>
                <th>ID</th>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Email</th>
                <th>Téléphone</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($clients)): ?>
                <?php foreach ($clients as $client): ?>
                    <tr>
                        <td><?= htmlspecialchars($client['id_client']); ?></td>
                        <td><?= htmlspecialchars($client['nom']); ?></td>
                        <td><?= htmlspecialchars($client['prenom']); ?></td>
                        <td><?= htmlspecialchars($client['email']); ?></td>
                        <td><?= htmlspecialchars($client['telephone']); ?></td>
                        <td>
                            <a href="modifier_client.php?id=<?= htmlspecialchars($client['id_client']); ?>" class="btn btn-warning btn-sm">Modifier</a>
                            <form action="index.php" method="POST" class="d-inline">
                                <input type="hidden" name="action" value="supprimer">
                                <input type="hidden" name="id_client" value="<?= htmlspecialchars($client['id_client']); ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce client ?');">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center">Aucun client trouvé.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php 
// Inclure le footer de la page.
include '../../templates/footer.php';
?>