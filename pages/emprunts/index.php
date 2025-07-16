<?php
// pages/ecritures/index.php

$titre = 'Liste des Écritures Comptables';


require_once('../../templates/header.php');
require_once('../../templates/navigation.php'); // Inclusion de la navigation


?>


<?php
// Inclure les fichiers nécessaires (connexion à la base de données, fonctions, etc.)
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_emprunts.php'; // Supposons un fichier pour gérer les emprunts

// Récupérer la liste des emprunts depuis la base de données
$emprunts = getListeEmprunts($pdo); // Fonction pour récupérer la liste des emprunts

// Traitement des messages de succès ou d'erreur
$successMessage = null;
$updateMessage = null;
$deleteMessage = null;
$errorMessage = null;
$bulkDeleteMessage = null; // New message for bulk deletion

// Vérifie chaque type de message dans l'ordre
if (isset($_GET['success']) && $_GET['success'] === '1') {
    $successMessage = "L'emprunt a été enregistré avec succès.";
}

if (isset($_GET['success_update']) && $_GET['success_update'] === '1') {
    $updateMessage = "L'emprunt a été mis à jour avec succès.";
}

if (isset($_GET['delete_success']) && $_GET['delete_success'] === '1') {
    $deleteMessage = "L'emprunt a été supprimé avec succès.";
}
// New check for bulk delete success
if (isset($_GET['bulk_delete_success']) && $_GET['bulk_delete_success'] === '1') {
    $bulkDeleteMessage = "Les emprunts sélectionnés ont été supprimés avec succès.";
}


