<?php
// export_liasse_fiscale.php

require_once(__DIR__ . '/fonctions/reporting/liasse_fiscale_fonctions.php');

// Récupération de l'année depuis l'URL
$annee_fiscale = isset($_GET['annee']) ? intval($_GET['annee']) : date('Y');

// Génération des données
$bilan = generer_bilan($annee_fiscale);
$compte_resultat = generer_compte_de_resultat($annee_fiscale);

// Nom du fichier CSV
$filename = "liasse_fiscale_" . $annee_fiscale . ".csv";

// En-têtes HTTP pour forcer le téléchargement
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Ouvrir la sortie standard comme un fichier
$output = fopen('php://output', 'w');

// Écriture de l'en-tête du bilan
fputcsv($output, ["=== BILAN ACTIF ==="]);
fputcsv($output, ["Compte", "Nom", "Total Débit", "Total Crédit"]);
foreach ($bilan['actif'] as $ligne) {
    fputcsv($output, [
        $ligne['Numero_Compte'],
        $ligne['Nom_Compte'],
        $ligne['total_debit'],
        $ligne['total_credit']
    ]);
}

fputcsv($output, []); // ligne vide
fputcsv($output, ["=== BILAN PASSIF ==="]);
fputcsv($output, ["Compte", "Nom", "Total Débit", "Total Crédit"]);
foreach ($bilan['passif'] as $ligne) {
    fputcsv($output, [
        $ligne['Numero_Compte'],
        $ligne['Nom_Compte'],
        $ligne['total_debit'],
        $ligne['total_credit']
    ]);
}

// Écriture du compte de résultat
fputcsv($output, []);
fputcsv($output, ["=== COMPTE DE RÉSULTAT - PRODUITS ==="]);
fputcsv($output, ["Compte", "Nom", "Solde"]);
foreach ($compte_resultat['produits'] as $ligne) {
    fputcsv($output, [
        $ligne['Numero_Compte'],
        $ligne['Nom_Compte'],
        $ligne['solde']
    ]);
}

fputcsv($output, []);
fputcsv($output, ["=== COMPTE DE RÉSULTAT - CHARGES ==="]);
fputcsv($output, ["Compte", "Nom", "Solde"]);
foreach ($compte_resultat['charges'] as $ligne) {
    fputcsv($output, [
        $ligne['Numero_Compte'],
        $ligne['Nom_Compte'],
        $ligne['solde']
    ]);
}

fclose($output);
exit;
?>
