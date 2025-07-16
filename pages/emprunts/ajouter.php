<?php
// ajouter.php - Script pour ajouter un nouvel emprunt bancaire et générer son échéancier.
// Les écritures comptables associées devront être déclenchées séparément.
// Utilise des fichiers de fonctions externes et une connexion PDO.
// --- Configuration et Initialisation Strictes ---


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');


// --- Inclure les fichiers nécessaires ---
// Connexion à la base de données
require_once '../../fonctions/database.php';
// Fonctions pour gérer les emprunts (inclut genererPlanAmortissement)
require_once '../../fonctions/gestion_emprunts.php';
// Fonctions pour gérer les comptes (pour récupérer les listes)
require_once '../../fonctions/gestion_comptes.php';
// Fonction pour récupérer les journaux (même si non utilisés pour les écritures, ils peuvent être pour le formulaire)
require_once '../../fonctions/gestion_journaux.php';


// --- Inclure l'en-tête et la navigation ---
require_once('../../templates/header.php');
require_once('../../templates/navigation.php');


$TITRE_PAGE = "Ajout Emprunt";
$messageErreur = "";
$messageSucces = ""; // Initialize success message
$agences = [];
$comptes = [];
$journaux = [];
$nom_utilisateur = $_SESSION['nom_utilisateur'] ?? 'SYSTEM'; // Fallback for nom_utilisateur

// --- Récupérer la liste des agences ---
try {
    $sql_agences = "SELECT CodeAgenceSCE, LibelleAgenceSCE FROM AGENCES_SCE ORDER BY LibelleAgenceSCE";
    $stmt_agences = $pdo->query($sql_agences);
    $agences = $stmt_agences->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $messageErreur .= "Erreur lors de la récupération des agences : " . $e->getMessage() . "<br>";
    error_log("Erreur SQL (ajouter.php - agences) : " . $e->getMessage());
}

// --- Récupérer la liste des comptes comptables ---
// These are still needed if your form uses them, even if not for generating entries here.
try {
    $sql_comptes = "SELECT ID_Compte, Numero_Compte, Nom_Compte FROM Comptes_compta ORDER BY Numero_Compte";
    $stmt_comptes = $pdo->query($sql_comptes);
    $comptes = $stmt_comptes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $messageErreur .= "Erreur lors de la récupération des comptes comptables : " . $e->getMessage() . "<br>";
    error_log("Erreur SQL (ajouter.php - comptes) : " . $e->getMessage());
}

// --- Récupérer la liste des journaux comptables ---
// Still retrieved as they might be part of the form, even if journal_cde isn't used for entry generation here.
try {
    $journaux = getJournaux($pdo);
    if (empty($journaux)) {
        $messageErreur .= "Erreur : Aucun journal comptable trouvé. Veuillez créer un journal comptable avant d'ajouter un emprunt.<br>";
    }
} catch (PDOException $e) {
    $messageErreur .= "Erreur lors de la récupération des journaux comptables : " . $e->getMessage() . "<br>";
    error_log("Erreur SQL (ajouter.php - journaux) : " . $e->getMessage());
}


