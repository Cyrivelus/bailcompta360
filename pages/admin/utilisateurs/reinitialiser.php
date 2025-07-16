<?php
// admin/utilisateurs/reinitialiser.php

// Inclure les fichiers nécessaires
require_once '../includes/header.php'; // Inclus l'en-tête admin
require_once '../includes/navigation.php'; // Menu de navigation admin
require_once '../../../fonctions/database.php'; // Connexion DB
require_once '../../../fonctions/gestion_utilisateurs.php'; // fonction utilisateur


// Initialisation des variables
$errorMessage = '';
$successMessage = '';
$utilisateur = null;
$utilisateurId = null;

try {
    // Vérification de la connexion PDO
    

    // Vérifier si l'ID de l'utilisateur est présent dans l'URL
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $utilisateurId = (int)$_GET['id'];

        // Récupérer les informations de l'utilisateur
        $utilisateur = getUtilisateurParId($pdo, $utilisateurId);
        if (!$utilisateur) {
            $errorMessage = "Utilisateur non trouvé.";
            throw new Exception($errorMessage);
        }
    } else {
        $errorMessage = "ID d'utilisateur invalide.";
        throw new Exception($errorMessage);
    }

    // Traitement du formulaire de réinitialisation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reinitialiser_mot_de_passe'])) {
        // Vérifier le jeton CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $errorMessage = "Erreur CSRF. Action non autorisée.";
            throw new Exception($errorMessage);
        }

       // Générer un nouveau mot de passe
        $nouvelleMotDePasse = genererMotDePasseAleatoire();
      

        // Mettre à jour le mot de passe
        if (mettreAJourMotDePasse($pdo, $utilisateurId, $nouvelleMotDePasse)) {
            $successMessage = "Le mot de passe de l'utilisateur '".htmlspecialchars($utilisateur['Nom'])."' a été réinitialisé avec succès. Le nouveau mot de passe est : <strong>".htmlspecialchars($nouvelleMotDePasse)."</strong>. Veuillez communiquer ce mot de passe à l'utilisateur de manière sécurisée.";
        } else {
            $errorMessage = "Erreur lors de la réinitialisation du mot de passe.";
            throw new Exception($errorMessage);
        }
    }

} catch (Exception $e) {
    $errorMessage = "Une erreur est survenue : " . htmlspecialchars($e->getMessage());
}

// Générer un jeton CSRF pour la sécurité du formulaire
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Affichage du formulaire
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Réinitialisation du mot de passe</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
    <div class="container">
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
        <?php endif; ?>

        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success"><?php echo $successMessage; ?></div>
        <?php else: ?>
            <h1>Réinitialisation du mot de passe</h1>
            <p>Vous êtes sur le point de réinitialiser le mot de passe de : <strong><?php echo htmlspecialchars($utilisateur['Nom']); ?></strong></p>
            
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="form-group">
                    <button type="submit" name="reinitialiser_mot_de_passe" class="btn btn-danger">Confirmer la réinitialisation</button>
                    <a href="liste_utilisateurs.php" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>