<?php



/**
 * Récupère une écriture comptable par son ID.
 *
 * @param PDO $pdo Instance de connexion PDO.
 * @param int $idEcriture ID de l'écriture à récupérer.
 * @return array|null Retourne un tableau associatif représentant l'écriture ou null si non trouvée.
 */
 
 function getEcrituresByFactureId(PDO $pdo, $idFacture) {
    try {
		
        // First, get the ID_Ecriture_Comptable from the Factures table for the given factureId
        $stmtFacture = $pdo->prepare("SELECT ID_Ecriture_Comptable FROM Factures WHERE ID_Facture = :id_facture");
        $stmtFacture->execute([':id_facture' => $idFacture]);
        $idEcritureComptable = $stmtFacture->fetchColumn();

        if (!$idEcritureComptable) {
            // If no ID_Ecriture_Comptable is set for this invoice, return empty array
            return [];
        }

        // Now, fetch the details of that specific Ecriture
        $sql = "SELECT E.*, J.Lib AS Code_Journal
                FROM ecritures E
                LEFT JOIN JAL J ON E.Cde = J.Cde -- Assumed JAL table for Journal code
                WHERE E.ID_Ecriture = :id_ecriture";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id_ecriture' => $idEcritureComptable]);
        $ecritures = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $ecritures; // This will be an array containing one entry or an empty array
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des écritures par facture ID: " . $e->getMessage());
        return []; // Return empty array on error
    }
}




function getEcriture(PDO $pdo, int $idEcriture): array|null // Renommée de getEcritureParId à getEcriture
{
    try {
        $sql = "SELECT ID_Ecriture, Date_Saisie, Description, Montant_Total
                FROM ecritures
                WHERE ID_Ecriture = :id_ecriture";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
        $stmt->execute();
        $ecriture = $stmt->fetch(PDO::FETCH_ASSOC);
        return $ecriture ? $ecriture : null; // Retourne null si aucun résultat
    } catch (PDOException $e) {
        // Gestion des erreurs : log l'erreur et retourne null
        error_log("Erreur PDO lors de la récupération de l'écriture : " . $e->getMessage());
        return null;
    }
}

function supprimerEcriture(PDO $pdo, int $id): bool {
    try {
        // --- IMPORTANT: Execute DELETE queries on dependent tables FIRST ---

        // 1. Supprimer les lignes d'écriture associées à cette écriture
        $stmtLignes = $pdo->prepare("DELETE FROM lignes_ecritures WHERE ID_Ecriture = :id");
        $stmtLignes->bindParam(':id', $id, PDO::PARAM_INT);
        $stmtLignes->execute();

        // 2. Supprimer les entrées associées dans la table 'Factures'
        //    Ceci suppose que 'ID_Ecriture_Comptable' dans 'Factures' est la colonne de clé étrangère
        $stmtFactures = $pdo->prepare("DELETE FROM Factures WHERE ID_Ecriture_Comptable = :id");
        $stmtFactures->bindParam(':id', $id, PDO::PARAM_INT);
        $stmtFactures->execute();

        // 3. Supprimer les entrées associées dans la table 'Echeances_Amortissement'
        //    Ceci suppose que 'ID_Ecriture_Comptable' dans 'Echeances_Amortissement' est la colonne de clé étrangère
        $stmtEcheances = $pdo->prepare("DELETE FROM Echeances_Amortissement WHERE ID_Ecriture_Comptable = :id");
        $stmtEcheances->bindParam(':id', $id, PDO::PARAM_INT);
        $stmtEcheances->execute();


        // --- Finally, delete the main entry ---

        // 4. Supprimer l'écriture elle-même de la table 'Ecritures'
        //    (Votre table PHP s'appelle Ecritures_Comptables, mais votre DDL SQL la nomme Ecritures)
        $stmtEcriture = $pdo->prepare("DELETE FROM ecritures WHERE ID_Ecriture = :id");
        $stmtEcriture->bindParam(':id', $id, PDO::PARAM_INT);
        $result = $stmtEcriture->execute();

        return $result; // Returns true if the main entry deletion succeeded
    } catch (PDOException $e) {
        // Log the exact database error. This is crucial for debugging.
        error_log("Erreur PDO lors de la suppression de l'écriture (ID: $id) ou de ses dépendances: " . $e->getMessage());
        return false;
    }
}


function getSoldeAnterieur(PDO $pdo, $compteId, $dateLimite = null): array {
    $result = ['total_debit' => 0.00, 'total_credit' => 0.00];

    // Validate $compteId as an integer for PDO binding
    $compteId = (int)$compteId;

    // If $dateLimite is not provided, is empty, or not a valid date string,
    // we consider there are no prior entries to calculate for the "anterior balance".
    // Using checkdate for a more robust date string validation.
    if (empty($dateLimite) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateLimite) || !checkdate((int)substr($dateLimite, 5, 2), (int)substr($dateLimite, 8, 2), (int)substr($dateLimite, 0, 4))) {
        return $result; // Anterior balance of 0
    }

    try {
        // The query sums the amounts from Lignes_Ecritures for the specified account,
        // joining with the Ecritures table to filter by Date_Saisie.
        // It only includes entries where Date_Saisie is STRICTLY LESS THAN $dateLimite.
        $sql = "SELECT 
                    SUM(CASE WHEN le.Sens = 'D' THEN le.Montant ELSE 0 END) as sum_debit,
                    SUM(CASE WHEN le.Sens = 'C' THEN le.Montant ELSE 0 END) as sum_credit
                FROM 
                    Lignes_Ecritures le
                JOIN 
                    Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
                WHERE 
                    le.ID_Compte = :compte_id 
                    AND e.Date_Saisie < :date_limite";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':compte_id', $compteId, PDO::PARAM_INT);
        
        // $dateLimite is in YYYY-MM-DD format. The comparison e.Date_Saisie < :date_limite
        // will work correctly for DATE and DATETIME types (before midnight of $dateLimite).
        $stmt->bindParam(':date_limite', $dateLimite, PDO::PARAM_STR);
        
        $stmt->execute();
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($totals) {
            $result['total_debit'] = (float)($totals['sum_debit'] ?? 0.00);
            $result['total_credit'] = (float)($totals['sum_credit'] ?? 0.00);
        }

    } catch (PDOException $e) {
        // In case of an error, log the error and return 0 for totals
        error_log("Erreur PDO dans getSoldeAnterieur pour compte ID {$compteId} et date {$dateLimite}: " . $e->getMessage());
        // $result remains ['total_debit' => 0.00, 'total_credit' => 0.00]
    }

    return $result;
}

function getEcritureDetails(PDO $pdo, int $idEcriture) {
    try {
        // Prépare la requête SQL pour sélectionner l'écriture par son ID.
        // Assurez-vous que les noms de colonnes correspondent exactement à votre table Ecritures.
        $sql = "SELECT 
                    ID_Ecriture,
                    Date_Saisie,
                    Description,
                    Montant_Total,
                    ID_Journal,
                    Cde, -- Supposant que Cde est l'ID du journal lié à la table JAL
                    NumeroAgenceSCE,
                    libelle2,
                    NomUtilisateur,
                    Mois,
                    Numero_Piece
                FROM 
                    Ecritures
                WHERE 
                    ID_Ecriture = :id_ecriture";

        $stmt = $pdo->prepare($sql);

        // Lie le paramètre :id_ecriture à la variable $idEcriture.
        $stmt->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);

        // Exécute la requête.
        $stmt->execute();

        // Récupère le résultat sous forme de tableau associatif.
        // fetch() est utilisé car nous attendons au plus une seule ligne (l'ID est unique).
        $ecriture = $stmt->fetch(PDO::FETCH_ASSOC);

        // Retourne l'écriture trouvée (peut être false si non trouvée).
        return $ecriture;

    } catch (PDOException $e) {
        // En cas d'erreur avec la base de données, log l'erreur et retourne false.
        // Vous pourriez vouloir une gestion d'erreur plus sophistiquée ici.
        error_log("Erreur lors de la récupération des détails de l'écriture (ID: $idEcriture): " . $e->getMessage());
        return false;
    }
}


