<?php
// pages/configuration/parametres.php

// Inclus le fichier de configuration de la base de données et les fonctions utilitaires
require_once '../../fonctions/database.php';

// Démarre la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialisation des variables pour les messages
$message = '';
$message_type = '';

// Gère la soumission du formulaire (méthode POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupère et nettoie les données du formulaire
    $nom_entreprise = htmlspecialchars($_POST['nom_entreprise'] ?? '');
    $adresse = htmlspecialchars($_POST['adresse'] ?? '');
    $email_support = htmlspecialchars($_POST['email_support'] ?? '');
    $taux_interet_standard = filter_var($_POST['taux_interet_standard'] ?? 0, FILTER_VALIDATE_FLOAT);
    $devise = htmlspecialchars($_POST['devise'] ?? 'XAF');
    
    // Valide le taux d'intérêt
    if ($taux_interet_standard === false) {
        $message = 'Le taux d\'intérêt doit être un nombre valide.';
        $message_type = 'danger';
    } else {
        // Met à jour les paramètres dans la base de données
        try {
            $sql = "INSERT INTO parametres (nom, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)";
            $stmt = $pdo->prepare($sql);
            
            $pdo->beginTransaction();
            
            $stmt->execute(['nom_entreprise', $nom_entreprise]);
            $stmt->execute(['adresse', $adresse]);
            $stmt->execute(['email_support', $email_support]);
            $stmt->execute(['taux_interet_standard', (string)$taux_interet_standard]);
            $stmt->execute(['devise', $devise]);
            
            $pdo->commit();
            
            $message = 'Les paramètres ont été mis à jour avec succès.';
            $message_type = 'success';
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = 'Erreur lors de la mise à jour des paramètres : ' . $e->getMessage();
            $message_type = 'danger';
            error_log("Erreur PDO lors de la mise à jour des paramètres : " . $e->getMessage());
        }
    }
}

// Récupère les paramètres actuels de la base de données pour les afficher
$parametres = [];
try {
    $stmt = $pdo->query("SELECT nom, valeur FROM parametres");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $parametres[$row['nom']] = $row['valeur'];
    }
} catch (PDOException $e) {
    $message = 'Impossible de charger les paramètres existants.';
    $message_type = 'danger';
    error_log("Erreur PDO lors du chargement des paramètres : " . $e->getMessage());
}

// Inclus l'en-tête de la page
include_once '../../templates/header.php';
require_once('../../templates/navigation.php');
require_once('../../templates/footer.php');
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h2>Paramètres Généraux de l'Application</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?= $message_type; ?> alert-dismissible fade show" role="alert">
                                <?= $message; ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        <?php endif; ?>
                        
                        <form action="parametres.php" method="POST">
                            <div class="form-group">
                                <label for="nom_entreprise">Nom de l'Entreprise</label>
                                <input type="text" class="form-control" id="nom_entreprise" name="nom_entreprise" value="<?= htmlspecialchars($parametres['nom_entreprise'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="adresse">Adresse de l'Entreprise</label>
                                <textarea class="form-control" id="adresse" name="adresse" rows="3"><?= htmlspecialchars($parametres['adresse'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="email_support">Email de Support</label>
                                <input type="email" class="form-control" id="email_support" name="email_support" value="<?= htmlspecialchars($parametres['email_support'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="taux_interet_standard">Taux d'Intérêt Standard (%)</label>
                                <input type="number" step="0.01" class="form-control" id="taux_interet_standard" name="taux_interet_standard" value="<?= htmlspecialchars($parametres['taux_interet_standard'] ?? 0); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="devise">Devise par défaut</label>
                                <select class="form-control" id="devise" name="devise" required>
                                    <option value="XAF" <?= (isset($parametres['devise']) && $parametres['devise'] == 'XAF') ? 'selected' : '' ?>>XAF</option>
                                    <option value="USD" <?= (isset($parametres['devise']) && $parametres['devise'] == 'USD') ? 'selected' : '' ?>>USD</option>
                                    <option value="EUR" <?= (isset($parametres['devise']) && $parametres['devise'] == 'EUR') ? 'selected' : '' ?>>EUR</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Enregistrer les Paramètres</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Inclus le pied de page de la page
include_once '../../templates/footer.php';
?>
