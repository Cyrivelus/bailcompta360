<?php
session_start();

// Authentification
if (!isset($_SESSION['utilisateur_id'], $_SESSION['role'])) {
    header('Location: ../../login.php');
    exit;
}

require_once '../../fonctions/database.php';
require_once 'fpdf/fpdf.php'; // Inclure la bibliothèque FPDF

// Fonctions de log
if (!function_exists('logApplicationError')) {
    function logApplicationError($msg) {
        error_log("Application Error (Export Report): " . $msg);
    }
}
if (!function_exists('logUserActivity')) {
    function logUserActivity($msg) {
        error_log("User Activity (Export Report): " . $msg);
    }
}

// Connexion PDO
if (!isset($pdo) || !$pdo instanceof PDO) {
    if (function_exists('getPdoConnection')) {
        $pdo = getPdoConnection();
    }
    if (!$pdo || !$pdo instanceof PDO) {
        logApplicationError('Connexion à la base de données manquante ou échouée lors de l\'exportation.');
        die('Erreur de configuration du serveur : impossible de se connecter à la base de données.');
    }
}

// Configuration de l'encodage
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

// Déclaration des constantes
const COMPTE_FOURNISSEUR_CONSOLIDE_NUMERO = '403200000000';
const COMPTE_FOURNISSEUR_CONSOLIDE_NOM = 'Fournisseurs (consolidé)';

// Récupération et validation des paramètres GET
$reportType = filter_input(INPUT_GET, 'report_type', FILTER_UNSAFE_RAW);
$format = filter_input(INPUT_GET, 'format', FILTER_UNSAFE_RAW);
$view = filter_input(INPUT_GET, 'view', FILTER_UNSAFE_RAW);

// Ajout de la validation pour les dates
$startDate = filter_input(INPUT_GET, 'start_date', FILTER_UNSAFE_RAW);
$endDate = filter_input(INPUT_GET, 'end_date', FILTER_UNSAFE_RAW);

$dateStartObj = null;
$dateEndObj = null;

if ($startDate) {
    $dateStartObj = DateTime::createFromFormat('Y-m-d', $startDate);
    if (!$dateStartObj || $dateStartObj->format('Y-m-d') !== $startDate) {
        die("Date de début invalide.");
    }
}

if ($endDate) {
    $dateEndObj = DateTime::createFromFormat('Y-m-d', $endDate);
    if (!$dateEndObj || $dateEndObj->format('Y-m-d') !== $endDate) {
        die("Date de fin invalide.");
    }
}

// Assurer que les dates sont formatées pour l'usage
$formattedStartDate = $dateStartObj ? $dateStartObj->format('Y-m-d') : null;
$formattedEndDate = $dateEndObj ? $dateEndObj->format('Y-m-d') : null;


