<?php
// fonctions/gestion_comptes.php

require_once("database.php"); // Inclure le fichier de connexion à la base de données

/**
 * Récupère la liste de tous les comptes comptables avec pagination.
 *
 * @param PDO   $pdo         L'objet de connexion à la base de données.
 * @param int   $page        Le numéro de la page à afficher (par défaut 1).
 * @param int   $perPage     Le nombre de comptes à afficher par page (par défaut 10).
 * @return array Un tableau associatif contenant :
 * - 'comptes'      : Un tableau des comptes comptables (tableaux associatifs).
 * - 'total'        : Le nombre total de comptes dans la base de données.
 * - 'totalPages'   : Le nombre total de pages.
 */
 
 function getAllComptesPLN($pdo) {
    try {
        $stmt = $pdo->query("SELECT Cpt, Lib FROM pln ORDER BY Cpt");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Enregistrez l'erreur ou gérez-la de manière appropriée
        error_log("Erreur lors de la récupération de tous les comptes (pln): " . $e->getMessage());
        return [];
    }
}



function getCompteIdByBankName(PDO $pdo, string $bankName) {
    $stmt = $pdo->prepare("SELECT ID_Compte FROM Comptes_compta WHERE Nom_Compte = :bankName AND Type_Compte = 'Banque'");
    $stmt->execute([':bankName' => $bankName]);
    $result = $stmt->fetchColumn();
    return $result ? (int)$result : false;
}


function getCompteIdByNumero(PDO $pdo, string $numeroCompte): ?int
{
    try {
        $sql = "SELECT ID_Compte FROM comptes_compta WHERE Numero_Compte = :numero_compte";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':numero_compte' => $numeroCompte]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return (int)$result['ID_Compte'];
        } else {
            // Log that the account number was not found
            error_log("getCompteIdByNumero: Compte avec numéro '{$numeroCompte}' non trouvé.");
            return null;
        }
    } catch (PDOException $e) {
        // Log the database error
        error_log("Erreur PDO dans getCompteIdByNumero pour le numéro '{$numeroCompte}': " . $e->getMessage());
        return null;
    }
}





function getCompteIdByNom(PDO $pdo, string $nomCompte): ?int
{
    try {
        $sql = "SELECT ID_Compte FROM comptes_compta WHERE Nom_Compte = :nom_compte";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':nom_compte' => $nomCompte]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return (int)$result['ID_Compte'];
        } else {
            // Log that the account name was not found
            error_log("getCompteIdByNom: Compte avec nom '{$nomCompte}' non trouvé.");
            return null;
        }
    } catch (PDOException $e) {
        // Log the database error
        error_log("Erreur PDO dans getCompteIdByNom pour le nom '{$nomCompte}': " . $e->getMessage());
        return null;
    }
}

if (!function_exists('getIdCompteFournisseurParFacture')) {
     /**
      * Récupère l'ID du compte comptable fournisseur pour une facture.
      * !!! IMPORTANT : Adaptez cette logique à votre base de données !!!
      * L'exemple suppose l'utilisation d'un compte collectif fournisseur par numéro de compte.
      *
      * @param PDO $pdo
      * @param int $idFacture L'ID de la facture (utilisé ici principalement pour le logging d'erreur).
      * @return int|null L'ID du compte fournisseur, ou null si non trouvé.
      */
     function getIdCompteFournisseurParFacture($pdo, $idFacture) {
         // --- !!! LOGIQUE À ADAPTER À VOTRE STRUCTURE DE BASE DE DONNÉES !!! ---
         // Option 1 (simplifiée) : Compte collectif par numéro connu
         $numeroCompteFournisseur = '401000'; // <-- REMPLACEZ par votre numéro de compte Fournisseur collectif réel
         $sql = "SELECT ID_Compte FROM comptes_compta WHERE Numero_Compte = :numeroCompte";
         $stmt = $pdo->prepare($sql);
         $stmt->execute([':numeroCompte' => $numeroCompteFournisseur]);
         // --- Fin Option 1 ---

         // --- Option 2 (plus avancée si vous avez une table Fournisseurs liée) ---
         /*
         // Cet exemple suppose que :
         // 1. La table Factures a une colonne ID_Fournisseur
         // 2. Il existe une table Fournisseurs
         // 3. La table Fournisseurs a une colonne ID_Compte_Fournisseur qui pointe vers Comptes_compta(ID_Compte)
         $sql = "SELECT c.ID_Compte
                 FROM Factures f
                 JOIN Fournisseurs fs ON f.ID_Fournisseur = fs.ID_Fournisseur -- DÉCOMMENTER ET ADAPTER si vous avez une table Fournisseurs liée
                 JOIN Comptes_compta c ON fs.ID_Compte_Fournisseur = c.ID_Compte -- DÉCOMMENTER ET ADAPTER si Fournisseurs est lié à Comptes_compta
                 WHERE f.ID_Facture = :idFacture"; // Adapter la condition de jointure si nécessaire
         $stmt = $pdo->prepare($sql);
         $stmt->execute([':idFacture' => $idFacture]);
         */
         // --- Fin Option 2 ---


         $idCompte = $stmt->fetchColumn();

         if ($idCompte) {
             return (int)$idCompte; // Retourne l'ID casté en int
         } else {
             // Loguer l'erreur ou lancer une exception
             error_log("ERREUR: Compte Fournisseur non trouvé pour la facture ID " . $idFacture . " (Numéro de compte recherché: " . ($numeroCompteFournisseur ?? 'N/A') . ")");
             // Il est souvent préférable de lancer une exception ici pour que le bloc try/catch
             // dans le script appelant puisse gérer l'erreur et annuler la transaction.
             // throw new Exception("Compte Fournisseur non configuré ou introuvable.");
             return null; // Retourne null si non trouvé
         }
     }
}

