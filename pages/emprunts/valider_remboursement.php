<?php
// pages/emprunts/valider_remboursement.php

// Script pour valider une �ch�ance de remboursement et cr�er l'�criture comptable associ�e.

// --- Initialisation ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// --- Inclusions ---
require_once '../../fonctions/database.php';

// --- V�rification des param�tres GET ---
if (!isset($_GET['id_echeance']) || !is_numeric($_GET['id_echeance'])) {
    header("Location: index.php?error=" . urlencode("ID d'�ch�ance non valide ou manquant pour la validation."));
    exit;
}

$idEcheance = (int)$_GET['id_echeance'];
$idEmpruntFallback = isset($_GET['id_emprunt_fallback']) ? (int)$_GET['id_emprunt_fallback'] : null;
$messageErreur = null;
$messageSucces = null;
$idEmpruntReel = null;

// V�rification de la connexion PDO
if (!isset($pdo) || !$pdo instanceof PDO) {
    $messageErreur = "La connexion à la base de donnees n'a pas ete correctement initialisée.";
    error_log("Erreur (valider_remboursement.php - PDO non initialisé) : " . $messageErreur);
    $redirectUrl = $idEmpruntFallback ? "details.php?id=" . $idEmpruntFallback : "index.php";
    header("Location: " . $redirectUrl . "&error=" . urlencode($messageErreur));
    exit;
}

// --- D�but de la transaction ---
$pdo->beginTransaction();

