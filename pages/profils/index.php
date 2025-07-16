<?php
// pages/ecritures/liste.php

$titre = 'Liste des Écritures Comptables';
$current_page = basename($_SERVER['PHP_SELF']); // Pour la classe 'active' dans la navigation

require_once('../../templates/header.php');
require_once('../../templates/navigation.php'); // Inclusion de la navigation
?>


<?php
// index.php (Gestion des profils)

// Inclure les fichiers nécessaires (connexion à la base de données, fonctions, etc.)
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_profils.php'; // Pour gérer les profils

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur (à adapter)
// session_start();
// if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'administrateur') {
//     header("Location: ../auth/login.php");
//     exit();
// }

// Récupérer la liste de tous les profils
$profils = getListeProfils($pdo);

// Traitement de l'ajout d'un nouveau profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_profil'])) {
    $nomProfil = $_POST['nom_profil'];

    if (!empty($nomProfil)) {
        if (ajouterProfil($pdo, $nomProfil)) {
            $messageSuccesAjout = "<div class='alert alert-success'>Le profil '" . htmlspecialchars($nomProfil) . "' a été ajouté avec succès.</div>";
            $profils = getListeProfils($db); // Recharger la liste
        } else {
            $messageErreurAjout = "<div class='alert alert-danger'>Erreur lors de l'ajout du profil.</div>";
        }
    } else {
        $messageErreurAjout = "<div class='alert alert-danger'>Le nom du profil ne peut pas être vide.</div>";
    }
}

// Traitement de la suppression d'un profil
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['supprimer_profil'])) {
    $profilIdSupprimer = $_GET['supprimer_profil'];

    // Vérifier si le profil est utilisé par des utilisateurs avant de supprimer (recommandé)
    $utilisateursAvecProfil = getUtilisateursParProfil($pdo, $profilIdSupprimer);
    if (empty($utilisateursAvecProfil)) {
        if (supprimerProfil($db, $profilIdSupprimer)) {
            $messageSuccesSuppression = "<div class='alert alert-success'>Le profil a été supprimé avec succès.</div>";
            $profils = getListeProfils($db); // Recharger la liste
        } else {
            $messageErreurSuppression = "<div class='alert alert-danger'>Erreur lors de la suppression du profil.</div>";
        }
    } else {
        $messageErreurSuppression = "<div class='alert alert-danger'>Ce profil est utilisé par des utilisateurs et ne peut pas être supprimé.</div>";
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Gestion des Profils</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css" integrity="sha384-HSMxcRTRxnN+Bdg0JdbxYKrThecOKuH5zCYotlSAcp1+c8xmyTe9GYg1l9a69psu" crossorigin="anonymous">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/formulaire.css">
	
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <div class="container">
        <h2 class="page-header">Gestion des Profils</h2>

        <?php if (isset($messageSuccesAjout)): ?>
            <?= $messageSuccesAjout ?>
        <?php endif; ?>

        <?php if (isset($messageErreurAjout)): ?>
            <?= $messageErreurAjout ?>
        <?php endif; ?>

        <?php if (isset($messageSuccesSuppression)): ?>
            <?= $messageSuccesSuppression ?>
        <?php endif; ?>

        <?php if (isset($messageErreurSuppression)): ?>
            <?= $messageErreurSuppression ?>
        <?php endif; ?>

        <h3>Ajouter un Nouveau Profil</h3>
        <form action="" method="POST" class="form-horizontal">
            <div class="form-group">
                <label for="nom_profil" class="col-sm-3 control-label">Nom du Profil</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" id="nom_profil" name="nom_profil" required>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-9">
                    <button type="submit" class="btn btn-primary" name="ajouter_profil">Ajouter le Profil</button>
                </div>
            </div>
        </form>

        <hr>

        <h3>Liste des Profils</h3>
        <?php if (!empty($profils)): ?>
            <table class="table table-striped">
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
                            <td><?= $profil['id_profil'] ?></td>
                            <td><?= htmlspecialchars($profil['nom_profil']) ?></td>
                            <td>
                                <a href="modifier_profil.php?id=<?= $profil['id_profil'] ?>" class="btn btn-sm btn-warning">Modifier</a>
                                <a href="?supprimer_profil=<?= $profil['id_profil'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce profil ?')"
                                   <?php if (!empty(getUtilisateursParProfil($db, $profil['id_profil']))): ?>
                                       style="pointer-events: none; opacity: 0.6;" title="Ce profil est utilisé par des utilisateurs et ne peut pas être supprimé."
                                   <?php endif; ?>
                                >Supprimer</a>
                                <a href="gestion_habilitations_profil.php?id=<?= $profil['id_profil'] ?>" class="btn btn-sm btn-info">Habilitations</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Aucun profil trouvé.</p>
        <?php endif; ?>

        <p><a href="../admin/index.php" class="btn btn-default">Administration</a></p>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js" integrity="sha384-aJ21OjlMXNLJ5ywYOIDjxxyTwCypxSoOO3FxyYr4fccRoP1h0IWcAukj0jz9uNNs" crossorigin="anonymous"></script>
	<script src="js/script.js"></script>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="../js/tableau_dynamique.js"></script>
    <script>
        $(document).ready(function() {
            // Initialisez le tableau dynamique en ciblant son ID ou sa classe
            initialiserTableauDynamique('#monTableauHTML', {
                selection: true,
                pagination: true,
                rowsPerPage: 10,
                sortable: true,
                searchable: true,
                // Autres options si nécessaire
            });

            // Vous pouvez initialiser d'autres tableaux dynamiques sur la même page ici
            initialiserTableauDynamique('#autreTableau', {
                // Différentes options pour cet autre tableau
            });
        });
    </script>
</body>
</body>
</html>

<?php
// Fonctions de gestion des profils (à implémenter dans fonctions/gestion_profils.php)

/**
 * Récupère la liste de tous les profils.
 * (Fonction à implémenter dans fonctions/gestion_profils.php)
 */
function getListeProfils($db) {
    try {
        $stmt = $db->query("SELECT id_profil, nom_profil FROM profils ORDER BY nom_profil");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des profils : " . $e->getMessage());
        return [];
    }
}

/**
 * Ajoute un nouveau profil.
 * (Fonction à implémenter dans fonctions/gestion_profils.php)
 */

/**
 * Récupère la liste des utilisateurs ayant un profil spécifique.
 * (Fonction à implémenter dans fonctions/gestion_utilisateurs.php)
 */
function getUtilisateursParProfil($db, $profilId) {
    try {
        $stmt = $db->prepare("SELECT id_utilisateur FROM utilisateurs WHERE profil_id = :profil_id");
        $stmt->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des utilisateurs par profil : " . $e->getMessage());
        return [];
    }
}

// Vous devrez également avoir une table 'profils' dans votre base de données.
?>
<?php
require_once('../../templates/footer.php');
?>