if (isset($_GET['error']) && !empty($_GET['error'])) {
    $errorMessage = htmlspecialchars($_GET['error']);
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Gestion des Emprunts</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css" integrity="sha384-HSMxcRTRxnN+Bdg0JdbxYKrThecOKuH5zCYotlSAcp1+c8xmyTe9GYg1l9a69psu" crossorigin="anonymous">
    <link rel="stylesheet" href="../../css/monstyle.css">
    <link rel="stylesheet" href="../../css/tableau.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <div class="container">
        <h2 class="page-header">Gestion des Emprunts Bancaires</h2>

        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>

        <?php if (!empty($updateMessage)): ?>
            <div class="alert alert-info"><?= htmlspecialchars($updateMessage) ?></div>
        <?php endif; ?>

        <?php if (!empty($deleteMessage)): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($deleteMessage) ?></div>
        <?php endif; ?>

        <?php if (!empty($bulkDeleteMessage)): ?>
            <div class="alert alert-warning"><?= htmlspecialchars($bulkDeleteMessage) ?></div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <p>
            <a href="ajouter.php" class="btn btn-primary">
                <span class="glyphicon glyphicon-plus"></span> Ajouter un nouvel emprunt
            </a>
        </p>

        <?php if (!empty($emprunts)): ?>
            <form action="supprimer_groupe.php" method="POST" id="bulkDeleteForm">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered" id="empruntsTable">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllEmprunts"></th>
                                <th>ID</th>
                                <th>Banque</th>
                                <th>Numéro de Prêt</th>
                                <th>Montant</th>
                                <th>Date de Début</th>
                                <th>Date de Fin</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($emprunts as $emprunt): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_emprunts[]" value="<?= $emprunt['ID_Emprunt'] ?>" class="emprunt-checkbox"></td>
                                    <td><?= $emprunt['ID_Emprunt'] ?></td>
                                    <td><?= htmlspecialchars($emprunt['Banque']) ?></td>
                                    <td><?= htmlspecialchars($emprunt['Numero_Pret']) ?></td>
                                    <td><?= number_format($emprunt['Montant_Pret'], 2, ',', ' ') ?></td>
                                    <td><?= date('d/m/Y', strtotime($emprunt['Date_Mise_En_Place'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($emprunt['Date_Derniere_Echeance'])) ?></td>
                                    <td>
                                        <a href="modifier.php?id=<?= $emprunt['ID_Emprunt'] ?>" class="btn btn-sm btn-warning">
                                            <span class="glyphicon glyphicon-pencil"></span> Modifier
                                        </a>
                                        <a href="details.php?id=<?= $emprunt['ID_Emprunt'] ?>" class="btn btn-sm btn-info">
                                            <span class="glyphicon glyphicon-eye-open"></span> Détails
                                        </a>
                                        <a href="details_comptable.php?id=<?= $emprunt['ID_Emprunt'] ?>" class="btn btn-sm btn-info">
                                            <span class="glyphicon glyphicon-list-alt"></span> Détails comptables
                                        </a>
                                        <a href="supprimer.php?id=<?= $emprunt['ID_Emprunt'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet emprunt ?')">
                                            <span class="glyphicon glyphicon-trash"></span> Supprimer
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <button type="submit" id="deleteSelectedBtn" class="btn btn-danger" style="display: none;" onclick="return confirm('Êtes-vous sûr de vouloir supprimer les emprunts sélectionnés ? Cette action est irréversible.')">
                    <span class="glyphicon glyphicon-trash"></span> Supprimer les emprunts sélectionnés
                </button>
            </form>
        <?php else: ?>
            <p class="alert alert-info">Aucun emprunt bancaire n'a été trouvé.</p>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js" integrity="sha384-aJ21OjlMXNLJ5ywYOIDjxxyTwCypxSoOO3FxyYr4fccRoP1h0IWcAukj0jz9uNNs" crossorigin="anonymous"></script>
    <script src="../js/jquery-3.7.1.js"></script>
    <script src="../js/bootstrap.min.js"></script>
	
    <script>
        $(document).ready(function() {
            // Check if initialiserTableauDynamique function exists before calling it
            // This prevents errors if tableau_dynamique.js isn't loaded correctly.
            if (typeof initialiserTableauDynamique === 'function') {
                initialiserTableauDynamique('#empruntsTable', { // Target your table by its ID
                    selection: false, // We handle selection manually with our checkboxes
                    pagination: true,
                    rowsPerPage: 10,
                    sortable: true,
                    searchable: true,
                    // Add other options as needed
                });
            } else {
                console.warn("Function 'initialiserTableauDynamique' not found. Dynamic table features might not work.");
            }

            // --- JavaScript for Bulk Deletion Logic ---

            // **1. "Select All" checkbox behavior**
            $('#selectAllEmprunts').on('change', function() {
                // Set the 'checked' property of all individual checkboxes to match the "Select All" checkbox
                $('.emprunt-checkbox').prop('checked', $(this).prop('checked'));
                toggleDeleteButtonVisibility(); // Update button visibility
            });

            // **2. Individual checkbox behavior**
            // When any individual checkbox changes, check if all are selected to update the "Select All" checkbox
            $('.emprunt-checkbox').on('change', function() {
                if ($('.emprunt-checkbox:checked').length === $('.emprunt-checkbox').length) {
                    $('#selectAllEmprunts').prop('checked', true); // All are checked
                } else {
                    $('#selectAllEmprunts').prop('checked', false); // Not all are checked
                }
                toggleDeleteButtonVisibility(); // Update button visibility
            });

            // **3. Function to show/hide the "Delete Selected" button**
            function toggleDeleteButtonVisibility() {
                if ($('.emprunt-checkbox:checked').length > 0) {
                    $('#deleteSelectedBtn').show(); // Show the button
                } else {
                    $('#deleteSelectedBtn').hide(); // Hide the button
                }
            }

            // **4. Initial check when the page loads**
            // This ensures the button's visibility is correct on page load,
            // useful if checkboxes are pre-checked (e.g., after a browser refresh).
            toggleDeleteButtonVisibility();
        });
    </script>
</body>
</html>

<?php
// Fonctions de gestion des emprunts (à implémenter dans fonctions/gestion_emprunts.php)

// Ensure this function is defined in fonctions/gestion_emprunts.php
// It should return the list of loans from the database.
function getListeEmprunts($pdo) {
    try {
        $stmt = $pdo->query("SELECT ID_Emprunt, Banque, Numero_Pret, Montant_Pret, Date_Mise_En_Place, Date_Derniere_Echeance FROM Emprunts_Bancaires ORDER BY Date_Mise_En_Place DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Gérer l'erreur (log, affichage, etc.)
        error_log("Erreur lors de la récupération de la liste des emprunts : " . $e->getMessage());
        return [];
    }
}
?>

<?php
require_once('../../templates/footer.php');
?>