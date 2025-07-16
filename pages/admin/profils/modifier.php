<?php
// pages/admin/profils/modifier.php

// Démarrer la session pour la gestion de l'authentification et des messages flash
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}



// --- Configuration et Includes ---
// Définir le titre de la page et indiquer si le style admin est requis AVANT d'inclure le header
$title = "Modifier un Profil";
$admin_style = true; // Variable pour le header, si il gère l'inclusion de admin_style.css

// Inclure le fichier de connexion à la base de données
// ASSUREZ-VOUS QUE CE CHEMIN EST CORRECT et cohérent avec les autres scripts
require_once('../../../fonctions/database.php'); // Chemin ajusté pour cohérence

// Vérifier si $pdo est bien initialisé
if (!isset($pdo) || !$pdo instanceof PDO) {
    $_SESSION['admin_message_error'] = "Erreur critique : La connexion à la base de données n'est pas disponible.";
    header('Location: index.php'); // Rediriger vers la liste des profils en cas d'erreur BD
    exit();
}

// --- Logique de récupération du profil à modifier ---

// Vérifier si l'ID du profil à modifier est présent dans l'URL et est un entier valide
$profil_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($profil_id === false || $profil_id === null || $profil_id <= 0) {
     $_SESSION['admin_message_error'] = "ID profil invalide spécifié.";
     header("Location: index.php"); // Rediriger avec message flash
     exit();
}


// Récupérer les informations du profil à modifier depuis la base de données
// CORRECTION : Changer 'Description' en 'Description_Profil'
// On récupère aussi ID_Utilisateur, qui peut être utile ou simplement pour info
$sql = "SELECT ID_Profil, Nom_Profil, Description_Profil, ID_Utilisateur FROM Profils WHERE ID_Profil = :id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':id', $profil_id, PDO::PARAM_INT);

try {
    $stmt->execute();
    $profil = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si le profil n'est pas trouvé
    if (!$profil) {
        $_SESSION['admin_message_error'] = "Profil non trouvé avec l'ID spécifié.";
        header("Location: index.php"); // Rediriger avec message flash
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['admin_message_error'] = "Erreur lors du chargement du profil pour modification : " . htmlspecialchars($e->getMessage());
    error_log("Erreur PDO (fetch profil for modify) dans modifier.php: " . $e->getMessage());
    header("Location: index.php"); // Rediriger avec message flash en cas d'erreur BD
    exit();
}


// --- Récupération des messages flash (si on vient d'une redirection vers cette page) ---
$errorMessage = $_SESSION['admin_message_error'] ?? null;
$successMessage = $_SESSION['admin_message_success'] ?? null;
unset($_SESSION['admin_message_error'], $_SESSION['admin_message_success']); // Nettoyer après lecture


// --- Inclusion des templates HTML ---
// Inclure l'en-tête de l'administration (qui contient doctype, head, body ouvrant, et potentiellement les CSS/JS de base)
// Assurez-vous que ce chemin est correct et que header.php utilise la variable $title et $admin_style
require_once('../includes/header.php'); // Chemin ajusté pour cohérence

// Inclure la navigation de l'administration (qui affiche le menu)
// Assurez-vous que ce chemin est correct
require_once('../includes/navigation.php'); // Chemin ajusté

// REMOVE: Suppression de cette inclusion redondante/potentiellement conflicutelle
// require_once '../../templates/admin_navigation.php';


// --- Contenu spécifique de la page ---
// Le contenu principal de la page va ici, DANS le <body> ouvert par header.php
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 page-content-admin">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Modifier le Profil : <?= htmlspecialchars($profil['Nom_Profil']) ?></h1>
    </div>

    <?php
    // Afficher les messages d'erreur ou de succès (utilisant les messages combinés)
    // Utilisation de classes Bootstrap 5+ pour les alertes
    if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($errorMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($successMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="col-lg-6">
        <form method="post" action="mettre_a_jour_profil.php">
            <?php
            // Jeton CSRF - ESSENTIEL pour la sécurité des formulaires de modification
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

            <input type="hidden" name="id" value="<?= htmlspecialchars($profil['ID_Profil']) ?>">

            <div class="mb-3"> <label for="nom_profil" class="form-label">Nom du Profil</label>
                <input type="text" class="form-control" id="nom_profil" name="nom_profil" value="<?= htmlspecialchars($profil['Nom_Profil']) ?>" required>
            </div>

            <div class="mb-3"> <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($profil['Description_Profil'] ?? '') ?></textarea>
                 <?php
                 // Note : Le nom du champ est 'description', mais il correspond à la colonne 'Description_Profil'
                 // dans la base de données. Le script mettre_a_jour_profil.php devra en tenir compte.
                 ?>
            </div>

            <?php
            // NOTE : Vous pourriez vouloir afficher l'utilisateur lié ici, mais la modification
            // de l'utilisateur lié n'est généralement pas faite sur cette page (elle se fait
            // souvent sur la page de gestion de l'utilisateur, ou nécessite une logique plus complexe).
            // Si vous voulez l'afficher :
            /*
            <div class="mb-3">
                <label class="form-label">Utilisateur Lié</label>
                <p class="form-control-static">
                    <?php
                    if (!empty($profil['ID_Utilisateur'])) {
                        // Si vous avez joint la table Utilisateurs dans la requête SELECT ci-dessus
                        // echo htmlspecialchars($profil['Nom_Utilisateur'] ?? $profil['Login_Utilisateur'] ?? 'ID: ' . $profil['ID_Utilisateur']);
                        // Sinon (juste l'ID est sélectionné)
                        echo htmlspecialchars('ID: ' . $profil['ID_Utilisateur']);
                    } else {
                        echo '<em>Non lié</em>';
                    }
                    ?>
                </p>
            </div>
            */
            ?>


            <button type="submit" class="btn btn-primary">Mettre à Jour le Profil</button>
            <a href="index.php" class="btn btn-secondary">Annuler</a>
        </form>
    </div>
</main>

<?php
// Inclure le pied de page de l'administration (qui ferme <body>, <html> et inclut les JS)
// Assurez-vous que ce chemin est correct
require_once('../includes/footer.php'); // Chemin ajusté
?>

<?php
// IMPORTANT : Supprimez toutes les balises HTML complètes qui étaient présentes ici,
// elles sont censées être gérées par header.php et footer.php
// Exemple : </body></html>
?>