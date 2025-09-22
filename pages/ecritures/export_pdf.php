<?php
// pages/ecritures/export_pdf.php (Now print-friendly HTML)

// Start the session to access $_SESSION['nom_utilisateur']
session_start();

// Ensure no output is sent before headers (though less critical for HTML output)
ob_start();

// Inclure les fichiers n�cessaires (sans les templates HTML de navigation/footer g�n�raux)
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_ecritures.php';
require_once '../../fonctions/gestion_comptes.php';



// R�cup�rer l'ID de l'�criture
$idEcriture = isset($_GET['id']) && is_numeric($_GET['id']) ? $_GET['id'] : null;

if (!$idEcriture) {
    echo "<p>ID d'�criture non valide.</p>";
    exit();
}

$ecriture = getEcriture($pdo, $idEcriture);
$lignesEcriture = getLignesEcriture($pdo, $idEcriture);
$comptes = getListeComptes($pdo);

if (!$ecriture) {
    echo "<p>�criture non trouv�e.</p>";
    exit();
}

// Prepare data
$dateSaisie = isset($ecriture['Date_Saisie']) ? date('d/m/Y', strtotime($ecriture['Date_Saisie'])) : '';
$description = isset($ecriture['Description']) ? $ecriture['Description'] : '';
$numeroPiece = isset($ecriture['Numero_Piece']) ? $ecriture['Numero_Piece'] : '';
$journal = isset($ecriture['Cde']) ? $ecriture['Cde'] : '';

$totalDebit = 0;
$totalCredit = 0;
// Calculate totalDebit for TVA calculation first
foreach ($lignesEcriture as $ligne) {
    if ($ligne['Sens'] == 'D') {
        $totalDebit += (float)$ligne['Montant'];
    }
}

// Calculate TVA details
const TVA_RATE = 0.1925; // 19.25%
$montantTVA = 0;
foreach ($lignesEcriture as $ligne) {
    if ($ligne['Sens'] == 'D') {
        $numeroCompte = '';
        foreach ($comptes as $compte) {
            if ($compte['ID_Compte'] == $ligne['ID_Compte']) {
                $numeroCompte = $compte['Numero_Compte'];
                break;
            }
        }
        if (strpos($numeroCompte, '445') === 0) { // Assuming TVA accounts start with '445'
            $montantTVA += (float)$ligne['Montant'];
        }
    }
}
$montantHorsTVA = ($montantTVA > 0) ? $totalDebit - $montantTVA : 0;

// Start HTML output for print preview
$printTime = (new DateTime())->modify('-1 hour')->format('d/m/Y H:i:s');
$printedBy = isset($_SESSION['nom_utilisateur']) ? $_SESSION['nom_utilisateur'] : 'Utilisateur inconnu';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Extrait de Compte #<?= htmlspecialchars($numeroPiece) ?> - Aper&ccedilu Impression</title>
    <style>
        /* CSS for print optimization */
        body {
            font-family: Arial, sans-serif;
            margin: 20mm; /* Standard margin for print */
            color: #333;
            font-size: 10pt;
        }
        h1, h2, h3 {
            color: #000;
            margin-bottom: 5mm;
        }
        h1 { font-size: 16pt; text-align: center; }
        h2 { font-size: 14pt; }
        h3 { font-size: 12pt; }

        .header-info, .section-title {
            margin-top: 15mm;
            margin-bottom: 5mm;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10mm;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .total-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .montant-debit {
            color: #d9534f; /* Retain color for screen preview, but it might not print in color */
            font-weight: bold;
        }
        .montant-credit {
            color: #5cb85c; /* Retain color for screen preview */
            font-weight: bold;
        }
        .tva-info {
            margin-top: 10mm;
            padding: 10px;
            background-color: #eaf7f7;
            border-left: 5px solid #00a0b0;
        }

        /* Hide elements not needed for print */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                margin: 0; /* No margins on printed page, handled by browser print dialog */
            }
            /* Ensure colors print for money values if browser setting allows */
            .montant-debit, .montant-credit {
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <h1>Extrait de Compte</h1>

        <div class="header-info">
            <h2>Informations g&eacuten&eacuterales</h2>
            <table>
                <tr>
                    <th>Num&eacutero pi&egravece</th>
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
            </table>
        </div>

        <div class="section-title">
            <h2>Lignes d'&eacutecriture</h2>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Compte</th>
                    <th>Libell&eacute</th>
                    <th class="text-right">D&eacutebit</th>
                    <th class="text-right">Cr&eacutedit</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $currentTotalDebit = 0; // Use different variable names to avoid confusion with overall totalDebit
                $currentTotalCredit = 0;

                foreach ($lignesEcriture as $ligne) :
                    $montant = (float)$ligne['Montant'];
                    $numeroCompte = '';
                    $nomCompte = '';
                    foreach ($comptes as $compte) {
                        if ($compte['ID_Compte'] == $ligne['ID_Compte']) {
                            $numeroCompte = $compte['Numero_Compte'];
                            $nomCompte = $compte['Nom_Compte'];
                            break;
                        }
                    }

                    if ($ligne['Sens'] == 'D') {
                        $currentTotalDebit += $montant;
                    } else {
                        $currentTotalCredit += $montant;
                    }
                ?>
                    <tr>
                        <td><?= htmlspecialchars($numeroCompte) . '&nbsp;' . htmlspecialchars($nomCompte) ?></td>
                        <td><?= htmlspecialchars($ligne['Libelle_Ligne'] ?? '') ?></td>
                        <td class="text-right montant-debit">
                            <?= $ligne['Sens'] == 'D' ? number_format($montant, 2, ',', ' ') : '' ?>
                        </td>
                        <td class="text-right montant-credit">
                            <?= $ligne['Sens'] == 'C' ? number_format($montant, 2, ',', ' ') : '' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="2"><strong>Total</strong></td>
                    <td class="text-right montant-debit"><?= number_format($currentTotalDebit, 2, ',', ' ') ?></td>
                    <td class="text-right montant-credit"><?= number_format($currentTotalCredit, 2, ',', ' ') ?></td>
                </tr>
            </tbody>
        </table>

        <?php if ($montantTVA > 0) : ?>
            <div class="tva-info">
                <h2>D&eacutetails TVA</h2>
                <p>Montant Hors TVA (d&eacutebit&eacute) : <strong><?= number_format($montantHorsTVA, 2, ',', ' ') ?></strong></p>
                <p>Montant TVA (19.25%) : <strong><?= number_format($montantTVA, 2, ',', ' ') ?></strong></p>
                <p>Montant TTC (total d&eacutebit) : <strong><?= number_format($totalDebit, 2, ',', ' ') ?></strong></p>
            </div>
        <?php endif; ?>
		
		<div class="print-footer">
    <p>Imprim&eacute par: <strong><?= htmlspecialchars($printedBy) ?></strong> le <strong><?= htmlspecialchars($printTime) ?></strong></p>
</div>

        <div class="no-print" style="text-align: center; margin-top: 20mm;">
            <button onclick="window.print()" style="padding: 10px 20px; font-size: 14pt; cursor: pointer;">Imprimer cet extrait</button>
            <button onclick="window.close()" style="padding: 10px 20px; font-size: 14pt; cursor: pointer; margin-left: 10px;">Fermer l'aper&ccedilu</button>
        </div>
    </div>
    <script>
        // Automatically open print dialog on page load
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
<?php
ob_end_flush(); // Flush the output buffer
exit();
?>