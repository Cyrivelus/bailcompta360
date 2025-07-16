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
$titre = 'Modifier le Budget';
$message = $messageType = '';
$budget = $comptes = [];

// Classe utilitaire pour la gestion des budgets
class BudgetManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getBudgetById($id) {
        $stmt = $this->pdo->prepare("
            SELECT b.*, c.Numero_Compte, c.Nom_Compte, c.Type_Compte as Type_Compte_Associe
            FROM Budget b
            JOIN Comptes_compta c ON b.ID_Compte = c.ID_Compte
            WHERE b.ID_Budget = :id
        ");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateBudget($data, $userId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE Budget SET
                    ID_Compte = :id_compte,
                    Annee_Budgetaire = :annee,
                    Montant_Budgetise = :montant,
                    Type_Budget = :type,
                    Description_Budget = :description,
                    Date_Mise_a_Jour = GETDATE(),
                    ID_Utilisateur = :user_id
                WHERE ID_Budget = :id
            ");

            $params = [
                ':id' => $data['id_budget'],
                ':id_compte' => $data['id_compte'],
                ':annee' => $data['annee_budgetaire'],
                ':montant' => $data['montant_budgetise'],
                ':type' => $data['type_budget'],
                ':description' => $data['description'],
                ':user_id' => $userId
            ];

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }

            if ($stmt->execute()) {
                logUserActivity("Mise à jour budget ID: {$data['id_budget']} par utilisateur ID: $userId");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            logApplicationError("Erreur PDO mise à jour budget: " . $e->getMessage());
            throw $e;
        }
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

// Récupération de l'ID du budget à modifier
$budgetId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Récupération des données du budget
if ($budgetId > 0) {
    $budget = $budgetManager->getBudgetById($budgetId);
    if (!$budget) {
        header('Location: gerer_budgets.php');
        exit;
    }
} else {
    header('Location: gerer_budgets.php');
    exit;
}

// Récupération des comptes
$comptes = $budgetManager->getAllAccounts();

// Mise à jour du budget
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modifier_budget') {
    $requiredFields = [
        'id_budget' => FILTER_VALIDATE_INT,
        'id_compte' => FILTER_VALIDATE_INT,
        'annee_budgetaire' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 2000, 'max_range' => 2100]],
        'montant_budgetise' => FILTER_VALIDATE_FLOAT,
        'type_budget' => ['filter' => FILTER_VALIDATE_REGEXP, 'options' => ['regexp' => '/^(Revenu|Depense)$/']]
    ];

    $data = filter_input_array(INPUT_POST, $requiredFields);
    $data['description'] = $_POST['description'] ?? '';

    if (in_array(false, $data, true)) {
        $message = "Données invalides. Veuillez vérifier les champs.";
        $messageType = 'danger';
    } else {
        try {
            if ($budgetManager->updateBudget($data, $_SESSION['utilisateur_id'])) {
                $message = "Budget mis à jour avec succès.";
                $messageType = 'success';
                header("Location: gerer_budgets.php");
                exit;
            } else {
                $message = "Erreur lors de la mise à jour du budget.";
                $messageType = 'danger';
            }
        } catch (PDOException $e) {
            $message = "Erreur de base de données : " . $e->getMessage();
            $messageType = 'danger';
        }
    }
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
            <div class="panel-heading">
                <h3 class="panel-title">Modifier le Budget</h3>
            </div>
            <div class="panel-body">
                <form action="edit_budget.php" method="POST">
                    <input type="hidden" name="action" value="modifier_budget">
                    <input type="hidden" name="id_budget" value="<?= $budget['ID_Budget'] ?>">

                    <div class="form-group">
                        <label for="id_compte" class="control-label">Compte Comptable *</label>
                        <select name="id_compte" id="id_compte" class="form-control" required>
                            <option value="">-- Sélectionner un compte --</option>
                            <?php foreach ($comptes as $compte): ?>
                                <option value="<?= $compte['ID_Compte'] ?>" <?= $compte['ID_Compte'] == $budget['ID_Compte'] ? 'selected' : '' ?>>
                                    <?= $compte['Numero_Compte'] ?> - <?= htmlspecialchars($compte['Nom_Compte']) ?> (<?= $compte['Type_Compte'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="annee_budgetaire" class="control-label">Année Budgétaire *</label>
                                <input type="number" class="form-control" id="annee_budgetaire"
                                       name="annee_budgetaire" min="2000" max="2100" value="<?= $budget['Annee_Budgetaire'] ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="montant_budgetise" class="control-label">Montant *</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="form-control" id="montant_budgetise"
                                           name="montant_budgetise" value="<?= $budget['Montant_Budgetise'] ?>" required>
                                    <span class="input-group-addon">FCFA</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="type_budget" class="control-label">Type de Budget *</label>
                        <select name="type_budget" id="type_budget" class="form-control" required>
                            <option value="">-- Choisir le type --</option>
                            <option value="Revenu" <?= $budget['Type_Budget'] === 'Revenu' ? 'selected' : '' ?>>Revenu (Produit)</option>
                            <option value="Depense" <?= $budget['Type_Budget'] === 'Depense' ? 'selected' : '' ?>>Dépense (Charge)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="description" class="control-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($budget['Description_Budget'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
    <a href="gerer_budgets.php" class="btn btn-default">Annuler</a>
    <button type="button" class="btn btn-info" onclick="history.back()">Retour</button>
</div>
                </form>
            </div>
        </div>
    </div>

    <?php require_once '../../templates/footer.php'; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
	<script src="js/jquery-3.7.1.js"></script>
	 <script src="../js/bootstrap.min.js"></script>
</body>
</html>
