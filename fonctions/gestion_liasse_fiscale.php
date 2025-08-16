<?php
// fonctions/gestion_liasse_fiscale.php

/**
 * Fonctions de génération des données pour la liasse fiscale.
 * Ces fonctions récupèrent et structurent les informations comptables
 * en vue de leur exportation dans des formats réglementaires.
 */

// Inclure la connexion à la base de données et les fonctions de reporting existantes.
// Note : Le fichier gestion_reporting.php peut être nécessaire pour certaines fonctions.
require_once 'database.php';
require_once 'gestion_reporting.php';

/**
 * Génère un tableau complet des données pour la liasse fiscale d'une année donnée.
 * Cette fonction agit comme un orchestrateur, appelant d'autres fonctions pour 
 * récupérer les données du bilan et du compte de résultat.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $anneeFiscale L'année pour laquelle générer la liasse (ex: 2024).
 * @return array Un tableau associatif contenant toutes les données de la liasse.
 * @throws Exception Si les données ne peuvent pas être générées.
 */
function genererDonneesLiasseFiscale(PDO $pdo, int $anneeFiscale): array
{
    // Définir la période fiscale
    $dateDebut = $anneeFiscale . '-01-01';
    $dateFin = $anneeFiscale . '-12-31';

    try {
        // 1. Récupérer les données pour le bilan (Actif et Passif)
        $bilan = getBilanData($pdo, $dateDebut, $dateFin);

        // 2. Récupérer les données pour le compte de résultat (Charges et Produits)
        $compteResultat = getCompteResultatData($pdo, $dateDebut, $dateFin);
        
        // 3. Récupérer les informations de l'entreprise (à adapter selon votre base de données)
        $entrepriseInfo = getCompanyInfo($pdo);

        // 4. Calculer le résultat net
        $resultatNet = array_sum(array_column($compteResultat['produits'], 'SoldeFinal')) - array_sum(array_column($compteResultat['charges'], 'SoldeFinal'));
        
        // Ajouter le résultat net au bilan (capitaux propres)
        // Note: Assurez-vous que cette structure existe avant d'y accéder.
        if (!isset($bilan['passif']['CapitauxPropres'])) {
            $bilan['passif']['CapitauxPropres'] = [];
        }
        $bilan['passif']['CapitauxPropres']['ResultatNet'] = $resultatNet;

        // Retourner la structure complète de la liasse
        return [
            'annee_fiscale' => $anneeFiscale,
            'entreprise' => $entrepriseInfo,
            'bilan' => $bilan,
            'compte_de_resultat' => $compteResultat,
            'date_generation' => date('Y-m-d H:i:s')
        ];

    } catch (Exception $e) {
        // Enregistrer l'erreur pour le débogage et la relancer
        error_log("Erreur lors de la génération des données de la liasse fiscale : " . $e->getMessage());
        throw new Exception("Impossible de générer les données de la liasse fiscale.");
    }
}

/**
 * Récupère les données détaillées pour la section Bilan de la liasse.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param string $dateDebut Date de début de la période.
 * @param string $dateFin Date de fin de la période.
 * @return array Un tableau structuré pour le bilan (Actif et Passif).
 */
function getBilanData(PDO $pdo, string $dateDebut, string $dateFin): array
{
    // Récupérer tous les comptes de classe 1 à 5
    $comptesActif = getComptesByClassePrefix($pdo, ['2', '3', '4', '5']);
    $comptesPassif = getComptesByClassePrefix($pdo, ['1', '4', '5']);

    $actif = [];
    $passif = [];

    // Logique pour l'actif
    foreach ($comptesActif as $compte) {
        $solde = calculerSoldeFinalCompte($pdo, $compte['ID_Compte'], $dateDebut, $dateFin);
        if ($solde > 0) { // Un compte d'actif a un solde débiteur
            $actif[] = [
                'Numero_Compte' => $compte['Numero_Compte'],
                'Nom_Compte' => $compte['Nom_Compte'],
                'SoldeFinal' => $solde
            ];
        }
    }

    // Logique pour le passif
    foreach ($comptesPassif as $compte) {
        $solde = calculerSoldeFinalCompte($pdo, $compte['ID_Compte'], $dateDebut, $dateFin);
        if ($solde < 0) { // Un compte de passif a un solde créditeur (ou négatif)
            $passif[] = [
                'Numero_Compte' => $compte['Numero_Compte'],
                'Nom_Compte' => $compte['Nom_Compte'],
                'SoldeFinal' => abs($solde)
            ];
        }
    }

    return [
        'actif' => $actif,
        'passif' => $passif
    ];
}

