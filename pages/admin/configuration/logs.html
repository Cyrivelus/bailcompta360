<?php
// pages/admin/logs.php
// Visualisation des logs (sécurité, erreurs, etc.)

// Démarrer la session pour la gestion de l'authentification
session_start();

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Admin') {
    // Rediriger si non autorisé
    header("Location: ../../index.php?error=Accès non autorisé");
    exit();
}

// Inclure l'en-tête de l'administration
$title = "Visualisation des Logs";
include('../includes/header.php');

// Inclure la navigation de l'administration
include('../includes/navigation.php');

// Définir le chemin du fichier de log (à adapter selon votre configuration)
$logFilePath = '../../../logs/application.log';

// Nombre de lignes à afficher par défaut
$linesToShow = 50;

// Récupérer le nombre de lignes à afficher depuis le paramètre GET si spécifié
if (isset($_GET['lines']) && is_numeric($_GET['lines']) && $_GET['lines'] > 0) {
    $linesToShow = intval($_GET['lines']);
}

$logContent = '';
$totalLines = 0;

// Vérifier si le fichier de log existe et est lisible
if (file_exists($logFilePath) && is_readable($logFilePath)) {
    // Lire le fichier de log en commençant par la fin
    $file = new SplFileObject($logFilePath, 'r');
    $file->seek(PHP_INT_MAX);
    $lastLine = '';
    $logLines = [];
    while ($file->valid() && count($logLines) < $linesToShow) {
        $file->seek($file->key() - 1);
        $currentLine = $file->current();
        if ($currentLine !== false) {
            $logLines[] = htmlspecialchars($currentLine);
        }
    }
    $logLines = array_reverse($logLines);
    $logContent = implode("", $logLines);
    $totalLines = count(file($logFilePath));
} else {
    $logContent = '<div class="alert alert-warning">Le fichier de log est introuvable ou illisible.</div>';
}
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Visualisation des Logs</h1>
    </div>

    <p>Ici, vous pouvez visualiser les dernières entrées du fichier de log de l'application.</p>

    <div class="mb-3">
        <form method="get" class="form-inline">
            <label for="lines" class="me-2">Afficher les</label>
            <input type="number" class="form-control form-control-sm me-2" id="lines" name="lines" value="<?php echo htmlspecialchars($linesToShow); ?>" min="1">
            <label class="me-2">dernières lignes</label>
            <button type="submit" class="btn btn-sm btn-outline-secondary">Afficher</button>
            <?php if ($totalLines > $linesToShow): ?>
                <span class="ms-2 text-muted">Total de lignes dans le log : <?php echo htmlspecialchars($totalLines); ?></span>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            Contenu du Fichier de Log (<?php echo htmlspecialchars($linesToShow); ?> dernières lignes)
        </div>
        <div class="card-body">
            <pre style="max-height: 500px; overflow-y: auto; font-size: 0.8rem;"><?php echo $logContent; ?></pre>
        </div>
    </div>

</main>

<?php
// Inclure le pied de page de l'administration
include('../includes/footer.php');
?>