function getEcrituresByCompte(PDO $pdo, int $compteId) {
    $sql = "SELECT e.ID_Ecriture, e.Date_Saisie, e.Description, le.Contrepartie, le.Montant, le.Sens, e.NumeroAgenceSCE
            FROM Ecritures e
            JOIN Lignes_Ecritures le ON e.ID_Ecriture = le.ID_Ecriture
            WHERE le.ID_Compte = :compteId";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['compteId' => $compteId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// fonctions/gestion_ecritures.php (Conceptual Example)
function getLignesEcrituresByCompte(PDO $pdo, $compteId, $dateDebut = null, $dateFin = null) {
    $sql = "
        SELECT le.*, e.Date_Saisie, e.Description, e.NumeroAgenceSCE
        FROM Lignes_Ecritures le
        JOIN Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
        WHERE le.ID_Compte = :compte_id
    ";

    $params = [':compte_id' => $compteId];

    if ($dateDebut) {
        $sql .= " AND e.Date_Saisie >= :date_debut";
        $params[':date_debut'] = $dateDebut;
    }
    if ($dateFin) {
        $sql .= " AND e.Date_Saisie <= :date_fin";
        $params[':date_fin'] = $dateFin;
    }

    // Ajout de la colonne `Lettre_Lettrage` pour la fonctionnalité de lettrage
    // Il est plus sûr de lister explicitement les colonnes pour éviter les erreurs.
    $sql = "
        SELECT 
            le.ID_Ligne, -- Correction du nom de la colonne
            le.ID_Ecriture,
            le.ID_Compte,
            le.Sens,
            le.Montant,
            le.Lettre_Lettrage, -- Ajout de la colonne pour le lettrage
            e.Date_Saisie, 
            e.Description, 
            e.NumeroAgenceSCE,
            e.NomUtilisateur
        FROM Lignes_Ecritures le
        JOIN Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
        WHERE le.ID_Compte = :compte_id
    ";
    
    // Réapplication des conditions de date
    if ($dateDebut) {
        $sql .= " AND e.Date_Saisie >= :date_debut";
        $params[':date_debut'] = $dateDebut;
    }
    if ($dateFin) {
        $sql .= " AND e.Date_Saisie <= :date_fin";
        $params[':date_fin'] = $dateFin;
    }

    $sql .= " ORDER BY e.Date_Saisie ASC, le.ID_Ligne ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEcrituress(PDO $pdo, int $compteId, string $dateDebut, string $dateFin, string $search = '', string $sort = 'Date_Saisie', string $order = 'ASC') {
    $sql = "SELECT e.ID_Ecriture, e.Date_Saisie as Date, e.Numero_Piece as Pièce, e.libelle2 as Journal, e.Description as Libellé,
                   le.Contrepartie, le.Montant as Débit, 0 as Crédit, e.NumeroAgenceSCE as Agence
            FROM Ecritures e
            JOIN Lignes_Ecritures le ON e.ID_Ecriture = le.ID_Ecriture
            WHERE le.ID_Compte = :compteId AND e.Date_Saisie BETWEEN :dateDebut AND :dateFin";

    if (!empty($search)) {
        $sql .= " AND (e.Numero_Piece LIKE :search OR e.Description LIKE :search OR e.libelle2 LIKE :search OR le.Contrepartie LIKE :search)";
    }

    $sql .= " ORDER BY $sort $order";

    $stmt = $pdo->prepare($sql);
    $params = ['compteId' => $compteId, 'dateDebut' => $dateDebut, 'dateFin' => $dateFin];

    if (!empty($search)) {
        $params['search'] = "%$search%";
    }

    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getEcritures(PDO $pdo, int $compteId, string $dateDebut, string $dateFin, string $search = '', string $sort = 'Date_Saisie', string $order = 'ASC') {
    $sql = "SELECT e.ID_Ecriture, e.Date_Saisie as Date, e.Numero_Piece as Pièce, e.libelle2 as Journal, e.Description as Libellé,
                   le.Contrepartie, le.Débit, le.Crédit, e.NumeroAgenceSCE as Agence
            FROM Ecritures e
            JOIN Lignes_Ecritures le ON e.ID_Ecriture = le.ID_Ecriture
            WHERE e.ID_Compte = :compteId AND e.Date_Saisie BETWEEN :dateDebut AND :dateFin";

    if (!empty($search)) {
        $sql .= " AND (e.Numero_Piece LIKE :search OR e.Description LIKE :search OR e.libelle2 LIKE :search OR le.Contrepartie LIKE :search)";
    }

    $sql .= " ORDER BY $sort $order";

    $stmt = $pdo->prepare($sql);
    $params = ['compteId' => $compteId, 'dateDebut' => $dateDebut, 'dateFin' => $dateFin];

    if (!empty($search)) {
        $params['search'] = "%$search%";
    }

    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// fonctions/gestion_ecritures.php (corrected version)

function enregistrerEcriture(
    PDO $pdo,
    string $date, // Le type-hint reste string
    string $description,
    float $montant,
    int $id_journal,
    int $cde_journal,
    string $numero_piece,
    string $mois_comptable,
    string $nom_utilisateur = 'SYSTEM',
    string $libelle2 = '',
    string $numero_agence = ''
) {
    try {
        // SQL query for MySQL. We don't need to dynamically get the identity column name.
        // We also don't use 'OUTPUT INSERTED' or 'IDENT_CURRENT', which are SQL Server specific.
        $sql = "INSERT INTO Ecritures (
                    Date_Saisie, Description, Montant_Total, ID_Journal, Cde,
                    NumeroAgenceSCE, libelle2, NomUtilisateur, Mois, Numero_Piece
                )
                VALUES (
                    :date_saisie, :description, :montant_total, :id_journal, :cde_journal,
                    :numero_agence, :libelle2, :nom_utilisateur, :mois_comptable, :numero_piece
                )";

        $stmt = $pdo->prepare($sql);

        // Bind the parameters
        $stmt->bindParam(':date_saisie', $date);
        $stmt->bindParam(':description', $description);
        // Bind float as a string to avoid precision issues
        $montantStr = (string)$montant;
        $stmt->bindParam(':montant_total', $montantStr);
        $stmt->bindParam(':id_journal', $id_journal, PDO::PARAM_INT);
        $stmt->bindParam(':cde_journal', $cde_journal, PDO::PARAM_INT);
        $stmt->bindParam(':numero_agence', $numero_agence);
        $stmt->bindParam(':libelle2', $libelle2);
        $stmt->bindParam(':nom_utilisateur', $nom_utilisateur);
        $stmt->bindParam(':mois_comptable', $mois_comptable);
        $stmt->bindParam(':numero_piece', $numero_piece);

        if ($stmt->execute()) {
            // After a successful insert, use PDO's built-in lastInsertId() method for MySQL.
            $newId = $pdo->lastInsertId();

            if ($newId) {
                return ['status' => true, 'id' => $newId];
            }

            // Fallback for tables without an auto-incrementing primary key (unlikely but good practice)
            return [
                'status' => false,
                'error' => "Insertion réussie, mais aucun ID retourné. La table 'Ecritures' a-t-elle un ID auto-incrémenté ?",
                'debug_info' => ["SQL: " . $sql]
            ];
        }

        $errorInfo = $stmt->errorInfo();
        return [
            'status' => false,
            'error' => $errorInfo[2] ?? 'Erreur inconnue lors de l\'exécution de la requête.',
            'debug_info' => $errorInfo
        ];
    } catch (PDOException $e) {
        return [
            'status' => false,
            'error' => $e->getMessage(),
            'debug_info' => [
                "Exception PDO",
                "Fichier: " . basename(__FILE__),
                "Ligne: " . $e->getLine(),
                "Requête SQL: " . $sql ?? 'N/A'
            ]
        ];
    }
}
if (!function_exists('getEcrituresAvecDetailsCompte')) {
    /**
     * Récupère les écritures comptables avec les détails du compte associé.
     * Peut filtrer les écritures par le numéro de compte ou le nom du compte.
     *
     * @param PDO $pdo L'objet de connexion PDO.
     * @param string $searchTerm Le terme de recherche (numéro ou nom de compte).
     * @return array La liste des écritures.
     */
    function getEcrituresAvecDetailsCompte(PDO $pdo, string $searchTerm = ''): array {
        $sql = "SELECT e.ID_Ecriture, e.Date_Ecriture, e.Libelle, e.Montant_Debit, e.Montant_Credit,
                       c.ID_Compte AS CompteID, c.Numero_Compte, c.Nom_Compte, c.Type_Compte
                FROM Ecritures_Comptables e
                INNER JOIN Comptes_compta c ON e.ID_Compte = c.ID_Compte";

        $params = [];
        if (!empty($searchTerm)) {
            // Recherche sur le numéro de compte OU le nom du compte
            $sql .= " WHERE (c.Numero_Compte LIKE :searchNumero OR c.Nom_Compte LIKE :searchNom)";
            $searchTermWithWildcards = '%' . $searchTerm . '%';
            $params['searchNumero'] = $searchTermWithWildcards;
            $params['searchNom'] = $searchTermWithWildcards;
        }

        // Trier par date (plus récent en premier), puis par ID d'écriture
        $sql .= " ORDER BY e.Date_Ecriture DESC, e.ID_Ecriture DESC";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // En production, loguez cette erreur et ne retournez pas de message détaillé à l'utilisateur.
            error_log("PDOException dans getEcrituresAvecDetailsCompte: " . $e->getMessage() . " SQL: " . $sql . " Params: " . print_r($params, true));
            // Pour le débogage, vous pouvez lancer l'exception ou retourner un message d'erreur.
            // die("Erreur de base de données lors de la récupération des écritures: " . $e->getMessage()); 
            return []; // Retourner un tableau vide en cas d'erreur pour l'AJAX
        }
    }
}

function calculerSoldeAnterieur(PDO $pdo, int $compteId, string $dateDebut): float
{
    // Sélectionne la somme des montants, en ajoutant si Débit et soustrayant si Crédit.
    // Filtre par l'ID du compte et par la date de saisie strictement AVANT la date de début.
    $sql = "SELECT
                SUM(CASE WHEN LE.Sens = 'D' THEN LE.Montant ELSE 0 END) -
                SUM(CASE WHEN LE.Sens = 'C' THEN LE.Montant ELSE 0 END) AS SoldeAnterieur
            FROM Lignes_Ecritures AS LE
            INNER JOIN Ecritures AS E ON LE.ID_Ecriture = E.ID_Ecriture -- Jointure pour accéder à la date
            WHERE LE.ID_Compte = :compte_id
              AND E.Date_Saisie < :date_debut"; // Date antérieure stricte

    try {
        $stmt = $pdo->prepare($sql);

        // Liez les paramètres
        $stmt->bindParam(':compte_id', $compteId, PDO::PARAM_INT);
        $stmt->bindParam(':date_debut', $dateDebut, PDO::PARAM_STR); // Liez la date comme chaîne (YYYY-MM-DD)

        // Exécutez la requête
        $stmt->execute();

        // Récupérez le résultat (une seule ligne, une seule colonne)
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // La fonction SUM() retourne NULL si aucune ligne ne correspond (pas de mouvements avant la date).
        // Utilisez l'opérateur de coalescence (??) pour retourner 0.0 si le résultat est NULL.
        // Convertissez explicitement en float.
        return (float)($result['SoldeAnterieur'] ?? 0.0);

    } catch (PDOException $e) {
        // Enregistrez l'erreur pour le débogage (ajustez le chemin/mécanisme de log si nécessaire)
        error_log("Erreur PDO dans calculerSoldeAnterieur() pour compte ID " . $compteId . " avant " . $dateDebut . " : " . $e->getMessage());

        // En cas d'erreur, retournez 0.0.
        return 0.0;
    }
}


/**
 * Calcule les totaux Débit et Crédit pour un compte dans une période donnée.
 *
 * @param PDO $pdo L'objet de connexion PDO à la base de données.
 * @param int $compteId L'ID du compte.
 * @param string $dateDebut La date de début de la période (incluse, au format YYYY-MM-DD).
 * @param string $dateFin La date de fin de la période (incluse, au format YYYY-MM-DD).
 * @return array Un tableau associatif avec les clés 'debit' et 'credit' contenant les totaux. Retourne ['debit' => 0.0, 'credit' => 0.0] en cas d'erreur.
 */
function calculerTotauxPeriode(PDO $pdo, int $compteId, string $dateDebut, string $dateFin): array
{
    // Sélectionne la somme des montants pour les Débits et la somme pour les Crédits séparément.
    // Filtre par l'ID du compte et par la date de saisie ENTRE la date de début et la date de fin (incluses).
    $sql = "SELECT
                SUM(CASE WHEN LE.Sens = 'D' THEN LE.Montant ELSE 0 END) AS TotalDebitPeriode,
                SUM(CASE WHEN LE.Sens = 'C' THEN LE.Montant ELSE 0 END) AS TotalCreditPeriode
            FROM Lignes_Ecritures AS LE
            INNER JOIN Ecritures AS E ON LE.ID_Ecriture = E.ID_Ecriture -- Jointure pour accéder à la date
            WHERE LE.ID_Compte = :compte_id
              AND E.Date_Saisie BETWEEN :date_debut AND :date_fin"; // Date incluse dans l'intervalle

    try {
        $stmt = $pdo->prepare($sql);

        // Liez les paramètres
        $stmt->bindParam(':compte_id', $compteId, PDO::PARAM_INT);
        $stmt->bindParam(':date_debut', $dateDebut, PDO::PARAM_STR); // Liez les dates comme chaînes
        $stmt->bindParam(':date_fin', $dateFin, PDO::PARAM_STR);

        // Exécutez la requête
        $stmt->execute();

        // Récupérez le résultat (une seule ligne avec deux colonnes)
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Les fonctions SUM() retournent NULL si aucune ligne ne correspond.
        // Utilisez l'opérateur de coalescence (??) pour retourner 0.0 si les résultats sont NULL.
        // Convertissez explicitement en float.
        return [
            'debit' => (float)($result['TotalDebitPeriode'] ?? 0.0),
            'credit' => (float)($result['TotalCreditPeriode'] ?? 0.0)
        ];

    } catch (PDOException $e) {
        // Enregistrez l'erreur pour le débogage (ajustez le chemin/mécanisme de log si nécessaire)
        error_log("Erreur PDO dans calculerTotauxPeriode() pour compte ID " . $compteId . " entre " . $dateDebut . " et " . $dateFin . " : " . $e->getMessage());

        // En cas d'erreur, retournez les totaux à 0.0.
        return ['debit' => 0.0, 'credit' => 0.0];
    }
}


// Vous aurez besoin d'une page pour afficher le détail d'une écriture, ex: ../ecritures/detail_ecriture.php
// Cette fonction pourrait être utile pour cette page de détail.
if (!function_exists('getEcritureDetailById')) {
    function getEcritureDetailById(PDO $pdo, int $idEcriture): ?array {
        $sql = "SELECT e.ID_Ecriture, e.Date_Ecriture, e.Libelle, e.Montant_Debit, e.Montant_Credit,
                       e.Journal_Code, e.Piece_Ref, -- et autres champs de Ecritures_Comptables
                       c.ID_Compte AS CompteID, c.Numero_Compte, c.Nom_Compte, c.Type_Compte
                FROM Ecritures_Comptables e
                INNER JOIN Comptes_compta c ON e.ID_Compte = c.ID_Compte
                WHERE e.ID_Ecriture = :idEcriture";
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['idEcriture' => $idEcriture]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("PDOException dans getEcritureDetailById: " . $e->getMessage());
            return null;
        }
    }
}


