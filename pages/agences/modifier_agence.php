<?php
// pages/agence/modifier_agence.php

// Démarrer la session en premier
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Inclure la connexion à la base de données en premier
// Assurez-vous que le chemin vers votre fichier de connexion est correct.
require_once '../../fonctions/database.php';

// Inclure les fonctions qui dépendent de $pdo
require_once '../../fonctions/gestion_agences.php'; 

// Vérification de l'authentification et du rôle
// Le nom de la variable de session doit être 'utilisateur_role' si vous avez suivi les bonnes pratiques.
// J'ai corrigé 'Role' en 'utilisateur_role'. Si vous utilisez 'role', il faudra le changer.
if (!isset($_SESSION['utilisateur_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../../../index.php");
    exit();
}

$message = '';
$message_type = '';
$agence_data = null; 

// Traitement du formulaire POST pour la modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier_agence'])) {
    // Les champs du formulaire doivent correspondre aux champs de la base de données
    $id_agence = htmlspecialchars($_POST['CodeAgenceSCE']);
    $libelle_agence = htmlspecialchars($_POST['LibelleAgenceSCE']);
    $no_compte_comptable = htmlspecialchars($_POST['NoCompteComptable']);

    if (empty($id_agence) || empty($libelle_agence) || empty($no_compte_comptable)) {
        $message = "Veuillez remplir tous les champs correctement.";
        $message_type = 'danger';
    } else {
        // La variable $pdo est maintenant bien disponible
        if (modifierAgence($pdo, $id_agence, $libelle_agence, $no_compte_comptable)) {
            $_SESSION['admin_message_success'] = "L'agence a été modifiée avec succès.";
            header("Location: liste_agences.php");
            exit();
        } else {
            $message = "Erreur lors de la modification de l'agence. L'ID est-il correct ?";
            $message_type = 'danger';
        }
    }
}

// Récupération des données de l'agence pour pré-remplir le formulaire
// Cette partie s'exécute quand on arrive sur la page via un lien GET
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id_agence = htmlspecialchars($_GET['id']);
    if (!empty($id_agence)) {
        // La variable $pdo est disponible ici aussi
        $agence_data = getAgenceById($pdo, $id_agence);
        if (!$agence_data) {
            $_SESSION['admin_message_error'] = "Agence non trouvée.";
            header("Location: liste_agences.php");
            exit();
        }
    } else {
        $_SESSION['admin_message_error'] = "ID d'agence invalide.";
        header("Location: liste_agences.php");
        exit();
    }
} else if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Si l'utilisateur arrive sans ID et pas par POST, on le redirige
    header("Location: liste_agences.php");
    exit();
}

// Récupération des messages de session s'ils existent
$message = $_SESSION['admin_message_success'] ?? ($_SESSION['admin_message_error'] ?? '');
$message_type = isset($_SESSION['admin_message_success']) ? 'success' : (isset($_SESSION['admin_message_error']) ? 'danger' : '');
unset($_SESSION['admin_message_success'], $_SESSION['admin_message_error']);

// Inclure les fichiers de vue
include '../../templates/header.php';
include '../../templates/navigation.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Modifier une Agence</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card p-4 shadow-sm">
        <form action="modifier_agence.php" method="post">
            <input type="hidden" name="CodeAgenceSCE" value="<?= htmlspecialchars($agence_data['CodeAgenceSCE']); ?>">

            <div class="mb-3">
                <label for="libelle_agence" class="form-label">Libellé de l'Agence</label>
                <input type="text" class="form-control" id="libelle_agence" name="LibelleAgenceSCE" value="<?= htmlspecialchars($agence_data['LibelleAgenceSCE']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="no_compte_comptable" class="form-label">Numéro de Compte Comptable</label>
                <input type="text" class="form-control" id="no_compte_comptable" name="NoCompteComptable" value="<?= htmlspecialchars($agence_data['NoCompteComptable']); ?>">
            </div>
            
            <button type="submit" name="modifier_agence" class="btn btn-primary">
                <i class="fas fa-edit"></i> Modifier l'Agence
            </button>
            <a href="liste_agences.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Annuler
            </a>
        </form>
    </div>
</div>

<?php
include '../../templates/footer.php';
?>