<?php
session_start();
require 'includes/db_connect.php';


// Must be logged in
if (!isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: catalog.php");
    exit();
}

// csrf
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    $_SESSION['error'] = "Invalid security token. Please refresh the page and try again.";
    header("Location: catalog.php"); 
    exit();
}

$member_id = (int) $_SESSION['member_id'];
$action    = $_POST['action'] ?? '';
$back      = (($_POST['from'] ?? '') === 'dashboard') ? 'member_dashboard.php' : 'catalog.php';

try {
    // ---------- RESERVE ----------
    if ($action === 'reserve') {
        $book_id = (int) ($_POST['book_id'] ?? 0);

        $pdo->beginTransaction();

        // Lock the book row so two people can't grab the same spot at once
        $stmt = $pdo->prepare("SELECT title, available_copies FROM books WHERE book_id = :book_id FOR UPDATE");
        $stmt->execute(['book_id' => $book_id]);
        $book = $stmt->fetch();

        if (!$book) {
            $_SESSION['error'] = "That book doesn't exist.";
        } else {
            // check if the member has this Book
            $loan = $pdo->prepare("
                SELECT COUNT(*) FROM transaction t
                JOIN status s ON t.status_id = s.status_id
                WHERE t.member_id = :member_id AND t.book_id = :book_id
                  AND s.status IN ('Borrowed', 'Overdue')
            ");
            $loan->execute(['member_id' => $member_id, 'book_id' => $book_id]);

            // check if already in line
            $existing = $pdo->prepare("
                SELECT COUNT(*) FROM reservations
                WHERE member_id = :member_id AND book_id = :book_id AND status = 'Active'
            ");
            $existing->execute(['member_id' => $member_id, 'book_id' => $book_id]);

            // check how many people are waiting for this book
            $held_stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE book_id = :book_id AND status = 'Active'");
            $held_stmt->execute(['book_id' => $book_id]);
            $held = (int) $held_stmt->fetchColumn();

            if ($loan->fetchColumn() > 0) {
                $_SESSION['error'] = "You already have this book borrowed.";
            } elseif ($existing->fetchColumn() > 0) {
                $_SESSION['error'] = "You already reserved this book.";
            } elseif ((int) $book['available_copies'] > $held) {
                $_SESSION['error'] = "This book is available right now. You can borrow it instead.";
            } else {
                $insert = $pdo->prepare("
                    INSERT INTO reservations (member_id, book_id, reserved_at, status)
                    VALUES (:member_id, :book_id, :reserved_at, 'Active')
                ");
                $insert->execute([
                    'member_id'   => $member_id,
                    'book_id'     => $book_id,
                    'reserved_at' => date('Y-m-d H:i:s')
                ]);
                $_SESSION['success'] = "You reserved \"" . $book['title'] . "\". You're #" . ($held + 1) . " in line.";
            }
        }

        $pdo->commit();

    // ---------- CANCEL ----------
    } elseif ($action === 'cancel') {
        $reservation_id = (int) ($_POST['reservation_id'] ?? 0);

        if ($_SESSION['role_id'] == 1) {
            // Admins cancellation reservation for all members (included admin)
            $stmt = $pdo->prepare("UPDATE reservations SET status = 'Cancelled' WHERE reservation_id = :reservation_id AND status = 'Active'");
            $stmt->execute(['reservation_id' => $reservation_id]);
        } else {
            // Residents can only cancel their OWN active reservations
            $stmt = $pdo->prepare("UPDATE reservations SET status = 'Cancelled' WHERE reservation_id = :reservation_id AND member_id = :member_id AND status = 'Active'");
            $stmt->execute(['reservation_id' => $reservation_id, 'member_id' => $member_id]);
        }

        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Reservation cancelled.";
        } else {
            $_SESSION['error'] = "Reservation not found.";
        }
        
        // route to back to admin panel if error
        if (($_POST['from'] ?? '') === 'admin_dashboard') {
            $back = 'admin_dashboard.php';
        }

    } else {
        $_SESSION['error'] = "Unknown action.";
    }
} catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "A system error occurred. Please try again.";
        header("Location: " . (isset($back) ? $back : "catalog.php"));
        exit();
}

header("Location: " . $back);
exit();