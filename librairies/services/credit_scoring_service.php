<?php

// librairies/services/credit_scoring_service.php

require_once 'fonctions/database.php';

class CreditScoringService {

    private $db;

    public function __construct() {
        $this->db = getDatabaseInstance();
    }

    /**
     * Évalue la solvabilité d'un client pour un montant de crédit donné.
     * Cette méthode est un simulateur basé sur des règles simples.
     * @param int $clientId L'ID du client.
     * @param float $montantCredit Le montant du crédit demandé.
     * @param int $dureeMois La durée du crédit en mois.
     * @return array Un tableau contenant le résultat de l'évaluation.
     */
    public function evaluerSolvabilite($clientId, $montantCredit, $dureeMois) {
        // Points de base pour l'évaluation.
        $score = 50;
        $raisons = [];

        // Étape 1 : Vérification de l'existence du client
        if (!$this->verifierClientExistant($clientId)) {
            $raisons[] = "Client introuvable.";
            return ['est_solvable' => false, 'score' => 0, 'raison' => implode(", ", $raisons)];
        }

        // Étape 2 : Vérification du solde des comptes du client
        $soldeTotal = $this->getSoldeTotalClient($clientId);
        if ($soldeTotal > 5000) {
            $score += 20;
            $raisons[] = "Bonne position financière.";
        } elseif ($soldeTotal < 100) {
            $score -= 15;
            $raisons[] = "Solde des comptes faible.";
        }

        // Étape 3 : Analyse de l'historique de paiement (simulé)
        $historiquePaiement = $this->getHistoriquePaiement($clientId);
        $retards = $historiquePaiement['retards'];
        $paiementsEffectues = $historiquePaiement['paiements_effectues'];
        
        if ($paiementsEffectues > 5 && $retards == 0) {
            $score += 25;
            $raisons[] = "Excellent historique de paiement.";
        } elseif ($retards > 2) {
            $score -= 30;
            $raisons[] = "Nombreux retards de paiement.";
        }

        // Étape 4 : Ratio d'endettement (simulé)
        $revenusMensuels = $this->getRevenusMensuels($clientId);
        if ($revenusMensuels > 0) {
            // Calculer la mensualité estimée du nouveau crédit
            $tauxMensuel = (0.05) / 12; // Taux d'intérêt standard pour la simulation
            $mensualite = ($montantCredit * $tauxMensuel) / (1 - pow(1 + $tauxMensuel, -$dureeMois));
            
            $ratioEndettement = ($mensualite / $revenusMensuels) * 100;

            if ($ratioEndettement > 35) {
                $score -= 20;
                $raisons[] = "Ratio d'endettement trop élevé (>35%).";
            } else {
                $score += 10;
                $raisons[] = "Ratio d'endettement acceptable.";
            }
        }

        // Définir la limite de score pour l'approbation.
        $scoreMinimum = 65;
        $estSolvable = ($score >= $scoreMinimum);

        if (!$estSolvable) {
             // Si non-solvable, donner une raison générique si d'autres raisons existent
             if (empty($raisons)) {
                 $raisons[] = "Score insuffisant.";
             }
        }

        return [
            'est_solvable' => $estSolvable,
            'score' => $score,
            'raison' => implode(", ", $raisons)
        ];
    }
    
    // --- Fonctions auxiliaires pour l'évaluation ---

    private function verifierClientExistant($clientId) {
        $sql = "SELECT COUNT(*) FROM clients WHERE id_client = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clientId]);
        return $stmt->fetchColumn() > 0;
    }

    private function getSoldeTotalClient($clientId) {
        // Simule la récupération des soldes de tous les comptes d'un client.
        $sql = "SELECT SUM(solde) FROM comptes WHERE id_client = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clientId]);
        return (float) $stmt->fetchColumn();
    }

    private function getHistoriquePaiement($clientId) {
        // Simule la récupération de l'historique de paiement.
        // Dans un vrai système, cela impliquerait d'interroger la table des crédits ou des transactions.
        // Pour l'exemple, nous allons retourner des données fictives.
        $historique = [
            1 => ['retards' => 1, 'paiements_effectues' => 10], // Client avec un bon historique
            2 => ['retards' => 3, 'paiements_effectues' => 5],  // Client à risque
            3 => ['retards' => 0, 'paiements_effectues' => 0]   // Nouveau client sans historique
        ];
        
        $donnees = isset($historique[$clientId]) ? $historique[$clientId] : ['retards' => 0, 'paiements_effectues' => 0];
        return $donnees;
    }
    
    private function getRevenusMensuels($clientId) {
        // Simule la récupération des revenus du client.
        // Dans un vrai système, ces données proviendraient des fiches de paie ou des déclarations.
        $revenus = [
            1 => 3000,
            2 => 1500,
            3 => 2500
        ];
        return isset($revenus[$clientId]) ? $revenus[$clientId] : 0;
    }
}