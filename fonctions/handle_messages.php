<?php
// fonctions/handle_messages.php
// Ce bloc de code doit être inclus au début de vos pages PHP (avant toute sortie HTML)
// pour lire les paramètres 'success', 'info', et 'error' de l'URL
// et définir les variables PHP correspondantes pour l'affichage.

// Initialiser les variables à null par défaut
$successMessage = null;
$updateMessage = null; // Variable non utilisée dans le code actuel mais peut être utile
$deleteMessage = null; // Variable non utilisée dans le code actuel mais peut être utile
$errorMessage = null;
$infoMessage = null; // Ajout pour les messages d'information

// Vérifier si un message de succès est présent dans l'URL
if (isset($_GET['success'])) {
    // Décoder le message et échapper les caractères HTML pour la sécurité
    $successMessage = htmlspecialchars(urldecode($_GET['success']));
    // Vous pouvez ajouter une logique ici pour afficher différents messages
    // en fonction du contenu de $_GET['success'], comme dans l'exemple précédent.
    // Pour l'instant, on affiche juste le message tel quel.
}

// Vérifier si un message d'information est présent dans l'URL
if (isset($_GET['info'])) {
    // Décoder et échapper le message d'information
    $infoMessage = htmlspecialchars(urldecode($_GET['info']));
}


// Vérifier si un message d'erreur est présent dans l'URL
if (isset($_GET['error'])) {
    // Décoder et échapper le message d'erreur
    $errorMessage = htmlspecialchars(urldecode($_GET['error']));
    // Comme pour le succès, vous pouvez ajouter une logique ici pour des messages spécifiques
    // en fonction du contenu de $_GET['error'].
}

// Note : Une fois que vous avez résolu les problèmes de base de données et de logique,
// il est recommandé de modifier les scripts de traitement (comme valider_remboursement.php, supprimer.php, etc.)
// pour ne PAS passer les messages d'erreur détaillés de la base de données dans l'URL
// pour des raisons de sécurité. Dans ce cas, vous utiliserez des messages d'erreur génériques
// définis ici ou dans les scripts de traitement eux-mêmes.

?>
