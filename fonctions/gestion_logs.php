<?php
// fonctions/gestion_logs.php
// (Assuming database.php and other includes are handled)

// ... (existing log functions like logUserActivity, logApplicationError) ...

/**
 * Retrieves activity logs from the database.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param string|null $logType Optional: Filter by log type ('activity', 'error', 'warning').
 * @param int|null $userId Optional: Filter by specific user ID.
 * @param string|null $startDate Optional: Start date for filtering (YYYY-MM-DD).
 * @param string|null $endDate Optional: End date for filtering (YYYY-MM-DD).
 * @param int $limit Optional: Limit the number of results.
 * @param int $offset Optional: Offset for pagination.
 * @return array An array of log entries.
 */
 

 
function getAuditLogs(
    PDO $pdo,
    ?string $logType = null,
    ?int $userId = null,
    ?string $startDate = null,
    ?string $endDate = null,
    int $limit = 50,
    int $offset = 0
): array {
    $sql = "
        SELECT
            l.id_log,
            l.log_type,
            l.message,
            l.log_timestamp,
            l.id_utilisateur,
            u.Nom_Utilisateur AS username,
            l.ip_address,
            l.user_agent
        FROM
            Logs l
        LEFT JOIN
            Utilisateurs u ON l.id_utilisateur = u.ID_Utilisateur
    ";
    $params = [];
    $whereClauses = [];

    if ($logType !== null) {
        $whereClauses[] = "l.log_type = :log_type";
        $params[':log_type'] = $logType;
    }
    if ($userId !== null) {
        $whereClauses[] = "l.id_utilisateur = :id_utilisateur";
        $params[':id_utilisateur'] = $userId;
    }
    if ($startDate !== null) {
        $whereClauses[] = "l.log_timestamp >= :start_date";
        $params[':start_date'] = $startDate . ' 00:00:00'; // Start of the day
    }
    if ($endDate !== null) {
        $whereClauses[] = "l.log_timestamp <= :end_date";
        $params[':end_date'] = $endDate . ' 23:59:59'; // End of the day
    }

    if (!empty($whereClauses)) {
        $sql .= " WHERE " . implode(" AND ", $whereClauses);
    }

    $sql .= " ORDER BY l.log_timestamp DESC LIMIT :limit OFFSET :offset;";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Log the error internally, but don't expose sensitive info to user
        error_log("Erreur PDO lors de la récupération des logs d'audit: " . $e->getMessage());
        return [];
    }
}

/**
 * Counts total audit logs for pagination.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param string|null $logType Optional: Filter by log type.
 * @param int|null $userId Optional: Filter by specific user ID.
 * @param string|null $startDate Optional: Start date for filtering.
 * @param string|null $endDate Optional: End date for filtering.
 * @return int The total count of log entries.
 */
function countAuditLogs(
    PDO $pdo,
    ?string $logType = null,
    ?int $userId = null,
    ?string $startDate = null,
    ?string $endDate = null
): int {
    $sql = "SELECT COUNT(*) FROM Logs l LEFT JOIN Utilisateurs u ON l.id_utilisateur = u.ID_Utilisateur";
    $params = [];
    $whereClauses = [];

    if ($logType !== null) {
        $whereClauses[] = "l.log_type = :log_type";
        $params[':log_type'] = $logType;
    }
    if ($userId !== null) {
        $whereClauses[] = "l.id_utilisateur = :id_utilisateur";
        $params[':id_utilisateur'] = $userId;
    }
    if ($startDate !== null) {
        $whereClauses[] = "l.log_timestamp >= :start_date";
        $params[':start_date'] = $startDate . ' 00:00:00';
    }
    if ($endDate !== null) {
        $whereClauses[] = "l.log_timestamp <= :end_date";
        $params[':end_date'] = $endDate . ' 23:59:59';
    }

    if (!empty($whereClauses)) {
        $sql .= " WHERE " . implode(" AND ", $whereClauses);
    }

    try {
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur PDO lors du comptage des logs d'audit: " . $e->getMessage());
        return 0;
    }
}

