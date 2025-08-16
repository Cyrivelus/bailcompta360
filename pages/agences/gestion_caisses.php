<?php
// pages/agence/gestion_caisses.php

// 1. Démarrer la session en premier
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Inclure la connexion à la base de données et les modules de fonctions
require_once '../../database.php'; // Assurez-vous que ce fichier définit la variable $pdo
require_once '../../fonctions/gestion_agences.php'; // Utile si vous avez des fonctions spécifiques aux agences
require_once '../../fonctions/gestion_operations_caisse.php'; // Contient getCaisseOuverte, ouvrirCaisse, fermerCaisse, calculerSoldeCourant

// 3. Vérification de l'authentification et du rôle
// Utiliser $_SESSION['utilisateur_role'] comme établi dans authentification.php
if (!isset($_SESSION['utilisateur_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Caissiere')) {
    header("Location: ../../index.php"); // Redirection vers la page de connexion si non authentifié ou non autorisé
    exit();
}

// 4. Initialisation des variables pour la vue et récupération des messages flash
$message = $_SESSION['admin_message_success'] ?? ($_SESSION['admin_message_error'] ?? '');
$message_type = isset($_SESSION['admin_message_success']) ? 'success' : (isset($_SESSION['admin_message_error']) ? 'danger' : '');
unset($_SESSION['admin_message_success'], $_SESSION['admin_message_error']);

$solde_initial = 0.0;
$solde_courant = 0.0;
$caisse_ouverte = false;
$mouvement_en_cours = false; // Initialisation pour éviter les avertissements si getCaisseOuverte retourne false

$id_utilisateur = $_SESSION['utilisateur_id'];

// 5. Vérifier et assigner l'ID de l'agence à l'utilisateur si ce n'est pas déjà fait
// Cette logique est essentielle pour lier le caissier à son agence.
if (!isset($_SESSION['id_agence'])) {
    try {
        // Récupération du CodeAgence depuis la table 'utilisateurs'
        // Assurez-vous que la colonne CodeAgence existe bien dans votre table 'utilisateurs'
        $stmt = $pdo->prepare("SELECT CodeAgence FROM utilisateurs WHERE ID_Utilisateur = ? LIMIT 1");
        $stmt->execute([$id_utilisateur]);
        $id_agence_from_db = $stmt->fetchColumn();

        if (!$id_agence_from_db) {
            $_SESSION['admin_message_error'] = "Votre compte n'est pas associé à une agence. Veuillez contacter l'administrateur.";
            header("Location: ../../index.php"); // Redirection vers le tableau de bord général
            exit();
        }
        $_SESSION['id_agence'] = $id_agence_from_db; // Stocke le CodeAgence dans la session
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération du Code d'agence (gestion_caisses.php) : " . $e->getMessage());
        $_SESSION['admin_message_error'] = "Erreur interne de base de données. Veuillez réessayer.";
        header("Location: ../../index.php");
        exit();
    }
}
$id_agence = $_SESSION['id_agence']; // Variable utilisée dans les fonctions de gestion de caisse

// 6. Traitement des actions POST (Ouvrir/Fermer Caisse)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        // Re-vérifier l'état de la caisse AVANT de traiter l'action POST pour une logique cohérente
        $mouvement_en_cours_post_check = getCaisseOuverte($pdo, $id_utilisateur);

        switch ($_POST['action']) {
            case 'ouvrir_caisse':
                $solde_initial_saisi = floatval($_POST['solde_initial'] ?? 0);
                if ($solde_initial_saisi < 0) {
                    $_SESSION['admin_message_error'] = "Le solde initial ne peut pas être négatif.";
                } elseif ($mouvement_en_cours_post_check) { // Si caisse_ouverte est TRUE ici
                    $_SESSION['admin_message_error'] = "Une caisse est déjà ouverte pour vous.";
                } else {
                    if (ouvrirCaisse($pdo, $id_utilisateur, $id_agence, $solde_initial_saisi)) {
                        $_SESSION['admin_message_success'] = "La caisse a été ouverte avec un solde de " . number_format($solde_initial_saisi, 2, ',', ' ') . " F CFA.";
                    } else {
                        $_SESSION['admin_message_error'] = "Échec de l'ouverture de la caisse. Vérifiez les logs.";
                    }
                }
                break;

            case 'fermer_caisse':
                $solde_final_declare = floatval($_POST['solde_final'] ?? 0);
                if ($mouvement_en_cours_post_check) { // Si caisse_ouverte est TRUE ici
                    // Recalculer le solde courant avant la fermeture
                    $solde_courant_calc = calculerSoldeCourant($pdo, $mouvement_en_cours_post_check['id_mouvement_caisse']);
                    $ecart = $solde_final_declare - $solde_courant_calc;
                    
                    if (fermerCaisse($pdo, $mouvement_en_cours_post_check['id_mouvement_caisse'], $solde_final_declare, $ecart)) {
                        $_SESSION['admin_message_success'] = "La caisse a été fermée avec succès. Écart : " . number_format($ecart, 2, ',', ' ') . " F CFA.";
                    } else {
                        $_SESSION['admin_message_error'] = "Échec de la fermeture de la caisse. Vérifiez les logs.";
                    }
                } else {
                    $_SESSION['admin_message_error'] = "Aucune caisse n'est ouverte à fermer.";
                }
                break;
        }
    } catch (PDOException $e) {
        error_log("Erreur PDO lors du traitement de l'action de caisse : " . $e->getMessage());
        $_SESSION['admin_message_error'] = "Erreur de base de données lors de l'opération. Veuillez réessayer.";
    }
    // Redirection unique après traitement POST pour éviter la soumission multiple et mettre à jour l'état
    header("Location: gestion_caisses.php");
    exit();
}

// 7. Vérification de l'état de la caisse pour l'affichage (après POST si redirection a eu lieu)
try {
    $mouvement_en_cours = getCaisseOuverte($pdo, $id_utilisateur); // Récupère l'état le plus récent
    if ($mouvement_en_cours) {
        $caisse_ouverte = true;
        $solde_initial = $mouvement_en_cours['solde_initial'];
        $solde_courant = calculerSoldeCourant($pdo, $mouvement_en_cours['id_mouvement_caisse']);
    } else {
        $caisse_ouverte = false; // S'assurer que c'est bien false si aucune caisse n'est trouvée
    }
} catch (PDOException $e) {
    error_log("Erreur PDO lors de la vérification de l'état de la caisse pour l'affichage : " . $e->getMessage());
    // Ceci s'affichera directement sur la page car la redirection est passée
    $message = "Erreur de base de données : " . htmlspecialchars($e->getMessage());
    $message_type = 'danger';
}

// 8. Inclure les fichiers de vue (HTML)
// L'ordre est important : header avant navigation si navigation est dans le corps du header
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

    <div class="card p-4 shadow-sm">
        <?php if (!$caisse_ouverte): ?>
            <h4>Ouvrir la Caisse</h4>
            <form action="gestion_caisses.php" method="post">
                <input type="hidden" name="action" value="ouvrir_caisse">
                <div class="mb-3">
                    <label for="solde_initial" class="form-label">Solde Initial de la Journée</label>
                    <input type="number" step="0.01" class="form-control" id="solde_initial" name="solde_initial" required>
                </div>
                <button type="submit" class="btn btn-success">Ouvrir la Caisse</button>
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
                <button type="submit" class="btn btn-danger">Fermer la Caisse</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php
// Inclure le footer de la page
include '../../templates/footer.php';
?>