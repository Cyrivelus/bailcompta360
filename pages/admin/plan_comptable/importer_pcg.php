<?php
// pages/admin/plan_comptable/importer_pcg.php

// Démarrer la session pour gérer l'authentification
session_start();

// Vérifier si l'utilisateur est connecté en tant qu'administrateur
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../../index.php?error=Accès non autorisé");
    exit();
}

// Inclure les fichiers nécessaires
require_once '../../../fonctions/database.php';
// Inclure le nouveau fichier de fonctions d'import du plan comptable
require_once '../../../fonctions/plan_comptable/import_pcg.php';

$titre = 'Importation du Plan Comptable';
$current_page = 'plan_comptable';

require_once('../../../templates/header.php');
require_once('../../../templates/navigation.php');

// Initialiser les messages
$message_success = '';
$message_erreur = '';

// Gérer la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['fichier_pcg']) && $_FILES['fichier_pcg']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['fichier_pcg']['tmp_name'];
        $file_name = $_FILES['fichier_pcg']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Vérifier si le fichier est un CSV
        if ($file_ext === 'csv') {
            // Appeler la fonction d'import du plan comptable
            $resultat_import = import_pcg_csv($file_tmp_path);

            if ($resultat_import['success']) {
                $message_success = "Importation réussie ! {$resultat_import['nombre_lignes']} comptes ont été ajoutés ou mis à jour.";
            } else {
                $message_erreur = "Erreur lors de l'importation : " . $resultat_import['message'];
            }
        } else {
            $message_erreur = "Type de fichier non valide. Veuillez importer un fichier CSV.";
        }
    } else {
        $message_erreur = "Une erreur est survenue lors du téléchargement du fichier.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?php echo $titre; ?></title>
    <link rel="shortcut icon" href="../../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../css/style.css">
    <link rel="stylesheet" href="../../../css/tableau.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<div class="container">
    <h2 class="page-header"><?php echo $titre; ?></h2>

    <?php if ($message_success): ?>
        <div class="alert alert-success" role="alert">
            <span class="glyphicon glyphicon-ok" aria-hidden="true"></span>
            <?php echo htmlspecialchars($message_success); ?>
        </div>
    <?php endif; ?>

    <?php if ($message_erreur): ?>
        <div class="alert alert-danger" role="alert">
            <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
            <?php echo htmlspecialchars($message_erreur); ?>
        </div>
    <?php endif; ?>

    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">Importer un Plan Comptable Standard (PCG)</h3>
        </div>
        <div class="panel-body">
            <p>Cet outil vous permet d'importer un plan comptable standard à partir d'un fichier CSV. Le format du fichier doit être le suivant :</p>
            <ul>
                <li>Une colonne `numero_compte` (obligatoire)</li>
                <li>Une colonne `libelle_compte` (obligatoire)</li>
            </ul>
            <form action="importer_pcg.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="fichier_pcg">Sélectionner le fichier CSV :</label>
                    <input type="file" class="form-control" id="fichier_pcg" name="fichier_pcg" accept=".csv" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <span class="glyphicon glyphicon-upload"></span> Importer le Plan Comptable
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="panel panel-info">
        <div class="panel-heading">
            <h3 class="panel-title">Structure du Plan Comptable</h3>
        </div>
        <div class="panel-body">
            <p>Aperçu de la structure actuelle du plan comptable (à implémenter).</p>
            <a href="index.php" class="btn btn-info">
                <span class="glyphicon glyphicon-list"></span> Voir le plan comptable
            </a>
        </div>
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>

<?php
require_once('../../../templates/footer.php');
?>