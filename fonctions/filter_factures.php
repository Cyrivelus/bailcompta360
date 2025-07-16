<?php
// fonctions/filter_factures.php

require_once('database.php');
require_once('gestion_factures.php');

// Vérification CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die("Erreur de sécurité: Token CSRF invalide");
}

$status = $_POST['status'] ?? '';
$search = $_POST['search'] ?? '';

// Récupérer les factures filtrées
$factures = getListeFactures($pdo, $status, $search);

// Générer le HTML des lignes du tableau
$html = '';
if (empty($factures)) {
    $html = '<tr><td colspan="11" class="text-center">Aucune facture trouvée.</td></tr>';
} else {
    foreach ($factures as $facture) {
        $statusClass = '';
        if ($facture['Statut_Facture'] === 'Payé') {
            $statusClass = 'status-paid';
        } elseif ($facture['Statut_Facture'] === 'Dû') {
            $statusClass = 'status-due';
        }
        
        $isOverdue = false;
        if ($facture['Statut_Facture'] === 'Dû' && $facture['Date_Echeance'] && strtotime($facture['Date_Echeance']) < time()) {
            $statusClass = 'status-overdue';
            $isOverdue = true;
        }

        $html .= '<tr data-status="'.htmlspecialchars($facture['Statut_Facture']).'" 
                data-numero="'.htmlspecialchars($facture['Numero_Facture']).'"
                data-fournisseur="'.htmlspecialchars($facture['Nom_Fournisseur'] ?? '').'">
                <td class="text-center">'.htmlspecialchars($facture['Numero_Facture']).'</td>
                <td class="text-center">'.htmlspecialchars($facture['Type_Facture'] ?? 'N/A').'</td>
                <td class="text-center" data-sort="'.strtotime($facture['Date_Emission']).'">
                    '.formatDate($facture['Date_Emission']).'
                </td>
                <td class="text-center" data-sort="'.strtotime($facture['Date_Reception']).'">
                    '.formatDate($facture['Date_Reception']).'
                </td>
                <td class="text-center" data-sort="'.strtotime($facture['Date_Echeance']).'">
                    '.formatDate($facture['Date_Echeance']).'
                    '.($isOverdue ? '<span class="badge badge-danger">En retard</span>' : '').'
                </td>
                <td>'.htmlspecialchars($facture['Nom_Fournisseur'] ?? 'N/A').'</td>
                <td class="text-right" data-sort="'.$facture['Montant_TTC'].'">
                    '.number_format($facture['Montant_TTC'], 2, ',', ' ').' FCFA
                </td>
                <td class="text-center">
                    <span class="status-badge '.$statusClass.'">
                        '.htmlspecialchars($facture['Statut_Facture'] ?? 'N/A').'
                    </span>
                </td>
                <td class="text-center">';
                
        if (!empty($facture['ID_Ecriture_Comptable'])) {
            $html .= '<a href="../ecritures/modifier.php?id='.$facture['ID_Ecriture_Comptable'].'" 
                       class="btn btn-xs btn-link" 
                       title="Voir l\'écriture comptable">
                        '.htmlspecialchars($facture['ID_Ecriture_Comptable']).' 
                        <i class="fas fa-external-link-alt"></i>
                    </a>';
        } else {
            $html .= 'N/A';
        }
        
        $html .= '</td>
                <td class="text-center">
                    <div class="btn-actions">
                        <a href="voir_facture.php?id='.$facture['ID_Facture'].'" 
                           class="btn btn-sm btn-primary" 
                           title="Voir les détails">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="modifier_facture.php?id='.$facture['ID_Facture'].'" 
                           class="btn btn-sm btn-warning" 
                           title="Modifier">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form method="POST" action="supprimer_facture.php" style="display:inline;"
                              onsubmit="return confirm(\'Êtes-vous sûr de vouloir supprimer cette facture N° '.htmlspecialchars(addslashes($facture['Numero_Facture'])).' ?\')">
                            <input type="hidden" name="facture_id" value="'.$facture['ID_Facture'].'">
                            <input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">
                            <button type="submit" class="btn btn-sm btn-danger" title="Supprimer">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    </div>
                </td>
                <td class="text-center">';
                
        if ($facture['Statut_Facture'] !== 'Payé') {
            $html .= '<div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-success dropdown-toggle" 
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-check-circle"></i> Payer
                        </button>
                        <div class="dropdown-menu">
                            <form method="POST" action="process_payment.php" class="px-2 py-1">
                                <input type="hidden" name="action" value="payer">
                                <input type="hidden" name="facture_id" value="'.$facture['ID_Facture'].'">
                                <input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">
                                <input type="hidden" name="payment_method" value="cheque">
                                <button type="submit" class="dropdown-item btn-block text-left" 
                                        onclick="return promptBankName(\'cheque\', '.$facture['ID_Facture'].', \''.htmlspecialchars(addslashes($facture['Numero_Facture'])).'\')">
                                    <i class="fas fa-money-check-alt mr-2"></i> Par chèque
                                </button>
                            </form>
                            <form method="POST" action="process_payment.php" class="px-2 py-1">
                                <input type="hidden" name="action" value="payer">
                                <input type="hidden" name="facture_id" value="'.$facture['ID_Facture'].'">
                                <input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">
                                <input type="hidden" name="payment_method" value="virement">
                                <button type="submit" class="dropdown-item btn-block text-left" 
                                        onclick="return promptBankName(\'virement\', '.$facture['ID_Facture'].', \''.htmlspecialchars(addslashes($facture['Numero_Facture'])).'\')">
                                    <i class="fas fa-exchange-alt mr-2"></i> Par virement
                                </button>
                            </form>
                            <div class="dropdown-divider"></div>
                            <form method="POST" action="process_payment_sans_banque.php" class="px-2 py-1">
                                <input type="hidden" name="action" value="payer">
                                <input type="hidden" name="facture_id" value="'.$facture['ID_Facture'].'">
                                <input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">
                                <input type="hidden" name="payment_method" value="sans_banque">
                                <button type="submit" class="dropdown-item btn-block text-left">
                                    <i class="fas fa-hand-holding-usd mr-2"></i> Payer sans banque
                                </button>
                            </form>
                        </div>
                    </div>';
        } else {
            $html .= '<span class="badge badge-success">Payée</span>';
        }
        
        $html .= '</td>
            </tr>';
    }
}

// Retourner la réponse en JSON
header('Content-Type: application/json');
echo json_encode([
    'html' => $html,
    'count' => count($factures)
]);