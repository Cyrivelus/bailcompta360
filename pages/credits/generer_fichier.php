
<?php
// pages/credits/generer_fichier.php

session_start();

// Inclus le fichier qui contient la fonction principale de génération
require_once '../../fonctions/GenerationFichiersDev.php';
// Inclus la base de données si nécessaire pour des informations supplémentaires
require_once '../../fonctions/database.php';

// Vérifie que les données de la session existent
if (!isset($_SESSION['simulation_data'])) {
    die("Erreur : Données de simulation non trouvées. Veuillez d'abord générer un tableau d'amortissement.");
}

$simulation_data = $_SESSION['simulation_data'];

// Récupération des informations additionnelles pour le document si disponibles
$matricule = 'XXX';
$numero_dossier = 'XXX/XXX';
$id_client = $simulation_data['id_client'] ?? null;

if ($id_client) {
    try {
        // Récupérer le matricule du client
        $stmt_matricule = $pdo->prepare("SELECT matricule FROM clients WHERE id_client = ?");
        $stmt_matricule->execute([$id_client]);
        $result_matricule = $stmt_matricule->fetch(PDO::FETCH_ASSOC);
        if ($result_matricule && !empty($result_matricule['matricule'])) {
            $matricule = $result_matricule['matricule'];
        }

        // Récupérer le numéro de dossier (id du dossier KYC)
        $stmt_dossier = $pdo->prepare("SELECT id FROM dossiers_kyc WHERE id_client = ?");
        $stmt_dossier->execute([$id_client]);
        $result_dossier = $stmt_dossier->fetch(PDO::FETCH_ASSOC);
        if ($result_dossier && !empty($result_dossier['id'])) {
            $numero_dossier = $result_dossier['id'];
        }
    } catch (PDOException $e) {
        // Gérer les erreurs de base de données
        error_log("Erreur de base de données : " . $e->getMessage());
        // Les valeurs par défaut seront utilisées
    }
}

// Ajoute les informations supplémentaires à la session pour qu'elles soient disponibles
// dans la fonction de génération de fichiers
$_SESSION['simulation_data']['matricule'] = $matricule;
$_SESSION['simulation_data']['numero_dossier'] = $numero_dossier;

// Appelle la fonction principale qui gère la génération en fonction des paramètres GET
genererFichier();

?>