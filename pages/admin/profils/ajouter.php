<?php
// pages/admin/profils/ajouter.php

// Démarrer la session si ce n'est pas déjà fait
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Configuration et Includes ---
// Définir le titre et si le style admin est requis AVANT d'inclure le header
$title = "Ajouter un Profil";
$admin_style = true; // Pour que header.php puisse conditionnellement charger admin_style.css

// Inclure le fichier de connexion à la base de données
// Assurez-vous que ce chemin est correct pour votre structure de projet
require_once('../../../fonctions/database.php'); // <-- ASSUREZ-VOUS QUE CE CHEMIN EST CORRECT ET LE FICHIER EXISTE

// Inclure l'en-tête de l'administration (qui contient doctype, head, body ouvrant)
require_once('../includes/header.php'); // Chemin correct

// Inclure la navigation de l'administration (qui affiche le menu)
require_once('../includes/navigation.php'); // Chemin correct

// Récupérer les messages flash depuis la session (si la page de traitement redirige ici)
$errorMessage = $_SESSION['admin_message_error'] ?? null;
$successMessage = $_SESSION['admin_message_success'] ?? null;
unset($_SESSION['admin_message_error'], $_SESSION['admin_message_success']); // Nettoyer après lecture

// --- Logique pour récupérer les utilisateurs ---
$users = []; // Initialiser un tableau vide pour stocker les utilisateurs
try {
    // Assurez-vous que $pdo est votre instance PDO connectée à la base de données
    // établie dans config/database.php
    $sql = "SELECT ID_Utilisateur, Nom, Login_Utilisateur FROM Utilisateurs ORDER BY Nom ASC";
    $stmt = $pdo->query($sql); // Utilisation de query car pas de paramètres dynamiques dans la requête
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Gérer l'erreur de base de données
    $errorMessage = "Erreur lors du chargement des utilisateurs : " . $e->getMessage();
    // Vous pouvez aussi logger l'erreur pour un débogage plus approfondi
    error_log("Database Error: " . $e->getMessage());
}

// Liste des profils proposés
$profilsProposes = [
    'SERVICE DU SYSTEME DINFORMATION' => 'SERVICE DU SYSTEME DINFORMATION',
	'INFORMATIQUE' => 'INFORMATIQUE',
    'CHEF DE BUREAU SYSTEME, RESEAU ET MAINTENANCE' => 'CHEF DE BUREAU SYSTEME, RESEAU ET MAINTENANCE',
    'CHEF SERVICE DU SYSTEME DINFORMATION' => 'CHEF SERVICE DU SYSTEME DINFORMATION',
    'CHEF BUREAU DU COURRIER' => 'CHEF BUREAU DU COURRIER',
    'CHEF SERVICE DES RESSOURCES HUMAINES' => 'CHEF SERVICE DES RESSOURCES HUMAINES',
    'CHEF SERVICE ET RECOUVREMENT DES CREANCES' => 'CHEF SERVICE ET RECOUVREMENT DES CREANCES',
    'CHARGEE DU RECOUVREMENT ET CORPORATE' => 'CHARGEE DU RECOUVREMENT ET CORPORATE',
    'CHEF SERVICE DE LA TRESORERIE' => 'SERVICE DE LA TRESORERIE',
    'SERVICE DE LA COMPTABILITE GENERALE & FISCALITE' => 'SERVICE DE LA COMPTABILITE GENERALE & FISCALITE',
    'DIRECTEUR FINANCIER ET COMPTABLE' => 'DIRECTEUR FINANCIER ET COMPTABLE',
    'CHEF SERVICE COURRIER ET ARCHIVES' => 'CHEF SERVICE COURRIER ET ARCHIVES',
    'RECOUVREMENT DU PORTEFEUILLE PARTICULIERS' => 'RECOUVREMENT DU PORTEFEUILLE PARTICULIERS',
    'CHEF SERVICE DE LA COMPTABILITE GENERALE' => 'SERVICE DE LA COMPTABILITE GENERALE',
    'SERVICE DU BUDGET' => 'SERVICE DU BUDGET',
    'DIRECTEUR DE LEXPLOITATION' => 'DIRECTEUR DE LEXPLOITATION',
    'DIRECTEUR GENERAL ADJOINT' => 'DIRECTEUR GENERAL ADJOINT',
    'AFFAIRES JURIDIQUES' => 'AFFAIRES JURIDIQUES',
    'DIRECTEUR REGIONAL DU CENTRE SUD EST' => 'DIRECTEUR REGIONAL DU CENTRE SUD EST',
    'RESPONSABLE MARKETING ET COMMERCIAL' => 'RESPONSABLE MARKETING ET COMMERCIAL',
    'RECLAMATION' => 'RECLAMATION',
    'DRISK' => 'DRISK',
    'DEPARTEMENT DU CONTROLE PERMANENT' => 'DEPARTEMENT DU CONTROLE PERMANENT',
    'COMPTABILITE CLIENT' => 'COMPTABILITE CLIENT',
    'AFFAIRES INSTITUTIONNELLES' => 'AFFAIRES INSTITUTIONNELLES',
    'AGENCE DE YAOUNDE' => 'AGENCE DE YAOUNDE',
    'ANALYSTE' => 'ANALYSTE',
    'COMPTABILITE CLIENT' => 'COMPTABILITE CLIENT',
    'Assistant Comptable principal' => 'Assistant Comptable principal'
];

