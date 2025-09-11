<?php

require_once 'fonctions/database.php';
require_once 'fonctions/transactions/gestion_transactions.php';

class GestionEpargne {

    private $db;
    private $gestionTransactions;

    public function __construct() {
        $this->db = getDatabaseInstance();
        $this->gestionTransactions = new GestionTransactions();
    }

    /**
     * Ouvre un nouveau compte d'épargne pour un client.
     * @param int $clientId L'ID du client.
     * @param string $numeroCompte Le numéro du nouveau compte.
     * @param float $montantInitial Le montant du dépôt initial.
     * @param float $tauxInteret Le taux d'intérêt annuel.
     * @param float $plafond Le plafond de dépôt du compte.
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function ouvrirCompteEpargne($clientId, $numeroCompte, $montantInitial, $tauxInteret, $plafond) {
        $this->db->beginTransaction();
        try {
            // 1. Vérifier si le client et le numéro de compte sont valides.
            if (!$this->verifierClientExistant($clientId)) {
                throw new Exception("Client introuvable.");
            }
            if ($this->verifierCompteExistant($numeroCompte)) {
                throw new Exception("Ce numéro de compte existe déjà.");
            }

            // 2. Créer le compte d'épargne dans la table 'comptes_epargne'.
            $sql = "INSERT INTO comptes_epargne (client_id, numero_compte, solde, taux_interet, plafond, date_ouverture) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$clientId, $numeroCompte, $montantInitial, $tauxInteret, $plafond]);
            $compteId = $this->db->lastInsertId();

            // 3. Enregistrer la transaction de dépôt initial.
            $description = "Ouverture compte épargne " . $numeroCompte . " - Dépôt initial";
            $this->gestionTransactions->enregistrerTransaction(
                $this->getCompteInterneEpargne(), 
                $compteId, // Compte de destination (le nouveau compte épargne)
                $montantInitial, 
                'DepotEpargne', 
                $description
            );

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors de l'ouverture du compte épargne : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Effectue un dépôt sur un compte d'épargne.
     * @param int $compteId L'ID du compte épargne.
     * @param float $montant Le montant à déposer.
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function deposerMontant($compteId, $montant) {
        $this->db->beginTransaction();
        try {
            $compte = $this->getCompteEpargne($compteId);
            if (!$compte) {
                throw new Exception("Compte introuvable.");
            }
            
            $nouveauSolde = $compte['solde'] + $montant;
            if ($nouveauSolde > $compte['plafond']) {
                throw new Exception("Le dépôt dépasse le plafond du compte.");
            }

            // Mettre à jour le solde du compte.
            $sql = "UPDATE comptes_epargne SET solde = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$nouveauSolde, $compteId]);

            // Enregistrer la transaction.
            $description = "Dépôt sur compte épargne n°" . $compte['numero_compte'];
            $this->gestionTransactions->enregistrerTransaction(
                $this->getCompteInterneEpargne(), 
                $compteId, 
                $montant, 
                'DepotEpargne', 
                $description
            );

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors du dépôt : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Effectue un retrait sur un compte d'épargne.
     * @param int $compteId L'ID du compte épargne.
     * @param float $montant Le montant à retirer.
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function retirerMontant($compteId, $montant) {
        $this->db->beginTransaction();
        try {
            $compte = $this->getCompteEpargne($compteId);
            if (!$compte) {
                throw new Exception("Compte introuvable.");
            }
            
            $nouveauSolde = $compte['solde'] - $montant;
            if ($nouveauSolde < 0) {
                throw new Exception("Fonds insuffisants sur le compte.");
            }

            // Mettre à jour le solde du compte.
            $sql = "UPDATE comptes_epargne SET solde = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$nouveauSolde, $compteId]);

            // Enregistrer la transaction.
            $description = "Retrait sur compte épargne n°" . $compte['numero_compte'];
            $this->gestionTransactions->enregistrerTransaction(
                $compteId, 
                $this->getCompteInterneEpargne(), 
                $montant, 
                'RetraitEpargne', 
                $description
            );

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors du retrait : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calcule et crédite les intérêts sur un compte d'épargne.
     * Cette méthode peut être appelée lors d'une routine de fin de mois/année.
     * @param int $compteId L'ID du compte épargne.
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function capitaliserInterets($compteId) {
        $this->db->beginTransaction();
        try {
            $compte = $this->getCompteEpargne($compteId);
            if (!$compte) {
                throw new Exception("Compte introuvable.");
            }

            // Calcul des intérêts annuels : (solde * taux)
            $interets = $compte['solde'] * $compte['taux_interet'];
            
            if ($interets > 0) {
                $nouveauSolde = $compte['solde'] + $interets;
                
                // Mettre à jour le solde.
                $sql = "UPDATE comptes_epargne SET solde = ? WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$nouveauSolde, $compteId]);

                // Enregistrer la transaction.
                $description = "Capitalisation des intérêts sur compte épargne n°" . $compte['numero_compte'];
                $this->gestionTransactions->enregistrerTransaction(
                    $this->getCompteInterneEpargne(), 
                    $compteId, 
                    $interets, 
                    'CapitalisationInterets', 
                    $description
                );
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors de la capitalisation des intérêts : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère les informations d'un compte épargne.
     * @param int $compteId L'ID du compte.
     * @return array Les données du compte ou faux si non trouvé.
     */
    public function getCompteEpargne($compteId) {
        $sql = "SELECT * FROM comptes_epargne WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$compteId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // --- Fonctions auxiliaires privées ---

    private function verifierClientExistant($clientId) {
        $sql = "SELECT COUNT(*) FROM clients WHERE id_client = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clientId]);
        return $stmt->fetchColumn() > 0;
    }

    private function verifierCompteExistant($numeroCompte) {
        $sql = "SELECT COUNT(*) FROM comptes_epargne WHERE numero_compte = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$numeroCompte]);
        return $stmt->fetchColumn() > 0;
    }

    private function getCompteInterneEpargne() {
        // Compte interne de la banque pour les opérations sur les comptes épargne.
        return 902; // ID de compte fictif
    }
}