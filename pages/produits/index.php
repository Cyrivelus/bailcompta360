<?php
// pages/produits_bancaires/index.php

/**
 * Cette page sert de tableau de bord pour la gestion des produits bancaires.
 * Elle redirige vers les pages de gestion spécifiques à chaque type de produit.
 */

// Inclure la connexion à la base de données et les fonctions nécessaires
require_once '../../database.php';

// Assurez-vous que l'utilisateur est authentifié et a les droits d'accès
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php"); // Redirection si non authentifié
    exit();
}

$message = '';
$message_type = '';

// On peut ici implémenter une logique pour afficher des messages de succès/erreur
// si l'utilisateur vient d'une page de création ou de modification de produit.
if (isset($_SESSION['produit_message'])) {
    $message = $_SESSION['produit_message']['text'];
    $message_type = $_SESSION['produit_message']['type'];
    unset($_SESSION['produit_message']);
}

// Inclure le header de la page (début du HTML)
include '../../templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Gestion des Produits Bancaires</h2>
        <a href="ajouter_produit.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Ajouter un nouveau produit
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <p class="lead">Sélectionnez une catégorie de produits pour les gérer.</p>

    <div class="row mt-5">
        
        <div class="col-md-4 mb-4">
            <div class="card h-100 text-center shadow-sm border-info">
                <div class="card-body d-flex flex-column justify-content-center">
                    <i class="fas fa-university fa-4x text-info mb-3"></i>
                    <h5 class="card-title">Produits Comptes</h5>
                    <p class="card-text">Gérer les produits de comptes courants et d'épargne.</p>
                </div>
                <div class="card-footer bg-info-subtle border-0">
                    <a href="produits_comptes.php" class="btn btn-info btn-sm w-75">
                        <i class="fas fa-arrow-right"></i> Voir les produits
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card h-100 text-center shadow-sm border-success">
                <div class="card-body d-flex flex-column justify-content-center">
                    <i class="fas fa-piggy-bank fa-4x text-success mb-3"></i>
                    <h5 class="card-title">Produits d'Épargne</h5>
                    <p class="card-text">Gérer les produits d'épargne à taux d'intérêt.</p>
                </div>
                <div class="card-footer bg-success-subtle border-0">
                    <a href="produits_epargne.php" class="btn btn-success btn-sm w-75">
                        <i class="fas fa-arrow-right"></i> Voir les produits
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card h-100 text-center shadow-sm border-warning">
                <div class="card-body d-flex flex-column justify-content-center">
                    <i class="fas fa-handshake fa-4x text-warning mb-3"></i>
                    <h5 class="card-title">Produits de Crédit</h5>
                    <p class="card-text">Gérer les produits de prêts et de crédits.</p>
                </div>
                <div class="card-footer bg-warning-subtle border-0">
                    <a href="produits_credits.php" class="btn btn-warning btn-sm w-75">
                        <i class="fas fa-arrow-right"></i> Voir les produits
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<?php 
// Inclure le footer de la page (fin du HTML)
include '../../templates/footer.php';
?>