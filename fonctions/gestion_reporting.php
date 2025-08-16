<?php
// fonctions/gestion_reporting.php

/**
 * Fonctions de gestion des rapports financiers.
 * Ce fichier centralise la logique de récupération et de calcul des données pour les différents états financiers.
 */

// Inclure la base de données
// Assurez-vous que le fichier database.php est correctement configuré et accessible.
require_once 'database.php';

/**
 * Récupère le solde antérieur d'un compte avant une date spécifiée.
 *
 * @param PDO $pdo L'objet de connexion PDO à la base de données.
 * @param int $compteId L'ID du compte.
 * @param string|null $dateFin La date jusqu'à laquelle le solde doit être calculé.
 * @return array Un tableau associatif contenant le solde total du débit et du crédit.
 */



/**
 * Récupère les totaux de débit et de crédit pour une plage de dates et une catégorie de comptes.
 * Utile pour les rapports comme le bilan ou le compte de résultat.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param array $compteIds Un tableau d'IDs de comptes à inclure.
 * @param string|null $dateDebut La date de début de la période.
 * @param string|null $dateFin La date de fin de la période.
 * @return array Un tableau associatif avec les totaux de débit et de crédit.
 */
function getTotalsByAccounts(PDO $pdo, array $compteIds, ?string $dateDebut = null, ?string $dateFin = null): array
{
    if (empty($compteIds)) {
        return ['total_debit' => 0, 'total_credit' => 0];
    }

    $inClause = implode(',', array_fill(0, count($compteIds), '?'));
    $sql = "
        SELECT 
            SUM(CASE WHEN le.Sens = 'D' THEN le.Montant ELSE 0 END) AS total_debit,
            SUM(CASE WHEN le.Sens = 'C' THEN le.Montant ELSE 0 END) AS total_credit
        FROM Lignes_Ecritures le
        JOIN Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
        WHERE le.ID_Compte IN ($inClause)
    ";

    $params = $compteIds;

    if ($dateDebut) {
        $sql .= " AND e.Date_Saisie >= ?";
        $params[] = $dateDebut;
    }
    if ($dateFin) {
        $sql .= " AND e.Date_Saisie <= ?";
        $params[] = $dateFin;
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_debit' => (float)($result['total_debit'] ?? 0),
            'total_credit' => (float)($result['total_credit'] ?? 0)
        ];
    } catch (PDOException $e) {
        error_log("Erreur de base de données (getTotalsByAccounts) : " . $e->getMessage());
        return ['total_debit' => 0, 'total_credit' => 0];
    }
}

/**
 * Génère un rapport de balance générale pour une période donnée.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param string|null $dateDebut La date de début de la période.
 * @param string|null $dateFin La date de fin de la période.
 * @return array Un tableau d'objets ou de tableaux représentant les lignes de la balance.
 */
function getBalanceGenerale(PDO $pdo, ?string $dateDebut = null, ?string $dateFin = null): array
{
    $sql = "
        SELECT
            c.Numero_Compte,
            c.Nom_Compte,
            SUM(CASE WHEN le.Sens = 'D' AND e.Date_Saisie < :date_debut THEN le.Montant ELSE 0 END) AS SoldeAnterieurDebit,
            SUM(CASE WHEN le.Sens = 'C' AND e.Date_Saisie < :date_debut THEN le.Montant ELSE 0 END) AS SoldeAnterieurCredit,
            SUM(CASE WHEN le.Sens = 'D' AND e.Date_Saisie >= :date_debut AND e.Date_Saisie <= :date_fin THEN le.Montant ELSE 0 END) AS MouvementPeriodeDebit,
            SUM(CASE WHEN le.Sens = 'C' AND e.Date_Saisie >= :date_debut AND e.Date_Saisie <= :date_fin THEN le.Montant ELSE 0 END) AS MouvementPeriodeCredit
        FROM Lignes_Ecritures le
        JOIN Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
        JOIN Comptes_compta c ON le.ID_Compte = c.ID_Compte
        GROUP BY c.Numero_Compte, c.Nom_Compte
        ORDER BY c.Numero_Compte ASC
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':date_debut' => $dateDebut,
            ':date_fin' => $dateFin
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur de base de données (getBalanceGenerale) : " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les données pour un rapport de profit et de perte (compte de résultat).
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param string|null $dateDebut La date de début de la période.
 * @param string|null $dateFin La date de fin de la période.
 * @return array Un tableau structuré avec les données de revenus et de dépenses.
 */
function getProfitLossStatement(PDO $pdo, ?string $dateDebut = null, ?string $dateFin = null): array
{
    // Récupérer les ID des comptes de revenus (classe 7)
    $revenusCompteIds = getComptesByClasse($pdo, '7');
    $revenusTotals = getTotalsByAccounts($pdo, $revenusCompteIds, $dateDebut, $dateFin);

    // Récupérer les ID des comptes de dépenses (classe 6)
    $depensesCompteIds = getComptesByClasse($pdo, '6');
    $depensesTotals = getTotalsByAccounts($pdo, $depensesCompteIds, $dateDebut, $dateFin);

    $totalRevenus = $revenusTotals['total_credit'] - $revenusTotals['total_debit'];
    $totalDepenses = $depensesTotals['total_debit'] - $depensesTotals['total_credit'];
    $resultatNet = $totalRevenus - $totalDepenses;

    return [
        'revenus' => $totalRevenus,
        'depenses' => $totalDepenses,
        'resultat_net' => $resultatNet
    ];
}

/**
 * Récupère les ID des comptes en fonction de leur numéro de classe.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param string $classe La classe de compte (ex: '6' ou '7').
 * @return array Un tableau d'IDs de comptes.
 */
function getComptesByClasse(PDO $pdo, string $classe): array
{
    $sql = "SELECT ID_Compte FROM Comptes_compta WHERE Numero_Compte LIKE :classe_prefix";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':classe_prefix' => $classe . '%']);
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (PDOException $e) {
        error_log("Erreur de base de données (getComptesByClasse) : " . $e->getMessage());
        return [];
    }
}