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
require_once '../../fonctions/gestion_factures.php'; // Inclure pour accéder aux statuts de facture

// Vérification de la connexion PDO
if (!isset($pdo) || !$pdo instanceof PDO) {
    $messageErreur = "Erreur de configuration de la base de données: La connexion PDO n'a pas été correctement initialisée.";
    error_log("Erreur (liste_ligne_ecriture.php - PDO non initialisé) : " . $messageErreur);
    echo '<div class="container mt-5"><div class="alert alert-danger">' . htmlspecialchars($messageErreur) . '</div></div>';
    require_once('../../templates/footer.php');
    exit;
}

$titre = 'Liste des Lignes d\'écritures';

// Récupérer les paramètres de l'URL (compte_id)
// NOTE: ECR_DEF doesn't have ID_Compte, we need to use 'Cpt' instead
$numeroCompte = filter_input(INPUT_GET, 'numero_compte', FILTER_UNSAFE_RAW);

if (!$numeroCompte) {
    $messageErreur = "Erreur: Numéro de compte non spécifié.";
    error_log("Erreur (liste_ligne_ecriture.php - Numéro de compte non spécifié)");
    echo '<div class="container mt-5"><div class="alert alert-danger">' . htmlspecialchars($messageErreur) . '</div></div>';
    require_once('../../templates/footer.php');
    exit;
}

// Déterminer le type du compte actuel
$isBankAccount = (str_starts_with($numeroCompte, '5') || str_starts_with($numeroCompte, '6'));
$isSupplierAccount = (str_starts_with($numeroCompte, '4') && !str_starts_with($numeroCompte, '445') && !str_starts_with($numeroCompte, '447'));

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
    $messageErreur = "La date de fin ne peut pas être antérieure à la date de début.";
    error_log("Erreur (liste_ligne_ecriture.php - Date incohérente) : " . $messageErreur);
}

// Adapt the query to the ECR_DEF table structure
// IMPORTANT: The `getLignesEcrituresByCompte` function needs to be adapted for ECR_DEF.
// For this example, I'll write the query directly here.
$query = "SELECT * FROM ECR_DEF WHERE Cpt = :numeroCompte";
$params = [':numeroCompte' => $numeroCompte];

if ($dateDebut) {
    $query .= " AND Dte >= :dateDebut";
    $params[':dateDebut'] = $dateDebut;
}
if ($dateFin) {
    $query .= " AND Dte <= :dateFin";
    $params[':dateFin'] = $dateFin;
}
$query .= " ORDER BY Dte, Id ASC"; // Order by date and then by the identity column for consistency

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$lignesEcritures = $stmt->fetchAll(PDO::FETCH_ASSOC);

$nombreLignes = count($lignesEcritures);

// Initialize cumulative debit and credit for the period being displayed
$debitCumul = 0;
$creditCumul = 0;

// Since we can't easily calculate an anterior balance without a more complex query,
// let's simplify for this example. The full balance calculation should be done
// on the server side with a dedicated function. For now, we will assume a zero starting balance.
// A proper `getSoldeAnterieur` for ECR_DEF would be needed.
$soldeAnterieurValue = 0;

// Initialize the final balance that will be displayed
$finalDisplayedBalance = $soldeAnterieurValue;

