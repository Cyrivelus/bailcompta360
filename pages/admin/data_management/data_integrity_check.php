<?php
session_start();

// Initialiser les variables pour les messages de succès ou d'erreur
$message = '';
$messageType = ''; // Peut être 'success', 'danger', 'info', 'warning'

// Check user authentication and authorization (only super_admins)
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'super_admin') {
    // En production, décommenter la ligne ci-dessous pour rediriger
    // header('Location: ../../../login.php');
    $message = "Accès non autorisé. Vous devez être connecté en tant que 'super_admin' pour accéder à cette fonction.";
    $messageType = 'danger';
    // exit; // Décommenter en production pour arrêter l'exécution
}

// Inclure les fichiers nécessaires seulement si l'utilisateur est autorisé ou pour afficher le message d'erreur
// Note: Assurez-vous que database.php initialise $pdo et qu'il est accessible globalement ou passé en paramètre.
// Pour cet exemple, nous supposons que $pdo est disponible après l'inclusion de database.php.
if ($messageType !== 'danger') {
    require_once '../../../templates/header.php';
    require_once '../../../templates/navigation.php';
    require_once '../../../fonctions/database.php'; // Pour les détails de connexion à la base de données (qui devrait initialiser $pdo)
    require_once '../../../fonctions/gestion_logs.php'; // Pour la journalisation des actions
} else {
    // Si l'utilisateur n'est pas autorisé, inclure un en-tête minimal pour afficher le message d'erreur.
    $titre = 'Accès Refusé';
    require_once '../../../templates/header.php';
    require_once '../../../templates/navigation.php';
}

$titre = 'Vérification de l\'Intégrité des Données';

$integrityChecks = [];

/**
 * Récupère la liste des anomalies précédemment reconnues.
 * @param PDO $pdo La connexion PDO.
 * @return array Un tableau associatif [check_name => [issue_data_json_string, ...]]
 */
function getAcknowledgedIssues(PDO $pdo): array {
    $acknowledged = [];
    try {
        $stmt = $pdo->query("SELECT check_name, issue_data_json FROM acknowledged_integrity_issues");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $acknowledged[$row['check_name']][] = $row['issue_data_json'];
        }
    } catch (PDOException $e) {
        // Log l'erreur mais ne bloque pas l'exécution
        if (function_exists('logApplicationError')) { // Vérifie si la fonction de log existe
            logApplicationError("Erreur lors de la récupération des anomalies reconnues: " . $e->getMessage());
        } else {
            error_log("Erreur lors de la récupération des anomalies reconnues: " . $e->getMessage());
        }
    }
    return $acknowledged;
}

/**
 * Exécute une seule vérification d'intégrité.
 * @param PDO $pdo La connexion PDO.
 * @param string $checkName Le nom de la vérification.
 * @param string $sql La requête SQL à exécuter.
 * @param array $params Les paramètres pour la requête préparée.
 * @param callable|null $issueIdentifierGenerator Une fonction pour générer un identifiant unique pour chaque anomalie.
 * @param array $acknowledgedIssues Les anomalies déjà reconnues pour filtrage.
 * @return array Le résultat de la vérification.
 */
