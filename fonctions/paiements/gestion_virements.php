<?php

require_once 'fonctions/database.php';
require_once 'fonctions/transactions/gestion_transactions.php';
require_once 'fonctions/clients/gestion_clients.php'; // Pour vérifier l'IBAN du bénéficiaire

class GestionVirements {

    private $db;
    private $gestionTransactions;

    public function __construct() {
        $this->db = getDatabaseInstance();
        $this->gestionTransactions = new GestionTransactions();
    }

    /**
     * Effectue un virement entre deux comptes internes de la banque.
     * @param int $idCompteSource L'ID du compte à débiter.
     * @param int $idCompteDestination L'ID du compte à créditer.
     * @param float $montant Le montant du virement.
     * @param string $motif Le motif du virement.
     * @return bool Vrai si le virement a réussi, faux en cas d'échec.
     */
    public function effectuerVirementInterne($idCompteSource, $idCompteDestination, $montant, $motif) {
        $this->db->beginTransaction();
        try {
            // Étape 1 : Vérifier la solvabilité du compte source
            $soldeSource = $this->gestionTransactions->getSoldeCompte($idCompteSource);
            if ($soldeSource < $montant) {
                throw new Exception("Fonds insuffisants sur le compte source.");
            }
            
            // Étape 2 : Vérifier que le compte de destination existe
            if (!$this->verifierCompteExistant($idCompteDestination)) {
                throw new Exception("Le compte de destination est introuvable.");
            }
            
            // Étape 3 : Enregistrer la transaction via le gestionnaire de transactions
            $description = "Virement interne - Motif: " . $motif;
            $this->gestionTransactions->enregistrerTransaction(
                $idCompteSource,
                $idCompteDestination,
                $montant,
                'VirementInterne',
                $description
            );

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors du virement interne : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Effectue un virement sortant vers une banque externe.
     * @param int $idCompteSource L'ID du compte à débiter.
     * @param float $montant Le montant du virement.
     * @param string $motif Le motif du virement.
     * @param string $ibanBeneficiaire L'IBAN du compte bénéficiaire.
     * @return bool Vrai si le virement a réussi, faux en cas d'échec.
     */
    public function effectuerVirementExterne($idCompteSource, $montant, $motif, $ibanBeneficiaire) {
        $this->db->beginTransaction();
        try {
            // Étape 1 : Vérifier la solvabilité du compte source
            $soldeSource = $this->gestionTransactions->getSoldeCompte($idCompteSource);
            if ($soldeSource < $montant) {
                throw new Exception("Fonds insuffisants sur le compte source.");
            }

            // Étape 2 : Débiter le compte source et créditer un compte de compensation de la banque
            $compteCompensation = $this->getCompteInterneCompensation();
            
            $description = "Virement externe vers IBAN: " . $ibanBeneficiaire . " - Motif: " . $motif;
            $this->gestionTransactions->enregistrerTransaction(
                $idCompteSource,
                $compteCompensation,
                $montant,
                'VirementExterne',
                $description
            );
            
            // Étape 3 (simulée) : Enregistrer le virement pour le traitement par le système de compensation interbancaire
            $this->creerOrdreVirement($idCompteSource, $montant, $ibanBeneficiaire);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors du virement externe : " . $e->getMessage());
            return false;
        }
    }
    
    // --- Fonctions auxiliaires privées ---

    private function verifierCompteExistant($idCompte) {
        $sql = "SELECT COUNT(*) FROM comptes WHERE id_compte = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idCompte]);
        return $stmt->fetchColumn() > 0;
    }

    private function getCompteInterneCompensation() {
        // Compte interne de la banque pour les virements sortants.
        // Ce compte sert de passerelle vers le système interbancaire.
        return 905; // ID de compte fictif
    }

    private function creerOrdreVirement($idCompteSource, $montant, $ibanBeneficiaire) {
        // Simule la création d'un ordre de virement dans une table dédiée,
        // qui sera ensuite traitée par un service de compensation (ex: SEPA).
        // Exemple de table : 'ordres_virements'
        $sql = "INSERT INTO ordres_virements (id_compte_source, montant, iban_beneficiaire, statut) VALUES (?, ?, ?, 'EN_ATTENTE')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idCompteSource, $montant, $ibanBeneficiaire]);
        
        error_log("Ordre de virement créé pour IBAN: " . $ibanBeneficiaire);
    }
}