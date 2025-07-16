<?php
// pages/ecritures/GenEmprunt.php
// Script pour exporter les écritures comptables liées à un emprunt spécifique au format CSV

// Configuration d'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// Paramètres de la page
$titre = 'Exporter Écritures Comptables Emprunt CSV';
$current_page = basename(__FILE__);

// Connexion à la base de données
require_once('../../fonctions/database.php');

try {
    // Vérification de la connexion PDO
    if (!isset($pdo) || !$pdo instanceof PDO) {
        $serverName = "192.168.100.226";
        $databaseName = "BD_AD_SCE";
        $pdo = new PDO("sqlsrv:Server=$serverName;Database=$databaseName");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception("La connexion PDO n'a pas pu être initialisée.");
    }

    // Récupération des emprunts
    $emprunts = [];
    try {
        $sql_emprunts = "SELECT ID_Emprunt, Numero_Pret, Banque, Date_Mise_En_Place 
                         FROM Emprunts_Bancaires 
                         ORDER BY Date_Mise_En_Place DESC";
        $stmt_emprunts = $pdo->query($sql_emprunts);
        $emprunts = $stmt_emprunts->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $messageErreurChargement = 'Erreur lors du chargement des emprunts : ' . $e->getMessage();
        error_log("Erreur (GenEmprunt.php - chargement emprunts) : " . $e->getMessage());
        echo '<div class="container mt-4"><div class="alert alert-danger">' . htmlspecialchars($messageErreurChargement) . '</div></div>';
        if (file_exists('../../templates/footer.php')) {
            require_once('../../templates/footer.php');
        }
        exit();
    }

    // Traitement de la demande d'export CSV
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_csv'])) {
        $idEmprunt = $_POST['id_emprunt'] ?? null;

        if (empty($idEmprunt) || !filter_var($idEmprunt, FILTER_VALIDATE_INT)) {
            header('Location: ' . basename(__FILE__) . '?error=' . urlencode('Veuillez sélectionner un emprunt valide.'));
            exit();
        }

        // Requête SQL modifiée pour utiliser le champ Description comme lien
        $sql = "SELECT
                    e.Date_Saisie,
                    e.Cde AS Journal_Cde,
                    cc.Numero_Compte,
                    e.libelle2 AS Description_Ligne,
                    le.Montant AS Montant_Ligne,
                    le.Sens AS Sens_Ligne,
                    e.NumeroAgenceSCE,
                    e.NomUtilisateur,
                    e.Mois
                FROM
                    Ecritures e
                JOIN Lignes_Ecritures le ON e.ID_Ecriture = le.ID_Ecriture
                JOIN Comptes_compta cc ON le.ID_Compte = cc.ID_Compte
                WHERE e.Description LIKE :id_emprunt_pattern
                ORDER BY e.Date_Saisie, e.ID_Ecriture, le.ID_Ligne";

        try {
            // Création du pattern pour rechercher l'ID dans le champ Description
            $idPattern = '%' . $idEmprunt . '%';
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id_emprunt_pattern', $idPattern, PDO::PARAM_STR);
            $stmt->execute();
            $lignesEcritures = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($lignesEcritures)) {
                throw new Exception("Aucune écriture trouvée pour cet emprunt.");
            }

            // Génération du fichier CSV
            $stmtEmpruntNom = $pdo->prepare("SELECT Numero_Pret FROM Emprunts_Bancaires WHERE ID_Emprunt = :id_emprunt");
            $stmtEmpruntNom->bindParam(':id_emprunt', $idEmprunt, PDO::PARAM_INT);
            $stmtEmpruntNom->execute();
            $empruntNom = $stmtEmpruntNom->fetchColumn() ?? 'Emprunt';

            $filename = 'ecritures_' . preg_replace('/[^A-Za-z0-9\-]/', '', $empruntNom) . '_' . date('Ymd_His') . '.csv';

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Journal', 'Compte', 'Libelle', 'Debit', 'Credit', 'AgenceSCE', 'Utilisateur', 'Mois'], ';');

            foreach ($lignesEcritures as $row) {
                $dateSaisie = !empty($row['Date_Saisie']) ? (new DateTime($row['Date_Saisie']))->format('Y-m-d') : '';
                $debit = ($row['Sens_Ligne'] === 'D') ? $row['Montant_Ligne'] : 0;
                $credit = ($row['Sens_Ligne'] === 'C') ? $row['Montant_Ligne'] : 0;

                fputcsv($output, [
                    $dateSaisie,
                    $row['Journal_Cde'],
                    $row['Numero_Compte'],
                    $row['Description_Ligne'],
                    number_format($debit, 2, '.', ''),
                    number_format($credit, 2, '.', ''),
                    $row['NumeroAgenceSCE'],
                    $row['NomUtilisateur'],
                    $row['Mois']
                ], ';');
            }

            fclose($output);
            exit();

        } catch (PDOException $e) {
            $errorMessage = 'Erreur lors de la récupération des écritures : ' . $e->getMessage();
            error_log("Erreur (GenEmprunt.php - query) : " . $e->getMessage());
            header('Location: ' . basename(__FILE__) . '?error=' . urlencode($errorMessage));
            exit();
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            error_log("Erreur (GenEmprunt.php - processing) : " . $e->getMessage());
            header('Location: ' . basename(__FILE__) . '?error=' . urlencode($errorMessage));
            exit();
        }
    }

    // Affichage du formulaire
    if (file_exists('../../templates/header.php')) {
        require_once('../../templates/header.php');
    }
    if (file_exists('../../templates/navigation.php')) {
        require_once('../../templates/navigation.php');
    }

    // Gestion des messages
    $messageSucces = isset($_GET['success']) ? "<div class='alert alert-success'>" . htmlspecialchars(urldecode($_GET['success'])) . "</div>" : null;
    $messageInfo = isset($_GET['info']) ? "<div class='alert alert-info'>" . htmlspecialchars(urldecode($_GET['info'])) . "</div>" : null;
    $messageErreur = isset($_GET['error']) ? "<div class='alert alert-danger'>" . htmlspecialchars(urldecode($_GET['error'])) . "</div>" : null;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?= htmlspecialchars($titre) ?></title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/monstyle.css">
    <link rel="stylesheet" href="../../css/formulaire.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <div class="container">
        <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

        <?= $messageSucces ?>
        <?= $messageInfo ?>
        <?= $messageErreur ?>

        <p>Sélectionnez l'emprunt dont vous souhaitez exporter les écritures comptables liées au format CSV.</p>

        <form action="<?= htmlspecialchars(basename(__FILE__)) ?>" method="POST" class="form-horizontal">
            <input type="hidden" name="export_csv" value="1">

            <div class="form-group">
                <label for="id_emprunt" class="col-sm-3 control-label">Emprunt Bancaire</label>
                <div class="col-sm-9">
                    <select class="form-control" id="id_emprunt" name="id_emprunt" required style="height: 40px;">
                        <option value="">Sélectionner un emprunt</option>
                        <?php foreach ($emprunts as $emprunt): ?>
                            <option value="<?= htmlspecialchars($emprunt['ID_Emprunt']) ?>">
                                <?= htmlspecialchars($emprunt['Numero_Pret'] . ' - ' . $emprunt['Banque'] . ' (' . (!empty($emprunt['Date_Mise_En_Place']) ? date('d/m/Y', strtotime($emprunt['Date_Mise_En_Place'])) : 'N/A') . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-9">
                    <button type="submit" class="btn btn-primary">Exporter en CSV</button>
                </div>
            </div>
        </form>
    </div>

    <?php 
    if (file_exists('../../templates/footer.php')) {
        require_once('../../templates/footer.php');
    }
    ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>
</html>
<?php
} catch (Exception $e) {
    error_log("Erreur fatale (GenEmprunt.php) : " . $e->getMessage());
    echo '<div class="container mt-4"><div class="alert alert-danger">Une erreur critique est survenue. Veuillez contacter l\'administrateur. Message : ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    if (file_exists('../../templates/footer.php')) {
        require_once('../../templates/footer.php');
    }
}
?>