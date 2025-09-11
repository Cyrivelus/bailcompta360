<?php
// pages/credits/tableau_amortissement.php

session_start();

require_once '../../fonctions/credits/CalculateurAmortissement.php';

$message = '';
$message_type = '';
$tableau_amortissement = [];
$loyer_mensuel_ht = 0;
$loyer_mensuel_ttc = 0;
$total_capital_rembourse = 0;
$total_interet_paye = 0;
$total_loyer_paye_ht = 0;
$total_loyer_paye_ttc = 0;
$total_frais_initiaux_ht = 0;
$total_frais_initiaux_ttc = 0;
$total_ttc_global = 0;
$teg = 0;

// Initialisation des totaux
$total_tva_loyers = 0;
$total_prestations_ht = 0;
$total_tva_prestations = 0;
$total_prestations_ttc = 0;
$total_commissions_encaissement_ttc = 0;

// Initialisation des valeurs d'entrée
$raison_sociale = '';
$montant_principal = 0;
$taux_interet_annuel = 0;
$duree_mois = 0;
$date_debut = date('Y-m-d');
$taux_frais_dossier = 3.5;
$taux_premier_loyer_majore = 15.55;
$taux_valeur_residuelle = 0;
$taux_depot_de_garantie = 0;
$montant_tracking_ht = 0;
$redevance_mensuelle_tracking_ht = 0;
$redevance_mensuelle_autres_prestations_ht = 0;
$frais_enregistrement_ttc = 0;
$commissions_encaissement_ht = 19500;

// Initialisation des valeurs calculées
$frais_dossier_ht = 0;
$frais_dossier_ttc = 0;
$premier_loyer_majore_ht = 0;
$premier_loyer_majore_ttc = 0;
$valeur_residuelle_ht = 0;
$valeur_residuelle_ttc = 0;
$depot_de_garantie_ht = 0;
$depot_de_garantie_ttc = 0;
$montant_tracking_ttc = 0;
$redevance_mensuelle_tracking_ttc = 0;
$redevance_mensuelle_autres_prestations_ttc = 0;
$frais_enregistrement_ht = 0;
$commissions_encaissement_ttc = 23234;
$date_reelle_premiere_echeance = '';

$tva_taux_decimal = 0.1925;

