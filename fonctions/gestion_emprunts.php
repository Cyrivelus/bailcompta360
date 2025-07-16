<?php
// fonctions/gestion_emprunts.php


/**
 * Calcule le montant de l'échéance mensuelle constante (annuité) d'un emprunt.
 * Utilise BCMath pour la précision si l'extension est disponible.
 *
 * @param float $montantCapitalInitial Le montant initial du capital à amortir.
 * @param float $tauxInteretAnnuel Le taux d'intérêt annuel en pourcentage (ex: 7.0 pour 7%).
 * @param int $nombreEcheancesTotal Le nombre total d'échéances pour la durée du prêt (ex: 48 pour 48 mois).
 * @param int $moisDiffere Le nombre de mois pendant lesquels seul l'intérêt est payé (différé de remboursement du capital).
 * @return float Le montant de l'échéance mensuelle constante. Retourne 0.0 en cas d'erreur ou de paramètres invalides.
 */
function calculerEcheanceMensuelle(
    float $montantCapitalInitial,
    float $tauxInteretAnnuel,
    int $nombreEcheancesTotal,
    int $moisDiffere = 0 // Par défaut, pas de différé
): float {
    // Vérifier si l'extension BCMath est chargée pour des calculs précis
    $use_bcmath = extension_loaded('bcmath');

    // Valider les entrées de base
    if ($montantCapitalInitial <= 0 || $nombreEcheancesTotal <= 0) {
        error_log("calculerEcheanceMensuelle: Montant initial ou nombre d'échéances invalide.");
        return 0.0;
    }

    if ($moisDiffere < 0 || $moisDiffere >= $nombreEcheancesTotal) {
        error_log("calculerEcheanceMensuelle: Nombre de mois différés invalide (doit être < nombre total d'échéances et >= 0).");
        return 0.0;
    }

    // Calculer le nombre d'échéances pendant lesquelles le capital est amorti
    $nombreEcheancesAmortissables = $nombreEcheancesTotal - $moisDiffere;

    // Si aucune échéance amortissable, il n'y a pas d'annuité constante de capital.
    // Dans un prêt "in fine" total, le capital est remboursé à la fin.
    if ($nombreEcheancesAmortissables <= 0) {
        // Dans ce cas, si un différé total est appliqué, il n'y a pas d'amortissement périodique
        // et l'annuité constante pour l'amortissement serait 0 (seuls les intérêts sont payés jusqu'à la fin).
        // Cela dépend de la gestion de votre type "in fine" ou différé total.
        // Pour une annuité constante standard, on retourne 0 ici.
        return 0.0;
    }

    // Calculer le taux mensuel
    $tauxMensuel = $use_bcmath ?
        bcdiv((string)$tauxInteretAnnuel, '1200', 10) : // Taux annuel en % -> Taux mensuel décimal
        ($tauxInteretAnnuel / 1200);

    // Si le taux d'intérêt est nul, l'échéance est simplement le capital divisé par le nombre d'échéances
    if (($use_bcmath && bccomp($tauxMensuel, '0', 10) == 0) || (!$use_bcmath && $tauxMensuel == 0)) {
        return $use_bcmath ?
            bcdiv((string)$montantCapitalInitial, (string)$nombreEcheancesAmortissables, 10) :
            $montantCapitalInitial / $nombreEcheancesAmortissables;
    }

    // Formule de l'annuité constante:
    // A = P * [ i / (1 - (1 + i)^-n) ]
    // Où:
    // A = Annuité (montant de l'échéance)
    // P = Capital initial
    // i = Taux d'intérêt par période (taux mensuel)
    // n = Nombre total de périodes (nombre d'échéances amortissables)

    if ($use_bcmath) {
        $montantCapitalInitial_bc = (string)$montantCapitalInitial;
        $tauxMensuel_bc = (string)$tauxMensuel;
        $nombreEcheancesAmortissables_bc = (string)$nombreEcheancesAmortissables;

        $pow_result = bcpow(bcadd('1', $tauxMensuel_bc, 10), bcmul('-1', $nombreEcheancesAmortissables_bc, 0), 10);
        $denom = bcsub('1', $pow_result, 10);

        if (bccomp($denom, '0', 10) == 0) {
            // Cela ne devrait pas arriver avec un taux > 0, mais pour éviter div par zéro
            error_log("calculerEcheanceMensuelle: Dénominateur est zéro dans le calcul de l'annuité constante.");
            return 0.0;
        }

        $annuite = bcmul($montantCapitalInitial_bc, bcdiv($tauxMensuel_bc, $denom, 10), 10);
        return (float)$annuite;

    } else {
        // Sans BCMath (moins précis pour les calculs financiers)
        $pow_result = pow(1 + $tauxMensuel, -$nombreEcheancesAmortissables);
        $denom = 1 - $pow_result;

        if ($denom == 0) {
            // Cela ne devrait pas arriver avec un taux > 0
            error_log("calculerEcheanceMensuelle: Dénominateur est zéro dans le calcul de l'annuité constante (sans BCMath).");
            return 0.0;
        }

        $annuite = $montantCapitalInitial * ($tauxMensuel / $denom);
        return $annuite;
    }
}


