<?php
session_start();
require 'includes/db_connect.php';

require 'includes/overdue.php'; // defines FINE_PER_DAY
const STATUS_RETURNED = 2;

// Security Check: Only Admins can process returns
if (!isset($_SESSION['member_id']) || $_SESSION['role_id'] != 1) {
    header("Location: catalog.php");
    exit();
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['transaction_id'])) {

    

    $transaction_id = (int) $_POST['transaction_id'];

    try {
        $pdo->beginTransaction();

        // Lock this loan and read what we need to calculate the fine
        $stmt = $pdo->prepare("
            SELECT book_id, status_id, due_at
            FROM transaction
            WHERE transaction_id = :transaction_id
            FOR UPDATE
        ");
        $stmt->execute(['transaction_id' => $transaction_id]);
        $transaction = $stmt->fetch();

        // Only process loans that haven't been returned yet
        if ($transaction && $transaction['status_id'] != STATUS_RETURNED) {
            $book_id = $transaction['book_id'];

            // Calculate fine: every started day past the due date costs FINE_PER_DAY
            $fine = 0;
            $now = new DateTime();
            $due = new DateTime($transaction['due_at']);

            if ($now > $due) {
                $seconds_late = $now->getTimestamp() - $due->getTimestamp();
                $days_late = (int) ceil($seconds_late / 86400);
                $fine = $days_late * FINE_PER_DAY;
            }

            $update_trans = $pdo->prepare("
                UPDATE transaction
                SET status_id = :status_id, fine_amount = :fine
                WHERE transaction_id = :transaction_id
            ");
            $update_trans->execute([
                'status_id'      => STATUS_RETURNED,
                'fine'           => $fine,
                'transaction_id' => $transaction_id
            ]);

            $update_book = $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE book_id = :book_id");
            $update_book->execute(['book_id' => $book_id]);

            $start_timer = $pdo->prepare("
                UPDATE reservations 
                SET expires_at = DATE_ADD(NOW(), INTERVAL 48 HOUR) 
                WHERE book_id = :book_id AND status = 'Active' AND expires_at IS NULL 
                ORDER BY reservation_id ASC LIMIT 1
            ");

            $start_timer->execute(['book_id' => $book_id]);

            $pdo->commit();

            $_SESSION['success'] = $fine > 0
                ? "Book returned. Overdue fine: ₱" . number_format($fine, 2)
                : "Book returned on time. No fine.";

            header("Location: admin_dashboard.php");
            exit();
        } else {
            $pdo->rollBack();
            header("Location: admin_dashboard.php");
            exit();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($e->getMessage()); 

        $_SESSION['error'] = "A system error occurred while processing your request.";
        header("Location: catalog.php"); 
        exit();
    }
} else {
    header("Location: admin_dashboard.php");
    exit();
}
?>