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
    header("Location: catalog.php");
    exit();
}

// Flip past-due loans from 'Borrowed' to 'Overdue'
update_overdue_statuses($pdo);

$pdo->query("
    UPDATE reservations 
    SET status = 'Cancelled' 
    WHERE status = 'Active' AND expires_at IS NOT NULL AND expires_at < NOW()
");

// Fetch all open loans (Borrowed + Overdue) with b.cover_image
$now = date('Y-m-d H:i:s');

$stmt = $pdo->prepare("
    SELECT t.transaction_id, b.title, b.cover_image, m.firstname, m.lastname, t.borrowed_at, t.due_at,
        (t.due_at < :now1) AS is_late,
        CASE
            WHEN t.due_at < :now2
            THEN CEIL(TIMESTAMPDIFF(SECOND, t.due_at, :now3) / 86400) * :rate
            ELSE 0
        END AS current_fine
    FROM transaction t
    JOIN books b ON t.book_id = b.book_id
    JOIN members m ON t.member_id = m.member_id
    JOIN status s ON t.status_id = s.status_id
    WHERE s.status IN ('Borrowed', 'Overdue')
    ORDER BY is_late DESC, t.due_at ASC
");
$stmt->execute([
    'now1' => $now,
    'now2' => $now,
    'now3' => $now,
    'rate' => FINE_PER_DAY
]);
$active_loans = $stmt->fetchAll();

// Summary numbers
$total_loans    = count($active_loans);
$overdue_count  = 0;
$accruing_fines = 0;
foreach ($active_loans as $loan) {
    if ($loan['is_late']) {
        $overdue_count++;
        $accruing_fines += $loan['current_fine'];
    }
}

// Active reservations grouped by book with b.cover_image
$res_rows = $pdo->query("
    SELECT b.book_id, b.title, b.cover_image, b.available_copies,
           COUNT(r.reservation_id) AS waiting,
           GROUP_CONCAT(CONCAT(r.reservation_id, '::', m.firstname, ' ', m.lastname)
                        ORDER BY r.reservation_id SEPARATOR '||') AS queue
    FROM reservations r
    JOIN books b ON r.book_id = b.book_id
    JOIN members m ON r.member_id = m.member_id
    WHERE r.status = 'Active'
    GROUP BY b.book_id, b.title, b.cover_image, b.available_copies
    ORDER BY waiting DESC, b.title ASC
")->fetchAll();

$total_reservations = 0;
foreach ($res_rows as $row) {
    $total_reservations += (int) $row['waiting'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Community Library</title>
    
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
        .admin-header {
            background: radial-gradient(circle at 50% 20%, #166534 0%, var(--primary-green) 70%, #062319 100%);
            padding: 50px 0 75px;
            color: white;
            border-bottom-left-radius: 32px;
            border-bottom-right-radius: 32px;
            box-shadow: 0 10px 30px rgba(15, 57, 43, 0.15);
            margin-bottom: -55px;
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

        /* Quick Admin Navigation Bar inside Header */
        .admin-actions-bar {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            padding: 8px 16px;
            display: inline-flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-top: 1.25rem;
        }

        .btn-header-action {
            color: white;
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 50px;
            padding: 6px 18px;
            font-size: 0.88rem;
            font-weight: 600;
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-header-action:hover {
            background: white;
            color: var(--primary-green);
            border-color: white;
            transform: translateY(-2px);
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
        .stat-icon.queue { background: #f3e8ff; color: #9333ea; }

        .stat-val {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 1.4rem;
            line-height: 1.1;
            color: var(--text-main);
        }

        .stat-lbl {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* --- Section Cards & Tables --- */
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

        /* Queue items inside reservation table */
        .queue-list-item {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 12px;
            padding: 6px 14px;
            margin-bottom: 6px;
            transition: background 0.2s;
        }

        .queue-list-item:hover {
            background: #f1f5f9;
        }

        /* Buttons */
        .btn-theme-success {
            background-color: var(--primary-green);
            color: white;
            font-weight: 600;
            border-radius: 50px;
            padding: 6px 16px;
            transition: all 0.2s ease;
            border: none;
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
            padding: 4px 12px;
            font-size: 0.8rem;
            transition: all 0.2s ease;
            background: transparent;
        }

        .btn-theme-outline-danger:hover {
            background-color: #fef2f2;
            border-color: #dc2626;
            color: #dc2626;
        }

        /* Custom Alert Styling */
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

        @media (max-width: 768px) {
            .admin-header {
                padding: 40px 0 65px;
                border-radius: 0 0 24px 24px;
            }
            .stats-grid-card {
                padding: 16px;
            }
            .table-responsive { overflow-x: auto; }
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
        <h2><i class="bi bi-shield-lock me-2"></i>Admin Dashboard</h2>
        <p>Monitor current loans, process book returns, and manage reservation queues.</p>
        
        <!-- Quick Action Nav Bar -->
        <div class="admin-actions-bar">
            <a href="manage_members.php" class="btn-header-action">
                <i class="bi bi-people"></i> Manage Members
            </a>
            <a href="add_book.php" class="btn-header-action">
                <i class="bi bi-plus-circle"></i> Add New Book
            </a>
            <a href="admin_report.php" class="btn-header-action">
                <i class="bi bi-printer"></i> Print Reports
            </a>
        </div>
    </div>
</header>

<!-- Main Container -->
<div class="container mb-5" style="position: relative; z-index: 30;">

    <!-- Summary Stat Cards -->
    <div class="stats-grid-card">
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="stat-mini-box">
                    <div class="stat-icon loans"><i class="bi bi-journal-arrow-up"></i></div>
                    <div>
                        <div class="stat-val"><?= $total_loans; ?></div>
                        <div class="stat-lbl">Books Out</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-mini-box">
                    <div class="stat-icon overdue"><i class="bi bi-exclamation-octagon"></i></div>
                    <div>
                        <div class="stat-val <?= $overdue_count > 0 ? 'text-danger' : 'text-success'; ?>"><?= $overdue_count; ?></div>
                        <div class="stat-lbl">Overdue Books</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-mini-box">
                    <div class="stat-icon fines"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <div class="stat-val <?= $accruing_fines > 0 ? 'text-danger' : 'text-success'; ?>">₱<?= number_format($accruing_fines, 2); ?></div>
                        <div class="stat-lbl">Accruing Fines</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-mini-box">
                    <div class="stat-icon queue"><i class="bi bi-people"></i></div>
                    <div>
                        <div class="stat-val"><?= $total_reservations; ?></div>
                        <div class="stat-lbl">Active Holds</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

    <!-- Table 1: Active Loans -->
    <div class="card card-custom mb-5">
        <div class="card-header-custom">
            <h5 class="card-header-title">
                <i class="bi bi-book-half"></i> Active Book Loans
            </h5>
            <span class="badge bg-light text-muted border rounded-pill px-3 py-2 fw-semibold">
                Rate: ₱<?= number_format(FINE_PER_DAY, 2); ?>/day per late book
            </span>
        </div>

        <div class="card-body p-0">
            <?php if (count($active_loans) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4" style="width: 70px;">Cover</th>
                                <th>Book Title</th>
                                <th>Borrower</th>
                                <th>Borrowed Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Fine</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_loans as $loan): ?>
                                <tr class="<?= $loan['is_late'] ? 'table-danger' : ''; ?>">
                                    <td class="ps-4">
                                        <?php if (!empty($loan['cover_image']) && file_exists('uploads/covers/' . $loan['cover_image'])): ?>
                                            <img src="uploads/covers/<?= htmlspecialchars($loan['cover_image']); ?>" alt="Cover" class="table-cover-thumb">
                                        <?php else: ?>
                                            <div class="table-cover-placeholder">
                                                <i class="bi bi-book"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-semibold text-dark">
                                        <?= htmlspecialchars($loan['title']); ?>
                                    </td>
                                    <td>
                                        <i class="bi bi-person me-1 text-muted"></i><?= htmlspecialchars($loan['firstname'] . ' ' . $loan['lastname']); ?>
                                    </td>
                                    <td>
                                        <i class="bi bi-calendar-event text-muted me-1"></i><?= date("M d, Y", strtotime($loan['borrowed_at'])); ?>
                                    </td>
                                    <td class="<?= $loan['is_late'] ? 'text-danger fw-bold' : ''; ?>">
                                        <i class="bi bi-clock text-muted me-1"></i><?= date("M d, Y", strtotime($loan['due_at'])); ?>
                                    </td>
                                    <td>
                                        <?php if ($loan['is_late']): ?>
                                            <span class="status-badge overdue"><i class="bi bi-exclamation-circle"></i> Overdue</span>
                                        <?php else: ?>
                                            <span class="status-badge active"><i class="bi bi-journal-text"></i> Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="<?= $loan['current_fine'] > 0 ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                        ₱<?= number_format($loan['current_fine'], 2); ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <form action="return_process.php" method="POST" class="m-0">
                                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf']; ?>">  
                                            <input type="hidden" name="transaction_id" value="<?= (int) $loan['transaction_id']; ?>">
                                            <button type="submit" class="btn btn-theme-success btn-sm">
                                                <i class="bi bi-check2-circle me-1"></i>Mark Returned
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-journal-check fs-1 opacity-50 d-block mb-2"></i>
                    <p class="mb-0">No active book loans at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Table 2: Book Reservations Queue -->
    <div class="card card-custom">
        <div class="card-header-custom">
            <h5 class="card-header-title">
                <i class="bi bi-bookmark-star"></i> Book Reservation Queues
            </h5>
            <span class="badge bg-light text-dark border rounded-pill px-3 py-2 fw-normal">
                FIFO Queue Management
            </span>
        </div>

        <div class="card-body p-0">
            <?php if (count($res_rows) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4" style="width: 70px;">Cover</th>
                                <th>Book Title</th>
                                <th>Copies Available</th>
                                <th>People Waiting</th>
                                <th>Waiting Line & Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($res_rows as $row): ?>
                                <?php $names = explode('||', $row['queue']); ?>
                                <tr>
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
                                    <td class="<?= $row['available_copies'] > 0 ? 'text-success fw-bold' : 'text-muted'; ?>">
                                        <?= (int) $row['available_copies']; ?> copies
                                    </td>
                                    <td>
                                        <span class="badge bg-warning text-dark font-monospace py-2 px-3 rounded-pill fs-6"><?= (int) $row['waiting']; ?> waiting</span>
                                    </td>
                                    <td>
                                        <ol class="mb-0 ps-3" style="min-width: 320px;">
                                            <?php foreach ($names as $entry): ?>
                                                <?php [$reservation_id, $name] = explode('::', $entry, 2); ?>
                                                <li class="queue-list-item">
                                                    <div class="d-flex justify-content-between align-items-center gap-3">
                                                        <span class="fw-medium text-dark small"><i class="bi bi-person-fill text-muted me-1"></i><?= htmlspecialchars($name); ?></span>
                                                        <form action="reserve_process.php" method="POST" class="m-0"
                                                              onsubmit="return confirm(<?= htmlspecialchars(json_encode('Cancel the reservation for ' . $name . '?'), ENT_QUOTES); ?>);">
                                                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf']; ?>">  
                                                            <input type="hidden" name="action" value="cancel">
                                                            <input type="hidden" name="from" value="admin_dashboard">
                                                            <input type="hidden" name="reservation_id" value="<?= (int) $reservation_id; ?>">
                                                            <button type="submit" class="btn btn-theme-outline-danger">
                                                                <i class="bi bi-x-lg me-1"></i>Cancel
                                                            </button>
                                                        </form>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ol>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-4 text-center text-muted">
                    <p class="mb-0">No active book reservations at the moment.</p>
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