// Le HTML de la page spécifique commence ici (contenu principal)
// Le DOCTYPE, <html>, <head>, et <body> ouvrant sont dans header.php
// La navigation est déjà incluse.
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 page-content-admin">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Ajouter un Profil</h1>
    </div>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($errorMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($successMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-6">
            <?php if (empty($users)): ?>
                <div class="alert alert-warning">Aucun utilisateur trouvé pour associer un profil.</div>
            <?php else: ?>
                <form method="post" action="enregistrer_profil.php">
                    <?php if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); } ?>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="form-group mb-3">
                        <label for="utilisateur_id" class="form-label">Associer à l'utilisateur</label>
                        <select class="form-control" id="utilisateur_id" name="utilisateur_id" required>
                            <option value="">-- Sélectionnez un utilisateur --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= htmlspecialchars($user['ID_Utilisateur']) ?>">
                                    <?= htmlspecialchars($user['Nom']) ?> (<?= htmlspecialchars($user['Login_Utilisateur']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php
                        // Note: Si votre table Profils doit avoir ID_Utilisateur UNIQUE
                        // comme discuté précédemment, vous pourriez vouloir filtrer cette liste
                        // pour n'afficher que les utilisateurs qui n'ont PAS ENCORE de profil.
                        // Cela nécessite une requête plus complexe (ex: LEFT JOIN et WHERE IS NULL).
                        ?>
                    </div>

                    <div class="form-group mb-3">
                        <label for="nom_profil" class="form-label">Nom du Profil</label>
                        <input type="text" class="form-control" id="nom_profil" name="nom_profil" required>
                    </div>

                    <div class="form-group mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Ajouter le Profil</button>
                    <a href="index.php" class="btn btn-secondary">Annuler</a>
                </form>
            <?php endif; ?>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Profils Proposés</h5>
                </div>
                <div class="card-body">
                    <div class="form-group mb-3">
                        <label for="profils_proposes" class="form-label">Sélectionnez un profil</label>
                        <select class="form-control" id="profils_proposes" onchange="copyToClipboard(this)">
                            <option value="">-- Sélectionnez un profil --</option>
                            <?php foreach ($profilsProposes as $profil): ?>
                                <option value="<?= htmlspecialchars($profil) ?>">
                                    <?= htmlspecialchars($profil) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label for="profil_copie" class="form-label">Profil Copié</label>
                        <input type="text" class="form-control" id="profil_copie" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function copyToClipboard(selectElement) {
    var profil = selectElement.value;
    document.getElementById('profil_copie').value = profil;
    document.getElementById('nom_profil').value = profil;
}
</script>

<?php
// Inclure le pied de page de l'administration (qui ferme body, html et inclut JS)
require_once('../includes/footer.php'); // Chemin correct
?>
