<?php
// pages/admin/utilisateurs/index.php

// Démarrer la session pour la gestion de l'authentification
session_start();

// Inclure les fichiers nécessaires
require_once(__DIR__ . '/../../../fonctions/database.php');
require_once(__DIR__ . '/../../../fonctions/gestion_utilisateurs.php');

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Admin') {
    // Rediriger si non autorisé
    header("Location: ../../../index.php?error=Accès non autorisé");
    exit();
}

// Configuration de la page
$title = "Gestion des Utilisateurs";
include(__DIR__ . '/../../../templates/header.php');
include(__DIR__ . '/../../../templates/navigation.php');

// Récupérer la liste de tous les utilisateurs
$utilisateurs = getTousLesUtilisateurs($pdo);
$utilisateursConnectes = getUtilisateursConnectesRecemment($pdo, 10);
$idsUtilisateursConnectes = array_column($utilisateursConnectes, 'ID_Utilisateur');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Administration - Gestion des Utilisateurs</title>
    <link rel="shortcut icon" href="../../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../css/style.css">
    <link rel="stylesheet" href="../../../css/admin_style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    /* Style pour l'indicateur "Connecté" */
    .status-dot {
        height: 12px;
        width: 12px;
        background-color: #28a745; /* Vert pour connecté */
        border-radius: 50%;
        display: inline-block;
        margin-right: 8px;
        vertical-align: middle;
    }
    .status-offline {
        background-color: #6c757d; /* Gris pour déconnecté */
    }
    .status-suspended {
        background-color: #ffc107; /* Jaune/Orange pour suspendu */
    }
    /* Styles pour les boutons d'action */
    td a.btn, td button.btn {
        margin-bottom: 8px; /* Petit espace entre les boutons dans une cellule */
        white-space: nowrap; /* Empêche les boutons de se diviser sur plusieurs lignes */
        margin-right: 8px; /* Espace entre les boutons */
        padding: 5px 10px; /* Réduire la taille des boutons */
        font-size: 12px; /* Réduire la taille de la police des boutons */
    }

    /* Centering the main content for Bootstrap 3 */
    .main-content-wrapper {
        padding-right: 20px; /* Augmenter le rembourrage */
        padding-left: 20px;  /* Augmenter le rembourrage */
        margin-right: auto;  /* Center the block */
        margin-left: auto;   /* Center the block */
        max-width: 1200px; /* Optional: set a max width for large screens */
    }
    @media (min-width: 768px) {
        .main-content-wrapper {
            width: 95%; /* Adjust width for medium screens and up */
        }
    }
    @media (min-width: 992px) {
        .main-content-wrapper {
            width: 90%; /* Adjust width for larger screens */
        }
    }
    @media (min-width: 1200px) {
        .main-content-wrapper {
            width: 1200px; /* Max width for very large screens */
        }
    }

    /* Adjusting the modal dialog width for a slightly wider modal */
    .modal-dialog {
        width: 500px; /* Augmenter la largeur du modal */
        margin: 30px auto; /* Centers horizontally and adds top margin */
    }
    @media (min-width: 768px) {
        .modal-dialog {
            width: 500px; /* Fixed width for larger screens */
        }
    }

    /* Ajouter des bordures et des ombres pour les cartes et les tableaux */
    .table-responsive {
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        border-radius: 5px;
        margin-bottom: 20px;
    }

    /* Améliorer l'espacement dans le tableau */
    .table {
        margin-bottom: 0;
    }
    .table th, .table td {
        padding: 12px; /* Augmenter le rembourrage des cellules */
        vertical-align: middle; /* Aligner verticalement le contenu des cellules */
    }

    /* Ajouter des marges autour des en-têtes et des boutons */
    .d-flex.justify-content-between.flex-wrap.flex-md-nowrap.align-items-center.pt-3.pb-2.mb-3.border-bottom {
        padding-bottom: 15px;
        margin-bottom: 25px;
    }

    /* Ajouter des marges autour des alertes */
    .alert {
        margin-bottom: 20px;
    }

    /* Ajouter des marges autour des titres */
    h1.h2 {
        margin-bottom: 20px;
    }
    h2 {
        margin-bottom: 20px;
    }
</style>