if (isset($_GET['clear_session'])) {
    unset($_SESSION['simulation_data']);
    // Redirection vers la page propre pour éviter la boucle
    header('Location: ' . $_SERVER['PHP_SELF']); 
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Récupération des données du formulaire
        $raison_sociale = filter_input(INPUT_POST, 'raison_sociale', FILTER_SANITIZE_STRING);
        $montant_principal = filter_input(INPUT_POST, 'montant_principal', FILTER_VALIDATE_FLOAT);
        $taux_interet_annuel = filter_input(INPUT_POST, 'taux_interet_annuel', FILTER_VALIDATE_FLOAT);
        $duree_mois = filter_input(INPUT_POST, 'duree_mois', FILTER_VALIDATE_INT);
        $date_debut = $_POST['date_debut'];

        // Récupération des taux et montants
        $taux_frais_dossier = filter_input(INPUT_POST, 'taux_frais_dossier', FILTER_VALIDATE_FLOAT) ?: 0;
        $taux_premier_loyer_majore = filter_input(INPUT_POST, 'taux_premier_loyer_majore', FILTER_VALIDATE_FLOAT) ?: 0;
        $taux_valeur_residuelle = filter_input(INPUT_POST, 'taux_valeur_residuelle', FILTER_VALIDATE_FLOAT) ?: 0;
        $taux_depot_de_garantie = filter_input(INPUT_POST, 'taux_depot_de_garantie', FILTER_VALIDATE_FLOAT) ?: 0;

        $montant_tracking_ht = filter_input(INPUT_POST, 'montant_tracking_ht', FILTER_VALIDATE_FLOAT) ?: 0;
        $redevance_mensuelle_tracking_ht = filter_input(INPUT_POST, 'redevance_mensuelle_tracking_ht', FILTER_VALIDATE_FLOAT) ?: 0;
        $redevance_mensuelle_autres_prestations_ht = filter_input(INPUT_POST, 'redevance_mensuelle_autres_prestations_ht', FILTER_VALIDATE_FLOAT) ?: 0;

        $frais_enregistrement_ttc = filter_input(INPUT_POST, 'frais_enregistrement_ttc', FILTER_VALIDATE_FLOAT) ?: 0;
        $commissions_encaissement_ht = filter_input(INPUT_POST, 'commissions_encaissement_ht', FILTER_VALIDATE_FLOAT) ?: 0;

        // Calcul des montants automatiques basés sur les taux
        $frais_dossier_ht = ($montant_principal * $taux_frais_dossier) / 100;
        $frais_dossier_ttc = $frais_dossier_ht * (1 + $tva_taux_decimal);

        $premier_loyer_majore_ht = ($montant_principal * $taux_premier_loyer_majore) / 100;
        $premier_loyer_majore_ttc = $premier_loyer_majore_ht * (1 + $tva_taux_decimal);

        $valeur_residuelle_ht = ($montant_principal * $taux_valeur_residuelle) / 100;
        $valeur_residuelle_ttc = $valeur_residuelle_ht * (1 + $tva_taux_decimal);

        $depot_de_garantie_ht = ($montant_principal * $taux_depot_de_garantie) / 100;
        $depot_de_garantie_ttc = $depot_de_garantie_ht * (1 + $tva_taux_decimal);

        $montant_tracking_ttc = $montant_tracking_ht * (1 + $tva_taux_decimal);
        $redevance_mensuelle_tracking_ttc = $redevance_mensuelle_tracking_ht * (1 + $tva_taux_decimal);
        $redevance_mensuelle_autres_prestations_ttc = $redevance_mensuelle_autres_prestations_ht * (1 + $tva_taux_decimal);

        $frais_enregistrement_ht = $frais_enregistrement_ttc / (1 + $tva_taux_decimal);
        $commissions_encaissement_ttc = $commissions_encaissement_ht * (1 + $tva_taux_decimal);

        // Date de première échéance
        $date_reelle_premiere_echeance = $date_debut;

        // Validation des données
        if ($montant_principal === false || $montant_principal <= 0) {
            throw new Exception("Le capital financé ne peut pas être nul ou négatif.");
        }
        if ($duree_mois === false || $duree_mois <= 0) {
            throw new Exception("La durée du prêt doit être supérieure à zéro.");
        }
        if ($taux_interet_annuel > 100 || $duree_mois > 360) {
            throw new Exception("Les valeurs extrêmes ne sont pas prises en charge. Veuillez saisir des données raisonnables.");
        }

        // Création de l'objet calculateur et appel de la méthode
        $calculateur = new CalculateurAmortissement($tva_taux_decimal);
   
        // Appel de la méthode genererTableau corrigé
$resultat = $calculateur->genererTableau(
    $montant_principal,
    $taux_interet_annuel,
    $duree_mois,
    $date_debut,
    $valeur_residuelle_ht,
    $depot_de_garantie_ht,
    $premier_loyer_majore_ht,
    $montant_tracking_ht,
    $redevance_mensuelle_tracking_ht,
    $redevance_mensuelle_autres_prestations_ht,
    $frais_dossier_ht,
    $frais_enregistrement_ht, // Correct, utilise la version HT
    $commissions_encaissement_ht
);

        // Extraction des résultats
        $tableau_amortissement = $resultat['tableau'];
        
        // Calcul des totaux pour les colonnes manquantes
        foreach ($tableau_amortissement as $ligne) {
            $total_tva_loyers += $ligne['tva_loyer'];
            $total_prestations_ht += $ligne['prestations_ht'];
            $total_tva_prestations += $ligne['tva_prestations'];
            $total_prestations_ttc += $ligne['prestations_ttc'];
            $total_commissions_encaissement_ttc += $ligne['commissions_encaissement_ttc'];
        }

        $synthese_data = [
            'total_loyer_paye_ht' => $resultat['total_loyer_paye_ht'],
            'total_loyer_paye_ttc' => $resultat['total_loyer_paye_ttc'],
            'total_frais_initiaux_ht' => $resultat['total_frais_initiaux_ht'],
            'total_frais_initiaux_ttc' => $resultat['total_frais_initiaux_ttc'],
            'total_ttc_global' => $resultat['total_loyer_paye_ttc'] + $resultat['total_frais_initiaux_ttc'],
            'teg' => $resultat['teg'] ?? 0,
        ];

        // Stockage en session
        $_SESSION['simulation_data'] = [
            'tableau' => $tableau_amortissement,
            'synthese' => $synthese_data,
            'loyer_mensuel_ht' => $resultat['loyer_mensuel_ht'],
            'loyer_mensuel_ttc' => $resultat['loyer_mensuel_ttc'],
            'total_interet_paye' => $resultat['total_interet_paye'],
            'total_capital_rembourse' => $resultat['total_capital_rembourse'],
            'raison_sociale' => $raison_sociale,
            'duree_mois' => $duree_mois,
            'montant_principal' => $montant_principal,
            'taux_interet_annuel' => $taux_interet_annuel,
            'teg' => $resultat['teg'] ?? 0,
            'date_debut' => $date_debut,
            
            // Stockage des valeurs d'entrée pour les afficher dans le formulaire
            'taux_frais_dossier' => $taux_frais_dossier,
            'taux_premier_loyer_majore' => $taux_premier_loyer_majore,
            'taux_valeur_residuelle' => $taux_valeur_residuelle,
            'taux_depot_de_garantie' => $taux_depot_de_garantie,
            'montant_tracking_ht' => $montant_tracking_ht,
            'redevance_mensuelle_tracking_ht' => $redevance_mensuelle_tracking_ht,
            'redevance_mensuelle_autres_prestations_ht' => $redevance_mensuelle_autres_prestations_ht,
            'frais_enregistrement_ttc' => $frais_enregistrement_ttc,
            'commissions_encaissement_ht' => $commissions_encaissement_ht,

            // Stockage des valeurs calculées
            'frais_dossier_ht' => $frais_dossier_ht,
            'frais_dossier_ttc' => $frais_dossier_ttc,
            'premier_loyer_majore_ht' => $premier_loyer_majore_ht,
            'premier_loyer_majore_ttc' => $premier_loyer_majore_ttc,
            'valeur_residuelle_ht' => $valeur_residuelle_ht,
            'valeur_residuelle_ttc' => $valeur_residuelle_ttc,
            'depot_de_garantie_ht' => $depot_de_garantie_ht,
            'depot_de_garantie_ttc' => $depot_de_garantie_ttc,
            'montant_tracking_ttc' => $montant_tracking_ttc,
            'redevance_mensuelle_tracking_ttc' => $redevance_mensuelle_tracking_ttc,
            'redevance_mensuelle_autres_prestations_ttc' => $redevance_mensuelle_autres_prestations_ttc,
            'frais_enregistrement_ht' => $frais_enregistrement_ht,
            'commissions_encaissement_ttc' => $commissions_encaissement_ttc,
            'date_reelle_premiere_echeance' => $date_reelle_premiere_echeance,
            // Ajout des nouveaux totaux à la session
            'total_tva_loyers' => $total_tva_loyers,
            'total_prestations_ht' => $total_prestations_ht,
            'total_tva_prestations' => $total_tva_prestations,
            'total_prestations_ttc' => $total_prestations_ttc,
            'total_commissions_encaissement_ttc' => $total_commissions_encaissement_ttc,
        ];
        
        // Extraction des variables pour le rendu de la page
        extract($_SESSION['simulation_data']);
        extract($synthese_data);

        $message = "Tableau d'amortissement généré avec succès pour " . htmlspecialchars($raison_sociale) . ".";
        $message_type = 'success';

    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $message_type = 'danger';
        // En cas d'erreur, on s'assure que les variables d'affichage sont initialisées
        $tableau_amortissement = [];
        $synthese_data = [];
        // Les autres variables sont initialisées à 0 par défaut
        extract($_POST);
    }
} else if(isset($_SESSION['simulation_data'])) {
    // Si la page est rechargée sans POST, on utilise les données de la session
    $data_from_session = $_SESSION['simulation_data'];
    extract($data_from_session);
    extract($data_from_session['synthese']);
}


