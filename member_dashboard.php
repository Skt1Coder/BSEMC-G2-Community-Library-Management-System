<?php
session_start();
require 'includes/db_connect.php';
require 'includes/overdue.php';

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// SECURITY: Kick out anyone who is not logged in OR is not a Resident (role_id 2)
if (!isset($_SESSION['member_id']) || $_SESSION['role_id'] != 2) {
    header("Location: login.php");
    exit();
}

// Flip any past-due loans from 'Borrowed' to 'Overdue'
update_overdue_statuses($pdo);

// Fetch the member's borrowing history with b.cover_image
$member_id = $_SESSION['member_id'];
$now = date('Y-m-d H:i:s');

$stmt = $pdo->prepare("
    SELECT b.title, b.cover_image, t.borrowed_at, t.due_at, s.status,
        CASE
            WHEN s.status IN ('Borrowed', 'Overdue') AND t.due_at < :now1
            THEN CEIL(TIMESTAMPDIFF(SECOND, t.due_at, :now2) / 86400) * :rate
            ELSE COALESCE(t.fine_amount, 0)
        END AS fine_amount,
        (s.status IN ('Borrowed', 'Overdue') AND t.due_at < :now3) AS is_late
    FROM transaction t
    JOIN books b ON t.book_id = b.book_id
    JOIN status s ON t.status_id = s.status_id
    WHERE t.member_id = :member_id
    ORDER BY t.borrowed_at DESC
");
$stmt->execute([
    'now1'      => $now,
    'now2'      => $now,
    'now3'      => $now,
    'rate'      => FINE_PER_DAY,
    'member_id' => $member_id
]);
$transactions = $stmt->fetchAll();

// Active reservations with queue position and b.cover_image
$res_stmt = $pdo->prepare("
    SELECT r.reservation_id, b.title, b.cover_image, r.reserved_at, b.available_copies,
           (SELECT COUNT(*) FROM reservations r2
            WHERE r2.book_id = r.book_id AND r2.status = 'Active'
              AND r2.reservation_id < r.reservation_id) AS ahead
    FROM reservations r
    JOIN books b ON r.book_id = b.book_id
    WHERE r.member_id = :member_id AND r.status = 'Active'
    ORDER BY r.reserved_at ASC
");
$res_stmt->execute(['member_id' => $member_id]);
$reservations = $res_stmt->fetchAll();

$ready_count = 0;
foreach ($reservations as $res) {
    if ((int) $res['ahead'] < (int) $res['available_copies']) {
        $ready_count++;
    }
}

// Summary for the alert banner
$overdue_count = 0;
$accruing_fines = 0;
$active_loans_count = 0;

foreach ($transactions as $row) {
    if ($row['status'] == 'Borrowed' || $row['status'] == 'Overdue') {
        $active_loans_count++;
    }
    if ($row['is_late']) {
        $overdue_count++;
        $accruing_fines += $row['fine_amount'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - Community Library</title>
    
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

        /* --- Dashboard Banner --- */
        .dashboard-header {
            background: radial-gradient(circle at 50% 20%, #166534 0%, var(--primary-green) 70%, #062319 100%);
            padding: 50px 0 70px;
            color: white;
            border-bottom-left-radius: 32px;
            border-bottom-right-radius: 32px;
            box-shadow: 0 10px 30px rgba(15, 57, 43, 0.15);
            margin-bottom: -50px;
            position: relative;
            z-index: 10;
        }

        .dashboard-header h2 {
            font-size: 2.25rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .dashboard-header p {
            color: #d1d5db;
            font-size: 1rem;
            margin-bottom: 0;
        }

        /* --- Overview Stats Grid --- */
        .stats-grid-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 22px;
            box-shadow: var(--shadow-lg);
            position: relative;
            z-index: 20;
            margin-bottom: 2.5rem;
        }

        .stat-mini-box {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 16px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform 0.2s ease;
        }

        .stat-mini-box:hover {
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            flex-shrink: 0;
        }

        .stat-icon.loans { background: #e0f2fe; color: #0284c7; }
        .stat-icon.overdue { background: #fef2f2; color: #dc2626; }
        .stat-icon.fines { background: #fffbeb; color: #d97706; }
        .stat-icon.ready { background: #ecfdf5; color: #059669; }

        .stat-val {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.4rem;
            line-height: 1.1;
            color: var(--text-main);
        }

        .stat-lbl {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* --- Section Cards --- */
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

        /* Table Cover Thumbnails */
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

        /* Badges */
        .status-badge {
            font-size: 0.78rem;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-badge.overdue {
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .status-badge.active {
            background-color: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .status-badge.returned {
            background-color: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .status-badge.ready {
            background-color: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }

        .status-badge.queue {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        /* --- Buttons --- */
        .btn-theme-success {
            background-color: var(--primary-green);
            color: white;
            font-weight: 600;
            border-radius: 50px;
            padding: 6px 16px;
            transition: all 0.2s ease;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
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
            transition: all 0.2s ease;
            background: transparent;
        }

        .btn-theme-outline-danger:hover {
            background-color: #fef2f2;
            border-color: #dc2626;
            color: #dc2626;
        }

        /* Alert Box */
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

        @media (max-width: 768px) {
            .dashboard-header {
                padding: 40px 0 60px;
                border-radius: 0 0 24px 24px;
            }
            .stats-grid-card {
                padding: 16px;
            }
            .table-responsive { overflow-x: auto; }
        }

        @media screen and (min-width: 1800px) {
            html { font-size: 18px; }
            .container { max-width: 1600px !important; }
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
                    <a class="nav-link" href="catalog.php"><i class="bi bi-journal-bookmark me-1"></i>Catalog</a>
                </li>

                <?php if (isset($_SESSION['member_id'])): ?>
                    <li class="nav-item">
                        <?php if ($_SESSION['role_id'] == 1): ?>
                            <a class="nav-link fw-bold text-success" href="admin_dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Admin Panel</a>
                        <?php else: ?>
                            <a class="nav-link active fw-bold text-success" href="member_dashboard.php"><i class="bi bi-person-badge me-1"></i>Dashboard</a>
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
<header class="dashboard-header text-center">
    <div class="container">
        <h2><i class="bi bi-grid-1x2 me-2"></i>My Dashboard</h2>
        <p>Track your active loans, manage reserved books, and stay updated on dues.</p>
    </div>
</header>

<!-- Main Container -->
<div class="container mb-5" style="position: relative; z-index: 30;">

    <!-- Summary Stat Cards -->
    <div class="stats-grid-card">
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="stat-mini-box">
                    <div class="stat-icon loans"><i class="bi bi-book"></i></div>
                    <div>
                        <div class="stat-val"><?= $active_loans_count; ?></div>
                        <div class="stat-lbl">Active Loans</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-mini-box">
                    <div class="stat-icon overdue"><i class="bi bi-exclamation-octagon"></i></div>
                    <div>
                        <div class="stat-val text-danger"><?= $overdue_count; ?></div>
                        <div class="stat-lbl">Overdue Books</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-mini-box">
                    <div class="stat-icon fines"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <div class="stat-val" style="color: #d97706;">₱<?= number_format($accruing_fines, 2); ?></div>
                        <div class="stat-lbl">Accruing Fines</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-mini-box">
                    <div class="stat-icon ready"><i class="bi bi-bookmark-check"></i></div>
                    <div>
                        <div class="stat-val text-success"><?= $ready_count; ?></div>
                        <div class="stat-lbl">Ready Holds</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Session Notifications -->
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

    <!-- Reservation Ready Alert -->
    <?php if ($ready_count > 0): ?>
        <div class="alert alert-success custom-alert d-flex align-items-start gap-3 mb-4" role="alert">
            <i class="bi bi-bookmark-check-fill fs-3 text-success"></i>
            <div>
                <strong><?= $ready_count; ?> book<?= $ready_count > 1 ? 's' : ''; ?> you reserved <?= $ready_count > 1 ? 'are' : 'is'; ?> ready to borrow!</strong><br>
                Head over to the <a href="catalog.php" class="alert-link text-decoration-underline">catalog</a> and claim <?= $ready_count > 1 ? 'them' : 'it'; ?> before your queue hold expires.
            </div>
        </div>
    <?php endif; ?>

    <!-- Overdue Alert -->
    <?php if ($overdue_count > 0): ?>
        <div class="alert alert-danger custom-alert d-flex align-items-start gap-3 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-3 text-danger"></i>
            <div>
                <strong>You have <?= $overdue_count; ?> overdue book<?= $overdue_count > 1 ? 's' : ''; ?>.</strong><br>
                Current accrued fine: <strong>₱<?= number_format($accruing_fines, 2); ?></strong>
                (₱<?= number_format(FINE_PER_DAY, 2); ?> per day per book). Please return these items to the library as soon as possible.
            </div>
        </div>
    <?php endif; ?>

    <!-- Borrowed Books Section -->
    <div class="card card-custom mb-5">
        <div class="card-header-custom">
            <h5 class="card-header-title">
                <i class="bi bi-book-half"></i> Borrowing History & Active Loans
            </h5>
            <a href="catalog.php" class="btn btn-sm btn-outline-success rounded-pill fw-semibold">
                <i class="bi bi-plus-lg me-1"></i> Browse Catalog
            </a>
        </div>
        
        <div class="card-body p-0">
            <?php if (count($transactions) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4" style="width: 70px;">Cover</th>
                                <th>Book Title</th>
                                <th>Borrowed On</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Fines</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $row): ?>
                                <tr class="<?= $row['is_late'] ? 'table-danger' : ''; ?>">
                                    <td class="ps-4">
                                        <?php if (!empty($row['cover_image']) && file_exists('uploads/covers/' . $row['cover_image'])): ?>
                                            <img src="uploads/covers/<?= htmlspecialchars($row['cover_image']); ?>" alt="Cover" class="table-cover-thumb">
                                        <?php else: ?>
                                            <div class="table-cover-placeholder">
                                                <i class="bi bi-book"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-semibold text-dark">
                                        <?= htmlspecialchars($row['title']); ?>
                                    </td>
                                    <td>
                                        <i class="bi bi-calendar-event text-muted me-1"></i><?= date("M d, Y", strtotime($row['borrowed_at'])); ?>
                                    </td>
                                    <td>
                                        <i class="bi bi-clock text-muted me-1"></i><?= date("M d, Y", strtotime($row['due_at'])); ?>
                                    </td>
                                    <td>
                                        <?php if ($row['is_late'] || $row['status'] == 'Overdue'): ?>
                                            <span class="status-badge overdue"><i class="bi bi-exclamation-circle"></i> Overdue</span>
                                        <?php elseif ($row['status'] == 'Borrowed'): ?>
                                            <span class="status-badge active"><i class="bi bi-journal-text"></i> Active</span>
                                        <?php else: ?>
                                            <span class="status-badge returned"><i class="bi bi-check-lg"></i> Returned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4 <?= $row['fine_amount'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?>">
                                        ₱<?= number_format($row['fine_amount'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-journal-x fs-1 opacity-50 d-block mb-2"></i>
                    <p class="mb-2">You haven't borrowed any books yet.</p>
                    <a href="catalog.php" class="btn btn-theme-success btn-sm mt-1">Explore Books</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- My Reservations Section -->
    <div class="card card-custom">
        <div class="card-header-custom">
            <h5 class="card-header-title">
                <i class="bi bi-bookmark-star"></i> My Reservations
            </h5>
            <span class="badge bg-light text-dark border rounded-pill px-3 py-2 fw-normal">
                Queue System Enabled
            </span>
        </div>
        
        <div class="card-body p-0">
            <?php if (count($reservations) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4" style="width: 70px;">Cover</th>
                                <th>Book Title</th>
                                <th>Reserved On</th>
                                <th>Queue Position</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $res): ?>
                                <?php $is_ready = ((int) $res['ahead'] < (int) $res['available_copies']); ?>
                                <tr>
                                    <td class="ps-4">
                                        <?php if (!empty($res['cover_image']) && file_exists('uploads/covers/' . $res['cover_image'])): ?>
                                            <img src="uploads/covers/<?= htmlspecialchars($res['cover_image']); ?>" alt="Cover" class="table-cover-thumb">
                                        <?php else: ?>
                                            <div class="table-cover-placeholder">
                                                <i class="bi bi-book"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-semibold text-dark">
                                        <?= htmlspecialchars($res['title']); ?>
                                    </td>
                                    <td>
                                        <i class="bi bi-calendar-event text-muted me-1"></i><?= date("M d, Y", strtotime($res['reserved_at'])); ?>
                                    </td>
                                    <td>
                                        <?php if ($is_ready): ?>
                                            <span class="status-badge ready"><i class="bi bi-check-circle-fill"></i> Ready to Borrow</span>
                                        <?php else: ?>
                                            <span class="status-badge queue"><i class="bi bi-hourglass-split"></i> #<?= (int) $res['ahead'] + 1; ?> in queue</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end align-items-center gap-2">
                                            <?php if ($is_ready): ?>
                                                <a href="catalog.php?search=<?= urlencode($res['title']); ?>" class="btn btn-theme-success btn-sm">
                                                    <i class="bi bi-arrow-right-circle"></i> Borrow
                                                </a>
                                            <?php endif; ?>
                                            
                                            <form action="reserve_process.php" method="POST" class="m-0" onsubmit="return confirm('Cancel this reservation?');">
                                                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf']; ?>">  
                                                <input type="hidden" name="action" value="cancel">
                                                <input type="hidden" name="from" value="dashboard">
                                                <input type="hidden" name="reservation_id" value="<?= (int) $res['reservation_id']; ?>">
                                                <button type="submit" class="btn btn-theme-outline-danger btn-sm">
                                                    <i class="bi bi-x-circle me-1"></i>Cancel
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-4 text-center text-muted">
                    <p class="mb-0">You have no active reservations. When a book is out of stock, tap <strong>Reserve</strong> in the catalog to hold your spot.</p>
                </div>
            <?php endif; ?>
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