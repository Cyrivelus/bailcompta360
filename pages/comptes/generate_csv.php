<?php
// fonctions/export_csv.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Africa/Douala');
require_once('../../fonctions/database.php');
require_once('../../fonctions/gestion_comptes.php');
require_once('../../fonctions/gestion_ecritures.php');
require_once('../../fonctions/gestion_factures.php'); // Assuming this function exists for invoice details

// Ensure PDO connection is established and UTF-8 configured
if (!isset($pdo) || !$pdo instanceof PDO) {
    // Log error and exit, or handle gracefully
    error_log("Erreur (export_csv.php - PDO non initialisé) : Connexion BD manquante.");
    exit("Erreur de connexion à la base de données.");
}

$compteId = filter_input(INPUT_GET, 'compte_id', FILTER_VALIDATE_INT);
$dateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : null;
$dateFin = isset($_GET['date_fin']) ? $_GET['date_fin'] : null;

if (!$compteId) {
    exit("ID de compte non spécifié.");
}

$compte = getCompteDetails($pdo, $compteId);
if (!$compte) {
    exit("Compte non trouvé.");
}

$lignesEcritures = getLignesEcrituresByCompte($pdo, $compteId, $dateDebut, $dateFin);
$soldeAnterieur = getSoldeAnterieur($pdo, $compteId, $dateDebut);
$soldeAnterieurValue = ($soldeAnterieur['total_credit'] ?? 0) - ($soldeAnterieur['total_debit'] ?? 0);

$debitCumul = 0;
$creditCumul = 0;
foreach ($lignesEcritures as $ligne) {
    $debitCumul += ($ligne['Sens'] == 'D' ? $ligne['Montant'] : 0);
    $creditCumul += ($ligne['Sens'] == 'C' ? $ligne['Montant'] : 0);
}
$currentSolde = $soldeAnterieurValue + $creditCumul - $debitCumul;

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="extrait_compte_' . urlencode($compte['Numero_Compte']) . '.csv"');

$output = fopen('php://output', 'w');

// --- CSV Header Information (Aesthetic part, similar to PDF header) ---
fputcsv($output, ["SCE-SOCIETE CAMEROUNAISE D'EQUIPEMENT"]);
fputcsv($output, ["EXTRAIT DE COMPTE : Compte N° " . ($compte['Numero_Compte'] ?? 'N/A') . " - " . ($compte['Nom_Compte'] ?? 'Nom inconnu')]);
fputcsv($output, []); // Empty line for spacing
fputcsv($output, ["Code Analytique : N/A"]);
fputcsv($output, ["PÉRIODE DÉBUT : " . ($dateDebut ?? 'N/A') . " ; PÉRIODE FIN : " . ($dateFin ?? 'N/A')]);
fputcsv($output, []); // Empty line for spacing

// --- Table Headers ---
fputcsv($output, ['DATE', 'PIÈCE', 'LIBELLÉ', 'JOURN', 'ANA', 'C.PART.', 'DEBIT', 'CRÉDIT', 'UTILISATEUR']);

// --- Solde Antérieur Row ---
fputcsv($output, [
    '', '', '', '', '',
    'SOLDE ANTÉRIEUR',
    number_format($soldeAnterieurValue, 2, ',', ''), // Use empty string for thousands separator
    '', '' // Empty cells for Credit and User
]);

// --- Data Rows ---
foreach ($lignesEcritures as $ligne) {
    $ecriture = getEcritureDetails($pdo, $ligne['ID_Ecriture']);

    $debit = $ligne['Sens'] == 'D' ? $ligne['Montant'] : 0;
    $credit = $ligne['Sens'] == 'C' ? $ligne['Montant'] : 0;

    $isFacture = false;
    $factureDetails = null;
    if (isset($ecriture['Description']) && strpos($ecriture['Description'], 'FACTURE') !== false) {
        preg_match('/FACTURE (\d+)/', $ecriture['Description'], $matches);
        if (isset($matches[1])) {
            $factureId = $matches[1];
            $factureDetails = getFactureDetails($pdo, $factureId);
            if ($factureDetails && $factureDetails['Statut_Facture'] == 'Payé') {
                $isFacture = true;
            }
        }
    }

    $sensOppose = ($ligne['Sens'] === 'D') ? 'C' : 'D';
    $counterpartQuery = "
        SELECT cc.Nom_Compte, cc.Numero_Compte
        FROM Lignes_Ecritures le
        JOIN Comptes_compta cc ON cc.ID_Compte = le.ID_Compte
        WHERE le.ID_Ecriture = :id_ecriture
            AND le.Sens = :sens_oppose
            AND le.ID_Compte != :id_compte_actuel
    ";

    if ($isFacture) {
        $counterpartQuery .= " AND cc.Numero_Compte LIKE '4%'";
    }
    
    $stmt = $pdo->prepare($counterpartQuery);
    $stmt->execute([
        ':id_ecriture' => $ligne['ID_Ecriture'],
        ':sens_oppose' => $sensOppose,
        ':id_compte_actuel' => $ligne['ID_Compte']
    ]);
    $contreparties = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $affichageContreparties = [];
    if ($isFacture) {
        foreach ($contreparties as $cp) {
            if (strpos($cp['Numero_Compte'], '4') === 0) {
                $affichageContreparties[] = $cp['Numero_Compte'] . ' - ' . $cp['Nom_Compte'];
            }
        }
    } else {
        $affichageContreparties = array_map(function($cp) {
            return $cp['Numero_Compte'] . ' - ' . $cp['Nom_Compte'];
        }, $contreparties);
    }
    $contrepartieString = implode('; ', $affichageContreparties); // Use semicolon for CSV to avoid conflicts with comma in names

    $debitAmount = $isFacture && $factureDetails ? $factureDetails['Montant_TTC'] : $debit;
    $creditAmount = $isFacture && $factureDetails ? $factureDetails['Montant_HT'] : $credit;

    fputcsv($output, [
        $ecriture['Date_Saisie'],
        $ligne['ID_Ecriture'],
        $ecriture['Description'],
        'N/A', // JOURNAL column (replace with actual value if available)
        'N/A', // ANA column (replace with actual value if available)
        $contrepartieString,
        number_format($debitAmount, 2, ',', ''),
        number_format($creditAmount, 2, ',', ''),
        ($ecriture['NomUtilisateur'] ?? 'N/A')
    ]);
}

// --- Summary Footer (similar to PDF footer) ---
fputcsv($output, []); // Empty line for spacing
fputcsv($output, ['PÉRIODE', '', '', '', '', '', number_format($debitCumul, 2, ',', ''), number_format($creditCumul, 2, ',', ''), '']);
fputcsv($output, ['CUMUL', '', '', '', '', '', number_format($debitCumul, 2, ',', ''), number_format($creditCumul, 2, ',', ''), '']);
fputcsv($output, ['SOLDE', '', '', '', '', '', number_format($currentSolde, 2, ',', ''), '', '']);

fputcsv($output, []); // Empty line for spacing
fputcsv($output, ["Imprimé par : " . ($_SESSION['nom_utilisateur'] ?? 'Utilisateur inconnu') . " le " . date('d/m/Y à H:i')]);

fclose($output);
exit;
?>