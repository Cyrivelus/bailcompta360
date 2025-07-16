<?php
// voir_facture.php

$titre = 'Détails de la Facture';
$current_page = basename($_SERVER['PHP_SELF']); // Utilisé pour la navigation active, etc.

// --- Configuration et Includes ---
ini_set('display_errors', 1); // Pour le développement, à désactiver en production
error_reporting(E_ALL);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
require_once('../../fonctions/database.php');
require_once('../../fonctions/gestion_factures.php'); // Contient getFactureById
require_once('../../fonctions/gestion_ecritures.php'); // Contient getEcrituresByFactureId

// Vérifier si l'ID de la facture est passé en paramètre et est valide
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || intval($_GET['id']) <= 0) {
    $_SESSION['error_message'] = "ID de facture invalide ou manquant.";
    header('Location: listes_factures.php');
    exit();
}
$idFacture = intval($_GET['id']);

// Vérifier la connexion PDO
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("ERREUR FATALE: \$pdo n'a pas été initialisé par database.php dans voir_facture.php");
    $_SESSION['error_message'] = "Erreur de connexion à la base de données.";
    // Afficher un message d'erreur plus convivial ou rediriger vers une page d'erreur générique
    // Pour l'instant, on redirige vers la liste avec un message si possible.
    if (headers_sent()) {
        die("Erreur critique de base de données. Contactez l'administrateur. \$pdo non initialisé.");
    }
    header('Location: listes_factures.php');
    exit();
}

// Récupérer les détails de la facture
$facture = getFactureById($pdo, $idFacture);

if (!$facture) {
    $_SESSION['error_message'] = "Facture non trouvée pour l'ID : " . htmlspecialchars($idFacture);
    header('Location: listes_factures.php');
    exit();
}

