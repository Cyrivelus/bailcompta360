<?php
// pages/clients/details_client.php

/**
 * Ce fichier affiche les détails complets d'un client, y compris ses informations
 * personnelles et ses comptes associés.
 */

require_once '../../database.php';
require_once '../../fonctions/gestion_clients.php'; // Fonctions pour la gestion des clients
require_once '../../fonctions/gestion_comptes.php'; // Fonctions pour la gestion des comptes

// Vérifier si un ID de client est passé dans l'URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: liste_clients.php"); // Rediriger si l'ID est manquant ou invalide
    exit();
}

$id_client = intval($_GET['id']);
$message = '';
$message_type = '';

try {
    // Récupérer les informations du client
    $client = getClientById($pdo, $id_client);

    if (!$client) {
        $message = "Client introuvable.";
        $message_type = 'danger';
        // Arrêter l'exécution si le client n'existe pas
        // Après l'inclusion du header, pour afficher l'erreur
    } else {
        // Si le client existe, récupérer ses comptes associés
        $comptes = getComptesByClient($pdo, $id_client);
    }
} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
    $client = null;
    $comptes = [];
}

// Inclure le header de la page (début du HTML, CSS, etc.)
include '../../templates/header.php'; 
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Détails du Client</h2>
        <a href="liste_clients.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour à la liste
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
            <div class="card-body">
                <h4 class="card-title text-primary"><i class="fas fa-user-circle"></i> Informations Personnelles</h4>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item"><strong>Nom :</strong> <?php echo htmlspecialchars($client['nom']); ?></li>
                            <li class="list-group-item"><strong>Prénoms :</strong> <?php echo htmlspecialchars($client['prenoms']); ?></li>
                            <li class="list-group-item"><strong>Date de naissance :</strong> <?php echo htmlspecialchars($client['date_naissance']); ?></li>
                            <li class="list-group-item"><strong>Sexe :</strong> <?php echo htmlspecialchars($client['sexe']); ?></li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item"><strong>Téléphone :</strong> <?php echo htmlspecialchars($client['telephone']); ?></li>
                            <li class="list-group-item"><strong>Adresse :</strong> <?php echo htmlspecialchars($client['adresse']); ?></li>
                            <li class="list-group-item"><strong>Email :</strong> <?php echo htmlspecialchars($client['email']); ?></li>
                            <li class="list-group-item"><strong>Date d'adhésion :</strong> <?php echo htmlspecialchars($client['date_adhesion']); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <a href="modifier_client.php?id=<?php echo urlencode($client['id_client']); ?>" class="btn btn-warning me-2">
                    <i class="fas fa-edit"></i> Modifier le profil
                </a>
                </div>
        </div>

        <h4 class="mt-5 text-primary"><i class="fas fa-university"></i> Comptes Bancaires</h4>
        <?php if (!empty($comptes)): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Numéro de Compte</th>
                            <th>Type de Compte</th>
                            <th>Solde Actuel</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comptes as $compte): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($compte['numero_compte']); ?></td>
                                <td><?php echo htmlspecialchars($compte['libelle_produit']); ?></td>
                                <td><?php echo number_format($compte['solde'], 2, ',', ' '); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo ($compte['statut'] == 'actif') ? 'success' : 'danger'; ?>">
                                        <?php echo htmlspecialchars(ucfirst($compte['statut'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="../comptes/details_compte.php?id=<?php echo urlencode($compte['id_compte']); ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-search-plus"></i> Détails
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center" role="alert">
                Ce client ne possède encore aucun compte.
                <a href="../comptes/creer_compte.php?id_client=<?php echo $id_client; ?>" class="alert-link">Créer un nouveau compte</a>
            </div>
        <?php endif; ?>

        <?php endif; ?>
</div>

<?php 
// Fonctions à implémenter dans 'gestion_clients.php' et 'gestion_comptes.php'
function getClientById(PDO $pdo, int $id_client): ?array {
    // Cette fonction devrait récupérer les données du client depuis la base de données
    // en se basant sur son ID.
    // Exemple : SELECT * FROM Clients WHERE id_client = ?
    return ['id_client' => 123, 'nom' => 'DION', 'prenoms' => 'BENOÎT', 'date_naissance' => '1990-01-01', 'sexe' => 'M', 'telephone' => '698456123', 'adresse' => 'Yaoundé', 'email' => 'db@email.com', 'date_adhesion' => '2025-01-15']; // Stub
}

function getComptesByClient(PDO $pdo, int $id_client): array {
    // Cette fonction devrait récupérer tous les comptes liés à un client.
    // Exemple : SELECT c.*, p.libelle_produit FROM Comptes c JOIN Produits_Bancaires p ON c.id_produit = p.id_produit WHERE c.id_client = ?
    return [
        ['id_compte' => 1, 'numero_compte' => '001-A-00001', 'solde' => 500000.00, 'statut' => 'actif', 'libelle_produit' => 'Compte Courant'],
        ['id_compte' => 2, 'numero_compte' => '001-E-00002', 'solde' => 150000.00, 'statut' => 'actif', 'libelle_produit' => 'Compte Épargne'],
    ]; // Stub
}

// Inclure le footer de la page (fin du HTML)
include '../../templates/footer.php';
?>