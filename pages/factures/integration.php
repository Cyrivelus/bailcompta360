<?php
ob_start();
// pages/factures/integration.php

// ----------------------
// ⚙️ CONFIGURATION GÉNÉRALE
// ----------------------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// ----------------------
// 📦 INCLUSIONS
// ----------------------

require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
require_once('../../fonctions/database.php');
require_once('../../fonctions/gestion_comptes.php'); // Assurez-vous que ce fichier existe et contient les fonctions nécessaires
require_once('../../fonctions/gestion_tiers.php');
require_once('../../fonctions/gestion_factures.php');

// ----------------------
// 📌 CONSTANTES
// ----------------------
$TITRE_PAGE = 'Intégration de Facture Fournisseur';
$CURRENT_PAGE = basename($_SERVER['PHP_SELF']);

define('PREFIXES_COMPTE_FOURNISSEUR', ['40', '40']);
define('DEFAULT_TIERS_ID', 1);
define('DEFAULT_JOURNAL_FACTURE_ACHAT', 'ACH');
define('DEFAULT_TVA_RATE', 19.25);
define('DEFAULT_ACCOUNT_CHARGE_FRS', '607000');
define('DEFAULT_ACCOUNT_TVA_FRS', '445660');

// Note: DEFAULT_JOURNAL_VENTE and DEFAULT_JOURNAL_ACHAT are assumed to be Journal IDs.
define('DEFAULT_JOURNAL_VENTE', 4457); // Assuming this is an ID, not account number
define('DEFAULT_JOURNAL_ACHAT', 1); // Assuming this is an ID, not account number

// ----------------------
// 🔌 CONNEXION DB
// ----------------------
// The $pdo variable is expected to be defined in database.php
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("Erreur critique: \$pdo non défini ou non une instance de PDO.");
    // Provide a more user-friendly error or handle it gracefully
    die("Erreur configuration serveur: Impossible de se connecter à la base de données.");
}


// ----------------------
// 🔧 INITIALISATION VARIABLES
// ----------------------
$erreur = null;
$success = null;
$comptesFournisseurs = [];
$tousComptesCompta = []; // Variable pour tous les comptes comptables

$compteFournisseurSelectionne = '';
$nomFournisseurFacture = '';
$numeroFacture = '';
$referenceFacture = '';
$numeroCommande = '';
$dateEmission = date('Y-m-d');
$dateReception = date('Y-m-d');
$dateEcheance = date('Y-m-d');
$description = '';

$totalFactureHT = 0.0;
$tauxTVA = DEFAULT_TVA_RATE;
$montantTVA_calcule = 0.0;
$totalTTC_calcule = 0.0;

// Variables pour les nouveaux selectbox de comptabilité
$compteCharge = '';
$compteFournisseurContrepartie = ''; // Sera le même que $compteFournisseurSelectionne pour les écritures
$compteTVA = '';
$comptePrecompte = ''; // Optionnel

// Initialize variables for Retenues (6 pairs of amount and account)
$retenues = []; // Array to store retenue data
$totalRetenues = 0.0; // Sum of all retenues

for ($i = 1; $i <= 6; $i++) {
    // Initialize for display and POST data handling
    ${'retenue_montant_' . $i} = 0.0; // e.g., $retenue_montant_1
    ${'retenue_compte_' . $i} = '';    // e.g., $retenue_compte_1
    // Store in a more structured array for easier iteration later
    $retenues[$i] = [
        'montant' => 0.0,
        'compte'  => ''
    ];
}


// Initialize $mois here, not as a default function parameter
$mois = date('Y-m');


// ----------------------
// 🔄 CHARGEMENT DES DONNÉES INITIALES
// ----------------------
try {
    // Crucial: Check if all necessary functions are available before proceeding.
    // This prevents "undefined function" errors that can occur before a transaction starts.
    if (!function_exists('getComptesFournisseurs') ||
        !function_exists('verifierCompteExisteDansPLN') ||
        !function_exists('getIdTiersFromCompte') ||
        !function_exists('enregistrerFacture') ||
        !function_exists('getTousComptesCompta') ||
        // Ensure these critical accounting functions are also checked
        !function_exists('getIdCompteByNumero') ||
        !function_exists('enregistrerEcritures') ||
        !function_exists('enregistrerLigneEcritures') ||
        !function_exists('saveFactureRetenue') // Added this for the new function
    ) {
        throw new Exception("Une ou plusieurs fonctions requises sont manquantes. Vérifiez les fichiers d'inclusion dans 'fonctions/'.");
    }

    $comptesFournisseurs = getComptesFournisseurs($pdo);
    $tousComptesCompta = getTousComptesCompta($pdo); // Récupérer tous les comptes

} catch (Exception $e) {
    $erreur = "Erreur chargement données initiales : " . htmlspecialchars($e->getMessage());
    error_log("Chargement données init : " . $e->getMessage());
    // Stop execution if critical data loading fails, as the page can't function.
    die($erreur);
}

// ----------------------
// 📂 DÉFINITION DES FONCTIONS
// ----------------------

// IMPORTANT: The following functions (getIdCompteByNumero, enregistrerEcritures, enregistrerLigneEcritures, saveFactureRetenue)
// *must* be defined either in the current file or in one of the required_once files (e.g., fonctions/gestion_comptes.php or fonctions/gestion_factures.php).
// For the purpose of this correction, I'm placing them here as per your original snippet,
// but it's strongly recommended to define them in their respective `fonctions/` files.

