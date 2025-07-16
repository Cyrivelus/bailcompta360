<?php
// pages/factures/import.php
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
require_once('../../fonctions/database.php');

// Configuration
$titre = 'Import des Factures';
$current_page = basename(__FILE__);
$cheminDossierUpload = '../../uploads/factures/'; // Dossier de stockage des fichiers uploadés
$typesFichiersAutorises = ['csv', 'xml']; // Formats supportés
$tailleMaxFichier = 5 * 1024 * 1024; // 5 Mo

// Créer le dossier d'upload s'il n'existe pas
if (!file_exists($cheminDossierUpload)) {
    mkdir($cheminDossierUpload, 0755, true);
}

// Gestion des messages
$message = '';

// Traitement du formulaire d'upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fichier_factures'])) {
    $nomFichier = $_FILES['fichier_factures']['name'];
    $tailleFichier = $_FILES['fichier_factures']['size'];
    $erreurFichier = $_FILES['fichier_factures']['error'];
    $tmpFichier = $_FILES['fichier_factures']['tmp_name'];
    $extensionFichier = strtolower(pathinfo($nomFichier, PATHINFO_EXTENSION));

    try {
        // Validation du fichier
        if ($erreurFichier !== UPLOAD_ERR_OK) {
            throw new Exception(getUploadErrorMessage($erreurFichier));
        }

        if ($tailleFichier > $tailleMaxFichier) {
            throw new Exception("La taille du fichier dépasse la limite autorisée (5 Mo).");
        }

        if (!in_array($extensionFichier, $typesFichiersAutorises)) {
            throw new Exception("Type de fichier non autorisé. Formats acceptés: " . implode(', ', $typesFichiersAutorises));
        }

        // Générer un nom de fichier unique
        $nomFichierUnique = uniqid('facture_') . '.' . $extensionFichier;
        $cheminFichierDestination = $cheminDossierUpload . $nomFichierUnique;

        // Déplacer le fichier uploadé
        if (!move_uploaded_file($tmpFichier, $cheminFichierDestination)) {
            throw new Exception("Erreur lors du déplacement du fichier uploadé.");
        }

        // Traiter le fichier selon son type
        $resultatImport = traiterFichierFacture($cheminFichierDestination, $extensionFichier, $pdo);

        if ($resultatImport['succes']) {
            $message = '<div class="alert alert-success">' . 
                       $resultatImport['nombre'] . ' factures importées avec succès.</div>';
        } else {
            $message = '<div class="alert alert-warning">' . 
                       $resultatImport['message'] . '</div>';
        }

    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">' . $e->getMessage() . '</div>';
    }
}

// Fonction pour traiter les fichiers selon leur type
function traiterFichierFacture($cheminFichier, $type, $pdo) {
    $resultat = ['succes' => false, 'nombre' => 0, 'message' => ''];
    
    try {
        switch ($type) {
            case 'csv':
                $donnees = lireFichierCSV($cheminFichier);
                break;
            case 'xml':
                $donnees = lireFichierXML($cheminFichier);
                break;
            default:
                throw new Exception("Format de fichier non supporté");
        }

        // Valider et importer les données
        if (!empty($donnees)) {
            $nombreImportees = 0;
            $pdo->beginTransaction();

            foreach ($donnees as $facture) {
                if (importerFacture($facture, $pdo)) {
                    $nombreImportees++;
                }
            }

            $pdo->commit();
            $resultat['succes'] = true;
            $resultat['nombre'] = $nombreImportees;
            $resultat['message'] = "Importation terminée";
        } else {
            $resultat['message'] = "Aucune donnée valide trouvée dans le fichier";
        }

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $resultat['message'] = $e->getMessage();
    }

    return $resultat;
}

// Fonction pour lire les fichiers CSV
function lireFichierCSV($cheminFichier) {
    $donnees = [];
    
    if (($handle = fopen($cheminFichier, "r")) !== false) {
        $entetes = fgetcsv($handle, 0, ";"); // Séparateur point-virgule
        
        while (($ligne = fgetcsv($handle, 0, ";")) !== false) {
            if (count($entetes) === count($ligne)) {
                $donnees[] = array_combine($entetes, $ligne);
            }
        }
        
        fclose($handle);
    }
    
    return $donnees;
}

// Fonction pour lire les fichiers XML (simplifiée)
function lireFichierXML($cheminFichier) {
    $donnees = [];
    $xml = simplexml_load_file($cheminFichier);
    
    if ($xml !== false) {
        foreach ($xml->facture as $facture) {
            $donnees[] = [
                'Numero_Facture' => (string)$facture->numero,
                'Date_Emission' => (string)$facture->date_emission,
                'Montant_HT' => (float)$facture->montant_ht,
                // Ajouter les autres champs nécessaires
            ];
        }
    }
    
    return $donnees;
}

