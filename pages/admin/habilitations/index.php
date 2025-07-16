<?php
// pages/admin/habilitations/index.php

// Démarrer la session pour la gestion de l'authentification et des messages flash
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur (exemple basique)
// Vous devriez avoir un système d'authentification et de gestion des rôles plus robuste.

// Inclure les fichiers de fonctions et de configuration
require_once(__DIR__ . '/../../../fonctions/database.php'); // Assurez-vous que ce fichier gère la connexion à la BD
require_once(__DIR__ . '/../../../fonctions/gestion_habilitations.php'); // Contient getHabilitationsProfilsAvecDetails et getHabilitationsUtilisateursAvecDetails

// Inclure l'en-tête de la page
include(__DIR__ . '/../../../templates/header.php'); // Contient le doctype, head, et début du body

// Inclure la barre de navigation
include(__DIR__ . '/../../../templates/navigation.php'); // Contient le menu de navigation



// Récupérer les habilitations
// On suppose que ces fonctions retournent un tableau de données ou false en cas d'erreur.
// Et qu'elles récupèrent aussi les noms des profils/utilisateurs.
$habilitationsProfils = getHabilitationsProfilsAvecDetails($pdo); // Devrait joindre avec la table Profils pour obtenir le nom du profil
$habilitationsUtilisateurs = getHabilitationsUtilisateursAvecDetails($pdo); // Devrait joindre avec la table Utilisateurs pour obtenir le nom de l'utilisateur

// Vérifier si une habilitation a été supprimée
if (isset($_GET['supprimer']) && $_GET['supprimer'] == 'success') {
    $_SESSION['flash_message'] = "L'habilitation a été supprimée avec succès.";
    $_SESSION['flash_type'] = 'success';
}

