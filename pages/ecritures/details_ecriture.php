<?php
// pages/ecritures/details_ecriture.php

$titre = 'Details de l\'ecriture Comptable';
$current_page = basename($_SERVER['PHP_SELF']);

// --- Configuration et Includes ---
ini_set('display_errors', 1); // For development, disable in production
error_reporting(E_ALL);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
require_once('../../fonctions/database.php');
require_once('../../fonctions/gestion_ecritures.php'); // Contains getEcritureById and getLignesEcritureByEcritureId

// 1. Verify if ID_Ecriture is passed and is valid
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || intval($_GET['id']) <= 0) {
    $_SESSION['error_message'] = "ID d'écriture comptable invalide ou manquant.";
    header('Location: liste.php'); // Redirect to the list of entries
    exit();
}
$idEcriture = intval($_GET['id']);

// 2. Verify PDO connection
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("FATAL ERROR: \$pdo not initialized by database.php in details_ecriture.php");
    $_SESSION['error_message'] = "Erreur de connexion à la base de données.";
    if (headers_sent()) {
        die("Erreur critique de base de données. Contactez l'administrateur. \$pdo non initialisé.");
    }
    header('Location: liste.php');
    exit();
}

// 3. Fetch main Ecriture details
$ecriture = getEcritureById($pdo, $idEcriture);

if (!$ecriture) {
    $_SESSION['error_message'] = "Écriture comptable non trouvée pour l'ID : " . htmlspecialchars($idEcriture);
    header('Location: liste.php');
    exit();
}

