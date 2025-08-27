<?php
// pages/reconciliation/index.php

require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_comptes.php';
require_once '../../fonctions/gestion_reconciliation.php';
require_once '../../fonctions/validation.php'; // Pour les fonctions utilitaires

$message = '';
$erreurs = [];

// Simuler l'utilisateur connecté (remplacez par votre système d'authentification)
$utilisateurConnecteId = 1;

// Récupérer les paramètres de filtre
$idCompteSelectionne = $_GET['id_compte'] ?? null;
$etatSelectionne = $_GET['etat'] ?? 'non';

// Traitement du formulaire de réconciliation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $lignesSelectionnees = $_POST['lignes_selectionnees'] ?? [];

    if (empty($lignesSelectionnees)) {
        $erreurs[] = "Veuillez sélectionner au moins une ligne.";
    } else {
        if ($action === 'reconcilier') {
            if (reconcilierLignes($pdo, $lignesSelectionnees, $utilisateurConnecteId)) {
                $message = "Réconciliation effectuée avec succès ! ✔️";
            } else {
                $erreurs[] = "Une erreur est survenue lors de la réconciliation. ❌";
            }
        } elseif ($action === 'dereconcilier') {
            if (dereconcilierLignes($pdo, $lignesSelectionnees)) {
                $message = "Déréconciliation effectuée avec succès ! ✔️";
            } else {
                $erreurs[] = "Une erreur est survenue lors de la déréconciliation. ❌";
            }
        }
    }
}

// Récupérer la liste des comptes pour le sélecteur
$comptes = getComptes($pdo);

// Récupérer les lignes d'écriture en fonction des filtres
$lignes = getLignesReconciliation($pdo, $idCompteSelectionne, $etatSelectionne);

// Inclusion des templates
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
?>

<div class="container-fluid content-container">
    <div class="row">
        <div class="col-md-12">
            <h2 class="text-center">Réconciliation Bancaire</h2>
            <hr>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if (!empty($erreurs)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($erreurs as $erreur): ?>
                            <li><?= htmlspecialchars($erreur) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="" method="get" class="form-inline mb-4">
                <div class="form-group mr-2">
                    <label for="id_compte" class="mr-2">Compte :</label>
                    <select name="id_compte" id="id_compte" class="form-control">
                        <option value="">-- Tous les comptes --</option>
                        <?php foreach ($comptes as $compte): ?>
                            <option value="<?= htmlspecialchars($compte['ID_Compte']) ?>" <?= ($compte['ID_Compte'] == $idCompteSelectionne) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($compte['Nom_Compte']) ?> (<?= htmlspecialchars($compte['Numero_Compte']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group mr-2">
                    <label for="etat" class="mr-2">État :</label>
                    <select name="etat" id="etat" class="form-control">
                        <option value="non" <?= ($etatSelectionne === 'non') ? 'selected' : '' ?>>Non réconcilié</option>
                        <option value="oui" <?= ($etatSelectionne === 'oui') ? 'selected' : '' ?>>Réconcilié</option>
                        <option value="tous" <?= ($etatSelectionne === 'tous') ? 'selected' : '' ?>>Tous</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Filtrer</button>
            </form>

            <form action="" method="post">
                <input type="hidden" name="id_compte" value="<?= htmlspecialchars($idCompteSelectionne ?? '') ?>">
                <input type="hidden" name="etat" value="<?= htmlspecialchars($etatSelectionne ?? '') ?>">

                <div class="d-flex justify-content-end mb-3">
                    <button type="submit" name="action" value="reconcilier" class="btn btn-success mr-2">Réconcilier</button>
                    <button type="submit" name="action" value="dereconcilier" class="btn btn-warning">Déréconcilier</button>
                </div>

                <?php if (!empty($lignes)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="checkAll"></th>
                                    <th>Compte</th>
                                    <th>Date</th>
                                    <th>Libellé</th>
                                    <th class="text-right">Débit</th>
                                    <th class="text-right">Crédit</th>
                                    <th>État</th>
                                    <th>Date de réconciliation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lignes as $ligne): ?>
                                <tr>
                                    <td><input type="checkbox" name="lignes_selectionnees[]" value="<?= htmlspecialchars($ligne['ID_Ligne']) ?>"></td>
                                    <td><?= htmlspecialchars($ligne['Nom_Compte']) ?></td>
                                    <td></td>
                                    <td><?= htmlspecialchars($ligne['Libelle_Ligne']) ?></td>
                                    <td class="text-right"><?= ($ligne['Sens'] === 'D') ? number_format($ligne['Montant'], 2, ',', ' ') : '' ?></td>
                                    <td class="text-right"><?= ($ligne['Sens'] === 'C') ? number_format($ligne['Montant'], 2, ',', ' ') : '' ?></td>
                                    <td>
                                        <?php if ($ligne['is_reconciled']): ?>
                                            <span class="badge badge-success">Réconcilié</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Non réconcilié</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($ligne['reconciled_at'] ?? 'N/A') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center" role="alert">
                        Aucune ligne d'écriture trouvée pour les filtres sélectionnés.
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('checkAll').addEventListener('change', function(e) {
        let checkboxes = document.querySelectorAll('input[name="lignes_selectionnees[]"]');
        for (let i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = e.target.checked;
        }
    });
</script>

<?php 
require_once('../../templates/footer.php');
?>