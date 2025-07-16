<?php
// pages/emprunts/details.php (Affichage des détails généraux de l'emprunt et décomposition des échéances)

// --- Titre de la page ---
$titre = "Details de l'Emprunt Bancaire";
$current_page = basename($_SERVER['PHP_SELF']); // Pour la classe 'active' dans la navigation

// --- Inclusions ---

require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
require_once '../../fonctions/database.php'; // Assurez-vous que ce fichier initialise $pdo
require_once '../../fonctions/gestion_emprunts.php'; // Assume this file exists or remove if unused


// --- Vérification de l'ID fourni ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php?error=" . urlencode("ID d'emprunt non valide ou manquant."));
    exit;
}

$idEmprunt = (int) $_GET['id'];
$emprunt = null;
$echeances = [];
$erreur = null;
$success_message = null; // To store the success message from the redirect
$error_message = null; // To store the error message from the redirect
$redirected_id_ecriture = null; // To store the ID_Ecriture passed from the redirect


// --- Check for messages from redirection (these are now handled by AJAX, but keep for initial page load errors) ---
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars(urldecode($_GET['success']));
    if (isset($_GET['id_ecriture']) && is_numeric($_GET['id_ecriture'])) {
        $redirected_id_ecriture = (int) $_GET['id_ecriture'];
    }
} elseif (isset($_GET['error'])) {
    $error_message = htmlspecialchars(urldecode($_GET['error']));
    if (isset($_GET['accounting_error'])) {
        $error_message .= " (Erreur(s) comptable(s) survenue(s)).";
    }
}


// --- Vérification de la connexion PDO ---
if (!isset($pdo) || !$pdo instanceof PDO) {
    $messageErreur = "Erreur de configuration de la base de données : La connexion PDO n'a pas été correctement initialisée.";
    error_log("Erreur (details.php - PDO non initialisé) : " . $messageErreur);
    echo '<div class="container mt-5"><div class="alert alert-danger">' . htmlspecialchars($messageErreur) . '</div></div>';
    require_once('../../templates/footer.php');
    exit;
}



