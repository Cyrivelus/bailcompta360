<?php
// pages/clients/ajouter_client.php

/**
 * Cette page est dédiée à l'ajout d'un nouveau client.
 * Elle affiche un formulaire et gère le traitement des données soumises.
 */

// Démarrer la session en premier.
session_start();

// Inclure la configuration de la base de données et les fonctions métier.
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_clients.php';

// Initialiser les variables pour les messages d'erreur et de succès.
$erreurs = [];
$succes = '';

// Traitement du formulaire lorsque la méthode POST est utilisée.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer et nettoyer les données du formulaire pour éviter les failles XSS.
    $nom = htmlspecialchars(trim($_POST['nom']));
    $prenom = htmlspecialchars(trim($_POST['prenom']));
    $email = htmlspecialchars(trim($_POST['email']));
    $telephone = htmlspecialchars(trim($_POST['telephone']));
    $adresse = htmlspecialchars(trim($_POST['adresse']));
    $date_naissance = $_POST['date_naissance'];
    $profession = htmlspecialchars(trim($_POST['profession']));
    $revenu_mensuel = floatval($_POST['revenu_mensuel']);
    $type_client = $_POST['type_client'];

    // Validation des données.
    if (empty($nom) || empty($prenom) || empty($email) || empty($telephone)) {
        $erreurs[] = "Les champs Nom, Prénom, Email et Téléphone sont obligatoires.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "L'adresse email n'est pas valide.";
    }

    // Si aucune erreur de validation, procéder à l'ajout.
    if (empty($erreurs)) {
        try {
            // Créer le client en appelant la fonction de gestion_clients.php.
            if (creerClient(
                $pdo,
                $nom,
                $prenom,
                $email,
                $telephone,
                $adresse,
                $date_naissance,
                $profession,
                $revenu_mensuel,
                $type_client
            )) {
                $succes = "Le client a été ajouté avec succès.";
                // Réinitialiser les champs du formulaire après un succès.
                $_POST = [];
            } else {
                $erreurs[] = "Une erreur est survenue lors de l'ajout du client.";
            }
        } catch (Exception $e) {
            $erreurs[] = "Erreur : " . $e->getMessage();
        }
    }
}

// Maintenant, incluez les fichiers de gabarit qui contiennent du HTML.
include '../../templates/header.php';
include '../../templates/navigation.php';
?>

<div class="container-fluid mt-4">
    <h2>Ajouter un nouveau client</h2>
    <hr>

    <?php if (!empty($erreurs)): ?>
        <div class="alert alert-danger" role="alert">
            <?php foreach ($erreurs as $erreur): ?>
                <p><?= htmlspecialchars($erreur) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($succes): ?>
        <div class="alert alert-success" role="alert">
            <?= htmlspecialchars($succes) ?>
        </div>
    <?php endif; ?>

    <form action="ajouter_client.php" method="POST">
        <h4>Informations personnelles</h4>
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="nom">Nom <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nom" name="nom" value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required>
            </div>
            <div class="form-group col-md-6">
                <label for="prenom">Prénom <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="prenom" name="prenom" value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="email">Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group col-md-6">
                <label for="telephone">Téléphone <span class="text-danger">*</span></label>
                <input type="tel" class="form-control" id="telephone" name="telephone" value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>" required>
            </div>
        </div>
        <div class="form-group">
            <label for="adresse">Adresse</label>
            <input type="text" class="form-control" id="adresse" name="adresse" value="<?= htmlspecialchars($_POST['adresse'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="date_naissance">Date de naissance</label>
            <input type="date" class="form-control" id="date_naissance" name="date_naissance" value="<?= htmlspecialchars($_POST['date_naissance'] ?? '') ?>">
        </div>

        <h4 class="mt-4">Informations supplémentaires</h4>
        <div class="form-group">
            <label for="profession">Profession</label>
            <input type="text" class="form-control" id="profession" name="profession" value="<?= htmlspecialchars($_POST['profession'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="revenu_mensuel">Revenu mensuel</label>
            <input type="number" step="0.01" class="form-control" id="revenu_mensuel" name="revenu_mensuel" value="<?= htmlspecialchars($_POST['revenu_mensuel'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="type_client">Type de client</label>
            <select class="form-control" id="type_client" name="type_client">
                <option value="particulier" <?= (($_POST['type_client'] ?? '') === 'particulier') ? 'selected' : ''; ?>>Particulier</option>
                <option value="entreprise" <?= (($_POST['type_client'] ?? '') === 'entreprise') ? 'selected' : ''; ?>>Entreprise</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary mt-3">Ajouter le client</button>
        <a href="index.php" class="btn btn-secondary mt-3">Annuler</a>
    </form>
</div>

<?php
// Inclure le footer de la page (fin du HTML).
include '../../templates/footer.php';
?>