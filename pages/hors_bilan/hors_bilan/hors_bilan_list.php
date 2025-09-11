<?php
// pages/hors_bilan/hors_bilan_list.php

/**
 * Cette page sert de tableau de bord pour les engagements hors bilan,
 * avec un aperçu et des liens de navigation.
 */

// Démarrer la session pour accéder aux variables de session.
session_start();

// Inclure la configuration de la base de données.
require_once '../../fonctions/database.php';
// Inclure les fonctions pour l'aperçu des données.
require_once '../../fonctions/hors_bilan_fonctions.php';

// Initialiser les variables pour l'aperçu des données.
$apercu = [
    'avances_client' => 0,
    'hypotheques' => 0,
];

try {
    // Récupérer le nombre total d'avances et d'hypothèques.
    $apercu['avances_client'] = count(getAvancesClient($pdo));
    $apercu['hypotheques'] = count(getHypotheques($pdo));

} catch (Exception $e) {
    // En cas d'erreur, ne pas bloquer l'affichage, mais vider les compteurs.
    $apercu = [
        'avances_client' => 0,
        'hypotheques' => 0,
    ];
    // Vous pouvez également afficher un message d'erreur si nécessaire.
    // $message = "Erreur de chargement : " . $e->getMessage();
}

// Inclure les fichiers de gabarit pour l'interface utilisateur.
include '../../templates/header.php'; 
include '../../templates/navigation.php'; 
?>

<div class="container-fluid mt-4">
    <h2>Résumé des Engagements Hors Bilan</h2>
    <hr>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card text-center shadow-sm">
                <div class="card-header bg-info text-white">
                    Avances Client
                </div>
                <div class="card-body">
                    <h3 class="card-title"><?= htmlspecialchars($apercu['avances_client']); ?></h3>
                    <p class="card-text">Total des avances en cours.</p>
                    <a href="avance_client.php" class="btn btn-info">Voir les détails</a>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card text-center shadow-sm">
                <div class="card-header bg-primary text-white">
                    Hypothèques
                </div>
                <div class="card-body">
                    <h3 class="card-title"><?= htmlspecialchars($apercu['hypotheques']); ?></h3>
                    <p class="card-text">Total des hypothèques actives.</p>
                    <a href="hypotheque.php" class="btn btn-primary">Voir les détails</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Inclure le footer de la page.
include '../../templates/footer.php';
?>