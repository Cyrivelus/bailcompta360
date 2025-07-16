<?php
// fonctions/gestion_agences.php

/**
 * Ce fichier contient les fonctions de gestion des agences (entités analytiques).
 * Il interagit avec la table [dbo].[AGENCES_SCE].
 */

/**
 * Récupère la liste de toutes les agences (Code, Libellé et N° Compte Comptable)
 * depuis la base de données.
 *
 * @param PDO $pdo L'objet de connexion PDO à la base de données.
 * @return array Un tableau associatif de toutes les agences.
 * Chaque élément du tableau est un tableau associatif représentant une agence,
 * avec les clés 'CodeAgenceSCE', 'LibelleAgenceSCE', 'NoCompteComptable'.
 * Retourne un tableau vide si aucune agence n'est trouvée ou en cas d'erreur.
 */
function getAllAgences(PDO $pdo): array {
    $agences = [];
    // Assurez-vous que le nom de la table et les colonnes correspondent à votre schéma
    $sql = "SELECT CodeAgenceSCE, LibelleAgenceSCE, NoCompteComptable FROM AGENCES_SCE ORDER BY CodeAgenceSCE";

    try {
        $stmt = $pdo->query($sql);
        if ($stmt) {
            // Utilisez PDO::FETCH_ASSOC pour obtenir un tableau associatif par ligne
            $agences = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // En cas d'erreur de base de données, loggez l'erreur
        // Plutôt que de tuer le script, on retourne un tableau vide pour que le script appelant
        // (saisie.php) puisse continuer à s'exécuter, même sans la liste des agences.
        error_log("Database error in getAllAgences: " . $e->getMessage());
        // $agences est déjà initialisé à un tableau vide
    }

    return $agences;
}

// Vous pourriez ajouter d'autres fonctions ici si nécessaire pour la gestion complète
// des agences (ajouter, modifier, supprimer, get by code, etc.), par exemple :

/**
 * Récupère une agence spécifique par son code.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param string $code Le code de l'agence à rechercher.
 * @return array|null Un tableau associatif représentant l'agence, ou null si non trouvée ou erreur.
 */
/*
function getAgenceByCode(PDO $pdo, string $code): ?array {
    $agence = null;
    $sql = "SELECT CodeAgenceSCE, LibelleAgenceSCE, NoCompteComptable FROM [dbo].[AGENCES_SCE] WHERE CodeAgenceSCE = ?";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$code]);
        $agence = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in getAgenceByCode: " . $e->getMessage());
        // $agence est déjà null
    }

    return $agence ?: null; // Retourne null si fetch a échoué ou n'a rien trouvé
}
*/

/**
 * Ajoute une nouvelle agence à la base de données.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param string $code Le code de la nouvelle agence.
 * @param string $libelle Le libellé de la nouvelle agence.
 * @param string|null $noCompte Le numéro de compte comptable (peut être null).
 * @return bool True en cas de succès, false en cas d'échec.
 */
/*
function addAgence(PDO $pdo, string $code, string $libelle, ?string $noCompte): bool {
    $sql = "INSERT INTO [dbo].[AGENCES_SCE] (CodeAgenceSCE, LibelleAgenceSCE, NoCompteComptable) VALUES (?, ?, ?)";

    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$code, $libelle, $noCompte]);
    } catch (PDOException $e) {
        error_log("Database error in addAgence: " . $e->getMessage());
        return false;
    }
}
*/

// etc. pour updateAgence, deleteAgence...

?>