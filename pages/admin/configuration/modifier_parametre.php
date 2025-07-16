<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BailCompta 360 | Administration - Modifier le paramètre</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <!-- Utilisation de Bootstrap 3 comme demandé -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css" xintegrity="sha384-HSMxcRTRxnN+Bdg0JdbxYKrThecOKuH5zCYotlSAcp1+c8xmyTe9GYg1l9a69psu" crossorigin="anonymous">
    <!-- Vos fichiers CSS personnalisés -->
    <link rel="stylesheet" href="../../css/style.css">
    <?php if (isset($admin_style) && $admin_style): ?>
        <link rel="stylesheet" href="../../css/admin_style.css">
    <?php endif; ?>
    <style>
        /* Styles pour les messages d'alerte, adaptés à Bootstrap si nécessaire */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            color: #3c763d;
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }
        .alert-danger {
            color: #a94442;
            background-color: #f2dede;
            border-color: #ebccd1;
        }
    </style>
</head>
<body>
<?php
// pages/admin/configuration/modifier_parametre.php

// Démarrer la session pour la gestion de l'authentification
session_start();

// Initialiser les variables pour les messages de succès ou d'erreur
$message = '';
$messageType = ''; // Peut être 'success' ou 'danger'

// --- DEBUG: Afficher les données de session au début (à supprimer en production) ---
/*
echo '<pre style="background-color: #fdd; border: 1px solid #c99; padding: 10px; margin: 10px;">';
echo 'DEBUG - Session Data:<br>';
echo 'Session ID: ' . session_id() . '<br>';
echo 'utilisateur_id: ' . ($_SESSION['utilisateur_id'] ?? 'Non défini') . '<br>';
echo 'role: ' . ($_SESSION['role'] ?? 'Non défini') . '<br>';
echo '</pre>';
*/
// ----------------------------------------------------------------------------------

// Vérifier si l'utilisateur est connecté et s'il a le rôle d'administrateur
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Admin') {
    $message = "Accès non autorisé. Vous devez être connecté en tant qu'administrateur pour accéder à cette page.";
    $messageType = 'danger';
    // --- Décommenter la ligne ci-dessous en production pour la redirection ---
    // header("Location: ../../index.php?error=Accès non autorisé");
    // exit(); // Décommenter en production pour arrêter l'exécution après la redirection
}

$param = $_GET['param'] ?? '';
$section = '';
$nomParametre = '';
$valeurActuelle = '';

// Définir le chemin absolu vers le fichier config.ini
$configFile = __DIR__ . '/../../../fonctions/config/config.ini';
$config = [];

// Exécuter la logique seulement si aucune erreur d'authentification n'a été détectée
if ($messageType === '') {
    // Vérifier si le paramètre à modifier est spécifié dans l'URL (ex: ?param=app_name)
    if (empty($param)) { // Utiliser empty() pour vérifier si le paramètre est vide ou non défini
        $message = "Paramètre 'param' non spécifié dans l'URL.";
        $messageType = 'danger';
        // --- Décommenter la ligne ci-dessous en production pour la redirection ---
        // header("Location: index.php?error=Paramètre non spécifié");
        // exit(); // Décommenter en production
    } else {
        // Lire le fichier de configuration seulement si le paramètre est défini et qu'il n'y a pas d'erreur
        if (!file_exists($configFile)) {
            error_log("Fichier de configuration introuvable à: " . $configFile);
            $message = "Erreur: Fichier de configuration essentiel introuvable. Contactez l'administrateur.";
            $messageType = 'danger';
            // --- Décommenter la ligne ci-dessous en production pour la redirection ---
            // header("Location: index.php?error=" . urlencode("Erreur: Fichier de configuration essentiel introuvable. Contactez l'administrateur."));
            // exit(); // Décommenter en production
        } else {
            $config = parse_ini_file($configFile, true);
        }

        // Déterminer la section et le nom du paramètre à partir de $_GET['param']
        if ($messageType === '') { // Seulement si aucune erreur précédente
            switch ($param) {
                case 'app_name':
                    $section = 'app';
                    $nomParametre = 'Nom de l\'Application';
                    $valeurActuelle = $config['app']['name'] ?? '';
                    break;
                case 'db_host':
                    $section = 'database';
                    $nomParametre = 'Hôte de la Base de Données';
                    $valeurActuelle = $config['database']['host'] ?? '';
                    break;
                default:
                    $message = "Paramètre URL inconnu: " . htmlspecialchars($param);
                    $messageType = 'danger';
                    // --- Décommenter la ligne ci-dessous en production pour la redirection ---
                    // header("Location: index.php?error=Paramètre inconnu");
                    // exit(); // Décommenter en production
                    break;
            }
        }
    }
}

