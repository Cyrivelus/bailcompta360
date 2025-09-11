<?php

require_once 'fonctions/database.php';
require_once 'fonctions/transactions/gestion_transactions.php';

class GestionCartes {

    private $db;
    private $gestionTransactions;

    public function __construct() {
        $this->db = getDatabaseInstance();
        $this->gestionTransactions = new GestionTransactions();
    }

    /**
     * Émet une nouvelle carte bancaire pour un client et l'associe à un compte.
     * @param int $clientId L'ID du client.
     * @param int $idCompteClient L'ID du compte client associé à la carte.
     * @param string $numeroCarte Le numéro unique de la carte.
     * @param string $dateExpiration La date d'expiration (format 'YYYY-MM-DD').
     * @param string $typeCarte Le type de carte (ex: 'DEBIT', 'CREDIT').
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function emettreCarte($clientId, $idCompteClient, $numeroCarte, $dateExpiration, $typeCarte) {
        try {
            // Vérifier que le numéro de carte n'existe pas déjà
            if ($this->verifierNumeroCarteExistant($numeroCarte)) {
                throw new Exception("Une carte avec ce numéro existe déjà.");
            }

            $sql = "INSERT INTO cartes (client_id, id_compte_client, numero_carte, date_expiration, type_carte, statut) VALUES (?, ?, ?, ?, ?, 'EN_ATTENTE_ACTIVATION')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$clientId, $idCompteClient, $numeroCarte, $dateExpiration, $typeCarte]);
            
            return true;
        } catch (Exception $e) {
            error_log("Erreur lors de l'émission de la carte : " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Active une carte émise après que le client a reçu et validé le code PIN.
     * @param string $numeroCarte Le numéro de la carte à activer.
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function activerCarte($numeroCarte) {
        try {
            $sql = "UPDATE cartes SET statut = 'ACTIVE' WHERE numero_carte = ? AND statut = 'EN_ATTENTE_ACTIVATION'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$numeroCarte]);

            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Erreur lors de l'activation de la carte : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Traite une transaction de débit par carte.
     * Cette fonction serait appelée par le service de traitement des paiements.
     * @param string $numeroCarte Le numéro de la carte.
     * @param float $montant Le montant de la transaction.
     * @param string $marchand Nom du marchand.
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function traiterTransactionDebit($numeroCarte, $montant, $marchand) {
        $this->db->beginTransaction();
        try {
            $carte = $this->getCarte($numeroCarte);
            if (!$carte || $carte['statut'] != 'ACTIVE') {
                throw new Exception("Carte invalide ou inactive.");
            }
            
            // Étape 1 : Vérifier la solvabilité du compte client
            $soldeClient = $this->gestionTransactions->getSoldeCompte($carte['id_compte_client']);
            if ($soldeClient < $montant) {
                // Le virement est rejeté et on le signale.
                $this->bloquerCarte($numeroCarte, "Solde insuffisant.");
                throw new Exception("Fonds insuffisants sur le compte client.");
            }
            
            // Étape 2 : Débiter le compte client et créditer un compte de compensation pour cartes
            $compteClient = $carte['id_compte_client'];
            $compteCompensation = $this->getCompteInterneCompensationCartes();
            
            $description = "Paiement par carte n°..." . substr($numeroCarte, -4) . " chez " . $marchand;
            $this->gestionTransactions->enregistrerTransaction(
                $compteClient,
                $compteCompensation,
                $montant,
                'PaiementCarte',
                $description
            );

            // Étape 3 : Enregistrer les détails de la transaction pour l'historique
            $this->enregistrerHistoriqueTransaction($carte['id'], $montant, 'DEBIT', $marchand);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors de la transaction par carte : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Bloque une carte en cas d'activité suspecte ou de solde insuffisant.
     * @param string $numeroCarte Le numéro de la carte.
     * @param string $raison La raison du blocage.
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function bloquerCarte($numeroCarte, $raison) {
        try {
            $sql = "UPDATE cartes SET statut = 'BLOQUEE', raison_blocage = ? WHERE numero_carte = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$raison, $numeroCarte]);

            return true;
        } catch (Exception $e) {
            error_log("Erreur lors du blocage de la carte : " . $e->getMessage());
            return false;
        }
    }

    // --- Fonctions auxiliaires privées ---

    private function verifierNumeroCarteExistant($numeroCarte) {
        $sql = "SELECT COUNT(*) FROM cartes WHERE numero_carte = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$numeroCarte]);
        return $stmt->fetchColumn() > 0;
    }

    private function getCarte($numeroCarte) {
        $sql = "SELECT * FROM cartes WHERE numero_carte = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$numeroCarte]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getCompteInterneCompensationCartes() {
        // Compte interne de la banque pour les flux de paiements par carte.
        return 907; // ID de compte fictif
    }
    
    private function enregistrerHistoriqueTransaction($carteId, $montant, $sens, $marchand) {
        // Simule l'enregistrement d'une transaction de carte dans une table dédiée.
        $sql = "INSERT INTO transactions_cartes (carte_id, montant, sens, marchand, date_transaction) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$carteId, $montant, $sens, $marchand]);
    }

     public function getCarteByNumero(string $numero_carte): ?array {
        $sql = "SELECT * FROM cartes WHERE numero_carte = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$numero_carte]);
        $resultat = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultat ?: null;
    }
}