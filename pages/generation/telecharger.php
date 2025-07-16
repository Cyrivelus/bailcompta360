<?php
// pages/ecritures/GenEcriture.php
// Script pour exporter toutes les écritures comptables non encore exportées au format Excel
echo "<pre>";
print_r($lignesEcritures);
echo "</pre>";
exit();
// Démarrer la mise en mémoire tampon de sortie (Output Buffering)
ob_start();

// Configuration d'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// Paramètres de la page
$titre = 'Exporter Toutes les Écritures Comptables (Non Exportées)';
$current_page = basename(__FILE__);

// Connexion à la base de données
require_once('../../fonctions/database.php');

try {
    // Vérification de la connexion PDO
    if (!isset($pdo) || !$pdo instanceof PDO) {
        $serverName = "192.168.100.226";
        $databaseName = "BD_AD_SCE";
        $pdo = new PDO("sqlsrv:Server=$serverName;Database=$databaseName", null, null);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    $messageSucces = null;
    $messageInfo = null;
    $messageErreur = null;

    // Traitement de la demande d'export Excel
    if (isset($_GET['action']) && $_GET['action'] === 'export_all') {
        // Requête SQL pour récupérer les lignes non exportées
        $sql = "SELECT
                    e.ID_Ecriture,
                    e.Date_Saisie,
                    e.Cde AS Journal_Cde,
                    cc.Numero_Compte,
                    le.Libelle_Ligne,
                    le.Montant AS Montant_Ligne,
                    le.Sens AS Sens_Ligne,
                    e.Numero_Piece,
                    e.NomUtilisateur,
                    e.Mois
                FROM
                    Ecritures e
                JOIN Lignes_Ecritures le ON e.ID_Ecriture = le.ID_Ecriture
                JOIN Comptes_compta cc ON le.ID_Compte = cc.ID_Compte
                WHERE e.is_exported = 0
                ORDER BY e.ID_Ecriture ASC, le.ID_Ligne ASC";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $lignesEcritures = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $exported_ecriture_ids = [];

            if (empty($lignesEcritures)) {
                ob_end_clean();
                header('Location: ' . $current_page . '?info=' . urlencode('Aucune nouvelle écriture comptable à exporter n\'a été trouvée.'));
                exit();
            } else {
                // Génération du fichier Excel
                $filename = 'toutes_nouvelles_ecritures_' . date('Ymd_His') . '.xls';

                // Vider le buffer et envoyer les en-têtes
                ob_end_clean();
                
                header('Content-Type: application/vnd.ms-excel; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Pragma: no-cache');
                header('Expires: 0');

                // Écrire directement dans la sortie
                echo "\xEF\xBB\xBF"; // BOM UTF-8 pour Excel
                echo "Date Piece;Journal;Compte;Libelle Ligne;Debit;Credit;Num Piece;Utilisateur;Mois Comptable\n";

                foreach ($lignesEcritures as $row) {
                    $datePiece = !empty($row['Date_Saisie']) ? (new DateTime($row['Date_Saisie']))->format('Y-m-d') : '';
                    $debit = ($row['Sens_Ligne'] === 'D') ? number_format((float)$row['Montant_Ligne'], 2, '.', '') : '0.00';
                    $credit = ($row['Sens_Ligne'] === 'C') ? number_format((float)$row['Montant_Ligne'], 2, '.', '') : '0.00';

                    $line = [
                        $datePiece,
                        $row['Journal_Cde'],
                        $row['Numero_Compte'],
                        $row['Libelle_Ligne'],
                        $debit,
                        $credit,
                        $row['Numero_Piece'],
                        $row['NomUtilisateur'],
                        $row['Mois']
                    ];
                    
                    echo implode(';', $line) . "\n";

                    if (!in_array($row['ID_Ecriture'], $exported_ecriture_ids)) {
                        $exported_ecriture_ids[] = $row['ID_Ecriture'];
                    }
                }

                // Mise à jour du statut d'exportation
                if (!empty($exported_ecriture_ids)) {
                    $pdo->beginTransaction();
                    try {
                        $placeholders = implode(',', array_fill(0, count($exported_ecriture_ids), '?'));
                        $update_sql = "UPDATE Ecritures SET is_exported = 1, exported_at = GETDATE() WHERE ID_Ecriture IN ($placeholders)";
                        $stmt_update = $pdo->prepare($update_sql);
                        $stmt_update->execute($exported_ecriture_ids);
                        $pdo->commit();
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        error_log("Erreur mise à jour statut export: " . $e->getMessage());
                    }
                }

                exit();
            }
        } catch (PDOException $e) {
            $messageErreur = 'Erreur lors de la récupération des écritures : ' . $e->getMessage();
            error_log("Erreur (query export) : " . $e->getMessage());
            ob_end_clean();
            header('Location: ' . $current_page . '?error=' . urlencode($messageErreur));
            exit();
        }
    }

    // Affichage du formulaire
    if (file_exists('../../templates/header.php')) {
        require_once('../../templates/header.php');
    } else {
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>' . htmlspecialchars($titre) . '</title>';
        echo '<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1"></head><body><div class="container">';
    }
    
    if (file_exists('../../templates/navigation.php')) {
        require_once('../../templates/navigation.php');
    }

    // Gestion des messages
    $messageSucces = isset($_GET['success']) ? "<div class='alert alert-success'>" . htmlspecialchars(urldecode($_GET['success'])) . "</div>" : null;
    $messageInfo = isset($_GET['info']) ? "<div class='alert alert-info'>" . htmlspecialchars(urldecode($_GET['info'])) . "</div>" : null;
    $messageErreur = isset($_GET['error']) ? "<div class='alert alert-danger'>" . htmlspecialchars(urldecode($_GET['error'])) . "</div>" : null;
?>
<div class="container">
    <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

    <?= $messageSucces ?>
    <?= $messageInfo ?>
    <?= $messageErreur ?>

    <p>Ce script exporte toutes les écritures comptables qui n'ont pas encore été marquées comme "exportées".</p>
    <p>Cliquez sur le bouton ci-dessous pour lancer l'exportation.</p>

    <form name="GenEcriture" action="<?= htmlspecialchars($current_page) ?>" method="GET" class="form-horizontal">
        <div class="form-group">
            <div class="col-sm-offset-3 col-sm-9">
                <input type="hidden" name="action" value="export_all">
                <button type="submit" class="btn btn-primary">Lancer l'Exportation des Nouvelles Écritures</button>
            </div>
        </div>
    </form>
</div>
<?php
    if (file_exists('../../templates/footer.php')) {
        require_once('../../templates/footer.php');
    } else {
        echo '</div></body></html>';
    }

} catch (Exception $e) {
    error_log("Erreur fatale : " . $e->getMessage());
    ob_end_clean();
    echo '<div class="container mt-4"><div class="alert alert-danger">Une erreur critique est survenue. Veuillez contacter l\'administrateur. Message : ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    if (file_exists('../../templates/footer.php')) {
        require_once('../../templates/footer.php');
    }
}
?>