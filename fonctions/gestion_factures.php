<?php
// fonctions/gestion_factures.php
// Fonctions spécifiques aux factures (et à la génération d'écritures comptables)

/**
 * Récupère l'ID d'un compte comptable à partir de son numéro.
 *
 * @param PDO $pdo Instance de connexion à la base de données.
 * @param string $numeroCompte Le numéro du compte à rechercher.
 * @return int|false L'ID du compte si trouvé, false sinon ou en cas d'erreur.
 
 
 */
 
 

// fonctions/gestion_factures.php

// ... (other functions like getListeFactures, enregistrerFacture, marquerFacturePayee)

/**
 * Supprime une facture de la base de données par son ID.
 *
 * Cette fonction tente de supprimer une facture. Si la facture est liée
 * à des écritures comptables via une contrainte de clé étrangère (FK)
 * configurée pour RESTRICT ou NO ACTION, la suppression échouera.
 * Dans ce cas, il faudrait d'abord gérer l'écriture comptable associée
 * (par exemple, la supprimer ou la dissocier) avant de supprimer la facture.
 *
 * @param PDO $pdo L'objet PDO connecté à la base de données.
 * @param int $factureId L'ID unique de la facture à supprimer.
 * @return bool Retourne true si la suppression a réussi, false sinon.
 */
 function getFilteredFactures(PDO $pdo, $statusFilter = '', $searchTerm = '', $sortBy = 'ID_Facture', $sortDirection = 'DESC') {
    $sql = "SELECT
                ID_Facture,
                Numero_Facture,
                Date_Emission,
                Date_Reception,
                Date_Echeance,
                Montant_HT,
                Montant_TVA,
                Montant_TTC,
                Statut_Facture,
                Date_Comptabilisation,
                ID_Ecriture_Comptable,
                ID_Journal,
                Numero_Bon_Commande,
                Commentaire,
                Nom_Fournisseur,
                Montant_Net_A_Payer,
                'Fournisseur' AS Type_Facture -- Assuming this is always 'Fournisseur' for now based on context
            FROM Factures";

    $conditions = [];
    $params = [];

    // Filter by Status
    if (!empty($statusFilter)) {
        $conditions[] = "Statut_Facture = :status_filter";
        $params[':status_filter'] = $statusFilter;
    }

    // Search by Numero_Facture or Nom_Fournisseur
    if (!empty($searchTerm)) {
        $conditions[] = "(Numero_Facture LIKE :search_term OR Nom_Fournisseur LIKE :search_term)";
        $params[':search_term'] = '%' . $searchTerm . '%';
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    // Add Sorting
    // Validate $sortBy and $sortDirection before appending to SQL (important for security)
    $allowedSortBy = [
        'ID_Facture', 'Numero_Facture', 'Date_Emission', 'Date_Reception',
        'Date_Echeance', 'Montant_TTC', 'Statut_Facture', 'Nom_Fournisseur',
        'Type_Facture' // Added Type_Facture here as it's assumed 'Fournisseur'
    ];
    $allowedSortDirection = ['ASC', 'DESC'];

    if (!in_array($sortBy, $allowedSortBy)) {
        $sortBy = 'ID_Facture'; // Default to a safe column
    }
    if (!in_array(strtoupper($sortDirection), $allowedSortDirection)) {
        $sortDirection = 'DESC'; // Default to DESC
    }

    $sql .= " ORDER BY " . $sortBy . " " . $sortDirection;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching filtered invoices: " . $e->getMessage());
        return [];
    }
}
 
 
function updateFactureStatutAndEcriture(PDO $pdo, int $idFacture, string $newStatut, int $idEcriture): bool
{
    try {
        $sql = "UPDATE Factures
                SET
                    Statut_Facture = :new_statut,
                    ID_Ecriture_Comptable = :id_ecriture,
                    Date_Comptabilisation = GETDATE() -- Use GETDATE() for SQL Server, NOW() for MySQL
                WHERE
                    ID_Facture = :id_facture";

        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':new_statut', $newStatut);
        $stmt->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
        $stmt->bindParam(':id_facture', $idFacture, PDO::PARAM_INT);

        if ($stmt->execute()) {
            // Check if any row was actually affected
            return $stmt->rowCount() > 0;
        } else {
            error_log("Erreur updateFactureStatutAndEcriture: " . implode(" | ", $stmt->errorInfo()));
            return false;
        }
    } catch (PDOException $e) {
        error_log("PDOException updateFactureStatutAndEcriture: " . $e->getMessage());
        return false;
    }
}

