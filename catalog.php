<?php
session_start();
require 'includes/db_connect.php';

$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Fetch all categories for the dropdown
$cat_stmt = $pdo->query("SELECT category_id, category FROM category ORDER BY category ASC");
$categories = $cat_stmt->fetchAll();

// Dynamically build the search query
$query = "SELECT b.book_id, b.title, b.author, b.available_copies, c.category
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

// This member's active reservations: book_id => how many people are ahead of them
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

// CSRF token for the Reserve buttons
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalog - Community Library</title>

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-green: #14532d;
            --primary-hover: #166534;
            --accent-green: #dcfce7;
            --dark-bg: #1c1917;
            --light-bg: #fafaf9;
            --text-main: #292524;
            --text-muted: #78716c;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-main);
        }

        h1, h2, h3, h4, h5, .navbar-brand {
            font-family: 'Outfit', sans-serif;
        }

        .text-theme {
            color: var(--primary-green);
        }

        /* Navbar */
        .navbar {
            background-color: var(--primary-green) !important;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            color: white !important;
            font-size: 1.25rem;
            letter-spacing: -0.5px;
        }

        .logo-box {
            width: 40px;
            height: 40px;
            background: white;
            color: var(--primary-green);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            flex-shrink: 0;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.85) !important;
            font-weight: 500;
            font-size: 0.95rem;
            padding: 0.5rem 1rem !important;
            transition: all 0.3s ease;
            border-radius: 8px;
        }

        .nav-link:hover,
        .nav-link.active {
            color: white !important;
            background-color: rgba(255, 255, 255, 0.15);
        }

        /* Book cards */
        .card-custom {
            display: flex;
            flex-direction: column;
            height: 100%;
            background: white;
            border: 1px solid #e7e5e4;
            border-radius: 14px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }

        .badge-category {
            background-color: var(--accent-green);
            color: var(--primary-green);
            font-weight: 600;
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <!-- Logo -->
        <a class="navbar-brand" href="homepage.php">
            <div class="logo-box"><i class="bi bi-book-half"></i></div>
            Community Library Management System
        </a>

        <!-- Mobile Toggle Button -->
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Nav Links -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto gap-2 mt-3 mt-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="homepage.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="catalog.php">Catalog</a>
                </li>

                <!-- Dynamic Auth Options -->
                <?php if (isset($_SESSION['member_id'])): ?>
                    <li class="nav-item">
                        <?php if ($_SESSION['role_id'] == 1): ?>
                            <!-- Admins -->
                            <a class="nav-link fw-bold" style="color: #dcfce7;" href="admin_dashboard.php">Admin Panel</a>
                        <?php else: ?>
                            <!-- Residents -->
                            <a class="nav-link fw-bold" style="color: #dcfce7;" href="member_dashboard.php">Dashboard</a>
                        <?php endif; ?>
                    </li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <a class="btn fw-semibold px-4 rounded-pill" 
                        style="background-color: transparent; border: 1.5px solid white; color: white; transition: all 0.2s;" 
                        onmouseover="this.style.backgroundColor='var(--accent-green)'; this.style.borderColor='var(--accent-green)'; this.style.color='var(--primary-green)';" 
                        onmouseout="this.style.backgroundColor='transparent'; this.style.borderColor='white'; this.style.color='white';" 
                        href="logout.php">Logout</a>
                    </li>
                <?php else: ?>
                    <!-- Logged-out users -->
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Membership</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container mt-5 mb-5">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="text-theme fw-bold mb-0">Library Catalog</h2>
        </div>
        <div class="col-md-6">
            <!-- Search Form -->
            <form action="catalog.php" method="GET" class="d-flex gap-2">
                <select name="category_id" class="form-select" style="max-width: 180px;" onchange="this.form.submit()">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id']; ?>" <?= $category_filter == $cat['category_id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($cat['category']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="search" class="form-control" placeholder="Search by title or author..." value="<?= htmlspecialchars($search_query); ?>">
                <button type="submit" class="btn btn-success px-4" style="background-color: #14532d;">Search</button>
                <?php if ($search_query || $category_filter > 0): ?>
                    <a href="catalog.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Books Grid -->
    <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-4">
        <?php if (count($books) > 0): ?>
            <?php foreach ($books as $book): ?>
                <div class="col">
                    <div class="card card-custom p-3">
                        <span class="badge badge-category mb-2 align-self-start"><?= htmlspecialchars($book['category']); ?></span>
                        <h5 class="card-title fw-bold text-dark mb-1"><?= htmlspecialchars($book['title']); ?></h5>
                        <p class="text-muted small mb-3">by <?= htmlspecialchars($book['author']); ?></p>

                        <div class="mt-auto border-top pt-3 d-flex justify-content-between align-items-center">
                            <?php
                                $bid   = $book['book_id'];
                                $avail = (int) $book['available_copies'];
                                $held  = (int) ($res_counts[$bid] ?? 0);
                                $ahead = isset($my_res[$bid]) ? (int) $my_res[$bid] : null; 

                                $free_copies = $avail - $held;
                            ?>

                            <!-- Dynamic Availability Text -->
                            <?php if ($ahead !== null && $ahead < $avail): ?>
                                <span class="small fw-bold text-success">Reserved for You</span>
                            <?php elseif ($avail == 0): ?>
                                <span class="small fw-bold text-danger">0 Available</span>
                            <?php elseif ($free_copies > 0): ?>
                                <span class="small fw-bold text-success"><?= $free_copies; ?> Available</span>
                            <?php else: ?>
                                <span class="small fw-bold" style="color: #b45309;">Copy on Hold</span>
                            <?php endif; ?>

                            <!-- Button Logic -->
                            <?php if (in_array($bid, $borrowed_ids)): ?>
                                <button class="btn btn-secondary btn-sm" disabled>Already Borrowed</button>
                            <?php elseif ($ahead !== null && $ahead < $avail): ?>
                                <form action="borrow_process.php" method="POST" class="m-0">                                
                                    <input type="hidden" name="book_id" value="<?= $bid; ?>">
                                    <button type="submit" class="btn btn-success btn-sm">Borrow (reserved)</button>
                                </form>
                            <?php elseif ($ahead !== null): ?>
                                <span class="badge bg-info text-dark">Reserved &middot; #<?= $ahead + 1; ?> in line</span>
                            <?php elseif ($avail > $held): ?>
                                <form action="borrow_process.php" method="POST" class="m-0">
                                    <input type="hidden" name="book_id" value="<?= $bid; ?>">
                                    <button type="submit" class="btn btn-outline-success btn-sm">Borrow</button>
                                </form>
                            <?php else: ?>
                                <form action="reserve_process.php" method="POST" class="m-0">
                                    <input type="hidden" name="action" value="reserve">
                                    <input type="hidden" name="book_id" value="<?= $bid; ?>">
                                    <button type="submit" class="btn btn-outline-warning btn-sm">Reserve</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-warning text-center">No books found matching your search.</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>