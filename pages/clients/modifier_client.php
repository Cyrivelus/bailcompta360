<?php
// pages/clients/modifier_client.php

session_start();

require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_clients.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id_client = intval($_GET['id']);
$message = '';
$message_type = '';

try {
    // Étape 1 : Récupérer les données existantes du client
    $client = trouverClientParId($pdo, $id_client);

    if (!$client) {
        throw new Exception("Client introuvable.");
    }

    // Étape 2 : Traiter la soumission du formulaire (mise à jour)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $donnees_a_jour = $_POST;
        
        // La validation doit être adaptée au type de client
        if (($client['type_client'] ?? 'particulier') === 'particulier') {
            if (empty($donnees_a_jour['nom_ou_raison_sociale']) || empty($donnees_a_jour['nom_abrege']) || empty($donnees_a_jour['telephone'])) {
                throw new Exception("Le nom, les prénoms et le téléphone sont obligatoires.");
            }
        } else { // Entreprise
            if (empty($donnees_a_jour['nom_ou_raison_sociale']) || empty($donnees_a_jour['matricule']) || empty($donnees_a_jour['numero_registre_commerce']) || empty($donnees_a_jour['numero_contribuable'])) {
                throw new Exception("Les champs Raison Sociale, Matricule, N° Registre de Commerce et N° de Contribuable sont obligatoires pour une entreprise.");
            }
        }

        if (mettreAJourClient($pdo, $id_client, $donnees_a_jour)) {
            $message = "Les informations du client ont été mises à jour avec succès.";
            $message_type = 'success';
            // Recharger les données du client pour refléter les changements
            $client = trouverClientParId($pdo, $id_client);
        } else {
            throw new Exception("Échec de la mise à jour des informations du client.");
        }
    }

} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
}

include '../../templates/header.php';
include '../../templates/navigation.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Modifier les informations du client</h2>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour à la liste
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type; ?>" role="alert">
            <?= htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($client): ?>
        <div class="card p-4 shadow-sm">
            <form action="modifier_client.php?id=<?= urlencode($id_client); ?>" method="post">
                <?php if (($client['type_client'] ?? 'particulier') === 'particulier'): ?>
                    <h4>Informations du Particulier</h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nom" name="nom_ou_raison_sociale" value="<?= htmlspecialchars($client['nom_ou_raison_sociale'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="prenoms" class="form-label">Prénoms <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="prenoms" name="nom_abrege" value="<?= htmlspecialchars($client['nom_abrege'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="date_naissance" class="form-label">Date de naissance</label>
                            <input type="date" class="form-control" id="date_naissance" name="date_naissance" value="<?= htmlspecialchars($client['date_naissance'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="sexe" class="form-label">Sexe</label>
                            <select class="form-select" id="sexe" name="sexe">
                                <option value="M" <?= ($client['sexe'] === 'M') ? 'selected' : ''; ?>>Masculin</option>
                                <option value="F" <?= ($client['sexe'] === 'F') ? 'selected' : ''; ?>>Féminin</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="telephone" class="form-label">Téléphone <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="telephone" name="telephone" value="<?= htmlspecialchars($client['telephone'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($client['email'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="adresse" class="form-label">Adresse</label>
                        <textarea class="form-control" id="adresse" name="adresse" rows="3"><?= htmlspecialchars($client['adresse'] ?? '') ?></textarea>
                    </div>

                <?php else: ?>
                    <h4>Informations de l'Entreprise</h4>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="raison_sociale" class="form-label">Raison Sociale <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="raison_sociale" name="nom_ou_raison_sociale" value="<?= htmlspecialchars($client['nom_ou_raison_sociale'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nom_abrege_entreprise" class="form-label">Nom Abrégé</label>
                            <input type="text" class="form-control" id="nom_abrege_entreprise" name="nom_abrege" value="<?= htmlspecialchars($client['nom_abrege'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="matricule" class="form-label">Matricule <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="matricule" name="matricule" value="<?= htmlspecialchars($client['matricule'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="numero_registre_commerce" class="form-label">N° Registre de Commerce <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="numero_registre_commerce" name="numero_registre_commerce" value="<?= htmlspecialchars($client['numero_registre_commerce'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="numero_contribuable" class="form-label">N° de Contribuable <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="numero_contribuable" name="numero_contribuable" value="<?= htmlspecialchars($client['numero_contribuable'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="forme_juridique" class="form-label">Forme Juridique</label>
                            <input type="text" class="form-control" id="forme_juridique" name="forme_juridique" value="<?= htmlspecialchars($client['forme_juridique'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="date_creation" class="form-label">Date de Création</label>
                            <input type="date" class="form-control" id="date_creation" name="date_creation" value="<?= htmlspecialchars($client['date_creation'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="objet_social" class="form-label">Objet Social</label>
                        <textarea class="form-control" id="objet_social" name="objet_social" rows="3"><?= htmlspecialchars($client['objet_social'] ?? '') ?></textarea>
                    </div>

                <?php endif; ?>

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
include '../../templates/footer.php';
?>