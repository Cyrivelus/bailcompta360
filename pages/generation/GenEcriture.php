<?php
// pages/generation/GenEcriture.php
// Script pour exporter toutes les écritures comptables non encore exportées au format TXT
// et afficher un tableau de toutes les écritures avec leur statut d'exportation.

// Démarrer la mise en mémoire tampon de sortie (Output Buffering)
// Il est préférable de démarrer ob_start() avant tout envoi de contenu,
// y compris les entêtes, ce qui est essentiel pour les redirections et les téléchargements de fichiers.
ob_start();

// Démarrer la session PHP
session_start();

// Configuration d'affichage des erreurs
ini_set('display_errors', 0); // En production, mettez à 0 pour ne pas afficher les erreurs à l'utilisateur
ini_set('display_startup_errors', 0); // Idem
error_reporting(E_ALL); // Pour le développement, E_ALL est recommandé
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// Paramètres de la page
$titre = 'Exporter et Lister les Écritures Comptables';
$current_page = basename(__FILE__);

// Variables pour les messages
$messageSucces = null;
$messageInfo = null;
$messageErreur = null;

// Inclure le fichier de connexion à la base de données
// Assurez-vous que ce fichier initialise la variable $pdo (une instance de PDO)
require_once('../../fonctions/database.php');