function runIntegrityCheck(PDO $pdo, string $checkName, string $sql, array $params = [], ?callable $issueIdentifierGenerator = null, array $acknowledgedIssues = []): array {
    $result = [
        'name' => $checkName,
        'status' => 'success', // Assume success unless issues found
        'issues' => [],
        'count' => 0
    ];
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rawIssues = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $filteredIssues = [];

        foreach ($rawIssues as $issue) {
            $uniqueId = null;
            if ($issueIdentifierGenerator) {
                $uniqueId = $issueIdentifierGenerator($issue);
            } else {
                // Par défaut, utiliser l'ensemble de la ligne comme identifiant (peut être lourd)
                $uniqueId = json_encode($issue);
            }

            // Vérifier si cette anomalie a déjà été reconnue
            if (!isset($acknowledgedIssues[$checkName]) || !in_array($uniqueId, $acknowledgedIssues[$checkName])) {
                $issue['_unique_id_for_acknowledgment'] = $uniqueId; // Ajouter l'ID unique pour le JavaScript
                $filteredIssues[] = $issue;
            }
        }

        $result['count'] = count($filteredIssues);

        if ($result['count'] > 0) {
            $result['status'] = 'danger';
            $result['issues'] = $filteredIssues;
        }
    } catch (PDOException $e) {
        $result['status'] = 'error';
        $result['issues'][] = ['error_message' => "Erreur SQL: " . $e->getMessage()];
        // Log l'erreur d'application pour le débogage côté serveur
        if (function_exists('logApplicationError')) { // Vérifie si la fonction de log existe
            logApplicationError("Erreur lors de la vérification d'intégrité '{$checkName}': " . $e->getMessage());
        } else {
            error_log("Erreur lors de la vérification d'intégrité '{$checkName}': " . $e->getMessage());
        }
    }
    return $result;
}

