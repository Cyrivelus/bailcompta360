<?php
// fonctions/plan_comptable/import_pcg.php

/**
 * Ce fichier contient les fonctions backend pour l'importation d'un plan comptable
 * à partir d'un fichier CSV.
 */

// Inclure la connexion à la base de données.
require_once(__DIR__ . '/../database.php');

/**
 * Lit un fichier CSV et insère les comptes comptables dans la base de données.
 * Le format attendu est une ligne par compte avec le numéro et le libellé.
 * * @param string $file_path Le chemin d'accès temporaire du fichier CSV téléchargé.
 * @return array Un tableau associatif avec 'success' (bool) et 'message' (string) ou
 * le nombre de lignes traitées.
 */
function import_pcg_csv($file_path) {
    $conn = connect_to_database();
    
    // Désactiver l'auto-commit pour améliorer les performances de l'insertion en masse
    $conn->autocommit(FALSE);

    $nombre_lignes = 0;
    
    // Ouvre le fichier en mode lecture.
    if (($handle = fopen($file_path, "r")) !== FALSE) {
        // Préparation de la requête d'insertion.
        // Utilisation de ON DUPLICATE KEY UPDATE pour gérer les doublons (si le numéro de compte existe déjà, il est mis à jour).
        $sql = "INSERT INTO comptes_comptables (numero_compte, libelle_compte) VALUES (?, ?) ON DUPLICATE KEY UPDATE libelle_compte = VALUES(libelle_compte)";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            fclose($handle);
            return ['success' => false, 'message' => "Erreur de préparation de la requête : " . $conn->error];
        }

        // Sauter la première ligne si elle contient des en-têtes
        fgetcsv($handle, 1000, ",");

        // Parcourir chaque ligne du fichier CSV.
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // S'assurer que la ligne a au moins deux colonnes
            if (count($data) >= 2) {
                $numero_compte = trim($data[0]);
                $libelle_compte = trim($data[1]);
                
                // Valider les données de base
                if (!empty($numero_compte) && !empty($libelle_compte)) {
                    // Lier les paramètres à la requête préparée.
                    $stmt->bind_param("ss", $numero_compte, $libelle_compte);
                    
                    // Exécuter la requête.
                    if ($stmt->execute()) {
                        $nombre_lignes++;
                    } else {
                        // En cas d'erreur d'exécution, on annule tout
                        $conn->rollback();
                        fclose($handle);
                        return ['success' => false, 'message' => "Erreur d'exécution pour le compte {$numero_compte} : " . $stmt->error];
                    }
                }
            }
        }
        
        // Fermer le statement et le fichier.
        $stmt->close();
        fclose($handle);
        
        // Valider toutes les transactions.
        $conn->commit();

        // Réactiver l'auto-commit.
        $conn->autocommit(TRUE);
        $conn->close();
        
        return ['success' => true, 'nombre_lignes' => $nombre_lignes];
    } else {
        return ['success' => false, 'message' => "Impossible d'ouvrir le fichier CSV."];
    }
}