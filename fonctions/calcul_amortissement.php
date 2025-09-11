<?php

/**
 * Cette classe génère un tableau d'amortissement détaillé pour un crédit-bail.
 * Elle inclut le calcul du TEG (Taux Effectif Global) basé sur les flux de trésorerie.
 */
class AmortizationCalculator
{
    private float $tvaRateDecimal;

    /**
     * @param float $tvaRate Le taux de TVA en pourcentage (ex: 20 pour 20%).
     */
    public function __construct(float $tvaRate)
    {
        $this->tvaRateDecimal = $tvaRate / 100;
    }

    /**
     * Génère un tableau d'amortissement détaillé pour un crédit-bail.
     *
     * @param float $principalAmount Montant du financement initial.
     * @param float $annualInterestRate Taux d'intérêt annuel en pourcentage.
     * @param int $durationMonths Durée du financement en mois.
     * @param string $startDate Date de début du financement (format 'Y-m-d').
     * @param float $residualValue Montant de la valeur résiduelle en fin de contrat.
     * @param float $securityDeposit Montant du dépôt de garantie initial.
     * @param float $firstIncreasedRentHT Montant du premier loyer majoré HT.
     * @param float $fileFeesHT Frais de dossier HT.
     * @param float $registrationFeesTTC Frais d'enregistrement TTC.
     * @param float $collectionCommissionsHT Commissions d'encaissement mensuelles HT.
     * @param float $otherServicesHT Autres prestations initiales HT.
     * @return array Un tableau associatif contenant le tableau d'amortissement et les totaux.
     */
    public function generateSchedule(
        float $principalAmount,
        float $annualInterestRate,
        int $durationMonths,
        string $startDate,
        float $residualValue,
        float $securityDeposit,
        float $firstIncreasedRentHT,
        float $fileFeesHT,
        float $registrationFeesTTC,
        float $collectionCommissionsHT,
        float $otherServicesHT
    ): array {
        if ($principalAmount <= 0 || $durationMonths <= 0) {
            throw new InvalidArgumentException("Le montant du financement et la durée doivent être supérieurs à zéro.");
        }

        // Taux d'intérêt mensuel
        $monthlyRate = ($annualInterestRate / 100) / 12;

        // Calcul du loyer constant (formule d'annuité de leasing)
        if ($monthlyRate > 0) {
            $baseMonthlyRentHT = ($principalAmount - $residualValue * pow(1 + $monthlyRate, -$durationMonths)) *
                ($monthlyRate / (1 - pow(1 + $monthlyRate, -$durationMonths)));
        } else {
            // Cas où le taux est de 0%
            $baseMonthlyRentHT = ($principalAmount - $residualValue) / $durationMonths;
        }

        $schedule = [];
        $remainingPrincipal = $principalAmount;
        $totalPrincipalRepaid = 0;
        $totalInterestPaid = 0;
        $totalRentPaidHT = 0;
        $totalRentPaidTTC = 0;

        // Calcul des frais initiaux pour la synthèse
        $initialFeesHT = $fileFeesHT + $firstIncreasedRentHT + $otherServicesHT;
        $initialFeesTTC = ($fileFeesHT * (1 + $this->tvaRateDecimal)) +
            ($firstIncreasedRentHT * (1 + $this->tvaRateDecimal)) +
            ($otherServicesHT * (1 + $this->tvaRateDecimal)) +
            $registrationFeesTTC;

        $currentDate = new DateTime($startDate);

        // Boucle pour générer le tableau d'amortissement
        for ($i = 1; $i <= $durationMonths; $i++) {
            $interest = $remainingPrincipal * $monthlyRate;
            $rentHTPerTerm = ($i == 1) ? $firstIncreasedRentHT : $baseMonthlyRentHT;

            // Ajustement du dernier mois pour la valeur résiduelle
            $principalRepaidPerTerm = $rentHTPerTerm - $interest;
            if ($i == $durationMonths) {
                // Le remboursement du capital doit amener le capital restant dû à la valeur résiduelle
                $principalRepaidPerTerm = $remainingPrincipal - $residualValue;
                $interest = $rentHTPerTerm - $principalRepaidPerTerm;
                if ($interest < 0) {
                    $interest = 0;
                }
            }

            $totalRentHT = $rentHTPerTerm + $collectionCommissionsHT;
            $tva = $totalRentHT * $this->tvaRateDecimal;
            $rentTTC = $totalRentHT + $tva;

            $remainingPrincipal -= $principalRepaidPerTerm;
            
            $schedule[] = [
                'echeance_num' => $i,
                'type_paiement' => 'Loyer',
                'date_echeance' => $currentDate->format('d/m/Y'),
                'capital_restant_du' => max(0, $remainingPrincipal),
                'interet_paye' => $interest,
                'capital_rembourse' => $principalRepaidPerTerm,
                'loyer_ht' => $totalRentHT,
                'tva' => $tva,
                'loyer_ttc' => $rentTTC,
            ];

            // Mise à jour des totaux
            $totalPrincipalRepaid += $principalRepaidPerTerm;
            $totalInterestPaid += $interest;
            $totalRentPaidHT += $totalRentHT;
            $totalRentPaidTTC += $rentTTC;

            $currentDate->modify('+1 month');
        }

        // Ajout de la valeur résiduelle dans la dernière ligne
        $schedule[] = [
            'echeance_num' => $durationMonths + 1,
            'type_paiement' => 'Valeur Résiduelle',
            'date_echeance' => $currentDate->format('d/m/Y'),
            'capital_restant_du' => 0,
            'interet_paye' => 0,
            'capital_rembourse' => $residualValue,
            'loyer_ht' => $residualValue,
            'tva' => $residualValue * $this->tvaRateDecimal,
            'loyer_ttc' => $residualValue * (1 + $this->tvaRateDecimal),
        ];

        // Calcul du TEG
        $teg = $this->calculateTEG($principalAmount, $baseMonthlyRentHT + $collectionCommissionsHT, $durationMonths, $firstIncreasedRentHT, $residualValue);

        // Retourner les données complètes
        return [
            'schedule' => $schedule,
            'monthly_rent_ht' => $baseMonthlyRentHT + $collectionCommissionsHT,
            'monthly_rent_ttc' => ($baseMonthlyRentHT + $collectionCommissionsHT) * (1 + $this->tvaRateDecimal),
            'total_principal_repaid' => $totalPrincipalRepaid,
            'total_interest_paid' => $totalInterestPaid,
            'total_rent_paid_ht' => $totalRentPaidHT,
            'total_rent_paid_ttc' => $totalRentPaidTTC,
            'total_initial_fees_ht' => $initialFeesHT,
            'total_initial_fees_ttc' => $initialFeesTTC,
            'teg' => $teg,
        ];
    }

