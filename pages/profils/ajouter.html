<?php
// pages/ecritures/liste.php

$titre = 'Liste des Écritures Comptables';
$current_page = basename($_SERVER['PHP_SELF']); // Pour la classe 'active' dans la navigation

require_once('../../templates/header.php');
require_once('../../templates/navigation.php'); // Inclusion de la navigation
?>


<?php
// ajouter.php (Ajouter un nouveau profil)

// Inclure les fichiers nécessaires (connexion à la base de données, fonctions, etc.)
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_profils.php'; // Pour ajouter un profil

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur (à adapter)
// session_start();
// if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'administrateur') {
//     header("Location: ../auth/login.php");
//     exit();
// }

// Traitement de l'ajout d'un nouveau profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomProfil = $_POST['nom_profil'];

    if (!empty($nomProfil)) {
        if (ajouterProfil($pdo, $nomProfil)) {
            $messageSucces = "<div class='alert alert-success'>Le profil '" . htmlspecialchars($nomProfil) . "' a été ajouté avec succès. <a href='index.php' class='alert-link'>Retour à la liste des profils</a></div>";
            // Réinitialiser le champ du formulaire après succès
            $_POST['nom_profil'] = '';
        } else {
            $messageErreur = "<div class='alert alert-danger'>Erreur lors de l'ajout du profil. Veuillez réessayer.</div>";
        }
    } else {
        $messageErreur = "<div class='alert alert-danger'>Le nom du profil ne peut pas être vide.</div>";
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Ajouter un Profil</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css" integrity="sha384-HSMxcRTRxnN+Bdg0JdbxYKrThecOKuH5zCYotlSAcp1+c8xmyTe9GYg1l9a69psu" crossorigin="anonymous">
    <link rel="stylesheet" href="../../css/monstyle.css">
    <link rel="stylesheet" href="../../css/formulaire.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <div class="container">
        <h2 class="page-header">Ajouter un Nouveau Profil</h2>

        <?php if (isset($messageSucces)): ?>
            <?= $messageSucces ?>
        <?php endif; ?>

        <?php if (isset($messageErreur)): ?>
            <?= $messageErreur ?>
        <?php endif; ?>

        <form action="" method="POST" class="form-horizontal">
            <div class="form-group">
                <label for="nom_profil" class="col-sm-3 control-label">Nom du Profil</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" id="nom_profil" name="nom_profil" value="<?= isset($_POST['nom_profil']) ? htmlspecialchars($_POST['nom_profil']) : '' ?>" required>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-9">
                    <button type="submit" class="btn btn-primary" name="ajouter_profil">Ajouter le Profil</button>
                    <a href="index.php" class="btn btn-default">Annuler</a>
                </div>
            </div>
        </form>

        <hr>
        <p><a href="index.php" class="btn btn-info">Retour à la liste des profils</a></p>
        <p><a href="../admin/index.php" class="btn btn-default">Administration</a></p>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js" integrity="sha384-aJ21OjlMXNLJ5ywYOIDjxxyTwCypxSoOO3FxyYr4fccRoP1h0IWcAukj0jz9uNNs" crossorigin="anonymous"></script>
</body>
</html>

<?php
// La fonction ajouterProfil est supposée exister dans votre fichier fonctions/gestion_profils.php
// (ou l'organisation de vos fichiers de fonctions).

// Exemple de fonction (à adapter dans votre fichier) :
/*
// fonctions/gestion_profils.php

function ajouterProfil($db, $nomProfil) {
    try {
        $stmt = $db->prepare("INSERT INTO profils (nom_profil) VALUES (:nom_profil)");
        $stmt->bindParam(':nom_profil', $nomProfil);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout du profil : " . $e->getMessage());
        return false;
    }
}
*/
?>

<?php
require_once('../../templates/footer.php');
?>