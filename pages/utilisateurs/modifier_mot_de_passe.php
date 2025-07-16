<?php
// pages/utilisateurs/modifier_mot_de_passe.php

// Démarrer la session si ce n'est pas déjà fait
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Inclure le fichier de connexion à la base de données
// Assurez-vous que le chemin est correct en fonction de l'emplacement de votre fichier de connexion
require_once __DIR__ . '/../../fonctions/database.php'; // Exemple de cheminrequire_once __DIR__ . '/../../functions/generer_url.php'; // Inclure la fonction generateUrl() si elle n'est pas déjà globale

// Rediriger si l'utilisateur n'est pas connecté
if (!isset($_SESSION['utilisateur_id'])) {
    header('Location: ' . generateUrl('index.php'));
    exit();
}

$utilisateur_id = $_SESSION['utilisateur_id'];
$message_success = '';
$message_erreur = '';

// Traitement du formulaire lorsque le mot de passe est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 1. Validation côté serveur
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message_erreur = "Tous les champs sont obligatoires.";
    } elseif ($new_password !== $confirm_password) {
        $message_erreur = "Le nouveau mot de passe et la confirmation ne correspondent pas.";
    } elseif (strlen($new_password) < 8) { // Exemple: minimum 8 caractères
        $message_erreur = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
    } else {
        try {
           

            // 2. Récupérer le mot de passe haché actuel de l'utilisateur
            $stmt = $pdo->prepare("SELECT Mot_de_Passe FROM Utilisateurs WHERE ID_Utilisateur = :id");
            $stmt->bindParam(':id', $utilisateur_id, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                // Ceci ne devrait pas arriver si l'utilisateur_id vient de la session
                $message_erreur = "Utilisateur non trouvé.";
            } else {
                $hashed_current_password_in_db = $user['Mot_de_Passe'];

                // 3. Vérifier le mot de passe actuel
                if (password_verify($current_password, $hashed_current_password_in_db)) {
                    // 4. Hacher le nouveau mot de passe
                    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                    // 5. Mettre à jour le mot de passe dans la base de données
                    $stmt_update = $pdo->prepare("UPDATE Utilisateurs SET Mot_de_Passe = :new_password WHERE ID_Utilisateur = :id");
                    $stmt_update->bindParam(':new_password', $new_hashed_password, PDO::PARAM_STR);
                    $stmt_update->bindParam(':id', $utilisateur_id, PDO::PARAM_INT);

                    if ($stmt_update->execute()) {
                        $message_success = "Votre mot de passe a été modifié avec succès.";
                        // Optionnel: Déconnecter l'utilisateur pour qu'il se reconnecte avec le nouveau mot de passe
                        // session_unset();
                        // session_destroy();
                        // header('Location: ' . generateUrl('index.php?msg=mdp_modifie'));
                        // exit();
                    } else {
                        $message_erreur = "Erreur lors de la mise à jour du mot de passe.";
                        error_log("DB Error updating password for user ID " . $utilisateur_id . ": " . print_r($stmt_update->errorInfo(), true));
                    }
                } else {
                    $message_erreur = "Le mot de passe actuel est incorrect.";
                }
            }
        } catch (PDOException $e) {
            $message_erreur = "Erreur de base de données : " . $e->getMessage();
            error_log("PDO Exception in password change for user ID " . $utilisateur_id . ": " . $e->getMessage());
        } catch (Exception $e) {
            $message_erreur = "Une erreur inattendue est survenue : " . $e->getMessage();
            error_log("General Exception in password change for user ID " . $utilisateur_id . ": " . $e->getMessage());
        }
    }
}

// Inclure le header de la page (peut nécessiter un ajustement de chemin)
$titre = "Modifier Mot de Passe";
include_once __DIR__ . '/../../templates/header.php';
include_once __DIR__ . '/../../templates/navigation.php';
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-theme/0.1.0-beta.10/select2-bootstrap.min.css" rel="stylesheet">
	 <link rel="stylesheet" href="../../css/style.css">
	 <link rel="stylesheet" href="../../css/bootstrap.min.css">
	<link rel="stylesheet" href="../../css/tableau.css">
	 <link rel="stylesheet" href="../../css/select2.min.css">
	  <link rel="stylesheet" href="../../css/select2-bootstrap.min.css">
<div class="main-content">
    <div class="container">
	&nbsp;
        <h2>Modifier votre mot de passe</h2>

        <?php if ($message_success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($message_success); ?>
            </div>
        <?php endif; ?>

        <?php if ($message_erreur): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($message_erreur); ?>
            </div>
        <?php endif; ?>

        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="post">
            <div class="form-group">
                <label for="current_password">Mot de passe actuel :</label>
                <input type="password" id="current_password" name="current_password" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="new_password">Nouveau mot de passe :</label>
                <input type="password" id="new_password" name="new_password" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmer le nouveau mot de passe :</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary">
    <span class="glyphicon glyphicon-floppy-disk" aria-hidden="true"></span> Modifier le mot de passe
</button>
<a href="<?= generateUrl('pages/dashboard.php') ?>" class="btn btn-secondary">
    <span class="glyphicon glyphicon-remove" aria-hidden="true"></span> Annuler
</a>
<a href="javascript:history.back()" class="btn btn-info">
    <span class="glyphicon glyphicon-arrow-left" aria-hidden="true"></span> Retour
</a>
        </form>
    </div>
</div>
</body>
</html>
<?php

include_once __DIR__ . '/../../templates/footer.php';
?>