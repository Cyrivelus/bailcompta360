<?php
// pages/factures/process_payment.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Define constants
// Suppression de DEFAULT_ACCOUNT_BANK
define('DEFAULT_JOURNAL_PAIEMENT_ID', 1);
define('ACCOUNT_NO_BANK_NUMBER', '38423'); // Nouveau compte fixe pour "COMPTE SANS BANQUE"

// ----------------------
// 📦 INCLUSIONS
// ----------------------
require_once('../../fonctions/database.php');
require_once('../../fonctions/gestion_factures.php');
require_once('../../fonctions/gestion_ecritures.php');
require_once('../../fonctions/gestion_comptes.php'); // Assurez-vous que cette fonction gère la recherche de compte

// ----------------------
// 🔌 CONNEXION DB
// ----------------------
if (!isset($pdo) || !$pdo instanceof PDO) {
    header('Location: listes_factures.php?error=' . urlencode('Erreur de configuration du serveur: connexion DB manquante.'));
    exit();
}

// ----------------------
// ⚙️ VÉRIFICATION DE LA REQUÊTE
// ----------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'payer' || !isset($_POST['facture_id'])) {
    header('Location: listes_factures.php?error=' . urlencode('Requête invalide ou action non spécifiée.'));
    exit();
}

$factureId = (int)$_POST['facture_id'];
$facture = getFactureById($pdo, $factureId);

// Récupérer le mode de paiement
$paymentMethod = $_POST['payment_method'] ?? '';
// Ajout de 'sans_banque' aux modes de paiement valides
if (!in_array($paymentMethod, ['cheque', 'virement', 'sans_banque', 'especes'])) { // Ajout de 'especes' si pertinent
    header('Location: listes_factures.php?error=' . urlencode('Mode de paiement invalide spécifié.'));
    exit();
}

// Nouveau: Récupérer le nom de la banque (si applicable)
$bankNameInput = trim($_POST['bank_name'] ?? '');

// Si le mode de paiement est chèque ou virement, la saisie de la banque est nécessaire
if (($paymentMethod === 'cheque' || $paymentMethod === 'virement' ) && empty($bankNameInput)) {
    header('Location: listes_factures.php?error=' . urlencode('Le nom de la banque est obligatoire pour le paiement par ' . $paymentMethod . '.'));
    exit();
}

// ----------------------
// ✅ VÉRIFICATION DE LA FACTURE
// ----------------------
if (!$facture) {
    header('Location: listes_factures.php?error=' . urlencode('Facture non trouvée.'));
    exit();
}

if ($facture['Statut_Facture'] === 'Payé') {
    header('Location: listes_factures.php?success=' . urlencode('Facture déjà marquée comme payée.'));
    exit();
}

// --- Récupération des montants de la facture ---
$montantTTC = (float)$facture['Montant_TTC'];
$montantNetAPayer = (float)$facture['Montant_Net_A_Payer'];
$nomFournisseur = $facture['Nom_Fournisseur'];
$numeroFacture = $facture['Numero_Facture'];

// ----------------------
// 📦 VÉRIFICATION DES FONCTIONS REQUISES
// ----------------------
if (!function_exists('getFactureById') ||
    !function_exists('updateFactureStatutAndEcriture') ||
    !function_exists('getRetenuesByFactureId') ||
    !function_exists('createEcriture') ||
    !function_exists('createLigneEcriture') ||
    !function_exists('getIdCompteByNumero') || // Utilisé pour le compte "sans banque"
    !function_exists('getCompteFournisseurId') ||
    !function_exists('getCompteBanqueId') // Fonction que nous allons potentiellement modifier ou appeler différemment
) {
    header('Location: listes_factures.php?error=' . urlencode('Erreur interne du système : fonctions requises manquantes.'));
    exit();
}

// ----------------------
// 💰 DÉBUT DE LA TRANSACTION
// ----------------------
$pdo->beginTransaction();

