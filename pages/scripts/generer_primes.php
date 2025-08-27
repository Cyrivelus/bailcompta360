<?php
// scripts/generer_primes.php
// Ce script est destiné à être exécuté via une tâche CRON

require_once __DIR__ . '/../fonctions/database.php';

try {
    $pdo = getPdoConnection();
    
    // Récupérer tous les contrats actifs dont la prochaine date de facturation est passée
    $stmt = $pdo->prepare("SELECT * FROM contrats_assurance WHERE Statut_Contrat = 'actif' AND Prochaine_Date_Facturation <= CURDATE()");
    $stmt->execute();
    $contrats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($contrats as $contrat) {
        // Préparer les données de la nouvelle facture
        $data_facture = [
            'id_client' => $contrat['ID_Client'],
            'date_facture' => date('Y-m-d'),
            'montant_ht' => $contrat['Prime_Annuelle'], // Ou le montant périodique
            'montant_ttc' => $contrat['Prime_Annuelle'],
            'description' => "Facture de prime d'assurance (N° police: " . $contrat['Numero_Police'] . ")",
            'statut' => 'impayé',
            'type' => 'prime_assurance'
        ];

        // Insertion dans la table des factures
        $sql_insert = "INSERT INTO kio-factures (id_client, date_facture, montant_ht, montant_ttc, description, statut, type) VALUES (:id_client, :date_facture, :montant_ht, :montant_ttc, :description, :statut, :type)";
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute($data_facture);

        // Mettre à jour la prochaine date de facturation
        $nouvelle_date = date('Y-m-d', strtotime($contrat['Prochaine_Date_Facturation'] . ' +1 year')); // Ou +1 month
        $sql_update = "UPDATE contrats_assurance SET Prochaine_Date_Facturation = :nouvelle_date WHERE ID_Contrat = :id_contrat";
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([':nouvelle_date' => $nouvelle_date, ':id_contrat' => $contrat['ID_Contrat']]);
    }
    echo "Génération des primes terminée avec succès.\n";

} catch (PDOException $e) {
    echo "Erreur de base de données : " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Erreur générale : " . $e->getMessage() . "\n";
    exit(1);
}