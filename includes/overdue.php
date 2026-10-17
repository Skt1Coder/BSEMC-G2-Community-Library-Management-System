<?php
/*
 * Shared overdue + fine settings.
 * Include with: require 'includes/overdue.php';
 */

// Fine in pesos, per overdue day, per book
if (!defined('FINE_PER_DAY')) {
    define('FINE_PER_DAY', 10);
}

/**
 * Flip every loan whose due date has passed from 'Borrowed' to 'Overdue'.
 * Looks the status IDs up by name, so no IDs are hardcoded.
 * Uses PHP's clock (same one that created due_at) to avoid timezone mismatches.
 */
function update_overdue_statuses(PDO $pdo): void
{
    $stmt = $pdo->prepare("
        UPDATE `transaction` t
        JOIN status sb ON sb.status = 'Borrowed'
        JOIN status so ON so.status = 'Overdue'
        SET t.status_id = so.status_id
        WHERE t.status_id = sb.status_id
          AND t.due_at < :now
    ");
    $stmt->execute(['now' => date('Y-m-d H:i:s')]);
}