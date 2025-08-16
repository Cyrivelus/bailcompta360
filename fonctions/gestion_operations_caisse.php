<?php
// fonctions/gestion_operations_caisse.php

/**
 * Récupère le mouvement de caisse ouvert pour un utilisateur et la date d'aujourd'hui.
 * @param PDO $pdo L'instance PDO.
 * @param int $id_utilisateur L'ID de l'utilisateur.
 * @return array|false Le mouvement de caisse s'il existe, sinon false.
 */
function getCaisseOuverte(PDO $pdo, int $id_utilisateur): array|false
{
    try {
        // Utilisation d'une plage de dates pour gérer les colonnes DATETIME avec CURDATE()
        $query = "SELECT * FROM Mouvements_Caisse 
                  WHERE id_utilisateur = ? 
                  AND date_ouverture >= CURDATE() 
                  AND date_ouverture < (CURDATE() + INTERVAL 1 DAY) 
                  AND etat = 'Ouverte'";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id_utilisateur]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getCaisseOuverte() : " . $e->getMessage());
        return false;
    }
}

/**
 * Ouvre une nouvelle caisse pour l'utilisateur.
 * @param PDO $pdo
 * @param int $id_utilisateur
 * @param string $id_agence Le code de l'agence (VARCHAR(3)).
 * @param float $solde_initial
 * @return bool
 */
function ouvrirCaisse(PDO $pdo, int $id_utilisateur, string $id_agence, float $solde_initial): bool
{
    try {
        // Requête pour insérer un nouvel enregistrement dans Mouvements_Caisse.
        // On utilise NOW() pour la date et l'heure actuelles.
        $query = "INSERT INTO Mouvements_Caisse (id_utilisateur, id_agence, date_ouverture, solde_initial, solde_final, ecart, etat) 
                  VALUES (?, ?, NOW(), ?, NULL, NULL, 'Ouverte')";
        
        $stmt = $pdo->prepare($query);
        
        // Assurez-vous que l'ordre des paramètres correspond à celui de la requête.
        // id_agence est de type string
        return $stmt->execute([$id_utilisateur, $id_agence, $solde_initial]);

    } catch (PDOException $e) {
        error_log("Erreur PDO lors de l'ouverture de la caisse : " . $e->getMessage());
        // Vous pouvez vérifier les contraintes d'unicité ici si le login est unique
        if ($e->getCode() === '23000') { // Code d'erreur pour violation de contrainte d'unicité
            // Gérer spécifiquement le cas où une caisse est déjà ouverte
            return false; // Ou une autre gestion d'erreur plus spécifique
        }
        return false;
    }
}

/**
 * Ferme la caisse ouverte en mettant à jour le mouvement de caisse.
 * @param PDO $pdo
 * @param int $id_mouvement_caisse L'ID du mouvement de caisse à fermer.
 * @param float $solde_final_declare Le solde déclaré par le caissier.
 * @param float $ecart L'écart calculé.
 * @return bool
 */
function fermerCaisse(PDO $pdo, int $id_mouvement_caisse, float $solde_final_declare, float $ecart): bool
{
    try {
        $query = "UPDATE Mouvements_Caisse SET date_fermeture = NOW(), solde_final = ?, ecart = ?, etat = 'Fermée' WHERE id_mouvement_caisse = ?";
        $stmt = $pdo->prepare($query);
        return $stmt->execute([$solde_final_declare, $ecart, $id_mouvement_caisse]);
    } catch (PDOException $e) {
        error_log("Erreur lors de la fermeture de la caisse : " . $e->getMessage());
        return false;
    }
}

/**
 * Calcule le solde courant de la caisse en se basant sur les transactions.
 * Vous devez avoir une table 'Transactions_Caisse' liée à 'Mouvements_Caisse'.
 * @param PDO $pdo
 * @param int $id_mouvement_caisse L'ID du mouvement de caisse ouvert.
 * @return float Le solde courant.
 */
function calculerSoldeCourant(PDO $pdo, int $id_mouvement_caisse): float
{
    try {
        // Récupérer le solde initial du mouvement de caisse en cours
        $stmt = $pdo->prepare("SELECT solde_initial FROM Mouvements_Caisse WHERE id_mouvement_caisse = ?");
        $stmt->execute([$id_mouvement_caisse]);
        $solde_initial = $stmt->fetchColumn();

        if ($solde_initial === false) {
            return 0.0; // Pas de mouvement de caisse trouvé ou erreur, retourne 0.0 pour éviter un crash
        }

        // Calculer la somme de toutes les transactions de la journée
        // Assurez-vous que la table Transactions_Caisse existe et est correctement liée
        $stmt_sum = $pdo->prepare("SELECT SUM(CASE WHEN type = 'Entree' THEN montant ELSE -montant END) 
                                   FROM Transactions_Caisse 
                                   WHERE id_mouvement_caisse = ?");
        $stmt_sum->execute([$id_mouvement_caisse]);
        $total_transactions = $stmt_sum->fetchColumn() ?? 0.0; // Utiliser ?? pour gérer les cas où SUM() retourne NULL

        return $solde_initial + $total_transactions;
    } catch (PDOException $e) {
        error_log("Erreur lors du calcul du solde courant : " . $e->getMessage());
        return 0.0; // Retourner 0.0 en cas d'erreur de base de données
    }
}

?>