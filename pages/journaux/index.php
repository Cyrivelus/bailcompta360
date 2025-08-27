<?php
// pages/journaux/index.php

// Inclure les fichiers de fonctions et de configuration
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_journaux.php';



// Récupérer les paramètres de recherche et de pagination
$recherche = $_GET['recherche'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 25; // Nombre de journaux par page

// Récupérer le nombre total de journaux pour la pagination
$totalJournaux = getNombreTotalJournaux($pdo, $recherche);
$totalPages = ceil($totalJournaux / $limit);
$offset = ($page - 1) * $limit;

// Récupérer la liste des journaux avec pagination et recherche
$journaux = getJournaux($pdo, $recherche, $limit, $offset);

// Inclusion des templates (en-tête, navigation)
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
?>

<div class="container-fluid content-container">
    <div class="row">
        <div class="col-md-12">
            <h2 class="text-center">Liste des Journaux</h2>
            <hr>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <form action="index.php" method="get" class="form-inline">
                        <div class="form-group mr-2">
                            <label for="recherche" class="sr-only">Rechercher</label>
                            <input type="text" class="form-control" id="recherche" name="recherche" placeholder="Rechercher par libellé..." value="<?= htmlspecialchars($recherche) ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Rechercher</button>
                    </form>
                </div>
                <div class="col-md-6 text-right">
                    <a href="ajouter.php" class="btn btn-success">Ajouter un Journal</a>
                </div>
            </div>

            <?php if (!empty($journaux)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Code (Cde)</th>
                                <th>Libellé (Lib)</th>
                                <th>Type (Typ)</th>
                                <th>Compte (Cpt)</th>
                                <th>Code Agence</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($journaux as $journal): ?>
                                <tr>
                                    <td><?= htmlspecialchars($journal['Cde']) ?></td>
                                    <td><?= htmlspecialchars($journal['Lib']) ?></td>
                                    <td><?= htmlspecialchars($journal['Typ']) ?></td>
                                    <td><?= htmlspecialchars($journal['Cpt']) ?></td>
                                    <td><?= htmlspecialchars($journal['NumeroAgenceSCE']) ?></td>
                                    <td>
                                        <a href="modifier.php?cde=<?= urlencode($journal['Cde']) ?>" class="btn btn-sm btn-info">Modifier</a>
                                        <a href="supprimer.php?cde=<?= urlencode($journal['Cde']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce journal ?');">Supprimer</a>
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
                    Aucun journal trouvé.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
require_once('../../templates/footer.php');
?>