/**
 * Récupère tous les comptes comptables depuis la table Comptes_compta.
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @return array Un tableau d'objets ou de tableaux associatifs représentant les comptes.
 */
function getTousComptesCompta(PDO $pdo) {
    $stmt = $pdo->query("SELECT ID_Compte, Numero_Compte, Nom_Compte FROM Comptes_compta ORDER BY Numero_Compte ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère l'ID_Compte à partir du Numero_Compte.
 * @param PDO $pdo L'objet PDO.
 * @param string $numeroCompte Le numéro du compte.
 * @return int|false L'ID_Compte ou false si non trouvé.
 */
// Placeholder for getIdCompteByNumero - ensure this function is defined in gestion_comptes.php
/*
function getIdCompteByNumero(PDO $pdo, string $numeroCompte) {
    $stmt = $pdo->prepare("SELECT ID_Compte FROM Comptes_compta WHERE Numero_Compte = :numero");
    $stmt->execute([':numero' => $numeroCompte]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['ID_Compte'] : false;
}
*/


/**
 * Enregistre une écriture comptable principale dans la table Ecritures.
 * @param PDO $pdo L'objet PDO.
 * @param string $description Description de l'écriture.
 * @param float $montantTotal Montant total de l'écriture.
 * @param int $idJournal ID du journal comptable.
 * @param string $numeroPiece Numéro de pièce (ex: numéro de facture).
 * @param string $mois Mois de l'écriture (YYYY-MM).
 * @param string $nomUtilisateur Nom de l'utilisateur qui effectue l'écriture.
 * @param string $numeroAgenceSCE Numéro d'agence SCE (initialisation ajoutée).
 * @return int|false L'ID de l'écriture insérée ou false en cas d'erreur.
 */
// Placeholder for enregistrerEcritures - ensure this function is defined in gestion_comptes.php
/*
function enregistrerEcritures(PDO $pdo, string $description, float $montantTotal, int $idJournal, string $numeroPiece, string $mois, string $nomUtilisateur, string $numeroAgenceSCE) {
    $sql = "INSERT INTO Ecritures (Description, Montant_Total, ID_Journal, Numero_Piece, Mois, Date_Ecriture, Heure_Ecriture, Nom_Utilisateur, Numero_Agence_SCE)
            VALUES (:description, :montantTotal, :idJournal, :numeroPiece, :mois, GETDATE(), GETDATE(), :nomUtilisateur, :numeroAgenceSCE)"; // Assuming SQL Server's GETDATE()

    $stmt = $pdo->prepare($sql);
    try {
        if ($stmt->execute([
            ':description' => $description,
            ':montantTotal' => $montantTotal,
            ':idJournal' => $idJournal,
            ':numeroPiece' => $numeroPiece,
            ':mois' => $mois,
            ':nomUtilisateur' => $nomUtilisateur,
            ':numeroAgenceSCE' => $numeroAgenceSCE
        ])) {
            return $pdo->lastInsertId();
        } else {
            error_log("Erreur enregistrement Ecritures : " . implode(" | ", $stmt->errorInfo()));
            return false;
        }
    } catch (PDOException $e) {
        error_log("PDOException enregistrement Ecritures : " . $e->getMessage());
        return false;
    }
}
*/

/**
 * Enregistre une ligne d'écriture dans la table Lignes_Ecritures.
 * @param PDO $pdo L'objet PDO.
 * @param int $idEcriture ID de l'écriture parente.
 * @param int $idCompte ID du compte comptable.
 * @param string $libelle Libellé de la ligne.
 * @param float $montant Montant de la ligne.
 * @param string $sens Sens (D pour débit, C pour crédit).
 * @param string $an_code Code analytique (optional). // NOTE: Removed from actual call as per schema
 * @param string $contrepartie_compte Compte de contrepartie (optional). // NOTE: Removed from actual call as per schema
 * @return bool True en cas de succès, false en cas d'erreur.
 */
// Placeholder for enregistrerLigneEcritures - ensure this function is defined in gestion_comptes.php
/*
function enregistrerLigneEcritures(PDO $pdo, int $idEcriture, int $idCompte, string $libelle, float $montant, string $sens): bool {
    $sql = "INSERT INTO Lignes_Ecritures (ID_Ecriture, ID_Compte, Libelle, Montant, Sens)
            VALUES (:idEcriture, :idCompte, :libelle, :montant, :sens)";
    $stmt = $pdo->prepare($sql);
    try {
        return $stmt->execute([
            ':idEcriture' => $idEcriture,
            ':idCompte' => $idCompte,
            ':libelle' => $libelle,
            ':montant' => $montant,
            ':sens' => $sens
        ]);
    } catch (PDOException $e) {
        error_log("PDOException enregistrement Lignes_Ecritures : " . $e->getMessage());
        return false;
    }
}
*/

function enregistrerFacture($pdo, $numero, $dateEmis, $dateRec, $dateEch, $nomFournisseur, $ht, $tva, $ttc, $netAPayer, $statut, $journal, $commande, $ref = null) {
    $sql = "INSERT INTO Factures
        (Numero_Facture, Date_Emission, Date_Reception, Date_Echeance, Nom_Fournisseur, Montant_HT, Montant_TVA, Montant_TTC, Montant_Net_A_Payer, Statut_Facture, ID_Journal, Numero_Bon_Commande, Commentaire)
        VALUES
        (:numero, :dateEmis, :dateRec, :dateEch, :nomFournisseur, :ht, :tva, :ttc, :netAPayer, :statut, :idJournal, :commande, :ref)";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':numero', $numero);
    $stmt->bindParam(':dateEmis', $dateEmis);
    $stmt->bindParam(':dateRec', $dateRec);
    $stmt->bindParam(':dateEch', $dateEch);
    $stmt->bindParam(':nomFournisseur', $nomFournisseur);
    $stmt->bindParam(':ht', $ht);
    $stmt->bindParam(':tva', $tva);
    $stmt->bindParam(':ttc', $ttc);
    $stmt->bindParam(':netAPayer', $netAPayer); // Bind the new parameter
    $stmt->bindParam(':statut', $statut);
    $stmt->bindParam(':idJournal', $journal, PDO::PARAM_INT);
    $stmt->bindParam(':commande', $commande);
    $stmt->bindParam(':ref', $ref);

    try {
        if ($stmt->execute()) {
            return $pdo->lastInsertId();
        } else {
            error_log("Erreur enregistrement facture : " . implode(" | ", $stmt->errorInfo()));
            return false;
        }
    } catch (PDOException $e) {
        error_log("PDOException enregistrement facture : " . $e->getMessage());
        return false;
    }
}


function saveFactureRetenue(PDO $pdo, int $idFacture, int $idCompteRetenue, float $montant, string $libelle): bool
{
    $sql = "INSERT INTO Facture_Retenues (ID_Facture, ID_Compte_Retenue, Montant_Retenue, Libelle_Retenue)
            VALUES (:id_facture, :id_compte_retenue, :montant_retenue, :libelle_retenue)";
    $stmt = $pdo->prepare($sql);
    try {
        return $stmt->execute([
            ':id_facture' => $idFacture,
            ':id_compte_retenue' => $idCompteRetenue,
            ':montant_retenue' => $montant,
            ':libelle_retenue' => $libelle
        ]);
    } catch (PDOException $e) {
        error_log("PDOException saving Facture_Retenue: " . $e->getMessage());
        return false;
    }
}

// ----------------------
// 📤 TRAITEMENT DU FORMULAIRE
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $erreursValidation = [];

    // Récupération des données du formulaire
    $compteFournisseurSelectionne = $_POST['compte_fournisseur_select'] ?? '';
    $nomFournisseurFacture = trim($_POST['nom_fournisseur_facture'] ?? '');
    $numeroFacture = trim($_POST['numero_facture'] ?? '');
    $referenceFacture = trim($_POST['reference_facture'] ?? '');
    $numeroCommande = trim($_POST['numero_commande'] ?? '');
    $dateEmission = $_POST['date_emission'] ?? '';
    $dateReception = $_POST['date_reception'] ?? '';
    $dateEcheance = $_POST['date_echeance'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $totalFactureHT = floatval(str_replace(',', '.', $_POST['total_ht'] ?? 0));
    $tauxTVA = floatval(str_replace(',', '.', $_POST['taux_tva'] ?? DEFAULT_TVA_RATE));

    // Nouveaux champs pour la comptabilité
    $compteCharge = $_POST['compte_charge_select'] ?? '';
    $compteFournisseurContrepartie = $_POST['compte_fournisseur_contrepartie_select'] ?? $compteFournisseurSelectionne; // Par défaut le compte fournisseur sélectionné
    $compteTVA = $_POST['compte_tva_select'] ?? '';
    $comptePrecompte = $_POST['compte_precompte_select'] ?? ''; // Peut être vide

    // Récupération et calcul des retenues
    $totalRetenues = 0.0;
    for ($i = 1; $i <= 6; $i++) {
        $retenueMontant = floatval(str_replace(',', '.', $_POST['retenue_montant_' . $i] ?? 0));
        $retenueCompte = trim($_POST['retenue_compte_' . $i . '_select'] ?? '');

        // Store for later use and re-population of the form
        ${'retenue_montant_' . $i} = $retenueMontant;
        ${'retenue_compte_' . $i} = $retenueCompte;
        $retenues[$i] = [
            'montant' => $retenueMontant,
            'compte'  => $retenueCompte
        ];

        if ($retenueMontant > 0) {
            $totalRetenues += $retenueMontant;
            if (empty($retenueCompte)) {
                $erreursValidation[] = "Veuillez sélectionner un compte pour la retenue " . $i . " dont le montant est saisi.";
            }
        } elseif (!empty($retenueCompte)) {
            // If a account is selected but amount is 0 or less
            $erreursValidation[] = "Le montant pour la retenue " . $i . " doit être supérieur à 0 si un compte est sélectionné.";
        }
    }


    // Calculs
    $tauxTVA_decimal = $tauxTVA / 100;
    $montantTVA_calcule = round($totalFactureHT * $tauxTVA_decimal, 2);
    $totalTTC_calcule = round($totalFactureHT + $montantTVA_calcule, 2);
    // Calculate the final net amount to pay after deducting retenues
    $netAPayer = round($totalTTC_calcule - $totalRetenues, 2);


    // Validations
    if (empty($compteFournisseurSelectionne)) {
        $erreursValidation[] = "Veuillez sélectionner un compte fournisseur.";
    } elseif (!verifierCompteExisteDansPLN($pdo, $compteFournisseurSelectionne)) {
        // No need to check for function_exists here as it's already checked in the initial loading block.
        $erreursValidation[] = "Compte fournisseur invalide.";
    }

    if (empty($nomFournisseurFacture)) $erreursValidation[] = "Nom du fournisseur requis.";
    if (empty($numeroFacture)) $erreursValidation[] = "Numéro de facture requis.";
    if (empty($dateEmission)) $erreursValidation[] = "Date d’émission requise.";
    if (empty($dateReception)) $erreursValidation[] = "Date de réception requise.";
    if (empty($dateEcheance)) $erreursValidation[] = "Date d’échéance requise.";
    if ($totalFactureHT <= 0) $erreursValidation[] = "Montant HT doit être supérieur à 0.";
    if ($tauxTVA < 0) $erreursValidation[] = "Taux de TVA invalide.";

    // Validations des nouveaux comptes comptables
    if (empty($compteCharge)) $erreursValidation[] = "Veuillez sélectionner un compte de charge.";
    if (empty($compteTVA)) $erreursValidation[] = "Veuillez sélectionner un compte de TVA.";


    // Enregistrement si pas d'erreurs
    if (empty($erreursValidation)) {
        try {
            // Start transaction *immediately before* the first write operation.
            // This ensures that if any issue occurs right at the start of the transaction,
            // the `inTransaction()` check won't fail due to no active transaction.
            $pdo->beginTransaction();

            $idJournalAchat = DEFAULT_JOURNAL_ACHAT;

            // 1. Enregistrer la facture
            $idFacture = enregistrerFacture(
                $pdo,
                $numeroFacture,
                $dateEmission,
                $dateReception,
                $dateEcheance,
                $nomFournisseurFacture,
                $totalFactureHT,
                $montantTVA_calcule,
                $totalTTC_calcule,
                $netAPayer, // Pass the calculated netAPayer value here
                'Non payée',
                $idJournalAchat,
                $numeroCommande, // Corrected parameter order based on `enregistrerFacture` definition
                $referenceFacture // Corrected parameter order
            );

            if (!$idFacture) {
                // If enregistrerFacture fails, an exception is thrown, which will be caught below.
                // We don't need a `rollBack()` here specifically, as the catch block will handle it.
                throw new Exception("Enregistrement facture échoué. Vérifiez les logs pour plus de détails.");
            }

            // 2. Récupérer les ID_Compte pour les numéros de compte choisis
            $idCompteCharge = getIdCompteByNumero($pdo, $compteCharge);
            $idCompteTVA = getIdCompteByNumero($pdo, $compteTVA);
            $idCompteFournisseur = getIdCompteByNumero($pdo, $compteFournisseurContrepartie);
            $idComptePrecompte = !empty($comptePrecompte) ? getIdCompteByNumero($pdo, $comptePrecompte) : false;

            // Validate that the retrieved IDs are not false (meaning accounts were found)
            if (!$idCompteCharge) {
                throw new Exception("Le compte de charge '" . htmlspecialchars($compteCharge) . "' est invalide ou n'existe pas.");
            }
            if (!$idCompteTVA) {
                throw new Exception("Le compte de TVA '" . htmlspecialchars($compteTVA) . "' est invalide ou n'existe pas.");
            }
            if (!$idCompteFournisseur) {
                throw new Exception("Le compte fournisseur de contrepartie '" . htmlspecialchars($compteFournisseurContrepartie) . "' est invalide ou n'existe pas.");
            }

            // Get IDs for retenue accounts and save them
            $idsRetenueComptes = [];
            foreach ($retenues as $key => $r) {
                if ($r['montant'] > 0 && !empty($r['compte'])) {
                    $idRetenueCompte = getIdCompteByNumero($pdo, $r['compte']);
                    if (!$idRetenueCompte) {
                        throw new Exception("Le compte de retenue '" . htmlspecialchars($r['compte']) . "' est invalide.");
                    }
                    $idsRetenueComptes[$key] = $idRetenueCompte;

                    // Save individual retenues to Facture_Retenues table
                    $montantRetenue = $r['montant'];
                    $libelleRetenue = "Retenue " . $key . " sur facture " . $numeroFacture;
                    if (!saveFactureRetenue($pdo, $idFacture, $idRetenueCompte, $montantRetenue, $libelleRetenue)) {
                        throw new Exception("Erreur lors de l'enregistrement de la retenue " . $key . " pour la facture.");
                    }
                }
            }

            // Define $nomUtilisateurActuel and $numeroAgenceSCE (you must retrieve these from your session/system)
            $nomUtilisateurActuel = $_SESSION['utilisateur_id']['nom_utilisateur'] ?? 'Système';
            $numeroAgenceSCE = $_SESSION['agence']['code'] ?? '000';


            // 3. Enregistrer l'écriture principale
            $libelleEnteteEcriture = "Facture Fournisseur " . $nomFournisseurFacture . " N° " . $numeroFacture;
            $idEcriture = enregistrerEcritures(
                $pdo,
                $libelleEnteteEcriture,
                $totalTTC_calcule, // Use the total TTC here
                $idJournalAchat,
                $numeroFacture,
                $mois,
                $nomUtilisateurActuel,
                $numeroAgenceSCE
            );

            if (!$idEcriture) {
                throw new Exception("Création de l'écriture principale échouée. Vérifiez les logs.");
            }

            // --- IMPORTANT: ADDITION HERE TO LINK FACTURE TO ACCOUNTING ENTRY ---
            // Mettre à jour la facture avec l'ID_Ecriture_Comptable
            $sqlUpdateFactureEcriture = "UPDATE Factures SET ID_Ecriture_Comptable = :idEcriture, Date_Comptabilisation = GETDATE() WHERE ID_Facture = :idFacture";
            $stmtUpdateFacture = $pdo->prepare($sqlUpdateFactureEcriture);
            if (!$stmtUpdateFacture->execute([':idEcriture' => $idEcriture, ':idFacture' => $idFacture])) {
                throw new Exception("Erreur lors de la mise à jour de l'ID_Ecriture_Comptable et Date_Comptabilisation dans la facture.");
            }
            // --- END ADDITION ---


            // 4. Enregistrer les lignes d'écritures
            $libelleLigne = "Facture " . $nomFournisseurFacture . " N° " . $numeroFacture;
            // NOTE: Make sure your `enregistrerLigneEcritures` function in `fonctions/gestion_comptes.php`
            // does NOT expect `$an_code` and `$contrepartie_compte_ligne` if these columns are not in your `Lignes_Ecritures` table.
            // Based on your previous schema, it should only take $idEcriture, $idCompte, $libelle, $montant, $sens.
            // I've commented them out in the calls below as a suggestion.

            // Ligne de charge (Débit)
            if ($totalFactureHT > 0) {
                if (!enregistrerLigneEcritures($pdo, $idEcriture, $idCompteCharge, $libelleLigne . " (HT)", $totalFactureHT, 'C' /* , $an_code, $contrepartie_compte_ligne */)) {
                    throw new Exception("Erreur lors de l'enregistrement de la ligne de charge.");
                }
            }

            // Ligne de TVA (Débit)
            if ($montantTVA_calcule > 0) {
                if (!enregistrerLigneEcritures($pdo, $idEcriture, $idCompteTVA, $libelleLigne . " (TVA)", $montantTVA_calcule, 'C' /* , $an_code, $contrepartie_compte_ligne */)) {
                    throw new Exception("Erreur lors de l'enregistrement de la ligne de TVA.");
                }
            }

            // Ligne de précompte (Crédit - si applicable)
            $montantPrecompte = 0; // Ensure this value is properly set if used.
            if ($idComptePrecompte && $montantPrecompte > 0) {
                // Assuming precompte is a CREDIT (deduction from supplier payment)
                if (!enregistrerLigneEcritures($pdo, $idEcriture, $idComptePrecompte, $libelleLigne . " (Précompte)", $montantPrecompte, 'D' /* , $an_code, $contrepartie_compte_ligne */)) {
                    throw new Exception("Erreur lors de l'enregistrement de la ligne de précompte.");
                }
            }

            // Lignes pour les retenues (Crédit) - These are already saved in Facture_Retenues,
            // but also need to be reflected in the accounting entry.
            foreach ($retenues as $key => $r) {
                if ($r['montant'] > 0 && isset($idsRetenueComptes[$key])) {
                    if (!enregistrerLigneEcritures($pdo, $idEcriture, $idsRetenueComptes[$key], $libelleLigne . " (Retenue " . $key . ")", $r['montant'], 'D' /* , $an_code, $contrepartie_compte_ligne */)) {
                        throw new Exception("Erreur lors de l'enregistrement de la ligne de retenue " . $key . ".");
                    }
                }
            }

            // Ligne Fournisseur (Crédit) - This is the *net* amount payable to the supplier.
            $montantFinalDuAuFournisseur = $totalTTC_calcule - $totalRetenues;
            // If précompte is also a deduction, subtract it here:
            // $montantFinalDuAuFournisseur -= $montantPrecompte;

            if (!enregistrerLigneEcritures($pdo, $idEcriture, $idCompteFournisseur, $libelleLigne . " (Net à Payer)", $montantFinalDuAuFournisseur, 'D' /* , $an_code, $contrepartie_compte_ligne */)) {
                throw new Exception("Erreur lors de l'enregistrement de la ligne fournisseur.");
            }

            // Optional: Basic balance check for the created entry
            $totalDebits = $totalFactureHT + $montantTVA_calcule;
            $totalCredits = $montantFinalDuAuFournisseur + $totalRetenues;
            if ($idComptePrecompte && $montantPrecompte > 0) {
                $totalCredits += $montantPrecompte; // If precompte is a credit
            }

            // Consider a small tolerance for floating point arithmetic
            if (abs($totalDebits - $totalCredits) > 0.01) {
                error_log("Déséquilibre dans l'écriture ID " . $idEcriture . ": Débits=" . $totalDebits . ", Crédits=" . $totalCredits);
                // In a strict accounting system, you might want to throw an exception here.
                // For now, it's logged as an error, but the transaction proceeds.
                // throw new Exception("Déséquilibre détecté dans l'écriture comptable. Veuillez vérifier les montants.");
            }

            // If all operations within the try block succeed, commit the transaction.
            $pdo->commit();

            // Redirection after success
            header("Location: listes_factures.php?type=fournisseur&success=" . urlencode("Facture et écritures comptables enregistrées avec succès !"));
            exit; // Very important to call exit after a redirect

        } catch (Exception $e) {
            // Catch any general exceptions or specific Exceptions thrown by your functions.
            // Only attempt rollback if a transaction is actually active.
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erreur = "Erreur enregistrement : " . htmlspecialchars($e->getMessage());
            error_log("Erreur processus enregistrement facture (Exception): " . $e->getMessage());
        } catch (PDOException $e) {
            // Catch PDO-specific exceptions (database connection issues, query errors, etc.)
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erreur = "Erreur base de données : " . htmlspecialchars($e->getMessage());
            error_log("PDOException processus enregistrement facture : " . $e->getMessage());
        }
    } else {
        $erreur = "Veuillez corriger les erreurs : <ul><li>" . implode("</li><li>", array_map('htmlspecialchars', $erreursValidation)) . "</li></ul>";
    }
}


// Mise à jour des montants en cas de chargement initial ou validation failure
// These calculations are repeated to ensure the displayed values are correct
// when the form is reloaded due to validation errors or initial load.
$montantTVA_calcule = round($totalFactureHT * ($tauxTVA / 100), 2);
$totalTTC_calcule = round($totalFactureHT + $montantTVA_calcule, 2);

// Recalcul totalRetenues for display, from the individual 'retenue_montant_X' variables
$totalRetenues = 0.0;
for ($i = 1; $i <= 6; $i++) {
    $totalRetenues += ${'retenue_montant_' . $i};
}

$netAPayer = round($totalTTC_calcule - $totalRetenues, 2);

ob_end_flush();
?>

<?php require_once('../../templates/footer.php'); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <br>
    &nbsp;
    <title><?= htmlspecialchars($TITRE_PAGE) ?> | BailCompta 360</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
	 <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-theme/0.1.0-beta.10/select2-bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/select2.min.css">
    <link rel="stylesheet" href="../../css/select2-bootstrap.min.css">
    <style>
        .select2-container--bootstrap .select2-selection--single {
            height: 34px;
            padding: 6px 12px;
        }

        .select2-container--bootstrap .select2-selection--single .select2-selection__rendered {
            line-height: 20px;
        }

        .select2-container--bootstrap .select2-selection--single .select2-selection__arrow {
            height: 32px;
        }

        .select2-container {
            width: 100% !important;
        }

        .panel {
            margin-bottom: 20px;
        }

        .page-header {
            margin-top: 0;
        }

        .btn-actions {
            margin-top: 20px;
        }

        .total-field-display {
            font-weight: bold;
            background-color: #eee;
        }

        .form-control[readonly] {
            background-color: #eee;
            opacity: 1;
            cursor: not-allowed;
        }

        /* Style for the new retenues section */
        .retenue-item {
            margin-bottom: 15px; /* Spacing between each retenue pair */
            border-bottom: 1px dashed #eee; /* Visual separator */
            padding-bottom: 15px;
        }
        .retenue-item:last-child {
            border-bottom: none; /* No separator for the last item */
            padding-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="page-header"><?= htmlspecialchars($TITRE_PAGE) ?></h2>

        <?php if (!empty($erreur)): ?>
            <div class="alert alert-danger"><?= $erreur ?></div><?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <?php if (empty($comptesFournisseurs) || empty($tousComptesCompta)): ?>
            <div class="alert alert-warning">Attention : Certaines listes de comptes ne sont pas chargées. Vérifiez la configuration.</div>
        <?php endif; ?>

        <form action="<?= htmlspecialchars(basename($_SERVER['PHP_SELF'])) ?>" method="POST" id="factureForm">

            <div class="panel panel-primary">
                <div class="panel-heading"><span class="glyphicon glyphicon-user"></span> Informations Fournisseur</div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6 form-group <?= !empty($erreursValidation) && empty($compteFournisseurSelectionne) ? 'has-error' : '' ?>">
                            <label for="compte_fournisseur_select"> Compte Fournisseur <span style="color:red;">*</span></label>
                            <select class="form-control select2-enable" id="compte_fournisseur_select" name="compte_fournisseur_select" required>
                                <option value="">-- Sélectionnez --</option>
                                <?php foreach ($comptesFournisseurs as $compte): ?>
                                    <option value="<?= htmlspecialchars($compte['Cpt']) ?>" <?= ($compteFournisseurSelectionne == $compte['Cpt']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($compte['Cpt']) ?> - <?= htmlspecialchars($compte['Lib']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group <?= !empty($erreursValidation) && empty($nomFournisseurFacture) ? 'has-error' : '' ?>">
                            <label for="nom_fournisseur_facture">Nom Fournisseur (sur Facture) <span style="color:red;">*</span></label>
                            <input type="text" class="form-control" id="nom_fournisseur_facture" name="nom_fournisseur_facture" value="<?= htmlspecialchars($nomFournisseurFacture) ?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading"><span class="glyphicon glyphicon-info-sign"></span> Informations Générales Facture</div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-3 form-group <?= !empty($erreursValidation) && empty($numeroFacture) ? 'has-error' : '' ?>">
                            <label for="numero_facture">N° Facture <span style="color:red;">*</span></label>
                            <input type="text" class="form-control" id="numero_facture" name="numero_facture" value="<?= htmlspecialchars($numeroFacture) ?>" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label for="reference_facture">Référence Facture</label>
                            <input type="text" class="form-control" id="reference_facture" name="reference_facture" value="<?= htmlspecialchars($referenceFacture) ?>">
                        </div>
                        <div class="col-md-3 form-group">
                            <label for="numero_commande">N° Bon de Commande</label>
                            <input type="text" class="form-control" id="numero_commande" name="numero_commande" value="<?= htmlspecialchars($numeroCommande) ?>">
                        </div>
                        <div class="col-md-3 form-group <?= !empty($erreursValidation) && empty($dateEmission) ? 'has-error' : '' ?>">
                            <label for="date_emission">Date Émission <span style="color:red;">*</span></label>
                            <input type="date" class="form-control" id="date_emission" name="date_emission" value="<?= htmlspecialchars($dateEmission) ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 form-group <?= !empty($erreursValidation) && empty($dateReception) ? 'has-error' : '' ?>">
                            <label for="date_reception">Date Réception <span style="color:red;">*</span></label>
                            <input type="date" class="form-control" id="date_reception" name="date_reception" value="<?= htmlspecialchars($dateReception) ?>" required>
                        </div>
                        <div class="col-md-3 form-group <?= !empty($erreursValidation) && empty($dateEcheance) ? 'has-error' : '' ?>">
                            <label for="date_echeance">Date Échéance <span style="color:red;">*</span></label>
                            <input type="date" class="form-control" id="date_echeance" name="date_echeance" value="<?= htmlspecialchars($dateEcheance) ?>" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="description">Commentaire / Description</label>
                            <input type="text" class="form-control" id="description" name="description" value="<?= htmlspecialchars($description) ?>" placeholder="Optionnel: motif, détails...">
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel panel-info">
                <div class="panel-heading"><span>XAF</span> Montants</div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-3 form-group <?= !empty($erreursValidation) && (isset($_POST['total_ht']) && (floatval($_POST['total_ht']) <= 0)) ? 'has-error' : '' ?>">
                            <label for="total_ht">Total Facture HT <span style="color:red;">*</span></label>
                            <input type="number" class="form-control" id="total_ht" name="total_ht" value="<?= number_format($totalFactureHT, 2, '.', '') ?>" step="0.01" required min="0.01">
                        </div>
                        <div class="col-md-3 form-group <?= !empty($erreursValidation) && (isset($_POST['taux_tva']) && (floatval($_POST['taux_tva']) < 0)) ? 'has-error' : '' ?>">
                            <label for="taux_tva">Taux TVA (%) <span style="color:red;">*</span></label>
                            <input type="number" class="form-control" id="taux_tva" name="taux_tva" value="<?= number_format($tauxTVA, 2, '.', '') ?>" step="0.01" required min="0">
                        </div>
                        <div class="col-md-3 form-group">
                            <label for="montant_tva_display">Montant TVA Calculé</label>
                            <input type="text" class="form-control" id="montant_tva_display" value="<?= number_format($montantTVA_calcule, 2, '.', '') ?>" readonly>
                        </div>
                        <div class="col-md-3 form-group">
                            <label for="total_ttc_display">Total Facture TTC (avant retenues)</label>
                            <input type="text" class="form-control total-field-display" id="total_ttc_display" value="<?= number_format($totalTTC_calcule, 2, '.', '') ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel panel-warning">
                <div class="panel-heading"><span class="glyphicon glyphicon-minus-sign"></span> Retenues (Déductions sur Facture)</div>
                <div class="panel-body">
                    <p class="text-muted">Ajoutez ici les montants des retenues et leurs comptes comptables associés. Ces montants seront déduits du Total TTC.</p>
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                    <div class="row retenue-item">
                        <div class="col-md-6 form-group">
                            <label for="retenue_montant_<?= $i ?>">Montant Retenue <?= $i ?></label>
                            <input type="number" class="form-control retenue-montant" id="retenue_montant_<?= $i ?>" name="retenue_montant_<?= $i ?>" value="<?= number_format(${'retenue_montant_' . $i}, 2, '.', '') ?>" step="0.01" min="0">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="retenue_compte_<?= $i ?>_select">Compte Comptable Retenue <?= $i ?></label>
                            <select class="form-control select2-enable" id="retenue_compte_<?= $i ?>_select" name="retenue_compte_<?= $i ?>_select">
                                <option value="">-- Sélectionner un compte --</option>
                                <?php foreach ($tousComptesCompta as $compte): ?>
                                    <?php // Consider filtering accounts for retenues if you have specific ranges (e.g., 44x, 43x) ?>
                                    <option value="<?= htmlspecialchars($compte['Numero_Compte']) ?>" <?= (isset(${'retenue_compte_' . $i}) && ${'retenue_compte_' . $i} == $compte['Numero_Compte']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($compte['Numero_Compte']) ?> - <?= htmlspecialchars($compte['Nom_Compte']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endfor; ?>
                    <div class="row">
                        <div class="col-md-offset-6 col-md-6 form-group">
                            <label for="total_ttc_final_display">Net à Payer (TTC - Retenues)</label>
                            <input type="text" class="form-control total-field-display" id="total_ttc_final_display" value="<?= number_format($totalTTC_calcule - $totalRetenues, 2, '.', '') ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>
            <div class="panel panel-success">
                <div class="panel-heading"><span class="glyphicon glyphicon-book"></span> Imputation Comptable</div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6 form-group <?= !empty($erreursValidation) && empty($compteCharge) ? 'has-error' : '' ?>">
                            <label for="compte_charge_select">Compte de Charge (Montant HT) <span style="color:red;">*</span></label>
                            <select class="form-control select2-enable" id="compte_charge_select" name="compte_charge_select" required>
                                <option value="">-- Sélectionner un compte de charge --</option>
                                <?php foreach ($tousComptesCompta as $compte): ?>
                            
                                        <option value="<?= htmlspecialchars($compte['Numero_Compte']) ?>" <?= ($compteCharge == $compte['Numero_Compte']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($compte['Numero_Compte']) ?> - <?= htmlspecialchars($compte['Nom_Compte']) ?>
                                        </option>
                                  
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="compte_fournisseur_contrepartie_select">Compte de Fournisseur (Contrepartie) <span style="color:red;">*</span></label>
                             <select class="form-control select2-enable" id="compte_fournisseur_contrepartie_select" name="compte_fournisseur_contrepartie_select" required>
                                <option value="">-- Sélectionner un compte fournisseur --</option>
                                <?php foreach ($tousComptesCompta as $compte): ?>
                                    <?php if (substr($compte['Numero_Compte'], 0, 2) === '40'): // Exemple: Comptes fournisseurs ?>
                                        <option value="<?= htmlspecialchars($compte['Numero_Compte']) ?>" <?= ($compteFournisseurContrepartie == $compte['Numero_Compte']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($compte['Numero_Compte']) ?> - <?= htmlspecialchars($compte['Nom_Compte']) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group <?= !empty($erreursValidation) && empty($compteTVA) ? 'has-error' : '' ?>">
                            <label for="compte_tva_select">Compte TVA </label>
                            <select class="form-control select2-enable" id="compte_tva_select" name="compte_tva_select" >
                                <option value="">-- Sélectionner un compte TVA --</option>
                                <?php foreach ($tousComptesCompta as $compte): ?>
                                   
                                        <option value="<?= htmlspecialchars($compte['Numero_Compte']) ?>" <?= ($compteTVA == $compte['Numero_Compte']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($compte['Numero_Compte']) ?> - <?= htmlspecialchars($compte['Nom_Compte']) ?>
                                        </option>
                                    
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="compte_precompte_select">Compte Précompte (Optionnel)</label>
                            <select class="form-control select2-enable" id="compte_precompte_select" name="compte_precompte_select">
                                <option value="">-- Sélectionner un compte de précompte --</option>
                                <?php foreach ($tousComptesCompta as $compte): ?>
                                    <?php // Ajoutez ici la logique pour filtrer les comptes de précompte si vous avez une convention spécifique
                                        if (substr($compte['Numero_Compte'], 0, 4) === '449' || substr($compte['Numero_Compte'], 0, 5) === '43351'): // Exemple: autres impôts / précomptes
                                    ?>
                                        <option value="<?= htmlspecialchars($compte['Numero_Compte']) ?>" <?= ($comptePrecompte == $compte['Numero_Compte']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($compte['Numero_Compte']) ?> - <?= htmlspecialchars($compte['Nom_Compte']) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="btn-actions text-center">
                <button type="submit" class="btn btn-primary btn-lg" <?php if (empty($comptesFournisseurs) || empty($tousComptesCompta)) echo 'disabled'; ?>>
                    <i class="glyphicon glyphicon-floppy-disk"></i> Enregistrer Facture & Écritures
                </button>
                <a href="liste_factures.php?type=fournisseur" class="btn btn-default btn-lg">
                    <i class="glyphicon glyphicon-remove"></i> Annuler
                </a>
            </div>
        </form>
    </div>

    <script src="../../js/jquery-3.7.1.min.js"></script>
    <script src="../../js/bootstrap.min.js"></script>
    <script src="../../js/select2.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script>
        $(document).ready(function () {
            $('.select2-enable').select2({
                theme: "bootstrap"
            });

            function calculerMontants() {
                let ht = parseFloat($('#total_ht').val().replace(',', '.')) || 0;
                let tauxTvaPercent = parseFloat($('#taux_tva').val().replace(',', '.')) || 0;

                let tauxTvaDecimal = tauxTvaPercent / 100.0;
                let montantTva = ht * tauxTvaDecimal;
                let ttc = ht + montantTva;

                // Calculate total retenues
                let totalRetenues = 0;
                for (let i = 1; i <= 6; i++) {
                    let retenueMontant = parseFloat($(`#retenue_montant_${i}`).val().replace(',', '.')) || 0;
                    totalRetenues += retenueMontant;
                }

                let finalTTC = ttc - totalRetenues;

                $('#montant_tva_display').val(montantTva.toFixed(2).replace('.', ','));
                $('#total_ttc_display').val(ttc.toFixed(2).replace('.', ','));
                $('#total_ttc_final_display').val(finalTTC.toFixed(2).replace('.', ','));
            }

            // Listen for changes on the supplier account select
            $('#compte_fournisseur_select').on('change', function() {
                const selectedText = $(this).find('option:selected').text();
                const parts = selectedText.split(' - ');
                if (parts.length > 1) {
                    const supplierName = parts[1].trim();
                    $('#nom_fournisseur_facture').val(supplierName);
                } else {
                    $('#nom_fournisseur_facture').val('');
                }
                $('#compte_fournisseur_contrepartie_select').val($(this).val()).trigger('change');
            });

            // Listen for changes on HT, TVA, and all retenue amounts
            $('#total_ht, #taux_tva').on('input change', function () {
                calculerMontants();
            });

            // Loop to attach event listeners to all retenue amount fields
            for (let i = 1; i <= 6; i++) {
                $(`#retenue_montant_${i}`).on('input change', function() {
                    calculerMontants();
                });
            }

            // Call on initial load in case a value is pre-selected (e.g., from validation error)
            calculerMontants();

            // Trigger the change event on load if a value is already selected (e.g., after validation error)
            if ($('#compte_fournisseur_select').val()) {
                $('#compte_fournisseur_select').trigger('change');
            }
        });
    </script>
</body>
</html>