function genererPlanAmortissement(
      PDO $pdo,
    int $idEmprunt,
    float $montantInitialAmort,
    float $tauxAnnuelUsedForCalculation,
    int $nombreEcheances,
    string $typeAmortissement,
    string $datePremiereEcheance,
    array $fraisEtTaxesForm,
    float $taxes,
    string $banque,
    int $moisDiffere = 0,
    bool $echeanceFinMois = false,
    bool $nombreJoursReels = false,
    ?string $dateDebutAmortissement = null,
    float $interetSpTaux = 0.0
): bool {
    // Debug: Indicates the start of the function
    echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: genererPlanAmortissement started for Emprunt ID: {$idEmprunt}<br>";

    // Use bcmath for better precision if available
    $use_bcmath = function_exists('bcpow');
    if ($use_bcmath) {
        bcscale(10); // Set precision for bcmath calculations
    }

    // Start transaction
    $pdo->beginTransaction();
    echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Transaction started.<br>";

    try {
        // --- Parameter Validation ---
        if ($montantInitialAmort <= 0 || $tauxAnnuelUsedForCalculation < 0 || $nombreEcheances <= 0 || empty($datePremiereEcheance)) {
            error_log("genererPlanAmortissement: Invalid parameters - Montant Initial Amort: {$montantInitialAmort}, Taux: {$tauxAnnuelUsedForCalculation}, Nb Echeances: {$nombreEcheances}, Date 1ere Ech: {$datePremiereEcheance}. Emprunt ID: {$idEmprunt}");
            echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Validation failed - Invalid parameters.<br>";
            throw new InvalidArgumentException("Paramètres d'entrée invalides pour la génération du plan d'amortissement.");
        }

        if ($taxes < 0) {
            error_log("genererPlanAmortissement: Invalid TVA rate: {$taxes}. Emprunt ID: {$idEmprunt}");
            echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Validation failed - Invalid TVA rate.<br>";
            throw new InvalidArgumentException("Le taux de TVA ne peut pas être négatif.");
        }

        if ($interetSpTaux < 0) {
            error_log("genererPlanAmortissement: Invalid SP Interest Rate: {$interetSpTaux}. Emprunt ID: {$idEmprunt}");
            echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Validation failed - Invalid Interet SP Taux.<br>";
            throw new InvalidArgumentException("Le taux d'intérêt SP ne peut pas être négatif.");
        }

        if ($moisDiffere < 0) {
            error_log("genererPlanAmortissement: Invalid deferred months: {$moisDiffere}. Emprunt ID: {$idEmprunt}");
            echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Validation failed - Invalid moisDiffere.<br>";
            throw new InvalidArgumentException("Le nombre de mois différé ne peut pas être négatif.");
        }

        if ($dateDebutAmortissement !== null && !strtotime($dateDebutAmortissement)) {
            error_log("genererPlanAmortissement: Invalid amortization start date: {$dateDebutAmortissement}. Emprunt ID: {$idEmprunt}");
            echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Validation failed - Invalid Date Debut Amortissement.<br>";
            throw new InvalidArgumentException("Date de début d'amortissement invalide.");
        }

        echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Parameters valid. Starting calculation loop.<br>";

        // --- Initialization of calculation variables ---
        $tauxMensuelDecimal = $use_bcmath ? bcdiv((string)$tauxAnnuelUsedForCalculation, '1200', 10) : ($tauxAnnuelUsedForCalculation / 100) / 12;
        $capitalRestant = $use_bcmath ? (string)$montantInitialAmort : $montantInitialAmort;
        $dateEcheance = new DateTime($datePremiereEcheance);

        $annuiteConstante = 0;
        $nombreEcheancesPourAnnuite = $nombreEcheances;

        if (strtolower($typeAmortissement) == 'annuite' || (strtolower($typeAmortissement) == 'differe' && $nombreEcheances > $moisDiffere)) {
            $capitalPourAnnuite = $montantInitialAmort;

            if (strtolower($typeAmortissement) == 'differe' && $moisDiffere > 0) {
                $nombreEcheancesPourAnnuite = $nombreEcheances - $moisDiffere;
                if ($nombreEcheancesPourAnnuite <= 0) {
                    error_log("genererPlanAmortissement: Invalid or zero post-deferred installments: {$nombreEcheancesPourAnnuite}. Emprunt ID: {$idEmprunt}");
                    echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Validation failed - No post-differe payments.<br>";
                    throw new InvalidArgumentException("Nombre d'échéances post-différé invalide.");
                }
            }

            if ($nombreEcheancesPourAnnuite > 0) {
                $annuiteConstante = calculerEcheanceMensuelle((float)$capitalPourAnnuite, $tauxAnnuelUsedForCalculation, $nombreEcheancesPourAnnuite);
                if ($annuiteConstante === false || ($use_bcmath ? bccomp((string)$annuiteConstante, '0', 10) <= 0 : $annuiteConstante <= 0)) {
                    error_log("genererPlanAmortissement: Error or zero/negative annuity ({$annuiteConstante}) when calculating constant annuity for type '{$typeAmortissement}'. Capital: {$capitalPourAnnuite}, Taux: {$tauxAnnuelUsedForCalculation}, Nb Echeances: {$nombreEcheancesPourAnnuite}. Emprunt ID: {$idEmprunt}");
                    echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Error calculating constant annuity.<br>";
                    throw new Exception("Erreur lors du calcul de l'annuité constante.");
                }
                echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Calculated constant annuity: {$annuiteConstante}<br>";
            }
        }

        // Prepare the insertion query for installments
        $sqlInsertionEcheance = "
            INSERT INTO Echeances_Amortissement (
                ID_Emprunt, Numero_Echeance, Date_Echeance,
                Amortissement, Interet_SP, Taxes_Interet_SP,
                Comm_Engagement, Comm_Deblocage, Taxe_Comm_E, Taxe_Comm_D,
                Frais_Etude, Taxe_Frais_Etude, Taxe_Capital,
                Montant_Echeance, Etat_Reste_Du
            ) VALUES (
                :id_emprunt, :numero, :date,
                :amortissement, :interet, :taxe_interet_sp,
                :comm_engagement, :comm_deblocage, :taxe_comm_e, :taxe_comm_d,
                :frais_etude, :taxe_frais_etude, :taxe_capital,
                :montant_total, :reste_du
            )
        ";
        $stmtInsertionEcheance = $pdo->prepare($sqlInsertionEcheance);
        echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Insert statement prepared.<br>";

        // --- Loop for generating installments ---
        for ($i = 1; $i <= $nombreEcheances; $i++) {
            echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Processing Echeance {$i} of {$nombreEcheances}. Capital Restant: {$capitalRestant}<br>";

            // Calculation of interest for the current period (always based on remaining capital)
            $interet_calc = $use_bcmath ? bcmul($capitalRestant, $tauxMensuelDecimal, 10) : $capitalRestant * $tauxMensuelDecimal;
            $interet = (float)$interet_calc;

            $amortissement = 0.0;
            $montant_total_echeance = 0.0;

            // Extract other fixed fees and taxes from fraisEtTaxesForm
            $comm_engagement = (float)($fraisEtTaxesForm['comm_engagement'] ?? 0.0);
            $comm_deblocage = (float)($fraisEtTaxesForm['comm_deblocage'] ?? 0.0);
            $taxe_comm_e = (float)($fraisEtTaxesForm['taxe_comm_e'] ?? 0.0);
            $taxe_comm_d = (float)($fraisEtTaxesForm['taxe_comm_d'] ?? 0.0);
            $frais_etude = (float)($fraisEtTaxesForm['frais_etude'] ?? 0.0);
            $taxe_frais_etude = (float)($fraisEtTaxesForm['taxe_frais_etude'] ?? 0.0);
            $taxe_capital = (float)($fraisEtTaxesForm['taxe_capital'] ?? 0.0);

            // Calculate the amount of Interest SP Tax based on the provided rate and calculated interest.
           $taxes_interet_sp_amount = $use_bcmath ?
    bcmul($interet, bcdiv((string)$interetSpTaux, '100', 10), 10) :
    $interet * ($interetSpTaux / 100);
            $taxes_interet_sp = (float)$taxes_interet_sp_amount;

            $interet_sp_montant_fixe = (float)($fraisEtTaxesForm['interet_sp_montant'] ?? 0.0);

            echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Echeance {$i} - Interet: {$interet}<br>";
            echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Echeance {$i} - InteretSpTaux: {$interetSpTaux}<br>";
            echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Echeance {$i} - Calculated taxes_interet_sp (percentage-based): {$taxes_interet_sp}<br>";
            echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Echeance {$i} - Fixed interet_sp_montant_fixe: {$interet_sp_montant_fixe}<br>";
            echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Echeance {$i} - Total bound for :taxe_interet_sp: " . round($taxes_interet_sp + $interet_sp_montant_fixe, 2) . "<br>";

            // --- Amortization type calculation logic ---
            switch (strtolower($typeAmortissement)) {
                case 'constant':
                    $amortissement_base = $use_bcmath ?
                        bcdiv((string)$montantInitialAmort, (string)$nombreEcheances, 10) :
                        $montantInitialAmort / $nombreEcheances;

                    $amortissement = ($i == $nombreEcheances) ?
                        ($use_bcmath ? (float)$capitalRestant : $capitalRestant) :
                        (float)$amortissement_base;

                    $total_non_tva_fees = $use_bcmath ?
                        bcadd(bcadd(bcadd(bcadd(bcadd(bcadd(bcadd(bcadd((string)$taxes_interet_sp, (string)$interet_sp_montant_fixe, 10), (string)$comm_engagement, 10), (string)$comm_deblocage, 10),
                            (string)$taxe_comm_e, 10), (string)$taxe_comm_d, 10), (string)$frais_etude, 10), (string)$taxe_frais_etude, 10), (string)$taxe_capital, 10) :
                        $taxes_interet_sp + $interet_sp_montant_fixe + $comm_engagement + $comm_deblocage + $taxe_comm_e + $taxe_comm_d + $frais_etude + $taxe_frais_etude + $taxe_capital;

                    $taxesTVA_calc = $use_bcmath ?
                        bcmul(bcadd((string)$interet, (string)$total_non_tva_fees, 10), bcdiv((string)$taxes, '100', 10), 10) :
                        ($interet + $total_non_tva_fees) * ($taxes / 100);
                    $taxesTVA = (float)$taxesTVA_calc;

                    $montant_total_echeance = $use_bcmath ?
                        bcadd(bcadd(bcadd((string)$amortissement, (string)$interet, 10), (string)$total_non_tva_fees, 10), (string)$taxesTVA, 10) :
                        $amortissement + $interet + $total_non_tva_fees + $taxesTVA;
                    break;

                case 'annuite':
                    if ($i <= $moisDiffere) {
                        $amortissement = 0.0;
                        $total_non_tva_fees = $use_bcmath ?
                            bcadd(bcadd(bcadd(bcadd(bcadd(bcadd(bcadd(bcadd((string)$taxes_interet_sp, (string)$interet_sp_montant_fixe, 10), (string)$comm_engagement, 10), (string)$comm_deblocage, 10),
                                (string)$taxe_comm_e, 10), (string)$taxe_comm_d, 10), (string)$frais_etude, 10), (string)$taxe_frais_etude, 10), (string)$taxe_capital, 10) :
                            $taxes_interet_sp + $interet_sp_montant_fixe + $comm_engagement + $comm_deblocage + $taxe_comm_e + $taxe_comm_d + $frais_etude + $taxe_frais_etude + $taxe_capital;

                        $taxesTVA_calc = $use_bcmath ?
                            bcmul(bcadd((string)$interet, (string)$total_non_tva_fees, 10), bcdiv((string)$taxes, '100', 10), 10) :
                            ($interet + $total_non_tva_fees) * ($taxes / 100);
                        $taxesTVA = (float)$taxesTVA_calc;

                        $montant_total_echeance = $use_bcmath ?
                            bcadd(bcadd((string)$interet, (string)$total_non_tva_fees, 10), (string)$taxesTVA, 10) :
                            $interet + $total_non_tva_fees + $taxesTVA;
                    } else {
                        $amortissement_calc = $use_bcmath ? bcsub((string)$annuiteConstante, (string)$interet, 10) : $annuiteConstante - $interet;
                        $amortissement = (float)$amortissement_calc;

                        $total_non_tva_fees = $use_bcmath ?
                            bcadd(bcadd(bcadd(bcadd(bcadd(bcadd(bcadd(bcadd((string)$taxes_interet_sp, (string)$interet_sp_montant_fixe, 10), (string)$comm_engagement, 10), (string)$comm_deblocage, 10),
                                (string)$taxe_comm_e, 10), (string)$taxe_comm_d, 10), (string)$frais_etude, 10), (string)$taxe_frais_etude, 10), (string)$taxe_capital, 10) :
                            $taxes_interet_sp + $interet_sp_montant_fixe + $comm_engagement + $comm_deblocage + $taxe_comm_e + $taxe_comm_d + $frais_etude + $taxe_frais_etude + $taxe_capital;

                        $taxesTVA_calc = $use_bcmath ?
                            bcmul(bcadd((string)$interet, (string)$total_non_tva_fees, 10), bcdiv((string)$taxes, '100', 10), 10) :
                            ($interet + $total_non_tva_fees) * ($taxes / 100);
                        $taxesTVA = (float)$taxesTVA_calc;

                        $montant_total_echeance = $use_bcmath ?
                            bcadd(bcadd(bcadd((string)$amortissement, (string)$interet, 10), (string)$total_non_tva_fees, 10), (string)$taxesTVA, 10) :
                            $amortissement + $interet + $total_non_tva_fees + $taxesTVA;
                    }
                    break;

                case 'in fine':
                    $amortissement = 0.0;
                    if ($i == $nombreEcheances) {
                        $amortissement = $use_bcmath ? (float)$montantInitialAmort : $montantInitialAmort;
                    }

                    $total_non_tva_fees = $use_bcmath ?
                        bcadd(bcadd(bcadd(bcadd(bcadd(bcadd(bcadd(bcadd((string)$taxes_interet_sp, (string)$interet_sp_montant_fixe, 10), (string)$comm_engagement, 10), (string)$comm_deblocage, 10),
                            (string)$taxe_comm_e, 10), (string)$taxe_comm_d, 10), (string)$frais_etude, 10), (string)$taxe_frais_etude, 10), (string)$taxe_capital, 10) :
                        $taxes_interet_sp + $interet_sp_montant_fixe + $comm_engagement + $comm_deblocage + $taxe_comm_e + $taxe_comm_d + $frais_etude + $taxe_frais_etude + $taxe_capital;

                    $taxesTVA_calc = $use_bcmath ?
                        bcmul(bcadd((string)$interet, (string)$total_non_tva_fees, 10), bcdiv((string)$taxes, '100', 10), 10) :
                        ($interet + $total_non_tva_fees) * ($taxes / 100);
                    $taxesTVA = (float)$taxesTVA_calc;

                    $montant_total_echeance = $use_bcmath ?
                        bcadd(bcadd(bcadd((string)$amortissement, (string)$interet, 10), (string)$total_non_tva_fees, 10), (string)$taxesTVA, 10) :
                        $amortissement + $interet + $total_non_tva_fees + $taxesTVA;
                    break;

                case 'differe':
                    if ($i <= $moisDiffere) {
                        $amortissement = 0.0;
                        $total_non_tva_fees = $use_bcmath ?
                            bcadd(bcadd(bcadd(bcadd(bcadd(bcadd(bcadd(bcadd((string)$taxes_interet_sp, (string)$interet_sp_montant_fixe, 10), (string)$comm_engagement, 10), (string)$comm_deblocage, 10),
                                (string)$taxe_comm_e, 10), (string)$taxe_comm_d, 10), (string)$frais_etude, 10), (string)$taxe_frais_etude, 10), (string)$taxe_capital, 10) :
                            $taxes_interet_sp + $interet_sp_montant_fixe + $comm_engagement + $comm_deblocage + $taxe_comm_e + $taxe_comm_d + $frais_etude + $taxe_frais_etude + $taxe_capital;

                        $taxesTVA_calc = $use_bcmath ?
                            bcmul(bcadd((string)$interet, (string)$total_non_tva_fees, 10), bcdiv((string)$taxes, '100', 10), 10) :
                            ($interet + $total_non_tva_fees) * ($taxes / 100);
                        $taxesTVA = (float)$taxesTVA_calc;

                        $montant_total_echeance = $use_bcmath ?
                            bcadd(bcadd((string)$interet, (string)$total_non_tva_fees, 10), (string)$taxes, 10) :
                            $interet + $total_non_tva_fees + $taxesTVA;
                    } else {
                        $amortissement_calc = $use_bcmath ? bcsub((string)$annuiteConstante, (string)$interet, 10) : $annuiteConstante - $interet;
                        $amortissement = (float)$amortissement_calc;

                        $total_non_tva_fees = $use_bcmath ?
                            bcadd(bcadd(bcadd(bcadd(bcadd(bcadd(bcadd(bcadd((string)$taxes_interet_sp, (string)$interet_sp_montant_fixe, 10), (string)$comm_engagement, 10), (string)$comm_deblocage, 10),
                                (string)$taxe_comm_e, 10), (string)$taxe_comm_d, 10), (string)$frais_etude, 10), (string)$taxe_frais_etude, 10), (string)$taxe_capital, 10) :
                            $taxes_interet_sp + $interet_sp_montant_fixe + $comm_engagement + $comm_deblocage + $taxe_comm_e + $taxe_comm_d + $frais_etude + $taxe_frais_etude + $taxe_capital;

                        $taxesTVA_calc = $use_bcmath ?
                            bcmul(bcadd((string)$interet, (string)$total_non_tva_fees, 10), bcdiv((string)$taxes, '100', 10), 10) :
                            ($interet + $total_non_tva_fees) * ($taxes / 100);
                        $taxesTVA = (float)$taxesTVA_calc;

                        $montant_total_echeance = $use_bcmath ?
                            bcadd(bcadd(bcadd((string)$amortissement, (string)$interet, 10), (string)$total_non_tva_fees, 10), (string)$taxesTVA, 10) :
                            $amortissement + $interet + $total_non_tva_fees + $taxesTVA;
                    }
                    break;
					
					case 'degressif':
        // Ensure $tauxDegressif is defined and represents the declining balance rate (e.g., 2 for double declining)
        // Also, ensure $montantInitialAmort (or a similar variable) is passed as the initial capital for this calculation.
        // The declining balance rate is often a multiple of the straight-line rate.
        // For simplicity, let's assume $tauxDegressif is already adjusted, e.g., 0.05 for 5% declining rate on remaining balance.
        // You might need to calculate $tauxDegressif based on $nombreEcheances and a degressive factor.

        // The amortization for a degressive method is applied to the remaining capital.
        // Assuming $tauxDegressif is a decimal (e.g., 0.10 for 10% declining balance rate)
        // You would typically define $tauxDegressif outside this loop, as it's a fixed rate.
        // For example: $tauxDegressif = 0.20; // 20% declining balance rate

        $amortissement_calc = $use_bcmath ?
            bcmul((string)$capitalRestant, (string)$tauxDegressif, 10) :
            $capitalRestant * $tauxDegressif;

        // In the last period, the remaining balance is typically fully amortized.
        if ($i == $nombreEcheances) {
             $amortissement = (float)$capitalRestant; // Amortize the full remaining balance
        } else {
             $amortissement = (float)$amortissement_calc;
        }

        // Apply any minimum amortization rule if applicable (e.g., switch to straight-line if it yields higher amort)
        // This is a common practice in declining balance, but not included here for simplicity.

        $total_non_tva_fees = $use_bcmath ? 
            bcadd(bcadd(bcadd(bcadd(bcadd(bcadd(bcadd(bcadd((string)$taxes_interet_sp, (string)$interet_sp_montant_fixe, 10), (string)$comm_engagement, 10), (string)$comm_deblocage, 10),
                (string)$taxe_comm_e, 10), (string)$taxe_comm_d, 10), (string)$frais_etude, 10), (string)$taxe_frais_etude, 10), (string)$taxe_capital, 10) :
            $taxes_interet_sp + $interet_sp_montant_fixe + $comm_engagement + $comm_deblocage + $taxe_comm_e + $taxe_comm_d + $frais_etude + $taxe_frais_etude + $taxe_capital;

        $taxesTVA_calc = $use_bcmath ?
            bcmul(bcadd((string)$interet, (string)$total_non_tva_fees, 10), bcdiv((string)$taxes, '100', 10), 10) :
            ($interet + $total_non_tva_fees) * ($taxes / 100);
        $taxesTVA = (float)$taxesTVA_calc;

        $montant_total_echeance = $use_bcmath ?
            bcadd(bcadd(bcadd((string)$amortissement, (string)$interet, 10), (string)$total_non_tva_fees, 10), (string)$taxesTVA, 10) :
            $amortissement + $interet + $total_non_tva_fees + $taxesTVA;
        break;

                default:
                    error_log("genererPlanAmortissement: Type d'amortissement non géré: {$typeAmortissement}. Emprunt ID: {$idEmprunt}");
                    echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Invalid amortization type.<br>";
                    throw new InvalidArgumentException("Type d'amortissement non pris en charge.");
            }

            // Update remaining capital
            $capitalRestant = $use_bcmath ? bcsub($capitalRestant, (string)$amortissement, 10) : $capitalRestant - $amortissement;
            if ($i == $nombreEcheances) {
                $capitalRestant = 0.0;
            } else {
                $capitalRestant = ($use_bcmath ? bccomp($capitalRestant, '0', 10) < 0 : $capitalRestant < 0) ? 0.0 : $capitalRestant;
            }

     
            // Binding parameters and executing the insert statement
            $stmtInsertionEcheance->bindValue(':id_emprunt', $idEmprunt, PDO::PARAM_INT);
            $stmtInsertionEcheance->bindValue(':numero', $i, PDO::PARAM_INT);
            $stmtInsertionEcheance->bindValue(':date', $dateEcheance->format('Y-m-d'), PDO::PARAM_STR);
            $stmtInsertionEcheance->bindValue(':amortissement', round($amortissement, 2), PDO::PARAM_STR);
            $stmtInsertionEcheance->bindValue(':interet', round($interet, 2), PDO::PARAM_STR);
            $stmtInsertionEcheance->bindValue(':taxe_interet_sp', round($taxes_interet_sp + $interet_sp_montant_fixe, 2), PDO::PARAM_STR);
            $stmtInsertionEcheance->bindValue(':comm_engagement', round($comm_engagement, 2), PDO::PARAM_STR);
            $stmtInsertionEcheance->bindValue(':comm_deblocage', round($comm_deblocage, 2), PDO::PARAM_STR);
            $stmtInsertionEcheance->bindValue(':taxe_comm_e', round($taxe_comm_e, 2), PDO::PARAM_STR);
            $stmtInsertionEcheance->bindValue(':taxe_comm_d', round($taxe_comm_d, 2), PDO::PARAM_STR);
            $stmtInsertionEcheance->bindValue(':frais_etude', round($frais_etude, 2), PDO::PARAM_STR);
            $stmtInsertionEcheance->bindValue(':taxe_frais_etude', round($taxe_frais_etude, 2), PDO::PARAM_STR);
            $stmtInsertionEcheance->bindValue(':taxe_capital', round($taxe_capital, 2), PDO::PARAM_STR);
            $stmtInsertionEcheance->bindValue(':montant_total', round($montant_total_echeance, 2), PDO::PARAM_STR);
            $stmtInsertionEcheance->bindValue(':reste_du', round((float)$capitalRestant, 2), PDO::PARAM_STR);

            $stmtInsertionEcheance->execute();
            echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Echeance {$i} inserted. Next Capital Restant: " . round((float)$capitalRestant, 2) . "<br>";

            // Move to the next installment date
            if ($echeanceFinMois) {
                $dateEcheance->modify('last day of next month');
            } else {
                $dateEcheance->modify('+1 month');
            }
        }

        // Commit the transaction if all insertions are successful
        $pdo->commit();
        echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Transaction committed successfully.<br>";
        return true;

    } catch (Exception $e) {
        // Rollback the transaction on error
        $pdo->rollBack();
        error_log("Erreur lors de la génération du plan d'amortissement pour l'emprunt ID {$idEmprunt}: " . $e->getMessage());
        echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Debug: Transaction rolled back. Error: " . $e->getMessage() . "<br>";
        return false;
    }
}

