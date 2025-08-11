<?php
session_start();

// Inclure la base de données et les fonctions de gestion des utilisateurs
require_once __DIR__ . "/../../fonctions/database.php";
require_once __DIR__ . "/../../fonctions/gestion_utilisateurs.php";

// Si l'utilisateur n'est pas connecté ou qu'il n'est pas en train de changer de mot de passe, le rediriger
if (!isset($_SESSION['utilisateur_id']) || (!isset($_SESSION['is_temp_password']) && !isset($_SESSION['admin_message_warning']))) {
    // Redirection si l'accès n'est pas autorisé
    header("Location: ../../index.php");
    exit();
}

$message = $_SESSION['admin_message_warning'] ?? '';
unset($_SESSION['admin_message_warning']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($newPassword) || empty($confirmPassword)) {
        $error = "Veuillez remplir tous les champs.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($newPassword) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères.";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        try {
            $sql = "UPDATE Utilisateurs SET Mot_de_Passe = :password WHERE ID_Utilisateur = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':id', $_SESSION['utilisateur_id'], PDO::PARAM_INT);
            $stmt->execute();
            
            // Réinitialiser les indicateurs de changement de mot de passe
            unset($_SESSION['is_temp_password']);

            // Redirection vers le tableau de bord avec un message de succès
            $_SESSION['success_message'] = "Votre mot de passe a été mis à jour avec succès.";
            header("Location: ../dashboard.php");
            exit();

        } catch (PDOException $e) {
            $error = "Une erreur est survenue lors de la mise à jour du mot de passe. Veuillez réessayer.";
            error_log("Erreur PDO lors du changement de mot de passe : " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Changer le mot de passe</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        .container {
            width: 400px;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Changement de mot de passe</h2>
        <div class="alert alert-warning">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?= $error ?>
            </div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="new_password">Nouveau mot de passe</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Changer le mot de passe</button>
        </form>
    </div>
</body>
</html>