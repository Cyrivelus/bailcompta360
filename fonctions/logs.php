<?php
// fonctions/logs.php

if (!function_exists('logMessage')) {
    function logMessage($level, $message) {
        $logFile = '../../../logs/application.log';
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[{$timestamp}] [{$level}] {$message}\n";

        // Vérifier si le dossier de logs existe, le créer si nécessaire
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0777, true)) {
                // Erreur lors de la création du dossier, vous pouvez la loguer ailleurs ou afficher un message
                error_log("Erreur: Impossible de créer le dossier de logs: " . $logDir, 0);
                return false;
            }
        }

        // Tenter d'écrire dans le fichier de log
        if (file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX)) {
            return true;
        } else {
            // Erreur lors de l'écriture dans le fichier de log
            error_log("Erreur: Impossible d'écrire dans le fichier de log: " . $logFile, 0);
            return false;
        }
    }
}

// Définir des constantes pour les niveaux de log (facultatif mais bonne pratique)
if (!defined('LOG_LEVEL_ERROR')) define('LOG_LEVEL_ERROR', 'ERROR');
if (!defined('LOG_LEVEL_WARNING')) define('LOG_LEVEL_WARNING', 'WARNING');
if (!defined('LOG_LEVEL_INFO')) define('LOG_LEVEL_INFO', 'INFO');
if (!defined('LOG_LEVEL_DEBUG')) define('LOG_LEVEL_DEBUG', 'DEBUG');
if (!defined('LOG_LEVEL_SECURITY')) define('LOG_LEVEL_SECURITY', 'SECURITY');

?>