<?php
// pages/immobilisations/calcul_amortissement.php
session_start();

// Configuration et inclusions
$titre = 'Calcul des Amortissements';
$current_page = basename(__FILE__);

require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_utilisateurs.php';

// Check if $pdo is available
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("Erreur critique: La connexion PDO n'est pas disponible dans calcul_amortissement.php.");
    header("Location: ../../index.php?error=" . urlencode("Une erreur interne est survenue. Connexion BD manquante."));
    exit();
}

$errorMessage = null;

try {
    // Récupérer toutes les immobilisations
    // ATTENTION: La colonne DateAcquisition n'existe pas, il faut utiliser Date_Acquisition
    $sql = "SELECT * FROM immobilisations ORDER BY Date_Acquisition ASC"; 
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $immobilisations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Initialiser le tableau pour les calculs d'amortissement
    $calculs = [];
    $date_du_jour = new DateTime();

    foreach ($immobilisations as $immo) {
        $valeur_origine = $immo['Montant_HT']; // Correction ici aussi
        $duree_utilisation = $immo['DureeUtilisation']; // Attention: cette colonne n'existe pas dans votre table, il faudra la créer
        $date_acquisition = new DateTime($immo['Date_Acquisition']); // Correction ici
        $amortissement_cumule = 0;
        $annuite = 0;

        // Calcul de l'amortissement linéaire
        if ($duree_utilisation > 0) {
            $taux_amortissement = 100 / $duree_utilisation;
            $annuite = ($valeur_origine * $taux_amortissement) / 100;

            // Calcul du prorata temporis pour la première année
            $premier_janvier_acquisition = new DateTime($date_acquisition->format('Y-01-01'));
            $jours_premiere_annee = $date_acquisition->diff($premier_janvier_acquisition)->days; // Jours écoulés dans l'année
            $prorata_premiere_annee = ($jours_premiere_annee == 365) ? $annuite : ($annuite * (365 - $jours_premiere_annee)) / 365;
            
            // Années complètes écoulées
            $annees_ecoulees = $date_du_jour->diff($date_acquisition)->y;

            // Calcul de l'amortissement cumulé
            $amortissement_cumule = $prorata_premiere_annee + ($annuite * ($annees_ecoulees - 1));
            
            // La valeur de l'amortissement cumulé ne peut pas dépasser la valeur d'origine
            if ($amortissement_cumule > $valeur_origine) {
                $amortissement_cumule = $valeur_origine;
            }
        }

        $vnc = $valeur_origine - $amortissement_cumule;

        $calculs[] = [
            'ID' => $immo['ID_Immobilisation'], // Correction
            'Designation' => $immo['Designation'],
            'ValeurOrigine' => $valeur_origine,
            'DureeUtilisation' => $duree_utilisation,
            'TauxAmortissement' => $taux_amortissement ?? 0,
            'DateAcquisition' => $date_acquisition->format('Y-m-d'),
            'Annuite' => $annuite,
            'AmortissementCumule' => $amortissement_cumule,
            'VNC' => $vnc,
        ];
    }
} catch (PDOException $e) {
    $errorMessage = "Erreur lors de la récupération des immobilisations : " . $e->getMessage();
    error_log("Erreur SQL dans calcul_amortissement.php: " . $e->getMessage());
}

require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <br>
    &nbsp;
    <title><?= htmlspecialchars($titre) ?> | BailCompta 360</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <style>
        .table-responsive {
            margin-top: 20px;
        }
        .page-header {
            margin-top: 0;
        }
        .text-right {
            text-align: right;
        }
    </style>
</head>
<body>
<div class="container">
    <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

    <?php if (isset($errorMessage)): ?>
        <div class="alert alert-danger"><?= $errorMessage ?></div>
    <?php endif; ?>

    <div class="action-buttons" style="margin-bottom: 20px;">
        <a href="ajouter.php" class="btn btn-primary">
            <span class="glyphicon glyphicon-plus"></span> Ajouter une immobilisation
        </a>
    </div>

    <?php if (!empty($calculs)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Désignation</th>
                        <th class="text-right">Valeur d'origine</th>
                        <th class="text-right">Durée (ans)</th>
                        <th class="text-right">Taux (%)</th>
                        <th>Date Acquisition</th>
                        <th class="text-right">Annuité</th>
                        <th class="text-right">Amortissement Cumulé</th>
                        <th class="text-right">VNC</th>
                        <th style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($calculs as $calcul): ?>
                        <tr>
                            <td><?= htmlspecialchars($calcul['ID']) ?></td>
                            <td><?= htmlspecialchars($calcul['Designation']) ?></td>
                            <td class="text-right"><?= number_format($calcul['ValeurOrigine'], 2, ',', ' ') ?></td>
                            <td class="text-right"><?= htmlspecialchars($calcul['DureeUtilisation']) ?></td>
                            <td class="text-right"><?= number_format($calcul['TauxAmortissement'], 2, ',', ' ') ?></td>
                            <td><?= htmlspecialchars($calcul['DateAcquisition']) ?></td>
                            <td class="text-right"><?= number_format($calcul['Annuite'], 2, ',', ' ') ?></td>
                            <td class="text-right"><?= number_format($calcul['AmortissementCumule'], 2, ',', ' ') ?></td>
                            <td class="text-right"><?= number_format($calcul['VNC'], 2, ',', ' ') ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="details.php?id=<?= $calcul['ID'] ?>" class="btn btn-info" title="Détails">
                                        <span class="glyphicon glyphicon-eye-open"></span>
                                    </a>
                                    <a href="modifier.php?id=<?= $calcul['ID'] ?>" class="btn btn-warning" title="Modifier">
                                       <span class="glyphicon glyphicon-folder-open"></span>
                                    </a>
                                    <a href="supprimer.php?id=<?= $calcul['ID'] ?>"
                                       class="btn btn-danger"
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette immobilisation (ID: <?= $calcul['ID'] ?>) ?')"
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
    <?php else: ?>
        <div class="alert alert-info">Aucune immobilisation trouvée dans la base de données.</div>
    <?php endif; ?>
</div>

<?php
require_once('../../templates/footer.php');
?>