function supprimerEmprunt(PDO $pdo, int $id): bool {
    // Il est préférable de gérer la transaction au niveau du script appelant
    // (ici supprimer_groupe.php) pour les opérations de groupe.
    // Cependant, si cette fonction est aussi appelée individuellement,
    // une transaction interne est un bon fallback. Pour une cohérence maximale
    // avec supprimer_groupe.php, j'ai simplifié la transaction ici.
    // Si supprimer_groupe.php gère déjà la transaction, celle-ci n'est pas strictement nécessaire
    // mais ne nuit pas (elle créera une transaction imbriquée si le driver supporte ou la promouvra à la transaction existante).

    try {
        // Supprimer d'abord les échéances d'amortissement liées à cet emprunt
        $stmtEcheances = $pdo->prepare("DELETE FROM Echeances_Amortissement WHERE ID_Emprunt = :id");
        $stmtEcheances->bindParam(':id', $id, PDO::PARAM_INT);
        $stmtEcheances->execute();

        // Puis supprimer l'emprunt lui-même
        $stmtEmprunt = $pdo->prepare("DELETE FROM Emprunts_Bancaires WHERE ID_Emprunt = :id");
        $stmtEmprunt->bindParam(':id', $id, PDO::PARAM_INT);
        $result = $stmtEmprunt->execute();

        return $result; // Retourne true si l'emprunt a été supprimé (même si 0 lignes affectées si déjà disparu)
    } catch (PDOException $e) {
        // Loggez l'erreur pour le débogage
        error_log("Erreur lors de la suppression de l'emprunt (ID: $id) ou de ses échéances : " . $e->getMessage());
        return false;
    }
}


