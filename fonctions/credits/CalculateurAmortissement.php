<?php

class CalculateurAmortissement
{
    private $tva_taux_decimal;

    public function __construct(float $tva_taux)
    {
        // Le taux de TVA doit être un nombre décimal, par exemple 0.20 pour 20%
        $this->tva_taux_decimal = $tva_taux;
    }

    /**
     * Génère un tableau d'amortissement détaillé pour un crédit-bail avec des champs additionnels.
     *
     * @param float $montant_principal Montant du financement initial.
     * @param float $taux_interet_annuel Taux d'intérêt annuel en pourcentage.
     * @param int $duree_mois Durée du financement en mois.
     * @param string $date_debut Date de début du financement (format 'Y-m-d').
     * @param float $valeur_residuelle Montant de la valeur résiduelle en fin de contrat.
     * @param float $depot_de_garantie Montant du dépôt de garantie initial.
     * @param float $montant_premier_loyer_majore_ht Montant du premier loyer majoré HT.
     * @param float $montant_tracking_ht Montant initial du tracking HT.
     * @param float $redevance_mensuelle_tracking_ht Redevance mensuelle de tracking HT.
     * @param float $redevance_mensuelle_autres_prestations_ht Redevance mensuelle pour d'autres prestations HT.
     * @param float $frais_dossier_ht Frais de dossier HT.
     * @param float $frais_enregistrement_et_autres_ht Frais d'enregistrement et autres frais initiaux HT.
     * @param float $commissions_mensuelles_encaissement_ht Commissions mensuelles d'encaissement HT.
     * @return array Un tableau associatif contenant le tableau d'amortissement et les totaux.
     */
    public function genererTableau(
        float $montant_principal,
        float $taux_interet_annuel,
        int $duree_mois,
        string $date_debut,
        float $valeur_residuelle,
        float $depot_de_garantie,
        float $montant_premier_loyer_majore_ht,
        float $montant_tracking_ht,
        float $redevance_mensuelle_tracking_ht,
        float $redevance_mensuelle_autres_prestations_ht,
        float $frais_dossier_ht,
        float $frais_enregistrement_et_autres_ht,
        float $commissions_mensuelles_encaissement_ht
    ): array {
        // Validation des entrées
        if ($montant_principal <= 0 || $duree_mois <= 0) {
            throw new InvalidArgumentException("Le montant du financement et la durée doivent être supérieurs à zéro.");
        }

        // Taux d'intérêt mensuel
        $taux_mensuel = ($taux_interet_annuel / 100) / 12;

        // Calcul du loyer constant (annuité)
        if ($taux_mensuel > 0) {
            $loyer_mensuel_ht = ($montant_principal - $valeur_residuelle * pow(1 + $taux_mensuel, -$duree_mois)) *
                ($taux_mensuel / (1 - pow(1 + $taux_mensuel, -$duree_mois)));
        } else {
            // Cas où le taux est de 0%
            $loyer_mensuel_ht = ($montant_principal - $valeur_residuelle) / $duree_mois;
        }

        $tableau = [];
        $total_loyer_paye_ht = 0;
        $total_tva_loyers = 0;
        $total_prestations_ht = 0;
        $total_tva_prestations = 0;
        $total_prestations_ttc = 0;
        $total_commissions_encaissement_ttc = 0;
        $total_loyer_paye_ttc = 0;
        $total_capital_rembourse = 0;
        $total_interet_paye = 0;

        $capital_restant_du = $montant_principal;
        $flux_de_tresorerie = [
            -($montant_principal - $frais_dossier_ht - $frais_enregistrement_et_autres_ht) // Flux initial
        ];

        // Frais initiaux et dépôt de garantie
        $frais_initiaux_ht = $frais_dossier_ht + $frais_enregistrement_et_autres_ht;
        $frais_initiaux_ttc = $frais_initiaux_ht * (1 + $this->tva_taux_decimal);

        // Premier mois (Loyer majoré + Frais initiaux + Prestations)
        $date_echeance = new DateTime($date_debut);
        $capital_initial_periode = $montant_principal;

        // Calcul des totaux des redevances mensuelles (sauf commissions d'encaissement)
        $redevance_mensuelle_total_ht = $redevance_mensuelle_tracking_ht + $redevance_mensuelle_autres_prestations_ht;
        $redevance_mensuelle_total_ttc = $redevance_mensuelle_total_ht * (1 + $this->tva_taux_decimal);
        $commissions_encaissement_ttc = $commissions_mensuelles_encaissement_ht * (1 + $this->tva_taux_decimal);

        // Loyer pour la première échéance (Loyer Majoré + Prestations)
        $loyer_ht_premier = $montant_premier_loyer_majore_ht;
        $tva_loyer_premier = $loyer_ht_premier * $this->tva_taux_decimal;
        $loyer_ttc_premier = $loyer_ht_premier + $tva_loyer_premier;

        $prestations_ht_premier = $montant_tracking_ht + $redevance_mensuelle_total_ht;
        $tva_prestations_premier = $prestations_ht_premier * $this->tva_taux_decimal;
        $prestations_ttc_premier = $prestations_ht_premier + $tva_prestations_premier;

        // Intérêts et capital remboursé pour le premier loyer
        $interet_premier = $montant_principal * $taux_mensuel;
        $capital_rembourse_premier = $loyer_ht_premier - $interet_premier;

        $tableau[] = [
            'echeance_num' => 1,
            'date_echeance' => $date_echeance->format('d/m/Y'),
            'capital_initial' => $capital_initial_periode,
            'loyer_ht' => $loyer_ht_premier,
            'tva_loyer' => $tva_loyer_premier,
            'prestations_ht' => $prestations_ht_premier,
            'tva_prestations' => $tva_prestations_premier,
            'prestations_ttc' => $prestations_ttc_premier,
            'commissions_encaissement_ttc' => $commissions_encaissement_ttc,
            'loyer_ttc' => $loyer_ttc_premier + $prestations_ttc_premier + $commissions_encaissement_ttc,
            'capital_rembourse' => $capital_rembourse_premier,
            'interet_paye' => $interet_premier,
            'capital_restant_du' => $capital_restant_du - $capital_rembourse_premier,
        ];

        // Mise à jour des totaux pour la première échéance
        $total_loyer_paye_ht += $loyer_ht_premier;
        $total_tva_loyers += $tva_loyer_premier;
        $total_prestations_ht += $prestations_ht_premier;
        $total_tva_prestations += $tva_prestations_premier;
        $total_prestations_ttc += $prestations_ttc_premier;
        $total_commissions_encaissement_ttc += $commissions_encaissement_ttc;
        $total_loyer_paye_ttc += $loyer_ttc_premier + $prestations_ttc_premier + $commissions_encaissement_ttc;
        $total_capital_rembourse += $capital_rembourse_premier;
        $total_interet_paye += $interet_premier;
        $capital_restant_du -= $capital_rembourse_premier;

        $flux_de_tresorerie[] = $loyer_ttc_premier + $prestations_ttc_premier + $commissions_encaissement_ttc;

        // Boucle pour les loyers suivants
        $loyer_ht_mensuel_standard = $loyer_mensuel_ht;
        $tva_loyer_mensuel_standard = $loyer_ht_mensuel_standard * $this->tva_taux_decimal;
        $loyer_ttc_mensuel_standard = $loyer_ht_mensuel_standard + $tva_loyer_mensuel_standard;

        $prestations_ht_mensuel = $redevance_mensuelle_tracking_ht + $redevance_mensuelle_autres_prestations_ht;
        $tva_prestations_mensuel = $prestations_ht_mensuel * $this->tva_taux_decimal;
        $prestations_ttc_mensuel = $prestations_ht_mensuel + $tva_prestations_mensuel;

        for ($i = 2; $i <= $duree_mois; $i++) {
            $date_echeance->modify('+1 month');
            $capital_initial_periode = $capital_restant_du;
            $interet = $capital_restant_du * $taux_mensuel;
            $capital_rembourse = $loyer_mensuel_ht - $interet;

            // Ajustement pour la dernière échéance
            if ($i == $duree_mois) {
                $capital_rembourse = $capital_restant_du - $valeur_residuelle;
                $loyer_ht_echeance = $capital_rembourse + $interet;
                $tva_loyer = $loyer_ht_echeance * $this->tva_taux_decimal;
                $loyer_ttc = $loyer_ht_echeance + $tva_loyer;
            } else {
                $loyer_ht_echeance = $loyer_ht_mensuel_standard;
                $tva_loyer = $tva_loyer_mensuel_standard;
                $loyer_ttc = $loyer_ttc_mensuel_standard;
            }

            $loyer_ttc_total = $loyer_ttc + $prestations_ttc_mensuel + $commissions_encaissement_ttc;
            $capital_restant_du -= $capital_rembourse;

            $tableau[] = [
                'echeance_num' => $i,
                'date_echeance' => $date_echeance->format('d/m/Y'),
                'capital_initial' => $capital_initial_periode,
                'loyer_ht' => $loyer_ht_echeance,
                'tva_loyer' => $tva_loyer,
                'prestations_ht' => $prestations_ht_mensuel,
                'tva_prestations' => $tva_prestations_mensuel,
                'prestations_ttc' => $prestations_ttc_mensuel,
                'commissions_encaissement_ttc' => $commissions_encaissement_ttc,
                'loyer_ttc' => $loyer_ttc_total,
                'capital_rembourse' => $capital_rembourse,
                'interet_paye' => $interet,
                'capital_restant_du' => max(0, $capital_restant_du),
            ];

            // Mise à jour des totaux
            $total_loyer_paye_ht += $loyer_ht_echeance;
            $total_tva_loyers += $tva_loyer;
            $total_prestations_ht += $prestations_ht_mensuel;
            $total_tva_prestations += $tva_prestations_mensuel;
            $total_prestations_ttc += $prestations_ttc_mensuel;
            $total_commissions_encaissement_ttc += $commissions_encaissement_ttc;
            $total_loyer_paye_ttc += $loyer_ttc_total;
            $total_capital_rembourse += $capital_rembourse;
            $total_interet_paye += $interet;
            $flux_de_tresorerie[] = $loyer_ttc_total;
        }

        // Ajout de la valeur résiduelle et du dépôt de garantie (flux négatif si remboursé)
        $date_echeance->modify('+1 month');
        $tableau[] = [
            'echeance_num' => $duree_mois + 1,
            'date_echeance' => $date_echeance->format('d/m/Y'),
            'capital_initial' => 0,
            'loyer_ht' => $valeur_residuelle,
            'tva_loyer' => 0,
            'prestations_ht' => 0,
            'tva_prestations' => 0,
            'prestations_ttc' => 0,
            'commissions_encaissement_ttc' => 0,
            'loyer_ttc' => $valeur_residuelle,
            'capital_rembourse' => $valeur_residuelle,
            'interet_paye' => 0,
            'capital_restant_du' => 0,
        ];
        
        // Le flux de trésorerie pour la dernière échéance inclut la valeur résiduelle et le remboursement du dépôt de garantie
        $flux_de_tresorerie[] = $valeur_residuelle + $depot_de_garantie;

        // Calcul du TEG (Taux Effectif Global)
        $teg = $this->calculerTEG($montant_principal, $flux_de_tresorerie, $duree_mois);

        return [
            'tableau' => $tableau,
            'loyer_mensuel_ht' => $loyer_mensuel_ht,
            'loyer_mensuel_ttc' => $loyer_mensuel_ht * (1 + $this->tva_taux_decimal),
            'total_capital_rembourse' => $total_capital_rembourse + $valeur_residuelle,
            'total_interet_paye' => $total_interet_paye,
            'total_loyer_paye_ht' => $total_loyer_paye_ht,
            'total_tva_loyers' => $total_tva_loyers,
            'total_prestations_ht' => $total_prestations_ht,
            'total_tva_prestations' => $total_tva_prestations,
            'total_prestations_ttc' => $total_prestations_ttc,
            'total_commissions_encaissement_ttc' => $total_commissions_encaissement_ttc,
            'total_loyer_paye_ttc' => $total_loyer_paye_ttc,
            'total_frais_initiaux_ht' => $frais_initiaux_ht,
            'total_frais_initiaux_ttc' => $frais_initiaux_ttc,
            'teg' => $teg,
        ];
    }
    
