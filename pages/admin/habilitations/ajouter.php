<?php
// pages/admin/habilitations/ajouter.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur (à implémenter)
// require_once('../../../fonctions/authentification.php'); // Exemple
// if (!estAdmin()) {
//     $_SESSION['flash_message'] = "Vous n'avez pas les droits pour accéder à cette page.";
//     $_SESSION['flash_type'] = 'error';
//     header('Location: ../../acces_refuse.php'); // Rediriger vers une page d'accès refusé
//     exit;
// }


// Inclure les fichiers de fonctions et de configuration
require_once('../../../fonctions/database.php'); // Assurez-vous que ce fichier gère la connexion à la BD
require_once('../../../fonctions/gestion_habilitations.php'); // Inclut maintenant les fonctions getAllProfils, getAllUtilisateurs et getPotentialPermissionObjects

// Inclure l'en-tête de la page
include('../../../templates/header.php');

// Inclure la barre de navigation
include('../../../templates/navigation.php');

// Connexion à la base de données (utiliser la connexion gérée par database.php si possible,
// sinon gardez la connexion directe si c'est votre choix actuel)



// Récupérer la liste des profils et utilisateurs pour les dropdowns
$profils = getAllProfils($pdo);
$utilisateurs = getAllUtilisateurs($pdo);

// Récupérer la liste des objets de permission potentiels
$permissionObjects = getPotentialPermissionObjects();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?= htmlspecialchars($titre) ?></title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
<div class="container mx-auto p-4 sm:p-6 lg:p-8">
    <h1 class="text-2xl sm:text-3xl font-bold mb-6 text-gray-800">Ajouter une Habilitation</h1>

    <?php
    // Afficher les messages flash (succès, erreur) s'il y en a
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = isset($_SESSION['flash_type']) && $_SESSION['flash_type'] === 'error' ? 'red' : 'green';
        // Afficher le message (même style que dans index.php)
        echo "<div class='bg-{$type}-100 border border-{$type}-400 text-{$type}-700 px-4 py-3 rounded relative mb-4' role='alert'>";
        echo "<strong class='font-bold'>" . ucfirst($type === 'red' ? 'Erreur' : 'Succès') . "!</strong>";
        echo "<span class='block sm:inline'> " . htmlspecialchars($message) . "</span>";
        echo "</div>";
        // Supprimer le message après l'affichage
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
    ?>

    <form action="traitement_ajout_habilitation.php" method="POST" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="objet">
                Objet (Permission) :
            </label>
            <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="objet" name="objet" required>
                <option value="">-- Sélectionner un objet --</option>
                <?php if (!empty($permissionObjects)) : ?>
                    <?php foreach ($permissionObjects as $object) : ?>
                        <option value="<?php echo htmlspecialchars($object); ?>"><?php echo htmlspecialchars($object); ?></option>
                    <?php endforeach; ?>
                <?php else : ?>
                    <option value="">Aucun objet de permission disponible</option>
                <?php endif; ?>
            </select>
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">
                Assigner à :
            </label>
            <div>
                <input type="radio" id="assign_profil" name="assign_type" value="profil" checked>
                <label for="assign_profil" class="mr-4">Profil</label>

                <input type="radio" id="assign_utilisateur" name="assign_type" value="utilisateur">
                <label for="assign_utilisateur">Utilisateur Spécifique</label>
            </div>
        </div>

        <div id="profil_select_container" class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="id_profil">
                Choisir un Profil :
            </label>
            <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="id_profil" name="id_profil">
                <?php if ($profils) : ?>
                    <?php foreach ($profils as $profil) : ?>
                        <option value="<?php echo htmlspecialchars($profil['ID_Profil']); ?>"><?php echo htmlspecialchars($profil['Nom_Profil']); ?></option>
                    <?php endforeach; ?>
                <?php else : ?>
                    <option value="">Aucun profil trouvé</option>
                <?php endif; ?>
            </select>
        </div>

        <div id="utilisateur_select_container" class="mb-4 hidden">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="id_utilisateur">
                Choisir un Utilisateur :
            </label>
            <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="id_utilisateur" name="id_utilisateur">
                <?php if ($utilisateurs) : ?>
                    <?php foreach ($utilisateurs as $utilisateur) : ?>
                        <option value="<?php echo htmlspecialchars($utilisateur['ID_Utilisateur']); ?>"><?php echo htmlspecialchars($utilisateur['Nom']); ?></option>
                    <?php endforeach; ?>
                <?php else : ?>
                    <option value="">Aucun utilisateur trouvé</option>
                <?php endif; ?>
            </select>
        </div>

        <div class="flex items-center justify-between">
            <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit">
                Ajouter l'Habilitation
            </button>
            <a href="index.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                Annuler
            </a>
        </div>
    </form>

</div>

<script>
    // JavaScript pour montrer/cacher les dropdowns profil/utilisateur
    const assignProfilRadio = document.getElementById('assign_profil');
    const assignUtilisateurRadio = document.getElementById('assign_utilisateur');
    const profilSelectContainer = document.getElementById('profil_select_container');
    const utilisateurSelectContainer = document.getElementById('utilisateur_select_container');
    const idProfilSelect = document.getElementById('id_profil');
    const idUtilisateurSelect = document.getElementById('id_utilisateur');


    function toggleSelects() {
        if (assignProfilRadio.checked) {
            profilSelectContainer.classList.remove('hidden');
            utilisateurSelectContainer.classList.add('hidden');
            idProfilSelect.setAttribute('required', 'required');
            idUtilisateurSelect.removeAttribute('required');
            idProfilSelect.disabled = false;
            idUtilisateurSelect.disabled = true;
        } else {
            profilSelectContainer.classList.add('hidden');
            utilisateurSelectContainer.classList.remove('hidden');
            idProfilSelect.removeAttribute('required');
            idUtilisateurSelect.setAttribute('required', 'required');
            idProfilSelect.disabled = true;
            idUtilisateurSelect.disabled = false;
        }
    }

    // Appeler au chargement de la page et lors du changement de radio
    toggleSelects();
    assignProfilRadio.addEventListener('change', toggleSelects);
    assignUtilisateurRadio.addEventListener('change', toggleSelects);

    // S'assurer que l'état initial est correct (redondant mais sécurisant)
     if (assignProfilRadio.checked) {
         idProfilSelect.disabled = false;
         idUtilisateurSelect.disabled = true;
     } else {
         idProfilSelect.disabled = true;
         idUtilisateurSelect.disabled = false;
     }

</script>

<?php
// Inclure le pied de page
include('../../../templates/footer.php');
?>
