<?php
// pages/agences/ajouter_agence.php

/**
 * Cette page gère l'ajout d'une nouvelle agence.
 * Elle affiche un formulaire et traite la soumission pour insérer les données en base.
 */

// 1. Démarrer la session en premier. C'est la première chose à faire.
session_start();

// 2. Inclure les fichiers de logique et de base de données.
require_once '../../fonctions/database.php';
require_once '../../fonctions/gestion_agences.php';
require_once '../../fonctions/gestion_comptes.php';

function getAllComptes(PDO $pdo) {
    try {
        // Définir la requête SQL pour sélectionner l'ID, le numéro et le nom de chaque compte
        $sql = "SELECT ID_Compte, Numero_Compte, Nom_Compte FROM Comptes_compta ORDER BY Numero_Compte ASC";
        
        // Préparer la requête pour éviter les injections SQL
        $stmt = $pdo->prepare($sql);
        
        // Exécuter la requête
        $stmt->execute();
        
        // Récupérer tous les résultats sous forme de tableau associatif
        $comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Retourner le tableau de comptes
        return $comptes;
        
    } catch (PDOException $e) {
        // Capturer les erreurs PDO et les relancer en tant qu'exceptions standard
        // pour une meilleure gestion des erreurs en amont
        throw new Exception("Erreur lors de la récupération des comptes : " . $e->getMessage());
    }
}


// Initialiser les messages d'alerte et la liste des comptes
$message = '';
$message_type = '';
$comptes = [];

try {
    // Récupérer la liste de tous les comptes comptables pour la liste déroulante
    $comptes = getAllComptes($pdo);
} catch (Exception $e) {
    $message = "Erreur lors de la récupération des comptes comptables : " . $e->getMessage();
    $message_type = 'danger';
}

// 3. Traitement du formulaire si la méthode est POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier si les données nécessaires sont présentes
    if (isset($_POST['code_agence']) && isset($_POST['libelle_agence']) && isset($_POST['no_compte'])) {
        $code_agence = trim(htmlspecialchars($_POST['code_agence']));
        $libelle_agence = trim(htmlspecialchars($_POST['libelle_agence']));
        $no_compte = trim(htmlspecialchars($_POST['no_compte']));

        // Vérifier si le code d'agence n'est pas vide
        if (empty($code_agence)) {
            $message = "Le code d'agence ne peut pas être vide.";
            $message_type = 'warning';
        } else {
            try {
                // Tenter d'ajouter la nouvelle agence
                if (addAgence($pdo, $code_agence, $libelle_agence, $no_compte)) {
                    $message = "L'agence a été ajoutée avec succès.";
                    $message_type = 'success';
                    // Rediriger vers la liste des agences après un ajout réussi
                    header('Location: liste_agences.php');
                    exit();
                } else {
                    $message = "Échec de l'ajout de l'agence. Une erreur est survenue.";
                    $message_type = 'danger';
                }
            } catch (Exception $e) {
                // Capter les exceptions de la fonction d'ajout (par exemple, doublon)
                $message = "Erreur lors de l'ajout de l'agence : " . $e->getMessage();
                $message_type = 'danger';
            }
        }
    } else {
        $message = "Tous les champs du formulaire sont requis.";
        $message_type = 'danger';
    }
}

// 4. Inclure les templates qui contiennent la mise en page HTML.
include '../../templates/header.php';
include '../../templates/navigation.php';
?>

<div class="container-fluid mt-4">
    <h2>Ajouter une Agence</h2>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <div class="mb-3">
            <label for="code_agence" class="form-label">Code Agence</label>
            <input type="text" class="form-control" id="code_agence" name="code_agence" required maxlength="2">
        </div>
        <div class="mb-3">
            <label for="libelle_agence" class="form-label">Libellé Agence</label>
            <input type="text" class="form-control" id="libelle_agence" name="libelle_agence" required>
        </div>
        <div class="mb-3">
            <label for="no_compte" class="form-label">N° Compte Comptable</label>
            <select class="form-select" id="no_compte" name="no_compte">
                <option value="">Sélectionner un compte</option>
                <?php foreach ($comptes as $compte): ?>
                    <option value="<?php echo htmlspecialchars($compte['ID_Compte']); ?>">
                        <?php echo htmlspecialchars($compte['Numero_Compte'] . ' - ' . $compte['Nom_Compte']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Ajouter l'agence</button>
        <a href="liste_agences.php" class="btn btn-secondary">Retour à la liste</a>
    </form>
</div>

<?php 
// Inclure le footer de la page (fin du HTML)
include '../../templates/footer.php';
?>
