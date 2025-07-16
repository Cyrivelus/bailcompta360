<?php
// pages/emprunts/valider_remboursement.php

// --- Initialisation ---
header('Content-Type: text/html; charset=utf-8');
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
ob_start('mb_output_handler');

session_start();

// --- Inclusions ---
require_once '../../fonctions/database.php';

// --- Vérification des paramètres GET ---
if (!isset($_GET['id_echeance']) || !is_numeric($_GET['id_echeance'])) {
    header("Location: index.php?error=" . urlencode("ID d'échéance invalide ou manquant."));
    exit;
}

$idEcheance = (int)$_GET['id_echeance'];
$idEmpruntFallback = isset($_GET['id_emprunt_fallback']) ? (int)$_GET['id_emprunt_fallback'] : null;

// Vérification connexion PDO
if (!isset($pdo) || !$pdo instanceof PDO) {
    $messageErreur = "Erreur de connexion à la base de données.";
    $redirectUrl = $idEmpruntFallback ? "details.php?id=".$idEmpruntFallback : "index.php";
    header("Location: ".$redirectUrl."&error=".urlencode($messageErreur));
    exit;
}

// Configuration encodage SQL Server
$pdo->setAttribute(PDO::SQLSRV_ATTR_ENCODING, PDO::SQLSRV_ENCODING_UTF8);

// --- Début transaction ---
$pdo->beginTransaction();

