<?php
// pages/ecritures/saisie.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


// Configuration d'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
setlocale(LC_TIME, 'fr_FR', 'fr_FR.utf8', 'fr');
$titre = 'Saisie des ecritures Comptables';
$current_page = basename($_SERVER['PHP_SELF']);

require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_comptes.php';
require_once '../../fonctions/gestion_journaux.php';
require_once '../../fonctions/gestion_ecritures.php';
require_once '../../fonctions/gestion_agences.php'; // <<< NOUVEAU : Include the agencies functions

if (!isset($pdo) || !$pdo instanceof PDO) {
    // Id�alement, assurez-vous que database.php configure PDO pour lancer des exceptions
    // $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("ERREUR FATALE: \$pdo n'a pas ete initialisé par database.php dans saisie.php");
    die("Une erreur critique de configuration de la base de donnees est survenue. Veuillez contacter l'administrateur.");
}

$journaux = getListeJournaux($pdo);
$GLOBALS['allComptesPLN'] = getAllComptesComptac($pdo);
// <<< NOUVEAU : Fetch the list of agencies
$agences = getAllAgences($pdo);

// Initialisation des variables pour pr�-remplir le formulaire (si n�cessaire) ou pour les valeurs POST
$selected_journal_cde = isset($_POST['code_journal']) ? $_POST['code_journal'] : '';


$selected_periode_mois = isset($_POST['periode_mois']) ? $_POST['periode_mois'] : date('m');
$selected_periode_annee = isset($_POST['periode_annee']) ? $_POST['periode_annee'] : date('Y');

 $dateTime = new DateTime();
    // Subtract 1 hour
    $dateTime->modify('-1 hour');
    $date_piece = $dateTime->format('Y-m-d H:i:s'); // Include time
$numero_piece = isset($_POST['numero_piece']) ? $_POST['numero_piece'] : '';
$libelle_general = isset($_POST['libelle_general']) ? $_POST['libelle_general'] : '';
$nom_utilisateur = isset($_SESSION['nom_utilisateur']) ? $_SESSION['nom_utilisateur'] : 'SYSTEM';

$lignes_post = []; // Pour re-afficher les lignes en cas d'erreur de validation
$erreur = null;
$success = null; // Pas utilisé directement ici mais peut etre utile pour d'autres contextes
$libelle2 = '';  // Ou une valeur par defaut, selon vos besoins

// --- DéBUT DU TRAITEMENT DU FORMULAIRE POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Recuperation des donnees d'en-tete
    $code_journal_cde = trim(isset($_POST['code_journal']) ? $_POST['code_journal'] : '');
$periode_mois = trim(isset($_POST['periode_mois']) ? $_POST['periode_mois'] : date('m'));
$periode_annee = trim(isset($_POST['periode_annee']) ? $_POST['periode_annee'] : date('Y'));
$date_piece = trim(isset($_POST['date_piece']) ? $_POST['date_piece'] : '');
$numero_piece = trim(isset($_POST['numero_piece']) ? $_POST['numero_piece'] : '');
$libelle_general = trim(isset($_POST['libelle_general']) ? $_POST['libelle_general'] : '');
$selected_an_array = isset($_POST['an']) ? $_POST['an'] : [];



    // Donc votre ligne deviendrait (en supposant que vous voulez la premiere valeur) :
    $selected_an = isset($_POST['an'][0]) ? trim($_POST['an'][0]) : '';

    $mois_comptable = $periode_annee . '-' . str_pad($periode_mois, 2, '0', STR_PAD_LEFT);

    // Rececuperation des donnees des lignes
   $comptes = isset($_POST['compte']) ? $_POST['compte'] : [];
$libelles_ligne = isset($_POST['libelle_ligne']) ? $_POST['libelle_ligne'] : [];
$debits = isset($_POST['debit']) ? $_POST['debit'] : [];
$credits = isset($_POST['credit']) ? $_POST['credit'] : [];
$ans = isset($_POST['an']) ? $_POST['an'] : [];
$contreparties = isset($_POST['contrepartie']) ? $_POST['contrepartie'] : [];


    $lignes_a_enregistrer = [];
    $total_debit_calc = 0;
    $total_credit_calc = 0;

    // Validation des champs d'en-tete obligatoires
    $erreurs_entete_details = [];
    if ($code_journal_cde === '') {
        $erreurs_entete_details[] = "Journal";
    }
    if (empty($date_piece)) {
        $erreurs_entete_details[] = "Date Pièce";
    }
    if ($numero_piece === '') {
        $erreurs_entete_details[] = "Numero Pièce";
    }
    if (empty($libelle_general)) {
        $erreurs_entete_details[] = "Libellé Général";
    }

    if (!empty($erreurs_entete_details)) {
        $erreur = "Les informations générales suivantes sont obligatoires : " . implode(", ", $erreurs_entete_details) . ".";
    } else {
        // Traitement et validation des lignes d'écriture
        for ($i = 0; $i < count($comptes); $i++) {
            $compte_cpt = trim(isset($comptes[$i]) ? $comptes[$i] : '');
$libelle_ligne_item = trim(isset($libelles_ligne[$i]) ? $libelles_ligne[$i] : '');
$debit_str = isset($debits[$i]) ? trim($debits[$i]) : '';
$credit_str = isset($credits[$i]) ? trim($credits[$i]) : '';
$an = isset($ans[$i]) ? trim($ans[$i]) : '';
$contrepartie = isset($contreparties[$i]) ? trim($contreparties[$i]) : '';


            $debit_item = !empty($debit_str) ? (float)str_replace(',', '.', $debit_str) : 0;
            $credit_item = !empty($credit_str) ? (float)str_replace(',', '.', $credit_str) : 0;

            // Pour réafficher les données en cas d'erreur sur une autre ligne ou sur l'équilibre
            $lignes_post[] = [
                'compte' => $compte_cpt,
                'libelle_ligne' => $libelle_ligne_item,
                'debit' => $debit_str,
                'credit' => $credit_str,
                'an' => $an,
                'contrepartie' => $contrepartie
            ];

            // Une ligne est considérée comme "active" si au moins un champ est rempli (compte, libellé, débit ou crédit)
            if (!empty($compte_cpt) || !empty($libelle_ligne_item) || $debit_item > 0 || $credit_item > 0) {
                if (empty($compte_cpt)) {
                    $erreur = "Erreur sur la ligne " . ($i + 1) . " : Le numero de compte est manquant.";
                    break;
                }
                if (empty($libelle_ligne_item)) { // Libellé de ligne maintenant obligatoire pour une ligne active
                    $erreur = "Erreur sur la ligne " . ($i + 1) . " : Le libelle de ligne est manquant pour le compte " . htmlspecialchars($compte_cpt) . ".";
                    break;
                }
                if ($debit_item == 0 && $credit_item == 0) {
                    $erreur = "Erreur sur la ligne " . ($i + 1) . " : Le montant (debit ou credit) est manquant ou nul pour le compte " . htmlspecialchars($compte_cpt) . ".";
                    break;
                }
                if ($debit_item > 0 && $credit_item > 0) {
                    $erreur = "Erreur sur la ligne " . ($i + 1) . " : Vous ne pouvez pas saisir un montant au debit ET au credit pour la meme ligne.";
                    break;
                }

                // Si la ligne est valide, l'ajouter au tableau des lignes � enregistrer
                if ($debit_item > 0) {
                    $lignes_a_enregistrer[] = [
                        'compte_cpt' => $compte_cpt,
                        'libelle_ligne' => $libelle_ligne_item,
                        'montant' => $debit_item,
                        'sens' => 'D',
                        'an' => $an,
                        'contrepartie' => $contrepartie
                    ];
                    $total_debit_calc += $debit_item;
                } elseif ($credit_item > 0) {
                    $lignes_a_enregistrer[] = [
                        'compte_cpt' => $compte_cpt,
                        'libelle_ligne' => $libelle_ligne_item,
                        'montant' => $credit_item,
                        'sens' => 'C',
                        'an' => $an,
                        'contrepartie' => $contrepartie
                    ];
                    $total_credit_calc += $credit_item;
                }
            }
        } // Fin de la boucle for pour les lignes
    } // Fin du else de validation d'en-tete

    // Si aucune erreur de validation d'en-tete ou de ligne n'a ete trouvee jusqu'ici
    if (!$erreur) {
        if (empty($lignes_a_enregistrer)) {
            $erreur = "Aucune ligne d'ecriture valide n'a été saisie.";
        } elseif (abs($total_debit_calc - $total_credit_calc) > 0.001) { // Tolerance pour les floats
            $erreur = "La pièce n'est pas équilibrée. Total Débit: " . number_format($total_debit_calc, 2, ',', ' ') . ", Total Crédit: " . number_format($total_credit_calc, 2, ',', ' ') . ".";
        } else {
            // Toutes les validations sont pass�es, on peut tenter d'enregistrer
            try {
                // Appel de la fonction enregistrerEcriture qui retourne un tableau détaillé
               $resultatEnregistrement = enregistrerEcriture(
    $pdo,
    $date_piece,           // Date de saisie
    $libelle_general,      // Description
    $total_debit_calc,     // Montant total
    $code_journal_cde,     // ID du journal
    $code_journal_cde,     // Cde du journal
	$numero_piece,   
    $mois_comptable,	// Libellé 2 (optionnel)
    $nom_utilisateur,       // Mois
	$libelle2,
    $selected_an         // Numéro d'agence (optionnel)
	
	
);


                // Vérifier le statut et l'ID dans le tableau retourné
                if (isset($resultatEnregistrement['status']) && $resultatEnregistrement['status'] === true && !empty($resultatEnregistrement['id']) && is_numeric($resultatEnregistrement['id'])) {

                    $idEcritureNumerique = (int)$resultatEnregistrement['id']; // $idEcritureNumerique EST MAINTENANT UN ENTIER

                    // L'en-t�te est enregistré, on enregistre les lignes
                    foreach ($lignes_a_enregistrer as $ligne) {
                        // S'assurer que enregistrerLigneEcriture est appelée avec les bons types et nombre d'arguments
                        enregistrerLigneEcriture(
                            $pdo,
                            $idEcritureNumerique,       // L'ID entier de l'écriture
                            (int)$ligne['compte_cpt'], // L'ID entier du compte
                            (string)$ligne['libelle_ligne'], // Le libellé de la ligne
                            (float)$ligne['montant'],
                            (string)$ligne['sens'],
                            (string)$ligne['an'], // Passing the agency code
                            (string)$ligne['contrepartie'] // Passing the contra account code
                        );
                    }
                    $_SESSION['success_message'] = "L'ecriture Numero" . htmlspecialchars($numero_piece) . " (ID: " . $idEcritureNumerique . ") a été enregistrée avec succès.";
                    header('Location: liste.php?success=1&id=' . $idEcritureNumerique);
                    exit();

                } else { // $resultatEnregistrement['status'] est false ou ID manquant/invalide
                    $erreur = "Erreur système lors de l'enregistrement de l'en-tete de l'ecriture. ";
                    if (!empty($resultatEnregistrement['error'])) {
                        $erreur .= "Détail : " . $resultatEnregistrement['error'];
                    } else {
                        $erreur .= "L'ID d'ecriture n'a pas été retourné ou est invalide, et aucun détail d'erreur spécifique n'est disponible.";
                    }

                    // Afficher les informations de debogage si presentes
                    if (!empty($resultatEnregistrement['debug_info'])) {
                        $erreur .= "<br><br><strong>--- Informations de Debogage (de enregistrerEcriture) ---</strong>";
                        $erreur .= "<pre style='background-color:#f5f5f5; border:1px solid #ddd; padding:10px; text-align:left; font-size:0.9em;'>";
                        if(is_array($resultatEnregistrement['debug_info'])) {
                            foreach ($resultatEnregistrement['debug_info'] as $msg) {
                                $erreur .= htmlspecialchars($msg) . "\n";
                            }
                        } else { // Au cas ou debug_info ne serait pas un tableau de messages
                            $erreur .= htmlspecialchars(print_r($resultatEnregistrement['debug_info'], true));
                        }
                        // Afficher les details de l'exception PDO si capturee par enregistrerEcriture
                        if (!empty($resultatEnregistrement['debug_info_exception'])) {
                            $erreur .= "\nInformations Exception PDO (errorInfo) :\n" . htmlspecialchars(print_r($resultatEnregistrement['debug_info_exception'], true));
                        }
                        $erreur .= "</pre>";
                    }
                }
            } catch (PDOException $e) {
                $erreur = "Erreur PDO (interceptée dans saisie.php) : " . htmlspecialchars($e->getMessage());
                // Optionnel: logguer plus de détails si error_log fonctionne pour vous ailleurs
                // error_log("Erreur PDO (saisie.php): " . $e->getMessage() . " // POST Data: " . json_encode($_POST));
            } catch (Exception $e) { // Capturer toute autre exception, y compris InvalidArgumentException si elle était levée
                $erreur = "Erreur Generale (interceptee dans saisie.php) : " . htmlspecialchars($e->getMessage());
            }
        }
    }
} // --- FIN DU TRAITEMENT DU FORMULAIRE POST ---

