<?php
session_start();

// Définir la langue par défaut
$lang = $_SESSION['lang'] ?? 'fr';

// Changer de langue si un paramètre est fourni dans l'URL
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'])) {
    $lang = $_GET['lang'];
    $_SESSION['lang'] = $lang;
    // Rediriger pour éviter la duplication du paramètre lang dans l'URL
    header('Location: '.str_replace('?lang='.$_GET['lang'], '', $_SERVER['REQUEST_URI']));
    exit;
}

// Charger les textes traduits
$translations = [
    'fr' => [
        'app_name' => 'BailCompta 360',
        'app_tagline' => 'Gestion Comptable Simplifiée',
        'username_label' => 'Nom d\'utilisateur',
        'username_placeholder' => 'Entrez votre nom d\'utilisateur',
        'password_label' => 'Mot de passe',
        'password_placeholder' => 'Entrez votre mot de passe',
        'remember_me' => 'Se souvenir de moi',
        'login_button' => 'Se connecter',
        'guest_login_button' => 'Se connecter en tant qu\'invité',
        'cancel_button' => 'Annuler',
        'forgot_password' => 'Mot de passe oublié ?',
        'error_invalid_password' => 'Mot de passe invalide',
        'error_user_not_found' => 'Nom d\'utilisateur non trouvé',
        'footer_text' => 'BailCompta 360 - Tous droits réservés ©',
        'language_label' => 'Langue',
    ],
    'en' => [
        'app_name' => 'BailCompta 360',
        'app_tagline' => 'Simplified Accounting Management',
        'username_label' => 'Username',
        'username_placeholder' => 'Enter your username',
        'password_label' => 'Password',
        'password_placeholder' => 'Enter your password',
        'remember_me' => 'Remember me',
        'login_button' => 'Log In',
        'guest_login_button' => 'Log in as Guest',
        'cancel_button' => 'Cancel',
        'forgot_password' => 'Forgot password?',
        'error_invalid_password' => 'Invalid password',
        'error_user_not_found' => 'Username not found',
        'footer_text' => 'BailCompta 360 - All rights reserved ©',
        'language_label' => 'Language',
    ],
];

// Récupérer les textes dans la langue courante
$text = $translations[$lang];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <title><?= $text['app_name'] ?> | <?= $lang === 'fr' ? 'Connexion' : 'Login' ?></title>
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
        .language-switcher {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        .language-switcher .dropdown-menu {
            right: 0;
            left: auto;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <header>
            <h1><?= $text['app_name'] ?></h1>
            <p><?= $text['app_tagline'] ?></p>
        </header>

        <?php if (isset($_GET['error'])) { ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php } ?>

        <form action="pages/authentification.php" method="POST">
            <div class="form-group">
                <label for="login"><?= $text['username_label'] ?></label>
                <input type="text" class="form-control" id="login" name="login" placeholder="<?= $text['username_placeholder'] ?>">
            </div>

            <div class="form-group">
                <label for="password"><?= $text['password_label'] ?></label>
                <div style="position: relative;">
                    <input type="password" class="form-control" id="password" name="password" placeholder="<?= $text['password_placeholder'] ?>">
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
                <label class="form-check-label" for="rememberMe"><?= $text['remember_me'] ?></label>
            </div>

            <button type="submit" class="btn btn-primary">
                <span class="glyphicon glyphicon-log-in"></span> <?= $text['login_button'] ?>
            </button>

            <button type="submit" class="btn btn-secondary" name="guestLogin">
                <span class="glyphicon glyphicon-user"></span> <?= $text['guest_login_button'] ?>
            </button>

            <button type="button" class="btn btn-cancel" onclick="window.location.href='index.php'">
                <span class="glyphicon glyphicon-remove"></span> <?= $text['cancel_button'] ?>
            </button>

            <div class="forgot-password">
                <a href="forgot_password.php"><span class="glyphicon glyphicon-lock"></span> <?= $text['forgot_password'] ?></a>
            </div>
        </form>
    </div>

  <div class="language-switcher">
    <a class="nav-link dropdown-toggle <?= strpos($_SERVER['REQUEST_URI'], '?lang=') !== false ? 'active' : '' ?>"
        href="#"
        id="languageDropdown"
        role="button"
        data-toggle="dropdown"
        aria-haspopup="true"
        aria-expanded="false"
        style="
            font-weight: bold; 
            color: blue; 
            border: 2px solid maroon; 
            background-color: rgba(255, 255, 255, 0.85);
            padding: 5px 10px; 
            border-radius: 5px;
        ">
        <span class="glyphicon glyphicon-globe" style="margin-right:5px;"></span>
        Langue
    </a>
    <div class="dropdown-menu" aria-labelledby="languageDropdown" style="background-color: rgba(255, 255, 255, 0.95); border: 1px solid maroon;">
        <a class="dropdown-item" href="?lang=fr" style="font-weight: bold; color: blue;">
            <img src="https://flagcdn.com/w20/fr.png" alt="Français" style="width:20px; height:auto; margin-right:5px;">
            Français
        </a>
        <br>
        <a class="dropdown-item" href="?lang=en" style="font-weight: bold; color: blue;">
            <img src="https://flagcdn.com/w20/gb.png" alt="English" style="width:20px; height:auto; margin-right:5px;">
            English
        </a>
    </div>
</div>

        </li>
    </div>

    <footer>
        <?= $text['footer_text'] ?> <?= date('Y'); ?>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
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
            alert("<?= $lang === 'fr' ? 'Attention ! En cochant cette case, vos informations de connexion seront stockées sur cet appareil. Cela peut poser un risque de sécurité.' : 'Warning! Checking this box will store your login information on this device. This may pose a security risk.' ?>");
        }
    </script>
</body>
</html>