<?php
// pages/journaux/ajouter.php

// Inclure les fichiers de fonctions
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_journaux.php';
require_once '../../fonctions/validation.php'; // Assurez-vous d'avoir ce fichier

$message = '';
$erreurs = [];
$formData = [
    'Cde' => '',
    'Lib' => '',
    'Typ' => '',
    'Cpt' => '',
    'NumeroAgenceSCE' => ''
];

// Traitement du formulaire si la méthode est POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer et assainir les données du formulaire
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

    // Si aucune erreur de validation, procéder à l'insertion
    if (empty($erreurs)) {
        if (ajouterJournal($pdo, $formData)) {
            $message = "Le journal a été ajouté avec succès. ✔️";
            // Réinitialiser les données du formulaire pour un nouvel ajout
            $formData = [
                'Cde' => '',
                'Lib' => '',
                'Typ' => '',
                'Cpt' => '',
                'NumeroAgenceSCE' => ''
            ];
        } else {
            $erreurs[] = "Une erreur est survenue lors de l'ajout du journal. ❌";
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
            <h2 class="text-center">Ajouter un nouveau Journal</h2>
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

            <form action="ajouter.php" method="post">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="Cde">Code (Cde)*</label>
                        <input type="number" class="form-control" id="Cde" name="Cde" required value="<?= htmlspecialchars($formData['Cde']) ?>">
                    </div>

                    <div class="form-group col-md-6">
                        <label for="Lib">Libellé (Lib)*</label>
                        <input type="text" class="form-control" id="Lib" name="Lib" required value="<?= htmlspecialchars($formData['Lib']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="Typ">Type (Typ)</label>
                        <input type="text" class="form-control" id="Typ" name="Typ" value="<?= htmlspecialchars($formData['Typ']) ?>">
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="Cpt">Compte (Cpt)</label>
                        <input type="text" class="form-control" id="Cpt" name="Cpt" value="<?= htmlspecialchars($formData['Cpt']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="NumeroAgenceSCE">Code Agence</label>
                        <input type="text" class="form-control" id="NumeroAgenceSCE" name="NumeroAgenceSCE" value="<?= htmlspecialchars($formData['NumeroAgenceSCE']) ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-success mt-3">Ajouter le Journal</button>
                <a href="index.php" class="btn btn-secondary mt-3">Annuler</a>
            </form>
        </div>
    </div>
</div>

<?php 
require_once('../../templates/footer.php');
?>