<?php
session_start();

// Initialiser les variables pour les messages de succès ou d'erreur
$message = '';
$messageType = ''; // Peut être 'success', 'danger', 'info'

// Check user authentication and authorization (only highly privileged admins)
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'super_admin') { // Assuming 'super_admin' role for this critical function
    // En production, décommenter la ligne ci-dessous pour rediriger
    // header('Location: ../../../login.php');
    $message = "Accès non autorisé. Vous devez être connecté en tant que 'super_admin' pour accéder à cette fonction.";
    $messageType = 'danger';
    // exit; // Décommenter en production pour arrêter l'exécution
}

// Inclure les fichiers nécessaires seulement si l'utilisateur est autorisé ou pour afficher le message d'erreur
if ($messageType !== 'danger') {
    require_once '../../../templates/header.php';
    require_once '../../../templates/navigation.php';
    require_once '../../../fonctions/database.php'; // Pour les détails de connexion à la base de données
    require_once '../../../fonctions/gestion_logs.php'; // Pour la journalisation des actions
} else {
    // Si l'utilisateur n'est pas autorisé, nous devons au moins inclure l'en-tête pour afficher le message
    // ou fournir un HTML minimal pour la page d'erreur.
    // Pour cet exemple, nous allons inclure un en-tête minimal.
    // En production, il serait préférable d'avoir une page d'erreur dédiée ou une redirection.
    $titre = 'Accès Refusé';
    // Si header.php contient toute la structure HTML, il faut l'inclure ici.
    // Sinon, un simple message d'erreur HTML peut suffire.
    // Pour rester proche de la structure originale, nous supposons que header.php et navigation.php
    // sont nécessaires pour le rendu de la page même en cas d'erreur précoce.
    require_once '../../../templates/header.php';
    require_once '../../../templates/navigation.php';
}


$titre = 'Sauvegarde et Restauration de la Base de Données';

// Directory where backups will be stored (MUST BE WRITEABLE BY WEB SERVER USER)
// It's recommended to place this outside the web root if possible for security.
$backupDir = __DIR__ . '/../../../backups'; // Example: Root/backups

// Path to MySQL dump utility (adjust for your OS/MySQL installation)
// For Windows: C:\Program Files\MySQL\MySQL Server X.X\bin\mysqldump.exe
// For Linux: /usr/bin/mysqldump or /usr/local/bin/mysqldump
// !!! IMPORTANT: Enclose paths with spaces in double quotes, and escape them properly !!!
// !!! VÉRIFIEZ ET AJUSTEZ CES CHEMINS EN FONCTION DE VOTRE INSTALLATION MYSQL !!!
$mysqldumpPath = 'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe'; // !!! ADJUST THIS PATH !!!
$mysqlPath = 'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe';     // !!! ADJUST THIS PATH !!!

// Escape the paths for shell command execution
$escapedMysqldumpPath = escapeshellarg($mysqldumpPath);
$escapedMysqlPath = escapeshellarg($mysqlPath);

// Vérifier si les exécutables existent
if (!file_exists($mysqldumpPath)) {
    $message = "Erreur: mysqldump.exe introuvable à l'emplacement spécifié: <code>" . htmlspecialchars($mysqldumpPath) . "</code>. Veuillez vérifier le chemin.";
    $messageType = 'danger';
    logApplicationError($message);
}
if (!file_exists($mysqlPath)) {
    $message = "Erreur: mysql.exe introuvable à l'emplacement spécifié: <code>" . htmlspecialchars($mysqlPath) . "</code>. Veuillez vérifier le chemin.";
    $messageType = 'danger';
    logApplicationError($message);
}

// Create backup directory if it doesn't exist
if ($messageType === '') { // Seulement si pas d'erreurs de chemin d'exécutable
    if (!is_dir($backupDir)) {
        if (!mkdir($backupDir, 0755, true)) {
            logApplicationError("Impossible de créer le répertoire de sauvegarde: {$backupDir}");
            $message = "Erreur: Le répertoire de sauvegarde n'a pas pu être créé. Vérifiez les permissions du dossier parent.";
            $messageType = 'danger';
        }
    } else {
        if (!is_writable($backupDir)) {
            $message = "Erreur: Le répertoire de sauvegarde n'est pas accessible en écriture par le serveur. Vérifiez les permissions du dossier: <code>" . htmlspecialchars($backupDir) . "</code>.";
            $messageType = 'danger';
            logApplicationError($message . " Répertoire: {$backupDir}");
        }
    }
}


