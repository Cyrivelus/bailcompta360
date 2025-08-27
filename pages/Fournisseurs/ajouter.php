<?php
// Inclure les fichiers de fonctions
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_fournisseurs.php';
require_once '../../fonctions/validation.php';


$message = '';
$erreurs = [];

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
        'DateProcuration' => trim($_POST['DateProcuration'] ?? ''),
        'ReferenceProcuration' => trim($_POST['ReferenceProcuration'] ?? ''),
        'PaiementSalaireParMobile' => isset($_POST['PaiementSalaireParMobile']) ? 1 : 0,
        'NoTel_PaiementSalaire' => trim($_POST['NoTel_PaiementSalaire'] ?? ''),
        'NoTelPaiementSalaire' => trim($_POST['NoTelPaiementSalaire'] ?? '')
    ];

    // Valider les données
    if (empty($formData['Cpt'])) {
        $erreurs[] = 'Le champ Compte est obligatoire.';
    }
    if (empty($formData['Lib'])) {
        $erreurs[] = 'Le champ Libellé est obligatoire.';
    }

    // Si aucune erreur de validation, procéder à l'insertion
    if (empty($erreurs)) {
        // Gérer les champs facultatifs (date, téléphone)
        if (empty($formData['DateProcuration'])) {
            $formData['DateProcuration'] = null;
        }

        if (ajouterFournisseur($pdo, $formData)) {
            $message = "Le fournisseur a été ajouté avec succès.";
            // Optionnel : Redirection vers la page d'index après l'ajout
            // header('Location: index.php?success=1');
            // exit();
        } else {
            $erreurs[] = "Une erreur est survenue lors de l'ajout du fournisseur.";
        }
    }
}

// Inclusion des templates
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
?>

<div class="container-fluid content-container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h2 class="text-center">Ajouter un nouveau Fournisseur</h2>
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

            <form action="" method="post">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="Cpt">Compte (Cpt)*</label>
                        <input type="text" class="form-control" id="Cpt" name="Cpt" required value="<?= htmlspecialchars($formData['Cpt'] ?? '') ?>">
                    </div>

                    <div class="form-group col-md-6">
                        <label for="Lib">Libellé (Lib)*</label>
                        <input type="text" class="form-control" id="Lib" name="Lib" required value="<?= htmlspecialchars($formData['Lib'] ?? '') ?>">
                    </div>
                </div>

                <h5>Informations Générales</h5>
                <hr>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="Sns">Sns</label>
                        <input type="text" class="form-control" id="Sns" name="Sns" value="<?= htmlspecialchars($formData['Sns'] ?? '') ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="Aux">Aux</label>
                        <input type="text" class="form-control" id="Aux" name="Aux" value="<?= htmlspecialchars($formData['Aux'] ?? '') ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="NumeroAgenceSCE">Code Agence</label>
                        <input type="text" class="form-control" id="NumeroAgenceSCE" name="NumeroAgenceSCE" value="<?= htmlspecialchars($formData['NumeroAgenceSCE'] ?? '') ?>">
                    </div>
                </div>

                <h5>Options du Compte</h5>
                <hr>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="DisponibiliteSolde" name="DisponibiliteSolde" value="1" <?= isset($formData['DisponibiliteSolde']) && $formData['DisponibiliteSolde'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="DisponibiliteSolde">Solde disponible</label>
                        </div>
                    </div>
                    <div class="form-group col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="CompteCloture" name="CompteCloture" value="1" <?= isset($formData['CompteCloture']) && $formData['CompteCloture'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="CompteCloture">Compte clôturé</label>
                        </div>
                    </div>
                    <div class="form-group col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="PeutObtenirAcompte" name="PeutObtenirAcompte" value="1" <?= isset($formData['PeutObtenirAcompte']) && $formData['PeutObtenirAcompte'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="PeutObtenirAcompte">Peut obtenir acompte</label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success mt-3">Ajouter le Fournisseur</button>
                <a href="index.php" class="btn btn-secondary mt-3">Annuler</a>
            </form>
        </div>
    </div>
</div>

<?php 
require_once('../../templates/footer.php');
?>