<?php
// pages/factures/listes_factures.php
// Ce fichier affiche la liste des factures fournisseurs
// avec des options pour consulter, modifier, supprimer et payer.

// --- Configuration et Includes ---
ini_set('display_errors', 1);        // Afficher les erreurs PHP (pour le débogage)
ini_set('display_startup_errors', 1); // Afficher les erreurs de démarrage
error_reporting(E_ALL);              // Rapporter tous les types d'erreurs
ini_set('default_charset', 'UTF-8');  // Définir l'encodage par défaut
mb_internal_encoding('UTF-8');       // Définir l'encodage interne pour les fonctions multibytes

// Démarrer la session si elle n'est pas déjà démarrée. Essentiel pour le token CSRF et l'utilisateur connecté.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$titre = 'Liste des Factures Fournisseurs'; // Titre de la page
$current_page = basename(__FILE__);          // Nom du fichier actuel pour la navigation

// Inclure les templates et les fonctions nécessaires. Assurez-vous que les chemins sont corrects.
require_once('../../templates/header.php');         // Entête HTML et balises <head>
require_once('../../templates/navigation.php');     // Menu de navigation latéral
require_once('../../fonctions/database.php');      // Pour la connexion PDO ($pdo)
require_once('../../fonctions/gestion_factures.php');
require_once('../../fonctions/gestion_ecritures.php'); // Pour getListeFactures, getFactureById (si définie ici)

// --- Vérification de la connexion PDO ---
// S'assurer que l'objet PDO a été correctement initialisé par database.php.
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("ERREUR FATALE: \$pdo n'a pas été initialisé par database.php dans listes_factures.php");
    // Afficher un message d'erreur critique à l'utilisateur et arrêter l'exécution.
    die("Une erreur critique de configuration de la base de données est survenue. Veuillez contacter l'administrateur.");
}

// --- Protection CSRF (Cross-Site Request Forgery) ---
// Génère un token CSRF unique par session si un n'existe pas encore.
// Ce token sera inclus dans les formulaires POST pour validation côté traitement.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Génère une chaîne hexadécimale aléatoire de 64 caractères.
}
$csrf_token = $_SESSION['csrf_token']; // Récupère le token pour l'inclure dans les formulaires.