// --- Handle Backup Action ---
if (isset($_POST['action']) && $_POST['action'] === 'backup' && $messageType === '') {
    // Assurez-vous que les variables de connexion à la DB sont définies par database.php
    // Si database.php ne définit pas ces variables globalement, vous devrez les récupérer autrement.
    // Pour cet exemple, nous supposons qu'elles sont disponibles.
    global $dbHost, $dbUser, $dbPass, $dbName; // Déclarer global si elles sont définies dans un autre fichier

    if (empty($dbHost) || empty($dbUser) || empty($dbName)) {
        $message = "Erreur: Les informations de connexion à la base de données (hôte, utilisateur, nom de la DB) ne sont pas correctement configurées dans 'fonctions/database.php'.";
        $messageType = 'danger';
        logApplicationError($message);
    } else {
        $backupFileName = 'bailcompta360_backup_' . date('Ymd_His') . '.sql';
        $backupFilePath = $backupDir . '/' . $backupFileName;
        $escapedBackupFilePath = escapeshellarg($backupFilePath); // Escape for shell command

        // Build the mysqldump command
        $command = "{$escapedMysqldumpPath} -h " . escapeshellarg($dbHost) . " -u " . escapeshellarg($dbUser) . " ";
        if (!empty($dbPass)) { // Only add password if it's set and not empty
            // Note: -p followed by password (no space), and escape the password too
            $command .= "-p" . escapeshellarg($dbPass) . " ";
        }
        $command .= escapeshellarg($dbName) . " > {$escapedBackupFilePath} 2>&1"; // Redirect errors to stdout

        $output = [];
        $returnVar = 0;

        // --- DEBUG: Afficher la commande exécutée pour le diagnostic ---
        $message .= "<p class='text-info'>Commande exécutée pour la sauvegarde : <code>" . htmlspecialchars($command) . "</code></p>";
        logApplicationError("Tentative de mysqldump: " . $command); // Log the command

        exec($command, $output, $returnVar);

        // Vérifier le fichier après l'exécution
        clearstatcache(); // Effacer le cache des informations de fichier
        $fileSize = file_exists($backupFilePath) ? filesize($backupFilePath) : 0;

        if ($returnVar === 0 && $fileSize > 0) {
            $message = "Sauvegarde de la base de données créée avec succès: <strong>{$backupFileName}</strong> (Taille: " . round($fileSize / 1024 / 1024, 2) . " Mo)";
            $messageType = 'success';
            logUserActivity("Sauvegarde de la base de données effectuée par l'utilisateur ID: " . $_SESSION['utilisateur_id'] . ". Fichier: {$backupFileName}");
        } else {
            $message = "Erreur lors de la création de la sauvegarde de la base de données.<br>";
            if ($fileSize === 0 && file_exists($backupFilePath)) {
                $message .= "Le fichier de sauvegarde a été créé mais est vide (0 octet).<br>";
            } else if (!file_exists($backupFilePath)) {
                $message .= "Le fichier de sauvegarde n'a pas été créé du tout.<br>";
            }
            $message .= "Code de retour de la commande: <strong>{$returnVar}</strong><br>";
            $message .= "Détails de la sortie (Stderr/Stdout): <pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
            $message .= "<p class='text-warning'>Vérifiez les points suivants :</p>";
            $message .= "<ul>";
            $message .= "<li>Le chemin vers `mysqldump.exe` est-il correct et accessible par le serveur web ? (Actuel: <code>" . htmlspecialchars($mysqldumpPath) . "</code>)</li>";
            $message .= "<li>Le répertoire de sauvegarde (<code>" . htmlspecialchars($backupDir) . "</code>) est-il accessible en écriture par l'utilisateur du serveur web (ex: `IIS_IUSRS` sur Windows, `www-data` sur Linux) ?</li>";
            $message .= "<li>Les identifiants de connexion à la base de données (hôte, utilisateur, mot de passe) dans `fonctions/database.php` sont-ils corrects et ont-ils les permissions nécessaires pour `mysqldump` ?</li>";
            $message .= "<li>Si vous êtes sur un hébergement mutualisé, la fonction `exec()` est-elle activée ?</li>";
            $message .= "<li>Essayez d'exécuter la commande affichée ci-dessus directement dans l'invite de commande de votre serveur pour voir les erreurs exactes.</li>";
            $message .= "</ul>";
            $messageType = 'danger';
            logApplicationError("Erreur mysqldump (code: {$returnVar}) par l'utilisateur ID: " . $_SESSION['utilisateur_id'] . ". Output: " . implode("\n", $output));
        }
    }
}

