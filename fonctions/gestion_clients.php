<?php
// fonctions/gestion_clients.php

require_once("database.php");

/**
 * Récupère la liste de tous les clients, en utilisant les nouveaux noms de colonnes.
 *
 * @param PDO $pdo L'objet de connexion à la base de données.
 * @return array Un tableau de clients.
 */
function listerClients(PDO $pdo): array {
    try {
        $sql = "SELECT id_client, nom_ou_raison_sociale, nom_abrege, email, telephone FROM clients ORDER BY nom_ou_raison_sociale ASC";
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la récupération de la liste des clients: " . $e->getMessage());
        return [];
    }
}

/**
 * Crée un nouveau client (particulier ou entreprise) dans la base de données.
 *
 * @param PDO $pdo L'objet de connexion à la base de données.
 * @param array $data Un tableau associatif des données du client.
 * @return bool Vrai si l'insertion a réussi, faux sinon.
 */
function creerClient(PDO $pdo, array $data): bool {
    // Déterminer le type de client pour choisir les champs à insérer
    if (($data['type_client'] ?? 'particulier') === 'particulier') {
        $sql = "INSERT INTO clients (
            nom_ou_raison_sociale, nom_abrege, email, telephone, adresse, 
            date_naissance, profession, revenu_mensuel, type_client, date_adhesion
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $data['nom_ou_raison_sociale'] ?? null,
            $data['nom_abrege'] ?? null,
            $data['email'] ?? null,
            $data['telephone'] ?? null,
            $data['adresse'] ?? null,
            $data['date_naissance'] ?? null,
            $data['profession'] ?? null,
            $data['revenu_mensuel'] ?? null,
            'particulier',
            date('Y-m-d')
        ];
    } else { // 'entreprise'
        $sql = "INSERT INTO clients (
            nom_ou_raison_sociale, nom_abrege, type_client, matricule, numero_registre_commerce, 
            date_creation, siege_social, forme_juridique, numero_contribuable, code_nature_clientele, 
            groupe_activite, secteur_institutionnel, code_ville, objet_social, site_web, 
            adresse_email_contact, boite_postale, telephone, email
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $data['nom_ou_raison_sociale'] ?? null,
            $data['nom_abrege'] ?? null,
            'entreprise',
            $data['matricule'] ?? null,
            $data['numero_registre_commerce'] ?? null,
            $data['date_creation'] ?? null,
            $data['siege_social'] ?? null,
            $data['forme_juridique'] ?? null,
            $data['numero_contribuable'] ?? null,
            $data['code_nature_clientele'] ?? null,
            $data['groupe_activite'] ?? null,
            $data['secteur_institutionnel'] ?? null,
            $data['code_ville'] ?? null,
            $data['objet_social'] ?? null,
            $data['site_web'] ?? null,
            $data['adresse_email_contact'] ?? null,
            $data['boite_postale'] ?? null,
            $data['telephone'] ?? null, // Le téléphone/email peut être null pour une entreprise si les champs ne sont pas présents sur le formulaire
            $data['email'] ?? null
        ];
    }

    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la création du client: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les détails d'un client par son ID, en utilisant les nouvelles colonnes.
 *
 * @param PDO $pdo L'objet de connexion à la base de données.
 * @param int $id L'ID du client à récupérer.
 * @return array|bool Un tableau associatif des détails du client, ou false si non trouvé.
 */
function trouverClientParId(PDO $pdo, int $id): array|bool {
    $sql = "SELECT * FROM clients WHERE id_client = ?";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    } catch (PDOException $e) {
        error_log("Erreur de récupération d'un client : " . $e->getMessage());
        return false;
    }
}

/**
 * Supprime un client de la base de données par son ID.
 *
 * @param PDO $pdo L'objet de connexion à la base de données.
 * @param int $id_client L'ID du client à supprimer.
 * @return bool Vrai si la suppression a réussi, faux sinon.
 */
