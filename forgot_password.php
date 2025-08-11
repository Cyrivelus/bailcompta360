<?php
session_start();

// Définir la langue par défaut
$lang = $_SESSION['lang'] ?? 'fr';

// Charger les traductions
$translations = [
    'fr' => [
        'title' => 'Réinitialisation du mot de passe',
        'info' => 'Procédure manuelle de réinitialisation',
        'instruction' => 'Pour réinitialiser votre mot de passe, veuillez suivre ces étapes :',
        'steps' => [
            'Contactez votre administrateur système',
            'Fournissez votre nom d\'utilisateur',
            'L\'administrateur vous fournira un nouveau mot de passe temporaire',
            'Connectez-vous et changez votre mot de passe immédiatement'
        ],
        'contact' => 'Contact administrateur :',
        'phone' => 'Téléphone : +XXX XX XXX XXX',
        'email' => 'Email : admin@votresociete.com',
        'back' => 'Retour à la connexion'
    ],
    'en' => [
        'title' => 'Password Reset',
        'info' => 'Manual reset procedure',
        'instruction' => 'To reset your password, please follow these steps:',
        'steps' => [
            'Contact your system administrator',
            'Provide your username',
            'The administrator will give you a temporary password',
            'Log in and change your password immediately'
        ],
        'contact' => 'Admin contact:',
        'phone' => 'Phone: +XXX XX XXX XXX',
        'email' => 'Email: admin@yourcompany.com',
        'back' => 'Back to login'
    ]
];

$text = $translations[$lang] ?? $translations['fr'];
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <title><?= $text['title'] ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }
        .panel-custom {
            border-color: #800020;
            margin-top: 50px;
        }
        .panel-custom > .panel-heading {
            background-color: #800020;
            color: white;
            border-color: #800020;
        }
        .btn-custom {
            background-color: #800020;
            color: white;
            border: none;
        }
        .btn-custom:hover {
            background-color: #6a001b;
            color: white;
        }
        .step-list {
            margin: 20px 0;
            padding-left: 20px;
        }
        .step-list li {
            margin-bottom: 10px;
        }
        .contact-info {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-md-6 col-md-offset-3">
                <div class="panel panel-default panel-custom">
                    <div class="panel-heading">
                        <h3 class="panel-title"><?= $text['title'] ?></h3>
                    </div>
                    <div class="panel-body">
                        <div class="alert alert-info">
                            <strong><?= $text['info'] ?></strong>
                        </div>
                        
                        <p><?= $text['instruction'] ?></p>
                        
                        <ol class="step-list">
                            <?php foreach ($text['steps'] as $step): ?>
                                <li><?= $step ?></li>
                            <?php endforeach; ?>
                        </ol>
                        
                        <div class="contact-info">
                            <h4><?= $text['contact'] ?></h4>
                            <p><i class="glyphicon glyphicon-phone"></i> <?= $text['phone'] ?></p>
                            <p><i class="glyphicon glyphicon-envelope"></i> <?= $text['email'] ?></p>
                        </div>
                        
                        <div class="text-center" style="margin-top: 20px;">
                            <a href="index.php" class="btn btn-custom">
                                <span class="glyphicon glyphicon-arrow-left"></span> <?= $text['back'] ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>
</html>