// Tableau associatif pour les agences
$agences = [
    '002' => 'BERTOUA',
    '003' => 'EBOLOWA',
    '004' => 'GAROUA',
    '005' => 'MAROUA',
    '006' => 'KRIBI',
    '007' => 'NGAOUNDERE',
    '008' => 'SANGMELIMA',
    '009' => 'YAOUNDE',
    '011' => 'BAMENDA',
    '012' => 'BUEA',
    '013' => 'DOUALA',
    '018' => 'BAFOUSSAM'
];
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
                <h3>Compte: <?= htmlspecialchars($numeroCompte) ?></h3>
                <p>Type: <?= $isBankAccount ? 'Banque' : ($isSupplierAccount ? 'Fournisseur' : 'Général') ?></p>
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
                        <th>Jal</th>
                        <th>Date</th>
                        <th>Libellé</th>
                        <th>Contrepartie</th>
                        <th>Débit</th>
                        <th>Crédit</th>
                        <th>Solde</th>
                        <th>Agence</th>
                        <th>Utilisateur</th>
                    </tr>
                </thead>
                <tbody id="lignes-ecritures-list-body">
                    <?php
                    // Start cumulative balance from the anterior balance
                    $currentSolde = $soldeAnterieurValue;

                    foreach ($lignesEcritures as $ligne):
                        // Update current balance for this line
                        $debit = $ligne['Deb'] ?? 0;
                        $credit = $ligne['Cre'] ?? 0;
                        $currentSolde += $credit - $debit;

                        // Also update cumulative sums for the period
                        $debitCumul += $debit;
                        $creditCumul += $credit;

                        // Déterminer la contrepartie. C'est l'un des défis ici car ECR_DEF n'a pas de concept de "contrepartie"
                        // explicite comme le ferait une table normalisée. Il faudrait une requête complexe pour la trouver.
                        // Pour cet exemple, nous utiliserons la colonne 'Ctr' si elle existe, ou laisserons vide.
                        $contrepartie = $ligne['Ctr'] ?? 'N/A';
                        $libelleLigne = $ligne['Lib'] ?? 'N/A';
                        $dateLigne = $ligne['Dte'] ? date('d-m-Y', strtotime($ligne['Dte'])) : 'N/A';

                        // Remplacer le code de l'agence par son nom
                        $agenceCode = $ligne['NumeroAgenceSCE'];
                        $agenceNom = isset($agences[$agenceCode]) ? $agences[$agenceCode] : $agenceCode;
                    ?>
                        <tr data-id="<?= htmlspecialchars($ligne['Id']) ?>"
                            data-date="<?= htmlspecialchars($dateLigne) ?>"
                            data-libelle="<?= htmlspecialchars($libelleLigne) ?>"
                            data-contrepartie="<?= htmlspecialchars($contrepartie) ?>"
                            data-debit="<?= htmlspecialchars($debit) ?>"
                            data-credit="<?= htmlspecialchars($credit) ?>"
                            data-agence="<?= htmlspecialchars($agenceNom) ?>"
                            data-utilisateur="<?= htmlspecialchars($ligne['NomUtilisateur'] ?? 'N/A') ?>">
                            <td class="id-ligne"><?= htmlspecialchars($ligne['Id']) ?></td>
                            <td class="id-ecriture"><?= htmlspecialchars($ligne['Jal'] ?? 'N/A') ?></td>
                            <td class="date"><?= htmlspecialchars($dateLigne) ?></td>
                            <td class="libelle"><?= htmlspecialchars($libelleLigne) ?></td>
                            <td class="contrepartie"><?= htmlspecialchars($contrepartie) ?></td>
                            <td class="debit">
                                <?php if ($debit > 0): ?>
                                    <span class="debit-amount-main">
                                        <?= number_format($debit, 2, ',', ' ') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="credit">
                                <?php if ($credit > 0): ?>
                                    <span class="credit-amount-main">
                                        <?= number_format($credit, 2, ',', ' ') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="solde <?= $currentSolde >= 0 ? 'credit' : 'debit' ?>">
                                <?= number_format(abs($currentSolde), 2, ',', ' ') ?>
                            </td>
                            <td class="agence"><?= htmlspecialchars($agenceNom) ?></td>
                            <td class="utilisateur"><?= htmlspecialchars($ligne['NomUtilisateur'] ?? 'N/A') ?></td>
                        </tr>
                    <?php
                    endforeach;
                    // Update final displayed balance with the cumulative result
                    $finalDisplayedBalance = $currentSolde;
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" class="text-right">PÉRIODE</th>
                        <th class="debit"><?= number_format($debitCumul, 2, ',', ' ') ?></th>
                        <th class="credit"><?= number_format($creditCumul, 2, ',', ' ') ?></th>
                        <th colspan="3"></th>
                    </tr>
                    <tr>
                        <th colspan="5" class="text-right">CUMUL</th>
                        <th class="debit"><?= number_format($debitCumul, 2, ',', ' ') ?></th>
                        <th class="credit"><?= number_format($creditCumul, 2, ',', ' ') ?></th>
                        <th colspan="3"></th>
                    </tr>
                    <tr>
                        <th colspan="5" class="text-right">SOLDE</th>
                        <th colspan="2" class="<?= ($finalDisplayedBalance) >= 0 ? 'credit' : 'debit' ?>">
                            <?= number_format(abs($finalDisplayedBalance), 2, ',', ' ') ?>
                        </th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
            </table>
            <div id="no-results" class="no-results" style="display: <?= $nombreLignes > 0 ? 'none' : 'block' ?>;">Aucune ligne d'écriture ne correspond à votre recherche</div>
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
                    <span class="<?= ($finalDisplayedBalance) >= 0 ? 'credit' : 'debit' ?>">
                        <?= number_format(abs($finalDisplayedBalance), 2, ',', ' ') ?>
                    </span>
                </div>
            </div>
            <div class="printed-by">
                Imprimé par : <?= htmlspecialchars($_SESSION['nom_utilisateur'] ?? 'Utilisateur inconnu') ?>
                le <?= date('d/m/Y à H:i') ?>
            </div>
        </div>
    </div>
