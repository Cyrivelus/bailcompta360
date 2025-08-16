<?php
// pages/comptes/liste_ligne_ecriture.php

// Ensure UTF-8 content type header is sent first
header('Content-Type: text/html; charset=UTF-8');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Africa/Douala');
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_comptes.php';
require_once '../../fonctions/gestion_ecritures.php';
require_once '../../fonctions/gestion_factures.php';
require_once '../../fonctions/gestion_lettrage.php'; // Inclure le fichier de fonctions de lettrage

// --- Vérification de la connexion PDO ---
if (!isset($pdo) || !$pdo instanceof PDO) {
    $messageErreur = "Erreur de configuration de la base de données: La connexion PDO n'a pas été correctement initialisée.";
    error_log("Erreur (liste_ligne_ecriture.php - PDO non initialisé) : " . $messageErreur);
    echo '<div class="container mt-5"><div class="alert alert-danger">' . htmlspecialchars($messageErreur) . '</div></div>';
    require_once('../../templates/footer.php');
    exit;
}

$titre = 'Liste des Lignes d\'écritures';

$compteId = filter_input(INPUT_GET, 'compte_id', FILTER_VALIDATE_INT);
$numeroCompte = filter_input(INPUT_GET, 'numero_compte', FILTER_UNSAFE_RAW);

if (!$compteId) {
    $messageErreur = "Erreur: ID de compte non spécifié.";
    error_log("Erreur (liste_ligne_ecriture.php - ID de compte non spécifié)");
    echo '<div class="container mt-5"><div class="alert alert-danger">' . htmlspecialchars($messageErreur) . '</div></div>';
    require_once('../../templates/footer.php');
    exit;
}

$compte = getCompteDetails($pdo, $compteId);
if (!$compte) {
    $messageErreur = "Erreur: Compte non trouvé.";
    error_log("Erreur (liste_ligne_ecriture.php - Compte non trouvé)");
    echo '<div class="container mt-5"><div class="alert alert-danger">' . htmlspecialchars($messageErreur) . '</div></div>';
    require_once('../../templates/footer.php');
    exit;
}

// --- Logique de lettrage ---
$lettrageMessage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'lettrer') {
    $lignesSelectionnees = isset($_POST['lignes_selectionnees']) ? $_POST['lignes_selectionnees'] : [];
    $lettre = isset($_POST['lettre_lettrage']) ? strtoupper(trim($_POST['lettre_lettrage'])) : '';
    
    if (empty($lignesSelectionnees) || empty($lettre)) {
        $lettrageMessage = ['type' => 'danger', 'text' => 'Veuillez sélectionner au moins une ligne et entrer une lettre de lettrage.'];
    } else {
        try {
            $succes = effectuerLettrage($pdo, $lignesSelectionnees, $lettre);
            if ($succes) {
                $lettrageMessage = ['type' => 'success', 'text' => 'Lettrage effectué avec succès.'];
            } else {
                $lettrageMessage = ['type' => 'danger', 'text' => 'Échec du lettrage. Assurez-vous que les lignes sont équilibrées.'];
            }
        } catch (Exception $e) {
            $lettrageMessage = ['type' => 'danger', 'text' => 'Erreur de lettrage : ' . $e->getMessage()];
        }
    }
}

$dateDebut = isset($_GET['date_debut']) && $_GET['date_debut'] !== '' ? $_GET['date_debut'] : null;
$dateFin = isset($_GET['date_fin']) && $_GET['date_fin'] !== '' ? $_GET['date_fin'] : null;

if ($dateDebut && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $dateDebut)) {
    $dateDebut = null;
}
if ($dateFin && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $dateFin)) {
    $dateFin = null;
}

if ($dateDebut && $dateFin && strtotime($dateFin) < strtotime($dateDebut)) {
    $messageErreur = "La date de fin ne peut pas être antérieure à la date de début.";
    error_log("Erreur (liste_ligne_ecriture.php - Date incohérente) : " . $messageErreur);
}

$soldeAnterieur = getSoldeAnterieur($pdo, $compteId, $dateDebut);
$debitAnterieur = $soldeAnterieur['total_debit'] ?? 0;
$creditAnterieur = $soldeAnterieur['total_credit'] ?? 0;
$soldeAnterieurValue = $creditAnterieur - $debitAnterieur;

