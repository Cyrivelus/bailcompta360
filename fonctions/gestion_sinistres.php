<?php
// fonctions/gestion_sinistres.php

/**
 * Récupère le nombre total de sinistres pour la pagination, en tenant compte des filtres.
 * @param PDO $pdo L'objet de connexion PDO.
 * @param string $terme Le terme de recherche.
 * @param string $statut Le statut à filtrer.
 * @return int Le nombre total de sinistres.
 */
function getNombreTotalSinistres(PDO $pdo, string $terme, string $statut): int
{
    $statuts_map = [
        'en_cours' => "('déclaré', 'en cours d''évaluation')",
        'réglé' => "('réglé')",
        'rejeté' => "('rejeté')",
        'tous' => "('déclaré', 'en cours d''évaluation', 'réglé', 'rejeté')"
    ];

    $where_clauses = ["1=1"];
    $params = [];

    if (!empty($terme)) {
        $where_clauses[] = "(s.ID_Sinistre LIKE :terme OR c.nom LIKE :terme OR c.prenoms LIKE :terme OR ca.Numero_Police LIKE :terme)";
        $params[':terme'] = '%' . $terme . '%';
    }

    if (isset($statuts_map[$statut])) {
        $where_clauses[] = "s.Statut_Sinistre IN " . $statuts_map[$statut];
    }

    $sql = "SELECT COUNT(*) FROM sinistres s
            JOIN contrats_assurance ca ON s.ID_Contrat = ca.ID_Contrat
            JOIN clients c ON ca.ID_Client = c.id_client
            WHERE " . implode(" AND ", $where_clauses);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

/**
 * Récupère les sinistres avec recherche et pagination.
 * @param PDO $pdo L'objet de connexion PDO.
 * @param string $terme Le terme de recherche.
 * @param string $statut Le statut à filtrer.
 * @param int $par_page Le nombre d'éléments par page.
 * @param int $offset Le décalage pour la pagination.
 * @return array Un tableau des sinistres trouvés.
 */
function listerSinistresPagine(PDO $pdo, string $terme, string $statut, int $par_page, int $offset): array
{
    $statuts_map = [
        'en_cours' => "('déclaré', 'en cours d''évaluation')",
        'réglé' => "('réglé')",
        'rejeté' => "('rejeté')",
        'tous' => "('déclaré', 'en cours d''évaluation', 'réglé', 'rejeté')"
    ];

    $where_clauses = ["1=1"];
    $params = [];

    if (!empty($terme)) {
        $where_clauses[] = "(s.ID_Sinistre LIKE :terme OR c.nom LIKE :terme OR c.prenoms LIKE :terme OR ca.Numero_Police LIKE :terme)";
        $params[':terme'] = '%' . $terme . '%';
    }

    if (isset($statuts_map[$statut])) {
        $where_clauses[] = "s.Statut_Sinistre IN " . $statuts_map[$statut];
    }
    
    $sql = "SELECT s.*, ca.Numero_Police, c.nom AS Nom_Client, c.prenoms AS Prenoms_Client
            FROM sinistres s
            JOIN contrats_assurance ca ON s.ID_Contrat = ca.ID_Contrat
            JOIN clients c ON ca.ID_Client = c.id_client
            WHERE " . implode(" AND ", $where_clauses) . "
            ORDER BY s.Date_Sinistre DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $par_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    foreach ($params as $key => &$val) {
        $stmt->bindValue($key, $val);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Retourne la classe CSS de badge pour un statut donné.
 * @param string $statut Le statut du sinistre.
 * @return string La classe CSS pour le badge.
 */
function getStatutBadgeColor(string $statut): string
{
    switch ($statut) {
        case 'déclaré':
            return 'secondary';
        case 'en cours d\'évaluation':
            return 'warning';
        case 'réglé':
            return 'success';
        case 'rejeté':
            return 'danger';
        default:
            return 'info';
    }
}

function getSinistresEnCours(PDO $pdo): array
{
    try {
        $sql = "SELECT s.*, ca.Numero_Police, c.nom AS Nom_Client, c.prenoms AS Prenoms_Client
                FROM sinistres s
                JOIN contrats_assurance ca ON s.ID_Contrat = ca.ID_Contrat
                JOIN clients c ON ca.ID_Client = c.id_client
                WHERE s.Statut_Sinistre IN ('déclaré', 'en cours d''évaluation')
                ORDER BY s.Date_Sinistre DESC";
        $stmt = $pdo->query($sql);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        // Enregistrez l'erreur dans les logs pour le débogage
        error_log("Erreur lors de la récupération des sinistres en cours : " . $e->getMessage());
        return [];
    }
}