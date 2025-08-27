<?php
// pages/agence/gestion_caisses.php

// 1. Démarrer la session en premier
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Inclure la connexion à la base de données et les modules de fonctions
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_agences.php';
require_once '../../fonctions/gestion_operations_caisse.php';

// 3. Vérification de l'authentification et du rôle
if (!isset($_SESSION['utilisateur_id'])) {
    header("Location: ../../index.php");
    exit();
}

$id_utilisateur = $_SESSION['utilisateur_id'];
$id_agence = $_SESSION['id_agence'] ?? null;
if (!$id_agence) {
    try {
        $stmt = $pdo->prepare("SELECT CodeAgence FROM utilisateurs WHERE ID_Utilisateur = ? LIMIT 1");
        $stmt->execute([$id_utilisateur]);
        $id_agence_from_db = $stmt->fetchColumn();
        if (!$id_agence_from_db) {
            $_SESSION['admin_message_error'] = "Votre compte n'est pas associé à une agence. Veuillez contacter l'administrateur.";
            header("Location: ../../index.php");
            exit();
        }
        $_SESSION['id_agence'] = $id_agence_from_db;
        $id_agence = $id_agence_from_db;
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération du Code d'agence (gestion_caisses.php) : " . $e->getMessage());
        $_SESSION['admin_message_error'] = "Erreur interne de base de données. Veuillez réessayer.";
        header("Location: ../../index.php");
        exit();
    }
}

// 4. Traitement des actions POST (Ouvrir/Fermer/Supprimer Solde)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $mouvement_en_cours_post_check = getCaisseOuverte($pdo, $id_utilisateur);

        switch ($_POST['action']) {
            case 'ouvrir_caisse':
                $solde_initial_saisi = floatval($_POST['solde_initial'] ?? 0);
                if ($solde_initial_saisi < 0) {
                    $_SESSION['admin_message_error'] = "Le solde initial ne peut pas être négatif.";
                } elseif ($mouvement_en_cours_post_check) {
                    $_SESSION['admin_message_error'] = "Une caisse est déjà ouverte pour vous.";
                } else {
                    ouvrirCaisse($pdo, $id_utilisateur, $id_agence, $solde_initial_saisi);
                    $_SESSION['admin_message_success'] = "La caisse a été ouverte avec un solde de " . number_format($solde_initial_saisi, 2, ',', ' ') . " F CFA.";
                }
                break;

            case 'fermer_caisse':
                $solde_final_declare = floatval($_POST['solde_final'] ?? 0);
                if ($mouvement_en_cours_post_check) {
                    $ecart_calcule = fermerCaisse($pdo, $mouvement_en_cours_post_check['id_mouvement_caisse'], $solde_final_declare, $id_agence);
                    $_SESSION['admin_message_success'] = "La caisse a été fermée avec succès. Écart : " . number_format($ecart_calcule, 2, ',', ' ') . " F CFA.";
                } else {
                    $_SESSION['admin_message_error'] = "Aucune caisse n'est ouverte à fermer.";
                }
                break;

            case 'supprimer_solde':
                $id_mouvement = intval($_POST['id_mouvement'] ?? 0);
                supprimerSoldeCaisse($pdo, $id_mouvement);
                $_SESSION['admin_message_success'] = "Le solde a été supprimé avec succès.";
                break;
        }
    } catch (Exception $e) {
        $_SESSION['admin_message_error'] = "Erreur : " . $e->getMessage();
    }
    header("Location: gestion_caisses.php");
    exit();
}

// 5. Préparation des données pour l'affichage (après la redirection POST)
$message = $_SESSION['admin_message_success'] ?? ($_SESSION['admin_message_error'] ?? '');
$message_type = isset($_SESSION['admin_message_success']) ? 'success' : (isset($_SESSION['admin_message_error']) ? 'danger' : '');
unset($_SESSION['admin_message_success'], $_SESSION['admin_message_error']);

$solde_initial = 0.0;
$solde_courant = 0.0;
$caisse_ouverte = false;
$mouvement_en_cours = getCaisseOuverte($pdo, $id_utilisateur);

if ($mouvement_en_cours) {
    $caisse_ouverte = true;
    $solde_initial = $mouvement_en_cours['solde_initial'];
    $solde_courant = calculerSoldeCourant($pdo, $mouvement_en_cours['id_mouvement_caisse']);
}

