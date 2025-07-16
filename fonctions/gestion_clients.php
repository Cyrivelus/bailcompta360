<?php
// fonctions/gestion_clients.php

require_once("database.php"); // Inclure le fichier de connexion à la base de données

/**
 * Récupère la liste des clients avec pagination.
 *
 * @param PDO   $db         L'objet de connexion à la base de données.
 * @param int   $page       Le numéro de la page à afficher (par défaut 1).
 * @param int   $perPage    Le nombre de clients à afficher par page (par défaut 10).
 * @param string|null $searchTerm Terme de recherche optionnel pour filtrer les clients.
 * @return array Un tableau associatif contenant :
 * - 'clients'    : Un tableau des informations des clients (tableaux associatifs).
 * - 'total'      : Le nombre total de clients (après filtrage si un terme est fourni).
 * - 'totalPages' : Le nombre total de pages.
 */
function getClients(PDO $pdo, int $page = 1, int $perPage = 10, ?string $searchTerm = null): array
{
    $offset = ($page - 1) * $perPage;
    $conditions = [];
    $params = [
        ':limit' => $perPage,
        ':offset' => $offset,
    ];
    $whereClause = "";

    if ($searchTerm) {
        $conditions[] = "(nom_commercial LIKE :term OR nom_legal LIKE :term OR adresse LIKE :term OR code_postal LIKE :term OR ville LIKE :term OR pays LIKE :term OR numero_telephone LIKE :term OR adresse_email LIKE :term OR numero_identification_fiscale LIKE :term OR code_comptable LIKE :term)";
        $params[':term'] = '%' . $searchTerm . '%';
        $whereClause = "WHERE " . implode(" AND ", $conditions);
    }

    $sql = "SELECT ID_Tiers, type_tiers, nom_commercial, nom_legal, adresse, code_postal, ville, pays, numero_telephone, adresse_email, numero_identification_fiscale, code_comptable
            FROM Tiers
            WHERE type_tiers = 'Client'
            $whereClause
            ORDER BY nom_commercial
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sqlTotal = "SELECT COUNT(*)
                 FROM Tiers
                 WHERE type_tiers = 'Client'
                 $whereClause";

    $stmtTotal = $pdo->prepare($sqlTotal);
    $stmtTotal->execute(array_slice($params, 0, -2, true)); // Exclude limit and offset for total count
    $total = $stmtTotal->fetchColumn();
    $totalPages = ceil($total / $perPage);

    return [
        'clients' => $clients,
        'total' => $total,
        'totalPages' => $totalPages,
    ];
}

/**
 * Récupère les détails d'un client spécifique.
 *
 * @param PDO $db L'objet de connexion à la base de données.
 * @param int $id L'ID du client à récupérer.
 * @return array|bool Un tableau associatif contenant les détails du client,
 * ou false si le client n'est pas trouvé.
 */
function getDetailsClient(PDO $pdo, int $id): array|bool
{
    $sql = "SELECT ID_Tiers, type_tiers, nom_commercial, nom_legal, adresse, code_postal, ville, pays, numero_telephone, adresse_email, numero_identification_fiscale, code_comptable
            FROM Tiers
            WHERE ID_Tiers = :id AND type_tiers = 'Client'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
}

/**
 * Ajoute un nouveau client.
 *
 * @param PDO   $db          L'objet de connexion à la base de données.
 * @param array $data        Un tableau associatif contenant les informations du client :
 * - 'nom_commercial' : Le nom commercial du client (obligatoire).
 * - 'nom_legal'    : Le nom légal du client.
 * - 'adresse'       : L'adresse du client.
 * - 'code_postal'   : Le code postal du client.
 * - 'ville'         : La ville du client.
 * - 'pays'          : Le pays du client.
 * - 'numero_telephone': Le numéro de téléphone du client.
 * - 'adresse_email' : L'adresse email du client.
 * - 'numero_identification_fiscale': Le numéro d'identification fiscale du client.
 * - 'code_comptable'  : Le code comptable du client.
 * @return bool True en cas de succès, false en cas d'erreur.
 */
function ajouterClient(PDO $pdo, array $data): bool
{
    $sql = "INSERT INTO Tiers (type_tiers, nom_commercial, nom_legal, adresse, code_postal, ville, pays, numero_telephone, adresse_email, numero_identification_fiscale, code_comptable)
            VALUES ('Client', :nom_commercial, :nom_legal, :adresse, :code_postal, :ville, :pays, :numero_telephone, :adresse_email, :numero_identification_fiscale, :code_comptable)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':nom_commercial', $data['nom_commercial']);
    $stmt->bindParam(':nom_legal', $data['nom_legal']);
    $stmt->bindParam(':adresse', $data['adresse']);
    $stmt->bindParam(':code_postal', $data['code_postal']);
    $stmt->bindParam(':ville', $data['ville']);
    $stmt->bindParam(':pays', $data['pays']);
    $stmt->bindParam(':numero_telephone', $data['numero_telephone']);
    $stmt->bindParam(':adresse_email', $data['adresse_email']);
    $stmt->bindParam(':numero_identification_fiscale', $data['numero_identification_fiscale']);
    $stmt->bindParam(':code_comptable', $data['code_comptable']);
    return $stmt->execute();
}

/**
 * Modifie les informations d'un client existant.
 *
 * @param PDO   $db          L'objet de connexion à la base de données.
 * @param int   $id          L'ID du client à modifier.
 * @param array $data        Un tableau associatif contenant les nouvelles informations du client.
 * (Les clés sont les mêmes que pour la fonction ajouterClient).
 * @return bool True en cas de succès, false en cas d'erreur.
 */
function modifierClient(PDO $pdo, int $id, array $data): bool
{
    $sql = "UPDATE Tiers
            SET nom_commercial = :nom_commercial,
                nom_legal = :nom_legal,
                adresse = :adresse,
                code_postal = :code_postal,
                ville = :ville,
                pays = :pays,
                numero_telephone = :numero_telephone,
                adresse_email = :adresse_email,
                numero_identification_fiscale = :numero_identification_fiscale,
                code_comptable = :code_comptable
            WHERE ID_Tiers = :id AND type_tiers = 'Client'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':nom_commercial', $data['nom_commercial']);
    $stmt->bindParam(':nom_legal', $data['nom_legal']);
    $stmt->bindParam(':adresse', $data['adresse']);
    $stmt->bindParam(':code_postal', $data['code_postal']);
    $stmt->bindParam(':ville', $data['ville']);
    $stmt->bindParam(':pays', $data['pays']);
    $stmt->bindParam(':numero_telephone', $data['numero_telephone']);
    $stmt->bindParam(':adresse_email', $data['adresse_email']);
    $stmt->bindParam(':numero_identification_fiscale', $data['numero_identification_fiscale']);
    $stmt->bindParam(':code_comptable', $data['code_comptable']);
    return $stmt->execute();
}

/**
 * Supprime un client.
 *
 * @param PDO $db L'objet de connexion à la base de données.
 * @param int $id L'ID du client à supprimer.
 * @return bool True en cas de succès, false en cas d'erreur.
 */
function supprimerClient(PDO $pdo, int $id): bool
{
    $sql = "DELETE FROM Tiers WHERE ID_Tiers = :id AND type_tiers = 'Client'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    return $stmt->execute();
}

// Vous pouvez ajouter d'autres fonctions spécifiques à la gestion des clients ici