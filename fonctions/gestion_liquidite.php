<?php
// fonctions/gestion_liquidite.php

/**
 * Récupère les soldes actuels des comptes de trésorerie (banque et caisse).
 *
 * @return array Un tableau associatif avec les soldes des comptes.
 */
function getSoldesActuels(): array {
    global $pdo;
    try {
        // Cette requête suppose que vous avez des codes de journaux pour la trésorerie (ex: BQ pour Banque, CA pour Caisse)
        $sql = "SELECT SUM(Montant_Total) AS solde FROM Ecritures WHERE Cde IN ('BQ', 'CA')";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ['banque_caisse' => (float)($result['solde'] ?? 0.0)];
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getSoldesActuels: " . $e->getMessage());
        return ['banque_caisse' => 0.0];
    }
}

/**
 * Calcule les prévisions de trésorerie pour une période donnée.
 *
 * @param string $dateDebut Date de début de la période (YYYY-MM-DD).
 * @param string $dateFin Date de fin de la période (YYYY-MM-DD).
 * @return array Tableau des prévisions.
 */
function getPrevisionsTresorerie(string $dateDebut, string $dateFin): array {
    global $pdo;
    
    // Solde initial (solde de trésorerie avant la date de début)
    $soldeInitial = getSoldeAnterieurLiquidite($dateDebut);

    // Encaissements prévus (basé sur les créances)
    // Using ID_Journal to identify sales invoices (Vente)
    $sqlEncaissements = "SELECT SUM(Montant_TTC) AS total FROM Factures WHERE ID_Journal = 1 AND Date_Echeance BETWEEN :date_debut AND :date_fin AND Statut_Facture != 'Payée'";
    $stmtEncaissements = $pdo->prepare($sqlEncaissements);
    $stmtEncaissements->execute([':date_debut' => $dateDebut, ':date_fin' => $dateFin]);
    $totalEncaissements = (float)($stmtEncaissements->fetchColumn() ?? 0.0);

    // Décaissements prévus (basé sur les dettes)
    // Using ID_Journal to identify purchase invoices (Achat)
    $sqlDecaissements = "SELECT SUM(Montant_TTC) AS total FROM Factures WHERE ID_Journal = 2 AND Date_Echeance BETWEEN :date_debut AND :date_fin AND Statut_Facture != 'Payée'";
    $stmtDecaissements = $pdo->prepare($sqlDecaissements);
    $stmtDecaissements->execute([':date_debut' => $dateDebut, ':date_fin' => $dateFin]);
    $totalDecaissements = (float)($stmtDecaissements->fetchColumn() ?? 0.0);

    // Solde final
    $soldeFinal = $soldeInitial + $totalEncaissements - $totalDecaissements;

    return [
        'solde_initial' => $soldeInitial,
        'total_encaissements' => $totalEncaissements,
        'total_decaissements' => $totalDecaissements,
        'solde_final' => $soldeFinal
    ];
}


/**
 * Récupère les créances clients à venir.
 *
 * @param string $dateFin Date de fin de l'échéance (YYYY-MM-DD).
 * @return array Liste des créances.
 */
function getCreancesClient(string $dateFin): array {
    global $pdo;
    try {
        $sql = "SELECT Date_Echeance, Commentaire AS Description, Montant_TTC FROM Factures WHERE ID_Journal = 1 AND Date_Echeance <= :date_fin AND Statut_Facture != 'Payée' ORDER BY Date_Echeance ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':date_fin' => $dateFin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getCreancesClient: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les dettes fournisseurs à venir.
 *
 * @param string $dateFin Date de fin de l'échéance (YYYY-MM-DD).
 * @return array Liste des dettes.
 */
function getDettesFournisseur(string $dateFin): array {
    global $pdo;
    try {
        $sql = "SELECT Date_Echeance, Commentaire AS Description, Montant_TTC FROM Factures WHERE ID_Journal = 2 AND Date_Echeance <= :date_fin AND Statut_Facture != 'Payée' ORDER BY Date_Echeance ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':date_fin' => $dateFin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getDettesFournisseur: " . $e->getMessage());
        return [];
    }
}

/**
 * Calcule le solde de trésorerie avant une date donnée.
 *
 * @param string $dateLimite La date limite (YYYY-MM-DD).
 * @return float Le solde calculé.
 */
function getSoldeAnterieurLiquidite(string $dateLimite): float {
    global $pdo;
    try {
        // Jointure pour s'assurer que seules les écritures de trésorerie sont prises en compte
        $sql = "SELECT SUM(Montant_Total) AS solde FROM Ecritures WHERE Cde IN ('BQ', 'CA') AND Date_Saisie < :date_limite";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':date_limite' => $dateLimite]);
        $result = $stmt->fetchColumn();
        return (float)($result ?? 0.0);
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getSoldeAnterieurLiquidite: " . $e->getMessage());
        return 0.0;
    }
}