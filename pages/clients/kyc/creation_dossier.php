<?php
// pages/clients/kyc/creation_dossier.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Correction des chemins d'accès
require_once '../../../database.php';
require_once '../../../fonctions/gestion_clients.php'; // Fonctions pour la gestion des clients
require_once '../../../fonctions/gestion_kyc.php';      // Fonctions pour la gestion du KYC

// Vérifier si un ID de client est passé dans l'URL
if (!isset($_GET['id_client']) || !is_numeric($_GET['id_client'])) {
    header("Location: ../liste_clients.php");
    exit();
}

$id_client = intval($_GET['id_client']);
$message = '';
$message_type = '';
$client = null;

try {
    // Utiliser la fonction pour trouver le client par ID (trouverClientParId)
    $client = trouverClientParId($pdo, $id_client);
    if (!$client) {
        throw new Exception("Client introuvable.");
    }

    // Traitement du formulaire si la méthode est POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $type_piece_identite = $_POST['type_piece_identite'];
        $numero_piece = $_POST['numero_piece'];
        $date_expiration = $_POST['date_expiration'];
        $justificatif_domicile = $_POST['justificatif_domicile'];
        $statut_verification = $_POST['statut_verification'];
        $commentaire = $_POST['commentaire'];

        // Enregistrement des documents (simulé)
        // Les noms des fichiers sont basés sur les données du formulaire,
        // mais une gestion d'upload de fichiers serait plus sûre dans la réalité.
        $nom_fichier_piece = 'pièce_' . $numero_piece . '.pdf';
        $nom_fichier_justificatif = 'justif_' . $justificatif_domicile . '.pdf';

        // Appel à la fonction de gestion KYC pour créer le dossier
        $success = creerDossierKyc(
            $pdo,
            $id_client,
            $type_piece_identite,
            $numero_piece,
            $date_expiration,
            $nom_fichier_piece,
            $justificatif_domicile,
            $nom_fichier_justificatif,
            $statut_verification,
            $commentaire
        );

        if ($success) {
            // Afficher le nom correct du client en fonction de son type
            $nom_complet = ($client['type_client'] === 'particulier') 
                            ? $client['nom_ou_raison_sociale'] . ' ' . $client['nom_abrege']
                            : $client['nom_ou_raison_sociale'];

            $message = "Dossier KYC créé avec succès pour le client : " . htmlspecialchars($nom_complet);
            $message_type = 'success';
        } else {
            $message = "Erreur lors de la création du dossier KYC. Veuillez réessayer.";
            $message_type = 'danger';
        }
    }
} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'danger';
}

// Inclure le header et la navigation
include '../../../templates/navigation.php';
include '../../../templates/header.php'; 
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Création d'un Dossier KYC</h2>
        <a href="../details_client.php?id=<?php echo urlencode($id_client); ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour au client
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card p-4 shadow-sm mb-4">
        <div class="card-body">
            <h4 class="card-title text-primary"><i class="fas fa-user-shield"></i> Dossier KYC pour 
                <?php
                if ($client) {
                    // Affiche le nom correct du client en fonction de son type
                    $nom_affiche = ($client['type_client'] === 'particulier') 
                                    ? $client['nom_ou_raison_sociale'] . ' ' . $client['nom_abrege'] 
                                    : $client['nom_ou_raison_sociale'];
                    echo htmlspecialchars($nom_affiche);
                } else {
                    echo 'Client Inconnu';
                }
                ?>
            </h4>
            <form action="creation_dossier.php?id_client=<?php echo urlencode($id_client); ?>" method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <fieldset class="border p-3 mb-3">
                            <legend class="float-none w-auto px-1">Pièce d'Identité</legend>
                            <div class="mb-3">
                                <label for="type_piece_identite" class="form-label">Type de pièce d'identité</label>
                                <select class="form-select" id="type_piece_identite" name="type_piece_identite" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="CNI">Carte Nationale d'Identité</option>
                                    <option value="PASSPORT">Passeport</option>
                                    <option value="PERMIS">Permis de Conduire</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="numero_piece" class="form-label">Numéro de pièce</label>
                                <input type="text" class="form-control" id="numero_piece" name="numero_piece" required>
                            </div>
                            <div class="mb-3">
                                <label for="date_expiration" class="form-label">Date d'expiration</label>
                                <input type="date" class="form-control" id="date_expiration" name="date_expiration" required>
                            </div>
                            <div class="mb-3">
                                <label for="fichier_piece" class="form-label">Document (fichier)</label>
                                <input class="form-control" type="file" id="fichier_piece" name="fichier_piece" disabled>
                                <div class="form-text">L'upload de fichier est désactivé pour cet exemple.</div>
                            </div>
                        </fieldset>
                    </div>

                    <div class="col-md-6">
                        <fieldset class="border p-3 mb-3">
                            <legend class="float-none w-auto px-1">Justificatif de Domicile</legend>
                            <div class="mb-3">
                                <label for="justificatif_domicile" class="form-label">Type de justificatif</label>
                                <select class="form-select" id="justificatif_domicile" name="justificatif_domicile" required>
                                    <option value="">Sélectionner...</option>
                                    <option value="FACT_EAU">Facture d'eau</option>
                                    <option value="FACT_ELEC">Facture d'électricité</option>
                                    <option value="CONTRAT_BAIL">Contrat de bail</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="fichier_justificatif" class="form-label">Document (fichier)</label>
                                <input class="form-control" type="file" id="fichier_justificatif" name="fichier_justificatif" disabled>
                                <div class="form-text">L'upload de fichier est désactivé pour cet exemple.</div>
                            </div>
                        </fieldset>

                        <fieldset class="border p-3 mb-3">
                            <legend class="float-none w-auto px-1">Statut de la vérification</legend>
                            <div class="mb-3">
                                <label for="statut_verification" class="form-label">Statut</label>
                                <select class="form-select" id="statut_verification" name="statut_verification" required>
                                    <option value="EN_ATTENTE" selected>En attente</option>
                                    <option value="VALIDE">Validé</option>
                                    <option value="REJETE">Rejeté</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="commentaire" class="form-label">Commentaire</label>
                                <textarea class="form-control" id="commentaire" name="commentaire" rows="3"></textarea>
                            </div>
                        </fieldset>
                    </div>
                </div>

                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Enregistrer le Dossier KYC
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
include '../../../templates/footer.php';
?>