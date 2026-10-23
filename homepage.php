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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

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
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, .navbar-brand { 
            font-family: 'Outfit', sans-serif; 
        }

        /* --- Navbar Redesign --- */
        .navbar {
            background: rgba(255, 255, 255, 0.85) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
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

        /* --- Hero Section --- */
        .hero-section {
            background: radial-gradient(circle at 50% 20%, #166534 0%, var(--primary-green) 70%, #062319 100%);
            position: relative;
            overflow: hidden;
            padding: 120px 20px 130px;
            border-bottom-left-radius: 40px;
            border-bottom-right-radius: 40px;
            box-shadow: 0 20px 40px rgba(15, 57, 43, 0.2);
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -10%;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            height: 350px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.25) 0%, rgba(0,0,0,0) 70%);
            filter: blur(50px);
            pointer-events: none;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--accent-green);
            padding: 6px 18px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            letter-spacing: 0.3px;
        }

        .hero-title {
            font-weight: 800;
            font-size: 3.6rem;
            line-height: 1.15;
            letter-spacing: -1px;
            margin-bottom: 1.25rem;
            text-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .hero-title span {
            background: linear-gradient(135deg, #a7f3d0 0%, #34d399 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 1.15rem;
            font-weight: 400;
            color: #e2e8f0;
            max-width: 640px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }

        .hero-highlight-pill {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #f1f5f9;
            padding: 10px 22px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .hero-highlight-pill:hover {
            background: rgba(255, 255, 255, 0.18);
            border-color: rgba(255, 255, 255, 0.4);
            transform: translateY(-2px);
        }

        /* --- Quick Access Features Section --- */
        .features-wrapper {
            margin-top: -50px;
            position: relative;
            z-index: 20;
            padding-bottom: 90px;
        }

        .section-header {
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--primary-light);
            letter-spacing: 2px;
            text-transform: uppercase;
            background: var(--accent-green);
            padding: 4px 14px;
            border-radius: 50px;
            display: inline-block;
        }

        .feature-card {
            border: 1px solid var(--border-color);
            border-radius: 20px;
            transition: all 0.35s cubic-bezier(0.165, 0.84, 0.44, 1);
            box-shadow: var(--shadow-sm);
            background-color: var(--card-bg);
            overflow: hidden;
            height: 100%;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .icon-circle {
            width: 52px;
            height: 52px;
            background-color: var(--accent-green);
            color: var(--primary-light);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.25rem;
            transition: all 0.3s ease;
        }

        .feature-card:hover .icon-circle {
            background-color: var(--primary-green);
            color: var(--accent-green);
            transform: scale(1.08) rotate(-4deg);
        }

        .card-title {
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--text-main);
            margin-bottom: 0.75rem;
        }
        
        .card-text {
            color: var(--text-muted);
            font-size: 0.92rem;
            line-height: 1.6;
        }

        .stat-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 10px 14px;
            border: 1px solid #f1f5f9;
        }

        .stat-number {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.35rem;
            color: var(--primary-green);
        }

        .book-item-pill {
            background: #f8fafc;
            border-radius: 10px;
            padding: 8px 12px;
            border: 1px solid #f1f5f9;
            transition: background 0.2s;
        }

        .book-item-pill:hover {
            background: var(--accent-green);
        }

        .book-category-tag {
            font-size: 0.72rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 50px;
            background: #e2e8f0;
            color: var(--text-main);
        }

        /* --- Footer --- */
        .footer {
            background-color: var(--dark-bg);
            padding: 70px 0 35px;
            border-top: 4px solid var(--accent-emerald);
            color: #9ca3af;
        }
        
        .footer-logo {
            font-family: 'Outfit', sans-serif;
            color: white;
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 1.2rem;
        }

        .footer-links a {
            color: #d1d5db;
            text-decoration: none;
            margin: 0 14px;
            font-size: 0.92rem;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: var(--accent-emerald);
        }

        .footer-divider {
            color: #4b5563;
        }

        @media (max-width: 768px) {
            .hero-title { font-size: 2.4rem; }
            .hero-subtitle { font-size: 1rem; }
            .features-wrapper { margin-top: 20px; }
            .hero-section { border-radius: 0 0 24px 24px; padding: 90px 16px 70px; }
        }

        @media screen and (min-width: 1800px) {
            html { font-size: 18px; }
            .container { max-width: 1600px !important; }
        }

        @media screen and (max-width: 767.98px) {
            .navbar-brand {
                font-size: 1rem !important;
                white-space: normal;
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
                <span>Community Library</span>
            </a>
            
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto gap-1 mt-3 mt-lg-0 align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link active" href="homepage.php"><i class="bi bi-house-door me-1"></i>Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="catalog.php"><i class="bi bi-journal-bookmark me-1"></i>Catalog</a>
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

    <!-- Hero Section -->
    <section class="hero-section text-center text-white d-flex align-items-center justify-content-center">
        <div class="container hero-content">
            <div class="hero-badge">
                <i class="bi bi-stars"></i> Modern Knowledge Hub
            </div>
            
            <h1 class="hero-title">Grow Your Mind.<br><span>Connect Your Community.</span></h1>
            <p class="hero-subtitle mb-0">Explore thousands of books, reserve physical resources, and manage loans seamlessly in a modern library space.</p>

            <!-- Feature Highlight Strip -->
            <div class="d-inline-flex flex-wrap gap-3 justify-content-center mt-4">
                <div class="hero-highlight-pill">
                    <i class="bi bi-shield-check text-warning me-1"></i> Free Membership
                </div>
                <div class="hero-highlight-pill">
                    <i class="bi bi-clock-history text-info me-1"></i> 7 Days Borrowing
                </div>
                <div class="hero-highlight-pill">
                    <i class="bi bi-people text-success me-1"></i> Real-Time Reservation Queue
                </div>
            </div>
        </div>
    </section>

    <!-- Dynamic Quick Access Section -->
    <section class="features-wrapper">
        <div class="container">
            <div class="text-center mb-4">
                <span class="section-header">Live Statistics & Overview</span>
            </div>
            
            <div class="row g-4 justify-content-center">
                <!-- Card 1: Collection Stats -->
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card feature-card p-4">
                        <div class="card-body p-0 d-flex flex-column">
                            <div class="icon-circle">
                                <i class="bi bi-collection-fill"></i>
                            </div>
                            <h5 class="card-title">Collection Size</h5>
                            <p class="card-text mb-3">Our catalog is live and updated in real-time by library administrators.</p>
                            
                            <div class="mt-auto pt-3 border-top">
                                <div class="stat-box d-flex justify-content-between align-items-center mb-2">
                                    <span class="small text-muted fw-medium">Total Titles</span>
                                    <span class="stat-number"><?= number_format($total_titles); ?></span>
                                </div>
                                <div class="stat-box d-flex justify-content-between align-items-center">
                                    <span class="small text-muted fw-medium">Categories</span>
                                    <span class="stat-number" style="color: var(--primary-light);"><?= number_format($total_categories); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: New Arrivals -->
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card feature-card p-4">
                        <div class="card-body p-0 d-flex flex-column">
                            <div class="icon-circle">
                                <i class="bi bi-stars"></i>
                            </div>
                            <h5 class="card-title">New Arrivals</h5>
                            
                            <?php if (count($latest_books) > 0): ?>
                                <div class="d-flex flex-column gap-2 mb-3">
                                    <?php foreach ($latest_books as $b): ?>
                                        <div class="book-item-pill">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="fw-semibold text-truncate small" style="max-width: 140px;" title="<?= htmlspecialchars($b['title']); ?>">
                                                    <?= htmlspecialchars($b['title']); ?>
                                                </span>
                                                <span class="book-category-tag"><?= htmlspecialchars($b['category']); ?></span>
                                            </div>
                                            <div class="text-muted small text-truncate" style="font-size: 0.78rem;">
                                                by <?= htmlspecialchars($b['author']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="card-text mb-3">Check out our newly acquired catalog items on the catalog page.</p>
                            <?php endif; ?>
                            
                            <div class="mt-auto pt-3 border-top">
                                <a href="catalog.php" class="btn btn-sm btn-outline-success w-100 rounded-pill fw-semibold">
                                    View in Catalog <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Quick Actions -->
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card feature-card p-4">
                        <div class="card-body p-0 d-flex flex-column">
                            <div class="icon-circle">
                                <i class="bi bi-person-workspace"></i>
                            </div>
                            <h5 class="card-title">Quick Actions</h5>
                            
                            <?php if (isset($_SESSION['member_id']) && $_SESSION['role_id'] == 1): ?>
                                <p class="card-text mb-3">Admin portal to manage catalog inventory, loan returns, and user queues.</p>
                                <div class="mt-auto pt-3 border-top d-flex flex-column gap-2">
                                    <a href="add_book.php" class="btn btn-sm text-white w-100 rounded-pill fw-semibold" style="background-color: var(--primary-light);">
                                        <i class="bi bi-plus-circle me-1"></i> Add New Book
                                    </a>
                                </div>
                            <?php elseif (isset($_SESSION['member_id'])): ?>
                                <p class="card-text mb-3">Review active loans, check upcoming return due dates, and track fines.</p>
                                <div class="mt-auto pt-3 border-top">
                                    <a href="member_dashboard.php" class="btn btn-sm text-white w-100 rounded-pill fw-semibold" style="background-color: var(--primary-light);">
                                        <i class="bi bi-grid-1x2 me-1"></i> My Loans & Fines
                                    </a>
                                </div>
                            <?php else: ?>
                                <p class="card-text mb-3">Register for a member account to borrow physical books and place queue holds.</p>
                                <div class="mt-auto pt-3 border-top">
                                    <a href="register.php" class="btn btn-sm text-white w-100 rounded-pill fw-semibold" style="background-color: var(--primary-light);">
                                        <i class="bi bi-person-plus me-1"></i> Create Account
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Borrowing Rules -->
                <div class="col-12 col-md-6 col-lg-3">
                    <div class="card feature-card p-4">
                        <div class="card-body p-0 d-flex flex-column">
                            <div class="icon-circle">
                                <i class="bi bi-shield-check"></i>
                            </div>
                            <h5 class="card-title">Borrowing Rules</h5>
                            
                            <div class="d-flex flex-column gap-2 mb-3">
                                <div class="d-flex align-items-center gap-2 text-muted small">
                                    <i class="bi bi-clock-history text-success fs-6"></i>
                                    <span>Loan Period: <strong>7 Days</strong></span>
                                </div>
                                <div class="d-flex align-items-center gap-2 text-muted small">
                                    <i class="bi bi-exclamation-circle text-warning fs-6"></i>
                                    <span>Late Return: <strong>₱<?= number_format(FINE_PER_DAY, 2); ?>/day</strong></span>
                                </div>
                            </div>

                            <div class="mt-auto pt-3 border-top">
                                <span class="badge bg-light text-dark border w-100 py-2 font-monospace fw-normal">
                                    <i class="bi bi-info-circle me-1 text-primary"></i> First Come, First Served
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

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