if (!function_exists('getIdCompteTresorerieParDefaut')) {
     /**
      * Récupère l'ID du compte comptable de trésorerie par défaut pour les paiements.
      * !!! IMPORTANT : Adaptez cette logique à votre base de données !!!
      *
      * @param PDO $pdo
      * @return int|null L'ID du compte de trésorerie, ou null si non trouvé.
      */
     function getIdCompteTresorerieParDefaut($pdo) {
         // --- !!! LOGIQUE À ADAPTER À VOTRE STRUCTURE DE BASE DE DONNÉES !!! ---
         // Recherchez l'ID de votre compte Banque ou Caisse par défaut.
         $numeroCompteTresorerie = '512000'; // <-- REMPLACEZ par votre numéro de compte Trésorerie par défaut réel

         $stmt = $pdo->prepare("SELECT ID_Compte FROM Comptes_compta WHERE Numero_Compte = :numeroCompte");
         $stmt->execute([':numeroCompte' => $numeroCompteTresorerie]);
         $idCompte = $stmt->fetchColumn();

         if ($idCompte) {
             return (int)$idCompte; // Retourne l'ID casté en int
         } else {
             // Loguer l'erreur ou lancer une exception
             error_log("ERREUR: Compte Trésorerie avec le numéro " . $numeroCompteTresorerie . " non trouvé dans la table Comptes_compta.");
              // throw new Exception("Compte Trésorerie non configuré.");
             return null; // Retourne null si non trouvé
         }
         // --- Fin Logique à adapter ---
     }
}