function getEcritureHeader(PDO $pdo, int $idEcriture): array | false {
    // Assurez-vous que les noms de colonnes ci-dessous correspondent EXACTEMENT
    // aux noms de colonnes dans votre table [dbo].[Ecritures].
    $sql = "SELECT
                ID_Ecriture,          -- L'ID unique de l'écriture
                Date_Piece,           -- La date de la pièce
                Libelle_General,      -- Le libellé global de l'écriture
                Total_Debit,          -- Le total des débits pour cette écriture
                Total_Credit,         -- Le total des crédits pour cette écriture
                Code_Journal,         -- Le code du journal (clé étrangère vers [dbo].[Journaux])
                Numero_Piece,         -- Le numéro de pièce
                Nom_Utilisateur,      -- L'utilisateur qui a créé (ou modifié?) l'écriture
                Mois_Comptable        -- La période comptable (format YYYY-MM)
                -- Ajoutez ici d'autres colonnes de l'en-tête si nécessaire (ex: Date_Creation, Date_Modification)
            FROM Ecritures
            WHERE ID_Ecriture = :id_ecriture"; // Sélectionne l'écriture par son ID

    try {
        $stmt = $pdo->prepare($sql);
        // Liez l'ID de l'écriture comme un entier pour des raisons de sécurité et de performance.
        $stmt->bindValue(':id_ecriture', $idEcriture, PDO::PARAM_INT);

        $stmt->execute();

        // Récupère la première ligne du résultat (il ne devrait y en avoir qu'une pour un ID unique).
        // fetch() retourne false si aucune ligne n'est trouvée.
        $header_data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $header_data;

    } catch (PDOException $e) {
        // En cas d'erreur de base de données, enregistrez l'erreur et retournez false.
        // error_log peut être configuré pour écrire dans un fichier ou Syslog.
        error_log("Erreur PDO (getEcritureHeader - ID: $idEcriture): " . $e->getMessage());

        // Optionnel: Loggez plus de détails sur l'erreur PDO si vous en avez besoin pour le débogage
        if ($stmt) {
             error_log("SQLSTATE: " . $stmt->errorCode());
             error_log("ErrorInfo: " . print_r($stmt->errorInfo(), true));
         }

        return false; // Indique que la récupération a échoué
    }
}

