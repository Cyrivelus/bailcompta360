<?php

require_once 'fonctions/database.php';
require_once 'fonctions/transactions/gestion_transactions.php';

class GestionDepotsTerme {

    private $db;
    private $gestionTransactions;

    public function __construct() {
        $this->db = getDatabaseInstance();
        $this->gestionTransactions = new GestionTransactions();
    }

    /**
     * Ouvre un nouveau dépôt à terme.
     * @param int $clientId L'ID du client.
     * @param float $montant Le montant du dépôt.
     * @param int $dureeMois La durée en mois.
     * @param float $tauxInteret Le taux d'intérêt annuel.
     * @param string $dateOuverture La date d'ouverture du dépôt (format 'YYYY-MM-DD').
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function ouvrirDepotTerme($clientId, $montant, $dureeMois, $tauxInteret, $dateOuverture) {
        $this->db->beginTransaction();
        try {
            // Vérifier que le client existe.
            if (!$this->verifierClientExistant($clientId)) {
                throw new Exception("Client introuvable.");
            }

            // Enregistrer le nouveau dépôt à terme.
            $sql = "INSERT INTO depots_terme (client_id, montant_principal, duree_mois, taux_interet, date_ouverture, statut) VALUES (?, ?, ?, ?, ?, 'ACTIF')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$clientId, $montant, $dureeMois, $tauxInteret, $dateOuverture]);
            $depotId = $this->db->lastInsertId();

            // Enregistrer la transaction de débit sur le compte courant du client.
            // ID du compte source et du compte de destination à définir.
            $compteSourceId = $this->getCompteCourantClient($clientId);
            $description = "Ouverture Dépôt à Terme n°" . $depotId;

            $this->gestionTransactions->enregistrerTransaction($compteSourceId, 
                $this->getCompteInterneDepotsTerme(), 
                $montant, 
                'DepotTermeOuverture', 
                $description
            );

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors de l'ouverture du dépôt à terme : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calcule le montant total à la clôture du dépôt à terme.
     * @param int $depotId L'ID du dépôt.
     * @return float Le montant total (capital + intérêts).
     */
    public function calculerMontantCloture($depotId) {
        $sql = "SELECT montant_principal, duree_mois, taux_interet FROM depots_terme WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$depotId]);
        $depot = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$depot) {
            return 0;
        }

        $montantPrincipal = $depot['montant_principal'];
        $dureeMois = $depot['duree_mois'];
        $tauxAnnuel = $depot['taux_interet'];

        // Calcul des intérêts : (Montant Principal * Taux Annuel * Durée en mois) / 12
        $interets = ($montantPrincipal * $tauxAnnuel * $dureeMois) / 12;

        return $montantPrincipal + $interets;
    }

    /**
     * Clôture un dépôt à terme à l'échéance.
     * @param int $depotId L'ID du dépôt.
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function cloturerDepotTerme($depotId) {
        $this->db->beginTransaction();
        try {
            $depot = $this->getDepotTerme($depotId);
            if (!$depot || $depot['statut'] != 'ACTIF') {
                throw new Exception("Dépôt introuvable ou déjà clôturé.");
            }

            $montantCloture = $this->calculerMontantCloture($depotId);

            // Mettre à jour le statut du dépôt à "CLOS".
            $sql = "UPDATE depots_terme SET statut = 'CLOS', date_cloture = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$depotId]);

            // Enregistrer la transaction de crédit sur le compte courant du client.
            $compteDestinationId = $this->getCompteCourantClient($depot['client_id']);
            $description = "Clôture Dépôt à Terme n°" . $depotId . " (Capital + Intérêts)";

            $this->gestionTransactions->enregistrerTransaction(
                $this->getCompteInterneDepotsTerme(), 
                $compteDestinationId, 
                $montantCloture, 
                'DepotTermeCloture', 
                $description
            );

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors de la clôture du dépôt à terme : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les informations d'un dépôt à terme.
     * @param int $depotId L'ID du dépôt.
     * @return array Les données du dépôt.
     */
    public function getDepotTerme($depotId) {
        $sql = "SELECT * FROM depots_terme WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$depotId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // --- Fonctions auxiliaires privées ---

    private function verifierClientExistant($clientId) {
        $sql = "SELECT COUNT(*) FROM clients WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clientId]);
        return $stmt->fetchColumn() > 0;
    }

    private function getCompteCourantClient($clientId) {
        // Logique pour trouver le compte courant principal du client.
        // Ceci pourrait être une requête vers une table 'comptes_bancaires'.
        // Par exemple: SELECT id FROM comptes_bancaires WHERE client_id = ? AND type_compte = 'Courant';
        return 101; // ID de compte fictif pour l'exemple
    }
    
    private function getCompteInterneDepotsTerme() {
        // Compte interne de la banque pour les opérations sur les dépôts à terme.
        // Ce compte est utilisé pour la contrepartie comptable.
        return 901; // ID de compte fictif
    }
}