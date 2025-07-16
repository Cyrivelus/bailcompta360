<?php
// pages/ecritures/liste.php

$titre = 'Liste des Écritures Comptables';
$current_page = basename($_SERVER['PHP_SELF']); // Pour la classe 'active' dans la navigation

require_once('../../templates/header.php');
require_once('../../templates/navigation.php'); // Inclusion de la navigation
?>

<?php
// modifier.php (Modifier un utilisateur)

// Inclure les fichiers nécessaires (connexion à la base de données, fonctions, etc.)
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_utilisateurs.php'; // Pour récupérer et modifier un utilisateur
require_once '../../fonctions/gestion_profils.php'; // Pour la liste des profils

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur (à adapter)
// session_start();
// if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'administrateur') {
//     header("Location: ../auth/login.php");
//     exit();
// }

// Vérifier si l'ID de l'utilisateur à modifier est passé en GET
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$utilisateurId = $_GET['id'];

// Récupérer l'utilisateur à modifier
$utilisateur = getUtilisateurParId($pdo, $utilisateurId);

if (!$utilisateur) {
    $messageErreur = "<div class='alert alert-danger'>Utilisateur non trouvé. <a href='index.php' class='alert-link'>Retour à la liste des utilisateurs</a></div>";
}

// Récupérer la liste de tous les profils pour le formulaire
$profils = getListeProfils($pdo);

