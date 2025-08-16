<?php
// pages/agence/transactions_caisse.php

// Démarrer la session en premier
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Inclure la connexion à la base de données
require_once '../../fonctions/database.php';

// Inclure les fonctions
require_once '../../fonctions/gestion_operations_caisse.php';

// Vérification de l'authentification et du rôle
// Assurez-vous que le nom de la variable de session pour le rôle est correct
// J'ai utilisé 'utilisateur_role' car c'est plus clair
if (!isset($_SESSION['utilisateur_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Caissiere')) {
    header("Location: ../../index.php");
    exit();
}

$id_utilisateur = $_SESSION['utilisateur_id'];

// Initialisation cruciale de $mouvement_en_cours
// C'est la ligne qui manquait ou qui était mal placée
$mouvement_en_cours = getCaisseOuverte($pdo, $id_utilisateur);

// Si aucune caisse n'est ouverte, on redirige avec un message d'erreur
if (!$mouvement_en_cours) {
    $_SESSION['admin_message_error'] = "Aucune caisse n'est ouverte. Veuillez d'abord ouvrir une caisse.";
    header("Location: gestion_caisses.php");
    exit();
}

// Les variables sont maintenant correctement définies
$id_mouvement_caisse = $mouvement_en_cours['id_mouvement_caisse'];
$solde_courant = calculerSoldeCourant($pdo, $id_mouvement_caisse);

// Initialisation des variables pour la vue
$message = $_SESSION['admin_message_success'] ?? ($_SESSION['admin_message_error'] ?? '');
$message_type = isset($_SESSION['admin_message_success']) ? 'success' : (isset($_SESSION['admin_message_error']) ? 'danger' : '');
unset($_SESSION['admin_message_success'], $_SESSION['admin_message_error']);

// Traitement de l'ajout d'une transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter_transaction') {
    $montant = floatval($_POST['montant'] ?? 0);
    $type = htmlspecialchars($_POST['type'] ?? '');
    $description = htmlspecialchars($_POST['description'] ?? '');

    // Valider les données
    if ($montant <= 0 || !in_array($type, ['Entree', 'Sortie'])) {
        $_SESSION['admin_message_error'] = "Le montant doit être supérieur à zéro et le type doit être valide.";
    } elseif ($type === 'Sortie' && $montant > $solde_courant) {
        $_SESSION['admin_message_error'] = "Fonds insuffisants. La transaction ne peut pas être effectuée.";
    } else {
        // Enregistrer la transaction dans la base de données
        try {
            $query = "INSERT INTO Transactions_Caisse (id_mouvement_caisse, date_transaction, montant, type, description)
                      VALUES (?, NOW(), ?, ?, ?)";
            $stmt = $pdo->prepare($query);
            
            if ($stmt->execute([$id_mouvement_caisse, $montant, $type, $description])) {
                $_SESSION['admin_message_success'] = "Transaction de " . number_format($montant, 2, ',', ' ') . " F CFA ajoutée avec succès.";
            } else {
                $_SESSION['admin_message_error'] = "Échec de l'enregistrement de la transaction.";
            }
        } catch (PDOException $e) {
            error_log("Erreur PDO lors de l'ajout de transaction : " . $e->getMessage());
            $_SESSION['admin_message_error'] = "Erreur interne de base de données. Veuillez réessayer.";
        }
    }
    // Redirection après le traitement POST
    header("Location: transactions_caisse.php");
    exit();
}

// Récupérer toutes les transactions du mouvement de caisse en cours pour l'affichage
try {
    $transactions = [];
    $query = "SELECT * FROM Transactions_Caisse WHERE id_mouvement_caisse = ? ORDER BY date_transaction DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$id_mouvement_caisse]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur PDO lors de la récupération des transactions : " . $e->getMessage());
    $message = "Erreur de base de données : impossible de charger les transactions.";
    $message_type = 'danger';
}

// Inclure les fichiers de vue (HTML)
include '../../templates/header.php';
include '../../templates/navigation.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Transactions de la Caisse</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card p-4 shadow-sm mb-4">
        <h4>Solde Courant : <span class="badge bg-primary"><?= number_format($solde_courant, 2, ',', ' '); ?> F CFA</span></h4>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card p-4 shadow-sm">
                <h5>Ajouter une Transaction</h5>
                <form action="transactions_caisse.php" method="post">
                    <input type="hidden" name="action" value="ajouter_transaction">
                    <div class="mb-3">
                        <label for="montant" class="form-label">Montant</label>
                        <input type="number" step="0.01" class="form-control" id="montant" name="montant" required>
                    </div>
                    <div class="mb-3">
                        <label for="type" class="form-label">Type de Transaction</label>
                        <select class="form-control" id="type" name="type" required>
                            <option value="Entree">Entrée</option>
                            <option value="Sortie">Sortie</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Enregistrer la transaction</button>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card p-4 shadow-sm">
                <h5>Historique des Transactions de la Journée</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Date & Heure</th>
                                <th>Montant</th>
                                <th>Type</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($transactions) > 0): ?>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i:s', strtotime($transaction['date_transaction'])); ?></td>
                                        <td class="<?= ($transaction['type'] === 'Entree') ? 'text-success' : 'text-danger'; ?>">
                                            <?= number_format($transaction['montant'], 2, ',', ' '); ?> F CFA
                                        </td>
                                        <td><?= htmlspecialchars($transaction['type']); ?></td>
                                        <td><?= htmlspecialchars($transaction['description']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">Aucune transaction enregistrée pour l'instant.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Inclure le footer de la page
include '../../templates/footer.php';
?>