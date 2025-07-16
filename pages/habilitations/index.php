<?php
// pages/habilitations/index.php

$titre = 'Gestion des Habilitations';
$current_page = basename($_SERVER['PHP_SELF']); // Pour la classe 'active' dans la navigation

require_once('../../templates/header.php');
require_once('../../templates/navigation.php'); // Inclusion de la navigation

// Inclure les fichiers nécessaires (connexion à la base de données, fonctions, etc.)
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_habilitations.php';

// Récupérer la liste des profils et des utilisateurs pour afficher les habilitations
$profils = getListeProfils($pdo);
$utilisateurs = getListeUtilisateurs($pdo);

// Traitement des messages de succès ou d'erreur (si redirection)
$successMessage = isset($_GET['success']) ? $_GET['success'] : null;
$errorMessage = isset($_GET['error']) ? $_GET['error'] : null;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Gestion des Habilitations</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css" integrity="sha384-HSMxcRTRxnN+Bdg0JdbxYKrThecOKuH5zCYotlSAcp1+c8xmyTe9GYg1l9a69psu" crossorigin="anonymous">
    <link rel="stylesheet" href="../../css/monstyle.css">
    <link rel="stylesheet" href="../../css/tableau.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .habilitation-section {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .habilitation-section h3 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        .habilitation-list {
            list-style-type: none;
            padding: 0;
        }
        .habilitation-list li {
            margin-bottom: 5px;
        }
        .btn-habilitation {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="page-header">Gestion des Habilitations</h2>

        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?= $successMessage ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?= $errorMessage ?></div>
        <?php endif; ?>

        <p>
            <a href="profils.php" class="btn btn-primary btn-habilitation">
                <span class="glyphicon glyphicon-list-alt"></span> Gestion des Profils
            </a>
            <a href="utilisateurs.php?section=habilitations" class="btn btn-info btn-habilitation">
                <span class="glyphicon glyphicon-user"></span> Gestion des Utilisateurs (Habilitations)
            </a>
            <a href="gestion_droits.php" class="btn btn-warning btn-habilitation">
                <span class="glyphicon glyphicon-lock"></span> Gestion des Droits Spécifiques
            </a>
        </p>

        <div class="habilitation-section">
            <h3>Habilitations par Profil</h3>
            <?php if (!empty($profils)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom du Profil</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($profils as $profil): ?>
                                <tr>
                                    <td><?= $profil['ID_Profil'] ?></td>
                                    <td><?= htmlspecialchars($profil['Nom_Profil']) ?></td>
                                    <td>
                                        <a href="modifier_profil_habilitations.php?id=<?= $profil['ID_Profil'] ?>" class="btn btn-sm btn-warning">
                                            <span class="glyphicon glyphicon-cog"></span> Gérer les Habilitations
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="alert alert-info">Aucun profil n'a été créé.</p>
            <?php endif; ?>
        </div>

        <div class="habilitation-section">
            <h3>Habilitations par Utilisateur</h3>
            <?php if (!empty($utilisateurs)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($utilisateurs as $utilisateur): ?>
                                <tr>
                                    <td><?= $utilisateur['ID_Utilisateur'] ?></td>
                                    <td><?= htmlspecialchars($utilisateur['Nom']) ?></td>
                                    <td><?= htmlspecialchars($utilisateur['Login']) ?></td>
                                    <td>
                                        <a href="modifier_utilisateur_habilitations.php?id=<?= $utilisateur['ID_Utilisateur'] ?>" class="btn btn-sm btn-warning">
                                            <span class="glyphicon glyphicon-cog"></span> Gérer les Habilitations
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="alert alert-info">Aucun utilisateur n'a été créé.</p>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js" integrity="sha384-aJ21OjlMXNLJ5ywYOIDjxxyTwCypxSoOO3FxyYr4fccRoP1h0IWcAukj0jz9uNNs" crossorigin="anonymous"></script>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="js/script.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="../js/tableau_dynamique.js"></script>
    <script>
        $(document).ready(function() {
            initialiserTableauDynamique('.table', {
                selection: false,
                pagination: true,
                rowsPerPage: 10,
                sortable: true,
                searchable: true
            });
        });
    </script>
</body>
</html>

<?php
// Fonctions de gestion des habilitations (à implémenter dans fonctions/gestion_habilitations.php)

function getListeProfils($db) {
    try {
        $stmt = $db->query("SELECT ID_Profil, Nom_Profil FROM Profils ORDER BY Nom_Profil ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la liste des profils : " . $e->getMessage());
        return [];
    }
}

function getListeUtilisateurs($db) {
    try {
        $stmt = $db->query("SELECT ID_Utilisateur, Nom, Login FROM Utilisateurs ORDER BY Nom ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la liste des utilisateurs : " . $e->getMessage());
        return [];
    }
}

// Vous aurez besoin des fichiers suivants :
// - profils.php : Pour la gestion de la liste des profils (ajout, modification, suppression).
// - utilisateurs.php?section=habilitations : Pour la gestion des habilitations au niveau des utilisateurs.
// - gestion_droits.php : Pour la gestion des droits spécifiques (objets protégés).
// - modifier_profil_habilitations.php : Formulaire pour modifier les habilitations d'un profil.
// - modifier_utilisateur_habilitations.php : Formulaire pour modifier les habilitations d'un utilisateur.
?>

<?php
require_once('../../templates/footer.php');
?>