try {
    // ----------------------
    // 🆔 RÉCUPÉRATION DES ID DE COMPTES
    // ----------------------
    $idCompteFournisseur = getCompteFournisseurId($pdo, $nomFournisseur);
    if (!$idCompteFournisseur) {
        throw new Exception("Compte fournisseur introuvable pour: " . htmlspecialchars($nomFournisseur));
    }

    $idCompteBanque = null;
    $bankDisplayName = ''; // Nom de la banque affiché dans la description

    // Logique pour déterminer le compte bancaire en fonction du mode de paiement
    if ($paymentMethod === 'sans_banque') {
        $idCompteBanque = getIdCompteByNumero($pdo, ACCOUNT_NO_BANK_NUMBER);
        $bankDisplayName = 'COMPTE SANS BANQUE';
        if (!$idCompteBanque) {
            throw new Exception("Compte 'COMPTE SANS BANQUE' (" . ACCOUNT_NO_BANK_NUMBER . ") introuvable.");
        }
    } elseif ($paymentMethod === 'especes') {
        // Supposons que le compte espèces est '571' (ou autre compte caisse)
        // Vous devez définir le numéro de compte espèces ou le récupérer dynamiquement
        $idCompteBanque = getIdCompteByNumero($pdo, '571'); // Exemple pour compte espèces
        $bankDisplayName = 'CAISSE (ESPÈCES)';
        if (!$idCompteBanque) {
            throw new Exception("Compte Espèces (571) introuvable. Vérifiez sa configuration.");
        }
    } else { // 'cheque' ou 'virement'
        // Recherche dynamique du compte bancaire basé sur $bankNameInput
        $searchQuery = substr($bankNameInput, 0, 9); // Premiers 3 caractères

        // 1. Priorité: Nom_Compte contient 'BANQUE' ou 'BANK' ET Numero_Compte commence par '56'
        $stmtBank = $pdo->prepare("SELECT ID_Compte, Nom_Compte FROM Comptes_compta WHERE Numero_Compte LIKE '56%' AND Nom_Compte LIKE ? ORDER BY Nom_Compte ASC");
        $stmtBank->execute(['%' . $searchQuery . '%BANQUE%']);
        $bankAccount = $stmtBank->fetch(PDO::FETCH_ASSOC);

        if (!$bankAccount) {
            $stmtBank->execute(['%' . $searchQuery . '%BANK%']);
            $bankAccount = $stmtBank->fetch(PDO::FETCH_ASSOC);
        }
		
		if (!$bankAccount) {
            $stmtBank->execute(['%' . $searchQuery . '%BQUE%']);
            $bankAccount = $stmtBank->fetch(PDO::FETCH_ASSOC);
        }
		
		if (!$bankAccount) {
            $stmtBank->execute(['%' . $searchQuery . '%BQ%']);
            $bankAccount = $stmtBank->fetch(PDO::FETCH_ASSOC);
        }
		
		if (!$bankAccount) {
            $stmtBank->execute(['%' . $searchQuery . '%YDE%']);
            $bankAccount = $stmtBank->fetch(PDO::FETCH_ASSOC);
        }
		
		if (!$bankAccount) {
            $stmtBank->execute(['%' . $searchQuery . '%YAOUNDE%']);
            $bankAccount = $stmtBank->fetch(PDO::FETCH_ASSOC);
        }

        // 2. Si pas trouvé avec 'BANQUE'/'BANK', alors Nom_Compte contient les 3 premières lettres ET Numero_Compte commence par '56'
        if (!$bankAccount) {
            $stmtBank = $pdo->prepare("SELECT ID_Compte, Nom_Compte FROM Comptes_compta WHERE Numero_Compte LIKE '56%' AND Nom_Compte LIKE ? ORDER BY Nom_Compte ASC");
            $stmtBank->execute(['%' . $searchQuery . '%']);
            $bankAccount = $stmtBank->fetch(PDO::FETCH_ASSOC);
        }

        if ($bankAccount) {
            $idCompteBanque = $bankAccount['ID_Compte'];
            $bankDisplayName = $bankAccount['Nom_Compte'];
        } else {
            throw new Exception("Compte bancaire introuvable pour la recherche '" . htmlspecialchars($bankNameInput) . "'. Veuillez vérifier le nom.");
        }
    }

    if (!$idCompteBanque) {
        throw new Exception("Compte bancaire non défini pour le paiement. Erreur interne.");
    }

    // ----------------------
    // 🔄 RÉCUPÉRATION DES RETENUES SPÉCIFIQUES
    // ----------------------
    $invoiceRetenues = getRetenuesByFactureId($pdo, $factureId);

    // ----------------------
    // 📝 PRÉPARATION DE L'ÉCRITURE COMPTABLE
    // ----------------------
    $idJournal = DEFAULT_JOURNAL_PAIEMENT_ID;

    // Ajuster la description en fonction du mode de paiement et de la banque
    $descriptionEcriture = "PAIEMENT ";
    switch ($paymentMethod) {
        case 'cheque':
            $descriptionEcriture .= 'PAR CHÈQUE';
            break;
        case 'virement':
            $descriptionEcriture .= 'PAR VIREMENT';
            break;
        case 'sans_banque':
            $descriptionEcriture .= 'avec le compte sans banque';
            break;
        case 'especes':
            $descriptionEcriture .= 'en espèces';
            break;
        default:
            $descriptionEcriture .= 'mode inconnu';
            break;
    }

    if (!empty($bankDisplayName)) {
        $descriptionEcriture .= " VIA " . htmlspecialchars($bankDisplayName);
    }
    $descriptionEcriture .= " FACTURE " . htmlspecialchars($numeroFacture) . " - " . htmlspecialchars($nomFournisseur);

    $dateSaisie = date('Y-m-d H:i:s');
    $mois = date('Y-m');
    $nomUtilisateur = $_SESSION['nom_utilisateur'] ?? 'System';
    $numeroPiece = 'PAIE-' . $numeroFacture;

    // ----------------------
    // ✍️ CRÉATION DE L'ÉCRITURE PRINCIPALE (EN-TÊTE)
    // ----------------------
    try {
        $idEcriture = createEcriture(
            $pdo,
            $dateSaisie,
            $descriptionEcriture, // Utilise la description modifiée
            $montantTTC,
            $idJournal,
            $nomUtilisateur,
            $mois,
            $numeroPiece
        );

        if (!$idEcriture) {
            $errorInfo = $pdo->errorInfo();
            $errorMessage = "Échec de la création de l'écriture principale. ";
            if (isset($errorInfo[2])) {
                $errorMessage .= "Erreur SQL: " . $errorInfo[2];
            } else {
                $errorMessage .= "Détails de l'erreur SQL inconnus. Vérifiez la fonction createEcriture.";
            }
            throw new Exception($errorMessage);
        }

        if (!is_numeric($idEcriture) || $idEcriture <= 0) {
            throw new Exception("ID d'écriture invalide retourné: " . var_export($idEcriture, true));
        }

    } catch (PDOException $e) {
        throw new Exception("Erreur PDO lors de la création de l'écriture: " . $e->getMessage());
    } catch (Exception $e) {
        throw new Exception("Erreur générale lors de la création de l'écriture: " . $e->getMessage());
    }

    // ----------------------
    // 📈 CRÉATION DES LIGNES D'ÉCRITURES
    // ----------------------
    $libelleLigne = "PAIEMENT FACT. FRS " . htmlspecialchars($numeroFacture);

    // 1. Débit Fournisseur (pour le montant TTC total, pour apurer la dette)
    // Sens 'D' pour le Fournisseur, car la dette est apurée (diminution du passif)
    if (!createLigneEcriture($pdo, $idEcriture, $idCompteFournisseur, $montantTTC, 'D', $libelleLigne . " (Apurement Fournisseur)")) {
        throw new Exception("Échec de la création de la ligne de débit fournisseur.");
    }

    // 2. Crédit Compte de Trésorerie (Banque/Caisse/Compte Sans Banque) (pour le Montant Net à Payer)
    // Sens 'C' pour la Trésorerie, car la trésorerie diminue (diminution de l'actif)
    $libelleLigneTresorerie = $libelleLigne . " (" . $bankDisplayName . ")";
    if (!createLigneEcriture($pdo, $idEcriture, $idCompteBanque, $montantNetAPayer, 'C', $libelleLigneTresorerie)) {
        throw new Exception("Échec de la création de la ligne de crédit trésorerie.");
    }

    // 3. Crédit Retenues (pour chaque retenue individuelle)
    // Sens 'C' pour les comptes de retenues, car ce sont des passifs (augmentation du passif)
    if (!empty($invoiceRetenues)) {
        foreach ($invoiceRetenues as $retenue) {
            if ($retenue['Montant_Retenue'] > 0) {
                if (!createLigneEcriture($pdo, $idEcriture, (int)$retenue['ID_Compte_Retenue'], (float)$retenue['Montant_Retenue'], 'C', $libelleLigne . " (" . htmlspecialchars($retenue['Libelle_Retenue']) . ")")) {
                    throw new Exception("Échec de la création de la ligne de crédit pour retenue " . htmlspecialchars($retenue['Libelle_Retenue']) . ".");
                }
            }
        }
    }

    // ----------------------
    // 📊 MISE À JOUR DE LA FACTURE
    // ----------------------
    if (!updateFactureStatutAndEcriture($pdo, $factureId, 'Payé', $idEcriture)) {
        throw new Exception("Échec de la mise à jour du statut de la facture.");
    }

    $pdo->commit();

    // ----------------------
    // ✅ SUCCÈS
    // ----------------------
    header('Location: listes_factures.php?success=' . urlencode('Paiement effectué avec succès ! (ID écriture: ' . $idEcriture . ')'));
    exit();

} catch (Exception $e) {
    // ----------------------
    // ❌ ERREUR ET ROLLBACK
    // ----------------------
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: listes_factures.php?error=' . urlencode('Échec du paiement : ' . $e->getMessage()));
    exit();
}