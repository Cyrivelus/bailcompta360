<?php
// pages/admin/utilisateurs/ajouter.php

// Démarrer la session pour la gestion de l'authentification et des messages
if (session_status() == PHP_SESSION_NONE) { // Vérifier avant de démarrer
    session_start();
}

// Vérifier si l'utilisateur est connecté et est un administrateur
// Utiliser les mêmes clés de session que dans enregistrer_utilisateur.php
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../../index.php?error=Accès non autorisé");
    exit();
}

// Inclure l'en-tête de l'administration
$title = "Ajouter un Utilisateur"; // Utilisé par header.php
// Le chemin ../includes/header.php pointe vers pages/admin/includes/header.php
include('../includes/header.php');

// Inclure la navigation de l'administration
// Le chemin ../includes/navigation.php pointe vers pages/admin/includes/navigation.php
include('../includes/navigation.php');

// Récupérer les messages flash depuis la session
$errorMessage = null;
$successMessage = null;
if (isset($_SESSION['admin_message_error'])) {
    $errorMessage = $_SESSION['admin_message_error'];
    unset($_SESSION['admin_message_error']); // Nettoyer après lecture
}
if (isset($_SESSION['admin_message_success'])) {
    $successMessage = $_SESSION['admin_message_success'];
    unset($_SESSION['admin_message_success']); // Nettoyer après lecture
}
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 page-content-admin"> <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Ajouter un Utilisateur</h1>
    </div>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert"> <?= htmlspecialchars($errorMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($successMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="col-lg-6">
        <form method="post" action="enregistrer_utilisateur.php">
            <div class="mb-3">
                <label for="nom" class="form-label">Nom</label>
                <input type="text" class="form-control" id="nom" name="nom" required>
            </div>
            <div class="mb-3">
                <label for="login" class="form-label">Login</label>
                <input type="text" class="form-control" id="login" name="login" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Mot de Passe</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Rôle</label>
                <select class="form-select" id="role" name="role" required> <option value="">Sélectionner un rôle</option>
                    <option value="Admin">Administrateur</option>
                    <option value="Comptable">Comptable</option>
                    <option value="Invité">Invité</option> </select>
            </div>
            <button type="submit" class="btn btn-primary">Ajouter l'Utilisateur</button>
            <a href="index.php" class="btn btn-secondary">Annuler</a> </form>
    </div>
</main>

<?php
// Inclure le pied de page de l'administration
// Le chemin ../includes/footer.php pointe vers pages/admin/includes/footer.php
include('../includes/footer.php');
?>