// Vérification et initialisation de la connexion PDO
// Le bloc try/catch ici est pour gérer l'échec de la connexion PDO elle-même.
try {
    // Si $pdo n'est pas déjà défini ou n'est pas une instance de PDO par database.php,
    // tentez de la créer ici comme fallback.
 

    // Vérification finale que $pdo est bien une instance de PDO
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception("La connexion PDO n'a pas pu être initialisée.");
    }

    // --- Traitement des messages via l'URL ---
    // Les messages sont décodés et échappés pour l'affichage HTML
    if (isset($_GET['success']) && $_GET['success'] !== '') {
        $messageSucces = "<div class='alert alert-success'>" . htmlspecialchars(urldecode($_GET['success'])) . "</div>";
    }
    if (isset($_GET['info']) && $_GET['info'] !== '') {
        $messageInfo = "<div class='alert alert-info'>" . htmlspecialchars(urldecode($_GET['info'])) . "</div>";
    }
    if (isset($_GET['error']) && $_GET['error'] !== '') {
        $messageErreur = "<div class='alert alert-danger'>" . urldecode($_GET['error']) . "</div>"; // Removed htmlspecialchars here to allow HTML in error messages
    }

    // --- Étape 1: Traitement de la demande d'export TXT ---
    if (isset($_GET['action']) && $_GET['action'] === 'export_all') {
        // Requête SQL pour récupérer les écritures
        $sql = "SELECT
                    e.ID_Ecriture,
                    e.Date_Saisie,
                    cc.Numero_Compte,
                    le.Libelle_Ligne,
                    le.Montant AS Montant_Ligne,
                    le.Sens AS Sens_Ligne,
                    e.Numero_Piece,
                    e.NomUtilisateur,
                    e.Mois,
                    e.Description,
                    e.NumeroAgenceSCE,
                    e.libelle2,
                    e.is_exported,
                    e.exported_at
                FROM
                    Ecritures e
                JOIN Lignes_Ecritures le ON e.ID_Ecriture = le.ID_Ecriture
                JOIN Comptes_compta cc ON le.ID_Compte = cc.ID_Compte
                WHERE (e.is_exported = 0 OR e.is_exported IS NULL)
                AND cc.Numero_Compte IS NOT NULL -- Maintained check for non-NULL account number
                ORDER BY e.ID_Ecriture ASC, le.ID_Ligne ASC";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $lignesEcritures = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($lignesEcritures)) {
                // Si aucune écriture n'est trouvée, rediriger avec un message d'information
                ob_end_clean(); // Nettoyer le buffer avant la redirection
                header('Location: ' . $current_page . '?info=' . urlencode('Aucune nouvelle écriture comptable à exporter n\'a été trouvée.'));
                exit();
            }

            // Pour stocker les écritures regroupées avec leurs lignes et leurs totaux
            $groupedEcritures = [];
            $invalid_entries_details = []; // Pour stocker les détails des lignes invalides

            foreach ($lignesEcritures as $row) {
                $ecriture_id = $row['ID_Ecriture'] ?? 'N/A';
                $montant_ligne_raw = $row['Montant_Ligne'] ?? null;
                $sens_ligne_val = $row['Sens_Ligne'] ?? '';
                $numero_compte_val = $row['Numero_Compte'] ?? '';

                // --- Robust numeric validation for Montant_Ligne ---
               if (!is_numeric($montant_ligne_raw) || $montant_ligne_raw === null || (float)$montant_ligne_raw < 0) { // Changed <= 0 to < 0
                    $invalid_entries_details[] = "Ligne ID_Ecriture: " . $ecriture_id . ": Montant invalide '" . htmlspecialchars($montant_ligne_raw ?? 'NULL') . "' (doit être numérique et positif).";
                    continue;
                }
                $montant_ligne_val = (float)$montant_ligne_raw;

                // --- Validation for Numero_Compte ---
                if (empty($numero_compte_val)) {
                    $invalid_entries_details[] = "Ligne ID_Ecriture: " . $ecriture_id . ": Numéro de compte manquant ou vide.";
                    continue;
                }

                // --- Validation for Sens ---
                $sens = mb_strtoupper(mb_substr($sens_ligne_val, 0, 1));
                if ($sens !== 'D' && $sens !== 'C') {
                    $invalid_entries_details[] = "Ligne ID_Ecriture: " . $ecriture_id . ": Sens invalide '" . htmlspecialchars($sens_ligne_val) . "' (doit être 'D' ou 'C').";
                    continue;
                }

                // Initialiser l'entrée pour cette écriture si elle n'existe pas
                if (!isset($groupedEcritures[$ecriture_id])) {
                    $groupedEcritures[$ecriture_id] = [
                        'details' => $row, // Garder les détails de l'écriture principale
                        'lignes' => [],
                        'totalDebit' => 0,
                        'totalCredit' => 0
                    ];
                }

                // Ajouter la ligne aux détails de l'écriture
                $groupedEcritures[$ecriture_id]['lignes'][] = [
                    'Numero_Compte' => $numero_compte_val,
                    'Libelle_Ligne' => $row['Libelle_Ligne'],
                    'Montant_Ligne' => $montant_ligne_val,
                    'Sens_Ligne' => $sens,
                ];

                // Accumuler les totaux débit/crédit pour cette écriture
                if ($sens === 'D') {
                    $groupedEcritures[$ecriture_id]['totalDebit'] += $montant_ligne_val;
                } else {
                    $groupedEcritures[$ecriture_id]['totalCredit'] += $montant_ligne_val;
                }
            }

            // Si des lignes invalides ont été trouvées, ne pas générer le fichier et rediriger avec un message d'erreur.
            if (!empty($invalid_entries_details)) {
                ob_end_clean();
                $error_message = 'Erreur lors de la génération du fichier TXT : Certaines lignes d\'écriture contiennent des données invalides.<br><br>Détails des lignes invalides:<ul><li>' . implode('</li><li>', $invalid_entries_details) . '</li></ul>';
                header('Location: ' . $current_page . '?error=' . urlencode($error_message));
                exit();
            }

            // Si aucune écriture n'a pu être traitée après validation
            if (empty($groupedEcritures)) {
                ob_end_clean();
                header('Location: ' . $current_page . '?info=' . urlencode('Aucune écriture valide à exporter après les vérifications.'));
                exit();
            }

            // --- Génération du fichier TXT ---
            $filename = 'toutes_nouvelles_ecritures_' . date('Ymd_His') . '.txt';
            $lines_to_write = [];
            $exported_ecriture_ids = [];

            foreach ($groupedEcritures as $ecriture_id => $data) {
                $ecriture_details = $data['details'];
                $lignes = $data['lignes'];
                $totalDebit = $data['totalDebit'];
                $totalCredit = $data['totalCredit'];

                // Vérifier si l'écriture est équilibrée.
                // Utiliser une petite tolérance pour les comparaisons de flottants
                if (abs($totalDebit - $totalCredit) > 0.001) {
                    $equilibre_value = abs($totalDebit - $totalCredit);
                    $sens_equilibre = ($totalDebit > $totalCredit) ? 'C' : 'D'; // Si D > C, ajouter au C pour équilibrer, sinon ajouter au D

                    // Définir un compte d'équilibrage par défaut
                    // **IMPORTANT**: Choisissez un numéro de compte d'équilibrage approprié dans votre plan comptable.
                    // Par exemple, un compte de "Suspense" ou un compte technique.
                    $compte_equilibrage = '749600000000'; // Exemple : Compte d'attente ou suspense
                    $libelle_equilibrage = 'EQUILIBRAGE AUTOMATIQUE';

                    // Formatage du montant pour la ligne d'équilibrage
                    $montant_equilibre_formatted = number_format($equilibre_value, 2, '', ''); // Ex: 1234.56 devient 123456
                    $montant_equilibre_padded = mb_substr(str_pad($montant_equilibre_formatted, 10, ' ', STR_PAD_LEFT), 0, 10);

                    // Initialiser les colonnes débit/crédit pour la ligne d'équilibrage
                    $debit_balance_column = str_pad('', 10, ' ', STR_PAD_RIGHT);
                    $credit_balance_column = str_pad('', 10, ' ', STR_PAD_RIGHT);

                    if ($sens_equilibre === 'D') {
                        $debit_balance_column = $montant_equilibre_padded;
                    } else {
                        $credit_balance_column = $montant_equilibre_padded;
                    }

                    // Récupérer les informations nécessaires pour la ligne d'équilibrage depuis l'écriture principale
                    $agence_val_original = $ecriture_details['NumeroAgenceSCE'] ?? '';
                    // Apply the transformation for Yaoundé/Yao to "009"
                    $agence_val_equilibre = str_ireplace(['Yaoundé', 'Yao'], '009', $agence_val_original);


                    $date_saisie_val = $ecriture_details['Date_Saisie'] ?? date('Y-m-d H:i:s'); // Fallback à la date actuelle si manquante

                    $agence = mb_substr(str_pad($agence_val_equilibre, 10, ' ', STR_PAD_RIGHT), 0, 10);
                    $agence2 = mb_substr(str_pad($agence_val_equilibre, 3, ' ', STR_PAD_RIGHT), 0, 3);
                    $numeroCompteEquilibre = mb_substr(str_pad($compte_equilibrage, 12, ' ', STR_PAD_RIGHT), 0, 12);
                    $libelle_equilibre_formatted = mb_substr(str_pad($libelle_equilibrage, 30, ' ', STR_PAD_RIGHT), 0, 30);

                    $datePieceEquilibre = '';
                    try {
                        $dateTimeObj = new DateTime($date_saisie_val);
                        $datePieceEquilibre = $dateTimeObj->format('Ymd');
                    } catch (Exception $e) {
                        error_log("Date invalide pour ligne équilibrage ID_Ecriture " . $ecriture_id . ": " . $date_saisie_val . " - " . $e->getMessage());
                        $datePieceEquilibre = date('Ymd'); // Fallback à la date du jour
                    }
                    $dateFormattedEquilibre = mb_substr(str_pad($datePieceEquilibre, 8, ' ', STR_PAD_RIGHT), 0, 8);


                    $line_equilibre = $agence . ';' .
                                            $agence2 . ';' .
                                            $numeroCompteEquilibre . ';' .
                                            $debit_balance_column . ';' .
                                            $credit_balance_column . ';' .
                                            $sens_equilibre . ';' .
                                            $montant_equilibre_padded . ';' .
                                            $libelle_equilibre_formatted . ';' .
                                            $dateFormattedEquilibre . ';' .
                                            ';'; // Column 10 (Empty with double separator)

                    // Ajouter la ligne d'équilibrage à la liste des lignes de l'écriture courante
                    // pour s'assurer qu'elle est traitée avec les autres lignes de cette écriture.
                    // Note: Il est crucial que cette ligne soit générée AVEC les autres lignes de l'écriture
                    // pour maintenir la cohérence dans le fichier TXT exporté.
                    $lignes[] = [
                        'Numero_Compte' => $compte_equilibrage,
                        'Libelle_Ligne' => $libelle_equilibrage,
                        'Montant_Ligne' => $equilibre_value,
                        'Sens_Ligne' => $sens_equilibre,
                        'is_balancing_line' => true // Marqueur pour identification si besoin
                    ];
                }

                // Ajouter toutes les lignes (originales + équilibrage si existante) pour cette écriture au tableau final
                foreach ($lignes as $ligne_detail) {
                    $agence_val_original = $ecriture_details['NumeroAgenceSCE'] ?? '';
                    // Apply the transformation for Yaoundé/Yao to "009"
                    $agence_val = str_ireplace(['Yaoundé', 'Yao'], '009', $agence_val_original);

                    $libelle_ligne_val = $ligne_detail['Libelle_Ligne'] ?? '';
                    $date_saisie_val = $ecriture_details['Date_Saisie'] ?? ''; // Utiliser la date de l'écriture principale
                    $numero_compte_val = $ligne_detail['Numero_Compte'] ?? '';
                    $sens_ligne_val = $ligne_detail['Sens_Ligne'] ?? '';
                    $montant_ligne_val = $ligne_detail['Montant_Ligne'] ?? 0;

                    $agence = mb_substr(str_pad($agence_val, 10, ' ', STR_PAD_RIGHT), 0, 10);
                    $agence2 = mb_substr(str_pad($agence_val, 3, ' ', STR_PAD_RIGHT), 0, 3);
                    $numeroCompte = mb_substr(str_pad($numero_compte_val, 12, ' ', STR_PAD_RIGHT), 0, 12);

                    $montant = number_format($montant_ligne_val, 2, '', '');
                    $montantFormatted = mb_substr(str_pad($montant, 10, ' ', STR_PAD_LEFT), 0, 10);

                    $sens = mb_strtoupper(mb_substr($sens_ligne_val, 0, 1));

                    $libelle_ligne = mb_strtoupper(ltrim(rtrim((string)$libelle_ligne_val)));
                    $libelle_ligne_formatted = mb_substr(str_pad($libelle_ligne, 30, ' ', STR_PAD_RIGHT), 0, 30);

                    $datePiece = '';
                    if (!empty($date_saisie_val)) {
                        try {
                            $dateTimeObj = new DateTime($date_saisie_val);
                            $datePiece = $dateTimeObj->format('Ymd');
                        } catch (Exception $e) {
                            error_log("Date invalide pour ID_Ecriture " . $ecriture_id . ": " . $date_saisie_val . " - " . $e->getMessage());
                            $datePiece = date('Ymd'); // Fallback à la date du jour
                        }
                    }
                    $dateFormatted = mb_substr(str_pad($datePiece, 8, ' ', STR_PAD_RIGHT), 0, 8);

                    $debit_column = str_pad('', 10, ' ', STR_PAD_RIGHT);
                    $credit_column = str_pad('', 10, ' ', STR_PAD_RIGHT);

                    if ($sens === 'D') {
                        $debit_column = $montantFormatted;
                    } elseif ($sens === 'C') {
                        $credit_column = $montantFormatted;
                    }

                    $line = $agence . ';' .
                                $agence2 . ';' .
                                $numeroCompte . ';' .
                                $debit_column . ';' .
                                $credit_column . ';' .
                                $sens . ';' .
                                $montantFormatted . ';' .
                                $libelle_ligne_formatted . ';' .
                                $dateFormatted . ';' .
                                ';';

                    $lines_to_write[] = $line;
                }
                // Ajouter l'ID de l'écriture à la liste des IDs exportés après traitement complet
                $exported_ecriture_ids[] = $ecriture_id;
            }

            // Si nous arrivons ici, toutes les données sont valides pour l'exportation.
            // On envoie les en-têtes et le contenu du fichier.
            ob_end_clean(); // Important: Nettoyer le buffer avant d'envoyer les en-têtes de fichier
            header('Content-Type: text/plain; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output_file = fopen('php://output', 'w');
            fwrite($output_file, "\xEF\xBB\xBF"); // BOM UTF-8

            foreach ($lines_to_write as $line) {
                fwrite($output_file, $line . "\n");
            }
            fclose($output_file);

            // Marquer que de nouvelles écritures ont été exportées pour activer le bouton de confirmation
            $_SESSION['new_export_done'] = true;

            exit();

        } catch (PDOException $e) {
            ob_end_clean();
            $messageErreur = 'Erreur lors de la récupération des écritures pour export : ' . htmlspecialchars($e->getMessage());
            error_log("Erreur (export query) : " . $e->getMessage());
            header('Location: ' . $current_page . '?error=' . urlencode($messageErreur));
            exit();
        } catch (Exception $e) {
            ob_end_clean();
            $messageErreur = 'Erreur lors de la génération du fichier TXT : ' . htmlspecialchars($e->getMessage());
            error_log("Erreur (export processing) : " . $e->getMessage());
            header('Location: ' . $current_page . '?error=' . urlencode($messageErreur));
            exit();
        }
    }

    // --- Étape 2: Traitement de la demande de mise à jour du statut (bouton "Confirmer l'exportation") ---
    if (isset($_GET['action']) && $_GET['action'] === 'confirm_export') {
        // Récupérer les IDs des écritures non exportées pour les marquer
// Récupérer les IDs des écritures non exportées pour les marquer, toutes classes confondues
$sql_unexported_ids = "SELECT DISTINCT e.ID_Ecriture
                               FROM Ecritures e
                               JOIN Lignes_Ecritures le ON e.ID_Ecriture = le.ID_Ecriture
                               JOIN Comptes_compta cc ON le.ID_Compte = cc.ID_Compte
                               WHERE (e.is_exported = 0 OR e.is_exported IS NULL)
                               AND cc.Numero_Compte IS NOT NULL"; // La condition de classe de compte a été supprimée
        $stmt_unexported_ids = $pdo->prepare($sql_unexported_ids);
        $stmt_unexported_ids->execute();
        $ids_to_update = array_unique(array_column($stmt_unexported_ids->fetchAll(PDO::FETCH_ASSOC), 'ID_Ecriture'));


        if (!empty($ids_to_update)) {
            $pdo->beginTransaction(); // Démarrer une transaction
            try {
                $batchSize = 2000; // Taille du lot pour éviter de dépasser les limites de paramètres
                $batches = array_chunk($ids_to_update, $batchSize);

                foreach ($batches as $batch) {
                    $placeholders = implode(',', array_fill(0, count($batch), '?'));
                    // Utiliser GETDATE() pour SQL Server pour la date actuelle
                    $update_sql = "UPDATE Ecritures SET is_exported = 1, exported_at = GETDATE() WHERE ID_Ecriture IN ($placeholders)";
                    $stmt_update = $pdo->prepare($update_sql);
                    $stmt_update->execute($batch);
                }

                $pdo->commit(); // Commiter la transaction si tout se passe bien

                // Réinitialiser le drapeau de session après confirmation de l'exportation
                unset($_SESSION['new_export_done']);

                $messageSucces = "Exportation de " . count($ids_to_update) . " écritures confirmée et marquées comme exportées.";
            } catch (PDOException $e) {
                $pdo->rollBack(); // Annuler la transaction en cas d'erreur
                $messageErreur = "Une erreur est survenue lors de la mise à jour du statut d'exportation : " . htmlspecialchars($e->getMessage());
                error_log("ERREUR BDD lors de la mise à jour du statut : " . $e->getMessage());
            }
        } else {
            $messageInfo = "Aucune écriture non exportée à confirmer.";
        }
        ob_end_clean(); // Nettoyer le buffer avant la redirection finale
        header('Location: ' . $current_page . '?success=' . urlencode($messageSucces ?? '') . '&error=' . urlencode($messageErreur ?? '') . '&info=' . urlencode($messageInfo ?? ''));
        exit();
    }

    // --- Récupérer toutes les écritures pour l'affichage du tableau (limité aux 1000 plus récentes) ---
