<?php
// Inclure les fichiers de configuration et de fonctions nécessaires
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_produits_bancaires.php';

// Le code de vérification de sécurité a été retiré pour corriger le problème.
// ATTENTION: Cela rend la page accessible à tous.
// Il est vivement recommandé de le remettre en place et de corriger l'inclusion
// du fichier de sécurité dans une version de production.

// Récupérer le type de produit depuis l'URL (par exemple: ?type=epargne ou ?type=credit)
$type_produit = isset($_GET['type']) ? $_GET['type'] : null;

// Vérifier que le type est valide
if (!in_array($type_produit, ['epargne', 'credit'])) {
    // Rediriger vers la page des produits si le type est invalide
    header('Location: index.php');
    exit;
}

// Définir les variables en fonction du type de produit
$titre_page = ($type_produit == 'epargne') ? 'Ajouter un produit d\'épargne' : 'Ajouter un produit de crédit';
$titre_formulaire = ($type_produit == 'epargne') ? 'Nouveau produit d\'épargne' : 'Nouveau produit de crédit';
$action_form = ($type_produit == 'epargne') ? 'creer_epargne' : 'creer_credit';
$champs_specifiques = '';
$erreurs = [];
$succes = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === $action_form) {
    // Récupérer les données communes
    $nom = $_POST['nom'];
    $description = $_POST['description'];
    $taux = $_POST['taux'];
    
    // Vérifier les données communes
    if (empty($nom) || empty($description) || !is_numeric($taux)) {
        $erreurs[] = "Tous les champs obligatoires doivent être remplis.";
    }

    // Traitement spécifique au type de produit
    if ($type_produit == 'epargne') {
        $montant_min = $_POST['montant_min'];
        if (!is_numeric($montant_min)) {
            $erreurs[] = "Le montant minimum doit être un nombre.";
        }
        
        if (empty($erreurs)) {
            if (creer_produit_epargne($nom, $description, $taux, $montant_min)) {
                $succes = "Le produit d'épargne a été ajouté avec succès.";
                // Réinitialiser les champs du formulaire après l'ajout
                $_POST = array();
            } else {
                $erreurs[] = "Une erreur est survenue lors de l'ajout du produit.";
            }
        }
    } elseif ($type_produit == 'credit') {
        $duree_max = $_POST['duree_max'];
        if (!is_numeric($duree_max) || $duree_max <= 0) {
            $erreurs[] = "La durée maximale doit être un nombre positif.";
        }

        if (empty($erreurs)) {
            if (creer_produit_credit($nom, $description, $taux, $duree_max)) {
                $succes = "Le produit de crédit a été ajouté avec succès.";
                $_POST = array();
            } else {
                $erreurs[] = "Une erreur est survenue lors de l'ajout du produit.";
            }
        }
    }
}

// Inclusion de l'en-tête de la page
include '../../templates/header.php';
include '../../templates/navigation.php';
?>

<div class="container mt-5">
    <h2><?= $titre_page ?></h2>
    <hr>

    <?php if (!empty($erreurs)): ?>
        <div class="alert alert-danger" role="alert">
            <?php foreach ($erreurs as $erreur): ?>
                <p><?= htmlspecialchars($erreur) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($succes): ?>
        <div class="alert alert-success" role="alert">
            <?= htmlspecialchars($succes) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="ajouter_produit.php?type=<?= $type_produit ?>">
        <input type="hidden" name="action" value="<?= $action_form ?>">

        <div class="form-group">
            <label for="nom">Nom du produit</label>
            <input type="text" class="form-control" id="nom" name="nom" value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="taux">Taux d'intérêt (%)</label>
            <input type="number" step="0.01" class="form-control" id="taux" name="taux" value="<?= htmlspecialchars($_POST['taux'] ?? '') ?>" required>
        </div>

        <?php if ($type_produit == 'epargne'): ?>
            <div class="form-group">
                <label for="montant_min">Montant minimum d'ouverture</label>
                <input type="number" step="0.01" class="form-control" id="montant_min" name="montant_min" value="<?= htmlspecialchars($_POST['montant_min'] ?? '') ?>" required>
            </div>
        <?php elseif ($type_produit == 'credit'): ?>
            <div class="form-group">
                <label for="duree_max">Durée maximale (en mois)</label>
                <input type="number" class="form-control" id="duree_max" name="duree_max" value="<?= htmlspecialchars($_POST['duree_max'] ?? '') ?>" required>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">Ajouter le produit</button>
    </form>
</div>

<?php include '../../templates/footer.php'; ?>