function supprimerClient(PDO $pdo, int $id_client): bool {
    try {
        $pdo->beginTransaction();

        // Vérifier si le client a des enregistrements associés
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM prets WHERE id_client = ?");
        $stmt_check->execute([$id_client]);
        if ($stmt_check->fetchColumn() > 0) {
            $pdo->rollBack();
            return false;
        }

        $stmt = $pdo->prepare("DELETE FROM clients WHERE id_client = ?");
        $stmt->execute([$id_client]);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erreur PDO lors de la suppression du client (ID: $id_client): " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les détails d'un client par son nom ou sa raison sociale.
 *
 * @param PDO $pdo L'objet de connexion à la base de données.
 * @param string $searchTerm Le nom ou la raison sociale à rechercher.
 * @return array|bool Un tableau associatif des détails du client, ou false si non trouvé.
 */
function trouverClientParNomOuRaison(PDO $pdo, string $searchTerm): array|bool {
    $sql = "SELECT id_client, nom_ou_raison_sociale, nom_abrege, email, telephone FROM clients WHERE nom_ou_raison_sociale LIKE ? OR nom_abrege LIKE ?";
    try {
        $stmt = $pdo->prepare($sql);
        $searchPattern = '%' . $searchTerm . '%';
        $stmt->execute([$searchPattern, $searchPattern]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    } catch (PDOException $e) {
        error_log("Erreur de recherche d'un client : " . $e->getMessage());
        return false;
    }
}

/**
 * Met à jour les informations d'un client dans la base de données.
 *
 * @param PDO $pdo L'objet de connexion à la base de données.
 * @param int $id_client L'ID du client à mettre à jour.
 * @param array $donnees Un tableau associatif des nouvelles données.
 * @return bool Vrai si la mise à jour a réussi, faux sinon.
 */
function mettreAJourClient(PDO $pdo, int $id_client, array $donnees): bool {
    // Récupérer le type de client actuel
    $client_existant = trouverClientParId($pdo, $id_client);
    if (!$client_existant) {
        return false;
    }
    $type_client = $client_existant['type_client'];

    if ($type_client === 'particulier') {
        $sql = "UPDATE clients SET 
            nom_ou_raison_sociale = ?, nom_abrege = ?, date_naissance = ?, sexe = ?,
            telephone = ?, adresse = ?, email = ?
            WHERE id_client = ?";
        $params = [
            $donnees['nom_ou_raison_sociale'] ?? null,
            $donnees['nom_abrege'] ?? null,
            $donnees['date_naissance'] ?? null,
            $donnees['sexe'] ?? null,
            $donnees['telephone'] ?? null,
            $donnees['adresse'] ?? null,
            $donnees['email'] ?? null,
            $id_client
        ];
    } else { // Entreprise
        $sql = "UPDATE clients SET 
            nom_ou_raison_sociale = ?, nom_abrege = ?, matricule = ?, numero_registre_commerce = ?, 
            date_creation = ?, siege_social = ?, forme_juridique = ?, numero_contribuable = ?,
            code_nature_clientele = ?, groupe_activite = ?, secteur_institutionnel = ?,
            code_ville = ?, objet_social = ?, site_web = ?, adresse_email_contact = ?,
            boite_postale = ?, telephone = ?, email = ?
            WHERE id_client = ?";
        $params = [
            $donnees['nom_ou_raison_sociale'] ?? null,
            $donnees['nom_abrege'] ?? null,
            $donnees['matricule'] ?? null,
            $donnees['numero_registre_commerce'] ?? null,
            $donnees['date_creation'] ?? null,
            $donnees['siege_social'] ?? null,
            $donnees['forme_juridique'] ?? null,
            $donnees['numero_contribuable'] ?? null,
            $donnees['code_nature_clientele'] ?? null,
            $donnees['groupe_activite'] ?? null,
            $donnees['secteur_institutionnel'] ?? null,
            $donnees['code_ville'] ?? null,
            $donnees['objet_social'] ?? null,
            $donnees['site_web'] ?? null,
            $donnees['adresse_email_contact'] ?? null,
            $donnees['boite_postale'] ?? null,
            $donnees['telephone'] ?? null,
            $donnees['email'] ?? null,
            $id_client
        ];
    }

    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Erreur PDO lors de la mise à jour du client (ID: $id_client): " . $e->getMessage());
        return false;
    }
}


?>