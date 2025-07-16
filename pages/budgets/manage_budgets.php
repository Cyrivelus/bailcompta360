<?php
session_start();

// Configuration et sécurité
define('ALLOWED_ROLES', ['Admin', 'Manager', 'Comptable']);
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_logs.php';

// Vérification d'authentification et d'autorisation
if (!isset($_SESSION['utilisateur_id'], $_SESSION['role']) || !in_array($_SESSION['role'], ALLOWED_ROLES)) {
    header('Location: ../../login.php');
    exit;
}

// Initialisation des variables
$titre = 'Gérer les Budgets';
$message = $messageType = '';
$budgets = $comptes = [];

// Classe utilitaire pour la gestion des budgets
class BudgetManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function deleteBudget($id, $userId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM Budget WHERE ID_Budget = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                logUserActivity("Suppression du budget ID: $id par l'utilisateur ID: $userId");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            logApplicationError("Erreur PDO suppression budget: " . $e->getMessage());
            throw $e;
        }
    }

    public function getAllBudgets() {
        return $this->pdo->query("
            SELECT b.*, c.Numero_Compte, c.Nom_Compte, c.Type_Compte as Type_Compte_Associe,
                   u.Nom as nom_utilisateur
            FROM Budget b
            JOIN Comptes_compta c ON b.ID_Compte = c.ID_Compte
            LEFT JOIN Utilisateurs u ON b.ID_Utilisateur = u.ID_Utilisateur
            ORDER BY b.Annee_Budgetaire DESC, c.Numero_Compte ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllAccounts() {
        return $this->pdo->query("
            SELECT ID_Compte, Numero_Compte, Nom_Compte, Type_Compte
            FROM Comptes_compta
            ORDER BY Numero_Compte ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Traitement des actions
$budgetManager = new BudgetManager($pdo);

// Suppression
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'supprimer') {
    try {
        if ($budgetManager->deleteBudget($_GET['id'], $_SESSION['utilisateur_id'])) {
            $message = "Budget supprimé avec succès.";
            $messageType = 'success';
            // Redirection pour éviter la resoumission du formulaire
            header("Location: ".strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        } else {
            $message = "Erreur lors de la suppression du budget.";
            $messageType = 'danger';
        }
    } catch (PDOException $e) {
        $message = "Erreur de base de données : " . $e->getMessage();
        $messageType = 'danger';
    }
}

// Récupération des données
try {
    $budgets = $budgetManager->getAllBudgets();
    $comptes = $budgetManager->getAllAccounts();
} catch (PDOException $e) {
    $message = "Erreur lors du chargement des données : " . $e->getMessage();
    $messageType = 'danger';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BailCompta 360 | <?= htmlspecialchars($titre) ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/tableau.css">
	 <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        .modifier-btn, .supprimer-btn {
            min-width: 70px;
            margin: 2px;
        }
        .table-actions {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <?php require_once '../../templates/header.php'; ?>
    <?php require_once '../../templates/navigation.php'; ?>

    <div class="container">
        <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade in" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="panel panel-default">
            <div class="panel-heading clearfix">
                <h3 class="panel-title pull-left">Liste des Budgets</h3>
                <a href="create_budget.php" class="btn btn-success btn-sm pull-right">
                    <span class="glyphicon glyphicon-plus"></span> Nouveau budget
                </a>
            </div>

            <div class="panel-body">
                <?php if (empty($budgets)): ?>
                    <div class="alert alert-info">Aucun budget n'a été créé pour l'instant.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-bordered">
                            <thead>
                                <tr class="active">
                                    <th>ID</th>
                                    <th>Compte</th>
                                    <th>Année</th>
                                    <th class="text-right">Montant</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Créé le</th>
                                    <th>Mis à jour</th>
                                    <th>Créé par</th>
                                    <th class="text-center table-actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($budgets as $budget): ?>
                                    <tr>
                                        <td><?= $budget['ID_Budget'] ?></td>
                                        <td>
                                            <small class="text-muted"><?= $budget['Numero_Compte'] ?></small><br>
                                            <?= htmlspecialchars($budget['Nom_Compte']) ?>
                                        </td>
                                        <td><?= $budget['Annee_Budgetaire'] ?></td>
                                        <td class="text-right"><?= number_format($budget['Montant_Budgetise'], 2, ',', ' ') ?></td>
                                        <td>
                                            <span class="label label-<?= $budget['Type_Budget'] === 'Revenu' ? 'success' : 'danger' ?>">
                                                <?= $budget['Type_Budget'] ?>
                                            </span>
                                        </td>
                                        <td><?= !empty($budget['Description_Budget']) ? htmlspecialchars($budget['Description_Budget']) : '<em class="text-muted">N/A</em>' ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($budget['Date_Creation'])) ?></td>
                                        <td><?= $budget['Date_Mise_a_Jour'] ? date('d/m/Y H:i', strtotime($budget['Date_Mise_a_Jour'])) : '<em class="text-muted">N/A</em>' ?></td>
                                        <td><?= htmlspecialchars($budget['nom_utilisateur'] ?? 'Inconnu') ?></td>
                                        <td class="text-center table-actions">
                                            <a href="edit_budget.php?id=<?= $budget['ID_Budget'] ?>" class="btn btn-xs btn-primary">
                                                <span class="glyphicon glyphicon-edit"></span> Modifier
                                            </a>
                                            <a href="?action=supprimer&id=<?= $budget['ID_Budget'] ?>"
                                               class="btn btn-xs btn-danger supprimer-btn"
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce budget ?');">
                                                <span class="glyphicon glyphicon-trash"></span> Supprimer
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php require_once '../../templates/footer.php'; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
	<script src="js/jquery-3.7.1.js"></script>
	 <script src="../js/bootstrap.min.js"></script>
    <script>
    $(document).ready(function() {
        // Confirmation de suppression
        $(document).on('click', '.supprimer-btn', function(e) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer ce budget ?')) {
                e.preventDefault();
            }
        });
    });
    </script>
</body>
</html>
