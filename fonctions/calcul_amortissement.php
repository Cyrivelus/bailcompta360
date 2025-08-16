<?php
// fonctions/immobilisations/calcul_amortissement.php

/**
 * Ce fichier contient les fonctions pour calculer l'amortissement.
 */

/**
 * Calcule le plan d'amortissement linéaire pour une immobilisation.
 *
 * @param float $valeur_origine La valeur d'origine (HT) de l'immobilisation.
 * @param int $duree_ans La durée d'utilisation en années.
 * @param string $date_acquisition La date d'acquisition au format 'YYYY-MM-DD'.
 * @return array Un tableau contenant le plan d'amortissement avec les colonnes 'annee', 'annuite', 'valeur_comptable_nette'.
 */
function calculer_amortissement_lineaire(float $valeur_origine, int $duree_ans, string $date_acquisition): array {
    $plan = [];
    $taux = 100 / $duree_ans;
    $annuite_constante = $valeur_origine * ($taux / 100);
    $valeur_nette_comptable = $valeur_origine;
    $annee_acquisition = (int)date('Y', strtotime($date_acquisition));
    $mois_acquisition = (int)date('m', strtotime($date_acquisition));
    $nb_mois_premiere_annee = 12 - $mois_acquisition + 1;

    // Première année (prorata temporis)
    $annuite_premiere_annee = $annuite_constante * ($nb_mois_premiere_annee / 12);
    $valeur_nette_comptable -= $annuite_premiere_annee;
    $plan[] = [
        'annee' => $annee_acquisition,
        'valeur_brute' => $valeur_origine,
        'taux' => $taux,
        'annuite' => $annuite_premiere_annee,
        'cumul_amortissement' => $annuite_premiere_annee,
        'valeur_comptable_nette' => max(0, $valeur_nette_comptable)
    ];

    // Années suivantes
    $cumul_amortissement = $annuite_premiere_annee;
    for ($i = 1; $i < $duree_ans - 1; $i++) {
        $valeur_nette_comptable -= $annuite_constante;
        $cumul_amortissement += $annuite_constante;
        $plan[] = [
            'annee' => $annee_acquisition + $i,
            'valeur_brute' => $valeur_origine,
            'taux' => $taux,
            'annuite' => $annuite_constante,
            'cumul_amortissement' => $cumul_amortissement,
            'valeur_comptable_nette' => max(0, $valeur_nette_comptable)
        ];
    }
    
    // Dernière année (complément de la première)
    if ($duree_ans > 1) {
        $annuite_derniere_annee = $annuite_constante * ($mois_acquisition - 1) / 12;
        $valeur_nette_comptable -= $annuite_derniere_annee;
        $cumul_amortissement += $annuite_derniere_annee;
        $plan[] = [
            'annee' => $annee_acquisition + $duree_ans -1 ,
            'valeur_brute' => $valeur_origine,
            'taux' => $taux,
            'annuite' => $annuite_derniere_annee,
            'cumul_amortissement' => $cumul_amortissement,
            'valeur_comptable_nette' => max(0, $valeur_nette_comptable)
        ];
    }

    return $plan;
}

/**
 * Calcule le plan d'amortissement dégressif pour une immobilisation.
 *
 * @param float $valeur_origine La valeur d'origine (HT) de l'immobilisation.
 * @param int $duree_ans La durée d'utilisation en années.
 * @param string $date_acquisition La date d'acquisition au format 'YYYY-MM-DD'.
 * @return array Un tableau contenant le plan d'amortissement dégressif.
 */
function calculer_amortissement_degressif(float $valeur_origine, int $duree_ans, string $date_acquisition): array {
    $plan = [];
    $taux_lineaire = 100 / $duree_ans;
    $coef_degressif = 0;

    if ($duree_ans >= 3 && $duree_ans <= 4) {
        $coef_degressif = 1.25;
    } elseif ($duree_ans == 5 || $duree_ans == 6) {
        $coef_degressif = 1.75;
    } elseif ($duree_ans > 6) {
        $coef_degressif = 2.25;
    }

    $taux_degressif = $taux_lineaire * $coef_degressif;
    $valeur_comptable_nette = $valeur_origine;
    $annee_courante = (int)date('Y', strtotime($date_acquisition));
    $cumul_amortissement = 0;

    for ($annee = 0; $annee < $duree_ans; $annee++) {
        // Le taux d'amortissement appliqué est le plus élevé entre le taux dégressif et le taux linéaire
        $taux_applique = max($taux_degressif, (100 / ($duree_ans - $annee)));

        if ($valeur_comptable_nette <= 0) {
            break; // L'immobilisation est totalement amortie
        }
        
        $annuite = $valeur_comptable_nette * ($taux_applique / 100);
        $valeur_comptable_nette -= $annuite;
        $cumul_amortissement += $annuite;

        $plan[] = [
            'annee' => $annee_courante + $annee,
            'valeur_brute' => $valeur_origine,
            'valeur_comptable_debut' => $valeur_comptable_nette + $annuite,
            'taux_applique' => $taux_applique,
            'annuite' => $annuite,
            'cumul_amortissement' => $cumul_amortissement,
            'valeur_comptable_nette' => max(0, $valeur_comptable_nette)
        ];
    }
    
    return $plan;
}