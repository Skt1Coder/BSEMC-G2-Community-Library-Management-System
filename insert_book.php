<?php
session_start();
require 'includes/db_connect.php';

// Admins only
if (!isset($_SESSION['member_id']) || $_SESSION['role_id'] != 1) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: add_book.php");
    exit();
}

// CSRF check
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    die('Invalid request.');
}

$action  = $_POST['action'] ?? '';
$book_id = (int) ($_POST['book_id'] ?? 0);

try {
    // ---------- ADD / UPDATE ----------
    if ($action === 'add' || $action === 'update') {
        $title       = trim($_POST['title'] ?? '');
        $author      = trim($_POST['author'] ?? '');
        $category_id = (int) ($_POST['category_id'] ?? 0);
        $copies      = filter_var($_POST['available_copies'] ?? '', FILTER_VALIDATE_INT);

        // Validate input
        if ($title === '' || $author === '') {
            $_SESSION['error'] = "Title and author are required.";
        } elseif (mb_strlen($title) > 255 || mb_strlen($author) > 255) {
            $_SESSION['error'] = "Title and author must be 255 characters or fewer.";
        } elseif ($copies === false || $copies < 0) {
            $_SESSION['error'] = "Copies must be a whole number of 0 or more.";
        } else {
            // Make sure the category exists
            $cat = $pdo->prepare("SELECT COUNT(*) FROM category WHERE category_id = :category_id");
            $cat->execute(['category_id' => $category_id]);

            if ($cat->fetchColumn() == 0) {
                $_SESSION['error'] = "Please choose a valid category.";
            } elseif ($action === 'add') {
                $stmt = $pdo->prepare("
                    INSERT INTO books (title, author, category_id, available_copies)
                    VALUES (:title, :author, :category_id, :copies)
                ");
                $stmt->execute([
                    'title'       => $title,
                    'author'      => $author,
                    'category_id' => $category_id,
                    'copies'      => $copies
                ]);
                $_SESSION['success'] = "\"" . $title . "\" was added to the catalog.";
            } else {
                $stmt = $pdo->prepare("
                    UPDATE books
                    SET title = :title, author = :author, category_id = :category_id, available_copies = :copies
                    WHERE book_id = :book_id
                ");
                $stmt->execute([
                    'title'       => $title,
                    'author'      => $author,
                    'category_id' => $category_id,
                    'copies'      => $copies,
                    'book_id'     => $book_id
                ]);
                $_SESSION['success'] = "\"" . $title . "\" was updated.";
            }
        }

    // ---------- DELETE ----------
    } elseif ($action === 'delete') {
        // Block removal if any copy is still out on loan
        $check = $pdo->prepare("
            SELECT COUNT(*) FROM transaction t
            JOIN status s ON t.status_id = s.status_id
            WHERE t.book_id = :book_id AND s.status IN ('Borrowed', 'Overdue')
        ");
        $check->execute(['book_id' => $book_id]);

        if ($check->fetchColumn() > 0) {
            $_SESSION['error'] = "This book can't be removed while copies are still borrowed.";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM books WHERE book_id = :book_id");
                $stmt->execute(['book_id' => $book_id]);
                $_SESSION['success'] = "Book removed from the catalog.";
            } catch (PDOException $e) {
                // Foreign key from past loan records
                $_SESSION['error'] = "This book has past borrowing records, so it can't be deleted. Set its copies to 0 instead.";
            }
        }

    } else {
        $_SESSION['error'] = "Unknown action.";
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
}

header("Location: add_book.php");
exit();