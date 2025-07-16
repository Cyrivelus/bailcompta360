<?php

require_once 'database.php'; // Ensure your database connection is accessible
require_once 'gestion_logs.php'; // For logging actions and errors

/**
 * Creates a new notification in the database.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $userId The ID of the user to whom the notification is addressed.
 * @param string $type The type of notification (e.g., 'system', 'alert', 'info', 'warning', 'error').
 * @param string $message The content of the notification message.
 * @param string|null $link Optional: A URL or internal path related to the notification.
 * @param int|null $relatedId Optional: ID of a related entity (e.g., ID_Ecriture, id_budget).
 * @return int|false The ID of the newly created notification, or false on failure.
 */
function createNotification(
    PDO $pdo,
    int $userId,
    string $type,
    string $message,
    ?string $link = null,
    ?int $relatedId = null
): int|false {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO Notifications
            (id_utilisateur, type_notification, message_notification, link_notification, related_id, created_at, is_read)
            VALUES (:id_utilisateur, :type_notification, :message_notification, :link_notification, :related_id, NOW(), 0)
        ");
        $stmt->bindParam(':id_utilisateur', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':type_notification', $type, PDO::PARAM_STR);
        $stmt->bindParam(':message_notification', $message, PDO::PARAM_STR);
        $stmt->bindParam(':link_notification', $link, PDO::PARAM_STR);
        $stmt->bindParam(':related_id', $relatedId, PDO::PARAM_INT);
        $stmt->execute();
        $notificationId = $pdo->lastInsertId();

        logActivity("Notification ID {$notificationId} créée pour Utilisateur ID {$userId} (Type: {$type}).");
        return (int)$notificationId;

    } catch (PDOException $e) {
        logError("Erreur PDO lors de la création de la notification: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        logError("Erreur lors de la création de la notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Retrieves notifications for a specific user, with optional filtering.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $userId The ID of the user.
 * @param bool|null $isRead Optional: Filter by read status (true for read, false for unread, null for all).
 * @param string|null $type Optional: Filter by notification type.
 * @param int $limit Optional: Limit the number of results.
 * @param int $offset Optional: Offset for pagination.
 * @return array An array of notifications.
 */
function getNotifications(
    PDO $pdo,
    int $userId,
    ?bool $isRead = null,
    ?string $type = null,
    int $limit = 20,
    int $offset = 0
): array {
    $sql = "
        SELECT
            id_notification,
            type_notification,
            message_notification,
            link_notification,
            related_id,
            created_at,
            is_read
        FROM
            Notifications
        WHERE
            id_utilisateur = :id_utilisateur
    ";
    $params = [':id_utilisateur' => $userId];
    $whereClauses = [];

    if ($isRead !== null) {
        $whereClauses[] = "is_read = :is_read";
        $params[':is_read'] = (int)$isRead; // PDO expects integer for boolean
    }
    if ($type !== null) {
        $whereClauses[] = "type_notification = :type_notification";
        $params[':type_notification'] = $type;
    }

    if (!empty($whereClauses)) {
        $sql .= " AND " . implode(" AND ", $whereClauses);
    }

    $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset;";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => &$val) { // Bind parameters by reference for execute
            $stmt->bindParam($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logError("Erreur PDO lors de la récupération des notifications pour Utilisateur ID {$userId}: " . $e->getMessage());
        return [];
    }
}

/**
 * Counts unread notifications for a specific user.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $userId The ID of the user.
 * @return int The count of unread notifications.
 */
function countUnreadNotifications(PDO $pdo, int $userId): int {
    $sql = "SELECT COUNT(*) FROM Notifications WHERE id_utilisateur = :id_utilisateur AND is_read = 0";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_utilisateur', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        logError("Erreur PDO lors du comptage des notifications non lues pour Utilisateur ID {$userId}: " . $e->getMessage());
        return 0;
    }
}

/**
 * Marks one or more notifications as read or unread.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int|array $notificationIds The ID(s) of the notification(s) to update.
 * @param bool $markAsRead True to mark as read, false to mark as unread.
 * @param int $userId The ID of the user who owns the notifications (for security).
 * @return bool True on success, false on failure.
 */
function updateNotificationReadStatus(
    PDO $pdo,
    int|array $notificationIds,
    bool $markAsRead,
    int $userId
): bool {
    if (!is_array($notificationIds)) {
        $notificationIds = [$notificationIds];
    }
    if (empty($notificationIds)) {
        return true; // Nothing to update
    }

    $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
    $sql = "UPDATE Notifications SET is_read = :is_read WHERE id_notification IN ({$placeholders}) AND id_utilisateur = :id_utilisateur";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':is_read', $markAsRead, PDO::PARAM_BOOL);
        $stmt->bindParam(':id_utilisateur', $userId, PDO::PARAM_INT);

        // Bind each notification ID
        foreach ($notificationIds as $index => $id) {
            $stmt->bindValue($index + 3, $id, PDO::PARAM_INT); // +3 because of the first two bound parameters
        }
        $stmt->execute();

        logActivity("Notifications ID(s) " . implode(', ', $notificationIds) . " marquées comme " . ($markAsRead ? 'lues' : 'non lues') . " par Utilisateur ID {$userId}.");
        return true;

    } catch (PDOException $e) {
        logError("Erreur PDO lors de la mise à jour du statut de lecture des notifications: " . $e->getMessage());
        return false;
    }
}

/**
 * Deletes one or more notifications.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int|array $notificationIds The ID(s) of the notification(s) to delete.
 * @param int $userId The ID of the user who owns the notifications (for security).
 * @return bool True on success, false on failure.
 */
function deleteNotifications(PDO $pdo, int|array $notificationIds, int $userId): bool {
    if (!is_array($notificationIds)) {
        $notificationIds = [$notificationIds];
    }
    if (empty($notificationIds)) {
        return true; // Nothing to delete
    }

    $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
    $sql = "DELETE FROM Notifications WHERE id_notification IN ({$placeholders}) AND id_utilisateur = :id_utilisateur";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_utilisateur', $userId, PDO::PARAM_INT);

        // Bind each notification ID
        foreach ($notificationIds as $index => $id) {
            $stmt->bindValue($index + 2, $id, PDO::PARAM_INT); // +2 because of the first bound parameter
        }
        $stmt->execute();

        logActivity("Notifications ID(s) " . implode(', ', $notificationIds) . " supprimées par Utilisateur ID {$userId}.");
        return true;

    } catch (PDOException $e) {
        logError("Erreur PDO lors de la suppression des notifications: " . $e->getMessage());
        return false;
    }
}



