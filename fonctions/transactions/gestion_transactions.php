<?php

require_once 'fonctions/database.php';

class GestionTransactions {

    private $db;

    public function __construct() {
        $this->db = getDatabaseInstance();
    }

    /**
     * Enregistre une transaction complète, incluant les écritures comptables.
     * C'est la fonction principale de cette classe.
     * @param int $idCompteDebit L'ID du compte à débiter.
     * @param int $idCompteCredit L'ID du compte à créditer.
     * @param float $montant Le montant de la transaction.
     * @param string $typeTransaction Le type de transaction (ex: 'Depot', 'Retrait', 'Virement').
     * @param string $description La description de la transaction.
     * @return bool Vrai si la transaction a réussi, Faux sinon.
     */
    public function enregistrerTransaction($idCompteDebit, $idCompteCredit, $montant, $typeTransaction, $description) {
        if ($montant <= 0) {
            error_log("Le montant de la transaction doit être positif.");
            return false;
        }

        $this->db->beginTransaction();

        try {
            // Étape 1 : Enregistrer l'écriture principale dans la table 'ecritures'
            $sqlEcriture = "INSERT INTO ecritures (Date_Saisie, Description, Montant_Total, Cde) VALUES (NOW(), ?, ?, ?)";
            $stmtEcriture = $this->db->prepare($sqlEcriture);
            $stmtEcriture->execute([$description, $montant, $typeTransaction]);
            $idEcriture = $this->db->lastInsertId();

            // Étape 2 : Enregistrer les lignes de débit et de crédit dans la table 'lignes_ecritures'
            
            // Ligne de débit
            $sqlLigneDebit = "INSERT INTO lignes_ecritures (ID_Ecriture, ID_Compte, Montant, Sens, Libelle_Ligne) VALUES (?, ?, ?, 'D', ?)";
            $stmtLigneDebit = $this->db->prepare($sqlLigneDebit);
            $stmtLigneDebit->execute([$idEcriture, $idCompteDebit, $montant, $description]);

            // Ligne de crédit
            $sqlLigneCredit = "INSERT INTO lignes_ecritures (ID_Ecriture, ID_Compte, Montant, Sens, Libelle_Ligne) VALUES (?, ?, ?, 'C', ?)";
            $stmtLigneCredit = $this->db->prepare($sqlLigneCredit);
            $stmtLigneCredit->execute([$idEcriture, $idCompteCredit, $montant, "Contrepartie: " . $description]);

            // Étape 3 : Mettre à jour les soldes dans la table 'comptes'
            
            // Mise à jour du solde du compte débité
            $sqlUpdateDebit = "UPDATE comptes SET solde = solde - ? WHERE id_compte = ?";
            $stmtUpdateDebit = $this->db->prepare($sqlUpdateDebit);
            $stmtUpdateDebit->execute([$montant, $idCompteDebit]);

            // Mise à jour du solde du compte crédité
            $sqlUpdateCredit = "UPDATE comptes SET solde = solde + ? WHERE id_compte = ?";
            $stmtUpdateCredit = $this->db->prepare($sqlUpdateCredit);
            $stmtUpdateCredit->execute([$montant, $idCompteCredit]);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors de l'enregistrement de la transaction : " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Récupère le solde d'un compte donné.
     * @param int $idCompte L'ID du compte.
     * @return float Le solde du compte ou faux si non trouvé.
     */
    public function getSoldeCompte($idCompte) {
        $sql = "SELECT solde FROM comptes WHERE id_compte = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idCompte]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['solde'] : false;
    }
    
    /**
     * Fonction pour un cas de virement de compte à compte client.
     * @param int $idCompteSource L'ID du compte client source.
     * @param int $idCompteDestination L'ID du compte client de destination.
     * @param float $montant Le montant à virer.
     * @param string $description La description du virement.
     * @return bool Vrai si le virement a réussi, Faux sinon.
     */
    public function effectuerVirement($idCompteSource, $idCompteDestination, $montant, $description) {
        // Dans ce cas, la source est un compte client et la destination aussi.
        // On n'a pas besoin de passer par un compte interne de la banque.
        
        $this->db->beginTransaction();
        try {
            // Vérifier que les comptes existent
            if (!$this->verifierCompteExistant($idCompteSource) || !$this->verifierCompteExistant($idCompteDestination)) {
                throw new Exception("Un ou plusieurs comptes sont introuvables.");
            }

            // Vérifier les fonds suffisants
            $soldeSource = $this->getSoldeCompte($idCompteSource);
            if ($soldeSource < $montant) {
                throw new Exception("Fonds insuffisants sur le compte source.");
            }
            
            // Enregistrer la transaction
            $this->enregistrerTransaction($idCompteSource, $idCompteDestination, $montant, 'Virement', $description);
            
            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors du virement : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fonction auxiliaire pour vérifier l'existence d'un compte.
     * @param int $idCompte L'ID du compte.
     * @return bool
     */
    private function verifierCompteExistant($idCompte) {
        $sql = "SELECT COUNT(*) FROM comptes WHERE id_compte = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idCompte]);
        return $stmt->fetchColumn() > 0;
    }

     public function getCarteByNumero(string $numero_carte): ?array {
        $sql = "SELECT * FROM cartes WHERE numero_carte = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$numero_carte]);
        $resultat = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultat ?: null;
    }
     public function getTransactionsByCompte(int $id_compte, string $date_debut, string $date_fin): array {
        $sql = "SELECT * FROM ecritures
                WHERE (id_compte_source = ? OR id_compte_destination = ?)
                AND Date_Saisie >= ? AND Date_Saisie <= ?
                ORDER BY Date_Saisie ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id_compte, $id_compte, $date_debut . ' 00:00:00', $date_fin . ' 23:59:59']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
     public function getSoldeAvantDate(int $id_compte, string $date): float {
        // Solde initial du compte (crédits - débits avant la date)
        $sql = "SELECT SUM(CASE WHEN id_compte_destination = ? THEN montant_total ELSE -montant_total END) as solde_initial
                FROM ecritures
                WHERE (id_compte_source = ? OR id_compte_destination = ?)
                AND Date_Saisie < ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id_compte, $id_compte, $id_compte, $date . ' 00:00:00']);
        $resultat = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultat['solde_initial'] ?: 0.0;
    }
}