<?php
// admin/habilitations/afficher_details.php

require_once '../../includes/header.php';
require_once '../../includes/navigation.php';
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_habilitations.php';
require_once '../../fonctions/gestion_profils.php';

// Vérifier si l'utilisateur est connecté et a les droits d'administrateur
if (!estAdminConnecte()) {
    header('Location: ../index.php');
    exit();
}

// Vérifier le type (profil ou utilisateur) et l'ID sont présents dans l'URL
if (!isset($_GET['type']) || !in_array($_GET['type'], ['profil', 'utilisateur']) || !isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $errorMessage = "Type ou ID invalide.";
} else {
    $type = $_GET['type'];
    $id = $_GET['id'];
    $detailsHabilitations = [];
    $nomEntite = '';

    if ($type === 'profil') {
        $profil = getProfilParId($pdo, $id);
        if ($profil) {
            $nomEntite = htmlspecialchars($profil['Nom_Profil']);
            $detailsHabilitations = getHabilitationsParProfilId($pdo, $id);
        } else {
            $errorMessage = "Profil non trouvé.";
        }
    } elseif ($type === 'utilisateur') {
        $utilisateur = getUtilisateurParId($pdo, $id);
        if ($utilisateur) {
            $nomEntite = htmlspecialchars($utilisateur['Nom']) . ' (' . htmlspecialchars($utilisateur['Login_Utilisateur']) . ')';
            $detailsHabilitations = getHabilitationsParUtilisateurId($pdo, $id);
        } else {
            $errorMessage = "Utilisateur non trouvé.";
        }
    }
}

?>

<div class="container mt-5">
    <h2>Détails des Habilitations pour
        <?php if (isset($type) && $type === 'profil' && isset($nomEntite)): ?>
            le Profil : <?= $nomEntite ?>
        <?php elseif (isset($type) && $type === 'utilisateur' && isset($nomEntite)): ?>
            l'Utilisateur : <?= $nomEntite ?>
        <?php endif; ?>
    </h2>

    <?php if (isset($errorMessage)): ?>
        <div class="alert alert-danger"><?= $errorMessage ?></div>
    <?php endif; ?>

    <?php if (isset($detailsHabilitations) && !empty($detailsHabilitations)): ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Objet de l'Habilitation</th>
                    </tr>
            </thead>
            <tbody>
                <?php foreach ($detailsHabilitations as $habilitation): ?>
                    <tr>
                        <td><?= htmlspecialchars($habilitation['Objet']) ?></td>
                        </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="index.php" class="btn btn-secondary">
            <span class="glyphicon glyphicon-arrow-left"></span> Retour à la liste des Habilitations
        </a>
    <?php elseif (!isset($errorMessage)): ?>
        <p>Aucun détail d'habilitation trouvé pour
            <?php if (isset($type) && $type === 'profil' && isset($nomEntite)): ?>
                le profil : <?= $nomEntite ?>.
            <?php elseif (isset($type) && $type === 'utilisateur' && isset($nomEntite)): ?>
                l'utilisateur : <?= $nomEntite ?>.
            <?php endif; ?>
        </p>
        <a href="index.php" class="btn btn-secondary">
            <span class="glyphicon glyphicon-arrow-left"></span> Retour à la liste des Habilitations
        </a>
    <?php endif; ?>
</div>

<?php
require_once '../../includes/footer.php';

// Fonctions à implémenter (dans fonctions/gestion_habilitations.php et gestion_profils.php) :
// - estAdminConnecte() : Vérifie si l'utilisateur connecté est un administrateur.
// - getProfilParId($pdo, $id) : Récupère les informations d'un profil par son ID.
// - getUtilisateurParId($pdo, $id) : Récupère les informations d'un utilisateur par son ID.
// - getHabilitationsParProfilId($pdo, $profilId) : Récupère les habilitations associées à un profil.
// - getHabilitationsParUtilisateurId($pdo, $utilisateurId) : Récupère les habilitations associées à un utilisateur (si vous en avez).
?>