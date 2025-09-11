<?php
// pages/generation/GenEcriture.php
// Script pour valider et exporter les écritures comptables, adapté pour MySQL.

ob_start();
session_start();

// Configuration de l'environnement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

$titre = 'Exporter et Lister les Écritures Comptables';
$current_page = basename(__FILE__);

$messageSucces = null;
$messageInfo = null;
$messageErreur = null;

// Assurez-vous que le fichier database.php est configuré pour se connecter à MySQL.
// Par exemple, en utilisant un DSN comme 'mysql:host=localhost;dbname=ma_base'
require_once('../../fonctions/database.php');

try {
    // --- Déclaration des fonctions utilitaires ---
    function getEcrituresData($pdo) {
        // Remplacement de TOP 1000 par LIMIT 1000 pour MySQL
        $sql = "SELECT 
                    Pce, DateDeSaisie, Lib, Deb, Cre, Jal, Cpt, ctr,
                    NumeroAgenceSCE, NomUtilisateur, EstValide,
                    is_exported, exported_at
                FROM ECR_DEF
                ORDER BY DateDeSaisie DESC
                LIMIT 1000";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function countEcritures($pdo) {
        $stmt_validate = $pdo->query("SELECT COUNT(Pce) FROM ECR_DEF WHERE EstValide = 0 OR EstValide IS NULL");
        $countToValidate = $stmt_validate->fetchColumn();

        $stmt_export = $pdo->query("
            SELECT COUNT(Pce)
            FROM ECR_DEF
            WHERE EstValide = 1 AND (is_exported = 0 OR is_exported IS NULL)
        ");
        $countToExport = $stmt_export->fetchColumn();

        return ['to_validate' => $countToValidate, 'to_export' => $countToExport];
    }

    function formatEcritureLine($row) {
        $agence_val = $row['NumeroAgenceSCE'] ?? '';
        $numeroCompte_val = $row['Cpt'] ?? '';
        $libelle_val = $row['Lib'] ?? '';
        $debit_val = (float)($row['Deb'] ?? 0);
        $credit_val = (float)($row['Cre'] ?? 0);
        $date_val = $row['Dte'] ?? date('Y-m-d H:i:s');
        
        $agence_val_transformed = str_ireplace(['Yaoundé', 'Yao', '00'], '009', $agence_val);
        $agence_val_transformed = str_replace('0099', '009', $agence_val_transformed);
        $agence = mb_substr(str_pad($agence_val_transformed, 10, ' ', STR_PAD_RIGHT), 0, 10);
        $agence2 = mb_substr(str_pad($agence_val_transformed, 3, ' ', STR_PAD_RIGHT), 0, 3);
        $numeroCompte = mb_substr(str_pad($numeroCompte_val, 12, ' ', STR_PAD_RIGHT), 0, 12);
        
        $sens = '';
        $montant_val = 0;
        
        if ($debit_val > 0) {
            $sens = 'D';
            $montant_val = $debit_val;
        } elseif ($credit_val > 0) {
            $sens = 'C';
            $montant_val = $credit_val;
        }
        
        $montant_formatted = number_format($montant_val, 2, '', '');
        $montant_padded = str_pad($montant_formatted, 10, ' ', STR_PAD_LEFT);
        
        $debit_column = str_pad('', 10, ' ', STR_PAD_RIGHT);
        $credit_column = str_pad('', 10, ' ', STR_PAD_RIGHT);

        if ($sens === 'D') {
            $debit_column = $montant_padded;
        } elseif ($sens === 'C') {
            $credit_column = $montant_padded;
        }
        
        $libelle_upper = mb_strtoupper(trim((string)$libelle_val));
        $libelle_padded = str_pad(mb_substr($libelle_upper, 0, 30, 'UTF-8'), 30, ' ', STR_PAD_RIGHT);
        
        try {
            $dateTimeObj = new DateTime($date_val);
            $datePiece = $dateTimeObj->format('Ymd');
        } catch (Exception $e) {
            error_log("Date invalide pour l'écriture : " . $date_val . " - " . $e->getMessage());
            $datePiece = date('Ymd');
        }
        $dateFormatted = str_pad($datePiece, 8, ' ', STR_PAD_RIGHT);
        
        return implode(';', [
            $agence, $agence2, $numeroCompte, $debit_column, $credit_column, $sens,
            $montant_padded, $libelle_padded, $dateFormatted, ''
        ]) . ';';
    }

    if (isset($_GET['success'])) {
        $messageSucces = "<div class='alert alert-success'>" . htmlspecialchars(urldecode($_GET['success'])) . "</div>";
    }
    if (isset($_GET['info'])) {
        $messageInfo = "<div class='alert alert-info'>" . htmlspecialchars(urldecode($_GET['info'])) . "</div>";
    }
    if (isset($_GET['error'])) {
        $messageErreur = "<div class='alert alert-danger'>" . urldecode($_GET['error']) . "</div>";
    }

    // --- Actions via GET/AJAX ---
    if (isset($_GET['action'])) {
        ob_end_clean();
        switch ($_GET['action']) {
            case 'validate_all':
                try {
                    $sql = "UPDATE ECR_DEF SET EstValide = 1 WHERE EstValide = 0 OR EstValide IS NULL";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute();
                    $validatedCount = $stmt->rowCount();
                    $message = ($validatedCount > 0)
                        ? "Validation de $validatedCount écritures en attente réussie."
                        : "Aucune nouvelle écriture à valider.";
                    header('Location: ' . $current_page . '?success=' . urlencode($message));
                    exit();
                } catch (PDOException $e) {
                    error_log('Erreur MySQL lors de la validation : ' . $e->getMessage());
                    header('Location: ' . $current_page . '?error=' . urlencode('Erreur lors de la validation : ' . $e->getMessage()));
                    exit();
                }

            case 'export_all':
                $maxSizeKB = isset($_GET['size']) ? intval($_GET['size']) : 1000;
                $maxSizeInBytes = $maxSizeKB * 1024;
                
                $sql_export = "SELECT
                    Pce, DateDeSaisie AS Dte, Lib, Deb, Cre, NumeroAgenceSCE, Cpt, ctr
                    FROM ECR_DEF
                    WHERE EstValide = 1
                    AND (Deb > 0 OR Cre > 0)
                    AND Cpt IS NOT NULL
                    AND (is_exported = 0 OR is_exported IS NULL)
                    ORDER BY Pce, DateDeSaisie";
                
                try {
                    $stmt_export = $pdo->prepare($sql_export);
                    $stmt_export->execute();

                    $groupedEcritures = [];
                    $piece_numbers_to_update = [];
                    $current_size = 0;
                    $output = '';

                    while ($row = $stmt_export->fetch(PDO::FETCH_ASSOC)) {
                        $piece_number = $row['Pce'];

                        if (!isset($groupedEcritures[$piece_number])) {
                            $groupedEcritures[$piece_number] = [
                                'lines' => [],
                                'totalDeb' => 0,
                                'totalCre' => 0,
                                'firstRow' => $row
                            ];
                        }

                        $debit_val = (float)($row['Deb'] ?? 0);
                        $credit_val = (float)($row['Cre'] ?? 0);
                        $groupedEcritures[$piece_number]['lines'][] = $row;
                        $groupedEcritures[$piece_number]['totalDeb'] += $debit_val;
                        $groupedEcritures[$piece_number]['totalCre'] += $credit_val;
                    }

                    if (empty($groupedEcritures)) {
                        header('Location: ' . $current_page . '?info=' . urlencode('Aucune écriture éligible à l\'exportation.'));
                        exit();
                    }

                    foreach ($groupedEcritures as $piece_number => $data) {
                        $totalDebit = $data['totalDeb'];
                        $totalCredit = $data['totalCre'];
                        $piece_data = $data['firstRow'];
                        $compte_contrepartie = $piece_data['ctr'] ?? '';

                        if (abs($totalDebit - $totalCredit) > 0.001) {
                            if (empty($compte_contrepartie)) {
                                throw new Exception("Le compte de contrepartie (ctr) est manquant pour la pièce n° $piece_number.");
                            }

                            $difference = round($totalDebit - $totalCredit, 2);
                            $balance_row = [
                                'Pce' => $piece_number,
                                'Cpt' => $compte_contrepartie,
                                'Lib' => 'EQUILIBRAGE PC ' . $piece_number,
                                'Dte' => $piece_data['Dte'],
                                'NumeroAgenceSCE' => $piece_data['NumeroAgenceSCE'],
                                'Deb' => ($difference < 0) ? abs($difference) : 0,
                                'Cre' => ($difference > 0) ? $difference : 0
                            ];
                            $data['lines'][] = $balance_row;
                        }
                        
                        $piece_output = '';
                        foreach ($data['lines'] as $line_data) {
                            $piece_output .= formatEcritureLine($line_data) . "\n";
                        }
                        
                        if ($current_size + strlen($piece_output) > $maxSizeInBytes) {
                            if ($current_size > 0) {
                                break;
                            } else {
                                header('Location: ' . $current_page . '?error=' . urlencode('La première pièce d\'écriture excède la taille maximale spécifiée.'));
                                exit();
                            }
                        }

                        $output .= $piece_output;
                        $current_size += strlen($piece_output);
                        $piece_numbers_to_update[] = $piece_number;
                    }
                    
                    if (empty($output)) {
                        header('Location: ' . $current_page . '?info=' . urlencode('Aucune écriture à exporter après vérification et équilibrage.'));
                        exit();
                    }

                    $filename = 'ecritures_exportees_' . date('Ymd_His') . '.txt';

                    header('Content-Type: text/plain; charset=UTF-8');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    header('Pragma: no-cache');
                    header('Expires: 0');
                    echo "\xEF\xBB\xBF";
                    echo $output;
                    
                    // --- MISE À JOUR DE LA TABLE ECR_DEF ---
                    if (!empty($piece_numbers_to_update)) {
                        $unique_pieces = array_unique($piece_numbers_to_update);
                        $placeholders = implode(',', array_fill(0, count($unique_pieces), '?'));
                        
                        $pdo->beginTransaction();
                        
                        // Remplacement de GETDATE() par NOW() pour MySQL
                        $sql_update_ecr_def = "
                            UPDATE ECR_DEF
                            SET is_exported = 1, exported_at = NOW()
                            WHERE Pce IN ($placeholders)
                        ";
                        $stmt_update = $pdo->prepare($sql_update_ecr_def);
                        $stmt_update->execute($unique_pieces);

                        $pdo->commit();
                    }
                    exit();
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Erreur MySQL lors de l\'exportation : ' . $e->getMessage());
                    header('Location: ' . $current_page . '?error=' . urlencode('Erreur lors de l\'exportation : ' . $e->getMessage()));
                    exit();
                }
            
            case 'refresh_data':
                $allEcritures = getEcrituresData($pdo);
                $counts = countEcritures($pdo);

                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success',
                    'data' => $allEcritures,
                    'counts' => $counts
                ]);
                exit();
        }
    }

    $allEcritures = getEcrituresData($pdo);
    $counts = countEcritures($pdo);
    $countToValidate = $counts['to_validate'];
    $countToExport = $counts['to_export'];
    
    if (file_exists('../../templates/header.php')) {
        require_once('../../templates/header.php');
    }
    if (file_exists('../../templates/navigation.php')) {
        require_once('../../templates/navigation.php');
    }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Exporter les Écritures</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <style>
        .eligible-for-export { background-color: #e8f5e9 !important; font-weight: bold; }
        .status-badge { display: inline-block; padding: 3px 8px; font-size: 12px; font-weight: bold; color: white; border-radius: 4px; text-align: center; cursor: help; }
        .status-yes { background-color: #4caf50; }
        .status-no-invalid { background-color: #f44336; }
        .status-no-eligible { background-color: #ff9800; }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h2><?= $titre ?></h2>
    </div>
    <div id="messages-container"><?= $messageSucces . $messageInfo . $messageErreur ?></div>

    <div class="well">
        <p>1. Valider les écritures en attente pour les rendre éligibles à l'exportation.</p>
        <a href="?action=validate_all" class="btn btn-warning <?= $countToValidate > 0 ? '' : 'disabled' ?>">
            <span class="glyphicon glyphicon-ok"></span> Valider les Écritures (<span id="count-to-validate"><?= $countToValidate ?></span>)
        </a>
        <hr>
        <p>2. Exporter toutes les écritures validées et non encore exportées.</p>
        <div class="form-group">
            <label for="exportSize">Taille max. du fichier (Ko):</label>
            <input type="number" id="exportSize" class="form-control" value="1000" min="10" style="width: 150px; display: inline-block;">
        </div>
        <button id="export-btn" class="btn btn-primary <?= $countToExport > 0 ? '' : 'disabled' ?>">
            <span class="glyphicon glyphicon-download-alt"></span> Exporter les Écritures (<span id="count-to-export"><?= $countToExport ?></span>)
        </button>
    </div>

    <h3>Liste des 1000 Écritures Récentes</h3>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>N° Pièce</th><th>Date Saisie</th><th>Description</th><th>Débit</th><th>Crédit</th><th>Compte Contrepartie</th><th>Agence SCE</th><th>Validé</th><th>Exporté</th><th>Date Export</th>
                </tr>
            </thead>
            <tbody id="ecritures-table-body">
                <?php if (empty($allEcritures)): ?>
                    <tr><td colspan="10" class="text-center">Aucune écriture trouvée</td></tr>
                <?php else: ?>
                    <?php foreach ($allEcritures as $e):
                        $isExported = ($e['is_exported'] == 1);
                        $isEligible = ($e['EstValide'] == 1 && $e['is_exported'] == 0);
                        $rowClass = $isEligible ? 'eligible-for-export' : '';
                        
                        $exportStatusHtml = '';
                        if ($isExported) {
                            $exportStatusHtml = '<span class="status-badge status-yes" data-toggle="tooltip" title="Cette écriture a déjà été exportée.">Oui</span>';
                        } else {
                            if ($e['EstValide'] == 1) {
                                $exportStatusHtml = '<span class="status-badge status-no-eligible" data-toggle="tooltip" title="Éligible à l\'export.">Non</span>';
                            } else {
                                $exportStatusHtml = '<span class="status-badge status-no-invalid" data-toggle="tooltip" title="Non éligible. Doit d\'abord être validée.">Non</span>';
                            }
                        }
                        $exportDateDisplay = $e['exported_at'] ? (new DateTime($e['exported_at']))->format('Y-m-d H:i') : 'N/A';
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td><?= htmlspecialchars($e['Pce'] ?? '') ?></td>
                        <td><?= !empty($e['DateDeSaisie']) ? (new DateTime($e['DateDeSaisie']))->format('Y-m-d') : 'N/A' ?></td>
                        <td><?= htmlspecialchars($e['Lib'] ?? '') ?></td>
                        <td><?= number_format($e['Deb'] ?? 0, 2, ',', ' ') ?></td>
                        <td><?= number_format($e['Cre'] ?? 0, 2, ',', ' ') ?></td>
                        <td><?= htmlspecialchars($e['ctr'] ?? '') ?></td>
                        <td><?= htmlspecialchars($e['NumeroAgenceSCE'] ?? '') ?></td>
                        <td><?= ($e['EstValide'] == 1) ? 'Oui' : 'Non' ?></td>
                        <td><?= $exportStatusHtml ?></td>
                        <td><?= htmlspecialchars($exportDateDisplay) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script>
$(document).ready(function() {
    $('[data-toggle="tooltip"]').tooltip();

    $('#export-btn').on('click', function(e) {
        if ($(this).hasClass('disabled')) {
            e.preventDefault();
            return;
        }
        var exportBtn = $(this);
        var size = $('#exportSize').val();
        if (size > 0) {
            exportBtn.prop('disabled', true).text('Exportation en cours...');
            window.location.href = '?action=export_all&size=' + size;
        } else {
            // Using a simple modal as per instructions instead of alert()
            // Create a temporary modal-like div for the message
            var alertDiv = $('<div>').addClass('alert alert-danger').text('Veuillez entrer une taille de fichier valide (supérieure à 0).');
            $('#messages-container').append(alertDiv);
            setTimeout(function() {
                alertDiv.fadeOut('slow', function() { $(this).remove(); });
            }, 3000);
        }
    });

    function refreshTable() {
        $.getJSON('?action=refresh_data', function(resp) {
            if (resp.status === 'success') {
                var body = $('#ecritures-table-body').empty();
                if (resp.data.length > 0) {
                    $.each(resp.data, function(index, e) {
                        var isExported = (e.is_exported == 1);
                        var isEligible = (e.EstValide == 1 && e.is_exported == 0);
                        var rowClass = isEligible ? 'eligible-for-export' : '';
                        
                        var exportStatusHtml = '';
                        if (isExported) {
                            exportStatusHtml = '<span class="status-badge status-yes" data-toggle="tooltip" title="Cette écriture a déjà été exportée.">Oui</span>';
                        } else {
                            if (e.EstValide == 1) {
                                exportStatusHtml = '<span class="status-badge status-no-eligible" data-toggle="tooltip" title="Éligible à l\'export.">Non</span>';
                            } else {
                                exportStatusHtml = '<span class="status-badge status-no-invalid" data-toggle="tooltip" title="Non éligible. Doit d\'abord être validée.">Non</span>';
                            }
                        }
                        
                        // Using Date object for formatting, works with MySQL's Y-m-d H:i:s format
                        var exportDateDisplay = e.exported_at ? new Date(e.exported_at).toLocaleString() : 'N/A';
                        
                        var row = '<tr class="' + rowClass + '">' +
                            '<td>' + (e.Pce || '') + '</td>' +
                            '<td>' + (e.DateDeSaisie ? new Date(e.DateDeSaisie).toISOString().split('T')[0] : 'N/A') + '</td>' +
                            '<td>' + (e.Lib || '') + '</td>' +
                            '<td>' + parseFloat(e.Deb).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, " ") + '</td>' +
                            '<td>' + parseFloat(e.Cre).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, " ") + '</td>' +
                            '<td>' + (e.ctr || '') + '</td>' +
                            '<td>' + (e.NumeroAgenceSCE || '') + '</td>' +
                            '<td>' + (e.EstValide == 1 ? 'Oui' : 'Non') + '</td>' +
                            '<td>' + exportStatusHtml + '</td>' +
                            '<td>' + exportDateDisplay + '</td>' +
                            '</tr>';
                        body.append(row);
                    });
                    $('[data-toggle="tooltip"]').tooltip();
                } else {
                    body.append('<tr><td colspan="10" class="text-center">Aucune écriture trouvée</td></tr>');
                }
                
                $('#count-to-validate').text(resp.counts.to_validate);
                $('#count-to-export').text(resp.counts.to_export);
                
                var exportBtn = $('#export-btn');
                var validateBtn = $('a.btn-warning');

                if (resp.counts.to_export > 0) {
                    exportBtn.removeClass('disabled');
                } else {
                    exportBtn.addClass('disabled');
                }

                if (resp.counts.to_validate > 0) {
                    validateBtn.removeClass('disabled');
                } else {
                    validateBtn.addClass('disabled');
                }
            }
        });
    }

    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success') || urlParams.has('info') || urlParams.has('error')) {
        refreshTable();
    }
});
</script>
</body>
</html>
<?php
    if (file_exists('../../templates/footer.php')) require_once('../../templates/footer.php');
} catch (Exception $e) {
    ob_end_clean();
    echo "Une erreur fatale est survenue : " . htmlspecialchars($e->getMessage());
}
ob_end_flush();
?>
