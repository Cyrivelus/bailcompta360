<?php
require_once '../fonctions/database.php';
header('Content-Type: application/json');

// Initialisation de la réponse
$response = [
    'success' => false,
    'suggestion_debit' => null,
    'suggestion_credit' => null,
    'message' => '',
    'contreparties_journal' => [] // Pour stocker les contreparties depuis la table Journal
];

try {
    // Vérification des données reçues
    if (!isset($_POST['compte'])) {
        throw new Exception('Compte non spécifié');
    }

    if (!isset($_POST['sens'])) {
        throw new Exception('Sens non spécifié');
    }

    $compteId = $_POST['compte'];
    $sens = $_POST['sens'];

    // Récupération des informations du compte sélectionné
    $stmt = $pdo->prepare("SELECT Numero_Compte FROM Comptes_compta WHERE ID_Compte = ?");
    $stmt->execute([$compteId]);
    $compteCompta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$compteCompta) {
        throw new Exception('Compte introuvable');
    }

    $numeroCompte = $compteCompta['Numero_Compte'];

    // Tableau des contreparties habituelles (Numero_Compte)
    $contreparties = [];

    // 1. Clients (411...)
    if (strpos($numeroCompte, '411') === 0) {
        $contreparties['credit'] = '707000'; // Ventes de marchandises
    }
    // 2. Fournisseurs (401...)
    elseif (strpos($numeroCompte, '401') === 0) {
        $contreparties['debit'] = '607000'; // Achats de marchandises
    }
    // 3. Banque (512...)
    elseif (strpos($numeroCompte, '512') === 0) {
        $contreparties['credit'] = '512000';
        $contreparties['debit'] = '512000'; // Banque
    }
    // 4. Caisse (57...)
     elseif (strpos($numeroCompte, '53') === 0) {
        $contreparties['credit'] = '530000';
        $contreparties['debit'] = '530000'; // Caisse
    }
    // 5. TVA (445...)
    elseif (strpos($numeroCompte, '445') === 0) {
        if ($sens === 'debit') {
            $contreparties['credit'] = '512000'; // Banque (paiement TVA)
        } else {
            $contreparties['debit'] = '411000'; // Client (TVA facturée)
        }
    }
    // 6. Ventes (70...)
    elseif (strpos($numeroCompte, '70') === 0) {
        $contreparties['debit'] = '411000'; // Client
    }
    // 7. Achats (60...)
    elseif (strpos($numeroCompte, '60') === 0) {
        $contreparties['credit'] = '401000'; // Fournisseur
    }

    // Si on a trouvé une contrepartie, on la retourne directement (Numéro de Compte)
    if ($sens === 'debit' && isset($contreparties['credit'])) {
        $response['suggestion_credit'] = $contreparties['credit'];
        $response['success'] = true;
    } elseif ($sens === 'credit' && isset($contreparties['debit'])) {
        $response['suggestion_debit'] = $contreparties['debit'];
        $response['success'] = true;
    }

    // Récupération des contreparties depuis la table Journal pour affichage
    $stmtJournal = $pdo->prepare("SELECT DISTINCT Compte FROM dbo.JAL WHERE Compte LIKE '512%' OR Compte LIKE '57%'");
    $stmtJournal->execute();
    $contrepartiesJournal = $stmtJournal->fetchAll(PDO::FETCH_COLUMN);

    if ($contrepartiesJournal) {
        $response['contreparties_journal'] = $contrepartiesJournal;
    }

    if (!$response['success']) {
        $response['message'] = 'Aucune suggestion disponible pour ce compte';
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
