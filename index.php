<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Connexion</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/select2.min.css">
    <link rel="stylesheet" href="../css/select2-bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-theme/0.1.0-beta.10/select2-bootstrap.min.css" rel="stylesheet" />
    <link rel="icon" type="image/x-icon" href="images/LOGO_SCE.ico" title="Icône de l'application" sizes="16x16 32x32">
    <style>
        body {
            background-image: url('images/sces.jpg');
            background-repeat: no-repeat;
            background-size: 100% 80%;
            background-position: top center;
            background-attachment: fixed;
            backdrop-filter: blur(6px) grayscale(0.5) opacity(0.8);
            font-family: sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .login-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 400px;
            text-align: center;
        }

        header {
            background-color: #800020;
            color: #fff;
            padding: 15px 0;
            margin-bottom: 20px;
            border-radius: 6px 6px 0 0;
        }

        header h1 {
            margin: 0;
            font-size: 1.8em;
            font-weight: bold;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1em;
        }

        .btn-primary {
            background-color: #800020;
            color: #fff;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            width: 100%;
            transition: background-color 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #660018;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: #fff;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            width: 100%;
            margin-top: 10px;
            transition: background-color 0.3s ease;
        }

        .btn-secondary:hover {
            background-color: #545b62;
        }

        .btn-cancel {
            background-color: #dc3545;
            color: #fff;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            width: 100%;
            margin-top: 10px;
            transition: background-color 0.3s ease;
        }

        .btn-cancel:hover {
            background-color: #c82333;
        }

        .forgot-password {
            margin-top: 15px;
            font-size: 0.9em;
            color: #800020;
        }

        .forgot-password a {
            color: #800020;
            text-decoration: none;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            text-align: center;
        }

        .form-check {
            margin-bottom: 15px;
            text-align: left;
        }

        .form-check-input {
            margin-right: 0.5em;
        }

        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: #333;
            color: #f4f4f4;
            text-align: center;
            padding: 10px 0;
            font-size: 0.8em;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <header>
            <h1>BailCompta 360</h1>
            <p>Gestion Comptable Simplifiée</p>
        </header>

        <?php if (isset($_GET['error'])) { ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php } ?>

        <form action="pages/authentification.php" method="POST">
            <div class="form-group">
                <label for="login">Nom d'utilisateur</label>
                <input type="text" class="form-control" id="login" name="login" placeholder="Entrez votre nom d'utilisateur">
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <div style="position: relative;">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Entrez votre mot de passe">
                    <span
                        style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;"
                        onclick="togglePasswordVisibility()"
                    >
                        <span id="togglePassword" class="glyphicon glyphicon-eye-close"></span>
                    </span>
                </div>
            </div>

            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="rememberMe" name="rememberMe" onclick="showWarning()">
                <label class="form-check-label" for="rememberMe">Se souvenir de moi</label>
            </div>

            <button type="submit" class="btn btn-primary">
                <span class="glyphicon glyphicon-log-in"></span> Se connecter
            </button>

            <button type="submit" class="btn btn-secondary" name="guestLogin">
                <span class="glyphicon glyphicon-user"></span> Se connecter en tant qu'invité
            </button>

            <button type="button" class="btn btn-cancel" onclick="window.location.href='index.php'">
                <span class="glyphicon glyphicon-remove"></span> Annuler
            </button>

            <div class="forgot-password">
                <a href="forgot_password.php"><span class="glyphicon glyphicon-lock"></span> Mot de passe oublié ?</a>
            </div>
        </form>
    </div>

    <footer>
        BailCompta 360 - Tous droits réservés © <?= date('Y'); ?>
    </footer>

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css" integrity="sha384-HSMxcRTRxnN+Bdg0JdbxYKrThecOKuH5zCYotlSAcp1+c8xmyTe9GYg1l9a69psu" crossorigin="anonymous">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js" integrity="sha384-aJ21OjlMXNLJ5ywYOIDjxxyTwCypxSoOO3FxyYr4fccRoP1h0IWcAukj0jz9uNNs" crossorigin="anonymous"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/jquery-3.7.1.js"></script>
    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById("password");
            const toggleIcon = document.getElementById("togglePassword");
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                toggleIcon.classList.remove("glyphicon-eye-close");
                toggleIcon.classList.add("glyphicon-eye-open");
            } else {
                passwordInput.type = "password";
                toggleIcon.classList.remove("glyphicon-eye-open");
                toggleIcon.classList.add("glyphicon-eye-close");
            }
        }

        function showWarning() {
            alert("Attention ! En cochant cette case, vos informations de connexion seront stockées sur cet appareil. Cela peut poser un risque de sécurité. Assurez-vous de vous déconnecter correctement après chaque session.");
        }
    </script>
</body>
</html>
<?php
// Excerpt from your login processing logic (e.g., login.php or your authentication handler)

require_once 'fonctions/database.php';
require_once 'fonctions/gestion_logs.php'; // Or gestion_login_history.php


$username_attempted = $_POST['username'] ?? '';
$password_entered = $_POST['password'] ?? '';
$ip_address = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

$is_successful = false;
$failure_reason = null;
$user_id = null;

// --- Your existing authentication logic ---
// Example: Query database for user
// NOTE: Selecting plain-text passwords is a severe security risk.
// You MUST store and compare password hashes using password_hash() and password_verify().
$stmt = $pdo->prepare("SELECT ID_Utilisateur, Nom, Mot_de_passe, Role FROM Utilisateurs WHERE Nom = :username");
$stmt->bindParam(':username', $username_attempted, PDO::PARAM_STR);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // INSECURE: Comparing plain-text passwords
    if ($password_entered === $user['Mot_de_passe']) {
        // Login successful
        $_SESSION['user_id'] = $user['ID_Utilisateur'];
        $_SESSION['username'] = $user['Nom'];
        $_SESSION['user_role'] = $user['Role'];
        $is_successful = true;
        $user_id = $user['ID_Utilisateur'];
        header('Location: dashboard.php'); // Redirect to dashboard
        exit;
    } else {
        // Invalid password
        $failure_reason = 'Mot de passe invalide';
    }
} else {
    // User not found
    $failure_reason = 'Nom d\'utilisateur non trouvé';
}

// Record the login attempt after the authentication logic
recordLoginAttempt(
    $pdo,
    $username_attempted,
    $ip_address,
    $user_agent,
    $is_successful,
    $user_id,
    $failure_reason
);

?>