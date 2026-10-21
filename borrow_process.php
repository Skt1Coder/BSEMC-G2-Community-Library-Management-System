<?php
session_start();
require 'includes/db_connect.php';

if (!isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_id'])) {
    
    // csrf
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $_SESSION['error'] = "Invalid security token. Please refresh the page and try again.";
        header("Location: catalog.php"); 
        exit();
    }

    $book_id = (int) $_POST['book_id'];
    $member_id = (int) $_SESSION['member_id'];


    try {
        $pdo->beginTransaction();

        // Lock the book row
        $stmt = $pdo->prepare("SELECT available_copies FROM books WHERE book_id = :book_id FOR UPDATE");
        $stmt->execute(['book_id' => $book_id]);
        $book = $stmt->fetch();

        // Ensure only one copy of the same book per member (Borrowed or Overdue)
        $check = $pdo->prepare("
            SELECT COUNT(*) FROM transaction t
            JOIN status s ON t.status_id = s.status_id
            WHERE t.member_id = :member_id AND t.book_id = :book_id
              AND s.status IN ('Borrowed', 'Overdue')
        ");
        $check->execute(['member_id' => $member_id, 'book_id' => $book_id]);

        if ($check->fetchColumn() > 0) {
            $pdo->rollBack();
            $_SESSION['error'] = "You have already borrowed this book.";
            header("Location: catalog.php");
            exit();
        }

        if (!$book || $book['available_copies'] <= 0) {
            $pdo->rollBack();
            $_SESSION['error'] = "Sorry, no copies are available. You can reserve this book instead.";
            header("Location: catalog.php");
            exit();
        }

        $available = (int) $book['available_copies'];

        // Ensure that free copies are held for people waiting in the reservation line
        $held_stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE book_id = :book_id AND status = 'Active'");
        $held_stmt->execute(['book_id' => $book_id]);
        $held = (int) $held_stmt->fetchColumn();

        $my_res_stmt = $pdo->prepare("
            SELECT reservation_id FROM reservations
            WHERE member_id = :member_id AND book_id = :book_id AND status = 'Active'
        ");
        $my_res_stmt->execute(['member_id' => $member_id, 'book_id' => $book_id]);
        $my_reservation_id = $my_res_stmt->fetchColumn();

        if ($my_reservation_id) {
            // Member is in line: allowed only if they're within the first N people (N = free copies)
            $ahead_stmt = $pdo->prepare("
                SELECT COUNT(*) FROM reservations
                WHERE book_id = :book_id AND status = 'Active' AND reservation_id < :reservation_id
            ");
            $ahead_stmt->execute(['book_id' => $book_id, 'reservation_id' => $my_reservation_id]);
            $allowed = ((int) $ahead_stmt->fetchColumn()) < $available;
        } else {
            // Not in line: only allowed if there are more free copies than people waiting
            $allowed = $available > $held;
        }

        if (!$allowed) {
            $pdo->rollBack();
            $_SESSION['error'] = "The available copies are held for members who reserved this book. You can join the line by reserving it.";
            header("Location: catalog.php");
            exit();
        }

        // Borrow it
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

        // If they borrowed through their reservation, mark it as fulfilled
        if ($my_reservation_id) {
            $fulfil = $pdo->prepare("UPDATE reservations SET status = 'Fulfilled' WHERE reservation_id = :reservation_id");
            $fulfil->execute(['reservation_id' => $my_reservation_id]);
        }

        $pdo->commit();

        header("Location: member_dashboard.php");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "A system error occurred. Please try again.";
        header("Location: " . (isset($back) ? $back : "catalog.php"));
        exit();
    }
} else {
    header("Location: catalog.php");
    exit();
}
?>