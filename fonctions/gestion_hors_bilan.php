<?php
// fonctions/gestion_hors_bilan.php

/**
 * Fonctions de gestion pour les engagements hors bilan.
 * Nécessite une connexion PDO active.
 */

/**
 * Récupère la liste de tous les engagements hors bilan de la base de données.
 * Effectue une jointure pour récupérer le nom et le prénom du client associé.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @return array Un tableau d'engagements hors bilan.
 */
function listerEngagementsHorsBilan(PDO $pdo): array
{
    $sql = "SELECT e.id_engagement, e.type_engagement, e.montant, e.date_emission, 
                   c.nom AS nom_client, c.prenom AS prenom_client
            FROM engagements_hors_bilan e
            JOIN clients c ON e.id_client = c.id_client
            ORDER BY e.date_emission DESC";

    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Enregistrez l'erreur et lancez une exception pour la gestion des erreurs côté utilisateur
        error_log("Erreur de base de données : " . $e->getMessage());
        throw new Exception("Impossible de récupérer la liste des engagements hors bilan.");
    }
}

/**
 * Supprime un engagement hors bilan de la base de données.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id L'ID de l'engagement à supprimer.
 * @return bool Vrai si la suppression a réussi, faux sinon.
 */
function supprimerEngagementHorsBilan(PDO $pdo, int $id): bool
{
    $sql = "DELETE FROM engagements_hors_bilan WHERE id_engagement = :id";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        // Enregistrez l'erreur et lancez une exception
        error_log("Erreur de base de données lors de la suppression : " . $e->getMessage());
        // Vous pouvez ajouter une vérification pour les erreurs de contrainte
        if (strpos($e->getMessage(), 'Foreign key constraint fails') !== false) {
             throw new Exception("Impossible de supprimer cet engagement car il est lié à d'autres données.");
        } else {
             throw new Exception("Une erreur s'est produite lors de la suppression de l'engagement.");
        }
    }
}

/**
 * Ajoute un nouvel engagement hors bilan à la base de données.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param array $donnees Les données de l'engagement (ex: ['id_client' => 1, 'type_engagement' => 'Aval', ...]).
 * @return bool Vrai si l'ajout a réussi, faux sinon.
 */
function ajouterEngagementHorsBilan(PDO $pdo, array $donnees): bool
{
    $sql = "INSERT INTO engagements_hors_bilan (id_client, type_engagement, montant, date_emission) 
            VALUES (:id_client, :type_engagement, :montant, :date_emission)";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_client', $donnees['id_client'], PDO::PARAM_INT);
        $stmt->bindParam(':type_engagement', $donnees['type_engagement'], PDO::PARAM_STR);
        $stmt->bindParam(':montant', $donnees['montant'], PDO::PARAM_STR);
        $stmt->bindParam(':date_emission', $donnees['date_emission'], PDO::PARAM_STR);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur de base de données lors de l'ajout : " . $e->getMessage());
        throw new Exception("Impossible d'ajouter le nouvel engagement.");
    }
}

/**
 * Met à jour un engagement hors bilan existant.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id_engagement L'ID de l'engagement à modifier.
 * @param array $donnees Les nouvelles données de l'engagement.
 * @return bool Vrai si la mise à jour a réussi, faux sinon.
 */
function modifierEngagementHorsBilan(PDO $pdo, int $id_engagement, array $donnees): bool
{
    $sql = "UPDATE engagements_hors_bilan 
            SET id_client = :id_client, type_engagement = :type_engagement, 
                montant = :montant, date_emission = :date_emission
            WHERE id_engagement = :id_engagement";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_engagement', $id_engagement, PDO::PARAM_INT);
        $stmt->bindParam(':id_client', $donnees['id_client'], PDO::PARAM_INT);
        $stmt->bindParam(':type_engagement', $donnees['type_engagement'], PDO::PARAM_STR);
        $stmt->bindParam(':montant', $donnees['montant'], PDO::PARAM_STR);
        $stmt->bindParam(':date_emission', $donnees['date_emission'], PDO::PARAM_STR);

        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur de base de données lors de la modification : " . $e->getMessage());
        throw new Exception("Impossible de mettre à jour l'engagement.");
    }
}

/**
 * Récupère un seul engagement hors bilan par son ID.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $id L'ID de l'engagement.
 * @return array|false L'engagement sous forme de tableau associatif ou faux s'il n'est pas trouvé.
 */
function trouverEngagementHorsBilanParId(PDO $pdo, int $id): array|false
{
    $sql = "SELECT * FROM engagements_hors_bilan WHERE id_engagement = :id";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur de base de données : " . $e->getMessage());
        throw new Exception("Impossible de trouver l'engagement.");
    }
}