<?php
// authentification.php

// --- 1. Initialisation et Configuration ---

// Activer l'affichage des erreurs pour le débogage (à désactiver en production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration des logs d'erreurs
ini_set('log_errors', 1);
// Assurez-vous que ce chemin est accessible en écriture par le serveur web
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Inclure le fichier de connexion à la base de données
require_once __DIR__ . "/../fonctions/database.php"; // Chemin ajusté avec __DIR__
// Inclure le fichier de gestion des utilisateurs où se trouve updateDerniereConnexion
require_once __DIR__ . "/../fonctions/gestion_utilisateurs.php";



// Configuration des paramètres de session pour la sécurité
session_set_cookie_params([
    'lifetime' => 0, // Durée de vie du cookie de session (0 signifie jusqu'à la fermeture du navigateur)
    'path' => '/', // Le cookie est disponible sur tout le site
    'domain' => '', // Laissez vide pour le domaine actuel
    'httponly' => true, // Empêche l'accès au cookie via JavaScript (XSS)
    'secure' => isset($_SERVER['HTTPS']), // N'active 'secure' que si HTTPS est utilisé
    'samesite' => 'Lax', // 'Lax' ou 'Strict'. 'Lax' est souvent un bon compromis pour les redirections
]);
session_start();

// --- 2. Variables et Fonctions Utilitaires ---

$maxLoginAttempts = 3; // Nombre maximal de tentatives avant blocage
$lockoutTimeMinutes = 15; // Temps de blocage en minutes
$passwordExpiryMonths = 3; // Période d'expiration du mot de passe en mois

// Récupérer les données soumises via POST
$login = filter_input(INPUT_POST, 'login', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$password = $_POST['password'] ?? ''; // Le mot de passe brut n'est pas assaini ici pour password_verify()
$rememberMe = isset($_POST['rememberMe']);
$guestLoginAttempt = isset($_POST['guestLogin']); // Vérifier si le bouton invité a été cliqué

/**
 * Fonction pour authentifier un utilisateur.
 * Gère également le comptage des tentatives de connexion et le blocage par compte.
 * Utilise password_verify() pour les mots de passe hachés.
 *
 * @param PDO $pdo Objet PDO de connexion à la base de données.
 * @param string $login Le login de l'utilisateur.
 * @param string $password Le mot de passe en clair (pour password_verify).
 * @param int $maxAttempts Nombre maximal de tentatives avant blocage.
 * @param int $lockoutMins Durée du blocage en minutes.
 * @param bool $isGuest Indique si c'est une tentative de connexion invité.
 * @return array|string Retourne les données de l'utilisateur si l'authentification réussit,
 * sinon une chaîne de caractères contenant le message d'erreur.
 */
function authentifier(PDO $pdo, string $login, string $password, int $maxAttempts, int $lockoutMins, bool $isGuest = false): array|string
{
    $sql = "SELECT ID_Utilisateur, Nom, Login_Utilisateur, Mot_de_Passe, Role, Derniere_Connexion, login_attempts, lockout_until
            FROM Utilisateurs
            WHERE Login_Utilisateur = :login";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':login', $login);
        $stmt->execute();
        $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$utilisateur) {
            // Pour des raisons de sécurité, ne pas indiquer si l'utilisateur existe ou non.
            // Traiter comme un échec de mot de passe incorrect.
            error_log("Tentative de connexion échouée pour l'utilisateur : " . $login . " (Utilisateur non trouvé ou mot de passe incorrect)");
            return "Nom d'utilisateur ou mot de passe incorrect.";
        }

        // Vérifier si le compte est actuellement bloqué
        $currentLockoutTime = strtotime($utilisateur['lockout_until'] ?? '1970-01-01 00:00:00'); // Default to past if NULL
        if ($utilisateur['login_attempts'] >= $maxAttempts && time() < $currentLockoutTime) {
            $remainingTime = $currentLockoutTime - time();
            $remainingMinutes = ceil($remainingTime / 60);
            return "Compte temporairement bloqué. Veuillez réessayer dans " . $remainingMinutes . " minute" . ($remainingMinutes > 1 ? 's' : '') . ".";
        }

        $passwordCorrect = false;
        if ($isGuest) {
            // WARNING: Storing plain text passwords for guests is a security risk.
            // Consider hashing guest passwords too, or using a fixed guest password that is hashed.
            $passwordCorrect = ($password === $utilisateur['Mot_de_Passe']);
        } else {
            $passwordCorrect = password_verify($password, $utilisateur['Mot_de_Passe']);
        }

        if ($passwordCorrect) {
            // Connexion réussie : Réinitialiser les tentatives et le blocage dans la DB
            $sql_reset_attempts = "UPDATE Utilisateurs SET login_attempts = 0, lockout_until = NULL WHERE ID_Utilisateur = :id";
            $stmt_reset = $pdo->prepare($sql_reset_attempts);
            $stmt_reset->bindParam(':id', $utilisateur['ID_Utilisateur'], PDO::PARAM_INT);
            $stmt_reset->execute();

            return $utilisateur;
        } else {
            // Mot de passe incorrect : Incrémenter les tentatives ou bloquer
            $newAttempts = $utilisateur['login_attempts'] + 1;
            $newLockoutUntil = NULL;

            if ($newAttempts >= $maxAttempts) {
                $newLockoutUntil = date('Y-m-d H:i:s', time() + ($lockoutMins * 60));
                error_log("Compte bloqué pour l'utilisateur : " . $login . " jusqu'à " . $newLockoutUntil);
            }

            $sql_update_attempts = "UPDATE Utilisateurs SET login_attempts = :attempts, lockout_until = :lockout WHERE ID_Utilisateur = :id";
            $stmt_update = $pdo->prepare($sql_update_attempts);
            $stmt_update->bindParam(':attempts', $newAttempts, PDO::PARAM_INT);
            $stmt_update->bindParam(':lockout', $newLockoutUntil);
            $stmt_update->bindParam(':id', $utilisateur['ID_Utilisateur'], PDO::PARAM_INT);
            $stmt_update->execute();

            if ($newLockoutUntil !== NULL) {
                $remainingTime = strtotime($newLockoutUntil) - time();
                $remainingMinutes = ceil($remainingTime / 60);
                return "Trop de tentatives de connexion. Votre compte est temporairement bloqué pendant " . $remainingMinutes . " minute" . ($remainingMinutes > 1 ? 's' : '') . ".";
            } else {
                return "Nom d'utilisateur ou mot de passe incorrect.";
            }
        }

    } catch (PDOException $e) {
        error_log("Erreur PDO lors de l'authentification : " . $e->getMessage());
        return "Une erreur de base de données est survenue. Veuillez réessayer plus tard.";
    }
}