if ($reportType === 'balance_general') {
    // Le code pour la balance générale est correct pour les dates
    $idCompteFournisseurConsolide = null;
    try {
        $stmt = $pdo->prepare("SELECT ID_Compte FROM Comptes_compta WHERE Numero_Compte = ?");
        $stmt->execute([COMPTE_FOURNISSEUR_CONSOLIDE_NUMERO]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $idCompteFournisseurConsolide = $result['ID_Compte'];
        } else {
            logApplicationError("Compte fournisseur consolidé " . COMPTE_FOURNISSEUR_CONSOLIDE_NUMERO . " non trouvé lors de l'exportation.");
            die("Erreur: Le compte fournisseur consolidé n'est pas configuré correctement.");
        }
    } catch (PDOException $e) {
        logApplicationError("Erreur PDO lors de la récupération du compte fournisseur consolidé pour l'exportation: " . $e->getMessage());
        die("Erreur serveur lors de la préparation de l'exportation.");
    }

    $selectedAccount = filter_input(INPUT_GET, 'id_compte', FILTER_SANITIZE_NUMBER_INT);

    $balanceEntries = [];
    $totalInitialDebit = 0;
    $totalInitialCredit = 0;
    $totalPeriodDebit = 0;
    $totalPeriodCredit = 0;
    $totalFinalDebit = 0;
    $totalFinalCredit = 0;

    try {
        $sql = "
            WITH CleanedMovements AS (
                SELECT
                    le.ID_Compte,
                    TRY_CONVERT(DATE, e.Date_Saisie) AS ConvertedDateSaisie,
                    le.Sens,
                    CAST(ISNULL(TRY_CAST(le.Montant AS DECIMAL(18, 2)), 0) AS DECIMAL(18, 2)) AS ConvertedMontant
                FROM
                    Lignes_Ecritures le
                JOIN
                    Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
            ),
            ConsolidatedMovements AS (
                SELECT
                    CASE
                        WHEN LEFT(cc.Numero_Compte, 3) = '403' THEN :consolidated_id_compte
                        ELSE cm.ID_Compte
                    END AS Grouped_ID_Compte,
                    CASE
                        WHEN LEFT(cc.Numero_Compte, 3) = '403' THEN '" . COMPTE_FOURNISSEUR_CONSOLIDE_NUMERO . "'
                        ELSE cc.Numero_Compte
                    END AS Grouped_Numero_Compte,
                    CASE
                        WHEN LEFT(cc.Numero_Compte, 3) = '403' THEN '" . COMPTE_FOURNISSEUR_CONSOLIDE_NOM . "'
                        ELSE cc.Nom_Compte
                    END AS Grouped_Nom_Compte,
                    cm.ConvertedDateSaisie,
                    cm.Sens,
                    cm.ConvertedMontant
                FROM
                    Comptes_compta cc
                JOIN
                    CleanedMovements cm ON cc.ID_Compte = cm.ID_Compte
            )
            SELECT
                cmc.Grouped_ID_Compte AS ID_Compte,
                cmc.Grouped_Numero_Compte AS Numero_Compte,
                cmc.Grouped_Nom_Compte AS Nom_Compte,
                SUM(CASE WHEN cmc.ConvertedDateSaisie < :start_initial AND cmc.Sens = 'D' THEN cmc.ConvertedMontant ELSE 0 END) AS SoldeInitialDebit,
                SUM(CASE WHEN cmc.ConvertedDateSaisie < :start_initial2 AND cmc.Sens = 'C' THEN cmc.ConvertedMontant ELSE 0 END) AS SoldeInitialCredit,
                SUM(CASE WHEN cmc.ConvertedDateSaisie BETWEEN :start_period AND :end_period AND cmc.Sens = 'D' THEN cmc.ConvertedMontant ELSE 0 END) AS MouvementDebit,
                SUM(CASE WHEN cmc.ConvertedDateSaisie BETWEEN :start_period2 AND :end_period2 AND cmc.Sens = 'C' THEN cmc.ConvertedMontant ELSE 0 END) AS MouvementCredit
            FROM
                ConsolidatedMovements cmc
            WHERE 1=1
        ";

        $params = [
            ':consolidated_id_compte' => $idCompteFournisseurConsolide,
            ':start_initial' => $formattedStartDate,
            ':start_initial2' => $formattedStartDate,
            ':start_period' => $formattedStartDate,
            ':end_period' => $formattedEndDate,
            ':start_period2' => $formattedStartDate,
            ':end_period2' => $formattedEndDate,
        ];

        if ($selectedAccount !== null && is_numeric($selectedAccount)) {
            $stmtCheckAccount = $pdo->prepare("SELECT Numero_Compte FROM Comptes_compta WHERE ID_Compte = ?");
            $stmtCheckAccount->execute([$selectedAccount]);
            $accountInfo = $stmtCheckAccount->fetch(PDO::FETCH_ASSOC);

            if ($accountInfo && strpos($accountInfo['Numero_Compte'], '403') === 0) {
                $sql .= " AND cmc.Grouped_ID_Compte = :selected_id_compte";
                $params[':selected_id_compte'] = $idCompteFournisseurConsolide;
            } else {
                $sql .= " AND cmc.Grouped_ID_Compte = :selected_id_compte";
                $params[':selected_id_compte'] = $selectedAccount;
            }
        }

        $sql .= " GROUP BY cmc.Grouped_ID_Compte, cmc.Grouped_Numero_Compte, cmc.Grouped_Nom_Compte ORDER BY cmc.Grouped_Numero_Compte ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rawBalanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $hasConsolidatedAccountEntry = false;
        foreach ($rawBalanceData as $row) {
            $nomCompte = htmlspecialchars_decode($row['Nom_Compte'], ENT_QUOTES);
            $nomCompte = mb_convert_encoding($nomCompte, 'UTF-8', 'UTF-8');
            $soldeInitialDebit = (float)($row['SoldeInitialDebit'] ?? 0);
            $soldeInitialCredit = (float)($row['SoldeInitialCredit'] ?? 0);
            $mouvementDebit = (float)($row['MouvementDebit'] ?? 0);
            $mouvementCredit = (float)($row['MouvementCredit'] ?? 0);
            $soldeFinalDebit = $soldeInitialDebit + $mouvementDebit;
            $soldeFinalCredit = $soldeInitialCredit + $mouvementCredit;

            if ($soldeFinalDebit > $soldeFinalCredit) {
                $soldeFinalDebit = $soldeFinalDebit - $soldeFinalCredit;
                $soldeFinalCredit = 0;
            } else {
                $soldeFinalCredit = $soldeFinalCredit - $soldeFinalDebit;
                $soldeFinalDebit = 0;
            }

            $balanceEntries[] = [
                'ID_Compte' => $row['ID_Compte'],
                'Numero_Compte' => $row['Numero_Compte'],
                'Nom_Compte' => $nomCompte,
                'SoldeInitialDebit' => $soldeInitialDebit,
                'SoldeInitialCredit' => $soldeInitialCredit,
                'MouvementDebit' => $mouvementDebit,
                'MouvementCredit' => $mouvementCredit,
                'SoldeFinalDebit' => $soldeFinalDebit,
                'SoldeFinalCredit' => $soldeFinalCredit
            ];

            if ($row['ID_Compte'] == $idCompteFournisseurConsolide) {
                $hasConsolidatedAccountEntry = true;
            }

            $totalInitialDebit += $soldeInitialDebit;
            $totalInitialCredit += $soldeInitialCredit;
            $totalPeriodDebit += $mouvementDebit;
            $totalPeriodCredit += $mouvementCredit;
            $totalFinalDebit += $soldeFinalDebit;
            $totalFinalCredit += $soldeFinalCredit;
        }

        if ((!$hasConsolidatedAccountEntry && ($selectedAccount === null || (int)$selectedAccount === (int)$idCompteFournisseurConsolide)) ||
            ($selectedAccount !== null && (int)$selectedAccount === (int)$idCompteFournisseurConsolide && empty($balanceEntries))) {
            $stmtConsolide = $pdo->prepare("SELECT ID_Compte, Numero_Compte, Nom_Compte FROM Comptes_compta WHERE ID_Compte = ?");
            $stmtConsolide->execute([$idCompteFournisseurConsolide]);
            $consolidatedAccountInfo = $stmtConsolide->fetch(PDO::FETCH_ASSOC);

            if ($consolidatedAccountInfo) {
                $balanceEntries[] = [
                    'ID_Compte' => $consolidatedAccountInfo['ID_Compte'],
                    'Numero_Compte' => $consolidatedAccountInfo['Numero_Compte'],
                    'Nom_Compte' => htmlspecialchars_decode($consolidatedAccountInfo['Nom_Compte'], ENT_QUOTES),
                    'SoldeInitialDebit' => 0,
                    'SoldeInitialCredit' => 0,
                    'MouvementDebit' => 0,
                    'MouvementCredit' => 0,
                    'SoldeFinalDebit' => 0,
                    'SoldeFinalCredit' => 0
                ];
            }
        }

        usort($balanceEntries, function($a, $b) {
            return strcmp($a['Numero_Compte'], $b['Numero_Compte']);
        });

        logUserActivity("Données de la Balance Générale extraites pour l'exportation par l'utilisateur ID: " . ($_SESSION['utilisateur_id'] ?? 'N/A') . " pour la période du {$startDate} au {$endDate}.");

    } catch (PDOException $e) {
        logApplicationError("Erreur PDO lors de l'extraction de la Balance Générale pour l'exportation: " . $e->getMessage());
        die("Erreur serveur lors de la récupération des données.");
    }

    if ($format === 'csv') {
        exportBalanceGeneralCsv($balanceEntries, $totalInitialDebit, $totalInitialCredit, $totalPeriodDebit, $totalPeriodCredit, $totalFinalDebit, $totalFinalCredit, $startDate, $endDate);
    } elseif ($format === 'pdf') {
        exportBalanceGeneralPdf($balanceEntries, $totalInitialDebit, $totalInitialCredit, $totalPeriodDebit, $totalPeriodCredit, $totalFinalDebit, $totalFinalCredit, $startDate, $endDate, $view);
    } else {
        die("Format d'exportation non supporté.");
    }
} elseif ($reportType === 'extrait_compte') {
    $numeroCompte = filter_input(INPUT_GET, 'numero_compte', FILTER_UNSAFE_RAW);
    if (!$numeroCompte) {
        $numeroCompte = filter_input(INPUT_GET, 'id_compte', FILTER_UNSAFE_RAW);
    }

    if (!$numeroCompte) {
        die("Erreur: Numéro de compte non spécifié pour l'exportation.");
    }

    $queryCompteInfo = "SELECT Nom_Compte FROM Comptes_compta WHERE Numero_Compte = :numeroCompte";
    $stmtCompteInfo = $pdo->prepare($queryCompteInfo);
    $stmtCompteInfo->execute([':numeroCompte' => $numeroCompte]);
    $compteInfo = $stmtCompteInfo->fetch(PDO::FETCH_ASSOC);
    $nomCompte = $compteInfo['Nom_Compte'] ?? 'Compte non trouvé';

    $query = "SELECT Dte, Lib, Ctr, Deb, Cre, NumeroAgenceSCE, NomUtilisateur, Id, Jal FROM ECR_DEF WHERE Cpt = :numeroCompte";
    $params = [':numeroCompte' => $numeroCompte];

    if ($formattedStartDate) {
        $query .= " AND Dte >= :dateDebut";
        $params[':dateDebut'] = $formattedStartDate;
    }
    if ($formattedEndDate) {
        $query .= " AND Dte <= :dateFin";
        $params[':dateFin'] = $formattedEndDate;
    }
    $query .= " ORDER BY Dte, Id ASC";

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $lignesEcritures = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $soldeAnterieurValue = 0;
        $querySoldeAnterieur = "SELECT SUM(Cre) - SUM(Deb) as solde FROM ECR_DEF WHERE Cpt = :numeroCompte AND Dte < :dateDebut";
        $stmtSoldeAnterieur = $pdo->prepare($querySoldeAnterieur);
        $stmtSoldeAnterieur->execute([':numeroCompte' => $numeroCompte, ':dateDebut' => $formattedStartDate ?? '1900-01-01']);
        $result = $stmtSoldeAnterieur->fetch(PDO::FETCH_ASSOC);
        if ($result && isset($result['solde'])) {
            $soldeAnterieurValue = (float) $result['solde'];
        }

        $debitCumul = 0;
        $creditCumul = 0;
        $currentSolde = $soldeAnterieurValue;
        $lignesPourExport = [];

        foreach ($lignesEcritures as $ligne) {
            $debit = (float)($ligne['Deb'] ?? 0);
            $credit = (float)($ligne['Cre'] ?? 0);
            $currentSolde += $credit - $debit;
            $debitCumul += $debit;
            $creditCumul += $credit;
            $lignesPourExport[] = array_merge($ligne, ['solde_cumul' => $currentSolde]);
        }
        $finalDisplayedBalance = $currentSolde;

        logUserActivity("Données d'extrait de compte extraites pour l'exportation par l'utilisateur ID: " . ($_SESSION['utilisateur_id'] ?? 'N/A') . " pour le compte {$numeroCompte} et la période du {$startDate} au {$endDate}.");

    } catch (PDOException $e) {
        logApplicationError("Erreur PDO lors de l'extraction de l'extrait de compte pour l'exportation: " . $e->getMessage());
        die("Erreur serveur lors de la récupération des données.");
    }

    if ($format === 'csv') {
        exportExtraitCompteCsv($lignesPourExport, $soldeAnterieurValue, $debitCumul, $creditCumul, $finalDisplayedBalance, $numeroCompte, $nomCompte, $startDate, $endDate);
    } elseif ($format === 'pdf') {
        exportExtraitComptePdf($lignesPourExport, $soldeAnterieurValue, $debitCumul, $creditCumul, $finalDisplayedBalance, $numeroCompte, $nomCompte, $startDate, $endDate, $view);
    } else {
        die("Format d'exportation non supporté.");
    }
} else {
    die("Type de rapport non spécifié.");
}

