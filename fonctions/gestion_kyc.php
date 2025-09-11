<?php
// fonctions/clients/gestion_kyc.php

/**
 * Fonctions pour la gestion des dossiers KYC (Know Your Customer)
 * Ce module est crucial pour la conformité réglementaire.
 */

class GestionKyc {

    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Crée un nouveau dossier KYC pour un client.
     * @param int $id_client L'ID du client.
     * @param array $donnees_kyc Un tableau contenant les informations du dossier.
     * @return bool Vrai en cas de succès, faux sinon.
     */
    public function creerDossierKyc(int $id_client, array $donnees_kyc): bool {
        try {
            // Vérifier si le client a déjà un dossier KYC actif
            $dossierExistant = $this->getDossierKycActif($id_client);
            if ($dossierExistant) {
                // Option : archiver l'ancien dossier avant de créer le nouveau
                $this->archiverDossierKyc($dossierExistant['id']);
            }
            
            $sql = "INSERT INTO dossiers_kyc (
                        id_client,
                        type_piece_identite,
                        numero_piece,
                        date_expiration_piece,
                        fichier_piece,
                        type_justificatif_domicile,
                        fichier_justificatif,
                        date_verification,
                        statut_verification,
                        commentaire
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $id_client,
                $donnees_kyc['type_piece_identite'],
                $donnees_kyc['numero_piece'],
                $donnees_kyc['date_expiration_piece'],
                $donnees_kyc['fichier_piece'] ?? null,
                $donnees_kyc['type_justificatif_domicile'] ?? null,
                $donnees_kyc['fichier_justificatif'] ?? null,
                $donnees_kyc['statut_verification'],
                $donnees_kyc['commentaire'] ?? null
            ]);

            return true;
        } catch (PDOException $e) {
            error_log("Erreur lors de la création du dossier KYC : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Met à jour le statut de vérification d'un dossier KYC.
     * @param int $dossier_id L'ID du dossier KYC.
     * @param string $nouveau_statut Le nouveau statut ('VALIDE', 'REJETE', etc.).
     * @param string|null $commentaire Un commentaire sur la mise à jour.
     * @return bool Vrai en cas de succès, faux sinon.
     */
    public function mettreAJourStatut(int $dossier_id, string $nouveau_statut, ?string $commentaire = null): bool {
        try {
            $sql = "UPDATE dossiers_kyc SET statut_verification = ?, commentaire = ?, date_verification = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$nouveau_statut, $commentaire, $dossier_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour du statut KYC : " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Récupère le dossier KYC actif pour un client.
     * @param int $id_client L'ID du client.
     * @return array|null Le dossier KYC ou null s'il n'y en a pas.
     */
    public function getDossierKycActif(int $id_client): ?array {
        $sql = "SELECT * FROM dossiers_kyc WHERE id_client = ? AND statut_verification != 'ARCHIVE' ORDER BY date_verification DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id_client]);
        $resultat = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultat ?: null;
    }

    /**
     * Archive un dossier KYC existant.
     * @param int $dossier_id L'ID du dossier à archiver.
     * @return bool Vrai en cas de succès, faux sinon.
     */
    private function archiverDossierKyc(int $dossier_id): bool {
        return $this->mettreAJourStatut($dossier_id, 'ARCHIVE', 'Archivé car un nouveau dossier a été créé.');
    }
}
function getDossiersKycByStatut(PDO $pdo, string $statut): array {
    try {
        $sql = "
            SELECT 
                d.id,
                d.id_client,
                d.type_piece_identite,
                d.numero_piece,
                d.date_creation_dossier,
                d.statut_verification,
                d.date_verification,
                c.nom_ou_raison_sociale AS nom_client,
                c.nom_abrege AS prenoms_client
            FROM dossiers_kyc AS d
            INNER JOIN clients AS c ON d.id_client = c.id_client
            WHERE d.statut_verification = ?
            ORDER BY d.date_creation_dossier DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$statut]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération des dossiers KYC : " . $e->getMessage());
        return [];
    }
}