// 4. Fetch associated Lignes_Ecritures
$lignes_ecriture = getLignesEcritureByEcritureId($pdo, $idEcriture);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | D&eacutetails &Eacutecriture Numero<?= htmlspecialchars($ecriture['ID_Ecriture'] ?? 'Inconnu') ?></title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/formulaire.css">
    <link rel="stylesheet" href="../../css/tableau.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
	 <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/select2.min.css">
    <link rel="stylesheet" href="../../css/select2-bootstrap.min.css">
    <style>
        body {
            padding-top: 70px;
            padding-left: 220px;
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
        .ecriture-section {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid #ddd;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .ecriture-section h4 {
            margin-top: 0;
            color: #16a085;
            border-bottom: 2px solid #1abc9c;
            padding-bottom: 10px;
            font-size: 1.3em;
        }
        .page-header {
            margin-bottom: 30px;
            color: #2c3e50;
        }
        .btn-actions-footer {
            margin-top: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        .btn-actions-footer .btn {
            margin-left: 5px;
            margin-right: 5px;
        }
        /* Styles pour les lignes d'écritures */
        .table-lignes-ecriture thead th {
            background-color: #f2f2f2;
            color: #333;
            border-bottom: 2px solid #ddd;
        }
        .table-lignes-ecriture tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .table-lignes-ecriture .text-right {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="page-header">D&eacutetails de l'&Eacutecriture Comptable Numero <?= htmlspecialchars($ecriture['ID_Ecriture'] ?? 'Inconnu') ?></h2>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <div class="ecriture-section">
            <h4>Informations G&eacuten&eacuterales de l'&Eacutecriture</h4>
            <div class="row">
                <div class="col-md-6">
                    <p><span class="detail-label">ID &Eacutecriture:</span> <span class="detail-value"><?= htmlspecialchars($ecriture['ID_Ecriture'] ?? 'N/A') ?></span></p>
                    <p><span class="detail-label">Date de Saisie:</span> <span class="detail-value"><?= !empty($ecriture['Date_Saisie']) ? date('d/m/Y H:i:s', strtotime($ecriture['Date_Saisie'])) : 'N/A' ?></span></p>
                    <p><span class="detail-label">Description:</span> <span class="detail-value"><?= htmlspecialchars($ecriture['Description'] ?? 'N/A') ?></span></p>
                    <p><span class="detail-label">Montant Total:</span> <span class="detail-value"><?= number_format($ecriture['Montant_Total'] ?? 0, 2, ',', ' ') ?> XAF</span></p>
                </div>
                <div class="col-md-6">
                    <p><span class="detail-label">Journal:</span> <span class="detail-value"><?= htmlspecialchars($ecriture['Code_Journal'] ?? 'N/A') ?> (ID: <?= htmlspecialchars($ecriture['ID_Journal'] ?? 'N/A') ?>)</span></p>
                    <p><span class="detail-label">Code Journal (Cde):</span> <span class="detail-value"><?= htmlspecialchars($ecriture['Cde'] ?? 'N/A') ?></span></p>
                    <p><span class="detail-label">Num&eacutero Piece:</span> <span class="detail-value"><?= htmlspecialchars($ecriture['Numero_Piece'] ?? 'N/A') ?></span></p>
                    <p><span class="detail-label">Mois Comptable:</span> <span class="detail-value"><?= htmlspecialchars($ecriture['Mois'] ?? 'N/A') ?></span></p>
                    <p><span class="detail-label">Saisie par:</span> <span class="detail-value"><?= htmlspecialchars($ecriture['NomUtilisateur'] ?? 'N/A') ?></span></p>
                </div>
            </div>
            <?php if (!empty($ecriture['NumeroAgenceSCE'])): ?>
            <div class="row" style="margin-top:15px;">
                <div class="col-md-12">
                    <p><span class="detail-label">Num&eacutero Agence SCE:</span> <span class="detail-value"><?= htmlspecialchars($ecriture['NumeroAgenceSCE']) ?></span></p>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($ecriture['libelle2'])): ?>
            <div class="row" style="margin-top:10px;">
                <div class="col-md-12">
                    <p><span class="detail-label">Libell&eacute Additionnel:</span></p>
                    <p class="detail-value" style="white-space: pre-wrap; background-color: #f9f9f9; border: 1px solid #eee; padding: 10px; border-radius:4px;"><?= htmlspecialchars($ecriture['libelle2']) ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="ecriture-section">
            <h4>D&eacutetail des Lignes d'&Eacutecritures</h4>
            <?php if (!empty($lignes_ecriture)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover table-lignes-ecriture">
                        <thead>
                            <tr>
                                <th class="text-center">ID Ligne</th>
                                <th class="text-center">Compte</th>
                                <th>Libell&eacute Ligne</th>
                                <th class="text-right">D&eacutebit (XAF)</th>
                                <th class="text-right">Cr&eacutedit (XAF)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $totalDebit = 0;
                            $totalCredit = 0;
                            foreach ($lignes_ecriture as $ligne):
                                $montant = $ligne['Montant'] ?? 0;
                                $sens = $ligne['Sens'] ?? '';
                                $debit = ($sens === 'D') ? $montant : 0;
                                $credit = ($sens === 'C') ? $montant : 0;
                                $totalDebit += $debit;
                                $totalCredit += $credit;
                            ?>
                                <tr>
                                    <td class="text-center"><?= htmlspecialchars($ligne['ID_Ligne'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($ligne['Numero_Compte'] ?? 'N/A') ?> - <?= htmlspecialchars($ligne['Nom_Compte'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($ligne['Libelle_Ligne'] ?? 'N/A') ?></td>
                                    <td class="text-right"><?= number_format($debit, 2, ',', ' ') ?></td>
                                    <td class="text-right"><?= number_format($credit, 2, ',', ' ') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-right">Totaux :</th>
                                <th class="text-right"><?= number_format($totalDebit, 2, ',', ' ') ?></th>
                                <th class="text-right"><?= number_format($totalCredit, 2, ',', ' ') ?></th>
                            </tr>
                            <?php if (abs($totalDebit - $totalCredit) > 0.001): // Check for balance with a small tolerance ?>
                            <tr>
                                <td colspan="5" class="text-center alert alert-danger">
                                    Attention : Le total D&eacutebit (<?= number_format($totalDebit, 2, ',', ' ') ?>) et le total Cr&eacutedit (<?= number_format($totalCredit, 2, ',', ' ') ?>) ne sont pas &eacutequilibr&eacutes !
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <p class="alert alert-info">Aucune ligne d'&eacutecriture trouv&eacutee pour cette écriture comptable.</p>
            <?php endif; ?>
        </div>

        <div class="btn-actions-footer">
            <a href="liste.php" class="btn btn-default">
    <i class="glyphicon glyphicon-th-list"></i> Liste des &Eacutecritures
</a>
			 <?php
    $retour_url = 'liste.php'; // Default fallback URL
    if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
        // Basic check to ensure it's not trying to return to itself
        if (strpos($_SERVER['HTTP_REFERER'], basename($_SERVER['PHP_SELF'])) === false) {
            $retour_url = $_SERVER['HTTP_REFERER'];
        }
    }
?>
<a href="<?= htmlspecialchars($retour_url) ?>" class="btn btn-default">
    <i class="glyphicon glyphicon-arrow-left"></i> Retour
</a>
            
            <?php if (!empty($ecriture['Numero_Piece'])): // Assuming Numero_Piece contains the invoice number like 'PAIE-JNIBUB' ?>
                <?php
                    // Extract invoice number from Numero_Piece if it follows the PAIE-XYZ format
                    $factureNum = str_replace('PAIE-', '', $ecriture['Numero_Piece']);
                    // You might need a function to get Facture ID from Numero_Facture if it's not simply the ID
                    // For now, let's assume if it's 'PAIE-ABC', 'ABC' is the Numero_Facture directly.
                    // A proper implementation would query the Factures table to find ID_Facture by Numero_Facture
                    // Example: $linkedFacture = getFactureByNumeroFacture($pdo, $factureNum);
                    // if ($linkedFacture) { $idFacture = $linkedFacture['ID_Facture']; }
                ?>
                <?php /* if ($idFacture): */ ?>
                    <?php /* endif; */ ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
	   <script src="../../js/jquery-3.7.1.min.js"></script>
    <script src="../../js/bootstrap.min.js"></script>
    <script src="../../js/select2.min.js"></script>
</body>
</html>

<?php
require_once('../../templates/footer.php');
?>