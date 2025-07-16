<?php
// pages/admin/configuration/base_de_donnees.php
// Configuration de la base de données (accès restreint !)

// Démarrer la session pour la gestion de l'authentification
session_start();

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Admin') {
    // Rediriger si non autorisé
    header("Location: ../../index.php?error=Accès non autorisé");
    exit();
}

// Inclure l'en-tête de l'administration
$title = "Configuration de la Base de Données";
include('../includes/header.php');

// Inclure la navigation de l'administration
include('../includes/navigation.php');

// Définir les informations de connexion à la base de données
$serverName = "localhost";
$databaseName = "BD_AD_SCE";

// Tentative de connexion PDO
$connexionStatus = '';
try {
    $pdo = new PDO("mysql:host=localhost;dbname=BD_AD_SCE;charset=utf8", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connexionStatus = '<div class="alert alert-success">Connexion à la base de données établie avec succès !</div>';
} catch (PDOException $e) {
    $connexionStatus = '<div class="alert alert-danger">Erreur de connexion à la base de données : ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Configuration de la Base de Données</h1>
    </div>

    <p><strong>Important :</strong> Cette page affiche les informations de connexion à la base de données. L'accès doit être strictement limité aux administrateurs.</p>

    <?php echo $connexionStatus; ?>

    <div class="card">
        <div class="card-header">
            Informations de Connexion
        </div>
        <div class="card-body">
            <ul class="list-group list-group-flush">
                <li class="list-group-item"><strong>Serveur :</strong> <?php echo htmlspecialchars($serverName); ?></li>
                <li class="list-group-item"><strong>Nom de la Base de Données :</strong> <?php echo htmlspecialchars($databaseName); ?></li>
                </ul>
            <div class="mt-3">
                <a href="index.php" class="btn btn-secondary">Retour à la Configuration Générale</a>
            </div>
        </div>
    </div>

</main>

<?php
// Inclure le pied de page de l'administration
include('../includes/footer.php');
?>