<?php
// pages/comptes/creer_compte.php

/**
 * Ce fichier gère la création d'un nouveau compte bancaire.
 * Il affiche un formulaire pour sélectionner le client, le type de compte,
 * et le solde initial.
 */

require_once '../../database.php';
require_once '../../fonctions/gestion_comptes.php';
require_once '../../fonctions/gestion_clients.php';
require_once '../../fonctions/gestion_produits_bancaires.php';

$message = '';
$message_type = '';
$clients = [];
$produits_comptes = [];

try {
    // Récupérer la liste des clients pour le champ de sélection
    $clients = listerClients($pdo);

    // Récupérer la liste des produits de type "compte" pour le champ de sélection
    $produits_comptes = listerProduitsComptes($pdo);

    // Si un ID de client est passé dans l'URL, le présélectionner
    $client_preselectionne_id = isset($_GET['id_client']) ? intval($_GET['id_client']) : null;

    // Traiter la soumission du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validation des données
        $id_client = intval($_POST['id_client']);
        $id_produit = intval($_POST['id_produit']);
        $solde_initial = floatval($_POST['solde_initial']);

        if (empty($id_client) || empty($id_produit)) {
            throw new Exception("Veuillez sélectionner un client et un type de compte.");
        }
        if ($solde_initial < 0) {
            throw new Exception("Le solde initial ne peut pas être négatif.");
        }

        // Création du compte
        if (creerCompte($pdo, $id_client, $id_produit, $solde_initial)) {
            $message = "Le compte a été créé avec succès avec un solde de " . number_format($solde_initial, 2, ',', ' ') . ".";
            $message_type = 'success';
        } else {
            throw new Exception("Une erreur est survenue lors de la création du compte.");
        }
    }
} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
}

// Inclure le header de la page (début du HTML)
include '../../templates/header.php'; 
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Créer un Nouveau Compte</h2>
        <a href="liste_comptes.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour à la liste des comptes
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card p-4 shadow-sm">
        <form action="creer_compte.php" method="post">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="id_client" class="form-label">Client <span class="text-danger">*</span></label>
                    <select class="form-select" id="id_client" name="id_client" required>
                        <option value="">Sélectionnez un client</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo htmlspecialchars($client['id_client']); ?>"
                                <?php echo ($client_preselectionne_id == $client['id_client']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenoms']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="id_produit" class="form-label">Type de Compte <span class="text-danger">*</span></label>
                    <select class="form-select" id="id_produit" name="id_produit" required>
                        <option value="">Sélectionnez un type</option>
                        <?php foreach ($produits_comptes as $produit): ?>
                            <option value="<?php echo htmlspecialchars($produit['id_produit']); ?>">
                                <?php echo htmlspecialchars($produit['nom_produit']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="solde_initial" class="form-label">Solde Initial <span class="text-danger">*</span></label>
                <input type="number" step="0.01" class="form-control" id="solde_initial" name="solde_initial" value="0.00" required>
            </div>
            
            <button type="submit" class="btn btn-primary mt-3">
                <i class="fas fa-plus-circle"></i> Créer le compte
            </button>
        </form>
    </div>
</div>

<?php
// NOTE: Fonctions à implémenter dans les modules respectifs

function listerClients(PDO $pdo): array {
    // Stub: Récupère une liste de clients (ID, Nom, Prénoms) depuis la base de données.
    return [
        ['id_client' => 123, 'nom' => 'DION', 'prenoms' => 'Benoît'],
        ['id_client' => 456, 'nom' => 'SAMBA', 'prenoms' => 'Marie'],
    ];
}

function listerProduitsComptes(PDO $pdo): array {
    // Stub: Récupère les produits bancaires de type 'compte' (par exemple)
    // depuis la table Produits_Bancaires.
    return [
        ['id_produit' => 1, 'nom_produit' => 'Compte Courant'],
        ['id_produit' => 2, 'nom_produit' => 'Compte Épargne'],
    ];
}

function creerCompte(PDO $pdo, int $id_client, int $id_produit, float $solde_initial): bool {
    // Stub: Insère un nouveau compte dans la table 'Comptes'
    // et enregistre le solde initial comme première écriture.
    return true;
}

// Inclure le footer de la page (fin du HTML)
include '../../templates/footer.php';
?>