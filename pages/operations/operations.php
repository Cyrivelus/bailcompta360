<?php
// pages/operations/operations.php

/**
 * Cette page sert de tableau de bord pour toutes les opérations de caisse.
 * Elle redirige vers les pages de transaction spécifiques (dépôt, retrait, virement).
 */

// Inclure la connexion à la base de données et les fonctions nécessaires
require_once '../../fonctions/database.php';

// Assurez-vous que l'utilisateur est authentifié et que sa caisse est ouverte
session_start();
if (!isset($_SESSION['utilisateur_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Caissiere')) {
    header("Location: ../../index.php"); // Redirection vers la page de connexion si non authentifié ou non autorisé
    exit();
}



// Inclure le header de la page (début du HTML)
include '../../templates/navigation.php'; 
include '../../templates/header.php'; 
?>

<div class="container-fluid mt-4">
    <h2>Opérations de Caisse</h2>
    <p class="lead">Bienvenue dans le module d'opérations. Veuillez choisir l'action que vous souhaitez effectuer.</p>

    <div class="row mt-5">
        
        <div class="col-md-4 mb-4">
            <div class="card h-100 text-center shadow-sm border-success">
                <div class="card-body d-flex flex-column justify-content-center">
                    <i class="fas fa-money-check-alt fa-4x text-success mb-3"></i>
                    <h5 class="card-title">Dépôt</h5>
                    <p class="card-text">Enregistrer un versement sur un compte client.</p>
                </div>
                <div class="card-footer bg-success-subtle border-0">
                    <a href="transactions/depot.php" class="btn btn-success btn-sm w-75">
                        <i class="fas fa-arrow-right"></i> Effectuer un dépôt
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card h-100 text-center shadow-sm border-danger">
                <div class="card-body d-flex flex-column justify-content-center">
                    <i class="fas fa-hand-holding-usd fa-4x text-danger mb-3"></i>
                    <h5 class="card-title">Retrait</h5>
                    <p class="card-text">Retirer de l'argent d'un compte client.</p>
                </div>
                <div class="card-footer bg-danger-subtle border-0">
                    <a href="transactions/retrait.php" class="btn btn-danger btn-sm w-75">
                        <i class="fas fa-arrow-right"></i> Effectuer un retrait
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card h-100 text-center shadow-sm border-warning">
                <div class="card-body d-flex flex-column justify-content-center">
                    <i class="fas fa-exchange-alt fa-4x text-warning mb-3"></i>
                    <h5 class="card-title">Virement</h5>
                    <p class="card-text">Transférer des fonds d'un compte à un autre.</p>
                </div>
                <div class="card-footer bg-warning-subtle border-0">
                    <a href="transactions/virement.php" class="btn btn-warning btn-sm w-75">
                        <i class="fas fa-arrow-right"></i> Effectuer un virement
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