$lignesEcritures = getLignesEcrituresByCompte($pdo, $compteId, $dateDebut, $dateFin);
$nombreLignes = count($lignesEcritures);

$debitCumul = 0;
$creditCumul = 0;

foreach ($lignesEcritures as $ligne) {
    $debit = $ligne['Sens'] == 'D' ? $ligne['Montant'] : 0;
    $credit = $ligne['Sens'] == 'C' ? $ligne['Montant'] : 0;
    $debitCumul += $debit;
    $creditCumul += $credit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Liste des Lignes d'écritures</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/tableau.css">
    <link rel="stylesheet" href="../../css/print.css" media="print">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .no-results { display: none; text-align: center; margin-top: 20px; font-size: 1.2em; color: #888; }
        .highlight { background-color: yellow; }
        .lettre-lettrage { font-weight: bold; color: #337ab7; }
        .print-header { text-align: center; margin-bottom: 20px; }
        .print-header .company-name { font-size: 16pt; font-weight: bold; margin-bottom: 5px; }
        .print-header .account-info { font-size: 12pt; font-weight: bold; margin-bottom: 10px; }
        .print-header .period-info { font-size: 10pt; margin-bottom: 15px; text-align: right; }
        .print-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .print-table th, .print-table td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        .print-table th { background-color: #f2f2f2; }
        .print-table .debit-col, .print-table .credit-col { text-align: right; }
        .print-summary-table { width: 100%; margin-top: 20px; }
        .print-summary-table th, .print-summary-table td { border: none; padding: 3px; text-align: right; }
        .print-summary-table th { font-weight: bold; }
        .print-summary-table .amount { font-weight: bold; }
        .positive { color: green; }
        .negative { color: red; }
        .printed-by { text-align: right; font-style: italic; margin-top: 10px; font-size: 9pt; }
        .lettrage-form .form-group { margin-right: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="print-header no-print">
            <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>
            <div class="compte-header" id="compte-header">
                <h3><?= htmlspecialchars($compte['Nom_Compte'] ?? 'Nom inconnu') ?></h3>
                <p>Numéro: <?= htmlspecialchars($compte['Numero_Compte'] ?? 'N/A') ?> | Type: <?= htmlspecialchars($compte['Type_Compte'] ?? 'N/A') ?></p>
            </div>
        </div>

        <?php if ($lettrageMessage): ?>
            <div class="alert alert-<?= htmlspecialchars($lettrageMessage['type']) ?>"><?= htmlspecialchars($lettrageMessage['text']) ?></div>
        <?php endif; ?>

        <div class="solde-info">
            <div class="row">
                <div class="col-md-4">
                    <strong>Solde antérieur :</strong>
                    <span class="<?= $soldeAnterieurValue >= 0 ? 'credit' : 'debit' ?>">
                        <?= number_format(abs($soldeAnterieurValue), 2, ',', ' ') ?>
                    </span>
                </div>
                <div class="col-md-4">
                    <strong>Débit période :</strong>
                    <span class="debit"><?= number_format($debitCumul, 2, ',', ' ') ?></span>
                </div>
                <div class="col-md-4">
                    <strong>Crédit période :</strong>
                    <span class="credit"><?= number_format($creditCumul, 2, ',', ' ') ?></span>
                </div>
            </div>
        </div>

        <div class="no-print">
            <div class="search-container">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="search-input">Rechercher une ligne d'écriture :</label>
                            <input type="text" id="search-input" class="form-control" placeholder="Libellé, montant, sens...">
                            <small class="form-text text-muted">La recherche filtre en temps réel</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-inline date-filter">
                            <div class="form-group mr-2">
                                <label for="date_debut" class="mr-2">Du :</label>
                                <input type="date" id="date_debut" name="date_debut" class="form-control" value="<?= htmlspecialchars($dateDebut ?? '') ?>">
                            </div>
                            <div class="form-group mr-2">
                                <label for="date_fin" class="mr-2">Au :</label>
                                <input type="date" id="date_fin" name="date_fin" class="form-control" value="<?= htmlspecialchars($dateFin ?? '') ?>">
                            </div>
                            <button id="btn-filtrer" class="btn btn-primary">Filtrer</button>
                            <button id="btn-reset-dates" class="btn btn-warning ml-2">Annuler</button>
                            <div class="btn-group ml-2">
                                <button type="button" class="btn btn-success dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    Imprimer extrait de compte <span class="caret"></span>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a href="#" id="btn-print-pdf">PDF</a></li>
                                    <li><a href="#" id="btn-print-csv">CSV</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form method="post" action="" class="lettrage-form" id="lettrage-form">
                <input type="hidden" name="action" value="lettrer">
                <div class="form-inline mt-3">
                    <div class="form-group">
                        <label for="lettre_lettrage">Lettre de lettrage :</label>
                        <input type="text" id="lettre_lettrage" name="lettre_lettrage" class="form-control" maxlength="1" required>
                    </div>
                    <button type="submit" class="btn btn-success" id="btn-lettrer-selection" disabled>
                        <span class="glyphicon glyphicon-tag"></span> Lettrer la sélection
                    </button>
                </div>
            </form>

            <div class="nombre-lignes">
                Nombre de lignes : <span id="nombre-lignes-value"><?= $nombreLignes ?></span>
            </div>
        </div>

        <div class="table-responsive">
            <table id="lignes-ecritures-list" class="table table-striped table-bordered table-hover">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all"></th>
                        <th>ID Ligne</th>
                        <th>ID Écriture</th>
                        <th>Date</th>
                        <th>Libellé</th>
                        <th>Contrepartie</th>
                        <th>Débit</th>
                        <th>Crédit</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="lignes-ecritures-list-body">
                    <?php
                    $currentSolde = $soldeAnterieurValue;
                    foreach ($lignesEcritures as $ligne):
                        $ecriture = getEcritureDetails($pdo, $ligne['ID_Ecriture']);
                        $sensClass = $ligne['Sens'] == 'D' ? 'debit' : 'credit';
                        $debit = $ligne['Sens'] == 'D' ? $ligne['Montant'] : 0;
                        $credit = $ligne['Sens'] == 'C' ? $ligne['Montant'] : 0;

                        if ($ligne['Sens'] == 'D') {
                            $currentSolde -= $ligne['Montant'];
                        } else {
                            $currentSolde += $ligne['Montant'];
                        }

                        $isFacture = false;
                        $factureDetails = null;
                        if (isset($ecriture['Description']) && strpos($ecriture['Description'], 'FACTURE') !== false) {
                            preg_match('/FACTURE (\d+)/', $ecriture['Description'], $matches);
                            if (isset($matches[1])) {
                                $factureId = $matches[1];
                                $factureDetails = getFactureDetails($pdo, $factureId);
                                if ($factureDetails && $factureDetails['Statut_Facture'] == 'Payé') {
                                    $isFacture = true;
                                }
                            }
                        }

                        $affichageContreparties = [];
                        $sensOppose = ($ligne['Sens'] === 'D') ? 'C' : 'D';

                        $counterpartQuery = "
                            SELECT cc.Nom_Compte, cc.Numero_Compte
                            FROM Lignes_Ecritures le
                            JOIN Comptes_compta cc ON cc.ID_Compte = le.ID_Compte
                            WHERE le.ID_Ecriture = :id_ecriture
                                AND le.Sens = :sens_oppose
                                AND le.ID_Compte != :id_compte_actuel
                        ";
                        
                        $stmt = $pdo->prepare($counterpartQuery);
                        $stmt->execute([
                            ':id_ecriture' => $ligne['ID_Ecriture'],
                            ':sens_oppose' => $sensOppose,
                            ':id_compte_actuel' => $ligne['ID_Compte']
                        ]);
                        $contreparties = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        $filteredContreparties = [];
                        foreach ($contreparties as $cp) {
                            $isRetentionAccount = ($cp['Numero_Compte'] === '433512000000');
                            $isRetentionName = (strpos(mb_strtolower($cp['Nom_Compte']), 'retenu') !== false);

                            if ($isFacture) {
                                if (!$isRetentionAccount && !$isRetentionName) {
                                    $filteredContreparties[] = htmlspecialchars($cp['Numero_Compte'] . ' - ' . $cp['Nom_Compte']);
                                }
                            } else {
                                $filteredContreparties[] = htmlspecialchars($cp['Numero_Compte'] . ' - ' . $cp['Nom_Compte']);
                            }
                        }
                        $affichageContreparties = $filteredContreparties;

                        $libelleForSearch = html_entity_decode($ecriture['Description'], ENT_QUOTES, 'UTF-8');
                        $contrepartieForSearch = html_entity_decode(implode(', ', $affichageContreparties), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr data-id="<?= htmlspecialchars($ligne['ID_Ligne_Ecriture']) ?>"
                            data-id-ecriture="<?= htmlspecialchars($ligne['ID_Ecriture']) ?>"
                            data-date="<?= htmlspecialchars($ecriture['Date_Saisie']) ?>"
                            data-libelle="<?= htmlspecialchars($libelleForSearch) ?>"
                            data-contrepartie="<?= htmlspecialchars($contrepartieForSearch) ?>"
                            data-debit="<?= htmlspecialchars($debit) ?>"
                            data-credit="<?= htmlspecialchars($credit) ?>"
                            data-lettrage="<?= htmlspecialchars($ligne['Lettre_Lettrage'] ?? '') ?>"
                            data-agence="<?= htmlspecialchars($ecriture['NumeroAgenceSCE'] ?? 'N/A') ?>"
                            data-utilisateur="<?= htmlspecialchars($ecriture['NomUtilisateur'] ?? 'N/A') ?>">
                            <td>
                                <?php if (!$ligne['Lettre_Lettrage']): ?>
                                    <input type="checkbox" name="lignes_selectionnees[]" value="<?= htmlspecialchars($ligne['ID_Ligne_Ecriture']) ?>" class="ligne-checkbox">
                                <?php endif; ?>
                            </td>
                            <td class="id-ligne"><?= htmlspecialchars($ligne['ID_Ligne_Ecriture']) ?></td>
                            <td class="id-ecriture"><?= htmlspecialchars($ligne['ID_Ecriture']) ?></td>
                            <td class="date"><?= htmlspecialchars($ecriture['Date_Saisie']) ?></td>
                            <td class="libelle"><?= htmlspecialchars($ecriture['Description']) ?></td>
                            <td class="contrepartie">
                                <?= implode('<br>', $affichageContreparties) ?>
                            </td>
                            <td class="debit">
                                <?php if ($ligne['Sens'] == 'D'): ?>
                                    <?= number_format($debit, 2, ',', ' ') ?>
                                <?php endif; ?>
                            </td>
                            <td class="credit">
                                <?php if ($ligne['Sens'] == 'C'): ?>
                                    <?= number_format($credit, 2, ',', ' ') ?>
                                <?php endif; ?>
                            </td>
                            <td class="statut">
                                <?php if ($ligne['Lettre_Lettrage']): ?>
                                    <span class="lettre-lettrage"><?= htmlspecialchars($ligne['Lettre_Lettrage']) ?></span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <?php if (!$ligne['Lettre_Lettrage']): ?>
                                    <a href="#" class="btn btn-xs btn-info lettrer-action-btn" data-toggle="modal" data-target="#lettrageModal" data-id="<?= htmlspecialchars($ligne['ID_Ligne_Ecriture']) ?>">
                                        <span class="glyphicon glyphicon-tag"></span> Lettrer
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="6" class="text-right">PÉRIODE</th>
                        <th class="debit"><?= number_format($debitCumul, 2, ',', ' ') ?></th>
                        <th class="credit"><?= number_format($creditCumul, 2, ',', ' ') ?></th>
                        <th colspan="2"></th>
                    </tr>
                    <tr>
                        <th colspan="6" class="text-right">CUMUL</th>
                        <th class="debit"><?= number_format($debitCumul, 2, ',', ' ') ?></th>
                        <th class="credit"><?= number_format($creditCumul, 2, ',', ' ') ?></th>
                        <th colspan="2"></th>
                    </tr>
                    <tr>
                        <th colspan="6" class="text-right">SOLDE</th>
                        <th colspan="2" class="<?= ($currentSolde) >= 0 ? 'credit' : 'debit' ?>">
                            <?= number_format(abs($currentSolde), 2, ',', ' ') ?>
                        </th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
            </table>
            <div id="no-results" class="no-results">Aucune ligne d'écriture ne correspond à votre recherche</div>
        </div>

        <div class="print-footer">
            <div class="row">
                <div class="col-md-4">
                    <strong>Période :</strong> Du <?= htmlspecialchars($dateDebut ?? 'début') ?> au <?= htmlspecialchars($dateFin ?? 'fin') ?>
                </div>
                <div class="col-md-4">
                    <strong>Cumul :</strong>
                    Débit: <span class="debit"><?= number_format($debitCumul, 2, ',', ' ') ?></span> |
                    Crédit: <span class="credit"><?= number_format($creditCumul, 2, ',', ' ') ?></span>
                </div>
                <div class="col-md-4">
                    <strong>Solde :</strong>
                    <span class="<?= ($currentSolde) >= 0 ? 'credit' : 'debit' ?>">
                        <?= number_format(abs($currentSolde), 2, ',', ' ') ?>
                    </span>
                </div>
            </div>
            <div class="printed-by">
                Imprimé par : <?= htmlspecialchars($_SESSION['nom_utilisateur'] ?? 'Utilisateur inconnu') ?>
                le <?= date('d/m/Y à H:i') ?>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="lettrageModal" tabindex="-1" role="dialog" aria-labelledby="lettrageModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="lettrageModalLabel">Lettrage de ligne(s)</h4>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            Vous êtes sur le point de lettrer la ligne sélectionnée (ou le groupe de lignes).
                        </div>
                        <div class="form-group">
                            <label for="lettre_lettrage_modal">Entrez la lettre de lettrage :</label>
                            <input type="text" class="form-control" id="lettre_lettrage_modal" name="lettre_lettrage" maxlength="1" required>
                        </div>
                        <p class="text-danger" id="lettrage-modal-error" style="display:none;"></p>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="action" value="lettrer">
                        <input type="hidden" id="lignes_a_lettrer_modal" name="lignes_selectionnees[]">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary" id="btn-modal-lettrer">Lettrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
	 <script src="../../js/jquery-3.7.1.js"></script>
	 <script src="../../js/bootstrap.min.js"></script>
    <script>
    $(document).ready(function() {
        // Fonctions pour la recherche et le filtrage
        function filterResults(searchTerm) {
            const $rows = $('#lignes-ecritures-list-body tr');
            let hasResults = false;
            let count = 0;
            const searchLower = searchTerm.toLowerCase();

            $rows.each(function() {
                const $row = $(this);
                const dataLibelle = $row.data('libelle').toLowerCase();
                const dataContrepartie = $row.data('contrepartie').toLowerCase();
                const dataDebit = $row.data('debit').toString().toLowerCase();
                const dataCredit = $row.data('credit').toString().toLowerCase();
                const dataLettrage = $row.data('lettrage').toString().toLowerCase();
                const dataAgence = $row.data('agence').toLowerCase();
                const dataUtilisateur = $row.data('utilisateur').toLowerCase();

                const matches = [
                    dataLibelle,
                    dataContrepartie,
                    dataDebit,
                    dataCredit,
                    dataLettrage,
                    dataAgence,
                    dataUtilisateur
                ].some(text => text.includes(searchLower));

                if (matches) {
                    $row.show();
                    hasResults = true;
                    count++;
                    if (searchTerm.length > 0) {
                        highlightText($row, searchTerm);
                    } else {
                        removeHighlight($row);
                    }
                } else {
                    $row.hide();
                    removeHighlight($row);
                }
            });

            $('#nombre-lignes-value').text(count);
            $('#no-results').toggle(!hasResults);
        }

        function highlightText($row, searchTerm) {
            const highlight = (text, term) => {
                const escapedTerm = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                return text.replace(new RegExp(`(${escapedTerm})`, 'gi'), '<span class="highlight">$1</span>');
            };

            const columnsToHighlight = ['.libelle', '.contrepartie', '.statut'];

            columnsToHighlight.forEach(selector => {
                $row.find(selector).each(function() {
                    const $el = $(this);
                    const originalHtml = $el.html();
                    const textWithoutHighlights = originalHtml.replace(/<span class="highlight">(.*?)<\/span>/gi, '$1');
                    $el.html(highlight(textWithoutHighlights, searchTerm));
                });
            });
        }

        function removeHighlight($row) {
            $row.find('.libelle, .contrepartie, .statut').each(function() {
                const $el = $(this);
                $el.html($el.html().replace(/<span class="highlight">(.*?)<\/span>/gi, '$1'));
            });
        }

        // Gestion des événements de filtrage
        $('#search-input').on('input', function() {
            const searchTerm = $(this).val().trim();
            filterResults(searchTerm);
        });

        if ($('#search-input').val().trim().length > 0) {
            filterResults($('#search-input').val().trim());
        }

        $('#btn-filtrer').on('click', function() {
            const dateDebut = $('#date_debut').val();
            const dateFin = $('#date_fin').val();

            if (dateDebut && dateFin && new Date(dateFin) < new Date(dateDebut)) {
                alert('La date de fin doit être postérieure à la date de début.');
                return;
            }

            // Reconstruction de l'URL avec les nouveaux paramètres
            const url = new URL(window.location.origin + window.location.pathname);
            url.searchParams.set('compte_id', '<?= $compteId ?>');
            if (dateDebut) {
                url.searchParams.set('date_debut', dateDebut);
            }
            if (dateFin) {
                url.searchParams.set('date_fin', dateFin);
            }
            window.location.href = url.toString();
        });

        $('#btn-reset-dates').on('click', function() {
            const url = new URL(window.location.origin + window.location.pathname);
            url.searchParams.set('compte_id', '<?= $compteId ?>');
            window.location.href = url.toString();
        });

        // Gestion du lettrage
        $('#select-all').on('click', function() {
            $('.ligne-checkbox').prop('checked', this.checked);
            toggleLettrageButton();
        });

        $(document).on('click', '.ligne-checkbox', function() {
            toggleLettrageButton();
        });

        function toggleLettrageButton() {
            if ($('.ligne-checkbox:checked').length > 0) {
                $('#btn-lettrer-selection').prop('disabled', false);
            } else {
                $('#btn-lettrer-selection').prop('disabled', true);
            }
        }
        
        // Clic sur le bouton de lettrage individuel
        $('.lettrer-action-btn').on('click', function(e) {
            e.preventDefault();
            const idLigne = $(this).data('id');
            $('#lignes_a_lettrer_modal').val(idLigne);
            $('#lettrageModal').modal('show');
        });

        // Clic sur le bouton lettrer la sélection
        $('#btn-lettrer-selection').on('click', function(e) {
            e.preventDefault();
            const selectedIds = $('.ligne-checkbox:checked').map(function() {
                return this.value;
            }).get();
            if (selectedIds.length > 0) {
                $('#lignes_a_lettrer_modal').val(selectedIds.join(','));
                $('#lettrageModal').modal('show');
            } else {
                alert('Veuillez sélectionner au moins une ligne à lettrer.');
            }
        });

        // Gérer la soumission du formulaire de lettrage via modal
        $('#lettrageModal form').on('submit', function(e) {
            e.preventDefault();
            const lettre = $('#lettre_lettrage_modal').val().trim();
            const lignes = $('#lignes_a_lettrer_modal').val();

            if (!lettre) {
                $('#lettrage-modal-error').text('Veuillez entrer une lettre de lettrage.').show();
                return;
            }

            const form = $(this);
            const formData = form.serialize() + '&lignes_selectionnees=' + lignes;

            $.ajax({
                url: window.location.pathname,
                type: 'POST',
                data: formData,
                success: function(response) {
                    // Recharger la page pour voir les changements
                    window.location.reload();
                },
                error: function(xhr, status, error) {
                    const errorMessage = xhr.responseJSON ? xhr.responseJSON.message : 'Une erreur est survenue lors du lettrage.';
                    $('#lettrage-modal-error').text(errorMessage).show();
                }
            });
        });

        // Fonctions pour l'impression
        $('#btn-print-pdf').on('click', function(e) {
            e.preventDefault();
            const printWindow = window.open('', '_blank');
            let tableContent = '';
            
            // Collecte des données visibles
            $('#lignes-ecritures-list-body tr:visible').each(function() {
                const $row = $(this);
                const date = $row.find('.date').text();
                const idEcriture = $row.find('.id-ecriture').text();
                const libelle = $row.find('.libelle').text();
                const contrepartie = $row.find('.contrepartie').html().replace(/<br>/g, ' | ');
                const debit = $row.find('.debit').text().trim();
                const credit = $row.find('.credit').text().trim();
                const statut = $row.find('.statut').text().trim();
                tableContent += `
                    <tr>
                        <td>${date}</td>
                        <td>${idEcriture}</td>
                        <td>${libelle}</td>
                        <td>${contrepartie}</td>
                        <td class="debit-col">${debit}</td>
                        <td class="credit-col">${credit}</td>
                        <td>${statut}</td>
                    </tr>
                `;
            });

            // Préparation du contenu HTML pour l'impression
            const dateDebutPrinted = $('#date_debut').val() || 'N/A';
            const dateFinPrinted = $('#date_fin').val() || 'N/A';
            const soldeAnterieurPrinted = '<?= number_format(abs($soldeAnterieurValue), 2, ',', ' ') ?>';
            const debitCumulPrinted = '<?= number_format($debitCumul, 2, ',', ' ') ?>';
            const creditCumulPrinted = '<?= number_format($creditCumul, 2, ',', ' ') ?>';
            const currentSoldePrinted = '<?= number_format(abs($currentSolde), 2, ',', ' ') ?>';
            const soldeClass = '<?= ($currentSolde) >= 0 ? 'positive' : 'negative' ?>';
            const printedBy = '<?= htmlspecialchars($_SESSION['nom_utilisateur'] ?? 'Utilisateur inconnu') ?>';
            const printedDate = '<?= date('d/m/Y à H:i') ?>';

            const printContent = `
                <!DOCTYPE html>
                <html lang="fr">
                <head>
                    <meta charset="UTF-8">
                    <title>Extrait de Compte - <?= htmlspecialchars($compte['Numero_Compte'], ENT_QUOTES, 'UTF-8') ?></title>
                    <link rel="stylesheet" href="../../css/print.css">
                    <style>
                        body { font-family: sans-serif; font-size: 10pt; margin: 20px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        .text-right { text-align: right; }
                        .positive { color: green; }
                        .negative { color: red; }
                        .header { text-align: center; margin-bottom: 20px; }
                        .header h1 { font-size: 16pt; margin: 0; }
                        .header p { font-size: 10pt; margin: 5px 0; }
                        .summary { margin-top: 20px; text-align: right; }
                        .summary table { width: auto; margin-left: auto; }
                        .debit-col, .credit-col { text-align: right; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Extrait de Compte</h1>
                        <p><strong>Compte :</strong> <?= htmlspecialchars($compte['Numero_Compte'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($compte['Nom_Compte'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p><strong>Période :</strong> Du ${dateDebutPrinted} au ${dateFinPrinted}</p>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>ID Écriture</th>
                                <th>Libellé</th>
                                <th>Contrepartie</th>
                                <th class="text-right">Débit</th>
                                <th class="text-right">Crédit</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${tableContent}
                        </tbody>
                    </table>
                    <div class="summary">
                        <p><strong>Solde antérieur :</strong> <span class="amount <?= ($soldeAnterieurValue) >= 0 ? 'positive' : 'negative' ?>">${soldeAnterieurPrinted}</span></p>
                        <p><strong>Total Débit :</strong> <span class="amount negative">${debitCumulPrinted}</span></p>
                        <p><strong>Total Crédit :</strong> <span class="amount positive">${creditCumulPrinted}</span></p>
                        <hr>
                        <p><strong>Solde final :</strong> <span class="amount ${soldeClass}">${currentSoldePrinted}</span></p>
                    </div>
                    <div class="printed-by">Imprimé par : ${printedBy} le ${printedDate}</div>
                </body>
                </html>
            `;
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        });

        $('#btn-print-csv').on('click', function(e) {
            e.preventDefault();
            let csvContent = "data:text/csv;charset=utf-8,\uFEFF"; // BOM for UTF-8
            csvContent += "ID Ligne;ID Ecriture;Date;Libelle;Contrepartie;Débit;Crédit;Statut\r\n";
            
            // Collecte des données visibles
            $('#lignes-ecritures-list-body tr:visible').each(function() {
                const $row = $(this);
                const idLigne = $row.find('.id-ligne').text();
                const idEcriture = $row.find('.id-ecriture').text();
                const date = $row.find('.date').text();
                const libelle = `"${$row.find('.libelle').text().replace(/"/g, '""')}"`;
                const contrepartie = `"${$row.find('.contrepartie').text().replace(/\s*<br>\s*/g, ' | ').replace(/"/g, '""')}"`;
                const debit = $row.find('.debit').text().trim();
                const credit = $row.find('.credit').text().trim();
                const statut = `"${$row.find('.statut').text().trim()}"`;
                csvContent += `${idLigne};${idEcriture};${date};${libelle};${contrepartie};${debit};${credit};${statut}\r\n`;
            });

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "extrait_compte_<?= htmlspecialchars($compte['Numero_Compte'], ENT_QUOTES, 'UTF-8') ?>.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    });
    </script>
    <?php require_once('../../templates/footer.php'); ?>
</body>
</html>