try {
    // 1. Récupération échéance
    $stmtEcheance = $pdo->prepare("SELECT * FROM Echeances_Amortissement WHERE ID_Echeance = :id_echeance");
    $stmtEcheance->bindParam(':id_echeance', $idEcheance, PDO::PARAM_INT);
    $stmtEcheance->execute();
    $echeance = $stmtEcheance->fetch(PDO::FETCH_ASSOC);

    if (!$echeance) {
        throw new Exception("Échéance introuvable.");
    }

    $idEmprunt = (int)$echeance['ID_Emprunt'];

    // Vérification si déjà payée
    if (!empty($echeance['ID_Ecriture_Comptable'])) {
        throw new Exception("Cette échéance a déjà été payée.");
    }

    // 2. Récupération emprunt
    $stmtEmprunt = $pdo->prepare("SELECT * FROM Emprunts_Bancaires WHERE ID_Emprunt = :id_emprunt");
    $stmtEmprunt->bindParam(':id_emprunt', $idEmprunt, PDO::PARAM_INT);
    $stmtEmprunt->execute();
    $emprunt = $stmtEmprunt->fetch(PDO::FETCH_ASSOC);

    if (!$emprunt) {
        throw new Exception("Emprunt associé introuvable.");
    }

    // 3. Définition des comptes comptables
    $comptes = [
        'emprunt' => '164000',
        'interet' => '661000',
        'taxes' => '631000',
        'banque' => '512000'
    ];

    // Récupération des IDs de comptes
    $compteIds = [];
    $stmtFindCompte = $pdo->prepare("SELECT ID_Compte FROM Comptes_compta WHERE Numero_Compte = :numero_compte");
    
    foreach ($comptes as $type => $numero) {
        $stmtFindCompte->bindParam(':numero_compte', $numero, PDO::PARAM_STR);
        $stmtFindCompte->execute();
        $id = $stmtFindCompte->fetchColumn();
        
        if ($id === false) {
            throw new Exception("Compte $numero introuvable.");
        }
        $compteIds[$type] = $id;
    }

    // 4. Préparation des montants
    $amortissement = (float)($echeance['Amortissement'] ?? 0);
    $interet = (float)($echeance['Interet_SP'] ?? 0);
    $taxes = (float)($echeance['Taxes_Interet_SP'] ?? 0);
    $montantTotal = $amortissement + $interet + $taxes;

    if ($montantTotal <= 0) {
        throw new Exception("Montant d'échéance invalide.");
    }

    // 5. Création écriture comptable
    $dateEcheance = $echeance['Date_Echeance'];
    $numeroEcheance = $echeance['Numero_Echeance'];
    $numeroPret = $emprunt['Numero_Pret'] ?? $idEmprunt;
    $banque = $emprunt['Banque'] ?? 'Banque';
    $utilisateur = $_SESSION['utilisateur_nom'] ?? 'Système';

    $description = "Remboursement Emprunt $numeroPret Échéance $numeroEcheance";
    $numeroPiece = "REM-$idEmprunt-$numeroEcheance";
    $dateSaisie = date('Y-m-d H:i:s');
    $moisComptable = date('Y-m', strtotime($dateEcheance));
    $codeJournal = '512'; // Journal Banque

    // Insertion entête écriture
    $stmtInsertEcriture = $pdo->prepare("
        INSERT INTO Ecritures 
        (Date_Saisie, Description, Montant_Total, Cde, Numero_Piece, NomUtilisateur, Mois) 
        VALUES (:date_saisie, :description, :montant, :code_journal, :numero_piece, :utilisateur, :mois)
    ");
    
    $stmtInsertEcriture->bindParam(':date_saisie', $dateSaisie);
    $stmtInsertEcriture->bindParam(':description', $description, PDO::PARAM_STR, 0, PDO::SQLSRV_ENCODING_UTF8);
    $stmtInsertEcriture->bindParam(':montant', $montantTotal);
    $stmtInsertEcriture->bindParam(':code_journal', $codeJournal, PDO::PARAM_STR);
    $stmtInsertEcriture->bindParam(':numero_piece', $numeroPiece, PDO::PARAM_STR);
    $stmtInsertEcriture->bindParam(':utilisateur', $utilisateur, PDO::PARAM_STR);
    $stmtInsertEcriture->bindParam(':mois', $moisComptable, PDO::PARAM_STR);
    $stmtInsertEcriture->execute();

    // Récupération ID écriture
    $idEcriture = $pdo->lastInsertId() ?: $pdo->query("SELECT SCOPE_IDENTITY()")->fetchColumn();

    if (!$idEcriture) {
        throw new Exception("Erreur lors de la création de l'écriture.");
    }

    // 6. Création lignes d'écriture
    $stmtInsertLigne = $pdo->prepare("
        INSERT INTO Lignes_Ecritures 
        (ID_Ecriture, ID_Compte, Libelle_Ligne, Montant, Sens) 
        VALUES (:id_ecriture, :id_compte, :libelle, :montant, :sens)
    ");

    // Lignes de débit
    $lignesDebit = [
        ['compte' => $compteIds['emprunt'], 'montant' => $amortissement, 'libelle' => "Capital Éch. $numeroEcheance"],
        ['compte' => $compteIds['interet'], 'montant' => $interet, 'libelle' => "Intérêts Éch. $numeroEcheance"],
        ['compte' => $compteIds['taxes'], 'montant' => $taxes, 'libelle' => "Taxes Éch. $numeroEcheance"]
    ];

    foreach ($lignesDebit as $ligne) {
        if ($ligne['montant'] > 0) {
            $libelleComplet = $ligne['libelle']." Emprunt $numeroPret";
            $stmtInsertLigne->bindParam(':id_ecriture', $idEcriture);
            $stmtInsertLigne->bindParam(':id_compte', $ligne['compte']);
            $stmtInsertLigne->bindParam(':libelle', $libelleComplet, PDO::PARAM_STR, 0, PDO::SQLSRV_ENCODING_UTF8);
            $stmtInsertLigne->bindParam(':montant', $ligne['montant']);
            $stmtInsertLigne->bindValue(':sens', 'D');
            $stmtInsertLigne->execute();
        }
    }

    // Ligne de crédit (Banque)
    $libelleBanque = "Paiement Éch. $numeroEcheance Emprunt $numeroPret";
    $stmtInsertLigne->bindParam(':id_ecriture', $idEcriture);
    $stmtInsertLigne->bindParam(':id_compte', $compteIds['banque']);
    $stmtInsertLigne->bindParam(':libelle', $libelleBanque, PDO::PARAM_STR, 0, PDO::SQLSRV_ENCODING_UTF8);
    $stmtInsertLigne->bindParam(':montant', $montantTotal);
    $stmtInsertLigne->bindValue(':sens', 'C');
    $stmtInsertLigne->execute();

    // 7. Mise à jour échéance
    $stmtUpdateEcheance = $pdo->prepare("
        UPDATE Echeances_Amortissement 
        SET ID_Ecriture_Comptable = :id_ecriture, 
            Statut = 'P', 
            Date_Paiement = GETDATE() 
        WHERE ID_Echeance = :id_echeance
    ");
    $stmtUpdateEcheance->execute([
        ':id_ecriture' => $idEcriture,
        ':id_echeance' => $idEcheance
    ]);

    // 8. Mise à jour emprunt si nécessaire
    $stmtUpdateEmprunt = $pdo->prepare("
        UPDATE Emprunts_Bancaires 
        SET Capital_Restant_Du = Capital_Restant_Du - :amortissement,
            Date_Dernier_Paiement = GETDATE()
        WHERE ID_Emprunt = :id_emprunt
    ");
    $stmtUpdateEmprunt->execute([
        ':amortissement' => $amortissement,
        ':id_emprunt' => $idEmprunt
    ]);

    // Validation transaction
    $pdo->commit();

    $messageSucces = "Échéance $numeroEcheance payée avec succès. Écriture #$idEcriture créée.";

} catch (Exception $e) {
    $pdo->rollBack();
    $messageErreur = "Erreur : ".$e->getMessage();
    error_log("Erreur valider_remboursement.php: ".$e->getMessage());
}

// Redirection
$redirectUrl = $idEmprunt ? "details.php?id=".$idEmprunt : ($idEmpruntFallback ? "details.php?id=".$idEmpruntFallback : "index.php");
if (isset($messageSucces)) {
    $redirectUrl .= "?success=".urlencode($messageSucces);
} elseif (isset($messageErreur)) {
    $redirectUrl .= (strpos($redirectUrl, '?') !== false ? '&' : '?')."error=".urlencode($messageErreur);
}

header("Location: ".$redirectUrl);
exit;
?>