</head>
<body>
    <div class="main-content-wrapper">
        <main class="col-md-9 ms-sm-auto col-lg-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Gestion des Utilisateurs</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="ajouter.php" class="btn btn-sm btn-outline-primary">Ajouter un Utilisateur</a>
                        <a href="?view=connected" class="btn btn-sm btn-outline-info">Voir Connectés (<?= count($utilisateursConnectes) ?>)</a>
                    </div>
                </div>
            </div>

            <?php
            // Afficher les messages flash de session
            if (isset($_SESSION['flash_message'])) {
                $alertClass = ($_SESSION['flash_type'] === 'success') ? 'alert-success' : 'alert-danger';
                echo '<div class="alert ' . $alertClass . '">' . htmlspecialchars($_SESSION['flash_message']) . '</div>';
                unset($_SESSION['flash_message']);
                unset($_SESSION['flash_type']);
            }
            ?>

            <?php
            $currentView = $_GET['view'] ?? 'all';
            if ($currentView === 'connected') {
                echo '<h2>Utilisateurs Connectés Récemment</h2>';
                $displayUsers = $utilisateursConnectes;
            } else {
                echo '<h2>Liste de Tous les Utilisateurs</h2>';
                $displayUsers = $utilisateurs;
            }
            ?>

            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Login</th>
                            <th>Rôle</th>
                            <th>Dernière Connexion</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($displayUsers)): ?>
                            <tr><td colspan="7">Aucun utilisateur trouvé pour cette vue.</td></tr>
                        <?php else: ?>
                            <?php foreach ($displayUsers as $utilisateur): ?>
                                <?php
                                $estConnecte = in_array($utilisateur['ID_Utilisateur'], $idsUtilisateursConnectes);
                                $lockoutUntil = $utilisateur['lockout_until'] ?? null;
                                $loginAttempts = $utilisateur['login_attempts'] ?? 0;
                                $isSuspended = ($lockoutUntil !== null && new DateTime() < new DateTime($lockoutUntil) && $loginAttempts >= 999);

                                if ($isSuspended) {
                                    $statusClass = 'status-suspended';
                                    $statusText = 'Suspendu jusqu\'au ' . date('d/m/Y H:i:s', strtotime($lockoutUntil));
                                } elseif ($estConnecte) {
                                    $statusClass = 'status-connected';
                                    $statusText = 'Connecté';
                                } else {
                                    $statusClass = 'status-offline';
                                    $statusText = 'Hors ligne';
                                }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($utilisateur['ID_Utilisateur']) ?></td>
                                    <td><?= htmlspecialchars($utilisateur['Nom']) ?></td>
                                    <td><?= htmlspecialchars($utilisateur['Login_Utilisateur']) ?></td>
                                    <td><?= htmlspecialchars($utilisateur['Role']) ?></td>
                                    <td>
                                        <?= htmlspecialchars(
                                            $utilisateur['Derniere_Connexion'] ?
                                            date('d/m/Y H:i:s', strtotime($utilisateur['Derniere_Connexion'])) :
                                            'Jamais'
                                        ) ?>
                                    </td>
                                    <td>
                                        <span class="status-dot <?= $statusClass ?>"></span> <?= $statusText ?>
                                    </td>
                                    <td>
                                        <a href="modifier.php?id=<?= htmlspecialchars($utilisateur['ID_Utilisateur']) ?>" class="btn btn-sm btn-outline-secondary">Modifier</a>
                                        <a href="reinitialiser.php?id=<?= htmlspecialchars($utilisateur['ID_Utilisateur']) ?>" class="btn btn-sm btn-outline-secondary">Réinitialiser mot de passe</a>
                                        <?php if (!$isSuspended): ?>
                                            <a href="deconnecter_force.php?id=<?= htmlspecialchars($utilisateur['ID_Utilisateur']) ?>" class="btn btn-sm btn-outline-warning" onclick="return confirm('Êtes-vous sûr de vouloir déconnecter cet utilisateur ?');">Déconnecter</a>
                                            <button type="button" class="btn btn-sm btn-warning" data-toggle="modal" data-target="#suspendModal" data-userid="<?= htmlspecialchars($utilisateur['ID_Utilisateur']) ?>" data-username="<?= htmlspecialchars($utilisateur['Nom']) ?>">
                                                Suspendre
                                            </button>
                                        <?php else: ?>
                                            <a href="unsuspendre.php?id=<?= htmlspecialchars($utilisateur['ID_Utilisateur']) ?>" class="btn btn-sm btn-success" onclick="return confirm('Êtes-vous sûr de vouloir lever la suspension de cet utilisateur ?');">Lever Suspension</a>
                                        <?php endif; ?>
                                        <a href="modifier_role.php?id=<?= htmlspecialchars($utilisateur['ID_Utilisateur']) ?>" class="btn btn-sm btn-outline-secondary">Rôle</a>
                                        <a href="supprimer.php?id=<?= htmlspecialchars($utilisateur['ID_Utilisateur']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">Supprimer</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Modal pour la suspension -->
    <div class="modal fade" id="suspendModal" tabindex="-1" role="dialog" aria-labelledby="suspendModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form action="suspendre.php" method="POST">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title" id="suspendModalLabel">Suspendre l'utilisateur : <span id="modalUserName"></span></h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="suspendUserId">
                        <div class="form-group">
                            <label for="suspension_end_date">Date et heure de fin de suspension :</label>
                            <input type="datetime-local" class="form-control" id="suspension_end_date" name="suspension_end_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-warning">Confirmer Suspension</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include(__DIR__ . '/../../../templates/footer.php'); ?>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script>
        // JavaScript pour remplir la modal avec les données de l'utilisateur
        $('#suspendModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var userId = button.data('userid');
            var userName = button.data('username');
            var modal = $(this);
            modal.find('#suspendUserId').val(userId);
            modal.find('#modalUserName').text(userName);

            // Définir la date par défaut pour la suspension (par exemple, 1 jour à partir de maintenant)
            var now = new Date();
            now.setDate(now.getDate() + 1);
            var year = now.getFullYear();
            var month = (now.getMonth() + 1).toString().padStart(2, '0');
            var day = now.getDate().toString().padStart(2, '0');
            var hours = now.getHours().toString().padStart(2, '0');
            var minutes = now.getMinutes().toString().padStart(2, '0');
            var defaultDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
            modal.find('#suspension_end_date').val(defaultDateTime);
        });
    </script>
</body>
</html>
