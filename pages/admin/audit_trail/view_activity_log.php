<?php
session_start();
// Check user authentication and authorization (e.g., only admins can view audit logs)
if (!isset($_SESSION['utilisateur_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'super_admin')) {
    header('Location: ../../login.php'); // Redirect to login or unauthorized page
    exit;
}


require_once('../../../templates/header.php');
require_once('../../../templates/navigation.php');
require_once '../../../fonctions/database.php';
require_once '../../../fonctions/gestion_logs.php'; // Include the logging functions

$titre = 'Journal d\'Activité (Audit Trail)';


// --- Pagination and Filtering ---
$logsPerPage = 50;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $logsPerPage;

$filterLogType = $_GET['log_type'] ?? null;
$filterUserId = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : null;
$filterStartDate = $_GET['start_date'] ?? null;
$filterEndDate = $_GET['end_date'] ?? null;

// Ensure date formats are correct for DB
if ($filterStartDate && !DateTime::createFromFormat('Y-m-d', $filterStartDate)) {
    $filterStartDate = null;
}
if ($filterEndDate && !DateTime::createFromFormat('Y-m-d', $filterEndDate)) {
    $filterEndDate = null;
}

// Fetch total count for pagination
$totalLogs = countAuditLogs($pdo, $filterLogType, $filterUserId, $filterStartDate, $filterEndDate);
$totalPages = ceil($totalLogs / $logsPerPage);

// Fetch logs with filters and pagination
$logs = getAuditLogs(
    $pdo,
    $filterLogType,
    $filterUserId,
    $filterStartDate,
    $filterEndDate,
    $logsPerPage,
    $offset
);

// Get list of users for filter dropdown
$users = [];
try {
    $stmtUsers = $pdo->query("SELECT ID_Utilisateur, Nom FROM Utilisateurs ORDER BY Nom ASC");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log this error, but don't break the page
    error_log("Erreur PDO lors de la récupération des utilisateurs pour le filtre: " . $e->getMessage());
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
    <style>
        .log-table th, .log-table td {
            vertical-align: middle;
            font-size: 0.9em;
        }
        .log-message {
            white-space: pre-wrap; /* Preserve line breaks */
            word-break: break-word; /* Break long words */
            max-width: 400px; /* Limit width for readability */
        }
        .log-type-activity { color: #337ab7; } /* Info/Blue */
        .log-type-error { color: #d9534f; font-weight: bold; } /* Red */
        .log-type-warning { color: #f0ad4e; } /* Orange */
        .log-type-info { color: #5cb85c; } /* Green */
        .pagination-container { text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

        <form action="" method="GET" class="form-inline mb-4">
            <div class="form-group">
                <label for="log_type">Type :</label>
                <select name="log_type" id="log_type" class="form-control">
                    <option value="">Tous les types</option>
                    <option value="activity" <?= $filterLogType === 'activity' ? 'selected' : '' ?>>Activité</option>
                    <option value="info" <?= $filterLogType === 'info' ? 'selected' : '' ?>>Information</option>
                    <option value="warning" <?= $filterLogType === 'warning' ? 'selected' : '' ?>>Avertissement</option>
                    <option value="error" <?= $filterLogType === 'error' ? 'selected' : '' ?>>Erreur</option>
                </select>
            </div>
            <div class="form-group">
                <label for="user_id">Utilisateur :</label>
                <select name="user_id" id="user_id" class="form-control">
                    <option value="">Tous les utilisateurs</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= htmlspecialchars($user['ID_Utilisateur']) ?>" <?= $filterUserId === (int)$user['ID_Utilisateur'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['Nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="start_date">Date début :</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($filterStartDate ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="end_date">Date fin :</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($filterEndDate ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary ml-2">Appliquer les filtres</button>
            <a href="view_activity_log.php" class="btn btn-default ml-2">Réinitialiser les filtres</a>
        </form>

        <?php if (empty($logs)): ?>
            <div class="alert alert-info">Aucun log trouvé avec les critères sélectionnés.</div>
        <?php else: ?>
            <table class="table table-bordered table-striped log-table">
                <thead>
                    <tr>
                        <th>ID Log</th>
                        <th>Date/Heure</th>
                        <th>Type</th>
                        <th>Utilisateur</th>
                        <th>Adresse IP</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['id_log']) ?></td>
                            <td><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($log['log_timestamp']))) ?></td>
                            <td class="log-type-<?= htmlspecialchars($log['log_type']) ?>">
                                <?= htmlspecialchars(ucfirst($log['log_type'])) ?>
                            </td>
                            <td><?= htmlspecialchars($log['username'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></td>
                            <td class="log-message"><?= htmlspecialchars($log['message']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <nav class="pagination-container">
                <ul class="pagination">
                    <li class="<?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                        <a href="?page=<?= $currentPage - 1 ?>&<?= http_build_query($_GET, '', '&') ?>" aria-label="Précédent">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="<?= ($i === $currentPage) ? 'active' : '' ?>">
                            <a href="?page=<?= $i ?>&<?= http_build_query($_GET, '', '&') ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="<?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                        <a href="?page=<?= $currentPage + 1 ?>&<?= http_build_query($_GET, '', '&') ?>" aria-label="Suivant">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>

    </div>
    <?php require_once('../../../templates/footer.php'); ?>
</body>
</html>