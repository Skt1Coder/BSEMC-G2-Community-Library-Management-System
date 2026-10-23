<?php
session_start();
require 'includes/db_connect.php';

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Fetch all categories for the dropdown
$cat_stmt = $pdo->query("SELECT category_id, category FROM category ORDER BY category ASC");
$categories = $cat_stmt->fetchAll();

// search query - Isinama na ang b.cover_image
$query = "SELECT b.book_id, b.title, b.author, b.available_copies, b.cover_image, c.category
          FROM books b
          JOIN category c ON b.category_id = c.category_id
          WHERE 1=1";
$params = [];

if ($search_query) {
    $query .= " AND (b.title LIKE :search OR b.author LIKE :search)";
    $params['search'] = "%$search_query%";
}
if ($category_filter > 0) {
    $query .= " AND b.category_id = :cat_id";
    $params['cat_id'] = $category_filter;
}
$query .= " ORDER BY b.title ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$books = $stmt->fetchAll();

// Books this member currently has borrowed
$borrowed_ids = [];
if (isset($_SESSION['member_id'])) {
    $b = $pdo->prepare("
        SELECT t.book_id FROM transaction t
        JOIN status s ON t.status_id = s.status_id
        WHERE t.member_id = :member_id AND s.status IN ('Borrowed', 'Overdue')
    ");
    $b->execute(['member_id' => $_SESSION['member_id']]);
    $borrowed_ids = $b->fetchAll(PDO::FETCH_COLUMN);
}

// Reservations: how many people are waiting per book
$res_counts = $pdo->query("
    SELECT book_id, COUNT(*) FROM reservations WHERE status = 'Active' GROUP BY book_id
")->fetchAll(PDO::FETCH_KEY_PAIR);

// This member's active reservations
$my_res = [];
if (isset($_SESSION['member_id'])) {
    $r = $pdo->prepare("
        SELECT r.book_id,
               (SELECT COUNT(*) FROM reservations r2
                WHERE r2.book_id = r.book_id AND r2.status = 'Active'
                  AND r2.reservation_id < r.reservation_id) AS ahead
        FROM reservations r
        WHERE r.member_id = :member_id AND r.status = 'Active'
    ");
    $r->execute(['member_id' => $_SESSION['member_id']]);
    $my_res = $r->fetchAll(PDO::FETCH_KEY_PAIR);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalog - Community Library</title>

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

        /* --- Page Header Banner --- */
        .catalog-header {
            background: radial-gradient(circle at 50% 20%, #166534 0%, var(--primary-green) 70%, #062319 100%);
            padding: 50px 0 65px;
            color: white;
            border-bottom-left-radius: 32px;
            border-bottom-right-radius: 32px;
            box-shadow: 0 10px 30px rgba(15, 57, 43, 0.15);
            margin-bottom: -45px;
            position: relative;
            z-index: 10;
        }

        .catalog-header h2 {
            font-size: 2.25rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .catalog-header p {
            color: #d1d5db;
            font-size: 1rem;
            margin-bottom: 0;
        }

        /* --- Search & Filter Bar --- */
        .search-filter-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 20px;
            box-shadow: var(--shadow-lg);
            position: relative;
            z-index: 20;
            margin-bottom: 2.5rem;
        }

        .form-select, .form-control {
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 10px 16px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .form-select:focus, .form-control:focus {
            border-color: var(--accent-emerald);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
        }

        /* --- Book Cards --- */
        .card-custom {
            display: flex;
            flex-direction: column;
            height: 100%;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.25rem;
            transition: all 0.35s cubic-bezier(0.165, 0.84, 0.44, 1);
            box-shadow: var(--shadow-sm);
        }

        .card-custom:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-color: rgba(16, 185, 129, 0.3);
        }

        /* Cover Image Box */
        .book-cover-box {
            width: 100%;
            height: 230px;
            border-radius: 14px;
            overflow: hidden;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            position: relative;
            border: 1px solid var(--border-color);
        }

        .book-cover-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.35s ease;
        }

        .card-custom:hover .book-cover-img {
            transform: scale(1.06);
        }

        .book-cover-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 0.85rem;
            text-align: center;
            padding: 10px;
        }

        .badge-category {
            background-color: var(--accent-green);
            color: var(--primary-light);
            font-weight: 600;
            font-size: 0.75rem;
            padding: 5px 12px;
            border-radius: 50px;
        }

        .book-title {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--text-main);
            line-height: 1.35;
            margin-bottom: 0.25rem;
        }

        .book-author {
            color: var(--text-muted);
            font-size: 0.88rem;
            margin-bottom: 1rem;
        }

        /* Status Badges */
        .status-pill {
            font-size: 0.82rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 50px;
            white-space: nowrap;
        }

        .status-pill.available {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .status-pill.unavailable {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .status-pill.hold {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        /* --- Buttons --- */
        .btn-theme-success {
            background-color: var(--primary-green);
            color: white;
            font-weight: 600;
            border-radius: 50px;
            padding: 8px 18px;
            transition: all 0.25s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-theme-success:hover {
            background-color: var(--primary-hover);
            color: white;
            transform: translateY(-1px);
        }

        .btn-theme-outline {
            border: 1.5px solid var(--primary-light);
            color: var(--primary-light);
            font-weight: 600;
            border-radius: 50px;
            padding: 5px 16px;
            font-size: 0.88rem;
            transition: all 0.2s ease;
            background: transparent;
        }

        .btn-theme-outline:hover {
            background-color: var(--accent-green);
            color: var(--primary-green);
            border-color: var(--accent-green);
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
    </style>
</head>
<body>

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
                    <a class="nav-link active fw-bold text-success" href="catalog.php"><i class="bi bi-journal-bookmark me-1"></i>Catalog</a>
                </li>

                <?php if (isset($_SESSION['member_id'])): ?>
                    <li class="nav-item">
                        <?php if ($_SESSION['role_id'] == 1): ?>
                            <a class="nav-link fw-bold text-success" href="admin_dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Admin Panel</a>
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

<!-- Page Header Banner -->
<header class="catalog-header text-center">
    <div class="container">
        <h2><i class="bi bi-journal-album me-2"></i>Library Catalog</h2>
        <p>Explore available titles, reserve physical copies, or borrow directly.</p>
    </div>
</header>

<!-- Main Content Area -->
<div class="container mb-5" style="position: relative; z-index: 30;">

    <!-- Search & Filter Bar -->
    <div class="search-filter-card">
        <form action="catalog.php" method="GET" class="row g-3 align-items-center">
            <div class="col-12 col-md-4 col-lg-3">
                <select name="category_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id']; ?>" <?= $category_filter == $cat['category_id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($cat['category']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-12 col-md-5 col-lg-6">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 12px 0 0 12px;">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" style="border-radius: 0 12px 12px 0;" placeholder="Search by title or author..." value="<?= htmlspecialchars($search_query); ?>">
                </div>
            </div>

            <div class="col-12 col-md-3 col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-theme-success w-100">
                    <i class="bi bi-search me-1"></i> Search
                </button>
                <?php if ($search_query || $category_filter > 0): ?>
                    <a href="catalog.php" class="btn btn-outline-secondary rounded-pill px-3 d-flex align-items-center">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Books Grid -->
    <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-4">
        <?php if (count($books) > 0): ?>
            <?php foreach ($books as $book): ?>
                <div class="col">
                    <div class="card card-custom">
                        
                        <!-- Top Category Tag -->
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge badge-category"><?= htmlspecialchars($book['category']); ?></span>
                        </div>

                        <!-- Book Cover Image / Fallback -->
                        <div class="book-cover-box">
                            <?php 
                                $cover_path = 'uploads/covers/' . $book['cover_image'];
                                if (!empty($book['cover_image']) && file_exists($cover_path)): 
                            ?>
                                <img src="<?= htmlspecialchars($cover_path); ?>" alt="<?= htmlspecialchars($book['title']); ?>" class="book-cover-img">
                            <?php else: ?>
                                <div class="book-cover-placeholder">
                                    <i class="bi bi-book fs-1 text-success mb-1"></i>
                                    <span class="small fw-semibold text-secondary">No Cover Available</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <h5 class="book-title"><?= htmlspecialchars($book['title']); ?></h5>
                        <p class="book-author">by <?= htmlspecialchars($book['author']); ?></p>

                        <div class="mt-auto border-top pt-3 d-flex justify-content-between align-items-center gap-2 flex-wrap">
                            <?php
                                $bid   = $book['book_id'];
                                $avail = (int) $book['available_copies'];
                                $held  = (int) ($res_counts[$bid] ?? 0);
                                $ahead = isset($my_res[$bid]) ? (int) $my_res[$bid] : null; 
                                $free_copies = $avail - $held;
                            ?>

                            <!-- Availability Indicators -->
                            <?php if ($ahead !== null && $ahead < $avail): ?>
                                <span class="status-pill available"><i class="bi bi-bookmark-check-fill"></i> Reserved for You</span>
                            <?php elseif ($avail == 0): ?>
                                <span class="status-pill unavailable"><i class="bi bi-x-circle-fill"></i> 0 Available</span>
                            <?php elseif ($free_copies > 0): ?>
                                <span class="status-pill available"><i class="bi bi-check-circle-fill"></i> <?= $free_copies; ?> Available</span>
                            <?php else: ?>
                                <span class="status-pill hold"><i class="bi bi-hourglass-split"></i> Copy on Hold</span>
                            <?php endif; ?>

                            <!-- Role-based Action Buttons -->
                            <?php if (isset($_SESSION['member_id']) && $_SESSION['role_id'] == 1): ?>
                                <!-- ADMIN VIEW -->
                                <span class="badge bg-light text-secondary border rounded-pill px-3 py-2 small ms-auto" title="Admins manage books from dashboard">
                                    <i class="bi bi-shield-lock me-1"></i>Admin View
                                </span>
                            <?php elseif (isset($_SESSION['member_id'])): ?>
                                <!-- RESIDENT / MEMBER VIEW -->
                                <?php if (in_array($bid, $borrowed_ids)): ?>
                                    <button class="btn btn-secondary btn-sm rounded-pill px-3" disabled>Borrowed</button>
                                <?php elseif ($ahead !== null && $ahead < $avail): ?>
                                    <form action="borrow_process.php" method="POST" class="m-0">
                                        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf']; ?>">                                
                                        <input type="hidden" name="book_id" value="<?= $bid; ?>">
                                        <button type="submit" class="btn btn-success btn-sm rounded-pill px-3">Borrow</button>
                                    </form>
                                <?php elseif ($ahead !== null): ?>
                                    <span class="badge bg-info text-dark rounded-pill py-2 px-3">#<?= $ahead + 1; ?> in line</span>
                                <?php elseif ($avail > $held): ?>
                                    <form action="borrow_process.php" method="POST" class="m-0">
                                        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf']; ?>">  
                                        <input type="hidden" name="book_id" value="<?= $bid; ?>">
                                        <button type="submit" class="btn btn-theme-outline btn-sm">Borrow</button>
                                    </form>
                                <?php else: ?>
                                    <form action="reserve_process.php" method="POST" class="m-0">
                                        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf']; ?>">  
                                        <input type="hidden" name="action" value="reserve">
                                        <input type="hidden" name="book_id" value="<?= $bid; ?>">
                                        <button type="submit" class="btn btn-outline-warning btn-sm rounded-pill">Reserve</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- GUEST VIEW -->
                                <a href="login.php" class="btn btn-theme-outline btn-sm">
                                    Login to Borrow
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="card p-5 text-center border-0 shadow-sm rounded-4">
                    <div class="mb-3 text-muted display-6"><i class="bi bi-journal-x"></i></div>
                    <h5 class="fw-bold">No books found</h5>
                    <p class="text-muted mb-3">We couldn't find any titles or authors matching your search criteria.</p>
                    <div>
                        <a href="catalog.php" class="btn btn-theme-success">View All Books</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
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