// --- Fonctions d'aide (si définies ici et non incluses ailleurs) ---
// Si votre fonction getFactureById est définie dans gestion_factures.php, vous pouvez supprimer ce bloc.
if (!function_exists('getFactureById')) {
    /**
     * Récupère les détails d'une facture par son ID.
     * @param PDO $pdo L'objet PDO.
     * @param int $idFacture L'ID de la facture.
     * @return array|false Les détails de la facture ou false si non trouvée.
     */
    function getFactureById($pdo, $idFacture) {
        $stmt = $pdo->prepare("SELECT * FROM Factures WHERE ID_Facture = :id");
        $stmt->execute([':id' => $idFacture]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Fonction d'aide pour formater les dates affichées. Peut être déplacée dans un fichier d'utilitaires généraux.
if (!function_exists('formatDate')) {
    /**
     * Formate une date au format jour/mois/année ou retourne 'N/A' si la date est nulle ou invalide.
     * @param string|null $date La date à formater (doit être un format valide pour strtotime).
     * @return string La date formatée (d/m/Y) ou la chaîne 'N/A'.
     */
    function formatDate($date) {
        // Vérifie si la date n'est pas vide et si elle est valide pour strtotime().
        return ($date && strtotime($date) !== false) ? date('d/m/Y', strtotime($date)) : 'N/A';
    }
}

// --- Récupération des données ---
// Récupérer la liste de toutes les factures pour les afficher dans le tableau.
// Assurez-vous que la fonction getListeFactures() est définie dans fonctions/gestion_factures.php
// et qu'elle sélectionne toutes les colonnes nécessaires (Numéro_Facture, Montant_TTC, Statut_Facture, etc.).
$factures = getListeFactures($pdo);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titre) ?> - BailCompta 360</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/select2-bootstrap-5-theme.min">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-theme/0.1.0-beta.10/select2-bootstrap.min.css" rel="stylesheet" />
    <style>
        /* Styles spécifiques à cette page */
        .container {
            max-width: 1400px;
            padding: 80px 20px 20px;
            margin: 0 auto;
        }
        body {
            padding-left: 200px;
        }
        h2 {
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .table-responsive {
            margin-bottom: 20px;
        }
        .table thead th {
            background-color: #f0f0f0;
            color: #2c3e50;
            border-bottom: 2px solid #ddd;
            vertical-align: middle;
            text-align: center;
        }
        .table tbody tr td {
            vertical-align: middle;
            padding: 8px;
        }

        .btn-actions {
            display: flex;
            gap: 5px;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
            line-height: 1.5;
            border-radius: 3px;
        }

        /* Couleurs des boutons Bootstrap */
        .btn-primary { background-color: #3498db; border-color: #3498db; }
        .btn-primary:hover { background-color: #2980b9; border-color: #2980b9; }
        .btn-success { background-color: #2ecc71; border-color: #2ecc71; }
        .btn-success:hover { background-color: #27ae60; border-color: #27ae60; }
        .btn-warning { background-color: #f39c12; border-color: #f39c12; }
        .btn-warning:hover { background-color: #d68910; border-color: #d68910; }
        .btn-danger { background-color: #e74c3c; border-color: #e74c3c; }
        .btn-danger:hover { background-color: #c0392b; border-color: #c0392b; }
        .btn-info { background-color: #3498db; border-color: #3498db; } /* Couleur pour le bouton Payer */
        .btn-info:hover { background-color: #2980b9; border-color: #2980b9; }

        /* Styles pour le texte centré */
        .text-center { text-align: center; }
        .text-right { text-align: right; }

        /* Styles pour les messages d'alerte */
        .alert { margin-bottom: 20px; }

        /* Styles pour les statuts de facture */
        .status-paid { color: green; font-weight: bold; }
        .status-due { color: orange; }
        .status-overdue { color: red; font-weight: bold; }
        
        /* Ajustement pour les boutons de paiement pour un meilleur alignement */
        .payment-actions {
            display: flex;
            flex-direction: column; /* Empile les boutons verticalement */
            gap: 5px; /* Espacement entre les boutons */
            align-items: center; /* Centre horizontalement les boutons */
        }
    </style>
</head>
<body>
    <div class="container">
        <h2><?= htmlspecialchars($titre) ?></h2>

        <?php // Afficher les messages de succès ou d'erreur ?>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <?= htmlspecialchars($_GET['success']) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success_saisie'])): ?>
            <div class="alert alert-success alert-dismissible" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <?= htmlspecialchars($_GET['success_saisie']) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Numéro</th>
                        <th>Type</th>
                        <th>Émission</th>
                        <th>Réception</th>
                        <th>Échéance</th>
                        <th>Fournisseur</th>
                        <th>Montant TTC</th>
                        <th>Statut</th>
                        <th>Écriture</th>
                        <th>Actions</th>
                        <th>Paiement</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($factures)): ?>
                        <tr>
                            <td colspan="11" class="text-center">Aucune facture trouvée.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($factures as $facture): ?>
                            <?php
                                // Déterminer la classe CSS pour le statut de la facture
                                $statusClass = '';
                                if ($facture['Statut_Facture'] === 'Payé') {
                                    $statusClass = 'status-paid';
                                } elseif ($facture['Statut_Facture'] === 'Dû') {
                                    $statusClass = 'status-due';
                                }
                                // TODO: Ajouter la logique pour déterminer 'status-overdue' basée sur la date d'échéance.
                                // Exemple: if ($facture['Statut_Facture'] === 'Dû' && strtotime($facture['Date_Echeance']) < time()) { $statusClass = 'status-overdue'; }
                            ?>
                            <tr>
                                <td class="text-center"><?= htmlspecialchars($facture['Numero_Facture']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($facture['Type_Facture'] ?? 'N/A') ?></td>
                                <td class="text-center"><?= formatDate($facture['Date_Emission']) ?></td>
                                <td class="text-center"><?= formatDate($facture['Date_Reception']) ?></td>
                                <td class="text-center"><?= formatDate($facture['Date_Echeance']) ?></td>
                                <td><?= htmlspecialchars($facture['Nom_Fournisseur'] ?? 'N/A') ?></td>
                                <td class="text-right"><?= number_format($facture['Montant_TTC'], 2, ',', ' ') ?> FCFA</td>
                                <td class="text-center <?= $statusClass ?>"><?= htmlspecialchars($facture['Statut_Facture'] ?? 'N/A') ?></td>
                                <td class="text-center">
                                    <?php if (!empty($facture['ID_Ecriture_Comptable'])): ?>
                                        <a href="../ecritures/modifier.php?id=<?= $facture['ID_Ecriture_Comptable'] ?>" title="Voir ou modifier l'écriture comptable liée">
                                            <?= htmlspecialchars($facture['ID_Ecriture_Comptable']) ?> <i class="glyphicon glyphicon-link"></i>
                                        </a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-actions">
                                        <a href="voir_facture.php?id=<?= $facture['ID_Facture'] ?>" class="btn btn-sm btn-primary" title="Voir les détails de la facture">
                                            <i class="glyphicon glyphicon-eye-open"></i> Voir
                                        </a>
                                        <a href="modifier_facture.php?id=<?= $facture['ID_Facture'] ?>" class="btn btn-sm btn-warning" title="Modifier la facture">
                                            <i class="glyphicon glyphicon-pencil"></i> Modifier
                                        </a>
                                        <form method="POST" action="supprimer_facture.php" style="display:inline;"
                                                onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette facture N° <?= htmlspecialchars($facture['Numero_Facture']) ?> ? Cette action est irréversible.')">
                                            <input type="hidden" name="facture_id" value="<?= $facture['ID_Facture'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Supprimer la facture">
                                                <i class="glyphicon glyphicon-trash"></i> Supprimer
                                            </button>
                                        </form>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($facture['Statut_Facture'] !== 'Payé'): ?>
                                        <div class="payment-actions">
                                            <form method="POST" action="process_payment.php" style="display:inline;"
                                                onsubmit="return handlePaymentConfirmation(event, '<?= htmlspecialchars($facture['Numero_Facture']) ?>', '<?= $facture['ID_Facture'] ?>');">
                                                <input type="hidden" name="action" value="payer">
                                                <input type="hidden" name="facture_id" value="<?= $facture['ID_Facture'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="payment_method" id="payment_method_<?= $facture['ID_Facture'] ?>" value="">
                                                <input type="hidden" name="bank_name" id="bank_name_<?= $facture['ID_Facture'] ?>" value="">
                                                <button type="submit" class="btn btn-sm btn-info" title="Marquer la facture comme Payée avec détails">
                                                    <i class="glyphicon glyphicon-ok-circle"></i> Payer (détails)
                                                </button>
                                            </form>
                                            <form method="POST" action="process_payment_sans_banque.php" style="display:inline;">
                                                <input type="hidden" name="action" value="payer">
                                                <input type="hidden" name="facture_id" value="<?= htmlspecialchars($facture['ID_Facture']) ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="payment_method" value="sans_banque">
                                                <input type="hidden" name="bank_name" value="">
                                                <button type="submit" class="btn btn-sm btn-info" title="Marquer la facture comme Payée sans détails de banque">
                                                    <span>xaf</span> Payer sans banque
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span class="status-paid"><i class="glyphicon glyphicon-check"></i> Payée</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <a href="integration.php" class="btn btn-success">
            <i class="glyphicon glyphicon-plus"></i> Ajouter une Facture
        </a>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script src="../../js/jquery-3.7.1.min.js"></script>
    <script src="../../js/bootstrap.min.js"></script>
    <script src="../../js/select2.min.js"></script>
    <script>
        function handlePaymentConfirmation(event, numeroFacture, factureId) {
            event.preventDefault(); // Empêche la soumission par défaut du formulaire

            let paymentMethod = '';
            // Boucle pour forcer le choix entre 'cheque' ou 'virement'
            while (paymentMethod !== 'cheque' && paymentMethod !== 'virement') {
                paymentMethod = prompt(`Confirmer le paiement de la facture N° ${numeroFacture} ?\n\nMode de paiement (tapez 'cheque' ou 'virement'):`);
                if (paymentMethod === null) { // L'utilisateur a cliqué sur Annuler
                    return false;
                }
                paymentMethod = paymentMethod.toLowerCase().trim(); // Nettoyer l'entrée
            }

            let bankName = '';
            if (paymentMethod === 'cheque' || paymentMethod === 'virement') {
                // Demander le nom de la banque
                bankName = prompt(`Veuillez entrer le nom de la banque pour le paiement par ${paymentMethod}:`);
                if (bankName === null) { // L'utilisateur a cliqué sur Annuler
                    return false;
                }
                bankName = bankName.trim(); // Nettoyer l'entrée
            }

            // Met à jour le champ caché avec le mode de paiement choisi
            document.getElementById('payment_method_' + factureId).value = paymentMethod;
            // Met à jour le champ caché avec le nom de la banque
            document.getElementById('bank_name_' + factureId).value = bankName;

            // Soumet le formulaire
            event.target.submit();
            return true;
        }

        $(document).ready(function() {
            // Initialiser les tooltips pour les boutons d'action et le bouton Payer
            $('[data-toggle="tooltip"]').tooltip(); // Utiliser data-toggle="tooltip" pour l'initialisation automatique

            // Rendre les alertes dismissible (fermables)
            $('.alert').alert();
        });
    </script>
</body>
</html>

<?php
// Inclure le pied de page
require_once('../../templates/footer.php');
?>