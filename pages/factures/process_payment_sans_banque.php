<?php
// process_payment_sans_banque.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Constantes
define('DEFAULT_JOURNAL_PAIEMENT_ID', 1);
define('ACCOUNT_NO_BANK_NUMBER', '384230000000'); // Numéro du compte "sans banque"

// Inclusions nécessaires
require_once('../../fonctions/database.php');
require_once('../../fonctions/gestion_factures.php');
require_once('../../fonctions/gestion_ecritures.php');
require_once('../../fonctions/gestion_comptes.php');

// Vérifier la connexion PDO
if (!isset($pdo) || !$pdo instanceof PDO) {
    exitWithError("Connexion à la base de données manquante.");
}

// Vérification de la requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'payer') {
    exitWithError("Requête invalide.");
}

// Récupération des données du formulaire
$factureId = (int) ($_POST['facture_id'] ?? 0);
$csrfToken = $_POST['csrf_token'] ?? '';
$paymentMethod = $_POST['payment_method'] ?? '';

// Ce fichier ne gère que les paiements sans banque
if ($paymentMethod !== 'sans_banque') {
    exitWithError("Ce script est réservé aux paiements sans banque.");
}

// Vérification du CSRF token
if (!verifyCsrfToken($csrfToken)) {
    exitWithError("Jeton CSRF invalide.");
}

// Récupération de la facture
$facture = getFactureById($pdo, $factureId);
if (!$facture) {
    exitWithError("Facture introuvable.");
}
if ($facture['Statut_Facture'] === 'Payé') {
    redirectSuccess("Facture déjà marquée comme payée.");
}

// Récupération des données de la facture
$montantTTC = (float) $facture['Montant_TTC'];
$montantNetAPayer = (float) $facture['Montant_Net_A_Payer'];
$nomFournisseur = $facture['Nom_Fournisseur'];
$numeroFacture = $facture['Numero_Facture'];

// Récupération des comptes comptables
$idCompteFournisseur = getCompteFournisseurId($pdo, $nomFournisseur);
$idCompteSansBanque = getIdCompteByNumero($pdo, ACCOUNT_NO_BANK_NUMBER);
$retenues = getRetenuesByFactureId($pdo, $factureId);

// Vérification des comptes
if (!$idCompteFournisseur) {
    exitWithError("Compte fournisseur introuvable pour : $nomFournisseur");
}
if (!$idCompteSansBanque) {
    exitWithError("Compte 'sans banque' introuvable (numéro " . ACCOUNT_NO_BANK_NUMBER . ").");
}

// Démarrage de la transaction
$pdo->beginTransaction();

try {
    $description = "PAIEMENT SANS BANQUE - Facture $numeroFacture - $nomFournisseur";
    $dateSaisie = date('Y-m-d H:i:s');
    $mois = date('Y-m');
    $utilisateur = $_SESSION['nom_utilisateur'] ?? 'Système';
    $numeroPiece = 'PAIE-' . $numeroFacture;

    // Création de l'écriture principale
    $idEcriture = createEcriture(
        $pdo,
        $dateSaisie,
        $description,
        $montantTTC,
        DEFAULT_JOURNAL_PAIEMENT_ID,
        $utilisateur,
        $mois,
        $numeroPiece
    );

    if (!$idEcriture) {
        throw new Exception("Échec de la création de l'écriture.");
    }

    // Ligne 1 : Débit fournisseur (paiement de la dette)
    if (!createLigneEcriture(
        $pdo,
        $idEcriture,
        $idCompteFournisseur,
        $montantTTC,
        'D',
        "PAIEMENT FACTURE $numeroFacture - Fournisseur"
    )) {
        throw new Exception("Erreur lors de la ligne fournisseur.");
    }

    // Ligne 2 : Crédit "sans banque"
    if (!createLigneEcriture(
        $pdo,
        $idEcriture,
        $idCompteSansBanque,
        $montantNetAPayer,
        'C',
        "PAIEMENT VIA COMPTE INTERNE (SANS BANQUE)"
    )) {
        throw new Exception("Erreur ligne compte sans banque.");
    }

    // Lignes 3+ : Crédit retenues (si présentes)
    foreach ($retenues as $retenue) {
        $montantRetenue = (float) $retenue['Montant_Retenue'];
        if ($montantRetenue > 0) {
            if (!createLigneEcriture(
                $pdo,
                $idEcriture,
                $retenue['ID_Compte_Retenue'],
                $montantRetenue,
                'C',
                "RETENUE: " . $retenue['Libelle_Retenue']
            )) {
                throw new Exception("Erreur ligne retenue : " . $retenue['Libelle_Retenue']);
            }
        }
    }

    // Mise à jour du statut de la facture
    if (!updateFactureStatutAndEcriture($pdo, $factureId, 'Payé', $idEcriture)) {
        throw new Exception("Erreur lors de la mise à jour du statut de la facture.");
    }

    $pdo->commit();
    redirectSuccess("Paiement effectué avec succès (écriture #$idEcriture).");

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    exitWithError("Erreur : " . $e->getMessage());
}

// ------------------------
// Fonctions utilitaires
// ------------------------

function exitWithError($msg) {
    header('Location: listes_factures.php?error=' . urlencode($msg));
    exit();
}

function redirectSuccess($msg) {
    header('Location: listes_factures.php?success=' . urlencode($msg));
    exit();
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
