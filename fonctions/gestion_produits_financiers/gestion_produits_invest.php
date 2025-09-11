<?php

require_once 'fonctions/database.php';
require_once 'fonctions/transactions/gestion_transactions.php';

class GestionProduitsInvest {

    private $db;
    private $gestionTransactions;

    public function __construct() {
        $this->db = getDatabaseInstance();
        $this->gestionTransactions = new GestionTransactions();
    }

    /**
     * Effectue l'achat d'un produit d'investissement pour un client.
     * @param int $clientId L'ID du client.
     * @param string $symbole Le symbole du titre (ex: 'AAPL', 'MSFT').
     * @param int $quantite La quantité de titres à acheter.
     * @param float $prixUnitaire Le prix d'achat par unité.
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function acheterProduitInvest($clientId, $symbole, $quantite, $prixUnitaire) {
        $montantTotal = $quantite * $prixUnitaire;
        $this->db->beginTransaction();
        try {
            // Étape 1 : Enregistrer le mouvement financier (débit du compte client)
            $compteClient = $this->getCompteCourantClient($clientId);
            $compteBanqueInvest = $this->getCompteInterneInvestissements();
            
            $description = "Achat de " . $quantite . " titres " . $symbole;
            $this->gestionTransactions->enregistrerTransaction(
                $compteClient,
                $compteBanqueInvest,
                $montantTotal,
                'AchatInvestissement',
                $description
            );

            // Étape 2 : Mettre à jour le portefeuille du client
            $sql = "INSERT INTO portefeuille_invest (client_id, symbole, quantite, prix_moyen_achat, date_achat)
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE quantite = quantite + VALUES(quantite),
                                            prix_moyen_achat = ((prix_moyen_achat * (quantite - VALUES(quantite))) + (VALUES(prix_moyen_achat) * VALUES(quantite))) / quantite";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$clientId, $symbole, $quantite, $prixUnitaire]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors de l'achat d'investissement : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Effectue la vente d'un produit d'investissement.
     * @param int $clientId L'ID du client.
     * @param string $symbole Le symbole du titre.
     * @param int $quantite La quantité de titres à vendre.
     * @param float $prixUnitaire Le prix de vente par unité.
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function vendreProduitInvest($clientId, $symbole, $quantite, $prixUnitaire) {
        $montantTotal = $quantite * $prixUnitaire;
        $this->db->beginTransaction();
        try {
            // Étape 1 : Vérifier que le client possède suffisamment de titres
            $portefeuille = $this->getPortefeuilleClient($clientId, $symbole);
            if (!$portefeuille || $portefeuille['quantite'] < $quantite) {
                throw new Exception("Quantité insuffisante de titres pour la vente.");
            }

            // Étape 2 : Enregistrer le mouvement financier (crédit sur le compte client)
            $compteClient = $this->getCompteCourantClient($clientId);
            $compteBanqueInvest = $this->getCompteInterneInvestissements();
            
            $description = "Vente de " . $quantite . " titres " . $symbole;
            $this->gestionTransactions->enregistrerTransaction(
                $compteBanqueInvest,
                $compteClient,
                $montantTotal,
                'VenteInvestissement',
                $description
            );

            // Étape 3 : Mettre à jour le portefeuille du client
            $quantiteRestante = $portefeuille['quantite'] - $quantite;
            if ($quantiteRestante > 0) {
                $sql = "UPDATE portefeuille_invest SET quantite = ?, date_vente = NOW() WHERE client_id = ? AND symbole = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$quantiteRestante, $clientId, $symbole]);
            } else {
                $sql = "DELETE FROM portefeuille_invest WHERE client_id = ? AND symbole = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$clientId, $symbole]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors de la vente d'investissement : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère le portefeuille d'investissement complet d'un client.
     * @param int $clientId L'ID du client.
     * @return array La liste des titres détenus par le client.
     */
    public function getPortefeuilleClient($clientId, $symbole = null) {
        $sql = "SELECT * FROM portefeuille_invest WHERE client_id = ?";
        $params = [$clientId];
        if ($symbole) {
            $sql .= " AND symbole = ?";
            $params[] = $symbole;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $symbole ? $stmt->fetch(PDO::FETCH_ASSOC) : $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Calcule la valeur totale d'un portefeuille à un prix du marché donné.
     * @param int $clientId L'ID du client.
     * @param array $prixCourants Un tableau associatif [symbole => prix].
     * @return float La valeur totale du portefeuille.
     */
    public function valoriserPortefeuille($clientId, $prixCourants) {
        $portefeuille = $this->getPortefeuilleClient($clientId);
        $valeurTotale = 0;

        foreach ($portefeuille as $titre) {
            $symbole = $titre['symbole'];
            $quantite = $titre['quantite'];
            if (isset($prixCourants[$symbole])) {
                $valeurTotale += $quantite * $prixCourants[$symbole];
            }
        }
        return $valeurTotale;
    }

    // --- Fonctions auxiliaires privées ---

    private function getCompteCourantClient($clientId) {
        // Logique pour trouver le compte courant principal du client.
        return 101; // ID de compte fictif
    }

    private function getCompteInterneInvestissements() {
        // Compte interne de la banque pour les opérations sur les investissements.
        return 904; // ID de compte fictif
    }
}