function getAllComptesComptac(PDO $pdo): array {
    $comptes = [];
    $sql = "SELECT ID_Compte, Numero_Compte AS Cpt, Nom_Compte AS Lib 
            FROM Comptes_compta 
            ORDER BY Numero_Compte ASC";

    try {
        $stmt = $pdo->query($sql);
        if ($stmt) {
            $comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Database error in getAllComptesCompta: " . $e->getMessage());
    }

    return $comptes;
}

function getAllComptesCompta(PDO $pdo): array
{
     try {
        $sql = "SELECT ID_Compte, Numero_Compte, Nom_Compte FROM Comptes_compta ORDER BY Numero_Compte";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getAllComptesCompta: " . $e->getMessage());
        return [];
    }
}

function getComptesComptaByPrefix(PDO $pdo, string $prefix): array
{
    try {
        $sql = "SELECT ID_Compte, Numero_Compte, Nom_Compte FROM Comptes_compta WHERE Numero_Compte LIKE :prefix ORDER BY Numero_Compte";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':prefix' => $prefix . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Retourne un tableau de tous les résultats
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getComptesComptaByPrefix: " . $e->getMessage());
        // Gérer l'erreur - pour l'affichage, on retourne un tableau vide
        return [];
    }
}

function getComptesPLNByPrefix($pdo, $prefix) {
    try {
        $stmt = $pdo->prepare("SELECT Cpt, Lib FROM pln WHERE Cpt LIKE :prefix ORDER BY Cpt");
        $stmt->execute([':prefix' => $prefix . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des comptes (pln) avec préfixe " . $prefix . ": " . $e->getMessage());
        return [];
    }
}
 
function getJournaux(PDO $pdo): array|false
{
    try {
        // Préparer la requête SQL pour sélectionner les journaux comptables.
        // Utilisation de Cde et Lib, correspondant à la structure de la table JAL.
        $sql = "SELECT Cde AS CdeJournal, Lib AS LibJournal FROM JAL ORDER BY Cde";
        $stmt = $pdo->prepare($sql);

        // Exécuter la requête préparée.
        $stmt->execute();

        // Récupérer tous les résultats sous forme de tableau associatif.
        $journaux = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Retourner le tableau des journaux.
        return $journaux;
    } catch (PDOException $e) {
        // En cas d'erreur PDO, logger l'erreur et retourner false.
        error_log("Erreur PDO lors de la récupération des journaux : " . $e->getMessage());
        return false; // Important de retourner false pour indiquer l'échec
    }
}


 
 function getComptesDeCharge(PDO $pdo, string $typeFiltre = 'Charge'): array
{
    // --- Configuration Cruciale ---
    // Assurez-vous que la valeur ci-dessous ('Charge' par défaut) correspond
    // EXACTEMENT à la chaîne de caractères utilisée dans votre colonne [Type_Compte]
    // pour désigner les comptes de charge. Adaptez si nécessaire.
    $valeurTypeCompteCharge = $typeFiltre;

    // Requête SQL pour sélectionner le numéro et le nom des comptes
    // correspondant au type "charge"
    // Utilisation des alias pour la clarté
    $sql = "SELECT
                cc.Numero_Compte,
                cc.Nom_Compte
            FROM
                Comptes_compta AS cc
            WHERE
                cc.Type_Compte = :typeCompte
            ORDER BY
                cc.Numero_Compte ASC"; // Tri par numéro de compte

    $comptesFormatte = []; // Initialisation du tableau qui sera retourné

    try {
        $stmt = $pdo->prepare($sql);

        // Lier le paramètre :typeCompte pour la sécurité et la flexibilité
        $stmt->bindParam(':typeCompte', $valeurTypeCompteCharge, PDO::PARAM_STR);

        $stmt->execute();

        // Récupérer tous les résultats
        // PDO::FETCH_ASSOC retourne chaque ligne comme un tableau associatif ['NomColonne' => valeur]
        $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Transformer les résultats pour correspondre au format attendu ['Cpt' => ..., 'Lib' => ...]
        foreach ($resultats as $ligne) {
            // Vérifier que les clés attendues sont présentes avant de les utiliser
            if (isset($ligne['Numero_Compte']) && isset($ligne['Nom_Compte'])) {
                 $comptesFormatte[] = [
                    'Cpt' => $ligne['Numero_Compte'], // Mappe Numero_Compte vers 'Cpt'
                    'Lib' => $ligne['Nom_Compte']     // Mappe Nom_Compte vers 'Lib'
                ];
            } else {
                 // Optionnel : logguer si une ligne a des colonnes manquantes
                 error_log("Attention dans getComptesDeCharge: Colonne Numero_Compte ou Nom_Compte manquante pour une ligne.");
            }
        }

    } catch (PDOException $e) {
        // En cas d'erreur (ex: table non trouvée, problème de connexion, erreur SQL)
        error_log("Erreur PDO dans getComptesDeCharge() pour le type '{$valeurTypeCompteCharge}': " . $e->getMessage());
        // Retourner un tableau vide pour éviter une erreur fatale dans le code appelant (ex: foreach)
        return [];
    }

    // Retourner le tableau formaté (peut être vide si aucun compte trouvé)
    return $comptesFormatte;
}

function getComptesDeTVA(PDO $pdo, string $prefixe = '445'): array
{
    // --- Configuration ---
    // Préfixe standard pour les comptes de TVA dans le plan comptable français.
    // Adaptez la valeur par défaut ('445') si votre plan comptable utilise une autre racine.
    $prefixeRecherche = $prefixe . '%'; // Ajoute le caractère wildcard pour la recherche SQL LIKE

    // Requête SQL pour sélectionner le numéro et le nom des comptes
    // dont le numéro commence par le préfixe spécifié.
    $sql = "SELECT
                cc.Numero_Compte,
                cc.Nom_Compte
            FROM
                Comptes_compta AS cc
            WHERE
                cc.Numero_Compte LIKE :prefixe
            ORDER BY
                cc.Numero_Compte ASC"; // Tri par numéro de compte

    $comptesFormatte = []; // Initialisation du tableau qui sera retourné

    try {
        $stmt = $pdo->prepare($sql);

        // Lier le paramètre :prefixe pour la sécurité
        $stmt->bindParam(':prefixe', $prefixeRecherche, PDO::PARAM_STR);

        $stmt->execute();

        // Récupérer tous les résultats
        $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Transformer les résultats pour correspondre au format attendu ['Cpt' => ..., 'Lib' => ...]
        foreach ($resultats as $ligne) {
             // Vérifier que les clés attendues sont présentes
            if (isset($ligne['Numero_Compte']) && isset($ligne['Nom_Compte'])) {
                $comptesFormatte[] = [
                    'Cpt' => $ligne['Numero_Compte'], // Mappe Numero_Compte vers 'Cpt'
                    'Lib' => $ligne['Nom_Compte']     // Mappe Nom_Compte vers 'Lib'
                ];
             } else {
                 error_log("Attention dans getComptesDeTVA: Colonne Numero_Compte ou Nom_Compte manquante pour une ligne.");
             }
        }

    } catch (PDOException $e) {
        // En cas d'erreur (ex: table non trouvée, problème de connexion, erreur SQL)
        error_log("Erreur PDO dans getComptesDeTVA() pour le préfixe '{$prefixeRecherche}': " . $e->getMessage());
        // Retourner un tableau vide pour éviter une erreur fatale dans le code appelant
        return [];
    }

    // Retourner le tableau formaté (peut être vide si aucun compte trouvé)
    return $comptesFormatte;
}


 
 function verifierCompteExisteDansComptesCompta(PDO $pdo, string $numeroCompte): bool
{
    // Validation de l'entrée
    $compteNettoye = trim($numeroCompte);
    if (empty($compteNettoye)) {
        error_log("Tentative de vérification d'un compte vide dans verifierCompteExisteDansComptesCompta.");
        return false;
    }

    // Requête SQL pour compter les occurrences
    $sql = "SELECT COUNT(*) FROM Comptes_compta WHERE Numero_Compte = :numeroCompte";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':numeroCompte', $compteNettoye, PDO::PARAM_STR);
        $stmt->execute();
        $count = (int) $stmt->fetchColumn();
        return ($count > 0);

    } catch (PDOException $e) {
        // Log de l'erreur et retour false
        error_log("Erreur PDO dans verifierCompteExisteDansComptesCompta() pour le compte '{$compteNettoye}': " . $e->getMessage());
        return false;
    }
}


function genererLignesEcritures(
    float $montantHT,
    float $montantTVA,
    float $montantTTC,
    string $compteFournisseur,
    string $compteCharge,
    string $compteTVA // Peut être une chaîne vide si la TVA est nulle
): array {

    // Initialise le tableau qui contiendra les lignes d'écriture
    $lignesEcriture = [];

    // Nettoyer les numéros de compte (enlever les espaces superflus)
    $compteCharge = trim($compteCharge);
    $compteTVA = trim($compteTVA);
    $compteFournisseur = trim($compteFournisseur);

    // --- Ligne de débit pour le compte de charge ---
    // Condition : Montant HT positif ET compte de charge fourni
    if ($montantHT > 0 && !empty($compteCharge)) {
        $lignesEcriture[] = [
            'compte' => $compteCharge,
            'montant' => round($montantHT, 2), // Arrondir à 2 décimales
            'sens' => 'D'                      // D = Débit
        ];
    }

    // --- Ligne de débit pour le compte de TVA ---
    // Condition : Montant TVA positif ET compte de TVA fourni
    if ($montantTVA > 0 && !empty($compteTVA)) {
        $lignesEcriture[] = [
            'compte' => $compteTVA,
            'montant' => round($montantTVA, 2), // Arrondir à 2 décimales
            'sens' => 'D'                       // D = Débit
        ];
    }
    // Note: Si $montantTVA = 0, cette ligne ne sera pas ajoutée,
    // même si un compte TVA était sélectionné (ce qui est correct).

    // --- Ligne de crédit pour le compte fournisseur ---
    // Condition : Montant TTC positif ET compte fournisseur fourni
    if ($montantTTC > 0 && !empty($compteFournisseur)) {
        $lignesEcriture[] = [
            'compte' => $compteFournisseur,
            'montant' => round($montantTTC, 2), // Arrondir à 2 décimales
            'sens' => 'C'                       // C = Crédit
        ];
    }

    // Retourne le tableau des lignes générées (peut être vide si TTC=0 par exemple)
    return $lignesEcriture;
}

// N'oubliez pas de conserver aussi la fonction verifierCompteExisteDansPLN !
/**
 * Vérifie si un compte existe dans le plan comptable (table PLN).
 */
function verifierCompteExisteDansPLN(PDO $pdo, string $compte): bool
{
    $compteNettoye = trim($compte);
     if (empty($compteNettoye)) {
        error_log("Tentative de vérification d'un compte PLN vide.");
        return false;
    }
    $sql = "SELECT COUNT(*) FROM pln WHERE Cpt = :compte"; // Vérifie PLN.Cpt
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':compte', $compteNettoye, PDO::PARAM_STR);
        $stmt->execute();
        $count = (int) $stmt->fetchColumn();
        return ($count > 0);
    } catch (PDOException $e) {
        error_log("Erreur DB (verifierCompteExisteDansPLN) pour compte pln '$compteNettoye': " . $e->getMessage());
        return false;
    }
}



 
 function getListeComptes(PDO $pdo): array {
    try {
        // --- CORRECTION : Utilisation de Comptes_compta et ajout Type_Compte ---
        $sql = "SELECT ID_Compte, Numero_Compte, Nom_Compte, Type_Compte FROM Comptes_compta ORDER BY Numero_Compte";
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute()) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Lever une exception avec les détails de l'erreur SQL
            throw new Exception("Erreur lors de la récupération des comptes : " . implode(" - ", $stmt->errorInfo()));
        }
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getListeComptes: " . $e->getMessage());
        throw new Exception("Erreur de base de données lors de la récupération des comptes.");
    } catch (Exception $e) {
        error_log("Erreur générale dans getListeComptes: " . $e->getMessage());
        // Relancer l'exception ou retourner une erreur gérée
        throw $e;
    }
}
 




