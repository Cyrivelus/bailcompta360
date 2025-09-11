<?php
// pages/clients/avance_client.php

/**
 * Cette page gère la recherche d'un client par son nom ou son ID
 * pour afficher les détails de ses avances.
 */

// Démarrer la session pour accéder aux variables de session.
session_start();

// Inclure la configuration de la base de données et les fonctions métier.
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_clients.php';
require_once '../../fonctions/gestion_avances.php';

// Initialiser les variables pour les messages d'erreur ou de succès.
$message = '';
$message_type = '';
$client = null;
$avances = [];
$showForm = true;

try {
    // Vérifier si le formulaire de recherche est soumis.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recherche'])) {
        $searchTerm = trim($_POST['recherche']);
        $showForm = false; // Ne plus afficher le formulaire après la recherche

        if (empty($searchTerm)) {
            throw new Exception("Veuillez saisir un nom ou un ID.");
        }

        // Tenter de trouver le client par son ID ou son nom/prénom.
        if (is_numeric($searchTerm)) {
            $id_client = intval($searchTerm);
            $client = trouverClientParId($pdo, $id_client);
        } else {
            $client = trouverClientParNomOuPrenom($pdo, $searchTerm);
        }
        
        if (!$client) {
            throw new Exception("Client introuvable.");
        }
        
        $id_client = $client['id_client'];

        // Récupérer la liste des avances pour ce client.
        $avances = listerAvancesParClient($pdo, $id_client);

    } elseif (isset($_GET['id']) && !empty($_GET['id'])) {
        // Gérer le cas où l'ID est toujours passé par l'URL (compatibilité).
        $id_client = intval($_GET['id']);
        $client = trouverClientParId($pdo, $id_client);
        if (!$client) {
            throw new Exception("Client introuvable.");
        }
        $avances = listerAvancesParClient($pdo, $id_client);
    }
    
} catch (Exception $e) {
    // Capturer les exceptions et afficher un message d'erreur.
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
    $client = null;
    $avances = [];
    $showForm = true; // Réafficher le formulaire en cas d'erreur
}

// Inclure les fichiers de gabarit pour l'interface utilisateur.
include '../../templates/header.php';
include '../../templates/navigation.php';
?>

<div class="container-fluid mt-4">
    <h2>Avances Client</h2>
    <hr>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type; ?>" role="alert">
            <?= htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($showForm || !$client): ?>
        <div class="card mb-4">
            <div class="card-header">Rechercher un client</div>
            <div class="card-body">
                <form action="avance_client.php" method="POST">
                    <div class="form-group mb-3">
                        <label for="recherche">Rechercher par ID ou nom/prénom</label>
                        <input type="text" class="form-control" id="recherche" name="recherche" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Rechercher</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($client): ?>
        <h3>Détails du client : <?= htmlspecialchars($client['prenoms']) . ' ' . htmlspecialchars($client['nom']); ?></h3>
        <div class="card mb-4">
            <div class="card-header">
                Informations du client
            </div>
            <div class="card-body">
                <p><strong>ID :</strong> <?= htmlspecialchars($client['id_client']); ?></p>
                <p><strong>Nom :</strong> <?= htmlspecialchars($client['nom']); ?></p>
                <p><strong>Prénom :</strong> <?= htmlspecialchars($client['prenoms']); ?></p>
                <p><strong>Email :</strong> <?= htmlspecialchars($client['email']); ?></p>
                <p><strong>Téléphone :</strong> <?= htmlspecialchars($client['telephone']); ?></p>
            </div>
        </div>

        <h3>Avances du client</h3>
        <a href="ajouter_avance.php?id_client=<?= htmlspecialchars($client['id_client']); ?>" class="btn btn-success mb-3">Ajouter une avance</a>

        <table class="table table-striped table-bordered">
            <thead class="thead-dark">
                <tr>
                    <th>ID Avance</th>
                    <th>Montant</th>
                    <th>Date d'émission</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($avances)): ?>
                    <?php foreach ($avances as $avance): ?>
                        <tr>
                            <td><?= htmlspecialchars($avance['id_avance']); ?></td>
                            <td><?= number_format($avance['montant'], 2, ',', ' '); ?> €</td>
                            <td><?= htmlspecialchars($avance['date_emission']); ?></td>
                            <td><?= htmlspecialchars($avance['statut']); ?></td>
                            <td>
                                <a href="modifier_avance.php?id=<?= htmlspecialchars($avance['id_avance']); ?>" class="btn btn-warning btn-sm">Modifier</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center">Aucune avance trouvée pour ce client.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php 
// Inclure le footer de la page.
include '../../templates/footer.php';
?>