// --- Handle Restore Action ---
// WARNING: Restoring a database will OVERWRITE ALL CURRENT DATA!
// This is an extremely dangerous operation and should be handled with utmost care.
// It's often safer to do this manually via command line or database management tools.
// For simplicity and safety, this example primarily focuses on a basic restore.
if (isset($_POST['action']) && $_POST['action'] === 'restore' && isset($_POST['backup_file']) && $messageType === '') {
    global $dbHost, $dbUser, $dbPass, $dbName;

    $restoreFileName = basename($_POST['backup_file']); // Sanitize input to prevent path traversal
    $restoreFilePath = $backupDir . '/' . $restoreFileName;
    $escapedRestoreFilePath = escapeshellarg($restoreFilePath);

    if (!file_exists($restoreFilePath)) {
        $message = "Erreur: Le fichier de sauvegarde spécifié n'existe pas: <code>" . htmlspecialchars($restoreFileName) . "</code>.";
        $messageType = 'danger';
        logApplicationError("Tentative de restauration d'un fichier inexistant: {$restoreFileName} par utilisateur ID: " . $_SESSION['utilisateur_id']);
    } else {
        // Build the mysql command to restore
        $command = "{$escapedMysqlPath} -h " . escapeshellarg($dbHost) . " -u " . escapeshellarg($dbUser) . " ";
        if (!empty($dbPass)) {
            $command .= "-p" . escapeshellarg($dbPass) . " ";
        }
        $command .= escapeshellarg($dbName) . " < {$escapedRestoreFilePath} 2>&1";

        $output = [];
        $returnVar = 0;

        // --- DEBUG: Afficher la commande exécutée pour le diagnostic ---
        $message .= "<p class='text-info'>Commande exécutée pour la restauration : <code>" . htmlspecialchars($command) . "</code></p>";
        logApplicationError("Tentative de restauration mysql: " . $command); // Log the command

        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            $message = "Restauration de la base de données effectuée avec succès à partir de: <strong>{$restoreFileName}</strong>";
            $messageType = 'success';
            logUserActivity("Restauration de la base de données effectuée par l'utilisateur ID: " . $_SESSION['utilisateur_id'] . ". Fichier: {$restoreFileName}");
        } else {
            $message = "Erreur lors de la restauration de la base de données.<br>";
            $message .= "Code de retour de la commande: <strong>{$returnVar}</strong><br>";
            $message .= "Détails de la sortie (Stderr/Stdout): <pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
            $message .= "<p class='text-warning'>Vérifiez les points suivants :</p>";
            $message .= "<ul>";
            $message .= "<li>Le chemin vers `mysql.exe` est-il correct et accessible par le serveur web ? (Actuel: <code>" . htmlspecialchars($mysqlPath) . "</code>)</li>";
            $message .= "<li>Le fichier de sauvegarde (<code>" . htmlspecialchars($restoreFilePath) . "</code>) est-il valide et non corrompu ?</li>";
            $message .= "<li>Les identifiants de connexion à la base de données (hôte, utilisateur, mot de passe) dans `fonctions/database.php` sont-ils corrects et ont-ils les permissions nécessaires pour restaurer la base de données ?</li>";
            $message .= "<li>Essayez d'exécuter la commande affichée ci-dessus directement dans l'invite de commande de votre serveur pour voir les erreurs exactes.</li>";
            $message .= "</ul>";
            $messageType = 'danger';
            logApplicationError("Erreur mysql (code: {$returnVar}) lors de la restauration par l'utilisateur ID: " . $_SESSION['utilisateur_id'] . ". Fichier: {$restoreFileName}. Output: " . implode("\n", $output));
        }
    }
}