function getCompteParNumero(PDO $pdo, string $numeroCompte): array|bool
{
    try {
        $sql = "SELECT ID_Compte, Numero_Compte, Nom_Compte, Type_Compte FROM Comptes_compta WHERE Numero_Compte = :numero_compte";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':numero_compte', $numeroCompte);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false; // Retourne false si non trouvé
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getCompteParNumero: " . $e->getMessage());
        // Gérer l'erreur comme approprié, potentiellement relancer une exception
        // ou retourner false pour indiquer un échec de recherche dû à une erreur DB.
        return false;
    }
	


}


// fonctions/gestion_comptes.php

function getComptesWithBalance(PDO $pdo, string $searchTerm = ''): array
{
    $sql = "SELECT c.*, 
            COALESCE(SUM(CASE WHEN le.Sens = 'D' THEN le.Montant ELSE 0 END), 0) as total_debit,
            COALESCE(SUM(CASE WHEN le.Sens = 'C' THEN le.Montant ELSE 0 END), 0) as total_credit
            FROM Comptes_compta c
            LEFT JOIN Lignes_Ecritures le ON c.ID_Compte = le.ID_Compte
            LEFT JOIN Ecritures e ON le.ID_Ecriture = e.ID_Ecriture";
    
    if ($searchTerm !== '') {
        $sql .= " WHERE c.Numero_Compte LIKE :search OR c.Nom_Compte LIKE :search";
    }
    
    $sql .= " GROUP BY c.ID_Compte, c.Numero_Compte, c.Nom_Compte, c.Type_Compte
              ORDER BY c.Numero_Compte";
    
    $stmt = $pdo->prepare($sql);
    
    if ($searchTerm !== '') {
        $stmt->execute(['search' => '%'.$searchTerm.'%']);
    } else {
        $stmt->execute();
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function getCompteDetails(PDO $pdo, int $compteId) {
    $sql = "SELECT * FROM Comptes_compta WHERE ID_Compte = :compteId";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['compteId' => $compteId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getComptes($pdo, $searchTerm = '') {
    // Base SQL query to select account details
    $sql = "SELECT ID_Compte, Numero_Compte, Nom_Compte, Type_Compte FROM Comptes_compta";
    $params = array(); // Array to hold parameters for the prepared statement

    // Check if a search term is provided
    if ($searchTerm) {
        // Add WHERE clause to filter by account number or name
        // Using LIKE with wildcards (%) for flexible searching
        // Casting Numero_Compte to NVARCHAR to ensure string comparison works correctly
        $sql .= " WHERE CAST(Numero_Compte AS NVARCHAR(255)) LIKE ? OR Nom_Compte LIKE ?";
        $searchTermWildcard = '%' . (string)$searchTerm . '%'; // Add wildcards and ensure search term is a string
        $params = array($searchTermWildcard, $searchTermWildcard); // Bind parameters for Numero_Compte and Nom_Compte
    }

    // Add ORDER BY clause to sort the results by account number
    $sql .= " ORDER BY Numero_Compte";

    try {
        // Prepare the SQL query using the PDO connection
        $stmt = $pdo->prepare($sql);
        // Execute the prepared statement with the bound parameters
        $stmt->execute($params);

        // Fetch all results as an associative array
        $comptes = $stmt->fetchAll(PDO::FETCH_ASSOC); // Explicitly fetch results as associative arrays
        return $comptes; // Return the fetched accounts
    } catch (PDOException $e) {
        // Log any database errors to the server's error log for debugging
        error_log("Erreur DB lors de la récupération des comptes: " . $e->getMessage());
        // In a production environment, you might return false or null, or throw a custom exception
        return []; // Return an empty array in case of a database error
    }
}

/**
 * Fetches a list of entries (lignes d'écriture) for a specific account using PDO.
 * Joins the Ecritures and Lignes_Ecriture tables and filters by the provided account ID.
 * Calculates Debit and Credit based on the Montant and Sens columns from Lignes_Ecriture.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $compteId The ID of the account to fetch entries for.
 * @return array An array of entry line rows, including calculated Debit and Credit.
 */
function getEcrituresForCompte($pdo, $compteId) {
    // SQL query to join Ecritures and Lignes_Ecriture tables and filter by account ID
    // Selects relevant fields from both tables, including Montant and Sens for calculation
    $sql = "SELECT
                e.ID_Ecriture,
                e.Date_Saisie,
                e.Description AS Description_Ecriture, -- Description from the main entry
                e.Numero_Piece,
                le.Libelle_Ligne, -- Libelle from the specific line
                le.Montant,      -- Montant from the line
                le.Sens          -- Sens (Debit/Credit) from the line
            FROM
                Lignes_Ecritures le -- Assuming this table links entries to accounts and amounts
            JOIN
                Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
            WHERE
                le.ID_Compte = ? -- Filter by the provided account ID using a placeholder
            ORDER BY
                e.Date_Saisie DESC, e.ID_Ecriture, le.ID_Ligne"; // Order entries by date (desc), then entry ID, then line ID

    $params = array($compteId); // Array containing the account ID parameter for the WHERE clause

    try {
        // Prepare the SQL query using the PDO connection
        $stmt = $pdo->prepare($sql);
        // Execute the prepared statement with the account ID parameter
        $stmt->execute($params);

        $ecritures = []; // Initialize an empty array to store fetched and processed entries
         // Fetch results row by row
         while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { // Fetch each row as an associative array
            // Format Date_Saisie if it's a DateTime object (common with sqlsrv PDO driver)
            // Check if the key exists and the value is a DateTime object before formatting
            if (isset($row['Date_Saisie']) && $row['Date_Saisie'] instanceof DateTime) {
                $row['Date_Saisie'] = $row['Date_Saisie']->format('d/m/Y'); // Format date as DD/MM/YYYY for display
            } else {
                $row['Date_Saisie'] = 'N/A'; // Assign 'N/A' if date is null, not set, or not a DateTime object
            }

            // Calculate Debit and Credit fields based on Montant and Sens
            // This makes it easier to display these values in the HTML table
            $row['Debit'] = ($row['Sens'] === 'D') ? $row['Montant'] : 0; // Assign Montant to Debit if Sens is 'D', otherwise 0
            $row['Credit'] = ($row['Sens'] === 'C') ? $row['Montant'] : 0; // Assign Montant to Credit if Sens is 'C', otherwise 0

            $ecritures[] = $row; // Add the processed row to the entries array
        }

        return $ecritures; // Return the array of fetched and processed entries
    } catch (PDOException $e) {
        // Log any database errors during entry fetching to the server's error log
        error_log("Erreur DB lors de la récupération des écritures: " . $e->getMessage());
        // In a production environment, handle errors gracefully, e.g., return empty array
        return []; // Return an empty array in case of a database error
    }
}
/**
 * Récupère les détails d'un compte comptable spécifique.
 *
 * @param PDO $pdo L'objet de connexion à la base de données.
 * @param int $id L'ID du compte à récupérer.
 * @return array|bool Un tableau associatif contenant les détails du compte,
 * ou false si le compte n'est pas trouvé.
 */

function getDetailsCompte(PDO $pdo, int $id): array|false
{
    $sql = "SELECT ID_Compte, Numero_Compte, Nom_Compte, Type_Compte 
            FROM Comptes_compta 
            WHERE ID_Compte = :id";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;

    } catch (PDOException $e) {
        throw new Exception("Erreur lors de la récupération du compte #$id : " . $e->getMessage());
    }
}

function getComptesFournisseurs(PDO $pdo): array
{
    $comptesFournisseurs = [];

    try {
        // Préfixes possibles des comptes fournisseurs
        $prefixesCompteFournisseur = ['40', '40'];
        
        // Requête SQL pour sélectionner les comptes fournisseurs dans la table PLN
        $sql = "SELECT Cpt, Lib FROM pln WHERE Cpt LIKE :prefixe1 OR Cpt LIKE :prefixe2";
        $stmt = $pdo->prepare($sql);

        // Lie les paramètres
        $stmt->bindValue(':prefixe1', $prefixesCompteFournisseur[0] . '%', PDO::PARAM_STR);
        $stmt->bindValue(':prefixe2', $prefixesCompteFournisseur[1] . '%', PDO::PARAM_STR);

        // Exécute la requête
        $stmt->execute();

        // Récupère les résultats
        $comptesFournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Gestion des erreurs : log et rethrow
        error_log("Erreur lors de la récupération des comptes fournisseurs depuis PLN : " . $e->getMessage());
        throw $e;
    }

    return $comptesFournisseurs;
}

function verifierCompteFournisseur(PDO $pdo, string $compte): bool
{
    try {
        // Préfixes possibles des comptes fournisseurs
        $prefixesCompteFournisseur = ['40'];

        // Vérifie si le compte commence par un des bons préfixes et existe dans la table PLN
        $sql = "SELECT COUNT(*) FROM pln WHERE Cpt = :compte AND (Cpt LIKE :prefixe1 OR Cpt LIKE :prefixe2)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':compte', $compte, PDO::PARAM_STR);
        $stmt->bindValue(':prefixe1', $prefixesCompteFournisseur[0] . '%', PDO::PARAM_STR);
        $stmt->bindValue(':prefixe2', $prefixesCompteFournisseur[1] . '%', PDO::PARAM_STR);
        $stmt->execute();
        $count = $stmt->fetchColumn();

        // Retourne true si le compte existe et a l'un des bons préfixes, false sinon
        return ($count > 0);
    } catch (PDOException $e) {
        // Gestion des erreurs : log et rethrow
        error_log("Erreur lors de la vérification du compte fournisseur : " . $e->getMessage());
        throw $e;
    }
}

/**
 * Ajoute un nouveau compte comptable.
 *
 * @param PDO   $pdo         L'objet de connexion à la base de données.
 * @param array $data        Un tableau associatif contenant les informations du compte :
 * - 'numero_compte' : Le numéro du compte.
 * - 'nom_compte'      : Le nom du compte.
 * - 'type_compte'     : Le type du compte (par exemple, 'Actif', 'Passif', 'Charge', 'Produit').
 * @return bool True en cas de succès, false en cas d'erreur (par exemple, numéro de compte déjà existant).
 */
function ajouterCompte(PDO $pdo, array $data): bool
{
    // Vérifier si le numéro de compte existe déjà
    if (getCompteParNumero($pdo, $data['numero_compte'])) {
        return false; // Le numéro de compte existe déjà
    }

    $sql = "INSERT INTO Comptes_compta (Numero_Compte, Nom_Compte, Type_Compte) VALUES (:numero_compte, :nom_compte, :type_compte)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':numero_compte', $data['numero_compte']);
    $stmt->bindParam(':nom_compte', $data['nom_compte']);
    $stmt->bindParam(':type_compte', $data['type_compte']);
    return $stmt->execute();
}

/**
 * Modifie un compte comptable existant.
 *
 * @param PDO   $pdo         L'objet de connexion à la base de données.
 * @param int   $id          L'ID du compte à modifier.
 * @param array $data        Un tableau associatif contenant les nouvelles informations du compte :
 * - 'numero_compte' : Le nouveau numéro du compte.
 * - 'nom_compte'      : Le nouveau nom du compte.
 * - 'type_compte'     : Le nouveau type du compte.
 * @return bool True en cas de succès, false en cas d'erreur (par exemple, numéro de compte déjà existant pour un autre ID).
 */
function modifierCompte(PDO $pdo, int $id, array $data): bool
{
    // Vérifier si le nouveau numéro de compte existe déjà pour un autre ID
    $compteExistant = getCompteParNumero($pdo, $data['numero_compte']);
    if ($compteExistant && $compteExistant['ID_Compte'] != $id) {
        return false; // Le numéro de compte existe déjà pour un autre compte
    }

    $sql = "UPDATE Comptes_compta SET Numero_Compte = :numero_compte, Nom_Compte = :nom_compte, Type_Compte = :type_compte WHERE ID_Compte = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':numero_compte', $data['numero_compte']);
    $stmt->bindParam(':nom_compte', $data['nom_compte']);
    $stmt->bindParam(':type_compte', $data['type_compte']);
    return $stmt->execute();
}

/**
 * Supprime un compte comptable.
 *
 * @param PDO $pdo L'objet de connexion à la base de données.
 * @param int $id L'ID du compte à supprimer.
 * @return bool True en cas de succès, false en cas d'erreur (par exemple, si le compte est référencé dans des écritures).
 */
function supprimerCompte(PDO $pdo, int $id): bool
{
    // Vérifier si le compte est utilisé dans des lignes d'écriture
    $sqlCheckEcritures = "SELECT COUNT(*) FROM Lignes_Ecritures WHERE ID_Compte = :id";
    $stmtCheckEcritures = $pdo->prepare($sqlCheckEcritures);
    $stmtCheckEcritures->bindParam(':id', $id, PDO::PARAM_INT);
    $stmtCheckEcritures->execute();
    if ($stmtCheckEcritures->fetchColumn() > 0) {
        return false; // Le compte est référencé dans des écritures
    }

    $sql = "DELETE FROM Comptes_compta WHERE ID_Compte = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    return $stmt->execute();
}
function getTousLesComptesPLN(PDO $pdo): array {
    try {
        // Sélectionne Cpt et Lib de TOUS les comptes dans PLN
        $sql = "SELECT Cpt, Lib FROM pln ORDER BY Cpt ASC";
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute()) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            throw new Exception("Erreur lors de la récupération de tous les comptes PLN : " . implode(" - ", $stmt->errorInfo()));
        }
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getTousLesComptesPLN: " . $e->getMessage());
        throw new Exception("Erreur de base de données lors de la récupération de tous les comptes.");
    } catch (Exception $e) {
        error_log("Erreur générale dans getTousLesComptesPLN: " . $e->getMessage());
        throw $e;
    }
}


/**
 * Récupère la liste des comptes fournisseurs depuis la table PLN.
 * Filtre basé sur le début du numéro de compte (à adapter si nécessaire).
 *
 * @param PDO $pdo L'objet de connexion à la base de données.
 * @param string $prefix Le préfixe des numéros de compte fournisseurs (ex: '401').
 * @return array Tableau des comptes fournisseurs ou lève une exception en cas d'erreur.
 */


/**
 * Récupère un compte spécifique par son numéro depuis PLN.
 *
 * @param PDO    $pdo          L'objet de connexion à la base de données.
 * @param string $numeroCompte Le numéro du compte (Cpt) à rechercher.
 * @return array|bool Un tableau associatif contenant les informations du compte, ou false si non trouvé.
 */
function getComptePLNParNumero(PDO $pdo, string $numeroCompte): array|bool
{
    try {
        // Cherche dans PLN basé sur Cpt
        $sql = "SELECT Cpt, Lib, Sns, Aux /* Ajoutez d'autres colonnes si besoin */ FROM pln WHERE Cpt = :numero_compte";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':numero_compte', $numeroCompte);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false; // Retourne false si non trouvé
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getComptePLNParNumero: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère un compte spécifique par son numéro.
 *
 * @param PDO   $pdo          L'objet de connexion à la base de données.
 * @param string $numeroCompte Le numéro du compte à rechercher.
 * @return array|bool Un tableau associatif contenant les informations du compte,
 * ou false si le compte n'est pas trouvé.
 */
