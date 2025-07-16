<?php

require_once 'database.php'; // Ensure your database connection is accessible
require_once 'gestion_ecritures.php'; // Assuming functions for creating/modifying entries are here
require_once 'gestion_logs.php';       // For logging actions and errors

/**
 * Adds a new recurring entry template to the database.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param string $description The description of the recurring entry.
 * @param int $journalId The ID of the journal this entry belongs to (e.g., 'OD' for Divers).
 * @param string $frequency The frequency of the entry (e.g., 'monthly', 'quarterly', 'annually').
 * @param string $startDate The date from which the recurring entry starts (YYYY-MM-DD).
 * @param string|null $endDate Optional: The date when the recurring entry stops (YYYY-MM-DD).
 * @param array $lines An array of entry lines: [['compte_id' => int, 'sens' => 'D'|'C', 'montant' => float], ...]
 * @param int $userId The ID of the user creating the template.
 * @return int|false The ID of the newly created recurring entry template, or false on failure.
 */
function addRecurringEntryTemplate(
    PDO $pdo,
    string $description,
    int $journalId,
    string $frequency,
    string $startDate,
    ?string $endDate,
    array $lines,
    int $userId
): int|false {
    try {
        $pdo->beginTransaction();

        // Insert into a new table: Recurring_Entry_Templates
        $stmt = $pdo->prepare("
            INSERT INTO Recurring_Entry_Templates
            (description, id_journal, frequency, start_date, end_date, created_by, created_at)
            VALUES (:description, :id_journal, :frequency, :start_date, :end_date, :created_by, NOW())
        ");
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':id_journal', $journalId, PDO::PARAM_INT);
        $stmt->bindParam(':frequency', $frequency, PDO::PARAM_STR);
        $stmt->bindParam(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->bindParam(':end_date', $endDate, PDO::PARAM_STR);
        $stmt->bindParam(':created_by', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $templateId = $pdo->lastInsertId();

        // Insert lines into a new table: Recurring_Entry_Template_Lines
        $stmtLine = $pdo->prepare("
            INSERT INTO Recurring_Entry_Template_Lines
            (id_template, id_compte, sens, montant)
            VALUES (:id_template, :id_compte, :sens, :montant)
        ");
        foreach ($lines as $line) {
            $stmtLine->bindParam(':id_template', $templateId, PDO::PARAM_INT);
            $stmtLine->bindParam(':id_compte', $line['compte_id'], PDO::PARAM_INT);
            $stmtLine->bindParam(':sens', $line['sens'], PDO::PARAM_STR);
            $stmtLine->bindParam(':montant', $line['montant'], PDO::PARAM_STR); // Store as string for precision
            $stmtLine->execute();
        }

        $pdo->commit();
        logActivity("Modèle d'écriture récurrente ID {$templateId} ajouté par Utilisateur ID {$userId}");
        return (int)$templateId;

    } catch (PDOException $e) {
        $pdo->rollBack();
        logError("Erreur PDO lors de l'ajout du modèle d'écriture récurrente: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        $pdo->rollBack();
        logError("Erreur lors de l'ajout du modèle d'écriture récurrente: " . $e->getMessage());
        return false;
    }
}

/**
 * Retrieves all recurring entry templates.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int|null $templateId Optional: ID of a specific template to retrieve.
 * @return array An array of recurring entry templates.
 */
function getRecurringEntryTemplates(PDO $pdo, ?int $templateId = null): array {
    $sql = "
        SELECT
            ret.id_template,
            ret.description,
            ret.id_journal,
            j.Libelle_Journal AS journal_libelle,
            ret.frequency,
            ret.start_date,
            ret.end_date,
            ret.last_posted_date,
            ret.next_due_date,
            ret.is_active,
            ret.created_by,
            u.Nom_Utilisateur AS created_by_name
        FROM
            Recurring_Entry_Templates ret
        JOIN
            Journaux j ON ret.id_journal = j.ID_Journal
        JOIN
            Utilisateurs u ON ret.created_by = u.ID_Utilisateur
    ";
    $params = [];

    if ($templateId !== null) {
        $sql .= " WHERE ret.id_template = :template_id";
        $params[':template_id'] = $templateId;
    }

    $sql .= " ORDER BY ret.description ASC;";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch lines for each template
        foreach ($templates as &$template) {
            $stmtLines = $pdo->prepare("
                SELECT
                    retl.id_line,
                    retl.id_compte,
                    cc.Numero_Compte,
                    cc.Nom_Compte,
                    retl.sens,
                    retl.montant
                FROM
                    Recurring_Entry_Template_Lines retl
                JOIN
                    Comptes_compta cc ON retl.id_compte = cc.ID_Compte
                WHERE
                    retl.id_template = :id_template
            ");
            $stmtLines->bindParam(':id_template', $template['id_template'], PDO::PARAM_INT);
            $stmtLines->execute();
            $template['lines'] = $stmtLines->fetchAll(PDO::FETCH_ASSOC);
        }

        return $templates;

    } catch (PDOException $e) {
        logError("Erreur PDO lors de la récupération des modèles d'écritures récurrentes: " . $e->getMessage());
        return [];
    }
}

/**
 * Updates an existing recurring entry template.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $templateId The ID of the template to update.
 * @param string $description The new description.
 * @param int $journalId The new journal ID.
 * @param string $frequency The new frequency.
 * @param string $startDate The new start date.
 * @param string|null $endDate The new end date.
 * @param array $lines The new array of entry lines.
 * @param int $userId The ID of the user performing the update.
 * @param bool $isActive Whether the template is active.
 * @return bool True on success, false on failure.
 */
function updateRecurringEntryTemplate(
    PDO $pdo,
    int $templateId,
    string $description,
    int $journalId,
    string $frequency,
    string $startDate,
    ?string $endDate,
    array $lines,
    int $userId,
    bool $isActive
): bool {
    try {
        $pdo->beginTransaction();

        // Update template details
        $stmt = $pdo->prepare("
            UPDATE Recurring_Entry_Templates SET
                description = :description,
                id_journal = :id_journal,
                frequency = :frequency,
                start_date = :start_date,
                end_date = :end_date,
                is_active = :is_active,
                updated_by = :updated_by,
                updated_at = NOW()
            WHERE id_template = :id_template
        ");
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':id_journal', $journalId, PDO::PARAM_INT);
        $stmt->bindParam(':frequency', $frequency, PDO::PARAM_STR);
        $stmt->bindParam(':start_date', $startDate, PDO::PARAM_STR);
        $stmt->bindParam(':end_date', $endDate, PDO::PARAM_STR);
        $stmt->bindParam(':is_active', $isActive, PDO::PARAM_BOOL);
        $stmt->bindParam(':updated_by', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':id_template', $templateId, PDO::PARAM_INT);
        $stmt->execute();

        // Delete existing lines and re-insert new ones (simpler than updating individual lines)
        $stmtDeleteLines = $pdo->prepare("DELETE FROM Recurring_Entry_Template_Lines WHERE id_template = :id_template");
        $stmtDeleteLines->bindParam(':id_template', $templateId, PDO::PARAM_INT);
        $stmtDeleteLines->execute();

        $stmtLine = $pdo->prepare("
            INSERT INTO Recurring_Entry_Template_Lines
            (id_template, id_compte, sens, montant)
            VALUES (:id_template, :id_compte, :sens, :montant)
        ");
        foreach ($lines as $line) {
            $stmtLine->bindParam(':id_template', $templateId, PDO::PARAM_INT);
            $stmtLine->bindParam(':id_compte', $line['compte_id'], PDO::PARAM_INT);
            $stmtLine->bindParam(':sens', $line['sens'], PDO::PARAM_STR);
            $stmtLine->bindParam(':montant', $line['montant'], PDO::PARAM_STR);
            $stmtLine->execute();
        }

        $pdo->commit();
        logActivity("Modèle d'écriture récurrente ID {$templateId} mis à jour par Utilisateur ID {$userId}");
        return true;

    } catch (PDOException $e) {
        $pdo->rollBack();
        logError("Erreur PDO lors de la mise à jour du modèle d'écriture récurrente: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        $pdo->rollBack();
        logError("Erreur lors de la mise à jour du modèle d'écriture récurrente: " . $e->getMessage());
        return false;
    }
}

/**
 * Deletes a recurring entry template.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $templateId The ID of the template to delete.
 * @return bool True on success, false on failure.
 */
function deleteRecurringEntryTemplate(PDO $pdo, int $templateId): bool {
    try {
        $pdo->beginTransaction();

        // Delete associated lines first
        $stmtDeleteLines = $pdo->prepare("DELETE FROM Recurring_Entry_Template_Lines WHERE id_template = :id_template");
        $stmtDeleteLines->bindParam(':id_template', $templateId, PDO::PARAM_INT);
        $stmtDeleteLines->execute();

        // Delete the template
        $stmt = $pdo->prepare("DELETE FROM Recurring_Entry_Templates WHERE id_template = :id_template");
        $stmt->bindParam(':id_template', $templateId, PDO::PARAM_INT);
        $stmt->execute();

        $pdo->commit();
        logActivity("Modèle d'écriture récurrente ID {$templateId} supprimé.");
        return true;

    } catch (PDOException $e) {
        $pdo->rollBack();
        logError("Erreur PDO lors de la suppression du modèle d'écriture récurrente: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        $pdo->rollBack();
        logError("Erreur lors de la suppression du modèle d'écriture récurrente: " . $e->getMessage());
        return false;
    }
}

/**
 * Processes and posts recurring entries that are due up to a given date.
 * This function would typically be called by a cron job or a daily automated task.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param string $processUpToDate The date up to which entries should be posted (YYYY-MM-DD).
 * @param int $systemUserId The ID of the user performing the automated posting (e.g., a system user).
 * @return array An array of results for each posted entry.
 */
function processDueRecurringEntries(PDO $pdo, string $processUpToDate, int $systemUserId): array {
    $results = [];
    $templatesToProcess = [];

    try {
        // Get active templates that are due
        $sql = "
            SELECT
                ret.id_template,
                ret.description,
                ret.id_journal,
                ret.frequency,
                ret.start_date,
                ret.end_date,
                ret.last_posted_date,
                ret.next_due_date
            FROM
                Recurring_Entry_Templates ret
            WHERE
                ret.is_active = 1
                AND ret.start_date <= :process_date
                AND (ret.end_date IS NULL OR ret.end_date >= :process_date)
                AND (ret.next_due_date IS NULL OR ret.next_due_date <= :process_date)
            ORDER BY ret.next_due_date ASC, ret.id_template ASC;
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':process_date', $processUpToDate, PDO::PARAM_STR);
        $stmt->execute();
        $templatesToProcess = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($templatesToProcess as $template) {
            $templateId = $template['id_template'];

            // Fetch lines for the current template
            $stmtLines = $pdo->prepare("
                SELECT id_compte, sens, montant
                FROM Recurring_Entry_Template_Lines
                WHERE id_template = :id_template
            ");
            $stmtLines->bindParam(':id_template', $templateId, PDO::PARAM_INT);
            $stmtLines->execute();
            $lines = $stmtLines->fetchAll(PDO::FETCH_ASSOC);

            // Determine the actual posting date for this cycle
            // This logic can be complex based on specific frequency rules (e.g., end of month, next business day)
            $postingDate = date('Y-m-d'); // Default to current date for simplicity, adjust as needed

            // Call a function from gestion_ecritures.php to create the actual journal entry
            // Assuming addJournalEntry can handle a raw date, description, journal, user, and multiple lines
            $entryAdded = addJournalEntry(
                $pdo,
                $postingDate,
                $template['description'] . " (Écriture récurrente)",
                $template['id_journal'],
                $systemUserId,
                $lines
            );

            if ($entryAdded) {
                // Update last_posted_date and calculate next_due_date
                $nextDueDate = calculateNextDueDate($template['last_posted_date'] ?? $template['start_date'], $template['frequency']);

                $stmtUpdateTemplate = $pdo->prepare("
                    UPDATE Recurring_Entry_Templates SET
                        last_posted_date = :posting_date,
                        next_due_date = :next_due_date
                    WHERE id_template = :id_template
                ");
                $stmtUpdateTemplate->bindParam(':posting_date', $postingDate, PDO::PARAM_STR);
                $stmtUpdateTemplate->bindParam(':next_due_date', $nextDueDate, PDO::PARAM_STR);
                $stmtUpdateTemplate->bindParam(':id_template', $templateId, PDO::PARAM_INT);
                $stmtUpdateTemplate->execute();

                $results[] = ['success' => true, 'message' => "Écriture récurrente {$template['description']} postée avec succès.", 'template_id' => $templateId];
                logActivity("Écriture récurrente ID {$templateId} postée le {$postingDate}. Prochaine échéance: {$nextDueDate}");
            } else {
                $results[] = ['success' => false, 'message' => "Échec du posting pour l'écriture récurrente: {$template['description']}.", 'template_id' => $templateId];
                logError("Échec du posting pour l'écriture récurrente ID {$templateId}.");
            }
        }

    } catch (PDOException $e) {
        logError("Erreur PDO lors du traitement des écritures récurrentes: " . $e->getMessage());
        $results[] = ['success' => false, 'message' => "Erreur système lors du traitement des écritures récurrentes."];
    } catch (Exception $e) {
        logError("Erreur inattendue lors du traitement des écritures récurrentes: " . $e->getMessage());
        $results[] = ['success' => false, 'message' => "Erreur inattendue lors du traitement des écritures récurrentes."];
    }

    return $results;
}

/**
 * Calculates the next due date based on a given frequency.
 * This is a simplified calculation and might need to handle edge cases (e.g., end of month, leap years).
 *
 * @param string $lastDate The last posted date or start date (YYYY-MM-DD).
 * @param string $frequency The frequency ('daily', 'weekly', 'monthly', 'quarterly', 'annually').
 * @return string The next due date (YYYY-MM-DD).
 */
function calculateNextDueDate(string $lastDate, string $frequency): string {
    $date = new DateTime($lastDate);
    switch (strtolower($frequency)) {
        case 'daily':
            $date->modify('+1 day');
            break;
        case 'weekly':
            $date->modify('+1 week');
            break;
        case 'monthly':
            $date->modify('+1 month');
            break;
        case 'quarterly':
            $date->modify('+3 months');
            break;
        case 'annually':
            $date->modify('+1 year');
            break;
        default:
            // Fallback for unknown frequency, might log an error
            return $lastDate;
    }
    return $date->format('Y-m-d');
}




// Helper for logging activity (assuming gestion_logs.php has logUserActivity)
function logActivity(string $message) {
    if (function_exists('logUserActivity')) {
        logUserActivity($message);
    } else {
        error_log($message);
    }
}