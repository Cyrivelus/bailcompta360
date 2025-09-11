<?php

require_once 'fonctions/database.php';
require_once 'fonctions/transactions/gestion_transactions.php';

class GestionPrelevements {

    private $db;
    private $gestionTransactions;

    public function __construct() {
        $this->db = getDatabaseInstance();
        $this->gestionTransactions = new GestionTransactions();
    }

    /**
     * Crée un nouveau mandat de prélèvement SEPA pour un client.
     * @param int $clientId L'ID du client.
     * @param string $numeroMandat L'identifiant unique du mandat.
     * @param string $creancier L'identité du créancier (celui qui prélève).
     * @param string $ibanBeneficiaire L'IBAN du compte bénéficiaire (du créancier).
     * @param string $dateSignature La date de signature du mandat.
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function creerMandat($clientId, $numeroMandat, $creancier, $ibanBeneficiaire, $dateSignature) {
        try {
            // Vérifier si le mandat existe déjà
            if ($this->verifierMandatExistant($numeroMandat)) {
                throw new Exception("Un mandat avec ce numéro existe déjà.");
            }

            $sql = "INSERT INTO mandats_prelevement (client_id, numero_mandat, creancier, iban_beneficiaire, date_signature, statut) VALUES (?, ?, ?, ?, ?, 'ACTIF')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$clientId, $numeroMandat, $creancier, $ibanBeneficiaire, $dateSignature]);
            
            return true;
        } catch (Exception $e) {
            error_log("Erreur lors de la création du mandat : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crée un ordre de prélèvement pour un mandat existant.
     * Cette fonction serait appelée par le créancier ou une routine planifiée.
     * @param int $mandatId L'ID du mandat.
     * @param float $montant Le montant à prélever.
     * @param string $motif Le motif du prélèvement.
     * @param string $datePrelevement La date d'exécution prévue.
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function creerOrdrePrelevement($mandatId, $montant, $motif, $datePrelevement) {
        $this->db->beginTransaction();
        try {
            $mandat = $this->getMandat($mandatId);
            if (!$mandat || $mandat['statut'] != 'ACTIF') {
                throw new Exception("Mandat introuvable ou inactif.");
            }

            // Étape 1 : Vérifier la solvabilité du compte client avant de créer l'ordre
            $soldeClient = $this->gestionTransactions->getSoldeCompte($mandat['id_compte_client']);
            if ($soldeClient < $montant) {
                // Créer l'ordre mais avec un statut "Fonds insuffisants" pour traitement ultérieur
                $statut = 'SOLDE_INSUFFISANT';
                $commentaire = 'Solde insuffisant pour le prélèvement.';
            } else {
                $statut = 'EN_ATTENTE';
                $commentaire = 'Prêt à être soumis au système de compensation.';
            }

            // Étape 2 : Créer l'ordre de prélèvement dans la table 'ordres_prelevement'
            $sql = "INSERT INTO ordres_prelevement (mandat_id, montant, date_prevue, motif, statut, commentaire) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$mandatId, $montant, $datePrelevement, $motif, $statut, $commentaire]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors de la création de l'ordre de prélèvement : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Exécute un prélèvement et enregistre la transaction.
     * Cette fonction serait déclenchée par une routine de fin de journée/batch.
     * @param int $ordreId L'ID de l'ordre de prélèvement.
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function executerPrelevement($ordreId) {
        $this->db->beginTransaction();
        try {
            $ordre = $this->getOrdre($ordreId);
            if (!$ordre || $ordre['statut'] != 'EN_ATTENTE') {
                throw new Exception("Ordre de prélèvement introuvable ou non prêt pour exécution.");
            }

            $mandat = $this->getMandat($ordre['mandat_id']);

            // Étape 1 : Débiter le compte client et créditer un compte de compensation
            $compteClientSource = $mandat['id_compte_client'];
            $compteCompensation = $this->getCompteInterneCompensation();
            
            $description = "Prélèvement n°" . $ordre['id'] . " via mandat " . $mandat['numero_mandat'];
            $this->gestionTransactions->enregistrerTransaction(
                $compteClientSource,
                $compteCompensation,
                $ordre['montant'],
                'PrelevementSEPA',
                $description
            );

            // Étape 2 : Mettre à jour le statut de l'ordre
            $sql = "UPDATE ordres_prelevement SET statut = 'EXECUTE', date_execution = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$ordreId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors de l'exécution du prélèvement : " . $e->getMessage());
            return false;
        }
    }
    
    // --- Fonctions auxiliaires privées ---

    private function verifierMandatExistant($numeroMandat) {
        $sql = "SELECT COUNT(*) FROM mandats_prelevement WHERE numero_mandat = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$numeroMandat]);
        return $stmt->fetchColumn() > 0;
    }

    private function getMandat($mandatId) {
        $sql = "SELECT m.*, c.id_compte AS id_compte_client FROM mandats_prelevement m JOIN comptes c ON m.client_id = c.id_client WHERE m.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$mandatId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getOrdre($ordreId) {
        $sql = "SELECT * FROM ordres_prelevement WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$ordreId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getCompteInterneCompensation() {
        // Compte de compensation pour les flux de prélèvements.
        return 906; // ID de compte fictif
    }
}