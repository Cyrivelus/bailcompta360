<?php
// pages/ecritures/brouillard_ecriture.php

$titre = 'Brouillard des ecritures comptables';
$current_page = basename($_SERVER['PHP_SELF']);

require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_brouillard.php';

// Messages
$successMessage = isset($_GET['success']) && $_GET['success'] === '1' ? "Operation reussie !" : null;
$deleteMessage = isset($_GET['delete_success']) && $_GET['delete_success'] === '1' ? "L'ecriture a ete supprimee avec succes." : null;
$bulkDeleteMessage = isset($_GET['bulk_delete_success']) && $_GET['bulk_delete_success'] === '1' ? "Les ecritures selectionnees ont ete supprimees avec succes." : null;
$errorMessage = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : null;

// Handle search and filtering
$search_params = [
    'journal' => $_GET['journal'] ?? '',
    'piece' => $_GET['piece'] ?? '',
    'date_debut' => $_GET['date_debut'] ?? '',
    'date_fin' => $_GET['date_fin'] ?? '',
    'compte' => $_GET['compte'] ?? '',
    'lettrage' => $_GET['lettrage'] ?? '',
    'keyword' => $_GET['keyword'] ?? ''
];

$brouillard_entries = getBrlEntries();

// Filter the entries based on search parameters
$filtered_entries = array_filter($brouillard_entries, function($entry) use ($search_params) {
    // Journal filter
    if ($search_params['journal'] !== '' && $entry['Jal'] !== $search_params['journal']) {
        return false;
    }
    // Piece filter
    if ($search_params['piece'] !== '' && $entry['Pce'] !== $search_params['piece']) {
        return false;
    }
    // Date range filter
    if ($search_params['date_debut'] !== '' && $entry['Dte'] < $search_params['date_debut']) {
        return false;
    }
    if ($search_params['date_fin'] !== '' && $entry['Dte'] > $search_params['date_fin']) {
        return false;
    }
    // Compte filter
    if ($search_params['compte'] !== '' && $entry['Cpt'] !== $search_params['compte']) {
        return false;
    }
    // Lettrage filter
    if ($search_params['lettrage'] !== '' && $entry['Lettrage'] !== $search_params['lettrage']) {
        return false;
    }
    // Keyword search (on Libelle, a field that you may have)
    if ($search_params['keyword'] !== '' && (
        (isset($entry['Libelle']) && stripos($entry['Libelle'], $search_params['keyword']) === false) &&
        (stripos($entry['Jal'], $search_params['keyword']) === false) &&
        (stripos($entry['Pce'], $search_params['keyword']) === false) &&
        (stripos($entry['Cpt'], $search_params['keyword']) === false)
    )) {
        return false;
    }
    
    return true;
});

