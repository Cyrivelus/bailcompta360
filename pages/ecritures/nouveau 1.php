<?php
// pages/ecritures/liste.php

$titre = 'Liste des Écritures Comptables';
$current_page = basename($_SERVER['PHP_SELF']); // Pour la classe 'active' dans la navigation

require_once('../../templates/header.php');
require_once('../../templates/navigation.php'); // Inclusion de la navigation
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Import de Factures</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css" integrity="sha384-HSMxcRTRxnN+Bdg0JdbxYKrThecOKuH5zCYotlSAcp1+c8xmyTe9GYg1l9a69psu" crossorigin="anonymous">
    <link rel="stylesheet" href="../../css/monstyle.css">
    <link rel="stylesheet" href="../../css/formulaire.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<div class="container">
    <h2 class="page-header">Gestion des Écritures Comptables</h2>

    <?php
    // Inclure les fichiers nécessaires (connexion à la base de données, fonctions, etc.)
    require_once '../../fonctions/database.php';
    require_once '../../fonctions/gestion_ecritures.php'; // Inclure le fichier de gestion des écritures

    // Récupérer la liste des écritures depuis la base de données
    $ecritures = getListeEcritures($pdo); // Fonction pour récupérer la liste des écritures

    // Traitement des messages de succès ou d'erreur
    $successMessage = isset($_GET['success']) ? "L'écriture a été enregistrée avec succès." : null;
    $updateMessage = isset($_GET['success_update']) ? "L'écriture a été mise à jour avec succès." : null;
    $deleteMessage = isset($_GET['delete_success']) ? "L'écriture a été supprimée avec succès." : null;
    $errorMessage = isset($_GET['error']) ? $_GET['error'] : null;
    ?>

    <?php if ($successMessage): ?>
        <div class="alert alert-success"><?= $successMessage ?></div>
    <?php endif; ?>

    <?php if ($updateMessage): ?>
        <div class="alert alert-success"><?= $updateMessage ?></div>
    <?php endif; ?>

    <?php if ($deleteMessage): ?>
        <div class="alert alert-success"><?= $deleteMessage ?></div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger"><?= $errorMessage ?></div>
    <?php endif; ?>

    <p>
        <a href="saisie.php" class="btn btn-primary">
            <span class="glyphicon glyphicon-plus"></span> Ajouter une nouvelle écriture
        </a>
    </p>

    <?php if (!empty($ecritures)): ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date de Saisie</th>
                        <th>Description</th>
                        <th>Montant Total</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ecritures as $ecriture): ?>
                        <tr>
                            <td><?= $ecriture['ID_Ecriture'] ?></td>
                            <td><?= date('d/m/Y H:i:s', strtotime($ecriture['Date_Saisie'])) ?></td>
                            <td><?= htmlspecialchars($ecriture['Description']) ?></td>
                            <td><?= number_format($ecriture['Montant_Total'], 2, ',', ' ') ?></td>
                            <td>
                                <a href="modifier.php?id=<?= $ecriture['ID_Ecriture'] ?>" class="btn btn-sm btn-warning">
                                    <span class="glyphicon glyphicon-pencil"></span> Modifier
                                </a>
                                <a href="details.php?id=<?= $ecriture['ID_Ecriture'] ?>" class="btn btn-sm btn-info">
                                    <span class="glyphicon glyphicon-eye-open"></span> Détails
                                </a>
                                <a href="supprimer.php?id=<?= $ecriture['ID_Ecriture'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette écriture ?')">
                                    <span class="glyphicon glyphicon-trash"></span> Supprimer
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="alert alert-info">Aucune écriture comptable n'a été trouvée.</p>
    <?php endif; ?>
</div>
</body>
</html>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js" integrity="sha384-aJ21OjlMXNLJ5ywYOIDjxxyTwCypxSoOO3FxyYr4fccRoP1h0IWcAukj0jz9uNNs" crossorigin="anonymous"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="../js/tableau_dynamique.js"></script>
<script>
    $(document).ready(function() {
        initialiserTableauDynamique('.table', {
            selection: true,
            pagination: true,
            rowsPerPage: 10,
            sortable: true,
            searchable: true,
        });
    });
</script>

<?php
require_once('../../templates/footer.php');
?>

<?php
// Fonction de gestion des écritures (à implémenter dans fonctions/gestion_ecritures.php)

function getListeEcritures($db) {
    try {
        $stmt = $db->query("SELECT ID_Ecriture, Date_Saisie, Description, Montant_Total FROM Ecritures ORDER BY Date_Saisie DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la liste des écritures : " . $e->getMessage());
        return [];
    }
}

// Vous aurez besoin des fichiers suivants (similaires à ceux des emprunts, mais adaptés aux écritures) :
// - saisie.php : Pour ajouter une nouvelle écriture.
// - modifier.php : Pour modifier une écriture existante.
// - details.php : Pour afficher les détails d'une écriture (et potentiellement les lignes d'écriture).
// - supprimer.php : Pour gérer la suppression d'une écriture.

// Exemple de contenu pour supprimer.php (à adapter pour les écritures) :
/*
<?php
require_once '../fonctions/database.php';
require_once '../fonctions/gestion_ecritures.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $idEcriture = $_GET['id'];
    if (supprimerEcriture($db, $idEcriture)) {
        header('Location: liste.php?delete_success=1');
        exit();
    } else {
        header('Location: liste.php?error=Erreur lors de la suppression de l\'écriture.');
        exit();
    }
} else {
    header('Location: liste.php?error=ID d\'écriture invalide.');
    exit();
}

function supprimerEcriture($db, $id) {
    try {
        // Il faudrait d'abord supprimer les lignes d'écriture associées
        $stmtLignes = $db->prepare("DELETE FROM Lignes_Ecritures WHERE ID_Ecriture = :id");
        $stmtLignes->bindParam(':id', $id);
        $stmtLignes->execute();

        $stmtEcriture = $db->prepare("DELETE FROM Ecritures WHERE ID_Ecriture = :id");
        $stmtEcriture->bindParam(':id', $id);
        return $stmtEcriture->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression de l'écriture : " . $e->getMessage());
        return false;
    }
}
?>
*/
?>