// Fonction d'exportation CSV pour la balance générale
function exportBalanceGeneralCsv($entries, $totalInitialDebit, $totalInitialCredit, $totalPeriodDebit, $totalPeriodCredit, $totalFinalDebit, $totalFinalCredit, $startDate, $endDate) {
    $filename = "balance_generale_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, ["Balance Generale"]);
    $displayStartDate = $startDate ? date('d/m/Y', strtotime($startDate)) : 'début';
    $displayEndDate = $endDate ? date('d/m/Y', strtotime($endDate)) : 'fin';
    fputcsv($output, ["Periode du " . $displayStartDate . " au " . $displayEndDate]);
    fputcsv($output, []);
    fputcsv($output, ['Numero Compte', 'Nom du Compte', 'Soldes d\'ouverture Debit', 'Soldes d\'ouverture Credit', 'Mouvements de la periode Debit', 'Mouvements de la periode Credit', 'Soldes de cloture Debit', 'Soldes de cloture Credit'], ';', '"');
    
    foreach ($entries as $row) {
        fputcsv($output, [
            $row['Numero_Compte'],
            $row['Nom_Compte'],
            number_format($row['SoldeInitialDebit'], 2, ',', ' '),
            number_format($row['SoldeInitialCredit'], 2, ',', ' '),
            number_format($row['MouvementDebit'], 2, ',', ' '),
            number_format($row['MouvementCredit'], 2, ',', ' '),
            number_format($row['SoldeFinalDebit'], 2, ',', ' '),
            number_format($row['SoldeFinalCredit'], 2, ',', ' ')
        ], ';', '"');
    }

    fputcsv($output, [
        'TOTAUX', '',
        number_format($totalInitialDebit, 2, ',', ' '),
        number_format($totalInitialCredit, 2, ',', ' '),
        number_format($totalPeriodDebit, 2, ',', ' '),
        number_format($totalPeriodCredit, 2, ',', ' '),
        number_format($totalFinalDebit, 2, ',', ' '),
        number_format($totalFinalCredit, 2, ',', ' ')
    ], ';', '"');
    fclose($output);
    exit;
}

