<?php
session_start();
require 'includes/db_connect.php';

// 1. Security Check: Only Admins can process returns
if (!isset($_SESSION['member_id']) || $_SESSION['role_id'] != 1) {
    header("Location: catalog.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['transaction_id'])) {
    $transaction_id = $_POST['transaction_id'];

    try {
        $pdo->beginTransaction();

        // 3. Find which book was borrowed in this specific transaction
        $stmt = $pdo->prepare("SELECT book_id FROM transaction WHERE transaction_id = :transaction_id FOR UPDATE");
        $stmt->execute(['transaction_id' => $transaction_id]);
        $transaction = $stmt->fetch();

        if ($transaction) {
            $book_id = $transaction['book_id'];

            $update_trans = $pdo->prepare("UPDATE transaction SET status_id = 2 WHERE transaction_id = :transaction_id");
            $update_trans->execute(['transaction_id' => $transaction_id]);

            $update_book = $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE book_id = :book_id");
            $update_book->execute(['book_id' => $book_id]);

            $pdo->commit();

            header("Location: admin_dashboard.php");
            exit();
        } else {
            $pdo->rollBack();
            header("Location: admin_dashboard.php");
            exit();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        die("System error: " . $e->getMessage());
    }
} else {
    header("Location: admin_dashboard.php");
    exit();
}
?>