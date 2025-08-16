<?php
// pages/agences/liste_agences.php

/**
 * Cette page affiche la liste des agences.
 * Elle permet de les visualiser, les modifier et les supprimer.
 */

// 1. Démarrer la session en premier. C'est la première chose à faire.
session_start();

// 2. Inclure les fichiers de logique et de base de données.
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_agences.php';

// Initialiser les messages d'alerte
$message = '';
$message_type = '';

try {
    // Récupérer la liste de toutes les agences
    $agences = getAllAgences($pdo);
    
    // Gérer les actions de l'utilisateur (suppression, par exemple)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action']) && $_POST['action'] === 'supprimer') {
            $code_a_supprimer = $_POST['code_agence'];
            if (deleteAgence($pdo, $code_a_supprimer)) {
                $message = "L'agence avec le code '{$code_a_supprimer}' a été supprimée avec succès.";
                $message_type = 'success';
                // Recharger la liste après la suppression
                $agences = getAllAgences($pdo);
            } else {
                $message = "Échec de la suppression de l'agence. Une erreur est survenue.";
                $message_type = 'danger';
            }
        }
    }
} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
    $agences = []; // Assurer que le tableau est vide en cas d'erreur grave
}

// 3. Inclure les templates qui contiennent la mise en page HTML.
include '../../templates/header.php';
include '../../templates/navigation.php'; 
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Gestion des Agences</h2>
        <a href="ajouter_agence.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Ajouter une agence
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <?php if (!empty($agences)): ?>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Code Agence</th>
                        <th>Libellé</th>
                        <th>N° Compte Comptable</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agences as $agence): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($agence['CodeAgenceSCE']); ?></td>
                            <td><?php echo htmlspecialchars($agence['LibelleAgenceSCE']); ?></td>
                            <td><?php echo htmlspecialchars($agence['NoCompteComptable'] ?? 'N/A'); ?></td>
                            <td>
                                <a href="modifier_agence.php?code=<?php echo urlencode($agence['CodeAgenceSCE']); ?>" class="btn btn-sm btn-info me-2">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>
                                <form action="" method="post" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette agence ? Cette action est irréversible et peut impacter des données existantes.');">
                                    <input type="hidden" name="action" value="supprimer">
                                    <input type="hidden" name="code_agence" value="<?php echo htmlspecialchars($agence['CodeAgenceSCE']); ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash-alt"></i> Supprimer
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-warning text-center" role="alert">
                Aucune agence n'a été trouvée. Veuillez en ajouter une pour commencer.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
// Inclure le footer de la page (fin du HTML)
include '../../templates/footer.php'; 
?>