<?php
// pages/ecritures/index.php

// Démarrer la session en premier
session_start();

// Configuration et inclusions des fichiers de fonctions
$titre = "Tableau de bord des écritures";
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_utilisateurs.php';
require_once '../../fonctions/gestion_ecritures.php';
require_once '../../fonctions/gestion_emprunts.php';

// --- LOGIQUE DES ALERTES D'ÉCHÉANCES ---
$currentUserRole = $_SESSION['role'] ?? 'Comptable';
$hasUpcomingEmpruntPayments = false;
$hasOverdueEmpruntPayments = false;

if ($currentUserRole === 'Comptable') {
    // La fonction doit être définie dans gestion_emprunts.php
    $loanAlerts = getAccountantLoanAlerts($pdo);
    $hasOverdueEmpruntPayments = $loanAlerts['overdue'];
    $hasUpcomingEmpruntPayments = $loanAlerts['upcoming'];
}

// --- GESTION DES MESSAGES DE RETOUR (succès/erreur) ---
$successMessage = null;
if (isset($_GET['success_saisie'])) {
    $successMessage = "✅ L'écriture a été enregistrée avec succès.";
}
if (isset($_GET['success_update'])) {
    $successMessage = "✅ L'écriture a été mise à jour avec succès.";
}
if (isset($_GET['delete_success'])) {
    $successMessage = "🗑️ L'écriture a été supprimée avec succès.";
}
$errorMessage = null;

// --- RECUPERATION DES 10 DERNIERES ECRITURES ---
// CHANGEMENT MAJEUR : Remplacement de TOP par LIMIT pour compatibilité MySQL
$sql = "SELECT 
            e.ID_Ecriture,
            e.Date_Saisie,
            e.Description,
            e.Montant_Total,
            e.Numero_Piece,
            j.Lib AS Journal_Libelle,
            e.NomUtilisateur
        FROM
            Ecritures e
        LEFT JOIN
            JAL j ON e.Cde = j.Cde
        ORDER BY
            e.Date_Saisie DESC
        LIMIT 10";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $ecritures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = "Erreur lors de la récupération des écritures : " . $e->getMessage();
    $ecritures = [];
}

// Préparer données pour les graphiques
$labels = [];
$montants = [];
$journalData = [];

foreach ($ecritures as $e) {
    $labels[] = date('d/m', strtotime($e['Date_Saisie']));
    $montants[] = (float) $e['Montant_Total'];
    $journal = $e['Journal_Libelle'] ?? 'N/A';
    if (!isset($journalData[$journal])) {
        $journalData[$journal] = 0;
    }
    $journalData[$journal] += (float) $e['Montant_Total'];
}

// --- AFFICHAGE DE LA PAGE (INCLUSIONS HTML) ---
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
?>
<div class="container">
    <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

    <?php if (isset($successMessage)): ?>
        <div class="alert alert-success"><?= $successMessage ?></div>
    <?php endif; ?>

    <?php if (isset($errorMessage)): ?>
        <div class="alert alert-danger"><?= $errorMessage ?></div>
    <?php endif; ?>

    <?php
    // --- AFFICHAGE DES ALERTES POUR LE COMPTABLE ---
    if ($currentUserRole === 'Comptable') {
        if ($hasOverdueEmpruntPayments) {
            echo '<div class="alert alert-danger alert-dismissible" role="alert">';
            echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
            echo '<span class="glyphicon glyphicon-warning-sign" aria-hidden="true"></span> ';
            echo '<strong>Attention, Comptable!</strong> Il y a des échéances d\'emprunt en **retard**. Veuillez les consulter et procéder au paiement sans délai.';
            echo ' <a href="../emprunts/index.php" class="alert-link">Voir les emprunts</a>';
            echo '</div>';
        } elseif ($hasUpcomingEmpruntPayments) {
            echo '<div class="alert alert-warning alert-dismissible" role="alert">';
            echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
            echo '<span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> ';
            echo '<strong>Rappel, Comptable!</strong> Des échéances d\'emprunt arrivent à échéance dans les 7 prochains jours. Pensez à les régler.';
            echo ' <a href="../emprunts/index.php" class="alert-link">Voir les emprunts</a>';
            echo '</div>';
        }
    }
    ?>
    
    <div class="action-buttons" style="margin-bottom: 20px;">
        <a href="saisie.php" class="btn btn-primary">
            <span class="glyphicon glyphicon-plus"></span> Nouvelle écriture
        </a>
        <a href="liste.php" class="btn btn-info">
            <span class="glyphicon glyphicon-list"></span> Liste complète
        </a>
    </div>

    <h3>📝 10 dernières écritures</h3>
    
    <?php if (!empty($ecritures)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead class="bg-primary text-white">
                    <tr>
                        <th>ID</th>
                        
                        <th>Journal</th>
                        <th>Description</th>
                        <th>Date saisie</th>
                    
                        <th style="width: 160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ecritures as $ecriture): ?>
                        <tr>
                            <td><?= $ecriture['ID_Ecriture'] ?></td>
                            
                            <td><?= htmlspecialchars($ecriture['Journal_Libelle'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($ecriture['Description']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($ecriture['Date_Saisie'])) ?></td>
                           
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="details.php?id=<?= $ecriture['ID_Ecriture'] ?>" class="btn btn-info" title="Détails">
                                        <span class="glyphicon glyphicon-eye-open"></span>
                                    </a>
                                    <a href="modifier.php?id=<?= $ecriture['ID_Ecriture'] ?>" class="btn btn-warning" title="Modifier">
                                       <span class="glyphicon glyphicon-pencil"></span>
                                    </a>
                                    <a href="supprimer.php?id=<?= $ecriture['ID_Ecriture'] ?>" 
                                       class="btn btn-danger" 
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette écriture (ID: <?= $ecriture['ID_Ecriture'] ?>) ?')"
                                       title="Supprimer">
                                        <span class="glyphicon glyphicon-trash"></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <hr>
        <h3>📊 Visualisation des écritures</h3>
        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading">Évolution des montants saisis</div>
                    <div class="panel-body">
                        <canvas id="chartEvolution"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading">Répartition par journal</div>
                    <div class="panel-body">
                        <canvas id="chartJournaux"></canvas>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info">ℹ️ Aucune écriture trouvée dans la base de données.</div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx1 = document.getElementById('chartEvolution').getContext('2d');
    new Chart(ctx1, {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: 'Montants saisis (FCFA)',
                data: <?= json_encode($montants) ?>,
                borderColor: 'rgba(54, 162, 235, 1)',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                fill: true,
                tension: 0.2
            }]
        },
        options: { 
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Montant (FCFA)'
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Évolution des montants des 10 dernières écritures'
                }
            }
        }
    });

    const ctx2 = document.getElementById('chartJournaux').getContext('2d');
    new Chart(ctx2, {
        type: 'pie',
        data: {
            labels: <?= json_encode(array_keys($journalData)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($journalData)) ?>,
                backgroundColor: ['#007bff','#28a745','#ffc107','#dc3545','#6f42c1','#20c997']
            }]
        },
        options: { 
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Répartition des 10 dernières écritures par journal'
                }
            }
        }
    });
</script>

<?php
require_once('../../templates/footer.php');
?>