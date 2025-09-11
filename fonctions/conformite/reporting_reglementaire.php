<?php

require_once 'fonctions/database.php';

class ReportingReglementaire {

    private $db;

    public function __construct() {
        $this->db = getDatabaseInstance();
    }

    /**
     * Génère un rapport de capital et de risque.
     * C'est une version simplifiée du ratio de solvabilité de Bâle III.
     * @return array Les données du rapport.
     */
    public function genererRapportSolvabilite() {
        // Étape 1 : Calculer le Capital Éligible (Tier 1 + Tier 2)
        // Pour cet exemple, on simplifie :
        $capitalTier1 = $this->calculerCapitalTier1();
        $capitalTier2 = $this->calculerCapitalTier2();
        $capitalTotal = $capitalTier1 + $capitalTier2;

        // Étape 2 : Calculer les Actifs Pondérés en fonction des Risques (RWA)
        $rwa = $this->calculerRisqueGlobal();

        // Étape 3 : Calculer le Ratio de Solvabilité
        $ratioSolvabilite = ($rwa > 0) ? ($capitalTotal / $rwa) * 100 : 0;
        
        return [
            'date_rapport' => date('Y-m-d'),
            'capital_tier1' => round($capitalTier1, 2),
            'capital_tier2' => round($capitalTier2, 2),
            'capital_total' => round($capitalTotal, 2),
            'rwa' => round($rwa, 2),
            'ratio_solvabilite' => round($ratioSolvabilite, 2) . "%",
            'commentaire' => 'Ce rapport est une simulation et ne représente pas les normes de Bâle III complètes.'
        ];
    }
    
    /**
     * Génère un rapport de liquidité.
     * @return array Les données du rapport.
     */
    public function genererRapportLiquidite() {
        // Pour cet exemple, on simule le calcul du Liquidity Coverage Ratio (LCR)
        
        // Étape 1 : Calculer les Actifs Liquides de Haute Qualité (HQLA)
        $hqla = $this->calculerHQLA();

        // Étape 2 : Calculer les Sorties Nettes de Trésorerie (Net Cash Outflows)
        $sortiesNetttes = $this->calculerSortiesNetttes();
        
        // Étape 3 : Calculer le Ratio de Liquidité (LCR)
        $lcr = ($sortiesNetttes > 0) ? ($hqla / $sortiesNetttes) * 100 : 0;

        return [
            'date_rapport' => date('Y-m-d'),
            'hqla' => round($hqla, 2),
            'sorties_nettes_tresorerie' => round($sortiesNetttes, 2),
            'ratio_liquidite' => round($lcr, 2) . "%",
            'commentaire' => 'Ce rapport est une simulation du LCR de Bâle III.'
        ];
    }

    /**
     * Prépare une déclaration de soupçon pour les autorités de régulation.
     * @param int $alerteId L'ID de l'alerte LCB-FT.
     * @return array Les données à soumettre.
     */
    public function preparerDeclarationSoupcon($alerteId) {
        $alerte = $this->getAlerte($alerteId);
        
        if (!$alerte || $alerte['statut'] != 'ESCALADE') {
            return ["error" => "Alerte invalide ou non prête pour une déclaration."];
        }

        $transaction = $this->getDetailsTransaction($alerte['transaction_id']);
        $clientSource = $this->getClientByCompte($transaction['id_compte_source']);
        $clientDestination = $this->getClientByCompte($transaction['id_compte_destination']);

        return [
            'type_rapport' => 'Déclaration de Soupçon',
            'date_declaration' => date('Y-m-d H:i:s'),
            'alerte_id' => $alerteId,
            'statut_alerte' => $alerte['statut'],
            'raisons_declenchement' => json_decode($alerte['raisons'], true),
            'details_transaction' => [
                'montant' => $transaction['montant_total'],
                'date' => $transaction['Date_Saisie']
            ],
            'informations_client_source' => [
                'id_client' => $clientSource['id_client'],
                'nom' => $clientSource['nom_client'],
                'compte' => $clientSource['numero_compte']
            ],
            'informations_client_destination' => [
                'id_client' => $clientDestination['id_client'],
                'nom' => $clientDestination['nom_client'],
                'compte' => $clientDestination['numero_compte']
            ],
            'commentaire_analyste' => $alerte['commentaire']
        ];
    }
    
    // --- Fonctions auxiliaires privées (simulations) ---

    private function calculerCapitalTier1() {
        // Simule le calcul du capital de base. Dans la réalité, on agrège des données
        // de la comptabilité générale (passif).
        $sql = "SELECT SUM(solde) FROM comptes_compta WHERE Type_Compte = 'CAPITAL_PROPRE'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    private function calculerCapitalTier2() {
        // Simule le calcul du capital complémentaire (dettes subordonnées, etc.).
        return 100000; // Valeur fictive
    }

    private function calculerRisqueGlobal() {
        // Simule le calcul des actifs pondérés en fonction du risque.
        // Cela impliquerait de classer chaque actif (prêts, investissements)
        // et de les multiplier par un coefficient de risque.
        $sql = "SELECT SUM(montant) FROM credits WHERE statut = 'ACTIF'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $montantPrets = $stmt->fetchColumn();
        return $montantPrets * 0.8; // Simplification: 80% du risque sur les prêts
    }
    
    private function calculerHQLA() {
        // Simule le calcul des Actifs Liquides de Haute Qualité (trésorerie, bons du trésor).
        return 200000; // Valeur fictive
    }
    
    private function calculerSortiesNetttes() {
        // Simule les sorties nettes de trésorerie sur une période de 30 jours.
        // On prend les soldes des comptes des clients pour le calcul.
        $sql = "SELECT SUM(solde) FROM comptes WHERE statut = 'actif'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $totalSoldes = $stmt->fetchColumn();
        return $totalSoldes * 0.15; // Simplification: 15% des dépôts sont considérés comme des sorties
    }

    private function getAlerte($alerteId) {
        $sql = "SELECT * FROM alertes_lcb WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$alerteId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getDetailsTransaction($transactionId) {
        $sql = "SELECT * FROM ecritures WHERE ID_Ecriture = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$transactionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getClientByCompte($idCompte) {
        $sql = "SELECT c.id_client, c.nom_client, cc.numero_compte 
                FROM comptes cc 
                JOIN clients c ON cc.id_client = c.id_client 
                WHERE cc.id_compte = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idCompte]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}