/**
 * Récupère la liste des écritures comptables avec possibilité de recherche et tri
 * 
 * @param PDO $pdo Instance PDO
 * @param string|null $search Terme de recherche (optionnel)
 * @param string $sort Colonne de tri (par défaut: Date_Saisie)
 * @param string $order Ordre de tri (ASC/DESC, par défaut: DESC)
 * @return array Tableau des écritures ou tableau vide en cas d'erreur
 */
 
 function getListeEmprunt($pdo) {
    try {
        $stmt = $pdo->query("SELECT ID_Emprunt, Banque, Numero_Pret, Montant_Pret, Date_Mise_En_Place, Date_Derniere_Echeance FROM Emprunts_Bancaires ORDER BY Date_Mise_En_Place DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Gérer l'erreur (log, affichage, etc.)
        error_log("Erreur lors de la récupération de la liste des emprunts : " . $e->getMessage());
        return [];
    }
}
 
 
function getListeEcritures($pdo, $searchTerm = '', $sortBy = 'ID_Ecriture', $sortOrder = 'DESC', $banque = null) {
    $sql = "SELECT ID_Ecriture, Date_Saisie, Description, Montant_Total, Cde, NumeroAgenceSCE, libelle2, NomUtilisateur, Mois, Numero_Piece FROM Ecritures WHERE 1=1";
    $params = [];

    if (!empty($searchTerm)) {
        $sql .= " AND (ID_Ecriture LIKE ? OR Description LIKE ? OR Cde LIKE ?)";
        $params[] = "%{$searchTerm}%";
        $params[] = "%{$searchTerm}%";
        $params[] = "%{$searchTerm}%"; // Assuming Cde can also be searched as a string
    }

    // --- THIS IS THE CRUCIAL PART FOR LINE 1006 ---
    // Make sure $banque is checked before use
    // If $banque is a parameter that could be optional, initialize it to null in the function signature.
    // If it's used inside a block that expects it, ensure it's defined or handle its absence.

    // Example of using $banque if it's a filter:
    if ($banque !== null && is_string($banque) && $banque !== '') {
        $lowerBanque = strtolower($banque); // Line 1006 fix
        // Add your logic related to $banque here, e.g.:
        // $sql .= " AND SomeBankColumn = ?";
        // $params[] = $lowerBanque;
    }
    // --- END CRUCIAL PART ---

    // Ensure the column name used for sorting is valid to prevent SQL injection
    $allowedSortFields = ['ID_Ecriture', 'Date_Saisie', 'Description', 'Montant_Total', 'Cde'];
    if (!in_array($sortBy, $allowedSortFields)) {
        $sortBy = 'ID_Ecriture'; // Default to a safe column
    }

    // Ensure sort order is either ASC or DESC
    $sortOrder = strtoupper($sortOrder);
    if (!in_array($sortOrder, ['ASC', 'DESC'])) {
        $sortOrder = 'DESC'; // Default to a safe order
    }

    $sql .= " ORDER BY " . $sortBy . " " . $sortOrder;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Log the database error instead of just returning an empty array
        error_log("Database Error in getListeEcritures: " . $e->getMessage());
        return []; // Return empty array on error
    }
}
function ajouterEcriture(PDO $pdo, array $dataForEcriture)
{
    // Vérification des données essentielles
    if (
        !isset($dataForEcriture['date_saisie']) || empty($dataForEcriture['date_saisie']) ||
        !isset($dataForEcriture['description']) || empty($dataForEcriture['description']) ||
        !isset($dataForEcriture['montant_total']) || !is_numeric($dataForEcriture['montant_total']) || $dataForEcriture['montant_total'] <= 0 ||
        !isset($dataForEcriture['cde_journal']) || !is_numeric($dataForEcriture['cde_journal']) ||
        !isset($dataForEcriture['nom_utilisateur']) || empty($dataForEcriture['nom_utilisateur']) ||
        !isset($dataForEcriture['lignes']) || !is_array($dataForEcriture['lignes']) || empty($dataForEcriture['lignes'])
    ) {
        error_log("ajouterEcriture: Données d'entrée incomplètes ou invalides.");
        return false;
    }

    $dateSaisie = $dataForEcriture['date_saisie'];
    $description = $dataForEcriture['description'];
    $montantTotal = $dataForEcriture['montant_total'];
    $cdeJournal = $dataForEcriture['cde_journal'];
    $nomUtilisateur = $dataForEcriture['nom_utilisateur'];
    $lignesEcriture = $dataForEcriture['lignes'];

    // Déterminer le mois à partir de la date de saisie
    $mois = date('Y-m', strtotime($dateSaisie));

    // Commencer une transaction
    $pdo->beginTransaction();

    try {
        // 1. Insérer l'écriture principale dans la table Ecritures
        // Utilisation des noms de colonnes basés sur le schéma fourni
        $sqlEcriture = "INSERT INTO Ecritures (Date_Saisie, Description, Montant_Total, Cde, NomUtilisateur, Mois)
                        VALUES (:date_saisie, :description, :montant_total, :cde_journal, :nom_utilisateur, :mois)";
        $stmtEcriture = $pdo->prepare($sqlEcriture);
        $stmtEcriture->execute([
            ':date_saisie' => $dateSaisie,
            ':description' => $description,
            ':montant_total' => round($montantTotal, 2), // Arrondir le montant total
            ':cde_journal' => $cdeJournal,
            ':nom_utilisateur' => $nomUtilisateur,
            ':mois' => $mois
        ]);

        // Récupérer l'ID de l'écriture nouvellement insérée
        // Pour SQL Server, SCOPE_IDENTITY() est souvent plus fiable que lastInsertId()
        // si lastInsertId() ne fonctionne pas comme prévu avec votre configuration/driver.
        // Si lastInsertId() fonctionne, utilisez-le. Sinon, utilisez la requête suivante :
        // $stmtLastId = $pdo->query("SELECT SCOPE_IDENTITY() AS ID_Ecriture");
        // $idEcriture = $stmtLastId->fetchColumn();
        $idEcriture = $pdo->lastInsertId(); // Tenter avec lastInsertId() d'abord

        if (!$idEcriture) {
             // Si lastInsertId() retourne 0 ou false, essayer SCOPE_IDENTITY()
             // Ceci est une gestion de fallback spécifique à SQL Server
             $stmtLastId = $pdo->query("SELECT SCOPE_IDENTITY() AS ID_Ecriture");
             $idEcriture = $stmtLastId->fetchColumn();
             if (!$idEcriture) {
                 throw new Exception("Impossible de récupérer l'ID de la nouvelle écriture.");
             }
        }


        // 2. Insérer les lignes d'écriture dans la table Lignes_Ecritures
        // Utilisation des noms de colonnes basés sur le schéma fourni
        $sqlLigne = "INSERT INTO Lignes_Ecritures (ID_Ecriture, ID_Compte, Montant, Sens)
                     VALUES (:id_ecriture, :id_compte, :montant, :sens)";
        $stmtLigne = $pdo->prepare($sqlLigne);

        foreach ($lignesEcriture as $ligne) {
            // Validation basique de chaque ligne
            if (!isset($ligne['id_compte']) || !is_numeric($ligne['id_compte']) ||
                !isset($ligne['montant']) || !is_numeric($ligne['montant']) || $ligne['montant'] < 0 ||
                !isset($ligne['sens']) || !in_array($ligne['sens'], ['D', 'C']))
            {
                throw new Exception("Données de ligne d'écriture invalides ou incomplètes.");
            }

            $stmtLigne->execute([
                ':id_ecriture' => $idEcriture,
                ':id_compte' => $ligne['id_compte'], // ID_Compte est INT selon le schéma Lignes_Ecritures
                ':montant' => round($ligne['montant'], 2), // Arrondir le montant de la ligne
                ':sens' => $ligne['sens']
            ]);
        }

        // Si tout s'est bien passé, valider la transaction
        $pdo->commit();

        // Retourner l'ID de l'écriture insérée
        return $idEcriture;

    } catch (PDOException $e) {
        // En cas d'erreur PDO, annuler la transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Journaliser l'erreur (en production, ne pas afficher à l'utilisateur)
        error_log("Erreur PDO lors de l'enregistrement de l'écriture: " . $e->getMessage());
        return false; // Indiquer l'échec
    } catch (Exception $e) {
         // Gérer d'autres exceptions (par exemple, validation des lignes)
         if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erreur lors de l'enregistrement de l'écriture: " . $e->getMessage());
        return false; // Indiquer l'échec
    }
}


function getLignesEcritureParId(PDO $pdo, int $idEcriture): array
{
    try {
        $sql = "SELECT
                    ID_Ligne,
                    ID_Compte,
                    Montant,
                    Sens
                FROM
                    Lignes_Ecritures
                WHERE
                    ID_Ecriture = :id_ecriture";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
        $stmt->execute();
        $lignesEcriture = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $lignesEcriture;

    } catch (PDOException $e) {
        // Gestion des erreurs : enregistrer l'erreur et retourner un tableau vide
        error_log("Erreur PDO lors de la récupération des lignes d'écriture : " . $e->getMessage());
        return [];
    }
}


function getEcritureParId(PDO $pdo, int $idEcriture): array|null
{
    try {
        $sql = "SELECT ID_Ecriture, Date_Saisie, Description, Montant_Total
                FROM Ecritures
                WHERE ID_Ecriture = :id_ecriture";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
        $stmt->execute();
        $ecriture = $stmt->fetch(PDO::FETCH_ASSOC);
        return $ecriture ? $ecriture : null; // Retourne null si aucun résultat
    } catch (PDOException $e) {
        // Gestion des erreurs : log l'erreur et retourne null
        error_log("Erreur PDO lors de la récupération de l'écriture : " . $e->getMessage());
        return null;
    }
}

/**
 * Récupère les lignes d'écriture associées à une écriture comptable.
 *
 * @param PDO $pdo Instance de connexion PDO.
 * @param int $idEcriture ID de l'écriture dont on veut les lignes.
 * @return array Retourne un tableau de tableaux associatifs représentant les lignes d'écriture.
 */
function getLignesEcriture(PDO $pdo, int $idEcriture): array
{
    try {
        $sql = "SELECT ID_Ligne, ID_Compte, Montant, Sens
                FROM Lignes_Ecritures
                WHERE ID_Ecriture = :id_ecriture
                ORDER BY ID_Ligne"; // Important de garder l'ordre des lignes
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Gestion des erreurs : log et retourne un tableau vide pour indiquer l'échec
        error_log("Erreur PDO lors de la récupération des lignes d'écriture : " . $e->getMessage());
        return [];
    }
}

/**
 * Modifie une écriture comptable.
 *
 * @param PDO $pdo Instance de connexion PDO.
 * @param int $idEcriture ID de l'écriture à modifier.
 * @param string $dateSaisie Nouvelle date de saisie.
 * @param string $description Nouvelle description.
 * @param float $montantTotal Nouveau montant total.
 * @return bool Retourne true en cas de succès, false en cas d'échec.
 */