/**
 * Récupère les données détaillées pour le Compte de Résultat.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param string $dateDebut Date de début de la période.
 * @param string $dateFin Date de fin de la période.
 * @return array Un tableau structuré pour le compte de résultat (Charges et Produits).
 */
function getCompteResultatData(PDO $pdo, string $dateDebut, string $dateFin): array
{
    // Récupérer les comptes de charges (classe 6) et de produits (classe 7)
    $comptesCharges = getComptesByClassePrefix($pdo, ['6']);
    $comptesProduits = getComptesByClassePrefix($pdo, ['7']);

    $charges = [];
    $produits = [];

    // Logique pour les charges
    foreach ($comptesCharges as $compte) {
        $solde = calculerSoldeFinalCompte($pdo, $compte['ID_Compte'], $dateDebut, $dateFin);
        if ($solde > 0) { // Un compte de charge a un solde débiteur
            $charges[] = [
                'Numero_Compte' => $compte['Numero_Compte'],
                'Nom_Compte' => $compte['Nom_Compte'],
                'SoldeFinal' => $solde
            ];
        }
    }

    // Logique pour les produits
    foreach ($comptesProduits as $compte) {
        $solde = calculerSoldeFinalCompte($pdo, $compte['ID_Compte'], $dateDebut, $dateFin);
        if ($solde < 0) { // Un compte de produit a un solde créditeur
            $produits[] = [
                'Numero_Compte' => $compte['Numero_Compte'],
                'Nom_Compte' => $compte['Nom_Compte'],
                'SoldeFinal' => abs($solde)
            ];
        }
    }

    return [
        'charges' => $charges,
        'produits' => $produits
    ];
}

/**
 * Calcule le solde final d'un compte sur une période donnée.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param int $compteId L'ID du compte.
 * @param string $dateDebut La date de début de la période.
 * @param string $dateFin La date de fin de la période.
 * @return float Le solde final (débit - crédit) du compte.
 */
function calculerSoldeFinalCompte(PDO $pdo, int $compteId, string $dateDebut, string $dateFin): float
{
    // Utiliser une requête simple pour obtenir les totaux de débit et de crédit
    $sql = "
        SELECT 
            SUM(CASE WHEN le.Sens = 'D' THEN le.Montant ELSE 0 END) AS total_debit,
            SUM(CASE WHEN le.Sens = 'C' THEN le.Montant ELSE 0 END) AS total_credit
        FROM Lignes_Ecritures le
        JOIN Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
        WHERE le.ID_Compte = :compte_id
        AND e.Date_Saisie BETWEEN :date_debut AND :date_fin
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':compte_id' => $compteId,
        ':date_debut' => $dateDebut,
        ':date_fin' => $dateFin
    ]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calculer le solde : Débit - Crédit
    $solde = (float)($result['total_debit'] ?? 0) - (float)($result['total_credit'] ?? 0);
    return $solde;
}

/**
 * Récupère les comptes dont le numéro commence par un des préfixes donnés.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param array $prefixes Un tableau de préfixes de classe (ex: ['1', '2', '3']).
 * @return array Un tableau d'objets ou de tableaux de comptes.
 */
function getComptesByClassePrefix(PDO $pdo, array $prefixes): array
{
    // Construire la clause WHERE dynamique
    $placeholders = implode(',', array_fill(0, count($prefixes), '?'));
    
    $sql = "
        SELECT ID_Compte, Numero_Compte, Nom_Compte
        FROM Comptes_compta 
        WHERE SUBSTRING(Numero_Compte, 1, 1) IN ($placeholders)
        ORDER BY Numero_Compte ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($prefixes);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les informations de l'entreprise.
 * Cette fonction est un simple exemple et doit être adaptée à votre table d'informations d'entreprise.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @return array Un tableau associatif des informations de l'entreprise.
 */
function getCompanyInfo(PDO $pdo): array
{
    // Exemple d'une requête hypothétique
    $sql = "SELECT Nom_Entreprise, Siren, Adresse FROM Parametres_Entreprise WHERE ID = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}