function ajouterEmprunt(PDO $pdo, array $data): int|false {
    try {
        // Préparer la requête SQL d'insertion
        $sql = "INSERT INTO Emprunts_Bancaires (
                    Banque, Date_Souscription, Agence, Devise, Numero_Pret,
                    Type_Pret, Client, Montant_Pret, Source_Financement,
                    Taux_Effectif_Global, Montant_Differe, Type_Plan,
                    Echeance_Fin_Mois, Nombre_Echeances, Gestion_Differe,
                    Nombre_Jours_Reels, Type_Interet, Date_Mise_En_Place,
                    Date_Premiere_Echeance, Date_Derniere_Echeance, Interet_SP_Taux,
                    Interet_SP_Montant, Taxes, Periode, Terme, Perception,
                    Date_Debut_Amortissement, Date_Fin_Amortissement, Montant_Initial, Duree, Type_Amortissement
                ) VALUES (
                    :banque, :date_souscription, :agence, :devise, :numero_pret,
                    :type_pret, :client, :montant_pret, :source_financement,
                    :taux_effectif_global, :montant_differe, :type_plan,
                    :echeance_fin_mois, :nombre_echeances, :gestion_differe,
                    :nombre_jours_reels, :type_interet, :date_mise_en_place,
                    :date_premiere_echeance, :date_derniere_echeance, :interet_sp_taux,
                    :interet_sp_montant, :taxes, :periode, :terme, :perception,
                    :date_debut_amortissement, :date_fin_amortissement, :montant_initial, :duree, :type_amortissement
                )";

        $stmt = $pdo->prepare($sql);

        // --- Liaison des paramètres ---
        // Utilisation de bindParam pour des raisons de flexibilité et gestion des types
        $stmt->bindParam(':banque', $data['Banque']);
        $stmt->bindParam(':date_souscription', $data['Date_Souscription']); // Assurez-vous que le format est 'YYYY-MM-DD'
        $stmt->bindParam(':agence', $data['Agence']);
        $stmt->bindParam(':devise', $data['Devise']);
        $stmt->bindParam(':numero_pret', $data['Numero_Pret']);
        $stmt->bindParam(':type_pret', $data['Type_Pret']);
        $stmt->bindParam(':client', $data['Client']);
        $stmt->bindParam(':montant_pret', $data['Montant_Pret'], PDO::PARAM_STR); // DECIMAL(18,2) -> PDO::PARAM_STR
        $stmt->bindParam(':source_financement', $data['Source_Financement']);
        $stmt->bindParam(':taux_effectif_global', $data['Taux_Effectif_Global'], PDO::PARAM_STR); // DECIMAL(10,6) -> PDO::PARAM_STR
        $stmt->bindParam(':montant_differe', $data['Montant_Differe'], PDO::PARAM_STR); // DECIMAL(18,2) -> PDO::PARAM_STR
        $stmt->bindParam(':type_plan', $data['Type_Plan']);
        $stmt->bindParam(':echeance_fin_mois', $data['Echeance_Fin_Mois'], PDO::PARAM_BOOL); // BIT -> PDO::PARAM_BOOL
        $stmt->bindParam(':nombre_echeances', $data['Nombre_Echeances'], PDO::PARAM_INT);
        $stmt->bindParam(':gestion_differe', $data['Gestion_Differe']);
        $stmt->bindParam(':nombre_jours_reels', $data['Nombre_Jours_Reels'], PDO::PARAM_BOOL); // BIT -> PDO::PARAM_BOOL
        $stmt->bindParam(':type_interet', $data['Type_Interet']);
        $stmt->bindParam(':date_mise_en_place', $data['Date_Mise_En_Place']); // Assurez-vous que le format est 'YYYY-MM-DD'
        $stmt->bindParam(':date_premiere_echeance', $data['Date_Premiere_Echeance']); // Assurez-vous que le format est 'YYYY-MM-DD'
        $stmt->bindParam(':date_derniere_echeance', $data['Date_Derniere_Echeance']); // Assurez-vous que le format est 'YYYY-MM-DD'
        $stmt->bindParam(':interet_sp_taux', $data['Interet_SP_Taux'], PDO::PARAM_STR); // DECIMAL -> PDO::PARAM_STR
        $stmt->bindParam(':interet_sp_montant', $data['Interet_SP_Montant'], PDO::PARAM_STR); // DECIMAL -> PDO::PARAM_STR
        $stmt->bindParam(':taxes', $data['Taxes'], PDO::PARAM_STR); // DECIMAL -> PDO::PARAM_STR
        $stmt->bindParam(':periode', $data['Periode']);
        $stmt->bindParam(':terme', $data['Terme']);
        $stmt->bindParam(':perception', $data['Perception']);
        $stmt->bindParam(':date_debut_amortissement', $data['Date_Debut_Amortissement']); // Assurez-vous que le format est 'YYYY-MM-DD'
        $stmt->bindParam(':date_fin_amortissement', $data['Date_Fin_Amortissement']); // Assurez-vous que le format est 'YYYY-MM-DD'
        $stmt->bindParam(':montant_initial', $data['Montant_Initial'], PDO::PARAM_STR); // DECIMAL -> PDO::PARAM_STR (Montant Initial Amortissable)
        $stmt->bindParam(':duree', $data['Duree'], PDO::PARAM_INT);
        $stmt->bindParam(':type_amortissement', $data['Type_Amortissement']);

        // Exécuter la requête
        $stmt->execute();

        // Retourne l'ID de la dernière insertion (pour SQL Server avec IDENTITY)
        // Note: Pour les bases de données SQL Server, lastInsertId() fonctionne généralement sans argument
        // ou avec le nom de la séquence si elle est explicitement utilisée.
        // Assurez-vous que votre colonne ID_Emprunt est bien définie comme IDENTITY(1,1).
        return (int)$pdo->lastInsertId();

    } catch (PDOException $e) {
        // Log l'erreur pour le débogage, mais ne l'affiche pas à l'utilisateur
        error_log("Erreur PDO lors de l'ajout de l'emprunt : " . $e->getMessage() . " | SQLSTATE: " . ($e->errorInfo[0] ?? 'N/A') . " | Driver Code: " . ($e->errorInfo[1] ?? 'N/A') . " | Driver Message: " . ($e->errorInfo[2] ?? 'N/A'));
        // Vous pouvez également stocker un message dans la session si vous voulez l'afficher à l'utilisateur après redirection
        $_SESSION['error_message'] = "Une erreur est survenue lors de l'ajout de l'emprunt.";
        return false;
    } catch (Exception $e) {
        // Gérer d'autres exceptions non PDO (par exemple, si une clé manquante dans $data cause une erreur)
        error_log("Erreur générale lors de l'ajout de l'emprunt : " . $e->getMessage());
        $_SESSION['error_message'] = "Une erreur inattendue est survenue lors de l'ajout de l'emprunt.";
        return false;
    }
}

