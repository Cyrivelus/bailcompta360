<?php
// fonctions/gestion_brouillard.php

/**
 * Fetches all entries from the Brl table.
 *
 * @return array An array of brouillard entries.
 */
function getBrlEntries() {
    // We'll need access to the database connection functions.
    // The database.php file is already included in the calling script.
    
    // Check if the required function exists
    if (!function_exists('executeQuery')) {
        return [];
    }

    // Initialize the query
    $sql = "SELECT 
                Id, 
                Jal, 
                Pce, 
                Dte, 
                Cpt, 
                Libellé, 
                Deb, 
                Cre, 
                Lettrage
            FROM 
                Brl
				order by Jal desc";
				

    try {
        $stmt = executeQuery($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Log the error for debugging, but return an empty array
        error_log("Erreur dans getBrlEntries: " . $e->getMessage());
        return [];
    }
}