// Vérifier si une habilitation a été ajoutée
if (isset($_GET['ajouter']) && $_GET['ajouter'] == 'success') {
    $_SESSION['flash_message'] = "L'habilitation a été ajoutée avec succès.";
    $_SESSION['flash_type'] = 'success';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?= htmlspecialchars($titre) ?></title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/monstyle.css">
    <link rel="stylesheet" href="../../css/formulaire.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        /* Styles personnalisés pour les boutons et autres éléments */
        .bg-green-500 {
            background-color: #48bb78;
        }
        .hover\:bg-green-700:hover {
            background-color: #2f855a;
        }
        .bg-blue-500 {
            background-color: #4299e1;
        }
        .hover\:bg-blue-700:hover {
            background-color: #2b6cb0;
        }
        .bg-red-500 {
            background-color: #f56565;
        }
        .hover\:bg-red-700:hover {
            background-color: #c53030;
        }
        .text-white {
            color: white;
        }
        .font-bold {
            font-weight: bold;
        }
        .py-2 {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }
        .px-4 {
            padding-left: 1rem;
            padding-right: 1rem;
        }
        .rounded {
            border-radius: 0.25rem;
        }
        .inline-flex {
            display: inline-flex;
        }
        .items-center {
            align-items: center;
        }
        .w-4 {
            width: 1rem;
        }
        .h-4 {
            height: 1rem;
        }
        .mr-2 {
            margin-right: 0.5rem;
        }
        .flex {
            display: flex;
        }
        .justify-end {
            justify-content: flex-end;
        }
        .mb-6 {
            margin-bottom: 1.5rem;
        }
        .text-2xl {
            font-size: 1.5rem;
        }
        .sm\:text-3xl {
            font-size: 1.875rem;
        }
        .font-bold {
            font-weight: bold;
        }
        .mb-6 {
            margin-bottom: 1.5rem;
        }
        .text-gray-800 {
            color: #2d3748;
        }
        .text-xl {
            font-size: 1.25rem;
        }
        .sm\:text-2xl {
            font-size: 1.5rem;
        }
        .font-semibold {
            font-weight: 600;
        }
        .text-gray-700 {
            color: #4a5568;
        }
        .flex {
            display: flex;
        }
        .justify-between {
            justify-content: space-between;
        }
        .items-center {
            align-items: center;
        }
        .mb-4 {
            margin-bottom: 1rem;
        }
        .overflow-x-auto {
            overflow-x: auto;
        }
        .bg-white {
            background-color: white;
        }
        .shadow-md {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .rounded-lg {
            border-radius: 0.5rem;
        }
        .min-w-full {
            min-width: 100%;
        }
        .leading-normal {
            line-height: 1.5;
        }
        .bg-gray-200 {
            background-color: #edf2f7;
        }
        .text-gray-600 {
            color: #718096;
        }
        .uppercase {
            text-transform: uppercase;
        }
        .text-sm {
            font-size: 0.875rem;
        }
        .py-3 {
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
        }
        .px-4 {
            padding-left: 1rem;
            padding-right: 1rem;
        }
        .sm\:px-6 {
            padding-left: 1.5rem;
            padding-right: 1.5rem;
        }
        .text-left {
            text-align: left;
        }
        .text-center {
            text-align: center;
        }
        .border-b {
            border-bottom-width: 1px;
        }
        .border-gray-200 {
            border-color: #e2e8f0;
        }
        .hover\:bg-gray-50:hover {
            background-color: #f7fafc;
        }
        .font-medium {
            font-weight: 500;
        }
        .text-xs {
            font-size: 0.75rem;
        }
        .text-gray-500 {
            color: #a0aec0;
        }
        .py-1 {
            padding-top: 0.25rem;
            padding-bottom: 0.25rem;
        }
        .px-3 {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }
        .text-xs {
            font-size: 0.75rem;
        }
        .sm\:text-sm {
            font-size: 0.875rem;
        }
        .space-x-2 {
            margin-left: 0.5rem;
            margin-right: 0.5rem;
        }
        .w-3 {
            width: 0.75rem;
        }
        .h-3 {
            height: 0.75rem;
        }
        .mr-1 {
            margin-right: 0.25rem;
        }
        .bg-white {
            background-color: white;
        }
        .p-6 {
            padding: 1.5rem;
        }
        .rounded-lg {
            border-radius: 0.5rem;
        }
        .shadow {
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        }
        .text-gray-600 {
            color: #718096;
        }
        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>
<div class="container mx-auto p-4 sm:p-6 lg:p-8">
    <h1 class="text-2xl sm:text-3xl font-bold mb-6 text-gray-800">Gestion des Habilitations</h1>
    <div class="flex justify-end mb-6">
        <a href="ajouter.php" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Ajouter une Habilitation
        </a>
    </div>

    <?php
    // Afficher les messages flash
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        $type = isset($_SESSION['flash_type']) && $_SESSION['flash_type'] === 'error' ? 'red' : 'green';
        unset($_SESSION['flash_type']);
        echo "<div class='bg-{$type}-100 border border-{$type}-400 text-{$type}-700 px-4 py-3 rounded relative mb-4' role='alert'>";
        echo "<strong class='font-bold'>" . ucfirst($type === 'red' ? 'Erreur' : 'Succès') . "!</strong>";
        echo "<span class='block sm:inline'> " . htmlspecialchars($message) . "</span>";
        echo "</div>";
    }
    ?>

    <section class="mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl sm:text-2xl font-semibold text-gray-700">Habilitations par Profil</h2>
        </div>

        <?php if ($habilitationsProfils && count($habilitationsProfils) > 0) : ?>
            <div class="overflow-x-auto bg-white shadow-md rounded-lg">
                <table class="min-w-full leading-normal">
                    <thead>
                        <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-4 sm:px-6 text-left">Profil</th>
                            <th class="py-3 px-4 sm:px-6 text-left">Permission</th>
                            <th class="py-3 px-4 sm:px-6 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php foreach ($habilitationsProfils as $hp) : ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-4 sm:px-6 text-left">
                                    <div class="font-medium"><?php echo htmlspecialchars($hp['Nom_Profil'] ?? 'N/A'); ?></div>
                                    <div class="text-xs text-gray-500">ID: <?php echo htmlspecialchars($hp['ID_Habilitation_Profil']); ?></div>
                                </td>
                                <td class="py-3 px-4 sm:px-6 text-left"><?php echo htmlspecialchars($hp['Objet']); ?></td>
                                <td class="py-3 px-4 sm:px-6 text-center">
                                    <div class="flex justify-center space-x-2">
                                        <a href="modifier.php?id=<?php echo $hp['ID_Habilitation_Profil']; ?>&type=profil"
                                           class="bg-blue-500 hover:bg-blue-700 text-white py-1 px-3 rounded text-xs sm:text-sm flex items-center">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                            Modifier
                                        </a>
                                        <a href="supprimer.php?id=<?php echo $hp['ID_Habilitation_Profil']; ?>&type=profil"
                                           class="bg-red-500 hover:bg-red-700 text-white py-1 px-3 rounded text-xs sm:text-sm flex items-center"
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette habilitation ?');">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                            Supprimer
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else : ?>
            <div class="bg-white p-6 rounded-lg shadow">
                <p class="text-gray-600 text-center">Aucune habilitation par profil trouvée.</p>
            </div>
        <?php endif; ?>
    </section>

    <section>
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl sm:text-2xl font-semibold text-gray-700">Habilitations Spécifiques</h2>
        </div>

        <?php if ($habilitationsUtilisateurs && count($habilitationsUtilisateurs) > 0) : ?>
            <div class="overflow-x-auto bg-white shadow-md rounded-lg">
                <table class="min-w-full leading-normal">
                    <thead>
                        <tr class="bg-gray-200 text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-3 px-4 sm:px-6 text-left">Utilisateur</th>
                            <th class="py-3 px-4 sm:px-6 text-left">Permission</th>
                            <th class="py-3 px-4 sm:px-6 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 text-sm">
                        <?php foreach ($habilitationsUtilisateurs as $hu) : ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-4 sm:px-6 text-left">
                                    <div class="font-medium"><?php echo htmlspecialchars($hu['Nom_Utilisateur'] ?? 'N/A'); ?></div>
                                    <div class="text-xs text-gray-500">ID: <?php echo htmlspecialchars($hu['ID_Habilitation_Utilisateur']); ?></div>
                                </td>
                                <td class="py-3 px-4 sm:px-6 text-left"><?php echo htmlspecialchars($hu['Objet']); ?></td>
                                <td class="py-3 px-4 sm:px-6 text-center">
                                    <div class="flex justify-center space-x-2">
                                        <a href="modifier.php?id=<?php echo $hu['ID_Habilitation_Utilisateur']; ?>&type=utilisateur"
                                           class="bg-blue-500 hover:bg-blue-700 text-white py-1 px-3 rounded text-xs sm:text-sm flex items-center">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                            Modifier
                                        </a>
                                        <a href="supprimer.php?id=<?php echo $hu['ID_Habilitation_Utilisateur']; ?>&type=utilisateur"
                                           class="bg-red-500 hover:bg-red-700 text-white py-1 px-3 rounded text-xs sm:text-sm flex items-center"
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette habilitation ?');">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                            Supprimer
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else : ?>
            <div class="bg-white p-6 rounded-lg shadow">
                <p class="text-gray-600 text-center">Aucune habilitation spécifique par utilisateur trouvée.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php
// Inclure le pied de page
include(__DIR__ . '/../../../templates/footer.php');
?>
