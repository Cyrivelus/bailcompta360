<?php
/**
 * Classe de calcul d'amortissement de prêt ou de financement.
 * Gère le calcul d'un tableau d'amortissement avec des options avancées
 * telles que frais, dépôts, et paiements initiaux.
 */
class CalculateurDeFinancement {

    /**
     * Génère un tableau d'amortissement complet.
     *
     * @param float $montant_principal Le capital emprunté ou financé.
     * @param float $taux_interet_annuel Le taux d'intérêt annuel en pourcentage (par exemple, 5 pour 5%).
     * @param int $duree_mois La durée totale du financement en mois.
     * @param string $date_debut La date de la première échéance (format 'AAAA-MM-JJ').
     * @param float $valeur_residuelle La valeur résiduelle de l'actif à la fin du contrat.
     * @param float $depot_de_garantie Le montant du dépôt de garantie initial.
     * @param float $premier_loyer_majore_ht Le montant HT du premier loyer majoré.
     * @param float $frais_dossier_ht Le montant HT des frais de dossier initiaux.
     * @param float $autres_prestations_ht Le montant HT des autres prestations initiales.
     * @param float $tva_taux_decimal Le taux de TVA en décimal (par exemple, 0.20 pour 20%).
     * @return array Un tableau contenant le tableau d'amortissement et les totaux.
     */
    public function genererTableau(
        $montant_principal, 
        $taux_interet_annuel, 
        $duree_mois, 
        $date_debut,
        $valeur_residuelle = 0,
        $depot_de_garantie = 0,
        $premier_loyer_majore_ht = 0,
        $frais_dossier_ht = 0,
        $autres_prestations_ht = 0,
        $tva_taux_decimal = 0.20
    ) {
        if ($montant_principal <= 0 || $duree_mois <= 0) {
            throw new Exception("Le montant et la durée doivent être supérieurs à zéro.");
        }

        $taux_interet_mensuel = $taux_interet_annuel / 100 / 12;

        $montant_a_amortir = $montant_principal - $valeur_residuelle;

        // Calcul du paiement mensuel constant
        if ($taux_interet_mensuel > 0) {
            $paiement_mensuel = $montant_a_amortir * $taux_interet_mensuel / (1 - pow(1 + $taux_interet_mensuel, -$duree_mois));
        } else {
            $paiement_mensuel = $montant_a_amortir / $duree_mois;
        }

        $tableau_amortissement = [];
        $capital_restant_du = $montant_principal;
        $date_echeance = new DateTime($date_debut);
        
        $total_interet_paye = 0;
        $total_capital_rembourse = 0;
        $total_loyer_paye = 0;
        $total_frais_initiaux_ht = $frais_dossier_ht + $autres_prestations_ht;
        
        // Premier loyer majoré (si applicable)
        if ($premier_loyer_majore_ht > 0) {
            $premier_loyer_ttc = $premier_loyer_majore_ht * (1 + $tva_taux_decimal);
            $tableau_amortissement[] = [
                'echeance_num' => 0, // Indique un paiement initial
                'date_echeance' => $date_echeance->format('Y-m-d'),
                'capital_restant_du' => number_format($capital_restant_du, 2, '.', ''),
                'interet_paye' => 0,
                'capital_rembourse' => 0,
                'paiement_total' => number_format($premier_loyer_ttc, 2, '.', ''),
                'type_paiement' => 'Loyer Initial Majore'
            ];
            $total_loyer_paye += $premier_loyer_ttc;
        }

        // Dépôt de garantie et frais initiaux
        if ($depot_de_garantie > 0) {
            $tableau_amortissement[] = [
                'echeance_num' => 0,
                'date_echeance' => $date_echeance->format('Y-m-d'),
                'capital_restant_du' => number_format($capital_restant_du, 2, '.', ''),
                'interet_paye' => 0,
                'capital_rembourse' => 0,
                'paiement_total' => number_format($depot_de_garantie, 2, '.', ''),
                'type_paiement' => 'Depot de Garantie'
            ];
        }

        if ($total_frais_initiaux_ht > 0) {
             $frais_initiaux_ttc = $total_frais_initiaux_ht * (1 + $tva_taux_decimal);
             $tableau_amortissement[] = [
                'echeance_num' => 0,
                'date_echeance' => $date_echeance->format('Y-m-d'),
                'capital_restant_du' => number_format($capital_restant_du, 2, '.', ''),
                'interet_paye' => 0,
                'capital_rembourse' => 0,
                'paiement_total' => number_format($frais_initiaux_ttc, 2, '.', ''),
                'type_paiement' => 'Frais Initiaux'
            ];
        }

        for ($i = 1; $i <= $duree_mois; $i++) {
            $interet_mensuel = $capital_restant_du * $taux_interet_mensuel;
            $capital_rembourse_mensuel = $paiement_mensuel - $interet_mensuel;
            
            // Ajustement final pour éviter les erreurs d'arrondi
            if ($i == $duree_mois) {
                $capital_rembourse_mensuel = $capital_restant_du - $valeur_residuelle;
                $paiement_mensuel = $capital_rembourse_mensuel + $interet_mensuel;
            }

            $capital_restant_du -= $capital_rembourse_mensuel;

            $date_echeance->modify('+1 month');

            $tableau_amortissement[] = [
                'echeance_num' => $i,
                'date_echeance' => $date_echeance->format('Y-m-d'),
                'capital_restant_du' => number_format($capital_restant_du, 2, '.', ''),
                'interet_paye' => number_format($interet_mensuel, 2, '.', ''),
                'capital_rembourse' => number_format($capital_rembourse_mensuel, 2, '.', ''),
                'paiement_total' => number_format($paiement_mensuel, 2, '.', ''),
                'type_paiement' => 'Loyer Mensuel'
            ];
            
            $total_interet_paye += $interet_mensuel;
            $total_capital_rembourse += $capital_rembourse_mensuel;
            $total_loyer_paye += $paiement_mensuel;
        }

        // Valeur résiduelle (paiement final)
        if ($valeur_residuelle > 0) {
            $tableau_amortissement[] = [
                'echeance_num' => $duree_mois + 1,
                'date_echeance' => $date_echeance->format('Y-m-d'),
                'capital_restant_du' => 0,
                'interet_paye' => 0,
                'capital_rembourse' => number_format($valeur_residuelle, 2, '.', ''),
                'paiement_total' => number_format($valeur_residuelle, 2, '.', ''),
                'type_paiement' => 'Valeur Residuelle'
            ];
            $total_capital_rembourse += $valeur_residuelle;
            $total_loyer_paye += $valeur_residuelle;
        }

        return [
            'tableau' => $tableau_amortissement,
            'total_interet_paye' => number_format($total_interet_paye, 2, '.', ''),
            'total_capital_rembourse' => number_format($total_capital_rembourse, 2, '.', ''),
            'paiement_mensuel' => number_format($paiement_mensuel, 2, '.', ''),
            'total_loyer_paye' => number_format($total_loyer_paye, 2, '.', ''),
            'total_frais_initiaux_ht' => $total_frais_initiaux_ht
        ];
    }
}
