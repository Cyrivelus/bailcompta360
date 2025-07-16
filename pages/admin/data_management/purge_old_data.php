<?php
session_start();

// Check user authentication and authorization (only highly privileged admins)
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'super_admin') { // 'super_admin' or a custom 'data_manager' role
    header('Location: ../../../login.php'); // Redirect to login or unauthorized page
    exit;
}

require_once '../../../templates/header.php';
require_once '../../../templates/navigation.php';
require_once '../../../fonctions/database.php'; // For database connection
require_once '../../../fonctions/gestion_logs.php'; // For logging actions

$titre = 'Purger les Données Historiques';


$message = '';
$messageType = ''; // 'success', 'danger', 'info', 'warning'

// Define purgeable tables and their date columns
// IMPORTANT: Customize this array based on your actual database schema and retention policies!
$purgeableTables = [
    'Ecritures' => [
        'label' => 'Écritures (Journal des Transactions)',
        'date_column' => 'Date_Ecriture',
        'description' => 'Supprime les écritures financières passées une certaine date. Attention: Peut impacter les rapports historiques.'
    ],
    'Logs' => [
        'label' => 'Logs d\'Activité et d\'Erreur',
        'date_column' => 'log_timestamp',
        'description' => 'Supprime les enregistrements de log. Utile pour la performance mais réduit l\'historique d\'audit.'
    ],
    'Login_Attempts' => [
        'label' => 'Tentatives de Connexion',
        'date_column' => 'login_timestamp',
        'description' => 'Supprime l\'historique des tentatives de connexion. Important pour la performance, moins critique pour l\'historique long terme.'
    ],
    'Notifications' => [
        'label' => 'Notifications Utilisateur',
        'date_column' => 'created_at',
        'description' => 'Supprime les notifications utilisateur anciennes. Moins impactant sur les données financières.'
    ],
    // Add more tables as needed:
    // 'Factures' => ['label' => 'Factures', 'date_column' => 'Date_Facture'],
    // 'Paiements' => ['label' => 'Paiements', 'date_column' => 'Date_Paiement'],
];


// --- Handle Purge Action ---
if (isset($_POST['action']) && $_POST['action'] === 'purge_data') {
    $tableToPurge = $_POST['table_to_purge'] ?? '';
    $purgeDate = $_POST['purge_date'] ?? '';

    // Validate inputs
    if (!array_key_exists($tableToPurge, $purgeableTables)) {
        $message = "Erreur: Table invalide sélectionnée.";
        $messageType = 'danger';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $purgeDate)) {
        $message = "Erreur: Format de date invalide. Utilisez AAAA-MM-JJ.";
        $messageType = 'danger';
    } else {
        $tableName = $tableToPurge;
        $dateColumn = $purgeableTables[$tableName]['date_column'];
        $deleteBeforeDate = date('Y-m-d', strtotime($purgeDate)); // Ensure date is valid and formatted

        try {
            // Start a transaction for safety
            $pdo->beginTransaction();

            // Prepare the DELETE statement
            // Use prepared statements to prevent SQL injection for table name (though checked via array_key_exists)
            // and the date value.
            $sql = "DELETE FROM `{$tableName}` WHERE `{$dateColumn}` < :purge_date";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':purge_date', $deleteBeforeDate, PDO::PARAM_STR);
            $stmt->execute();

            $rowCount = $stmt->rowCount();
            $pdo->commit(); // Commit the transaction if successful

            $message = "Purge effectuée avec succès pour la table '{$purgeableTables[$tableName]['label']}'. <strong>{$rowCount}</strong> enregistrements supprimés avant le {$deleteBeforeDate}.";
            $messageType = 'success';
            logUserActivity("Purge de données effectuée par l'utilisateur ID: " . $_SESSION['user_id'] . ". Table: {$tableName}, Date: {$deleteBeforeDate}, Enregistrements supprimés: {$rowCount}.");

        } catch (PDOException $e) {
            $pdo->rollBack(); // Rollback on error
            $message = "Erreur lors de la purge des données pour la table '{$purgeableTables[$tableName]['label']}'. Détails: " . $e->getMessage();
            $messageType = 'danger';
            logApplicationError("Erreur PDO lors de la purge de données par l'utilisateur ID: " . $_SESSION['user_id'] . ". Table: {$tableName}, Date: {$deleteBeforeDate}. Erreur: " . $e->getMessage());
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Une erreur inattendue est survenue lors de la purge des données: " . $e->getMessage();
            $messageType = 'danger';
            logApplicationError("Erreur inattendue lors de la purge de données par l'utilisateur ID: " . $_SESSION['user_id'] . ". Table: {$tableName}, Date: {$deleteBeforeDate}. Erreur: " . $e->getMessage());
        }
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?= htmlspecialchars($titre) ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../css/style.css">
    <link rel="stylesheet" href="../../../css/tableau.css">
</head>
<body>
    <div class="container">
        <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="panel panel-danger">
            <div class="panel-heading">
                <h3 class="panel-title">Purger les Données Historiques <span class="text-danger">(Danger: Action Irréversible!)</span></h3>
            </div>
            <div class="panel-body">
                <p class="text-danger">
                    **Attention :** La purge des données supprime définitivement les enregistrements de la base de données.
                    **CETTE ACTION EST IRRÉVERSIBLE.**
                    **ASSUREZ-VOUS D'AVOIR UNE SAUVEGARDE RÉCENTE ET VALIDÉE DE LA BASE DE DONNÉES AVANT DE CONTINUER.**
                </p>
                <hr>

                <form method="POST" action="" onsubmit="return confirm('Êtes-vous ABSOLUMENT sûr de vouloir purger les données ? Cette action est IRRÉVERSIBLE et supprimera toutes les données de la table sélectionnée antérieures à la date spécifiée.');">
                    <div class="form-group">
                        <label for="table_to_purge">Sélectionner la table à purger :</label>
                        <select name="table_to_purge" id="table_to_purge" class="form-control" required>
                            <option value="">-- Choisir une table --</option>
                            <?php foreach ($purgeableTables as $tableName => $tableInfo): ?>
                                <option value="<?= htmlspecialchars($tableName) ?>">
                                    <?= htmlspecialchars($tableInfo['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="help-block">Sélectionnez la table de laquelle vous souhaitez supprimer des données anciennes.</p>
                    </div>

                    <div class="form-group">
                        <label for="purge_date">Purger les données antérieures à la date (AAAA-MM-JJ) :</label>
                        <input type="date" class="form-control" id="purge_date" name="purge_date" required>
                        <p class="help-block">Tous les enregistrements avec une date antérieure à celle-ci seront supprimés.</p>
                    </div>

                    <div class="form-group">
                        <label>Description de la purge sélectionnée :</label>
                        <p id="purge_description" class="well"></p>
                    </div>

                    <button type="submit" name="action" value="purge_data" class="btn btn-danger btn-lg">
                        <span class="glyphicon glyphicon-trash"></span> Purger les données maintenant
                    </button>
                </form>
            </div>
        </div>

    </div>

    <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
    <script>
        $(document).ready(function() {
            var purgeableTables = <?= json_encode($purgeableTables) ?>;

            $('#table_to_purge').change(function() {
                var selectedTable = $(this).val();
                if (selectedTable && purgeableTables[selectedTable]) {
                    $('#purge_description').text(purgeableTables[selectedTable].description);
                } else {
                    $('#purge_description').text('Veuillez sélectionner une table pour voir sa description.');
                }
            }).trigger('change'); // Trigger on load to show initial description
        });
    </script>
    <?php require_once '../../../templates/footer.php'; ?>
</body>
</html>