// Fonction pour importer une facture en base
function importerFacture($data, $pdo) {
    // Validation des données requises
    $required = ['Numero_Facture', 'Date_Emission', 'Montant_HT'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Champ requis manquant: $field");
        }
    }

    // Préparation de la requête d'insertion
    $sql = "INSERT INTO Factures (
                Numero_Facture, Date_Emission, Date_Reception, Date_Echeance,
                Montant_HT, Montant_TVA, Montant_TTC, Statut_Facture,
                Nom_Fournisseur, Commentaire
            ) VALUES (
                :numero, :date_emission, :date_reception, :date_echeance,
                :montant_ht, :montant_tva, :montant_ttc, :statut,
                :fournisseur, :commentaire
            )";

    $stmt = $pdo->prepare($sql);
    
    // Calcul des montants si nécessaire
    $montantHT = (float)$data['Montant_HT'];
    $montantTVA = isset($data['Montant_TVA']) ? (float)$data['Montant_TVA'] : $montantHT * 0.2; // TVA 20% par défaut
    $montantTTC = $montantHT + $montantTVA;

    // Exécution de la requête
    return $stmt->execute([
        ':numero' => $data['Numero_Facture'],
        ':date_emission' => $data['Date_Emission'],
        ':date_reception' => $data['Date_Reception'] ?? null,
        ':date_echeance' => $data['Date_Echeance'] ?? null,
        ':montant_ht' => $montantHT,
        ':montant_tva' => $montantTVA,
        ':montant_ttc' => $montantTTC,
        ':statut' => $data['Statut_Facture'] ?? 'Nouvelle',
        ':fournisseur' => $data['Nom_Fournisseur'] ?? null,
        ':commentaire' => $data['Commentaire'] ?? null
    ]);
}

// Fonction pour les messages d'erreur d'upload
function getUploadErrorMessage($code) {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE: return 'Le fichier dépasse la taille maximale autorisée.';
        case UPLOAD_ERR_FORM_SIZE: return 'Le fichier dépasse la taille spécifiée dans le formulaire.';
        case UPLOAD_ERR_PARTIAL: return 'Le fichier n\'a été que partiellement uploadé.';
        case UPLOAD_ERR_NO_FILE: return 'Aucun fichier n\'a été uploadé.';
        case UPLOAD_ERR_NO_TMP_DIR: return 'Dossier temporaire manquant.';
        case UPLOAD_ERR_CANT_WRITE: return 'Échec de l\'écriture du fichier sur le disque.';
        case UPLOAD_ERR_EXTENSION: return 'Une extension PHP a arrêté l\'upload du fichier.';
        default: return 'Erreur inconnue lors de l\'upload.';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?= htmlspecialchars($titre) ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/monstyle.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <div class="container">
        <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>

        <?= $message ?>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Importer des factures</h3>
            </div>
            <div class="panel-body">
                <form action="<?= htmlspecialchars($current_page) ?>" method="POST" enctype="multipart/form-data" class="form-horizontal">
                    <div class="form-group">
                        <label for="fichier_factures" class="col-sm-3 control-label">Fichier de factures</label>
                        <div class="col-sm-9">
                            <input type="file" class="form-control" id="fichier_factures" name="fichier_factures" required>
                            <p class="help-block">Formats acceptés: <?= implode(', ', $typesFichiersAutorises) ?>. Taille max: 5 Mo.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-sm-offset-3 col-sm-9">
                            <button type="submit" class="btn btn-primary">
                                <i class="glyphicon glyphicon-upload"></i> Importer
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Instructions</h3>
            </div>
            <div class="panel-body">
                <h4>Format CSV requis:</h4>
                <p>Le fichier CSV doit utiliser le point-virgule (;) comme séparateur et contenir les colonnes suivantes:</p>
                <ul>
                    <li><strong>Numero_Facture</strong> (requis)</li>
                    <li><strong>Date_Emission</strong> (format YYYY-MM-DD, requis)</li>
                    <li>Date_Reception</li>
                    <li>Date_Echeance</li>
                    <li><strong>Montant_HT</strong> (requis)</li>
                    <li>Montant_TVA</li>
                    <li>Nom_Fournisseur</li>
                    <li>Commentaire</li>
                </ul>
            </div>
        </div>
    </div>

    <?php require_once('../../templates/footer.php'); ?>
</body>
</html>