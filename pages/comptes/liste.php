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
require_once '../../fonctions/gestion_factures.php'; // Assuming this function exists for invoice details

// --- Vérification de la connexion PDO ---
// It's crucial that your database connection in `database.php` is configured for UTF-8.
// For SQL Server, ensure your DSN includes `CharacterSet=UTF-8;` if applicable, or that the SQL Server instance
// and database collation are set to a UTF-8 compatible collation (e.g., French_CI_AS with a UTF-8 code page).
// If you are using MySQL, ensure DSN has `charset=utf8mb4` and `SET NAMES utf8mb4` is executed after connection.
if (!isset($pdo) || !$pdo instanceof PDO) {
    $messageErreur = "Erreur de configuration de la base de données: La connexion PDO n'a pas été correctement initialisée.";
    error_log("Erreur (liste_ligne_ecriture.php - PDO non initialisé) : " . $messageErreur);
    echo '<div class="container mt-5"><div class="alert alert-danger">' . htmlspecialchars($messageErreur) . '</div></div>';
    require_once('../../templates/footer.php');
    exit;
}

$titre = 'Liste des Lignes d\'écritures';

// Récupérer les paramètres de l'URL (compte_id)
$compteId = filter_input(INPUT_GET, 'compte_id', FILTER_VALIDATE_INT);
// Use FILTER_UNSAFE_RAW for string and ensure proper encoding for storage/display if needed
$numeroCompte = filter_input(INPUT_GET, 'numero_compte', FILTER_UNSAFE_RAW);

if (!$compteId) {
    $messageErreur = "Erreur: ID de compte non spécifié.";
    error_log("Erreur (liste_ligne_ecriture.php - ID de compte non spécifié)");
    echo '<div class="container mt-5"><div class="alert alert-danger">' . htmlspecialchars($messageErreur) . '</div></div>';
    require_once('../../templates/footer.php');
    exit;
}

// Récupérer les détails du compte
$compte = getCompteDetails($pdo, $compteId);
if (!$compte) {
    $messageErreur = "Erreur: Compte non trouvé.";
    error_log("Erreur (liste_ligne_ecriture.php - Compte non trouvé)");
    echo '<div class="container mt-5"><div class="alert alert-danger">' . htmlspecialchars($messageErreur) . '</div></div>';
    require_once('../../templates/footer.php');
    exit;
}

// Récupérer les dates pour le filtre
$dateDebut = isset($_GET['date_debut']) && $_GET['date_debut'] !== '' ? $_GET['date_debut'] : null;
$dateFin = isset($_GET['date_fin']) && $_GET['date_fin'] !== '' ? $_GET['date_fin'] : null;

// Validate dates if they are provided
if ($dateDebut && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $dateDebut)) {
    $dateDebut = null; // Invalid date format
}
if ($dateFin && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $dateFin)) {
    $dateFin = null; // Invalid date format
}

// Ensure dateFin is not before dateDebut if both are set
if ($dateDebut && $dateFin && strtotime($dateFin) < strtotime($dateDebut)) {
    // Optionally, reset one of the dates or show an error
    $messageErreur = "La date de fin ne peut pas être antérieure à la date de début.";
    error_log("Erreur (liste_ligne_ecriture.php - Date incohérente) : " . $messageErreur);
    // You might want to unset one or both dates here to avoid an empty table
    // For now, let the functions handle potentially empty results.
}


// Calculer le solde anterieur
$soldeAnterieur = getSoldeAnterieur($pdo, $compteId, $dateDebut);
$debitAnterieur = $soldeAnterieur['total_debit'] ?? 0;
$creditAnterieur = $soldeAnterieur['total_credit'] ?? 0;
$soldeAnterieurValue = $creditAnterieur - $debitAnterieur;

// Récupérer les lignes d'écriture pour ce compte avec tri par date
$lignesEcritures = getLignesEcrituresByCompte($pdo, $compteId, $dateDebut, $dateFin);
$nombreLignes = count($lignesEcritures);