<script src="../../js/jquery-3.7.1.min.js"></script>
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
        const rowText = $row.text().toLowerCase(); // 🔥 prend tout le texte de la ligne

        if (rowText.includes(searchLower)) {
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

    // applique à toutes les cellules de la ligne
    $row.find('td').each(function() {
        const $el = $(this);
        const originalHtml = $el.html();
        const textWithoutHighlights = originalHtml.replace(/<span class="highlight">(.*?)<\/span>/gi, '$1');
        $el.html(highlight(textWithoutHighlights, searchTerm));
    });
}


        function removeHighlight($row) {
            $row.find('.libelle, .contrepartie, .agence, .utilisateur').each(function() {
                const $el = $(this);
                $el.html($el.html().replace(/<span class="highlight">(.*?)<\/span>/gi, '$1'));
            });
        }

        // Search input event
        $('#search-input').on('keyup', function() {
            filterResults($(this).val());
        });

        // Date filter events
        $('#btn-filtrer').on('click', function() {
            const dateDebut = $('#date_debut').val();
            const dateFin = $('#date_fin').val();
            const url = new URL(window.location.href);
            url.searchParams.set('date_debut', dateDebut);
            url.searchParams.set('date_fin', dateFin);
            url.searchParams.set('numero_compte', '<?= htmlspecialchars($numeroCompte) ?>');
            window.location.href = url.toString();
        });

        $('#btn-reset-dates').on('click', function() {
            const url = new URL(window.location.href);
            url.searchParams.delete('date_debut');
            url.searchParams.delete('date_fin');
            url.searchParams.delete('numero_compte');
            window.location.href = url.toString();
        });

        // Print to PDF/CSV (assuming backend handles generation)
      $('#btn-print-pdf').on('click', function(e) {
    e.preventDefault();
    const url = new URL('/../bailcompta360/pages/exports/export_reports.php', window.location.origin);
    url.searchParams.set('report_type', 'extrait_compte');
    url.searchParams.set('format', 'pdf');
    url.searchParams.set('id_compte', '<?= htmlspecialchars($numeroCompte) ?>');
    if ($('#date_debut').val()) url.searchParams.set('start_date', $('#date_debut').val());
    if ($('#date_fin').val()) url.searchParams.set('end_date', $('#date_fin').val());
    window.open(url.toString(), '_blank');
});

$('#btn-print-csv').on('click', function(e) {
    e.preventDefault();
    const url = new URL('../bailcompta360/pages/exports/export_reports.php', window.location.origin);
    url.searchParams.set('report_type', 'extrait_compte');
    url.searchParams.set('format', 'csv');
    url.searchParams.set('id_compte', '<?= htmlspecialchars($numeroCompte) ?>');
    if ($('#date_debut').val()) url.searchParams.set('start_date', $('#date_debut').val());
    if ($('#date_fin').val()) url.searchParams.set('end_date', $('#date_fin').val());
    window.open(url.toString(), '_blank');
});
	});
    </script>
</body>
</html>
<?php
require_once('../../templates/footer.php');
?>