/**
 * Vérifie et restaure la session d'un utilisateur via un cookie "se souvenir de moi".
 *
 * @param PDO $pdo Objet PDO de connexion à la base de données.
 * @param int $utilisateurId L'ID de l'utilisateur.
 * @param string $token Le token en clair du cookie.
 * @return void
 */
function restaurerSessionViaCookie(PDO $pdo, int $utilisateurId, string $token): void
{
    $sql = "SELECT ID_Utilisateur, Nom, Login_Utilisateur, Role, remember_token, remember_expiry, login_attempts, lockout_until
            FROM Utilisateurs
            WHERE ID_Utilisateur = :utilisateur_id AND remember_token IS NOT NULL AND remember_expiry > NOW()";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':utilisateur_id', $utilisateurId, PDO::PARAM_INT);
        $stmt->execute();
        $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($utilisateur && password_verify($token, $utilisateur['remember_token'])) {
            // Vérifier aussi que le compte n'est pas bloqué par tentatives échouées
            $maxLoginAttempts = 3; // Réutiliser la variable globale ou passer en paramètre
            $currentLockoutTime = strtotime($utilisateur['lockout_until'] ?? '1970-01-01 00:00:00');

            if ($utilisateur['login_attempts'] >= $maxLoginAttempts && time() < $currentLockoutTime) {
                // Compte bloqué, ne pas restaurer la session via cookie
                error_log("Tentative de restauration de session via cookie échouée pour ID : " . $utilisateurId . " (Compte bloqué)");
                setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
                setcookie('remember_id', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
                return;
            }

            $_SESSION['utilisateur_id'] = $utilisateur['ID_Utilisateur'];
            $_SESSION['nom_utilisateur'] = $utilisateur['Nom'];
            $_SESSION['role'] = $utilisateur['Role'];
            $_SESSION['last_activity'] = time();

            // *** APPEL DE updateDerniereConnexion ICI ***
            updateDerniereConnexion($pdo, $_SESSION['utilisateur_id']);
            // *****************************************

        } else {
            // Le cookie est invalide ou expiré, le supprimer
            setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
            setcookie('remember_id', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
            error_log("Cookie remember me invalide ou expiré pour ID : " . $utilisateurId);
        }
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la restauration de session via cookie : " . $e->getMessage());
        // En cas d'erreur BD, supprimez les cookies pour éviter de boucler sur un problème
        setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
        setcookie('remember_id', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    }
}

/**
 * Vérifie si le mot de passe de l'utilisateur a expiré.
 *
 * @param DateTime|string $lastLoginDate La date de dernière connexion de l'utilisateur.
 * @param int $expiryMonths Le nombre de mois après lequel le mot de passe expire.
 * @return bool Vrai si le mot de passe a expiré, faux sinon.
 */
function checkPasswordExpiry(DateTime|string $lastLoginDate, int $expiryMonths): bool
{
    // Convertir la date de dernière connexion en objet DateTime si ce n'est pas déjà le cas
    if (is_string($lastLoginDate)) {
        try {
            $lastLoginDate = new DateTime($lastLoginDate);
        } catch (Exception $e) {
            error_log("Erreur de conversion de date: " . $e->getMessage());
            return false; // Ou gérer l'erreur comme vous le souhaitez
        }
    }

    $expiryDate = clone $lastLoginDate;
    $expiryDate->modify("+$expiryMonths months");
    $now = new DateTime();

    return $now > $expiryDate;
}


// --- 3. Traitement des Cookies "Se souvenir de moi" (Déplacé avant la soumission du formulaire) ---

// Vérification de la présence des cookies "remember_me" si aucune session active
if (isset($_COOKIE['remember_token']) && isset($_COOKIE['remember_id']) && !isset($_SESSION['utilisateur_id'])) {
    restaurerSessionViaCookie($pdo, (int)$_COOKIE['remember_id'], $_COOKIE['remember_token']);
    // Si la session a été restaurée, rediriger pour éviter que l'utilisateur ne soumette le formulaire
    if (isset($_SESSION['utilisateur_id'])) {
        // Redirection après connexion via cookie
        switch ($_SESSION['role']) {
            case 'Admin':
                header("Location: ../pages/utilisateurs/index.php");
                break;
            case 'Comptable':
                header("Location: ../pages/ecritures/index.php");
                break;
            case 'super_admin':
                header("Location: ../pages/admin/data_management/index.php");
                break;
            default: // Rôle 'Invité' ou autre
                header("Location: ../pages/accueil_invite.php");
                break;
        }
        exit();
    }
}

// --- 4. Vérification des Horaires de Connexion (Optionnel) ---
// À décommenter et adapter si cette fonctionnalité est requise.
/*
$currentHour = date('H:i');
$forbiddenStart = '22:00';
$forbiddenEnd = '07:30';

if ($currentHour >= $forbiddenStart || $currentHour < $forbiddenEnd) {
    header("Location: ../index.php?error=" . urlencode("Connexion non autorisée en dehors des heures de bureau."));
    exit();
}
*/

// --- 5. Traitement de la Soumission du Formulaire ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($login) && !empty($password)) {
    // La fonction authentifier gère maintenant le blocage et retourne soit l'utilisateur, soit un message d'erreur.
    $authResult = authentifier($pdo, $login, $password, $maxLoginAttempts, $lockoutTimeMinutes, $guestLoginAttempt);

    if (is_array($authResult)) { // Authentification réussie
        $utilisateur = $authResult;

        // Définir les variables de session
        $_SESSION['utilisateur_id'] = $utilisateur['ID_Utilisateur'];
        $_SESSION['nom_utilisateur'] = $utilisateur['Nom'];
        $_SESSION['role'] = $utilisateur['Role'];
        // Correction ici : Fournir une valeur par défaut si Derniere_Connexion est NULL
        $_SESSION['derniere_connexion'] = $utilisateur['Derniere_Connexion'] ?? date('Y-m-d H:i:s');
        $_SESSION['last_activity'] = time(); // Pour le timeout de session

        // *** APPEL DE updateDerniereConnexion APRÈS UNE CONNEXION FORMULAIRE RÉUSSIE ***
        updateDerniereConnexion($pdo, $_SESSION['utilisateur_id']);
        // *************************************************************************

        // --- Vérification de l'expiration du mot de passe ---
        if ($utilisateur['Role'] !== 'Invité' && checkPasswordExpiry($_SESSION['derniere_connexion'], $passwordExpiryMonths)) {
            $_SESSION['admin_message_warning'] = "Votre mot de passe a expiré. Veuillez le changer.";
            header("Location: ../pages/changement_mot_de_passe.php");
            exit();
        }

        // --- Gestion du "Se souvenir de moi" ---
        if ($rememberMe) {
            $token = bin2hex(random_bytes(32)); // Générer un token sécurisé
            $hashedTokenForDb = password_hash($token, PASSWORD_DEFAULT);
            $expiryTimestamp = time() + (86400 * 30); // 30 jours pour le cookie

            try {
                $sql_remember = "UPDATE Utilisateurs SET remember_token = :hashed_token, remember_expiry = :expiry WHERE ID_Utilisateur = :utilisateur_id";
                $stmt_remember = $pdo->prepare($sql_remember);
                $stmt_remember->bindParam(':hashed_token', $hashedTokenForDb);
                $stmt_remember->bindParam(':expiry', date('Y-m-d H:i:s', $expiryTimestamp));
                $stmt_remember->bindParam(':utilisateur_id', $_SESSION['utilisateur_id']);
                $stmt_remember->execute();

                setcookie('remember_token', $token, [
                    'expires' => $expiryTimestamp,
                    'path' => '/',
                    'domain' => '',
                    'httponly' => true,
                    'secure' => isset($_SERVER['HTTPS']),
                    'samesite' => 'Lax'
                ]);
                setcookie('remember_id', $_SESSION['utilisateur_id'], [
                    'expires' => $expiryTimestamp,
                    'path' => '/',
                    'domain' => '',
                    'httponly' => true,
                    'secure' => isset($_SERVER['HTTPS']),
                    'samesite' => 'Lax'
                ]);
            } catch (PDOException $e) {
                error_log("Erreur PDO lors de la mise à jour du token 'remember me' : " . $e->getMessage());
            }
        } else {
            // Si "Se souvenir de moi" n'est pas coché, supprimer les cookies et nettoyer la DB
            setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
            setcookie('remember_id', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
            try {
                $sql_clear_remember = "UPDATE Utilisateurs SET remember_token = NULL, remember_expiry = NULL WHERE ID_Utilisateur = :utilisateur_id";
                $stmt_clear = $pdo->prepare($sql_clear_remember);
                $stmt_clear->bindParam(':utilisateur_id', $_SESSION['utilisateur_id']);
                $stmt_clear->execute();
            } catch (PDOException $e) {
                error_log("Erreur PDO lors de la suppression du token 'remember me' : " . $e->getMessage());
            }
        }

        // Redirection finale en fonction du rôle
        switch ($_SESSION['role']) {
            case 'Admin':
                header("Location: ../pages/utilisateurs/index.php");
                break;
            case 'Comptable':
                header("Location: ../pages/ecritures/index.php");
                break;
            case 'super_admin':
                header("Location: ../pages/admin/data_management/index.php");
                break;
            default: // Rôle 'Invité' ou autre
                header("Location: ../pages/accueil_invite.php");
                break;
        }
        exit();

    } else { // Authentification échouée ou compte bloqué
        $errorMessage = $authResult; // Le message d'erreur est retourné directement par la fonction
        header("Location: ../index.php?error=" . urlencode($errorMessage));
        exit();
    }
} else {
    // Rediriger si la page est accédée directement sans soumission de formulaire ou champs vides
    header("Location: ../index.php?error=" . urlencode("Veuillez saisir votre login et votre mot de passe."));
    exit();
}