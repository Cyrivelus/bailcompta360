<?php
// pages/changement_mot_de_passe.php

session_start();

// --- 1. Vérification d'Accès ---
// L'utilisateur doit être connecté pour changer son mot de passe.
// Si l'utilisateur n'est pas connecté, redirigez-le vers la page de connexion.
if (!isset($_SESSION['utilisateur_id'])) {
    header("Location: ../index.php?error=" . urlencode("Veuillez vous connecter pour accéder à cette page."));
    exit();
}

// Inclure le fichier de connexion à la base de données
require_once __DIR__ . "/../fonctions/database.php"; // Chemin ajusté avec __DIR__

// Vérifier la connexion PDO
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("Erreur critique: La connexion PDO n'est pas disponible dans changement_mot_de_passe.php.");
    $_SESSION['admin_message_error'] = "Une erreur interne est survenue. Veuillez réessayer plus tard.";
    header("Location: ../index.php"); // Rediriger vers l'accueil ou la page de login
    exit();
}

$message_success = $_SESSION['admin_message_success'] ?? '';
$message_error = $_SESSION['admin_message_error'] ?? '';
$message_warning = $_SESSION['admin_message_warning'] ?? '';

// Nettoyer les messages après affichage
unset($_SESSION['admin_message_success']);
unset($_SESSION['admin_message_error']);
unset($_SESSION['admin_message_warning']);

// --- 2. Traitement du Formulaire de Changement de Mot de Passe ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $utilisateur_id = $_SESSION['utilisateur_id'];

    // Récupérer le hachage du mot de passe actuel de l'utilisateur depuis la base de données
    $sql_get_password = "SELECT Mot_de_Passe FROM Utilisateurs WHERE ID_Utilisateur = :id";
    try {
        $stmt_get_password = $pdo->prepare($sql_get_password);
        $stmt_get_password->bindParam(':id', $utilisateur_id, PDO::PARAM_INT);
        $stmt_get_password->execute();
        $user_data = $stmt_get_password->fetch(PDO::FETCH_ASSOC);

        if (!$user_data) {
            $_SESSION['admin_message_error'] = "Utilisateur non trouvé ou problème de session.";
            header("Location: ../index.php");
            exit();
        }

        $stored_hashed_password = $user_data['Mot_de_Passe'];

        // Validation 1: Vérifier le mot de passe actuel
        // Utilisez password_verify() pour comparer le mot de passe actuel saisi par l'utilisateur
        // avec le hachage stocké dans la base de données.
        if (!password_verify($current_password, $stored_hashed_password)) {
            $message_error = "Le mot de passe actuel est incorrect.";
        }
        // Validation 2: Les nouveaux mots de passe doivent correspondre
        elseif ($new_password !== $confirm_password) {
            $message_error = "Le nouveau mot de passe et sa confirmation ne correspondent pas.";
        }
        // Validation 3: Le nouveau mot de passe doit être différent de l'ancien
        elseif (password_verify($new_password, $stored_hashed_password)) {
            $message_error = "Le nouveau mot de passe doit être différent de l'ancien.";
        }
        // Validation 4: Longueur minimale du nouveau mot de passe (vous pouvez ajouter plus de règles)
        elseif (strlen($new_password) < 8) { // Exemple: minimum 8 caractères
            $message_error = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
        }
        else {
            // Tous les contrôles sont passés, hacher le nouveau mot de passe
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            if ($new_hashed_password === false) {
                $message_error = "Une erreur est survenue lors du hachage du nouveau mot de passe.";
            } else {
                // Mettre à jour le mot de passe et la date de dernière connexion dans la base de données
                $sql_update_password = "UPDATE Utilisateurs
                                        SET Mot_de_Passe = :new_password, Derniere_Connexion = GETDATE()
                                        WHERE ID_Utilisateur = :id";
                try {
                    $stmt_update_password = $pdo->prepare($sql_update_password);
                    $stmt_update_password->bindParam(':new_password', $new_hashed_password);
                    $stmt_update_password->bindParam(':id', $utilisateur_id, PDO::PARAM_INT);
                    $stmt_update_password->execute();

                    $message_success = "Votre mot de passe a été changé avec succès.";
                    // Mettez à jour la session pour la nouvelle date de dernière connexion
                    $_SESSION['derniere_connexion'] = date('Y-m-d H:i:s'); // Mise à jour pour éviter une nouvelle redirection immédiate
                    
                    // Rediriger l'utilisateur vers son tableau de bord après un changement réussi
                    // Vous pouvez adapter la redirection selon le rôle de l'utilisateur
                    switch ($_SESSION['role']) {
                        case 'Admin':
                            header("Location: ./utilisateurs/index.php");
                            break;
                        case 'Comptable':
                            header("Location: ./ecritures/index.php");
                            break;
                        case 'super_admin':
                            header("Location: ./admin/data_management/index.php");
                            break;
                        default:
                            header("Location: ./accueil_invite.php");
                            break;
                    }
                    exit();

                } catch (PDOException $e) {
                    error_log("Erreur PDO lors de la mise à jour du mot de passe : " . $e->getMessage());
                    $message_error = "Erreur lors de la mise à jour du mot de passe. Veuillez réessayer.";
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération du mot de passe : " . $e->getMessage());
        $message_error = "Erreur interne lors de la vérification du mot de passe actuel.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changer le Mot de Passe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="../css/style.css"> </head>
<body class="d-flex flex-column min-vh-100">

    <header class="bg-primary text-white text-center py-3">
        <h1>Changer Votre Mot de Passe</h1>
    </header>

    <main class="container my-5 flex-grow-1">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card p-4 shadow-sm">
                    <?php if ($message_success): ?>
                        <div class="alert alert-success" role="alert">
                            <?= htmlspecialchars($message_success) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($message_error): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= htmlspecialchars($message_error) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($message_warning): ?>
                        <div class="alert alert-warning" role="alert">
                            <?= htmlspecialchars($message_warning) ?>
                        </div>
                    <?php endif; ?>

                    <form action="changement_mot_de_passe.php" method="POST">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mot de passe actuel</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Nouveau mot de passe</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Changer le mot de passe</button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="
                            <?php
                            // Rediriger vers la page appropriée après le changement de mot de passe (ou annuler)
                            switch ($_SESSION['role'] ?? 'default') {
                                case 'Admin': echo './utilisateurs/index.php'; break;
                                case 'Comptable': echo './ecritures/index.php'; break;
                                case 'super_admin': echo './admin/data_management/index.php'; break;
                                default: echo './accueil_invite.php'; break;
                            }
                            ?>
                        " class="btn btn-secondary mt-2">Annuler et Retour</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include_once '../templates/footer.php'; // Inclure votre footer si vous en avez un ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>