<?php
// pages/clients/ajouter_client.php

session_start();

require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_clients.php';

$erreurs = [];
$succes = '';
$client_data = $_POST ?? []; // Use POST data to repopulate the form on error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type_client = $client_data['type_client'] ?? 'particulier';
    
    if ($type_client === 'particulier') {
        // Validation for individuals
        if (empty($client_data['nom_ou_raison_sociale']) || empty($client_data['nom_abrege']) || empty($client_data['email']) || empty($client_data['telephone'])) {
            $erreurs[] = "Les champs Nom, Prénom, Email et Téléphone sont obligatoires pour un particulier.";
        } elseif (!filter_var($client_data['email'], FILTER_VALIDATE_EMAIL)) {
            $erreurs[] = "L'adresse email n'est pas valide.";
        }
    } else {
        // Validation for businesses based on Delphi logic
        if (empty($client_data['nom_ou_raison_sociale']) || empty($client_data['matricule']) || empty($client_data['numero_registre_commerce']) || empty($client_data['numero_contribuable'])) {
            $erreurs[] = "Les champs Raison Sociale, Matricule, N° Registre de Commerce et N° de Contribuable sont obligatoires pour une entreprise.";
        }
    }

    if (empty($erreurs)) {
        try {
            // Call the function to create a client, now with all possible parameters
            if (creerClient($pdo, $client_data)) {
                $succes = "Le client a été ajouté avec succès.";
                $client_data = []; // Clear the form on success
            } else {
                $erreurs[] = "Une erreur est survenue lors de l'ajout du client. Veuillez vérifier les données.";
            }
        } catch (Exception $e) {
            $erreurs[] = "Erreur : " . $e->getMessage();
        }
    }
}

// Ensure the `creerClient` function in `gestion_clients.php` is updated to handle all fields.
// You need to create this function with the correct database insert logic.
// Here's an example of how it should look:

/*
function creerClient(PDO $pdo, array $data): bool {
    // Determine which fields to insert based on the client type
    if ($data['type_client'] === 'particulier') {
        $sql = "INSERT INTO clients (nom_ou_raison_sociale, nom_abrege, email, telephone, adresse, date_naissance, profession, revenu_mensuel, type_client, date_adhesion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $data['nom_ou_raison_sociale'],
            $data['nom_abrege'],
            $data['email'],
            $data['telephone'],
            $data['adresse'],
            $data['date_naissance'],
            $data['profession'],
            $data['revenu_mensuel'],
            $data['type_client'],
            date('Y-m-d')
        ];
    } else {
        // Add all fields for an 'entreprise'
        $sql = "INSERT INTO clients (nom_ou_raison_sociale, nom_abrege, type_client, matricule, numero_registre_commerce, date_creation, siege_social, forme_juridique, numero_contribuable, groupe_activite, secteur_institutionnel, code_ville, objet_social, site_web, adresse_email_contact, boite_postale, date_adhesion, telephone, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $data['nom_ou_raison_sociale'],
            $data['nom_abrege'],
            $data['type_client'],
            $data['matricule'],
            $data['numero_registre_commerce'],
            $data['date_creation'],
            $data['siege_social'],
            $data['forme_juridique'],
            $data['numero_contribuable'],
            $data['groupe_activite'],
            $data['secteur_institutionnel'],
            $data['code_ville'],
            $data['objet_social'],
            $data['site_web'],
            $data['adresse_email_contact'],
            $data['boite_postale'],
            date('Y-m-d'),
            $data['telephone'] ?? null,
            $data['email'] ?? null
        ];
    }

    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la création du client: " . $e->getMessage());
        return false;
    }
}
*/

include '../../templates/header.php';
include '../../templates/navigation.php';
?>

