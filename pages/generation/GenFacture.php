<?php
// pages/ecritures/GenFacture.php
// Script pour exporter les écritures comptables liées à une facture spécifique au format Excel

// Configuration d'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// Paramètres de la page
$titre = 'Exporter Écritures Comptables Facture Excel';
$current_page = basename(__FILE__); // Garde le nom du script actuel (GenFacture.php)

// Connexion à la base de données
require_once('../../fonctions/database.php');

try {
    // Vérification de la connexion PDO après inclusion
    if (!isset($pdo) || !$pdo instanceof PDO) {
         // Si database.php n'a pas initialisé $pdo, tenter une connexion ici
         // NOTE : Idéalement, la connexion devrait être GÉRÉE UNIQUEMENT dans database.php
         // et configurée là-bas avec CharacterSet=UTF-8 pour SQL Server.
         // Ce bloc est un fallback, mais la correction durable est dans database.php.
        $serverName = "192.168.100.226";
        $databaseName = "BD_AD_SCE";
         // Ajouter CharacterSet=UTF-8 ici aussi si non configuré dans database.php et que l'erreur d'encodage persiste
         // $pdo = new PDO("sqlsrv:Server=$serverName;Database=$databaseName;CharacterSet=UTF-8", null, null);
        $pdo = new PDO("sqlsrv:Server=$serverName;Database=$databaseName", null, null); // Connexion simple si database.php ne le fait pas
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // Vérification finale que $pdo est bien une instance de PDO
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception("La connexion PDO n'a pas pu être initialisée.");
    }

    // --- Récupération de la liste des factures pour le formulaire ---
    $factures = [];
    try {
        // Sélection des factures avec des informations pertinentes pour la liste déroulante
        // On sélectionne aussi ID_Ecriture_Comptable pour s'assurer qu'une écriture est liée
        $sql_factures = "SELECT ID_Facture, Numero_Facture, Date_Emission, Nom_Fournisseur, ID_Ecriture_Comptable
                         FROM Factures
                         ORDER BY Date_Emission DESC, Numero_Facture ASC";
        $stmt_factures = $pdo->query($sql_factures);
        $factures = $stmt_factures->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $messageErreurChargement = 'Erreur lors du chargement des factures : ' . $e->getMessage();
        error_log("Erreur (GenFacture.php - chargement factures) : " . $e->getMessage());
         // Afficher l'erreur et arrêter l'exécution pour ne pas afficher le formulaire vide
        echo '<div class="container mt-4"><div class="alert alert-danger">' . htmlspecialchars($messageErreurChargement) . '</div></div>';
         if (file_exists('../../templates/footer.php')) {
             require_once('../../templates/footer.php');
         }
         exit();
    }

    // --- Traitement de la demande d'export Excel (si formulaire soumis) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_excel'])) {
        $idFacture = $_POST['id_facture'] ?? null;

        // Validation de l'ID de la facture
        if (empty($idFacture) || !filter_var($idFacture, FILTER_VALIDATE_INT)) {
            header('Location: ' . $current_page . '?error=' . urlencode('Veuillez sélectionner une facture valide.'));
            exit();
        }

        // --- Récupérer l'écriture liée à cette facture ---
        $stmtGetEcritureId = $pdo->prepare("SELECT ID_Ecriture_Comptable, Numero_Facture FROM Factures WHERE ID_Facture = :id_facture");
        $stmtGetEcritureId->bindParam(':id_facture', $idFacture, PDO::PARAM_INT);
        $stmtGetEcritureId->execute();
        $factureInfo = $stmtGetEcritureId->fetch(PDO::FETCH_ASSOC);

        $idEcriture = $factureInfo['ID_Ecriture_Comptable'] ?? null;
        $numeroFacture = $factureInfo['Numero_Facture'];

        // --- Requête SQL pour récupérer les lignes de l'écriture comptable liée ---
        // On joint Ecritures, Lignes_Ecritures et Comptes_compta
        $sql = "SELECT
                    e.Date_Saisie,
                    e.Cde AS Journal_Cde, -- Code Journal
                    cc.Numero_Compte,     -- Numéro de Compte
                    le.Libelle_Ligne,     -- Libellé spécifique de la ligne
                    le.Montant AS Montant_Ligne,
                    le.Sens AS Sens_Ligne,
                    e.Numero_Piece,       -- Numéro de Pièce de l'écriture (souvent lié au N° Facture)
                    e.NomUtilisateur,
                    e.Mois                -- Mois Comptable
                FROM
                    Ecritures e
                JOIN Lignes_Ecritures le ON e.ID_Ecriture = le.ID_Ecriture
                JOIN Comptes_compta cc ON le.ID_Compte = cc.ID_Compte
                WHERE e.ID_Ecriture = :id_ecriture -- Filtre direct par l'ID de l'écriture liée à la facture
                ORDER BY le.ID_Ligne"; // Ordre des lignes dans l'écriture

        try {
            $lignesEcritures = [];
            if ($idEcriture) {
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id_ecriture', $idEcriture, PDO::PARAM_INT);
                $stmt->execute();
                $lignesEcritures = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // --- Génération du fichier Excel ---
            // Utiliser le numéro de facture dans le nom du fichier
            $safeNumeroFacture = preg_replace('/[^A-Za-z0-9\-\_]/', '', $numeroFacture); // Nettoyer pour le nom de fichier
            $filename = 'ecritures_facture_' . $safeNumeroFacture . '_' . date('Ymd_His') . '.xls';

            // En-têtes pour forcer le téléchargement du fichier Excel
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            // Empêcher la mise en cache
            header('Pragma: no-cache');
            header('Expires: 0');

            // Ouvrir le flux de sortie pour écrire le Excel
            $output = fopen('php://output', 'w');

            // Écrire l'en-tête du Excel
            // Adapter les en-têtes si nécessaire pour mieux coller aux données des factures/écritures
            fputcsv($output, ['Date Piece', 'Journal', 'Compte', 'Libelle Ligne', 'Debit', 'Credit', 'Num Piece', 'Utilisateur', 'Mois Comptable'], ';');

            // Écrire les lignes de données
            if (empty($lignesEcritures)) {
                // Si aucune écriture, ajouter une ligne vide
                fputcsv($output, ['', '', '', 'Aucune écriture trouvée', '', '', '', '', ''], ';');
            } else {
                foreach ($lignesEcritures as $row) {
                    // Adapter le formatage de la date si nécessaire
                    $datePiece = !empty($row['Date_Saisie']) ? (new DateTime($row['Date_Saisie']))->format('Y-m-d') : ''; // Utilise Date_Saisie de Ecritures
                    $debit = ($row['Sens_Ligne'] === 'D') ? $row['Montant_Ligne'] : 0;
                    $credit = ($row['Sens_Ligne'] === 'C') ? $row['Montant_Ligne'] : 0;

                     // Convertir les montants en chaînes formatées pour le Excel, avec un point comme séparateur décimal standard Excel
                     $debit_csv = number_format((float)$debit, 2, '.', '');
                     $credit_csv = number_format((float)$credit, 2, '.', '');

                    fputcsv($output, [
                        $datePiece,
                        $row['Journal_Cde'],
                        $row['Numero_Compte'],
                        $row['Libelle_Ligne'], // Utilise le libellé spécifique de la ligne d'écriture
                        $debit_csv,
                        $credit_csv,
                        $row['Numero_Piece'], // Numéro de pièce de l'écriture
                        $row['NomUtilisateur'],
                        $row['Mois'] // Mois comptable de l'écriture
                    ], ';'); // Utilisez ';' comme séparateur pour la compatibilité Excel en France/Europe
                }
            }

            // Fermer le flux de sortie
            fclose($output);

            // Arrêter l'exécution après l'envoi du fichier
            exit();

        } catch (PDOException $e) {
            // Gérer les erreurs de requête BDD
            $errorMessage = 'Erreur lors de la récupération des écritures liées à la facture : ' . $e->getMessage();
            error_log("Erreur (GenFacture.php - query export) : " . $e->getMessage());
            header('Location: ' . $current_page . '?error=' . urlencode($errorMessage));
            exit();
        } catch (Exception $e) {
            // Gérer toute autre exception pendant le traitement
            $errorMessage = 'Erreur lors de la génération du Excel pour la facture : ' . $e->getMessage();
            error_log("Erreur (GenFacture.php - processing export) : " . $e->getMessage());
            header('Location: ' . $current_page . '?error=' . urlencode($errorMessage));
            exit();
        }
    }

    // --- Affichage du formulaire de sélection de facture ---
    // Inclure les templates seulement si le formulaire doit être affiché (pas lors de l'export Excel)
    if (file_exists('../../templates/header.php')) {
        require_once('../../templates/header.php');
    } else {
        // Fallback minimal si header.php n'existe pas
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>' . htmlspecialchars($titre) . '</title>';
        echo '<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1"></head><body><div class="container">';
    }
    if (file_exists('../../templates/navigation.php')) {
        require_once('../../templates/navigation.php');
    }

    // Gestion des messages passés via l'URL après une redirection
    $messageSucces = isset($_GET['success']) ? "<div class='alert alert-success'>" . htmlspecialchars(urldecode($_GET['success'])) . "</div>" : null;
    $messageInfo = isset($_GET['info']) ? "<div class='alert alert-info'>" . htmlspecialchars(urldecode($_GET['info'])) . "</div>" : null;
    $messageErreur = isset($_GET['error']) ? "<div class='alert alert-danger'>" . htmlspecialchars(urldecode($_GET['error'])) . "</div>" : null;

?>
<div class="container">
    <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

    <?= $messageSucces ?>
    <?= $messageInfo ?>
    <?= $messageErreur ?>

    <p>Sélectionnez la facture dont vous souhaitez exporter les écritures comptables liées au format Excel.</p>

    <?php if (empty($factures)): ?>
         <div class="alert alert-warning">Aucune facture trouvée dans la base de données.</div>
    <?php else: ?>
        <form action="<?= htmlspecialchars($current_page) ?>" method="POST" class="form-horizontal">
            <input type="hidden" name="export_excel" value="1">

            <div class="form-group">
                <label for="id_facture" class="col-sm-3 control-label">Facture</label>
                <div class="col-sm-9">
                    <select class="form-control" id="id_facture" name="id_facture" required style="height: 40px;">
                        <option value="">Sélectionner une facture</option>
                        <?php foreach ($factures as $facture): ?>
                            <option value="<?= htmlspecialchars($facture['ID_Facture']) ?>">
                                <?= htmlspecialchars($facture['Numero_Facture'] . ' - ' . $facture['Nom_Fournisseur'] . ' (' . (!empty($facture['Date_Emission']) ? date('d/m/Y', strtotime($facture['Date_Emission'])) : 'N/A') . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-9">
                    <button type="submit" class="btn btn-primary">Exporter en Excel</button>
                </div>
            </div>
        </form>
    <?php endif; ?>

</div> <?php
    // Inclure le pied de page si le fichier existe
    if (file_exists('../../templates/footer.php')) {
        require_once('../../templates/footer.php');
    } else {
        // Fallback minimal si footer.php n'existe pas
        echo '</div></body></html>';
    }
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<?php
// Le bloc catch fatal error global est déplacé avant la fin du script PHP principal.
} catch (Exception $e) {
    error_log("Erreur fatale (GenFacture.php) : " . $e->getMessage());
    // Afficher une erreur fatale si elle n'a pas été gérée plus tôt
    // Note : si l'export Excel a déjà envoyé des headers, ceci ne s'affichera pas correctement.
    // L'idéal est que toutes les erreurs d'export soient gérées par redirection avant l'envoi du Excel.
    // Ce catch global ne devrait attraper que les erreurs avant l'affichage du formulaire ou après l'export.
    echo '<div class="container mt-4"><div class="alert alert-danger">Une erreur critique est survenue. Veuillez contacter l\'administrateur. Message : ' . htmlspecialchars($e->getMessage()) . '</div></div>';
    if (file_exists('../../templates/footer.php')) {
        require_once('../../templates/footer.php');
    }
}
?>
