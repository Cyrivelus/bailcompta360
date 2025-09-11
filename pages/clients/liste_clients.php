<?php
// pages/clients/liste_clients.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_clients.php';
require_once '../../fonctions/gestion_contrats_assurance.php';

$message = '';
$message_type = '';
$client = null;
$contrats_assurance = [];
$liste_clients = [];

try {
    // Correct the function call to use the new listerClients() function.
    $liste_clients = listerClients($pdo);

    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $id_client = intval($_GET['id']);
        // Use the trouverClientParId function that handles the new table structure.
        $client = trouverClientParId($pdo, $id_client);
        
        if ($client) {
            // Note: Make sure getContratsAssuranceByClientId is compatible with the new structure if necessary.
            $contrats_assurance = getContratsAssuranceByClientId($pdo, $id_client);
        } else {
            $message = "Client introuvable pour l'ID $id_client.";
            $message_type = 'warning';
        }
    }
} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
}

include '../../templates/navigation.php';
include '../../templates/header.php';
?>

<div class="container-fluid mt-4">
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($client): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-info text-white">
                Détails du Client : 
                <?= htmlspecialchars($client['type_client'] === 'particulier' ? $client['nom_ou_raison_sociale'] . ' ' . $client['nom_abrege'] : $client['nom_ou_raison_sociale']) ?>
            </div>
            <div class="card-body">
                <?php if ($client['type_client'] === 'particulier'): ?>
                    <p><strong>Nom :</strong> <?= htmlspecialchars($client['nom_ou_raison_sociale'] ?? '') ?></p>
                    <p><strong>Prénoms :</strong> <?= htmlspecialchars($client['nom_abrege'] ?? '') ?></p>
                    <p><strong>Date de naissance :</strong> <?= htmlspecialchars($client['date_naissance'] ?? '') ?></p>
                    <p><strong>Sexe :</strong> <?= htmlspecialchars($client['sexe'] ?? '') ?></p>
                    <p><strong>Profession :</strong> <?= htmlspecialchars($client['profession'] ?? '') ?></p>
                    <p><strong>Revenu mensuel :</strong> <?= number_format($client['revenu_mensuel'] ?? 0, 2, ',', ' ') ?> XAF</p>
                <?php else: ?>
                    <p><strong>Raison Sociale :</strong> <?= htmlspecialchars($client['nom_ou_raison_sociale'] ?? '') ?></p>
                    <p><strong>Matricule :</strong> <?= htmlspecialchars($client['matricule'] ?? '') ?></p>
                    <p><strong>N° Registre de Commerce :</strong> <?= htmlspecialchars($client['numero_registre_commerce'] ?? '') ?></p>
                    <p><strong>N° de Contribuable :</strong> <?= htmlspecialchars($client['numero_contribuable'] ?? '') ?></p>
                    <p><strong>Forme Juridique :</strong> <?= htmlspecialchars($client['forme_juridique'] ?? '') ?></p>
                    <p><strong>Date de Création :</strong> <?= htmlspecialchars($client['date_creation'] ?? '') ?></p>
                    <p><strong>Objet Social :</strong> <?= htmlspecialchars($client['objet_social'] ?? '') ?></p>
                <?php endif; ?>
                <p><strong>Téléphone :</strong> <?= htmlspecialchars($client['telephone'] ?? '') ?></p>
                <p><strong>Email :</strong> <?= htmlspecialchars($client['email'] ?? '') ?></p>
                <p><strong>Adresse :</strong> <?= htmlspecialchars($client['adresse'] ?? '') ?></p>
                <p><strong>Date d'adhésion :</strong> <?= htmlspecialchars($client['date_adhesion'] ?? '') ?></p>
                <p><strong>Statut :</strong> 
                    <span class="badge bg-<?= ($client['statut'] == 'actif') ? 'success' : 'danger' ?>">
                        <?= htmlspecialchars(ucfirst($client['statut'])) ?>
                    </span>
                </p>
            </div>
        </div>

        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">
                Contrats d'Assurance
            </div>
            <div class="card-body">
                <?php if (!empty($contrats_assurance)): ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>N° Police</th>
                                <th>Type</th>
                                <th>Date Début</th>
                                <th>Date Fin</th>
                                <th>Prime</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contrats_assurance as $contrat): ?>
                                <tr>
                                    <td><?= htmlspecialchars($contrat['Numero_Police']) ?></td>
                                    <td><?= htmlspecialchars($contrat['Type_Assurance']) ?></td>
                                    <td><?= htmlspecialchars($contrat['Date_Debut']) ?></td>
                                    <td><?= htmlspecialchars($contrat['Date_Fin']) ?></td>
                                    <td><?= number_format($contrat['Prime_Annuelle'], 2, ',', ' ') ?></td>
                                    <td><?= htmlspecialchars($contrat['Statut_Contrat']) ?></td>
                                    <td>
                                        <a href="modifier_contrat.php?id=<?= $contrat['ID_Contrat'] ?>" class="btn btn-sm btn-warning">Modifier</a>
                                        <a href="supprimer_contrat.php?id=<?= $contrat['ID_Contrat'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr ?');">Supprimer</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucun contrat d'assurance pour ce client.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <h3>Liste des Clients</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Type</th>
                <th>Nom / Raison Sociale</th>
                <th>Téléphone</th>
                <th>Email</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($liste_clients as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['id_client']) ?></td>
                    <td>
                        <span class="badge bg-secondary">
                            <?= htmlspecialchars($c['type_client'] === 'particulier' ? 'Particulier' : 'Entreprise') ?>
                        </span>
                    </td>
                    <td>
                        <?= htmlspecialchars($c['type_client'] === 'particulier' ? $c['nom'] . ' ' . $c['prenoms'] : $c['nom']) ?>
                    </td>
                    <td><?= htmlspecialchars($c['telephone']) ?></td>
                    <td><?= htmlspecialchars($c['email']) ?></td>
                    <td>
                        <a href="?id=<?= urlencode($c['id_client']) ?>" class="btn btn-sm btn-info">Voir Détails</a>
                        <a href="modifier_client.php?id=<?= urlencode($c['id_client']) ?>" class="btn btn-sm btn-warning">Modifier</a>
                        <a href="supprimer_client.php?id=<?= urlencode($c['id_client']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce client ?');">Supprimer</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../../templates/footer.php'; ?>