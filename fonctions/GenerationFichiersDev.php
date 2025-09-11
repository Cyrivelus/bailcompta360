<?php
// fonctions/GenerationFichiersDev.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Fonction principale de génération de fichiers.
 * Gère l'exportation des données de simulation vers différents formats.
 */
function genererFichier() {
    // 1. Vérifier si les données de simulation sont présentes en session
    if (!isset($_SESSION['simulation_data'])) {
        http_response_code(404);
        die("Erreur : Données de simulation non trouvées ou session expirée. Veuillez d'abord générer un tableau d'amortissement.");
    }

    $simulation_data = $_SESSION['simulation_data'];
    $tableau_amortissement = $simulation_data['tableau'] ?? [];
    $synthese_data = $simulation_data['synthese'] ?? [];
    $raison_sociale = $simulation_data['raison_sociale'] ?? 'Non spécifié';
    $matricule = $simulation_data['matricule'] ?? 'XXX';
    $numero_dossier = $simulation_data['numero_dossier'] ?? 'XXX/XXX';
    
    // Récupérer le nom de l'utilisateur connecté depuis la session
    $nom_utilisateur = $_SESSION['nom_utilisateur'] ?? 'Utilisateur inconnu';

    // 2. Récupérer le type de fichier et le type de document demandé via la requête GET
    $type_fichier = $_GET['type'] ?? null;
    $doc_type = $_GET['doc_type'] ?? 'simulation'; // Par défaut, génère le document de simulation

    if (empty($type_fichier)) {
        http_response_code(400);
        die("Erreur : Type de fichier non spécifié (csv ou pdf).");
    }

    $nom_fichier = 'simulation_financement_' . str_replace(' ', '_', $raison_sociale) . '_' . date('Ymd_His');

    // Définir le chemin d'accès au logo pour les PDF
    $logo_path = '/bailcompta360/images/logo_bailcompta.png';

    // 3. Traiter l'exportation en fonction du type de fichier
    switch ($type_fichier) {
        case 'csv':
            exporterEnCSV($nom_fichier, $tableau_amortissement, $synthese_data, $nom_utilisateur, $raison_sociale);
            break;

        case 'pdf':
            switch ($doc_type) {
                case 'simulation':
                    afficherHTMLPourPDFSimulation($nom_fichier, $simulation_data, $logo_path);
                    break;
                case 'client':
                    afficherHTMLPourPDFClient($nom_fichier, $simulation_data, $logo_path);
                    break;
                case 'entreprise':
                    afficherHTMLPourPDFEntreprise($nom_fichier, $simulation_data, $logo_path);
                    break;
                default:
                    http_response_code(400);
                    die("Erreur : Type de document non pris en charge.");
            }
            break;

        default:
            http_response_code(400);
            die("Erreur : Type de fichier non pris en charge.");
    }
}

/**
 * Exporte les données en format CSV et force le téléchargement.
 * (Cette fonction reste inchangée par rapport à votre version originale)
 * @param string $nom_fichier
 * @param array $tableau
 * @param array $synthese
 * @param string $nom_utilisateur
 * @param string $raison_sociale
 */
function exporterEnCSV($nom_fichier, $tableau, $synthese, $nom_utilisateur, $raison_sociale) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nom_fichier . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Informations du document
    fputcsv($output, ['Document généré par', $nom_utilisateur]);
    fputcsv($output, ['Pour le client', $raison_sociale]);
    fputcsv($output, ['Date de génération', date('d/m/Y H:i:s')]);
    fputcsv($output, []);

    // Entêtes du tableau
    fputcsv($output, ['Tableau d\'Amortissement']);
    if (!empty($tableau)) {
        fputcsv($output, array_keys($tableau[0]));
    }

    // Données du tableau
    foreach ($tableau as $ligne) {
        fputcsv($output, array_values($ligne));
    }

    // Lignes vides de séparation
    fputcsv($output, []);
    fputcsv($output, ['Synthèse des Coûts']);
    fputcsv($output, ['Total des Loyers HT', number_format($synthese['total_loyer_paye_ht'] ?? 0, 2, ',', ' ')]);
    fputcsv($output, ['Total des Loyers TTC', number_format($synthese['total_loyer_paye_ttc'] ?? 0, 2, ',', ' ')]);
    fputcsv($output, ['Total des Frais Initiaux HT', number_format($synthese['total_frais_initiaux_ht'] ?? 0, 2, ',', ' ')]);
    fputcsv($output, ['Total des Frais Initiaux TTC', number_format($synthese['total_frais_initiaux_ttc'] ?? 0, 2, ',', ' ')]);
    fputcsv($output, ['Coût Total Global TTC', number_format($synthese['total_ttc_global'] ?? 0, 2, ',', ' ')]);

    fclose($output);
    exit;
}

