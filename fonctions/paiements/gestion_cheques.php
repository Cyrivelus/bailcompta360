<?php

require_once 'fonctions/database.php';
require_once 'fonctions/transactions/gestion_transactions.php';

class GestionCheques {

    private $db;
    private $gestionTransactions;

    public function __construct() {
        $this->db = getDatabaseInstance();
        $this->gestionTransactions = new GestionTransactions();
    }

    /**
     * Enregistre l'émission d'un chèque par un client.
     * Cette action ne débite pas encore le compte.
     * @param int $idCompteClient L'ID du compte client émetteur.
     * @param string $numeroCheque Le numéro unique du chèque.
     * @param float $montant Le montant du chèque.
     * @param string $beneficiaire Le nom du bénéficiaire.
     * @param string $dateEmission La date d'émission (format 'YYYY-MM-DD').
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function emettreCheque($idCompteClient, $numeroCheque, $montant, $beneficiaire, $dateEmission) {
        try {
            // Vérifier si le chèque a déjà été enregistré
            if ($this->getCheque($numeroCheque)) {
                throw new Exception("Ce chèque a déjà été émis.");
            }

            $sql = "INSERT INTO cheques (id_compte_client, numero_cheque, montant, beneficiaire, date_emission, statut) VALUES (?, ?, ?, ?, ?, 'EN_ATTENTE_ENCAISSEMENT')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$idCompteClient, $numeroCheque, $montant, $beneficiaire, $dateEmission]);

            return true;
        } catch (Exception $e) {
            error_log("Erreur lors de l'émission du chèque : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Encaisse un chèque et débite le compte de l'émetteur.
     * @param string $numeroCheque Le numéro du chèque à encaisser.
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function encaisserCheque($numeroCheque) {
        $this->db->beginTransaction();
        try {
            $cheque = $this->getCheque($numeroCheque);

            if (!$cheque || $cheque['statut'] != 'EN_ATTENTE_ENCAISSEMENT') {
                throw new Exception("Chèque invalide ou déjà traité.");
            }

            // Étape 1 : Vérifier la solvabilité du compte émetteur
            $soldeClient = $this->gestionTransactions->getSoldeCompte($cheque['id_compte_client']);
            if ($soldeClient < $cheque['montant']) {
                $this->mettreStatutCheque($numeroCheque, 'SANS_PROVISION');
                throw new Exception("Solde insuffisant pour encaisser le chèque.");
            }

            // Étape 2 : Débiter le compte client et créditer un compte de compensation
            $compteClient = $cheque['id_compte_client'];
            $compteCompensation = $this->getCompteInterneCheques();

            $description = "Encaissement du chèque n°" . $numeroCheque . " - Bénéficiaire: " . $cheque['beneficiaire'];
            $this->gestionTransactions->enregistrerTransaction(
                $compteClient,
                $compteCompensation,
                $cheque['montant'],
                'EncaissementCheque',
                $description
            );

            // Étape 3 : Mettre à jour le statut du chèque
            $this->mettreStatutCheque($numeroCheque, 'ENCAISSE', $this->db);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors de l'encaissement du chèque : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Met un chèque en opposition (stoppe l'encaissement).
     * @param string $numeroCheque Le numéro du chèque.
     * @param string $motifMotif de l'opposition.
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function mettreOpposition($numeroCheque, $motif) {
        try {
            $cheque = $this->getCheque($numeroCheque);

            if (!$cheque || $cheque['statut'] != 'EN_ATTENTE_ENCAISSEMENT') {
                throw new Exception("Impossible de mettre en opposition un chèque déjà encaissé.");
            }
            $this->mettreStatutCheque($numeroCheque, 'OPPOSE', $this->db, $motif);
            return true;
        } catch (Exception $e) {
            error_log("Erreur lors de la mise en opposition : " . $e->getMessage());
            return false;
        }
    }
    
    // --- Fonctions auxiliaires privées ---

    private function getCheque($numeroCheque) {
        $sql = "SELECT * FROM cheques WHERE numero_cheque = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$numeroCheque]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function mettreStatutCheque($numeroCheque, $statut, $db = null, $raison = null) {
        $db = $db ?? $this->db;
        $sql = "UPDATE cheques SET statut = ?, date_mise_a_jour = NOW()";
        $params = [$statut];
        if ($raison) {
            $sql .= ", commentaire = ?";
            $params[] = $raison;
        }
        $sql .= " WHERE numero_cheque = ?";
        $params[] = $numeroCheque;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }
    
    private function getCompteInterneCheques() {
        // Compte interne de la banque pour la gestion des chèques.
        return 908; // ID de compte fictif
    }
}