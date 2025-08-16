<?php
// fonctions/gestion_agences.php

/**
 * Ce fichier contient les fonctions de gestion des agences (entités analytiques).
 * Il interagit avec la table [dbo].[AGENCES_SCE].
 *
 * Assurez-vous d'avoir une connexion PDO configurée et disponible via 'database.php'.
 */

require_once 'database.php'; // Inclure le fichier de connexion à la base de données

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
function getAllAgences(PDO $pdo): array
{
    $agences = [];
    // Assurez-vous que le nom de la table et les colonnes correspondent à votre schéma [dbo].[AGENCES_SCE]
    $sql = "SELECT CodeAgenceSCE, LibelleAgenceSCE, NoCompteComptable FROM AGENCES_SCE ORDER BY CodeAgenceSCE";

    try {
        $stmt = $pdo->query($sql);
        if ($stmt) {
            // Utilisez PDO::FETCH_ASSOC pour obtenir un tableau associatif par ligne
            $agences = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // En cas d'erreur de base de données, loggez l'erreur pour le débogage
        error_log("Erreur BD dans getAllAgences: " . $e->getMessage());
        // $agences est déjà initialisé à un tableau vide, donc on le retourne tel quel
    }

    return $agences;
}

/**
 * Récupère une agence spécifique par son code.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param string $codeAgence Le code de l'agence à rechercher (CodeAgenceSCE).
 * @return array|null Un tableau associatif représentant l'agence, ou null si non trouvée ou erreur.
 */
function getAgenceByCode(PDO $pdo, string $codeAgence): ?array
{
    $agence = null;
    $sql = "SELECT CodeAgenceSCE, LibelleAgenceSCE, NoCompteComptable FROM agences_sce WHERE CodeAgenceSCE = ?";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$codeAgence]);
        $agence = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur BD dans getAgenceByCode: " . $e->getMessage());
        // L'agence reste null en cas d'erreur
    }

    return $agence; // Retourne null si fetch a échoué ou n'a rien trouvé
}

/**
 * Ajoute une nouvelle agence à la base de données.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param string $codeAgence Le code unique de la nouvelle agence (varchar(3)).
 * @param string $libelleAgence Le libellé descriptif de l'agence (varchar(15)).
 * @param string|null $noCompteComptable Le numéro de compte comptable associé (varchar(12), peut être null).
 * @return bool True en cas de succès, false en cas d'échec ou si le code existe déjà.
 * @throws Exception Si le code agence est trop long ou en cas d'erreur de base de données.
 */
function addAgence(PDO $pdo, string $codeAgence, string $libelleAgence, ?string $noCompteComptable): bool
{
    // Simple validation de la longueur pour correspondre au schéma
    if (strlen($codeAgence) > 3) {
        throw new Exception("Le code agence ne peut pas dépasser 3 caractères.");
    }
    if (strlen($libelleAgence) > 15) {
        throw new Exception("Le libellé agence ne peut pas dépasser 15 caractères.");
    }
    if ($noCompteComptable !== null && strlen($noCompteComptable) > 12) {
        throw new Exception("Le numéro de compte comptable ne peut pas dépasser 12 caractères.");
    }

    $sql = "INSERT INTO AGENCES_SCE (CodeAgenceSCE, LibelleAgenceSCE, NoCompteComptable) VALUES (?, ?, ?)";

    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$codeAgence, $libelleAgence, $noCompteComptable]);
    } catch (PDOException $e) {
        // Gérer spécifiquement les erreurs de doublons si nécessaire (ex: code 23000 pour MySQL/SQLSTATE)
        if ($e->getCode() === '23000') { // Code d'erreur pour violation de contrainte d'unicité
            error_log("Tentative d'ajout d'une agence avec un CodeAgenceSCE existant: " . $codeAgence);
            throw new Exception("Une agence avec ce code existe déjà.");
        }
        error_log("Erreur BD dans addAgence: " . $e->getMessage());
        return false;
    }
}

/**
 * Met à jour les informations d'une agence existante.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param string $codeAgence Le code de l'agence à mettre à jour.
 * @param string $libelleAgence Le nouveau libellé de l'agence.
 * @param string|null $noCompteComptable Le nouveau numéro de compte comptable.
 * @return bool True en cas de succès, false en cas d'échec.
 * @throws Exception Si le libellé agence est trop long ou en cas d'erreur de base de données.
 */
function updateAgence(PDO $pdo, string $codeAgence, string $libelleAgence, ?string $noCompteComptable): bool
{
    if (strlen($libelleAgence) > 15) {
        throw new Exception("Le libellé agence ne peut pas dépasser 15 caractères.");
    }
    if ($noCompteComptable !== null && strlen($noCompteComptable) > 12) {
        throw new Exception("Le numéro de compte comptable ne peut pas dépasser 12 caractères.");
    }

    $sql = "UPDATE AGENCES_SCE SET LibelleAgenceSCE = ?, NoCompteComptable = ? WHERE CodeAgenceSCE = ?";

    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$libelleAgence, $noCompteComptable, $codeAgence]);
    } catch (PDOException $e) {
        error_log("Erreur BD dans updateAgence: " . $e->getMessage());
        return false;
    }
}

function modifierAgence(PDO $pdo, string $code_agence, string $libelle_agence, string $no_compte_comptable): bool
{
    try {
        $query = "UPDATE agences_sce SET LibelleAgenceSCE = ?, NoCompteComptable = ? WHERE CodeAgenceSCE = ?";
        $stmt = $pdo->prepare($query);
        return $stmt->execute([$libelle_agence, $no_compte_comptable, $code_agence]);
    } catch (PDOException $e) {
        error_log("Erreur PDO dans modifierAgence : " . $e->getMessage());
        return false;
    }
}

/**
 * Supprime une agence de la base de données par son code.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param string $codeAgence Le code de l'agence à supprimer.
 * @return bool True en cas de succès, false en cas d'échec (ex: si des enregistrements dépendants existent).
 * @throws Exception Si l'agence est liée à d'autres données (ex: écritures).
 */
function deleteAgence(PDO $pdo, string $codeAgence): bool
{
    try {
        $pdo->beginTransaction();

        // Vérification des dépendances (exemple: Lignes_Ecritures ou Comptes liés à cette agence)
        // C'est crucial dans un système core banking pour maintenir l'intégrité des données.
        // Adaptez ces vérifications à votre schéma réel.
        $checkDependenciesSql = "SELECT COUNT(*) FROM Lignes_Ecritures WHERE NumeroAgenceSCE = ?"; // Supposons une colonne d'agence
        $stmtCheck = $pdo->prepare($checkDependenciesSql);
        $stmtCheck->execute([$codeAgence]);
        if ($stmtCheck->fetchColumn() > 0) {
            throw new Exception("Impossible de supprimer cette agence. Des écritures ou d'autres données y sont liées.");
        }

        // Si aucune dépendance n'est trouvée, procéder à la suppression
        $sql = "DELETE FROM AGENCES_SCE WHERE CodeAgenceSCE = ?";
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([$codeAgence]);

        if ($success) {
            $pdo->commit();
            return true;
        } else {
            $pdo->rollBack();
            return false;
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erreur BD dans deleteAgence: " . $e->getMessage());
        throw new Exception("Erreur de base de données lors de la suppression de l'agence: " . $e->getMessage());
    } catch (Exception $e) {
        $pdo->rollBack(); // Assurez-vous de faire un rollback même pour les exceptions non-PDO
        error_log("Erreur logique dans deleteAgence: " . $e->getMessage());
        throw $e; // Relaunch l'exception pour que le script appelant puisse la gérer
    }
}
