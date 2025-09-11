<?php

require_once 'fonctions/database.php';
require_once 'fonctions/transactions/gestion_transactions.php';
require_once 'librairies/services/credit_scoring_service.php';

class GestionCredits {

    private $db;
    private $gestionTransactions;
    private $creditScoringService;

    public function __construct() {
        $this->db = getDatabaseInstance();
        $this->gestionTransactions = new GestionTransactions();
        $this->creditScoringService = new CreditScoringService();
    }

    /**
     * Simule un crédit et génère un tableau d'amortissement.
     * @param float $montant Le capital emprunté.
     * @param float $tauxAnnuel Le taux d'intérêt annuel.
     * @param int $dureeMois La durée du prêt en mois.
     * @return array Le tableau d'amortissement.
     */
    public function simulerCredit($montant, $tauxAnnuel, $dureeMois) {
        if ($montant <= 0 || $tauxAnnuel < 0 || $dureeMois <= 0) {
            return ["error" => "Les paramètres de simulation sont invalides."];
        }

        $tauxMensuel = ($tauxAnnuel / 100) / 12;
        
        // Calcul de la mensualité par la formule d'amortissement
        if ($tauxMensuel == 0) {
            $mensualite = $montant / $dureeMois;
        } else {
            $mensualite = ($montant * $tauxMensuel) / (1 - pow(1 + $tauxMensuel, -$dureeMois));
        }

        $tableauAmortissement = [];
        $capitalRestant = $montant;

        for ($i = 1; $i <= $dureeMois; $i++) {
            $interetsMois = $capitalRestant * $tauxMensuel;
            $capitalRembourse = $mensualite - $interetsMois;
            $capitalRestant -= $capitalRembourse;

            $tableauAmortissement[] = [
                'mois' => $i,
                'mensualite' => round($mensualite, 2),
                'capital_rembourse' => round($capitalRembourse, 2),
                'interets' => round($interetsMois, 2),
                'capital_restant' => round($capitalRestant, 2),
            ];
        }

        return $tableauAmortissement;
    }

    /**
     * Octroie un crédit à un client après vérification.
     * @param int $clientId L'ID du client.
     * @param float $montant Le capital du prêt.
     * @param float $tauxAnnuel Le taux d'intérêt annuel.
     * @param int $dureeMois La durée du prêt en mois.
     * @param string $dateOctroi La date de l'octroi.
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function octroyerCredit($clientId, $montant, $tauxAnnuel, $dureeMois, $dateOctroi) {
        $this->db->beginTransaction();
        try {
            // Étape 1 : Évaluation de la solvabilité du client
            $solvabilite = $this->creditScoringService->evaluerSolvabilite($clientId, $montant, $dureeMois);
            if (!$solvabilite['est_solvable']) {
                throw new Exception("Le client n'est pas solvable. Raison : " . $solvabilite['raison']);
            }

            // Étape 2 : Enregistrer le prêt dans la table 'credits'
            $sql = "INSERT INTO credits (client_id, montant, taux_annuel, duree_mois, date_octroi, statut) VALUES (?, ?, ?, ?, ?, 'ACTIF')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$clientId, $montant, $tauxAnnuel, $dureeMois, $dateOctroi]);
            $creditId = $this->db->lastInsertId();

            // Étape 3 : Transférer les fonds du compte de la banque au compte courant du client
            $compteBanqueCredit = $this->getCompteInterneCredits();
            $compteClientDestination = $this->getCompteCourantClient($clientId);
            
            $description = "Octroi de crédit n°" . $creditId;
            $this->gestionTransactions->enregistrerTransaction(
                $compteBanqueCredit,
                $compteClientDestination,
                $montant,
                'OctroiCredit',
                $description
            );

            // Étape 4 : Générer et enregistrer le tableau d'amortissement
            $tableauAmortissement = $this->simulerCredit($montant, $tauxAnnuel, $dureeMois);
            $this->enregistrerTableauAmortissement($creditId, $tableauAmortissement);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors de l'octroi du crédit : " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enregistre un paiement de crédit.
     * @param int $creditId L'ID du crédit.
     * @param float $montant Le montant payé.
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function enregistrerPaiement($creditId, $montant) {
        $this->db->beginTransaction();
        try {
            $credit = $this->getCredit($creditId);
            if (!$credit || $credit['statut'] != 'ACTIF') {
                throw new Exception("Crédit introuvable ou inactif.");
            }

            // Étape 1 : Enregistrer la transaction de remboursement
            $compteClientSource = $this->getCompteCourantClient($credit['client_id']);
            $compteBanqueCredit = $this->getCompteInterneCredits();

            $description = "Remboursement crédit n°" . $creditId;
            $this->gestionTransactions->enregistrerTransaction(
                $compteClientSource,
                $compteBanqueCredit,
                $montant,
                'RemboursementCredit',
                $description
            );

            // Étape 2 : Mettre à jour le solde restant dû du crédit
            $nouveauSoldeDu = $credit['solde_du'] - $montant;
            $statut = ($nouveauSoldeDu <= 0) ? 'CLOS' : 'ACTIF';
            
            $sql = "UPDATE credits SET solde_du = ?, statut = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$nouveauSoldeDu, $statut, $creditId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors du paiement du crédit : " . $e->getMessage());
            return false;
        }
    }

    // --- Fonctions auxiliaires privées ---

    private function getCredit($creditId) {
        $sql = "SELECT * FROM credits WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$creditId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getCompteCourantClient($clientId) {
        // Logique pour trouver le compte courant principal du client.
        return 101; // ID de compte fictif
    }

    private function getCompteInterneCredits() {
        // Compte interne de la banque pour les opérations de crédits.
        return 903; // ID de compte fictif
    }

    private function enregistrerTableauAmortissement($creditId, $tableau) {
        $sql = "INSERT INTO amortissements_credit (credit_id, mois, mensualite, capital_rembourse, interets, capital_restant) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        foreach ($tableau as $ligne) {
            $stmt->execute([
                $creditId,
                $ligne['mois'],
                $ligne['mensualite'],
                $ligne['capital_rembourse'],
                $ligne['interets'],
                $ligne['capital_restant']
            ]);
        }
    }
}