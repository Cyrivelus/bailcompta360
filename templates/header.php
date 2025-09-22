<?php
// templates/header.php

// Démarrer la session si ce n'est pas déjà fait
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté et définir le nom
$estConnecte = isset($_SESSION['utilisateur_id']);
$nomUtilisateur = $estConnecte && isset($_SESSION['nom_utilisateur']) ? $_SESSION['nom_utilisateur'] : 'Invité';

// Définir le titre de la page
$titrePage = isset($titre) ? $titre : 'BailCompta 360';

// Couleurs principales
$couleurPrincipale = '#6d071a';
$couleurTextePrimaire = '#ffffff';

// Récupération et formatage de la dernière activité
$derniereActivite = $_SESSION['LAST_ACTIVITY'] ?? null;
$formatDerniereActivite = '';

if ($estConnecte && $derniereActivite) {
    $formatDerniereActivite = ' ' . date('d/m/Y H:i:s', $derniereActivite);
} elseif (!$estConnecte) {
    $formatDerniereActivite = 'Dernière connexion : ' . date('d/m/Y H:i:s');
}

// Déconnexion
if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header('location: /bailcompta360/index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titrePage) ?></title>

    <link rel="stylesheet" href="/bailcompta360/css/style.css">
    <link rel="stylesheet" href="/bailcompta360/css/formulaire.css">
    <link rel="stylesheet" href="/bailcompta360/css/tableau.css">
    <link rel="stylesheet" href="/bailcompta360/css/bootstrap.min.css">
    <link rel="stylesheet" href="/bailcompta360/css/loading.css">
    <link rel="shortcut icon" href="/bailcompta360/images/LOGO_SCE.ico" type="image/x-icon">
    
    <script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>

    <style>
        body {
            font-family: 'Times New Roman', sans-serif;
            background-color: #f5f5f5;
            padding-top: 80px;
        }

        /* Overlay de chargement */
        #loading-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease;
        }

        .spinner {
            border: 8px solid #f3f3f3;
            border-top: 8px solid <?= $couleurPrincipale ?>;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Header */
        header {
            background-color: <?= $couleurPrincipale ?>;
            color: <?= $couleurTextePrimaire ?>;
            padding: 5px 20px;
            position: fixed;
            top: 0; left: 215px; right: 0;
            z-index: 1030;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .logo-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            text-align: center;
            background-color: rgba(255,255,255,0.2);
            padding: 10px 15px;
            border-radius: 8px;
        }

        .logo h1 {
            margin: 0;
            font-size: 1.5em;
            font-family: 'Arial Black', Gadget, sans-serif;
            font-weight: bold;
            color: white;
        }

        .logo span {
            color: #B22222;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            color: white;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .user-name {
            font-weight: bold;
            font-size: 1.1em;
        }

        .last-activity {
            font-size: 0.8em;
            opacity: 0.8;
        }

        .logout-btn {
            background-color: transparent;
            border: 1px solid white;
            color: white;
            padding: 5px 15px;
            border-radius: 4px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            margin-top: 5px;
        }

        .logout-btn:hover {
            background-color: white;
            color: <?= $couleurPrincipale ?>;
        }

        /* Style for the React Clock */
        #react-clock-container {
            font-size: 0.8em;
            opacity: 0.8;
            margin-top: 5px;
            text-align: right;
            color: white;
        }
    </style>
</head>

<body>

<div id="loading-overlay">
    <div class="spinner"></div>
</div>

<header>
    <div class="logo-container">
        <div class="logo">
            <h1>Bail<span>Compta</span> 360</h1>
        </div>

        <div class="user-info">
            <div class="user-details">
                <?php if ($estConnecte): ?>
                    <span class="user-name"><span class="glyphicon glyphicon-user" aria-hidden="true"></span> <?= htmlspecialchars($nomUtilisateur) ?></span>
                <?php else: ?>
                    <span class="user-name"><span class="glyphicon glyphicon-eye-open" aria-hidden="true"></span> <?= htmlspecialchars($nomUtilisateur) ?></span>
                <?php endif; ?>
                
                <?php if (!empty($formatDerniereActivite)): ?>
                    <div class="last-activity">
                        <span class="glyphicon glyphicon-time" aria-hidden="true"></span>
                        Dernière activité : <?= htmlspecialchars($formatDerniereActivite) ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($estConnecte): ?>
                <div class="mt-2" style="display: flex; align-items: center; gap: 0.5rem;">
                    <a href="/bailcompta360/pages/utilisateurs/modifier_mot_de_passe.php" class="btn btn-sm btn-warning">
                        <span class="glyphicon glyphicon-edit" aria-hidden="true"></span> Modifier mot de passe
                    </a>
                    <form method="post" action="" style="margin:0;">
                        <button type="submit" name="logout" class="btn btn-sm btn-danger">
                            <span class="glyphicon glyphicon-log-out" aria-hidden="true"></span> Déconnexion
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <a href="/bailcompta360/index.php" class="logout-btn mt-2">
                    <span class="glyphicon glyphicon-log-in" aria-hidden="true"></span> Connexion
                </a>
            <?php endif; ?>

            <div id="react-clock-container"></div>
        </div>
    </div>
</header>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>

<script type="text/babel">
    // React Clock Component
    function Clock() {
        const [time, setTime] = React.useState(new Date());

        React.useEffect(() => {
            const timerId = setInterval(() => {
                setTime(new Date());
            }, 1000);

            // Cleanup function to clear the interval when the component unmounts
            return () => clearInterval(timerId);
        }, []);

        const formatTime = (date) => {
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');
            return `${hours}:${minutes}:${seconds}`;
        };

        return (
            <div>
                {formatTime(time)}
            </div>
        );
    }

    // Mount the React Clock component to the specified container
    const clockContainer = document.getElementById('react-clock-container');
    if (clockContainer) {
        ReactDOM.render(<Clock />, clockContainer);
    }
</script>

<script>
    // Fades out the loading overlay when the page is fully loaded
    window.addEventListener('load', function () {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.style.opacity = '0';
            setTimeout(() => {
                overlay.style.display = 'none';
            }, 500);
        }
    });
</script>
</body>
</html>