<div class="container-fluid mt-4">
    <h2>Ajouter un nouveau client</h2>
    <hr>
    <?php if (!empty($erreurs)): ?>
        <div class="alert alert-danger" role="alert">
            <?php foreach ($erreurs as $erreur): ?>
                <p><?= htmlspecialchars($erreur) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($succes): ?>
        <div class="alert alert-success" role="alert">
            <?= htmlspecialchars($succes) ?>
        </div>
    <?php endif; ?>

    <form action="ajouter_client.php" method="POST">
        <ul class="nav nav-tabs" id="clientTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="particulier-tab" data-toggle="tab" href="#particulier" role="tab" aria-controls="particulier" aria-selected="true">Particulier</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="entreprise-tab" data-toggle="tab" href="#entreprise" role="tab" aria-controls="entreprise" aria-selected="false">Entreprise</a>
            </li>
        </ul>

        <div class="tab-content mt-3">
            <input type="hidden" id="type_client_input" name="type_client" value="particulier">
            
            <div class="tab-pane fade show active" id="particulier" role="tabpanel" aria-labelledby="particulier-tab">
                <h4>Informations du Particulier</h4>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="nom">Nom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nom" name="nom_ou_raison_sociale" value="<?= htmlspecialchars($client_data['nom_ou_raison_sociale'] ?? '') ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="prenom">Prénom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="prenom" name="nom_abrege" value="<?= htmlspecialchars($client_data['nom_abrege'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="email">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($client_data['email'] ?? '') ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="telephone">Téléphone <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="telephone" name="telephone" value="<?= htmlspecialchars($client_data['telephone'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="adresse">Adresse</label>
                    <input type="text" class="form-control" id="adresse" name="adresse" value="<?= htmlspecialchars($client_data['adresse'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="date_naissance">Date de naissance</label>
                    <input type="date" class="form-control" id="date_naissance" name="date_naissance" value="<?= htmlspecialchars($client_data['date_naissance'] ?? '') ?>">
                </div>
                <h4 class="mt-4">Informations supplémentaires</h4>
                <div class="form-group">
                    <label for="profession">Profession</label>
                    <input type="text" class="form-control" id="profession" name="profession" value="<?= htmlspecialchars($client_data['profession'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="revenu_mensuel">Revenu mensuel</label>
                    <input type="number" step="0.01" class="form-control" id="revenu_mensuel" name="revenu_mensuel" value="<?= htmlspecialchars($client_data['revenu_mensuel'] ?? '') ?>">
                </div>
            </div>

            <div class="tab-pane fade" id="entreprise" role="tabpanel" aria-labelledby="entreprise-tab">
                <h4>Informations de l'Entreprise</h4>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="raison_sociale">Raison Sociale <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="raison_sociale" name="nom_ou_raison_sociale" value="<?= htmlspecialchars($client_data['nom_ou_raison_sociale'] ?? '') ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="nom_abrege_entreprise">Nom Abrégé</label>
                        <input type="text" class="form-control" id="nom_abrege_entreprise" name="nom_abrege" value="<?= htmlspecialchars($client_data['nom_abrege'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="matricule">Matricule <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="matricule" name="matricule" value="<?= htmlspecialchars($client_data['matricule'] ?? '') ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="numero_registre_commerce">N° Registre de Commerce <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="numero_registre_commerce" name="numero_registre_commerce" value="<?= htmlspecialchars($client_data['numero_registre_commerce'] ?? '') ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="numero_contribuable">N° de Contribuable <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="numero_contribuable" name="numero_contribuable" value="<?= htmlspecialchars($client_data['numero_contribuable'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="forme_juridique">Forme Juridique</label>
                        <input type="text" class="form-control" id="forme_juridique" name="forme_juridique" value="<?= htmlspecialchars($client_data['forme_juridique'] ?? '') ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="date_creation">Date de Création</label>
                        <input type="date" class="form-control" id="date_creation" name="date_creation" value="<?= htmlspecialchars($client_data['date_creation'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="objet_social">Objet Social</label>
                    <textarea class="form-control" id="objet_social" name="objet_social"><?= htmlspecialchars($client_data['objet_social'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary mt-3">Ajouter le client</button>
        <a href="index.php" class="btn btn-secondary mt-3">Annuler</a>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $('#clientTabs a').on('click', function (e) {
        e.preventDefault();
        $(this).tab('show');
        const clientType = $(this).attr('aria-controls');
        $('#type_client_input').val(clientType);
    });

    // Handle form state on page load
    const currentType = '<?= htmlspecialchars($_POST['type_client'] ?? 'particulier') ?>';
    $('#' + currentType + '-tab').tab('show');
</script>

<?php
include '../../templates/footer.php';
?>