// Traitement de la modification de l'utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomUtilisateur = $_POST['nom_utilisateur'];
    $nouveauMotDePasse = $_POST['nouveau_mot_de_passe'];
    $profilId = $_POST['profil_id'];

    $erreurs = [];
    if (empty($nomUtilisateur)) {
        $erreurs[] = "Le nom d'utilisateur est obligatoire.";
    }
    if (empty($profilId)) {
        $erreurs[] = "Le profil est obligatoire.";
    }

    if (empty($erreurs)) {
        if (modifierUtilisateur($db, $utilisateurId, $nomUtilisateur, $profilId, $nouveauMotDePasse)) {
            $messageSucces = "<div class='alert alert-success'>L'utilisateur '" . htmlspecialchars($nomUtilisateur) . "' a été modifié avec succès. <a href='index.php' class='alert-link'>Retour à la liste des utilisateurs</a></div>";
            $utilisateur = getUtilisateurParId($db, $utilisateurId); // Recharger les informations de l'utilisateur
        } else {
            $messageErreurModification = "<div class='alert alert-danger'>Erreur lors de la modification de l'utilisateur. Veuillez réessayer.</div>";
        }
    } else {
        $messageErreurFormulaire = "<div class='alert alert-danger'>Erreurs dans le formulaire :<ul><li>" . implode("</li><li>", $erreurs) . "</li></ul></div>";
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Modifier un Utilisateur</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css" integrity="sha384-HSMxcRTRxnN+Bdg0JdbxYKrThecOKuH5zCYotlSAcp1+c8xmyTe9GYg1l9a69psu" crossorigin="anonymous">
    <link rel="stylesheet" href="../../css/monstyle.css">
    <link rel="stylesheet" href="../../css/formulaire.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <div class="container">
        <h2 class="page-header">Modifier l'Utilisateur</h2>

        <?php if (isset($messageErreur)): ?>
            <?= $messageErreur ?>
        <?php elseif ($utilisateur): ?>

            <?php if (isset($messageSucces)): ?>
                <?= $messageSucces ?>
            <?php endif; ?>

            <?php if (isset($messageErreurModification)): ?>
                <?= $messageErreurModification ?>
            <?php endif; ?>

            <?php if (isset($messageErreurFormulaire)): ?>
                <?= $messageErreurFormulaire ?>
            <?php endif; ?>

            <form action="" method="POST" class="form-horizontal">
                <input type="hidden" name="utilisateur_id" value="<?= $utilisateur['id_utilisateur'] ?>">

                <div class="form-group">
                    <label for="nom_utilisateur" class="col-sm-3 control-label">Nom d'utilisateur</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" id="nom_utilisateur" name="nom_utilisateur" value="<?= htmlspecialchars($utilisateur['nom_utilisateur']) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="nouveau_mot_de_passe" class="col-sm-3 control-label">Nouveau mot de passe (laisser vide pour ne pas changer)</label>
                    <div class="col-sm-9">
                        <input type="password" class="form-control" id="nouveau_mot_de_passe" name="nouveau_mot_de_passe">
                        <span class="help-block">Si vous souhaitez changer le mot de passe, entrez le nouveau mot de passe ici.</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="profil_id" class="col-sm-3 control-label">Profil</label>
                    <div class="col-sm-9">
                        <select class="form-control" id="profil_id" name="profil_id" required>
                            <option value="">Sélectionner un profil</option>
                            <?php if (!empty($profils)): ?>
                                <?php foreach ($profils as $profil): ?>
                                    <option value="<?= $profil['id_profil'] ?>" <?= ($utilisateur['profil_id'] == $profil['id_profil']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($profil['nom_profil']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>Aucun profil disponible</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <div class="col-sm-offset-3 col-sm-9">
                        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                        <a href="index.php" class="btn btn-default">Annuler</a>
                    </div>
                </div>
            </form>

        <?php endif; ?>

        <hr>
        <p><a href="index.php" class="btn btn-info">Retour à la liste des utilisateurs</a></p>
        <p><a href="../admin/index.php" class="btn btn-default">Retour à l'administration</a></p>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js" integrity="sha384-aJ21OjlMXNLJ5ywYOIDjxxyTwCypxSoOO3FxyYr4fccRoP1h0IWcAukj0jz9uNNs" crossorigin="anonymous"></script>
</body>
</html>

<?php
// Les fonctions getUtilisateurParId, modifierUtilisateur et getListeProfils sont supposées exister
// dans vos fichiers fonctions/gestion_utilisateurs.php et fonctions/gestion_profils.php
// (ou l'organisation de vos fichiers de fonctions).

// Exemple de fonctions (à adapter dans vos fichiers) :

/*
// fonctions/gestion_utilisateurs.php

function getUtilisateurParId($db, $utilisateurId) {
    try {
        $stmt = $db->prepare("SELECT id_utilisateur, nom_utilisateur, profil_id FROM utilisateurs WHERE id_utilisateur = :id");
        $stmt->bindParam(':id', $utilisateurId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de l'utilisateur : " . $e->getMessage());
        return false;
    }
}

function modifierUtilisateur($db, $utilisateurId, $nomUtilisateur, $profilId, $nouveauMotDePasse = null) {
    try {
        $sql = "UPDATE utilisateurs SET nom_utilisateur = :nom, profil_id = :profil_id WHERE id_utilisateur = :id";
        if (!empty($nouveauMotDePasse)) {
            $sql = "UPDATE utilisateurs SET nom_utilisateur = :nom, mot_de_passe = :mot_de_passe, profil_id = :profil_id WHERE id_utilisateur = :id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':mot_de_passe', password_hash($nouveauMotDePasse, PASSWORD_DEFAULT));
        } else {
            $stmt = $db->prepare($sql);
        }
        $stmt->bindParam(':nom', $nomUtilisateur);
        $stmt->bindParam(':profil_id', $profilId, PDO::PARAM_INT);
        $stmt->bindParam(':id', $utilisateurId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la modification de l'utilisateur : " . $e->getMessage());
        return false;
    }
}

// fonctions/gestion_profils.php (ou gestion_utilisateurs.php)

function getListeProfils($db) {
    try {
        $stmt = $db->query("SELECT id_profil, nom_profil FROM profils ORDER BY nom_profil");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des profils : " . $e->getMessage());
        return [];
    }
}
*/
?>

<?php
require_once('../../templates/footer.php');
?>