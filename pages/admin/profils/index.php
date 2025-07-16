<?php
// pages/admin/profils/index.php

// Démarrer la session pour la gestion de l'authentification
session_start();

// Vérifier si l'utilisateur est connecté en tant qu'administrateur
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Admin') {
    // Si l'utilisateur n'est pas connecté ou n'est pas un administrateur,
    // le rediriger vers la page de connexion ou une page d'erreur.
    header("Location: ../index.php?error=Accès non autorisé");
    exit();
}


// Inclure l'en-tête de l'administration
$title = "Gestion des Profils";
include('../../../templates/header.php');

// Inclure la navigation de l'administration
include('../../../templates/navigation.php');

// Inclure le fichier de connexion à la base de données
require_once('../../../fonctions/database.php');

// Inclure le fichier de gestion des profils
require_once('../../../fonctions/gestion_profils.php');

// Récupérer la liste des profils
$profils = getTousLesProfils($pdo);

// --- Logique de récupération des données ---
$profils = []; // Initialiser un tableau vide
$errorMessage = null; // Initialiser pour les erreurs potentielles lors de la récupération BD

try {
    // Assurez-vous que $pdo est bien l'instance PDO de votre connexion BD
    // La fonction getTousLesProfils doit maintenant récupérer ID_Utilisateur
    // et potentiellement faire une jointure pour obtenir le nom/login de l'utilisateur lié.
    // IMPORTANT : Modifiez la fonction getTousLesProfils dans gestion_profils.php
    // pour qu'elle retourne les colonnes nécessaires, y compris ID_Utilisateur
    // et idéalement les informations de l'utilisateur lié (Nom, Login).
    /*
    Exemple de modification pour gestion_profils.php::getTousLesProfils:
    function getTousLesProfils(PDO $pdo) {
        $sql = "SELECT
                    P.ID_Profil,
                    P.Nom_Profil,
                    P.Description_Profil,
                    P.ID_Utilisateur,
                    U.Nom AS Nom_Utilisateur,      -- Récupère le nom de l'utilisateur
                    U.Login_Utilisateur             -- Récupère le login de l'utilisateur
                FROM Profils AS P
                LEFT JOIN Utilisateurs AS U ON P.ID_Utilisateur = U.ID_Utilisateur -- Jointure pour lier Profil et Utilisateur
                ORDER BY P.Nom_Profil ASC"; // Ou un autre ordre
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    */
    $profils = getTousLesProfils($pdo);

} catch (PDOException $e) {
    $errorMessage = "Erreur lors du chargement des profils : " . htmlspecialchars($e->getMessage());
    error_log("Erreur PDO (getTousLesProfils) dans index.php: " . $e->getMessage());
}


// --- Récupération des messages flash ---
// Récupérer les messages stockés en session par d'autres scripts (ajouter, enregistrer, supprimer)
$sessionErrorMessage = isset($_SESSION['admin_message_error']) ? $_SESSION['admin_message_error'] : null;
$sessionSuccessMessage = isset($_SESSION['admin_message_success']) ? $_SESSION['admin_message_success'] : null;

// Nettoyer les messages de la session après les avoir récupérés
unset($_SESSION['admin_message_error'], $_SESSION['admin_message_success']);

// Combiner les messages potentiels (erreur BD lors du chargement + message flash de session)
if ($sessionErrorMessage) {
    $errorMessage = $sessionErrorMessage; // Le message de session a priorité
}
// Le message de succès vient uniquement de la session
$successMessage = $sessionSuccessMessage;




// --- Contenu spécifique de la page ---
// Le contenu principal de la page va ici, DANS le <body> ouvert par header.php
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?= htmlspecialchars($titre) ?></title>
    <link rel="shortcut icon" href="../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
	
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 page-content-admin">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Gestion des Profils</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="ajouter.php" class="btn btn-sm btn-outline-primary">Ajouter un Profil</a>
            </div>
        </div>
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

    <h2>Liste des Profils</h2>
    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th>ID Profil</th> <th>Nom du Profil</th>
                    <th>Description</th>
                    <th>Utilisateur Lié</th> <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($profils)): ?>
                    <tr><td colspan="5">Aucun profil trouvé.</td></tr> <?php else: ?>
                    <?php foreach ($profils as $profil): ?>
                        <tr>
                            <td><?= htmlspecialchars($profil['ID_Profil']) ?></td>
                            <td><?= htmlspecialchars($profil['Nom_Profil']) ?></td>
                            <td><?= htmlspecialchars(isset($profil['Description_Profil']) ? $profil['Description_Profil'] : '') ?></td>
 <td>
                                <?php
                                // Afficher les infos de l'utilisateur lié, si ID_Utilisateur n'est pas NULL
                                if (!empty($profil['ID_Utilisateur'])) {
                                    // Si getTousLesProfils fait la jointure et récupère Nom/Login :
                                    if (isset($profil['Nom_Utilisateur'])) {
    $valeur = $profil['Nom_Utilisateur'];
} elseif (isset($profil['Login_Utilisateur'])) {
    $valeur = $profil['Login_Utilisateur'];
} else {
    $valeur = 'ID: ' . $profil['ID_Utilisateur'];
}

echo htmlspecialchars($valeur);

                                    // Sinon, afficher juste l'ID :
                                    // echo htmlspecialchars($profil['ID_Utilisateur']);
                                } else {
                                    echo '<em>Non lié</em>'; // Si ID_Utilisateur est NULL
                                }
                                ?>
                            </td>
                            <td>
                                <a href="modifier.php?id=<?= htmlspecialchars($profil['ID_Profil']) ?>" class="btn btn-sm btn-outline-secondary">Modifier</a>
                                <a href="supprimer.php?id=<?= htmlspecialchars($profil['ID_Profil']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce profil (ID: <?= htmlspecialchars($profil['ID_Profil']) ?>) ? Cette action est irréversible.');">Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php
// Inclure le pied de page de l'administration (qui ferme <body>, <html> et inclut les JS)
// Assurez-vous que ce chemin est correct
require_once('../includes/footer.php'); // Chemin ajusté
?>