function modifierEcriture(PDO $pdo, int $idEcriture, string $dateSaisie, string $description, float $montantTotal): bool
{
    try {
        $sql = "UPDATE Ecritures
                SET Date_Saisie = :date_saisie,
                    Description = :description,
                    Montant_Total = :montant_total
                WHERE ID_Ecriture = :id_ecriture";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':date_saisie', $dateSaisie);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':montant_total', $montantTotal);
        $stmt->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        // Gestion des erreurs : log et retourne false
        error_log("Erreur PDO lors de la modification de l'écriture : " . $e->getMessage());
        return false;
    }
}

/**
 * Supprime toutes les lignes d'écriture associées à une écriture.
 *
 * @param PDO $pdo Instance de connexion PDO.
 * @param int $idEcriture ID de l'écriture dont les lignes doivent être supprimées.
 * @return bool Retourne true en cas de succès, false en cas d'échec.
 */
function supprimerLignesEcriture(PDO $pdo, int $idEcriture): bool
{
    try {
        $sql = "DELETE FROM Lignes_Ecritures WHERE ID_Ecriture = :id_ecriture";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        // Tu peux ajouter un log ou un return false ici
        return false;
    }
}


/**
 * Enregistre une nouvelle ligne d'écriture.
 *
 * @param PDO $pdo Instance de connexion PDO.
 * @param int $idEcriture ID de l'écriture à laquelle la ligne est associée.
 * @param int $idCompte ID du compte concerné par la ligne.
 * @param float $montant Montant de la ligne.
 * @param string $sens Sens de la ligne ('D' pour débit, 'C' pour crédit).
 * @return bool Retourne true en cas de succès, false en cas d'échec.
 */
function enregistrerLigneEcriture(PDO $pdo, int $idEcriture, string $compte_cpt, string $libelle_ligne, float $montant, string $sens, string $an_code, string $contrepartie_compte): void {
    // --- ADAPTÉ À VOTRE STRUCTURE DE TABLE [dbo].[Lignes_Ecritures] ---

    // Nom de la table tel que défini dans votre script CREATE TABLE
    $tableName = 'Lignes_Ecritures';

    // Les noms de colonnes correspondent EXACTEMENT à votre script CREATE TABLE.
    // Notez l'absence de Code_Agence et Code_Contrepartie.
    // Notez les noms corrects : ID_Compte, Montant, Sens.
    $sql = "INSERT INTO $tableName (
                ID_Ecriture,         -- Colonne pour lier à l'en-tête d'écriture
                ID_Compte,           -- Colonne pour l'ID du compte (type INT dans la table)
                Libelle_Ligne,       -- Colonne pour le libellé de la ligne
                Montant,             -- Colonne pour le montant (type DECIMAL dans la table)
                Sens                 -- Colonne pour le sens ('D' ou 'C', type CHAR(1) dans la table)
                -- Les colonnes Code_Agence et Code_Contrepartie ne sont PAS incluses ici
                -- car elles n'existent pas dans la définition de table fournie.
            ) VALUES (
                :id_ecriture,
                :id_compte,
                :libelle_ligne,
                :montant,
                :sens
            )";

    // --- FIN DE LA PARTIE ADAPTÉE ---


    try {
        $stmt = $pdo->prepare($sql);

        // Lier les paramètres aux placeholders
        // Utilisez les types PDO::PARAM_* appropriés.
        // PDO::PARAM_STR convient pour VARCHAR, NVARCHAR, CHAR, etc.
        // PDO::PARAM_INT pour INT.
        // PDO::PARAM_STR est souvent le plus sûr pour les types DECIMAL.
        $stmt->bindValue(':id_ecriture', $idEcriture, PDO::PARAM_INT);
        // ATTENTION : Votre fonction reçoit $compte_cpt comme string,
        // mais la table attend un INT pour ID_Compte.
        // On caste en INT ici. Assurez-vous que $compte_cpt contient bien l'ID numérique du compte.
        $stmt->bindValue(':id_compte', (int)$compte_cpt, PDO::PARAM_INT);
        $stmt->bindValue(':libelle_ligne', $libelle_ligne, PDO::PARAM_STR);
        $stmt->bindValue(':montant', $montant, PDO::PARAM_STR); // Lier le float comme string pour DECIMAL
        $stmt->bindValue(':sens', $sens, PDO::PARAM_STR);

        // Les liaisons pour :code_agence et :code_contrepartie sont supprimées
        // car ces colonnes n'existent pas dans la table cible.

        // Exécuter la requête préparée
        $stmt->execute();

        // Si PDO est configuré pour lancer des exceptions (recommandé : PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION),
        // toute erreur SQL sera capturée par le bloc catch ci-dessous.
        // Sinon, vous devriez vérifier $stmt->errorCode() et $stmt->errorInfo() ici.

    } catch (PDOException $e) {
        // Enregistrer l'erreur pour le débogage
        error_log("Erreur PDO dans enregistrerLigneEcriture: " . $e->getMessage());
        // Informations de débogage supplémentaires
        if ($stmt) {
             error_log("SQLSTATE: " . $stmt->errorCode());
             error_log("ErrorInfo: " . print_r($stmt->errorInfo(), true));
             // Attention: logguer la requête SQL avec les paramètres peut exposer des données sensibles.
             // Utilisez ceci uniquement en environnement de développement sécurisé.
             // error_log("SQL Query: " . $sql);
             // error_log("Parameters: " . json_encode([
             //     ':id_ecriture' => $idEcriture,
             //     ':id_compte' => (int)$compte_cpt,
             //     ':libelle_ligne' => $libelle_ligne,
             //     ':montant' => $montant,
             //     ':sens' => $sens
             // ]));
        }
        // Renvoyer l'exception pour que le code appelant (saisie.php) puisse la gérer
        throw $e;
    }
}

/**
 * Génère les écritures comptables pour un plan d'amortissement d'emprunt donné,
 * en utilisant les tables Ecritures (assumée) et Lignes_Ecritures.
 *
 * @param PDO $pdo La connexion PDO à la base de données.
 * @param int $emprunt_id L'ID de l'emprunt pour lequel générer les écritures.
 * @param int $journal_cde Le code (Cde) du journal comptable à utiliser (issu de la table JAL).
 * @param int $comptePrincipalId L'ID du compte comptable pour le capital de l'emprunt (ex: 164).
 * @param int $compteInteretId L'ID du compte comptable pour les charges d'intérêts (ex: 661).
 * @param int $compteBanqueId L'ID du compte comptable de la banque (ex: 512).
 * @param int $compteTaxesFraisId L'ID du compte comptable pour les taxes et frais (optionnel, ex: 627). Par défaut à 0 si non utilisé.
 * @return bool True si les écritures sont générées avec succès, False sinon.
 */
// Apply specific bank logic to all relevant amounts before generating accounting entries