try {
    // 1. R�cup�rer les d�tails de l'�ch�ance
    $stmtEcheance = $pdo->prepare("SELECT * FROM Echeances_Amortissement WHERE ID_Echeance = :id_echeance");
    $stmtEcheance->bindParam(':id_echeance', $idEcheance, PDO::PARAM_INT);
    $stmtEcheance->execute();
    $echeance = $stmtEcheance->fetch(PDO::FETCH_ASSOC);

    if (!$echeance) {
        throw new Exception("echeance de remboursement ID " . $idEcheance . " non trouvée.");
    }

    $idEmpruntReel = (int)$echeance['ID_Emprunt'];

    if (!empty($echeance['ID_Ecriture_Comptable'])) {
        throw new Exception("Cette echeance (ID: " . $idEcheance . ") a deja ete comptabilisée (ecriture ID: " . $echeance['ID_Ecriture_Comptable'] . ").");
    }

    // 2. R�cup�rer les d�tails de l'emprunt associ�
    $stmtEmprunt = $pdo->prepare("SELECT * FROM Emprunts_Bancaires WHERE ID_Emprunt = :id_emprunt");
    $stmtEmprunt->bindParam(':id_emprunt', $idEmpruntReel, PDO::PARAM_INT);
    $stmtEmprunt->execute();
    $emprunt = $stmtEmprunt->fetch(PDO::FETCH_ASSOC);

    if (!$emprunt) {
        throw new Exception("Emprunt (ID: " . $idEmpruntReel . ") associé à l'échéance non trouvé.");
    }

    // --- D�finir les comptes comptables ---
    $numeroCompteEmprunt = '164000';
    $numeroCompteInteret = '661000';
    $numeroCompteTaxes = '631000';
    $numeroCompteBanque = '512000';
    $codeJournalComptable = 512; // Code journal direct (entier)

    // --- Rechercher les ID_Compte correspondants ---
    $stmtFindCompte = $pdo->prepare("SELECT ID_Compte FROM Comptes_compta WHERE Numero_Compte = :numero_compte");

    $comptes = [
        'emprunt' => $numeroCompteEmprunt,
        'interet' => $numeroCompteInteret,
        'taxes' => $numeroCompteTaxes,
        'banque' => $numeroCompteBanque
    ];

    $compteIds = [];
    foreach ($comptes as $type => $numero) {
        $stmtFindCompte->bindParam(':numero_compte', $numero, PDO::PARAM_STR);
        $stmtFindCompte->execute();
        $id = $stmtFindCompte->fetchColumn();

        if ($id === false) {
            throw new Exception("Compte Numero '" . $numero . "' non trouvé dans Comptes_compta.");
        }
        $compteIds[$type] = $id;
    }

    // 3. Pr�parer les montants de l'�ch�ance
    $amortissement = (float)($echeance['Amortissement'] ?? 0);
    $interetSP = (float)($echeance['Interet_SP'] ?? 0);
    $taxesInteretSP = (float)($echeance['Taxes_Interet_SP'] ?? 0);
    $montantTotalEcheance = $amortissement + $interetSP + $taxesInteretSP;

    if ($montantTotalEcheance <= 0) {
        throw new Exception("Le montant total de l'echeance est nul ou negatif. Comptabilisation impossible.");
    }

    // 4. Cr�er l'�criture comptable
    $dateEcheanceFormatSQL = $echeance['Date_Echeance'];
    $numeroEcheance = $echeance['Numero_Echeance'];
    $numeroPret = $emprunt['Numero_Pret'] ?? $idEmpruntReel;
    $banqueEmprunt = $emprunt['Banque'] ?? 'N/A';
    $nomUtilisateurSession = $_SESSION['utilisateur_nom'] ?? ($_SESSION['nom'] ?? 'SYSTEM_VALIDATION');

    $descriptionEcriture = "Remb. Emprunt " . $numeroPret . " (" . $banqueEmprunt . ") ech. " . $numeroEcheance;
    $numeroPieceEcriture = "REM-" . $idEmpruntReel . "-" . $numeroEcheance;
    $moisComptableEcriture = date('Y-m', strtotime($dateEcheanceFormatSQL));
    $dateSaisieEcriture = date('Y-m-d H:i:s');

    // Insertion dans Ecritures avec code journal direct
    $sqlInsertEcriture = "INSERT INTO Ecritures
                          (Date_Saisie, Description, Montant_Total, Cde, Numero_Piece, NomUtilisateur, Mois)
                          VALUES (:date_saisie, :description, :montant_total, :cde, :numero_piece, :nom_utilisateur, :mois)";

    $stmtInsertEcriture = $pdo->prepare($sqlInsertEcriture);
    $stmtInsertEcriture->bindParam(':date_saisie', $dateSaisieEcriture);
    $stmtInsertEcriture->bindParam(':description', $descriptionEcriture, PDO::PARAM_STR);
    $stmtInsertEcriture->bindParam(':montant_total', $montantTotalEcheance);
    $stmtInsertEcriture->bindParam(':cde', $codeJournalComptable, PDO::PARAM_INT);
    $stmtInsertEcriture->bindParam(':numero_piece', $numeroPieceEcriture, PDO::PARAM_STR);
    $stmtInsertEcriture->bindParam(':nom_utilisateur', $nomUtilisateurSession, PDO::PARAM_STR);
    $stmtInsertEcriture->bindParam(':mois', $moisComptableEcriture, PDO::PARAM_STR);
    $stmtInsertEcriture->execute();

    // R�cup�ration de l'ID de l'�criture
    $idEcritureNouvelle = $pdo->lastInsertId();
    if ($idEcritureNouvelle === false || $idEcritureNouvelle === '0' || empty($idEcritureNouvelle)) {
        try {
            $stmtScopeId = $pdo->query("SELECT SCOPE_IDENTITY()");
            $idEcritureNouvelle = $stmtScopeId ? $stmtScopeId->fetchColumn() : null;
        } catch (PDOException $e) {
            error_log("Erreur lors de la recuperation de l'ID avec SCOPE_IDENTITY() : " . $e->getMessage());
            $idEcritureNouvelle = null;
        }
        
        if (empty($idEcritureNouvelle)) {
            throw new Exception("Erreur lors de la recuperation de l'ID de la nouvelle ecriture comptable.");
        }
    }

    // 5. Cr�er les lignes de l'�criture
    $sqlInsertLigne = "INSERT INTO Lignes_Ecritures
                       (ID_Ecriture, ID_Compte, Libelle_Ligne, Montant, Sens)
                       VALUES (:id_ecriture, :id_compte, :libelle_ligne, :montant, :sens)";

    $stmtInsertLigne = $pdo->prepare($sqlInsertLigne);

    // Lignes de d�bit
    $lignesDebit = [
        ['compte' => $compteIds['emprunt'], 'montant' => $amortissement, 'libelle' => "Remb. Capital ech. " . $numeroEcheance],
        ['compte' => $compteIds['interet'], 'montant' => $interetSP, 'libelle' => "Interets ech. " . $numeroEcheance],
        ['compte' => $compteIds['taxes'], 'montant' => $taxesInteretSP, 'libelle' => "Taxes s/ Interets ech. " . $numeroEcheance]
    ];

    foreach ($lignesDebit as $ligne) {
        if ($ligne['montant'] > 0) {
            $libelleLigne = $ligne['libelle'] . " Emprunt " . $numeroPret;
            $stmtInsertLigne->execute([
                ':id_ecriture' => $idEcritureNouvelle,
                ':id_compte' => $ligne['compte'],
                ':libelle_ligne' => $libelleLigne,
                ':montant' => $ligne['montant'],
                ':sens' => 'D'
            ]);
        }
    }

    // Ligne de cr�dit (Banque)
    $libelleBanque = "Paiement ech. " . $numeroEcheance . " Emprunt " . $numeroPret . " (" . $banqueEmprunt . ")";
    $stmtInsertLigne->execute([
        ':id_ecriture' => $idEcritureNouvelle,
        ':id_compte' => $compteIds['banque'],
        ':libelle_ligne' => $libelleBanque,
        ':montant' => $montantTotalEcheance,
        ':sens' => 'C'
    ]);

    // 6. Mettre � jour l'�ch�ance avec l'ID de l'�criture
    $stmtUpdateEcheance = $pdo->prepare("UPDATE Echeances_Amortissement
                                         SET ID_Ecriture_Comptable = :id_ecriture_comptable
                                         WHERE ID_Echeance = :id_echeance_update");
    $stmtUpdateEcheance->execute([
        ':id_ecriture_comptable' => $idEcritureNouvelle,
        ':id_echeance_update' => $idEcheance
    ]);

    // 7. Valider la transaction
    $pdo->commit();

    $messageSucces = "Remboursement (ech. " . htmlspecialchars($numeroEcheance) . ", Emprunt " . htmlspecialchars($numeroPret) . ") valide et comptabilisé. ecriture ID: " . htmlspecialchars($idEcritureNouvelle) . ".";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $messageErreur = "ERREUR : " . $e->getMessage();
    error_log("Erreur (valider_remboursement.php) : " . $e->getMessage());
    if (!$idEmpruntReel && $idEmpruntFallback) {
        $idEmpruntReel = $idEmpruntFallback;
    }
}

// --- Redirection ---
$redirectUrl = $idEmpruntReel ? "details.php?id=" . $idEmpruntReel : "index.php";
if ($messageSucces) {
    $redirectUrl .= (strpos($redirectUrl, '?') === false ? '?' : '&') . "success=" . urlencode($messageSucces) . "&id_ecriture=" . urlencode($idEcritureNouvelle ?? '');
} elseif ($messageErreur) {
    $redirectUrl .= (strpos($redirectUrl, '?') === false ? '?' : '&') . "error=" . urlencode($messageErreur);
}

header("Location: " . $redirectUrl);
exit;
?>