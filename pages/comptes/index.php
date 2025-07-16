<?php
// pages/comptes/index.php
session_start();
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_comptes.php';

// --- Vérification de la connexion PDO ---
if (!isset($pdo) || !$pdo instanceof PDO) {
    $messageErreur = "Erreur de configuration de la base de donnees: La connexion PDO n'a pas et correctement initialisée.";
    error_log("Erreur (details.php - PDO non initialisé) : " . $messageErreur);
    require_once('../../templates/header.php');
    require_once('../../templates/navigation.php');
    echo '<div class="container mt-5"><div class="alert alert-danger">' . htmlspecialchars($messageErreur) . '</div></div>';
    require_once('../../templates/footer.php');
    exit;
}

$titre = 'Liste des Comptes';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Détails des emprunts</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
	<link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/tableau.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .search-container {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .table-responsive {
            margin-top: 20px;
        }
        .table-hover tbody tr {
            transition: all 0.3s ease;
        }
        .table-hover tbody tr:hover {
            background-color: #f5f5f5;
            cursor: pointer;
        }
        .credit {
            color: green;
        }
        .debit {
            color: red;
        }
        .solde {
            font-weight: bold;
        }
        .no-results {
            display: none;
            padding: 10px;
            text-align: center;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        .highlight {
            background-color: #fff3cd !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

        <div class="search-container">
            <div class="form-group">
                <label for="search-input">Rechercher un compte :</label>
                <input type="text" id="search-input" class="form-control" 
                       placeholder="Numéro ou nom de compte">
                <small class="form-text text-muted">La recherche filtre en temps réel</small>
            </div>
        </div>

        <div class="table-responsive">
            <table id="account-list" class="table table-striped table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Numéro</th>
                        <th>Nom</th>
                        <th>Type</th>
                        <th>Débit</th>
                        <th>Crédit</th>
                        <th>Solde</th>
                    </tr>
                </thead>
                <tbody id="account-list-body">
                    <?php
                    $comptes = getComptesWithBalance($pdo);
                    foreach ($comptes as $compte) {
                        $solde = $compte['total_credit'] - $compte['total_debit'];
                        $soldeClass = $solde >= 0 ? 'credit' : 'debit';
                        echo '<tr data-id="'.htmlspecialchars($compte['ID_Compte']).'" 
                                 data-numero="'.htmlspecialchars($compte['Numero_Compte']).'" 
                                 data-nom="'.htmlspecialchars($compte['Nom_Compte']).'" 
                                 data-type="'.htmlspecialchars($compte['Type_Compte']).'">';
                        echo '<td class="numero">'.htmlspecialchars($compte['Numero_Compte']).'</td>';
                        echo '<td class="nom">'.htmlspecialchars($compte['Nom_Compte']).'</td>';
                        echo '<td class="type">'.htmlspecialchars($compte['Type_Compte']).'</td>';
                        echo '<td class="debit">'.number_format($compte['total_debit'], 2, ',', ' ').' (TVA : '.number_format($compte['total_debit'] - ($compte['total_debit'] / (1 + 19.25 / 100)), 2, ',', ' ').')</td>';

                        echo '<td class="credit">'.number_format($compte['total_credit'], 2, ',', ' ').'</td>';
                        echo '<td class="solde '.$soldeClass.'">'.number_format(abs($solde), 2, ',', ' ').'</td>';
                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
            <div id="no-results" class="no-results">Aucun compte ne correspond à votre recherche</div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
	 <script src="../js/jquery-3.7.1.js"></script>
    <script>
    $(document).ready(function() {
        // Fonction pour filtrer les résultats
        function filterResults(searchTerm) {
            const $rows = $('#account-list-body tr');
            let hasResults = false;
            const searchLower = searchTerm.toLowerCase();
            
            $rows.each(function() {
                const $row = $(this);
                const numero = $row.find('.numero').text().toLowerCase();
                const nom = $row.find('.nom').text().toLowerCase();
                const type = $row.find('.type').text().toLowerCase();
                
                // Vérifie si le terme de recherche correspond à l'une des colonnes
                const matches = numero.includes(searchLower) || 
                                nom.includes(searchLower) || 
                                type.includes(searchLower);
                
                if (matches) {
                    $row.show().addClass('highlight');
                    hasResults = true;
                    
                    // Mettre en évidence le texte correspondant
                    if (searchTerm.length > 0) {
                        highlightText($row.find('.numero'), searchTerm);
                        highlightText($row.find('.nom'), searchTerm);
                        highlightText($row.find('.type'), searchTerm);
                    }
                } else {
                    $row.hide().removeClass('highlight');
                    removeHighlight($row);
                }
            });
            
            // Afficher le message si aucun résultat
            if (hasResults) {
                $('#no-results').hide();
            } else {
                $('#no-results').show();
            }
        }
        
        // Fonction pour mettre en évidence le texte
        function highlightText($element, searchTerm) {
            const text = $element.text();
            const regex = new RegExp(searchTerm, 'gi');
            const highlighted = text.replace(regex, match => `<span class="highlight">${match}</span>`);
            $element.html(highlighted);
        }
        
        // Fonction pour supprimer la mise en évidence
        function removeHighlight($row) {
            $row.find('.numero, .nom, .type').each(function() {
                const $el = $(this);
                $el.text($el.text());
            });
        }
        
        // Recherche en temps réel
        $('#search-input').on('input', function() {
            const searchTerm = $(this).val().trim();
            filterResults(searchTerm);
            
            // Réinitialiser le highlight après un court délai
            if (searchTerm.length === 0) {
                setTimeout(() => {
                    $('#account-list-body tr').removeClass('highlight');
                    removeHighlight($('#account-list-body tr'));
                }, 300);
            }
        });
        
        // Redirection au clic sur une ligne
        $('#account-list').on('click', 'tbody tr', function() {
            const compteId = $(this).data('id');
            const numeroCompte = $(this).data('numero');
            window.location.href = `../comptes/liste.php?compte_id=${compteId}&numero_compte=${encodeURIComponent(numeroCompte)}`;
        });
        
        // Focus sur le champ de recherche au chargement
        $('#search-input').focus();
    });
    </script>

    <?php require_once('../../templates/footer.php'); ?>
</body>
</html>