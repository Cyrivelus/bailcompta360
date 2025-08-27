<?php
// pages/journaux/modifier.php

// Inclure les fichiers de fonctions
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_journaux.php';
require_once '../../fonctions/validation.php'; // Fichier de validation si nécessaire

$message = '';
$erreurs = [];

// Vérifier si un code (Cde) est passé dans l'URL
if (!isset($_GET['cde']) || empty($_GET['cde'])) {
    header('Location: index.php');
    exit();
}

$cde = (int)$_GET['cde']; // Assurez-vous que le code est un entier
$journal = getJournalByCde($pdo, $cde);

// Si le journal n'existe pas, rediriger vers la page d'index
if (!$journal) {
    header('Location: index.php?error=notfound');
    exit();
}

// Traitement du formulaire si la méthode est POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'Cde' => trim($_POST['Cde'] ?? ''),
        'Lib' => trim($_POST['Lib'] ?? ''),
        'Typ' => trim($_POST['Typ'] ?? ''),
        'Cpt' => trim($_POST['Cpt'] ?? ''),
        'NumeroAgenceSCE' => trim($_POST['NumeroAgenceSCE'] ?? '')
    ];

    // Valider les données
    if (empty($formData['Cde'])) {
        $erreurs[] = 'Le champ Code (Cde) est obligatoire.';
    }
    if (empty($formData['Lib'])) {
        $erreurs[] = 'Le champ Libellé (Lib) est obligatoire.';
    }

    // Si aucune erreur, mettre à jour le journal
    if (empty($erreurs)) {
        // Le Cde peut avoir été modifié dans le formulaire,
        // on l'enlève pour éviter de le mettre à jour dans la table
        // et on utilise la variable initiale pour le WHERE.
        $nouveauCde = $formData['Cde'];
        unset($formData['Cde']);

        if (modifierJournal($pdo, $cde, $formData)) {
            $message = "Le journal a été mis à jour avec succès. ✔️";
            
            // Si le Cde a été modifié, on redirige vers le nouveau Cde
            if ((int)$nouveauCde !== $cde) {
                header('Location: modifier.php?cde=' . urlencode($nouveauCde) . '&success=1');
                exit();
            }
            // Mettre à jour les données affichées après la modification
            $journal = getJournalByCde($pdo, $cde);
        } else {
            $erreurs[] = "Une erreur est survenue lors de la mise à jour du journal. ❌";
        }
    }
}

// Données à afficher dans le formulaire (soit celles du POST, soit celles de la BDD)
$form_data_to_display = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $journal;

// Inclusion des templates
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
?>

<div class="container-fluid content-container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h2 class="text-center">Modifier le Journal: <?= htmlspecialchars($journal['Lib']) ?></h2>
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

            <form action="modifier.php?cde=<?= urlencode($cde) ?>" method="post">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="Cde">Code (Cde)*</label>
                        <input type="number" class="form-control" id="Cde" name="Cde" required value="<?= htmlspecialchars($form_data_to_display['Cde'] ?? '') ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="Lib">Libellé (Lib)*</label>
                        <input type="text" class="form-control" id="Lib" name="Lib" required value="<?= htmlspecialchars($form_data_to_display['Lib'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="Typ">Type (Typ)</label>
                        <input type="text" class="form-control" id="Typ" name="Typ" value="<?= htmlspecialchars($form_data_to_display['Typ'] ?? '') ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="Cpt">Compte (Cpt)</label>
                        <input type="text" class="form-control" id="Cpt" name="Cpt" value="<?= htmlspecialchars($form_data_to_display['Cpt'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="NumeroAgenceSCE">Code Agence</label>
                        <input type="text" class="form-control" id="NumeroAgenceSCE" name="NumeroAgenceSCE" value="<?= htmlspecialchars($form_data_to_display['NumeroAgenceSCE'] ?? '') ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-3">Mettre à jour le Journal</button>
                <a href="index.php" class="btn btn-secondary mt-3">Annuler</a>
            </form>
        </div>
    </div>
</div>

<?php 
require_once('../../templates/footer.php');
?>