<?php
// pages/emprunts/modifier.php
// Script pour modifier un emprunt bancaire existant.
// L'écriture comptable initiale liée NE sera PAS mise à jour.

// --- Initialisation ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); // Afficher toutes les erreurs pour le débogage
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

header('Content-Type: text/html; charset=utf-8');

// --- Titre de la page ---
$titre = 'Modifier un Emprunt Bancaire';
$current_page = basename(__FILE__); // Pour la classe 'active' dans la navigation

// --- Inclusions ---
// Ce fichier doit initialiser $pdo, une instance de PDO connectée à la base de données.
require_once '../../fonctions/database.php';

// Vous pouvez retirer ces inclusions si elles ne sont plus utilisées après avoir retiré la logique d'écriture comptable
// require_once '../../fonctions/gestion_emprunts.php';
// require_once '../../fonctions/gestion_ecritures.php';

// --- Vérification de l'ID fourni ---
$idEmprunt = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

// Initialisation des variables pour le formulaire
$emprunt = null;
$erreur = null;
$success = null;
$formData = []; // Tableau pour stocker les données du formulaire (initiales ou postées)

// --- Connexion à la base de données (assumant que $pdo est initialisée par database.php) ---
if (!isset($pdo) || !$pdo instanceof PDO) {
    $erreur = "La connexion à la base de données n'a pas été correctement initialisée.";
    error_log("Erreur (modifier.php - PDO non initialisé) : " . $erreur);
}

// --- Récupérer les informations de l'emprunt si un ID est fourni et la connexion DB est OK ---
if ($idEmprunt && !$erreur) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM Emprunts_Bancaires WHERE ID_Emprunt = :id_emprunt");
        $stmt->bindParam(':id_emprunt', $idEmprunt, PDO::PARAM_INT);
        $stmt->execute();
        $emprunt = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($emprunt) {
            // Charger les données de l'emprunt dans $formData pour pré-remplir le formulaire
            $formData = $emprunt;
            // Formater les dates pour les champs input type="date" (YYYY-MM-DD)
            $dateFields = ['Date_Souscription', 'Date_Mise_En_Place', 'Date_Premiere_Echeance', 'Date_Derniere_Echeance', 'Date_Debut_Amortissement', 'Date_Fin_Amortissement'];
            foreach ($dateFields as $field) {
                if (isset($formData[$field]) && !empty($formData[$field])) {
                    // Utiliser DateTime pour gérer les formats de date potentiels de la DB
                    try {
                        $dateObj = new DateTime($formData[$field]);
                        $formData[$field] = $dateObj->format('Y-m-d');
                    } catch (Exception $e) {
                        // En cas d'erreur de format, laisser vide ou gérer autrement
                        $formData[$field] = '';
                        error_log("Erreur de format de date pour le champ " . $field . " (ID Emprunt: " . $idEmprunt . ") : " . $e->getMessage());
                    }
                } else {
                    $formData[$field] = ''; // Assurer que le champ est vide si la date est nulle ou vide
                }
            }
            // Convertir le taux TEG en pourcentage pour l'affichage
            if (isset($formData['Taux_Effectif_Global'])) {
                $formData['Taux_Effectif_Global'] *= 100;
            }
            // Convertir le taux Interet_SP_Taux en pourcentage pour l'affichage
            if (isset($formData['Interet_SP_Taux'])) {
                $formData['Interet_SP_Taux'] *= 100;
            }


        } else {
            $erreur = "Emprunt non trouvé avec l'ID " . htmlspecialchars($idEmprunt) . ".";
        }
    } catch (PDOException $e) {
        $erreur = "Erreur lors de la récupération de l'emprunt : " . $e->getMessage();
        error_log("Erreur (modifier.php - get emprunt) : " . $e->getMessage());
    }
} elseif (!$idEmprunt && !$erreur) {
    $erreur = "ID d'emprunt non valide ou manquant.";
}


