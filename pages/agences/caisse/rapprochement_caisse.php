<?php
// pages/agences/caisses/rapprochement_caisse.php

/**
 * Interface de rapprochement des caisses d'agence.
 * Permet de comparer le solde théorique des transactions avec le solde physique.
 */
session_start();

// 1. Inclure le fichier de connexion à la base de données en PREMIER.
require_once '../../../database.php';

// 2. Maintenant que $pdo est défini, inclure les fichiers de classes et de fonctions.
require_once '../../../fonctions/agences/gestion_caisses.php';
require_once '../../../fonctions/gestion_agences.php';

// 3. Initialiser l'objet GestionCaisses APRÈS que $pdo soit disponible.
$gestionCaisses = new GestionCaisses($pdo);
// Le code interagit ici avec la table `caisses` pour obtenir la liste des caisses.
$caisses = $gestionCaisses->getToutesCaisses();

$message = '';
$message_type = '';
$rapport_rapprochement = null;

// Traitement du formulaire si la méthode est POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id_caisse = filter_input(INPUT_POST, 'id_caisse', FILTER_VALIDATE_INT);
        $date_rapprochement = filter_input(INPUT_POST, 'date_rapprochement', FILTER_SANITIZE_STRING);
        $solde_physique = filter_input(INPUT_POST, 'solde_physique', FILTER_VALIDATE_FLOAT);

        // Validation des champs
        if ($id_caisse === false || empty($date_rapprochement) || $solde_physique === false) {
            throw new Exception("Veuillez remplir tous les champs avec des valeurs valides.");
        }

        // 1. Récupérer les transactions de la caisse pour la date spécifiée
        // Cette fonction récupère les données de la table `ecritures_caisses`.
        $transactions_caisse = $gestionCaisses->getTransactionsCaisse($id_caisse, $date_rapprochement);

        // 2. Calculer le solde théorique - la logique est maintenant simplifiée.
        $solde_theorique = 0;
        foreach ($transactions_caisse as $transaction) {
            // Un dépôt augmente le solde, un retrait le diminue
            if ($transaction['type_operation'] === 'depot') {
                $solde_theorique += $transaction['montant'];
            } elseif ($transaction['type_operation'] === 'retrait') {
                $solde_theorique -= $transaction['montant'];
            }
        }

        // 3. Calculer l'écart
        $ecart = $solde_physique - $solde_theorique;
        
        // 4. Enregistrer le rapprochement
        // Cette fonction insère le rapport final dans une table de rapprochement.
        $succes_enregistrement = $gestionCaisses->enregistrerRapprochement(
            $id_caisse,
            $date_rapprochement,
            $solde_theorique,
            $solde_physique,
            $ecart
        );

        if ($succes_enregistrement) {
            $message = "Le rapprochement de caisse a été enregistré avec succès. Écart : " . number_format($ecart, 2, ',', ' ') . " XAFa";
            $message_type = 'success';
        } else {
            throw new Exception("Une erreur est survenue lors de l'enregistrement du rapprochement.");
        }
        
        // Créer le rapport pour l'affichage, qu'il y ait des transactions ou non
        $rapport_rapprochement = [
            'id_caisse' => $id_caisse,
            'date' => $date_rapprochement,
            'solde_theorique' => $solde_theorique,
            'solde_physique' => $solde_physique,
            'ecart' => $ecart
        ];

        if (empty($transactions_caisse)) {
            $message = "Aucune transaction trouvée pour cette caisse à la date du " . htmlspecialchars($date_rapprochement) . ". Le solde théorique est de 0.";
            $message_type = 'info';
        }

    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $message_type = 'danger';
        $rapport_rapprochement = null;
    }
}

// Inclure le header et la navigation
include '../../../templates/navigation.php';
include '../../../templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Rapprochement de Caisse</h2>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type; ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card p-4 shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Formulaire de Rapprochement</h4>
        </div>
        <div class="card-body">
            <form action="rapprochement_caisse.php" method="POST">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="id_caisse" class="form-label">Sélectionner une Caisse</label>
                        <select class="form-select" id="id_caisse" name="id_caisse" required>
                            <option value="">-- Sélectionner --</option>
                            <?php foreach ($caisses as $caisse): ?>
                                <option value="<?= htmlspecialchars($caisse['id_caisse']); ?>">
                                    <?= htmlspecialchars($caisse['nom_caisse'] . ' - Agence ' . $caisse['nom_agence']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="date_rapprochement" class="form-label">Date du Rapprochement</label>
                        <input type="date" class="form-control" id="date_rapprochement" name="date_rapprochement" required value="<?= date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="solde_physique" class="form-label">Solde Physique en Caisse (XAFa)</label>
                        <input type="number" step="0.01" class="form-control" id="solde_physique" name="solde_physique" required>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg mt-3">
                        <i class="fas fa-sync-alt"></i> Effectuer le Rapprochement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($rapport_rapprochement): ?>
        <div class="card p-4 shadow-sm">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0">Rapport de Rapprochement</h4>
            </div>
            <div class="card-body">
                <p><strong>Caisse :</strong> <?= htmlspecialchars($rapport_rapprochement['id_caisse']); ?></p>
                <p><strong>Date :</strong> <?= htmlspecialchars($rapport_rapprochement['date']); ?></p>
                <hr>
                <p class="fs-4">
                    <strong>Solde Théorique (informatique) :</strong>
                    <span class="badge bg-secondary"><?= number_format($rapport_rapprochement['solde_theorique'], 2, ',', ' '); ?> XAFa</span>
                </p>
                <p class="fs-4">
                    <strong>Solde Physique :</strong>
                    <span class="badge bg-secondary"><?= number_format($rapport_rapprochement['solde_physique'], 2, ',', ' '); ?> XAFa</span>
                </p>
                <hr>
                <p class="fs-3">
                    <strong>Écart :</strong>
                    <span class="badge bg-<?= ($rapport_rapprochement['ecart'] == 0) ? 'success' : 'danger'; ?>">
                        <?= number_format($rapport_rapprochement['ecart'], 2, ',', ' '); ?> XAFa
                    </span>
                </p>
                <?php if ($rapport_rapprochement['ecart'] != 0): ?>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle"></i> Un écart a été détecté. Une enquête peut être nécessaire pour en déterminer la cause.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// Inclure le footer de la page
include '../../../templates/footer.php';
?>
