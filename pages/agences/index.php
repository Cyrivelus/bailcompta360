<?php
// pages/agence/index.php

// Démarrer la session pour l'authentification
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Inclure la connexion à la base de données et les fonctions de gestion des utilisateurs
require_once '../../database.php';
require_once '../../fonctions/gestion_utilisateurs.php';

// Vérification de l'authentification et du rôle
// Seuls les rôles 'Admin' et 'Caissiere' sont autorisés à accéder à ce dossier
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['role'])) {
    // Redirection vers la page de connexion si l'utilisateur n'est pas connecté
    header("Location: ../../../index.php");
    exit();
}

$role = $_SESSION['role'];
if ($role !== 'Admin' && $role !== 'Caissiere') {
    // Redirection si le rôle n'est pas autorisé
    $_SESSION['auth_message_error'] = "Accès non autorisé.";
    header("Location: ../../index.php");
    exit();
}

// Inclure les en-têtes et la navigation
// Les chemins sont relatifs à partir du fichier courant
include('../../templates/header.php');
include('../../templates/navigation.php');

?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Tableau de Bord de l'Agence</h1>
    </div>

    <p>Bienvenue sur le tableau de bord de l'agence. Vous pouvez gérer les opérations quotidiennes ici.</p>

    <div class="row row-cols-1 row-cols-md-2 g-4">
        <div class="col">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Gestion des Caisses</h5>
                    <p class="card-text">Ouvrir, fermer et consulter l'historique des caisses.</p>
                    <a href="gestion_caisses.php" class="btn btn-primary">Accéder</a>
                </div>
            </div>
        </div>

        <?php if ($role === 'Admin'): ?>
            <div class="col">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Liste des Agences</h5>
                        <p class="card-text">Gérer les différentes agences et leurs configurations.</p>
                        <a href="liste_agences.php" class="btn btn-primary">Accéder</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php
// Inclure le pied de page
include('../../templates/footer.php');
?>