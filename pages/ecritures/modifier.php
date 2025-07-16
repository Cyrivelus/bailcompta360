<?php
// pages/ecritures/liste.php

$titre = 'Consultation de l\'ecriture comptable';
$current_page = basename($_SERVER['PHP_SELF']);

require_once('../../templates/header.php');
require_once('../../templates/navigation.php');

// Inclure les fichiers n�cessaires
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_ecritures.php';
require_once '../../fonctions/gestion_comptes.php';

// Connexion � la base de donn�es
if (!isset($pdo) || !$pdo instanceof PDO) {
    // MySQL connection parameters for localhost
    $host = 'localhost'; // Usually 'localhost' for local development
    $dbName = 'BD_AD_SCE'; // Replace with your actual MySQL database name
    $username = 'root'; // Your MySQL username (e.g., 'root' for XAMPP/WAMP)
    $password = ''; // Your MySQL password (often empty for XAMPP/WAMP 'root' user)
    $charset = 'utf8mb4'; // Recommended charset for broad character support

    $dsn = "mysql:host=$host;dbname=$dbName;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Essential: Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch results as associative arrays by default
        PDO::ATTR_EMULATE_PREPARES   => false,                  // For better security and performance with prepared statements
    ];

    try {
        $pdo = new PDO($dsn, $username, $password, $options);
    } catch (PDOException $e) {
        $erreur = "Erreur de connexion à la base de données : " . $e->getMessage();
        error_log("Erreur de connexion (details.php) : " . $e->getMessage());

        // Display error message and exit cleanly
        require_once('../../templates/header.php');
        require_once('../../templates/navigation.php');
        echo "<div class='container'><div class='alert alert-danger'>$erreur</div></div>";
        require_once('../../templates/footer.php');
        exit();
    }
}

// R�cup�rer la liste des comptes group�s par type
$comptes = getListeComptes($pdo);
$comptesGroupes = [];
foreach ($comptes as $compte) {
    $prefixe = substr($compte['Numero_Compte'], 0, 2);
    $comptesGroupes[$prefixe][] = $compte;
}

// Initialisation des variables
$idEcriture = isset($_GET['id']) && is_numeric($_GET['id']) ? $_GET['id'] : null;
$dateSaisie = '';
$description = '';
$lignesEcriture = [];
$erreur = null;
$success = isset($_GET['success']) ? $_GET['success'] : null;

// Define the TVA rate
// You might want to get this from a configuration or database setting in a real application
const TVA_RATE = 0.1925; // 19.25%

// Initialize TVA related variables
$montantHorsTVA = 0;
$montantTVA = 0;

