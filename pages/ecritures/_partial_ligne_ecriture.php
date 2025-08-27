<?php
// pages/ecritures/_partial_ligne_ecriture.php
// Partial template for a single accounting entry line

// Expects the following variables to be available in the calling scope:
// $index (int|string) : The index for naming/IDing fields (e.g., 0, 1, {{INDEX}})
// $ligne_p (array)    : An array containing current values for the line (e.g., ['compte' => '...', 'libelle_ligne' => '...', 'debit' => '...', 'credit' => '...', 'an' => '...', 'contrepartie' => '...'])
// $GLOBALS['allComptesPLN'] (array) : List of all accounts (from Comptes_compta, keys 'Cpt', 'Lib')
// $agences (array)             : List of all agencies (from AGENCES_SCE, keys 'CodeAgenceSCE', 'LibelleAgenceSCE')
// $code_agence (string)        : The default agency code for the user

// Ensure variables are set if this partial is included directly or with minimal data
$index = $index ?? '{{INDEX}}'; // Use a placeholder for the JS template
$ligne_p = $ligne_p ?? ['compte'=>'', 'libelle_ligne'=>'', 'debit'=>'', 'credit'=>'', 'an'=>'', 'contrepartie'=>''];
$agences = $agences ?? [];
$GLOBALS['allComptesPLN'] = $GLOBALS['allComptesPLN'] ?? [];
$code_agence = $code_agence ?? '';

// Use the value from $ligne_p if it exists, otherwise use the user's default agency code for the 'an' field
$selected_an = $ligne_p['an'] ?? $code_agence;
// Use the value from $ligne_p for the 'contrepartie' field
$selected_contrepartie = $ligne_p['contrepartie'] ?? '';
$loggedInUserId = $_SESSION['utilisateur_id'] ?? null;

// Initialiser le code d'agence de l'utilisateur à null
$userAgencyCode = null;

if ($loggedInUserId) {
    // 2. Exécuter la requête pour obtenir le code d'agence de l'utilisateur
    //    Ceci récupère la valeur pour la variable $userAgencyCode.
    $sqlUserAgency = "SELECT CodeAgenceSCE FROM Utilisateurs WHERE ID_Utilisateur = :id";
    $stmtUserAgency = $pdo->prepare($sqlUserAgency);
    $stmtUserAgency->bindParam(':id', $loggedInUserId, PDO::PARAM_INT);
    $stmtUserAgency->execute();
    $userAgency = $stmtUserAgency->fetch(PDO::FETCH_ASSOC);

    if ($userAgency) {
        $userAgencyCode = $userAgency['CodeAgenceSCE'];
    }
}

// 3. Exécuter la requête pour obtenir la liste complète des agences
//    Ceci remplit la variable $agences utilisée dans le formulaire.
$agences = [];
try {
    $sqlAgences = "SELECT CodeAgenceSCE, LibelleAgenceSCE FROM AGENCES_SCE ORDER BY CodeAgenceSCE ASC";
    $stmtAgences = $pdo->query($sqlAgences);
    $agences = $stmtAgences->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Gérer l'erreur si la requête échoue
    error_log("Erreur de base de données : " . $e->getMessage());
}
?>