// ... (other functions) ...
?>

<?php
// fonctions/gestion_logs.php
// (Assuming database.php and other includes are handled)

// ... (existing log functions like logUserActivity, logApplicationError, getAuditLogs, countAuditLogs) ...

/**
 * Records a login attempt in the database.
 * This should be called every time a user tries to log in.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param string $usernameAttempted The username entered by the user during the login attempt.
 * @param string $ipAddress The IP address from which the attempt was made.
 * @param string $userAgent The user agent string (browser/OS).
 * @param bool $isSuccessful True if the login was successful, false otherwise.
 * @param int|null $userId If successful, the ID of the logged-in user. NULL for failures.
 * @param string|null $failureReason For failed attempts, a reason (e.g., 'invalid_password', 'user_not_found').
 * @return int|false The ID of the newly recorded login attempt, or false on failure.
 */
function recordLoginAttempt(
    PDO $pdo,
    string $usernameAttempted,
    string $ipAddress,
    string $userAgent,
    bool $isSuccessful,
    ?int $userId = null,
    ?string $failureReason = null
): int|false {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO Login_Attempts
            (id_utilisateur, username_attempted, login_timestamp, ip_address, user_agent, is_successful, failure_reason)
            VALUES (:id_utilisateur, :username_attempted, NOW(), :ip_address, :user_agent, :is_successful, :failure_reason)
        ");
        $stmt->bindParam(':id_utilisateur', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':username_attempted', $usernameAttempted, PDO::PARAM_STR);
        $stmt->bindParam(':ip_address', $ipAddress, PDO::PARAM_STR);
        $stmt->bindParam(':user_agent', $userAgent, PDO::PARAM_STR);
        $stmt->bindParam(':is_successful', $isSuccessful, PDO::PARAM_BOOL);
        $stmt->bindParam(':failure_reason', $failureReason, PDO::PARAM_STR);
        $stmt->execute();
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        // Log this error internally, as failure to record login attempts is critical
        error_log("Erreur PDO lors de l'enregistrement de la tentative de connexion: " . $e->getMessage());
        return false;
    }
}

/**
 * Retrieves login history from the database.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int|null $userId Optional: Filter by specific user ID.
 * @param bool|null $isSuccessful Optional: Filter by success status (true for successful, false for failed, null for all).
 * @param string|null $startDate Optional: Start date for filtering (YYYY-MM-DD).
 * @param string|null $endDate Optional: End date for filtering (YYYY-MM-DD).
 * @param int $limit Optional: Limit the number of results per page.
 * @param int $offset Optional: Offset for pagination.
 * @return array An array of login attempt entries.
 */
function getLoginHistory(
    PDO $pdo,
    ?int $userId = null,
    ?bool $isSuccessful = null,
    ?string $startDate = null,
    ?string $endDate = null,
    int $limit = 50,
    int $offset = 0
): array {
    $sql = "
        SELECT
            la.id_attempt,
            la.username_attempted,
            la.login_timestamp,
            la.ip_address,
            la.user_agent,
            la.is_successful,
            la.failure_reason,
            u.Nom_Utilisateur AS actual_username -- Actual username if id_utilisateur is not NULL
        FROM
            Login_Attempts la
        LEFT JOIN
            Utilisateurs u ON la.id_utilisateur = u.ID_Utilisateur
    ";
    $params = [];
    $whereClauses = [];

    if ($userId !== null) {
        $whereClauses[] = "la.id_utilisateur = :id_utilisateur";
        $params[':id_utilisateur'] = $userId;
    }
    if ($isSuccessful !== null) {
        $whereClauses[] = "la.is_successful = :is_successful";
        $params[':is_successful'] = (int)$isSuccessful;
    }
    if ($startDate !== null) {
        $whereClauses[] = "la.login_timestamp >= :start_date";
        $params[':start_date'] = $startDate . ' 00:00:00';
    }
    if ($endDate !== null) {
        $whereClauses[] = "la.login_timestamp <= :end_date";
        $params[':end_date'] = $endDate . ' 23:59:59';
    }

    if (!empty($whereClauses)) {
        $sql .= " WHERE " . implode(" AND ", $whereClauses);
    }

    $sql .= " ORDER BY la.login_timestamp DESC LIMIT :limit OFFSET :offset;";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération de l'historique de connexion: " . $e->getMessage());
        return [];
    }
}

