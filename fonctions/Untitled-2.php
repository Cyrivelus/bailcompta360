<?php
// fonctions/lettrage.php

/**
 * Récupère les comptes ayant des écritures non lettrées.
 * Un compte est lettrable si le solde de ses écritures n'est pas nul.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @return array Une liste des comptes lettrables.
 */
function getComptesLettrables(PDO $pdo): array
{
    $sql = "SELECT DISTINCT Cpt FROM lignes_ecritures GROUP BY Cpt HAVING SUM(CASE WHEN Sns = 'D' THEN Montant ELSE -Montant END) != 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère toutes les lignes d'écritures pour un compte donné.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param string $cpt Le numéro de compte.
 * @return array Un tableau des lignes d'écritures.
 */
function getLignesEcrituresByCpt(PDO $pdo, string $cpt): array
{
    $sql = "SELECT * FROM lignes_ecritures WHERE Cpt = :cpt ORDER BY Dte DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':cpt', $cpt, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>