// Traitement de la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $messageType === '') { // Traiter seulement si la requête est POST et pas d'erreurs bloquantes
    $nouvelleValeur = $_POST['valeur'] ?? '';

    if (!isset($config[$section])) {
        $config[$section] = [];
    }

    // Utiliser directement $param comme clé pour la recherche dans le fichier INI
    // car c'est la clé réelle utilisée dans le fichier (ex: 'app_name', 'db_host')
    $paramKeyInIni = $param;

    $keyFound = false;
    // Vérifier si la clé spécifique existe dans la section déterminée
    if (isset($config[$section][$paramKeyInIni])) {
        $config[$section][$paramKeyInIni] = $nouvelleValeur;
        $keyFound = true;
    }

    if (!$keyFound) {
        $message = "Paramètre de configuration introuvable dans le fichier INI pour la mise à jour.";
        $messageType = 'danger';
        // --- Décommenter la ligne ci-dessous en production pour la redirection ---
        // header("Location: index.php?error=" . urlencode("Paramètre de configuration introuvable dans le fichier INI."));
        // exit(); // Décommenter en production
    } else {
        // Reconstruire le contenu complet du fichier INI pour l'écriture
        $newContent = '';
        foreach ($config as $sectionName => $sectionData) {
            $newContent .= "[" . $sectionName . "]\n";
            foreach ($sectionData as $key => $value) {
                // Échapper les guillemets doubles dans la valeur pour ne pas casser le format INI.
                $valueToWrite = str_replace('"', '\"', $value);
                // Ajouter des guillemets autour de la valeur si elle contient des espaces ou des caractères spéciaux.
                // C'est une bonne pratique pour les fichiers INI.
                if (strpos($valueToWrite, ' ') !== false || preg_match('/[^a-zA-Z0-9_.-]/', $valueToWrite)) {
                    $newContent .= $key . " = \"" . $valueToWrite . "\"\n";
                } else {
                    $newContent .= $key . " = " . $valueToWrite . "\n";
                }
            }
            $newContent .= "\n";
        }

        if (file_put_contents($configFile, $newContent)) {
            $message = htmlspecialchars($nomParametre) . " mis à jour avec succès.";
            $messageType = 'success';
            // --- Décommenter la ligne ci-dessous en production pour la redirection ---
            // header("Location: index.php?success=" . urlencode($nomParametre . " mis à jour avec succès"));
            // exit(); // Décommenter en production
        } else {
            $message = "Erreur lors de l'écriture dans le fichier de configuration. Vérifiez les permissions du fichier.";
            $messageType = 'danger';
            // --- Décommenter la ligne ci-dessous en production pour la redirection ---
            // header("Location: index.php?error=" . urlencode("Erreur lors de l'écriture dans le fichier de configuration. Vérifiez les permissions du fichier."));
            // exit(); // Décommenter en production
        }
    }
}

// Inclure l'en-tête de l'administration
// Assurez-vous que header.php ne contient pas les balises <html>, <head>, <body> complètes
$title = "Modifier un Paramètre"; // Variable utilisée par header.php
include(__DIR__ . '/../includes/header.php');

// Inclure la navigation de l'administration
// Assurez-vous que navigation.php ne contient pas les balises <html>, <head>, <body> complètes
include(__DIR__ . '/../includes/navigation.php');
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Modifier le Paramètre : <?php echo htmlspecialchars($nomParametre); ?></h1>
    </div>

    <?php if (!empty($message)): // Afficher le message s'il existe ?>
        <div class="alert alert-<?php echo $messageType; ?>" role="alert">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($messageType !== 'danger' || empty($message)): // Afficher le formulaire seulement s'il n'y a pas d'erreur bloquante ?>
        <div class="col-lg-6">
            <form method="post">
                <div class="form-group"> <!-- Utilisation de form-group pour Bootstrap 3 -->
                    <label for="valeur"><?php echo htmlspecialchars($nomParametre); ?></label>
                    <input type="text" class="form-control" id="valeur" name="valeur" value="<?php echo htmlspecialchars($valeurActuelle); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">Enregistrer la Modification</button>
                <a href="index.php" class="btn btn-default">Annuler</a> <!-- btn-default pour Bootstrap 3 -->
            </form>
        </div>
    <?php endif; ?>
</main>

<?php
// Inclure le pied de page
// Assurez-vous que footer.php ne contient pas les balises <html>, <head>, <body> complètes
include(__DIR__ . '/../includes/footer.php');
?>
</body>
</html>
