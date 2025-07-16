<?php
// pages/analyse/index.php

// Démarrer la session si elle n'est pas déjà active
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once('../../templates/header.php');
// Inclure la navigation (assurez-vous que ce fichier existe et fonctionne correctement)
require_once('../../templates/navigation.php');

// Définir le titre de la page pour la balise <title>
$TITRE_PAGE = "Analyse des Risques Financiers | BailCompta 360";

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($TITRE_PAGE) ?></title>
    <link rel="shortcut icon" href="../../images/logo_bailcompta.png" type="image/x-icon">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-theme/0.1.0-beta.10/select2-bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/select2-bootstrap-5-theme.min.css">
    <link rel="stylesheet" href="../../css/select2.min.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 2rem 1rem 100px;
            position: relative;
            min-height: 100vh;
            background-color: #f8f9fa;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        form#riskForm {
            max-width: 1200px;
            width: 100%;
            margin-bottom: 80px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            border-radius: 0.5rem;
            background-color: #fff;
        }

        .select2-container .select2-selection--single {
            height: 38px;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            display: flex;
            align-items: center;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px;
            padding-left: 0.75rem;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }

        #menu {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: #ffffff;
            padding: 1rem;
            text-align: center;
            box-shadow: 0px -4px 10px rgba(0,0,0,0.1);
            box-sizing: border-box;
            z-index: 1000;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section h2 {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>

<body class="bg-light">
    <form id="riskForm" class="bg-white p-4">
        <div class="form-section">
            &nbsp;
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Informations Générales du Prêt</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <label for="transaction_id" class="form-label">ID de la transaction :</label>
                    <input type="text" id="transaction_id" name="transaction_id" class="form-control" placeholder="Ex: T001234">
                </div>

                <div class="col-md-4 mb-4">
                    <label for="transaction_amount" class="form-label">Montant du prêt (XAF) :</label>
                    <input type="number" id="transaction_amount" name="transaction_amount" min="0" step="1000" class="form-control" placeholder="Ex: 500000">
                </div>

                <div class="col-md-4 mb-4">
                    <label for="loan_purpose" class="form-label">Motif du prêt :</label>
                    <select id="loan_purpose" name="loan_purpose" class="form-control select2">
                        <option value="">Sélectionner un motif</option>
                        <option value="Immobilier">Immobilier (Achat/Construction)</option>
                        <option value="Automobile">Automobile (Achat véhicule)</option>
                        <option value="Personnel">Personnel (Consommation/Voyage)</option>
                        <option value="Éducation">Éducation (Frais de scolarité)</option>
                        <option value="Professionnel">Professionnel (Création/Extension entreprise)</option>
                        <option value="Santé">Santé (Frais médicaux)</option>
                        <option value="Autre">Autre</option>
                    </select>
                </div>

                <div class="col-md-4 mb-4">
                    <label for="loan_duration" class="form-label">Durée du prêt (mois) :</label>
                    <input type="number" id="loan_duration" name="loan_duration" min="1" max="360" class="form-control" placeholder="Ex: 12">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Informations Client</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <label for="customer_id" class="form-label">ID du client (ou RCCM) :</label>
                    <input type="text" id="customer_id" name="customer_id" class="form-control" placeholder="Ex: CUST007 ou RCCM12345">
                </div>
                <div class="col-md-4 mb-4">
                    <label for="age" class="form-label">Âge du client :</label>
                    <input type="number" id="age" name="age" min="18" max="99" class="form-control" placeholder="Ex: 35">
                </div>
                <div class="col-md-4 mb-4">
                    <label for="profession" class="form-label">Profession du client :</label>
                    <select id="profession" name="profession" class="form-control select2">
                        <option value="">Sélectionner une profession</option>
                        <option value="Fonctionnaire">Fonctionnaire</option>
                        <option value="Secteur privé">Salarié secteur privé</option>
                        <option value="Entrepreneur formel">Entrepreneur (formel)</option>
                        <option value="Secteur informel">Secteur informel</option>
                        <option value="Étudiant">Étudiant</option>
                        <option value="Retraité">Retraité</option>
                        <option value="Sans activité">Sans activité / Chômeur</option>
                        <option value="Autre">Autre</option>
                    </select>
                </div>
                <div class="col-md-4 mb-4">
                    <label for="nombre_emails" class="form-label">Nombre d'emails (historique) :</label>
                    <input type="number" id="nombre_emails" name="nombre_emails" min="0" class="form-control" placeholder="Ex: 2 (pour les relances)">
                    <p class="form-text text-muted">Nombre d'emails envoyés ou reçus, peut indiquer l'activité.</p>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Données Financières</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <label for="salaire_pension" class="form-label">Revenu mensuel net (Salaire/Pension) :</label>
                    <input type="number" id="salaire_pension" name="salaire_pension" min="0" step="1000" class="form-control" placeholder="Ex: 250000">
                </div>
                <div class="col-md-4 mb-4">
                    <label for="engagement_moyen" class="form-label">Engagement moyen en banque :</label>
                    <input type="number" id="engagement_moyen" name="engagement_moyen" min="0" step="100" class="form-control" placeholder="Ex: 15000">
                    <p class="form-text text-muted">Engagement moyen sur les 3 derniers mois.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <label for="debt_to_income_ratio" class="form-label">Ratio d'endettement actuel (Dette/Revenu) :</label>
                    <input type="number" id="debt_to_income_ratio" name="debt_to_income_ratio" min="0" step="0.01" max="1" class="form-control" placeholder="Ex: 0.30">
                    <p class="form-text text-muted">Ex: 0.30 pour 30%. Valeur maximale 1 (100%).</p>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Historique et Garanties</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <label for="credit_score" class="form-label">Score de crédit (BIC) :</label>
                    <input type="number" id="credit_score" name="credit_score" min="0" max="850" class="form-control" placeholder="Ex: 680 (BIC)">
                    <p class="form-text text-muted">Score fourni par le Bureau d'Information sur le Crédit.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <label for="credit_history" class="form-label">Historique de crédit (BIC) :</label>
                    <select id="credit_history" name="credit_history" class="form-control select2">
                        <option value="">Sélectionner un historique</option>
                        <option value="positif">Positif (Pas d'incidents)</option>
                        <option value="incidents_passes">Incidents passés (Résolus)</option>
                        <option value="retard_defaut">Retard/défaut de paiement (Actuel)</option>
                        <option value="aucun">Aucun historique connu</option>
                    </select>
                    <p class="form-text text-muted">Basé sur les données du BIC.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <label for="guarantee_type" class="form-label">Type de garantie :</label>
                    <select id="guarantee_type" name="guarantee_type" class="form-control select2">
                        <option value="">Sélectionner une garantie</option>
                        <option value="biens_fonciers">Biens fonciers (Terrain, Bâtiment)</option>
                        <option value="vehicules">Véhicules</option>
                        <option value="epargne_bloquee">Épargne bloquée</option>
                        <option value="cautionnement">Cautionnement (personnel)</option>
                        <option value="caution_solidaire">Caution solidaire (Entreprise)</option>
                        <option value="materiel">Matériel (Équipement professionnel)</option>
                        <option value="garantie_etat">Garantie par l'État (Nouveauté Afrique Centrale)</option>
                        <option value="aucune">Aucune garantie</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Frais et Commissions Liés au Prêt (Optionnel)</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <label for="frais_assurance" class="form-label">Frais d'assurance (XAF) :</label>
                    <input type="number" id="frais_assurance" name="frais_assurance" min="0" step="100" value="0" class="form-control">
                </div>
                <div class="col-md-4 mb-4">
                    <label for="frais_etudes_dossier" class="form-label">Frais d'études de dossier (XAF) :</label>
                    <input type="number" id="frais_etudes_dossier" name="frais_etudes_dossier" min="0" step="100" value="0" class="form-control">
                </div>
                <div class="col-md-4 mb-4">
                    <label for="commissions" class="form-label">Commissions diverses (XAF) :</label>
                    <input type="number" id="commissions" name="commissions" min="0" step="100" value="0" class="form-control">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Autres Facteurs</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <label for="nombre_mois_activite" class="form-label">Nombre de mois d'activité :</label>
                    <input type="number" id="nombre_mois_activite" name="nombre_mois_activite" min="0" class="form-control" placeholder="Ex: 12">
                    <p class="form-text text-muted">Nombre de mois d'activité professionnelle.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <label for="nombre_dependants" class="form-label">Nombre de personnes à charge (facultatif) :</label>
                    <input type="number" id="nombre_dependants" name="nombre_dependants" min="0" class="form-control" placeholder="Ex: 2">
                    <p class="form-text text-muted">Nombre de personnes dépendantes financièrement.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <label for="stabilite_emploi" class="form-label">Stabilité de l'emploi :</label>
                    <select id="stabilite_emploi" name="stabilite_emploi" class="form-control select2">
                        <option value="">Sélectionner une option</option>
                        <option value="stable">Stable (CDI, Fonctionnaire)</option>
                        <option value="moyenne">Moyenne (CDD, Contrat temporaire)</option>
                        <option value="instable">Instable (Intérim, Saisonnier, CDI instable)</option>
                        <option value="inconnu">Inconnu</option>
                    </select>
                </div>
            </div>
        </div>

        <div id="fraisSupplementaires" class="col-12"></div>
        <button type="button" id="ajouterFrais" class="btn btn-success mb-4">Ajouter d'autres frais</button>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 pt-3 border-top border-secondary">
            <button class="btn btn-primary btn-lg" type="button" id="analyserRisque">
                Analyser le risque
            </button>
            <button class="btn btn-secondary btn-lg" type="reset" id="annulerFormulaire">
                Annuler
            </button>
            <div id="resultatAnalyse" class="mt-2 mt-md-0 ms-md-3 text-xl font-bold text-warning text-center text-md-left">
            </div>
        </div>
    </form>

    <div id="graphiqueRisque" class="bg-white shadow rounded p-4 mt-4" style="display: none;">
        <h2 class="text-xl font-semibold text-center text-gray-800 mb-4">Répartition du Risque Estimé</h2>
        <canvas id="risqueChart" width="400" height="200"></canvas>
    </div>

    <script src="../js/chart.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/i18n/fr.js"></script>
    <script src="../../js/jquery-3.7.1.min.js"></script>
    <script src="../../js/bootstrap.min.js"></script>
    <script src="../../js/bootstrap.bundle.min.js"></script>
    <script src="../../js/select2.min.js"></script>
    <script>
        // Initialisation de Select2
        $(document).ready(function() {
            $('.select2').select2({
                theme: "bootstrap-5",
                language: "fr"
            });
        });

        const fraisSupplementairesDiv = document.getElementById('fraisSupplementaires');
        const ajouterFraisBouton = document.getElementById('ajouterFrais');
        const analyserRisqueBouton = document.getElementById('analyserRisque');
        const resultatAnalyseDiv = document.getElementById('resultatAnalyse');
        const graphiqueRisqueDiv = document.getElementById('graphiqueRisque');

        let fraisIndex = 0;

        ajouterFraisBouton.addEventListener('click', () => {
            fraisIndex++;
            const nouveauChampFrais = document.createElement('div');
            nouveauChampFrais.className = 'mb-4 col-12 col-md-6';
            nouveauChampFrais.innerHTML = `
                <label for="autre_frais_nom_${fraisIndex}" class="form-label">Autre frais ${fraisIndex} :</label>
                <div class="d-flex gap-2">
                    <input type="text" id="autre_frais_nom_${fraisIndex}" name="autre_frais_nom_${fraisIndex}" class="form-control" placeholder="Nom du frais">
                    <input type="number" id="autre_frais_valeur_${fraisIndex}" name="autre_frais_valeur_${fraisIndex}" min="0" step="100" class="form-control" placeholder="Montant (XAF)">
                </div>
            `;
            fraisSupplementairesDiv.appendChild(nouveauChampFrais);
        });

        function collectFormData() {
            const formData = {
                transaction_id: document.getElementById('transaction_id').value || '',
                customer_id: document.getElementById('customer_id').value || '',
                transaction_amount: parseFloat(document.getElementById('transaction_amount').value) || 0,
                loan_duration: parseInt(document.getElementById('loan_duration').value) || 0,
                salaire_pension: parseFloat(document.getElementById('salaire_pension').value) || 0,
                engagement_moyen: parseFloat(document.getElementById('engagement_moyen').value) || 0,
                frais_assurance: parseFloat(document.getElementById('frais_assurance').value) || 0,
                frais_etudes_dossier: parseFloat(document.getElementById('frais_etudes_dossier').value) || 0,
                commissions: parseFloat(document.getElementById('commissions').value) || 0,
                age: parseInt(document.getElementById('age').value) || 0,
                profession: document.getElementById('profession').value || '',
                nombre_emails: parseInt(document.getElementById('nombre_emails').value) || 0,
                credit_score: parseInt(document.getElementById('credit_score').value) || 0,
                debt_to_income_ratio: parseFloat(document.getElementById('debt_to_income_ratio').value) || 0,
                loan_purpose: document.getElementById('loan_purpose').value || '',
                credit_history: document.getElementById('credit_history').value || '',
                guarantee_type: document.getElementById('guarantee_type').value || '',
                nombre_mois_activite: parseInt(document.getElementById('nombre_mois_activite').value) || 0,
                nombre_dependants: parseInt(document.getElementById('nombre_dependants').value) || 0,
                stabilite_emploi: document.getElementById('stabilite_emploi').value || '',
            };

            for (let i = 1; i <= fraisIndex; i++) {
                const nomFraisInput = document.getElementById(`autre_frais_nom_${i}`);
                const valeurFraisInput = document.getElementById(`autre_frais_valeur_${i}`);
                if (nomFraisInput && valeurFraisInput) {
                    const nomFrais = nomFraisInput.value;
                    const valeurFrais = parseFloat(valeurFraisInput.value);
                    if (nomFrais && !isNaN(valeurFrais)) {
                        formData[`autre_frais_${nomFrais.replace(/\s+/g, '_').toLowerCase()}`] = valeurFrais;
                    }
                }
            }
            return formData;
        }

        let riskChartInstance = null;
        function displayRiskChart(riskLevel) {
            const ctx = document.getElementById('risqueChart').getContext('2d');
            graphiqueRisqueDiv.style.display = 'block';

            if (riskChartInstance) {
                riskChartInstance.destroy();
            }

            const riskLevels = ['Faible', 'Moyen', 'Élevé', 'Très élevé'];
            const dataValues = [0, 0, 0, 0];

            let currentRiskIndex = riskLevels.indexOf(riskLevel);
            if (currentRiskIndex !== -1) {
                dataValues[currentRiskIndex] = 100;
            } else {
                dataValues[2] = 50;
                dataValues[0] = 50;
            }

            riskChartInstance = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: riskLevels,
                    datasets: [{
                        label: 'Répartition du Risque',
                        data: dataValues,
                        backgroundColor: [
                            'rgba(76, 175, 80, 0.7)',
                            'rgba(255, 193, 7, 0.7)',
                            'rgba(255, 87, 34, 0.7)',
                            'rgba(220, 20, 60, 0.7)'
                        ],
                        borderColor: [
                            'rgba(76, 175, 80, 1)',
                            'rgba(255, 193, 7, 1)',
                            'rgba(255, 87, 34, 1)',
                            'rgba(220, 20, 60, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: {
                                    size: 14
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: `Niveau de Risque: ${riskLevel}`,
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            color: '#333'
                        }
                    }
                }
            });
        }

        analyserRisqueBouton.addEventListener('click', () => {
            const formData = collectFormData();

            if (!formData.transaction_amount || isNaN(formData.transaction_amount) || formData.transaction_amount <= 0) {
                resultatAnalyseDiv.textContent = "Erreur: Le montant du prêt est obligatoire et doit être positif.";
                resultatAnalyseDiv.classList.add('text-danger');
                graphiqueRisqueDiv.style.display = 'none';
                return;
            }
            if (!formData.customer_id) {
                resultatAnalyseDiv.textContent = "Erreur: L'ID du client est obligatoire.";
                resultatAnalyseDiv.classList.add('text-danger');
                graphiqueRisqueDiv.style.display = 'none';
                return;
            }

            resultatAnalyseDiv.classList.remove('text-danger');

            $.ajax({
                url: 'process_risk.php',
                type: 'POST',
                dataType: 'json',
                data: formData,
                beforeSend: function() {
                    resultatAnalyseDiv.textContent = "Analyse en cours...";
                    resultatAnalyseDiv.classList.remove('text-success', 'text-warning', 'text-danger');
                    resultatAnalyseDiv.classList.add('text-primary');
                    graphiqueRisqueDiv.style.display = 'none';
                },
                success: function(response) {
                    if (response.status === 'success') {
                        const result = response.data;
                        resultatAnalyseDiv.innerHTML = `<span class="text-primary">Niveau de risque:</span> <span class="fw-bold text-${result.riskLevel === 'Faible' ? 'success' : (result.riskLevel === 'Moyen' ? 'warning' : 'danger')}">${result.riskLevel}</span><br>
                                            <span class="text-primary">Action recommandée:</span> ${result.action}<br>
                                            <span class="text-primary">Facteurs de risque:</span> ${result.riskFactors.join(', ') || 'Aucun facteur de risque identifié'}`;
                        resultatAnalyseDiv.classList.remove('text-primary');
                        displayRiskChart(result.riskLevel);
                    } else {
                        resultatAnalyseDiv.textContent = "Erreur lors de l'analyse: " + (response.message || "Une erreur inconnue est survenue.");
                        resultatAnalyseDiv.classList.add('text-danger');
                        resultatAnalyseDiv.classList.remove('text-primary');
                        graphiqueRisqueDiv.style.display = 'none';
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    resultatAnalyseDiv.textContent = "Erreur de communication avec le serveur: " + textStatus + " - " + errorThrown;
                    resultatAnalyseDiv.classList.add('text-danger');
                    resultatAnalyseDiv.classList.remove('text-primary');
                    graphiqueRisqueDiv.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
<?php
require_once('../../templates/footer.php');
?>
