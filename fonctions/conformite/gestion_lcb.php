<?php

require_once 'fonctions/database.php';
require_once 'librairies/services/client_scoring_service.php'; // Service pour évaluer le risque client

class GestionLCB {

    private $db;
    private $clientScoringService;

    public function __construct() {
        $this->db = getDatabaseInstance();
        $this->clientScoringService = new ClientScoringService();
    }

    /**
     * Analyse une transaction pour la LCB-FT.
     * Cette fonction est le point d'entrée pour le monitoring.
     * @param int $transactionId L'ID de la transaction à analyser.
     * @return bool Vrai si l'analyse a réussi, faux sinon.
     */
    public function analyserTransaction($transactionId) {
        try {
            $transaction = $this->getDetailsTransaction($transactionId);
            if (!$transaction) {
                throw new Exception("Transaction non trouvée.");
            }

            $idCompteSource = $transaction['id_compte_source'];
            $idCompteDestination = $transaction['id_compte_destination'];
            $montant = $transaction['montant_total'];

            // Récupérer les informations des clients
            $clientSource = $this->getClientByCompte($idCompteSource);
            $clientDestination = $this->getClientByCompte($idCompteDestination);

            // Vérifier les règles de détection
            $reglesDeclenchees = [];

            // Règle 1: Montant élevé
            if ($this->estMontantEleve($montant)) {
                $reglesDeclenchees[] = "Montant de transaction élevé (" . $montant . " USD).";
            }

            // Règle 2: Activité anormale du client source
            if ($this->activiteAnormale($idCompteSource, $montant)) {
                $reglesDeclenchees[] = "Activité inhabituelle sur le compte source.";
            }

            // Règle 3: Transaction avec une juridiction à risque (simulée)
            if ($this->estJuridictionARisque($clientDestination['pays'])) {
                $reglesDeclenchees[] = "Transaction avec un pays à risque (" . $clientDestination['pays'] . ").";
            }

            // Règle 4: Client source ou destination à haut risque (ex: PEP)
            if ($this->clientScoringService->estClientARisque($clientSource['id_client'])) {
                $reglesDeclenchees[] = "Client source identifié comme à haut risque (PEP).";
            }
            if ($this->clientScoringService->estClientARisque($clientDestination['id_client'])) {
                $reglesDeclenchees[] = "Client destination identifié comme à haut risque (PEP).";
            }
            
            // Si une ou plusieurs règles sont déclenchées, générer une alerte
            if (!empty($reglesDeclenchees)) {
                $this->genererAlerteLCB($transactionId, $reglesDeclenchees);
            }

            return true;

        } catch (Exception $e) {
            error_log("Erreur lors de l'analyse LCB de la transaction " . $transactionId . " : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Génère une alerte LCB-FT et l'enregistre dans une table dédiée.
     * @param int $transactionId L'ID de la transaction.
     * @param array $regles Les règles qui ont déclenché l'alerte.
     * @return bool
     */
    public function genererAlerteLCB($transactionId, $regles) {
        $this->db->beginTransaction();
        try {
            $sql = "INSERT INTO alertes_lcb (transaction_id, date_alerte, raisons, statut) VALUES (?, NOW(), ?, 'NOUVELLE')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$transactionId, json_encode($regles)]);
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors de la génération de l'alerte LCB : " . $e->getMessage());
            return false;
        }
    }
    
    // --- Fonctions auxiliaires privées (règles de détection) ---

    private function getDetailsTransaction($transactionId) {
        // Simuler la récupération des détails d'une transaction à partir des tables `ecritures` et `lignes_ecritures`
        $sql = "SELECT e.Montant_Total, le_source.ID_Compte as id_compte_source, le_dest.ID_Compte as id_compte_destination 
                FROM ecritures e 
                JOIN lignes_ecritures le_source ON e.ID_Ecriture = le_source.ID_Ecriture AND le_source.Sens = 'D'
                JOIN lignes_ecritures le_dest ON e.ID_Ecriture = le_dest.ID_Ecriture AND le_dest.Sens = 'C'
                WHERE e.ID_Ecriture = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$transactionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getClientByCompte($idCompte) {
        // Simuler la récupération du client à partir de l'ID du compte
        $sql = "SELECT c.id_client, c.pays FROM comptes cc JOIN clients c ON cc.id_client = c.id_client WHERE cc.id_compte = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idCompte]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function estMontantEleve($montant, $seuil = 10000) {
        return $montant > $seuil;
    }

    private function activiteAnormale($idCompte, $montant) {
        // Simuler une règle d'activité anormale
        // Par exemple, si le montant de la transaction est > 50% du solde moyen sur les 30 derniers jours
        $soldeMoyen = $this->getSoldeMoyenRecent($idCompte);
        if ($soldeMoyen > 0 && $montant > ($soldeMoyen * 0.5)) {
            return true;
        }
        return false;
    }
    
    private function getSoldeMoyenRecent($idCompte) {
        // Simule le calcul du solde moyen sur les 30 derniers jours.
        // Cela nécessiterait des données historiques de solde. Pour cet exemple, on retourne une valeur fictive.
        return 5000;
    }

    private function estJuridictionARisque($pays) {
        // Simuler une liste de pays à risque. Dans la réalité, cette liste serait mise à jour par l'analyste LCB.
        $paysARisque = ['IRAN', 'SYRIE', 'COREE_DU_NORD'];
        return in_array(strtoupper($pays), $paysARisque);
    }
}