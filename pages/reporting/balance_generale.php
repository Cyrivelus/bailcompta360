<?php
session_start();

// Authentification
if (!isset($_SESSION['utilisateur_id'], $_SESSION['role'])) {
    header('Location: ../../login.php');
    exit;
}

require_once '../../templates/header.php';
require_once '../../templates/navigation.php';
require_once '../../fonctions/database.php'; // Assurez-vous que ce fichier initialise $pdo correctement

// Fonctions de log (placeholders si gestion_logs.php n'est pas inclus)
if (!function_exists('logApplicationError')) {
    function logApplicationError($msg) {
        error_log("Application Error (Balance Générale): " . $msg);
    }
}
if (!function_exists('logUserActivity')) {
    function logUserActivity($msg) {
        error_log("User Activity (Balance Générale): " . $msg);
    }
}

$titre = 'Balance Générale';
$message = '';
$messageType = '';

// Connexion PDO
if (!isset($pdo) || !$pdo instanceof PDO) {
    if (function_exists('getPdoConnection')) {
        $pdo = getPdoConnection();
    }
    if (!$pdo || !$pdo instanceof PDO) {
        logApplicationError('Connexion à la base de données manquante ou échouée.');
        // Message d'erreur plus informatif pour l'utilisateur
        header('Location: ../../index.php?error=' . urlencode('Erreur de configuration du serveur : impossible de se connecter à la base de données. Veuillez contacter l\'administrateur.'));
        exit;
    }
}

// Définir le numéro de compte fournisseur consolidé
const COMPTE_FOURNISSEUR_CONSOLIDE_NUMERO = '403200000000';
const COMPTE_FOURNISSEUR_CONSOLIDE_NOM = 'Fournisseurs (consolidé)';
// Nous allons essayer de trouver l'ID_Compte pour 403200000000.
// Si ce compte n'existe pas, nous devrons utiliser un ID fictif ou créer un enregistrement.
// Pour cet exemple, nous allons d'abord essayer de le récupérer.
$idCompteFournisseurConsolide = null;
try {
    $stmt = $pdo->prepare("SELECT ID_Compte FROM Comptes_compta WHERE Numero_Compte = ?");
    $stmt->execute([COMPTE_FOURNISSEUR_CONSOLIDE_NUMERO]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $idCompteFournisseurConsolide = $result['ID_Compte'];
    } else {
        // Optionnel: Insérer le compte 403200000000 s'il n'existe pas.
        // Si vous choisissez de ne pas l'insérer, assurez-vous de choisir un ID qui ne va pas entrer en conflit.
        // Pour la robustesse, il est souvent préférable que le compte existe physiquement.
        // Ou, utilisez une valeur négative (par ex. -1) et traitez-la spécifiquement dans le regroupement SQL.
        // Pour la démo, nous allons le créer si absent.
        $stmt_insert = $pdo->prepare("INSERT INTO Comptes_compta (Numero_Compte, Nom_Compte) VALUES (?, ?)");
        $stmt_insert->execute([COMPTE_FOURNISSEUR_CONSOLIDE_NUMERO, COMPTE_FOURNISSEUR_CONSOLIDE_NOM]);
        $idCompteFournisseurConsolide = $pdo->lastInsertId(); // Récupère le dernier ID inséré
        logApplicationError("Le compte fournisseur consolidé " . COMPTE_FOURNISSEUR_CONSOLIDE_NUMERO . " a été créé avec l'ID: " . $idCompteFournisseurConsolide);
    }
} catch (PDOException $e) {
    logApplicationError("Erreur PDO lors de la récupération/création du compte fournisseur consolidé: " . $e->getMessage());
    $message = "Erreur de configuration: Impossible de gérer le compte fournisseur consolidé. " . $e->getMessage();
    $messageType = 'danger';
    // Gérer l'erreur, peut-être empêcher l'exécution de la balance
}

