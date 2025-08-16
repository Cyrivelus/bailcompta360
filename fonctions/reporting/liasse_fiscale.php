<?php
// liasse_fiscale.php

require_once(__DIR__ . '/fonctions/reporting/liasse_fiscale_fonctions.php');

// Année fiscale depuis formulaire ou par défaut année en cours
$annee_fiscale = isset($_GET['annee']) ? intval($_GET['annee']) : date('Y');

// Récupération des données
try {
    $bilan = generer_bilan($annee_fiscale);
    $compte_resultat = generer_compte_de_resultat($annee_fiscale);
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liasse Fiscale <?= htmlspecialchars($annee_fiscale) ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
</head>
<body class="container">
    <h1 class="mt-4">Liasse Fiscale - Année <?= htmlspecialchars($annee_fiscale) ?></h1>

    <!-- Formulaire de sélection d'année -->
    <form method="get" class="form-inline mb-4">
        <label for="annee">Année fiscale :</label>
        <input type="number" class="form-control" name="annee" id="annee" 
               value="<?= htmlspecialchars($annee_fiscale) ?>" required>
        <button type="submit" class="btn btn-primary">Afficher</button>
        <a href="export_liasse_fiscale.php?annee=<?= htmlspecialchars($annee_fiscale) ?>" class="btn btn-success">
            <span class="glyphicon glyphicon-download-alt"></span> Exporter en CSV
        </a>
    </form>

    <!-- Tableau Bilan Actif -->
    <h2>Bilan - Actif</h2>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Compte</th>
                <th>Nom</th>
                <th>Total Débit</th>
                <th>Total Crédit</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($bilan['actif'] as $ligne): ?>
            <tr>
                <td><?= htmlspecialchars($ligne['Numero_Compte']) ?></td>
                <td><?= htmlspecialchars($ligne['Nom_Compte']) ?></td>
                <td><?= number_format($ligne['total_debit'], 2, ',', ' ') ?></td>
                <td><?= number_format($ligne['total_credit'], 2, ',', ' ') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Tableau Bilan Passif -->
    <h2>Bilan - Passif</h2>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Compte</th>
                <th>Nom</th>
                <th>Total Débit</th>
                <th>Total Crédit</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($bilan['passif'] as $ligne): ?>
            <tr>
                <td><?= htmlspecialchars($ligne['Numero_Compte']) ?></td>
                <td><?= htmlspecialchars($ligne['Nom_Compte']) ?></td>
                <td><?= number_format($ligne['total_debit'], 2, ',', ' ') ?></td>
                <td><?= number_format($ligne['total_credit'], 2, ',', ' ') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Compte de Résultat - Produits -->
    <h2>Compte de Résultat - Produits</h2>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Compte</th>
                <th>Nom</th>
                <th>Solde</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($compte_resultat['produits'] as $ligne): ?>
            <tr>
                <td><?= htmlspecialchars($ligne['Numero_Compte']) ?></td>
                <td><?= htmlspecialchars($ligne['Nom_Compte']) ?></td>
                <td><?= number_format($ligne['solde'], 2, ',', ' ') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Compte de Résultat - Charges -->
    <h2>Compte de Résultat - Charges</h2>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Compte</th>
                <th>Nom</th>
                <th>Solde</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($compte_resultat['charges'] as $ligne): ?>
            <tr>
                <td><?= htmlspecialchars($ligne['Numero_Compte']) ?></td>
                <td><?= htmlspecialchars($ligne['Nom_Compte']) ?></td>
                <td><?= number_format($ligne['solde'], 2, ',', ' ') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

</body>
</html>
