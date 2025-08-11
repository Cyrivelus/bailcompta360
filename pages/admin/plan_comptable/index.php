<?php
// pages/admin/plan_comptable/index.php

session_start();

if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../../index.php?error=Accès non autorisé");
    exit();
}

require_once '../../../fonctions/database.php';
require_once '../../../fonctions/plan_comptable/gestion_plan_comptable.php';

$titre = 'Plan Comptable Général';
$current_page = 'plan_comptable';

$message = '';
$message_type = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Action d'ajout de compte
    if (isset($_POST['action']) && $_POST['action'] === 'ajouter_compte') {
        $numero_compte = $_POST['numero_compte'] ?? '';
        $libelle_compte = $_POST['libelle_compte'] ?? '';

        if (empty($numero_compte) || empty($libelle_compte)) {
            $message_type = 'danger';
            $message = 'Le numéro et le libellé du compte sont obligatoires.';
        } else {
            $resultat = ajouter_compte_comptable($numero_compte, $libelle_compte);
            $message_type = $resultat['success'] ? 'success' : 'danger';
            $message = $resultat['message'];
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    // Action de suppression de compte
    if ($_GET['action'] === 'supprimer' && isset($_GET['numero'])) {
        $resultat = supprimer_compte_comptable($_GET['numero']);
        $message_type = $resultat['success'] ? 'success' : 'danger';
        $message = $resultat['message'];
    }
}

// Récupérer la liste des comptes comptables après toute modification
$comptes = get_all_comptes();

require_once('../../../templates/header.php');
require_once('../../../templates/navigation.php');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?php echo htmlspecialchars($titre); ?></title>
    <link rel="shortcut icon" href="../../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../css/style.css">
    <link rel="stylesheet" href="../../../css/tableau.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .table-responsive { margin-top: 20px; }
        .action-links a { margin-right: 10px; }
    </style>
</head>
<body>
<div class="container">
    <h2 class="page-header"><?php echo htmlspecialchars($titre); ?></h2>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-12">
            <a href="importer_pcg.php" class="btn btn-info pull-right">
                <span class="glyphicon glyphicon-upload"></span> Importer un Plan Comptable
            </a>
            <button type="button" class="btn btn-success pull-right" data-toggle="modal" data-target="#ajouterCompteModal" style="margin-right: 10px;">
                <span class="glyphicon glyphicon-plus"></span> Ajouter un Compte
            </button>
        </div>
    </div>
    
    <?php if (!empty($comptes)): ?>
    <div class="table-responsive">
        <table class="table table-striped table-bordered table-hover">
            <thead>
                <tr>
                    <th>Numéro de Compte</th>
                    <th>Libellé du Compte</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($comptes as $compte): ?>
                <tr>
                    <td><?php echo htmlspecialchars($compte['Numero_Compte']); ?></td>
                    <td><?php echo htmlspecialchars($compte['Nom_Compte']); ?></td>
                    <td class="text-center action-links">
                        <a href="modifier_compte.php?numero=<?php echo urlencode($compte['Numero_Compte']); ?>" class="btn btn-warning btn-xs" title="Modifier">
                            <span class="glyphicon glyphicon-pencil"></span>
                        </a>
                        <a href="?action=supprimer&numero=<?php echo urlencode($compte['Numero_Compte']); ?>" class="btn btn-danger btn-xs" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce compte ?');">
                            <span class="glyphicon glyphicon-trash"></span>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="alert alert-warning" role="alert">
            <p>Aucun compte comptable n'est actuellement enregistré. Veuillez <a href="importer_pcg.php">importer un plan comptable</a> ou ajouter un compte manuellement.</p>
        </div>
    <?php endif; ?>

</div>

<div class="modal fade" id="ajouterCompteModal" tabindex="-1" role="dialog" aria-labelledby="ajouterCompteModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="ajouterCompteModalLabel">Ajouter un Compte Comptable</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="ajouter_compte">
                    <div class="form-group">
                        <label for="numero_compte">Numéro de Compte</label>
                        <input type="text" class="form-control" id="numero_compte" name="numero_compte" required>
                    </div>
                    <div class="form-group">
                        <label for="libelle_compte">Libellé du Compte</label>
                        <input type="text" class="form-control" id="libelle_compte" name="libelle_compte" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<?php require_once('../../../templates/footer.php'); ?>