// --- List available backups ---
$availableBackups = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        // Exclure . et .. et s'assurer que c'est un fichier .sql
        if ($file !== '.' && $file !== '..' && preg_match('/\.sql$/i', $file)) {
            $filePath = $backupDir . '/' . $file;
            // Vérifier que le fichier existe et est lisible avant d'obtenir sa taille
            if (file_exists($filePath) && is_readable($filePath)) {
                $availableBackups[] = [
                    'name' => $file,
                    'size' => filesize($filePath),
                    'date' => filemtime($filePath)
                ];
            } else {
                logApplicationError("Fichier de sauvegarde non lisible ou introuvable lors de la liste: {$filePath}");
            }
        }
    }
    // Sort by date, newest first
    usort($availableBackups, function($a, $b) {
        return $b['date'] <=> $a['date'];
    });
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
    <link rel="stylesheet" href="../../../css/bootstrap.min.css">
    <style>
        /* Styles pour les messages d'alerte, adaptés à Bootstrap */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            color: #3c763d;
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }
        .alert-danger {
            color: #a94442;
            background-color: #f2dede;
            border-color: #ebccd1;
        }
        .alert-info {
            color: #31708f;
            background-color: #d9edf7;
            border-color: #bce8f1;
        }
        pre {
            background-color: #f5f5f5;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            white-space: pre-wrap; /* Permet le retour à la ligne pour les longues commandes */
            word-wrap: break-word;
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
                <h3 class="panel-title">Créer une nouvelle sauvegarde</h3>
            </div>
            <div class="panel-body">
                <p>Ceci créera un fichier de sauvegarde de l'intégralité de votre base de données à l'heure actuelle.</p>
                <form method="POST" action="">
                    <button type="submit" name="action" value="backup" class="btn btn-success">
                        <span class="glyphicon glyphicon-save"></span> Créer une sauvegarde maintenant
                    </button>
                </form>
            </div>
        </div>

        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title">Restaurer à partir d'une sauvegarde existante <span class="text-danger">(Attention: Ceci écrasera les données actuelles!)</span></h3>
            </div>
            <div class="panel-body">
                <?php if (empty($availableBackups)): ?>
                    <p class="text-info">Aucun fichier de sauvegarde trouvé dans le répertoire <code><?= htmlspecialchars($backupDir) ?></code>.</p>
                <?php else: ?>
                    <form method="POST" action="" onsubmit="return confirm('Êtes-vous ABSOLUMENT sûr de vouloir restaurer la base de données? Cela écrasera TOUTES les données actuelles. Cette action est IRREVERSIBLE.');">
                        <div class="form-group">
                            <label for="backup_file">Sélectionner un fichier de sauvegarde :</label>
                            <select name="backup_file" id="backup_file" class="form-control" required>
                                <?php foreach ($availableBackups as $backup): ?>
                                    <option value="<?= htmlspecialchars($backup['name']) ?>">
                                        <?= htmlspecialchars($backup['name']) ?> (<?= round($backup['size'] / 1024 / 1024, 2) ?> Mo) - <?= date('d/m/Y H:i:s', strtotime('-1 hour', $backup['date'])) ?>

                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="action" value="restore" class="btn btn-danger">
                            <span class="glyphicon glyphicon-repeat"></span> Restaurer la base de données
                        </button>
                    </form>
                    <p class="help-block text-warning mt-2">Assurez-vous d'avoir une sauvegarde récente et valide avant de procéder à une restauration.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Fichiers de sauvegarde disponibles</h3>
            </div>
            <div class="panel-body">
                <?php if (empty($availableBackups)): ?>
                    <p>Aucune sauvegarde n'est disponible.</p>
                <?php else: ?>
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Nom du fichier</th>
                                <th>Taille</th>
                                <th>Date de création</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($availableBackups as $backup): ?>
                                <tr>
                                    <td><?= htmlspecialchars($backup['name']) ?></td>
                                    <td><?= round($backup['size'] / 1024 / 1024, 2) ?> Mo</td>
                                    <td><?= date('d/m/Y H:i:s', strtotime('-1 month -1 hour', $backup['date'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    </div>
    <?php require_once '../../../templates/footer.php'; ?>
    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script src="../js/jquery-3.7.1.js"></script>
<script src="../js/bootstrap.min.js"></script>

</body>
</html>
