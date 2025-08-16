<?php
// fonctions/reporting/liasse_fiscale_fonctions.php

/**
 * Ce fichier contient les fonctions de génération des rapports fiscaux.
 * Il requiert la connexion PDO et les tables Lignes_Ecritures, Comptes_compta,
 * et la table Ecritures.
 */

require_once(__DIR__ . '/../database.php');

/**
 * Génère le bilan pour une année fiscale donnée.
 *
 * @param int $annee_fiscale L'année pour laquelle le bilan doit être généré.
 * @return array Le bilan structuré par catégories (Actif, Passif).
 * @throws Exception si une erreur de base de données survient.
 */
function generer_bilan(int $annee_fiscale): array
{
    global $pdo;
    $bilan = ['actif' => [], 'passif' => []];

    // Requête pour utiliser la colonne Date_Saisie pour le filtre
    $sql = "
        SELECT 
            SUBSTRING(c.Numero_Compte, 1, 1) as classe,
            c.Numero_Compte,
            c.Nom_Compte,
            SUM(CASE WHEN le.Sens = 'D' THEN le.Montant ELSE 0 END) as total_debit,
            SUM(CASE WHEN le.Sens = 'C' THEN le.Montant ELSE 0 END) as total_credit
        FROM Lignes_Ecritures le
        JOIN Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
        JOIN Comptes_compta c ON le.ID_Compte = c.ID_Compte
        WHERE YEAR(e.Date_Saisie) <= :annee_fiscale AND SUBSTRING(c.Numero_Compte, 1, 1) IN ('1', '2', '3', '4', '5')
        GROUP BY c.Numero_Compte, c.Nom_Compte
        ORDER BY c.Numero_Compte ASC
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['annee_fiscale' => $annee_fiscale]);
        $soldes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($soldes as $solde) {
            $solde_compte = $solde['total_debit'] - $solde['total_credit'];
            if ($solde_compte != 0) {
                if (in_array($solde['classe'], ['2', '3', '4'])) {
                    $bilan['actif'][] = $solde;
                } else {
                    $bilan['passif'][] = $solde;
                }
            }
        }
    } catch (PDOException $e) {
        throw new Exception("Erreur BD lors de la génération du bilan : " . $e->getMessage());
    }
    
    return $bilan;
}

/**
 * Génère le compte de résultat pour une année fiscale donnée.
 *
 * @param int $annee_fiscale L'année pour laquelle le compte de résultat doit être généré.
 * @return array Le compte de résultat structuré (Produits, Charges).
 * @throws Exception si une erreur de base de données survient.
 */
function generer_compte_de_resultat(int $annee_fiscale): array
{
    global $pdo;
    $compte_de_resultat = ['produits' => [], 'charges' => []];

    // Requête pour utiliser la colonne Date_Saisie pour le filtre
    $sql = "
        SELECT 
            SUBSTRING(c.Numero_Compte, 1, 1) as classe,
            c.Numero_Compte,
            c.Nom_Compte,
            SUM(CASE WHEN le.Sens = 'D' THEN le.Montant ELSE 0 END) as total_debit,
            SUM(CASE WHEN le.Sens = 'C' THEN le.Montant ELSE 0 END) as total_credit
        FROM Lignes_Ecritures le
        JOIN Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
        JOIN Comptes_compta c ON le.ID_Compte = c.ID_Compte
        WHERE YEAR(e.Date_Saisie) = :annee_fiscale AND SUBSTRING(c.Numero_Compte, 1, 1) IN ('6', '7')
        GROUP BY c.Numero_Compte, c.Nom_Compte
        ORDER BY c.Numero_Compte ASC
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['annee_fiscale' => $annee_fiscale]);
        $soldes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($soldes as $solde) {
            if ($solde['classe'] === '6') {
                $solde_compte = $solde['total_debit'] - $solde['total_credit'];
                if ($solde_compte != 0) {
                    $compte_de_resultat['charges'][] = array_merge($solde, ['solde' => $solde_compte]);
                }
            } else {
                $solde_compte = $solde['total_credit'] - $solde['total_debit'];
                if ($solde_compte != 0) {
                    $compte_de_resultat['produits'][] = array_merge($solde, ['solde' => $solde_compte]);
                }
            }
        }
    } catch (PDOException $e) {
        throw new Exception("Erreur BD lors de la génération du compte de résultat : " . $e->getMessage());
    }

    return $compte_de_resultat;
}