// Fonction d'exportation PDF pour la balance générale
function exportBalanceGeneralPdf($entries, $totalInitialDebit, $totalInitialCredit, $totalPeriodDebit, $totalPeriodCredit, $totalFinalDebit, $totalFinalCredit, $startDate, $endDate, $view) {
    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTitle('Balance Generale', true);
    $pdf->SetAuthor('Systeme de Gestion', true);
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, iconv('UTF-8', 'windows-1252', 'Balance Generale'), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $displayStartDate = $startDate ? date('d/m/Y', strtotime($startDate)) : 'début';
    $displayEndDate = $endDate ? date('d/m/Y', strtotime($endDate)) : 'fin';
    $pdf->Cell(0, 10, iconv('UTF-8', 'windows-1252', 'Période du ' . $displayStartDate . ' au ' . $displayEndDate), 0, 1, 'C');
    $pdf->Ln(10);
    $w = array(20, 50, 25, 25, 25, 25, 25, 25);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($w[0], 10, iconv('UTF-8', 'windows-1252', 'N° Compte'), 1, 0, 'C');
    $pdf->Cell($w[1], 10, 'Nom du Compte', 1, 0, 'C');
    $pdf->Cell($w[2]+$w[3], 5, iconv('UTF-8', 'windows-1252', 'Soldes d\'ouverture'), 1, 0, 'C');
    $pdf->Cell($w[4]+$w[5], 5, iconv('UTF-8', 'windows-1252', 'Mouvements periode'), 1, 0, 'C');
    $pdf->Cell($w[6]+$w[7], 5, iconv('UTF-8', 'windows-1252', 'Soldes cloture'), 1, 1, 'C');
    $pdf->SetX($pdf->GetX() + $w[0] + $w[1]);
    $pdf->Cell($w[2], 5, iconv('UTF-8', 'windows-1252', 'Débit'), 1, 0, 'C');
    $pdf->Cell($w[3], 5, iconv('UTF-8', 'windows-1252', 'Crédit'), 1, 0, 'C');
    $pdf->Cell($w[4], 5, iconv('UTF-8', 'windows-1252', 'Débit'), 1, 0, 'C');
    $pdf->Cell($w[5], 5, iconv('UTF-8', 'windows-1252', 'Crédit'), 1, 0, 'C');
    $pdf->Cell($w[6], 5, iconv('UTF-8', 'windows-1252', 'Débit'), 1, 0, 'C');
    $pdf->Cell($w[7], 5, iconv('UTF-8', 'windows-1252', 'Crédit'), 1, 1, 'C');
    $pdf->SetFont('Arial', '', 8);
    foreach ($entries as $row) {
        $nomCompte = iconv('UTF-8', 'windows-1252', $row['Nom_Compte']);
        $pdf->Cell($w[0], 6, $row['Numero_Compte'], 1, 0, 'L');
        $pdf->Cell($w[1], 6, substr($nomCompte, 0, 30), 1, 0, 'L');
        $pdf->Cell($w[2], 6, number_format($row['SoldeInitialDebit'], 2, ',', ' '), 1, 0, 'R');
        $pdf->Cell($w[3], 6, number_format($row['SoldeInitialCredit'], 2, ',', ' '), 1, 0, 'R');
        $pdf->Cell($w[4], 6, number_format($row['MouvementDebit'], 2, ',', ' '), 1, 0, 'R');
        $pdf->Cell($w[5], 6, number_format($row['MouvementCredit'], 2, ',', ' '), 1, 0, 'R');
        $pdf->Cell($w[6], 6, number_format($row['SoldeFinalDebit'], 2, ',', ' '), 1, 0, 'R');
        $pdf->Cell($w[7], 6, number_format($row['SoldeFinalCredit'], 2, ',', ' '), 1, 1, 'R');
    }
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell($w[0]+$w[1], 6, 'TOTAUX', 1, 0, 'R');
    $pdf->Cell($w[2], 6, number_format($totalInitialDebit, 2, ',', ' '), 1, 0, 'R');
    $pdf->Cell($w[3], 6, number_format($totalInitialCredit, 2, ',', ' '), 1, 0, 'R');
    $pdf->Cell($w[4], 6, number_format($totalPeriodDebit, 2, ',', ' '), 1, 0, 'R');
    $pdf->Cell($w[5], 6, number_format($totalPeriodCredit, 2, ',', ' '), 1, 0, 'R');
    $pdf->Cell($w[6], 6, number_format($totalFinalDebit, 2, ',', ' '), 1, 0, 'R');
    $pdf->Cell($w[7], 6, number_format($totalFinalCredit, 2, ',', ' '), 1, 1, 'R');
    $filename = "balance_generale_" . date('Ymd_His') . ".pdf";
    if ($view === 'preview') {
        $pdf->Output('I', $filename);
    } else {
        $pdf->Output('D', $filename);
    }
    exit;
}

