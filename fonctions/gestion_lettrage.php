<?php
/**
 * Fichier de fonctions pour la gestion du lettrage des écritures.
 */

require_once 'database.php'; // Assurez-vous que le chemin est correct

/**
 * Effectue le lettrage d'un ensemble de lignes d'écritures.
 *
 * @param PDO $pdo L'objet de connexion PDO.
 * @param array $lignes_ids Tableau des identifiants des lignes d'écritures à lettrer.
 * @param string $lettre_lettrage La lettre à assigner.
 * @return bool Retourne true en cas de succès, false sinon.
 * @throws Exception Si les lignes ne sont pas équilibrées ou en cas d'erreur de base de données.
 */
function effectuerLettrage(PDO $pdo, array $lignes_ids, string $lettre_lettrage): bool
{
    if (empty($lignes_ids) || empty($lettre_lettrage)) {
        throw new Exception("Veuillez sélectionner au moins une ligne et fournir une lettre de lettrage.");
    }

    // Convertir les IDs en une liste de chaînes pour la clause IN
    $placeholders = implode(',', array_fill(0, count($lignes_ids), '?'));
    
    // 1. Vérifier si les lignes sont déjà lettrées
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM Lignes_Ecritures WHERE ID_Ligne_Ecriture IN ($placeholders) AND Lettre_Lettrage IS NOT NULL");
    $checkStmt->execute($lignes_ids);
    if ($checkStmt->fetchColumn() > 0) {
        throw new Exception("Une ou plusieurs lignes sélectionnées sont déjà lettrées.");
    }

    // 2. Vérifier l'équilibre des montants
    $querySolde = "
        SELECT SUM(CASE WHEN Sens = 'C' THEN Montant ELSE 0 END) AS TotalCredit,
               SUM(CASE WHEN Sens = 'D' THEN Montant ELSE 0 END) AS TotalDebit
        FROM Lignes_Ecritures
        WHERE ID_Ligne_Ecriture IN ($placeholders)
    ";
    
    $stmtSolde = $pdo->prepare($querySolde);
    $stmtSolde->execute($lignes_ids);
    $solde = $stmtSolde->fetch(PDO::FETCH_ASSOC);

    if (abs($solde['TotalCredit'] - $solde['TotalDebit']) > 0.01) { // Tolérance de 0.01 pour les erreurs de virgule flottante
        throw new Exception("Les lignes sélectionnées ne sont pas équilibrées. Le total du débit est de " . number_format($solde['TotalDebit'], 2, ',', ' ') . " et le total du crédit est de " . number_format($solde['TotalCredit'], 2, ',', ' '));
    }

    // 3. Effectuer la mise à jour
    try {
        $pdo->beginTransaction();

        $updateStmt = $pdo->prepare("UPDATE Lignes_Ecritures SET Lettre_Lettrage = ? WHERE ID_Ligne_Ecriture IN ($placeholders)");
        $params = array_merge([$lettre_lettrage], $lignes_ids);
        $updateSuccess = $updateStmt->execute($params);

        if (!$updateSuccess) {
            throw new Exception("La mise à jour de la base de données a échoué.");
        }

        $pdo->commit();
        return true;

    } catch (PDOException $e) {
        $pdo->rollBack();
        throw new Exception("Erreur de base de données lors du lettrage : " . $e->getMessage());
    }
}