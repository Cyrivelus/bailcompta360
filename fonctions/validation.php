<?php
// fonctions/validation.php
// Fonctions de validation des données

/**
 * Valide si une valeur est non vide.
 *
 * @param mixed $value La valeur à vérifier.
 * @return bool True si la valeur n'est pas vide, false sinon.
 */
function estNonVide($value) {
    return !empty(trim($value));
}

/**
 * Valide si une valeur est un email valide.
 *
 * @param string $email L'adresse email à vérifier.
 * @return bool True si l'email est valide, false sinon.
 */
function estEmailValide($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Valide si une valeur a une longueur minimale spécifiée.
 *
 * @param string $value La chaîne de caractères à vérifier.
 * @param int $minLength La longueur minimale requise.
 * @return bool True si la longueur est supérieure ou égale à la longueur minimale, false sinon.
 */
function aLongueurMinimale($value, $minLength) {
    return strlen(trim($value)) >= $minLength;
}

/**
 * Valide si une valeur a une longueur maximale spécifiée.
 *
 * @param string $value La chaîne de caractères à vérifier.
 * @param int $maxLength La longueur maximale autorisée.
 * @return bool True si la longueur est inférieure ou égale à la longueur maximale, false sinon.
 */
function aLongueurMaximale($value, $maxLength) {
    return strlen(trim($value)) <= $maxLength;
}

/**
 * Valide si une valeur correspond à un format spécifique (via une expression régulière).
 *
 * @param string $value La chaîne de caractères à vérifier.
 * @param string $pattern L'expression régulière (PCRE) à utiliser pour la validation.
 * @return bool True si la valeur correspond au format, false sinon.
 */
function correspondAuFormat($value, $pattern) {
    return preg_match($pattern, $value);
}

/**
 * Valide si une valeur est numérique.
 *
 * @param mixed $value La valeur à vérifier.
 * @return bool True si la valeur est numérique, false sinon.
 */
function estNumerique($value) {
    return is_numeric($value);
}

/**
 * Valide si une valeur est un entier.
 *
 * @param mixed $value La valeur à vérifier.
 * @return bool True si la valeur est un entier, false sinon.
 */
function estEntier($value) {
    return filter_var($value, FILTER_VALIDATE_INT) !== false;
}

/**
 * Valide si une valeur est un nombre flottant.
 *
 * @param mixed $value La valeur à vérifier.
 * @return bool True si la valeur est un nombre flottant, false sinon.
 */
function estFlottant($value) {
    return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
}

/**
 * Valide si une valeur est une URL valide.
 *
 * @param string $url L'URL à vérifier.
 * @return bool True si l'URL est valide, false sinon.
 */
function estURLValide($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Valide si une valeur est égale à une autre.
 *
 * @param mixed $value1 La première valeur à comparer.
 * @param mixed $value2 La deuxième valeur à comparer.
 * @return bool True si les valeurs sont égales, false sinon.
 */
function estEgalA($value1, $value2) {
    return $value1 === $value2;
}

/**
 * Valide si une valeur est dans une liste donnée.
 *
 * @param mixed $value La valeur à vérifier.
 * @param array $allowedValues Un tableau des valeurs autorisées.
 * @return bool True si la valeur est dans la liste, false sinon.
 */
function estDansListe($value, array $allowedValues) {
    return in_array($value, $allowedValues, true);
}

// Vous pouvez ajouter d'autres fonctions de validation spécifiques à votre application ici.

?>