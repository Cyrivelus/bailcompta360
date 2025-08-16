<?php
// pages/produits_bancaires/produits_credits.php

/**
 * This page displays the list of all "credit" or "loan" type banking products.
 * It allows you to view details, modify, or delete a product.
 */

// 1. Start the session first. It must be the very first command.
session_start();

// 2. Include logic and database files that do not produce any HTML output.
require_once '../../database.php';
require_once '../../fonctions/gestion_produits_bancaires.php';

$message = '';
$message_type = '';
$produits_credits = [];

// The function listerProduitsParType() should be in gestion_produits_bancaires.php.
// Here's an example of its implementation for clarity.
function listerProduitsParType(PDO $pdo, string $type_produit): array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM Produits_Bancaires WHERE type_produit = :type_produit AND statut = 'actif'");
        $stmt->bindParam(':type_produit', $type_produit, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error listing products by type: " . $e->getMessage());
        return [];
    }
}

try {
    // Retrieve only 'credit' or 'loan' type products.
    $produits_credits = listerProduitsParType($pdo, 'credit');

    // Handle deletion if the form is submitted.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
        $id_produit_a_supprimer = intval($_POST['id_produit']);
        if (supprimerProduitBancaire($pdo, $id_produit_a_supprimer)) {
            $message = "The credit product has been successfully deleted.";
            $message_type = 'success';
            // Reload the list after deletion.
            $produits_credits = listerProduitsParType($pdo, 'credit');
        } else {
            throw new Exception("Product deletion failed. It may be linked to existing loans.");
        }
    }
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
    $message_type = 'danger';
}

// 3. Now, include the template files that contain HTML.
include '../../templates/header.php';
include '../../templates/navigation.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Produits de Crédit</h2>
        <a href="ajouter_produit.php?type=credit" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Ajouter un produit de crédit
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <?php if (!empty($produits_credits)): ?>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Nom du Produit</th>
                        <th>Taux d'Intérêt</th>
                        <th>Durée Max</th>
                        <th>Montant Min</th>
                        <th>Montant Max</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produits_credits as $produit): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($produit['nom_produit']); ?></td>
                            <td><?php echo number_format($produit['taux_interet'], 2, ',', ' '); ?> %</td>
                            <td><?php echo htmlspecialchars($produit['duree_max'] ?? 'N/A'); ?> mois</td>
                            <td><?php echo number_format($produit['montant_min'], 2, ',', ' '); ?></td>
                            <td><?php echo number_format($produit['montant_max'], 2, ',', ' '); ?></td>
                            <td>
                                <span class="badge bg-<?php echo ($produit['statut'] == 'actif') ? 'success' : 'danger'; ?>">
                                    <?php echo htmlspecialchars(ucfirst($produit['statut'])); ?>
                                </span>
                            </td>
                            <td>
                                <a href="modifier_produit.php?id=<?php echo urlencode($produit['id_produit']); ?>" class="btn btn-sm btn-info me-2">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>
                                <form action="" method="post" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?');">
                                    <input type="hidden" name="action" value="supprimer">
                                    <input type="hidden" name="id_produit" value="<?php echo htmlspecialchars($produit['id_produit']); ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash-alt"></i> Supprimer
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-warning text-center" role="alert">
                Aucun produit de crédit n'a été trouvé.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
// Include the footer of the page
include '../../templates/footer.php'; 
?>