// Fonction d'exportation CSV pour l'extrait de compte
function exportExtraitCompteCsv($lignes, $soldeAnterieur, $debitCumul, $creditCumul, $soldeFinal, $numeroCompte, $nomCompte, $startDate, $endDate) {
    $filename = "extrait_compte_" . $numeroCompte . "_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, ["Extrait de Compte"], ';', '"');
    fputcsv($output, ["Compte: " . $numeroCompte . " - " . $nomCompte], ';', '"');
    $displayStartDate = $startDate ? date('d/m/Y', strtotime($startDate)) : 'début';
    $displayEndDate = $endDate ? date('d/m/Y', strtotime($endDate)) : 'fin';
    fputcsv($output, ["Periode du " . $displayStartDate . " au " . $displayEndDate], ';', '"');
    fputcsv($output, ["Solde Anterieur: " . number_format($soldeAnterieur, 2, ',', ' ')], ';', '"');
    fputcsv($output, []);
    fputcsv($output, ['ID Ligne', 'Jal', 'Date', 'Libelle', 'Contrepartie', 'Debit', 'Credit', 'Solde', 'Agence', 'Utilisateur'], ';', '"');
    foreach ($lignes as $ligne) {
        $dateLigne = $ligne['Dte'] ? date('d/m/Y', strtotime($ligne['Dte'])) : 'N/A';
        fputcsv($output, [
            $ligne['Id'],
            $ligne['Jal'] ?? 'N/A',
            $dateLigne,
            $ligne['Lib'] ?? 'N/A',
            $ligne['Ctr'] ?? 'N/A',
            number_format($ligne['Deb'] ?? 0, 2, ',', ' '),
            number_format($ligne['Cre'] ?? 0, 2, ',', ' '),
            number_format($ligne['solde_cumul'], 2, ',', ' '),
            $ligne['NumeroAgenceSCE'] ?? 'N/A',
            $ligne['NomUtilisateur'] ?? 'N/A'
        ], ';', '"');
    }
    fputcsv($output, []);
    fputcsv($output, ['Totaux periode', '', '', '', '', number_format($debitCumul, 2, ',', ' '), number_format($creditCumul, 2, ',', ' ')], ';', '"');
    fputcsv($output, ['Solde final', '', '', '', '', '', '', number_format($soldeFinal, 2, ',', ' ')], ';', '"');
    fclose($output);
    exit;
}