// --- Handle Acknowledge Issue Action ---
if (isset($_POST['action']) && $_POST['action'] === 'acknowledge_issue' && $messageType === '') {
    header('Content-Type: application/json'); // Répondre en JSON
    if (!isset($pdo) || !$pdo instanceof PDO) {
        echo json_encode(['success' => false, 'message' => "Erreur: La connexion à la base de données n'est pas établie."]);
        exit;
    }

    $checkName = $_POST['check_name'] ?? '';
    $issueDataJson = $_POST['issue_data_json'] ?? '';
    $userId = $_SESSION['utilisateur_id'] ?? null;

    if (empty($checkName) || empty($issueDataJson)) {
        echo json_encode(['success' => false, 'message' => "Données manquantes pour l'acquittement."]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO acknowledged_integrity_issues (check_name, issue_data_json, acknowledged_by_user_id, acknowledged_at) VALUES (:check_name, :issue_data_json, :user_id, NOW()) ON DUPLICATE KEY UPDATE acknowledged_at = NOW(), acknowledged_by_user_id = :user_id");
        $stmt->execute([
            ':check_name' => $checkName,
            ':issue_data_json' => $issueDataJson,
            ':user_id' => $userId
        ]);
        if (function_exists('logUserActivity')) {
            logUserActivity("Anomalie d'intégrité reconnue: {$checkName} - " . $issueDataJson . " par utilisateur ID: " . ($userId ?? 'N/A'));
        }
        echo json_encode(['success' => true, 'message' => "Anomalie reconnue avec succès."]);
    } catch (PDOException $e) {
        if (function_exists('logApplicationError')) {
            logApplicationError("Erreur lors de l'acquittement de l'anomalie: " . $e->getMessage());
        } else {
            error_log("Erreur lors de l'acquittement de l'anomalie: " . $e->getMessage());
        }
        echo json_encode(['success' => false, 'message' => "Erreur lors de l'acquittement de l'anomalie: " . $e->getMessage()]);
    }
    exit; // Arrêter l'exécution après la réponse AJAX
}


// --- Perform integrity checks when requested ---
if (isset($_POST['action']) && $_POST['action'] === 'run_checks' && $messageType === '') {
    // Vérifier que $pdo est bien initialisé
    if (!isset($pdo) || !$pdo instanceof PDO) {
        $message = "Erreur: La connexion à la base de données n'est pas établie. Veuillez vérifier 'fonctions/database.php'.";
        $messageType = 'danger';
        if (function_exists('logApplicationError')) {
            logApplicationError($message);
        } else {
            error_log($message);
        }
    } else {
        if (function_exists('logUserActivity')) {
            logUserActivity("Lancement de la vérification d'intégrité des données par l'utilisateur ID: " . ($_SESSION['utilisateur_id'] ?? 'N/A'));
        }

        // Charger les anomalies déjà reconnues
        $acknowledgedIssues = getAcknowledgedIssues($pdo);

        // Définir les générateurs d'identifiants uniques pour chaque type de vérification
        $ecritureIdGenerator = fn($issue) => json_encode(['ID_Ecriture' => $issue['ID_Ecriture']]);
        $compteIdGenerator = fn($issue) => json_encode(['ID_Compte' => $issue['ID_Compte']]);
        // Note: ID_Piece n'est plus utilisé car la table Pieces n'existe pas.
        // $pieceIdGenerator = fn($issue) => json_encode(['ID_Piece' => $issue['ID_Piece']]);
        $duplicatePieceGenerator = fn($issue) => json_encode([
            'Numero_Piece' => $issue['Numero_Piece'],
            'ID_Compte' => $issue['ID_Compte'],
            'Date_Ecriture' => $issue['Date_Ecriture']
        ]);
        $duplicateEcritureGenerator = fn($issue) => json_encode([
            'Date_Ecriture' => $issue['Date_Ecriture'],
            'Montant' => $issue['Montant'],
            'Description' => $issue['Description'],
            'ID_Compte' => $issue['ID_Compte']
        ]);
        // Générateur par défaut si aucun spécifique n'est défini (utilise toute la ligne)
        $defaultGenerator = fn($issue) => json_encode($issue);


        // Check 1: Orphaned Ecritures (entries without a valid account)
        $integrityChecks[] = runIntegrityCheck(
            $pdo,
            "Écritures orphelines (sans compte valide)",
            "SELECT e.* FROM Ecritures e WHERE e.ID_Compte IS NULL OR e.ID_Compte NOT IN (SELECT ID_Compte FROM Comptes_compta)", // Changed Comptes to Comptes_compta
            [],
            $ecritureIdGenerator,
            $acknowledgedIssues
        );

        // Check 2: Comptes with no associated entries (might indicate unused/redundant accounts)
        $integrityChecks[] = runIntegrityCheck(
            $pdo,
            "Comptes sans écritures associées",
            "SELECT c.* FROM Comptes_compta c WHERE c.ID_Compte NOT IN (SELECT DISTINCT ID_Compte FROM Ecritures)", // Changed Comptes to Comptes_compta
            [],
            $compteIdGenerator,
            $acknowledgedIssues
        );

        // Check 3: Balances of Comptes not matching sum of Ecritures (requires a balance column in Comptes)
        $integrityChecks[] = runIntegrityCheck(
            $pdo,
            "Déséquilibre Solde Compte vs Somme Écritures",
            "SELECT c.Nom_Compte, c.Solde_Actuel, SUM(e.Montant) AS Calculated_Balance, c.ID_Compte
             FROM Comptes_compta c
             LEFT JOIN Ecritures e ON c.ID_Compte = e.ID_Compte
             GROUP BY c.ID_Compte, c.Nom_Compte, c.Solde_Actuel
             HAVING ABS(c.Solde_Actuel - COALESCE(SUM(e.Montant), 0)) > 0.01",
            [],
            $compteIdGenerator, // Utilise l'ID_Compte comme identifiant
            $acknowledgedIssues
        );

        // Check 4: Missing categories for Ecritures (if you have a Categories table)
        // COMMENTED OUT as per user feedback: Table 'bd_ad_sce.categories' doesn't exist
        /*
        $integrityChecks[] = runIntegrityCheck(
            $pdo,
            "Écritures sans catégorie valide",
            "SELECT e.* FROM Ecritures e WHERE e.ID_Categorie IS NOT NULL AND e.ID_Categorie NOT IN (SELECT ID_Categorie FROM Categories)",
            [],
            $ecritureIdGenerator,
            $acknowledgedIssues
        );
        */

        // Check 5: Duplicate transaction numbers (if 'Numero_Piece' should be unique per account/date)
        $integrityChecks[] = runIntegrityCheck(
            $pdo,
            "Numéros de pièce dupliqués (par compte et date)",
            "SELECT Numero_Piece, ID_Compte, Date_Ecriture, COUNT(*) as Duplicates
             FROM Ecritures
             WHERE Numero_Piece IS NOT NULL AND Numero_Piece != ''
             GROUP BY Numero_Piece, ID_Compte, Date_Ecriture
             HAVING COUNT(*) > 1",
            [],
            $duplicatePieceGenerator,
            $acknowledgedIssues
        );

        // --- NOUVELLES VÉRIFICATIONS POUR L'AUDIT ET LE RISQUE ---

        // Check 6: Vérification de l'équilibre des débits et crédits (Comptabilité en partie double)
        $integrityChecks[] = runIntegrityCheck(
            $pdo,
            "Déséquilibre global Débits vs Crédits (Comptabilité en partie double)",
            "SELECT
                SUM(COALESCE(Debit, 0)) AS Total_Debit,
                SUM(COALESCE(Credit, 0)) AS Total_Credit,
                ABS(SUM(COALESCE(Debit, 0)) - SUM(COALESCE(Credit, 0))) AS Difference
            FROM Ecritures
            HAVING ABS(SUM(COALESCE(Debit, 0)) - SUM(COALESCE(Credit, 0))) > 0.01",
            [],
            // Pour cette vérification globale, l'identifiant peut être statique car elle représente un état global
            fn($issue) => json_encode(['global_balance_check' => true]),
            $acknowledgedIssues
        );

        // Check 7: Écritures avec des dates invalides (futures ou trop anciennes)
        $integrityChecks[] = runIntegrityCheck(
            $pdo,
            "Écritures avec des dates invalides (futures ou trop anciennes)",
            "SELECT * FROM Ecritures WHERE Date_Ecriture > CURDATE() OR Date_Ecriture < '2000-01-01'",
            [],
            $ecritureIdGenerator,
            $acknowledgedIssues
        );

        // Check 8: Utilisateurs inactifs ayant effectué des écritures récentes
        $integrityChecks[] = runIntegrityCheck(
            $pdo,
            "Écritures récentes par utilisateurs inactifs",
            "SELECT e.*, u.Nom, u.Role AS Statut_Utilisateur
             FROM Ecritures e
             JOIN Utilisateurs u ON e.ID_Utilisateur = u.ID_Utilisateur
             WHERE u.Role = 'inactif' AND e.Date_Ecriture > CURDATE() - INTERVAL 30 DAY", // Changed Nom_Utilisateur to Nom, and Statut to Role
            [],
            $ecritureIdGenerator,
            $acknowledgedIssues
        );

        // Check 9: Écritures sans référence à une pièce comptable valide
        // COMMENTED OUT as per user feedback: Table 'bd_ad_sce.pieces' doesn't exist
        /*
        $integrityChecks[] = runIntegrityCheck(
            $pdo,
            "Écritures sans pièce comptable valide",
            "SELECT e.* FROM Ecritures e WHERE e.ID_Piece IS NOT NULL AND e.ID_Piece NOT IN (SELECT ID_Piece FROM Pieces)",
            [],
            $ecritureIdGenerator,
            $acknowledgedIssues
        );
        */

        // Check 10: Doublons potentiels d'écritures (même date, montant, description, compte)
        $integrityChecks[] = runIntegrityCheck(
            $pdo,
            "Doublons potentiels d'écritures",
            "SELECT Date_Ecriture, Montant, Description, ID_Compte, COUNT(*) as Duplicates
             FROM Ecritures
             GROUP BY Date_Ecriture, Montant, Description, ID_Compte
             HAVING COUNT(*) > 1",
            [],
            $duplicateEcritureGenerator,
            $acknowledgedIssues
        );

        // Check 11: Comptes avec solde négatif inattendu (pour les comptes d'actifs ou de revenus)
        $integrityChecks[] = runIntegrityCheck(
            $pdo,
            "Comptes d'actifs/revenus avec solde négatif inattendu",
            "SELECT c.Nom_Compte, c.Solde_Actuel, c.Type_Compte, c.ID_Compte
             FROM Comptes_compta c
             WHERE c.Solde_Actuel < 0 AND c.Type_Compte IN ('Actif', 'Revenu')", // Changed Comptes to Comptes_compta
            [],
            $compteIdGenerator,
            $acknowledgedIssues
        );

        // Check 12: Écritures avec montant zéro ou négatif (si non autorisé)
        $integrityChecks[] = runIntegrityCheck(
            $pdo,
            "Écritures avec montant zéro ou négatif",
            "SELECT * FROM Ecritures WHERE Montant <= 0",
            [],
            $ecritureIdGenerator,
            $acknowledgedIssues
        );


        // Determine overall status
        $overallStatus = 'success';
        foreach ($integrityChecks as $check) {
            if ($check['status'] === 'danger' || $check['status'] === 'error') {
                $overallStatus = 'danger';
                break;
            }
        }

        if ($overallStatus === 'success') {
            $message = "Toutes les vérifications d'intégrité des données se sont déroulées sans problème. Aucune anomalie détectée.";
            $messageType = 'success';
        } else {
            $message = "Des problèmes d'intégrité des données ont été détectés. Veuillez examiner les détails ci-dessous.";
            $messageType = 'danger';
        }
    }
} // End of if (run_checks)

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?= htmlspecialchars($titre) ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../css/style.css">
    <link rel="stylesheet" href="../../../css/tableau.css">
    <link rel="stylesheet" href="../../../css/bootstrap.min.css">
    <style>
        .check-status-success { color: #28a745; font-weight: bold; }
        .check-status-danger { color: #dc3545; font-weight: bold; }
        .check-status-error { color: #ffc107; font-weight: bold; } /* Yellow for internal errors */
        .integrity-table th, .integrity-table td { vertical-align: middle; }
        .panel-body h4 { margin-top: 20px; margin-bottom: 10px; }
        .panel-body hr { margin-top: 20px; margin-bottom: 20px; border-top: 1px solid #eee; }
        .acknowledge-btn {
            margin-left: 10px;
            padding: 5px 10px;
            font-size: 12px;
            line-height: 1.5;
            border-radius: 3px;
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
                <h3 class="panel-title">Lancer la vérification</h3>
            </div>
            <div class="panel-body">
                <p>Cliquez sur le bouton ci-dessous pour lancer une série de vérifications de l'intégrité de votre base de données. Cela peut prendre un certain temps en fonction de la taille de vos données.</p>
                <form method="POST" action="">
                    <button type="submit" name="action" value="run_checks" class="btn btn-primary">
                        <span class="glyphicon glyphicon-play"></span> Lancer les vérifications
                    </button>
                </form>
            </div>
        </div>

        <?php if (!empty($integrityChecks)): ?>
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">Résultats des vérifications</h3>
                </div>
                <div class="panel-body">
                    <?php foreach ($integrityChecks as $check): ?>
                        <div class="mb-3">
                            <h4><?= htmlspecialchars($check['name']) ?> :
                                <span class="check-status-<?= $check['status'] ?>">
                                    <?php
                                        if ($check['status'] === 'success') echo 'OK';
                                        elseif ($check['status'] === 'danger') echo 'ANOMALIES DÉTECTÉES (' . $check['count'] . ')';
                                        elseif ($check['status'] === 'error') echo 'ERREUR INTERNE';
                                    ?>
                                </span>
                            </h4>
                            <?php if (!empty($check['issues'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-condensed table-bordered integrity-table">
                                        <thead>
                                            <tr>
                                                <?php
                                                // Dynamically generate table headers based on the first issue's keys
                                                $firstIssue = reset($check['issues']);
                                                if ($firstIssue) {
                                                    foreach (array_keys($firstIssue) as $key) {
                                                        // Exclure la clé interne utilisée pour l'acquittement
                                                        if ($key === '_unique_id_for_acknowledgment') continue;
                                                        echo '<th>' . htmlspecialchars($key) . '</th>';
                                                    }
                                                    echo '<th>Action</th>'; // Nouvelle colonne pour le bouton d'acquittement
                                                }
                                                ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($check['issues'] as $issue): ?>
                                                <tr id="issue-<?= md5($issue['_unique_id_for_acknowledgment']) ?>">
                                                    <?php foreach ($issue as $key => $value): ?>
                                                        <?php if ($key === '_unique_id_for_acknowledgment') continue; ?>
                                                        <td><?= htmlspecialchars(is_null($value) ? 'NULL' : $value) ?></td>
                                                    <?php endforeach; ?>
                                                    <td>
                                                        <button
                                                            type="button"
                                                            class="btn btn-xs btn-warning acknowledge-btn"
                                                            data-check-name="<?= htmlspecialchars($check['name']) ?>"
                                                            data-issue-data-json="<?= htmlspecialchars($issue['_unique_id_for_acknowledgment']) ?>"
                                                            data-issue-row-id="issue-<?= md5($issue['_unique_id_for_acknowledgment']) ?>"
                                                        >
                                                            Marquer comme résolu
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="text-danger">Action requise : Examinez les enregistrements ci-dessus et corrigez-les manuellement ou via des scripts de maintenance.</p>
                            <?php elseif ($check['status'] === 'error'): ?>
                                <p class="text-warning">Une erreur s'est produite lors de l'exécution de cette vérification. Veuillez consulter les logs d'erreurs du serveur pour plus de détails.</p>
                            <?php else: ?>
                                <p class="text-success">Aucune anomalie détectée pour cette vérification.</p>
                            <?php endif; ?>
                        </div>
                        <hr>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
    <?php require_once '../../../templates/footer.php'; ?>
    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
     <script src="../js/jquery-3.7.1.js"></script>
<script src="../js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            // Gérer le clic sur le bouton "Marquer comme résolu"
            $(document).on('click', '.acknowledge-btn', function() {
                var button = $(this);
                var checkName = button.data('check-name');
                var issueDataJson = button.data('issue-data-json');
                var issueRowId = button.data('issue-row-id'); // L'ID de la ligne à cacher

                // Désactiver le bouton pour éviter les clics multiples
                button.prop('disabled', true).text('Traitement...');

                $.ajax({
                    url: window.location.href, // Envoyer la requête à la même page
                    type: 'POST',
                    data: {
                        action: 'acknowledge_issue',
                        check_name: checkName,
                        issue_data_json: issueDataJson
                    },
                    dataType: 'json', // Attendre une réponse JSON
                    success: function(response) {
                        if (response.success) {
                            // Cacher la ligne de l'anomalie
                            $('#' + issueRowId).fadeOut(500, function() {
                                $(this).remove(); // Supprimer la ligne du DOM après l'animation
                            });
                            // Afficher un message de succès (peut être un petit toast ou une alerte temporaire)
                            // Pour l'instant, on peut juste logguer ou mettre à jour un message global si besoin
                            console.log(response.message);
                            // Vous pouvez ajouter ici une alerte Bootstrap temporaire si vous le souhaitez
                            // Par exemple: $('body').prepend('<div class="alert alert-success fixed-top">Anomalie reconnue!</div>');
                        } else {
                            alert('Erreur lors de l\'acquittement : ' + response.message);
                            button.prop('disabled', false).text('Marquer comme résolu'); // Réactiver le bouton
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Erreur de communication avec le serveur : ' + error);
                        console.error('AJAX Error:', status, error, xhr.responseText);
                        button.prop('disabled', false).text('Marquer comme résolu'); // Réactiver le bouton
                    }
                });
            });
        });
    </script>
</body>
</html>