function genererEcrituresAmortissement(
    PDO $pdo,
    int $emprunt_id,
    int $journal_cde,
    int $comptePrincipalId,
    int $compteInteretId,
    int $compteBanqueId,
    int $compteTaxesFraisId = 0,
    int $compteTVAId = 0,
    float $taxes_percentage = 19.25
): array { // Changement du retour en array pour plus d'informations

    // Initialisation de la réponse
    $response = [
        'status' => 'error',
        'message' => 'Une erreur inconnue est survenue.',
        'ecritures_generes' => 0,
        'errors' => []
    ];

    // --- Validation des paramètres initiaux ---
    $invalid_params = [];
    if ($comptePrincipalId <= 0) $invalid_params[] = 'Compte Principal';
    if ($compteInteretId <= 0) $invalid_params[] = 'Compte Intérêt';
    if ($compteBanqueId <= 0) $invalid_params[] = 'Compte Banque';
    if ($journal_cde <= 0) $invalid_params[] = 'Journal Code';

    if (!empty($invalid_params)) {
        $message_erreur = "Erreur: Paramètres comptables requis manquants ou invalides: " . implode(', ', $invalid_params);
        $response['message'] = $message_erreur;
        $response['errors'][] = $message_erreur;
        return $response;
    }

    // Validation des comptes optionnels
    if ($compteTaxesFraisId < 0) {
        $message_erreur = "Erreur: Le Compte Taxes/Frais ID est invalide ($compteTaxesFraisId). Doit être un entier positif ou 0.";
        $response['message'] = $message_erreur;
        $response['errors'][] = $message_erreur;
        return $response;
    }

    if ($compteTVAId < 0) {
        $message_erreur = "Erreur: Le Compte TVA ID est invalide ($compteTVAId). Doit être un entier positif ou 0.";
        $response['message'] = $message_erreur;
        $response['errors'][] = $message_erreur;
        return $response;
    }

    try {
        $pdo->beginTransaction();

        // --- 1. Récupérer les données de l'emprunt ---
        $stmtEmprunt = $pdo->prepare("SELECT Banque, Numero_Pret FROM Emprunts_Bancaires WHERE ID_Emprunt = :id_emprunt");
        $stmtEmprunt->bindParam(':id_emprunt', $emprunt_id, PDO::PARAM_INT);
        $stmtEmprunt->execute();
        $empruntDetails = $stmtEmprunt->fetch(PDO::FETCH_ASSOC);

        if (!$empruntDetails) {
            $message_erreur = "Erreur: Détails de l'emprunt introuvables pour l'ID: $emprunt_id";
            $response['message'] = $message_erreur;
            $response['errors'][] = $message_erreur;
            $pdo->rollBack();
            return $response;
        }

        $numero_pret = htmlspecialchars($empruntDetails['Numero_Pret'] ?? 'N/A');

        // --- 2. Récupérer les échéances ---
        $sql_select_plan = "SELECT ID_Echeance, Numero_Echeance, Date_Echeance, Amortissement,
                            Interet_SP, Taxes_Interet_SP, Comm_Engagement, Comm_Deblocage,
                            Taxe_Comm_E, Taxe_Comm_D, Frais_Etude, Taxe_Frais_Etude,
                            Taxe_Capital, Montant_Echeance, Etat_Reste_Du
                            FROM Echeances_Amortissement
                            WHERE ID_Emprunt = :emprunt_id
                            ORDER BY Numero_Echeance ASC";

        $stmt_select_plan = $pdo->prepare($sql_select_plan);
        $stmt_select_plan->bindParam(':emprunt_id', $emprunt_id, PDO::PARAM_INT);
        $stmt_select_plan->execute();
        $echeances = $stmt_select_plan->fetchAll(PDO::FETCH_ASSOC);

        if (empty($echeances)) {
            $message_erreur = "Erreur: Aucune échéance trouvée pour l'emprunt ID $emprunt_id";
            $response['message'] = $message_erreur;
            $response['errors'][] = $message_erreur;
            $pdo->rollBack();
            return $response;
        }

        // --- Préparation des requêtes SQL pour insertion d'écritures ---
        // Adaptez les noms de colonnes et le nombre de placeholders selon votre table `Ecritures`
        $sql_insert_entete_ecriture = "INSERT INTO ecritures (
            Date_Ecriture, Journal_Code, Ref_Piece, Libelle_Ecriture,
            Montant_Debit, Montant_Credit, Date_Saisie, Utilisateur_Saisie, Agence, Etat_Ecriture, Validation_Date, Valider_Par
        ) VALUES (
            :date_ecriture, :journal_code, :ref_piece, :libelle_ecriture,
            :montant_debit, :montant_credit, :date_saisie, :utilisateur_saisie, :agence, :etat_ecriture, :validation_date, :valider_par
        )";
        $stmt_insert_entete_ecriture = $pdo->prepare($sql_insert_entete_ecriture);

        // Adaptez les noms de colonnes et le nombre de placeholders selon votre table `Lignes_Ecritures`
        $sql_insert_ligne_ecriture = "INSERT INTO lignes_ecritures (
            ID_Ecriture, ID_Compte, Montant, Sens, Libelle_Ligne
        ) VALUES (
            :id_ecriture, :id_compte, :montant, :sens, :libelle_ligne
        )";
        $stmt_insert_ligne_ecriture = $pdo->prepare($sql_insert_ligne_ecriture);

        // --- Session et infos utilisateur ---
        if (session_status() == PHP_SESSION_NONE) session_start();
        $nom_utilisateur = $_SESSION['nom_utilisateur'] ?? 'SYSTEM';
        $agence = $_SESSION['numero_agence_sce'] ?? '009';

        $ecritures_crees = 0;
        $errors_ecritures = [];

        foreach ($echeances as $echeance) {
            $date_echeance = $echeance['Date_Echeance'];
            $numero_echeance = $echeance['Numero_Echeance'];

            // Calcul des montants avec arrondi
            $amortissement_initial = round((float)($echeance['Amortissement'] ?? 0.0), 2);
            $interet_initial = round((float)($echeance['Interet_SP'] ?? 0.0), 2);
            $montant_echeance_total = round((float)($echeance['Montant_Echeance'] ?? 0.0), 2);

            // Calcul TVA sur intérêts
            $montant_tva_sur_interet_calcule = round($interet_initial * ($taxes_percentage / 100.0), 2);
            $montant_interet_principal_comptable = round($interet_initial - $montant_tva_sur_interet_calcule, 2);

            // Calcul autres frais. Incluez tous les autres frais pertinents de votre échéancier
            $autres_frais_de_lecheancier = round(
                (float)($echeance['Taxes_Interet_SP'] ?? 0.0) +
                (float)($echeance['Comm_Engagement'] ?? 0.0) +
                (float)($echeance['Comm_Deblocage'] ?? 0.0) +
                (float)($echeance['Taxe_Comm_E'] ?? 0.0) +
                (float)($echeance['Taxe_Comm_D'] ?? 0.0) +
                (float)($echeance['Frais_Etude'] ?? 0.0) +
                (float)($echeance['Taxe_Frais_Etude'] ?? 0.0) +
                (float)($echeance['Taxe_Capital'] ?? 0.0)
            , 2);

            $montant_comptable_autres_frais = $autres_frais_de_lecheancier;

            // Vérification des montants négatifs et s'assurer qu'ils sont au minimum 0
            $montant_comptable_capital = max(0.0, $amortissement_initial);
            $montant_interet_principal_comptable = max(0.0, $montant_interet_principal_comptable);
            $montant_tva_sur_interet_calcule = max(0.0, $montant_tva_sur_interet_calcule);
            $montant_comptable_autres_frais = max(0.0, $montant_comptable_autres_frais);

            // Calcul du total des débits prévus
            $total_debits_sum = $montant_comptable_capital + $montant_interet_principal_comptable;
            if ($compteTVAId > 0) $total_debits_sum += $montant_tva_sur_interet_calcule;
            if ($compteTaxesFraisId > 0) $total_debits_sum += $montant_comptable_autres_frais;
            $total_debits_sum = round($total_debits_sum, 2);

            // Ajustement d'équilibre pour s'assurer que débits = crédits
            $tolerance = 0.02; // Tolérance pour les erreurs d'arrondi
            $difference_to_balance = round($montant_echeance_total - $total_debits_sum, 2);

            if (abs($difference_to_balance) > $tolerance) {
                // Si une différence existe, ajuster sur les autres frais d'abord
                if ($compteTaxesFraisId > 0 && $montant_comptable_autres_frais > 0) {
                    $montant_comptable_autres_frais += $difference_to_balance;
                    // S'assurer que le montant ne devient pas négatif après ajustement
                    $montant_comptable_autres_frais = max(0.0, $montant_comptable_autres_frais);
                } else if ($montant_interet_principal_comptable > 0) {
                    // Sinon, ajuster sur les intérêts
                    $montant_interet_principal_comptable += $difference_to_balance;
                    $montant_interet_principal_comptable = max(0.0, $montant_interet_principal_comptable);
                }
                // Recalculer total débits après ajustement
                $total_debits_sum = round(
                    $montant_comptable_capital +
                    $montant_interet_principal_comptable +
                    ($compteTVAId > 0 ? $montant_tva_sur_interet_calcule : 0) +
                    ($compteTaxesFraisId > 0 ? $montant_comptable_autres_frais : 0),
                    2
                );
            }

            // Vérification finale si l'équilibre est atteint
            if (abs($montant_echeance_total - $total_debits_sum) > $tolerance) {
                // Si l'équilibre n'est toujours pas bon après ajustements, loguer une erreur
                $errors_ecritures[] = "Déséquilibre comptable majeur pour l'échéance $numero_echeance (différence: " . ($montant_echeance_total - $total_debits_sum) . "). Écriture non générée.";
                continue; // Passer à l'échéance suivante
            }


            // Création de l'écriture
            $ref_piece = "EMP" . $emprunt_id . "-ECH" . $numero_echeance;
            $libelle_entete = "Echéance $numero_echeance - Emprunt $numero_pret";

            $dateTime = new DateTime('now', new DateTimeZone('Africa/Douala'));
            $date_saisie_formatted = $dateTime->format('Y-m-d H:i:s.v'); // Format pour SQL Server DATETIME2 ou équivalent
            $mois = $dateTime->format('Y-m'); // Année-Mois pour votre champ Mois_Comptable

            try {
                // Insertion entête
                $stmt_insert_entete_ecriture->execute([
                    ':date_ecriture' => $date_echeance,
                    ':journal_code' => $journal_cde,
                    ':ref_piece' => $ref_piece,
                    ':libelle_ecriture' => $libelle_entete,
                    ':montant_debit' => $total_debits_sum, // Le total des débits
                    ':montant_credit' => $montant_echeance_total, // Le total des crédits (montant échéance)
                    ':date_saisie' => $date_saisie_formatted,
                    ':utilisateur_saisie' => $nom_utilisateur,
                    ':agence' => $agence,
                    ':etat_ecriture' => 'Provisoire', // Ou 'Validée' selon votre flux
                    ':validation_date' => null, // À remplir si l'écriture est validée immédiatement
                    ':valider_par' => null // À remplir si l'écriture est validée immédiatement
                ]);
                $id_ecriture = $pdo->lastInsertId();

                if (!$id_ecriture) {
                    throw new Exception("Impossible de récupérer l'ID de l'écriture après insertion de l'entête.");
                }

                // Insertion des lignes
                $lines_to_insert = [];

                // Ligne capital (débit)
                if ($montant_comptable_capital > 0) {
                    $lines_to_insert[] = [
                        'ID_Ecriture' => $id_ecriture,
                        'ID_Compte' => $comptePrincipalId,
                        'Montant' => $montant_comptable_capital,
                        'Sens' => 'D',
                        'Libelle_Ligne' => 'Amortissement Capital Emprunt ' . $numero_pret . ' (Échéance ' . $numero_echeance . ')'
                    ];
                }

                // Ligne intérêts (débit)
                if ($montant_interet_principal_comptable > 0) {
                    $lines_to_insert[] = [
                        'ID_Ecriture' => $id_ecriture,
                        'ID_Compte' => $compteInteretId,
                        'Montant' => $montant_interet_principal_comptable,
                        'Sens' => 'D',
                        'Libelle_Ligne' => 'Intérêts Emprunt (hors TVA) ' . $numero_pret . ' (Échéance ' . $numero_echeance . ')'
                    ];
                }

                // Ligne TVA (si compte configuré et montant > 0)
                if ($compteTVAId > 0 && $montant_tva_sur_interet_calcule > 0) {
                    $lines_to_insert[] = [
                        'ID_Ecriture' => $id_ecriture,
                        'ID_Compte' => $compteTVAId,
                        'Montant' => $montant_tva_sur_interet_calcule,
                        'Sens' => 'D',
                        'Libelle_Ligne' => 'TVA sur Intérêts (' . $taxes_percentage . '%) Emprunt ' . $numero_pret . ' (Échéance ' . $numero_echeance . ')'
                    ];
                }

                // Ligne autres frais (si compte configuré et montant > 0)
                if ($compteTaxesFraisId > 0 && $montant_comptable_autres_frais > 0) {
                    $lines_to_insert[] = [
                        'ID_Ecriture' => $id_ecriture,
                        'ID_Compte' => $compteTaxesFraisId,
                        'Montant' => $montant_comptable_autres_frais,
                        'Sens' => 'D',
                        'Libelle_Ligne' => 'Autres Frais & Taxes Emprunt ' . $numero_pret . ' (Échéance ' . $numero_echeance . ')'
                    ];
                }

                // Ligne crédit banque (crédit)
                if ($montant_echeance_total > 0) {
                    $lines_to_insert[] = [
                        'ID_Ecriture' => $id_ecriture,
                        'ID_Compte' => $compteBanqueId,
                        'Montant' => $montant_echeance_total,
                        'Sens' => 'C',
                        'Libelle_Ligne' => 'Règlement Échéance ' . $numero_echeance . ' Emprunt ' . $numero_pret
                    ];
                }

                // Exécuter l'insertion des lignes
                foreach ($lines_to_insert as $line) {
                    $stmt_insert_ligne_ecriture->execute([
                        ':id_ecriture' => $line['ID_Ecriture'],
                        ':id_compte' => $line['ID_Compte'],
                        ':montant' => $line['Montant'],
                        ':sens' => $line['Sens'],
                        ':libelle_ligne' => $line['Libelle_Ligne']
                    ]);
                }

                $ecritures_crees++;

            } catch (Exception $e) {
                $errors_ecritures[] = "Erreur lors de la création de l'écriture pour l'échéance $numero_echeance (ID: {$echeance['ID_Echeance']}): " . $e->getMessage();
                // Ne pas rollback ici pour ne pas annuler les écritures précédentes
                continue; // Continue avec l'échéance suivante
            }
        }

        // Gestion des résultats finaux
        if (!empty($errors_ecritures)) {
            $response['errors'] = array_merge($response['errors'], $errors_ecritures);
            $response['message'] = "Des erreurs sont survenues lors de la création de certaines écritures.";

            if ($ecritures_crees > 0) {
                // Certaines écritures ont été créées avec succès, donc on commit les réussites.
                $pdo->commit();
                $response['status'] = 'partial_success'; // Nouveau statut pour succès partiel
                $response['message'] .= " ($ecritures_crees écritures créées avec succès).";
                $response['ecritures_generes'] = $ecritures_crees;
            } else {
                // Aucune écriture n'a été créée, on annule toutes les opérations.
                $pdo->rollBack();
                $response['status'] = 'error';
                $response['message'] = "Aucune écriture n'a pu être générée en raison d'erreurs.";
            }
        } else {
            // Toutes les écritures ont été créées avec succès
            $pdo->commit();
            $response['status'] = 'success';
            $response['message'] = "Toutes les écritures ont été générées avec succès ($ecritures_crees écritures).";
            $response['ecritures_generes'] = $ecritures_crees;
        }

        return $response;

    } catch (Exception $e) {
        // En cas d'erreur fatale avant la boucle ou de problème de transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $response['message'] = "Erreur système inattendue: " . $e->getMessage();
        $response['errors'][] = $e->getMessage();
        return $response;
    }
}

