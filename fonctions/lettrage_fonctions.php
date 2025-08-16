<?php
// fonctions/lettrage_fonctions.php

/**
 * Récupère toutes les lignes d'écriture d'un compte qui ne sont pas lettrées.
 *
 * @param PDO $pdo La connexion à la base de données.
 * @param int $compteId L'ID du compte.
 * @return array Les lignes d'écriture.
 */
function getEcrituresNonLettrees(PDO $pdo, int $compteId): array
{
    $sql = "
        SELECT 
            le.ID_Ligne_Ecriture,
            le.Montant,
            le.Sens,
            le.Date_Saisie,
            e.Libelle_Ecriture
        FROM Lignes_Ecritures le
        JOIN Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
        WHERE le.ID_Compte = :compteId AND le.Lettre_Lettrage IS NULL
        ORDER BY le.Date_Saisie ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['compteId' => $compteId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Effectue le lettrage pour un ensemble de lignes d'écriture.
 *
 * @param PDO $pdo La connexion à la base de données.
 * @param array $lignesIds Les IDs des lignes d'écriture à lettrer.
 * @return bool True en cas de succès, False en cas d'échec.
 */
function lettrerEcritures(PDO $pdo, array $lignesIds): bool
{
    if (empty($lignesIds)) {
        return false;
    }
    
    // Générer une lettre de lettrage unique
    $lettre = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 1);
    
    $inClause = str_repeat('?,', count($lignesIds) - 1) . '?';
    $sql = "
        UPDATE Lignes_Ecritures 
        SET Lettre_Lettrage = :lettre
        WHERE ID_Ligne_Ecriture IN ($inClause)
    ";

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':lettre', $lettre, PDO::PARAM_STR);
        foreach ($lignesIds as $index => $id) {
            $stmt->bindValue($index + 2, $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erreur lors du lettrage des écritures : " . $e->getMessage());
        return false;
    }
}