<?php
session_start();
require 'includes/db_connect.php';

// Admins only
if (!isset($_SESSION['member_id']) || $_SESSION['role_id'] != 1) {
    header("Location: login.php");
    exit();
}

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Categories for the dropdown
$categories = $pdo->query("SELECT category_id, category FROM category ORDER BY category ASC")->fetchAll();

// Edit mode: load the book if ?edit=ID is present
$edit_book = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT book_id, title, author, category_id, available_copies FROM books WHERE book_id = :book_id");
    $stmt->execute(['book_id' => (int) $_GET['edit']]);
    $edit_book = $stmt->fetch() ?: null;
}

// Inventory list
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "
    SELECT b.book_id, b.title, b.author, b.available_copies, c.category
    FROM books b
    JOIN category c ON b.category_id = c.category_id
";
$params = [];
if ($search_query) {
    $sql .= " WHERE b.title LIKE :search OR b.author LIKE :search";
    $params['search'] = "%$search_query%";
}
$sql .= " ORDER BY b.title ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Management - Community Library</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-green: #14532d;
            --accent-green: #dcfce7;
            --light-bg: #fafaf9;
            --text-main: #292524;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--light-bg); color: var(--text-main); }
        h1, h2, h3, h4, h5, .navbar-brand { font-family: 'Outfit', sans-serif; }
        .text-theme { color: var(--primary-green); }

        .navbar { background-color: var(--primary-green) !important; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 1rem 0; }
        .navbar-brand { display: flex; align-items: center; gap: 12px; font-weight: 800; color: white !important; font-size: 1.25rem; letter-spacing: -0.5px; }
        .logo-box { width: 40px; height: 40px; background: white; color: var(--primary-green); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 4px 10px rgba(0,0,0,0.15); flex-shrink: 0; }
        .nav-link { color: rgba(255,255,255,0.85) !important; font-weight: 500; font-size: 0.95rem; padding: 0.5rem 1rem !important; transition: all 0.3s ease; border-radius: 8px; }
        .nav-link:hover, .nav-link.active { color: white !important; background-color: rgba(255,255,255,0.15); }

        .card-custom { background: white; border: 1px solid #e7e5e4; border-radius: 14px; }
        .table thead th { background-color: var(--accent-green); color: var(--primary-green); font-weight: 600; border-bottom: none; }
        .btn-green { background-color: var(--primary-green); color: white; }
        .btn-green:hover { background-color: #166534; color: white; }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand" href="homepage.php">
            <div class="logo-box"><i class="bi bi-book-half"></i></div>
            Community Library Management System
        </a>

        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto gap-2 mt-3 mt-lg-0">
                <li class="nav-item"><a class="nav-link" href="homepage.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="catalog.php">Catalog</a></li>
                <li class="nav-item"><a class="nav-link fw-bold" style="color: #dcfce7;" href="admin_dashboard.php">Admin Panel</a></li>
                <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                    <a class="btn fw-semibold px-4 rounded-pill" 
                    style="background-color: transparent; border: 1.5px solid white; color: white; transition: all 0.2s;" 
                    onmouseover="this.style.backgroundColor='var(--accent-green)'; this.style.borderColor='var(--accent-green)'; this.style.color='var(--primary-green)';" 
                    onmouseout="this.style.backgroundColor='transparent'; this.style.borderColor='white'; this.style.color='white';" 
                    href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container mt-5 mb-5">
    <h2 class="text-theme fw-bold mb-4">Book Management</h2>

    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Add / Edit Form -->
    <div class="card card-custom p-4 mb-5">
        <h5 class="fw-bold text-theme mb-3">
            <?= $edit_book ? 'Edit Book' : 'Add New Book'; ?>
        </h5>

        <form action="insert_book.php" method="POST">
            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf']; ?>">
            <input type="hidden" name="action" value="<?= $edit_book ? 'update' : 'add'; ?>">
            <?php if ($edit_book): ?>
                <input type="hidden" name="book_id" value="<?= (int) $edit_book['book_id']; ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Title</label>
                    <input type="text" name="title" class="form-control" maxlength="255" required
                           value="<?= htmlspecialchars($edit_book['title'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Author</label>
                    <input type="text" name="author" class="form-control" maxlength="255" required
                           value="<?= htmlspecialchars($edit_book['author'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Category</label>
                    <select name="category_id" class="form-select" required>
                        <option value="">Select a category...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int) $cat['category_id']; ?>"
                                <?= ($edit_book['category_id'] ?? '') == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Available Copies</label>
                    <input type="number" name="available_copies" class="form-control" min="0" required
                           value="<?= (int) ($edit_book['available_copies'] ?? 1); ?>">
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-green px-4">
                    <?= $edit_book ? 'Save Changes' : 'Add Book'; ?>
                </button>
                <?php if ($edit_book): ?>
                    <a href="add_book.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Inventory -->
    <div class="row mb-3 align-items-center">
        <div class="col-md-6">
            <h5 class="fw-bold text-theme mb-0">Current Inventory (<?= count($books); ?>)</h5>
        </div>
        <div class="col-md-6 mt-3 mt-md-0">
            <form action="add_book.php" method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="Search by title or author..." value="<?= htmlspecialchars($search_query); ?>">
                <button type="submit" class="btn btn-success" style="background-color: #2b5329;">Search</button>
                <?php if ($search_query): ?>
                    <a href="add_book.php" class="btn btn-secondary ms-2">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card card-custom overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Title</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>Copies</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($books) > 0): ?>
                        <?php foreach ($books as $book): ?>
                            <tr>
                                <td class="ps-4 fw-semibold"><?= htmlspecialchars($book['title']); ?></td>
                                <td><?= htmlspecialchars($book['author']); ?></td>
                                <td><?= htmlspecialchars($book['category']); ?></td>
                                <td><?= (int) $book['available_copies']; ?></td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="add_book.php?edit=<?= (int) $book['book_id']; ?>#top" class="btn btn-outline-success btn-sm">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <form action="insert_book.php" method="POST" class="m-0"
                                              onsubmit="return confirm('Remove this book from the catalog? This cannot be undone.');">
                                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="book_id" value="<?= (int) $book['book_id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i class="bi bi-trash"></i> Remove
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No books found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>