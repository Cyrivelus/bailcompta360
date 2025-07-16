<?php
// pages/aide/index.php

$titre = 'Aide et Support'; // Titre spécifique pour la page d'aide
$current_page = basename($_SERVER['PHP_SELF']); // Pour la navigation active

// Inclure les fichiers nécessaires
// require_once '../../fonctions/database.php'; // Décommentez si besoin d'accès DB
// require_once '../../fonctions/fonctions_communes.php'; // Pour d'autres fonctions partagées

// Inclure les templates (header et navigation)
// Ces fichiers sont supposés gérer le démarrage de session et la redirection si non connecté.
require_once '../../templates/header.php';   // Inclut <head>, <body>, <header>
require_once '../../templates/navigation.php'; // Inclut la navigation principale
?>

<div class="container">
    <div class="page-header">
        <h1><?php echo htmlspecialchars($titre); ?> <small>BailCompta 360</small></h1>
    </div>

    <p class="lead">Bienvenue dans la section d'aide et de support de BailCompta 360. Vous trouverez ici des informations et des guides pour vous aider à utiliser efficacement toutes les fonctionnalités de l'application.</p>

    <div class="row">
        <div class="col-md-4">
            <h3>Table des matières</h3>
            <div class="list-group">
                <a href="#getting-started" class="list-group-item">1. Premiers pas et Navigation</a>
                <a href="#ecritures" class="list-group-item">2. Gestion des Écritures Comptables</a>
                <a href="#emprunts" class="list-group-item">3. Suivi des Emprunts</a>
                <a href="#factures" class="list-group-item">4. Intégration des Factures</a>
                <a href="#importation" class="list-group-item">5. Importation de Données (Relevés Bancaires)</a>
                <a href="#generation" class="list-group-item">6. Génération de Fichiers (ex: ABS)</a>
                <a href="#utilisateurs-profils" class="list-group-item">7. Gestion des Utilisateurs et Profils</a>
                <a href="#habilitations" class="list-group-item">8. Gestion des Habilitations</a>
                <a href="#faq" class="list-group-item">09. Questions Fréquemment Posées (FAQ)</a>
                <a href="#contact" class="list-group-item">10. Contacter le Support</a>
            </div>
        </div>

        <div class="col-md-8">
            <article>
                <section id="getting-started" style="padding-top: 70px; margin-top: -70px;"> <h3>1. Premiers pas et Navigation</h3>
                    <p>Cette section vous guide à travers les étapes initiales pour commencer avec BailCompta 360. Découvrez comment vous connecter, comprendre le tableau de bord principal et naviguer efficacement entre les différents modules de l'application.</p>
                    <p><em>(Contenu détaillé à ajouter ici : captures d'écran, description des menus, etc.)</em></p>
                </section>
                <hr>

                <section id="ecritures" style="padding-top: 70px; margin-top: -70px;">
                    <h3>2. Gestion des Écritures Comptables</h3>
                    <p>Apprenez tout sur la saisie manuelle des écritures, la consultation du journal, la modification et la suppression d'écritures. Comprenez comment les écritures sont structurées et comment utiliser les outils de recherche et de filtrage.</p>
                    <p><a href="../ecritures/" class="btn btn-info btn-xs">Accéder à la Gestion des Écritures</a></p>
                    <p><em>(Contenu détaillé à ajouter ici : guide étape par étape pour la saisie, explication des champs, etc.)</em></p>
                </section>
                <hr>

                <section id="emprunts" style="padding-top: 70px; margin-top: -70px;">
                    <h3>3. Suivi des Emprunts</h3>
                    <p>Découvrez comment enregistrer de nouveaux emprunts, suivre les échéanciers d'amortissement, visualiser les détails de chaque prêt et gérer les remboursements. Cette section couvre également la génération des écritures liées aux emprunts.</p>
                    <p><a href="../emprunts/" class="btn btn-info btn-xs">Accéder au Suivi des Emprunts</a></p>
                    <p><em>(Contenu détaillé à ajouter ici : formulaire d'ajout d'emprunt, tableau d'amortissement, etc.)</em></p>
                </section>
                <hr>

                <section id="factures" style="padding-top: 70px; margin-top: -70px;">
                    <h3>4. Intégration des Factures</h3>
                    <p>Cette section explique comment intégrer et gérer les factures clients et fournisseurs. Apprenez à enregistrer les factures, les lier à des écritures comptables et suivre leur statut.</p>
                    <p><a href="../factures/listes_factures.php" class="btn btn-info btn-xs">Accéder à la Gestion des Factures</a></p>
                    <p><em>(Contenu détaillé à ajouter ici : processus d'intégration, types de factures, etc.)</em></p>
                </section>
                <hr>

                <section id="importation" style="padding-top: 70px; margin-top: -70px;">
                    <h3>5. Importation de Données (Relevés Bancaires)</h3>
                    <p>Instructions pour importer des données externes, telles que les relevés bancaires, afin de faciliter la réconciliation et la saisie des opérations. Formats supportés et étapes du processus d'importation.</p>
                    <p><a href="../import/" class="btn btn-info btn-xs">Accéder à l'Importation de Données</a></p>
                    <p><em>(Contenu détaillé à ajouter ici : formats CSV/Excel, mapping des colonnes, etc.)</em></p>
                </section>
                <hr>

                <section id="generation" style="padding-top: 70px; margin-top: -70px;">
                    <h3>6. Génération de Fichiers (ex: ABS)</h3>
                    <p>Comment utiliser BailCompta 360 pour générer des fichiers spécifiques, comme les fichiers au format ABS 2000 pour les échanges bancaires ou d'autres exports de données nécessaires pour vos opérations.</p>
                    <p><a href="../generation/" class="btn btn-info btn-xs">Accéder à la Génération de Fichiers</a></p>
                    <p><em>(Contenu détaillé à ajouter ici : configuration des exports, types de fichiers, etc.)</em></p>
                </section>
                <hr>

                <section id="utilisateurs-profils" style="padding-top: 70px; margin-top: -70px;">
                    <h3>7. Gestion des Utilisateurs et Profils (pour Administrateurs)</h3>
                    <p>Guide destiné aux administrateurs pour la création, la modification et la suppression des comptes utilisateurs. Comprend également la gestion des profils utilisateurs et l'assignation des rôles.</p>
                    <p><a href="../admin/utilisateurs/" class="btn btn-info btn-xs">Gérer les Utilisateurs</a> | <a href="../admin/profils/" class="btn btn-info btn-xs">Gérer les Profils</a></p>
                    <p><em>(Contenu détaillé à ajouter ici : gestion des mots de passe, rôles disponibles, etc.)</em></p>
                </section>
                <hr>

                <section id="habilitations" style="padding-top: 70px; margin-top: -70px;">
                    <h3>8. Gestion des Habilitations (Droits d'accès - pour Administrateurs)</h3>
                    <p>Explication du système d'habilitations de BailCompta 360. Comment définir et attribuer les droits d'accès spécifiques aux différentes fonctionnalités pour chaque profil ou utilisateur.</p>
                    <p><a href="../admin/habilitations/" class="btn btn-info btn-xs">Gérer les Habilitations</a></p>
                    <p><em>(Contenu détaillé à ajouter ici : granularité des droits, impact sur l'interface utilisateur, etc.)</em></p>
                </section>
                <hr>

                <section id="faq" style="padding-top: 70px; margin-top: -70px;">
                    <h3>09. Questions Fréquemment Posées (FAQ)</h3>
                    <p>Retrouvez ici les réponses aux questions les plus courantes posées par les utilisateurs de BailCompta 360. Cette section est mise à jour régulièrement.</p>
                    <p><em>(Contenu détaillé à ajouter ici : Q1, R1; Q2, R2; etc.)</em></p>
                    </section>
                <hr>

            <section id="contact" style="padding-top: 70px; margin-top: -70px;">
    <h3>10. Contacter le Support</h3>
    <p>Si vous ne trouvez pas de réponse à votre question dans cette aide ou si vous rencontrez un problème technique, notre équipe de support est disponible pour vous accompagner en présentiel.</p>
    
    <p>Modalité de contact :</p>
    <ul>
        <li><strong>Support en présentiel uniquement</strong>, au sein de nos locaux.</li>
        <li><strong>Horaires d’accueil :</strong> du lundi au vendredi, de 8h à 17h.</li>
        <!-- Ajoutez une adresse si vous souhaitez la préciser -->
        <!-- <li>Adresse : Bâtiment administratif, 2e étage, Bureau 204</li> -->
    </ul>

    <p><em>Merci de vous présenter à l’accueil ou de prendre rendez-vous directement sur place.</em></p>
</section>


            </article>
        </div>
    </div>
</div>

<?php
// Inclure le footer
require_once '../../templates/footer.php'; 
?>