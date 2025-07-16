<?php
// pages/habilitations/gestion_droits.php

$titre = 'Gestion des Droits et Habilitations';

// Inclure les fichiers nécessaires
require_once '../../fonctions/database.php'; // Assurez-vous que ce fichier établit la connexion $pdo
require_once '../../fonctions/gestion_utilisateurs.php'; // Nécessaire pour getListeProfils
require_once '../../fonctions/gestion_habilitations.php'; // Contient les fonctions pour les habilitations

// Inclure les templates (header et navigation gèrent la session et les droits d'affichage de la nav)
// L'ordre d'inclusion peut être important si vos templates démarrent des sessions ou gèrent la sécurité
// On inclut souvent les fonctions et la logique métier avant les templates d'affichage.
// require_once '../../templates/header.php'; // Peut être inclus plus tard si le header génère du HTML
// require_once '../../templates/navigation.php'; // Peut être inclus plus tard si la navigation génère du HTML

// Assurez-vous que $pdo est défini après l'inclusion de database.php
if (!isset($pdo)) {
    // Tente de charger la configuration de la base de données si database.php ne l'a pas fait
    // et établit la connexion. C'est une sécurité, la logique principale devrait être dans database.php
    try {
        require_once '../../config/config.php'; // Assurez-vous d'avoir un fichier config.php
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Impossible de se connecter à la base de données : " . $e->getMessage());
    }
}


// Vérifier si l'utilisateur est connecté (la navigation.php gère déjà la redirection si non connecté)
// Si cette page nécessite une permission spécifique (par ex. 'gerer_droits'), vérifiez-la ici.
// La fonction hasPermission doit être disponible (soit définie dans un fichier inclus, soit dans le template navigation.php comme précédemment).
// Si vous l'avez définie dans navigation.php, assurez-vous que navigation.php est inclus avant cette vérification.
/*
// Exemple de vérification de permission après inclusion de navigation.php
require_once '../../templates/navigation.php'; // Inclure la navigation pour hasPermission si elle y est définie
if (!hasPermission($pdo, $_SESSION['utilisateur_id'] ?? 0, 'gestion_droits_page')) { // Remplacez 'gestion_droits_page' par la permission réelle
    echo "<div class='alert alert-danger'>Vous n'avez pas la permission d'accéder à cette page.</div>";
    require_once '../../templates/footer.php'; // Inclure le footer avant de quitter
    exit();
}
require_once '../../templates/header.php'; // Inclure le header après la vérification si navigation est incluse avant
*/

// Si vous incluez header/navigation plus tard, assurez-vous que la session est démarrée ici si nécessaire pour la logique.
// session_start(); // La navigation.php ou header.php devraient déjà le faire via require_once


// Récupérer la liste des profils et des habilitations disponibles
$profils = getListeProfils($pdo); // Assurez-vous que cette fonction existe et fonctionne dans gestion_utilisateurs.php
$habilitationsDisponibles = getListeHabilitations($pdo); // Utilise la fonction que nous avons ajoutée dans gestion_habilitations.php

$messageSucces = '';
$messageErreur = '';

