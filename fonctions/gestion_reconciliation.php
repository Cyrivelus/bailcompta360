<?php
// fonctions/gestion_reconciliation.php

/**
 * Récupère les lignes d'écriture pour la réconciliation.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int|null $idCompte L'ID du compte à filtrer.
 * @param string|null $etat L'état de réconciliation ('non' ou 'oui').
 * @return array Un tableau des lignes d'écriture.
 */
function getLignesReconciliation(PDO $pdo, ?int $idCompte, ?string $etat): array
{
    $sql = "SELECT le.*, cc.Numero_Compte, cc.Nom_Compte 
            FROM lignes_ecritures le
            JOIN comptes_compta cc ON le.ID_Compte = cc.ID_Compte
            WHERE 1=1";
    $params = [];

    if ($idCompte !== null) {
        $sql .= " AND le.ID_Compte = :id_compte";
        $params[':id_compte'] = $idCompte;
    }

    if ($etat === 'non') {
        $sql .= " AND le.is_reconciled = 0";
    } elseif ($etat === 'oui') {
        $sql .= " AND le.is_reconciled = 1";
    }

    $sql .= " ORDER BY le.ID_Ecriture DESC, le.ID_Ligne DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Marque une ou plusieurs lignes comme réconciliées.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param array $idsLignes Un tableau d'IDs de lignes à réconcilier.
 * @param int $reconciledBy L'ID de l'utilisateur qui effectue la réconciliation.
 * @return bool True si la mise à jour a réussi, false sinon.
 */
function reconcilierLignes(PDO $pdo, array $idsLignes, int $reconciledBy): bool
{
    if (empty($idsLignes)) {
        return false;
    }

    $placeholders = implode(',', array_fill(0, count($idsLignes), '?'));
    
    $sql = "UPDATE lignes_ecritures 
            SET is_reconciled = 1, 
                reconciled_at = NOW(),
                reconciled_by = ?
            WHERE ID_Ligne IN ($placeholders)";

    $stmt = $pdo->prepare($sql);
    
    $params = array_merge([$reconciledBy], $idsLignes);
    
    return $stmt->execute($params);
}

/**
 * Annule la réconciliation pour une ou plusieurs lignes.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param array $idsLignes Un tableau d'IDs de lignes à déréconcilier.
 * @return bool True si la mise à jour a réussi, false sinon.
 */
function dereconcilierLignes(PDO $pdo, array $idsLignes): bool
{
    if (empty($idsLignes)) {
        return false;
    }

    $placeholders = implode(',', array_fill(0, count($idsLignes), '?'));
    
    $sql = "UPDATE lignes_ecritures 
            SET is_reconciled = 0, 
                reconciled_at = NULL,
                reconciled_by = NULL
            WHERE ID_Ligne IN ($placeholders)";

    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute($idsLignes);
}