// R�cup�rer l'�criture si ID fourni
if ($idEcriture) {
    $ecriture = getEcriture($pdo, $idEcriture);
    $lignesEcriture = getLignesEcriture($pdo, $idEcriture);

    if ($ecriture) {
        $dateSaisie = isset($ecriture['Date_Saisie']) ? date('d/m/Y', strtotime($ecriture['Date_Saisie'])) : '';
        $description = isset($ecriture['Description']) ? $ecriture['Description'] : '';
        $numeroPiece = isset($ecriture['Numero_Piece']) ? $ecriture['Numero_Piece'] : '';
        $journal = isset($ecriture['Cde']) ? $ecriture['Cde'] : '';

        // Calculate TVA based on debit lines
        foreach ($lignesEcriture as $ligne) {
            if ($ligne['Sens'] == 'D') {
                $numeroCompte = '';
                foreach ($comptes as $compte) {
                    if ($compte['ID_Compte'] == $ligne['ID_Compte']) {
                        $numeroCompte = $compte['Numero_Compte'];
                        break;
                    }
                }

                // Assuming TVA accounts start with '445'
                // You might need to adjust this logic based on your chart of accounts
                if (strpos($numeroCompte, '445') === 0) { // Check if account number starts with '445'
                    $montantTVA += $ligne['Montant'];
                } else {
                    $montantHorsTVA += $ligne['Montant'];
                }
            }
        }
        // If TVA is calculated from a specific TVA account, then the Hors TVA is the total debit minus TVA
        // This logic might need adjustment if your TVA is always calculated from a specific expense account
        if ($montantTVA > 0) {
            // If TVA is explicitly booked, the rest of the debit is usually Hors TVA
            $montantHorsTVA = $totalDebit - $montantTVA;
        } else if ($totalDebit > 0) {
             // If no explicit TVA account is found, but you know some debit lines are subject to TVA
             // You might infer TVA from other accounts (e.g., if total debit includes a T.T.C. amount)
             // This is a more complex scenario and might require specific account number checks
             // For simplicity, we'll assume TVA is explicitly booked in a 445 account or calculated from a Gross amount.
             // If TVA isn't in a separate account, and you want to extract it from a total amount (e.g., a purchase),
             // you'd need to identify the gross amount and calculate TVA = Gross / (1 + Rate) * Rate
             // For now, we calculate it if it's found in a '445' account.
        }


    } else {
        $erreur = "Ecriture non trouv�e.";
    }
} else {
    $erreur = "ID d'ecriture non valide.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?= htmlspecialchars($titre) ?></title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">

    <link rel="stylesheet" href="../../css/style.css">
	 <link rel="stylesheet" href="../../css//bootstrap.min.css">
    <style>
        .detail-panel {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .ligne-ecriture {
            margin-bottom: 10px;
            padding: 10px;
            background: #fff;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .table-details {
            width: 100%;
            margin-bottom: 20px;
        }
        .table-details th {
            background-color: #f2f2f2;
            text-align: left;
            padding: 8px;
        }
        .table-details td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        .montant-debit {
            color: #d9534f;
            font-weight: bold;
        }
        .montant-credit {
            color: #5cb85c;
            font-weight: bold;
        }
        .total-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .tva-info {
            margin-top: 15px;
            padding: 10px;
            background-color: #eaf7f7;
            border-left: 5px solid #00a0b0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

        <?php if ($success) : ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($erreur) : ?>
            <div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <?php if ($idEcriture && !$erreur) : ?>
            <div class="detail-panel">
                <h3>Informations g&eacuten&eacuterales</h3>
                <table class="table-details">
                    <tr>
                        <th>Num&eacutero piece</th>
                        <td><?= htmlspecialchars($numeroPiece) ?></td>
                    </tr>
                    <tr>
                        <th>Journal</th>
                        <td><?= htmlspecialchars($journal) ?></td>
                    </tr>
                    <tr>
                        <th>Date de saisie</th>
                        <td><?= htmlspecialchars($dateSaisie) ?></td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td><?= htmlspecialchars($description) ?></td>
                    </tr>
                </table>

                <h3>Lignes d'&eacutecriture</h3>
                <table class="table-details">
                    <thead>
                        <tr>
                            <th>Compte</th>
                            <th>Libell&eacute</th>
                            <th>D&eacutebit</th>
                            <th>Cr&eacutedit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalDebit = 0;
                        $totalCredit = 0;
                        
                        foreach ($lignesEcriture as $ligne) : 
                            $montant = $ligne['Montant'];
                            if ($ligne['Sens'] == 'D') {
                                $totalDebit += $montant;
                            } else {
                                $totalCredit += $montant;
                            }
                        ?>
                            <tr class="ligne-ecriture">
                                <td>
                                    <?php 
                                    $numeroCompte = '';
                                    foreach ($comptes as $compte) {
                                        if ($compte['ID_Compte'] == $ligne['ID_Compte']) {
                                            $numeroCompte = $compte['Numero_Compte'];
											$NomCompte = $compte['Nom_Compte'];
                                            break;
                                        }
                                    }
                                    echo htmlspecialchars($numeroCompte);
									echo '&nbsp';
									   echo htmlspecialchars($NomCompte);
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($ligne['Libelle_Ligne'] ?? '') ?></td>
                                <td class="montant-debit">
                                    <?= $ligne['Sens'] == 'D' ? number_format($montant, 2, ',', ' ') : '' ?>
                                </td>
                                <td class="montant-credit">
                                    <?= $ligne['Sens'] == 'C' ? number_format($montant, 2, ',', ' ') : '' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="2"><strong>Total</strong></td>
                            <td class="montant-debit"><?= number_format($totalDebit, 2, ',', ' ') ?></td>
                            <td class="montant-credit"><?= number_format($totalCredit, 2, ',', ' ') ?></td>
                        </tr>
                    </tbody>
                </table>

                <?php if ($montantTVA > 0) : ?>
                    <div class="tva-info">
                        <h4>D&eacutetails TVA</h4>
                        <p>Montant Hors TVA (d&eacutebit&eacute) : <strong><?= number_format($montantHorsTVA, 2, ',', ' ') ?></strong></p>
                        <p>Montant TVA (19.25%) : <strong><?= number_format($montantTVA, 2, ',', ' ') ?></strong></p>
                        <p>Montant TTC (total d&eacutebit) : <strong><?= number_format($totalDebit, 2, ',', ' ') ?></strong></p>
                    </div>
                <?php endif; ?>

                <div class="text-right">
                    <a href="javascript:window.history.back()" class="btn btn-default">Retour</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

   
	<script src="../../js/jquery-3.7.1.min.js"></script>
	<script src="../../js/bootstrap.min.js"></script>
    
</body>
</html>

<?php
require_once('../../templates/footer.php');
?>