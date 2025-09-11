<?php
// fonctions/credits/gestion_credits.php

class GestionCredits {
    
    /**
     * @var PDO L'objet de connexion à la base de données.
     */
    private $db;

    /**
     * Constructeur de la classe GestionCredits.
     * @param PDO $db L'objet de connexion à la base de données.
     */
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Crée une nouvelle demande de crédit dans la base de données.
     * @param int $id_client L'ID du client demandeur.
     * @param float $montant Le montant demandé.
     * @param int $duree_mois La durée en mois.
     * @param float $taux_interet Le taux d'intérêt.
     * @param string $type_credit Le type de crédit.
     * @param string $motif Le motif de la demande.
     * @return bool Vrai si la création a réussi, faux sinon.
     */
    public function creerDemande(
        int $id_client,
        float $montant,
        int $duree_mois,
        float $taux_interet,
        string $type_credit,
        string $motif
    ): bool {
        try {
            $sql = "INSERT INTO demandes_credits (
                        id_client,
                        montant,
                        duree_mois,
                        taux_interet,
                        type_credit,
                        motif,
                        date_demande,
                        statut
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'EN_COURS')";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $id_client,
                $montant,
                $duree_mois,
                $taux_interet,
                $type_credit,
                $motif
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Erreur de BDD lors de la création de la demande de crédit : " . $e->getMessage());
            return false;
        }
    }
      
    public function getDemandesByStatut(string $statut): array {
        $sql = "SELECT d.*, c.nom AS nom_client, c.prenoms AS prenoms_client 
                FROM demandes_credits d 
                JOIN clients c ON d.id_client = c.id_client 
                WHERE d.statut = ?
                ORDER BY d.date_demande ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$statut]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function creerHypotheque(
        int $id_client,
        float $valeur_bien,
        float $montant_emprunte,
        string $date_emprunt,
        int $duree_emprunt,
        ?string $description
    ): bool {
        try {
            $sql = "INSERT INTO hypotheques (
                        id_client,
                        valeur_bien,
                        montant_emprunte,
                        date_emprunt,
                        duree_emprunt,
                        description
                    ) VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $id_client,
                $valeur_bien,
                $montant_emprunte,
                $date_emprunt,
                $duree_emprunt,
                $description
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Erreur lors de l'enregistrement de l'hypothèque : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves all mortgages for a given client ID.
     * @param int $id_client The client ID.
     * @return array
     */
    public function getHypothequesByClient(int $id_client): array {
        $sql = "SELECT * FROM hypotheques WHERE id_client = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id_client]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}