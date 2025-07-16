<?php
session_start();
// Check user authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /index.php'); // Redirect to login(index)
    exit;
}

require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_notifications.php'; // This file is crucial for notification logic

$titre = 'Mes Notifications';
$pdo = getPDO();
$userId = $_SESSION['user_id'];

$filter = $_GET['filter'] ?? 'all'; // 'all', 'unread', 'read'
$notifications = [];

// Fetch notifications based on the selected filter
if ($filter === 'unread') {
    $notifications = getNotifications($pdo, $userId, false); // Assuming getNotifications(PDO $pdo, int $userId, ?bool $isRead = null)
} elseif ($filter === 'read') {
    $notifications = getNotifications($pdo, $userId, true);
} else {
    $notifications = getNotifications($pdo, $userId); // Gets all notifications for the user
}

// Handle actions (mark as read/unread, delete) via POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $notificationIds = $_POST['notification_ids'] ?? []; // Array of notification IDs from checkboxes
    if (!empty($notificationIds) && is_array($notificationIds)) {
        if ($_POST['action'] === 'mark_read') {
            updateNotificationReadStatus($pdo, $notificationIds, true, $userId); // Assuming updateNotificationReadStatus(PDO $pdo, array $notificationIds, bool $isRead, int $userId)
        } elseif ($_POST['action'] === 'mark_unread') {
            updateNotificationReadStatus($pdo, $notificationIds, false, $userId);
        } elseif ($_POST['action'] === 'delete') {
            deleteNotifications($pdo, $notificationIds, $userId); // Assuming deleteNotifications(PDO $pdo, array $notificationIds, int $userId)
        }
        // Redirect to clear POST data and show updated list
        header('Location: index.php?filter=' . urlencode($filter));
        exit;
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
    <style>
        /* Styling for individual notification items based on type and read status */
        .notification-item {
            padding: 10px;
            margin-bottom: 10px;
            border-left: 5px solid;
            border-radius: 4px; /* Slightly rounded corners */
        }
        .notification-item.info { border-color: #5bc0de; background-color: #d9edf7; }
        .notification-item.alert { border-color: #f0ad4e; background-color: #fcf8e3; }
        .notification-item.warning { border-color: #f0ad4e; background-color: #fcf8e3; }
        .notification-item.error { border-color: #d9534f; background-color: #f2dede; }
        .notification-item.system { border-color: #337ab7; background-color: #e6f7ff; }
        .notification-item.read { opacity: 0.7; background-color: #f5f5f5; border-color: #ddd; } /* Faded for read notifications */
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px; /* Spacing between header and message */
        }
        .notification-actions button { margin-left: 5px; }
        .notification-item p { margin-bottom: 0; } /* Remove default paragraph margin */
        .mb-4 { margin-bottom: 20px; } /* Added for filter button spacing */
        .well { padding: 15px; margin-bottom: 20px; background-color: #f7f7f7; border: 1px solid #e3e3e3; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

        <div class="mb-4">
            <a href="?filter=all" class="btn btn-<?= $filter === 'all' ? 'primary' : 'default' ?>">Toutes</a>
            <a href="?filter=unread" class="btn btn-<?= $filter === 'unread' ? 'primary' : 'default' ?>">Non lues <span class="badge"><?= getUnreadNotificationCount($pdo, $userId) ?></span></a>
            <a href="?filter=read" class="btn btn-<?= $filter === 'read' ? 'primary' : 'default' ?>">Lues</a>
        </div>

        <form method="POST" action="index.php?filter=<?= urlencode($filter) ?>">
            <?php if (!empty($notifications)): ?>
                <div class="well">
                    <button type="submit" name="action" value="mark_read" class="btn btn-sm btn-success">Marquer comme lu</button>
                    <button type="submit" name="action" value="mark_unread" class="btn btn-sm btn-warning">Marquer comme non lu</button>
                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer les notifications sélectionnées ?');">Supprimer</button>
                </div>

                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item <?= htmlspecialchars($notification['type_notification']) ?> <?= $notification['is_read'] ? 'read' : '' ?>">
                        <div class="notification-header">
                            <div>
                                <input type="checkbox" name="notification_ids[]" value="<?= htmlspecialchars($notification['id_notification']) ?>">
                                <strong><?= htmlspecialchars(ucfirst($notification['type_notification'])) ?></strong> -
                                <small><?= htmlspecialchars(date('d/m/Y H:i', strtotime($notification['created_at']))) ?></small>
                            </div>
                            <?php if ($notification['link_notification']): ?>
                                <a href="<?= htmlspecialchars($notification['link_notification']) ?>" class="btn btn-xs btn-info">Voir les détails</a>
                            <?php endif; ?>
                        </div>
                        <p><?= nl2br(htmlspecialchars($notification['message_notification'])) ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">Vous n'avez aucune notification <?= $filter === 'unread' ? 'non lue' : ($filter === 'read' ? 'lue' : '') ?> pour le moment.</div>
            <?php endif; ?>
        </form>

    </div>
    <?php require_once('../../templates/footer.php'); ?>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>
</html>