// Fonction d'exportation PDF pour l'extrait de compte
// Fonction d'exportation PDF pour l'extrait de compte
function exportExtraitComptePdf($lignes, $soldeAnterieur, $debitCumul, $creditCumul, $soldeFinal, $numeroCompte, $nomCompte, $startDate, $endDate, $view) {
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();

    // Logo en haut à gauche
    if (file_exists(__DIR__ . '/logo_bailcompta.PNG')) {
        $pdf->Image(__DIR__ . '/logo_bailcompta.PNG', 10, 8, 25); 
    }

    // Titre centré
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, iconv('UTF-8', 'windows-1252', "Extrait de Compte"), 0, 1, 'C');

    // Sous-titre
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, iconv('UTF-8', 'windows-1252', "Compte: " . $numeroCompte . " - " . $nomCompte), 0, 1, 'C');

    $displayStartDate = $startDate ? date('d/m/Y', strtotime($startDate)) : 'début';
    $displayEndDate = $endDate ? date('d/m/Y', strtotime($endDate)) : 'fin';
    $pdf->Cell(0, 8, iconv('UTF-8', 'windows-1252', "Période du " . $displayStartDate . " au " . $displayEndDate), 0, 1, 'C');

    $pdf->Ln(5);

    // Solde antérieur
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, iconv('UTF-8', 'windows-1252', "Solde Antérieur: " . number_format($soldeAnterieur, 2, ',', ' ') . " XAF"), 0, 1, 'R');
    $pdf->Ln(5);

    // Largeurs ajustées pour éviter chevauchement
    $w = [15, 10, 20, 60, 35, 20, 20, 25];

    // En-têtes
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell($w[0], 7, 'ID', 1, 0, 'C');
    $pdf->Cell($w[1], 7, 'Jal', 1, 0, 'C');
    $pdf->Cell($w[2], 7, 'Date', 1, 0, 'C');
    $pdf->Cell($w[3], 7, 'Libelle', 1, 0, 'C');
    $pdf->Cell($w[4], 7, 'Contrepartie', 1, 0, 'C');
    $pdf->Cell($w[5], 7, 'Debit (XAF)', 1, 0, 'C');
    $pdf->Cell($w[6], 7, 'Credit (XAF)', 1, 0, 'C');
    $pdf->Cell($w[7], 7, 'Solde', 1, 1, 'C');

    // Contenu
    $pdf->SetFont('Arial', '', 8);
    foreach ($lignes as $ligne) {
        $dateLigne = $ligne['Dte'] ? date('d/m/Y', strtotime($ligne['Dte'])) : 'N/A';
        $pdf->Cell($w[0], 6, $ligne['Id'], 1, 0, 'L');
        $pdf->Cell($w[1], 6, $ligne['Jal'] ?? 'N/A', 1, 0, 'L');
        $pdf->Cell($w[2], 6, $dateLigne, 1, 0, 'L');

        // Libellé adaptatif (MultiCell si trop long)
        $x = $pdf->GetX(); 
        $y = $pdf->GetY();
        $pdf->MultiCell($w[3], 6, iconv('UTF-8', 'windows-1252', $ligne['Lib'] ?? 'N/A'), 1, 'L');
        $pdf->SetXY($x + $w[3], $y);

        $pdf->Cell($w[4], 6, iconv('UTF-8', 'windows-1252', $ligne['Ctr'] ?? 'N/A'), 1, 0, 'L');
        $pdf->Cell($w[5], 6, number_format($ligne['Deb'] ?? 0, 2, ',', ' '), 1, 0, 'R');
        $pdf->Cell($w[6], 6, number_format($ligne['Cre'] ?? 0, 2, ',', ' '), 1, 0, 'R');
        $pdf->Cell($w[7], 6, number_format($ligne['solde_cumul'], 2, ',', ' '), 1, 1, 'R');
    }

    // Totaux
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($w[0] + $w[1] + $w[2] + $w[3] + $w[4], 6, 'Totaux periode', 1, 0, 'R');
    $pdf->Cell($w[5], 6, number_format($debitCumul, 2, ',', ' '), 1, 0, 'R');
    $pdf->Cell($w[6], 6, number_format($creditCumul, 2, ',', ' '), 1, 0, 'R');
    $pdf->Cell($w[7], 6, '', 1, 1, 'R');

    $pdf->Cell($w[0] + $w[1] + $w[2] + $w[3] + $w[4] + $w[5] + $w[6], 6, 'Solde final', 1, 0, 'R');
    $pdf->Cell($w[7], 6, number_format($soldeFinal, 2, ',', ' '), 1, 1, 'R');

    // Footer avec utilisateur + date impression
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 8);
    $utilisateur = $_SESSION['nom_utilisateur'] ?? 'Inconnu';
    $dateImpression = date('d/m/Y H:i:s');
    $pdf->Cell(0, 6, "Imprime par: $utilisateur le $dateImpression", 0, 1, 'R');

    // Aperçu ou téléchargement
    $filename = "extrait_compte_" . $numeroCompte . "_" . date('Ymd_His') . ".pdf";
    if ($view === 'preview') {
        $pdf->Output('I', $filename); // Aperçu
    } else {
        $pdf->Output('D', $filename); // Téléchargement
    }
    exit;
}

?>