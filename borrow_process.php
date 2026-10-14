<?php
session_start();
require 'includes/db_connect.php';

if (!isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_id'])) {
    $book_id = $_POST['book_id'];
    $member_id = $_SESSION['member_id'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT available_copies FROM books WHERE book_id = :book_id FOR UPDATE");
        $stmt->execute(['book_id' => $book_id]);
        $book = $stmt->fetch();

        if ($book && $book['available_copies'] > 0) {

            $update_stmt = $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE book_id = :book_id");
            $update_stmt->execute(['book_id' => $book_id]);

            $borrowed_at = date('Y-m-d H:i:s');
            $due_at = date('Y-m-d H:i:s', strtotime('+7 days')); 
            
            $insert_stmt = $pdo->prepare("INSERT INTO transaction (member_id, book_id, borrowed_at, due_at, status_id) VALUES (:member_id, :book_id, :borrowed_at, :due_at, 1)");
            $insert_stmt->execute([
                'member_id' => $member_id,
                'book_id' => $book_id,
                'borrowed_at' => $borrowed_at,
                'due_at' => $due_at
            ]);

            $pdo->commit();

            header("Location: member_dashboard.php");
            exit();
        } else {

            $pdo->rollBack();
            header("Location: catalog.php");
            exit();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        die("System error: " . $e->getMessage());
    }
} else {
    header("Location: catalog.php");
    exit();
}
?>