    /**
     * Calcule le TEG (Taux Effectif Global) en utilisant la méthode de la fausse position.
     * Cette version est plus robuste et se base sur les flux de trésorerie réels.
     *
     * @param float $principal Le capital initial financé.
     * @param float $monthlyRent Le loyer mensuel constant HT.
     * @param int $durationMonths La durée en mois.
     * @param float $firstIncreasedRentHT Le premier loyer majoré HT.
     * @param float $residualValue La valeur résiduelle.
     * @return float Le TEG en pourcentage.
     */
    private function calculateTEG(float $principal, float $monthlyRent, int $durationMonths, float $firstIncreasedRentHT, float $residualValue): float
    {
        // On part d'une estimation de taux entre 0 et 100%
        $lowRate = 0;
        $highRate = 1;
        $maxIterations = 100;
        $tolerance = 0.00001;
        $presentValue = 0;

        // Définition des flux de trésorerie nets
        $cashFlows = [-$principal];
        $cashFlows[] = $firstIncreasedRentHT; // Premier loyer majoré
        for ($i = 2; $i <= $durationMonths; $i++) {
            $cashFlows[] = $monthlyRent;
        }
        $cashFlows[] = $residualValue; // Valeur résiduelle en fin de contrat

        // Fonction pour calculer la VAN (Valeur Actuelle Nette)
        $calculateNPV = function ($rate) use ($cashFlows) {
            $npv = 0;
            if ($rate == -1.0) {
                return PHP_FLOAT_MAX;
            }
            foreach ($cashFlows as $index => $flow) {
                $npv += $flow / pow(1 + $rate, $index);
            }
            return $npv;
        };

        // Utilisation de la méthode de la fausse position pour trouver le taux qui annule la VAN
        for ($i = 0; $i < $maxIterations; $i++) {
            $npvLow = $calculateNPV($lowRate);
            $npvHigh = $calculateNPV($highRate);
            
            // Si l'un des taux donne une VAN proche de zéro, on le retourne
            if (abs($npvLow) < $tolerance) {
                return $lowRate * 12 * 100;
            }
            if (abs($npvHigh) < $tolerance) {
                return $highRate * 12 * 100;
            }

            // Calcul du nouveau taux par interpolation
            $newRate = $lowRate - $npvLow * (($highRate - $lowRate) / ($npvHigh - $npvLow));
            $npvNew = $calculateNPV($newRate);

            if (abs($npvNew) < $tolerance) {
                return $newRate * 12 * 100;
            }

            if ($npvNew * $npvLow > 0) {
                $lowRate = $newRate;
            } else {
                $highRate = $newRate;
            }
        }

        // Si la convergence n'a pas été atteinte
        return 0;
    }
}