<div class="entry-line" data-index="<?= htmlspecialchars($index) ?>">
    <div class="row form-row">
        <div class="col-md-2 col-sm-6">
            <div class="form-group">
                <label for="compte_<?= htmlspecialchars($index) ?>">Compte <span class="text-danger">*</span></label>
                <select class="form-control compte-select" id="compte_<?= htmlspecialchars($index) ?>" name="compte[]" required>
                    <option value="">S&eacutelectionner</option>
                    <?php foreach ($GLOBALS['allComptesPLN'] as $compte): ?>
                        <?php
                        // Assumons que $ligne_p['compte'] doit contenir ID_Compte pour la sélection
                        // Si $ligne_p['compte'] contient Numero_Compte (Cpt), changez la condition en :
                        // ($ligne_p['compte'] == $compte['Cpt'])
                        $selected = (isset($ligne_p['compte']) && isset($compte['ID_Compte']) && $ligne_p['compte'] == $compte['ID_Compte']) ? 'selected' : '';
                        ?>
                        <option value="<?= htmlspecialchars($compte['ID_Compte'] ?? '') ?>" <?= $selected ?>>
                            <?= htmlspecialchars(($compte['Cpt'] ?? '') . ' - ' . ($compte['Lib'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="form-group">
                <label for="libelle_ligne_<?= htmlspecialchars($index) ?>">Libell&eacute Ligne <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="libelle_ligne_<?= htmlspecialchars($index) ?>" name="libelle_ligne[]" value="<?= htmlspecialchars($ligne_p['libelle_ligne'] ?? '') ?>" required>
            </div>
        </div>

    <div class="col-md-1 col-sm-4">
    <div class="form-group">
        <label for="an_<?= htmlspecialchars($index) ?>">Agence</label>
        <select class="form-control" id="an_<?= htmlspecialchars($index) ?>" name="an[]" style="width:100%;">
            <option value="">Choisir</option>
            <?php foreach ($agences as $agence): ?>
                <?php
                // Utilisez le code d'agence de l'utilisateur pour pré-sélectionner l'option
                $selectedAn = '';
                if (isset($agence['CodeAgenceSCE']) && $userAgencyCode == $agence['CodeAgenceSCE']) {
                    $selectedAn = 'selected';
                }
                ?>
               <option value="<?= htmlspecialchars($agence['CodeAgenceSCE'] ?? '') ?>" <?= $selectedAn ?>>
    <?= htmlspecialchars(($agence['CodeAgenceSCE'] ?? '') . ' - ' . ($agence['LibelleAgenceSCE'] ?? '')) ?>
</option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

        <div class="col-md-2 col-sm-6">
            <div class="form-group">
                <label for="contrepartie_<?= htmlspecialchars($index) ?>">Contrepartie</label>
                <select class="form-control contrepartie-select" id="contrepartie_<?= htmlspecialchars($index) ?>" name="contrepartie[]">
                    <option value="">S&eacutelectionner</option>
                    <?php foreach ($GLOBALS['allComptesPLN'] as $compte): ?>
                        <?php
                        // Assumons que $selected_contrepartie doit contenir ID_Compte pour la sélection
                        // Si $selected_contrepartie contient Numero_Compte (Cpt), changez la condition en :
                        // ($selected_contrepartie == $compte['Cpt'])
                        $selectedCp = (isset($selected_contrepartie) && isset($compte['ID_Compte']) && $selected_contrepartie == $compte['ID_Compte']) ? 'selected' : '';
                        ?>
                        <option value="<?= htmlspecialchars($compte['ID_Compte'] ?? '') ?>" <?= $selectedCp ?>>
                            <?= htmlspecialchars(($compte['Cpt'] ?? '') . ' - ' . ($compte['Lib'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="col-md-1 col-sm-6">
            <div class="form-group">
                <label for="debit_<?= htmlspecialchars($index) ?>">D&eacutebit</label>
                <input type="text" class="form-control debit-input" id="debit_<?= htmlspecialchars($index) ?>" name="debit[]" value="<?= htmlspecialchars($ligne_p['debit'] ?? '') ?>" placeholder="0,00">
            </div>
        </div>
        <div class="col-md-1 col-sm-6">
            <div class="form-group">
                <label for="credit_<?= htmlspecialchars($index) ?>">Cr&eacutedit</label>
                <input type="text" class="form-control credit-input" id="credit_<?= htmlspecialchars($index) ?>" name="credit[]" value="<?= htmlspecialchars($ligne_p['credit'] ?? '') ?>" placeholder="0,00">
            </div>
        </div>
        <div class="col-md-2 col-sm-12 text-right">
            <button type="button" class="btn btn-info btn-xs btn-contrepartie" style="margin-top: 25px;">
                <span class="glyphicon glyphicon-retweet"></span> Contrepartie
            </button>
            <button type="button" class="btn btn-danger btn-xs btn-remove-line" style="margin-top: 5px;">
                <span class="glyphicon glyphicon-trash"></span> Suppr.
            </button>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.compte-select').forEach(function (selectCompte) {
        selectCompte.addEventListener('change', function () {
            const index = selectCompte.id.split('_')[1]; // ex: compte_0 ? "0"
            const contrepartieSelect = document.getElementById('contrepartie_' + index);

            if (!contrepartieSelect) return;

            const selectedOption = selectCompte.options[selectCompte.selectedIndex];
            const compteText = selectedOption.textContent.trim(); // ex: "560441312900 - Libellé"

            // Si on détecte le compte 560441312900
            if (compteText.startsWith('56041312900')) {
                // Parcourir les options du champ contrepartie
                for (let option of contrepartieSelect.options) {
                    if (option.textContent.trim().startsWith('57512')) {
                        contrepartieSelect.value = option.value; // Sélectionne 57512
                        break;
                    }
                }
            }
        });
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.compte-select').forEach(function (selectCompte) {
        selectCompte.addEventListener('change', function () {
            const index = selectCompte.id.split('_')[1]; // ex: compte_0 ? "0"
            const contrepartieSelect = document.getElementById('contrepartie_' + index);

            if (!contrepartieSelect) return;

            const selectedOption = selectCompte.options[selectCompte.selectedIndex];
            const compteText = selectedOption.textContent.trim(); // ex: "560441312900 - Libellé"

            // Si on détecte le compte 560441312900
            if (compteText.startsWith('57512')) {
                // Parcourir les options du champ contrepartie
                for (let option of contrepartieSelect.options) {
                    if (option.textContent.trim().startsWith('56041312900')) {
                        contrepartieSelect.value = option.value; // Sélectionne 57512
                        break;
                    }
                }
            }
        });
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-contrepartie').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const line = btn.closest('.entry-line');
            if (!line) return;

            const index = line.getAttribute('data-index');

            // Champs à inverser
            const compteSelect = document.getElementById('compte_' + index);
            const contrepartieSelect = document.getElementById('contrepartie_' + index);
            const debitInput = document.getElementById('debit_' + index);
            const creditInput = document.getElementById('credit_' + index);

            if (!compteSelect || !contrepartieSelect || !debitInput || !creditInput) return;

            // Échanger les comptes
            const oldCompte = compteSelect.value;
            compteSelect.value = contrepartieSelect.value;
            contrepartieSelect.value = oldCompte;

            // Échanger les montants
            const oldDebit = debitInput.value;
            debitInput.value = creditInput.value;
            creditInput.value = oldDebit;
        });
    });
});
</script>