function getEmpruntById(PDO $pdo, int $idEmprunt): ?array {
    try {
        // Préparer la requête pour sélectionner les informations de l'emprunt
        // Sélectionner au moins ID_Emprunt et Numero_Pret.
        // Vous pouvez ajouter d'autres colonnes si vous en avez besoin ailleurs (ex: Banque, Montant_Pret).
        $sql = "SELECT ID_Emprunt, Numero_Pret, Banque, Montant_Pret
                FROM Emprunts_Bancaires
                WHERE ID_Emprunt = :id";

        $stmt = $pdo->prepare($sql);

        // Exécuter la requête avec l'ID de l'emprunt
        $stmt->execute([':id' => $idEmprunt]);

        // Récupérer la première ligne de résultat (il ne devrait y en avoir qu'une pour un ID unique)
        $emprunt = $stmt->fetch(PDO::FETCH_ASSOC);

        // Retourner le tableau associatif si un emprunt est trouvé, sinon retourner null
        return $emprunt ?: null;

    } catch (PDOException $e) {
        // En cas d'erreur de base de données, journaliser l'erreur
        error_log("Erreur DB lors de la récupération de l'emprunt ID " . $idEmprunt . ": " . $e->getMessage());
        // Retourner null pour indiquer qu'une erreur s'est produite
        return null;
    }
}


