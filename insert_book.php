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
    $_SESSION['error'] = "Invalid security token. Please refresh the page and try again.";
    header("Location: add_book.php"); 
    exit();
}

$action  = $_POST['action'] ?? '';
$book_id = (int) ($_POST['book_id'] ?? 0);

// Helper function to handle image upload safely
function handleCoverUpload($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];

    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $file_mime = mime_content_type($file['tmp_name']);

    if (!in_array($file_ext, $allowed_exts) || !in_array($file_mime, $allowed_mimes)) {
        $_SESSION['error'] = "Invalid cover image format. Only JPG, PNG, and WEBP files are allowed.";
        return false;
    }

    // 2MB Size Limit
    if ($file['size'] > 2 * 1024 * 1024) {
        $_SESSION['error'] = "Cover image must be 2MB or smaller.";
        return false;
    }

    $upload_dir = 'uploads/covers/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $new_filename = uniqid('cover_', true) . '.' . $file_ext;
    $target_path = $upload_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return $new_filename;
    }

    $_SESSION['error'] = "Failed to upload cover image.";
    return false;
}

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
            } else {
                // Handle file upload if provided
                $cover_filename = handleCoverUpload($_FILES['cover_image'] ?? null);

                // Stop if file upload failed validation
                if ($cover_filename === false) {
                    header("Location: add_book.php" . ($action === 'update' ? "?edit=$book_id" : ''));
                    exit();
                }

                if ($action === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO books (title, author, category_id, available_copies, cover_image)
                        VALUES (:title, :author, :category_id, :copies, :cover_image)
                    ");
                    $stmt->execute([
                        'title'       => $title,
                        'author'      => $author,
                        'category_id' => $category_id,
                        'copies'      => $copies,
                        'cover_image' => $cover_filename
                    ]);
                    $_SESSION['success'] = "\"" . $title . "\" was added to the catalog.";
                } else {
                    // Update mode
                    if ($cover_filename !== null) {
                        // Delete old cover image if replaced
                        $old_cover_stmt = $pdo->prepare("SELECT cover_image FROM books WHERE book_id = :book_id");
                        $old_cover_stmt->execute(['book_id' => $book_id]);
                        $old_cover = $old_cover_stmt->fetchColumn();

                        if (!empty($old_cover) && file_exists('uploads/covers/' . $old_cover)) {
                            @unlink('uploads/covers/' . $old_cover);
                        }

                        // Update with new image
                        $stmt = $pdo->prepare("
                            UPDATE books
                            SET title = :title, author = :author, category_id = :category_id, available_copies = :copies, cover_image = :cover_image
                            WHERE book_id = :book_id
                        ");
                        $stmt->execute([
                            'title'       => $title,
                            'author'      => $author,
                            'category_id' => $category_id,
                            'copies'      => $copies,
                            'cover_image' => $cover_filename,
                            'book_id'     => $book_id
                        ]);
                    } else {
                        // Keep current cover image
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
                    }
                    $_SESSION['success'] = "\"" . $title . "\" was updated.";
                }
            }
        }

    // ---------- DELETE ----------
    } elseif ($action === 'delete') {
        // Block removal if any copy is still out on loan OR reserved
        $check = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM transaction t JOIN status s ON t.status_id = s.status_id WHERE t.book_id = :book_id AND s.status IN ('Borrowed', 'Overdue')) +
                (SELECT COUNT(*) FROM reservations r WHERE r.book_id = :book_id AND r.status = 'Active') 
            AS active_links
        ");
        $check->execute(['book_id' => $book_id]);

        if ($check->fetchColumn() > 0) {
            $_SESSION['error'] = "This book can't be removed while copies are borrowed or reserved.";
        } else {
            try {
                // Fetch image before deleting record
                $img_stmt = $pdo->prepare("SELECT cover_image FROM books WHERE book_id = :book_id");
                $img_stmt->execute(['book_id' => $book_id]);
                $cover_to_delete = $img_stmt->fetchColumn();

                $stmt = $pdo->prepare("DELETE FROM books WHERE book_id = :book_id");
                $stmt->execute(['book_id' => $book_id]);

                // Delete image file if present
                if (!empty($cover_to_delete) && file_exists('uploads/covers/' . $cover_to_delete)) {
                    @unlink('uploads/covers/' . $cover_to_delete);
                }

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