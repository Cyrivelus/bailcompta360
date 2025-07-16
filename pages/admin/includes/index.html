<?php

// admin/includes/index.php

// Démarre la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifie si l'utilisateur est connecté en tant qu'administrateur
if (!isset($_SESSION['admin'])) {
    // Si l'utilisateur n'est pas connecté ou n'est pas un administrateur,
    // le redirige vers la page de connexion de l'administration.
    header("Location: ../index.php"); // Assurez-vous que le chemin est correct
    exit();
}

// Inclut le fichier de configuration de la base de données
require_once('../../fonctions/database.php'); // Ajustez le chemin si nécessaire

// Inclut les fonctions d'administration (si vous en avez)
require_once('../functions/admin_functions.php'); // Ajustez le chemin si nécessaire

// Définit le titre de la page
$page_title = "Tableau de Bord de l'Administration";

// Inclut l'en-tête de l'administration
include_once('header.php');
include_once('navigation.php');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Administration - Modifier le rôle</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css" integrity="sha384-HSMxcRTRxnN+Bdg0JdbxYKrThecOKuH5zCYotlSAcp1+c8xmyTe9GYg1l9a69psu" crossorigin="anonymous">
    <link rel="stylesheet" href="../../css/style.css">
    <?php if (isset($admin_style) && $admin_style): ?>
        <link rel="stylesheet" href="../../css/admin_style.css">
    <?php endif; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>

<div class="container-fluid">
    <div class="row">

      

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $page_title; ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    </div>
            </div>

            <h2>Statistiques Générales</h2>
            <div class="row">
                <div class="col-md-4">
                    <div class="card bg-primary text-white mb-4">
                        <div class="card-body">
                            <?php
                            // Exemple de requête pour compter le nombre total d'utilisateurs
                            $stmt = $pdo->query("SELECT COUNT(*) FROM Utilisateurs");
                            $total_utilisateurs = $stmt->fetchColumn();
                            echo "<h3>$total_utilisateurs</h3>";
                            echo "<p class=\"mb-0\">Total des Utilisateurs</p>";
                            ?>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a class="small text-white stretched-link" href="../utilisateurs/index.php">Voir les détails</a>
                            <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white mb-4">
                        <div class="card-body">
                            <?php
                            // Exemple de requête pour compter le nombre total de profils
                            $stmt = $pdo->query("SELECT COUNT(*) FROM Profils");
                            $total_profils = $stmt->fetchColumn();
                            echo "<h3>$total_profils</h3>";
                            echo "<p class=\"mb-0\">Total des Profils</p>";
                            ?>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a class="small text-white stretched-link" href="../profils/index.php">Voir les détails</a>
                            <div class="small text-white"><i class="fas fa-angle-right"></i></div>
                        </div>
                    </div>
                </div>
                </div>

            <h2>Actions Rapides</h2>
            <div class="row">
                <div class="col-md-3">
                    <div class="card border-primary mb-4">
                        <div class="card-body text-center">
                            <i class="fas fa-user-plus fa-3x text-primary mb-2"></i>
                            <h5 class="card-title"><a href="../utilisateurs/ajouter.php" class="text-primary">Ajouter un Utilisateur</a></h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-success mb-4">
                        <div class="card-body text-center">
                            <i class="fas fa-users-cog fa-3x text-success mb-2"></i>
                            <h5 class="card-title"><a href="../profils/ajouter.php" class="text-success">Ajouter un Profil</a></h5>
                        </div>
                    </div>
                </div>
                </div>

            </main>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

</body>
</html>