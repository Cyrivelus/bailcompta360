<?php
// pages/hors_bilan/ajouter_hypotheque.php

/**
 * Cette page gère l'ajout d'une nouvelle hypothèque.
 */

// Démarrer la session pour accéder aux variables de session.
session_start();

// Inclure la configuration de la base de données et les fonctions métier.
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_clients.php'; // Pour récupérer la liste des clients
require_once '../../fonctions/gestion_hypotheques.php'; // Pour ajouter l'hypothèque

// Initialiser les variables pour les messages d'erreur ou de succès.
$message = '';
$message_type = '';
$clients = [];

try {
    // Récupérer la liste des clients pour le menu déroulant du formulaire.
    $clients = getAllClients($pdo);

    // Vérifier si le formulaire est soumis.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Nettoyer et valider les données du formulaire.
        $clientId = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT);
        $montantPrincipal = filter_input(INPUT_POST, 'montant_principal', FILTER_VALIDATE_FLOAT);
        $dateCreation = trim($_POST['date_creation']);
        $statut = trim($_POST['statut']);

        // Vérifier que toutes les données requises sont présentes et valides.
        if ($clientId && $montantPrincipal !== false && !empty($dateCreation) && !empty($statut)) {
            // Appeler la fonction pour ajouter l'hypothèque.
            if (ajouterHypotheque($pdo, $clientId, $montantPrincipal, $dateCreation, $statut)) {
                $message = "L'hypothèque a été ajoutée avec succès.";
                $message_type = 'success';
                
                // Redirection après succès pour éviter la soumission multiple.
                header('Location: index.php?message=' . urlencode($message) . '&type=' . urlencode($message_type));
                exit();
            } else {
                throw new Exception("Échec de l'ajout de l'hypothèque. Veuillez réessayer.");
            }
        } else {
            throw new Exception("Veuillez remplir tous les champs du formulaire correctement.");
        }
    }

} catch (Exception $e) {
    // Capturer les exceptions et afficher un message d'erreur.
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
}

// Inclure les fichiers de gabarit pour l'interface utilisateur.
include '../../templates/header.php';
include '../../templates/navigation.php';
?>

<div class="container-fluid mt-4">
    <h2>Ajouter une nouvelle hypothèque</h2>
    <hr>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type; ?>" role="alert">
            <?= htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="ajouter_hypotheque.php" method="POST">
        <div class="form-group mb-3">
            <label for="client_id">Client</label>
            <select class="form-control" id="client_id" name="client_id" required>
                <option value="">Sélectionner un client</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= htmlspecialchars($client['id_client']); ?>">
                        <?= htmlspecialchars($client['prenoms'] . ' ' . $client['nom']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group mb-3">
            <label for="montant_principal">Montant Principal (€)</label>
            <input type="number" step="0.01" class="form-control" id="montant_principal" name="montant_principal" required>
        </div>

        <div class="form-group mb-3">
            <label for="date_creation">Date de création</label>
            <input type="date" class="form-control" id="date_creation" name="date_creation" required>
        </div>

        <div class="form-group mb-3">
            <label for="statut">Statut</label>
            <select class="form-control" id="statut" name="statut" required>
                <option value="En cours">En cours</option>
                <option value="Soldée">Soldée</option>
                <option value="Annulée">Annulée</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Ajouter l'hypothèque</button>
        <a href="index.php" class="btn btn-secondary">Annuler</a>
    </form>
</div>

<?php 
// Inclure le footer de la page.
include '../../templates/footer.php';
?>