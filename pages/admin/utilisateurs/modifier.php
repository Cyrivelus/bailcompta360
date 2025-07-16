<?php
// pages/admin/utilisateurs/modifier.php

// Démarrer la session si ce n'est pas déjà fait
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté et est un administrateur
// Assurez-vous que 'utilisateur_id' et 'role' sont les bonnes clés de session
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    $_SESSION['admin_message_error'] = "Accès non autorisé.";
    header("Location: ../../index.php"); // Redirige vers la racine du site ou page de login
    exit();
}

// Définir le titre et si le style admin est requis AVANT d'inclure le header
$title = "Modifier un Utilisateur";
$admin_style = true; // Pour que header.php puisse conditionnellement charger admin_style.css

// Inclure l'en-tête de l'administration (qui contient doctype, head, body ouvrant)
require_once('../includes/header.php'); // Correct: pages/admin/includes/header.php

// Inclure la navigation de l'administration (qui affiche le menu)
require_once('../includes/navigation.php'); // Correct: pages/admin/includes/navigation.php

// Inclure le fichier de connexion à la base de données
require_once('../../../fonctions/database.php'); // Correct: bailcompta360/fonctions/database.php

// Variables pour les messages et données utilisateur
$errorMessage = $_SESSION['admin_message_error'] ?? null;
$successMessage = $_SESSION['admin_message_success'] ?? null;
unset($_SESSION['admin_message_error'], $_SESSION['admin_message_success']); // Nettoyer après lecture

$utilisateur = null;
$utilisateur_id = null;

// Vérifier si $pdo est disponible (doit être défini dans database.php)
if (!isset($pdo) || !$pdo instanceof PDO) {
    $errorMessage = "Erreur critique : Connexion à la base de données non disponible.";
    // Afficher une erreur et ne pas continuer si pas de DB
} else {
    // Vérifier si l'ID de l'utilisateur à modifier est présent dans l'URL
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        $_SESSION['admin_message_error'] = "ID utilisateur invalide pour la modification.";
        header("Location: index.php"); // Rediriger vers la liste des utilisateurs
        exit();
    }

    $utilisateur_id = (int)$_GET['id'];

    // Récupérer les informations de l'utilisateur à modifier
    try {
        $sql = "SELECT ID_Utilisateur, Nom, Login_Utilisateur, Role FROM Utilisateurs WHERE ID_Utilisateur = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $utilisateur_id, PDO::PARAM_INT);
        $stmt->execute();
        $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$utilisateur) {
            $_SESSION['admin_message_error'] = "Utilisateur non trouvé.";
            header("Location: index.php");
            exit();
        }
    } catch (PDOException $e) {
        $errorMessage = "Erreur lors de la récupération des données de l'utilisateur : " . htmlspecialchars($e->getMessage());
        error_log("Erreur PDO dans modifier.php (récupération utilisateur): " . $e->getMessage());
        // Ne pas afficher le formulaire si on ne peut pas charger l'utilisateur
        $utilisateur = null;
    }
}

// Le HTML de cette page spécifique commence ici (contenu principal)
?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 page-content-admin"> <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            Modifier l'Utilisateur : 
            <?php if ($utilisateur) echo htmlspecialchars($utilisateur['Nom']); else echo "Inconnu"; ?>
        </h1>
    </div>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <?= htmlspecialchars($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($utilisateur): // Afficher le formulaire seulement si l'utilisateur a été chargé ?>
    <div class="col-lg-6">
        <form method="post" action="mettre_a_jour_utilisateur.php">
            <input type="hidden" name="id" value="<?= htmlspecialchars($utilisateur['ID_Utilisateur']); ?>">
            
            <?php if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="mb-3"> <label for="nom" class="form-label">Nom</label>
                <input type="text" class="form-control" id="nom" name="nom" value="<?= htmlspecialchars($utilisateur['Nom']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="login" class="form-label">Login</label>
                <input type="text" class="form-control" id="login" name="login" value="<?= htmlspecialchars($utilisateur['Login_Utilisateur']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Nouveau Mot de Passe (laisser vide pour ne pas changer)</label>
                <input type="password" class="form-control" id="password" name="password">
                <small class="form-text text-muted">Si vous laissez ce champ vide, le mot de passe actuel restera inchangé.</small>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Rôle</label>
                <select class="form-select form-control" id="role" name="role" required> <option value="Admin" <?= ($utilisateur['Role'] === 'Admin') ? 'selected' : ''; ?>>Administrateur</option>
                    <option value="Comptable" <?= ($utilisateur['Role'] === 'Comptable') ? 'selected' : ''; ?>>Comptable</option>
                    <option value="Invité" <?= ($utilisateur['Role'] === 'Invité' || $utilisateur['Role'] === 'Utilisateur') ? 'selected' : ''; ?>>Invité</option> </select>
            </div>
            <button type="submit" class="btn btn-primary">Mettre à Jour l'Utilisateur</button>
            
            <a href="index.php" class="btn btn-default">Annuler</a> </form>
    </div>
    <?php elseif (!$errorMessage) : // Si $utilisateur est null et qu'il n'y a pas d'erreur explicite (devrait avoir été géré par redirection) ?>
        <p>Les informations de l'utilisateur ne peuvent pas être affichées.</p>
        <a href="index.php" class="btn btn-info">Retour à la liste</a>
    <?php endif; ?>
</main>

<?php
// Inclure le pied de page de l'administration (qui contient la fin de body et html, et les scripts JS)
require_once('../includes/footer.php'); // Correct: pages/admin/includes/footer.php
?>