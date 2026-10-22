<?php
session_start();
require 'includes/db_connect.php';

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

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
        
            @media screen and (min-width: 1800px) {
        html {
            font-size: clamp(16px, 0.85vw, 30px);
        }

        body {
            font-size: 1rem;
            line-height: 1.55;
        }

        .container {
            width: min(94vw, 3200px) !important;
            max-width: 3200px !important;
        }

        .container-fluid {
            padding-left: clamp(1rem, 2vw, 4rem);
            padding-right: clamp(1rem, 2vw, 4rem);
        }

        .row {
            --bs-gutter-x: clamp(1rem, 1.35vw, 3rem);
            --bs-gutter-y: clamp(0.75rem, 0.9vw, 2rem);
        }

        .navbar {
            padding-top: clamp(0.65rem, 0.75vw, 1.5rem) !important;
            padding-bottom: clamp(0.65rem, 0.75vw, 1.5rem) !important;
        }

        .navbar-brand {
            font-size: clamp(1.1rem, 0.8vw, 1.75rem) !important;
            gap: clamp(0.5rem, 0.65vw, 1.25rem);
            letter-spacing: normal;
            line-height: 1.25;
            white-space: normal;
            max-width: 60%;
        }

        .nav-link {
            font-size: clamp(0.9rem, 0.55vw, 1.2rem) !important;
            padding: 0.55em 0.9em !important;
        }

        .logo-box {
            width: clamp(40px, 1.8vw, 68px);
            height: clamp(40px, 1.8vw, 68px);
            font-size: clamp(1.1rem, 0.75vw, 1.8rem);
        }

        /* Headings */
        h1 {
            font-size: clamp(2rem, 2vw, 4.5rem);
            line-height: 1.15;
        }

        h2 {
            font-size: clamp(1.65rem, 1.45vw, 3.1rem);
            line-height: 1.2;
        }

        h3 {
            font-size: clamp(1.35rem, 1.1vw, 2.4rem);
            line-height: 1.25;
        }

        h4 {
            font-size: clamp(1.15rem, 0.9vw, 2rem);
        }

        .form-control,
        .form-select {
            font-size: clamp(0.9rem, 0.55vw, 1.2rem);
            min-height: clamp(2.5rem, 2.2vw, 3.8rem);
            padding: 0.65em 0.85em;
            min-width: 0;
        }

        .form-label {
            font-size: clamp(0.9rem, 0.55vw, 1.15rem);
        }

        .btn,
        .btn-primary-custom,
        .btn-outline-custom {
            font-size: clamp(0.85rem, 0.52vw, 1.2rem);
            line-height: 1.35;
            white-space: normal;
        }

        .card,
        .card-custom,
        .feature-card {
            border-radius: clamp(12px, 0.75vw, 24px);
        }

        .card-body,
        .card-custom,
        .feature-card {
            min-width: 0;
        }

        .stat-number {
            font-size: clamp(1.75rem, 1.35vw, 3rem) !important;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .icon-circle {
            width: clamp(48px, 1.8vw, 68px);
            height: clamp(48px, 1.8vw, 68px);
            font-size: clamp(1.2rem, 0.8vw, 2rem);
        }

        .card-title {
            font-size: clamp(1rem, 0.68vw, 1.45rem);
        }

        .card-text {
            font-size: clamp(0.9rem, 0.58vw, 1.2rem);
            overflow-wrap: anywhere;
        }

        .table {
            font-size: clamp(0.9rem, 0.55vw, 1.2rem);
            vertical-align: middle;
        }

        .table > :not(caption) > * > * {
            padding: clamp(0.55rem, 0.55vw, 1.2rem);
            vertical-align: middle;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .hero-section {
            padding: clamp(100px, 4.8vw, 200px)
                    clamp(20px, 2vw, 64px)
                    clamp(85px, 4vw, 165px);
        }

        .hero-title {
            font-size: clamp(3rem, 3vw, 6.5rem);
            line-height: 1.12;
        }

        .hero-subtitle {
            font-size: clamp(1rem, 0.85vw, 1.65rem);
            max-width: min(72vw, 1200px);
            line-height: 1.6;
        }

        .features-wrapper {
            padding-bottom: clamp(3rem, 4vw, 8rem);
        }

        .feature-card {
            height: 100%;
        }

        .auth-card {
            width: min(88vw, 3000px) !important;
            max-width: min(88vw, 3000px) !important;
        }

        .auth-sidebar,
        .auth-form-wrapper {
            padding: clamp(2rem, 2.6vw, 5rem);
        }

        .header {
            margin-bottom: clamp(1.5rem, 2vw, 3.5rem);
            padding-bottom: clamp(0.5rem, 0.7vw, 1.25rem);
        }

        .no-print-btn {
            font-size: clamp(0.9rem, 0.55vw, 1.2rem);
            padding: 0.7em 1.15em;
        }
    }

    @media screen and (min-width: 1200px) {
        .table td,
        .table th {
            overflow-wrap: anywhere;
        }

        .table form,
        .table .d-flex {
            flex-wrap: wrap;
        }
    }

    @media screen and (max-width: 767.98px) {
        .navbar-brand {
            max-width: calc(100% - 64px);
            font-size: 1rem !important;
            white-space: normal;
        }

        .hero-title {
            font-size: clamp(2rem, 8vw, 2.8rem);
        }

        .hero-section {
            padding: 80px 16px 72px;
            border-bottom-left-radius: 24px;
            border-bottom-right-radius: 24px;
        }

        .auth-card {
            max-width: 100%;
        }

        .auth-sidebar,
        .auth-form-wrapper {
            padding: 1.5rem;
        }

        .table-responsive {
            overflow-x: auto;
        }
    }
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
            <!-- csrf -->
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
                                            <!-- csrf -->
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