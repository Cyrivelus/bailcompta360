<?php
/**
 * Fonctions de gestion de l'analyse de performance.
 * Ce fichier contient les fonctions pour récupérer les données de performance
 * en comparant les budgets aux dépenses réelles.
 */

require_once 'database.php';

/**
 * Récupère les données de performance financière agrégées.
 *
 * Cette fonction joint les tables 'budget', 'ECR_DEF' et 'Comptes_compta' pour
 * calculer les montants budgétisés et les montants réels, puis les retourne.
 *
 * @param string $search Terme de recherche optionnel.
 * @param string $sort Champ de tri optionnel.
 * @param string $order Ordre de tri ('ASC' ou 'DESC').
 * @return array Tableau d'objets représentant les données de performance.
 */
function getPerformanceData($search = '', $sort = 'Annee', $order = 'DESC') {
    $conn = connexion_bd();
    
    // Liste blanche des champs de tri pour éviter les injections SQL
    $allowedSortFields = ['Annee', 'ID_Compte', 'Nom_Compte', 'Montant_Budgetise', 'Montant_Reel', 'Ecart'];
    $sortField = in_array($sort, $allowedSortFields) ? $sort : 'Annee';
    $sortOrder = ($order === 'ASC') ? 'ASC' : 'DESC';

    // Requête SQL complexe pour joindre et agréger les données
    // Le calcul de l'écart est fait côté PHP pour simplifier la requête SQL
    $sql = "SELECT
                T1.Annee_Budgetaire AS Annee,
                T1.ID_Compte AS ID_Compte,
                T3.Nom_Compte AS Nom_Compte,
                SUM(T1.Montant_Budgetise) AS Montant_Budgetise,
                SUM(COALESCE(T2.Deb, 0)) - SUM(COALESCE(T2.Cre, 0)) AS Montant_Reel
            FROM
                budget AS T1
            LEFT JOIN
                ECR_DEF AS T2 ON T1.ID_Compte = T2.Cpt AND YEAR(T2.Dte) = T1.Annee_Budgetaire
            INNER JOIN
                Comptes_compta AS T3 ON T1.ID_Compte = T3.ID_Compte
            GROUP BY
                T1.Annee_Budgetaire, T1.ID_Compte, T3.Nom_Compte
            ";

    $params = [];
    
    // Clause WHERE pour la recherche sur plusieurs champs
    if (!empty($search)) {
        // Ajout d'une clause HAVING pour filtrer après l'agrégation
        $sql .= " HAVING
            T1.Annee_Budgetaire LIKE ? OR T1.ID_Compte LIKE ? OR T3.Nom_Compte LIKE ?";
        $searchTerm = '%' . $search . '%';
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    // Ajout de la clause ORDER BY
    // Le tri sur l'écart (Ecart) sera géré dans la boucle PHP
    if ($sortField != 'Ecart') {
        $sql .= " ORDER BY " . $sortField . " " . $sortOrder;
    }

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $performanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si le tri est sur l'écart, on le fait côté PHP après le calcul
        if ($sortField == 'Ecart') {
            usort($performanceData, function($a, $b) use ($sortOrder) {
                $ecartA = $a['Montant_Budgetise'] - $a['Montant_Reel'];
                $ecartB = $b['Montant_Budgetise'] - $b['Montant_Reel'];
                if ($ecartA == $ecartB) {
                    return 0;
                }
                return ($sortOrder == 'ASC') ? ($ecartA > $ecartB ? 1 : -1) : ($ecartA < $ecartB ? 1 : -1);
            });
        }
        
        return $performanceData;
    } catch (PDOException $e) {
        error_log("Erreur de récupération des données de performance : " . $e->getMessage());
        return [];
    }
}