/**
 * Génère le contenu HTML pour l'impression du document de simulation.
 * @param string $nom_fichier
 * @param array $simulation_data
 * @param string $logo_path
 */
function afficherHTMLPourPDFSimulation($nom_fichier, $simulation_data, $logo_path) {
    $synthese = $simulation_data['synthese'] ?? [];
    $loyer_data = $simulation_data['loyer_data'] ?? [];
    $tableau_amortissement = $simulation_data['tableau'] ?? [];
    $nom_client = $simulation_data['raison_sociale'] ?? 'Non spécifié';
    
    echo '<!DOCTYPE html>';
    echo '<html lang="fr">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Simulation de Prêt - ' . htmlspecialchars($nom_fichier) . '</title>';
    echo '<style>';
    echo 'body { font-family: sans-serif; margin: 20px; }';
    echo '.header { text-align: center; margin-bottom: 20px; }';
    echo '.header img { max-width: 150px; margin-bottom: 10px; }';
    echo '.info-table { width: 100%; border-collapse: collapse; margin-top: 20px; }';
    echo '.info-table td { padding: 8px; border: 1px solid #ddd; }';
    echo '.info-table td:first-child { font-weight: bold; }';
    echo 'h1, h2 { text-align: center; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<div class="header">';
    echo '<img src="' . htmlspecialchars($logo_path) . '" alt="Logo BailCompta">';
    echo '<h1>SIMULATION DE FINANCEMENT DE PRÊT </h1>';
   
// ...
// Ancien code :
// echo '<p>Edité le ' . date('d/m/Y H:i:s') . '</p>';

// Nouveau code corrigé :
$date = new DateTime();
$date->modify('-1 hour');
echo '<p>Edité le ' . $date->format('d/m/Y H:i:s') . '</p>';

    echo '</div>';
    
    echo '<table class="info-table">';
    echo '<tr><td>NOM DU CLIENT :</td><td>' . htmlspecialchars($nom_client) . '</td></tr>';
    echo '<tr><td>Périodicité (M;T;S;A)</td><td>' . htmlspecialchars($simulation_data['periodicite'] ?? 'M') . '</td></tr>';
    echo '<tr><td>Nombre de périodes d\'Amortissement</td><td>' . htmlspecialchars($simulation_data['nb_periodes'] ?? '60') . '</td></tr>';
    echo '<tr><td>Nombre de Loyers/an</td><td>' . htmlspecialchars($simulation_data['loyers_par_an'] ?? '12') . '</td></tr>';
    echo '<tr><td>Nombre de Loyers</td><td>' . htmlspecialchars($simulation_data['nb_loyers'] ?? '60') . '</td></tr>';
    echo '<tr><td>Prix Matériel HT</td><td>' . number_format($simulation_data['prix_materiel_ht'] ?? 0, 2, ',', ' ') . '</td></tr>';
    echo '<tr><td>TVA sur matériel</td><td>' . number_format($simulation_data['tva_materiel'] ?? 0, 2, ',', ' ') . '</td></tr>';
    echo '<tr><td>Prix total TTC</td><td>' . number_format($simulation_data['prix_total_ttc'] ?? 0, 2, ',', ' ') . '</td></tr>';
    echo '<tr><td>Base calc. loyers</td><td>' . number_format($simulation_data['base_loyers'] ?? 0, 2, ',', ' ') . '</td></tr>';
    echo '<tr><td>Dépôt de Garantie</td><td>' . number_format($simulation_data['depot_garantie'] ?? 0, 2, ',', ' ') . '</td></tr>';
    echo '<tr><td>Tx rémunération DG</td><td>' . number_format($simulation_data['tx_remuneration_dg'] ?? 0, 2, ',', ' ') . ' %</td></tr>';
    echo '<tr><td>Valeur Résiduelle</td><td>' . number_format($simulation_data['valeur_res_ht'] ?? 0, 2, ',', ' ') . '</td></tr>';
    echo '<tr><td>Taux contrat</td><td>' . number_format($simulation_data['taux_contrat'] ?? 0, 2, ',', ' ') . ' %</td></tr>';
    echo '<tr><td>Durée Financement (Mois)</td><td>' . htmlspecialchars($simulation_data['duree_financement_mois'] ?? '60') . '</td></tr>';
    echo '<tr><td>Tx TVA/Loyer</td><td>' . number_format($simulation_data['tx_tva_loyer'] ?? 0, 2, ',', ' ') . ' %</td></tr>';
    echo '<tr><td>1er Loyer Majoré</td><td>' . number_format($loyer_data['premier_loyer_majore_ht'] ?? 0, 2, ',', ' ') . ' HT / ' . number_format($loyer_data['premier_loyer_majore_ttc'] ?? 0, 2, ',', ' ') . ' TTC</td></tr>';
    echo '<tr><td>Loyer Mensuel</td><td>' . number_format($loyer_data['loyer_mensuel_ht'] ?? 0, 2, ',', ' ') . ' HT / ' . number_format($loyer_data['loyer_mensuel_ttc'] ?? 0, 2, ',', ' ') . ' TTC</td></tr>';
    echo '<tr><td>Encours total (VR compris)</td><td>' . number_format($synthese['total_ttc_global'] ?? 0, 2, ',', ' ') . '</td></tr>';
    echo '</table>';

    echo '<script>window.print();</script>';
    echo '</body>';
    echo '</html>';
    exit;
}

