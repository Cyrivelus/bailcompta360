<?php
// modifier_facture.php

// --- Configuration et Initialisation ---
ini_set('display_errors', 1); // À desactiver en production
error_reporting(E_ALL);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$titre = 'Modifier la Facture';
$current_page = basename($_SERVER['PHP_SELF']);

// --- Includes ---
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
require_once('../../fonctions/database.php');
require_once('../../fonctions/gestion_factures.php'); // Doit contenir getFactureById et updateFacture

// --- Vérification de la connexion PDO ---
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("ERREUR FATALE: \$pdo n'a pas été initialisé dans modifier_facture.php");
    $_SESSION['error_message'] = "Erreur critique de configuration de la base de données. Veuillez contacter l'administrateur.";
    header('Location: listes_factures.php');
    exit();
}

// --- Vérification de l'ID de la facture ---
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || intval($_GET['id']) <= 0) {
    $_SESSION['error_message'] = "ID de facture invalide ou manquant.";
    header('Location: listes_factures.php');
    exit();
}
$idFacture = intval($_GET['id']);

// --- Récupérer les détails de la facture à modifier ---
$facture = getFactureById($pdo, $idFacture); // Assurez-vous que cette fonction retourne toutes les colonnes de la table Factures

if (!$facture) {
    $_SESSION['error_message'] = "Facture non trouvée pour l'ID : " . htmlspecialchars($idFacture);
    header('Location: listes_factures.php');
    exit();
}

$erreurs = []; // Initialisation du tableau des erreurs

