<?php
require_once '../includes/header.php'; // Inclus l'en-tête admin
require_once '../includes/navigation.php'; // Menu de navigation admin
require_once '../../../fonctions/database.php'; // Connexion DB


// Vérifier si l'ID est présent et numérique
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['admin_message_error'] = "ID d'utilisateur invalide.";
    header('Location: index.php');
    exit();
}

$id = (int) $_GET['id'];


// Récupérer les infos actuelles de l'utilisateur
$stmt = $pdo->prepare("SELECT ID_Utilisateur, Nom, Role FROM Utilisateurs WHERE ID_Utilisateur = ?");
$stmt->execute([$id]);
$utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$utilisateur) {
    $_SESSION['admin_message_error'] = "Utilisateur non trouvé.";
    header('Location: index.php');
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nouveauRole = $_POST['role'] ?? '';
    $rolesValid = ['Admin', 'Comptable', 'Invité'];

    if (!in_array($nouveauRole, $rolesValid)) {
        $_SESSION['admin_message_error'] = "Rôle non valide.";
    } else {
        $updateStmt = $pdo->prepare("UPDATE Utilisateurs SET Role = ? WHERE ID_Utilisateur = ?");
        $updateStmt->execute([$nouveauRole, $id]);

        $_SESSION['admin_message_success'] = "Le rôle a été mis à jour avec succès.";
        header('Location: index.php');
        exit();
    }
}
?>

<div class="container mt-5">
    <h2>Modifier le rôle de l'utilisateur</h2>

    <form method="post">
        <div class="mb-3">
            <label for="nom" class="form-label">Nom de l'utilisateur :</label>
            <input type="text" class="form-control" id="nom" value="<?= htmlspecialchars($utilisateur['Nom']) ?>" disabled>
        </div>

        <div class="mb-3">
            <label for="role" class="form-label">Rôle :</label>
            <select name="role" id="role" class="form-control" required>
                <?php
                $roles = ['Admin', 'Comptable', 'Invité'];
                foreach ($roles as $role) {
                    $selected = $utilisateur['Role'] === $role ? 'selected' : '';
                    echo "<option value=\"$role\" $selected>$role</option>";
                }
                ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Mettre à jour</button>
        <a href="index.php" class="btn btn-secondary">Annuler</a>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
