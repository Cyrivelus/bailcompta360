<?php
// pages/clients/modifier_client.php

/**
 * Ce fichier permet de modifier les informations d'un client.
 * Il affiche un formulaire pré-rempli avec les données existantes du client.
 */

require_once '../../database.php';
require_once '../../fonctions/gestion_clients.php'; // Inclure le module de gestion des clients

// Vérifier si un ID de client est passé dans l'URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: liste_clients.php"); // Rediriger si l'ID est manquant ou invalide
    exit();
}

$id_client = intval($_GET['id']);
$message = '';
$message_type = '';
$client = null;

try {
    // Étape 1 : Récupérer les données existantes du client
    $client = getClientById($pdo, $id_client);

    if (!$client) {
        throw new Exception("Client introuvable.");
    }

    // Étape 2 : Traiter la soumission du formulaire (mise à jour)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validation des données du formulaire
        $nom = trim($_POST['nom']);
        $prenoms = trim($_POST['prenoms']);
        $date_naissance = trim($_POST['date_naissance']);
        $sexe = trim($_POST['sexe']);
        $telephone = trim($_POST['telephone']);
        $adresse = trim($_POST['adresse']);
        $email = trim($_POST['email']);

        if (empty($nom) || empty($prenoms) || empty($telephone)) {
            throw new Exception("Le nom, les prénoms et le téléphone sont obligatoires.");
        }

        $donnees_a_jour = [
            'nom' => $nom,
            'prenoms' => $prenoms,
            'date_naissance' => $date_naissance,
            'sexe' => $sexe,
            'telephone' => $telephone,
            'adresse' => $adresse,
            'email' => $email
        ];

        // Appel de la fonction de mise à jour du client
        if (mettreAJourClient($pdo, $id_client, $donnees_a_jour)) {
            $message = "Les informations du client ont été mises à jour avec succès.";
            $message_type = 'success';
            // Recharger les données du client pour refléter les changements
            $client = getClientById($pdo, $id_client);
        } else {
            throw new Exception("Échec de la mise à jour des informations du client.");
        }
    }

} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
}

// Inclure le header de la page (début du HTML, CSS, etc.)
include '../../templates/header.php'; 
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Modifier les informations du client</h2>
        <a href="details_client.php?id=<?php echo urlencode($id_client); ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour aux détails
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($client): ?>
        <div class="card p-4 shadow-sm">
            <form action="modifier_client.php?id=<?php echo urlencode($id_client); ?>" method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nom" name="nom" value="<?php echo htmlspecialchars($client['nom']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="prenoms" class="form-label">Prénoms <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="prenoms" name="prenoms" value="<?php echo htmlspecialchars($client['prenoms']); ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="date_naissance" class="form-label">Date de naissance</label>
                        <input type="date" class="form-control" id="date_naissance" name="date_naissance" value="<?php echo htmlspecialchars($client['date_naissance']); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="sexe" class="form-label">Sexe</label>
                        <select class="form-select" id="sexe" name="sexe">
                            <option value="M" <?php echo ($client['sexe'] === 'M') ? 'selected' : ''; ?>>Masculin</option>
                            <option value="F" <?php echo ($client['sexe'] === 'F') ? 'selected' : ''; ?>>Féminin</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="telephone" class="form-label">Téléphone <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="telephone" name="telephone" value="<?php echo htmlspecialchars($client['telephone']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($client['email']); ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="adresse" class="form-label">Adresse</label>
                    <textarea class="form-control" id="adresse" name="adresse" rows="3"><?php echo htmlspecialchars($client['adresse']); ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary mt-3">
                    <i class="fas fa-save"></i> Enregistrer les modifications
                </button>
            </form>
        </div>
    <?php else: ?>
        <div class="alert alert-danger text-center" role="alert">
            Le client que vous souhaitez modifier n'existe pas.
        </div>
    <?php endif; ?>
</div>

<?php 
// Fonction à implémenter dans 'gestion_clients.php'
function mettreAJourClient(PDO $pdo, int $id_client, array $donnees): bool {
    // Cette fonction devrait mettre à jour les données du client dans la base de données.
    // Utiliser une requête UPDATE.
    return true; // Stub
}

// Inclure le footer de la page (fin du HTML)
include '../../templates/footer.php';
?>