// Traitement de la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_droits'])) {
    // On s'attend à recevoir un tableau pour chaque profil, indiquant les habilitations sélectionnées
    // Le nom des champs checkbox doit être structuré pour identifier le profil, par exemple habilitations[profil_id][]

    // Vérifier la permission AVANT de traiter la soumission si vous ne l'avez pas fait en haut de page
    /*
    if (!hasPermission($pdo, $_SESSION['utilisateur_id'] ?? 0, 'modifier_droits')) { // Remplacez 'modifier_droits' par la permission réelle de modification
         $messageErreur = "<div class='alert alert-danger'>Vous n'avez pas la permission de modifier les droits.</div>";
    } else {
        // Logique de mise à jour uniquement si la permission est accordée
        $pdo->beginTransaction(); // Commencer une transaction pour assurer l'atomicité

        $all_successful = true; // Flag pour vérifier si toutes les mises à jour ont réussi

        // Assurez-vous que $_POST['habilitations'] existe et est un tableau
        $submittedHabilitations = isset($_POST['habilitations']) ? $_POST['habilitations'] : [];

        foreach ($profils as $profil) {
            $profilId = $profil['id_profil'];
            // Récupérer les IDs d'habilitations sélectionnés pour ce profil spécifique
            // On s'attend à ce que $submittedHabilitations[$profilId] soit un tableau d'IDs ou non défini
            $selectedHabilitationIds = isset($submittedHabilitations[$profilId]) ? (array)$submittedHabilitations[$profilId] : [];

            // Utiliser la fonction de synchronisation pour mettre à jour les habilitations de ce profil
            // Assurez-vous que synchroniserHabilitationsProfil est correctement définie et incluse
            if (!function_exists('synchroniserHabilitationsProfil') || !synchroniserHabilitationsProfil($pdo, $profilId, $selectedHabilitationIds)) {
                $all_successful = false;
                // Enregistrer des messages d'erreur spécifiques par profil si nécessaire (optionnel)
                 error_log("Échec de la synchronisation pour le profil ID: " . $profilId . " - PDO Error: " . print_r($pdo->errorInfo(), true)); // Exemple d'ajout de log d'erreur PDO
            }
        }

        if ($all_successful) {
            $pdo->commit();
            $messageSucces = "<div class='alert alert-success'>Les droits ont été mis à jour avec succès.</div>";
            // Optionnel : Recharger les données si l'affichage dépend des modifications
            // $profils = getListeProfils($pdo); // Si la liste des profils pouvait changer
            // $habilitationsDisponibles = getListeHabilitations($pdo); // Si la liste des habilitations pouvait changer
        } else {
            $pdo->rollBack();
            $messageErreur = "<div class='alert alert-danger'>Une erreur est survenue lors de la mise à jour des droits. Voir les logs pour plus de détails.</div>";
        }
    }
    */

    // --- LOGIQUE DE TRAITEMENT SANS VÉRIFICATION DE PERMISSION À L'INTÉRIEUR DU POST (à adapter) ---
     $pdo->beginTransaction();
     $all_successful = true;
     $submittedHabilitations = isset($_POST['habilitations']) ? $_POST['habilitations'] : [];

     foreach ($profils as $profil) {
         $profilId = $profil['id_profil'];
         $selectedHabilitationIds = isset($submittedHabilitations[$profilId]) ? (array)$submittedHabilitations[$profilId] : [];

         if (!function_exists('synchroniserHabilitationsProfil') || !synchroniserHabilitationsProfil($pdo, $profilId, $selectedHabilitationIds)) {
             $all_successful = false;
             error_log("Échec de la synchronisation pour le profil ID: " . $profilId . " - PDO Error: " . print_r($pdo->errorInfo(), true));
         }
     }

     if ($all_successful) {
         $pdo->commit();
         $messageSucces = "<div class='alert alert-success'>Les droits ont été mis à jour avec succès.</div>";
     } else {
         $pdo->rollBack();
         $messageErreur = "<div class='alert alert-danger'>Une erreur est survenue lors de la mise à jour des droits. Voir les logs pour plus de détails.</div>";
     }
    // --- FIN LOGIQUE DE TRAITEMENT ---


}


// Inclure les templates qui génèrent le HTML (après la logique métier)
require_once '../../templates/header.php'; // Inclut <head>, <body>, <header>
require_once '../../templates/navigation.php'; // Inclut la navigation verticale
?>

<div class="container mt-4"> <h2><?php echo htmlspecialchars($titre); ?></h2>

    <?php if ($messageSucces): ?>
        <?= $messageSucces ?>
    <?php endif; ?>

    <?php if ($messageErreur): ?>
        <?= $messageErreur ?>
    <?php endif; ?>

    <p>Cochez les habilitations que chaque profil doit posséder.</p>

    <form method="POST" action="">
        <input type="hidden" name="update_droits" value="1">

        <?php if (!empty($profils)): ?>
            <div class="table-responsive"> <table class="table table-bordered table-striped table-hover"> <thead>
                        <tr>
                            <th>Profil</th>
                            <?php foreach ($habilitationsDisponibles as $habilitation): ?>
                                <th class="text-center" style="min-width: 100px;"><?= htmlspecialchars($habilitation['nom_habilitation']) ?></th> <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($profils as $profil): ?>
                            <?php
                            // Récupérer les habilitations actuelles pour ce profil
                            // Assurez-vous que getHabilitationsParProfil est correctement définie et incluse
                            if (!function_exists('getHabilitationsParProfil')) {
                                // Fallback ou message d'erreur si la fonction n'existe pas
                                $habilitationsActuelles = [];
                                error_log("Erreur fatale: La fonction getHabilitationsParProfil est indéfinie.");
                                // Vous pourriez vouloir interrompre l'affichage ou afficher un message d'erreur ici
                            } else {
                                $habilitationsActuelles = getHabilitationsParProfil($pdo, $profil['id_profil']);
                            }

                            // Convertir le tableau d'objets/arrays en une simple liste d'IDs pour une vérification facile
                            $habilitationIdsActuelles = array_column($habilitationsActuelles, 'id_habilitation');
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($profil['nom_profil']) ?></td>
                                <?php foreach ($habilitationsDisponibles as $habilitation): ?>
                                    <td class="text-center">
                                        <input
                                            type="checkbox"
                                            name="habilitations[<?= $profil['id_profil'] ?>][]"
                                            value="<?= $habilitation['id_habilitation'] ?>"
                                            <?= in_array($habilitation['id_habilitation'], $habilitationIdsActuelles) ? 'checked' : '' ?>
                                        >
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div> <button type="submit" class="btn btn-primary mt-3">Enregistrer les Modifications</button>

        <?php else: ?>
            <div class="alert alert-info">Aucun profil trouvé. Veuillez d'abord créer des profils.</div>
        <?php endif; ?>

        <?php if (empty($habilitationsDisponibles)): ?>
             <div class="alert alert-warning">Aucune habilitation disponible. Veuillez d'abord créer des habilitations.</div>
        <?php endif; ?>

    </form>

</div> <?php
// Inclure le footer
require_once '../../templates/footer.php'; // Assurez-vous que ce fichier existe
?>