// Les includes de templates

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Génération du Tableau d'Amortissement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .container-fluid { max-width: 1400px; }
        body { background-color: #f8f9fa; }
        .card-header.bg-primary { background-color: #007bff !important; }
        .btn-primary { background-color: #007bff; border-color: #007bff; }
        .table-hover tbody tr:hover { background-color: #f1f1f1; }
        .form-control[readonly] { background-color: #e9ecef; opacity: 1; }
        
       @page {
    size: A4;
    margin: 1cm;
}

@media print {
    body {
        background-color: #fff;
        color: #000;
        margin: 0;
        padding: 0;
    }
    .no-print {
        display: none !important;
    }
    .printable-area {
        width: 100%;
        padding: 20px;
        box-sizing: border-box;
    }
    nav, footer {
        display: none;
    }
    .simulation-title {
        text-align: center;
        margin-top: 2rem !important;
        margin-bottom: 2rem !important;
    }
    .card, .shadow-sm {
        box-shadow: none !important;
        border: none !important;
    }

    /* Ajoutez cette nouvelle règle */
    @page {
        margin: 0;
        padding: 0;
    }
    .page-header, .page-footer {
        display: none;
    }
}
    </style>
</head>
<body>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h2>Génération du Tableau d'Amortissement</h2>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show no-print" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card p-4 shadow-sm mb-4 form-card no-print">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Détails du Financement</h4>
        </div>
        <div class="card-body">
            <form action="tableau_amortissement.php" method="POST">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="raison_sociale" class="form-label">Raison Sociale / Nom du Client</label>
                        <input type="text" class="form-control" id="raison_sociale" name="raison_sociale" value="<?php echo htmlspecialchars($raison_sociale); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="montant_principal" class="form-label">Montant du financement (XAF)</label>
                        <input type="number" step="0.01" class="form-control" id="montant_principal" name="montant_principal" required value="<?php echo htmlspecialchars($montant_principal); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="taux_interet_annuel" class="form-label">Taux annuel (%)</label>
                        <input type="number" step="0.01" class="form-control" id="taux_interet_annuel" name="taux_interet_annuel" required value="<?php echo htmlspecialchars($taux_interet_annuel); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="duree_mois" class="form-label">Durée (en mois)</label>
                        <input type="number" class="form-control" id="duree_mois" name="duree_mois" required value="<?php echo htmlspecialchars($duree_mois); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="date_debut" class="form-label">Date de début</label>
                        <input type="date" class="form-control" id="date_debut" name="date_debut" required value="<?php echo htmlspecialchars($date_debut); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="date_reelle_premiere_echeance" class="form-label">Date réelle 1ère échéance</label>
                        <input type="date" class="form-control" id="date_reelle_premiere_echeance" name="date_reelle_premiere_echeance" readonly value="<?php echo htmlspecialchars($date_reelle_premiere_echeance); ?>">
                    </div>
                </div>

                <hr class="my-4">

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="taux_frais_dossier" class="form-label">Taux Frais de dossier (%)</label>
                        <input type="number" step="0.01" class="form-control" id="taux_frais_dossier" name="taux_frais_dossier" value="<?php echo htmlspecialchars($taux_frais_dossier); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="frais_dossier_ht" class="form-label">Montant Frais de dossier HT (XAF)</label>
                        <input type="number" step="0.01" class="form-control" id="frais_dossier_ht" name="frais_dossier_ht" readonly value="<?php echo number_format($frais_dossier_ht, 2, '.', ''); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="frais_dossier_ttc" class="form-label">Montant Frais de dossier TTC (XAF)</label>
                        <input type="number" step="0.01" class="form-control" id="frais_dossier_ttc" name="frais_dossier_ttc" readonly value="<?php echo number_format($frais_dossier_ttc, 2, '.', ''); ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="taux_premier_loyer_majore" class="form-label">Taux 1er Loyer majoré (%)</label>
                        <input type="number" step="0.01" class="form-control" id="taux_premier_loyer_majore" name="taux_premier_loyer_majore" value="<?php echo htmlspecialchars($taux_premier_loyer_majore); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="premier_loyer_majore_ht" class="form-label">Montant 1er Loyer majoré HT (XAF)</label>
                        <input type="number" step="0.01" class="form-control" id="premier_loyer_majore_ht" name="premier_loyer_majore_ht" readonly value="<?php echo number_format($premier_loyer_majore_ht, 2, '.', ''); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="premier_loyer_majore_ttc" class="form-label">Montant 1er Loyer majoré TTC (XAF)</label>
                        <input type="number" step="0.01" class="form-control" id="premier_loyer_majore_ttc" name="premier_loyer_majore_ttc" readonly value="<?php echo number_format($premier_loyer_majore_ttc, 2, '.', ''); ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="taux_depot_de_garantie" class="form-label">Taux Dépôt de garantie (%)</label>
                        <input type="number" step="0.01" class="form-control" id="taux_depot_de_garantie" name="taux_depot_de_garantie" value="<?php echo htmlspecialchars($taux_depot_de_garantie); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="depot_de_garantie_ht" class="form-label">Montant Dépôt de garantie HT (XAF)</label>
                        <input type="number" step="0.01" class="form-control" id="depot_de_garantie_ht" name="depot_de_garantie_ht" readonly value="<?php echo number_format($depot_de_garantie_ht, 2, '.', ''); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="depot_de_garantie_ttc" class="form-label">Montant Dépôt de garantie TTC (XAF)</label>
                        <input type="number" step="0.01" class="form-control" id="depot_de_garantie_ttc" name="depot_de_garantie_ttc" readonly value="<?php echo number_format($depot_de_garantie_ttc, 2, '.', ''); ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="taux_valeur_residuelle" class="form-label">Taux Valeur résiduelle (%)</label>
                        <input type="number" step="0.01" class="form-control" id="taux_valeur_residuelle" name="taux_valeur_residuelle" value="<?php echo htmlspecialchars($taux_valeur_residuelle); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="valeur_residuelle_ht" class="form-label">Montant Valeur résiduelle HT (XAF)</label>
                        <input type="number" step="0.01" class="form-control" id="valeur_residuelle_ht" name="valeur_residuelle_ht" readonly value="<?php echo number_format($valeur_residuelle_ht, 2, '.', ''); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="valeur_residuelle_ttc" class="form-label">Montant Valeur résiduelle TTC (XAF)</label>
                        <input type="number" step="0.01" class="form-control" id="valeur_residuelle_ttc" name="valeur_residuelle_ttc" readonly value="<?php echo number_format($valeur_residuelle_ttc, 2, '.', ''); ?>">
                    </div>
                </div>

                <hr class="my-4">

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="montant_tracking_ht" class="form-label">Montant Tracking HT (XAF)</label>
                        <input type="number" step="0.01" class="form-control" id="montant_tracking_ht" name="montant_tracking_ht" value="<?php echo htmlspecialchars($montant_tracking_ht); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="montant_tracking_ttc" class="form-label">Montant Tracking TTC (XAF)</label>
                        <input type="number" step="0.01" class="form-control" id="montant_tracking_ttc" name="montant_tracking_ttc" readonly value="<?php echo number_format($montant_tracking_ttc, 2, '.', ''); ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="redevance_mensuelle_tracking_ht" class="form-label">Redevance mensuelle Tracking HT (XAF)</label>
                        <input type="number" step="0.01" class="form-control" id="redevance_mensuelle_tracking_ht" name="redevance_mensuelle_tracking_ht" value="<?php echo htmlspecialchars($redevance_mensuelle_tracking_ht); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="redevance_mensuelle_tracking_ttc" class="form-label">Redevance mensuelle Tracking TTC (XAF)</label>
                        <input type="number" step="0.01" class="form-control" id="redevance_mensuelle_tracking_ttc" name="redevance_mensuelle_tracking_ttc" readonly value="<?php echo number_format($redevance_mensuelle_tracking_ttc, 2, '.', ''); ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="redevance_mensuelle_autres_prestations_ht" class="form-label">Redevance mensuelle Autres prestations HT (XAF)</label>
                        <input type="number" step="0.01" class="form-control" id="redevance_mensuelle_autres_prestations_ht" name="redevance_mensuelle_autres_prestations_ht" value="<?php echo htmlspecialchars($redevance_mensuelle_autres_prestations_ht); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="redevance_mensuelle_autres_prestations_ttc" class="form-label">Redevance mensuelle Autres prestations TTC (XAF)</label>
                        <input type="number" step="0.01" class="form-control" id="redevance_mensuelle_autres_prestations_ttc" name="redevance_mensuelle_autres_prestations_ttc" readonly value="<?php echo number_format($redevance_mensuelle_autres_prestations_ttc, 2, '.', ''); ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="frais_enregistrement_ttc" class="form-label">Frais d'enregistrement TTC (XAF)</label>
                        <input type="number" step="0.01" class="form-control" id="frais_enregistrement_ttc" name="frais_enregistrement_ttc" value="<?php echo htmlspecialchars($frais_enregistrement_ttc); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="frais_enregistrement_ht" class="form-label">Frais d'enregistrement HT (XAF)</label>
                        <input type="number" step="0.01" class="form-control" id="frais_enregistrement_ht" name="frais_enregistrement_ht" readonly value="<?php echo number_format($frais_enregistrement_ht, 2, '.', ''); ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="commissions_encaissement_ht" class="form-label">Commissions encaissement mensuel HT (XAF)</label>
                        <input type="number" step="0.01" class="form-control" id="commissions_encaissement_ht" name="commissions_encaissement_ht" value="<?php echo htmlspecialchars($commissions_encaissement_ht); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="commissions_encaissement_ttc" class="form-label">Commissions encaissement mensuel TTC (XAF)</label>
                        <input type="number" step="0.01" class="form-control" id="commissions_encaissement_ttc" name="commissions_encaissement_ttc" readonly value="<?php echo number_format($commissions_encaissement_ttc, 2, '.', ''); ?>">
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-calculator"></i> Générer le tableau</button>
                    <a href="tableau_amortissement.php?clear_session" class="btn btn-secondary mt-1">
    <i class="fas fa-sync-alt"></i> Réinitialiser la simulation
</a>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($tableau_amortissement)): ?>
        <div class="printable-area">
            <h4 class="mt-5 simulation-title">
                Tableau d'Amortissement
                <span class="badge bg-success no-print">Loyer mensuel HT : <?php echo number_format($loyer_mensuel_ht, 2, ',', ' '); ?> XAF</span>
                <span class="badge bg-success no-print">Loyer mensuel TTC : <?php echo number_format($loyer_mensuel_ttc, 2, ',', ' '); ?> XAF</span>
                <span class="badge bg-danger no-print">TEG : <?php echo number_format($teg * 0.2825, 2, ',', ' '); ?> %</span>
            </h4>
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title">Détails de la simulation</h5>
                            <p><strong>Raison Sociale :</strong> <?php echo htmlspecialchars($raison_sociale ?? 'N/A'); ?></p>
                            <p><strong>Capital financé :</strong> <?php echo number_format($montant_principal ?? 0, 2, ',', ' '); ?> XAF</p>
                            <p><strong>Taux annuel :</strong> <?php echo htmlspecialchars($taux_interet_annuel ?? 0); ?> %</p>
                            <p><strong>Durée :</strong> <?php echo htmlspecialchars($duree_mois ?? 0); ?> mois</p>
                            <p><strong>TEG :</strong> <span class="badge bg-danger"><?php echo number_format(($teg ?? 0) * 0.2825, 2, ',', ' '); ?> %</span></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
    <table class="table table-bordered table-striped table-hover align-middle">
        <thead>
            <tr>
                <th>#</th>
                <th>Échéance</th>
                <th>Capital Financé</th>
                <th>Loyer HT</th>
                <th>TVA/Loyer</th>
                <th>Prestations HT</th>
                <th>TVA/Prestations</th>
                <th>Prestations TTC</th>
                <th>Commiss° Encaissement TTC</th>
                <th>Loyers TTC</th>
                <th>Capital Remboursé</th>
                <th>Intérêts</th>
                <th>Reste à Rembourser</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tableau_amortissement as $ligne): ?>
                <tr>
                    <td><?php echo htmlspecialchars($ligne['echeance_num']); ?></td>
                    <td><?php echo htmlspecialchars($ligne['date_echeance']); ?></td>
                    <td><?php echo number_format($ligne['capital_initial'], 2, ',', ' '); ?></td>
                    <td><?php echo number_format($ligne['loyer_ht'], 2, ',', ' '); ?></td>
                    <td><?php echo number_format($ligne['tva_loyer'], 2, ',', ' '); ?></td>
                    <td><?php echo number_format($ligne['prestations_ht'], 2, ',', ' '); ?></td>
                    <td><?php echo number_format($ligne['tva_prestations'], 2, ',', ' '); ?></td>
                    <td><?php echo number_format($ligne['prestations_ttc'], 2, ',', ' '); ?></td>
                    <td><?php echo number_format($ligne['commissions_encaissement_ttc'], 2, ',', ' '); ?></td>
                    <td><?php echo number_format($ligne['loyer_ttc'], 2, ',', ' '); ?></td>
                    <td><?php echo number_format($ligne['capital_rembourse'], 2, ',', ' '); ?></td>
                    <td><?php echo number_format($ligne['interet_paye'], 2, ',', ' '); ?></td>
                    <td><?php echo number_format($ligne['capital_restant_du'], 2, ',', ' '); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="table-dark">
                <th colspan="3" class="text-end">Totaux</th>
                <th><?php echo number_format($total_loyer_paye_ht, 2, ',', ' '); ?></th>
                <th><?php echo number_format($total_tva_loyers, 2, ',', ' '); ?></th>
                <th><?php echo number_format($total_prestations_ht, 2, ',', ' '); ?></th>
                <th><?php echo number_format($total_tva_prestations, 2, ',', ' '); ?></th>
                <th><?php echo number_format($total_prestations_ttc, 2, ',', ' '); ?></th>
                <th><?php echo number_format($total_commissions_encaissement_ttc, 2, ',', ' '); ?></th>
                <th><?php echo number_format($total_loyer_paye_ttc, 2, ',', ' '); ?></th>
                <th><?php echo number_format($total_capital_rembourse, 2, ',', ' '); ?></th>
                <th><?php echo number_format($total_interet_paye, 2, ',', ' '); ?></th>
                <th></th>
            </tr>
        </tfoot>
    </table>
</div>

            <div class="synthesis-output">
                <h4 class="mt-5">Synthèse des Coûts</h4>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">Coûts de Loyer</h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text">Total des Loyers HT : <strong><?php echo number_format($total_loyer_paye_ht, 2, ',', ' '); ?> XAF</strong></p>
                                <p class="card-text">Total des Loyers TTC : <strong><?php echo number_format($total_loyer_paye_ttc, 2, ',', ' '); ?> XAF</strong></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0">Coûts Initiaux</h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text">Total des Frais Initiaux HT : <strong><?php echo number_format($total_frais_initiaux_ht, 2, ',', ' '); ?> XAF</strong></p>
                                <p class="card-text">Total des Frais Initiaux TTC : <strong><?php echo number_format($total_frais_initiaux_ttc, 2, ',', ' '); ?> XAF</strong></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0">Total Global du Financement</h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text fs-4 text-center">Coût Total TTC : <strong><?php echo number_format($total_ttc_global, 2, ',', ' '); ?> XAF</strong></p>
                                <p class="card-text fs-5 text-center">TEG : <strong><?php echo number_format(($teg ?? 0) * 0.2825, 2, ',', ' '); ?> %</strong></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-center gap-3 mt-4 mb-5 no-print">
            <button onclick="handlePrint()" class="btn btn-info text-white shadow-sm">
                <i class="fas fa-print me-2"></i> Imprimer la simulation
            </button>
            <a href="generer_fichier.php?type=csv" class="btn btn-success shadow-sm">
                <i class="fas fa-file-csv me-2"></i> Exporter en CSV
            </a>
            <a href="generer_fichier.php?type=pdf&doc_type=simulation" class="btn btn-warning text-dark shadow-sm" target="_blank">
                <i class="fas fa-file-pdf me-2"></i> Exporter Simulation en PDF
            </a>
            <a href="generer_fichier.php?type=pdf&doc_type=client" class="btn btn-info text-dark shadow-sm" target="_blank">
                <i class="fas fa-file-pdf me-2"></i> Exporter Echéancier Client en PDF
            </a>
            <a href="generer_fichier.php?type=pdf&doc_type=entreprise" class="btn btn-primary text-dark shadow-sm" target="_blank">
                <i class="fas fa-file-pdf me-2"></i> Exporter Tableau d'Amortissement Entreprise en PDF
            </a>
            <button onclick="window.location.href='../dashboard.php'" class="btn btn-danger shadow-sm">
                <i class="fas fa-sign-out-alt me-2"></i> Quitter
            </button>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function handlePrint() {
        window.print();
    }

    document.getElementById('btn-reinitialiser').addEventListener('click', function() {
        const form = document.querySelector('form');
        form.reset();
        const printableArea = document.querySelector('.printable-area');
        if (printableArea) {
            printableArea.style.display = 'none';
        }
        window.location.href = 'tableau_amortissement.php?clear_session=true';
    });
    
    // Script pour les calculs en temps réel
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const montantPrincipalInput = document.getElementById('montant_principal');
        const dateDebutInput = document.getElementById('date_debut');
        const dateReelleInput = document.getElementById('date_reelle_premiere_echeance');
        const tvaTaux = 0.1925;

        // Fonction de calcul et mise à jour
        function updateCalculatedFields() {
            const montantPrincipal = parseFloat(montantPrincipalInput.value) || 0;
            
            // Mettre à jour la date réelle de la première échéance
            dateReelleInput.value = dateDebutInput.value;

            // Calculs basés sur les taux
            const tauxFraisDossier = parseFloat(document.getElementById('taux_frais_dossier').value) || 0;
            const fraisDossierHT = (montantPrincipal * tauxFraisDossier) / 100;
            document.getElementById('frais_dossier_ht').value = fraisDossierHT.toFixed(2);
            document.getElementById('frais_dossier_ttc').value = (fraisDossierHT * (1 + tvaTaux)).toFixed(2);
            
            const tauxPremierLoyerMajore = parseFloat(document.getElementById('taux_premier_loyer_majore').value) || 0;
            const premierLoyerMajoreHT = (montantPrincipal * tauxPremierLoyerMajore) / 100;
            document.getElementById('premier_loyer_majore_ht').value = premierLoyerMajoreHT.toFixed(2);
            document.getElementById('premier_loyer_majore_ttc').value = (premierLoyerMajoreHT * (1 + tvaTaux)).toFixed(2);

            const tauxDepotDeGarantie = parseFloat(document.getElementById('taux_depot_de_garantie').value) || 0;
            const depotDeGarantieHT = (montantPrincipal * tauxDepotDeGarantie) / 100;
            document.getElementById('depot_de_garantie_ht').value = depotDeGarantieHT.toFixed(2);
            document.getElementById('depot_de_garantie_ttc').value = (depotDeGarantieHT * (1 + tvaTaux)).toFixed(2);

            const tauxValeurResiduelle = parseFloat(document.getElementById('taux_valeur_residuelle').value) || 0;
            const valeurResiduelleHT = (montantPrincipal * tauxValeurResiduelle) / 100;
            document.getElementById('valeur_residuelle_ht').value = valeurResiduelleHT.toFixed(2);
            document.getElementById('valeur_residuelle_ttc').value = (valeurResiduelleHT * (1 + tvaTaux)).toFixed(2);
            
            // Calculs basés sur des montants HT/TTC
            const montantTrackingHT = parseFloat(document.getElementById('montant_tracking_ht').value) || 0;
            document.getElementById('montant_tracking_ttc').value = (montantTrackingHT * (1 + tvaTaux)).toFixed(2);
            
            const redevanceTrackingHT = parseFloat(document.getElementById('redevance_mensuelle_tracking_ht').value) || 0;
            document.getElementById('redevance_mensuelle_tracking_ttc').value = (redevanceTrackingHT * (1 + tvaTaux)).toFixed(2);
            
            const autresPrestationsHT = parseFloat(document.getElementById('redevance_mensuelle_autres_prestations_ht').value) || 0;
            document.getElementById('redevance_mensuelle_autres_prestations_ttc').value = (autresPrestationsHT * (1 + tvaTaux)).toFixed(2);
            
            const fraisEnregistrementTTC = parseFloat(document.getElementById('frais_enregistrement_ttc').value) || 0;
            document.getElementById('frais_enregistrement_ht').value = (fraisEnregistrementTTC / (1 + tvaTaux)).toFixed(2);
            
            const commissionsEncaissementHT = parseFloat(document.getElementById('commissions_encaissement_ht').value) || 0;
            document.getElementById('commissions_encaissement_ttc').value = (commissionsEncaissementHT * (1 + tvaTaux)).toFixed(2);
        }

        // Écouter les événements sur tous les champs d'entrée pour les calculs en temps réel
        const inputs = form.querySelectorAll('input[type="number"], input[type="date"]');
        inputs.forEach(input => {
            input.addEventListener('input', updateCalculatedFields);
        });

        // Appeler la fonction une première fois au chargement de la page pour les valeurs initiales
        updateCalculatedFields();
    });
</script>
<div class="no-print">
<?php
require_once '../../templates/navigation.php';
require_once '../../templates/header.php';
?>
</div>
</body>
</html>