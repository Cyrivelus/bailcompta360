<?php
// pages/ecritures/liste.php

$titre = 'Liste des Écritures Comptables';
$current_page = basename($_SERVER['PHP_SELF']); // Pour la classe 'active' dans la navigation

require_once('../../templates/header.php');
require_once('../../templates/navigation.php'); // Inclusion de la navigation
?>


<?php
// modifier.php (Modifier un profil)

// Inclure les fichiers nécessaires (connexion à la base de données, fonctions, etc.)
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_profils.php'; // Pour récupérer et modifier un profil

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur (à adapter)
// session_start();
// if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'administrateur') {
//     header("Location: ../auth/login.php");
//     exit();
// }

// Vérifier si l'ID du profil à modifier est passé en GET
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$profilId = $_GET['id'];

// Récupérer le profil à modifier
$profil = getProfilParId($pdo, $profilId);

if (!$profil) {
    $messageErreur = "<div class='alert alert-danger'>Profil non trouvé. <a href='index.php' class='alert-link'>Retour à la liste des profils</a></div>";
}

// Traitement de la modification du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nouveauNomProfil = $_POST['nom_profil'];

    if (!empty($nouveauNomProfil)) {
        if (modifierProfil($pdo, $profilId, $nouveauNomProfil)) {
            $messageSucces = "<div class='alert alert-success'>Le profil '" . htmlspecialchars($nouveauNomProfil) . "' a été modifié avec succès. <a href='index.php' class='alert-link'>Retour à la liste des profils</a></div>";
            $profil = getProfilParId($pdo, $profilId); // Recharger les informations du profil
        } else {
            $messageErreurModification = "<div class='alert alert-danger'>Erreur lors de la modification du profil. Veuillez réessayer.</div>";
        }
    } else {
        $messageErreurNomVide = "<div class='alert alert-danger'>Le nom du profil ne peut pas être vide.</div>";
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Modifier un Profil</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css" integrity="sha384-HSMxcRTRxnN+Bdg0JdbxYKrThecOKuH5zCYotlSAcp1+c8xmyTe9GYg1l9a69psu" crossorigin="anonymous">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/formulaire.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <div class="container">
        <h2 class="page-header">Modifier le Profil</h2>

        <?php if (isset($messageErreur)): ?>
            <?= $messageErreur ?>
        <?php elseif ($profil): ?>

            <?php if (isset($messageSucces)): ?>
                <?= $messageSucces ?>
            <?php endif; ?>

            <?php if (isset($messageErreurModification)): ?>
                <?= $messageErreurModification ?>
            <?php endif; ?>

            <?php if (isset($messageErreurNomVide)): ?>
                <?= $messageErreurNomVide ?>
            <?php endif; ?>

            <form action="" method="POST" class="form-horizontal">
                <input type="hidden" name="profil_id" value="<?= $profil['id_profil'] ?>">

                <div class="form-group">
                    <label for="nom_profil" class="col-sm-3 control-label">Nom du Profil</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" id="nom_profil" name="nom_profil" value="<?= htmlspecialchars($profil['nom_profil']) ?>" required>
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
        <p><a href="index.php" class="btn btn-info">Retour à la liste des profils</a></p>
        <p><a href="../admin/index.php" class="btn btn-default">Administration</a></p>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js" integrity="sha384-aJ21OjlMXNLJ5ywYOIDjxxyTwCypxSoOO3FxyYr4fccRoP1h0IWcAukj0jz9uNNs" crossorigin="anonymous"></script>
</body>
</html>

<?php
// Les fonctions getProfilParId et modifierProfil sont supposées exister
// dans votre fichier fonctions/gestion_profils.php
// (ou l'organisation de vos fichiers de fonctions).

// Exemple de fonctions (à adapter dans votre fichier) :

/*
// fonctions/gestion_profils.php

function getProfilParId($db, $profilId) {
    try {
        $stmt = $db->prepare("SELECT id_profil, nom_profil FROM profils WHERE id_profil = :id");
        $stmt->bindParam(':id', $profilId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération du profil : " . $e->getMessage());
        return false;
    }
}

function modifierProfil($db, $profilId, $nouveauNomProfil) {
    try {
        $stmt = $db->prepare("UPDATE profils SET nom_profil = :nom WHERE id_profil = :id");
        $stmt->bindParam(':nom', $nouveauNomProfil);
        $stmt->bindParam(':id', $profilId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de la modification du profil : " . $e->getMessage());
        return false;
    }
}
*/
?>
<?php
require_once('../../templates/footer.php');
?>