// --- Traitement du formulaire si soumis ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $idEmprunt && !$erreur) {
    // Récupérer toutes les données du formulaire dans $formData
    // Utiliser un tableau pour gérer facilement tous les champs
    $formData = $_POST;

    // Convertir les valeurs numériques et booléennes si nécessaire
    $formData['Montant_Pret'] = isset($formData['Montant_Pret']) ? floatval($formData['Montant_Pret']) : 0;
    $formData['Taux_Effectif_Global'] = isset($formData['Taux_Effectif_Global']) ? floatval($formData['Taux_Effectif_Global']) / 100 : 0; // Convertir % en décimal
    $formData['Montant_Differe'] = isset($formData['Montant_Differe']) ? floatval($formData['Montant_Differe']) : 0;
    $formData['Nombre_Echeances'] = isset($formData['Nombre_Echeances']) ? intval($formData['Nombre_Echeances']) : 0;
    $formData['Nombre_Jours_Reels'] = isset($formData['Nombre_Jours_Reels']) ? intval($formData['Nombre_Jours_Reels']) : 0;
    $formData['Interet_SP_Taux'] = isset($formData['Interet_SP_Taux']) ? floatval($formData['Interet_SP_Taux']) / 100 : 0; // Convertir % en décimal
    $formData['Interet_SP_Montant'] = isset($formData['Interet_SP_Montant']) ? floatval($formData['Interet_SP_Montant']) : 0;
    $formData['Taxes'] = isset($formData['Taxes']) ? floatval($formData['Taxes']) : 0;
    $formData['Montant_Initial'] = isset($formData['Montant_Initial']) ? floatval($formData['Montant_Initial']) : 0;
    $formData['Duree'] = isset($formData['Duree']) ? intval($formData['Duree']) : 0;
    // Gérer le champ Echeance_Fin_Mois (checkbox)
    $formData['Echeance_Fin_Mois'] = isset($formData['Echeance_Fin_Mois']) ? 1 : 0; // 1 si coché, 0 sinon

    // Validation des données (ajouter des validations pour tous les champs requis/importants)
    if (empty($formData['Banque'])) { $erreur = "Le nom de la banque est requis."; }
    if (empty($formData['Numero_Pret'])) { $erreur = "Le numéro de pret est requis."; }
    if ($formData['Montant_Pret'] <= 0) { $erreur = "Le montant du pret doit être supérieur à zéro."; }
    if (empty($formData['Date_Mise_En_Place'])) { $erreur = "La date de mise en place est requise."; }
    // Ajoutez d'autres validations selon vos besoins pour les autres champs...


    if (!$erreur) {
        // --- Début de la transaction ---
        $pdo->beginTransaction();

        try {
            // 1. Mettre à jour les informations de l'emprunt dans Emprunts_Bancaires
            $stmtUpdateEmprunt = $pdo->prepare("UPDATE Emprunts_Bancaires SET
                Banque = :banque,
                Date_Souscription = :date_souscription,
                Agence = :agence,
                Devise = :devise,
                Numero_Pret = :numero_pret,
                Type_Pret = :type_pret,
                Client = :client,
                Montant_Pret = :montant_pret,
                Source_Financement = :source_financement,
                Taux_Effectif_Global = :taux_effectif_global,
                Montant_Differe = :montant_differe,
                Type_Plan = :type_plan,
                Echeance_Fin_Mois = :echeance_fin_mois,
                Nombre_Echeances = :nombre_echeances,
                Gestion_Differe = :gestion_differe,
                Nombre_Jours_Reels = :nombre_jours_reels,
                Type_Interet = :type_interet,
                Date_Mise_En_Place = :date_mise_en_place,
                Date_Premiere_Echeance = :date_premiere_echeance,
                Date_Derniere_Echeance = :date_derniere_echeance,
                Interet_SP_Taux = :interet_sp_taux,
                Interet_SP_Montant = :interet_sp_montant,
                Taxes = :taxes,
                Periode = :periode,
                Terme = :terme,
                Perception = :perception,
                Date_Debut_Amortissement = :date_debut_amortissement,
                Date_Fin_Amortissement = :date_fin_amortissement,
                Montant_Initial = :montant_initial,
                Duree = :duree,
                Type_Amortissement = :type_amortissement
                WHERE ID_Emprunt = :id_emprunt");

            // Lier les paramètres (utiliser les valeurs de $formData)
            // Utilisez bindParam pour les valeurs qui pourraient être NULL et bindValue pour les autres
            $stmtUpdateEmprunt->bindParam(':banque', $formData['Banque']);
            $stmtUpdateEmprunt->bindParam(':date_souscription', $formData['Date_Souscription']);
            $stmtUpdateEmprunt->bindParam(':agence', $formData['Agence']);
            $stmtUpdateEmprunt->bindParam(':devise', $formData['Devise']);
            $stmtUpdateEmprunt->bindParam(':numero_pret', $formData['Numero_Pret']);
            $stmtUpdateEmprunt->bindParam(':type_pret', $formData['Type_Pret']);
            $stmtUpdateEmprunt->bindParam(':client', $formData['Client']);
            $stmtUpdateEmprunt->bindParam(':montant_pret', $formData['Montant_Pret']);
            $stmtUpdateEmprunt->bindParam(':source_financement', $formData['Source_Financement']);
            $stmtUpdateEmprunt->bindParam(':taux_effectif_global', $formData['Taux_Effectif_Global']);
            $stmtUpdateEmprunt->bindParam(':montant_differe', $formData['Montant_Differe']);
            $stmtUpdateEmprunt->bindParam(':type_plan', $formData['Type_Plan']);
            $stmtUpdateEmprunt->bindParam(':echeance_fin_mois', $formData['Echeance_Fin_Mois'], PDO::PARAM_BOOL);
            $stmtUpdateEmprunt->bindParam(':nombre_echeances', $formData['Nombre_Echeances'], PDO::PARAM_INT);
            $stmtUpdateEmprunt->bindParam(':gestion_differe', $formData['Gestion_Differe']);
            $stmtUpdateEmprunt->bindParam(':nombre_jours_reels', $formData['Nombre_Jours_Reels'], PDO::PARAM_INT);
            $stmtUpdateEmprunt->bindParam(':type_interet', $formData['Type_Interet']);
            $stmtUpdateEmprunt->bindParam(':date_mise_en_place', $formData['Date_Mise_En_Place']);
            $stmtUpdateEmprunt->bindParam(':date_premiere_echeance', $formData['Date_Premiere_Echeance']);
            $stmtUpdateEmprunt->bindParam(':date_derniere_echeance', $formData['Date_Derniere_Echeance']);
            $stmtUpdateEmprunt->bindParam(':interet_sp_taux', $formData['Interet_SP_Taux']);
            $stmtUpdateEmprunt->bindParam(':interet_sp_montant', $formData['Interet_SP_Montant']);
            $stmtUpdateEmprunt->bindParam(':taxes', $formData['Taxes']);
            $stmtUpdateEmprunt->bindParam(':periode', $formData['Periode']);
            $stmtUpdateEmprunt->bindParam(':terme', $formData['Terme']);
            $stmtUpdateEmprunt->bindParam(':perception', $formData['Perception']);
            $stmtUpdateEmprunt->bindParam(':date_debut_amortissement', $formData['Date_Debut_Amortissement']);
            $stmtUpdateEmprunt->bindParam(':date_fin_amortissement', $formData['Date_Fin_Amortissement']);
            $stmtUpdateEmprunt->bindParam(':montant_initial', $formData['Montant_Initial']);
            $stmtUpdateEmprunt->bindParam(':duree', $formData['Duree'], PDO::PARAM_INT);
            $stmtUpdateEmprunt->bindParam(':type_amortissement', $formData['Type_Amortissement']);
            $stmtUpdateEmprunt->bindParam(':id_emprunt', $idEmprunt, PDO::PARAM_INT);

            $stmtUpdateEmprunt->execute();

            // --- DEBUT DE LA SECTION SUPPRIMEE / COMMENTEE POUR NE PAS METTRE A JOUR L'ECRITURE ---
            /*
            // 2. Tenter de trouver et mettre à jour l'écriture comptable initiale liée
            // On cherche une écriture créée autour de la date de mise en place/souscription
            // et dont la description contient l'ID ou le numéro de pret.
            // C'est une recherche heuristique et peut ne pas trouver la bonne écriture si le format de description varie.
            $searchTerm = "%Emprunt ID " . $idEmprunt . "%"; // Recherche par ID comme dans details.php
            $searchTermAlt = "%Emprunt Numero " . $formData['Numero_Pret'] . "%"; // Recherche par Numero_Pret

            $stmtFindEcriture = $pdo->prepare("SELECT ID_Ecriture, Description FROM Ecritures
                WHERE (Description LIKE :searchTerm OR Description LIKE :searchTermAlt)
                AND (Date_Saisie = :date_mise_en_place OR Date_Saisie = :date_souscription)
                ORDER BY Date_Saisie ASC"); // On prend la plus ancienne si plusieurs correspondent

            $stmtFindEcriture->bindParam(':searchTerm', $searchTerm);
            $stmtFindEcriture->bindParam(':searchTermAlt', $searchTermAlt);
            $stmtFindEcriture->bindParam(':date_mise_en_place', $formData['Date_Mise_En_Place']);
            $stmtFindEcriture->bindParam(':date_souscription', $formData['Date_Souscription']);
            $stmtFindEcriture->execute();
            $ecritureLiee = $stmtFindEcriture->fetch(PDO::FETCH_ASSOC);

            if ($ecritureLiee) {
                // Si une écriture est trouvée, la mettre à jour
                // Puisque la colonne 'Reference' n'existe pas dans la table Ecritures selon le schéma fourni,
                // nous ne mettons à jour que la colonne 'Description'.
                $nouvelleDescription = "MODIF: Emprunt Numero " . htmlspecialchars($formData['Numero_Pret']) . " (" . htmlspecialchars($formData['Banque']) . ") - Mise à jour des détails";

                $stmtUpdateEcriture = $pdo->prepare("UPDATE Ecritures SET Description = :description WHERE ID_Ecriture = :id_ecriture");
                $stmtUpdateEcriture->bindParam(':description', $nouvelleDescription);
                $stmtUpdateEcriture->bindParam(':id_ecriture', $ecritureLiee['ID_Ecriture'], PDO::PARAM_INT);
                $stmtUpdateEcriture->execute();

                // Note: La mise à jour des montants dans les Lignes_Ecritures n'est PAS faite ici car cela
                // nécessiterait une logique complexe de recalcul et d'impact sur les comptes.
                // Cette amélioration se limite à mettre à jour les informations descriptives de l'écriture initiale.

            } else {
                // Optionnel: Logguer si aucune écriture liée n'a été trouvée pour la mise à jour
                error_log("Aucune écriture comptable initiale trouvée pour l'emprunt ID " . $idEmprunt . " pour la mise à jour.");
            }
            */
            // --- FIN DE LA SECTION SUPPRIMEE / COMMENTEE ---


            // 3. Valider la transaction
            $pdo->commit();

            $success = "L'emprunt a été mis à jour avec succès."; // Message de succès mis à jour
        } catch (PDOException $e) {
            // En cas d'erreur, annuler la transaction
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erreur = "Erreur lors de la mise à jour de l'emprunt : " . $e->getMessage();
            error_log("Erreur (modifier.php - update) : " . $e->getMessage());
        }
    }
}