function getEmprunt(PDO $pdo, int $idEmprunt): array|false {
    try {
        $sql = "SELECT 
            Banque, 
            Numero_Pret, 
            Montant_Pret, 
            Date_Mise_En_Place, 
            Date_Premiere_Echeance, 
            Date_Derniere_Echeance, 
            Taux_Effectif_Global, 
            Nombre_Echeances,
            Type_Amortissement
        FROM 
            Emprunts_Bancaires  -- Utilisez le nom de table correct
        WHERE 
            ID_Emprunt = :id_emprunt";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_emprunt', $idEmprunt, PDO::PARAM_INT);
        $stmt->execute();
        $emprunt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $emprunt ? $emprunt : false;
        
    } catch (PDOException $e) {
        // Gestion de l'erreur : journaliser et retourner false
        error_log("Erreur lors de la récupération de l'emprunt : " . $e->getMessage());
        return false;
    }
}

// Fonction pour modifier un emprunt
function modifierEmprunt(
    PDO $pdo,
    int $idEmprunt,
    string $banque,
    string $dateMiseEnPlace,
    ?string $agence,  
    ?string $devise,  
    string $numeroPret,
    ?string $typePret,  
    ?string $client,  
    float $montantPret,
    ?string $sourceFinancement,  
    float $tauxInteret,
    ?float $montantDiffere,  
    ?string $typePlan,  
    ?bool $echeanceFinMois,  
    int $nombreEcheances,
    ?string $gestionDiffere,  
    ?int $nombreJoursReels,  
    ?string $typeInteret,  
    string $dateValeur, 
    string $datePremiereEcheance,
    string $dateDerniereEcheance,
    ?float $interetSpTaux,  
    ?float $interetSpMontant,  
    ?float $taxes,  
    ?string $periode,  
    ?string $terme,  
    ?string $perception,  
    ?string $dateDebutAmortissement,  
    ?string $dateFinAmortissement,  
    float $montantInitial,
    ?int $duree,  
    string $typeAmortissement
): bool {
    try {
        $sql = "UPDATE Emprunts_Bancaires SET  -- Utilisez le nom de table correct
            Banque = :banque,
            Date_Souscription = :dateMiseEnPlace, -- Utilisez le nom de colonne correct
            Agence = :agence,
            Devise = :devise,
            Numero_Pret = :numeroPret,
            Type_Pret = :typePret,
            Client = :client,
            Montant_Pret = :montantPret,
            Source_Financement = :sourceFinancement,
            Taux_Effectif_Global = :tauxInteret,
            Montant_Differe = :montantDiffere,
            Type_Plan = :typePlan,
            Echeance_Fin_Mois = :echeanceFinMois,
            Nombre_Echeances = :nombreEcheances,
            Gestion_Differe = :gestionDiffere,
            Nombre_Jours_Reels = :nombreJoursReels,
            Type_Interet = :typeInteret,
            Date_Mise_En_Place = :dateValeur,  -- Utilisez le nom de colonne correct
            Date_Premiere_Echeance = :datePremiereEcheance,
            Date_Derniere_Echeance = :dateDerniereEcheance,
            Interet_SP_Taux = :interetSpTaux,
            Interet_SP_Montant = :interetSpMontant,
            Taxes = :taxes,
            Periode = :periode,
            Terme = :terme,
            Perception = :perception,
            Date_Debut_Amortissement = :dateDebutAmortissement,
            Date_Fin_Amortissement = :dateFinAmortissement,
            Montant_Initial = :montantInitial,
            Duree = :duree,
            Type_Amortissement = :typeAmortissement
        WHERE 
            ID_Emprunt = :idEmprunt";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':idEmprunt', $idEmprunt, PDO::PARAM_INT);
        $stmt->bindParam(':banque', $banque, PDO::PARAM_STR);
        $stmt->bindParam(':dateMiseEnPlace', $dateMiseEnPlace, PDO::PARAM_STR);
        $stmt->bindParam(':agence', $agence, PDO::PARAM_STR);
        $stmt->bindParam(':devise', $devise, PDO::PARAM_STR);
        $stmt->bindParam(':numeroPret', $numeroPret, PDO::PARAM_STR);
        $stmt->bindParam(':typePret', $typePret, PDO::PARAM_STR);
        $stmt->bindParam(':client', $client, PDO::PARAM_STR);
        $stmt->bindParam(':montantPret', $montantPret, PDO::PARAM_STR);
        $stmt->bindParam(':sourceFinancement', $sourceFinancement, PDO::PARAM_STR);
        $stmt->bindParam(':tauxInteret', $tauxInteret, PDO::PARAM_STR);
        $stmt->bindParam(':montantDiffere', $montantDiffere, PDO::PARAM_STR);
        $stmt->bindParam(':typePlan', $typePlan, PDO::PARAM_STR);
        $stmt->bindParam(':echeanceFinMois', $echeanceFinMois, PDO::PARAM_BOOL);
        $stmt->bindParam(':nombreEcheances', $nombreEcheances, PDO::PARAM_INT);
        $stmt->bindParam(':gestionDiffere', $gestionDiffere, PDO::PARAM_STR);
        $stmt->bindParam(':nombreJoursReels', $nombreJoursReels, PDO::PARAM_INT);
        $stmt->bindParam(':typeInteret', $typeInteret, PDO::PARAM_STR);
        $stmt->bindParam(':dateValeur', $dateValeur, PDO::PARAM_STR);
        $stmt->bindParam(':datePremiereEcheance', $datePremiereEcheance, PDO::PARAM_STR);
        $stmt->bindParam(':dateDerniereEcheance', $dateDerniereEcheance, PDO::PARAM_STR);
        $stmt->bindParam(':interetSpTaux', $interetSpTaux, PDO::PARAM_STR);
        $stmt->bindParam(':interetSpMontant', $interetSpMontant, PDO::PARAM_STR);
        $stmt->bindParam(':taxes', $taxes, PDO::PARAM_STR);
        $stmt->bindParam(':periode', $periode, PDO::PARAM_STR);
        $stmt->bindParam(':terme', $terme, PDO::PARAM_STR);
        $stmt->bindParam(':perception', $perception, PDO::PARAM_STR);
        $stmt->bindParam(':dateDebutAmortissement', $dateDebutAmortissement, PDO::PARAM_STR);
        $stmt->bindParam(':dateFinAmortissement', $dateFinAmortissement, PDO::PARAM_STR);
        $stmt->bindParam(':montantInitial', $montantInitial, PDO::PARAM_STR);
        $stmt->bindParam(':duree', $duree, PDO::PARAM_INT);
        $stmt->bindParam(':typeAmortissement', $typeAmortissement, PDO::PARAM_STR);
        
        return $stmt->execute();
        
    } catch (PDOException $e) {
        // Gestion de l'erreur : journaliser et retourner false
        error_log("Erreur lors de la modification de l'emprunt : " . $e->getMessage());
        return false;
    }
}



?>