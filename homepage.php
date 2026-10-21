<?php
session_start();
require 'includes/db_connect.php';
require_once 'includes/overdue.php'; 

try {
    $total_titles     = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
    $total_categories = $pdo->query("SELECT COUNT(*) FROM category")->fetchColumn();

    $latest_stmt = $pdo->query("
        SELECT b.title, b.author, c.category 
        FROM books b 
        JOIN category c ON b.category_id = c.category_id 
        ORDER BY b.book_id DESC LIMIT 3
    ");
    $latest_books = $latest_stmt->fetchAll();
} catch (Exception $e) {
    // Fallback if queries fail
    $total_titles = 0;
    $total_categories = 0;
    $latest_books = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Library Management System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">

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

        h1, h2, h3, h4, h5, .navbar-brand { font-family: 'Outfit', sans-serif; }

        .navbar {
            background-color: rgba(250, 250, 249, 0.95) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1rem 0;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            color: var(--primary-green) !important;
            font-size: 1.25rem;
            letter-spacing: -0.5px;
        }
        
        .logo-box {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-green), #22c55e);
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            box-shadow: 0 4px 10px rgba(20, 83, 45, 0.2);
            flex-shrink: 0;
        }

        .nav-link {
            color: var(--text-main) !important;
            font-weight: 500;
            font-size: 0.95rem;
            padding: 0.5rem 1rem !important;
            transition: all 0.3s ease;
            border-radius: 8px;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary-green) !important;
            background-color: var(--accent-green);
        }

        .hero-section {
            background: linear-gradient(135deg, var(--primary-green) 0%, #166534 100%);
            position: relative;
            overflow: hidden;
            padding: 140px 20px 120px;
            border-bottom-left-radius: 50px;
            border-bottom-right-radius: 50px;
            box-shadow: 0 10px 30px rgba(20, 83, 45, 0.15);
        }

        .hero-title {
            font-weight: 800;
            font-size: 3.5rem;
            line-height: 1.2;
            letter-spacing: -1px;
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .hero-subtitle {
            font-size: 1.15rem;
            font-weight: 400;
            color: #dcfce7;
            margin-bottom: 2.5rem;
            max-width: 650px;
            margin-left: auto;
            margin-right: auto;
        }

        .btn-primary-custom {
            background-color: var(--light-bg);
            color: var(--primary-green);
            font-weight: 600;
            padding: 14px 32px;
            border-radius: 50px;
            border: 2px solid var(--light-bg);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .btn-primary-custom:hover {
            background-color: #ffffff;
            color: var(--primary-hover);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .btn-outline-custom {
            background-color: rgba(255,255,255,0.05);
            backdrop-filter: blur(5px);
            color: white;
            font-weight: 600;
            padding: 14px 32px;
            border-radius: 50px;
            border: 2px solid rgba(220, 252, 231, 0.4);
            transition: all 0.3s;
        }

        .btn-outline-custom:hover {
            background-color: rgba(220, 252, 231, 0.15);
            border-color: var(--accent-green);
            transform: translateY(-3px);
            color: white;
        }

        .features-wrapper {
            margin-top: -70px;
            position: relative;
            z-index: 20;
            padding-bottom: 90px;
        }

        .section-header {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-muted);
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .feature-card {
            border: 1px solid rgba(20, 83, 45, 0.05);
            border-radius: 24px;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.04);
            background-color: white;
            overflow: hidden;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(20, 83, 45, 0.08);
            border-color: var(--accent-green);
        }

        .icon-circle {
            width: 56px;
            height: 56px;
            background-color: var(--accent-green);
            color: var(--primary-green);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 1.25rem;
            transition: all 0.3s ease;
        }

        .feature-card:hover .icon-circle {
            background-color: var(--primary-green);
            color: var(--accent-green);
            transform: scale(1.05) rotate(-5deg);
        }

        .card-title {
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--text-main);
            margin-bottom: 0.75rem;
        }
        
        .card-text {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .footer {
            background-color: var(--dark-bg);
            padding: 70px 0 40px;
            border-top: 4px solid var(--primary-green);
        }
        
        .footer-logo {
            font-family: 'Outfit', sans-serif;
            color: white;
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.2rem;
        }

        .footer-links a {
            color: #a8a29e;
            text-decoration: none;
            margin: 0 15px;
            font-size: 0.95rem;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: var(--accent-green);
        }

        @media (max-width: 768px) {
            .hero-title { font-size: 2.5rem; }
            .features-wrapper { margin-top: 30px; }
            .hero-section { border-radius: 0 0 30px 30px; padding: 100px 20px 80px; }
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
                <ul class="navbar-nav ms-auto gap-2 mt-3 mt-lg-0 align-items-center">
                    <li class="nav-item">
                        <a class="nav-link active" href="homepage.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="catalog.php">Catalog</a>
                    </li>

                    <?php if (isset($_SESSION['member_id'])): ?>
                        <li class="nav-item">
                            <?php if ($_SESSION['role_id'] == 1): ?>
                                <a class="nav-link fw-bold" style="color: #14532d;" href="admin_dashboard.php">Admin Panel</a>
                            <?php else: ?>
                                <a class="nav-link fw-bold" style="color: #14532d;" href="member_dashboard.php">Dashboard</a>
                            <?php endif; ?>
                        </li>
                        <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                            <a class="btn fw-semibold px-4 rounded-pill" 
                            style="background-color: transparent; border: 1.5px solid var(--primary-green); color: var(--primary-green); transition: all 0.2s;" 
                            onmouseover="this.style.backgroundColor='var(--accent-green)'; this.style.borderColor='var(--accent-green)';" 
                            onmouseout="this.style.backgroundColor='transparent'; this.style.borderColor='var(--primary-green)';" 
                            href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Membership</a>
                        </li>
                        <li class="nav-item me-1">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section text-center text-white d-flex align-items-center justify-content-center">
        <div class="container hero-content">
            <h1 class="hero-title">Grow Your Mind.<br>Connect Your Community.</h1>
            <p class="hero-subtitle">Explore thousands of books, reserve physical resources, and manage loans in a seamless modern environment.</p>
            
            <div class="d-flex gap-3 justify-content-center">
                <?php if (isset($_SESSION['member_id'])): ?>
                    <a href="catalog.php" class="btn btn-primary-custom">Browse Catalog</a>
                    <?php if ($_SESSION['role_id'] == 1): ?>
                        <a href="admin_dashboard.php" class="btn btn-outline-custom">Admin Panel</a>
                    <?php else: ?>
                        <a href="member_dashboard.php" class="btn btn-outline-custom">My Dashboard</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="register.php" class="btn btn-primary-custom">Become a Member</a>
                    <a href="catalog.php" class="btn btn-outline-custom">Explore Catalog</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Dynamic Quick Access Section -->
    <section class="features-wrapper">
        <div class="container">
            <div class="text-center mb-5 d-none d-md-block">
                <p class="section-header">Overview</p>
            </div>
            
            <div class="row g-4 justify-content-center">
                <!-- Card 1 -->
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card feature-card h-100 p-4">
                        <div class="card-body p-0 d-flex flex-column">
                            <div class="icon-circle">
                                <i class="bi bi-collection-fill"></i>
                            </div>
                            <h5 class="card-title">Collection Size</h5>
                            <p class="card-text mb-3">Our catalog is live and updated in real-time by library administrators.</p>
                            <div class="mt-auto pt-3 border-top">
                                <div class="d-flex justify-content-between align-items-center small">
                                    <span class="text-muted">Total Titles:</span>
                                    <span class="fw-bold text-success fs-6"><?= $total_titles; ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center small mt-1">
                                    <span class="text-muted">Categories:</span>
                                    <span class="fw-bold text-dark fs-6"><?= $total_categories; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card feature-card h-100 p-4">
                        <div class="card-body p-0 d-flex flex-column">
                            <div class="icon-circle">
                                <i class="bi bi-stars"></i>
                            </div>
                            <h5 class="card-title">New Arrivals</h5>
                            <?php if (count($latest_books) > 0): ?>
                                <ul class="list-unstyled mb-3 small">
                                    <?php foreach ($latest_books as $b): ?>
                                        <li class="mb-2 text-truncate">
                                            <i class="bi bi-book me-1 text-success"></i>
                                            <strong><?= htmlspecialchars($b['title']); ?></strong>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="card-text mb-3">Check out our newly acquired catalog items on the catalog page.</p>
                            <?php endif; ?>
                            <div class="mt-auto pt-3 border-top">
                                <a href="catalog.php" class="btn btn-sm btn-outline-success w-100 rounded-pill">View in Catalog</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card feature-card h-100 p-4">
                        <div class="card-body p-0 d-flex flex-column">
                            <div class="icon-circle">
                                <i class="bi bi-person-workspace"></i>
                            </div>
                            <h5 class="card-title">Quick Actions</h5>
                            <?php if (isset($_SESSION['member_id']) && $_SESSION['role_id'] == 1): ?>
                                <p class="card-text mb-3">Admin options to manage inventory, return loans, and review queues.</p>
                                <div class="mt-auto pt-3 border-top">
                                    <a href="add_book.php" class="btn btn-sm btn-success w-100 rounded-pill mb-2">+ Add New Book</a>
                                    <a href="admin_dashboard.php" class="btn btn-sm btn-outline-success w-100 rounded-pill">Admin Dashboard</a>
                                </div>
                            <?php elseif (isset($_SESSION['member_id'])): ?>
                                <p class="card-text mb-3">Review your borrowed items, due dates, and active book reservations.</p>
                                <div class="mt-auto pt-3 border-top">
                                    <a href="member_dashboard.php" class="btn btn-sm btn-success w-100 rounded-pill">My Loans & Fines</a>
                                </div>
                            <?php else: ?>
                                <p class="card-text mb-3">Register for a member account to borrow books and place reservations.</p>
                                <div class="mt-auto pt-3 border-top">
                                    <a href="register.php" class="btn btn-sm btn-success w-100 rounded-pill">Create Account</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Card 4 -->
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card feature-card h-100 p-4">
                        <div class="card-body p-0 d-flex flex-column">
                            <div class="icon-circle">
                                <i class="bi bi-shield-check"></i>
                            </div>
                            <h5 class="card-title">Borrowing Rules</h5>
                            <p class="card-text mb-2 small text-muted">
                                Standard loan period is <strong>7 days</strong> per book.
                            </p>
                            <p class="card-text mb-3 small text-muted">
                                Late returns incur an automated fine of <strong>₱<?= number_format(FINE_PER_DAY, 2); ?>/day</strong>.
                            </p>
                            <div class="mt-auto pt-3 border-top">
                                <span class="badge bg-light text-dark border w-100 py-2">First Come, First Served Queue</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer text-center">
        <div class="container">
            <div class="footer-logo">
                <i class="bi bi-book-half me-2" style="color: var(--accent-green);"></i>Community Library Management System
            </div>
            <div class="footer-links my-4 d-flex flex-wrap justify-content-center gap-2">
                <a href="homepage.php">Home</a>
                <span class="footer-divider d-none d-sm-inline">•</span>
                <a href="catalog.php">Catalog</a>
                <span class="footer-divider d-none d-sm-inline">•</span>
                <a href="register.php">Membership</a>
            </div>
            <p class="text-secondary small mb-0 mt-4" style="opacity: 0.7;">
                &copy; <?= date('Y'); ?> Community Library Management System.<br>Designed for Student Development.
            </p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>