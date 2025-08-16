<?php
// pages/securite/audit.php

/**
 * Ce fichier gère l'interface de l'audit.
 * Il affiche l'historique des activités importantes du système.
 */

// 1. Démarrer la session en premier. C'est la première chose à faire.
session_start();

// 2. Vérifier les permissions de l'utilisateur avant d'inclure tout HTML.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    // Rediriger si l'utilisateur n'est pas un administrateur
    header("Location: ../../index.php");
    exit();
}

// 3. Inclure les fichiers de logique et de base de données.
require_once '../../database.php';
require_once '../../fonctions/gestion_audit.php';
require_once '../../fonctions/gestion_utilisateurs.php';

$message = '';
$message_type = '';
$logs = [];

// Définir les paramètres de pagination
$par_page = 20;
$page_courante = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page_courante - 1) * $par_page;

try {
    // Récupérer le nombre total d'enregistrements pour la pagination
    // Assurez-vous que ces fonctions (getNombreTotalLogs et getLogsAudit) sont définies
    // dans votre fichier 'gestion_audit.php'
    $total_logs = getNombreTotalLogs($pdo);
    $total_pages = ceil($total_logs / $par_page);

    // Récupérer les logs d'audit avec pagination
    $logs = getLogsAudit($pdo, $par_page, $offset);

} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
    $logs = [];
}

// 4. Inclure les templates qui contiennent la mise en page HTML.
// Ils peuvent maintenant démarrer la session sans problème car elle a déjà été lancée.
include '../../templates/header.php';
include '../../templates/navigation.php';

?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Journal d'Audit</h2>
        <a href="../dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour au tableau de bord
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($logs)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>ID Log</th>
                        <th>Date et Heure</th>
                        <th>Utilisateur</th>
                        <th>Action</th>
                        <th>Détails</th>
                        <th>Adresse IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['id_log']); ?></td>
                            <td><?php echo htmlspecialchars($log['date_log']); ?></td>
                            <td><?php echo htmlspecialchars($log['nom_utilisateur']); ?></td>
                            <td><span class="badge bg-primary"><?php echo htmlspecialchars($log['action_type']); ?></span></td>
                            <td><?php echo htmlspecialchars($log['details']); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <nav aria-label="Pagination">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo ($page_courante <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="audit.php?page=<?php echo $page_courante - 1; ?>">Précédent</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($page_courante == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="audit.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo ($page_courante >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="audit.php?page=<?php echo $page_courante + 1; ?>">Suivant</a>
                </li>
            </ul>
        </nav>

    <?php else: ?>
        <div class="alert alert-info text-center" role="alert">
            Aucun log d'audit trouvé.
        </div>
    <?php endif; ?>
</div>

<?php
// Inclure le footer de la page
include '../../templates/footer.php';
?>