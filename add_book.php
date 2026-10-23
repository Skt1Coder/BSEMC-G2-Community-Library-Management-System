<?php
session_start();
require 'includes/db_connect.php';
require 'includes/overdue.php';

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// STRICT SECURITY: Kick out anyone who is NOT an Admin (role_id 1)
if (!isset($_SESSION['member_id']) || $_SESSION['role_id'] != 1) {
    header("Location: login.php");
    exit();
}

// Categories for the dropdown
$categories = $pdo->query("SELECT category_id, category FROM category ORDER BY category ASC")->fetchAll();

// Edit mode: load the book if ?edit=ID is present
$edit_book = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT book_id, title, author, category_id, available_copies, cover_image FROM books WHERE book_id = :book_id");
    $stmt->execute(['book_id' => (int) $_GET['edit']]);
    $edit_book = $stmt->fetch() ?: null;
}

// Inventory list
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "
    SELECT b.book_id, b.title, b.author, b.available_copies, b.cover_image, c.category
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

    <!-- Google Fonts & Bootstrap Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-green: #0f392b;
            --primary-light: #15803d;
            --primary-hover: #166534;
            --accent-green: #dcfce7;
            --accent-emerald: #10b981;
            --dark-bg: #111827;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: rgba(226, 232, 240, 0.8);
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        h1, h2, h3, h4, h5, .navbar-brand { 
            font-family: 'Outfit', sans-serif; 
        }

        /* --- Navbar Redesign --- */
        .navbar {
            background: rgba(255, 255, 255, 0.85) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            padding: 0.85rem 0;
            transition: all 0.3s ease;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            color: var(--primary-green) !important;
            font-size: 1.2rem;
            letter-spacing: -0.3px;
        }

        .logo-box {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, var(--primary-green), var(--accent-emerald));
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
            flex-shrink: 0;
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover .logo-box {
            transform: scale(1.05) rotate(-3deg);
        }

        .nav-link {
            color: var(--text-main) !important;
            font-weight: 500;
            font-size: 0.95rem;
            padding: 0.5rem 1.1rem !important;
            transition: all 0.25s ease;
            border-radius: 50px;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary-light) !important;
            background-color: var(--accent-green);
        }

        /* --- Admin Header Banner --- */
        .admin-header {
            background: radial-gradient(circle at 50% 20%, #166534 0%, var(--primary-green) 70%, #062319 100%);
            padding: 50px 0 75px;
            color: white;
            border-bottom-left-radius: 32px;
            border-bottom-right-radius: 32px;
            box-shadow: 0 10px 30px rgba(15, 57, 43, 0.15);
            margin-bottom: -45px;
            position: relative;
            z-index: 10;
        }

        .admin-header h2 {
            font-size: 2.25rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .admin-header p {
            color: #d1d5db;
            font-size: 1rem;
            margin-bottom: 0;
        }

        /* --- Section Cards & Forms --- */
        .card-custom { 
            border-radius: 20px; 
            border: 1px solid var(--border-color); 
            box-shadow: var(--shadow-sm); 
            background: var(--card-bg); 
            overflow: hidden;
        }

        .card-header-custom {
            background: #ffffff;
            border-bottom: 1px solid var(--border-color);
            padding: 18px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .card-header-title {
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--primary-green);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Form Control Custom Styling */
        .form-control, .form-select {
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 0.6rem 1rem;
            font-size: 0.92rem;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent-emerald);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
        }

        .form-label {
            font-weight: 600;
            font-size: 0.88rem;
            color: var(--text-main);
            margin-bottom: 6px;
        }

        /* Table Styling */
        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background-color: #f8fafc;
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .table tbody td {
            padding: 14px 20px;
            font-size: 0.92rem;
            color: var(--text-main);
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
        }

        .badge-category {
            background-color: var(--accent-green);
            color: var(--primary-light);
            font-weight: 600;
            font-size: 0.78rem;
            padding: 5px 12px;
            border-radius: 50px;
        }

        /* Table Cover Preview */
        .table-cover-thumb {
            width: 42px;
            height: 56px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background-color: #f1f5f9;
        }

        .table-cover-placeholder {
            width: 42px;
            height: 56px;
            border-radius: 6px;
            background-color: #f1f5f9;
            border: 1px dashed #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 1.1rem;
        }

        /* Form Cover Preview Box */
        .edit-cover-preview {
            width: 70px;
            height: 95px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        /* --- Buttons --- */
        .btn-theme-success {
            background-color: var(--primary-green);
            color: white;
            font-weight: 600;
            border-radius: 50px;
            padding: 8px 22px;
            transition: all 0.2s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-theme-success:hover {
            background-color: var(--primary-hover);
            color: white;
        }

        .btn-theme-outline-danger {
            border: 1.5px solid #fca5a5;
            color: #dc2626;
            font-weight: 600;
            border-radius: 50px;
            padding: 5px 14px;
            font-size: 0.82rem;
            transition: all 0.2s ease;
            background: transparent;
        }

        .btn-theme-outline-danger:hover {
            background-color: #fef2f2;
            border-color: #dc2626;
            color: #dc2626;
        }

        .btn-theme-outline-success {
            border: 1.5px solid #86efac;
            color: #16a34a;
            font-weight: 600;
            border-radius: 50px;
            padding: 5px 14px;
            font-size: 0.82rem;
            transition: all 0.2s ease;
            background: transparent;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-theme-outline-success:hover {
            background-color: #f0fdf4;
            border-color: #16a34a;
            color: #16a34a;
        }

        .custom-alert {
            border-radius: 16px;
            border: none;
            box-shadow: var(--shadow-sm);
            padding: 16px 20px;
        }

        /* --- Footer --- */
        .footer {
            background-color: var(--dark-bg);
            padding: 60px 0 30px;
            border-top: 4px solid var(--accent-emerald);
            color: #9ca3af;
            margin-top: auto;
        }

        .footer-logo {
            font-family: 'Outfit', sans-serif;
            color: white;
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .footer-links a {
            color: #d1d5db;
            text-decoration: none;
            margin: 0 12px;
            font-size: 0.92rem;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: var(--accent-emerald);
        }

        .footer-divider {
            color: #4b5563;
        }

        @media screen and (min-width: 1800px) {
            html { font-size: clamp(16px, 0.85vw, 30px); }
            body { font-size: 1rem; line-height: 1.55; }
            .container { width: min(94vw, 3200px) !important; max-width: 3200px !important; }
            .navbar { padding-top: clamp(0.65rem, 0.75vw, 1.5rem) !important; padding-bottom: clamp(0.65rem, 0.75vw, 1.5rem) !important; }
            .navbar-brand { font-size: clamp(1.1rem, 0.8vw, 1.75rem) !important; gap: clamp(0.5rem, 0.65vw, 1.25rem); }
            .nav-link { font-size: clamp(0.9rem, 0.55vw, 1.2rem) !important; padding: 0.55em 0.9em !important; }
            .logo-box { width: clamp(40px, 1.8vw, 68px); height: clamp(40px, 1.8vw, 68px); font-size: clamp(1.1rem, 0.75vw, 1.8rem); }
            h2 { font-size: clamp(1.65rem, 1.45vw, 3.1rem); line-height: 1.2; }
            .form-control, .form-select { font-size: clamp(0.9rem, 0.55vw, 1.2rem); min-height: clamp(2.5rem, 2.2vw, 3.8rem); }
            .btn { font-size: clamp(0.85rem, 0.52vw, 1.2rem); }
            .card-custom { border-radius: clamp(12px, 0.75vw, 24px); }
            .table { font-size: clamp(0.9rem, 0.55vw, 1.2rem); }
        }

        @media screen and (max-width: 767.98px) {
            .admin-header { padding: 40px 0 65px; border-radius: 0 0 24px 24px; }
            .table-responsive { overflow-x: auto; }
        }
    </style>
</head>
<body id="top">

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand" href="homepage.php">
            <div class="logo-box"><i class="bi bi-book-half"></i></div>
            <span>Community Library</span>
        </a>

        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto gap-1 mt-3 mt-lg-0 align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link" href="homepage.php"><i class="bi bi-house-door me-1"></i>Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="catalog.php"><i class="bi bi-journal-bookmark me-1"></i>Catalog</a>
                </li>

                <?php if (isset($_SESSION['member_id'])): ?>
                    <li class="nav-item">
                        <?php if ($_SESSION['role_id'] == 1): ?>
                            <a class="nav-link active fw-bold text-success" href="admin_dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Admin Panel</a>
                        <?php else: ?>
                            <a class="nav-link fw-bold text-success" href="member_dashboard.php"><i class="bi bi-person-badge me-1"></i>Dashboard</a>
                        <?php endif; ?>
                    </li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <a class="btn btn-outline-danger btn-sm rounded-pill px-4" href="logout.php">
                            <i class="bi bi-box-arrow-right me-1"></i>Logout
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php"><i class="bi bi-person-plus me-1"></i>Membership</a>
                    </li>
                    <li class="nav-item ms-lg-1">
                        <a class="btn btn-sm px-4 rounded-pill text-white fw-semibold" style="background-color: var(--primary-green);" href="login.php">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Login
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Header Banner -->
<header class="admin-header text-center">
    <div class="container">
        <h2><i class="bi bi-journal-plus me-2"></i>Book Management</h2>
        <p>Add new books with cover photos, update existing titles, and manage library inventory.</p>
    </div>
</header>

<!-- Main Container -->
<div class="container mb-5" style="position: relative; z-index: 30;">

    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success custom-alert alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger custom-alert alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Add / Edit Book Form Card -->
    <div class="card card-custom mb-5" style="box-shadow: var(--shadow-lg);">
        <div class="card-header-custom">
            <h5 class="card-header-title">
                <i class="bi <?= $edit_book ? 'bi-pencil-square' : 'bi-plus-circle-fill'; ?>"></i>
                <?= $edit_book ? 'Edit Book Information' : 'Add New Book Title'; ?>
            </h5>
            <?php if ($edit_book): ?>
                <span class="badge bg-warning text-dark border rounded-pill px-3 py-2 fw-semibold">
                    Editing ID #<?= (int) $edit_book['book_id']; ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="card-body p-4">
            <!-- Naka-enctype para sa File Upload -->
            <form action="insert_book.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf']; ?>">
                <input type="hidden" name="action" value="<?= $edit_book ? 'update' : 'add'; ?>">
                <?php if ($edit_book): ?>
                    <input type="hidden" name="book_id" value="<?= (int) $edit_book['book_id']; ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Book Title</label>
                        <input type="text" name="title" class="form-control" placeholder="Enter book title..." maxlength="255" required
                               value="<?= htmlspecialchars($edit_book['title'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Author Name</label>
                        <input type="text" name="author" class="form-control" placeholder="Enter author full name..." maxlength="255" required
                               value="<?= htmlspecialchars($edit_book['author'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
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
                        <label class="form-label">Available Copies</label>
                        <input type="number" name="available_copies" class="form-control" min="0" placeholder="Number of copies..." required
                               value="<?= (int) ($edit_book['available_copies'] ?? 1); ?>">
                    </div>

                    <!-- Cover Photo Upload Field -->
                    <div class="col-12">
                        <label class="form-label">Book Cover Photo <span class="text-muted fw-normal">(Optional, max 2MB: JPG, PNG, WEBP)</span></label>
                        <div class="d-flex align-items-center gap-3">
                            <?php if ($edit_book && !empty($edit_book['cover_image']) && file_exists('uploads/covers/' . $edit_book['cover_image'])): ?>
                                <div>
                                    <img src="uploads/covers/<?= htmlspecialchars($edit_book['cover_image']); ?>" alt="Current Cover" class="edit-cover-preview">
                                </div>
                            <?php endif; ?>
                            <div class="flex-grow-1">
                                <input type="file" name="cover_image" class="form-control" accept="image/jpeg, image/png, image/webp">
                                <?php if ($edit_book && !empty($edit_book['cover_image'])): ?>
                                    <div class="form-text text-muted">Leave empty if you don't want to change the existing cover image.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-theme-success">
                        <i class="bi <?= $edit_book ? 'bi-check-lg' : 'bi-plus-lg'; ?> me-1"></i>
                        <?= $edit_book ? 'Save Changes' : 'Add Book to Catalog'; ?>
                    </button>
                    <?php if ($edit_book): ?>
                        <a href="add_book.php" class="btn btn-outline-secondary rounded-pill px-4 d-inline-flex align-items-center">
                            <i class="bi bi-x-circle me-1"></i> Cancel Edit
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Search Inventory Card -->
    <div class="card card-custom mb-4">
        <div class="card-body p-4">
            <form action="add_book.php" method="GET" class="row g-3 align-items-center">
                <div class="col-md-8 col-lg-9">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search catalog inventory by title or author..." value="<?= htmlspecialchars($search_query); ?>">
                    </div>
                </div>
                <div class="col-md-4 col-lg-3 d-flex gap-2">
                    <button type="submit" class="btn btn-theme-success w-100">
                        <i class="bi bi-search me-1"></i> Search
                    </button>
                    <?php if ($search_query): ?>
                        <a href="add_book.php" class="btn btn-outline-secondary rounded-pill px-3 d-inline-flex align-items-center">
                            <i class="bi bi-x-circle me-1"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Inventory Table Card -->
    <div class="card card-custom">
        <div class="card-header-custom">
            <h5 class="card-header-title">
                <i class="bi bi-journal-bookmark-fill"></i> Current Book Inventory
            </h5>
            <span class="badge bg-light text-dark border rounded-pill px-3 py-2 fw-semibold">
                <?= count($books); ?> Book Title(s)
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4" style="width: 70px;">Cover</th>
                            <th>Title</th>
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
                                    <!-- Cover Thumbnail Column -->
                                    <td class="ps-4">
                                        <?php if (!empty($book['cover_image']) && file_exists('uploads/covers/' . $book['cover_image'])): ?>
                                            <img src="uploads/covers/<?= htmlspecialchars($book['cover_image']); ?>" alt="Cover" class="table-cover-thumb">
                                        <?php else: ?>
                                            <div class="table-cover-placeholder">
                                                <i class="bi bi-book"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-semibold text-dark">
                                        <?= htmlspecialchars($book['title']); ?>
                                    </td>
                                    <td class="text-secondary">
                                        <i class="bi bi-person me-1 text-muted"></i><?= htmlspecialchars($book['author']); ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-category"><?= htmlspecialchars($book['category']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ((int) $book['available_copies'] > 0): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 font-monospace">
                                                <?= (int) $book['available_copies']; ?> available
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-1 font-monospace">
                                                0 available
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="add_book.php?edit=<?= (int) $book['book_id']; ?>#top" class="btn btn-theme-outline-success">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <form action="insert_book.php" method="POST" class="m-0"
                                                  onsubmit="return confirm('Remove this book from the catalog? This cannot be undone.');">
                                                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="book_id" value="<?= (int) $book['book_id']; ?>">
                                                <button type="submit" class="btn btn-theme-outline-danger">
                                                    <i class="bi bi-trash"></i> Remove
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-journal-x fs-1 opacity-50 d-block mb-2"></i>
                                    No books found matching your search.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Dynamic Footer -->
<footer class="footer text-center">
    <div class="container">
        <div class="footer-logo">
            <i class="bi bi-book-half me-2" style="color: var(--accent-emerald);"></i>Community Library System
        </div>
        
        <div class="footer-links my-4 d-flex flex-wrap justify-content-center gap-2 align-items-center">
            <a href="homepage.php">Home</a>
            <span class="footer-divider d-none d-sm-inline">•</span>
            <a href="catalog.php">Catalog</a>

            <?php if (isset($_SESSION['member_id'])): ?>
                <span class="footer-divider d-none d-sm-inline">•</span>
                <?php if ($_SESSION['role_id'] == 1): ?>
                    <a href="admin_dashboard.php">Admin Panel</a>
                <?php else: ?>
                    <a href="member_dashboard.php">Dashboard</a>
                <?php endif; ?>
            <?php else: ?>
                <span class="footer-divider d-none d-sm-inline">•</span>
                <a href="register.php">Membership</a>
            <?php endif; ?>
        </div>

        <p class="text-secondary small mb-0 mt-4" style="opacity: 0.75;">
            &copy; <?= date('Y'); ?> Community Library Management System.<br>Designed for Student Development.
        </p>
    </div>
</footer>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>