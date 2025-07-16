<?php
// process_risk.php - Ce fichier recevra les données du formulaire et effectuera l'analyse de risque.

header('Content-Type: application/json'); // Indique que la réponse sera du JSON

$response = [
    'status' => 'error',
    'message' => 'Données invalides.',
    'data' => null
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données envoyées via AJAX
    $formData = json_decode(file_get_contents('php://input'), true);

    // Si json_decode échoue ou si les données ne sont pas présentes
    if ($formData === null) {
        // Fallback pour les données encodées en 'application/x-www-form-urlencoded' (comme par $.ajax par défaut)
        $formData = $_POST;
    }

    // Valider les données reçues
    if (empty($formData) || !isset($formData['transaction_amount']) || !isset($formData['customer_id'])) {
        echo json_encode($response);
        exit;
    }

    // Appeler la fonction d'analyse de risque (la logique JavaScript portée en PHP)
    // C'est ici que vous intégreriez une logique d'analyse plus robuste,
    // potentiellement avec des requêtes à une base de données, etc.
    $result = calculateRiskServerSide($formData);

    $response['status'] = 'success';
    $response['message'] = 'Analyse de risque effectuée avec succès.';
    $response['data'] = $result;

    echo json_encode($response);

} else {
    $response['message'] = 'Méthode de requête non autorisée.';
    echo json_encode($response);
}

