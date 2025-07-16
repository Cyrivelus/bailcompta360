<?php
// fonctions/traitement_donnees.php
// Fonctions pour le traitement et la manipulation des données

/**
 * Nettoie une chaîne de caractères en supprimant les espaces inutiles,
 * les balises HTML et en échappant les caractères spéciaux pour l'affichage HTML.
 *
 * @param string $data La chaîne de caractères à nettoyer.
 * @return string La chaîne de caractères nettoyée et échappée.
 */
function nettoyerEtAfficher($data) {
    $data = trim($data);
    $data = strip_tags($data);
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Échappe une chaîne de caractères pour une utilisation sécurisée dans une requête SQL.
 * Note : Cette fonction suppose que vous utilisez l'objet de connexion PDO.
 * Si vous utilisez une autre extension de base de données, adaptez la fonction en conséquence.
 *
 * @param PDO $db L'objet de connexion PDO.
 * @param string $data La chaîne de caractères à échapper.
 * @return string La chaîne de caractères échappée.
 */
function echapperPourSQL(PDO $pdo, $data) {
    return $pdo->quote($data);
}

/**
 * Convertit une date au format YYYY-MM-DD.
 * Vous pouvez adapter le format d'entrée si nécessaire.
 *
 * @param string $date La date au format d'entrée (ex: DD/MM/YYYY).
 * @param string $formatEntree Le format de la date d'entrée (par défaut d/m/Y).
 * @return string|null La date au format YYYY-MM-DD, ou null en cas d'erreur de format.
 */
function convertirDatePourBD($date, $formatEntree = 'd/m/Y') {
    $dateObj = DateTime::createFromFormat($formatEntree, $date);
    if ($dateObj) {
        return $dateObj->format('Y-m-d');
    }
    return null;
}

/**
 * Formatte une date depuis le format YYYY-MM-DD vers un format d'affichage.
 *
 * @param string $date La date au format YYYY-MM-DD.
 * @param string $formatAffichage Le format souhaité pour l'affichage (par défaut d/m/Y).
 * @return string|null La date formatée pour l'affichage, ou null en cas d'erreur de format.
 */
function formatterDateAffichage($date, $formatAffichage = 'd/m/Y') {
    $dateObj = new DateTime($date);
    if ($dateObj) {
        return $dateObj->format($formatAffichage);
    }
    return null;
}

/**
 * Formatte un nombre en devise (par exemple, avec un symbole de devise et un séparateur de milliers).
 *
 * @param float $nombre Le nombre à formater.
 * @param string $symboleDevise Le symbole de la devise (par défaut '€').
 * @param string $separateurDecimal Le séparateur décimal (par défaut ',').
 * @param string $separateurMilliers Le séparateur de milliers (par défaut ' ').
 * @return string Le nombre formaté en devise.
 */
function formatterEnDevise($nombre, $symboleDevise = '€', $separateurDecimal = ',', $separateurMilliers = ' ') {
    return number_format($nombre, 2, $separateurDecimal, $separateurMilliers) . ' ' . $symboleDevise;
}

/**
 * Génère une chaîne de caractères aléatoire.
 *
 * @param int $longueur La longueur de la chaîne aléatoire à générer (par défaut 10).
 * @return string La chaîne de caractères aléatoire.
 */
function genererChaineAleatoire($longueur = 10) {
    $caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $chaineAleatoire = '';
    $max = strlen($caracteres) - 1;
    for ($i = 0; $i < $longueur; $i++) {
        $chaineAleatoire .= $caracteres[random_int(0, $max)];
    }
    return $chaineAleatoire;
}

/**
 * Hash un mot de passe en utilisant l'algorithme par défaut de PHP (bcrypt).
 *
 * @param string $motDePasse Le mot de passe à hasher.
 * @return string Le hash du mot de passe.
 */
function hasherMotDePasse($motDePasse) {
    return password_hash($motDePasse, PASSWORD_DEFAULT);
}

/**
 * Vérifie si un mot de passe correspond à un hash donné.
 *
 * @param string $motDePasse Le mot de passe à vérifier.
 * @param string $hash Le hash du mot de passe à comparer.
 * @return bool True si le mot de passe correspond au hash, false sinon.
 */
function verifierMotDePasse($motDePasse, $hash) {
    return password_verify($motDePasse, $hash);
}

// Vous pouvez ajouter d'autres fonctions de traitement de données spécifiques à votre application ici.

?>