$allEcritures = [];
try {
    // La condition de classe de compte a été supprimée du WHERE EXISTS
    $sql_all_ecritures = "SELECT
                                ID_Ecriture,
                                Date_Saisie,
                                Description,
                                Montant_Total,
                                ID_Journal,
                                Cde,
                                NumeroAgenceSCE,
                                libelle2,
                                NomUtilisateur,
                                Mois,
                                Numero_Piece,
                                is_exported,
                                exported_at
                            FROM Ecritures e -- Removed [BD_AD_SCE].[dbo].
                            WHERE EXISTS (SELECT 1 FROM Lignes_Ecritures le JOIN Comptes_compta cc ON le.ID_Compte = cc.ID_Compte WHERE le.ID_Ecriture = e.ID_Ecriture AND cc.Numero_Compte IS NOT NULL)
                            ORDER BY ID_Ecriture DESC
                            LIMIT 1000"; // Tri par ID_Ecriture DESC pour voir les plus récentes
    $stmt_all_ecritures = $pdo->query($sql_all_ecritures);
    $allEcritures = $stmt_all_ecritures->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $messageErreur = 'Erreur lors du chargement de la liste des écritures : ' . htmlspecialchars($e->getMessage());
    error_log("Erreur (chargement liste) : " . $e->getMessage());
}

    // Vérifier s'il y a des écritures non exportées pour activer le bouton de confirmation
    $unexportedCount = 0;
    try {
        $stmt_count_unexported = $pdo->query("SELECT COUNT(DISTINCT e.ID_Ecriture)
                                                 FROM Ecritures e
                                                 JOIN Lignes_Ecritures le ON e.ID_Ecriture = le.ID_Ecriture
                                                 JOIN Comptes_compta cc ON le.ID_Compte = cc.ID_Compte
                                                 WHERE (e.is_exported = 0 OR e.is_exported IS NULL)
                                                 AND cc.Numero_Compte IS NOT NULL"); // Class restriction removed
        $unexportedCount = $stmt_count_unexported->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erreur lors du comptage des écritures non exportées: " . $e->getMessage());
    }
    // Le bouton de confirmation est actif s'il y a des écritures non exportées
    // OU si une nouvelle exportation vient d'être effectuée (via la session)
    $confirmButtonActive = ($unexportedCount > 0 || ($_SESSION['new_export_done'] ?? false));

    // --- Affichage de la page HTML ---
    if (file_exists('../../templates/header.php')) {
        require_once('../../templates/header.php');
    } else {
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>' . htmlspecialchars($titre) . '</title>';
        echo '<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1"></head><body><div class="container">';
    }

    if (file_exists('../../templates/navigation.php')) {
        require_once('../../templates/navigation.php');
    }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>BailCompta 360 | Exporter les Écritures</title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/tableau.css">
    <link rel="stylesheet" href="../../css/print.css" media="print">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        /* Styles spécifiques à cette page */
        .page-header {
            border-bottom: 2px solid #eee;
            padding-bottom: 9px;
            margin: 20px 0 30px;
        }
        .form-horizontal .form-group {
            margin-right: -15px;
            margin-left: -15px;
        }
        .table-responsive {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h2><?php echo htmlspecialchars($titre); ?></h2>
        </div>

        <?php
        // Affichage des messages
        echo $messageSucces;
        echo $messageInfo;
        echo $messageErreur;
        ?>

        <div class="well">
            <h3>Actions d'Exportation</h3>
            <p>Cliquez sur "Exporter les Écritures" pour générer un fichier TXT des écritures non encore exportées.</p>
            <p>Après un export réussi, le bouton "Confirmer l'exportation" sera actif. Cliquez dessus pour marquer les écritures exportées comme telles dans la base de données.</p>
            <a href="?action=export_all" class="btn btn-primary" onclick="return confirm('Êtes-vous sûr de vouloir exporter toutes les écritures non exportées ?');">
                <span class="glyphicon glyphicon-download-alt"></span> Exporter les Écritures
            </a>
            <a href="?action=confirm_export" class="btn btn-success <?php echo $confirmButtonActive ? '' : 'disabled'; ?>" <?php echo $confirmButtonActive ? '' : 'onclick="return false;"'; ?>>
                <span class="glyphicon glyphicon-ok"></span> Confirmer l'exportation (<?php echo $unexportedCount; ?>)
            </a>
            <?php if (!$confirmButtonActive && $unexportedCount == 0 && isset($_SESSION['new_export_done']) && $_SESSION['new_export_done']): ?>
                <p class="text-info">Un export a été effectué. Veuillez confirmer l'exportation une fois le fichier traité.</p>
            <?php endif; ?>
        </div>

        <h3>Liste des 1000 Écritures Récentes </h3>
        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered">
                <thead>
                    <tr>
                        <th>ID Ecriture</th>
                        <th>Date Saisie</th>
                        <th>Description</th>
                        <th>Montant Total</th>
                        <th>Journal</th>
                        <th>Cde</th>
                        <th>Agence SCE</th>
                        <th>Libellé 2</th>
                        <th>Utilisateur</th>
                        <th>Mois</th>
                        <th>N° Pièce</th>
                        <th>Exporté</th>
                        <th>Date Export</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allEcritures)): ?>
                        <tr>
                            <td colspan="13" class="text-center">Aucune écriture trouvée</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allEcritures as $ecriture): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ecriture['ID_Ecriture']); ?></td>
                                <td><?php echo htmlspecialchars($ecriture['Date_Saisie'] ? (new DateTime($ecriture['Date_Saisie']))->format('Y-m-d') : 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($ecriture['Description']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($ecriture['Montant_Total'], 2, ',', ' ')); ?></td>
                                <td><?php echo htmlspecialchars($ecriture['ID_Journal']); ?></td>
                                <td><?php echo htmlspecialchars($ecriture['Cde']); ?></td>
                                <td><?php
                                    // Apply transformation for display in the table
                                    $display_agence_val = $ecriture['NumeroAgenceSCE'] ?? '';
                                    echo htmlspecialchars(str_ireplace(['Yaoundé', 'Yao'], '009', $display_agence_val));
                                ?></td>
                                <td><?php echo htmlspecialchars($ecriture['libelle2']); ?></td>
                                <td><?php echo htmlspecialchars($ecriture['NomUtilisateur']); ?></td>
                                <td><?php echo htmlspecialchars($ecriture['Mois']); ?></td>
                                <td><?php echo htmlspecialchars($ecriture['Numero_Piece']); ?></td>
                                <td><?php echo $ecriture['is_exported'] ? 'Oui' : 'Non'; ?></td>
                                <td><?php echo htmlspecialchars($ecriture['exported_at'] ? (new DateTime($ecriture['exported_at']))->format('Y-m-d H:i:s') : 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div><script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="../js/jquery-3.7.1.min.jss"></script>
</body>
</html>

<?php
    // Fermer la connexion PDO
    $pdo = null;

    // Terminer la mise en mémoire tampon de sortie et envoyer le contenu au navigateur
    ob_end_flush();

} catch (Exception $e) {
    // Gérer les exceptions non PDO qui pourraient survenir avant ou pendant la connexion
    ob_end_clean(); // Assurez-vous de nettoyer le buffer en cas d'erreur grave
    error_log("Erreur critique inattendue : " . $e->getMessage());
    // Rediriger vers une page d'erreur ou afficher un message générique
    header('Location: ' . $current_page . '?error=' . urlencode('Une erreur critique est survenue : ' . htmlspecialchars($e->getMessage())));
    exit();
}
?>