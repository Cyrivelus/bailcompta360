<?php
// Inclure les fichiers nécessaires
require_once(__DIR__ . '/../../fonctions/database.php');
require_once(__DIR__ . '/../../fonctions/gestion_utilisateurs.php');

// Démarrer la session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['releve_file'])) {
    $file = $_FILES['releve_file'];
    $filename = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowed_ext = array('csv');

    // Validation du fichier
    if (!in_array($file_ext, $allowed_ext)) {
        $message = "Seuls les fichiers CSV sont autorisés pour l'importation.";
        $message_type = 'danger';
    } else {
        $pdo = getPdo();
        $rowCount = 0;
        $errors = [];

        // Récupérer le code de l'agence sélectionnée
        $agence_code = isset($_POST['agence_code']) ? $_POST['agence_code'] : null;
        if (!$agence_code) {
            $message = "Veuillez sélectionner une agence.";
            $message_type = 'danger';
        } else {
            // Lecture du fichier CSV
            if (($handle = fopen($file_tmp, "r")) !== FALSE) {
                // Lire l'en-tête pour identifier les colonnes
                $header = fgetcsv($handle, 1000, ';');
                $date_col_index = array_search('Date', $header);
                $libelle_col_index = array_search('Libelle', $header);
                $debit_col_index = array_search('Debit', $header);
                $credit_col_index = array_search('Credit', $header);

                if ($date_col_index === false || $libelle_col_index === false || $debit_col_index === false || $credit_col_index === false) {
                    $errors[] = "Le fichier doit contenir les colonnes 'Date', 'Libelle', 'Debit' et 'Credit'.";
                }

                if (empty($errors)) {
                    // Préparer les requêtes SQL en dehors de la boucle pour de meilleures performances
                    $pdo->beginTransaction();

                    try {
                        while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
                            $rowCount++;

                            // Validation des données
                            $date_op_str = $data[$date_col_index];
                            $libelle = trim($data[$libelle_col_index]);
                            $debit = trim($data[$debit_col_index]);
                            $credit = trim($data[$credit_col_index]);

                            // Traitement de la date
                            $date_op = DateTime::createFromFormat('Y-m-d', $date_op_str);
                            if ($date_op === false) {
                                $errors[] = "Ligne {$rowCount}: Format de date invalide. Format attendu: AAAA-MM-JJ.";
                                continue;
                            }
                            $date_op = $date_op->format('Y-m-d H:i:s');

                            // Traitement des montants
                            $debit = floatval(str_replace(',', '.', $debit));
                            $credit = floatval(str_replace(',', '.', $credit));

                            $montant = 0;
                            $sens = '';
                            if ($debit > 0 && $credit == 0) {
                                $montant = $debit;
                                $sens = 'D';
                            } elseif ($credit > 0 && $debit == 0) {
                                $montant = $credit;
                                $sens = 'C';
                            } else {
                                $errors[] = "Ligne {$rowCount}: Un montant invalide ou les deux montants sont renseignés.";
                                continue;
                            }

                            // Début de l'écriture
                            $stmt_ecriture = $pdo->prepare("
                                INSERT INTO ecritures (Date_Saisie, Description, Montant_Total, ID_Journal, NumeroAgenceSCE, NomUtilisateur, Mois, Numero_Piece)
                                VALUES (:date_saisie, :description, :montant_total, :id_journal, :agence_code, :nom_utilisateur, :mois, :numero_piece)
                            ");

                            $id_journal = 1; // ID du journal d'opérations bancaires, à adapter
                            $mois = substr($date_op, 0, 7);
                            $numero_piece = 'IMP-' . date('YmdHis') . '-' . $rowCount;

                            $stmt_ecriture->execute([
                                ':date_saisie' => $date_op,
                                ':description' => "Opération de relevé bancaire : {$libelle}",
                                ':montant_total' => $montant,
                                ':id_journal' => $id_journal,
                                ':agence_code' => $agence_code,
                                ':nom_utilisateur' => $_SESSION['nom_utilisateur'] ?? 'ImportAutomatique',
                                ':mois' => $mois,
                                ':numero_piece' => $numero_piece
                            ]);

                            $id_ecriture = $pdo->lastInsertId();

                            // Ligne de l'écriture pour le compte de l'agence (compte banque)
                            // Récupérer le NoCompteComptable de l'agence sélectionnée
                            $stmt_compte_agence = $pdo->prepare("SELECT NoCompteComptable FROM agences_sce WHERE CodeAgenceSCE = :agence_code");
                            $stmt_compte_agence->execute([':agence_code' => $agence_code]);
                            $compte_banque = $stmt_compte_agence->fetchColumn();

                            if (!$compte_banque) {
                                $errors[] = "Ligne {$rowCount}: Compte comptable de l'agence non trouvé.";
                                $pdo->rollBack();
                                fclose($handle);
                                break;
                            }
                            
                            // Récupérer l'ID_Compte à partir du NoCompteComptable
                            $stmt_id_compte_agence = $pdo->prepare("SELECT ID_Compte FROM comptes_compta WHERE NumeroCompte = :numero_compte");
                            $stmt_id_compte_agence->execute([':numero_compte' => $compte_banque]);
                            $id_compte_banque = $stmt_id_compte_agence->fetchColumn();

                            if (!$id_compte_banque) {
                                $errors[] = "Ligne {$rowCount}: Le compte comptable de l'agence '{$compte_banque}' n'est pas valide.";
                                $pdo->rollBack();
                                fclose($handle);
                                break;
                            }

                            $stmt_ligne_agence = $pdo->prepare("
                                INSERT INTO lignes_ecritures (ID_Ecriture, ID_Compte, Montant, Sens, Libelle_Ligne)
                                VALUES (:id_ecriture, :id_compte, :montant, :sens, :libelle_ligne)
                            ");
                            $sens_agence = ($sens === 'D') ? 'C' : 'D'; // Inverser le sens pour le compte de la banque
                            $stmt_ligne_agence->execute([
                                ':id_ecriture' => $id_ecriture,
                                ':id_compte' => $id_compte_banque,
                                ':montant' => $montant,
                                ':sens' => $sens_agence,
                                ':libelle_ligne' => $libelle
                            ]);

                            // Ligne de l'écriture pour le compte de contrepartie
                            $compte_contrepartie_id = isset($_POST['compte_contrepartie']) ? $_POST['compte_contrepartie'] : null;
                            if (!$compte_contrepartie_id) {
                                $errors[] = "Ligne {$rowCount}: Le compte de contrepartie n'est pas spécifié.";
                                $pdo->rollBack();
                                fclose($handle);
                                break;
                            }

                            $stmt_ligne_contrepartie = $pdo->prepare("
                                INSERT INTO lignes_ecritures (ID_Ecriture, ID_Compte, Montant, Sens, Libelle_Ligne)
                                VALUES (:id_ecriture, :id_compte, :montant, :sens, :libelle_ligne)
                            ");
                            $stmt_ligne_contrepartie->execute([
                                ':id_ecriture' => $id_ecriture,
                                ':id_compte' => $compte_contrepartie_id,
                                ':montant' => $montant,
                                ':sens' => $sens, // Conserver le sens original
                                ':libelle_ligne' => $libelle
                            ]);
                        }

                        if (empty($errors)) {
                            $pdo->commit();
                            $message = "Importation réussie. {$rowCount} lignes traitées.";
                            $message_type = 'success';

                            // Ajout dans logs_audit
                            $stmt_log = $pdo->prepare("
                                INSERT INTO logs_audit (date_action, utilisateur_id, action_type, description)
                                VALUES (NOW(), :utilisateur_id, :action_type, :description)
                            ");
                            $stmt_log->execute([
                                ':utilisateur_id' => $_SESSION['utilisateur_id'] ?? 0,
                                ':action_type' => 'Importation Relevé Bancaire',
                                ':description' => "Importation du fichier de relevé bancaire '{$filename}' pour l'agence '{$agence_code}'."
                            ]);

                        } else {
                            $pdo->rollBack();
                            $message = "L'importation a échoué. " . implode(" ", $errors);
                            $message_type = 'danger';
                        }
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $message = "Une erreur s'est produite lors de l'importation: " . $e->getMessage();
                        $message_type = 'danger';
                    } finally {
                        fclose($handle);
                    }
                } else {
                    $message = "L'importation a échoué: " . implode(" ", $errors);
                    $message_type = 'danger';
                }
            }
        }
    }
}
include '../../templates/navigation.php';
include '../../templates/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importation Relevé Bancaire</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/select2.min.css">
    <link rel="stylesheet" href="../../css/select2-bootstrap.min.css">
</head>
<body>
    <div class="container">
        <h2>Importation de Relevé Bancaire</h2>
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type; ?>" role="alert">
                <?= $message; ?>
            </div>
        <?php endif; ?>

        <form action="releves.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="agence_code">Sélectionner l'Agence :</label>
                <select id="agence_code" name="agence_code" class="form-control" required>
                    <option value="">-- Sélectionnez une agence --</option>
                    <?php
                    $pdo = getPdo();
                    $stmt = $pdo->query("SELECT CodeAgenceSCE, LibelleAgenceSCE FROM agences_sce ORDER BY LibelleAgenceSCE ASC");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                    ?>
                        <option value="<?= htmlspecialchars($row['CodeAgenceSCE']); ?>"><?= htmlspecialchars($row['LibelleAgenceSCE'] . ' - ' . $row['CodeAgenceSCE']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="compte_contrepartie">Compte de contrepartie :</label>
                <select id="compte_contrepartie" name="compte_contrepartie" class="form-control" required>
                    <option value="">-- Sélectionnez un compte --</option>
                    <?php
                    $stmt = $pdo->query("SELECT ID_Compte, NumeroCompte, LibelleCompte FROM comptes_compta ORDER BY NumeroCompte ASC");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                    ?>
                        <option value="<?= htmlspecialchars($row['ID_Compte']); ?>"><?= htmlspecialchars($row['NumeroCompte'] . ' - ' . $row['LibelleCompte']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="releve_file">Fichier de Relevé (CSV):</label>
                <input type="file" class="form-control" id="releve_file" name="releve_file" required>
                <p class="help-block">Veuillez convertir votre fichier Excel en format CSV avant l'importation.</p>
            </div>

            <button type="submit" class="btn btn-primary">Importer</button>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script src="../../js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#agence_code').select2({
                placeholder: "Rechercher une agence...",
                theme: "bootstrap"
            });
            $('#compte_contrepartie').select2({
                placeholder: "Rechercher un compte...",
                theme: "bootstrap"
            });
        });
    </script>
</body>
</html>
<?php include '../../templates/footer.php'; ?>