/**
 * Counts total login history records for pagination.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int|null $userId Optional: Filter by specific user ID.
 * @param bool|null $isSuccessful Optional: Filter by success status.
 * @param string|null $startDate Optional: Start date for filtering.
 * @param string|null $endDate Optional: End date for filtering.
 * @return int The total count of login attempt entries.
 */
function countLoginHistory(
    PDO $pdo,
    ?int $userId = null,
    ?bool $isSuccessful = null,
    ?string $startDate = null,
    ?string $endDate = null
): int {
    $sql = "SELECT COUNT(*) FROM Login_Attempts la LEFT JOIN Utilisateurs u ON la.id_utilisateur = u.ID_Utilisateur";
    $params = [];
    $whereClauses = [];

    if ($userId !== null) {
        $whereClauses[] = "la.id_utilisateur = :id_utilisateur";
        $params[':id_utilisateur'] = $userId;
    }
    if ($isSuccessful !== null) {
        $whereClauses[] = "la.is_successful = :is_successful";
        $params[':is_successful'] = (int)$isSuccessful;
    }
    if ($startDate !== null) {
        $whereClauses[] = "la.login_timestamp >= :start_date";
        $params[':start_date'] = $startDate . ' 00:00:00';
    }
    if ($endDate !== null) {
        $whereClauses[] = "la.login_timestamp <= :end_date";
        $params[':end_date'] = $endDate . ' 23:59:59';
    }

    if (!empty($whereClauses)) {
        $sql .= " WHERE " . implode(" AND ", $whereClauses);
    }

    try {
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur PDO lors du comptage de l'historique de connexion: " . $e->getMessage());
        return 0;
    }
}

// ... (other functions) ...
?>
<?php
// fonctions/gestion_logs.php

// Requires database.php or similar if logging to DB
// require_once 'database.php'; // Adjust path if needed

function logUserActivity($activityDescription) {
    global $pdo; // If you intend to use the PDO object for database logging

    $logFilePath = __DIR__ . '/../logs/user_activity.log'; // Path to your log file

    $timestamp = date('Y-m-d H:i:s');
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'N/A';
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest'; // Assuming you store username in session

    $logEntry = sprintf("[%s] User ID: %s (%s) - %s\n", $timestamp, $userId, $username, $activityDescription);

    // Option 1: Log to a file
    error_log($logEntry, 3, $logFilePath);

    // Option 2: Log to a database (example, adjust table/columns as needed)
    /*
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("INSERT INTO User_Logs (UserID, Activity, Timestamp) VALUES (:user_id, :activity, :timestamp)");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':activity', $activityDescription, PDO::PARAM_STR);
            $stmt->bindParam(':timestamp', $timestamp, PDO::PARAM_STR);
            $stmt->execute();
        } catch (PDOException $e) {
            // Handle database logging error (e.g., log to file instead, or just suppress)
            error_log("Database log error: " . $e->getMessage() . " - " . $logEntry, 3, $logFilePath);
        }
    }
    */
}

function logApplicationError($errorDescription) {
    $logFilePath = __DIR__ . '/../logs/application_errors.log'; // Path to your error log file

    $timestamp = date('Y-m-d H:i:s');
    $logEntry = sprintf("[%s] APPLICATION ERROR: %s\n", $timestamp, $errorDescription);
    error_log($logEntry, 3, $logFilePath);
}
?>