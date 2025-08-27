<?php
// Inclure les fichiers de fonctions et de configuration
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_fournisseurs.php';
require_once '../../fonctions/validation.php';


// Récupérer les paramètres de recherche et de pagination
$recherche = $_GET['recherche'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 25; // Nombre de fournisseurs par page

// Récupérer le nombre total de fournisseurs pour la pagination
$totalFournisseurs = getNombreTotalFournisseurs($pdo, $recherche);
$totalPages = ceil($totalFournisseurs / $limit);
$offset = ($page - 1) * $limit;

// Récupérer la liste des fournisseurs avec pagination et recherche
$fournisseurs = getFournisseurs($pdo, $recherche, $limit, $offset);

// Inclure les parties du template (header, navigation) avant le contenu HTML
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
?>

<div class="container-fluid content-container">
    <div class="row">
        <div class="col-md-12">
            <h2 class="text-center">Liste des Fournisseurs</h2>
            <hr>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <form action="index.php" method="get" class="form-inline">
                        <div class="form-group mr-2">
                            <label for="recherche" class="sr-only">Rechercher</label>
                            <input type="text" class="form-control" id="recherche" name="recherche" placeholder="Rechercher par nom..." value="<?= htmlspecialchars($recherche) ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Rechercher</button>
                    </form>
                </div>
                <div class="col-md-6 text-right">
                    <a href="ajouter.php" class="btn btn-success">Ajouter un Fournisseur</a>
                </div>
            </div>

            <?php if (!empty($fournisseurs)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Compte (Cpt)</th>
                                <th>Libellé (Lib)</th>
                                <th>Code Agence (NumeroAgenceSCE)</th>
                                <th>Solde Disponible</th>
                                <th>Compte Clôturé</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fournisseurs as $fournisseur): ?>
                                <tr>
                                    <td><?= htmlspecialchars($fournisseur['Cpt']) ?></td>
                                    <td><?= htmlspecialchars($fournisseur['Lib']) ?></td>
                                    <td><?= htmlspecialchars($fournisseur['NumeroAgenceSCE']) ?></td>
                                    <td>
                                        <?= $fournisseur['DisponibiliteSolde'] ? 
                                            '<span class="badge badge-success">Oui</span>' : 
                                            '<span class="badge badge-danger">Non</span>' ?>
                                    </td>
                                    <td>
                                        <?= $fournisseur['CompteCloture'] ? 
                                            '<span class="badge badge-danger">Oui</span>' : 
                                            '<span class="badge badge-success">Non</span>' ?>
                                    </td>
                                    <td>
                                        <a href="modifier.php?cpt=<?= urlencode($fournisseur['Cpt']) ?>" class="btn btn-sm btn-info">Modifier</a>
                                        <a href="supprimer.php?cpt=<?= urlencode($fournisseur['Cpt']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce fournisseur ?');">Supprimer</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?recherche=<?= urlencode($recherche) ?>&page=<?= $page - 1 ?>">Précédent</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                <a class="page-link" href="?recherche=<?= urlencode($recherche) ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?recherche=<?= urlencode($recherche) ?>&page=<?= $page + 1 ?>">Suivant</a>
                        </li>
                    </ul>
                </nav>

            <?php else: ?>
                <div class="alert alert-info text-center" role="alert">
                    Aucun fournisseur trouvé.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
// Le footer doit être inclus à la fin du corps de la page
require_once('../../templates/footer.php');
?>