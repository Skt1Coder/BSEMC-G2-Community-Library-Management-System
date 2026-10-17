<?php
session_start();
require 'includes/db_connect.php';

/*
 * Handles two actions from forms:
 *   reserve - join the waiting line for a book that has no free copies
 *   cancel  - leave the waiting line
 *
 * Needs the `reservations` table (see reservations.sql).
 */

// Must be logged in
if (!isset($_SESSION['member_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: catalog.php");
    exit();
}

// CSRF check
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    die('Invalid request.');
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
            // Does the member already have this book?
            $loan = $pdo->prepare("
                SELECT COUNT(*) FROM transaction t
                JOIN status s ON t.status_id = s.status_id
                WHERE t.member_id = :member_id AND t.book_id = :book_id
                  AND s.status IN ('Borrowed', 'Overdue')
            ");
            $loan->execute(['member_id' => $member_id, 'book_id' => $book_id]);

            // Already in line?
            $existing = $pdo->prepare("
                SELECT COUNT(*) FROM reservations
                WHERE member_id = :member_id AND book_id = :book_id AND status = 'Active'
            ");
            $existing->execute(['member_id' => $member_id, 'book_id' => $book_id]);

            // How many people are waiting for this book?
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

        // Members can only cancel their own active reservations
        $stmt = $pdo->prepare("
            UPDATE reservations SET status = 'Cancelled'
            WHERE reservation_id = :reservation_id AND member_id = :member_id AND status = 'Active'
        ");
        $stmt->execute(['reservation_id' => $reservation_id, 'member_id' => $member_id]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Reservation cancelled.";
        } else {
            $_SESSION['error'] = "Reservation not found.";
        }

    } else {
        $_SESSION['error'] = "Unknown action.";
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("System error: " . $e->getMessage());
}

header("Location: " . $back);
exit();