// Votre fonction getIdCompteByNumero reste inchangée et est utile.
function getIdCompteByNumero(PDO $pdo, string $numeroCompte) {
    $sql = "SELECT ID_Compte FROM Comptes_compta WHERE Numero_Compte = :numeroCompte";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':numeroCompte', $numeroCompte);
    try {
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int) $result['ID_Compte'] : false;
    } catch (PDOException $e) {
        // Afficher l'erreur directement
        echo "<div class='alert alert-danger'>PDOException getIdCompteByNumero : " . htmlspecialchars($e->getMessage()) . "</div>";
        return false;
    }
}






/**
 * Enregistre une écriture comptable principale dans la table Ecritures.
 *
 * IMPORTANT: Corrected to use 'Cde' column for Journal ID and added 'Date_Saisie'.
 *
 * @param PDO $pdo L'objet PDO.
 * @param string $description Description de l'écriture.
 * @param float $montantTotal Montant total de l'écriture.
 * @param int $cdeJournal ID du journal comptable (mapped to 'Cde' column).
 * @param string $numeroPiece Numéro de pièce (ex: numéro de facture).
 * @param string $mois Mois de l'écriture (YYYY-MM).
 * @param string $nomUtilisateur Nom de l'utilisateur qui effectue l'écriture.
 * @param string $numeroAgenceSCE Numéro d'agence SCE.
 * @return int|false L'ID de l'écriture insérée ou false en cas d'erreur.
 */
function enregistrerEcritures(PDO $pdo, string $description, float $montantTotal, int $cdeJournal, string $numeroPiece, string $mois, string $nomUtilisateur, string $numeroAgenceSCE) {
    $sql = "INSERT INTO Ecritures (Date_Saisie, Description, Montant_Total, Cde, NumeroAgenceSCE, NomUtilisateur, Mois, Numero_Piece)
            VALUES (GETDATE(), :description, :montantTotal, :cdeJournal, :numeroAgenceSCE, :nomUtilisateur, :mois, :numeroPiece)";
    $stmt = $pdo->prepare($sql);
    try {
        if ($stmt->execute([
            ':description' => $description,
            ':montantTotal' => $montantTotal,
            ':cdeJournal' => $cdeJournal, // Changed from ID_Journal to Cde
            ':numeroAgenceSCE' => $numeroAgenceSCE,
            ':nomUtilisateur' => $nomUtilisateur,
            ':mois' => $mois,
            ':numeroPiece' => $numeroPiece
        ])) {
            return $pdo->lastInsertId();
        } else {
            error_log("Erreur enregistrement Ecritures : " . implode(" | ", $stmt->errorInfo()));
            return false;
        }
    } catch (PDOException $e) {
        error_log("PDOException enregistrement Ecritures : " . $e->getMessage());
        return false;
    }
}





function enregistrerLigneEcritures(PDO $pdo, int $idEcriture, int $idCompte, string $libelle, float $montant, string $sens): bool {
    // Removed An_Code and Contrepartie_Compte from the query and parameters
    $sql = "INSERT INTO lignes_ecritures (ID_Ecriture, ID_Compte, Montant, Sens, Libelle_Ligne)
            VALUES (:idEcriture, :idCompte, :montant, :sens, :libelle)";
    $stmt = $pdo->prepare($sql);
    try {
        return $stmt->execute([
            ':idEcriture' => $idEcriture,
            ':idCompte' => $idCompte,
            ':montant' => $montant,
            ':sens' => $sens,
            ':libelle' => $libelle
        ]);
    } catch (PDOException $e) {
        error_log("PDOException enregistrement lignes_ecritures : " . $e->getMessage());
        return false;
    }
}


// fonctions/gestion_ecritures.php
// Ce fichier contient les fonctions pour la gestion des écritures comptables.

/**
 * Crée une nouvelle écriture comptable dans la base de données.
 *
 * @param PDO $pdo L'objet PDO pour la connexion à la base de données.
 * @param string $dateSaisie La date de saisie de l'écriture (format 'YYYY-MM-DD').
 * @param string $description La description de l'écriture.
 * @param float $montantTotal Le montant total de l'écriture.
 * @param int $idJournal L'ID du journal comptable.
 * @param string $nomUtilisateur Le nom de l'utilisateur qui crée l'écriture.
 * @param string $mois Le mois de l'écriture (format 'YYYY-MM').
 * @param string $numeroPiece Le numéro de pièce justificative.
 * @param int|null $cde Le code du journal (Cde de JAL), peut être null.
 * @param string|null $numeroAgenceSCE Le numéro d'agence SCE, peut être null.
 * @param string|null $libelle2 Un libellé additionnel, peut être null.
 * @return int|false L'ID de la nouvelle écriture si succès, sinon false.
 */