// =======================================================
// Fonction d'analyse de risque (portée du JavaScript en PHP)
// Vous DEVEZ affiner et sécuriser cette logique pour une application réelle.
// =======================================================
function calculateRiskServerSide($data) {
    $riskScore = 0;
    $riskFactors = [];
    $maxScore = 100; // Définir un score maximum pour la normalisation

    // Fonction pour ajouter un facteur de risque si une condition est remplie
    $addRiskFactor = function($condition, $factor) use (&$riskFactors) {
        if ($condition) {
            $riskFactors[] = $factor;
        }
    };

    // Cast des valeurs numériques pour s'assurer qu'elles sont bien des nombres
    $data['transaction_amount'] = (float)($data['transaction_amount'] ?? 0);
    $data['salaire_pension'] = (float)($data['salaire_pension'] ?? 0);
    $data['solde_debiteur'] = (float)($data['solde_debiteur'] ?? 0);
    $data['frais_assurance'] = (float)($data['frais_assurance'] ?? 0);
    $data['frais_etudes_dossier'] = (float)($data['frais_etudes_dossier'] ?? 0);
    $data['commissions'] = (float)($data['commissions'] ?? 0);
    $data['age'] = (int)($data['age'] ?? 0);
    $data['nombre_emails'] = (int)($data['nombre_emails'] ?? 0);
    $data['credit_score'] = (int)($data['credit_score'] ?? 0);
    $data['debt_to_income_ratio'] = (float)($data['debt_to_income_ratio'] ?? 0);


    // Facteurs de risque liés au prêt et au montant
    if ($data['transaction_amount'] > 0 && $data['salaire_pension'] > 0) {
        $amountRatio = $data['transaction_amount'] / $data['salaire_pension'];
        $riskScore += min($amountRatio * 20, 40); // Max 40 points pour ce facteur
        $addRiskFactor($amountRatio > 3, "Montant du prêt élevé par rapport au revenu");
    } else if ($data['transaction_amount'] > 0 && $data['salaire_pension'] === 0.0) { // Utiliser 0.0 pour les floats
        $riskScore += 50; // Très haut risque si pas de revenu déclaré
        $addRiskFactor(true, "Revenu non déclaré");
    }

    // Facteurs liés à l'endettement et au solde débiteur
    $riskScore += $data['debt_to_income_ratio'] * 30; // Un ratio de 1 (100%) ajoute 30 points
    $addRiskFactor($data['debt_to_income_ratio'] >= 0.4, "Ratio d'endettement élevé (>40%)");

    if ($data['solde_debiteur'] > 0 && $data['salaire_pension'] > 0) {
        $debtToSalaryRatio = $data['solde_debiteur'] / $data['salaire_pension'];
        $riskScore += min($debtToSalaryRatio * 10, 20); // Max 20 points
        $addRiskFactor($debtToSalaryRatio > 0.1, "Solde débiteur fréquent");
    } else if ($data['solde_debiteur'] > 0) {
        $riskScore += 15; // Risque si solde débiteur sans revenu connu
        $addRiskFactor(true, "Solde débiteur sans revenu déclaré");
    }

    // Facteurs liés au score de crédit et à l'historique (BIC)
    if ($data['credit_score'] > 0) {
        $riskScore += (1 - ($data['credit_score'] / 850)) * 50;
        $addRiskFactor($data['credit_score'] < 500, "Score de crédit faible");
    } else {
        $riskScore += 30; // Risque si pas de score connu
        $addRiskFactor(true, "Score de crédit non disponible");
    }

    switch ($data['credit_history']) {
        case 'retard_defaut': $riskScore += 40; $addRiskFactor(true, "Historique: Retards/Défauts de paiement"); break;
        case 'incidents_passes': $riskScore += 20; $addRiskFactor(true, "Historique: Incidents passés"); break;
        case 'aucun': $riskScore += 10; $addRiskFactor(true, "Historique: Aucun historique de crédit"); break;
        case 'positif': $riskScore -= 5; break; // Historique positif réduit un peu le risque
    }

    // Facteurs liés à l'âge et à la profession
    if ($data['age'] > 0 && ($data['age'] < 25 || $data['age'] > 60)) {
        $riskScore += 5; // Moins de stabilité ou revenu après retraite
        $addRiskFactor(true, "Âge du client (jeune ou avancé)");
    }
    if ($data['age'] === 0) { // Si l'âge n'est pas renseigné
        $riskScore += 10;
        $addRiskFactor(true, "Âge non renseigné");
    }

    switch ($data['profession']) {
        case 'Secteur informel': $riskScore += 25; $addRiskFactor(true, "Profession: Secteur informel (revenus incertains)"); break;
        case 'Sans activité': $riskScore += 35; $addRiskFactor(true, "Profession: Sans activité"); break;
        case 'Entrepreneur formel': $riskScore -= 5; break; // Stabilité professionnelle
        case 'Fonctionnaire': $riskScore -= 10; break; // Stabilité professionnelle élevée
    }

    // Facteurs liés au type de garantie
    switch ($data['guarantee_type']) {
        case 'aucune': $riskScore += 20; $addRiskFactor(true, "Absence de garantie"); break;
        case 'cautionnement': $riskScore += 10; $addRiskFactor(true, "Garantie par cautionnement simple"); break;
        case 'materiel': $riskScore += 5; break;
        case 'biens_fonciers': $riskScore -= 10; break; // Garantie forte
        case 'epargne_bloquee': $riskScore -= 15; break; // Très forte garantie
        case 'garantie_etat': $riskScore -= 20; break; // La plus forte garantie
    }

    // Impact des frais supplémentaires (plus ils sont élevés, plus le risque est élevé)
    $totalAdditionalFees = 0;
    foreach ($data as $key => $value) {
        if (strpos($key, 'autre_frais_') === 0 && is_numeric($value)) {
            $totalAdditionalFees += (float)$value;
        }
    }
    $riskScore += min($totalAdditionalFees / 1000, 10); // Chaque 1000 XAF de frais ajoute 1 point, max 10 points

    // Assurer que le score ne dépasse pas le maximum et ne soit pas négatif
    $riskScore = max(0, min($riskScore, $maxScore));

    $riskLevel = "Inconnu";
    $action = "Veuillez vérifier les données saisies ou compléter les informations manquantes.";

    if ($riskScore <= 30) {
        $riskLevel = "Faible";
        $action = "Octroi rapide du crédit, conditions favorables, surveillance légère.";
    } else if ($riskScore <= 60) {
        $riskLevel = "Moyen";
        $action = "Analyse plus poussée, conditions de prêt standard, surveillance régulière.";
    } else if ($riskScore <= 85) {
        $riskLevel = "Élevé";
        $action = "Dossier à surveiller de près, taux d'intérêt potentiellement plus élevé, garanties additionnelles requises, approbation par une autorité supérieure.";
    } else if ($riskScore <= 100) {
        $riskLevel = "Très élevé";
        $action = "Refus de crédit recommandé, signalement potentiel aux autorités de régulation (COBAC/BEAC) si des anomalies sont détectées.";
    }

    return ['riskLevel' => $riskLevel, 'action' => $action, 'riskFactors' => $riskFactors];
}
?>