    /**
     * Calcule le TEG (Taux Effectif Global) par une méthode de bracketing et d'interpolation linéaire.
     * Cette méthode cherche d'abord à encadrer le taux réel entre deux valeurs (i1 et i2) 
     * avant d'affiner l'approximation avec une interpolation linéaire.
     *
     * @param float $capital Le capital initial financé.
     * @param array $flux_de_tresorerie Un tableau des flux financiers (dépenses et recettes).
     * @param int $duree_mois La durée en mois.
     * @return float Le TEG en pourcentage.
     */
    private function calculerTEG(float $capital, array $flux_de_tresorerie, int $duree_mois): float
    {
        $precision = 0.000001;
        $maxIterations = 100;
        
        // --- Étape 1: Bracketing pour trouver un intervalle [i1, i2] ---
        $i1 = 0;
        $i2 = 0.01;
        $step = 0.01;
        
        $npv1 = $this->calculerVAN($i1, $flux_de_tresorerie);
        $npv2 = $this->calculerVAN($i2, $flux_de_tresorerie);

        // On cherche un intervalle où la VAN change de signe
        while ($npv1 * $npv2 > 0 && $i2 < 1.0) { // Limite la recherche à 100%
            $i1 = $i2;
            $i2 += $step;
            $npv1 = $npv2;
            $npv2 = $this->calculerVAN($i2, $flux_de_tresorerie);
        }

        // Si le taux n'est pas dans l'intervalle [0, 100%], retourne 0.
        if ($npv1 * $npv2 > 0) {
            return 0; 
        }

        // --- Étape 2: Interpolation linéaire (Méthode de la fausse position) ---
        for ($i = 0; $i < $maxIterations; $i++) {
            // Évite la division par zéro
            if (abs($npv2 - $npv1) < $precision) {
                break;
            }

            // Formule d'interpolation linéaire
            $i_new = $i1 - ($npv1 * ($i2 - $i1)) / ($npv2 - $npv1);
            $npv_new = $this->calculerVAN($i_new, $flux_de_tresorerie);
            
            // Si la nouvelle VAN est suffisamment proche de zéro, on a trouvé notre taux
            if (abs($npv_new) < $precision) {
                return $i_new * 12 * 100; // TEG = Taux mensuel * 12 * 100 (pourcentage)
            }

            // On met à jour l'intervalle
            if ($npv1 * $npv_new < 0) {
                $i2 = $i_new;
                $npv2 = $npv_new;
            } else {
                $i1 = $i_new;
                $npv1 = $npv_new;
            }
        }
        
        return $i_new * 12 * 100; // Retourne le meilleur résultat après le nombre max d'itérations
    }

    /**
     * Calcule la Valeur Actuelle Nette (VAN) des flux financiers.
     *
     * @param float $taux Taux mensuel d'actualisation.
     * @param array $flux_de_tresorerie Les flux de trésorerie.
     * @return float La VAN calculée.
     */
    private function calculerVAN(float $taux, array $flux_de_tresorerie): float
    {
        $van = 0;
        foreach ($flux_de_tresorerie as $t => $flux) {
            $van += $flux / pow(1 + $taux, $t);
        }
        return $van;
    }
}