// Initialize cumulative debit and credit
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
        /* Vos styles existants... */
        .no-results {
            display: none;
            text-align: center;
            margin-top: 20px;
            font-size: 1.2em;
            color: #888;
        }
        .highlight {
            background-color: yellow;
        }
        /* Styles for print version (PDF/CSV visual consistency) */
        .print-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .print-header .company-name {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .print-header .account-info {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .print-header .period-info {
            font-size: 10pt;
            margin-bottom: 15px;
            text-align: right;
        }
        .print-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .print-table th, .print-table td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        .print-table th {
            background-color: #f2f2f2;
        }
        .print-table .debit-col, .print-table .credit-col {
            text-align: right;
        }
        .print-summary-table {
            width: 100%;
            margin-top: 20px;
        }
        .print-summary-table th, .print-summary-table td {
            border: none;
            padding: 3px;
            text-align: right;
        }
        .print-summary-table th {
            font-weight: bold;
        }
        .print-summary-table .amount {
            font-weight: bold;
        }
        .positive { color: green; }
        .negative { color: red; }
        .printed-by {
            text-align: right;
            font-style: italic;
            margin-top: 10px;
            font-size: 9pt;
        }
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

            <div class="nombre-lignes">
                Nombre de lignes : <span id="nombre-lignes-value"><?= $nombreLignes ?></span>
            </div>
        </div>

        <div class="table-responsive">
            <table id="lignes-ecritures-list" class="table table-striped table-bordered table-hover">
                <thead>
                    <tr>
                        <th>ID Ligne</th>
                        <th>ID Écriture</th>
                        <th>Date</th>
                        <th>Libellé</th>
                        <th>Contrepartie</th>
                        <th>Débit</th>
                        <th>Crédit</th>
                        <th>Agence</th>
                        <th>Utilisateur</th>
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

                        // Update current balance
                        if ($ligne['Sens'] == 'D') {
                            $currentSolde -= $ligne['Montant'];
                        } else {
                            $currentSolde += $ligne['Montant'];
                        }

                        // Determine if it's an invoice related line
                        $isFacture = false;
                        $factureDetails = null;
                        if (isset($ecriture['Description']) && strpos($ecriture['Description'], 'FACTURE') !== false) {
                            // Extract invoice ID, assuming format "FACTURE [ID]"
                            preg_match('/FACTURE (\d+)/', $ecriture['Description'], $matches);
                            if (isset($matches[1])) {
                                $factureId = $matches[1];
                                $factureDetails = getFactureDetails($pdo, $factureId);
                                if ($factureDetails && $factureDetails['Statut_Facture'] == 'Payé') {
                                    $isFacture = true;
                                }
                            }
                        }

                        // Récupération des contreparties for display
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

                        // Filter out the specific account and accounts with "retenu" in their name for invoice counterparty
                        $filteredContreparties = [];
                        foreach ($contreparties as $cp) {
                            $isRetentionAccount = ($cp['Numero_Compte'] === '433512000000');
                            $isRetentionName = (strpos(mb_strtolower($cp['Nom_Compte']), 'retenu') !== false);

                            if ($isFacture) {
                                // For invoices, exclude specific retention account AND any account with "retenu" in its name
                                if (!$isRetentionAccount && !$isRetentionName) {
                                    $filteredContreparties[] = htmlspecialchars($cp['Numero_Compte'] . ' - ' . $cp['Nom_Compte']);
                                }
                            } else {
                                // For non-invoices, always include all counterparts (or apply other general filters if needed)
                                $filteredContreparties[] = htmlspecialchars($cp['Numero_Compte'] . ' - ' . $cp['Nom_Compte']);
                            }
                        }
                        $affichageContreparties = $filteredContreparties;

                        // Decode HTML entities if necessary for data attributes for JavaScript search
                        $libelleForSearch = html_entity_decode($ecriture['Description'], ENT_QUOTES, 'UTF-8');
                        $contrepartieForSearch = html_entity_decode(implode(', ', $affichageContreparties), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr data-id="<?= htmlspecialchars($ligne['ID_Ligne']) ?>"
                            data-id-ecriture="<?= htmlspecialchars($ligne['ID_Ecriture']) ?>"
                            data-date="<?= htmlspecialchars($ecriture['Date_Saisie']) ?>"
                            data-libelle="<?= htmlspecialchars($libelleForSearch) ?>"
                            data-contrepartie="<?= htmlspecialchars($contrepartieForSearch) ?>"
                            data-debit="<?= htmlspecialchars($debit) ?>"
                            data-credit="<?= htmlspecialchars($credit) ?>"
                            data-agence="<?= htmlspecialchars($ecriture['NumeroAgenceSCE']) ?>"
                            data-utilisateur="<?= htmlspecialchars($ecriture['NomUtilisateur'] ?? 'N/A') ?>">
                            <td class="id-ligne"><?= htmlspecialchars($ligne['ID_Ligne']) ?></td>
                            <td class="id-ecriture"><?= htmlspecialchars($ligne['ID_Ecriture']) ?></td>
                            <td class="date"><?= htmlspecialchars($ecriture['Date_Saisie']) ?></td>
                            <td class="libelle"><?= htmlspecialchars($ecriture['Description']) ?></td>
                            <td class="contrepartie">
                                <?= implode('<br>', $affichageContreparties) ?>
                            </td>
                            <td class="debit">
                                <?php if ($ligne['Sens'] == 'D'): ?>
                                    <span class="debit-amount-main">
                                        <?php if ($isFacture && $factureDetails): ?>
                                            <?= number_format($factureDetails['Montant_TTC'], 2, ',', ' ') ?>
                                        <?php else: ?>
                                            <?= number_format($debit, 2, ',', ' ') ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="credit">
                                <?php if ($ligne['Sens'] == 'C'): ?>
                                    <span class="credit-amount-main">
                                        <?php if ($isFacture && $factureDetails): ?>
                                            <?= number_format($factureDetails['Montant_HT'], 2, ',', ' ') ?>
                                        <?php else: ?>
                                            <?= number_format($credit, 2, ',', ' ') ?>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="agence"><?= htmlspecialchars($ecriture['NumeroAgenceSCE']) ?></td>
                            <td class="utilisateur"><?= htmlspecialchars($ecriture['NomUtilisateur'] ?? 'N/A') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" class="text-right">PÉRIODE</th>
                        <th class="debit"><?= number_format($debitCumul, 2, ',', ' ') ?></th>
                        <th class="credit"><?= number_format($creditCumul, 2, ',', ' ') ?></th>
                        <th colspan="2"></th>
                    </tr>
                    <tr>
                        <th colspan="5" class="text-right">CUMUL</th>
                        <th class="debit"><?= number_format($debitCumul, 2, ',', ' ') ?></th>
                        <th class="credit"><?= number_format($creditCumul, 2, ',', ' ') ?></th>
                        <th colspan="2"></th>
                    </tr>
                    <tr>
                        <th colspan="5" class="text-right">SOLDE</th>
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
	 <script src="../js/jquery-3.7.1.js"></script>
	 <script src="../js/bootstrap.min.js"></script>
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
                // Get data attributes for searching, which should contain decoded characters
                const dataLibelle = $row.data('libelle').toLowerCase();
                const dataContrepartie = $row.data('contrepartie').toLowerCase();
                const dataDebit = $row.data('debit').toString().toLowerCase();
                const dataCredit = $row.data('credit').toString().toLowerCase();
                const dataAgence = $row.data('agence').toLowerCase();
                const dataUtilisateur = $row.data('utilisateur').toLowerCase();

                const matches = [
                    dataLibelle,
                    dataContrepartie,
                    dataDebit,
                    dataCredit,
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
                    removeHighlight($row); // Ensure highlights are removed from hidden rows
                }
            });

            $('#nombre-lignes-value').text(count);
            $('#no-results').toggle(!hasResults);
        }

        function highlightText($row, searchTerm) {
            const highlight = (text, term) => {
                // Escape special characters in the search term for regex
                const escapedTerm = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                return text.replace(new RegExp(`(${escapedTerm})`, 'gi'), '<span class="highlight">$1</span>');
            };

            // Columns to apply highlighting to
            const columnsToHighlight = ['.libelle', '.contrepartie', '.agence', '.utilisateur'];

            columnsToHighlight.forEach(selector => {
                $row.find(selector).each(function() {
                    const $el = $(this);
                    // Temporarily store original HTML to avoid re-highlighting existing spans
                    const originalHtml = $el.html();
                    // Remove existing highlights before applying new ones
                    const textWithoutHighlights = originalHtml.replace(/<span class="highlight">(.*?)<\/span>/gi, '$1');
                    // Apply new highlights
                    $el.html(highlight(textWithoutHighlights, searchTerm));
                });
            });
        }

        function removeHighlight($row) {
            // Remove highlight spans from all relevant cells
            $row.find('.libelle, .contrepartie, .agence, .utilisateur').each(function() {
                const $el = $(this);
                $el.html($el.html().replace(/<span class="highlight">(.*?)<\/span>/gi, '$1'));
            });
        }

        // Gestion des événements
        $('#search-input').on('input', function() {
            const searchTerm = $(this).val().trim();
            filterResults(searchTerm);
        });

        // Initial call to filter if there's a search term on page load
        // This handles cases where user navigates back and form input is pre-filled by browser
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

            $('#lignes-ecritures-list-body').html('<tr><td colspan="9" class="text-center">Chargement en cours...</td></tr>');
            $('#search-input').val(''); // Clear search input when applying date filter

            $.ajax({
                url: window.location.pathname,
                type: 'GET',
                data: {
                    compte_id: <?= $compteId ?>,
                    numero_compte: '<?= htmlspecialchars($numeroCompte, ENT_QUOTES, 'UTF-8') ?>', // Ensure this is also encoded for AJAX
                    date_debut: dateDebut,
                    date_fin: dateFin
                },
                success: function(data) {
                    const $response = $(data);
                    // Extract the relevant parts from the full HTML response
                    $('#lignes-ecritures-list-body').html($response.find('#lignes-ecritures-list-body').html());
                    $('.solde-info').html($response.find('.solde-info').html());
                    $('#nombre-lignes-value').text($response.find('#nombre-lignes-value').text());
                    $('#no-results').toggle($response.find('#no-results:visible').length > 0);

                    // Update URL without full page reload
                    const url = new URL(window.location.href);
                    dateDebut ? url.searchParams.set('date_debut', dateDebut) : url.searchParams.delete('date_debut');
                    dateFin ? url.searchParams.set('date_fin', dateFin) : url.searchParams.delete('date_fin');
                    window.history.pushState({}, '', url.toString());

                    // Re-apply search filter if there was one after new data loads
                    const currentSearchTerm = $('#search-input').val().trim();
                    if (currentSearchTerm.length > 0) {
                        filterResults(currentSearchTerm);
                    }
                },
                error: function() {
                    $('#lignes-ecritures-list-body').html('<tr><td colspan="9" class="text-center text-danger">Erreur lors du chargement des données</td></tr>');
                    $('.solde-info').html('<div class="alert alert-danger">Erreur de chargement des soldes.</div>');
                    $('#nombre-lignes-value').text('0');
                    $('#no-results').show();
                }
            });
        });

        $('#btn-reset-dates').on('click', function() {
            $('#date_debut').val('');
            $('#date_fin').val('');
            $('#btn-filtrer').click(); // Trigger filter with empty dates
        });

        $('#btn-print-pdf').on('click', function(e) {
            e.preventDefault();
            const printWindow = window.open('', '_blank');
            let tableContent = '';
            
            // Re-iterate visible rows to get current filtered/searched data
            $('#lignes-ecritures-list-body tr:visible').each(function() {
                const $row = $(this);
                // Extract original data attributes for clean printing, or text content if preferred
                const date = $row.data('date');
                const idEcriture = $row.data('id-ecriture');
                const libelle = $row.data('libelle');
                const contrepartie = $row.data('contrepartie'); // This already contains space-separated values
                const debit = $row.find('.debit-amount-main').text() || ''; // Get the text content from the span
                const credit = $row.find('.credit-amount-main').text() || ''; // Get the text content from the span
                const agence = $row.data('agence');
                const utilisateur = $row.data('utilisateur');

                tableContent += `
                    <tr>
                        <td>${date}</td>
                        <td>${idEcriture}</td>
                        <td>${libelle}</td>
                        <td>${contrepartie}</td>
                        <td class="debit-col">${debit}</td>
                        <td class="credit-col">${credit}</td>
                        <td>${agence}</td>
                        <td>${utilisateur}</td>
                    </tr>
                `;
            });

            // Recalculate sums based on currently visible rows for printing if needed,
            // or use the pre-calculated PHP values if they represent the *full* period without client-side filtering.
            // For print, it's safer to use the values from the last server-side load for "Cumul" and "Solde"
            // as the client-side filtering only hides rows, it doesn't re-calculate sums.
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
                        /* Add any additional print-specific styles here if needed */
                        body { font-family: sans-serif; margin: 20px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        .text-right { text-align: right; }
                        .debit-col, .credit-col { text-align: right; }
                        .positive { color: green; }
                        .negative { color: red; }
                        .print-header { text-align: center; margin-bottom: 20px; }
                        .print-header .company-name { font-size: 16pt; font-weight: bold; }
                        .print-header .account-info { font-size: 12pt; font-weight: bold; }
                        .print-header .period-info { font-size: 10pt; text-align: right; margin-top: 10px; }
                        .print-summary-table { width: 100%; margin-top: 20px; }
                        .print-summary-table td { border: none; padding: 3px; text-align: right; }
                        .print-summary-table .amount { font-weight: bold; }
                        .printed-by { text-align: right; font-style: italic; margin-top: 10px; font-size: 9pt; }
                    </style>
                </head>
                <body>
                    <div class="print-header">
                        <div class="company-name">BAILCOMPTA 360</div>
                        <div class="account-info">Extrait de Compte: <?= htmlspecialchars($compte['Nom_Compte'] ?? 'Nom inconnu') ?> (<?= htmlspecialchars($compte['Numero_Compte'] ?? 'N/A') ?>)</div>
                        <div class="period-info">Période du : ${dateDebutPrinted} au : ${dateFinPrinted}</div>
                    </div>
                    
                    <table class="print-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>ID Écriture</th>
                                <th>Libellé</th>
                                <th>Contrepartie</th>
                                <th class="debit-col">Débit</th>
                                <th class="credit-col">Crédit</th>
                                <th>Agence</th>
                                <th>Utilisateur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" class="text-right"><strong>Solde Antérieur</strong></td>
                                <td colspan="2" class="text-right ${parseFloat(soldeAnterieurPrinted.replace(',', '.')) >= 0 ? 'positive' : 'negative'}">${soldeAnterieurPrinted}</td>
                                <td colspan="2"></td>
                            </tr>
                            ${tableContent}
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-right">PÉRIODE</th>
                                <th class="debit-col">${debitCumulPrinted}</th>
                                <th class="credit-col">${creditCumulPrinted}</th>
                                <th colspan="2"></th>
                            </tr>
                            <tr>
                                <th colspan="4" class="text-right">SOLDE</th>
                                <th colspan="2" class="${soldeClass}">${currentSoldePrinted}</th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                    <div class="printed-by">
                        Imprimé par : ${printedBy} le ${printedDate}
                    </div>
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
            let csvContent = "data:text/csv;charset=utf-8,%EF%BB%BF"; // BOM for UTF-8
            csvContent += "Date;ID Ecriture;Libellé;Contrepartie;Débit;Crédit;Agence;Utilisateur\n"; // CSV Header

            $('#lignes-ecritures-list-body tr:visible').each(function() {
                const $row = $(this);
                // Get the displayed text content for CSV, not necessarily the raw data attribute values
                const date = $row.find('.date').text().trim();
                const idEcriture = $row.find('.id-ecriture').text().trim();
                const libelle = $row.find('.libelle').text().trim();
                const contrepartie = $row.find('.contrepartie').text().trim().replace(/<br>/g, ' | '); // Replace <br> with a separator
                const debit = $row.find('.debit').text().trim();
                const credit = $row.find('.credit').text().trim();
                const agence = $row.find('.agence').text().trim();
                const utilisateur = $row.find('.utilisateur').text().trim();

                // Sanitize values for CSV (handle commas, newlines by enclosing in quotes)
                const escapeCsv = (str) => {
                    if (str === null || str === undefined) return '';
                    str = String(str).replace(/"/g, '""'); // Escape double quotes
                    if (str.includes(';') || str.includes('\n') || str.includes('\r') || str.includes('"')) {
                        return `"${str}"`;
                    }
                    return str;
                };

                csvContent += `${escapeCsv(date)};${escapeCsv(idEcriture)};${escapeCsv(libelle)};${escapeCsv(contrepartie)};${escapeCsv(debit)};${escapeCsv(credit)};${escapeCsv(agence)};${escapeCsv(utilisateur)}\n`;
            });

            // Add summary rows at the end of the CSV
            csvContent += `\n`; // Blank line for separation
            csvContent += `Solde antérieur;;;;;<?= number_format(abs($soldeAnterieurValue), 2, ',', ' ') ?>;;;\n`;
            csvContent += `PÉRIODE;;;;<?= number_format($debitCumul, 2, ',', ' ') ?>;<?= number_format($creditCumul, 2, ',', ' ') ?>;;;\n`;
            csvContent += `SOLDE;;;;;<?= number_format(abs($currentSolde), 2, ',', ' ') ?>;;;\n`;
            csvContent += `\n`;
            csvContent += `Imprimé par : <?= htmlspecialchars($_SESSION['nom_utilisateur'] ?? 'Utilisateur inconnu') ?> le <?= date('d/m/Y à H:i') ?>;;;;;;;\n`;


            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'extrait_compte_<?= htmlspecialchars($compte['Numero_Compte'] ?? 'N_A') ?>.csv');
            document.body.appendChild(link); // Required for Firefox
            link.click();
            document.body.removeChild(link); // Clean up
        });

    });
    </script>
    <?php require_once('../../templates/footer.php'); ?>
</body>
</html>