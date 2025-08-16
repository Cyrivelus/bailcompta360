<?php
// pages/clients/liste_clients.php

/**
 * Ce fichier est l'index de la gestion des clients.
 * Il affiche la liste de tous les clients avec des options pour les gérer.
 * Ajoute la fonctionnalité de recherche et de pagination.
 */

require_once '../../database.php';
require_once '../../fonctions/gestion_clients.php';

$message = '';
$message_type = '';
$clients = [];
$total_clients = 0;

// Paramètres de pagination
$par_page = 20;
$page_courante = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page_courante - 1) * $par_page;

// Paramètres de recherche
$terme_recherche = isset($_GET['q']) ? trim($_GET['q']) : '';

try {
    // Récupérer le nombre total de clients correspondant à la recherche
    $total_clients = getNombreTotalClients($pdo, $terme_recherche);
    $total_pages = ceil($total_clients / $par_page);

    // Récupérer la liste des clients avec recherche et pagination
    $clients = listerClientsPagine($pdo, $terme_recherche, $par_page, $offset);

    // Gérer la suppression si le formulaire est soumis
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
        $id_client_a_supprimer = intval($_POST['id_client']);
        
        if (supprimerClient($pdo, $id_client_a_supprimer)) {
            $message = "Le client a été supprimé avec succès.";
            $message_type = 'success';
            // Recharger la page pour refléter le changement
            header("Location: liste_clients.php?page=" . $page_courante);
            exit();
        } else {
            throw new Exception("Échec de la suppression du client. Il peut avoir des comptes ou des prêts associés.");
        }
    }

} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
    $clients = [];
}

// Inclure le header de la page
include '../../templates/header.php'; 
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Liste des Clients</h2>
        <a href="ajouter_client.php" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Ajouter un client
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card mb-4 p-3 shadow-sm">
        <form action="liste_clients.php" method="get">
            <div class="input-group">
                <input type="text" class="form-control" placeholder="Rechercher par nom, prénom, téléphone ou email..." name="q" value="<?php echo htmlspecialchars($terme_recherche); ?>">
                <button class="btn btn-outline-secondary" type="submit">
                    <i class="fas fa-search"></i> Rechercher
                </button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <?php if (!empty($clients)): ?>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Nom & Prénoms</th>
                        <th>Téléphone</th>
                        <th>Email</th>
                        <th>Date d'adhésion</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenoms']); ?></td>
                            <td><?php echo htmlspecialchars($client['telephone']); ?></td>
                            <td><?php echo htmlspecialchars($client['email']); ?></td>
                            <td><?php echo htmlspecialchars($client['date_adhesion']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo ($client['statut'] == 'actif') ? 'success' : 'danger'; ?>">
                                    <?php echo htmlspecialchars(ucfirst($client['statut'])); ?>
                                </span>
                            </td>
                            <td>
                                <a href="details_client.php?id=<?php echo urlencode($client['id_client']); ?>" class="btn btn-sm btn-info me-2">
                                    <i class="fas fa-search-plus"></i> Détails
                                </a>
                                <a href="modifier_client.php?id=<?php echo urlencode($client['id_client']); ?>" class="btn btn-sm btn-warning me-2">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>
                                <form action="" method="post" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce client ? Cette action est irréversible et peut impacter les données des comptes associés.');">
                                    <input type="hidden" name="action" value="supprimer">
                                    <input type="hidden" name="id_client" value="<?php echo htmlspecialchars($client['id_client']); ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash-alt"></i> Supprimer
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-warning text-center" role="alert">
                Aucun client n'a été trouvé.
            </div>
        <?php endif; ?>
    </div>

    <nav aria-label="Pagination">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= ($page_courante <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?= $page_courante - 1 ?>&q=<?= urlencode($terme_recherche) ?>">Précédent</a>
            </li>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($page_courante == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($terme_recherche) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= ($page_courante >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?= $page_courante + 1 ?>&q=<?= urlencode($terme_recherche) ?>">Suivant</a>
            </li>
        </ul>
    </nav>
</div>

<?php 
// NOTE: Fonctions à implémenter dans 'gestion_clients.php'
function getNombreTotalClients(PDO $pdo, string $terme_recherche = ''): int {
    // Stub: Récupère le nombre total de clients, éventuellement filtré par un terme de recherche.
    // Exemple : SELECT COUNT(*) FROM Clients WHERE nom LIKE ? OR ...
    return 22; // Exemple de valeur
}

function listerClientsPagine(PDO $pdo, string $terme_recherche, int $limit, int $offset): array {
    // Stub: Récupère une liste paginée et filtrée de clients depuis la base de données.
    // Exemple : SELECT * FROM Clients WHERE nom LIKE ? OR ... ORDER BY nom, prenoms LIMIT ? OFFSET ?
    return [
        ['id_client' => 123, 'nom' => 'DION', 'prenoms' => 'Benoît', 'telephone' => '698456123', 'email' => 'db@email.com', 'date_adhesion' => '2025-01-15', 'statut' => 'actif'],
        ['id_client' => 456, 'nom' => 'SAMBA', 'prenoms' => 'Marie', 'telephone' => '675896321', 'email' => 'sm@email.com', 'date_adhesion' => '2025-02-20', 'statut' => 'actif'],
    ];
}

function supprimerClient(PDO $pdo, int $id_client): bool {
    // Stub: Supprime un client de la base de données.
    // Vérifier les contraintes d'intégrité avant de supprimer.
    return true;
}

// Inclure le footer de la page
include '../../templates/footer.php';
?>