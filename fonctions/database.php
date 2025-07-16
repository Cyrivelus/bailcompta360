<?php
// fonctions/database.php

// Configuration de la connexion MySQL sur localhost
$dbHost = "localhost"; // Généralement 'localhost' pour une base de données locale
$dbName = "BD_AD_SCE";  // Nom de votre base de données MySQL
$dbUser = "root";      // Nom d'utilisateur MySQL (souvent 'root' pour localhost)
$dbPass = "";          // Mot de passe MySQL (souvent vide pour 'root' sur localhost, ou votre mot de passe)
$dbCharset = "utf8mb4"; // Jeu de caractères recommandé pour MySQL

// DSN (Data Source Name) pour PDO MySQL
$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=$dbCharset";

// Options de connexion PDO
$connectionOptions = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Configure PDO pour lancer des exceptions en cas d'erreur
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Définit le mode de récupération par défaut des résultats en tableau associatif
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Désactive l'émulation des requêtes préparées pour une meilleure sécurité et performance
];

try {
    // TENTATIVE DE CONNEXION :
    // Utilisation du DSN, du nom d'utilisateur et du mot de passe MySQL
    $pdo = new PDO($dsn, $dbUser, $dbPass, $connectionOptions);

    // Une vérification simple pour s'assurer que la connexion est active.
    $pdo->query("SELECT 1");

} catch (PDOException $e) {
    // Journalisation de l'erreur détaillée dans les logs d'Apache/PHP
    error_log("Erreur de connexion à la base de données MySQL: " . $e->getMessage());

    // Envoi d'une en-tête HTTP 500 pour indiquer une erreur serveur
    if (!headers_sent()) {
        header("HTTP/1.1 500 Internal Server Error");
    }

    // Affiche le message d'erreur détaillé directement sur la page web pour le débogage.
    // TRÈS IMPORTANT : NE PAS UTILISER CECI EN PRODUCTION pour des raisons de sécurité !
    die("Impossible de se connecter à la base de données. Veuillez réessayer plus tard.<br><br><b>DÉTAIL DE L'ERREUR (DEBUG) :</b> " . $e->getMessage() . "<br>Veuillez vérifier les identifiants MySQL, l'état du serveur MySQL (e.g., XAMPP/WAMP est-il démarré ?) et les pare-feu.");
}

// Fonction utilitaire pour exécuter des requêtes préparées en toute sécurité.
function executeQuery($sql, $params = []) {
    global $pdo; // Accède à l'objet PDO global pour la connexion

    try {
        $stmt = $pdo->prepare($sql); // Prépare la requête SQL
        $stmt->execute($params);     // Exécute la requête avec les paramètres fournis
        return $stmt;                // Retourne l'objet PDOStatement
    } catch (PDOException $e) {
        // Journalisation de l'erreur SQL spécifique pour le débogage
        error_log("Erreur SQL MySQL: " . $e->getMessage() . " - Requête: " . $sql);
        // Relance l'exception pour que le code appelant puisse la gérer
        throw $e;
    }
}