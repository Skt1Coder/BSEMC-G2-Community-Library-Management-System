<?php
session_start();
require 'includes/db_connect.php';

// Check if the user submitted a search query
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search_query) {
    $stmt = $pdo->prepare("
        SELECT b.book_id, b.title, b.author, b.available_copies, c.category 
        FROM books b 
        JOIN category c ON b.category_id = c.category_id 
        WHERE b.title LIKE :search OR b.author LIKE :search
        ORDER BY b.title ASC
    ");
    $stmt->execute(['search' => "%$search_query%"]);
} else {
    $stmt = $pdo->query("
        SELECT b.book_id, b.title, b.author, b.available_copies, c.category 
        FROM books b 
        JOIN category c ON b.category_id = c.category_id 
        ORDER BY b.title ASC
    ");
}
$books = $stmt->fetchAll();
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

        /* Exact Homepage Navbar Styling */
        .navbar {
            background-color: var(--primary-green) !important;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            padding: 1rem 0;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            color: white !important; /* Changed to white for contrast */
            font-size: 1.25rem;
            letter-spacing: -0.5px;
        }
        
        .logo-box {
            width: 40px;
            height: 40px;
            background: white; /* Inverted logo box */
            color: var(--primary-green);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            flex-shrink: 0;
        }

        .nav-link {
            color: rgba(255,255,255,0.85) !important; /* Off-white for resting links */
            font-weight: 500;
            font-size: 0.95rem;
            padding: 0.5rem 1rem !important;
            transition: all 0.3s ease;
            border-radius: 8px;
        }

        .nav-link:hover, .nav-link.active {
            color: white !important;
            background-color: rgba(255,255,255,0.15);
        }
    </style>
</head>

<!-- Navigation -->
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
                            <!-- What Admins see -->
                            <a class="nav-link fw-bold" style="color: #dcfce7;" href="admin_dashboard.php">Admin Panel</a>
                        <?php else: ?>
                            <!-- What Residents see -->
                            <a class="nav-link fw-bold" style="color: #dcfce7;" href="member_dashboard.php">Dashboard</a>
                        <?php endif; ?>
                    </li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <a class="btn btn-danger text-white fw-semibold px-4 rounded-pill" href="logout.php">Logout</a>
                    </li>
                <?php else: ?>
                    <!-- What logged-out users see -->
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
            <form action="catalog.php" method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="Search by title or author..." value="<?= htmlspecialchars($search_query); ?>">
                <button type="submit" class="btn btn-success" style="background-color: #2b5329;">Search</button>
                <?php if($search_query): ?>
                    <a href="catalog.php" class="btn btn-secondary ms-2">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

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
                            <span class="small fw-bold <?= $book['available_copies'] > 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $book['available_copies']; ?> Available
                            </span>
                            
                            <?php if ($book['available_copies'] > 0): ?>
                                <form action="borrow_process.php" method="POST" class="m-0">
                                    <input type="hidden" name="book_id" value="<?= $book['book_id']; ?>">
                                    <button type="submit" class="btn btn-outline-success btn-sm">Borrow</button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-outline-danger btn-sm" disabled>Out of Stock</button>
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