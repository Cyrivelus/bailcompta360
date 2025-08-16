<?php
// pages/reporting/liasse_fiscale.php
session_start();

// Vérification de l'accès (rôle 'Comptable' requis)
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Comptable') {
    header("Location: ../../index.php?error=Accès non autorisé");
    exit();
}

// Configuration et inclusions
$titre = 'Liasse Fiscale';
$current_page = basename(__FILE__);

// Inclure la base de données et les fonctions de liasse fiscale
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_liasse_fiscale.php';
require_once '../../fonctions/gestion_reporting.php'; // S'assurer que les dépendances sont incluses

$donnees_liasse = [];
$message = null;

// Par défaut, nous considérons l'année fiscale en cours
$annee_fiscale = date('Y');

// Si un formulaire est soumis pour changer l'année
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $annee_fiscale = $_POST['annee_fiscale'] ?? date('Y');
}

// Tenter de générer les rapports
try {
    // Appel de la fonction principale pour récupérer toutes les données en une fois
    $donnees_liasse = genererDonneesLiasseFiscale($pdo, $annee_fiscale);
} catch (Exception $e) {
    $message = "Erreur lors de la génération des documents fiscaux : " . $e->getMessage();
}

// Inclusions de la vue
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($titre) ?> | BailCompta 360</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
</head>
<body>
<div class="container">
    <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

    <?php if ($message): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="panel panel-default">
        <div class="panel-heading">Générer et Exporter la Liasse Fiscale</div>
        <div class="panel-body">
            <form method="post" action="liasse_fiscale.php" class="form-inline">
                <div class="form-group">
                    <label for="annee_fiscale">Année :</label>
                    <input type="number" class="form-control" id="annee_fiscale" name="annee_fiscale" value="<?= htmlspecialchars($annee_fiscale) ?>">
                </div>
                <button type="submit" class="btn btn-primary">Afficher le rapport</button>
            </form>

            <?php if (!empty($donnees_liasse)): ?>
                <hr>
                <h4>Exporter pour l'année <?= htmlspecialchars($annee_fiscale) ?> :</h4>
                <div class="btn-group" role="group">
                    <a href="export_liasse_fiscale.php?annee=<?= htmlspecialchars($annee_fiscale) ?>&format=xml" class="btn btn-success">Exporter en XML</a>
                    <a href="export_liasse_fiscale.php?annee=<?= htmlspecialchars($annee_fiscale) ?>&format=json" class="btn btn-info">Exporter en JSON</a>
                    <a href="export_liasse_fiscale.php?annee=<?= htmlspecialchars($annee_fiscale) ?>&format=excel" class="btn btn-success">Exporter en Excel (CSV)</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($donnees_liasse)): ?>
        
        <div class="panel panel-info">
            <div class="panel-heading">Bilan au 31/12/<?= htmlspecialchars($annee_fiscale) ?></div>
            <div class="panel-body">
                <p>Structure du Bilan :</p>
                <pre><?= print_r($donnees_liasse['bilan'], true) ?></pre>
            </div>
        </div>

        <div class="panel panel-info">
            <div class="panel-heading">Compte de Résultat détaillé pour l'exercice <?= htmlspecialchars($annee_fiscale) ?></div>
            <div class="panel-body">
                <p>Structure du Compte de Résultat :</p>
                <pre><?= print_r($donnees_liasse['compte_de_resultat'], true) ?></pre>
            </div>
        </div>
    
    <?php else: ?>
        <div class="alert alert-warning">Aucune donnée disponible pour générer la liasse fiscale pour l'année <?= htmlspecialchars($annee_fiscale) ?>.</div>
    <?php endif; ?>

</div>
<?php require_once('../../templates/footer.php'); ?>
</body>
</html>