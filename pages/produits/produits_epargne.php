<?php
// pages/produits_bancaires/produits_epargne.php

/**
 * Cette page affiche la liste de tous les produits bancaires de type "épargne".
 * Elle permet de consulter les détails, modifier ou supprimer un produit.
 */

// 1. Démarrer la session en premier.
session_start();

// 2. Inclure les fichiers de logique et de base de données.
require_once '../../database.php';
require_once '../../fonctions/gestion_produits_bancaires.php';

$message = '';
$message_type = '';
$produits_epargne = [];

// Définir la fonction listerProduitsParType() ici ou dans gestion_produits_bancaires.php
// Il est préférable qu'elle soit dans un fichier de fonctions séparé.
// Pour ce cas, on la définit directement pour montrer la solution, mais déplacez-la dans son fichier dédié.
function listerProduitsParType(PDO $pdo, string $type_produit): array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM Produits_Bancaires WHERE type_produit = :type_produit AND statut = 'actif'");
        $stmt->bindParam(':type_produit', $type_produit, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erreur lors du listage des produits par type : " . $e->getMessage());
        return [];
    }
}

try {
    // Récupérer uniquement les produits de type 'epargne'
    $produits_epargne = listerProduitsParType($pdo, 'epargne');

    // Gérer la suppression si le formulaire est soumis
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
        $id_produit_a_supprimer = intval($_POST['id_produit']);
        if (supprimerProduitBancaire($pdo, $id_produit_a_supprimer)) {
            $message = "Le produit d'épargne a été supprimé avec succès.";
            $message_type = 'success';
            // Recharger la liste après la suppression
            $produits_epargne = listerProduitsParType($pdo, 'epargne');
        } else {
            throw new Exception("Échec de la suppression du produit. Il est peut-être lié à des comptes existants.");
        }
    }
} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
}

// 3. Inclure les fichiers de gabarit (templates) après l'exécution de la logique.
include '../../templates/header.php';
include '../../templates/navigation.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Produits d'Épargne</h2>
        <a href="ajouter_produit.php?type=epargne" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Ajouter un produit d'épargne
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <?php if (!empty($produits_epargne)): ?>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Nom du Produit</th>
                        <th>Taux d'Intérêt</th>
                        <th>Frais de Gestion</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produits_epargne as $produit): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($produit['nom_produit']); ?></td>
                            <td><?php echo number_format($produit['taux_interet'], 2, ',', ' '); ?> %</td>
                            <td><?php echo number_format($produit['frais_gestion'], 2, ',', ' '); ?></td>
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
                Aucun produit d'épargne n'a été trouvé.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
// Inclure le footer de la page
include '../../templates/footer.php'; 
?>