// --- Inclusions du template HTML ---
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | <?= htmlspecialchars($titre) ?></title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
	<link rel="stylesheet" href="../../css/bootstrap.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .details-container {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .details-container h3 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .details-container p {
            margin-bottom: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .solde-final {
            font-weight: bold;
            margin-top: 15px;
            font-size: 1.2em;
        }
        .extrait-header {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #e9ecef;
            border-radius: 5px;
            border: 1px solid #ced4da;
        }
        .extrait-header h4 {
            margin-top: 0;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px dashed #adb5bd;
        }
        .extrait-links {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #e9f7ef;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
        }
        .extrait-links h4 {
            margin-top: 0;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px dashed #8fd19e;
        }
        .extrait-links a {
            margin-right: 15px;
            display: inline-block;
        }
        .table-extrait th {
            text-align: center;
        }
        .table-extrait td {
            text-align: left;
        }
        .table-extrait td.text-right {
            text-align: right;
        }
        .echeance-header-row td {
            font-weight: bold;
            background-color: #f2f2f2;
            border-bottom: none;
        }
        .echeance-detail-row td {
            border-top: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="page-header"><?= htmlspecialchars($titre) ?></h2>
        <?php if ($erreur): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?> <a href="index.php" class="alert-link">Retour à la liste des emprunts</a></div>
        <?php endif; ?>

        <?php if ($idEmprunt && $emprunt && !$erreur): ?>
            <form action="modifier.php?id=<?= htmlspecialchars($idEmprunt) ?>" method="POST" class="form-horizontal">
                <h4>Informations Générales</h4>
                <div class="form-group">
                    <label for="Banque" class="col-sm-3 control-label">Banque</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" id="Banque" name="Banque" value="<?= htmlspecialchars($formData['Banque'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="Date_Souscription" class="col-sm-3 control-label">Date de Souscription</label>
                    <div class="col-sm-9">
                        <input type="date" class="form-control" id="Date_Souscription" name="Date_Souscription" value="<?= htmlspecialchars($formData['Date_Souscription'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Agence" class="col-sm-3 control-label">Agence</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" id="Agence" name="Agence" value="<?= htmlspecialchars($formData['Agence'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Devise" class="col-sm-3 control-label">Devise</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" id="Devise" name="Devise" value="<?= htmlspecialchars($formData['Devise'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Numero_Pret" class="col-sm-3 control-label">Numéro de Prêt</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" id="Numero_Pret" name="Numero_Pret" value="<?= htmlspecialchars($formData['Numero_Pret'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="Type_Pret" class="col-sm-3 control-label">Type de Prêt</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" id="Type_Pret" name="Type_Pret" value="<?= htmlspecialchars($formData['Type_Pret'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Client" class="col-sm-3 control-label">Client</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" id="Client" name="Client" value="<?= htmlspecialchars($formData['Client'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Montant_Pret" class="col-sm-3 control-label">Montant du Prêt</label>
                    <div class="col-sm-9">
                        <input type="number" class="form-control" id="Montant_Pret" name="Montant_Pret" value="<?= htmlspecialchars($formData['Montant_Pret'] ?? '') ?>" step="0.01" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="Source_Financement" class="col-sm-3 control-label">Source de Financement</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" id="Source_Financement" name="Source_Financement" value="<?= htmlspecialchars($formData['Source_Financement'] ?? '') ?>">
                    </div>
                </div>

                <h4>Informations Financières</h4>
                <div class="form-group">
                    <label for="Taux_Effectif_Global" class="col-sm-3 control-label">Taux Effectif Global (%)</label>
                    <div class="col-sm-9">
                        <input type="number" class="form-control" id="Taux_Effectif_Global" name="Taux_Effectif_Global" value="<?= htmlspecialchars($formData['Taux_Effectif_Global'] ?? '') ?>" step="0.0001">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Montant_Differe" class="col-sm-3 control-label">Montant Différé</label>
                    <div class="col-sm-9">
                        <input type="number" class="form-control" id="Montant_Differe" name="Montant_Differe" value="<?= htmlspecialchars($formData['Montant_Differe'] ?? '') ?>" step="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Type_Plan" class="col-sm-3 control-label">Type de Plan</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" id="Type_Plan" name="Type_Plan" value="<?= htmlspecialchars($formData['Type_Plan'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Echeance_Fin_Mois" class="col-sm-3 control-label">Échéance Fin Mois</label>
                    <div class="col-sm-9">
                        <input type="checkbox" id="Echeance_Fin_Mois" name="Echeance_Fin_Mois" value="1" <?= (isset($formData['Echeance_Fin_Mois']) && $formData['Echeance_Fin_Mois']) ? 'checked' : '' ?>>
                    </div>
                </div>
                <div class="form-group">
                    <label for="Nombre_Echeances" class="col-sm-3 control-label">Nombre d'échéances</label>
                    <div class="col-sm-9">
                        <input type="number" class="form-control" id="Nombre_Echeances" name="Nombre_Echeances" value="<?= htmlspecialchars($formData['Nombre_Echeances'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Gestion_Differe" class="col-sm-3 control-label">Gestion du Différé</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" id="Gestion_Differe" name="Gestion_Differe" value="<?= htmlspecialchars($formData['Gestion_Differe'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Nombre_Jours_Reels" class="col-sm-3 control-label">Nombre de Jours Réels</label>
                    <div class="col-sm-9">
                        <input type="number" class="form-control" id="Nombre_Jours_Reels" name="Nombre_Jours_Reels" value="<?= htmlspecialchars($formData['Nombre_Jours_Reels'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Type_Interet" class="col-sm-3 control-label">Type d'Intérêt</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" id="Type_Interet" name="Type_Interet" value="<?= htmlspecialchars($formData['Type_Interet'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Interet_SP_Taux" class="col-sm-3 control-label">Intérêt SP Taux (%)</label>
                    <div class="col-sm-9">
                        <input type="number" class="form-control" id="Interet_SP_Taux" name="Interet_SP_Taux" value="<?= htmlspecialchars($formData['Interet_SP_Taux'] ?? '') ?>" step="0.0001">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Interet_SP_Montant" class="col-sm-3 control-label">Intérêt SP Montant</label>
                    <div class="col-sm-9">
                        <input type="number" class="form-control" id="Interet_SP_Montant" name="Interet_SP_Montant" value="<?= htmlspecialchars($formData['Interet_SP_Montant'] ?? '') ?>" step="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Taxes" class="col-sm-3 control-label">Taxes</label>
                    <div class="col-sm-9">
                        <input type="number" class="form-control" id="Taxes" name="Taxes" value="<?= htmlspecialchars($formData['Taxes'] ?? '') ?>" step="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Periode" class="col-sm-3 control-label">Période</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" id="Periode" name="Periode" value="<?= htmlspecialchars($formData['Periode'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Terme" class="col-sm-3 control-label">Terme</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" id="Terme" name="Terme" value="<?= htmlspecialchars($formData['Terme'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Perception" class="col-sm-3 control-label">Perception</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" id="Perception" name="Perception" value="<?= htmlspecialchars($formData['Perception'] ?? '') ?>">
                    </div>
                </div>

                <h4>Dates Clés</h4>
                <div class="form-group">
                    <label for="Date_Mise_En_Place" class="col-sm-3 control-label">Date de Mise en Place</label>
                    <div class="col-sm-9">
                        <input type="date" class="form-control" id="Date_Mise_En_Place" name="Date_Mise_En_Place" value="<?= htmlspecialchars($formData['Date_Mise_En_Place'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="Date_Premiere_Echeance" class="col-sm-3 control-label">Date Première Échéance</label>
                    <div class="col-sm-9">
                        <input type="date" class="form-control" id="Date_Premiere_Echeance" name="Date_Premiere_Echeance" value="<?= htmlspecialchars($formData['Date_Premiere_Echeance'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Date_Derniere_Echeance" class="col-sm-3 control-label">Date Dernière Échéance</label>
                    <div class="col-sm-9">
                        <input type="date" class="form-control" id="Date_Derniere_Echeance" name="Date_Derniere_Echeance" value="<?= htmlspecialchars($formData['Date_Derniere_Echeance'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Date_Debut_Amortissement" class="col-sm-3 control-label">Date Début Amortissement</label>
                    <div class="col-sm-9">
                        <input type="date" class="form-control" id="Date_Debut_Amortissement" name="Date_Debut_Amortissement" value="<?= htmlspecialchars($formData['Date_Debut_Amortissement'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Date_Fin_Amortissement" class="col-sm-3 control-label">Date Fin Amortissement</label>
                    <div class="col-sm-9">
                        <input type="date" class="form-control" id="Date_Fin_Amortissement" name="Date_Fin_Amortissement" value="<?= htmlspecialchars($formData['Date_Fin_Amortissement'] ?? '') ?>">
                    </div>
                </div>

                <h4>Amortissement</h4>
                <div class="form-group">
                    <label for="Montant_Initial" class="col-sm-3 control-label">Montant Initial</label>
                    <div class="col-sm-9">
                        <input type="number" class="form-control" id="Montant_Initial" name="Montant_Initial" value="<?= htmlspecialchars($formData['Montant_Initial'] ?? '') ?>" step="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Duree" class="col-sm-3 control-label">Durée (jours)</label>
                    <div class="col-sm-9">
                        <input type="number" class="form-control" id="Duree" name="Duree" value="<?= htmlspecialchars($formData['Duree'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="Type_Amortissement" class="col-sm-3 control-label">Type d'Amortissement</label>
                    <div class="col-sm-9">
                        <select class="form-control" id="Type_Amortissement" name="Type_Amortissement" style="height:50%;">
                            <option value="">Sélectionner un type</option>
                            <option value="linéaire" <?= (isset($formData['Type_Amortissement']) && $formData['Type_Amortissement'] == 'linéaire') ? 'selected' : '' ?>>Linéaire</option>
                            <option value="progressif" <?= (isset($formData['Type_Amortissement']) && $formData['Type_Amortissement'] == 'progressif') ? 'selected' : '' ?>>Progressif</option>
                             <option value="constant" <?= (isset($formData['Type_Amortissement']) && $formData['Type_Amortissement'] == 'constant') ? 'selected' : '' ?>>Constant</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <div class="col-sm-offset-3 col-sm-9">
                        <button type="submit" class="btn btn-primary">Mettre à jour l'emprunt</button>
                        <a href="index.php" class="btn btn-default">Annuler</a>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <p>Impossible de charger le formulaire de modification de l'emprunt.</p>
            <div class="text-right">
                <a href="index.php" class="btn btn-default">Retour à la liste des emprunts</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
	<script src="../js/jquery-3.7.1.js"></script>
	<script src="../js/bootstrap.min.js"></script>
</body>
</html>

<?php
require_once('../../templates/footer.php');
?>