// --- Traitement du formulaire de modification ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération et nettoyage des données du formulaire
    $numeroFacture = trim(filter_input(INPUT_POST, 'numero_facture', FILTER_SANITIZE_STRING));
    $dateEmission = trim(filter_input(INPUT_POST, 'date_emission', FILTER_SANITIZE_STRING));
    $dateReception = trim(filter_input(INPUT_POST, 'date_reception', FILTER_SANITIZE_STRING));
    $dateEcheance = trim(filter_input(INPUT_POST, 'date_echeance', FILTER_SANITIZE_STRING));
    $nomFournisseur = trim(filter_input(INPUT_POST, 'nom_fournisseur', FILTER_SANITIZE_STRING));
    
    // Pour les montants, s'assurer que le format est correct (point comme séparateur décimal)
    $montantHT_str = str_replace(',', '.', trim($_POST['montant_ht'] ?? '0'));
    $montantTVA_str = str_replace(',', '.', trim($_POST['montant_tva'] ?? '0'));
    $montantTTC_str = str_replace(',', '.', trim($_POST['montant_ttc'] ?? '0'));

    $montantHT = filter_var($montantHT_str, FILTER_VALIDATE_FLOAT);
    $montantTVA = filter_var($montantTVA_str, FILTER_VALIDATE_FLOAT);
    $montantTTC = filter_var($montantTTC_str, FILTER_VALIDATE_FLOAT);

    $statutFacture = trim(filter_input(INPUT_POST, 'statut_facture', FILTER_SANITIZE_STRING));
    $idJournal_str = trim(filter_input(INPUT_POST, 'id_journal', FILTER_SANITIZE_STRING));
    $idJournal = !empty($idJournal_str) ? filter_var($idJournal_str, FILTER_VALIDATE_INT) : null;

    $numeroBonCommande = trim(filter_input(INPUT_POST, 'numero_bon_commande', FILTER_SANITIZE_STRING));
    $commentaire = trim(filter_input(INPUT_POST, 'commentaire', FILTER_SANITIZE_STRING));

    // --- Validation des données ---
    if (empty($numeroFacture)) {
        $erreurs['numero_facture'] = "Le numéro de facture est obligatoire.";
    } elseif (strlen($numeroFacture) > 255) {
        $erreurs['numero_facture'] = "Le numéro de facture ne doit pas dépasser 255 caractères.";
    }

    if (empty($dateEmission)) {
        $erreurs['date_emission'] = "La date d'émission est obligatoire.";
    } elseif (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $dateEmission)) {
        $erreurs['date_emission'] = "Format de date d'émission invalide (AAAA-MM-JJ attendu).";
    }
    
    // Dates optionnelles, mais si fournies, valider le format
    if (!empty($dateReception) && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $dateReception)) {
        $erreurs['date_reception'] = "Format de date de réception invalide (AAAA-MM-JJ attendu).";
    }
    if (!empty($dateEcheance) && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $dateEcheance)) {
        $erreurs['date_echeance'] = "Format de date d'échéance invalide (AAAA-MM-JJ attendu).";
    }

    if (empty($nomFournisseur)) {
        $erreurs['nom_fournisseur'] = "Le nom du fournisseur est obligatoire.";
    } elseif (strlen($nomFournisseur) > 255) {
        $erreurs['nom_fournisseur'] = "Le nom du fournisseur ne doit pas dépasser 255 caractères.";
    }
    
    if ($montantHT === false || $montantHT < 0) {
        $erreurs['montant_ht'] = "Le montant HT est invalide ou négatif.";
    }
    if ($montantTVA === false || $montantTVA < 0) {
        $erreurs['montant_tva'] = "Le montant TVA est invalide ou négatif.";
    }
    if ($montantTTC === false || $montantTTC < 0) {
        $erreurs['montant_ttc'] = "Le montant TTC est invalide ou négatif.";
    }
    // Vérification de cohérence simple
    if ($montantHT !== false && $montantTVA !== false && $montantTTC !== false && abs(($montantHT + $montantTVA) - $montantTTC) > 0.015) { // Marge pour erreurs d'arrondi
        $erreurs['montant_coherence'] = "Incohérence entre Montant HT, TVA et TTC.";
    }


    $statutsValides = ['Non payée', 'Partiellement payée', 'Payée', 'Annulée', 'En attente']; // Ajoutez les statuts pertinents
    if (empty($statutFacture)) {
        $erreurs['statut_facture'] = "Le statut de la facture est obligatoire.";
    } elseif (!in_array($statutFacture, $statutsValides)) {
        $erreurs['statut_facture'] = "Le statut de la facture sélectionné n'est pas valide.";
    }

    if ($idJournal_str !== '' && ($idJournal === false || $idJournal <= 0)) { // Si non vide, doit être un entier positif
        $erreurs['id_journal'] = "L'ID Journal est invalide. Laissez vide ou entrez un nombre entier positif.";
    }

    if (strlen($numeroBonCommande) > 100) {
        $erreurs['numero_bon_commande'] = "Le numéro de bon de commande ne doit pas dépasser 100 caractères.";
    }
    if (strlen($commentaire) > 500) {
        $erreurs['commentaire'] = "Le commentaire ne doit pas dépasser 500 caractères.";
    }
    

    if (empty($erreurs)) {
        // --- Appel à la fonction de mise à jour ---
        // Assurez-vous que la fonction updateFacture dans gestion_factures.php
        // accepte ces paramètres dans cet ordre et gère les types correctement.
        $dataToUpdate = [
            'Numero_Facture' => $numeroFacture,
            'Date_Emission' => $dateEmission,
            'Date_Reception' => !empty($dateReception) ? $dateReception : null,
            'Date_Echeance' => !empty($dateEcheance) ? $dateEcheance : null,
            'Montant_HT' => $montantHT,
            'Montant_TVA' => $montantTVA,
            'Montant_TTC' => $montantTTC,
            'Statut_Facture' => $statutFacture,
            'ID_Journal' => $idJournal, // Peut être NULL
            'Numero_Bon_Commande' => !empty($numeroBonCommande) ? $numeroBonCommande : null,
            'Commentaire' => !empty($commentaire) ? $commentaire : null,
            'Nom_Fournisseur' => $nomFournisseur
            // 'Date_Comptabilisation' et 'ID_Ecriture_Comptable' ne sont pas modifiés via ce formulaire par défaut.
        ];

        $resultat = updateFacture($pdo, $idFacture, $dataToUpdate);

        if ($resultat) {
            $_SESSION['success_message'] = "Facture N°" . htmlspecialchars($numeroFacture) . " mise à jour avec succès.";
            header('Location: listes_factures.php');
            exit();
        } else {
            // L'erreur spécifique devrait être loggée dans la fonction updateFacture
            $erreurs['general'] = "Erreur lors de la mise à jour de la facture. Veuillez vérifier les logs ou contacter l'administrateur.";
        }
    }
    // Si erreurs, les données soumises (et nettoyées) sont réinjectées dans $facture pour pré-remplir le formulaire
    if (!empty($erreurs)) {
        $facture['Numero_Facture'] = $numeroFacture;
        $facture['Date_Emission'] = $dateEmission;
        $facture['Date_Reception'] = $dateReception;
        $facture['Date_Echeance'] = $dateEcheance;
        $facture['Nom_Fournisseur'] = $nomFournisseur;
        $facture['Montant_HT'] = $montantHT_str; // Utiliser les strings pour préremplir
        $facture['Montant_TVA'] = $montantTVA_str;
        $facture['Montant_TTC'] = $montantTTC_str;
        $facture['Statut_Facture'] = $statutFacture;
        $facture['ID_Journal'] = $idJournal_str;
        $facture['Numero_Bon_Commande'] = $numeroBonCommande;
        $facture['Commentaire'] = $commentaire;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Modifier Facture N°<?= htmlspecialchars($facture['Numero_Facture'] ?? '') ?></title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css"> <link rel="stylesheet" href="../../css/formulaire.css"> <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
	<style>
        body {
            padding-top: 70px; /* Si header fixe */
            padding-left: 220px; /* Si navigation latérale fixe */
        }
        .container {
            max-width: 900px; /* Un peu plus large pour les formulaires */
        }
        .page-header {
             margin-bottom: 30px;
             color: #2c3e50;
        }
        .form-error {
            color: #a94442; /* Bootstrap danger color */
            font-size: 0.9em;
            margin-top: 5px;
        }
        .form-group.has-error .form-control { /* Style pour les champs en erreur */
            border-color: #a94442;
            box-shadow: inset 0 1px 1px rgba(0,0,0,.075);
        }
        .form-group.has-error .control-label {
            color: #a94442;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="page-header">Modifier la Facture N° <?= htmlspecialchars($facture['Numero_Facture'] ?? '') ?></h2>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        <?php if (isset($erreurs['general'])): ?>
             <div class="alert alert-danger"><?= htmlspecialchars($erreurs['general']) ?></div>
        <?php endif; ?>
         <?php if (isset($erreurs['montant_coherence'])): ?>
             <div class="alert alert-warning"><?= htmlspecialchars($erreurs['montant_coherence']) ?></div>
        <?php endif; ?>


        <form action="modifier_facture.php?id=<?= $idFacture ?>" method="post" class="form-horizontal">
            
            <div class="form-group <?= isset($erreurs['numero_facture']) ? 'has-error' : '' ?>">
                <label for="numero_facture" class="col-sm-3 control-label">Numéro de Facture:</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" id="numero_facture" name="numero_facture" value="<?= htmlspecialchars($facture['Numero_Facture'] ?? '') ?>" required>
                    <?php if (isset($erreurs['numero_facture'])): ?><div class="form-error"><?= $erreurs['numero_facture'] ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-group <?= isset($erreurs['nom_fournisseur']) ? 'has-error' : '' ?>">
                <label for="nom_fournisseur" class="col-sm-3 control-label">Nom du Fournisseur:</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" id="nom_fournisseur" name="nom_fournisseur" value="<?= htmlspecialchars($facture['Nom_Fournisseur'] ?? '') ?>" required>
                    <?php if (isset($erreurs['nom_fournisseur'])): ?><div class="form-error"><?= $erreurs['nom_fournisseur'] ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-group <?= isset($erreurs['date_emission']) ? 'has-error' : '' ?>">
                <label for="date_emission" class="col-sm-3 control-label">Date d'Émission:</label>
                <div class="col-sm-9">
                    <input type="date" class="form-control" id="date_emission" name="date_emission" value="<?= htmlspecialchars($facture['Date_Emission'] ?? '') ?>" required>
                    <?php if (isset($erreurs['date_emission'])): ?><div class="form-error"><?= $erreurs['date_emission'] ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-group <?= isset($erreurs['date_reception']) ? 'has-error' : '' ?>">
                <label for="date_reception" class="col-sm-3 control-label">Date de Réception:</label>
                <div class="col-sm-9">
                    <input type="date" class="form-control" id="date_reception" name="date_reception" value="<?= htmlspecialchars($facture['Date_Reception'] ?? '') ?>">
                    <?php if (isset($erreurs['date_reception'])): ?><div class="form-error"><?= $erreurs['date_reception'] ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-group <?= isset($erreurs['date_echeance']) ? 'has-error' : '' ?>">
                <label for="date_echeance" class="col-sm-3 control-label">Date d'Échéance:</label>
                <div class="col-sm-9">
                    <input type="date" class="form-control" id="date_echeance" name="date_echeance" value="<?= htmlspecialchars($facture['Date_Echeance'] ?? '') ?>">
                    <?php if (isset($erreurs['date_echeance'])): ?><div class="form-error"><?= $erreurs['date_echeance'] ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-group <?= isset($erreurs['montant_ht']) ? 'has-error' : '' ?>">
                <label for="montant_ht" class="col-sm-3 control-label">Montant HT (€):</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" id="montant_ht" name="montant_ht" value="<?= htmlspecialchars(number_format(floatval(str_replace(',', '.', $facture['Montant_HT'] ?? '0')), 2, '.', '')) ?>" pattern="^\d+(\.\d{1,2})?$" title="Utilisez un point comme séparateur décimal. Ex: 1234.56" required>
                    <?php if (isset($erreurs['montant_ht'])): ?><div class="form-error"><?= $erreurs['montant_ht'] ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-group <?= isset($erreurs['montant_tva']) ? 'has-error' : '' ?>">
                <label for="montant_tva" class="col-sm-3 control-label">Montant TVA (€):</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" id="montant_tva" name="montant_tva" value="<?= htmlspecialchars(number_format(floatval(str_replace(',', '.', $facture['Montant_TVA'] ?? '0')), 2, '.', '')) ?>" pattern="^\d+(\.\d{1,2})?$" title="Utilisez un point comme séparateur décimal." required>
                    <?php if (isset($erreurs['montant_tva'])): ?><div class="form-error"><?= $erreurs['montant_tva'] ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-group <?= isset($erreurs['montant_ttc']) ? 'has-error' : '' ?>">
                <label for="montant_ttc" class="col-sm-3 control-label">Montant TTC (€):</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" id="montant_ttc" name="montant_ttc" value="<?= htmlspecialchars(number_format(floatval(str_replace(',', '.', $facture['Montant_TTC'] ?? '0')), 2, '.', '')) ?>" pattern="^\d+(\.\d{1,2})?$" title="Utilisez un point comme séparateur décimal." required>
                    <?php if (isset($erreurs['montant_ttc'])): ?><div class="form-error"><?= $erreurs['montant_ttc'] ?></div><?php endif; ?>
                </div>
            </div>
            
            <div class="form-group <?= isset($erreurs['statut_facture']) ? 'has-error' : '' ?>">
                <label for="statut_facture" class="col-sm-3 control-label">Statut:</label>
                <div class="col-sm-9">
                    <select class="form-control" id="statut_facture" name="statut_facture" required>
                        <?php 
                        $statutsDisponibles = ['En attente', 'Non payée', 'Partiellement payée', 'Payée', 'Annulée'];
                        $statutActuel = $facture['Statut_Facture'] ?? 'En attente';
                        foreach ($statutsDisponibles as $statut) {
                            echo "<option value=\"" . htmlspecialchars($statut) . "\"" . ($statutActuel === $statut ? ' selected' : '') . ">" . htmlspecialchars($statut) . "</option>";
                        }
                        ?>
                    </select>
                    <?php if (isset($erreurs['statut_facture'])): ?><div class="form-error"><?= $erreurs['statut_facture'] ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-group <?= isset($erreurs['id_journal']) ? 'has-error' : '' ?>">
                <label for="id_journal" class="col-sm-3 control-label">ID Journal:</label>
                <div class="col-sm-9">
                    <input type="number" class="form-control" id="id_journal" name="id_journal" value="<?= htmlspecialchars($facture['ID_Journal'] ?? '') ?>" placeholder="Laissez vide si non applicable">
                    <?php if (isset($erreurs['id_journal'])): ?><div class="form-error"><?= $erreurs['id_journal'] ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-group <?= isset($erreurs['numero_bon_commande']) ? 'has-error' : '' ?>">
                <label for="numero_bon_commande" class="col-sm-3 control-label">N° Bon de Commande:</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" id="numero_bon_commande" name="numero_bon_commande" value="<?= htmlspecialchars($facture['Numero_Bon_Commande'] ?? '') ?>" maxlength="100">
                    <?php if (isset($erreurs['numero_bon_commande'])): ?><div class="form-error"><?= $erreurs['numero_bon_commande'] ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-group <?= isset($erreurs['commentaire']) ? 'has-error' : '' ?>">
                <label for="commentaire" class="col-sm-3 control-label">Commentaire:</label>
                <div class="col-sm-9">
                    <textarea class="form-control" id="commentaire" name="commentaire" rows="4" maxlength="500"><?= htmlspecialchars($facture['Commentaire'] ?? '') ?></textarea>
                    <?php if (isset($erreurs['commentaire'])): ?><div class="form-error"><?= $erreurs['commentaire'] ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <div class="col-sm-offset-3 col-sm-9">
                    <button type="submit" class="btn btn-primary">
                        <i class="glyphicon glyphicon-save"></i> Enregistrer les modifications
                    </button>
                    <a href="listes_factures.php" class="btn btn-default">
                        <i class="glyphicon glyphicon-arrow-left"></i> Annuler
                    </a>
                </div>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
	<script src="../js/jquery-3.7.1.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script>
        // Petit script pour s'assurer que les montants sont bien formatés avant soumission si besoin,
        // ou pour aider à la saisie (ex: remplacer virgule par point).
        // Pour ce formulaire, la validation HTML5 pattern et la conversion PHP sont les principales.
        document.addEventListener('DOMContentLoaded', function() {
            const amountInputs = ['montant_ht', 'montant_tva', 'montant_ttc'];
            amountInputs.forEach(function(id) {
                const input = document.getElementById(id);
                if (input) {
                    input.addEventListener('input', function(e) {
                        // Optionnel: remplacer la virgule par un point à la volée
                        // e.target.value = e.target.value.replace(',', '.');
                    });
                }
            });
        });
    </script>
</body>
</html>

<?php
require_once('../../templates/footer.php');
?>