function deleteFacture(PDO $pdo, int $factureId): bool
{
    try {
        // Préparer la requête SQL pour la suppression
        $sql = "DELETE FROM Factures WHERE ID_Facture = :factureId";
        $stmt = $pdo->prepare($sql);

        // Exécuter la requête avec l'ID de la facture
        $result = $stmt->execute([':factureId' => $factureId]);

        // Retourner le résultat de l'exécution (true en cas de succès, false en cas d'échec)
        return $result;

    } catch (PDOException $e) {
        // En cas d'erreur de base de données (par exemple, violation de contrainte de clé étrangère),
        // journaliser l'erreur pour le débogage.
        error_log("Erreur PDO lors de la suppression de la facture (ID: $factureId): " . $e->getMessage());
        return false; // Indiquer que la suppression a échoué
    }
}


 
 
 
 
 function getFactureById(PDO $pdo, int $factureId)
{
    try {
        $sql = "SELECT * FROM Factures WHERE ID_Facture = :facture_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':facture_id' => $factureId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur PDO dans getFactureById: " . $e->getMessage());
        return false;
    }
}

function getRetenuesByFactureId(PDO $pdo, int $factureId): array
{
    $sql = "SELECT ID_Compte_Retenue, Montant_Retenue, Libelle_Retenue
            FROM Facture_Retenues
            WHERE ID_Facture = :facture_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':facture_id' => $factureId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function deleteEcrituresByFactureId(PDO $pdo, int $idFacture): bool {
    try {
        // Étape 1: Récupérer l'ID_Ecriture_Comptable de la facture
        $sqlGetId = "SELECT ID_Ecriture_Comptable FROM Factures WHERE ID_Facture = :id_facture";
        $stmtGetId = $pdo->prepare($sqlGetId);
        $stmtGetId->bindParam(':id_facture', $idFacture, PDO::PARAM_INT);
        $stmtGetId->execute();
        $result = $stmtGetId->fetch(PDO::FETCH_ASSOC);

        if (!$result || empty($result['ID_Ecriture_Comptable'])) {
            // Aucune écriture comptable n'est liée à cette facture,
            // donc on considère l'opération comme "réussie" car il n'y a rien à faire.
            return true;
        }

        $idEcritureComptable = $result['ID_Ecriture_Comptable'];

        // Étape 2: Procéder à la suppression dans une transaction
        $pdo->beginTransaction();

        // 2a. Supprimer les lignes comptables
        // (Supposer que la table des lignes s'appelle dbo.Lignes_Comptables)
        $sqlDeleteLignes = "DELETE FROM Lignes_Comptables WHERE ID_Ecriture_Comptable = :id_ecriture_comptable";
        $stmtDeleteLignes = $pdo->prepare($sqlDeleteLignes);
        $stmtDeleteLignes->bindParam(':id_ecriture_comptable', $idEcritureComptable, PDO::PARAM_INT);
        $stmtDeleteLignes->execute();
        // $rowCountLignes = $stmtDeleteLignes->rowCount(); // Optionnel: pour logguer le nombre de lignes supprimées

        // 2b. Supprimer l'en-tête de l'écriture comptable
        // (Supposer que la table des en-têtes s'appelle dbo.Ecritures_Comptables)
        $sqlDeleteEcriture = "DELETE FROM Ecritures_Comptables WHERE ID_Ecriture_Comptable = :id_ecriture_comptable";
        $stmtDeleteEcriture = $pdo->prepare($sqlDeleteEcriture);
        $stmtDeleteEcriture->bindParam(':id_ecriture_comptable', $idEcritureComptable, PDO::PARAM_INT);
        $stmtDeleteEcriture->execute();
        // $rowCountEcriture = $stmtDeleteEcriture->rowCount(); // Optionnel: pour vérifier qu'une écriture a été supprimée

        // if ($rowCountEcriture === 0) {
            // Si l'ID_Ecriture_Comptable dans Factures pointait vers une écriture inexistante,
            // cela pourrait être un problème de données. On pourrait choisir de rollBack ici.
            // error_log("Avertissement: Aucun en-tête d'écriture trouvé pour ID_Ecriture_Comptable {$idEcritureComptable} lors de la suppression pour Facture ID {$idFacture}.");
        // }
        
        // 2c. Mettre à jour la facture pour enlever la référence à l'écriture supprimée
        $sqlUpdateFacture = "UPDATE Factures SET ID_Ecriture_Comptable = NULL WHERE ID_Facture = :id_facture";
        $stmtUpdateFacture = $pdo->prepare($sqlUpdateFacture);
        $stmtUpdateFacture->bindParam(':id_facture', $idFacture, PDO::PARAM_INT);
        $stmtUpdateFacture->execute();

        // 2e. Valider la transaction
        $pdo->commit();
        return true;

    } catch (PDOException $e) {
        // En cas d'erreur, annuler la transaction si elle est active
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erreur PDO dans deleteEcrituresByFactureId pour Facture ID {$idFacture}: " . $e->getMessage());
        return false;
    }
}

function updateFactureStatus(PDO $pdo, int $factureId, string $newStatus, ?int $ecritureComptableId = null): bool
{
    // Start building the SQL query
    $sql = "UPDATE Factures SET Statut_Facture = :new_status";
    $params = [':new_status' => $newStatus, ':id_facture' => $factureId];

    // If an accounting entry ID is provided, add it to the update query
    if ($ecritureComptableId !== null) {
        $sql .= ", ID_Ecriture_Comptable = :ecriture_id";
        $params[':ecriture_id'] = $ecritureComptableId;
    }

    $sql .= " WHERE ID_Facture = :id_facture";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Check if any rows were affected
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        // Log the error for debugging purposes
        error_log("Erreur lors de la mise à jour du statut de la facture ID {$factureId}: " . $e->getMessage());
        // You might want to throw the exception or return false based on your error handling strategy
        return false;
    }
}