require_once('../../templates/header.php');
require_once('../../templates/navigation.php');

// Le reste de votre fichier HTML (affichage du formulaire, des erreurs, etc.) reste identique.
// Assurez-vous que la variable $erreur est bien affichée dans votre HTML.
?>


<?php if ($erreur): ?>
    <div class="alert alert-danger alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <strong>Erreur !</strong> <?php echo $erreur; // $erreur peut contenir du HTML, donc pas de htmlspecialchars ici si vous voulez que <br> et <pre> fonctionnent ?>
    </div>
<?php endif; ?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?= htmlspecialchars($titre) ?></title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/monstyle.css">
    <link rel="stylesheet" href="../../css/formulaire.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<style>
        .entry-line { margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; background-color: #f9f9f9; }
        .entry-line .form-group { margin-bottom: 5px; }
        .totals-display { margin-top: 20px; padding: 15px; background-color: #e9ecef; border-radius: 4px; }
        .totals-display strong { font-size: 1.1em; }
        .balance-error { color: red; font-weight: bold; }
        .balance-ok { color: green; font-weight: bold; }
        .form-row { margin-left: -5px; margin-right: -5px; }
        .form-row > [class*="col-"] { padding-left: 5px; padding-right: 5px; }
        .btn-xs { padding: 1px 5px; font-size: 12px; line-height: 1.5; border-radius: 3px;}
        label { margin-bottom: 2px; font-size:13px; }
        .page-header { margin-top: 0; }
        /* Added styles for the new columns */
        @media (min-width: 992px) { /* Medium devices and up */
            .col-md-1 { width: 8.33333333%; } /* Adjust if needed to fit */
            .col-md-2 { width: 16.66666667%; }
            .col-md-3 { width: 25%; }
        }
    </style>
    <style>
        /* Styles pour les sélecteurs */
        select.form-control {
            height: 40px;
            font-size: 14px;
            padding: 8px 12px;
        }

        /* Amélioration de la lisibilité */
        .form-control {
            border-radius: 4px;
            border: 1px solid #ccc;
        }

        /* Contraste amélioré pour les options */
        select option {
            padding: 8px;
            background: white;
            color: #333;
        }

        /* Style pour les petits ecrans */
        @media (max-width: 768px) {
            select.form-control {
                font-size: 16px; /* Plus grand sur mobile */
            }
        }

        /* Style pour les caracteres speciaux */
        .special-chars {
            font-family: Arial, sans-serif; /* Police qui gere bien les caracteres speciaux */
        }
    </style>
</head>
<body>


<div class="container">
    <div class="page-header">
        <h1><?php echo htmlspecialchars($titre); ?></h1>
    </div>

    <?php if ($erreur): ?>
        <div class="alert alert-danger alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <strong>Erreur !</strong> <?php echo htmlspecialchars($erreur); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <?php echo htmlspecialchars($_SESSION['success_message']); ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <form id="form-saisie-ecriture" action="saisie.php" method="POST">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title">Informations G&eacuten&eacuterales de la Pi&egravece</h3>
            </div>
            <div class="panel-body">
                <div class="row form-row">
                    <div class="col-md-3 col-sm-6">
                       <?php
// Simulate fetching journaux data from a database or other source
// In a real application, $journaux would come from your database
$journaux = [
    ['Cde' => '0', 'Lib' => 'JOURNAL D\'OUVERTURE', 'Typ' => 'Op.DIVERS', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '1', 'Lib' => 'JOURNAL PRODUITS SCE BANQUE', 'Typ' => 'OP.DIVERS', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '2', 'Lib' => 'MARCHANDISES LOCALES', 'Typ' => 'OP.DIVERS', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '3', 'Lib' => 'FACT.F.G.I ACHATS DIVERS', 'Typ' => 'OP.DIVERS', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '4', 'Lib' => 'JOURNAL DES SALAIRES', 'Typ' => 'OP.DIVERS', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '5', 'Lib' => 'ACCEPTATION DES TRAITES', 'Typ' => 'OP.DIVERS', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '6', 'Lib' => 'REGL. FOURN. PAR CHEQUES', 'Typ' => 'OP.DIVERS', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '7', 'Lib' => 'STOCKS VENDUS', 'Typ' => 'OP.DIVERS', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '8', 'Lib' => 'VENTES MARCHANDISES', 'Typ' => 'OP.DIVERS', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '11', 'Lib' => 'OPERATIONS DIVERSES', 'Typ' => 'OP.DIVERS', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12', 'Lib' => 'MUTATIONS MARCHANDISES', 'Typ' => 'OP.DIVERS', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13', 'Lib' => 'GRATIFICATION 13ème MOIS', 'Typ' => 'Op.DIVERS', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15', 'Lib' => 'CAISSE TRANSFERT EXP. UNION DLA DU 19', 'Typ' => 'TRESORERIE', 'Cpt' => '571913300000', 'NumeroAgence' => '13'],
    ['Cde' => '16', 'Lib' => 'ECRITURES SPECIFIQUES', 'Typ' => 'OP.DIVERS', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '99', 'Lib' => 'B.E.A.C', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '202', 'Lib' => 'B.I.C.E.C BERTOUA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '203', 'Lib' => 'B.C.E .C EBOLOWA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '204', 'Lib' => 'B.I.C.E.C GAROUA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '205', 'Lib' => 'B.I.C.E.C MAROUA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '206', 'Lib' => 'B.I.C.E.C KRIBI', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '207', 'Lib' => 'B.I.C.E.C NGAOUNDERE', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '208', 'Lib' => 'B.I.C.E.C SANGMELIMA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122080', 'NumeroAgence' => '8'],
    ['Cde' => '209', 'Lib' => 'B.I.C.E.C YAOUNDE', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122090', 'NumeroAgence' => '10'],
    ['Cde' => '211', 'Lib' => 'B.I.C.E.C BAMENDA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122110', 'NumeroAgence' => '11'],
    ['Cde' => '212', 'Lib' => 'B.I.C.E.C BUEA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122120', 'NumeroAgence' => '12'],
    ['Cde' => '213', 'Lib' => 'B.I.C.E.C DOUALA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122130', 'NumeroAgence' => '13'],
    ['Cde' => '218', 'Lib' => 'B.I.C.E.C BAFOUSSAM', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122180', 'NumeroAgence' => '18'],
    ['Cde' => '219', 'Lib' => 'B.I.C.E.C DOUALA II', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122131', 'NumeroAgence' => '13'],
    ['Cde' => '300', 'Lib' => 'STE GENERALE PARIS', 'Typ' => 'TRESORERIE', 'Cpt' => '560481502000', 'NumeroAgence' => '10'],
    ['Cde' => '302', 'Lib' => 'S.G.B.C. BERTOUA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413123020', 'NumeroAgence' => '2'],
    ['Cde' => '304', 'Lib' => 'S.G.B.C. GAROUA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413123040', 'NumeroAgence' => '4'],
    ['Cde' => '305', 'Lib' => 'S.G.B.C. MAROUA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413123050', 'NumeroAgence' => '5'],
    ['Cde' => '309', 'Lib' => 'S.G.B.C YAOUNDE', 'Typ' => 'TRESORERIE', 'Cpt' => '560413123090', 'NumeroAgence' => '10'],
    ['Cde' => '311', 'Lib' => 'S.G.B.C. BAMENDA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413123110', 'NumeroAgence' => '11'],
    ['Cde' => '313', 'Lib' => 'S.G.B.C DOUALA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413123130', 'NumeroAgence' => '13'],
    ['Cde' => '318', 'Lib' => 'S.G.B.C. BAFOUSSAM', 'Typ' => 'TRESORERIE', 'Cpt' => '560413123180', 'NumeroAgence' => '18'],
    ['Cde' => '323', 'Lib' => 'S.G.B.C. LOUM', 'Typ' => 'TRESORERIE', 'Cpt' => '560413123230', 'NumeroAgence' => '9'],
    ['Cde' => '325', 'Lib' => 'S.G.B.C. BELABO', 'Typ' => 'TRESORERIE', 'Cpt' => '560413123250', 'NumeroAgence' => '9'],
    ['Cde' => '326', 'Lib' => 'S.G.B.C. KRIBI', 'Typ' => 'TRESORERIE', 'Cpt' => '560413123260', 'NumeroAgence' => '6'],
    ['Cde' => '403', 'Lib' => 'SCB EBOLOWA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413124030', 'NumeroAgence' => '3'],
    ['Cde' => '404', 'Lib' => 'SCB GAROUA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413124040', 'NumeroAgence' => '4'],
    ['Cde' => '405', 'Lib' => 'SCB MAROUA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413124050', 'NumeroAgence' => '5'],
    ['Cde' => '407', 'Lib' => 'SCB NGAOUNDERE', 'Typ' => 'TRESORERIE', 'Cpt' => '560413124070', 'NumeroAgence' => '7'],
    ['Cde' => '409', 'Lib' => 'SCB YAOUNDE', 'Typ' => 'TRESORERIE', 'Cpt' => '560413124090', 'NumeroAgence' => '10'],
    ['Cde' => '411', 'Lib' => 'SCB BAMENDA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413124110', 'NumeroAgence' => '11'],
    ['Cde' => '413', 'Lib' => 'SCB DOUALA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413124130', 'NumeroAgence' => '13'],
    ['Cde' => '419', 'Lib' => 'SCB BAFOUSSAM(new)', 'Typ' => 'TRESORERIE', 'Cpt' => '560413124180', 'NumeroAgence' => '18'],
    ['Cde' => '561', 'Lib' => 'C C C PLC BAMENDA', 'Typ' => 'TRESORERIE', 'Cpt' => '560915010611', 'NumeroAgence' => '11'],
    ['Cde' => '562', 'Lib' => 'CCC PLC BUEA', 'Typ' => 'TREORERIE', 'Cpt' => '560915010612', 'NumeroAgence' => '12'],
    ['Cde' => '563', 'Lib' => 'UBC YDE', 'Typ' => 'TRESORERIE', 'Cpt' => '560413129200', 'NumeroAgence' => '10'],
    ['Cde' => '565', 'Lib' => 'RENAPROV YAOUNDE', 'Typ' => 'TRESORERIE', 'Cpt' => '560413129200', 'NumeroAgence' => '9'],
    ['Cde' => '566', 'Lib' => 'LA REGIONALE NGAOUNDERE', 'Typ' => 'TRESORERIE', 'Cpt' => '560915010307', 'NumeroAgence' => '7'],
    ['Cde' => '567', 'Lib' => 'LA REGIONALE BAMENDA', 'Typ' => 'TRESORERIE', 'Cpt' => '560915010311', 'NumeroAgence' => '11'],
    ['Cde' => '568', 'Lib' => 'FIRST TRUST BAMENDA', 'Typ' => 'TRESORERIE', 'Cpt' => '560915010711', 'NumeroAgence' => '11'],
    ['Cde' => '569', 'Lib' => 'LE CONSERVATEUR YDE', 'Typ' => 'TRESORERIE', 'Cpt' => '560915010709', 'NumeroAgence' => '9'],
    ['Cde' => '570', 'Lib' => 'CCA ABBIA YDE', 'Typ' => 'TRESORERIE', 'Cpt' => '560915010209', 'NumeroAgence' => '10'],
    ['Cde' => '574', 'Lib' => 'BANK ORANGE MONEY', 'Typ' => 'TRESORERIE', 'Cpt' => '560915010901', 'NumeroAgence' => '10'],
    ['Cde' => '575', 'Lib' => 'BANK MOBILE MONEY', 'Typ' => 'TRESORERIE', 'Cpt' => '560915010902', 'NumeroAgence' => '10'],
    ['Cde' => '609', 'Lib' => 'NATIONAL FINANCIAL YDE', 'Typ' => 'TRESORERIE', 'Cpt' => '560413129109', 'NumeroAgence' => '10'],
    ['Cde' => '611', 'Lib' => 'NATIONAL FINANCIAL BDA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413129111', 'NumeroAgence' => '11'],
    ['Cde' => '700', 'Lib' => 'BGFI BANK YAOUNDE', 'Typ' => 'TRESORERIE', 'Cpt' => '560413127090', 'NumeroAgence' => '10'],
    ['Cde' => '709', 'Lib' => 'C.C.P YAOUNDE', 'Typ' => 'TRESORERIE', 'Cpt' => '560514010090', 'NumeroAgence' => '10'],
    ['Cde' => '800', 'Lib' => 'C.B.C BANK YAOUNDE', 'Typ' => 'TRESORERIE', 'Cpt' => '560413128090', 'NumeroAgence' => '10'],
    ['Cde' => '802', 'Lib' => 'BQ ATLANTIQ MONEY G. BTOA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413127802', 'NumeroAgence' => '2'],
    ['Cde' => '803', 'Lib' => 'BQ ATLANTIQ MONEY G. EBWA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413127803', 'NumeroAgence' => '3'],
    ['Cde' => '804', 'Lib' => 'BQ ATLANTIQ MONEY G.GROUA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413127804', 'NumeroAgence' => '4'],
    ['Cde' => '805', 'Lib' => 'BQ ATLANTIQ MONEY G.MROUA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413127805', 'NumeroAgence' => '5'],
    ['Cde' => '806', 'Lib' => 'BQ ATLANTIQ MONEY G. BMDA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413127811', 'NumeroAgence' => '11'],
    ['Cde' => '807', 'Lib' => 'BQ ATLANTIQ MONEY G.NGDER', 'Typ' => 'TRESORERIE', 'Cpt' => '560413127807', 'NumeroAgence' => '7'],
    ['Cde' => '808', 'Lib' => 'BQ ATLANTIQ MONEY G.SAGMA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413127808', 'NumeroAgence' => '8'],
    ['Cde' => '810', 'Lib' => 'ECO BANK', 'Typ' => 'TRESORERIE', 'Cpt' => '560413127100', 'NumeroAgence' => '10'],
    ['Cde' => '811', 'Lib' => 'BANQUE ATLANTIQUE CAM.', 'Typ' => 'TRESORERIE', 'Cpt' => '560413127909', 'NumeroAgence' => '10'],
    ['Cde' => '812', 'Lib' => 'BQ ATLANTIQ MONEY G. BUEA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413127812', 'NumeroAgence' => '12'],
    ['Cde' => '813', 'Lib' => 'BQ ATLANTIQ MONEY G. DLA', 'Typ' => 'TRESORERIE', 'Cpt' => '560413127813', 'NumeroAgence' => '13'],
    ['Cde' => '814', 'Lib' => 'MONEYGRAM BANQU ATLAN YDE', 'Typ' => 'TRESORERIE', 'Cpt' => '560413127820', 'NumeroAgence' => '9'],
    ['Cde' => '815', 'Lib' => 'UBA YAOUNDE', 'Typ' => 'TRESORERIE', 'Cpt' => '560413125000', 'NumeroAgence' => '10'],
    ['Cde' => '816', 'Lib' => 'BQ ATLANTIQ MONEY G. KRIB', 'Typ' => 'TRESORERIE', 'Cpt' => '560413127816', 'NumeroAgence' => '6'],
    ['Cde' => '817', 'Lib' => 'CREDIT DU SAHEL MROUA', 'Typ' => 'TRESORERIE', 'Cpt' => '560915010505', 'NumeroAgence' => '5'],
    ['Cde' => '818', 'Lib' => 'BQ ATLANTIQ MONEY G.BSSAM', 'Typ' => 'TRESORERIE', 'Cpt' => '560413127818', 'NumeroAgence' => '18'],
    ['Cde' => '900', 'Lib' => 'AFRILAND FIRST BANK', 'Typ' => 'TRESORERIE', 'Cpt' => '560413129000', 'NumeroAgence' => '10'],
    ['Cde' => '902', 'Lib' => 'CAISSE BERTOUA', 'Typ' => 'TRESORERIE', 'Cpt' => '571020000000', 'NumeroAgence' => '2'],
    ['Cde' => '903', 'Lib' => 'CAISSE EBOLOWA', 'Typ' => 'TRESORERIE', 'Cpt' => '571030000000', 'NumeroAgence' => '3'],
    ['Cde' => '904', 'Lib' => 'CAISSE GAROUA', 'Typ' => 'TRESORERIE', 'Cpt' => '571040000000', 'NumeroAgence' => '4'],
    ['Cde' => '905', 'Lib' => 'CAISSE MAROUA', 'Typ' => 'TRESORERIE', 'Cpt' => '571050000000', 'NumeroAgence' => '5'],
    ['Cde' => '906', 'Lib' => 'CAISSE KRIBI', 'Typ' => 'TRESORERIE', 'Cpt' => '571060000000', 'NumeroAgence' => '6'],
    ['Cde' => '907', 'Lib' => 'CAISSE NGAOUNDERE', 'Typ' => 'TRESORERIE', 'Cpt' => '571070000000', 'NumeroAgence' => '7'],
    ['Cde' => '908', 'Lib' => 'CAISSE SANGMELIMA', 'Typ' => 'TRESORERIE', 'Cpt' => '571080000000', 'NumeroAgence' => '8'],
    ['Cde' => '909', 'Lib' => 'CAISSE YAOUNDE', 'Typ' => 'TRESORERIE', 'Cpt' => '571090000000', 'NumeroAgence' => '9'],
    ['Cde' => '910', 'Lib' => 'CAISSE BILLETAGE', 'Typ' => 'TRESORERIE', 'Cpt' => '571910000000', 'NumeroAgence' => '9'],
    ['Cde' => '911', 'Lib' => 'CAISSE BAMENDA', 'Typ' => 'TRESORERIE', 'Cpt' => '571110000000', 'NumeroAgence' => '11'],
    ['Cde' => '912', 'Lib' => 'CAISSE BUEA', 'Typ' => 'TRESORERIE', 'Cpt' => '571120000000', 'NumeroAgence' => '12'],
    ['Cde' => '913', 'Lib' => 'CAISSE DOUALA', 'Typ' => 'TRESORERIE', 'Cpt' => '571130000000', 'NumeroAgence' => '13'],
    ['Cde' => '918', 'Lib' => 'CAISSE BAFOUSSAM', 'Typ' => 'TRESORERIE', 'Cpt' => '571180000000', 'NumeroAgence' => '18'],
    ['Cde' => '921', 'Lib' => 'EXPRESS UNION BAMENDA', 'Typ' => 'TRESORERIE', 'Cpt' => '466010000110', 'NumeroAgence' => '11'],
    ['Cde' => '922', 'Lib' => 'EXPRESS UNION BUEA', 'Typ' => 'TRESORERIE', 'Cpt' => '466010000120', 'NumeroAgence' => '12'],
    ['Cde' => '923', 'Lib' => 'EXPRESS UNION DOUALA', 'Typ' => 'TRESORERIE', 'Cpt' => '466010000130', 'NumeroAgence' => '13'],
    ['Cde' => '928', 'Lib' => 'EXPRESS UNION BAFOUSSAM', 'Typ' => 'TRESORERIE', 'Cpt' => '466010000180', 'NumeroAgence' => '18'],
    ['Cde' => '929', 'Lib' => 'EXPRESS UNION YDE', 'Typ' => 'TRESORERIE', 'Cpt' => '466010000090', 'NumeroAgence' => '9'],
    ['Cde' => '931', 'Lib' => 'REGLT FOUR/ CARTE SIMPLY', 'Typ' => 'TRESORERIE', 'Cpt' => '403420000000', 'NumeroAgence' => '10'],
    ['Cde' => '932', 'Lib' => 'EXPRESS UNION BERTOUA', 'Typ' => 'TRESORERIE', 'Cpt' => '466010000020', 'NumeroAgence' => '2'],
    ['Cde' => '933', 'Lib' => 'EXPRESS UNION EBOLOWA', 'Typ' => 'TRESORERIE', 'Cpt' => '466010000030', 'NumeroAgence' => '3'],
    ['Cde' => '934', 'Lib' => 'EXPRESS UNION GAROUA', 'Typ' => 'TRESORERIE', 'Cpt' => '466010000040', 'NumeroAgence' => '4'],
    ['Cde' => '935', 'Lib' => 'EXPRESS UNION MAROUA', 'Typ' => 'TRESORERIE', 'Cpt' => '466010000050', 'NumeroAgence' => '5'],
    ['Cde' => '938', 'Lib' => 'EXPRESS UNION SGMA', 'Typ' => 'TRESORERIE', 'Cpt' => '466010000080', 'NumeroAgence' => '8'],
    ['Cde' => '982', 'Lib' => 'CAISSE TRANF D\'ARGENT BTA', 'Typ' => 'TRESORERIE', 'Cpt' => '571932000000', 'NumeroAgence' => '2'],
    ['Cde' => '983', 'Lib' => 'CAISSE TRANSF D\'ARG. EBWA', 'Typ' => 'TRESORERIE', 'Cpt' => '571933000000', 'NumeroAgence' => '3'],
    ['Cde' => '984', 'Lib' => 'CAISSE TRANSF. D\'ARG. GRA', 'Typ' => 'TRESORERIE', 'Cpt' => '571934000000', 'NumeroAgence' => '4'],
    ['Cde' => '985', 'Lib' => 'CAISSE TRANSF D\'ARG MRA', 'Typ' => 'TRESORERIE', 'Cpt' => '571935000000', 'NumeroAgence' => '5'],
    ['Cde' => '986', 'Lib' => 'CAISSE TRANSF. D\'ARG KRBI', 'Typ' => 'TRESORERIE', 'Cpt' => '571936000000', 'NumeroAgence' => '6'],
    ['Cde' => '987', 'Lib' => 'CAISSE TRANSFERT D ARGENT NGAOUNDERE', 'Typ' => 'TRESORERIE', 'Cpt' => '571937000000', 'NumeroAgence' => '7'],
    ['Cde' => '988', 'Lib' => 'CAISSE TRANSF D\'ARG SGMA', 'Typ' => 'TRESORERIE', 'Cpt' => '571938000000', 'NumeroAgence' => '8'],
    ['Cde' => '989', 'Lib' => 'CAISSE MTN MONEY YDE', 'Typ' => 'TRESORERIE', 'Cpt' => '571890000000', 'NumeroAgence' => '9'],
    ['Cde' => '990', 'Lib' => 'CAISSE DIRECT.GENERALE', 'Typ' => 'TRESORERIE', 'Cpt' => '571900000000', 'NumeroAgence' => '10'],
    ['Cde' => '991', 'Lib' => 'CAISSE TRESORERIE', 'Typ' => 'TRESORERIE', 'Cpt' => '571920000000', 'NumeroAgence' => '10'],
    ['Cde' => '992', 'Lib' => 'CSSE TRANSF. D\'ARGENT YDE', 'Typ' => 'TRESORERIE', 'Cpt' => '571930000000', 'NumeroAgence' => '9'],
    ['Cde' => '993', 'Lib' => 'CSSE TRANSF. D\'ARGENT DLA', 'Typ' => 'TRESORERIE', 'Cpt' => '571913000000', 'NumeroAgence' => '13'],
    ['Cde' => '994', 'Lib' => 'CSSE TRANSF. D\'ARGENT BDA', 'Typ' => 'TRESORERIE', 'Cpt' => '571911000000', 'NumeroAgence' => '11'],
    ['Cde' => '995', 'Lib' => 'CSSE TRANSF. D\'ARGENT BUE', 'Typ' => 'TRESORERIE', 'Cpt' => '571912000000', 'NumeroAgence' => '12'],
    ['Cde' => '996', 'Lib' => 'CSSE TRANSF. D\'ARGENT BAF', 'Typ' => 'TRESORERIE', 'Cpt' => '571918000000', 'NumeroAgence' => '18'],
    ['Cde' => '997', 'Lib' => 'CAISS TRANSF E.U YDE', 'Typ' => 'TRESORERIE', 'Cpt' => '571930200000', 'NumeroAgence' => '9'],
    ['Cde' => '999', 'Lib' => 'FINALE', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '9'],
    ['Cde' => '8151', 'Lib' => 'UBA CARTE PREPAYE', 'Typ' => 'TRESORERIE', 'Cpt' => '560413125100', 'NumeroAgence' => '10'],
    ['Cde' => '9002', 'Lib' => 'CAISSE CLIENTS DOM. SCE BERTOUA', 'Typ' => 'TRESORERIE', 'Cpt' => '571910200000', 'NumeroAgence' => '2'],
    ['Cde' => '9003', 'Lib' => 'CAISSE CLIENTS DOM. SCE EBOLOWA', 'Typ' => 'TRESORERIE', 'Cpt' => '571910300000', 'NumeroAgence' => '3'],
    ['Cde' => '9004', 'Lib' => 'CAISSE CLIENTS DOM. SCE GAROUA', 'Typ' => 'TRESORERIE', 'Cpt' => '571910400000', 'NumeroAgence' => '4'],
    ['Cde' => '9005', 'Lib' => 'CAISSE CLIENTS DOM. SCE MAROUA', 'Typ' => 'TRESORERIE', 'Cpt' => '571910500000', 'NumeroAgence' => '5'],
    ['Cde' => '9007', 'Lib' => 'CAISSE CLIENTS DOM. SCE NGAOUNDERE', 'Typ' => 'TRESORERIE', 'Cpt' => '571910700000', 'NumeroAgence' => '7'],
    ['Cde' => '9008', 'Lib' => 'CAISSE CLIENTS DOM. SCE SANGMELIMA', 'Typ' => 'TRESORERIE', 'Cpt' => '571910800000', 'NumeroAgence' => '8'],
    ['Cde' => '9011', 'Lib' => 'CAISSE CLIENTS DOM. SCE BAMENDA', 'Typ' => 'TRESORERIE', 'Cpt' => '571910110000', 'NumeroAgence' => '11'],
    ['Cde' => '9012', 'Lib' => 'CAISSE CLIENTS DOM. SCE BUEA', 'Typ' => 'TRESORERIE', 'Cpt' => '571910120000', 'NumeroAgence' => '12'],
    ['Cde' => '9013', 'Lib' => 'CAISSE CLIENTS DOM. SCE DOUALA', 'Typ' => 'TRESORERIE', 'Cpt' => '571910130000', 'NumeroAgence' => '13'],
    ['Cde' => '9018', 'Lib' => 'CAISSE CLIENTS DOM. SCE BAFOUSSAM', 'Typ' => 'TRESORERIE', 'Cpt' => '571910180000', 'NumeroAgence' => '18'],
    ['Cde' => '9502', 'Lib' => 'HOP BERTOUA', 'Typ' => 'TRESORERIE', 'Cpt' => '466040000020', 'NumeroAgence' => '2'],
    ['Cde' => '9503', 'Lib' => 'HOP EBOLOWA', 'Typ' => 'TRESORERIE', 'Cpt' => '466040000030', 'NumeroAgence' => '3'],
    ['Cde' => '9504', 'Lib' => 'HOP GAROUA', 'Typ' => 'TRESORERIE', 'Cpt' => '466040000040', 'NumeroAgence' => '4'],
    ['Cde' => '9505', 'Lib' => 'HOP MAROUA', 'Typ' => 'TRESORERIE', 'Cpt' => '466040000050', 'NumeroAgence' => '5'],
    ['Cde' => '9506', 'Lib' => 'HOP KRIBI', 'Typ' => 'TRESORERIE', 'Cpt' => '466040000060', 'NumeroAgence' => '6'],
    ['Cde' => '9507', 'Lib' => 'HOP NGAOUNDERE', 'Typ' => 'TRESORERIE', 'Cpt' => '466040000070', 'NumeroAgence' => '7'],
    ['Cde' => '9508', 'Lib' => 'HOP SANGMELIMA', 'Typ' => 'TRESORERIE', 'Cpt' => '466040000080', 'NumeroAgence' => '8'],
    ['Cde' => '9509', 'Lib' => 'HOP YAOUNDE', 'Typ' => 'TRESORERIE', 'Cpt' => '466040000090', 'NumeroAgence' => '9'],
    ['Cde' => '9511', 'Lib' => 'HOP BAMENDA', 'Typ' => 'TRESORERIE', 'Cpt' => '466040000110', 'NumeroAgence' => '11'],
    ['Cde' => '9512', 'Lib' => 'HOP BUEA', 'Typ' => 'TRESORERIE', 'Cpt' => '466040000120', 'NumeroAgence' => '12'],
    ['Cde' => '9513', 'Lib' => 'HOP DOUALA', 'Typ' => 'TRESORERIE', 'Cpt' => '466040000130', 'NumeroAgence' => '13'],
    ['Cde' => '9518', 'Lib' => 'HOP BAFOUSSAM', 'Typ' => 'TRESORERIE', 'Cpt' => '466040000180', 'NumeroAgence' => '18'],
    ['Cde' => '9901', 'Lib' => 'BEAC DU 1ER', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9902', 'Lib' => 'BEAC DU 02', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9903', 'Lib' => 'BEAC DU 03', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9904', 'Lib' => 'BEAC DU 04', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9905', 'Lib' => 'BEAC DU 05', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9906', 'Lib' => 'BEAC DU 06', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9907', 'Lib' => 'BEAC DU 07', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9908', 'Lib' => 'BEAC DU 08', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9909', 'Lib' => 'BEAC DU 09', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9910', 'Lib' => 'BEAC DU 10', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9911', 'Lib' => 'BEAC DU 11', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9912', 'Lib' => 'BEAC DU 12', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9913', 'Lib' => 'BEAC DU 13', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9914', 'Lib' => 'BEAC DU 14', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9915', 'Lib' => 'BEAC DU 15', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9916', 'Lib' => 'BEAC DU 16', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9917', 'Lib' => 'BEAC DU 17', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9918', 'Lib' => 'BEAC DU 18', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9919', 'Lib' => 'BEAC DU 19', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9920', 'Lib' => 'BEAC DU 20', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9921', 'Lib' => 'BEAC DU 21', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9922', 'Lib' => 'BEAC DU 22', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9923', 'Lib' => 'BEAC DU 23', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9924', 'Lib' => 'BEAC DU 24', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9925', 'Lib' => 'BEAC DU 25', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9926', 'Lib' => 'BEAC DU 26', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9927', 'Lib' => 'BEAC DU 27', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9928', 'Lib' => 'BEAC DU 28', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9929', 'Lib' => 'BEAC DU 29', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9930', 'Lib' => 'XXXX', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => 'NULL'],
    ['Cde' => '9931', 'Lib' => 'BEAC DU 31', 'Typ' => 'TRESORERIE', 'Cpt' => '560111010000', 'NumeroAgence' => '10'],
    ['Cde' => '9933', 'Lib' => 'CAISSE TRANSFERT EU DLA', 'Typ' => 'TRESORERIE', 'Cpt' => '571913300000', 'NumeroAgence' => '13'],
    ['Cde' => '9999', 'Lib' => 'TRANSFERT PORTEFEUILLE', 'Typ' => 'OP. DIVERS', 'Cpt' => 'NULL', 'NumeroAgence' => 'NULL'],
    ['Cde' => '12001', 'Lib' => 'MUTAT° MCHDISES DU 01', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12002', 'Lib' => 'MUTAT° MCHDISES DU 02', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12003', 'Lib' => 'MUTAT° MCHDISES DU 03', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12004', 'Lib' => 'MUTAT° MCHDISES DU 04', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12005', 'Lib' => 'MUTAT° MCHDISES DU 05', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12006', 'Lib' => 'MUTAT° MCHDISES DU 06', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12007', 'Lib' => 'MUTAT° MCHDISES DU 07', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12008', 'Lib' => 'MUTAT° MCHDISES DU 08', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12009', 'Lib' => 'MUTAT° MCHDISES DU 09', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12010', 'Lib' => 'MUTAT° MCHDISES DU 10', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12011', 'Lib' => 'MUTAT° MCHDISES DU 11', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12012', 'Lib' => 'MUTAT° MCHDISES DU 12', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12013', 'Lib' => 'MUTAT° MCHDISES DU 13', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12014', 'Lib' => 'MUTAT° MCHDISES DU 14', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12015', 'Lib' => 'MUTAT° MCHDISES DU 15', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12016', 'Lib' => 'MUTAT° MCHDISES DU 16', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12017', 'Lib' => 'MUTAT° MCHDISES DU 17', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12018', 'Lib' => 'MUTAT° MCHDISES DU 18', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12019', 'Lib' => 'MUTAT° MCHDISES DU 19', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12020', 'Lib' => 'MUTAT° MCHDISES DU 20', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12021', 'Lib' => 'MUTAT° MCHDISES DU 21', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12022', 'Lib' => 'MUTAT° MCHDISES DU 22', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12023', 'Lib' => 'MUTAT° MCHDISES DU 23', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12024', 'Lib' => 'MUTAT° MCHDISES DU 24', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12025', 'Lib' => 'MUTAT° MCHDISES DU 25', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12026', 'Lib' => 'MUTAT° MCHDISES DU 26', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12027', 'Lib' => 'MUTAT° MCHDISES DU 27', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12028', 'Lib' => 'MUTAT° MCHDISES DU 28', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12029', 'Lib' => 'MUTAT° MCHDISES DU 29', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12030', 'Lib' => 'MUTAT° MCHDISES DU 30', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '12031', 'Lib' => 'MUTAT° MCHDISES DU 31', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13001', 'Lib' => 'GRATIF 13éME MOIS DU 01', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13002', 'Lib' => 'GRATIF 13éME MOIS DU 02', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13003', 'Lib' => 'GRATIF 13éME MOIS DU 03', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13004', 'Lib' => 'GRATIF 13éME MOIS DU 04', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13005', 'Lib' => 'GRATIF 13éME MOIS DU 05', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13006', 'Lib' => 'GRATIF 13éME MOIS DU 06', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13007', 'Lib' => 'GRATIF 13éME MOIS DU 07', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13008', 'Lib' => 'GRATIF 13éME MOIS DU 08', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13009', 'Lib' => 'GRATIF 13éME MOIS DU 09', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13010', 'Lib' => 'GRATIF 13éME MOIS DU 10', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13011', 'Lib' => 'GRATIF 13éME MOIS DU 11', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13012', 'Lib' => 'GRATIF 13éME MOIS DU 12', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13013', 'Lib' => 'GRATIF 13éME MOIS DU 13', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13014', 'Lib' => 'GRATIF 13éME MOIS DU 14', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13015', 'Lib' => 'GRATIF 13éME MOIS DU 15', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13016', 'Lib' => 'GRATIF 13éME MOIS DU 16', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13017', 'Lib' => 'GRATIF 13éME MOIS DU 17', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13018', 'Lib' => 'GRATIF 13éME MOIS DU 18', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13019', 'Lib' => 'GRATIF 13éME MOIS DU 19', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13020', 'Lib' => 'GRATIF 13éME MOIS DU 20', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13021', 'Lib' => 'GRATIF 13éME MOIS DU 21', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13022', 'Lib' => 'GRATIF 13éME MOIS DU 22', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13023', 'Lib' => 'GRATIF 13éME MOIS DU 23', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13024', 'Lib' => 'GRATIF 13éME MOIS DU 24', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13025', 'Lib' => 'GRATIF 13éME MOIS DU 25', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13026', 'Lib' => 'GRATIF 13éME MOIS DU 26', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13027', 'Lib' => 'GRATIF 13éME MOIS DU 27', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13028', 'Lib' => 'GRATIF 13éME MOIS DU 28', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13029', 'Lib' => 'GRATIF 13éME MOIS DU 29', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '13030', 'Lib' => 'GRATIF 13éME MOIS DU 30', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15001', 'Lib' => 'OPERATION CREDIT-BAIL 01', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15002', 'Lib' => 'OPERATION CREDIT-BAIL 02', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15003', 'Lib' => 'OPERATION CREDIT-BAIL 03', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15004', 'Lib' => 'OPERATION CREDIT-BAIL 04', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15005', 'Lib' => 'OPERATION CREDIT-BAIL 05', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15006', 'Lib' => 'OPERATION CREDIT-BAIL 06', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15007', 'Lib' => 'OPERATION CREDIT-BAIL 07', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15008', 'Lib' => 'OPERATION CREDIT-BAIL 08', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15009', 'Lib' => 'OPERATION CREDIT-BAIL 09', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15010', 'Lib' => 'OPERATION CREDIT-BAIL 10', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15011', 'Lib' => 'OPERATION CREDIT-BAIL 11', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15012', 'Lib' => 'OPERATION CREDIT-BAIL 12', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15013', 'Lib' => 'OPERATION CREDIT-BAIL 13', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15014', 'Lib' => 'OPERATION CREDIT-BAIL 14', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15015', 'Lib' => 'OPERATION CREDIT-BAIL 15', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15016', 'Lib' => 'OPERATION CREDIT-BAIL 16', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15017', 'Lib' => 'OPERATION CREDIT-BAIL 17', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15018', 'Lib' => 'OPERATION CREDIT-BAIL 18', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15019', 'Lib' => 'OPERATION CREDIT-BAIL 19', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15020', 'Lib' => 'OPERATION CREDIT-BAIL 20', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15021', 'Lib' => 'OPERATION CREDIT-BAIL 21', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15022', 'Lib' => 'OPERATION CREDIT-BAIL 22', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15023', 'Lib' => 'OPERATION CREDIT-BAIL 23', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15024', 'Lib' => 'OPERATION CREDIT-BAIL 24', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15025', 'Lib' => 'OPERATION CREDIT-BAIL 25', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15026', 'Lib' => 'OPERATION CREDIT-BAIL 26', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15027', 'Lib' => 'OPERATION CREDIT-BAIL 27', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15028', 'Lib' => 'OPERATION CREDIT-BAIL 28', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15029', 'Lib' => 'OPERATION CREDIT-BAIL 29', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15030', 'Lib' => 'OPERATION CREDIT-BAIL 30', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '15031', 'Lib' => 'OPERATION CREDIT-BAIL 31', 'Typ' => 'NULL', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16001', 'Lib' => 'ECRITURE SPECIFIQUE DU 01', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16002', 'Lib' => 'ECRITURE SPECIFIQUE DU 02', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16003', 'Lib' => 'ECRITURE SPECIFIQUE DU 03', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16004', 'Lib' => 'ECRITURE SPECIFIQUE DU 04', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16005', 'Lib' => 'ECRITURE SPECIFIQUE DU 05', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16006', 'Lib' => 'ECRITURE SPECIFIQUE DU 06', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16007', 'Lib' => 'ECRITURE SPECIFIQUE DU 07', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16008', 'Lib' => 'ECRITURE SPECIFIQUE DU 08', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16009', 'Lib' => 'ECRITURE SPECIFIQUE DU 09', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16010', 'Lib' => 'ECRITURE SPECIFIQUE DU 10', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16011', 'Lib' => 'ECRITURE SPECIFIQUE DU 11', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16012', 'Lib' => 'ECRITURE SPECIFIQUE DU 12', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16013', 'Lib' => 'ECRITURE SPECIFIQUE DU 13', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16014', 'Lib' => 'ECRITURE SPECIFIQUE DU 14', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16015', 'Lib' => 'ECRITURE SPECIFIQUE DU 15', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16016', 'Lib' => 'ECRITURE SPECIFIQUE DU 16', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16017', 'Lib' => 'ECRITURE SPECIFIQUE DU 17', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16018', 'Lib' => 'ECRITURE SPECIFIQUE DU 18', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16019', 'Lib' => 'ECRITURE SPECIFIQUE DU 19', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16020', 'Lib' => 'ECRITURE SPECIFIQUE DU 20', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16021', 'Lib' => 'ECRITURE SPECIFIQUE DU 21', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16022', 'Lib' => 'ECRITURE SPECIFIQUE DU 22', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16023', 'Lib' => 'ECRITURE SPECIFIQUE DU 23', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16024', 'Lib' => 'ECRITURE SPECIFIQUE DU 24', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16025', 'Lib' => 'ECRITURE SPECIFIQUE DU 25', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16026', 'Lib' => 'ECRITURE SPECIFIQUE DU 26', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16027', 'Lib' => 'ECRITURE SPECIFIQUE DU 27', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16028', 'Lib' => 'ECRITURE SPECIFIQUE DU 28', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16029', 'Lib' => 'ECRITURE SPECIFIQUE DU 29', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16030', 'Lib' => 'ECRITURE SPECIFIQUE DU 30', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '16031', 'Lib' => 'ECRITURE SPECIFIQUE DU 31', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20001', 'Lib' => 'MARCHANDISES LOCALE DU 01', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20002', 'Lib' => 'MARCHANDISES LOCALE DU 02', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20003', 'Lib' => 'MARCHANDISES LOCALE DU 03', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20004', 'Lib' => 'MARCHANDISES LOCALE DU 04', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20005', 'Lib' => 'MARCHANDISES LOCALE DU 05', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20006', 'Lib' => 'MARCHANDISES LOCALE DU 06', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20007', 'Lib' => 'MARCHANDISES LOCALE DU 07', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20008', 'Lib' => 'MARCHANDISES LOCALE DU 08', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20009', 'Lib' => 'MARCHANDISES LOCALE DU 09', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20010', 'Lib' => 'MARCHANDISES LOCALE DU 10', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20011', 'Lib' => 'MARCHANDISES LOCALE DU 11', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20012', 'Lib' => 'MARCHANDISES LOCALE DU 12', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20013', 'Lib' => 'MARCHANDISES LOCALE DU 13', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20014', 'Lib' => 'MARCHANDISES LOCALE DU 14', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20015', 'Lib' => 'MARCHANDISES LOCALE DU 15', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20016', 'Lib' => 'MARCHANDISES LOCALE DU 16', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20017', 'Lib' => 'MARCHANDISES LOCALE DU 17', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20018', 'Lib' => 'MARCHANDISES LOCALE DU 18', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20019', 'Lib' => 'MARCHANDISES LOCALE DU 19', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20020', 'Lib' => 'MARCHANDISES LOCALE DU 20', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20021', 'Lib' => 'MARCHANDISES LOCALE DU 21', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20022', 'Lib' => 'MARCHANDISES LOCALE DU 22', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20023', 'Lib' => 'MARCHANDISES LOCALE DU 23', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20024', 'Lib' => 'MARCHANDISES LOCALE DU 24', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20025', 'Lib' => 'MARCHANDISES LOCALE DU 25', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20026', 'Lib' => 'MARCHANDISES LOCALE DU 26', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20027', 'Lib' => 'MARCHANDISES LOCALE DU 27', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20028', 'Lib' => 'MARCHANDISES LOCALE DU 28', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20029', 'Lib' => 'MARCHANDISES LOCALE DU 29', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20030', 'Lib' => 'MARCHANDISES LOCALE DU 30', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20031', 'Lib' => 'MARCHANDISES LOCALE DU 31', 'Typ' => 'OD', 'Cpt' => 'NULL', 'NumeroAgence' => '10'],
    ['Cde' => '20201', 'Lib' => 'B.I.C.E.C BERTOUA DU 1', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20202', 'Lib' => 'B.I.C.E.C BERTOUA DU 2', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20203', 'Lib' => 'B.I.C.E.C BERTOUA DU 3', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20204', 'Lib' => 'B.I.C.E.C BERTOUA DU 4', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20205', 'Lib' => 'B.I.C.E.C BERTOUA DU 5', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20206', 'Lib' => 'B.I.C.E.C BERTOUA DU 6', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20207', 'Lib' => 'B.I.C.E.C BERTOUA DU 7', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20208', 'Lib' => 'B.I.C.E.C BERTOUA DU 8', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20209', 'Lib' => 'B.I.C.E.C BERTOUA DU 9', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20210', 'Lib' => 'B.I.C.E.C BERTOUA DU 10', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20211', 'Lib' => 'B.I.C.E.C BERTOUA DU 11', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20212', 'Lib' => 'B.I.C.E.C BERTOUA DU 12', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20213', 'Lib' => 'B.I.C.E.C BERTOUA DU 13', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20214', 'Lib' => 'B.I.C.E.C BERTOUA DU 14', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20215', 'Lib' => 'B.I.C.E.C BERTOUA DU 15', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20216', 'Lib' => 'B.I.C.E.C BERTOUA DU 16', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20217', 'Lib' => 'B.I.C.E.C BERTOUA DU 17', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20218', 'Lib' => 'B.I.C.E.C BERTOUA DU 18', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20219', 'Lib' => 'B.I.C.E.C BERTOUA DU 19', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20220', 'Lib' => 'B.I.C.E.C BERTOUA DU 20', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20221', 'Lib' => 'B.I.C.E.C BERTOUA DU 21', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20222', 'Lib' => 'B.I.C.E.C BERTOUA DU 22', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20223', 'Lib' => 'B.I.C.E.C BERTOUA DU 23', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20224', 'Lib' => 'B.I.C.E.C BERTOUA DU 24', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20225', 'Lib' => 'B.I.C.E.C BERTOUA DU 25', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20226', 'Lib' => 'B.I.C.E.C BERTOUA DU 26', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20227', 'Lib' => 'B.I.C.E.C BERTOUA DU 27', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20228', 'Lib' => 'B.I.C.E.C BERTOUA DU 28', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20229', 'Lib' => 'B.I.C.E.C BERTOUA DU 29', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20230', 'Lib' => 'B.I.C.E.C BERTOUA DU 30', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
    ['Cde' => '20231', 'Lib' => 'B.I.C.E.C BERTOUA DU 31', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122020', 'NumeroAgence' => '2'],
	 ['Cde' => '20301', 'Lib' => 'B.C.E .C EBOLOWA DU 1', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20302', 'Lib' => 'B.C.E .C EBOLOWA DU 2', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20303', 'Lib' => 'B.C.E .C EBOLOWA DU 3', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20304', 'Lib' => 'B.C.E .C EBOLOWA DU 4', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20305', 'Lib' => 'B.C.E .C EBOLOWA DU 5', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20306', 'Lib' => 'B.C.E .C EBOLOWA DU 6', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20307', 'Lib' => 'B.C.E .C EBOLOWA DU 7', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20308', 'Lib' => 'B.C.E .C EBOLOWA DU 8', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20309', 'Lib' => 'B.C.E .C EBOLOWA DU 9', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20310', 'Lib' => 'B.C.E .C EBOLOWA DU 10', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20311', 'Lib' => 'B.C.E .C EBOLOWA DU 11', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20312', 'Lib' => 'B.C.E .C EBOLOWA DU 12', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20313', 'Lib' => 'B.C.E .C EBOLOWA DU 13', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20314', 'Lib' => 'B.C.E .C EBOLOWA DU 14', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20315', 'Lib' => 'B.C.E .C EBOLOWA DU 15', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20316', 'Lib' => 'B.C.E .C EBOLOWA DU 16', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20317', 'Lib' => 'B.C.E .C EBOLOWA DU 17', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20318', 'Lib' => 'B.C.E .C EBOLOWA DU 18', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20319', 'Lib' => 'B.C.E .C EBOLOWA DU 19', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20320', 'Lib' => 'B.C.E .C EBOLOWA DU 20', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20321', 'Lib' => 'B.C.E .C EBOLOWA DU 21', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20322', 'Lib' => 'B.C.E .C EBOLOWA DU 22', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20323', 'Lib' => 'B.C.E .C EBOLOWA DU 23', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20324', 'Lib' => 'B.C.E .C EBOLOWA DU 24', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20325', 'Lib' => 'B.C.E .C EBOLOWA DU 25', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20326', 'Lib' => 'B.C.E .C EBOLOWA DU 26', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20327', 'Lib' => 'B.C.E .C EBOLOWA DU 27', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20328', 'Lib' => 'B.C.E .C EBOLOWA DU 28', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20329', 'Lib' => 'B.C.E .C EBOLOWA DU 29', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20330', 'Lib' => 'B.C.E .C EBOLOWA DU 30', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20331', 'Lib' => 'B.C.E .C EBOLOWA DU 31', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122030', 'NumeroAgence' => '3'],
    ['Cde' => '20401', 'Lib' => 'B.I.C.E.C GAROUA DU 1', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20402', 'Lib' => 'B.I.C.E.C GAROUA DU 2', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20403', 'Lib' => 'B.I.C.E.C GAROUA DU 3', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20404', 'Lib' => 'B.I.C.E.C GAROUA DU 4', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20405', 'Lib' => 'B.I.C.E.C GAROUA DU 5', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20406', 'Lib' => 'B.I.C.E.C GAROUA DU 6', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20407', 'Lib' => 'B.I.C.E.C GAROUA DU 7', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20408', 'Lib' => 'B.I.C.E.C GAROUA DU 8', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20409', 'Lib' => 'B.I.C.E.C GAROUA DU 9', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20410', 'Lib' => 'B.I.C.E.C GAROUA DU 10', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20411', 'Lib' => 'B.I.C.E.C GAROUA DU 11', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20412', 'Lib' => 'B.I.C.E.C GAROUA DU 12', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20413', 'Lib' => 'B.I.C.E.C GAROUA DU 13', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20414', 'Lib' => 'B.I.C.E.C GAROUA DU 14', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20415', 'Lib' => 'B.I.C.E.C GAROUA DU 15', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20416', 'Lib' => 'B.I.C.E.C GAROUA DU 16', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20417', 'Lib' => 'B.I.C.E.C GAROUA DU 17', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20418', 'Lib' => 'B.I.C.E.C GAROUA DU 18', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20419', 'Lib' => 'B.I.C.E.C GAROUA DU 19', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20420', 'Lib' => 'B.I.C.E.C GAROUA DU 20', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20421', 'Lib' => 'B.I.C.E.C GAROUA DU 21', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20422', 'Lib' => 'B.I.C.E.C GAROUA DU 22', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20423', 'Lib' => 'B.I.C.E.C GAROUA DU 23', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20424', 'Lib' => 'B.I.C.E.C GAROUA DU 24', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20425', 'Lib' => 'B.I.C.E.C GAROUA DU 25', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20426', 'Lib' => 'B.I.C.E.C GAROUA DU 26', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20427', 'Lib' => 'B.I.C.E.C GAROUA DU 27', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20428', 'Lib' => 'B.I.C.E.C GAROUA DU 28', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20429', 'Lib' => 'B.I.C.E.C GAROUA DU 29', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20430', 'Lib' => 'B.I.C.E.C GAROUA DU 30', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20431', 'Lib' => 'B.I.C.E.C GAROUA DU 31', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122040', 'NumeroAgence' => '4'],
    ['Cde' => '20501', 'Lib' => 'B.I.C.E.C MAROUA DU 1', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20502', 'Lib' => 'B.I.C.E.C MAROUA DU 2', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20503', 'Lib' => 'B.I.C.E.C MAROUA DU 3', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20504', 'Lib' => 'B.I.C.E.C MAROUA DU 4', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20505', 'Lib' => 'B.I.C.E.C MAROUA DU 5', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20506', 'Lib' => 'B.I.C.E.C MAROUA DU 6', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20507', 'Lib' => 'B.I.C.E.C MAROUA DU 7', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20508', 'Lib' => 'B.I.C.E.C MAROUA DU 8', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20509', 'Lib' => 'B.I.C.E.C MAROUA DU 9', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20510', 'Lib' => 'B.I.C.E.C MAROUA DU 10', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20511', 'Lib' => 'B.I.C.E.C MAROUA DU 11', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20512', 'Lib' => 'B.I.C.E.C MAROUA DU 12', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20513', 'Lib' => 'B.I.C.E.C MAROUA DU 13', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20514', 'Lib' => 'B.I.C.E.C MAROUA DU 14', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20515', 'Lib' => 'B.I.C.E.C MAROUA DU 15', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20516', 'Lib' => 'B.I.C.E.C MAROUA DU 16', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20517', 'Lib' => 'B.I.C.E.C MAROUA DU 17', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20518', 'Lib' => 'B.I.C.E.C MAROUA DU 18', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20519', 'Lib' => 'B.I.C.E.C MAROUA DU 19', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20520', 'Lib' => 'B.I.C.E.C MAROUA DU 20', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20521', 'Lib' => 'B.I.C.E.C MAROUA DU 21', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20522', 'Lib' => 'B.I.C.E.C MAROUA DU 22', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20523', 'Lib' => 'B.I.C.E.C MAROUA DU 23', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20524', 'Lib' => 'B.I.C.E.C MAROUA DU 24', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20525', 'Lib' => 'B.I.C.E.C MAROUA DU 25', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20526', 'Lib' => 'B.I.C.E.C MAROUA DU 26', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20527', 'Lib' => 'B.I.C.E.C MAROUA DU 27', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20528', 'Lib' => 'B.I.C.E.C MAROUA DU 28', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20529', 'Lib' => 'B.I.C.E.C MAROUA DU 29', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20530', 'Lib' => 'B.I.C.E.C MAROUA DU 30', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20531', 'Lib' => 'B.I.C.E.C MAROUA DU 31', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122050', 'NumeroAgence' => '5'],
    ['Cde' => '20601', 'Lib' => 'B.I.C.E.C KRIBI DU 1er', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20602', 'Lib' => 'B.I.C.E.C KRIBI DU 2', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20603', 'Lib' => 'B.I.C.E.C KRIBI DU 3', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20604', 'Lib' => 'B.I.C.E.C KRIBI DU 4', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20605', 'Lib' => 'B.I.C.E.C KRIBI DU 5', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20606', 'Lib' => 'B.I.C.E.C KRIBI DU 6', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20607', 'Lib' => 'B.I.C.E.C KRIBI DU 7', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20608', 'Lib' => 'B.I.C.E.C KRIBI DU 8', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20609', 'Lib' => 'B.I.C.E.C KRIBI DU 9', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20610', 'Lib' => 'B.I.C.E.C KRIBI DU 10', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20611', 'Lib' => 'B.I.C.E.C KRIBI DU 11', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20612', 'Lib' => 'B.I.C.E.C KRIBI DU 12', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20613', 'Lib' => 'B.I.C.E.C KRIBI DU 13', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20614', 'Lib' => 'B.I.C.E.C KRIBI DU 14', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20615', 'Lib' => 'B.I.C.E.C KRIBI DU 15', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20616', 'Lib' => 'B.I.C.E.C KRIBI DU 16', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20617', 'Lib' => 'B.I.C.E.C KRIBI DU 17', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20618', 'Lib' => 'B.I.C.E.C KRIBI DU 18', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20619', 'Lib' => 'B.I.C.E.C KRIBI DU 19', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20620', 'Lib' => 'B.I.C.E.C KRIBI DU 20', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20621', 'Lib' => 'B.I.C.E.C KRIBI DU 21', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20622', 'Lib' => 'B.I.C.E.C KRIBI DU 22', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20623', 'Lib' => 'B.I.C.E.C KRIBI DU 23', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20624', 'Lib' => 'B.I.C.E.C KRIBI DU 24', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20625', 'Lib' => 'B.I.C.E.C KRIBI DU 25', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20626', 'Lib' => 'B.I.C.E.C KRIBI DU 26', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20627', 'Lib' => 'B.I.C.E.C KRIBI DU 27', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20628', 'Lib' => 'B.I.C.E.C KRIBI DU 28', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20629', 'Lib' => 'B.I.C.E.C KRIBI DU 29', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20630', 'Lib' => 'B.I.C.E.C KRIBI DU 30', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20631', 'Lib' => 'B.I.C.E.C KRIBI DU 31', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122060', 'NumeroAgence' => '6'],
    ['Cde' => '20701', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 1', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20702', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 2', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20703', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 3', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20704', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 4', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20705', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 5', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20706', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 6', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20707', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 7', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20708', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 8', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20709', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 9', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20710', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 10', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20711', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 11', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20712', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 12', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20713', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 13', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20714', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 14', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20715', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 15', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20716', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 16', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20717', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 17', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20718', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 18', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20719', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 19', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20720', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 20', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20721', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 21', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20722', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 22', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20723', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 23', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20724', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 24', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20725', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 25', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20726', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 26', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20727', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 27', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20728', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 28', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20729', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 29', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20730', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 30', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20731', 'Lib' => 'B.I.C.E.C NGAOUNDERE DU 31', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122070', 'NumeroAgence' => '7'],
    ['Cde' => '20801', 'Lib' => 'B.I.C.E.C SANGMELIMA DU 1', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122080', 'NumeroAgence' => '8'],
    ['Cde' => '20802', 'Lib' => 'B.I.C.E.C SANGMELIMA DU 2', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122080', 'NumeroAgence' => '8'],
    ['Cde' => '20803', 'Lib' => 'B.I.C.E.C SANGMELIMA DU 3', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122080', 'NumeroAgence' => '8'],
    ['Cde' => '20804', 'Lib' => 'B.I.C.E.C SANGMELIMA DU 4', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122080', 'NumeroAgence' => '8'],
    ['Cde' => '20805', 'Lib' => 'B.I.C.E.C SANGMELIMA DU 5', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122080', 'NumeroAgence' => '8'],
    ['Cde' => '20806', 'Lib' => 'B.I.C.E.C SANGMELIMA DU 6', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122080', 'NumeroAgence' => '8'],
    ['Cde' => '20807', 'Lib' => 'B.I.C.E.C SANGMELIMA DU 7', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122080', 'NumeroAgence' => '8'],
    ['Cde' => '20808', 'Lib' => 'B.I.C.E.C SANGMELIMA DU 8', 'Typ' => 'TRESORERIE', 'Cpt' => '560413122080', 'NumeroAgence' => '8']
];

// This variable would typically be set based on user input or a default value
$selected_journal_cde = ''; // Example: Set to '15' to pre-select it

// Filter specific_journaux to only include 'TRESORERIE' types
$specific_journaux = array_filter($journaux, function($journal) {
    return $journal['Typ'] === 'TRESORERIE';
});

?>

<div class="form-group">
    <label for="code_journal">Sélectionner un Journal </label>
    <select class="form-control" id="code_journal" name="code_journal" required onchange="checkOtherJournal()">
        <option value="">Sélectionner un Journal</option>
        <?php foreach ($journaux as $journal): ?>
            <option value="<?php echo htmlspecialchars($journal['Cde']); ?>" <?php echo ($selected_journal_cde !== '' && $selected_journal_cde == $journal['Cde']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($journal['Cde']) . ' - ' . htmlspecialchars($journal['Lib']); ?>
            </option>
        <?php endforeach; ?>
        <option value="autre">Autre (préciser)</option>
    </select>
    <div id="other_journal_container" style="display: none; margin-top: 10px;">
        <input type="text" class="form-control" id="other_journal_code" name="other_journal_code" placeholder="Code du nouveau journal">
        <input type="text" class="form-control mt-2" id="other_journal_lib" name="other_journal_lib" placeholder="Libellé du nouveau journal">
    </div>
</div>


<script>
    function checkOtherJournal() {
        var selectBox = document.getElementById("code_journal");
        var otherJournalContainer = document.getElementById("other_journal_container");
        var specificJournalSelectContainer = document.getElementById("specific_journal_select_container");
        var specificJournalSelect = document.getElementById("specific_journal");

        // Hide both containers by default and remove required attributes
        otherJournalContainer.style.display = "none";
        document.getElementById("other_journal_code").removeAttribute("required");
        document.getElementById("other_journal_lib").removeAttribute("required");

        specificJournalSelectContainer.style.display = "none";
        specificJournalSelect.removeAttribute("required");


        if (selectBox.value === "autre") {
            otherJournalContainer.style.display = "block";
            document.getElementById("other_journal_code").setAttribute("required", "required");
            document.getElementById("other_journal_lib").setAttribute("required", "required");
        } else {
            // Check if the selected journal type is 'TRESORERIE' from the PHP data
            // This would typically involve fetching the 'Typ' from the server or having it in a JavaScript object.
            // For this example, we'll simulate it based on the provided data.
            const journauxData = <?php echo json_encode($journaux); ?>;
            const selectedJournal = journauxData.find(journal => journal.Cde === selectBox.value);

            if (selectedJournal && selectedJournal.Typ === "TRESORERIE") {
                specificJournalSelectContainer.style.display = "block";
                specificJournalSelect.setAttribute("required", "required");
            }
        }
    }

    // Call the function on page load to apply initial display logic
    document.addEventListener('DOMContentLoaded', checkOtherJournal);
</script>
                    </div>
                    <div class="col-md-2 col-sm-6">
                       <div class="form-group">
    <label for="periode_mois">Période (Mois) <span class="text-danger">*</span></label>
	<?php
$mois_fr = [
    1 => 'Janvier',
    2 => 'Février',
    3 => 'Mars',
    4 => 'Avril',
    5 => 'Mai',
    6 => 'Juin',
    7 => 'Juillet',
    8 => 'Août',
    9 => 'Septembre',
    10 => 'Octobre',
    11 => 'Novembre',
    12 => 'Décembre'
];
?>

<select class="form-control" id="periode_mois" name="periode_mois" required> 
    <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>" 
            <?php echo ($selected_periode_mois == str_pad($m, 2, '0', STR_PAD_LEFT)) ? 'selected' : ''; ?>>
            <?php echo $mois_fr[$m]; ?>
        </option>
    <?php endfor; ?>
</select>

    <?php
// Vérifier si l'utilisateur est un chef service de la comptabilité générale
$isComptable = false;

// Vérifier d'abord si l'utilisateur est connecté et a un ID
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    // Vérifier si l'utilisateur a l'habilitation "CHEF SERVICE DE LA COMPTABILITE GENERALE"
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Habilitations_Utilisateur
                          WHERE ID_Utilisateur = :user_id
                          AND Objet = 'CHEF SERVICE DE LA COMPTABILITE GENERALE'");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $count = $stmt->fetchColumn();

    $isComptable = $count > 0;
}

if ($isComptable):
?>

        
   
        <input type="text" class="form-control" value="<?php echo $monthName; ?>" readonly>
        <input type="hidden" name="periode_mois" value="<?php echo $currentMonth; ?>">
    <?php endif; ?>
</div>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <!-- Période (Année) -->
<div class="form-group">
    <label for="periode_annee">Période (Année) <span class="text-danger">*</span></label>
    <?php if ($isComptable): ?>
        <input type="number" class="form-control" id="periode_annee" name="periode_annee" 
               value="<?= htmlspecialchars($selected_periode_annee) ?>" 
               min="2000" max="<?= date('Y') + 5 ?>" required>
    <?php else: 
        $currentYear = isset($selected_periode_annee) ? $selected_periode_annee : date('Y');

    ?>
        <input type="text" class="form-control" value="<?= $currentYear ?>" readonly>
        <input type="hidden" name="periode_annee" value="<?= $currentYear ?>">
    <?php endif; ?>
</div>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <!-- Date Pièce -->
<div class="form-group">
    <label for="date_piece">Date Pièce <span class="text-danger">*</span></label>
    <?php if ($isComptable): ?>
        <input type="datetime-local" class="form-control" id="date_piece" name="date_piece"
               value="<?= htmlspecialchars(date('Y-m-d\TH:i:s', strtotime($date_piece))) ?>" required>
    <?php else:
        // Use the adjusted $date_piece for display
        $currentDate = $date_piece;
    ?>
        <input type="text" class="form-control" value="<?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($currentDate))) ?>" readonly>
        <input type="hidden" name="date_piece" value="<?= htmlspecialchars($currentDate) ?>">
    <?php endif; ?>
</div>
                    </div>
                    <div class="col-md-3 col-sm-12">
                        <div class="form-group">
                            <label for="numero_piece">Numero Pi&egravece <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="numero_piece" name="numero_piece" value="<?php echo htmlspecialchars($numero_piece); ?>" required>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="libelle_general">Libell&eacute G&eacuten&eacuteral Pi&egravece <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="libelle_general" name="libelle_general" value="<?php echo htmlspecialchars($libelle_general); ?>" required>
                </div>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Lignes d'&eacutecriture</h3>
            </div>
            <div class="panel-body">
                <div id="lignes-container">
                    <?php if (!empty($lignes_post)): ?>
                        <?php foreach ($lignes_post as $index => $ligne_p): ?>
                            <?php include '_partial_ligne_ecriture.php'; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php $index = 0; $ligne_p = ['compte'=>'', 'libelle_ligne'=>'', 'debit'=>'', 'credit'=>'']; include '_partial_ligne_ecriture.php'; ?>
                    <?php endif; ?>
                </div>
                <button type="button" id="btn-add-line" class="btn btn-success btn-sm" style="margin-top:10px;">
                    <span class="glyphicon glyphicon-plus"></span> Ajouter une ligne
                </button>
            </div>
        </div>

        <div class="totals-display panel panel-info">
            <div class="panel-body">
                <div class="row">
                    <div class="col-sm-3"><strong>Total D&eacutebit:</strong> <span id="total-debit">0.00</span> XAF</div>
                    <div class="col-sm-3"><strong>Total Cr&eacutedit:</strong> <span id="total-credit">0.00</span> XAF</div>
                    <div class="col-sm-3"><strong>Solde:</strong> <span id="solde">0.00</span> XAF</div>
                    <div class="col-sm-3"><span id="balance-status" class="pull-right"></span></div>
                </div>
            </div>
        </div>

        <div class="text-right" style="margin-top: 20px; margin-bottom: 50px;">
            <a href="liste.php" class="btn btn-default">Annuler</a>
            <button type="submit" id="btn-valider" class="btn btn-primary">
                <span class="glyphicon glyphicon-floppy-disk"></span> Valider et Enregistrer
            </button>
        </div>
    </form>
</div>



</body>
</html>
<script type="text/template" id="template-ligne-ecriture">
    <?php $index = '{{INDEX}}'; $ligne_p = ['compte'=>'', 'libelle_ligne'=>'', 'debit'=>'', 'credit'=>'']; include '_partial_ligne_ecriture.php'; ?>
</script>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="../../js/jquery-3.7.1.min.js"></script>
    <script src="../../js/bootstrap.min.js"></script>


<script>
$(document).ready(function() {
    let lineIndex = $('#lignes-container .entry-line').length;

    // Function to initialize event listeners for a single line
    function initializeLineEvents($line) {
        // Debit input logic
        $line.find('.debit-input').on('input focus', function() {
            const $currentLigne = $(this).closest('.entry-line');
            if ($(this).val() !== '' && parseFloat($(this).val().replace(',', '.')) !== 0) {
                $currentLigne.find('.credit-input').val('').prop('readonly', true);
            } else {
                $currentLigne.find('.credit-input').prop('readonly', false);
            }
            updateTotals();
        });

        // Credit input logic
        $line.find('.credit-input').on('input focus', function() {
            const $currentLigne = $(this).closest('.entry-line');
            if ($(this).val() !== '' && parseFloat($(this).val().replace(',', '.')) !== 0) {
                $currentLigne.find('.debit-input').val('').prop('readonly', true);
            } else {
                $currentLigne.find('.debit-input').prop('readonly', false);
            }
            updateTotals();
        });

        // Initial readonly state check for existing values
        const $debitInput = $line.find('.debit-input');
        const $creditInput = $line.find('.credit-input');
        if ($debitInput.val() && parseFloat($debitInput.val().replace(',', '.')) !== 0) {
            $creditInput.prop('readonly', true);
        } else if ($creditInput.val() && parseFloat($creditInput.val().replace(',', '.')) !== 0) {
            $debitInput.prop('readonly', true);
        }

        // Contrepartie button logic
        $line.find('.btn-contrepartie').on('click', generateContrepartie);

        // Account select change logic (merged from the two separate blocks)
        $line.find('.compte-select').on('change', function() {
            const selectCompte = this; // Use 'this' for the current select element
            const index = selectCompte.id.split('_')[1];
            const contrepartieSelect = document.getElementById('contrepartie_' + index);

            if (!contrepartieSelect) return;

            const selectedOption = selectCompte.options[selectCompte.selectedIndex];
            const compteText = selectedOption.textContent.trim();

            // Logic from the first block
            if (compteText.startsWith('56041312900')) {
                for (let option of contrepartieSelect.options) {
                    if (option.textContent.trim().startsWith('57512')) {
                        contrepartieSelect.value = option.value;
                        break;
                    }
                }
            }

            // Logic from the second block
            // NOTE: This part is contradictory if the first block's condition is also met.
            // Assuming these are mutually exclusive or the second check is intended to override/handle a different case.
            // If the intention was that selecting 5604... sets contrepartie to 57512 AND selecting 57512 sets contrepartie to 5604..., this is correct.
            if (compteText.startsWith('57512')) {
                 for (let option of contrepartieSelect.options) {
                    if (option.textContent.trim().startsWith('56041312900')) {
                         contrepartieSelect.value = option.value;
                         break;
                     }
                 }
            }
        });
    }

   // Fonction mise à jour pour générer la ligne de contrepartie complète
function generateContrepartie() {
    const $originalLigne = $(this).closest('.entry-line');
    const libelleOriginal = $originalLigne.find('input[name^="libelle_ligne"]').val();
    const debitOriginal = parseFloat($originalLigne.find('.debit-input').val().replace(',', '.')) || 0;
    const creditOriginal = parseFloat($originalLigne.find('.credit-input').val().replace(',', '.')) || 0;
    const compteOriginal = $originalLigne.find('.compte-select').val();
    const contrepartieOriginal = $originalLigne.find('.contrepartie-select').val();
    const anOriginal = $originalLigne.find('select[name^="an"]').val();

    let montantContrepartie = 0;
    let sensContrepartieIsDebit = false;

    if (debitOriginal > 0) {
        montantContrepartie = debitOriginal;
        sensContrepartieIsDebit = false;
    } else if (creditOriginal > 0) {
        montantContrepartie = creditOriginal;
        sensContrepartieIsDebit = true;
    } else {
        alert("Aucun montant sur la ligne d'origine pour générer une contrepartie.");
        return;
    }

    $('#btn-add-line').trigger('click');

    let $nouvelleLigne = $('#lignes-container .entry-line[data-index="' + (lineIndex - 1) + '"]');
    if ($nouvelleLigne.length === 0) {
        $nouvelleLigne = $('#lignes-container .entry-line').last();
    }

    // Libellé
    $nouvelleLigne.find('input[name^="libelle_ligne"]').val('Contrepartie: ' + libelleOriginal);

    // Compte ↔ Contrepartie
    $nouvelleLigne.find('select[name^="compte"]').val(contrepartieOriginal);
    $nouvelleLigne.find('select[name^="contrepartie"]').val(compteOriginal);

    // Montants inversés
    if (sensContrepartieIsDebit) {
        $nouvelleLigne.find('.debit-input').val(montantContrepartie.toFixed(2).replace('.', ','));
        $nouvelleLigne.find('.credit-input').val('').prop('readonly', true);
    } else {
        $nouvelleLigne.find('.credit-input').val(montantContrepartie.toFixed(2).replace('.', ','));
        $nouvelleLigne.find('.debit-input').val('').prop('readonly', true);
    }

    // Garder la même valeur de AN
    $nouvelleLigne.find('select[name^="an"]').val(anOriginal);

    updateTotals();
}


    // Initialize events for existing lines on page load
    $('#lignes-container .entry-line').each(function() {
        initializeLineEvents($(this));
    });

    // Add line button click handler
    $('#btn-add-line').click(function() {
        const template = $('#template-ligne-ecriture').html().replace(/\{\{INDEX\}\}/g, lineIndex);
        $('#lignes-container').append(template);

        // Find the newly added line using the data-index attribute added by the template
        let $nouvelleLigne = $('#lignes-container .entry-line[data-index="' + lineIndex + '"]');
        if ($nouvelleLigne.length === 0) {
            $nouvelleLigne = $('#lignes-container .entry-line').last(); // Fallback if data-index is not used
        }


        // Clear inputs for the new line
        $nouvelleLigne.find('select.compte-select, select.contrepartie-select').val(''); // Also clear contrepartie select
        $nouvelleLigne.find('input[type="text"]').val('');
        $nouvelleLigne.find('input.debit-input, input.credit-input').val('');
        $nouvelleLigne.find('input.debit-input, input.credit-input').prop('readonly', false); // Ensure not readonly initially

        // Initialize events for the new line
        initializeLineEvents($nouvelleLigne);
        lineIndex++;
        updateTotals();
    });

    // Remove line button click handler (delegated)
    $('#lignes-container').on('click', '.btn-remove-line', function() {
        if ($('#lignes-container .entry-line').length > 1) {
            $(this).closest('.entry-line').remove();
            updateTotals();
        } else {
            alert("Il doit y avoir au moins une ligne d'�criture.");
        }
    });

    // Function to update total debits, credits, and balance
    function updateTotals() {
        let totalDebit = 0;
        let totalCredit = 0;

        $('#lignes-container .entry-line').each(function() {
            const debitVal = $(this).find('.debit-input').val();
            const creditVal = $(this).find('.credit-input').val();
            // Use replace(',', '.') for decimal parsing and default to 0 if empty/invalid
            const debit = parseFloat(debitVal ? debitVal.replace(',', '.') : 0) || 0;
            const credit = parseFloat(creditVal ? creditVal.replace(',', '.') : 0) || 0;
            totalDebit += debit;
            totalCredit += credit;
        });

        // Format totals to 2 decimal places and replace '.' with ',' for display
        $('#total-debit').text(totalDebit.toFixed(2).replace('.', ','));
        $('#total-credit').text(totalCredit.toFixed(2).replace('.', ','));

        const solde = totalDebit - totalCredit;
        $('#solde').text(solde.toFixed(2).replace('.', ','));

        const $balanceStatus = $('#balance-status');
        const $btnValider = $('#btn-valider');

        // Check balance and enable/disable validation button
        // Use a small tolerance for floating point comparison
        if (Math.abs(solde) < 0.001 && totalDebit > 0) {
            $balanceStatus.html('<span class="glyphicon glyphicon-ok-circle"></span> &Eacute;QUILIBR&Eacute').removeClass('balance-error').addClass('balance-ok');
            $btnValider.prop('disabled', false);
        } else if (totalDebit === 0 && totalCredit === 0) {
            $balanceStatus.text('En attente de saisie').removeClass('balance-error balance-ok');
            $btnValider.prop('disabled', true);
        } else {
            $balanceStatus.html('<span class="glyphicon glyphicon-remove-circle"></span> D&Eacute;S&Eacute;QUILIBR&Eacute').removeClass('balance-ok').addClass('balance-error');
            $btnValider.prop('disabled', true);
        }
    }

    // Initial total calculation on page load
    updateTotals();

    // Form submission validation
    $('#form-saisie-ecriture').submit(function(event) {
        // Re-calculate totals before final validation
        updateTotals();
        const solde = parseFloat($('#solde').text().replace(',', '.'));
        const totalDebit = parseFloat($('#total-debit').text().replace(',', '.'));
        let formIsValid = true;
        let hasAtLeastOnePopulatedLine = false; // Check if at least one line has data

        // Validate each line
        $('#lignes-container .entry-line').each(function(idx) {
            const compte = $(this).find('.compte-select').val();
            const libelle = $(this).find('input[name^="libelle_ligne"]').val();
            const debitVal = $(this).find('.debit-input').val();
            const creditVal = $(this).find('.credit-input').val();
            const debitNum = parseFloat(debitVal ? debitVal.replace(',', '.') : 0) || 0;
            const creditNum = parseFloat(creditVal ? creditVal.replace(',', '.') : 0) || 0;

            // Consider a line "populated" if it has a compte OR a non-empty libelle OR a non-zero amount
            if (compte || (libelle && libelle.trim() !== "") || debitNum > 0 || creditNum > 0) {
                 hasAtLeastOnePopulatedLine = true;

                if (!compte) {
                    alert("Erreur Ligne " + (idx + 1) + ": Veuillez sélectionner un compte.");
                    $(this).find('.compte-select').focus();
                    formIsValid = false;
                    return false; // Stop the .each loop
                }
                if (!libelle || libelle.trim() === "") {
                    alert("Erreur Ligne " + (idx + 1) + ": Veuillez saisir un libellé pour la ligne.");
                    $(this).find('input[name^="libelle_ligne"]').focus();
                    formIsValid = false;
                    return false; // Stop the .each loop
                }
                if (debitNum === 0 && creditNum === 0) {
                    alert("Erreur Ligne " + (idx + 1) + ": Veuillez saisir un montant Débit ou Crédit.");
                    if (!debitVal || debitNum === 0) $(this).find('.debit-input').focus();
                    else $(this).find('.credit-input').focus();
                    formIsValid = false;
                    return false; // Stop the .each loop
                }
            }
        });

        // Overall form validation checks
        if (!hasAtLeastOnePopulatedLine) {
             alert("Veuillez ajouter au moins une ligne d'�criture valide.");
             formIsValid = false;
        }


        if (!formIsValid) {
            event.preventDefault(); // Prevent form submission
            return;
        }

        // Check for balance and non-zero total debit before final submission
        // Use a small tolerance for floating point comparison for the solde
        if (Math.abs(solde) >= 0.001 || totalDebit <= 0) {
            event.preventDefault(); // Prevent form submission
            alert("La piece comptable doit etre equilibree (Total Debit = Total Credit) et les totaux doivent etre supèrieurs à zéro avant de pouvoir valider.");
             // Update status message
            $('#balance-status').html('<span class="glyphicon glyphicon-remove-circle"></span> D&Eacute;S&Eacute;QUILIBR&Eacute - VEUILLEZ CORRIGER').removeClass('balance-ok').addClass('balance-error');
        }
        // If validation passes, the form will submit normally
    });

    // Period change logic to update the date_piece field
    $('#periode_mois, #periode_annee').change(function() {
        const mois = $('#periode_mois').val();
        const annee = $('#periode_annee').val();
        const datePieceInput = $('#date_piece');

        if (mois && annee) {
            const currentPieceDateVal = datePieceInput.val();
            let newPieceDateVal = '';

            if (currentPieceDateVal) {
                // Parse existing date, assuming YYYY-MM-DD format
                // Use UTC to avoid timezone issues that might change the day/month
                 const parts = currentPieceDateVal.split('-');
                 if (parts.length === 3) {
                     const currentPieceDate = new Date(Date.UTC(parts[0], parts[1] - 1, parts[2]));
                     const currentPieceMonth = ('0' + (currentPieceDate.getUTCMonth() + 1)).slice(-2);
                     const currentPieceYear = currentPieceDate.getUTCFullYear().toString();

                     if (currentPieceMonth !== mois || currentPieceYear !== annee) {
                         // If existing date is outside the new period, set to the first day of the new period
                         newPieceDateVal = annee + '-' + mois + '-01';
                     } else {
                         // If existing date is within the new period, keep it
                         newPieceDateVal = currentPieceDateVal;
                     }
                 } else {
                     // If existing date format is unexpected, default to the first day of the new period
                      newPieceDateVal = annee + '-' + mois + '-01';
                 }

            } else {
                // If no date exists, set to the first day of the selected period
                newPieceDateVal = annee + '-' + mois + '-01';
            }

            // Only update the input if the value has changed
            if (newPieceDateVal !== currentPieceDateVal && newPieceDateVal !== '') {
                 datePieceInput.val(newPieceDateVal);
            }
        } else if (!mois && !annee) {
             // If both month and year are cleared, clear the date input
             datePieceInput.val('');
        }
         // If only one of month/year is selected, keep the date as is or clear if invalid
         // The logic above already handles setting to 01 if the existing date is outside the new period.
         // If only one is selected, the 'if (mois && annee)' condition won't be met, so the date remains unchanged.

    }).trigger('change'); // Trigger change on load to set the date if period is pre-filled

});
</script>
<?php require_once('../../templates/footer.php'); ?>