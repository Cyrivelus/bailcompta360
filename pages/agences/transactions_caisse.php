<?php
// pages/agences/transactions_caisse.php

// 1. Démarrer la session en premier
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Inclure la connexion à la base de données et les modules de fonctions
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_operations_caisse.php';
require_once '../../fonctions/gestion_agences.php'; // Inclure pour l'id_agence et le nom de l'utilisateur

// 3. Vérification de l'authentification et du rôle
if (!isset($_SESSION['utilisateur_id'])) {
    header("Location: ../../index.php");
    exit();
}

$id_utilisateur = $_SESSION['utilisateur_id'];
$id_agence = $_SESSION['id_agence'] ?? null;
$nom_utilisateur = $_SESSION['nom_utilisateur'] ?? null; // Assurez-vous d'avoir stocké le nom dans la session

// Récupérer le nom de l'utilisateur si non disponible dans la session
if (!$nom_utilisateur) {
    $stmt_user = $pdo->prepare("SELECT Nom_Utilisateur FROM utilisateurs WHERE ID_Utilisateur = ?");
    $stmt_user->execute([$id_utilisateur]);
    $nom_utilisateur = $stmt_user->fetchColumn();
    $_SESSION['nom_utilisateur'] = $nom_utilisateur;
}

// Vérifier si une caisse est ouverte pour l'utilisateur
$mouvement_en_cours = getCaisseOuverte($pdo, $id_utilisateur);

if (!$mouvement_en_cours) {
    $_SESSION['admin_message_error'] = "Vous devez d'abord ouvrir une caisse pour effectuer des transactions.";
    header("Location: gestion_caisses.php");
    exit();
}

// 4. Traitement des actions POST (Ajout de transaction)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $type = $_POST['type'] ?? '';
        $montant_saisi = $_POST['montant'] ?? ''; // Récupère la valeur en tant que string
        $description = trim($_POST['description'] ?? '');
        $compte_contrepartie = $_POST['compte_contrepartie'] ?? '';

        // Validation et conversion du montant
        // On utilise is_numeric() pour vérifier si la chaîne est un nombre
        if (!is_numeric($montant_saisi) || $montant_saisi <= 0) {
            $_SESSION['admin_message_error'] = "Le montant doit être un nombre positif.";
        } elseif (empty($description) || empty($compte_contrepartie)) {
            $_SESSION['admin_message_error'] = "Veuillez remplir tous les champs correctement.";
        } elseif ($type !== 'Entree' && $type !== 'Sortie') {
            $_SESSION['admin_message_error'] = "Type de transaction invalide.";
        } else {
            // Conversion en float ici, après validation
            $montant = floatval($montant_saisi);

            // Appel de la fonction avec les bons types de données
            ajouterTransactionCaisse($pdo, $mouvement_en_cours['id_mouvement_caisse'], $montant, $type, $description, $id_agence, $nom_utilisateur, $compte_contrepartie);

            $_SESSION['admin_message_success'] = "Transaction de " . number_format($montant, 2, ',', ' ') . " F CFA ajoutée avec succès.";
        }
    } catch (Exception $e) {
        $_SESSION['admin_message_error'] = "Erreur : " . $e->getMessage();
    }
    header("Location: transactions_caisse.php");
    exit();
}

// 5. Préparation des données pour l'affichage
$message = $_SESSION['admin_message_success'] ?? ($_SESSION['admin_message_error'] ?? '');
$message_type = isset($_SESSION['admin_message_success']) ? 'success' : (isset($_SESSION['admin_message_error']) ? 'danger' : '');
unset($_SESSION['admin_message_success'], $_SESSION['admin_message_error']);

// Récupérer le solde courant
$solde_courant = calculerSoldeCourant($pdo, $mouvement_en_cours['id_mouvement_caisse']);

// Récupérer l'historique des transactions de la caisse ouverte
$historique_transactions = getTransactionsCaisse($pdo, $mouvement_en_cours['id_mouvement_caisse']);

// Récupérer la liste des comptes de caisse et de contrepartie pour le formulaire
$comptes_contrepartie = getComptesContrepartie($pdo);

// 6. Inclure les fichiers de vue (HTML)
include '../../templates/header.php';
include '../../templates/navigation.php';

?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Transactions de Caisse</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card p-4 shadow-sm mb-4">
        <h4>Solde de la Caisse Ouverte</h4>
        <div class="alert alert-info">
            Solde Courant : <strong><?= number_format($solde_courant, 2, ',', ' '); ?> F CFA</strong>
        </div>
    </div>

    <div class="card p-4 shadow-sm mb-4">
        <h4>Enregistrer une Transaction</h4>
        <form action="transactions_caisse.php" method="post">
            <input type="hidden" name="action" value="ajouter_transaction">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="type_transaction" class="form-label">Type de Transaction</label>
                    <select class="form-select" id="type_transaction" name="type" required>
                        <option value="Entree">Entrée</option>
                        <option value="Sortie">Sortie</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="compte_contrepartie" class="form-label">Compte de Contrepartie</label>
                    <select class="form-select" id="compte_contrepartie" name="compte_contrepartie" required>
                        <option value="">Sélectionner un compte</option>
                        <?php foreach ($comptes_contrepartie as $compte): ?>
                            <option value="<?= htmlspecialchars($compte['Numero_Compte']); ?>">
                                <?= htmlspecialchars($compte['Nom_Compte']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label for="montant" class="form-label">Montant</label>
                <input type="number" step="0.01" class="form-control" id="montant" name="montant" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Enregistrer la Transaction</button>
        </form>
    </div>

    <div class="card p-4 shadow-sm">
        <h4>Historique des Transactions de la Caisse Ouverte</h4>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($historique_transactions)): ?>
                        <tr>
                            <td colspan="5" class="text-center">Aucune transaction enregistrée pour cette caisse.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($historique_transactions as $transaction): ?>
                            <tr>
                                <td><?= htmlspecialchars($transaction['id_transaction']); ?></td>
                                <td><?= htmlspecialchars($transaction['date_transaction']); ?></td>
                                <td>
                                    <?php if ($transaction['type'] === 'Entree'): ?>
                                        <span class="badge bg-success">Entrée</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Sortie</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($transaction['description']); ?></td>
                                <td><?= number_format($transaction['montant'], 2, ',', ' '); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include '../../templates/footer.php';
?>