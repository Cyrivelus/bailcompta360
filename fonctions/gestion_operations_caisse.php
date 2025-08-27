<?php
// fonctions/gestion_operations_caisse.php

/**
 * Récupère le mouvement de caisse ouvert pour un utilisateur et la date d'aujourd'hui.
 * @param PDO $pdo L'instance PDO.
 * @param int $id_utilisateur L'ID de l'utilisateur.
 * @return array|false Le mouvement de caisse s'il existe, sinon false.
 */

/**
 * Calcule le solde courant de la caisse en se basant sur les transactions.
 * Vous devez avoir une table 'Transactions_Caisse' liée à 'Mouvements_Caisse'.
 * @param PDO $pdo
 * @param int $id_mouvement_caisse L'ID du mouvement de caisse ouvert.
 * @return float Le solde courant.
 */
function ouvrirCaisse(PDO $pdo, int $id_utilisateur, string $id_agence, float $solde_initial)
{
    $pdo->beginTransaction();

    try {
        // Enregistrer l'ouverture dans la table des mouvements de caisse
        $stmt = $pdo->prepare("INSERT INTO Mouvements_Caisse (ID_Utilisateur, date_ouverture, solde_initial, CodeAgence) VALUES (?, NOW(), ?, ?)");
        $stmt->execute([$id_utilisateur, $solde_initial, $id_agence]);
        $id_mouvement = $pdo->lastInsertId();

        // Créer l'écriture comptable pour le solde initial (débit de la caisse)
        // Compte de caisse (exemple : 5710000000) et un compte de contrepartie (Banque : 5210000000)
        $compte_caisse = '571090000000';
        $compte_contrepartie_ouverture = '5210000000'; 

        // Insérer l'écriture principale
        $stmt_ecriture = $pdo->prepare("INSERT INTO ecritures (Date_Saisie, Description, Montant_Total, NumeroAgenceSCE, NomUtilisateur, Mois) VALUES (NOW(), ?, ?, ?, ?, ?)");
        $stmt_ecriture->execute([
            "Ouverture de caisse - Solde Initial",
            $solde_initial,
            $id_agence,
            $_SESSION['nom_utilisateur'] ?? 'Système', 
            date('Y-m')
        ]);
        $id_ecriture = $pdo->lastInsertId();

        // Ligne de débit : Solde initial de la caisse
        $stmt_ligne_debit = $pdo->prepare("INSERT INTO lignes_ecritures (ID_Ecriture, ID_Compte, Montant, Sens, Libelle_Ligne) VALUES (?, ?, ?, 'D', 'Solde Initial Caisse')");
        $stmt_ligne_debit->execute([$id_ecriture, $compte_caisse, $solde_initial]);

        // Ligne de crédit : Contrepartie de l'ouverture
        $stmt_ligne_credit = $pdo->prepare("INSERT INTO lignes_ecritures (ID_Ecriture, ID_Compte, Montant, Sens, Libelle_Ligne) VALUES (?, ?, ?, 'C', 'Contrepartie Ouverture Caisse')");
        $stmt_ligne_credit->execute([$id_ecriture, $compte_contrepartie_ouverture, $solde_initial]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur lors de l'ouverture de caisse : " . $e->getMessage());
        throw new Exception("Échec de l'ouverture de la caisse. Erreur interne.");
    }
}

function getComptesCaisse(PDO $pdo): array
{
    try {
        $stmt = $pdo->prepare("SELECT Numero_Compte, Nom_Compte FROM comptes_compta WHERE Nom_Compte LIKE '%CAISSE%' ORDER BY Nom_Compte");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération des comptes de caisse : " . $e->getMessage());
        return [];
    }
}

/**
 * Ferme la caisse et génère l'écriture comptable pour l'écart.
 * @param PDO $pdo
 * @param int $id_mouvement_caisse
 * @param float $solde_final_declare
 * @param string $id_agence
 * @throws Exception
 */
function fermerCaisse(PDO $pdo, int $id_mouvement_caisse, float $solde_final_declare, string $id_agence)
{
    $pdo->beginTransaction();

    try {
        // 1. Calculer le solde théorique
        $solde_courant_calcule = calculerSoldeCourant($pdo, $id_mouvement_caisse);
        $ecart = $solde_final_declare - $solde_courant_calcule;

        // 2. Mettre à jour le mouvement de caisse
        $stmt = $pdo->prepare("UPDATE Mouvements_Caisse SET date_fermeture = NOW(), solde_final = ?, ecart = ? WHERE id_mouvement_caisse = ?");
        $stmt->execute([$solde_final_declare, $ecart, $id_mouvement_caisse]);

        // 3. Générer l'écriture comptable de l'écart (si un écart existe)
        if (abs($ecart) > 0.01) { // Tolérance de 0.01 pour les arrondis
            $compte_caisse = '571090000000';
            $compte_boni = '7580000000'; // Compte de boni de caisse
            $compte_mali = '6580000000'; // Compte de mali de caisse

            $sens = ($ecart > 0) ? 'D' : 'C'; // Débit si boni, Crédit si mali
            $compte_ecart = ($ecart > 0) ? $compte_boni : $compte_mali;
            $montant_ecart = abs($ecart);

            $description_ecart = ($ecart > 0) ? "Boni de caisse" : "Mali de caisse";
            
            // Insérer l'écriture principale de l'écart
            $stmt_ecriture = $pdo->prepare("INSERT INTO ecritures (Date_Saisie, Description, Montant_Total, NumeroAgenceSCE, NomUtilisateur, Mois) VALUES (NOW(), ?, ?, ?, ?, ?)");
            $stmt_ecriture->execute([
                $description_ecart,
                $montant_ecart,
                $id_agence,
                $_SESSION['nom_utilisateur'] ?? 'Système', 
                date('Y-m')
            ]);
            $id_ecriture = $pdo->lastInsertId();

            // Ligne de l'écart (boni ou mali)
            $stmt_ligne_ecart = $pdo->prepare("INSERT INTO lignes_ecritures (ID_Ecriture, ID_Compte, Montant, Sens, Libelle_Ligne) VALUES (?, ?, ?, ?, ?)");
            $stmt_ligne_ecart->execute([$id_ecriture, $compte_ecart, $montant_ecart, $sens, $description_ecart]);
            
            // Ligne de contrepartie (débit ou crédit de la caisse)
            $sens_caisse = ($ecart > 0) ? 'C' : 'D';
            $stmt_ligne_caisse = $pdo->prepare("INSERT INTO lignes_ecritures (ID_Ecriture, ID_Compte, Montant, Sens, Libelle_Ligne) VALUES (?, ?, ?, ?, ?)");
            $stmt_ligne_caisse->execute([$id_ecriture, $compte_caisse, $montant_ecart, $sens_caisse, "Ajustement de caisse"]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur lors de la fermeture de caisse : " . $e->getMessage());
        throw new Exception("Échec de la fermeture de la caisse. Erreur interne.");
    }
}

/**
 * Calcule le solde courant d'une caisse.
 * @param PDO $pdo
 * @param int $id_mouvement_caisse
 * @return float
 */
function calculerSoldeCourant(PDO $pdo, int $id_mouvement_caisse): float
{
    try {
        $stmt_solde_initial = $pdo->prepare("SELECT solde_initial FROM Mouvements_Caisse WHERE id_mouvement_caisse = ?");
        $stmt_solde_initial->execute([$id_mouvement_caisse]);
        $solde_initial = $stmt_solde_initial->fetchColumn() ?? 0.0;

        $stmt_total_entrees = $pdo->prepare("SELECT SUM(montant) FROM Transactions_Caisse WHERE id_mouvement_caisse = ? AND type = 'Entrée'");
        $stmt_total_entrees->execute([$id_mouvement_caisse]);
        $total_entrees = $stmt_total_entrees->fetchColumn() ?? 0.0;

        $stmt_total_sorties = $pdo->prepare("SELECT SUM(montant) FROM Transactions_Caisse WHERE id_mouvement_caisse = ? AND type = 'Sortie'");
        $stmt_total_sorties->execute([$id_mouvement_caisse]);
        $total_sorties = $stmt_total_sorties->fetchColumn() ?? 0.0;
        
        return $solde_initial + $total_entrees - $total_sorties;
    } catch (PDOException $e) {
        error_log("Erreur PDO lors du calcul du solde courant : " . $e->getMessage());
        return 0.0;
    }
}

/**
 * Récupère le mouvement de caisse actuellement ouvert pour un utilisateur.
 * @param PDO $pdo
 * @param int $id_utilisateur
 * @return array|false
 */
function getCaisseOuverte(PDO $pdo, int $id_utilisateur): array|false
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM Mouvements_Caisse WHERE ID_Utilisateur = ? AND date_fermeture IS NULL LIMIT 1");
        $stmt->execute([$id_utilisateur]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la vérification de la caisse ouverte : " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère l'historique complet des mouvements de caisse.
 * @param PDO $pdo
 * @return array
 */

function ajouterTransactionCaisse(PDO $pdo, int $id_mouvement_caisse, float $montant, string $type, string $description, string $id_agence, string $nom_utilisateur, string $compte_contrepartie)
{
    // Démarrer une transaction pour garantir l'intégrité
    $pdo->beginTransaction();

    try {
        // Déterminer le sens de l'écriture comptable
        $sens_caisse = ($type === 'Entree') ? 'D' : 'C';
        $sens_contrepartie = ($type === 'Entree') ? 'C' : 'D';

        // Compte de caisse principal (à adapter si vous avez des comptes de caisse par agence)
        // Note : Ce compte devrait être dynamique, basé sur l'agence de l'utilisateur.
        $compte_caisse_principal = '571090000000'; // Par exemple, "CAISSE YAOUNDE"

        // 1. Enregistrer la transaction dans la table "Transactions_Caisse"
        $stmt_transaction = $pdo->prepare("INSERT INTO Transactions_Caisse (id_mouvement_caisse, date_transaction, montant, type, description) VALUES (?, NOW(), ?, ?, ?)");
        $stmt_transaction->execute([$id_mouvement_caisse, $montant, $type, $description]);
        $id_transaction_caisse = $pdo->lastInsertId();

        // 2. Créer une nouvelle écriture comptable dans la table "ecritures"
        $stmt_ecriture = $pdo->prepare("INSERT INTO ecritures (Date_Saisie, Description, Montant_Total, NumeroAgenceSCE, NomUtilisateur, Mois) VALUES (NOW(), ?, ?, ?, ?, ?)");
        $stmt_ecriture->execute([
            "Transaction de caisse - " . $description,
            $montant,
            $id_agence,
            $nom_utilisateur,
            date('Y-m') // Format YYYY-MM
        ]);
        $id_ecriture = $pdo->lastInsertId();

        // 3. Créer la ligne de l'écriture pour le compte de caisse
        $stmt_ligne_caisse = $pdo->prepare("INSERT INTO lignes_ecritures (ID_Ecriture, ID_Compte, Montant, Sens, Libelle_Ligne, id_transaction) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_ligne_caisse->execute([$id_ecriture, $compte_caisse_principal, $montant, $sens_caisse, $description, $id_transaction_caisse]);

        // 4. Créer la ligne de contrepartie
        $stmt_ligne_contrepartie = $pdo->prepare("INSERT INTO lignes_ecritures (ID_Ecriture, ID_Compte, Montant, Sens, Libelle_Ligne, id_transaction) VALUES (?, ?, ?, ?, 'Contrepartie transaction caisse', ?)");
        $stmt_ligne_contrepartie->execute([$id_ecriture, $compte_contrepartie, $montant, $sens_contrepartie, $id_transaction_caisse]);

        // 5. Valider la transaction si tout a réussi
        $pdo->commit();
    } catch (Exception $e) {
        // En cas d'erreur, annuler toutes les opérations
        $pdo->rollBack();
        error_log("Erreur lors de l'ajout d'une transaction de caisse : " . $e->getMessage());
        throw new Exception("Échec de l'enregistrement de la transaction : " . $e->getMessage());
    }
}

function getComptesContrepartie(PDO $pdo): array
{
    try {
        $stmt = $pdo->prepare("SELECT Numero_Compte, Nom_Compte FROM comptes_compta WHERE Nom_Compte NOT LIKE '%CAISSE%' ORDER BY Numero_Compte");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération des comptes de contrepartie : " . $e->getMessage());
        return [];
    }
}

function getTransactionsCaisse(PDO $pdo, int $id_mouvement_caisse): array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM Transactions_Caisse WHERE id_mouvement_caisse = ? ORDER BY date_transaction DESC");
        $stmt->execute([$id_mouvement_caisse]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération des transactions de caisse : " . $e->getMessage());
        return [];
    }
}




function getHistoriqueCaisses(PDO $pdo): array
{
    try {
        $query = "
            SELECT 
                mc.*,
                u.nom_utilisateur,
                (mc.solde_initial + COALESCE(SUM(CASE WHEN tc.type = 'Entrée' THEN tc.montant ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN tc.type = 'Sortie' THEN tc.montant ELSE 0 END), 0)) AS solde_final_calcule
            FROM Mouvements_Caisse mc
            LEFT JOIN Transactions_Caisse tc ON mc.id_mouvement_caisse = tc.id_mouvement_caisse
            JOIN utilisateurs u ON mc.ID_Utilisateur = u.ID_Utilisateur
            GROUP BY mc.id_mouvement_caisse
            ORDER BY mc.date_ouverture DESC
        ";
        $stmt = $pdo->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération de l'historique des caisses : " . $e->getMessage());
        return [];
    }
}

/**
 * Supprime le solde initial et final d'un mouvement de caisse fermé.
 * @param PDO $pdo
 * @param int $id_mouvement
 * @throws Exception
 */
function supprimerSoldeCaisse(PDO $pdo, int $id_mouvement)
{
    $pdo->beginTransaction();

    try {
        // Récupérer le mouvement de caisse
        $stmt_mouvement = $pdo->prepare("SELECT * FROM Mouvements_Caisse WHERE id_mouvement_caisse = ? AND date_fermeture IS NOT NULL");
        $stmt_mouvement->execute([$id_mouvement]);
        $mouvement = $stmt_mouvement->fetch(PDO::FETCH_ASSOC);

        if (!$mouvement) {
            throw new Exception("Mouvement de caisse non trouvé ou déjà ouvert.");
        }

        // Réinitialiser les soldes dans la table des mouvements
        $stmt_update = $pdo->prepare("UPDATE Mouvements_Caisse SET solde_initial = 0, solde_final = 0, ecart = 0, date_fermeture = NULL WHERE id_mouvement_caisse = ?");
        $stmt_update->execute([$id_mouvement]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur lors de la suppression des soldes : " . $e->getMessage());
        throw new Exception("Échec de la suppression des soldes.");
    }
}
?>