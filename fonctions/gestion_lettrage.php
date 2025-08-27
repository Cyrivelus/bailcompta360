<?php
// fonctions/gestion_lettrage.php

/**
 * Récupère les lignes d'écriture d'un compte donné, non lettrées et lettrées par une lettre spécifique.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $idCompte L'ID du compte sur lequel effectuer le lettrage.
 * @param string $lettreOptionnelle (Optionnel) La lettre de lettrage pour afficher les lignes déjà lettrées.
 * @return array Un tableau des lignes d'écriture.
 */
function getLignesPourLettrage(PDO $pdo, int $idCompte, string $lettreOptionnelle = null): array
{
    $sql = "SELECT * FROM lignes_ecritures
            WHERE ID_Compte = :id_compte 
            AND (Lettre_Lettrage IS NULL OR Lettre_Lettrage = :lettre_optionnelle)
            ORDER BY reconciled_at, Montant, Sens DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id_compte', $idCompte, PDO::PARAM_INT);
    $stmt->bindValue(':lettre_optionnelle', $lettreOptionnelle, PDO::PARAM_STR);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Applique une lettre de lettrage à une sélection de lignes d'écriture.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param array $idsLignes Un tableau d'IDs de lignes à lettrer.
 * @param string $lettre La lettre de lettrage à appliquer.
 * @return bool True si la mise à jour a réussi, false sinon.
 */
function appliquerLettrage(PDO $pdo, array $idsLignes, string $lettre): bool
{
    if (empty($idsLignes)) {
        return false;
    }

    // Créer une liste de marqueurs pour la requête
    $placeholders = implode(',', array_fill(0, count($idsLignes), '?'));
    
    $sql = "UPDATE lignes_ecritures 
            SET Lettre_Lettrage = ?, 
                is_reconciled = 1, 
                reconciled_at = NOW() 
            WHERE ID_Ligne IN ($placeholders)";

    $stmt = $pdo->prepare($sql);
    
    // Associer la lettre et les IDs
    $params = array_merge([$lettre], $idsLignes);
    
    return $stmt->execute($params);
}