<?php
// pages/ecritures/export_excel.php

session_start();
ob_start();

require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_ecritures.php';
require_once '../../fonctions/gestion_comptes.php';


const TVA_RATE = 0.1925; // Taux de TVA 19.25%

// Validation de l'ID d'�criture
$idEcriture = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$idEcriture) {
    die("ID d'�criture non valide.");
}

// R�cup�ration des donn�es
$ecriture = getEcriture($pdo, $idEcriture);
if (!$ecriture) {
    die("�criture non trouv�e.");
}

$lignesEcriture = getLignesEcriture($pdo, $idEcriture);
$comptes = getListeComptes($pdo);

// Formatage des donn�es
$dateSaisie = date('d/m/Y', strtotime($ecriture['Date_Saisie'] ?? 'now'));
$description = htmlspecialchars($ecriture['Description'] ?? '');
$numeroPiece = htmlspecialchars($ecriture['Numero_Piece'] ?? '');
$journal = htmlspecialchars($ecriture['Cde'] ?? '');

// Informations d'audit
$printTime = date('d/m/Y H:i:s');
$printedBy = htmlspecialchars($_SESSION['nom_utilisateur'] ?? 'Utilisateur inconnu');

// Cr�ation du fichier CSV avec am�liorations
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="extrait_compte_' . $numeroPiece . '_' . date('Ymd-His') . '.csv"');

$output = fopen("php://output", "w");

// Ajout BOM pour Excel
fwrite($output, "\xEF\xBB\xBF");

// --- En-t�te du document ---
fputcsv($output, ["EXTRACTION COMPTABLE - " . strtoupper($journal)], ';');
fputcsv($output, ["Imprim� le " . $printTime . " par " . $printedBy], ';');
fputcsv($output, [""], ';'); // Ligne vide

// --- Section Informations G�n�rales ---
fputcsv($output, ["INFORMATIONS GENERALES"], ';');
fputcsv($output, ["Num�ro pi�ce", "Journal", "Date saisie", "Description"], ';');
fputcsv($output, [$numeroPiece, $journal, $dateSaisie, $description], ';');
fputcsv($output, [""], ';'); // Ligne vide

// --- Section Lignes d'�criture ---
fputcsv($output, ["LIGNES D'ECRITURE"], ';');
fputcsv($output, ["Compte", "Libell� compte", "Libell� ligne", "D�bit", "Cr�dit"], ';');

$totalDebit = 0;
$totalCredit = 0;
$montantTVA = 0;
$montantHorsTVA = 0;

foreach ($lignesEcriture as $ligne) {
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

    $debit = $ligne['Sens'] == 'D' ? number_format($montant, 2, ',', ' ') : '';
    $credit = $ligne['Sens'] == 'C' ? number_format($montant, 2, ',', ' ') : '';
    
    if ($ligne['Sens'] == 'D') {
        $totalDebit += $montant;
        if (strpos($numeroCompte, '445') === 0) {
            $montantTVA += $montant;
        } else {
            $montantHorsTVA += $montant;
        }
    } else {
        $totalCredit += $montant;
    }

    fputcsv($output, [
        $numeroCompte,
        $nomCompte,
        htmlspecialchars($ligne['Libelle_Ligne'] ?? ''),
        $debit,
        $credit
    ], ';');
}

// Ligne de totaux
fputcsv($output, ["TOTAL", "", "", 
    number_format($totalDebit, 2, ',', ' '), 
    number_format($totalCredit, 2, ',', ' ')
], ';');
fputcsv($output, [""], ';'); // Ligne vide

// --- Section TVA ---
if ($montantTVA > 0) {
    fputcsv($output, ["CALCUL DE LA TVA"], ';');
    fputcsv($output, ["Montant HT", "Taux TVA", "Montant TVA", "Montant TTC"], ';');
    fputcsv($output, [
        number_format($montantHorsTVA, 2, ',', ' '),
        "19,25%",
        number_format($montantTVA, 2, ',', ' '),
        number_format($totalDebit, 2, ',', ' ')
    ], ';');
    fputcsv($output, [""], ';'); // Ligne vide
}

// --- Pied de page ---
fputcsv($output, ["DOCUMENT GENERE AUTOMATIQUEMENT - NE PAS MODIFIER MANUELLEMENT"], ';');
fputcsv($output, ["Extraction valide � la date du " . $printTime], ';');

fclose($output);
ob_end_flush();
exit();
?>