/**
 * Génère le contenu HTML pour l'impression de l'échéancier du client.
 * @param string $nom_fichier
 * @param array $simulation_data
 * @param string $logo_path
 */
function afficherHTMLPourPDFClient($nom_fichier, $simulation_data, $logo_path) {
    $tableau = $simulation_data['tableau'] ?? [];
    $synthese = $simulation_data['synthese'] ?? [];
    $loyer_data = $simulation_data['loyer_data'] ?? [];
    $nom_client = $simulation_data['raison_sociale'] ?? 'Non spécifié';
    $num_dossier = $simulation_data['numero_dossier'] ?? 'XXX/XXX';
    $matricule = $simulation_data['matricule'] ?? 'XXX';

    // Correction de la date: utiliser la date de la session
    $date_debut = $simulation_data['date_debut'] ?? date('Y-m-d');
    $date_actuelle = new DateTime($date_debut);
    
    echo '<!DOCTYPE html>';
    echo '<html lang="fr">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Échéancier Client - ' . htmlspecialchars($nom_fichier) . '</title>';
    echo '<style>';
    echo 'body { font-family: sans-serif; margin: 20px; }';
    echo '.logo-container { text-align: center; margin-bottom: 20px; }';
    echo '.logo-container img { max-width: 150px; }';
    echo 'h1, h2 { text-align: center; }';
    echo '.info-header { margin-bottom: 20px; }';
    echo '.info-header table { width: 100%; }';
    echo '.info-header td { padding: 5px; }';
    echo 'table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }';
    echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }';
    echo 'th { background-color: #f2f2f2; }';
    echo '.signature-section { margin-top: 50px; text-align: right; }';
    echo '.signature-section p { border-top: 1px solid #000; display: inline-block; padding-top: 5px; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<div class="logo-container">';
    echo '<img src="' . htmlspecialchars($logo_path) . '" alt="Logo BailCompta">';
    echo '</div>';
    
    echo '<h1>ECHEANCIER DOSSIER N° ' . htmlspecialchars($num_dossier) . '</h1>';
    echo '<p style="text-align: center;">Edité le : ' . date('d/m/Y H:i:s') . '</p>';

    echo '<div class="info-header">';
    echo '<table>';
    echo '<tr>';
    echo '<td>Nom du Client : <strong>' . htmlspecialchars($nom_client) . '</strong></td>';
    echo '<td>Périodicité : <strong>MENSUELLE</strong></td>';
    echo '<td>Valeur Résiduelle HT : <strong>' . number_format($simulation_data['valeur_res_ht'] ?? 0, 2, ',', ' ') . '</strong></td>';
    echo '<td>Nbre de Loyers : <strong>' . htmlspecialchars($simulation_data['nb_loyers'] ?? '60') . '</strong></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td>Montant du Financement HT : <strong>' . number_format($simulation_data['prix_materiel_ht'] ?? 0, 2, ',', ' ') . '</strong></td>';
    echo '<td>Matricule : <strong>' . htmlspecialchars($matricule) . '</strong></td>';
    echo '<td>TEG : <strong>' . number_format(($simulation_data['teg'] ?? 0) * 0.2825, 3, ',', ' ') . '</strong></td>';
    echo '<td></td>';
    echo '</tr>';
    echo '</table>';
    echo '</div>';
    
    echo '<table>';
    echo '<thead><tr>';
    echo '<th>N° Ech</th>';
    echo '<th>DATE DE VERSEMENT</th>';
    echo '<th>CAPITAL RESTANT DÛ</th>';
    echo '<th>LOYERS HT</th>';
    echo '<th>LOYERS TTC + PRESTATIONS TTC</th>';
    echo '<th>TVA/LOYERS</th>';
    echo '<th>Périodes Loyers</th>';
    echo '<th>PRESTATION TTC</th>';
    echo '<th>VR-HT</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    // Date du premier loyer majoré
    $date_premier_loyer = date('d/m/Y', strtotime($loyer_data['date_premier_loyer'] ?? 'now'));
    echo '<tr>';
    echo '<td></td>';
    echo '<td>' . $date_premier_loyer . '</td>';
    echo '<td>' . number_format($tableau[0]['capital_restant_du'] ?? 0, 2, ',', ' ') . '</td>';
    echo '<td>' . number_format($loyer_data['premier_loyer_majore_ht'] ?? 0, 2, ',', ' ') . '</td>';
    echo '<td>' . number_format($loyer_data['premier_loyer_majore_ttc'] ?? 0, 2, ',', ' ') . '</td>';
    echo '<td>' . number_format($loyer_data['tva_premier_loyer_majore'] ?? 0, 2, ',', ' ') . '</td>';
    echo '<td colspan="3"></td>';
    echo '</tr>';
    
    // Lignes de loyers mensuels
    foreach ($tableau as $ligne) {
        if (($ligne['echeance_num'] ?? '') != 'VR') {
            // Utilisation de la date stockée dans la ligne du tableau
            $date_echeance = htmlspecialchars($ligne['date_echeance'] ?? 'N/A');
            echo '<tr>';
            echo '<td>' . htmlspecialchars($ligne['echeance_num'] ?? 'N/A') . '</td>';
            echo '<td>' . $date_echeance . '</td>';
            echo '<td>' . number_format($ligne['capital_restant_du'] ?? 0, 2, ',', ' ') . '</td>';
            echo '<td>' . number_format($ligne['loyer_ht'] ?? 0, 2, ',', ' ') . '</td>';
            echo '<td>' . number_format($ligne['loyer_ttc_prestation'] ?? 0, 2, ',', ' ') . '</td>';
            echo '<td>' . number_format($ligne['tva_loyer'] ?? 0, 2, ',', ' ') . '</td>';
            echo '<td></td>';
            echo '<td>' . number_format($ligne['prestation_ttc'] ?? 0, 2, ',', ' ') . '</td>';
            echo '<td></td>';
            echo '</tr>';
        } else {
            // Ligne de la Valeur Résiduelle
            $date_vr = htmlspecialchars($ligne['date_echeance'] ?? 'N/A');
            echo '<tr>';
            echo '<td></td>';
            echo '<td>' . $date_vr . '</td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
            echo '<td>' . number_format($ligne['valeur_residuelle_ht'] ?? 0, 2, ',', ' ') . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody>';
    echo '</table>';

    echo '<div class="signature-section">';
    echo '<p>Pour le Client<br>(Signature précédée de la mention "Lu et Approuvé")</p>';
    echo '</div>';

    echo '<script>window.print();</script>';
    echo '</body>';
    echo '</html>';
    exit;
}

/**
 * Génère le contenu HTML pour l'impression du tableau d'amortissement détaillé pour l'entreprise.
 * @param string $nom_fichier
 * @param array $simulation_data
 * @param string $logo_path
 */
function afficherHTMLPourPDFEntreprise($nom_fichier, $simulation_data, $logo_path) {
    $tableau = $simulation_data['tableau'] ?? [];
    $synthese = $simulation_data['synthese'] ?? [];
    $loyer_data = $simulation_data['loyer_data'] ?? [];
    $nom_client = $simulation_data['raison_sociale'] ?? 'Non spécifié';
    $num_dossier = $simulation_data['numero_dossier'] ?? 'XXX/XXX';
    $matricule = $simulation_data['matricule'] ?? 'XXX';

    echo '<!DOCTYPE html>';
    echo '<html lang="fr">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Tableau d\'Amortissement - ' . htmlspecialchars($nom_fichier) . '</title>';
    echo '<style>';
    echo 'body { font-family: sans-serif; margin: 20px; }';
    echo '.header { text-align: center; }';
    echo '.header img { max-width: 150px; margin-bottom: 10px; }';
    echo '.page-info { font-style: italic; font-size: 10px; }';
    echo '.details-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 12px; }';
    echo '.details-table td { padding: 5px; }';
    echo 'table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 10px; }';
    echo 'th, td { border: 1px solid #ddd; padding: 5px; text-align: right; }';
    echo 'th { background-color: #f2f2f2; text-align: center; }';
    echo '.red-text { color: red; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    echo '<div class="header">';
    echo '<img src="' . htmlspecialchars($logo_path) . '" alt="Logo BailCompta">';
    echo '<h1>TABLEAU D\'AMORTISSEMENT DE PRÊT </h1>';
    echo '<p>Edité le : ' . date('d/m/Y H:i:s') . ' Page : 1</p>';
    echo '</div>';
    
    echo '<table class="details-table">';
    echo '<tr>';
    echo '<td>Raison Sociale : <strong>' . htmlspecialchars($nom_client) . '</strong></td>';
    echo '<td>No Dossier : <strong>' . htmlspecialchars($num_dossier) . '</strong></td>';
    echo '<td>Matricule : <strong>' . htmlspecialchars($matricule) . '</strong></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td>Montant du Prêt : <strong>' . number_format($simulation_data['prix_materiel_ht'] ?? 0, 2, ',', ' ') . '</strong></td>';
    echo '<td>Nombre de Mensualités : <strong>' . htmlspecialchars($simulation_data['nb_loyers'] ?? '60') . '</strong></td>';
    echo '<td>Date de Début de l\'Emprunt : <strong>' . date('d/m/Y', strtotime($simulation_data['date_debut'] ?? 'now')) . '</strong></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td>Valeur Résiduelle HT : <strong>' . number_format($simulation_data['valeur_res_ht'] ?? 0, 2, ',', ' ') . '</strong></td>';
    echo '<td>Taux d\'intérêt Annuel : <strong>' . number_format($simulation_data['taux_contrat'] ?? 0, 2, ',', ' ') . '%</strong></td>';
    echo '<td>Frais d\'Installation Tracking TTC : <strong>0</strong></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td>Premier loyer majoré TTC : <strong>' . number_format($loyer_data['premier_loyer_majore_ttc'] ?? 0, 2, ',', ' ') . '</strong></td>';
    echo '<td>Loyer Mensuel HT : <strong>' . number_format($loyer_data['loyer_mensuel_ht'] ?? 0, 2, ',', ' ') . '</strong></td>';
    echo '<td>Taux Effectif Global (TEG) : <strong class="red-text">' . number_format(($simulation_data['teg'] ?? 0) * 0.2825, 3, ',', ' ') . '%</strong></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td>Taux dépôt de Garantie : <strong>' . number_format($simulation_data['tx_remuneration_dg'] ?? 0, 2, ',', ' ') . '%</strong></td>';
    echo '<td>Montant dépôt de Garantie : <strong>' . number_format($simulation_data['depot_garantie'] ?? 0, 2, ',', ' ') . '</strong></td>';
    echo '<td>Taux premier Loyer Majoré : <strong>' . number_format($simulation_data['tx_premier_loyer'] ?? 0, 2, ',', ' ') . '%</strong></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td>Taux Valeur Résiduelle : <strong>' . number_format($simulation_data['tx_valeur_res'] ?? 0, 2, ',', ' ') . '%</strong></td>';
    echo '<td>Premier loyer majoré HT : <strong>' . number_format($loyer_data['premier_loyer_majore_ht'] ?? 0, 2, ',', ' ') . '</strong></td>';
    echo '<td>Frais d\'Installation Tracking HT : <strong>0</strong></td>';
    echo '</tr>';
    echo '</table>';
    
    echo '<h2>Détails des Loyers</h2>';
    echo '<table>';
    echo '<thead><tr>';
    echo '<th>N° Ech</th>';
    echo '<th>DATE DE VERSEMENT</th>';
    echo '<th>CAPITAL FINANCE</th>';
    echo '<th>LOYERS HT</th>';
    echo '<th>PRESTATIONS HT</th>';
    echo '<th>TVA/LOYERS</th>';
    echo '<th>TVA/PRESTATION</th>';
    echo '<th>PRESTATION TTC</th>';
    echo '<th>LOYERS TTC</th>';
    echo '<th>CAPITAL REMBOURSE</th>';
    echo '<th>INTERÊTS</th>';
    echo '<th>RESTE A REMBOURSER</th>';
    echo '</tr></thead>';
    echo '<tbody>';
    
    $vr_found = false;
    foreach ($tableau as $ligne) {
        if (($ligne['echeance_num'] ?? '') === 'VR') {
            $vr_found = true;
            continue;
        }

        // On récupère la date de la ligne
        $date_echeance = htmlspecialchars($ligne['date_echeance'] ?? 'N/A');
    
        echo '<tr>';
        echo '<td>' . htmlspecialchars($ligne['echeance_num'] ?? 'N/A') . '</td>';
        echo '<td>' . $date_echeance . '</td>';
        echo '<td>' . number_format($ligne['capital_finance'] ?? 0, 2, ',', ' ') . '</td>';
        echo '<td>' . number_format($ligne['loyer_ht'] ?? 0, 2, ',', ' ') . '</td>';
        echo '<td>' . number_format($ligne['prestation_ht'] ?? 0, 2, ',', ' ') . '</td>';
        echo '<td>' . number_format($ligne['tva_loyer'] ?? 0, 2, ',', ' ') . '</td>';
        echo '<td>' . number_format($ligne['tva_prestation'] ?? 0, 2, ',', ' ') . '</td>';
        echo '<td>' . number_format($ligne['prestation_ttc'] ?? 0, 2, ',', ' ') . '</td>';
        echo '<td>' . number_format($ligne['loyer_ttc'] ?? 0, 2, ',', ' ') . '</td>';
        echo '<td>' . number_format($ligne['capital_rembourse'] ?? 0, 2, ',', ' ') . '</td>';
        echo '<td>' . number_format($ligne['interets'] ?? 0, 2, ',', ' ') . '</td>';
        echo '<td>' . number_format($ligne['reste_a_rembourser'] ?? 0, 2, ',', ' ') . '</td>';
        echo '</tr>';
    }

    if ($vr_found) {
        $vr_row = array_filter($tableau, function($item) {
            return ($item['echeance_num'] ?? '') === 'VR';
        });
        if (!empty($vr_row)) {
            $vr_row = reset($vr_row);
            $date_vr = htmlspecialchars($vr_row['date_echeance'] ?? 'N/A');
            echo '<tr>';
            echo '<td>VR</td>';
            echo '<td>' . $date_vr . '</td>';
            echo '<td colspan="9" style="text-align: right; font-weight: bold;">Valeur Résiduelle (VR)</td>';
            echo '<td>' . number_format($vr_row['reste_a_rembourser'] ?? 0, 2, ',', ' ') . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody>';
    echo '</table>';

    echo '<script>window.print();</script>';
    echo '</body>';
    echo '</html>';
    exit;
}

// Lancer la génération
genererFichier();
?>