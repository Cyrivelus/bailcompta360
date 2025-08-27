<?php
// pages/lettrage/index.php

require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_comptes.php'; // Supposons que vous avez une fonction pour récupérer les comptes
require_once '../../fonctions/gestion_lettrage.php';
require_once '../../fonctions/validation.php';


$message = '';
$erreurs = [];

// Récupérer la liste des comptes pour le sélecteur
$comptes = getComptes($pdo); // Fonction à créer pour récupérer les comptes

// Variables de lettrage
$idCompteSelectionne = $_GET['id_compte'] ?? null;
$lettreLettrage = $_GET['lettre_lettrage'] ?? null;

// Traitement du formulaire de lettrage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lettrer') {
    $idsLignes = $_POST['lignes_a_lettrer'] ?? [];
    $nouvelleLettre = trim($_POST['nouvelle_lettre'] ?? '');
    
    if (empty($idsLignes)) {
        $erreurs[] = "Veuillez sélectionner au moins une ligne à lettrer.";
    } elseif (empty($nouvelleLettre) || strlen($nouvelleLettre) > 1) {
        $erreurs[] = "Veuillez entrer une lettre de lettrage valide (un seul caractère).";
    } else {
        if (appliquerLettrage($pdo, $idsLignes, $nouvelleLettre)) {
            $message = "Lettrage effectué avec succès ! ✔️";
        } else {
            $erreurs[] = "Une erreur est survenue lors de l'application du lettrage. ❌";
        }
    }
}

// Récupérer les lignes d'écriture du compte sélectionné
$lignes = [];
if ($idCompteSelectionne) {
    $lignes = getLignesPourLettrage($pdo, $idCompteSelectionne, $lettreLettrage);
}

// Inclusion des templates
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
?>

<div class="container-fluid content-container">
    <div class="row">
        <div class="col-md-12">
            <h2 class="text-center">Outil de Lettrage</h2>
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

            <form action="" method="get" class="form-inline mb-3">
                <div class="form-group mr-2">
                    <label for="id_compte" class="mr-2">Sélectionner un compte :</label>
                    <select name="id_compte" id="id_compte" class="form-control" onchange="this.form.submit()">
                        <option value="">-- Choisir un compte --</option>
                        <?php foreach ($comptes as $compte): ?>
                            <option value="<?= htmlspecialchars($compte['ID_Compte']) ?>" <?= ($compte['ID_Compte'] == $idCompteSelectionne) ? 'selected' : '' ?>>
    <?= htmlspecialchars($compte['Nom_Compte']) ?> (<?= htmlspecialchars($compte['Numero_Compte']) ?>)
</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mr-2">
                    <label for="lettre_lettrage" class="mr-2">Filtre Lettre :</label>
                    <input type="text" class="form-control" name="lettre_lettrage" id="lettre_lettrage" maxlength="1" value="<?= htmlspecialchars($lettreLettrage ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-secondary">Filtrer</button>
            </form>

            <?php if ($idCompteSelectionne): ?>
            
                <div class="d-flex justify-content-between mb-3">
                    <h4>Lignes du compte : **<?= htmlspecialchars($idCompteSelectionne) ?>**</h4>
                    <form action="" method="post">
                        <input type="hidden" name="action" value="lettrer">
                        <input type="hidden" name="id_compte" value="<?= htmlspecialchars($idCompteSelectionne) ?>">
                        <input type="hidden" name="lettre_lettrage" value="<?= htmlspecialchars($lettreLettrage) ?>">
                        <div class="form-inline">
                            <label for="nouvelle_lettre" class="mr-2">Lettre à appliquer :</label>
                            <input type="text" class="form-control mr-2" id="nouvelle_lettre" name="nouvelle_lettre" maxlength="1" required>
                            <button type="submit" class="btn btn-success">Lettrer les lignes sélectionnées</button>
                        </div>
                    </form>
                </div>

                <?php if (!empty($lignes)): ?>
                    <form action="" method="post">
                        <input type="hidden" name="action" value="lettrer">
                        <input type="hidden" name="id_compte" value="<?= htmlspecialchars($idCompteSelectionne) ?>">
                        <input type="hidden" name="nouvelle_lettre" value=""> <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="checkAll"></th>
                                        <th>ID Ligne</th>
                                        <th>Date</th>
                                        <th>Libellé</th>
                                        <th class="text-right">Débit</th>
                                        <th class="text-right">Crédit</th>
                                        <th>Lettre Lettrage</th>
                                        <th>Lettré</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lignes as $ligne): ?>
                                    <tr>
                                        <td>
                                            <?php if ($ligne['Lettre_Lettrage'] === null): ?>
                                                <input type="checkbox" name="lignes_a_lettrer[]" value="<?= htmlspecialchars($ligne['ID_Ligne']) ?>">
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($ligne['ID_Ligne']) ?></td>
                                        <td></td>
                                        <td><?= htmlspecialchars($ligne['Libelle_Ligne']) ?></td>
                                        <td class="text-right"><?= ($ligne['Sens'] === 'D') ? number_format($ligne['Montant'], 2, ',', ' ') : '' ?></td>
                                        <td class="text-right"><?= ($ligne['Sens'] === 'C') ? number_format($ligne['Montant'], 2, ',', ' ') : '' ?></td>
                                        <td><?= htmlspecialchars($ligne['Lettre_Lettrage']) ?></td>
                                        <td><?= $ligne['is_reconciled'] ? '<span class="badge badge-success">Oui</span>' : '<span class="badge badge-warning">Non</span>' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>

                <?php else: ?>
                    <div class="alert alert-info text-center" role="alert">
                        Aucune ligne d'écriture non lettrée trouvée pour ce compte.
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-info text-center" role="alert">
                    Veuillez sélectionner un compte pour commencer le lettrage.
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php 
require_once('../../templates/footer.php');
?>