// Grouping logic based on filtered data
$grouped_entries = [];
foreach ($filtered_entries as $entry) {
    $journal = $entry['Jal'];
    $date = $entry['Dte'];
    $compte = $entry['Cpt'];
    
    if (!isset($grouped_entries[$journal])) {
        $grouped_entries[$journal] = [];
    }

    if (!isset($grouped_entries[$journal][$date])) {
        $grouped_entries[$journal][$date] = [];
    }
    
    if (!isset($grouped_entries[$journal][$date][$compte])) {
        $grouped_entries[$journal][$date][$compte] = [
            'count' => 0,
            'entries' => []
        ];
    }
    
    $grouped_entries[$journal][$date][$compte]['entries'][] = $entry;
    $grouped_entries[$journal][$date][$compte]['count']++;
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Brouillard d'ecritures</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/formulaire.css">
    <link rel="stylesheet" href="../../css/tableau.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .journal-group-header {
            background-color: #337ab7;
            color: white;
            padding: 10px;
            margin-top: 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.2em;
        }
        .date-group-header {
            background-color: #5bc0de;
            color: white;
            padding: 8px;
            margin-top: 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .compte-group-header {
            background-color: #f0ad4e;
            color: white;
            padding: 6px;
            margin-top: 5px;
            border-radius: 3px;
            cursor: pointer;
        }
    </style>
</head>

<body>
<div class="container">
</BR>
</BR>
    <h2 class="page-header">Brouillard des &eacutecritures comptables</h2>

    <?php if ($successMessage): ?><div class="alert alert-success"><?= $successMessage ?></div><?php endif; ?>
    <?php if ($deleteMessage): ?><div class="alert alert-warning"><?= $deleteMessage ?></div><?php endif; ?>
    <?php if ($bulkDeleteMessage): ?><div class="alert alert-warning"><?= $bulkDeleteMessage ?></div><?php endif; ?>
    <?php if ($errorMessage): ?><div class="alert alert-danger"><?= $errorMessage ?></div><?php endif; ?>

    <div style="margin-bottom: 20px;">
        <a href="saisie.php" class="btn btn-primary">
            <span class="glyphicon glyphicon-plus"></span> Ajouter une nouvelle &eacutecriture
        </a>
        <a href="brouillard_ecriture.php" class="btn btn-primary" data-toggle="tooltip" data-placement="top" title="Affichez, filtrez et gerez les ecritures du brouillard par journal, date, type et compte.">
            <span class="glyphicon glyphicon-folder-open"></span> Consultation des brouillards d'&eacutecritures
        </a>
    </div>

    <div class="panel panel-info">
        <div class="panel-heading">
            <h3 class="panel-title">Filtres de recherche <a data-toggle="collapse" href="#search-form" class="pull-right">
                <span class="glyphicon glyphicon-chevron-down"></span></a>
            </h3>
        </div>
        <div id="search-form" class="panel-collapse collapse in">
            <div class="panel-body">
                <form action="brouillard_ecriture.php" method="GET" class="form-horizontal">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="journal" class="col-sm-4 control-label">Journal:</label>
                                <div class="col-sm-8">
                                    <input type="text" class="form-control" name="journal" id="journal" value="<?= htmlspecialchars($search_params['journal']) ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="compte" class="col-sm-4 control-label">Compte:</label>
                                <div class="col-sm-8">
                                    <input type="text" class="form-control" name="compte" id="compte" value="<?= htmlspecialchars($search_params['compte']) ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="date_debut" class="col-sm-4 control-label">Date D&eacutebut:</label>
                                <div class="col-sm-8">
                                    <input type="date" class="form-control" name="date_debut" id="date_debut" value="<?= htmlspecialchars($search_params['date_debut']) ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="date_fin" class="col-sm-4 control-label">Date Fin:</label>
                                <div class="col-sm-8">
                                    <input type="date" class="form-control" name="date_fin" id="date_fin" value="<?= htmlspecialchars($search_params['date_fin']) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="piece" class="col-sm-4 control-label">Pi&egravece:</label>
                                <div class="col-sm-8">
                                    <input type="text" class="form-control" name="piece" id="piece" value="<?= htmlspecialchars($search_params['piece']) ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="lettrage" class="col-sm-4 control-label">Lettrage:</label>
                                <div class="col-sm-8">
                                    <input type="text" class="form-control" name="lettrage" id="lettrage" value="<?= htmlspecialchars($search_params['lettrage']) ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="keyword" class="col-sm-4 control-label">Recherche g&eacuten&eacuterale:</label>
                                <div class="col-sm-8">
                                    <input type="text" class="form-control" name="keyword" id="keyword" placeholder="Rechercher par libelle..." value="<?= htmlspecialchars($search_params['keyword']) ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-sm-12 text-center">
                            <button type="submit" class="btn btn-primary">
                                <span class="glyphicon glyphicon-search"></span> Rechercher
                            </button>
                            <a href="brouillard_ecriture.php" class="btn btn-default">
                                <span class="glyphicon glyphicon-refresh"></span> R&eacuteinitialiser
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <form action="supprimer_ecritures_groupe.php" method="POST" id="bulkDeleteForm">
        <div class="table-responsive">
            <?php if (empty($grouped_entries)): ?>
                <div class="alert alert-info text-center">Aucune &eacutecriture trouv&eacutee pour cette recherche.</div>
            <?php else: ?>
                <?php foreach ($grouped_entries as $journal => $dates): ?>
                    <div class="journal-group-header" data-toggle="collapse" data-target="#collapse-<?= htmlspecialchars($journal) ?>">
                        Journal : <?= htmlspecialchars($journal) ?>
                    </div>
                    <div id="collapse-<?= htmlspecialchars($journal) ?>" class="collapse-in">
                        <?php foreach ($dates as $date => $comptes): ?>
                            <div class="date-group-header" data-toggle="collapse" data-target="#collapse-<?= htmlspecialchars($journal) ?>-<?= htmlspecialchars($date) ?>">
                                Date : <?= htmlspecialchars(date('d/m/Y', strtotime($date))) ?>
                            </div>
                            <div id="collapse-<?= htmlspecialchars($journal) ?>-<?= htmlspecialchars($date) ?>" class="collapse-in">
                                <?php foreach ($comptes as $compte => $group_data): ?>
                                    <div class="compte-group-header" data-toggle="collapse" data-target="#collapse-<?= htmlspecialchars($journal) ?>-<?= htmlspecialchars($date) ?>-<?= htmlspecialchars($compte) ?>">
                                        Compte : <?= htmlspecialchars($compte) ?> (<?= $group_data['count'] ?> &eacutecriture<?= $group_data['count'] > 1 ? 's' : '' ?>)
                                    </div>
                                    <div id="collapse-<?= htmlspecialchars($journal) ?>-<?= htmlspecialchars($date) ?>-<?= htmlspecialchars($compte) ?>" class="collapse-in">
                                        <table class="table table-striped table-bordered table-hover">
                                            <thead>
                                                <tr>
                                                    <th><input type="checkbox" id="selectAll-<?= htmlspecialchars($journal) ?>-<?= htmlspecialchars($date) ?>-<?= htmlspecialchars($compte) ?>" class="select-all-checkbox"></th>
                                                    <th>ID</th>
                                                    <th>Journal</th>
                                                    <th>Pi&egravece</th>
                                                    <th>Date</th>
                                                    <th>Compte</th>
                                                    <th>D&eacutebit</th>
                                                    <th>Cr&eacutedit</th>
                                                    <th>Lettrage</th>
                                                    
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($group_data['entries'] as $entry): ?>
                                                    <tr>
                                                        <td><input type="checkbox" name="selected_entries[]" value="<?= htmlspecialchars($entry['Id']) ?>" class="brl-checkbox"></td>
                                                        <td><?= htmlspecialchars($entry['Id']) ?></td>
                                                        <td><?= htmlspecialchars($entry['Jal']) ?></td>
                                                        <td><?= htmlspecialchars($entry['Pce']) ?></td>
                                                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($entry['Dte']))) ?></td>
                                                        <td><?= htmlspecialchars($entry['Cpt']) ?></td>
                                                        <td><?= htmlspecialchars(number_format($entry['Deb'], 2, ',', ' ')) ?></td>
                                                        <td><?= htmlspecialchars(number_format($entry['Cre'], 2, ',', ' ')) ?></td>
                                                        <td><?= htmlspecialchars($entry['Lettrage']) ?></td>
                                                       
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <button type="submit" id="deleteSelectedBtn" class="btn btn-danger" style="display: none;" onclick="return confirm('etes-vous sur de vouloir supprimer les ecritures selectionnees ? Cette action est irreversible.')">
            <span class="glyphicon glyphicon-trash"></span> Supprimer les &eacutecritures s&eacutelectionn&eacutees
        </button>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script>
$(document).ready(function() {
    // Initialisation des tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Logique de suppression en masse
    $('#brouillard-body').on('change', '.brl-checkbox', function() {
        toggleDeleteButtonVisibility();
    });

    $('.select-all-checkbox').on('change', function() {
        var group_id = $(this).attr('id').replace('selectAll-', '');
        $('#collapse-' + group_id + ' .brl-checkbox').prop('checked', $(this).prop('checked'));
        toggleDeleteButtonVisibility();
    });

    function toggleDeleteButtonVisibility() {
        if ($('.brl-checkbox:checked').length > 0) {
            $('#deleteSelectedBtn').show();
        } else {
            $('#deleteSelectedBtn').hide();
        }
    }

    toggleDeleteButtonVisibility(); // Initial check on page load
});
</script>

<?php require_once('../../templates/footer.php'); ?>