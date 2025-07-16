<?php
session_start();

// Check user authentication and authorization
// Only users with appropriate roles (e.g., Administrator, Manager, Accountant) should create budgets
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Manager', 'Comptable'])) {
    header('Location: ../login.php'); // Redirect to login or an unauthorized page
    exit;
}

require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_logs.php'; // For logging

$titre = 'Créer une Entrée Budgétaire';


$message = '';
$messageType = '';

// Fetch all accounts to link budget items to
$comptes = [];
try {
    $stmt = $pdo->query("SELECT ID_Compte, Numero_Compte, Nom_Compte, Type_Compte FROM Comptes_compta ORDER BY Numero_Compte ASC");
    $comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logApplicationError("Erreur PDO lors du chargement des comptes pour le budget: " . $e->getMessage());
    $message = "Erreur lors du chargement des comptes comptables.";
    $messageType = 'danger';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idCompte = $_POST['id_compte'] ?? null;
    $anneeBudgetaire = $_POST['annee_budgetaire'] ?? null;
    $montantBudgetise = $_POST['montant_budgetise'] ?? null;
    $typeBudget = $_POST['type_budget'] ?? null; // 'Revenue' or 'Expense' for clarity
    $description = $_POST['description'] ?? null;

    // Basic Validation
    if (empty($idCompte) || !is_numeric($idCompte)) {
        $message .= "Veuillez sélectionner un compte valide.<br>";
    }
    if (empty($anneeBudgetaire) || !is_numeric($anneeBudgetaire) || $anneeBudgetaire < 2000 || $anneeBudgetaire > 2100) {
        $message .= "Veuillez saisir une année budgétaire valide.<br>";
    }
    if (empty($montantBudgetise) || !is_numeric($montantBudgetise) || $montantBudgetise < 0) {
        $message .= "Veuillez saisir un montant budgétisé positif.<br>";
    }
    if (empty($typeBudget) || !in_array($typeBudget, ['Revenu', 'Depense'])) { // Match your Type_Compte values
        $message .= "Veuillez spécifier le type de budget (Revenu/Dépense).<br>";
    }
    // Description can be optional

    if (empty($message)) {
        try {
            // Check if a budget for this account and year already exists
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM Budget WHERE ID_Compte = :id_compte AND Annee_Budgetaire = :annee_budgetaire");
            $stmtCheck->bindParam(':id_compte', $idCompte, PDO::PARAM_INT);
            $stmtCheck->bindParam(':annee_budgetaire', $anneeBudgetaire, PDO::PARAM_INT);
            $stmtCheck->execute();

            if ($stmtCheck->fetchColumn() > 0) {
                $message = "Un budget existe déjà pour ce compte et cette année. Veuillez modifier l'entrée existante ou choisir un autre compte/année.";
                $messageType = 'warning';
            } else {
                // Determine budget type based on account type if not explicitly provided or for consistency
                $stmtAccountType = $pdo->prepare("SELECT Type_Compte FROM Comptes_compta WHERE ID_Compte = :id_compte");
                $stmtAccountType->bindParam(':id_compte', $idCompte, PDO::PARAM_INT);
                $stmtAccountType->execute();
                $accountType = $stmtAccountType->fetchColumn();

                if (($accountType == 'Revenu' && $typeBudget != 'Revenu') || ($accountType == 'Depense' && $typeBudget != 'Depense')) {
                     // Optionally, enforce consistency: warn or prevent if Type_Compte doesn't match budget type
                    // For now, we'll use the user-selected $typeBudget but it's important to consider this.
                    // A better approach might be to auto-set $typeBudget based on $accountType.
                }

                $stmt = $pdo->prepare("
                    INSERT INTO Budget (ID_Compte, Annee_Budgetaire, Montant_Budgetise, Type_Budget, Description_Budget, Date_Creation, ID_Utilisateur)
                    VALUES (:id_compte, :annee_budgetaire, :montant_budgetise, :type_budget, :description, GETDATE(), :id_utilisateur)
                "); // Using GETDATE() for SQL Server to get current timestamp

                $stmt->bindParam(':id_compte', $idCompte, PDO::PARAM_INT);
                $stmt->bindParam(':annee_budgetaire', $anneeBudgetaire, PDO::PARAM_INT);
                $stmt->bindParam(':montant_budgetise', $montantBudgetise); // PDO will handle float/decimal
                $stmt->bindParam(':type_budget', $typeBudget);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':id_utilisateur', $_SESSION['user_id'], PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $message = "Entrée budgétaire créée avec succès.";
                    $messageType = 'success';
                    logUserActivity("Création d'une entrée budgétaire pour le compte ID: " . $idCompte . ", Année: " . $anneeBudgetaire . " par l'utilisateur ID: " . $_SESSION['user_id'] . ".");
                    // Clear form fields after successful submission if desired
                    $_POST = [];
                } else {
                    $message = "Erreur lors de la création de l'entrée budgétaire.";
                    $messageType = 'danger';
                    logApplicationError("Erreur lors de l'exécution de l'INSERT pour le budget: " . json_encode($stmt->errorInfo()));
                }
            }
        } catch (PDOException $e) {
            logApplicationError("Erreur PDO lors de la création du budget: " . $e->getMessage());
            $message = "Erreur de base de données: " . $e->getMessage();
            $messageType = 'danger';
        }
    } else {
        $messageType = 'danger'; // Set message type for accumulated errors
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?= htmlspecialchars($titre) ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
	
</head>
<body>
<?php require_once '../../templates/header.php'; ?>
    <?php require_once '../../templates/navigation.php'; ?>
    <div class="container">
        <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Informations sur la Nouvelle Entrée Budgétaire</h3>
            </div>
            <div class="panel-body">
                <form action="" method="POST">
                    <div class="form-group">
                        <label for="id_compte">Compte Comptable :</label>
                        <select name="id_compte" id="id_compte" class="form-control" required>
                            <option value="">-- Sélectionner un compte --</option>
                            <?php foreach ($comptes as $compte): ?>
                                <option value="<?= htmlspecialchars($compte['ID_Compte']) ?>"
                                    <?= (isset($_POST['id_compte']) && $_POST['id_compte'] == $compte['ID_Compte']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($compte['Numero_Compte']) ?> - <?= htmlspecialchars($compte['Nom_Compte']) ?> (<?= htmlspecialchars($compte['Type_Compte']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="annee_budgetaire">Année Budgétaire :</label>
                        <input type="number" class="form-control" id="annee_budgetaire" name="annee_budgetaire"
                               value="<?= htmlspecialchars($_POST['annee_budgetaire'] ?? date('Y')) ?>" min="2000" max="2100" required>
                    </div>

                    <div class="form-group">
                        <label for="montant_budgetise">Montant Budgétisé :</label>
                        <input type="number" step="0.01" class="form-control" id="montant_budgetise" name="montant_budgetise"
                               value="<?= htmlspecialchars($_POST['montant_budgetise'] ?? '') ?>" placeholder="Ex: 1500.50" required>
                    </div>

                    <div class="form-group">
                        <label for="type_budget">Type de Budget :</label>
                        <select name="type_budget" id="type_budget" class="form-control" required>
                            <option value="">-- Choisir le type --</option>
                            <option value="Revenu" <?= (isset($_POST['type_budget']) && $_POST['type_budget'] == 'Revenu') ? 'selected' : '' ?>>Revenu (Produit)</option>
                            <option value="Depense" <?= (isset($_POST['type_budget']) && $_POST['type_budget'] == 'Depense') ? 'selected' : '' ?>>Dépense (Charge)</option>
                            </select>
                    </div>

                    <div class="form-group">
                        <label for="description">Description (Optionnel) :</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                                  placeholder="Brève description du budget..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-success">
                        <span class="glyphicon glyphicon-plus"></span> Créer le Budget
                    </button>
                    <a href="manage_budgets.php" class="btn btn-default">
                        <span class="glyphicon glyphicon-list"></span> Gérer les Budgets
                    </a>
                </form>
            </div>
        </div>
    </div>
    <?php require_once '../../templates/footer.php'; ?>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
	<script src="js/jquery-3.7.1.js"></script>
	 <script src="../js/bootstrap.min.js"></script>
</body>
</html>