// --- Récupération des données ---
try {
    // --- Récupération des détails de l'emprunt ---
    $stmtEmprunt = $pdo->prepare("SELECT * FROM Emprunts_Bancaires WHERE ID_Emprunt = :id_emprunt");
    $stmtEmprunt->bindParam(':id_emprunt', $idEmprunt, PDO::PARAM_INT);
    $stmtEmprunt->execute();
    $emprunt = $stmtEmprunt->fetch(PDO::FETCH_ASSOC);

    if (!$emprunt) {
        header("Location: index.php?error=" . urlencode("Emprunt bancaire non trouvé (ID: " . $idEmprunt . ")."));
        exit;
    }

    // --- Récupération des échéances AVEC le statut ET l'ID_Ecriture_Comptable ---
    $stmtEcheances = $pdo->prepare("
        SELECT
            E.*, -- Select all columns from Echeances_Amortissement
            S.Code_Statut,
            S.Libelle_Statut
        FROM Echeances_Amortissement E
        LEFT JOIN Statuts S ON E.ID_Statut = S.ID_Statut
        WHERE E.ID_Emprunt = ?
        ORDER BY E.Date_Echeance ASC
    ");
    $stmtEcheances->execute([$idEmprunt]);
    $echeances = $stmtEcheances->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $erreur = "Erreur lors de la récupération des détails de l'emprunt : " . $e->getMessage();
    error_log("Erreur (emprunts/details.php - chargement) : " . $e->getMessage());
    echo '<div class="container mt-5"><div class="alert alert-danger">' . htmlspecialchars($erreur) . '</div></div>';
    require_once('../../templates/footer.php');
    exit;
}

// --- HTML Output starts here ---

// fonctions/database.php

function getComptesCompta($pdo) {
    try {
        $sql = "SELECT ID_Compte, Numero_Compte, Nom_Compte FROM Comptes_compta ORDER BY Numero_Compte ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur de récupération des comptes comptables: " . $e->getMessage());
        return []; // Retourne un tableau vide en cas d'erreur
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Détails des emprunts </title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/tableau.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .details-container {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .details-container h3 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .details-container p {
            margin-bottom: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .solde-final {
            font-weight: bold;
            margin-top: 15px;
            font-size: 1.2em;
        }
        .echeance-paid-row {
            background-color: #d4edda !important; /* Light green background */
            opacity: 0.7; /* Slightly dim it */
            text-decoration: line-through; /* Strikethrough text */
        }
        /* Ensure links/buttons inside don't get strikethrough */
        .echeance-paid-row a, .echeance-paid-row button {
            text-decoration: none;
            opacity: 1; /* Reset opacity for clickable elements */
        }
        /* Spinning icon for processing button */
        .spinning {
            animation: spin 1s infinite linear;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .processing-row {
            background-color: #f8f9fa !important;
            opacity: 0.8;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h2 class="page-header">Détails de l'emprunt</h2>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <?= $success_message; ?>
            <?php if ($redirected_id_ecriture): ?>
                <a href="../ecritures/details.php?id=<?= htmlspecialchars($redirected_id_ecriture) ?>" class="btn btn-info btn-xs" title="Voir écriture Comptable">
                    <span class="glyphicon glyphicon-list-alt"></span> Voir l'écriture
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <?= $error_message; ?>
        </div>
    <?php endif; ?>

    <?php if ($emprunt): ?>
        <div class="details-container">
            <div class="row">
                <div class="col-md-6">
                    <h3>Informations Générales</h3>
                    <p><strong>ID:</strong> <?= htmlspecialchars($emprunt['ID_Emprunt'] ?? 'N/A') ?></p>
                    <p><strong>Banque:</strong> <?= htmlspecialchars($emprunt['Banque'] ?? 'N/A') ?></p>
                    <p><strong>Numéro de Prêt:</strong> <?= htmlspecialchars($emprunt['Numero_Pret'] ?? 'N/A') ?></p>
                    <p><strong>Montant:</strong> <?= isset($emprunt['Montant_Pret']) ? number_format($emprunt['Montant_Pret'], 2, ',', ' ') : '0,00' ?> <?= htmlspecialchars($emprunt['Devise'] ?? 'N/A') ?></p>
                    <p><strong>Date de Souscription:</strong> <?= isset($emprunt['Date_Souscription']) ? htmlspecialchars(date('d/m/Y', strtotime($emprunt['Date_Souscription']))) : 'N/A' ?></p>
                    <p><strong>Agence:</strong> <?= htmlspecialchars($emprunt['Agence'] ?? 'N/A') ?></p>
                    <h3>Dates Clés</h3>
                    <p><strong>Mise en Place:</strong> <?= isset($emprunt['Date_Mise_En_Place']) ? htmlspecialchars(date('d/m/Y', strtotime($emprunt['Date_Mise_En_Place']))) : 'N/A' ?></p>
                    <p><strong>Première échéance:</strong> <?= isset($emprunt['Date_Premiere_Echeance']) ? htmlspecialchars(date('d/m/Y', strtotime($emprunt['Date_Premiere_Echeance']))) : 'N/A' ?></p>
                    <p><strong>Dernière échéance:</strong> <?= isset($emprunt['Date_Derniere_Echeance']) ? htmlspecialchars(date('d/m/Y', strtotime($emprunt['Date_Derniere_Echeance']))) : 'N/A' ?></p>
                </div>
                <div class="col-md-6">
                    <h3>Informations Financières</h3>
                    <p><strong>TEG:</strong> <?= isset($emprunt['Taux_Effectif_Global']) ? htmlspecialchars(number_format($emprunt['Taux_Effectif_Global'] * 1, 4, ',', ' ')) . '%' : 'N/A' ?></p>
                    <p><strong>Type de Plan:</strong> <?= htmlspecialchars($emprunt['Type_Plan'] ?? 'N/A') ?></p>
                    <p><strong>Nombre d'échéances:</strong> <?= htmlspecialchars($emprunt['Nombre_Echeances'] ?? 'N/A') ?></p>
                    <p><strong>Type d'Intérêt:</strong> <?= htmlspecialchars($emprunt['Type_Interet'] ?? 'N/A') ?></p>
                    <h3>Amortissement</h3>
                    <p><strong>Montant Initial:</strong> <?= isset($emprunt['Montant_Pret']) ? number_format($emprunt['Montant_Pret'], 2, ',', ' ') : '0,00' ?> <?= htmlspecialchars($emprunt['Devise'] ?? 'N/A') ?></p>
                    <p><strong>Durée (jours):</strong> <?= htmlspecialchars($emprunt['Duree'] ?? 'N/A') ?></p>
                    <p><strong>Type:</strong> <?= htmlspecialchars($emprunt['Type_Amortissement'] ?? 'N/A') ?></p>
                </div>
            </div>

            <h3>Décomposition des échéances</h3>
            <?php if (!empty($echeances)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Date échéance</th>
                                <th>Numéro échéance</th>
                                <th class="text-right">Capital</th>
                                <th class="text-right">Intérêt SP</th>
                                <th class="text-right">TVA</th>
                                <th class="text-right">Montant Total</th>
                                <th class="text-right">Capital Restant</th>
                                <th>Statut</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $capitalRestantDu = $emprunt['Montant_Pret'] ?? 0;
                            $tvaRate = 0.1925; // 19.25%

                            foreach ($echeances as $echeance):
                                $dateEcheanceFormatted = isset($echeance['Date_Echeance']) ? htmlspecialchars(date('d/m/Y', strtotime($echeance['Date_Echeance']))) : 'N/A';
                                $numeroEcheance = htmlspecialchars($echeance['Numero_Echeance'] ?? 'N/A');
                                $amortissement = $echeance['Amortissement'] ?? 0;
                                $interetSP = $echeance['Interet_SP'] ?? 0;

                                $totalFeesTaxes = ($echeance['Taxes_Interet_SP'] ?? 0) +
                                                     ($echeance['Comm_Engagement'] ?? 0) +
                                                     ($echeance['Comm_Deblocage'] ?? 0) +
                                                     ($echeance['Taxe_Comm_E'] ?? 0) +
                                                     ($echeance['Taxe_Comm_D'] ?? 0) +
                                                     ($echeance['Frais_Etude'] ?? 0) +
                                                     ($echeance['Taxe_Frais_Etude'] ?? 0) +
                                                     ($echeance['Taxe_Capital'] ?? 0);

                                $TaxesTVA = ($interetSP + $totalFeesTaxes) * $tvaRate;
                                $montantEcheance = $amortissement + $interetSP + $totalFeesTaxes + $TaxesTVA;

                                $capitalRestantDu -= $amortissement;

                                if ($numeroEcheance == count($echeances)) {
                                    $capitalRestantDu = round($capitalRestantDu, 2);
                                    if (abs($capitalRestantDu) < 0.01) {
                                        $capitalRestantDu = 0;
                                    }
                                }

                                $statusCode = $echeance['Code_Statut'] ?? '';
                                $statusLabel = htmlspecialchars($echeance['Libelle_Statut'] ?? 'Statut inconnu');
                                $isPaid = ($statusCode === 'COMP');
                                $echeance_id = htmlspecialchars($echeance['ID_Echeance'] ?? '');
                                $ecriture_id = htmlspecialchars($echeance['ID_Ecriture_Comptable'] ?? '');
                            ?>
                            <tr id="echeance-row-<?= $echeance_id ?>" class="<?= $isPaid ? 'echeance-paid-row' : '' ?>">
                                <td><?= $dateEcheanceFormatted ?></td>
                                <td class="text-center"><?= $numeroEcheance ?></td>
                                <td class="text-right"><?= number_format($amortissement, 2, ',', ' ') ?></td>
                                <td class="text-right"><?= number_format($interetSP, 2, ',', ' ') ?></td>
                                <td class="text-right"><?= number_format($TaxesTVA, 2, ',', ' ') ?></td>
                                <td class="text-right"><?= number_format($montantEcheance, 2, ',', ' ') ?></td>
                                <td class="text-right"><?= number_format($capitalRestantDu, 2, ',', ' ') ?></td>
                                <td class="text-center status-cell">
                                    <?php if ($isPaid): ?>
                                        <span class="label label-success"><?= $statusLabel ?></span>
                                        <?php if (!empty($ecriture_id)): ?>
                                            <br>
                                            <a href="../ecritures/details.php?id=<?= $ecriture_id ?>" class="btn btn-info btn-xs" title="Voir écriture Comptable">
                                                <span class="glyphicon glyphicon-eye-open"></span> Voir
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="label label-warning"><?= $statusLabel ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center action-cell">
                                    <?php if (!$isPaid): ?>
                                        <button
                                            class="btn btn-primary btn-xs mark-paid-btn"
                                            data-id-echeance="<?= $echeance_id ?>"
                                            data-id-emprunt="<?= htmlspecialchars($emprunt['ID_Emprunt'] ?? '') ?>"
                                            title="Marquer comme payer et comptabiliser">
                                            <span class="glyphicon glyphicon-ok"></span> Payer & Comptabiliser
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-success btn-xs" disabled title="Déjà payé">
                                            <span class="glyphicon glyphicon-ok"></span> Payé
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                           <tr>
    <th colspan="5" class="text-right">Total Échéances Restantes à Payer :</th>
    <th class="text-right" id="total-montant-restant"></th>
    <th class="text-right" id="capital-restant-final">Capital Principal Restant à Payer :</th> <th colspan="2"></th>
</tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">Aucune échéance trouvée pour cet emprunt.</div>
            <?php endif; ?>

            <input type="hidden" id="initial-capital-restant" value="<?= htmlspecialchars($emprunt['Montant_Pret'] ?? '0') ?>">

        </div>
    <?php else: ?>
        <div class="alert alert-info">Aucune échéance trouvée pour cet emprunt.</div>
    <?php endif; ?>

</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-theme/0.1.0-beta.10/select2-bootstrap.min.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>


<div class="modal fade" id="accountModal" tabindex="-1" role="dialog" aria-labelledby="accountModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="accountModalLabel">Comptes Comptables pour l'Échéance</h4>
            </div>
            <div class="modal-body">
                <form id="accountForm">
                    <div class="form-group">
                        <label for="comptePrincipal">Compte Principal de l'Emprunt (Crédit initial / Débit amortissement)</label>
                        <select class="form-control" id="comptePrincipal" name="compte_principal_id" required>
                            <option value="">-- Sélectionnez un compte --</option>
                            <?php
                            // Assurez-vous que $pdo est disponible ici (par exemple, via un include de 'database.php')
                            // Si $pdo n'est pas accessible globalement, vous devrez le passer ici ou l'initialiser.
                            // Exemple: $comptes = getComptesCompta($pdo);
                            // Pour cet exemple, supposons que getComptesCompta($pdo) est accessible.
                            global $pdo; // Si $pdo est une variable globale
                            $comptes = getComptesCompta($pdo);
                            foreach ($comptes as $compte) {
                                echo "<option value='{$compte['ID_Compte']}'>" . htmlspecialchars($compte['Numero_Compte'] . ' - ' . $compte['Nom_Compte']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="compteChargesInterets">Compte Charges d'Intérêts (Débit)</label>
                        <select class="form-control" id="compteChargesInterets" name="compte_interet_id" required>
                            <option value="">-- Sélectionnez un compte --</option>
                            <?php
                            foreach ($comptes as $compte) {
                                echo "<option value='{$compte['ID_Compte']}'>" . htmlspecialchars($compte['Numero_Compte'] . ' - ' . $compte['Nom_Compte']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="compteBanque">Compte Banque (Crédit)</label>
                        <select class="form-control" id="compteBanque" name="compte_banque_id" required>
                            <option value="">-- Sélectionnez un compte --</option>
                            <?php
                            foreach ($comptes as $compte) {
                                echo "<option value='{$compte['ID_Compte']}'>" . htmlspecialchars($compte['Numero_Compte'] . ' - ' . $compte['Nom_Compte']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="compteTaxesFrais">Compte Taxes/Frais (Débit) (Optionnel)</label>
                        <select class="form-control" id="compteTaxesFrais" name="compte_taxes_frais_id">
                            <option value="0">-- Aucun / Sélectionnez un compte --</option> <?php
                            foreach ($comptes as $compte) {
                                echo "<option value='{$compte['ID_Compte']}'>" . htmlspecialchars($compte['Numero_Compte'] . ' - ' . $compte['Nom_Compte']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <input type="hidden" id="modalEcheanceId" name="id_echeance">
                    <input type="hidden" id="modalEmpruntId" name="id_emprunt">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="confirmPaymentBtn">Confirmer & Payer</button>
            </div>
        </div>
    </div>
</div>
<script>
<script>
    $(document).ready(function() {
        // Initialiser Select2 sur tous les combobox pertinents
        $('#comptePrincipal, #compteChargesInterets, #compteBanque, #compteTaxesFrais').select2({
            theme: "bootstrap", // Utilise le thème Bootstrap pour Select2 pour un meilleur look
            width: '100%',     // Fait en sorte que le champ prenne toute la largeur disponible
            placeholder: "Rechercher par numéro ou nom de compte...", // Texte indicatif
            allowClear: true   // Permet de vider la sélection
        });

        // Fonction pour gérer l'ouverture du modal et le pré-remplissage
        $('#accountModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget); // Bouton qui a déclenché le modal
            var id_echeance = button.data('id-echeance');
            var id_emprunt = button.data('id-emprunt');

            // Récupérez les IDs de compte par défaut passés via les data attributes du bouton
            var default_compte_principal = button.data('compte-principal') || '';
            var default_compte_interet = button.data('compte-interet') || '';
            var default_compte_banque = button.data('compte-banque') || '';
            var default_compte_taxes_frais = button.data('compte-taxes-frais') || '0'; // '0' pour l'option 'Aucun'

            var modal = $(this);
            modal.find('#modalEcheanceId').val(id_echeance);
            modal.find('#modalEmpruntId').val(id_emprunt);

            // Définir la valeur sélectionnée pour les combobox Select2
            // La méthode .val() de jQuery suivie de .trigger('change') est nécessaire pour Select2
            $('#comptePrincipal').val(default_compte_principal).trigger('change');
            $('#compteChargesInterets').val(default_compte_interet).trigger('change');
            $('#compteBanque').val(default_compte_banque).trigger('change');
            $('#compteTaxesFrais').val(default_compte_taxes_frais).trigger('change');

            // Écouteur d'événement pour le bouton de confirmation (important de le re-lier à chaque ouverture)
            $('#confirmPaymentBtn').off('click').on('click', function() {
                // Vérifier la validité des champs requis avant de soumettre
                if (!document.getElementById('accountForm').checkValidity()) {
                    // Si la validation échoue, afficher les messages d'erreur du navigateur
                    document.getElementById('accountForm').reportValidity();
                    return; // Arrêter la soumission AJAX
                }

                var formData = $('#accountForm').serialize(); // Récupère toutes les données du formulaire
                
                $.ajax({
                    url: 'pages/emprunts/mark_echeance_paid.php', // Assurez-vous que le chemin est correct
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            alert(response.message); // Ou utilisez un modal plus joli (ex: Bootstrap modal d'alerte)
                            modal.modal('hide');
                            location.reload(); // Recharger la page pour voir les mises à jour
                        } else {
                            alert('Erreur: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Erreur AJAX: " + status + " " + error + "\n" + xhr.responseText);
                        alert("Une erreur de communication est survenue. Veuillez vérifier la console du navigateur.");
                    }
                });
            });
        });
    });
</script>
</script>
<?php require_once('../../templates/footer.php'); ?>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script>
$(document).ready(function() {
    let currentButton = null; // Store the button that triggered the modal

    // Function to calculate and update totals
    function updateTotals() {
        let totalMontantRestant = 0;
        let finalCapitalRestant = 0;
        let firstUnpaidFound = false;

        $('table tbody tr').each(function() {
            if (!$(this).hasClass('echeance-paid-row')) {
                const montantTotalText = $(this).find('td:eq(5)').text(); // Montant Total column
                const capitalRestantText = $(this).find('td:eq(6)').text(); // Capital Restant column

                // Convert text to number, handling French number format (comma as decimal separator)
                const montantTotal = parseFloat(montantTotalText.replace(/\s/g, '').replace(',', '.'));
                const capitalRestant = parseFloat(capitalRestantText.replace(/\s/g, '').replace(',', '.'));

                if (!isNaN(montantTotal)) {
                    totalMontantRestant += montantTotal;
                }

                // The 'Capital Restant' of the *last* unpaid tranche is the final capital remaining
                // Or, if all are paid, it should be 0. We'll capture the first one encountered if iterating backwards,
                // or ensure we get the correct last one if iterating forwards.
                // For now, let's keep it simple and assume the last row's capital remaining is the true final one.
                // A more robust solution might require re-calculating it based on *unpaid* amortissements.
                // For simplicity, we'll use the one from the last row, or the first unpaid if found.
                if (!firstUnpaidFound && !$(this).hasClass('echeance-paid-row')) {
                    finalCapitalRestant = capitalRestant;
                    firstUnpaidFound = true; // Mark that we've found the first unpaid row
                }
            }
        });

        // If all are paid, finalCapitalRestant should be 0.
        if (!firstUnpaidFound) {
            finalCapitalRestant = 0;
        }


        $('#total-montant-restant').text(numberWithCommas(totalMontantRestant.toFixed(2)));
        $('#capital-restant-final').text('Capital Principal Restant à Payer : ' + numberWithCommas(finalCapitalRestant.toFixed(2)));
    }


    function numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " "); // Add space as thousand separator
    }

    // --- Handle click on 'Payer & Comptabiliser' button to show modal ---
    $(document).on('click', '.mark-paid-btn', function() {
        currentButton = $(this); // Store the current button
        var id_echeance = currentButton.data('id-echeance');
        var id_emprunt = currentButton.data('id-emprunt');

        // Set hidden fields in the modal
        $('#modalEcheanceId').val(id_echeance);
        $('#modalEmpruntId').val(id_emprunt);

        // Clear previous input values (optional, depending on desired behavior)
        $('#accountForm')[0].reset();

        // Show the modal
        $('#accountModal').modal('show');
    });

    // --- Handle click on 'Confirmer & Payer' button in the modal ---
    $('#confirmPaymentBtn').on('click', function() {
        var button = currentButton; // Use the stored button
        var id_echeance = $('#modalEcheanceId').val();
        var id_emprunt = $('#modalEmpruntId').val();

        var comptePrincipal = $('#comptePrincipal').val().trim();
        var compteChargesInterets = $('#compteChargesInterets').val().trim();
        var compteBanque = $('#compteBanque').val().trim();
        var compteTaxesFrais = $('#compteTaxesFrais').val().trim(); // Optional

        // Basic validation
        if (!comptePrincipal || !compteChargesInterets || !compteBanque) {
            alert('Veuillez remplir tous les champs obligatoires (Compte Principal, Compte Charges d\'Intérêts, Compte Banque).');
            return;
        }

        // Close the modal
        $('#accountModal').modal('hide');

        var row = button.closest('tr');

        // Disable the button to prevent multiple clicks
        button.prop('disabled', true).html('<span class="glyphicon glyphicon-refresh spinning"></span> Traitement...');

        // Add loading class to row
        row.addClass('processing-row');

        $.ajax({
            url: 'mark_echeance_paid.php', // This is the PHP script that processes the payment
            method: 'POST',
            dataType: 'json',
            data: {
                id_echeance: id_echeance,
                id_emprunt: id_emprunt,
                // --- START OF MODIFICATION: Corrected parameter names ---
                compte_principal_id: comptePrincipal,       // Changed from compte_principal
                compte_interet_id: compteChargesInterets,   // Changed from compte_charges_interets
                compte_banque_id: compteBanque,             // Changed from compte_banque
                compte_taxes_frais_id: compteTaxesFrais     // Changed from compte_taxes_frais
                // --- END OF MODIFICATION ---
            },
            success: function(response) {
                row.removeClass('processing-row'); // Remove processing class regardless of success/error

                if (response.status === 'success') {
                    // Update UI immediately for the paid row
                    row.addClass('echeance-paid-row');
                    row.find('.status-cell').html(
                        '<span class="label label-success">Comptabilisée</span>' +
                        (response.id_ecriture ?
                            '<br><a href="../ecritures/details.php?id=' + response.id_ecriture +
                            '" class="btn btn-info btn-xs" title="Voir écriture Comptable">' +
                            '<span class="glyphicon glyphicon-eye-open"></span> Voir</a>' : '')
                    );
                    row.find('.action-cell').html(
                        '<button class="btn btn-success btn-xs" disabled title="Déjà payé">' +
                        '<span class="glyphicon glyphicon-ok"></span> Payé</button>'
                    );

                    // Show success message at the top of the page
                    var successMsg = 'Échéance marquée comme payée avec succès.';
                    if (response.id_ecriture) {
                        successMsg += ' Écriture #' + response.id_ecriture + ' créée.';
                    }
                    if (response.accounting_error) { // If accounting had issues but payment recorded
                        successMsg += ' (Avertissement: ' + response.message + ')';
                    }

                    // Remove any existing alerts and add the new success alert
                    $('.alert-success').remove();
                    $('.alert-danger').remove(); // Also remove error messages if payment was successful
                    $('.page-header').after(
                        '<div class="alert alert-success">' + successMsg +
                        (response.id_ecriture ?
                            ' <a href="../ecritures/details.php?id=' + response.id_ecriture +
                            '" class="btn btn-info btn-xs" title="Voir écriture Comptable">' +
                            '<span class="glyphicon glyphicon-list-alt"></span> Voir l\'écriture</a>' : '') +
                        '</div>'
                    );

                    // Update totals after a successful payment
                    updateTotals();

                } else {
                    // Handle error response
                    button.prop('disabled', false).html('<span class="glyphicon glyphicon-ok"></span> Payer & Comptabiliser');

                    // Remove existing alerts and add the new error alert
                    $('.alert-success').remove();
                    $('.alert-danger').remove();
                    $('.page-header').after(
                        '<div class="alert alert-danger">Erreur: ' + (response.message || 'Erreur inconnue') + '</div>'
                    );
                    // No need to update totals on error, as nothing changed
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // Handle AJAX errors (network issues, server errors before PHP runs, etc.)
                button.prop('disabled', false).html('<span class="glyphicon glyphicon-ok"></span> Payer & Comptabiliser');
                row.removeClass('processing-row');

                var errorMsg = 'Erreur réseau ou serveur: ' + textStatus + ' - ' + errorThrown;
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    errorMsg = jqXHR.responseJSON.message;
                } else if (jqXHR.responseText) {
                    errorMsg = 'Erreur serveur: ' + jqXHR.responseText;
                }

                console.error("AJAX Error:", jqXHR.status, textStatus, errorThrown, jqXHR.responseText); // Log full error

                // Remove existing alerts and add the new error alert
                $('.alert-success').remove();
                $('.alert-danger').remove();
                $('.page-header').after(
                    '<div class="alert alert-danger">' + errorMsg + '</div>'
                );
                // No need to update totals on AJAX error
            }
        });
    });

    // Initialize totals on page load
    updateTotals();
});

</script>

</body>
</html>