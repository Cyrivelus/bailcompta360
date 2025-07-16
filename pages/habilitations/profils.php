<?php
// pages/ecritures/liste.php

$titre = 'Liste des Écritures Comptables';
$current_page = basename($_SERVER['PHP_SELF']); // Pour la classe 'active' dans la navigation

require_once('../../templates/header.php');
require_once('../../templates/navigation.php'); // Inclusion de la navigation
?>


<?php
// profils.php (Gestion des profils et habilitations)

// Inclure les fichiers nécessaires (connexion à la base de données, fonctions, etc.)
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_utilisateurs.php'; // Pour gérer les utilisateurs et leurs rôles
require_once '../../fonctions/gestion_habilitations.php'; // Pour gérer les habilitations

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur (à adapter)
// session_start();
// if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'administrateur') {
//     header("Location: ../auth/login.php");
//     exit();
// }

// Récupérer la liste des profils (rôles) existants
$profils = getListeProfils($pdo);

// Récupérer la liste de toutes les habilitations possibles
$habilitationsDisponibles = getListeHabilitations($pdo);

// Traitement de la création d'un nouveau profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_profil'])) {
    $nomProfil = $_POST['nom_profil'];

    if (!empty($nomProfil)) {
        if (ajouterProfil($pdo, $nomProfil)) {
            $messageSucces = "<div class='alert alert-success'>Le profil '" . htmlspecialchars($nomProfil) . "' a été créé avec succès.</div>";
            $profils = getListeProfils($pdo); // Recharger la liste des profils
        } else {
            $messageErreur = "<div class='alert alert-danger'>Erreur lors de la création du profil.</div>";
        }
    } else {
        $messageErreur = "<div class='alert alert-danger'>Le nom du profil ne peut pas être vide.</div>";
    }
}

// Traitement de la modification des habilitations d'un profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_habilitations'])) {
    $profilId = $_POST['profil_id'];
    $habilitationsSelectionnees = isset($_POST['habilitations']) ? $_POST['habilitations'] : [];

    if (modifierHabilitationsProfil($pdo, $profilId, $habilitationsSelectionnees)) {
        $messageSuccesHabilitations = "<div class='alert alert-success'>Les habilitations du profil ont été mises à jour avec succès.</div>";
    } else {
        $messageErreurHabilitations = "<div class='alert alert-danger'>Erreur lors de la mise à jour des habilitations du profil.</div>";
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Gestion des Profils et Habilitations</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css" integrity="sha384-HSMxcRTRxnN+Bdg0JdbxYKrThecOKuH5zCYotlSAcp1+c8xmyTe9GYg1l9a69psu" crossorigin="anonymous">
    <link rel="stylesheet" href="../../css/monstyle.css">
    <link rel="stylesheet" href="../../css/formulaire.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <div class="container">
        <h2 class="page-header">Gestion des Profils et Habilitations</h2>

        <?php if (isset($messageSucces)): ?>
            <?= $messageSucces ?>
        <?php endif; ?>

        <?php if (isset($messageErreur)): ?>
            <?= $messageErreur ?>
        <?php endif; ?>

        <h3>Créer un Nouveau Profil</h3>
        <form action="" method="POST" class="form-horizontal">
            <div class="form-group">
                <label for="nom_profil" class="col-sm-3 control-label">Nom du Profil</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" id="nom_profil" name="nom_profil" required>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-9">
                    <button type="submit" class="btn btn-primary" name="creer_profil">Créer le Profil</button>
                </div>
            </div>
        </form>

        <hr>

        <h3>Modifier les Habilitations des Profils</h3>

        <?php if (isset($messageSuccesHabilitations)): ?>
            <?= $messageSuccesHabilitations ?>
        <?php endif; ?>

        <?php if (isset($messageErreurHabilitations)): ?>
            <?= $messageErreurHabilitations ?>
        <?php endif; ?>

        <?php if (!empty($profils)): ?>
            <?php foreach ($profils as $profil): ?>
                <h4><?= htmlspecialchars($profil['nom_profil']) ?></h4>
                <form action="" method="POST" class="form-horizontal">
                    <input type="hidden" name="profil_id" value="<?= $profil['id_profil'] ?>">
                    <div class="form-group">
                        <label class="col-sm-3 control-label">Habilitations</label>
                        <div class="col-sm-9">
                            <?php
                            $habilitationsProfil = getHabilitationsProfil($pdo, $profil['id_profil']);
                            if (!empty($habilitationsDisponibles)):
                                foreach ($habilitationsDisponibles as $habilitation):
                                    $checked = in_array($habilitation['id_habilitation'], array_column($habilitationsProfil, 'id_habilitation')) ? 'checked' : '';
                                    ?>
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="habilitations[]" value="<?= $habilitation['id_habilitation'] ?>" <?= $checked ?>>
                                            <?= htmlspecialchars($habilitation['nom_habilitation']) ?> - <?= htmlspecialchars($habilitation['description']) ?>
                                        </label>
                                    </div>
                                    <?php
                                endforeach;
                            else: ?>
                                <p>Aucune habilitation disponible.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-sm-offset-3 col-sm-9">
                            <button type="submit" class="btn btn-primary" name="modifier_habilitations">Enregistrer les Habilitations</button>
                        </div>
                    </div>
                </form>
                <hr>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Aucun profil trouvé.</p>
        <?php endif; ?>

        <p><a href="../admin/index.php" class="btn btn-default">Administration</a></p>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js" integrity="sha384-aJ21OjlMXNLJ5ywYOIDjxxyTwCypxSoOO3FxyYr4fccRoP1h0IWcAukj0jz9uNNs" crossorigin="anonymous"></script>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="../js/tableau_dynamique.js"></script>
	<script src="js/script.js"></script>
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
// Fonctions de gestion des profils et habilitations (à implémenter dans fonctions/gestion_habilitations.php et gestion_utilisateurs.php)

/**
 * Récupère la liste de tous les profils (rôles).
 * (Fonction à implémenter dans gestion_utilisateurs.php)
 */

/**
 * Ajoute un nouveau profil.
 * (Fonction à implémenter dans gestion_utilisateurs.php)
 */

/**
 * Récupère la liste de toutes les habilitations disponibles.
 * (Fonction à implémenter dans gestion_habilitations.php)
 */


/**
 * Récupère les habilitations associées à un profil spécifique.
 * (Fonction à implémenter dans gestion_habilitations.php)
 */


/**
 * Modifie les habilitations associées à un profil.
 * (Fonction à implémenter dans gestion_habilitations.php)
 */


// Vous devrez également avoir des tables 'profils', 'habilitations' et 'profil_habilitations'
// dans votre base de données.
?>
<?php
require_once('../../templates/footer.php');
?>