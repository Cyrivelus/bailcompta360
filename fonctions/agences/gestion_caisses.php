<?php
// fonctions/agences/gestion_caisses.php

class GestionCaisses {
    private PDO $db;

    /**
     * Constructeur.
     *
     * @param PDO $db Instance de connexion PDO à la base de données.
     */
    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Récupère toutes les caisses d'agence avec le nom de l'agence associée.
     * Cette fonction est utilisée pour remplir le menu déroulant.
     * Elle utilise un LEFT JOIN pour s'assurer que toutes les caisses sont listées,
     * même si la correspondance avec la table 'agences' n'existe pas.
     *
     * @return array Un tableau de caisses.
     */
    public function getToutesCaisses(): array {
        try {
            $sql = "SELECT c.id_caisse, c.nom_caisse, a.nom_agence
                    FROM caisses c
                    LEFT JOIN agences a ON c.CodeAgenceSCE = a.CodeAgence
                    ORDER BY a.nom_agence, c.nom_caisse";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur PDO dans getToutesCaisses: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère le solde théorique d'une caisse à une date donnée.
     * Ce solde est calculé en sommant les transactions antérieures à la date spécifiée.
     *
     * @param int $idCaisse L'ID de la caisse.
     * @param string $date La date de référence au format YYYY-MM-DD.
     * @return float Le solde calculé.
     */
    public function getSoldeTheorique(int $idCaisse, string $date): float {
        try {
            // Le solde est la somme des montants des écritures de la caisse avant la date spécifiée.
            // On joint la table 'Ecritures' pour utiliser la 'Date_Saisie' et le champ 'Cde'.
            // On suppose que Cde 'CA' correspond aux écritures de caisse.
            $sql = "SELECT SUM(E.Montant_Total) AS solde
                     FROM Ecritures E
                     JOIN Cde J ON E.Cde = J.Cde -- Suppose a join to get more info on Cde
                     WHERE E.Cde = 'CA' AND E.NumeroAgenceSCE = :id_caisse AND E.Date_Saisie < :date";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id_caisse' => $idCaisse,
                ':date' => $date
            ]);
            $result = $stmt->fetchColumn();
            return (float) ($result ?? 0.0);
        } catch (PDOException $e) {
            error_log("Erreur PDO dans getSoldeTheorique: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Récupère toutes les transactions pour une caisse sur une période donnée.
     *
     * @param int $idCaisse L'ID de la caisse.
     * @param string $dateDebut La date de début de la période au format YYYY-MM-DD.
     * @param string $dateFin La date de fin de la période au format YYYY-MM-DD.
     * @return array Un tableau de transactions.
     */
    public function getTransactionsCaisse(int $idCaisse, string $dateDebut, string $dateFin): array {
        try {
            // Dans ce modèle, les transactions de caisse sont des écritures avec le Cde 'CA'.
            // Le montant total représente le flux (positif pour un encaissement, négatif pour un décaissement).
            $sql = "SELECT *
                     FROM Ecritures
                     WHERE NumeroAgenceSCE = :id_caisse
                     AND Cde = 'CA'
                     AND Date_Saisie BETWEEN :date_debut AND :date_fin
                     ORDER BY Date_Saisie ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id_caisse' => $idCaisse,
                ':date_debut' => $dateDebut,
                ':date_fin' => $dateFin
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur PDO dans getTransactionsCaisse: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Enregistre un rapport de rapprochement de caisse dans une table dédiée.
     *
     * @param int $idCaisse L'ID de la caisse.
     * @param string $dateRapprochement La date du rapprochement.
     * @param float $soldeTheorique Le solde calculé par le système.
     * @param float $soldePhysique Le solde réel, constaté physiquement.
     * @param float $ecart La différence entre le solde physique et le solde théorique.
     * @return bool Vrai si l'enregistrement a réussi, faux sinon.
     */
    public function enregistrerRapprochement(
        int $idCaisse,
        string $dateRapprochement,
        float $soldeTheorique,
        float $soldePhysique,
        float $ecart
    ): bool {
        try {
            // Vous devez avoir une table 'rapprochements_caisses' pour stocker ces informations.
            // Les noms des colonnes ci-dessous sont des exemples.
            $sql = "INSERT INTO rapprochements_caisses (
                         ID_Caisse, Date_Rapprochement, Solde_Theorique, Solde_Physique, Ecart
                     ) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $idCaisse,
                $dateRapprochement,
                $soldeTheorique,
                $soldePhysique,
                $ecart
            ]);
        } catch (PDOException $e) {
            error_log("Erreur PDO lors de l'enregistrement du rapprochement: " . $e->getMessage());
            return false;
        }
    }
}