// Filtres par défaut
$startDate = filter_input(INPUT_POST, 'start_date', FILTER_UNSAFE_RAW) ?: date('Y-01-01');
$endDate = filter_input(INPUT_POST, 'end_date', FILTER_UNSAFE_RAW) ?: date('Y-12-31');
$selectedAccount = filter_input(INPUT_POST, 'id_compte', FILTER_SANITIZE_NUMBER_INT);

// Récupérer la liste des comptes pour le filtre déroulant
$comptes = [];
try {
    $stmt = $pdo->query("SELECT ID_Compte, Numero_Compte, Nom_Compte FROM Comptes_compta ORDER BY Numero_Compte ASC");
    $comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logApplicationError("Erreur PDO lors de la récupération des comptes pour le filtre de la balance: " . $e->getMessage());
    $message = "Erreur lors du chargement des comptes pour le filtre : " . $e->getMessage();
    $messageType = 'danger';
}

$balanceEntries = [];
$totalInitialDebit = 0;
$totalInitialCredit = 0;
$totalPeriodDebit = 0;
$totalPeriodCredit = 0;
$totalFinalDebit = 0;
$totalFinalCredit = 0;

// Traitement de la génération du rapport
if (isset($_POST['generate_report']) || (!isset($_POST['generate_report']) && empty($messageType))) {
    $dateStartObj = DateTime::createFromFormat('Y-m-d', $startDate);
    $dateEndObj = DateTime::createFromFormat('Y-m-d', $endDate);

    if (!$dateStartObj || $dateStartObj->format('Y-m-d') !== $startDate ||
        !$dateEndObj || $dateEndObj->format('Y-m-d') !== $endDate) {
        $message = "Veuillez saisir des dates valides au format AAAA-MM-JJ.";
        $messageType = 'danger';
    } elseif ($dateStartObj > $dateEndObj) {
        $message = "La date de début ne peut pas être postérieure à la date de fin.";
        $messageType = 'danger';
    } else {
        $formattedStartDate = $dateStartObj->format('Y-m-d');
        $formattedEndDate = $dateEndObj->format('Y-m-d');

        try {
            // Requête SQL modifiée pour consolider les comptes fournisseurs
            $sql = "
                WITH CleanedMovements AS (
                    SELECT
                        le.ID_Compte,
                        CAST(e.Date_Saisie AS DATE) AS ConvertedDateSaisie,
                        le.Sens,
                        CAST(
                            CASE
                                WHEN REPLACE(le.Montant, ',', '.') REGEXP '^-?[0-9]+(\.[0-9]{1,2})?$' THEN REPLACE(le.Montant, ',', '.')
                                ELSE 0
                            END AS DECIMAL(18, 2)
                        ) AS ConvertedMontant
                    FROM
                        Lignes_Ecritures le
                    JOIN
                        Ecritures e ON le.ID_Ecriture = e.ID_Ecriture
                ),
                ConsolidatedMovements AS (
                    SELECT
                        -- Si le numéro de compte commence par '403', utilise l'ID du compte consolidé, sinon l'ID original
                        CASE
                            WHEN LEFT(cc.Numero_Compte, 3) = '403' THEN :consolidated_id_compte
                            ELSE cm.ID_Compte
                        END AS Grouped_ID_Compte,
                        -- Si le numéro de compte commence par '403', utilise le numéro consolidé, sinon l'original
                        CASE
                            WHEN LEFT(cc.Numero_Compte, 3) = '403' THEN '" . COMPTE_FOURNISSEUR_CONSOLIDE_NUMERO . "'
                            ELSE cc.Numero_Compte
                        END AS Grouped_Numero_Compte,
                        -- Si le numéro de compte commence par '403', utilise le nom consolidé, sinon l'original
                        CASE
                            WHEN LEFT(cc.Numero_Compte, 3) = '403' THEN '" . COMPTE_FOURNISSEUR_CONSOLIDE_NOM . "'
                            ELSE cc.Nom_Compte
                        END AS Grouped_Nom_Compte,
                        cm.ConvertedDateSaisie,
                        cm.Sens,
                        cm.ConvertedMontant
                    FROM
                        Comptes_compta cc
                    JOIN
                        CleanedMovements cm ON cc.ID_Compte = cm.ID_Compte
                )
                SELECT
                    cmc.Grouped_ID_Compte AS ID_Compte,
                    cmc.Grouped_Numero_Compte AS Numero_Compte,
                    cmc.Grouped_Nom_Compte AS Nom_Compte,
                    -- Solde initial (avant la date de début)
                    SUM(CASE WHEN cmc.ConvertedDateSaisie < :start_initial AND cmc.Sens = 'D' THEN cmc.ConvertedMontant ELSE 0 END) AS SoldeInitialDebit,
                    SUM(CASE WHEN cmc.ConvertedDateSaisie < :start_initial2 AND cmc.Sens = 'C' THEN cmc.ConvertedMontant ELSE 0 END) AS SoldeInitialCredit,
                    -- Mouvements sur la période
                    SUM(CASE WHEN cmc.ConvertedDateSaisie BETWEEN :start_period AND :end_period AND cmc.Sens = 'D' THEN cmc.ConvertedMontant ELSE 0 END) AS MouvementDebit,
                    SUM(CASE WHEN cmc.ConvertedDateSaisie BETWEEN :start_period2 AND :end_period2 AND cmc.Sens = 'C' THEN cmc.ConvertedMontant ELSE 0 END) AS MouvementCredit
                FROM
                    ConsolidatedMovements cmc
                WHERE 1=1
            ";

            $params = [
                ':consolidated_id_compte' => $idCompteFournisseurConsolide,
                ':start_initial' => $formattedStartDate,
                ':start_initial2' => $formattedStartDate,
                ':start_period' => $formattedStartDate,
                ':end_period' => $formattedEndDate,
                ':start_period2' => $formattedStartDate,
                ':end_period2' => $formattedEndDate,
            ];

            // Si un compte spécifique est sélectionné dans le filtre
            if ($selectedAccount !== null && is_numeric($selectedAccount)) {
                // Si le compte sélectionné est le compte consolidé des fournisseurs
                if ((int)$selectedAccount === (int)$idCompteFournisseurConsolide) {
                    $sql .= " AND cmc.Grouped_ID_Compte = :selected_id_compte";
                    $params[':selected_id_compte'] = $selectedAccount;
                }
                // Si le compte sélectionné est un compte fournisseur individuel
                else if (strpos((string)($comptes[array_search($selectedAccount, array_column($comptes, 'ID_Compte'))]['Numero_Compte'] ?? ''), '403') === 0) {
                    // Si un compte 403 est sélectionné spécifiquement, nous voulons toujours le montrer
                    // dans le compte consolidé. Le filtre sera appliqué sur le Grouped_ID_Compte.
                    $sql .= " AND cmc.Grouped_ID_Compte = :selected_id_compte";
                    $params[':selected_id_compte'] = $idCompteFournisseurConsolide;
                }
                // Si le compte sélectionné n'est PAS un compte fournisseur
                else {
                    $sql .= " AND cmc.Grouped_ID_Compte = :selected_id_compte";
                    $params[':selected_id_compte'] = $selectedAccount;
                }
            }

            $sql .= " GROUP BY cmc.Grouped_ID_Compte, cmc.Grouped_Numero_Compte, cmc.Grouped_Nom_Compte ORDER BY cmc.Grouped_Numero_Compte ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rawBalanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Pour l'affichage, nous devons nous assurer que le compte consolidé existe même s'il n'y a pas de mouvements
            // ou si aucun autre compte ne commence par '403' et s'il n'était pas le seul compte sélectionné.
            // C'est déjà géré par LEFT JOIN et GROUP BY, mais on pourrait vouloir ajouter une ligne vide si nécessaire.

            $hasConsolidatedAccountEntry = false;
            foreach ($rawBalanceData as $row) {
                // Ensure all values are treated as floats, defaulting to 0 if not present
                $soldeInitialDebit = (float)($row['SoldeInitialDebit'] ?? 0);
                $soldeInitialCredit = (float)($row['SoldeInitialCredit'] ?? 0);
                $mouvementDebit = (float)($row['MouvementDebit'] ?? 0);
                $mouvementCredit = (float)($row['MouvementCredit'] ?? 0);

                // Calcul du solde final pour chaque compte
                $soldeFinalDebit = $soldeInitialDebit + $mouvementDebit;
                $soldeFinalCredit = $soldeInitialCredit + $mouvementCredit;

                // Déterminer le solde final net (débit ou crédit)
                if ($soldeFinalDebit > $soldeFinalCredit) {
                    $soldeFinalDebit = $soldeFinalDebit - $soldeFinalCredit;
                    $soldeFinalCredit = 0;
                } else {
                    $soldeFinalCredit = $soldeFinalCredit - $soldeFinalDebit;
                    $soldeFinalDebit = 0;
                }

                $balanceEntries[] = [
                    'ID_Compte' => $row['ID_Compte'],
                    'Numero_Compte' => $row['Numero_Compte'],
                    'Nom_Compte' => $row['Nom_Compte'],
                    'SoldeInitialDebit' => $soldeInitialDebit,
                    'SoldeInitialCredit' => $soldeInitialCredit,
                    'MouvementDebit' => $mouvementDebit,
                    'MouvementCredit' => $mouvementCredit,
                    'SoldeFinalDebit' => $soldeFinalDebit,
                    'SoldeFinalCredit' => $soldeFinalCredit
                ];

                // Check if the consolidated account is in the results
                if ($row['ID_Compte'] == $idCompteFournisseurConsolide) {
                    $hasConsolidatedAccountEntry = true;
                }

                // Calcul des totaux généraux
                $totalInitialDebit += $soldeInitialDebit;
                $totalInitialCredit += $soldeInitialCredit;
                $totalPeriodDebit += $mouvementDebit;
                $totalPeriodCredit += $mouvementCredit;
                $totalFinalDebit += $soldeFinalDebit;
                $totalFinalCredit += $soldeFinalCredit;
            }

            // Si le compte consolidé n'est pas dans les résultats mais aurait dû l'être (par exemple, si selectedAccount est 403200000000
            // ou si des mouvements de fournisseurs existent mais ne sont pas dans le jeu de résultats pour une raison quelconque
            // parce qu'il n'y a pas d'autres comptes que 403 en dehors de la période sélectionnée, etc.)
            // Cette partie est principalement pour s'assurer que si on filtre sur le 403200000000, il apparaisse toujours même vide.
            if ((!$hasConsolidatedAccountEntry && ($selectedAccount === null || (int)$selectedAccount === (int)$idCompteFournisseurConsolide)) ||
                ($selectedAccount !== null && (int)$selectedAccount === (int)$idCompteFournisseurConsolide && empty($balanceEntries))
            ) {
                    // Fetch the specific consolidated account details if it's not already in results.
                    // This ensures the 403200000000 account always appears if it's the target or no other accounts exist
                $stmtConsolide = $pdo->prepare("SELECT ID_Compte, Numero_Compte, Nom_Compte FROM Comptes_compta WHERE ID_Compte = ?");
                $stmtConsolide->execute([$idCompteFournisseurConsolide]);
                $consolidatedAccountInfo = $stmtConsolide->fetch(PDO::FETCH_ASSOC);

                if ($consolidatedAccountInfo) {
                    $balanceEntries[] = [
                        'ID_Compte' => $consolidatedAccountInfo['ID_Compte'],
                        'Numero_Compte' => $consolidatedAccountInfo['Numero_Compte'],
                        'Nom_Compte' => $consolidatedAccountInfo['Nom_Compte'],
                        'SoldeInitialDebit' => 0,
                        'SoldeInitialCredit' => 0,
                        'MouvementDebit' => 0,
                        'MouvementCredit' => 0,
                        'SoldeFinalDebit' => 0,
                        'SoldeFinalCredit' => 0
                    ];
                }
            }
            // Sort entries to ensure the consolidated account appears in its numerical order
            usort($balanceEntries, function($a, $b) {
                return strcmp($a['Numero_Compte'], $b['Numero_Compte']);
            });


            logUserActivity("Génération de la Balance Générale par l'utilisateur ID: " . ($_SESSION['utilisateur_id'] ?? 'N/A') . " pour la période du {$startDate} au {$endDate}. Consolidation fournisseurs activée.");

        } catch (PDOException $e) {
            logApplicationError("Erreur PDO lors de la génération de la Balance Générale (consolidation fournisseurs): " . $e->getMessage());
            $message = "Erreur lors du chargement de la balance. Veuillez vérifier les données et les filtres. Détails techniques : " . $e->getMessage();
            $messageType = 'danger';
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
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/tableau.css">
     <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <style>
        .balance-table thead th { background-color: #f2f2f2; }
        .balance-table tfoot th { background-color: #e6f7ff; font-weight: bold; }
        .text-debit { color: #d9534f; } /* Rouge */
        .text-credit { color: #5cb85c; } /* Vert */
        .balance-row-summary { background-color: #dff0d8; font-weight: bold; } /* Vert clair pour les totaux */
        select.form-control {
            height: auto; /* Permet à la hauteur de s'ajuster au contenu */
            min-height: 34px; /* Hauteur minimale pour un champ de formulaire standard Bootstrap */
        }
    </style>
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

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Filtres de la Balance Générale</h3>
            </div>
            <div class="panel-body">
                <form action="" method="POST" class="form-horizontal">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="start_date" class="col-sm-4 control-label">Date Début :</label>
                                <div class="col-sm-8">
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="end_date" class="col-sm-4 control-label">Date Fin :</label>
                                <div class="col-sm-8">
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="id_compte" class="col-sm-4 control-label">Compte :</label>
                                <div class="col-sm-8">
                                    <select name="id_compte" id="id_compte" class="form-control">
                                        <option value="">Tous les comptes</option>
                                        <option value="<?= htmlspecialchars($idCompteFournisseurConsolide) ?>"
                                            <?= ((int)$selectedAccount === (int)$idCompteFournisseurConsolide) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(COMPTE_FOURNISSEUR_CONSOLIDE_NUMERO) ?> - <?= htmlspecialchars(COMPTE_FOURNISSEUR_CONSOLIDE_NOM) ?>
                                        </option>
                                        <?php foreach ($comptes as $compte): ?>
                                            <?php if ($compte['ID_Compte'] != $idCompteFournisseurConsolide && strpos($compte['Numero_Compte'], '403') !== 0): ?>
                                                <option value="<?= htmlspecialchars($compte['ID_Compte']) ?>"
                                                    <?= ($selectedAccount == $compte['ID_Compte']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($compte['Numero_Compte']) ?> - <?= htmlspecialchars($compte['Nom_Compte']) ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-sm-offset-2 col-sm-10">
                            <button type="submit" name="generate_report" class="btn btn-primary">
                                <span class="glyphicon glyphicon-balance-scale"></span> Afficher la Balance
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (isset($_POST['generate_report']) && empty($message)): ?>
            
            <div class="panel panel-info">
                <div class="panel-footer text-right">
                    <a href="../exports/export_reports.php?report_type=balance_general&format=csv&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&id_compte=<?= urlencode($selectedAccount ?? '') ?>" class="btn btn-success">
                        <span class="glyphicon glyphicon-download-alt"></span> Exporter en CSV
                    </a>
                    <a href="../exports/export_reports.php?report_type=balance_general&format=pdf&view=preview&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&id_compte=<?= urlencode($selectedAccount ?? '') ?>" target="_blank" class="btn btn-info">
                        <span class="glyphicon glyphicon-eye-open"></span> Aperçu PDF
                    </a>
                    <a href="../exports/export_reports.php?report_type=balance_general&format=pdf&view=download&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&id_compte=<?= urlencode($selectedAccount ?? '') ?>" class="btn btn-primary">
                        <span class="glyphicon glyphicon-print"></span> Imprimer PDF
                    </a>
                </div>
            </div>

            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">Balance Générale</h3>
                    <p class="text-muted small">Période du <?= htmlspecialchars(date('d/m/Y', strtotime($startDate))) ?> au <?= htmlspecialchars(date('d/m/Y', strtotime($endDate))) ?></p>
                </div>
                <div class="panel-body">
                    <?php if (empty($balanceEntries) && ($totalInitialDebit == 0 && $totalInitialCredit == 0 && $totalPeriodDebit == 0 && $totalPeriodCredit == 0)): ?>
                        <div class="alert alert-info">Aucun solde de compte trouvé pour la période et les filtres sélectionnés.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped balance-table">
                                <thead>
                                    <tr>
                                        <th rowspan="2">N° Compte</th>
                                        <th rowspan="2">Nom du Compte</th>
                                        <th colspan="2" class="text-center">Soldes d'ouverture</th>
                                        <th colspan="2" class="text-center">Mouvements de la période</th>
                                        <th colspan="2" class="text-center">Soldes de clôture</th>
                                    </tr>
                                    <tr>
                                        <th class="text-right">Débit</th>
                                        <th class="text-right">Crédit</th>
                                        <th class="text-right">Débit</th>
                                        <th class="text-right">Crédit</th>
                                        <th class="text-right">Débit</th>
                                        <th class="text-right">Crédit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($balanceEntries as $entry): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($entry['Numero_Compte']) ?></td>
                                            <td><?= htmlspecialchars($entry['Nom_Compte']) ?></td>
                                            <td class="text-right text-debit"><?= htmlspecialchars(number_format($entry['SoldeInitialDebit'], 2, ',', ' ')) ?></td>
                                            <td class="text-right text-credit"><?= htmlspecialchars(number_format($entry['SoldeInitialCredit'], 2, ',', ' ')) ?></td>
                                            <td class="text-right text-debit"><?= htmlspecialchars(number_format($entry['MouvementDebit'], 2, ',', ' ')) ?></td>
                                            <td class="text-right text-credit"><?= htmlspecialchars(number_format($entry['MouvementCredit'], 2, ',', ' ')) ?></td>
                                            <td class="text-right text-debit"><?= htmlspecialchars(number_format($entry['SoldeFinalDebit'], 2, ',', ' ')) ?></td>
                                            <td class="text-right text-credit"><?= htmlspecialchars(number_format($entry['SoldeFinalCredit'], 2, ',', ' ')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="balance-table-footer balance-row-summary">
                                        <th colspan="2" class="text-right">Totaux :</th>
                                        <th class="text-right text-debit"><?= htmlspecialchars(number_format($totalInitialDebit, 2, ',', ' ')) ?></th>
                                        <th class="text-right text-credit"><?= htmlspecialchars(number_format($totalInitialCredit, 2, ',', ' ')) ?></th>
                                        <th class="text-right text-debit"><?= htmlspecialchars(number_format($totalPeriodDebit, 2, ',', ' ')) ?></th>
                                        <th class="text-right text-credit"><?= htmlspecialchars(number_format($totalPeriodCredit, 2, ',', ' ')) ?></th>
                                        <th class="text-right text-debit"><?= htmlspecialchars(number_format($totalFinalDebit, 2, ',', ' ')) ?></th>
                                        <th class="text-right text-credit"><?= htmlspecialchars(number_format($totalFinalCredit, 2, ',', ' ')) ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif (isset($_POST['generate_report']) && !empty($message)): ?>
            <?php else: ?>
            <div class="alert alert-info">Sélectionnez une période et un compte (facultatif), puis cliquez sur "Afficher la Balance" pour générer le rapport.</div>
        <?php endif; ?>

    </div>
    <?php require_once '../../templates/footer.php'; ?>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script src="../../js/jquery-3.7.1.min.js"></script>
    <script src="../../js/bootstrap.min.js"></script>
</body>
</html>