// --- Traitement du formulaire si soumis ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupération et nettoyage des données du formulaire
    $banque = htmlspecialchars($_POST['banque'] ?? '');
    $date_souscription = $_POST['date_souscription'] ?? '';
    $agence = htmlspecialchars($_POST['agence'] ?? '');
    $devise = htmlspecialchars($_POST['devise'] ?? '');
    $numero_pret = htmlspecialchars($_POST['numero_pret'] ?? '');
    $type_pret = htmlspecialchars($_POST['type_pret'] ?? '');
    $client = htmlspecialchars($_POST['client'] ?? '');
    $montant_pret = floatval($_POST['montant_pret'] ?? 0);
    $source_financement = htmlspecialchars($_POST['source_financement'] ?? '');
    $taux_effectif_global = floatval($_POST['taux_effectif_global'] ?? 0);
    $montant_differe = floatval($_POST['montant_differe'] ?? 0);
    $type_plan = htmlspecialchars($_POST['type_plan'] ?? '');
    $echeance_fin_mois = isset($_POST['echeance_fin_mois']) ? true : false;
    $nombre_echeances = intval($_POST['nombre_echeances'] ?? 0);
    $gestion_differe = htmlspecialchars($_POST['gestion_differe'] ?? '');
    $nombre_jours_reels = isset($_POST['nombre_jours_reels']) ? true : false;
    $type_interet = htmlspecialchars($_POST['type_interet'] ?? '');
    $date_mise_en_place = $_POST['date_mise_en_place'] ?? '';
    $date_premiere_echeance = $_POST['date_premiere_echeance'] ?? '';
    $date_derniere_echeance = $_POST['date_derniere_echeance'] ?? '';
    $interet_sp_taux = floatval($_POST['interet_sp_taux'] ?? 0);
    $interet_sp_montant = floatval($_POST['interet_sp_montant'] ?? 0);
    $taxes = floatval($_POST['taxes'] ?? 0);
    $periode = htmlspecialchars($_POST['periode'] ?? '');
    $terme = htmlspecialchars($_POST['terme'] ?? '');
    $perception = htmlspecialchars($_POST['perception'] ?? '');
    $date_debut_amortissement = $_POST['date_debut_amortissement'] ?? '';
    $date_fin_amortissement = $_POST['date_fin_amortissement'] ?? '';
    $montant_initial_amort = floatval($_POST['montant_initial_amort'] ?? 0);
    $duree = intval($_POST['duree'] ?? 0);
    $type_amortissement = htmlspecialchars($_POST['type_amortissement'] ?? '');
    $nombre_mois_differe = intval($_POST['nombre_mois_differe'] ?? 0);

    // Rcupration des IDs des comptes comptables (still needed for the form's display)
    $compte_principal_id = intval($_POST['compte_principal'] ?? 0);
    $compte_interet_id = intval($_POST['compte_interet'] ?? 0);
    $compte_banque_id = intval($_POST['compte_banque'] ?? 0);
    $compte_taxes_frais_id = intval($_POST['compte_taxes_frais'] ?? 0);
    $journal_cde = intval($_POST['journal_cde'] ?? 0); // Still retrieved if the form includes it


    // --- Préparation des données pour la fonction d'ajout d'emprunt ---
    $emprunt_data = array(
        'Banque' => $banque,
        'Date_Souscription' => $date_souscription,
        'Agence' => $agence,
        'Devise' => $devise,
        'Numero_Pret' => $numero_pret,
        'Type_Pret' => $type_pret,
        'Client' => $client,
        'Montant_Pret' => $montant_pret,
        'Source_Financement' => $source_financement,
        'Taux_Effectif_Global' => $taux_effectif_global,
        'Montant_Differe' => $montant_differe,
        'Type_Plan' => $type_plan,
        'Echeance_Fin_Mois' => $echeance_fin_mois,
        'Nombre_Echeances' => $nombre_echeances,
        'Gestion_Differe' => $gestion_differe,
        'Nombre_Jours_Reels' => isset($_POST['nombre_jours_reels']) ? 1 : 0,
        'Type_Interet' => $type_interet,
        'Date_Mise_En_Place' => $date_mise_en_place,
        'Date_Premiere_Echeance' => $date_premiere_echeance,
        'Date_Derniere_Echeance' => $date_derniere_echeance,
        'Interet_SP_Taux' => $interet_sp_taux,
        'Interet_SP_Montant' => $interet_sp_montant,
        'Taxes' => $taxes,
        'Periode' => $periode,
        'Terme' => $terme,
        'Perception' => $perception,
        'Date_Debut_Amortissement' => $date_debut_amortissement,
        'Date_Fin_Amortissement' => $date_fin_amortissement,
        'Montant_Initial' => $montant_initial_amort,
        'Duree' => $duree,
        'Type_Amortissement' => $type_amortissement
    );

    // --- Appel de la fonction pour ajouter l'emprunt ---
    $idEmprunt = ajouterEmprunt($pdo, $emprunt_data);

    if ($idEmprunt === false) {
        $messageErreur .= "<div class='alert alert-danger'>Erreur lors de l'insertion de l'emprunt. Vérifiez les logs serveur pour plus de détails.</div>";
        error_log("Erreur Emprunt (ajouter.php) : Échec de l'ajout de l'emprunt.");
    } else {
        $messageSucces .= "<div class='alert alert-success'>Emprunt ajouté avec succès (ID: {$idEmprunt}) !</div>";

        // L'emprunt a été ajouté avec succès, maintenant générer le plan d'amortissement
        try {
            $planGenere = genererPlanAmortissement(
                $pdo,
                $idEmprunt,
                $emprunt_data['Montant_Initial'] ?? 0.0,
                $emprunt_data['Taux_Effectif_Global'] ?? 0.0,
                $emprunt_data['Nombre_Echeances'] ?? 0,
                $emprunt_data['Type_Amortissement'] ?? '',
                $emprunt_data['Date_Premiere_Echeance'] ?? '',
                $emprunt_data,
                (int)($emprunt_data['Gestion_Differe'] ?? 0),
                $emprunt_data['Echeance_Fin_Mois'] ?? false,
                $emprunt_data['Nombre_Jours_Reels'] ?? false,
                $emprunt_data['Date_Debut_Amortissement'] ?? null
            );

            if ($planGenere) {
                $messageSucces .= "<div class='alert alert-success'>Échéancier généré avec succès !</div>";
                // Optionally, redirect to a view page for the loan or a success message
                // header("Location: view_emprunt.php?id=" . $idEmprunt . "&success=" . urlencode($messageSucces));
                // exit;
            } else {
                $messageErreur .= "<div class='alert alert-warning'>La génération de l'échéancier a échoué. Vérifiez les logs serveur pour plus de détails.</div>";
                error_log("Erreur Amortissement (ajouter.php) : Échec de la génération de l'échéancier pour emprunt ID: {$idEmprunt}.");
            }
        } catch (Exception $e) {
            $messageErreur .= "<div class='alert alert-danger'>Exception lors de la génération de l'échéancier : " . $e->getMessage() . "</div>";
            error_log("Exception Amortissement (ajouter.php) : " . $e->getMessage());
        }
    }

    // Afficher les messages (succès ou erreur)
    if (!empty($messageErreur)) {
        echo '<div class="container mt-4">' . $messageErreur . '</div>';
    }
    if (!empty($messageSucces)) { // Display success message only if no errors
        echo '<div class="container mt-4">' . $messageSucces . '</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Nouvel Emprunt Bancaire</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-theme/0.1.0-beta.10/select2-bootstrap.min.css" rel="stylesheet">
	 <link rel="stylesheet" href="../../css/style.css">
	 <link rel="stylesheet" href="../../css/bootstrap.min.css">
	<link rel="stylesheet" href="../../css/tableau.css">
	 <link rel="stylesheet" href="../../css/select2.min.css">
	  <link rel="stylesheet" href="../../css/select2-bootstrap.min.css">
    <style>
        .grid-cols-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .form-panel {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .required-field::after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
	&nbsp;
        <h1>Ajouter un Nouvel Emprunt Bancaire</h1>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-panel">
                <div class="grid-cols-2">
                    <div class="form-group">
                        <label for="banque" class="required-field">Banque</label>
                        <select id="banque" name="banque" class="form-control" required>
                            <option value="">S&eacutelectionnez une banque</option>
                            <option value="afriland">AFRILAND FIRST BANK</option>
                            <option value="bicec">BICEC</option>
                            <option value="bgfibank">BGFIBank</option>
                            <option value="commercialbank">COMMERCIAL BANK</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="date_souscription" class="required-field">Date de Souscription</label>
                        <input type="date" id="date_souscription" name="date_souscription" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="agence" class="required-field">Agence</label>
                        <select id="agence" name="agence" class="form-control" required>
                            <option value="">-- S&eacutelectionner une agence --</option>
                            <?php foreach ($agences as $agence_data): ?>
                                <option value="<?php echo htmlspecialchars($agence_data['CodeAgenceSCE']); ?>">
                                    <?php echo htmlspecialchars($agence_data['LibelleAgenceSCE']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
					<div class="form-group" >
                        <label for="bgfiLimit" class="required-field">Montant Max. BGFIBank</label>
                        <input type="number" id="bgfiLimit" name="bgfi_limit" class="form-control" step="0.01" min="0">
                    </div>

                    <div class="form-group" >
                        <label for="bicecTegProportionnel" class="required-field">TEG Proportionnel (%)</label>
                        <input type="number" id="bicecTegProportionnel" name="bicec_teg_proportionnel" class="form-control" step="0.001" min="0">
                    </div>
                    <div class="form-group">
                        <label for="devise" class="required-field">Devise</label>
                        <input type="text" id="devise" name="devise" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="numero_pret" class="required-field">Num&eacutero de Pr&ecirct</label>
                        <input type="text" id="numero_pret" name="numero_pret" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="type_pret">Type de Pr&ecirct</label>
                        <input type="text" id="type_pret" name="type_pret" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="client">Client</label>
                        <input type="text" id="client" name="client" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="montant_pret" class="required-field">Montant du Pr&ecirct</label>
                        <input type="number" id="montant_pret" name="montant_pret" step="0.01" class="form-control" required>
                    </div>
				
                    <div class="form-group">
                        <label for="source_financement">Source de Financement</label>
                        <input type="text" id="source_financement" name="source_financement" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="taux_effectif_global" class="required-field">Taux Effectif Global (%)</label>
                        <input type="number" id="taux_effectif_global" name="taux_effectif_global" step="0.001" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="montant_differe">Montant Diff&eacuter&eacute</label>
                        <input type="number" id="montant_differe" name="montant_differe" step="0.01" value="0.00" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="montant_initial_amort" class="required-field">Montant Initial Amortissable</label>
                        <input type="number" id="montant_initial_amort" name="montant_initial_amort" step="0.01" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="type_plan">Type de Plan</label>
                        <input type="text" id="type_plan" name="type_plan" class="form-control">
                    </div>
                    <div class="form-group form-check">
                        <label class="form-check-label">
                            <input type="checkbox" id="echeance_fin_mois" name="echeance_fin_mois" class="form-check-input">&nbsp;&nbsp;&nbsp; &Eacutech&eacuteance Fin de Mois
                        </label>
                    </div>
                    <div class="form-group">
                        <label for="nombre_echeances" class="required-field">Nombre d'&eacutech&eacuteances</label>
                        <input type="number" id="nombre_echeances" name="nombre_echeances" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="nombre_mois_differe">Nombre de mois diff&eacuter&eacute (int&eacuter&ecirct seul)</label>
                        <input type="number" id="nombre_mois_differe" name="nombre_mois_differe" value="0" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="gestion_differe">Gestion Diff&eacuter&eacute (Texte)</label>
                        <input type="text" id="gestion_differe" name="gestion_differe" class="form-control">
                    </div>
                    <div class="form-group form-check">
                        <label class="form-check-label">
                            <input type="checkbox" id="nombre_jours_reels" name="nombre_jours_reels" class="form-check-input"> &nbsp;&nbsp;&nbsp; Calcul Jours R&eacuteels (Optionnel)
                        </label>
                    </div>
                    <div class="form-group">
                        <label for="type_interet">Type d'Int&eacuter&ecirct</label>
                        <input type="text" id="type_interet" name="type_interet" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="date_mise_en_place" class="required-field">Date de Mise En Place</label>
                        <input type="date" id="date_mise_en_place" name="date_mise_en_place" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="date_premiere_echeance" class="required-field">Date Premi&egravere &eacutech&eacuteance</label>
                        <input type="date" id="date_premiere_echeance" name="date_premiere_echeance" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="date_derniere_echeance" class="required-field">Date Derni&egravere &eacutech&eacuteance</label>
                        <input type="date" id="date_derniere_echeance" name="date_derniere_echeance" class="form-control" required readonly>
                    </div>
                    <div class="form-group">
                        <label for="interet_sp_taux">Int&eacuter&ecirct SP Taux (%)</label>
                        <input type="number" id="interet_sp_taux" name="interet_sp_taux" step="0.001" value="0.000" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="interet_sp_montant">Int&eacuter&ecirct SP Montant</label>
                        <input type="number" id="interet_sp_montant" name="interet_sp_montant" step="0.01" value="0.00" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="taxes">Taxes (%)</label>
                        <input type="number" id="taxes" name="taxes" step="0.01" value="19.25" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="comm_engagement">Comm. Engagement</label>
                        <input type="number" id="comm_engagement" name="comm_engagement" step="0.01" value="0.00" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="comm_deblocage">Comm. D&eacuteblocage</label>
                        <input type="number" id="comm_deblocage" name="comm_deblocage" step="0.01" value="0.00" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="taxe_comm_e">Taxe Comm. E</label>
                        <input type="number" id="taxe_comm_e" name="taxe_comm_e" step="0.01" value="0.00" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="taxe_comm_d">Taxe Comm. D</label>
                        <input type="number" id="taxe_comm_d" name="taxe_comm_d" step="0.01" value="0.00" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="frais_etude">Frais &eacutetude</label>
                        <input type="number" id="frais_etude" name="frais_etude" step="0.01" value="0.00" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="taxe_frais_etude">Taxe Frais &eacutetude</label>
                        <input type="number" id="taxe_frais_etude" name="taxe_frais_etude" step="0.01" value="0.00" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="taxe_capital">Taxe Capital</label>
                        <input type="number" id="taxe_capital" name="taxe_capital" step="0.01" value="0.00" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="periode">P&eacuteriode</label>
                        <input type="text" id="periode" name="periode" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="terme">Terme</label>
                        <input type="text" id="terme" name="terme" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="perception">Perception</label>
                        <input type="text" id="perception" name="perception" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="date_debut_amortissement" class="required-field">Date D&eacutebut Amortissement</label>
                        <input type="date" id="date_debut_amortissement" name="date_debut_amortissement" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="date_fin_amortissement" class="required-field">Date Fin Amortissement</label>
                        <input type="date" id="date_fin_amortissement" name="date_fin_amortissement" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="duree" class="required-field">Dur&eacutee (en mois)</label>
                        <input type="number" id="duree" name="duree" class="form-control" required>
                    </div>
                 <div class="form-group">
    <label for="type_amortissement" class="required-field">Type d'Amortissement</label>
    <select id="type_amortissement" name="type_amortissement" class="form-control" required>
        <option value="">-- S&eacutelectionner un type d'amortissement --</option>
        <option value="annuite">Annuit&eacute Constante</option>
        <option value="constant">Amortissement Constant (Lin&eacuteaire)</option>
        <option value="degressif">Amortissement D&eacutegressif</option> <option value="in fine">In Fine</option>
        <option value="differe">Diff&eacuter&eacute (Int&eacuter&ecirct seul puis Annuit&eacute)</option>
    </select>
</div>
                </div>

                <hr>

                <h3>Comptabilit&eacute</h3>
                <div class="grid-cols-2">
                    <div class="form-group">
                        <label for="compte_principal">Compte Principal de l'Emprunt (Cr&eacutedit initial / D&eacutebit amortissement)</label>
                        <select class="form-control" id="compte_principal" name="compte_principal" required>
                            <option value="">-- S&eacutelectionner un compte --</option>
                            <?php foreach ($comptes as $compte): ?>
                                <option value="<?php echo htmlspecialchars($compte['ID_Compte']); ?>">
                                    <?php echo htmlspecialchars($compte['Numero_Compte'] . ' - ' . $compte['Nom_Compte']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="compte_interet">Compte Charges d'Int&eacuter&ecircts (D&eacutebit)</label>
                        <select class="form-control" id="compte_interet" name="compte_interet" required>
                            <option value="">-- S&eacutelectionner un compte --</option>
                            <?php foreach ($comptes as $compte): ?>
                                <option value="<?php echo htmlspecialchars($compte['ID_Compte']); ?>">
                                    <?php echo htmlspecialchars($compte['Numero_Compte'] . ' - ' . $compte['Nom_Compte']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="compte_banque">Compte Banque (Cr&eacutedit)</label>
                        <select class="form-control" id="compte_banque" name="compte_banque" required>
                            <option value="">-- S&eacutelectionner un compte --</option>
                            <?php foreach ($comptes as $compte): ?>
                                <option value="<?php echo htmlspecialchars($compte['ID_Compte']); ?>">
                                    <?php echo htmlspecialchars($compte['Numero_Compte'] . ' - ' . $compte['Nom_Compte']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="compte_taxes_frais">Compte Taxes/Frais (D&eacutebit) (Optionnel)</label>
                        <select class="form-control" id="compte_taxes_frais" name="compte_taxes_frais">
                            <option value="">-- Aucun / S&eacutelectionner un compte --</option>
                            <?php foreach ($comptes as $compte): ?>
                                <option value="<?php echo htmlspecialchars($compte['ID_Compte']); ?>">
                                    <?php echo htmlspecialchars($compte['Numero_Compte'] . ' - ' . $compte['Nom_Compte']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="journal_cde" class="required-field">Journal Comptable</label>
                        <select id="journal_cde" name="journal_cde" class="form-control" required>
                            <option value="" disabled selected>S&eacutelectionner un journal</option>
                            <?php
                            try {
                                $sql_jal = "SELECT Cde, Lib FROM JAL ORDER BY Cde ASC";
                                $stmt_jal = $pdo->prepare($sql_jal);
                                $stmt_jal->execute();
                                $journaux = $stmt_jal->fetchAll(PDO::FETCH_ASSOC);

                                foreach ($journaux as $journal) {
                                    echo '<option value="' . $journal['Cde'] . '">' . $journal['Cde'] . ' - ' . htmlspecialchars($journal['Lib']) . '</option>';
                                }
                            } catch (PDOException $e) {
                                echo "<div class='alert alert-danger'>Erreur de base de donn�es lors du chargement des journaux : " . $e->getMessage() . "</div>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary mt-3">Ajouter l'Emprunt et G&eacuten&eacuterer l'&eacutech&eacuteancier</button>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
	<script src="js/jquery-3.7.1.js"></script>
    <script src="../js/tableau_dynamique.js"></script>
	 <script src="../js/bootstrap.min.js"></script>
	  <script src="../js/select2.min.jss"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Select2 for enhanced dropdowns
        $('select').select2({
            theme: "bootstrap"
        });

        // Get references to DOM elements
        const banqueSelect = document.getElementById('banque');
        const montantPretInput = document.getElementById('montant_pret');
        const interetSpTauxInput = document.getElementById('interet_sp_taux');
        const interetSpMontantInput = document.getElementById('interet_sp_montant');
        const montantDiffereInput = document.getElementById('montant_differe');
        const montantInitialAmortInput = document.getElementById('montant_initial_amort');
        const datePremiereEcheanceInput = document.getElementById('date_premiere_echeance');
        const nombreEcheancesInput = document.getElementById('nombre_echeances');
        const dateDerniereEcheanceInput = document.getElementById('date_derniere_echeance');

        // BGFIBank specific elements
        const bgfiLimitGroup = document.getElementById('bgfiLimitGroup');
        const bgfiLimitInput = document.getElementById('bgfiLimit');

        // BICEC specific elements
        const bicecTegProportionnelGroup = document.getElementById('bicecTegProportionnelGroup');
        const bicecTegProportionnelInput = document.getElementById('bicecTegProportionnel');

        // --- Function to calculate "Int�r�t SP Montant" ---
        function calculateInteretSpMontant() {
            const montantPret = parseFloat(montantPretInput.value) || 0;
            const interetSpTaux = parseFloat(interetSpTauxInput.value) || 0;
            let calculatedMontant = (montantPret * interetSpTaux) / 100;
            interetSpMontantInput.value = calculatedMontant.toFixed(2);
            updateMontantInitialAmort();
        }

        // --- Function to update "Montant Initial Amortissable" ---
        function updateMontantInitialAmort() {
            const montantPret = parseFloat(montantPretInput.value) || 0;
            const montantDiffere = parseFloat(montantDiffereInput.value) || 0;
            const selectedBanque = banqueSelect.value;
            const interetSpMontant = parseFloat(interetSpMontantInput.value) || 0;
            const bicecTegProportionnel = parseFloat(bicecTegProportionnelInput.value) || 0;

            let finalMontantAmort = montantPret;

            // Subtract Montant Diff�r�
            finalMontantAmort -= montantDiffere;

            // Apply bank-specific deductions
            if (selectedBanque === 'afriland') {
                const afrilandDeductionRate = -0.563008 / 100;
                const deductionAfriland = montantPret * afrilandDeductionRate;
                finalMontantAmort += deductionAfriland;
            }
			
			if (selectedBanque === 'commercialbank') {
                const afrilandDeductionRate = 2.4517006856 / 100;
                const deductionAfriland = montantPret * afrilandDeductionRate;
                finalMontantAmort += deductionAfriland;
            }
			
            // Use lowercase 'bicec' for consistency with recommended HTML value
            else if (selectedBanque === 'bicec') {
             // Fixed BICEC deduction
    const bicecDeductionRateFixed = 10.498305 / 100; // Use a dot for decimals
    const deductionBicecFixed = montantPret * bicecDeductionRateFixed;
    finalMontantAmort += deductionBicecFixed;

    // TEG proportional BICEC deduction (if bicecTegProportionnel is relevant)
    const bicecDeductionProportional = finalMontantAmort * (bicecTegProportionnel / 100);
    finalMontantAmort -= bicecDeductionProportional;
            }
			
			

            // Decide if 'Int�r�t SP Montant' should reduce the amortizable principal.
            // This line was commented out in your original code. Uncomment if it should be a deduction.
            // finalMontantAmort -= interetSpMontant;

            montantInitialAmortInput.value = finalMontantAmort.toFixed(2);
        }

        // --- Function to calculate "Date Derni�re �ch�ance" ---
        function calculateDateDerniereEcheance() {
            const premiereEcheanceValue = datePremiereEcheanceInput.value;
            const nombreEcheancesValue = parseInt(nombreEcheancesInput.value, 10);

            if (premiereEcheanceValue && !isNaN(nombreEcheancesValue) && nombreEcheancesValue > 0) {
                const date = new Date(premiereEcheanceValue);
                // We subtract 1 from nombreEcheancesValue because the first repayment is counted as the "1st" month.
                // So, if you have 12 repayments, the last one is 11 months *after* the first.
                date.setMonth(date.getMonth() + nombreEcheancesValue - 1);

                const year = date.getFullYear();
                const month = ('0' + (date.getMonth() + 1)).slice(-2);
                const day = ('0' + date.getDate()).slice(-2);

                dateDerniereEcheanceInput.value = `${year}-${month}-${day}`;
            } else {
                dateDerniereEcheanceInput.value = '';
            }
        }

        // --- Event Listeners ---
        banqueSelect.addEventListener('change', function() {
            // Manage BGFIBank visibility (using lowercase 'bgfibank' for consistency)
            if (this.value === 'bgfibank') {
                bgfiLimitGroup.style.display = 'block';
                bgfiLimitInput.setAttribute('required', 'required');
            } else {
                bgfiLimitGroup.style.display = 'none';
                bgfiLimitInput.removeAttribute('required');
                bgfiLimitInput.value = '';
            }

            // Manage BICEC TEG Proportionnel visibility (using lowercase 'bicec' for consistency)
            if (this.value === 'bicec') {
                bicecTegProportionnelGroup.style.display = 'block';
                bicecTegProportionnelInput.setAttribute('required', 'required');
            } else {
                bicecTegProportionnelGroup.style.display = 'none';
                bicecTegProportionnelInput.removeAttribute('required');
                bicecTegProportionnelInput.value = '';
            }

            updateMontantInitialAmort(); // Recalculate when bank changes
        });

        // Listeners for inputs that directly affect Montant Initial Amortissable
        montantPretInput.addEventListener('input', function() {
            calculateInteretSpMontant();
            updateMontantInitialAmort();
        });

        montantDiffereInput.addEventListener('input', updateMontantInitialAmort);
        interetSpTauxInput.addEventListener('input', calculateInteretSpMontant);

        // Listen for changes in the BICEC TEG Proportionnel input
        bicecTegProportionnelInput.addEventListener('input', updateMontantInitialAmort);

        // Listeners for date calculations
        datePremiereEcheanceInput.addEventListener('change', calculateDateDerniereEcheance);
        nombreEcheancesInput.addEventListener('input', calculateDateDerniereEcheance);

        // Initialize state on page load
        // This block needs to be after all function definitions.
        // It ensures fields are correctly displayed/hidden if a bank is pre-selected
        // (e.g., if the form is loaded after a submission error).

        // Initialize BGFIBank visibility
        if (banqueSelect.value === 'bgfibank') {
            bgfiLimitGroup.style.display = 'visible';
            bgfiLimitInput.setAttribute('required', 'required');
        } else {
            bgfiLimitGroup.style.display = 'none';
            bgfiLimitInput.removeAttribute('required');
        }

        // Initialize BICEC visibility
        if (banqueSelect.value === 'bicec') {
            bicecTegProportionnelGroup.style.display = 'block';
            bicecTegProportionnelInput.setAttribute('required', 'required');
        } else {
            bicecTegProportionnelGroup.style.display = 'none';
            bicecTegProportionnelInput.removeAttribute('required');
        }

        // Initial calculations on page load
        calculateInteretSpMontant();
        updateMontantInitialAmort();
        calculateDateDerniereEcheance();
    });
</script>
</body>
</html>

<?php
require_once('../../templates/footer.php');
?>