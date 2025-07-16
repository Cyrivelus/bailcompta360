<?php
// pages/ecritures/liste.php

$titre = 'Liste des Écritures Comptables';
$current_page = basename($_SERVER['PHP_SELF']); // Pour la classe 'active' dans la navigation

require_once('../../templates/header.php');
require_once('../../templates/navigation.php'); // Inclusion de la navigation
?>


<?php
// generer_csv.php

// Inclure les fichiers nécessaires (connexion à la base de données, fonctions, etc.)
require_once '../fonctions/database.php';
require_once '../fonctions/gestion_factures.php'; // Pour récupérer les factures

// *** Configuration de l'export CSV ***
$nomFichierCSV = 'factures_export_' . date('Ymd_His') . '.csv';
$separateurCSV = ';';
$enclosureCSV = '"';
$ligneTerminaisonCSV = "\n";

// *** Récupérer les factures à exporter ***
// Vous pouvez ajouter des critères de filtrage ici (par exemple, par date, client, statut)
$factures = getListeFactures($db); // Fonction pour récupérer la liste des factures

if (!empty($factures)) {
    // *** Définir les en-têtes HTTP pour forcer le téléchargement du fichier CSV ***
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nomFichierCSV . '"');

    // *** Ouvrir un flux de sortie en écriture ***
    $output = fopen('php://output', 'w');

    // *** Écrire l'en-tête du fichier CSV ***
    $enTete = [
        'ID Facture',
        'Numéro de Facture',
        'Date d\'Émission',
        'Date d\'Échéance',
        'Montant HT',
        'Taux TVA',
        'Montant TTC',
        'ID Client',
        'Nom du Client', // Vous devrez peut-être joindre la table des clients
        'Statut',
        'Description',
        'Date de Création',
        'Date de Paiement'
    ];
    fputcsv($output, $enTete, $separateurCSV, $enclosureCSV);

    // *** Écrire les données des factures dans le fichier CSV ***
    foreach ($factures as $facture) {
        // *** Récupérer le nom du client (nécessite une jointure ou une autre requête) ***
        $nomClient = getNomClient($db, $facture['id_client']); // Fonction à implémenter

        $ligne = [
            $facture['ID_Facture'],
            $facture['numero_facture'],
            $facture['date_emission'],
            $facture['date_echeance'],
            $facture['montant_ht'],
            $facture['taux_tva'],
            $facture['montant_ttc'],
            $facture['id_client'],
            $nomClient,
            $facture['statut'],
            $facture['description'],
            $facture['date_creation'],
            $facture['date_paiement']
        ];
        fputcsv($output, $ligne, $separateurCSV, $enclosureCSV);
    }

    // *** Fermer le flux de sortie ***
    fclose($output);
    exit(); // Important de s'arrêter ici pour ne pas afficher d'autre contenu HTML
} else {
    // *** Afficher un message si aucune facture n'a été trouvée ***
    echo "<div class='alert alert-info'>Aucune facture à exporter. <a href='../factures/index.php'>Retour à la gestion des factures</a></div>";
}

// Fonction (à implémenter dans gestion_factures.php ou gestion_clients.php) pour récupérer le nom d'un client
function getNomClient($db, $clientId) {
    $stmt = $db->prepare("SELECT Nom_Commercial FROM Tiers WHERE ID_Tiers = :id AND Type_Tiers = 'Client'");
    $stmt->bindParam(':id', $clientId, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['Nom_Commercial'] : '';
}

// La fonction getListeFactures est supposée exister dans fonctions/gestion_factures.php
?>
<?php
require_once('../../templates/footer.php');
?>