// Récupérer l'historique complet des mouvements de caisse
$historique_caisses = getHistoriqueCaisses($pdo);

// 6. Inclure les fichiers de vue (HTML)
include '../../templates/header.php';
include '../../templates/navigation.php';

?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Gestion de la Caisse</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card p-4 shadow-sm mb-4">
        <?php if (!$caisse_ouverte): ?>
            <h4>Ouvrir la Caisse</h4>
            <form action="gestion_caisses.php" method="post">
                <input type="hidden" name="action" value="ouvrir_caisse">
                <div class="mb-3">
                    <label for="solde_initial" class="form-label">Solde Initial de la Journée</label>
                    <input type="number" step="0.01" class="form-control" id="solde_initial" name="solde_initial" required>
                </div>
                <button type="submit" name="effectuer_ouverture" class="btn btn-success">Ouvrir la Caisse</button>
            </form>
        <?php else: ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> La caisse est actuellement ouverte.
            </div>
            <h4>Informations Caisse</h4>
            <ul class="list-group mb-4">
                <li class="list-group-item"><strong>Solde Initial :</strong> <?= number_format($solde_initial, 2, ',', ' '); ?> F CFA</li>
                <li class="list-group-item"><strong>Solde Courant :</strong> <?= number_format($solde_courant, 2, ',', ' '); ?> F CFA</li>
            </ul>
            <a href="transactions_caisse.php" class="btn btn-info mb-3">
                <i class="fas fa-cash-register"></i> Effectuer des Transactions
            </a>
            <h4 class="mt-4">Fermer la Caisse</h4>
            <form action="gestion_caisses.php" method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir fermer la caisse pour la journée ?');">
                <input type="hidden" name="action" value="fermer_caisse">
                <div class="mb-3">
                    <label for="solde_final" class="form-label">Solde Final Compté</label>
                    <input type="number" step="0.01" class="form-control" id="solde_final" name="solde_final" required>
                </div>
                <button type="submit" name="effectuer_fermeture" class="btn btn-danger">Fermer la Caisse</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="card p-4 shadow-sm">
        <h4>Historique des Mouvements de Caisse</h4>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Utilisateur</th>
                        <th>Date Ouverture</th>
                        <th>Solde Initial</th>
                        <th>Date Fermeture</th>
                        <th>Solde Final Déclaré</th>
                        <th>Solde Calculé</th>
                        <th>Écart</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($historique_caisses)): ?>
                        <tr>
                            <td colspan="10" class="text-center">Aucun mouvement de caisse enregistré.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($historique_caisses as $mouvement): ?>
                            <tr>
                                <td><?= htmlspecialchars($mouvement['id_mouvement_caisse']); ?></td>
                                <td><?= htmlspecialchars($mouvement['nom_utilisateur']); ?></td>
                                <td><?= htmlspecialchars($mouvement['date_ouverture']); ?></td>
                                <td><?= number_format($mouvement['solde_initial'], 2, ',', ' '); ?></td>
                                <td><?= htmlspecialchars($mouvement['date_fermeture'] ?? 'N/A'); ?></td>
                                <td><?= number_format($mouvement['solde_final'] ?? 0, 2, ',', ' '); ?></td>
                                <td><?= number_format($mouvement['solde_final_calcule'] ?? 0, 2, ',', ' '); ?></td>
                                <td><?= number_format($mouvement['ecart'] ?? 0, 2, ',', ' '); ?></td>
                                <td>
                                    <?php if ($mouvement['date_fermeture']): ?>
                                        <span class="badge bg-danger">Fermée</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Ouverte</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($mouvement['date_fermeture']): ?>
                                        <form action="gestion_caisses.php" method="post" onsubmit="return confirm('Attention ! Cela va réinitialiser les soldes. Continuer ?');">
                                            <input type="hidden" name="action" value="supprimer_solde">
                                            <input type="hidden" name="id_mouvement" value="<?= htmlspecialchars($mouvement['id_mouvement_caisse']); ?>">
                                            <button type="submit" class="btn btn-sm btn-warning">Supprimer Solde</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include '../../templates/footer.php';
?>