function obtenirIdCompteParNumero(PDO $pdo, string $numeroCompte): int|false
{
    $sql = "SELECT ID_Compte FROM Comptes_compta WHERE Numero_Compte = :numero_compte";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':numero_compte', $numeroCompte, PDO::PARAM_STR); // Spécifiez le type de paramètre
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && isset($result['ID_Compte'])) {
        return (int) $result['ID_Compte'];
    }

    return false;
}

/**
 * Génère les lignes d'écritures comptables pour une facture donnée.
 *
 * @param PDO $pdo Instance de connexion à la base de données.
 * @param int $factureId L'ID de la facture pour laquelle générer les écritures.
 * @return array|false Un tableau contenant les informations de l'écriture (ID_Ecriture, Date_Saisie, Description, Montant_Total)
 * en cas de succès, false en cas d'erreur ou si la facture a déjà été comptabilisée.
 */
function genererEcrituresComptablesFacture(PDO $pdo, int $factureId): array|false
{
    // Récupérer les informations de la facture
    $sqlFacture = "SELECT ID_Facture, Type_Facture, Numero_Facture, Date_Emission, Montant_HT, Montant_TVA, Montant_TTC, ID_Tiers, Nom_Tiers
                    FROM Factures
                    WHERE ID_Facture = :factureId";
    $stmtFacture = $pdo->prepare($sqlFacture);
    $stmtFacture->bindParam(':factureId', $factureId, PDO::PARAM_INT);
    $stmtFacture->execute();
    $facture = $stmtFacture->fetch(PDO::FETCH_ASSOC);

    if (!$facture) {
        error_log("Facture ID " . $factureId . " non trouvée.");
        return false;
    }

    if (isset($facture['ID_Ecriture_Comptable']) && $facture['ID_Ecriture_Comptable'] !== null) {
        error_log("La facture ID " . $factureId . " a déjà été comptabilisée (Ecriture ID " . $facture['ID_Ecriture_Comptable'] . ").");
        return false;
    }

    // Définir la description de l'écriture
    $dateEmissionFormattee = (new DateTime($facture['Date_Emission']))->format('d/m/Y');
    $descriptionEcriture = "Facture " . htmlspecialchars($facture['Type_Facture']) . " N° " . htmlspecialchars($facture['Numero_Facture']) . " du " . $dateEmissionFormattee  . " - " . htmlspecialchars($facture['Nom_Tiers']);

    // Début de la transaction pour assurer l'intégrité des données
    $pdo->beginTransaction();

    try {
        // 1. Créer l'enregistrement dans la table Ecritures
        $sqlEcriture = "INSERT INTO Ecritures (Date_Saisie, Description, Montant_Total)
                            VALUES (:date_saisie, :description, :montant_total);
                            SELECT LAST_INSERT_ID() AS ID_Ecriture;"; // Utilisation de LAST_INSERT_ID()
        $stmtEcriture = $pdo->prepare($sqlEcriture);
        $dateSaisie = new DateTime();
        $stmtEcriture->bindParam(':date_saisie', $dateSaisie->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmtEcriture->bindParam(':description', $descriptionEcriture, PDO::PARAM_STR);
        $stmtEcriture->bindParam(':montant_total', $facture['Montant_TTC'], PDO::PARAM_STR); // Utilisez PARAM_STR pour les montants
        $stmtEcriture->execute();
        $idEcriture = $stmtEcriture->fetch(PDO::FETCH_ASSOC)['ID_Ecriture'];

        if (!$idEcriture) {
            throw new Exception("Erreur lors de la création de l'écriture comptable.");
        }

        // 2. Déterminer les comptes à utiliser (Ceci est une logique simplifiée et devra être adaptée à votre plan comptable)
        $compteVenteHT = '411000'; // Exemple de compte de vente HT (à configurer)
        $compteTVACollectee = '445710'; // Exemple de compte de TVA collectée (à configurer)
        $compteClient = '411' . substr($facture['ID_Tiers'], 0, 3); // Exemple basé sur l'ID du tiers
        $compteAchatHT = '607000'; // Exemple de compte d'achat HT (à configurer)
        $compteTVADeductible = '445660'; // Exemple de compte de TVA déductible (à configurer)
        $compteFournisseur = '401' . substr($facture['ID_Tiers'], 0, 3); // Exemple basé sur l'ID du tiers

        // Récupérer les IDs des comptes
        $idCompteVenteHT = obtenirIdCompteParNumero($pdo, $compteVenteHT);
        $idCompteTVACollectee = obtenirIdCompteParNumero($pdo, $compteTVACollectee);
        $idCompteClient = obtenirIdCompteParNumero($pdo, $compteClient);
        $idCompteAchatHT = obtenirIdCompteParNumero($pdo, $compteAchatHT);
        $idCompteTVADeductible = obtenirIdCompteParNumero($pdo, $compteTVADeductible);
        $idCompteFournisseur = obtenirIdCompteParNumero($pdo, $compteFournisseur);

        if (!$idCompteVenteHT || !$idCompteTVACollectee || !$idCompteClient || !$idCompteAchatHT || !$idCompteTVADeductible || !$idCompteFournisseur) {
            throw new Exception("Erreur : Un ou plusieurs comptes comptables n'ont pas été trouvés.");
        }

        // 3. Créer les lignes d'écritures
        if ($facture['Type_Facture'] === 'Client') {
            // Ligne de débit (Client)
            $sqlLigneDebit = "INSERT INTO Lignes_Ecritures (ID_Ecriture, ID_Compte, Montant, Sens)
                                            VALUES (:id_ecriture, :id_compte, :montant, 'D')";
            $stmtLigneDebit = $pdo->prepare($sqlLigneDebit);
            $stmtLigneDebit->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
            $stmtLigneDebit->bindParam(':id_compte', $idCompteClient, PDO::PARAM_INT);
            $stmtLigneDebit->bindParam(':montant', $facture['Montant_TTC'], PDO::PARAM_STR);
            $stmtLigneDebit->execute();

            // Ligne de crédit (Vente HT)
            $sqlLigneCreditHT = "INSERT INTO Lignes_Ecritures (ID_Ecriture, ID_Compte, Montant, Sens)
                                            VALUES (:id_ecriture, :id_compte, :montant, 'C')";
            $stmtLigneCreditHT = $pdo->prepare($sqlLigneCreditHT);
            $stmtLigneCreditHT->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
            $stmtLigneCreditHT->bindParam(':id_compte', $idCompteVenteHT, PDO::PARAM_INT);
            $stmtLigneCreditHT->bindParam(':montant', $facture['Montant_HT'], PDO::PARAM_STR);
            $stmtLigneCreditHT->execute();

            // Ligne de crédit (TVA Collectée)
            $sqlLigneCreditTVA = "INSERT INTO Lignes_Ecritures (ID_Ecriture, ID_Compte, Montant, Sens)
                                            VALUES (:id_ecriture, :id_compte, :montant, 'C')";
            $stmtLigneCreditTVA = $pdo->prepare($sqlLigneCreditTVA);
            $stmtLigneCreditTVA->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
            $stmtLigneCreditTVA->bindParam(':id_compte', $idCompteTVACollectee, PDO::PARAM_INT);
            $stmtLigneCreditTVA->bindParam(':montant', $facture['Montant_TVA'], PDO::PARAM_STR);
            $stmtLigneCreditTVA->execute();
        } elseif ($facture['Type_Facture'] === 'Fournisseur') {
            // Ligne de débit (Achat HT)
            $sqlLigneDebitHT = "INSERT INTO Lignes_Ecritures (ID_Ecriture, ID_Compte, Montant, Sens)
                                            VALUES (:id_ecriture, :id_compte, :montant, 'D')";
            $stmtLigneDebitHT = $pdo->prepare($sqlLigneDebitHT);
            $stmtLigneDebitHT->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
            $stmtLigneDebitHT->bindParam(':id_compte', $idCompteAchatHT, PDO::PARAM_INT);
            $stmtLigneDebitHT->bindParam(':montant', $facture['Montant_HT'], PDO::PARAM_STR);
            $stmtLigneDebitHT->execute();

            // Ligne de débit (TVA Déductible)
            $sqlLigneDebitTVA = "INSERT INTO Lignes_Ecritures (ID_Ecriture, ID_Compte, Montant, Sens)
                                            VALUES (:id_ecriture, :id_compte, :montant, 'D')";
            $stmtLigneDebitTVA = $pdo->prepare($sqlLigneDebitTVA);
            $stmtLigneDebitTVA->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
            $stmtLigneDebitTVA->bindParam(':id_compte', $idCompteTVADeductible, PDO::PARAM_INT);
            $stmtLigneDebitTVA->bindParam(':montant', $facture['Montant_TVA'], PDO::PARAM_STR);
            $stmtLigneDebitTVA->execute();

            // Ligne de crédit (Fournisseur)
            $sqlLigneCredit = "INSERT INTO Lignes_Ecritures (ID_Ecriture, ID_Compte, Montant, Sens)
                                            VALUES (:id_ecriture, :id_compte, :montant, 'C')";
            $stmtLigneCredit = $pdo->prepare($sqlLigneCredit);
            $stmtLigneCredit->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
            $stmtLigneCredit->bindParam(':id_compte', $idCompteFournisseur, PDO::PARAM_INT);
            $stmtLigneCredit->bindParam(':montant', $facture['Montant_TTC'], PDO::PARAM_STR);
            $stmtLigneCredit->execute();
        } else {
            throw new Exception("Type de facture non géré : " . $facture['Type_Facture']);
        }

        // 4. Mettre à jour l'ID de l'écriture comptable dans la table Factures
        $sqlUpdateFacture = "UPDATE Factures
                                        SET ID_Journal = :id_ecriture_comptable,
                                            Date_Comptabilisation = :date_comptabilisation
                                        WHERE ID_Facture = :facture_id";
        $stmtUpdateFacture = $pdo->prepare($sqlUpdateFacture);
        $stmtUpdateFacture->bindParam(':id_ecriture_comptable', $idEcriture, PDO::PARAM_INT);
        $stmtUpdateFacture->bindParam(':date_comptabilisation', $dateSaisie->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmtUpdateFacture->bindParam(':facture_id', $factureId, PDO::PARAM_INT);
        $stmtUpdateFacture->execute();

        // Valider la transaction
        $pdo->commit();

        return [
            'ID_Ecriture' => $idEcriture,
            'Date_Saisie' => $dateSaisie->format('Y-m-d H:i:s'),
            'Description' => $descriptionEcriture,
            'Montant_Total' => $facture['Montant_TTC']
            // Vous pouvez ajouter plus d'informations si nécessaire
        ];
    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $pdo->rollBack();
        error_log("Erreur lors de la génération des écritures comptables pour la facture ID " . $factureId . ": " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère toutes les factures avec les informations du tiers associé.
 *
 * @param PDO $pdo Instance de connexion à la base de données.
 * @return array Un tableau associatif contenant les informations de toutes les factures.
 */
function getAllFactures(PDO $pdo): array
{
    try {
        // Requête SQL pour sélectionner toutes les factures et les informations du tiers associé.
        $sql = "SELECT
                    f.ID_Facture,
                    f.Numero_Facture,
                    f.Type_Facture,
                    f.Date_Emission,
                    f.Date_Reception,
                    f.Date_Echeance,
                    t.ID_Tiers,
                    t.Nom_Commercial AS Nom_Tiers,  -- Alias pour éviter la confusion avec un éventuel Nom_Commercial de la facture
                    f.Montant_TTC,
                    f.Statut_Facture,
                    f.Date_Comptabilisation
                FROM Facture f
                INNER JOIN Tiers t ON f.ID_Tiers = t.ID_Tiers
                ORDER BY f.Date_Emission DESC"; // Tri par date d'émission pour afficher les plus récentes en premier

        // Préparer la requête pour éviter les injections SQL.
        $stmt = $pdo->prepare($sql);

        // Exécuter la requête.
        $stmt->execute();

        // Récupérer toutes les lignes de résultat sous forme de tableau associatif.
        $factures = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Retourner le tableau contenant les informations des factures.
        return $factures;
    } catch (PDOException $e) {
        // En cas d'erreur, enregistrer l'erreur dans le journal des erreurs (à adapter selon votre configuration).
        error_log("Erreur PDO dans getAllFactures : " . $e->getMessage());
        // Retourner un tableau vide pour indiquer qu'une erreur s'est produite.
        return [];
    }
}

/**
 * Récupère les factures en fonction du type.
 *
 * @param PDO $pdo Instance de connexion à la base de données.
 * @param string|null $type Le type de facture à récupérer ('client' ou 'fournisseur').  Si null, récupère toutes les factures.
 * @return array Un tableau associatif contenant les informations des factures.
 */
function getFactures(PDO $pdo, ?string $type = null): array
{
    try {
        // Début de la construction de la requête SQL.
        $sql = "SELECT
                    f.ID_Facture,
                    f.Numero_Facture,
                    f.Type_Facture,
                    f.Date_Emission,
                    f.Date_Reception,
                    f.Date_Echeance,
                    t.ID_Tiers,
                    t.Nom_Commercial,
                    f.Montant_TTC,
                    f.Statut_Facture,
                    f.Date_Comptabilisation
                FROM Facture f
                INNER JOIN Tiers t ON f.ID_Tiers = t.ID_Tiers"; // Jointure avec la table Tiers pour récupérer le nom du fournisseur/client

        // Ajout d'une condition WHERE si un type est spécifié.
        if ($type) {
            $sql .= " WHERE f.Type_Facture = :type";
        }

        $sql .= " ORDER BY f.Date_Emission DESC"; // Tri par date d'émission, du plus récent au plus ancien

        // Préparation de la requête.
        $stmt = $pdo->prepare($sql);

        // Liaison du paramètre si un type est spécifié.
        if ($type) {
            $stmt->bindParam(':type', $type, PDO::PARAM_STR);
        }

        // Exécution de la requête.
        $stmt->execute();

        // Récupération de tous les résultats sous forme de tableau associatif.
        $factures = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Retourne le tableau des factures.
        return $factures;
    } catch (PDOException $e) {
        // En cas d'erreur, on enregistre l'erreur dans le journal (à adapter selon votre système de logs).
        error_log("Erreur PDO dans getFactures : " . $e->getMessage());
        // Retourne un tableau vide pour indiquer l'erreur.  Alternative: throw $e; pour remonter l'exception.
        return [];
    }
}

/**
 * Récupère les informations nécessaires pour modifier une facture, incluant les détails de la facture et la liste des fournisseurs ou des clients.
 *
 * @param PDO $pdo L'objet PDO de connexion à la base de données.
 * @param int $idFacture L'ID de la facture à modifier.
 * @param string $type Le type de facture ('fournisseur' ou 'client').
 * @return array Un tableau associatif contenant les informations de la facture et les tiers associés, ou un tableau vide en cas d'erreur.
 */
function getInfosModificationFacture(PDO $pdo, int $idFacture, string $type): array
{
    try {
        // Assainir le type pour prévenir les injections SQL.
        $type = ($type === 'fournisseur') ? 'fournisseur' : 'client';

        // Début de la transaction pour assurer la cohérence des données.
        $pdo->beginTransaction();

        // 1. Récupérer les informations de la facture à modifier.
        $sqlFacture = "SELECT
                                    f.ID_Facture,
                                    f.Numero_Facture,
                                    f.Type_Facture,
                                    f.Date_Emission,
                                    f.Date_Reception,
                                    f.Date_Echeance,
                                    f.ID_Tiers,
                                    f.Montant_TTC,
                                    f.Statut_Facture
                                FROM Facture f
                                WHERE f.ID_Facture = :idFacture AND f.Type_Facture = :type"; // Filtre par ID et Type

        $stmtFacture = $pdo->prepare($sqlFacture);
        $stmtFacture->bindParam(':idFacture', $idFacture, PDO::PARAM_INT);
        $stmtFacture->bindParam(':type', $type, PDO::PARAM_STR);
        $stmtFacture->execute();
        $facture = $stmtFacture->fetch(PDO::FETCH_ASSOC);

        if (!$facture) {
            $pdo->rollBack();
            return []; // Retourne un tableau vide si la facture n'est pas trouvée.
        }

        // 2. Récupérer la liste des fournisseurs ou des clients.
        $tableTiers = ($type === 'fournisseur') ? 'Fournisseur' : 'Client';
        $sqlTiers = "SELECT t.ID_Tiers, t.Nom_Commercial FROM Tiers t
                                   INNER JOIN $tableTiers ft ON t.ID_Tiers = ft.ID_Tiers";

        $stmtTiers = $pdo->prepare($sqlTiers);
        $stmtTiers->execute();
        $tiers = $stmtTiers->fetchAll(PDO::FETCH_ASSOC);

        // Validation de la transaction.
        $pdo->commit();

        // Retourner les données de la facture et la liste des tiers.
        return [
            'facture' => $facture,
            'tiers' => $tiers,
        ];
    } catch (PDOException $e) {
        // En cas d'erreur, annuler la transaction et enregistrer l'erreur.
        $pdo->rollBack();
        error_log("Erreur PDO dans getInfosModificationFacture : " . $e->getMessage());
        return [];
    }
}

/**
 * Modifie une facture dans la base de données.
 *
 * @param PDO $pdo L'objet PDO de connexion à la base de données.
 * @param int $idFacture L'ID de la facture à modifier.
 * @param string $numeroFacture Le nouveau numéro de facture.
 * @param string $dateEmission La nouvelle date d'émission.
 * @param string $dateReception La nouvelle date de réception.
 * @param string $dateEcheance La nouvelle date d'échéance.
 * @param int $tiersId Le nouvel ID du tiers (fournisseur ou client).
 * @param float $montantTTC Le nouveau montant TTC.
 * @param string $statutFacture Le nouveau statut de la facture.
  * @param string $type Le type de la facture ('fournisseur' ou 'client').
 * @return bool True en cas de succès, false en cas d'erreur.
 */
function modifierFacture(
    PDO $pdo,
    int $idFacture,
    string $numeroFacture,
    string $dateEmission,
    string $dateReception,
    string $dateEcheance,
    int $tiersId,
    float $montantTTC,
    string $statutFacture,
    string $type
): bool {
    try {
        // Assainir le type
        $type = ($type === 'fournisseur') ? 'fournisseur' : 'client';

        // Début de la transaction.
        $pdo->beginTransaction();

        // Requête SQL pour la mise à jour de la facture.
        $sql = "UPDATE Facture SET
                    Numero_Facture = :numeroFacture,
                    Date_Emission = :dateEmission,
                    Date_Reception = :dateReception,
                    Date_Echeance = :dateEcheance,
                    ID_Tiers = :tiersId,
                    Montant_TTC = :montantTTC,
                    Statut_Facture = :statutFacture
                WHERE ID_Facture = :idFacture AND Type_Facture = :type"; // Ajout du filtre sur le type

        // Préparation de la requête.
        $stmt = $pdo->prepare($sql);

        // Liaison des paramètres.
        $stmt->bindParam(':numeroFacture', $numeroFacture, PDO::PARAM_STR);
        $stmt->bindParam(':dateEmission', $dateEmission, PDO::PARAM_STR);
        $stmt->bindParam(':dateReception', $dateReception, PDO::PARAM_STR);
        $stmt->bindParam(':dateEcheance', $dateEcheance, PDO::PARAM_STR);
        $stmt->bindParam(':tiersId', $tiersId, PDO::PARAM_INT);
        $stmt->bindParam(':montantTTC', $montantTTC, PDO::PARAM_STR); // Utilisez PARAM_STR pour les nombres décimaux
        $stmt->bindParam(':statutFacture', $statutFacture, PDO::PARAM_STR);
        $stmt->bindParam(':idFacture', $idFacture, PDO::PARAM_INT);
        $stmt->bindParam(':type', $type, PDO::PARAM_STR);

        // Exécution de la requête.
        $stmt->execute();

        // Si une ligne a été affectée, la mise à jour a réussi.
        $success = ($stmt->rowCount() > 0);

        if ($success) {
            $pdo->commit();
            return true;
        } else {
            $pdo->rollBack();
            return false; // La facture n'a pas été trouvée ou aucune modification n'a été faite.
        }
    } catch (PDOException $e) {
        // En cas d'erreur, annuler la transaction et enregistrer l'erreur.
        $pdo->rollBack();
        error_log("Erreur PDO dans modifierFacture : " . $e->getMessage());
        return false; // Retourne false en cas d'erreur.
    }
}

/**
 * Insère une nouvelle facture dans la base de données.
 *
 * @param PDO $pdo Instance de connexion à la base de données.
 * @param string $typeFacture Le type de la facture ('Client' ou 'Fournisseur').
 * @param string $numeroFacture Le numéro de la facture.
 * @param string $dateEmission La date d'émission de la facture.
 * @param string $dateReception La date de réception de la facture.
 * @param string $dateEcheance La date d'échéance de la facture.
 * @param int $idTiers L'ID du tiers (client ou fournisseur) associé à la facture.
 * @param string $nomTiers Le nom du tiers.
 * @param float $montantHT Le montant hors taxes de la facture.
 * @param float $montantTVA Le montant de la TVA.
 * @param float $montantTTCLe montant toutes taxes comprises.
 * @param string $statutFacture Le statut de la facture.
 * @param string|null $dateComptabilisation La date de comptabilisation (peut être nulle).
 * @param string $codeJournal Le code du journal comptable.
 * @return int|false L'ID de la nouvelle facture en cas de succès, false en cas d'erreur.
 */

function getInfosSaisieFacture(PDO $pdo): array
{
    try {
        // Récupérer la liste des clients
        $sqlClients = "SELECT ID_Tiers, Nom_Commercial FROM Tiers WHERE Type_Tiers = 'Client' ORDER BY Nom_Commercial ASC";
        $stmtClients = $pdo->query($sqlClients);

        if ($stmtClients === false) {
            error_log("Erreur SQL (Clients): " . print_r($pdo->errorInfo(), true));
            return []; // Retourne un tableau vide en cas d'erreur
        }

        $clients = $stmtClients->fetchAll(PDO::FETCH_ASSOC);


        return [
            'clients' => $clients,
        ];
    } catch (PDOException $e) {
        // Log l'erreur et retourne un tableau vide
        error_log("Erreur de base de données : " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère la liste des factures clients.
 *
 * @param PDO $pdo Instance de connexion à la base de données.
 * @return array Un tableau associatif contenant les informations des factures clients.
 */
function getListeFacturesClients(PDO $pdo): array
{
    try {
        $sql = "SELECT
                    f.ID_Facture,
                    f.Numero_Facture,
                    f.Type_Facture,
                    f.Date_Emission,
                    f.Date_Reception,
                    f.Date_Echeance,
                    t.Nom_Commercial AS Nom_Tiers,
                    f.Montant_TTC,
                    f.Statut_Facture,
                    f.Date_Comptabilisation
                FROM
                    Facture f
                JOIN
                    Tiers t ON f.ID_Tiers = t.ID_Tiers
                WHERE
                    t.Type_Tiers = 'Client'
                ORDER BY
                    f.Date_Emission DESC"; // Tri par date d'émission, par exemple

        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            // Gestion d'erreur : afficher l'erreur SQL et retourner un tableau vide
            error_log("Erreur SQL: " . print_r($pdo->errorInfo(), true));
            return [];
        }
        $factures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $factures;
    } catch (PDOException $e) {
        // Gestion d'erreur : log l'erreur et retourner un tableau vide
        error_log("Erreur de base de données : " . $e->getMessage());
        return [];
    }
}

/**
 * Récupère la liste des clients.
 *
 * @param PDO $pdo Instance de connexion à la base de données.
 * @return array Un tableau associatif contenant les informations des clients.
 */
function getListeClients(PDO $pdo): array
{
    try {
        $sql = "SELECT ID_Tiers, Nom_Commercial, Adresse, Ville, Pays FROM Tiers WHERE Type_Tiers = 'Client'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $clients;
    } catch (PDOException $e) {
        // Gestion de l'erreur : log, message à l'utilisateur, etc.
        error_log("Erreur lors de la récupération de la liste des clients : " . $e->getMessage());
        return []; // Retourne un tableau vide pour indiquer une erreur
    }
}

function getListeFactures(PDO $pdo): array {
    try {
        // Colonnes à sélectionner depuis la table dbo.Factures
        // Note: 'Type_Facture' n'est pas présente dans le schéma de la table 'Factures'
        // que vous avez fourni. Si cette information est nécessaire, elle devra être
        // ajoutée à la table 'Factures' ou provenir d'une jointure avec une autre table
        // (par exemple, si ID_Journal peut être lié à un type).
        // Pour l'instant, elle n'est pas sélectionnée ici, et listes_factures.php
        // utilisera 'N/A' comme valeur par défaut.
        
        $sql = "SELECT 
                    ID_Facture, 
                    Numero_Facture, 
                    Date_Emission, 
                    Date_Reception, 
                    Date_Echeance, 
                    Montant_HT,      -- Inclus pour information, même si non affiché directement
                    Montant_TVA,     -- Inclus pour information, même si non affiché directement
                    Montant_TTC, 
                    Statut_Facture,
                    Nom_Fournisseur,
                    Date_Comptabilisation, -- Inclus pour information
                    ID_Journal             -- Inclus pour information (pourrait servir pour Type_Facture plus tard)
                FROM 
                    Factures  -- 'dbo.' est le schéma par défaut pour SQL Server, ajustez si besoin.
                ORDER BY 
                    Date_Emission DESC, ID_Facture DESC"; // Tri suggéré: les plus récentes en premier

        $stmt = $pdo->query($sql);
        
        // Récupérer toutes les lignes en tant que tableau associatif
        $factures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Si $factures est false (erreur) ou null, retourner un tableau vide
        return $factures ?: []; 

    } catch (PDOException $e) {
        // Enregistrement de l'erreur dans les logs du serveur
        error_log("Erreur PDO lors de la récupération de la liste des factures: " . $e->getMessage());
        
        // Pour l'utilisateur, il est préférable de ne pas afficher l'erreur technique.
        // Retourner un tableau vide permet à la page de s'afficher proprement avec "Aucune facture trouvée".
        // Si vous souhaitez notifier l'utilisateur d'une erreur de base de données plus explicitement,
        // vous pourriez lancer une exception personnalisée ou retourner un code d'erreur spécifique.
        // Pour ce contexte, un tableau vide est suffisant pour listes_factures.php.
        return [];
    }
}


/**
 * Récupère toutes les factures avec les détails des tiers.
 *
 * @param PDO $pdo Instance de connexion à la base de données.
 * @return array Un tableau associatif contenant les informations de toutes les factures avec les détails des tiers.
 */
function getToutesLesFactures(PDO $pdo): array
{
    try {
        $sql = "SELECT
                    f.ID_Facture,
                    f.Type_Facture,
                    f.Numero_Facture,
                    DATE_FORMAT(f.Date_Emission, '%Y-%m-%d') AS Date_Emission,
                    DATE_FORMAT(f.Date_Echeance, '%Y-%m-%d') AS Date_Echeance,
                    t.Nom_Commercial AS Nom_Tiers,
                    f.Montant_TTC,
                    f.Statut_Facture,
                    f.Date_Comptabilisation
                FROM
                    Factures f
                INNER JOIN
                    Tiers t ON f.ID_Tiers = t.ID_Tiers
                ORDER BY
                    f.Date_Emission DESC"; // Tri par date d'émission descendante (plus récent en premier)

        $stmt = $pdo->query($sql);

        if ($stmt === false) {
            // Gestion de l'erreur de requête (pour le débogage)
            error_log("Erreur SQL dans getToutesLesFactures : " . print_r($pdo->errorInfo(), true));
            return [];
        }

        $factures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $factures;
    } catch (PDOException $e) {
        // Gestion de l'erreur PDO (pour la production)
        error_log("Erreur PDO dans getToutesLesFactures : " . $e->getMessage());
        return [];
    }
}
