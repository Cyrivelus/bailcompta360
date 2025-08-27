<?php
// Inclure les fichiers de fonctions
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_fournisseurs.php';
require_once '../../fonctions/validation.php';



$message = '';
$erreurs = [];

// Vérifier si un compte (Cpt) est passé dans l'URL
if (!isset($_GET['cpt']) || empty($_GET['cpt'])) {
    header('Location: index.php');
    exit();
}

$cpt = $_GET['cpt'];
$fournisseur = getFournisseurByCpt($pdo, $cpt);

// Si le fournisseur n'existe pas, rediriger vers la page d'index
if (!$fournisseur) {
    header('Location: index.php?error=notfound');
    exit();
}

// Traitement du formulaire si la méthode est POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer et assainir les données du formulaire
    $formData = [
        'Cpt' => trim($_POST['Cpt'] ?? ''),
        'Lib' => trim($_POST['Lib'] ?? ''),
        'Sns' => trim($_POST['Sns'] ?? ''),
        'Aux' => trim($_POST['Aux'] ?? ''),
        'FAP' => trim($_POST['FAP'] ?? ''),
        'FGX' => trim($_POST['FGX'] ?? ''),
        'FB' => trim($_POST['FB'] ?? ''),
        'FGI' => trim($_POST['FGI'] ?? ''),
        'FM' => trim($_POST['FM'] ?? ''),
        'HE' => trim($_POST['HE'] ?? ''),
        'PRV' => trim($_POST['PRV'] ?? ''),
        'AT' => trim($_POST['AT'] ?? ''),
        'DisponibiliteSolde' => isset($_POST['DisponibiliteSolde']) ? 1 : 0,
        'MentionSiIndisponible' => trim($_POST['MentionSiIndisponible'] ?? ''),
        'ObservationsSurIndisponibilite' => trim($_POST['ObservationsSurIndisponibilite'] ?? ''),
        'NumeroAgenceSCE' => trim($_POST['NumeroAgenceSCE'] ?? ''),
        'PeutObtenirAcompte' => isset($_POST['PeutObtenirAcompte']) ? 1 : 0,
        'NombreExtraitsPayants' => trim($_POST['NombreExtraitsPayants'] ?? ''),
        'CompteCloture' => isset($_POST['CompteCloture']) ? 1 : 0,
        'RecenceOuiNon' => isset($_POST['RecenceOuiNon']) ? 1 : 0,
        'FraisTenueDeCompteSuspendus' => isset($_POST['FraisTenueDeCompteSuspendus']) ? 1 : 0,
        'NumeroAgencePaiementTemp' => trim($_POST['NumeroAgencePaiementTemp'] ?? ''),
        'ExistenceProcuration' => isset($_POST['ExistenceProcuration']) ? 1 : 0,
        'DateProcuration' => empty($_POST['DateProcuration']) ? null : trim($_POST['DateProcuration'] ?? ''),
        'ReferenceProcuration' => trim($_POST['ReferenceProcuration'] ?? ''),
        'PaiementSalaireParMobile' => isset($_POST['PaiementSalaireParMobile']) ? 1 : 0,
        'NoTel_PaiementSalaire' => trim($_POST['NoTel_PaiementSalaire'] ?? ''),
        'NoTelPaiementSalaire' => trim($_POST['NoTelPaiementSalaire'] ?? '')
    ];

    // Valider les données (vous pouvez ajouter plus de validations ici)
    if (empty($formData['Cpt'])) {
        $erreurs[] = 'Le champ Compte est obligatoire.';
    }
    if (empty($formData['Lib'])) {
        $erreurs[] = 'Le champ Libellé est obligatoire.';
    }

    // Si pas d'erreurs, mettre à jour le fournisseur
    if (empty($erreurs)) {
        // Le Cpt peut avoir été modifié par l'utilisateur
        $nouveauCpt = $formData['Cpt'];
        unset($formData['Cpt']); // On supprime le Cpt du tableau de données de mise à jour

        if (modifierFournisseur($pdo, $cpt, $formData)) {
            $message = "Le fournisseur a été mis à jour avec succès.";
            
            // Si le Cpt a été modifié, on redirige vers la nouvelle URL
            if ($nouveauCpt !== $cpt) {
                header('Location: modifier.php?cpt=' . urlencode($nouveauCpt) . '&success=1');
                exit();
            }
            // Mettre à jour les données affichées après la modification
            $fournisseur = getFournisseurByCpt($pdo, $cpt);
        } else {
            $erreurs[] = "Une erreur est survenue lors de la mise à jour du fournisseur.";
        }
    }
}

// Déterminer quelles données afficher dans le formulaire (postées ou existantes)
$form_data_to_display = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $fournisseur;

// Inclusion des templates
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
?>

<div class="container-fluid content-container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h2 class="text-center">Modifier le Fournisseur: <?= htmlspecialchars($fournisseur['Lib']) ?></h2>
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

            <form action="modifier.php?cpt=<?= urlencode($cpt) ?>" method="post">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="Cpt">Compte (Cpt)*</label>
                        <input type="text" class="form-control" id="Cpt" name="Cpt" required value="<?= htmlspecialchars($form_data_to_display['Cpt'] ?? '') ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="Lib">Libellé (Lib)*</label>
                        <input type="text" class="form-control" id="Lib" name="Lib" required value="<?= htmlspecialchars($form_data_to_display['Lib'] ?? '') ?>">
                    </div>
                </div>

                <h5>Informations Générales</h5>
                <hr>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="Sns">Sns</label>
                        <input type="text" class="form-control" id="Sns" name="Sns" value="<?= htmlspecialchars($form_data_to_display['Sns'] ?? '') ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="Aux">Aux</label>
                        <input type="text" class="form-control" id="Aux" name="Aux" value="<?= htmlspecialchars($form_data_to_display['Aux'] ?? '') ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="NumeroAgenceSCE">Code Agence</label>
                        <input type="text" class="form-control" id="NumeroAgenceSCE" name="NumeroAgenceSCE" value="<?= htmlspecialchars($form_data_to_display['NumeroAgenceSCE'] ?? '') ?>">
                    </div>
                </div>

                <h5>Options du Compte</h5>
                <hr>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="DisponibiliteSolde" name="DisponibiliteSolde" value="1" <?= isset($form_data_to_display['DisponibiliteSolde']) && $form_data_to_display['DisponibiliteSolde'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="DisponibiliteSolde">Solde disponible</label>
                        </div>
                    </div>
                    <div class="form-group col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="CompteCloture" name="CompteCloture" value="1" <?= isset($form_data_to_display['CompteCloture']) && $form_data_to_display['CompteCloture'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="CompteCloture">Compte clôturé</label>
                        </div>
                    </div>
                    <div class="form-group col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="PeutObtenirAcompte" name="PeutObtenirAcompte" value="1" <?= isset($form_data_to_display['PeutObtenirAcompte']) && $form_data_to_display['PeutObtenirAcompte'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="PeutObtenirAcompte">Peut obtenir acompte</label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-3">Mettre à jour le Fournisseur</button>
                <a href="index.php" class="btn btn-secondary mt-3">Annuler</a>
            </form>
        </div>
    </div>
</div>

<?php 
require_once('../../templates/footer.php');
?>