// Récupérer les écritures associées à la facture
// La fonction getEcrituresByFactureId doit retourner les champs utilisés dans la table HTML ci-dessous
$ecritures = getEcrituresByFactureId($pdo, $idFacture); 

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Détails Facture N°<?= htmlspecialchars($facture['Numero_Facture'] ?? 'Inconnu') ?></title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
	<link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/formulaire.css">
    <link rel="stylesheet" href="../../css/tableau.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            padding-top: 70px; /* Ajustement pour le header fixe si la navigation est en haut */
            padding-left: 220px; /* Ajustement pour la navigation latérale fixe si style.css la définit */
        }
        .container {
            max-width: 1100px;
        }
        .detail-label {
            font-weight: bold;
            color: #2c3e50;
        }
        .detail-value {
            color: #34495e;
            margin-bottom: 10px;
        }
        .facture-section {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px; /* Coins un peu plus arrondis */
            margin-bottom: 25px;
            border: 1px solid #ddd;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); /* Ombre légère */
        }
        .facture-section h4 {
            margin-top: 0;
            color: #16a085; /* Couleur thématique */
            border-bottom: 2px solid #1abc9c; /* Soulignement thématique */
            padding-bottom: 10px;
            font-size: 1.3em;
        }
        .page-header {
            margin-bottom: 30px;
            color: #2c3e50;
        }
        .btn-actions-footer { /* Style pour les boutons en bas de page */
            margin-top: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        .btn-actions-footer .btn {
            margin-left: 5px;
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="page-header">Détails de la Facture N° <?= htmlspecialchars($facture['Numero_Facture'] ?? 'Inconnu') ?></h2>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <div class="facture-section">
            <h4>Informations Générales</h4>
            <div class="row">
                <div class="col-md-6">
                    <p><span class="detail-label">Numéro de Facture:</span> <span class="detail-value"><?= htmlspecialchars($facture['Numero_Facture'] ?? 'N/A') ?></span></p>
                    
                    <p><span class="detail-label">Date d'Émission:</span> <span class="detail-value"><?= !empty($facture['Date_Emission']) ? date('d/m/Y', strtotime($facture['Date_Emission'])) : 'N/A' ?></span></p>
                    <p><span class="detail-label">Date de Réception:</span> <span class="detail-value"><?= !empty($facture['Date_Reception']) ? date('d/m/Y', strtotime($facture['Date_Reception'])) : 'N/A' ?></span></p>
                    <p><span class="detail-label">Date d'Échéance:</span> <span class="detail-value"><?= !empty($facture['Date_Echeance']) ? date('d/m/Y', strtotime($facture['Date_Echeance'])) : 'N/A' ?></span></p>
                </div>
                <div class="col-md-6">
                    <p><span class="detail-label">Fournisseur:</span> <span class="detail-value"><?= htmlspecialchars($facture['Nom_Fournisseur'] ?? 'N/A') ?></span></p>
                    <p><span class="detail-label">Montant HT:</span> <span class="detail-value"><?= number_format($facture['Montant_HT'] ?? 0, 2, ',', ' ') ?> XAF</span></p>
                    <p><span class="detail-label">Montant TVA:</span> <span class="detail-value"><?= number_format($facture['Montant_TVA'] ?? 0, 2, ',', ' ') ?> XAF</span></p>
                    <p><span class="detail-label">Montant TTC:</span> <span class="detail-value"><?= number_format($facture['Montant_TTC'] ?? 0, 2, ',', ' ') ?> XAF</span></p>
                    <p><span class="detail-label">Statut:</span> <span class="detail-value"><?= htmlspecialchars($facture['Statut_Facture'] ?? 'N/A') ?></span></p>
                </div>
            </div>
            <?php if (!empty($facture['Commentaire'])): ?>
            <div class="row" style="margin-top:15px;">
                <div class="col-md-12">
                    <p><span class="detail-label">Commentaire:</span></p>
                    <p class="detail-value" style="white-space: pre-wrap; background-color: #f9f9f9; border: 1px solid #eee; padding: 10px; border-radius:4px;"><?= htmlspecialchars($facture['Commentaire']) ?></p>
                </div>
            </div>
            <?php endif; ?>
             <?php if (!empty($facture['Numero_Bon_Commande'])): ?>
            <div class="row" style="margin-top:10px;">
                <div class="col-md-12">
                    <p><span class="detail-label">N° Bon de Commande:</span> <span class="detail-value"><?= htmlspecialchars($facture['Numero_Bon_Commande']) ?></span></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="facture-section">
            <h4>Écritures Comptables Associées</h4>
            <?php if (!empty($ecritures)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover">
                        <thead>
                            <tr>
                                <th class="text-center">ID Écriture</th>
                                <th class="text-center">Date</th>
                                <th>Description</th>
                                <th class="text-right">Montant Total</th>
                                <th class="text-center">Journal</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ecritures as $ecriture): ?>
                                <tr>
                                    <td class="text-center"><?= htmlspecialchars($ecriture['ID_Ecriture'] ?? ($ecriture['ID_Ecriture_Comptable'] ?? 'N/A')) ?></td>
                                    <td class="text-center"><?= !empty($ecriture['Date_Saisie']) ? date('d/m/Y', strtotime($ecriture['Date_Saisie'])) : (!empty($ecriture['Date_Ecriture']) ? date('d/m/Y', strtotime($ecriture['Date_Ecriture'])) : 'N/A') ?></td>
                                    <td><?= htmlspecialchars($ecriture['Description'] ?? ($ecriture['Libelle_Ecriture'] ?? 'N/A')) ?></td>
                                    <td class="text-right"><?= isset($ecriture['Montant_Total']) ? number_format($ecriture['Montant_Total'], 2, ',', ' ') . ' €' : 'N/A' ?></td>
                                    <td class="text-center"><?= htmlspecialchars($ecriture['Code_Journal'] ?? 'N/A') ?></td>
                                    <td class="text-center">
                                        <?php $idEcriturePourLien = $ecriture['ID_Ecriture'] ?? ($ecriture['ID_Ecriture_Comptable'] ?? null); ?>
                                        <?php if ($idEcriturePourLien): ?>
                                        <a href="../ecritures/details_ecriture.php?id=<?= htmlspecialchars($idEcriturePourLien) ?>" class="btn btn-xs btn-info" title="Voir Détails Écriture">
                                            <i class="glyphicon glyphicon-eye-open"></i>
                                        </a>
                                        <?php else: echo 'N/A'; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="alert alert-info">
                    Aucune écriture comptable n'est explicitement liée à cette facture pour le moment.
                    <?php if (!empty($facture['ID_Ecriture_Comptable'])): ?>
                        (ID Écriture Comptable lié dans la facture : <?= htmlspecialchars($facture['ID_Ecriture_Comptable']) ?>. Vérifiez la fonction `getEcrituresByFactureId` si des écritures sont attendues.)
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="btn-actions-footer">
            <a href="listes_factures.php" class="btn btn-default">
                <i class="glyphicon glyphicon-arrow-left"></i> Retour à la Liste
            </a>
            <a href="modifier_facture.php?id=<?= $facture['ID_Facture'] ?>" class="btn btn-warning">
                <i class="glyphicon glyphicon-pencil"></i> Modifier la Facture
            </a>
            <?php if(!empty($facture['ID_Ecriture_Comptable']) && !empty($ecritures)): // Si une écriture est liée ET affichée ?>
                 <a href="../ecritures/details_ecriture.php?id=<?= $facture['ID_Ecriture_Comptable'] ?>" class="btn btn-primary" title="Voir l'écriture comptable liée à cette facture">
                    <i class="glyphicon glyphicon-book"></i> Voir Écriture Liée (N°<?= htmlspecialchars($facture['ID_Ecriture_Comptable']) ?>)
                </a>
            <?php elseif(empty($facture['ID_Ecriture_Comptable'])): // Si aucun ID d'écriture n'est dans la facture ?>
                <a href="../ecritures/liste.php?id_facture=<?= $facture['ID_Facture'] ?>" class="btn btn-success" title="Créer une nouvelle écriture comptable et la lier à cette facture">
                    <i class="glyphicon glyphicon-plus"></i> Voir les écriture Comptables
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>
</html>

<?php
require_once('../../templates/footer.php'); // S'assurer que ce fichier existe et est correct
?>