function createEcriture(
    PDO $pdo,
    string $dateSaisie,
    string $description,
    float $montantTotal,
    int $idJournal,
    string $nomUtilisateur,
    string $mois,
    string $numeroPiece
): int|false {
    try {
        $sql = "INSERT INTO ecritures (
            Date_Saisie,
            Description,
            Montant_Total,
            ID_Journal,
            NomUtilisateur,
            Mois,
            Numero_Piece
        ) VALUES (
            CONVERT(datetime, :date_saisie, 120), -- <-- MODIFICATION CLÉ ICI
            :description,
            :montant_total,
            :id_journal,
            :nom_utilisateur,
            :mois,
            :numero_piece
        )";

        $stmt = $pdo->prepare($sql);

        // Pas besoin de NumeroAgenceSCE ni Cde si vous les ignorez comme demandé précédemment
        // et que les colonnes sont nullables dans la BDD.

        $stmt->bindParam(':date_saisie', $dateSaisie); // Toujours bind en tant que chaîne
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':montant_total', $montantTotal);
        $stmt->bindParam(':id_journal', $idJournal, PDO::PARAM_INT);
        $stmt->bindParam(':nom_utilisateur', $nomUtilisateur);
        $stmt->bindParam(':mois', $mois);
        $stmt->bindParam(':numero_piece', $numeroPiece);

        if ($stmt->execute()) {
            return $pdo->lastInsertId();
        } else {
            $errorInfo = $stmt->errorInfo();
            $errorMessage = "SQLSTATE: " . ($errorInfo[0] ?? 'N/A') . " | Code: " . ($errorInfo[1] ?? 'N/A') . " | Message: " . ($errorInfo[2] ?? 'Unknown error');
            throw new PDOException("Échec de l'exécution de la requête INSERT dans Ecritures: " . $errorMessage);
        }
    } catch (PDOException $e) {
        throw $e; // Re-lance l'exception pour que process_payment.php puisse la capturer et l'afficher.
    }
}

/**
 * Récupère l'ID du compte d'un fournisseur depuis la table PLN.
 * Vous avez fourni PLN avec 'Cpt' et 'Lib'. Assumons que 'Cpt' est le Numero_Compte
 * et 'Lib' est le Nom_Fournisseur. Vous devrez mapper cet ID à un ID_Compte de Comptes_compta.
 * Pour cet exemple, nous allons directement chercher l'ID_Compte dans Comptes_compta
 * en utilisant le 'Cpt' de PLN comme 'Numero_Compte'.
 *
 * @param PDO $pdo L'objet PDO.
 * @param string $nomFournisseur Le nom du fournisseur (Lib de PLN).
 * @return int|false L'ID du compte (ID_Compte) du fournisseur dans Comptes_compta ou false si non trouvé.
 */
function getCompteFournisseurId(PDO $pdo, $nomFournisseur) {
    try {
        // D'abord, trouver le Cpt (Numéro de Compte) dans PLN
        $stmtPln = $pdo->prepare("SELECT Cpt FROM PLN WHERE Lib = :nom_fournisseur");
        $stmtPln->execute([':nom_fournisseur' => $nomFournisseur]);
        $numeroComptePln = $stmtPln->fetchColumn();

        if (!$numeroComptePln) {
            return false; // Fournisseur non trouvé dans PLN
        }

        // Ensuite, trouver l'ID_Compte correspondant dans Comptes_compta
        $stmtCompte = $pdo->prepare("SELECT ID_Compte FROM Comptes_compta WHERE Numero_Compte = :numero_compte");
        $stmtCompte->execute([':numero_compte' => $numeroComptePln]);
        $idCompte = $stmtCompte->fetchColumn();

        return $idCompte ? (int)$idCompte : false;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de l'ID compte fournisseur: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère l'ID du compte bancaire depuis la table Comptes_compta.
 * Pour cet exemple, on peut récupérer le premier compte de type 'Banque'.
 * Vous devriez avoir une logique plus robuste pour choisir la banque (ex: ID_Banque dans la config, ou sélection utilisateur).
 *
 * @param PDO $pdo L'objet PDO.
 * @return int|false L'ID du compte (ID_Compte) de la banque dans Comptes_compta ou false si non trouvé.
 */
function getCompteBanqueId(PDO $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT ID_Compte FROM Comptes_compta WHERE Type_Compte = 'Banque' ORDER BY ID_Compte ASC");
        $stmt->execute();
        $result = $stmt->fetchColumn();
        return $result ? (int)$result : false;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de l'ID compte banque: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère l'ID du journal par son code (Cde).
 *
 * @param PDO $pdo L'objet PDO.
 * @param string $codeJournal Le code du journal (ex: 'BQ' pour Banque).
 * @return int|false L'ID du journal (Cde de JAL) ou false si non trouvé.
 */
function getJournalIdByCode(PDO $pdo, $codeJournal) {
    try {
        // Assuming 'Lib' in JAL table stores the code/short name of the journal
        $stmt = $pdo->prepare("SELECT Cde FROM JAL WHERE Lib = :code_journal");
        $stmt->execute([':code_journal' => $codeJournal]);
        $result = $stmt->fetchColumn();
        return $result ? (int)$result : false;
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de l'ID du journal: " . $e->getMessage());
        return false;
    }
}

/**
 * Crée une ligne d'écriture détaillée pour une écriture comptable.
 *
 * @param PDO $pdo L'objet PDO.
 * @param int $idEcriture L'ID de l'écriture parente.
 * @param int $idCompte L'ID du compte concerné (ID_Compte de Comptes_compta).
 * @param float $montant Le montant de la ligne.
 * @param string $sens Le sens de l'écriture ('D' pour Débit, 'C' pour Crédit).
 * @param string $libelleLigne Le libellé de la ligne d'écriture.
 * @return bool True si succès, sinon false.
 */

function createLigneEcriture(
    PDO $pdo,
    int $idEcriture,
    int $idCompte,
    float $montant,
    string $sens,
    string $libelleLigne
    // Les paramètres $anCode et $contrepartieCompteId sont retirés car non présents dans le schéma de Lignes_Ecritures
): bool {
    try {
        $sql = "INSERT INTO lignes_ecritures (
            ID_Ecriture,
            ID_Compte,
            Montant,             -- Corrigé: Utilise 'Montant' comme dans votre schéma
            Sens,
            Libelle_Ligne
            -- 'is_reconciled', 'reconciled_at', 'reconciled_by' sont gérés par défaut ou non pertinents ici
        ) VALUES (
            :id_ecriture,
            :id_compte,
            :montant,
            :sens,
            :libelle_ligne
        )";
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
        $stmt->bindParam(':id_compte', $idCompte, PDO::PARAM_INT);
        $stmt->bindParam(':montant', $montant);
        $stmt->bindParam(':sens', $sens);
        $stmt->bindParam(':libelle_ligne', $libelleLigne);

        if ($stmt->execute()) {
            return true;
        } else {
            // Log les détails de l'erreur SQL
            error_log("Erreur createLigneEcriture: " . implode(" | ", $stmt->errorInfo()) . " | SQL: " . $sql . " | Params: " . json_encode([
                'id_ecriture' => $idEcriture,
                'id_compte' => $idCompte,
                'montant' => $montant,
                'sens' => $sens,
                'libelle_ligne' => $libelleLigne
            ]));
            return false;
        }
    } catch (PDOException $e) {
        // Log l'exception PDO
        error_log("PDOException createLigneEcriture: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
        return false;
    }
}



?>
<?php
// fonctions/gestion_ecritures.php

// ... (your existing createEcriture, getCompteFournisseurId, getCompteBanqueId, getJournalIdByCode, createLigneEcriture, getCompteRetenuesId functions) ...


/**
 * Récupère les détails d'une écriture comptable par son ID.
 * Inclut le code du journal (Lib de JAL).
 *
 * @param PDO $pdo L'objet PDO.
 * @param int $idEcriture L'ID de l'écriture à récupérer.
 * @return array|false Les détails de l'écriture ou false si non trouvée.
 */
function getEcritureById(PDO $pdo, int $idEcriture) {
    try {
        $sql = "SELECT E.*, J.Lib AS Code_Journal
                FROM ecritures E
                LEFT JOIN JAL J ON E.Cde = J.Cde -- Assumed JAL table has Cde and Lib for journal code
                WHERE E.ID_Ecriture = :id_ecriture";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id_ecriture' => $idEcriture]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de l'écriture par ID: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère toutes les lignes d'écritures pour une écriture comptable donnée.
 * Inclut les détails du compte (Numero_Compte, Nom_Compte).
 *
 * @param PDO $pdo L'objet PDO.
 * @param int $idEcriture L'ID de l'écriture parente.
 * @return array Un tableau de lignes d'écritures.
 */
function getLignesEcritureByEcritureId(PDO $pdo, int $idEcriture) {
    try {
        $sql = "SELECT LE.*, CC.Numero_Compte, CC.Nom_Compte
                FROM lignes_ecritures LE
                JOIN Comptes_compta CC ON LE.ID_Compte = CC.ID_Compte
                WHERE LE.ID_Ecriture = :id_ecriture
                AND LE.libelle_ligne NOT LIKE :libelle_to_exclude
                ORDER BY LE.ID_Ligne ASC";

        $stmt = $pdo->prepare($sql);

        $params = [
            ':id_ecriture' => $idEcriture,
            ':libelle_to_exclude' => '%Net à Payer%'
        ];

        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des lignes d'écriture: " . $e->getMessage());
        return [];
    }
}

// ... (your existing getEcrituresByFactureId and other functions) ...

?>