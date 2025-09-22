<?php
// pages/ecritures/liste.php

session_start();
// Authentification
if (!isset($_SESSION['utilisateur_id'], $_SESSION['role'])) {
    header('Location: ../../login.php');
    exit;
}

$titre = 'Consultation de l\'ecriture comptable';
$current_page = basename($_SERVER['PHP_SELF']);

require_once('../../templates/header.php');
require_once('../../templates/navigation.php');

// Inclure les fichiers nécessaires
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_ecritures.php';
require_once '../../fonctions/gestion_comptes.php';
require_once '../../fonctions/gestion_factures.php';


// Récupérer la liste des comptes
$comptes = getListeComptes($pdo);

// Initialisation des variables
$idEcriture = isset($_GET['id']) && is_numeric($_GET['id']) ? $_GET['id'] : null;
$dateSaisie = '';
$description = '';
$lignesEcriture = [];
$erreur = null;
$success = isset($_GET['success']) ? $_GET['success'] : null;

// Constantes
const TVA_RATE = 0.1925; // 19.25%

// Variables pour calculs
$montantHorsTVA = 0;
$montantTVA = 0;
$totalDebit = 0;
$totalCredit = 0;
$factureImpayee = false; // Initialisé à false par défaut

// Récupérer l'écriture si ID fourni
if ($idEcriture) {
    $ecriture = getEcriture($pdo, $idEcriture);
    $lignesEcriture = getLignesEcriture($pdo, $idEcriture);
    $facture = getFactureParEcriture($pdo, $idEcriture);

    if ($ecriture) {
        $dateSaisie = isset($ecriture['Date_Saisie']) ? date('d/m/Y', strtotime($ecriture['Date_Saisie'])) : '';
        $description = isset($ecriture['Description']) ? $ecriture['Description'] : '';
        $numeroPiece = isset($ecriture['Numero_Piece']) ? $ecriture['Numero_Piece'] : '';
        $journal = isset($ecriture['Cde']) ? $ecriture['Cde'] : '';

        // *** Début de la zone de vérification et correction ***
        
        // Vérifier si une facture est associée et si son statut est 'Non payée'
        // Si $facture est null (pas de facture associée), alors elle n'est ni payée ni impayée au sens d'une facture.
        if ($facture && isset($facture['Statut_Facture'])) {
            if ($facture['Statut_Facture'] === 'Non payée') {
                $factureImpayee = true;
            } else {
                // Si le statut existe mais n'est pas 'Non payée', on considère qu'elle n'est pas impayée
                $factureImpayee = false;
            }
        } else {
            // Si aucune facture n'est associée à cette écriture, elle n'est pas "impayée" au sens d'une facture.
            $factureImpayee = false;
        }

        // *** Fin de la zone de vérification et correction ***

        // Calcul des totaux
        foreach ($lignesEcriture as $ligne) {
            $montant = (float)$ligne['Montant'];
            
            if ($ligne['Sens'] == 'D') {
                $totalDebit += $montant;
                
                // Trouver le numéro de compte
                $numeroCompte = '';
                foreach ($comptes as $compte) {
                    if ($compte['ID_Compte'] == $ligne['ID_Compte']) {
                        $numeroCompte = $compte['Numero_Compte'];
                        break;
                    }
                }
                
                // Calcul TVA (comptes 445...)
                if (strpos($numeroCompte, '445') === 0) {
                    $montantTVA += $montant;
                }
            } else {
                $totalCredit += $montant;
            }
        }

        // Calcul montant HT
        if ($montantTVA > 0) {
            $montantHorsTVA = $totalDebit - $montantTVA;
        }
    } else {
        $erreur = "Ecriture non trouvée.";
    }
} else {
    $erreur = "ID d'écriture non valide.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">&nbsp;
    <title>&nbsp; BailCompta 360 |&nbsp;<?= htmlspecialchars($titre) ?></title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <style>
        .detail-panel {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .ligne-ecriture {
            margin-bottom: 10px;
            padding: 10px;
            background: #fff;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .table-details {
            width: 100%;
            margin-bottom: 20px;
        }
        .table-details th {
            background-color: #f2f2f2;
            text-align: left;
            padding: 8px;
        }
        .table-details td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        .montant-debit {
            color: #d9534f;
            font-weight: bold;
        }
        .montant-credit {
            color: #5cb85c;
            font-weight: bold;
        }
        .total-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .tva-info {
            margin-top: 15px;
            padding: 10px;
            background-color: #eaf7f7;
            border-left: 5px solid #00a0b0;
        }
        .facture-impayee {
            background-color: #fff3f3;
            border-left: 5px solid #d9534f;
            padding: 15px;
            margin-bottom: 20px;
        }
        .warning-impayee {
            color: #d9534f;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

        <?php if ($success) : ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($erreur) : ?>
            <div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <?php if ($idEcriture && !$erreur) : ?>
            <div class="detail-panel">
                <?php if ($factureImpayee) : ?>
                    <div class="facture-impayee">
                        <h3 class="warning-impayee">⚠️ FACTURE IMPAYÉE</h3>
                        <p>Seules les opérations au crédit sont affichées ci-dessous.</p>
                    </div>
                <?php endif; ?>

                <h3>Informations générales</h3>
                <table class="table-details">
                    <tr>
                        <th>Numéro pièce</th>
                        <td><?= htmlspecialchars($numeroPiece) ?></td>
                    </tr>
                    <tr>
                        <th>Journal</th>
                        <td><?= htmlspecialchars($journal) ?></td>
                    </tr>
                    <tr>
                        <th>Date de saisie</th>
                        <td><?= htmlspecialchars($dateSaisie) ?></td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td><?= htmlspecialchars($description) ?></td>
                    </tr>
                    <?php if ($facture) : // Afficher le statut seulement s'il y a une facture associée ?>
                        <tr>
                            <th>Statut facture</th>
                            <td>
                                <?php if ($facture['Statut_Facture'] === 'Payée') : ?>
                                    <span class="label label-success">Payée</span>
                                <?php elseif ($facture['Statut_Facture'] === 'Non payée') : ?>
                                    <span class="label label-danger">Non payée</span>
                                <?php else : ?>
                                    <span class="label label-info">Statut : <?= htmlspecialchars($facture['Statut_Facture']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>

                <h3>Lignes d'écriture</h3>
                <table class="table-details">
                    <thead>
                        <tr>
                            <th>Compte</th>
                            <th>Libellé</th>
                            <th>Débit</th>
                            <th>Crédit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lignesEcriture as $ligne) : ?>
                            <?php 
                            // Pour les factures impayées, on n'affiche que les crédits (logique inchangée ici)
                            // Si cette logique doit être revue, cela dépend de ce que vous souhaitez afficher
                            // pour une facture impayée (débits masqués, seulement crédits)
                            if ($factureImpayee && $ligne['Sens'] !== 'C') continue;
                            
                            $montant = (float)$ligne['Montant'];
                            ?>
                            <tr class="ligne-ecriture">
                                <td>
                                    <?php
                                    $numeroCompte = '';
                                    $nomCompte = '';
                                    foreach ($comptes as $compte) {
                                        if ($compte['ID_Compte'] == $ligne['ID_Compte']) {
                                            $numeroCompte = $compte['Numero_Compte'];
                                            $nomCompte = $compte['Nom_Compte'];
                                            break;
                                        }
                                    }
                                    echo htmlspecialchars($numeroCompte);
                                    echo '&nbsp;';
                                    echo htmlspecialchars($nomCompte);
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($ligne['Libelle_Ligne'] ?? '') ?></td>
                                <td class="montant-debit">
                                    <?= (!$factureImpayee && $ligne['Sens'] == 'D') ? number_format($montant, 2, ',', ' ') : '' ?>
                                </td>
                                <td class="montant-credit">
                                    <?= $ligne['Sens'] == 'C' ? number_format($montant, 2, ',', ' ') : '' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="2"><strong>Total</strong></td>
                            <td class="montant-debit">
                                <?= !$factureImpayee ? number_format($totalDebit, 2, ',', ' ') : '' ?>
                            </td>
                            <td class="montant-credit">
                                <?= number_format($totalCredit, 2, ',', ' ') ?>
                            </td>
                        </tr>
                        <?php if ($factureImpayee) : ?>
                            <tr class="warning-impayee">
                                <td colspan="4">
                                    <strong>Déséquilibre comptable dû au non-paiement : 
                                    <?= number_format($totalDebit - $totalCredit, 2, ',', ' ') ?> FCFA</strong>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if (!$factureImpayee && $montantTVA > 0) : ?>
                    <div class="tva-info">
                        <h4>Détails TVA</h4>
                        <p>Montant Hors TVA (débité) : <strong><?= number_format($montantHorsTVA, 2, ',', ' ') ?></strong></p>
                        <p>Montant TVA (19.25%) : <strong><?= number_format($montantTVA, 2, ',', ' ') ?></strong></p>
                        <p>Montant TTC (total débit) : <strong><?= number_format($totalDebit, 2, ',', ' ') ?></strong></p>
                    </div>
                <?php endif; ?>

                <div class="text-right">
                    <a href="javascript:window.history.back()" class="btn btn-default">Retour</a>
                    <a href="export_excel.php?id=<?= urlencode($idEcriture) ?>" class="btn btn-success" target="_blank">
                        <span class="glyphicon glyphicon-download"></span> Exporter en Excel
                    </a>
                    <a href="export_pdf.php?id=<?= urlencode($idEcriture) ?>" class="btn btn-danger" target="_blank">
                        <span class="glyphicon glyphicon-print"></span> Imprimer en PDF
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>
</html>

<?php
require_once('../../templates/footer.php');
?>