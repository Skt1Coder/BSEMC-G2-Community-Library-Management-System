<?php
session_start();
require 'includes/db_connect.php';
require 'includes/overdue.php';

if (!isset($_SESSION['member_id']) || $_SESSION['role_id'] != 1) {
    header("Location: catalog.php");
    exit();
}

update_overdue_statuses($pdo);
$now = date('Y-m-d H:i:s');

// Fetch Loans with b.cover_image
$stmt = $pdo->prepare("
    SELECT b.title, b.cover_image, m.firstname, m.lastname, t.due_at, s.status,
        CASE WHEN t.due_at < :now THEN CEIL(TIMESTAMPDIFF(SECOND, t.due_at, :now2) / 86400) * :rate ELSE 0 END AS fine
    FROM transaction t
    JOIN books b ON t.book_id = b.book_id
    JOIN members m ON t.member_id = m.member_id
    JOIN status s ON t.status_id = s.status_id
    WHERE s.status IN ('Borrowed', 'Overdue')
    ORDER BY s.status DESC, t.due_at ASC
");
$stmt->execute(['now' => $now, 'now2' => $now, 'rate' => FINE_PER_DAY]);
$loans = $stmt->fetchAll();

// Fetch Reservations with b.cover_image
$res_stmt = $pdo->query("
    SELECT b.title, b.cover_image, COUNT(r.reservation_id) as waiting
    FROM reservations r
    JOIN books b ON r.book_id = b.book_id
    WHERE r.status = 'Active'
    GROUP BY b.book_id, b.title, b.cover_image
    ORDER BY waiting DESC
");
$reservations = $res_stmt->fetchAll();

// Calculate totals for report summary cards
$total_borrowed = 0;
$total_overdue = 0;
$total_fines = 0;
foreach ($loans as $l) {
    if ($l['status'] === 'Overdue') {
        $total_overdue++;
    } else {
        $total_borrowed++;
    }
    $total_fines += $l['fine'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library System Report - <?= date('Y-m-d'); ?></title>
    
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, .navbar-brand { 
            font-family: 'Outfit', sans-serif; 
        }

        /* --- Navbar --- */
        .navbar {
            background: rgba(255, 255, 255, 0.85) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            padding: 0.85rem 0;
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

        /* --- Main Layout --- */
        .report-wrapper {
            flex: 1;
            padding: 40px 0 80px;
        }

        .report-card {
            background: var(--card-bg);
            border-radius: 24px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            padding: 2.5rem;
            margin-bottom: 2rem;
        }

        .report-header {
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-stat-box {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1rem 1.25rem;
            height: 100%;
        }

        .summary-stat-box .stat-label {
            font-size: 0.82rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }

        .summary-stat-box .stat-val {
            font-family: 'Outfit', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary-green);
        }

        /* Custom Tables & Cover Thumbnails */
        .custom-table {
            margin-bottom: 0;
        }

        .custom-table th {
            background-color: #f1f5f9;
            color: var(--text-main);
            font-weight: 700;
            font-size: 0.88rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
            padding: 12px 16px;
        }

        .custom-table td {
            padding: 12px 16px;
            vertical-align: middle;
            font-size: 0.95rem;
            border-bottom: 1px solid var(--border-color);
        }

        .table-cover-thumb {
            width: 38px;
            height: 52px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background-color: #f1f5f9;
        }

        .table-cover-placeholder {
            width: 38px;
            height: 52px;
            border-radius: 6px;
            background-color: #f1f5f9;
            border: 1px dashed #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 1rem;
        }

        /* Status Badges */
        .badge-status-borrowed {
            background-color: #e0f2fe;
            color: #0369a1;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 50px;
        }

        .badge-status-overdue {
            background-color: #fee2e2;
            color: #dc2626;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 50px;
        }

        /* Buttons */
        .btn-print {
            background-color: var(--primary-green);
            color: white;
            font-weight: 600;
            border-radius: 50px;
            padding: 10px 24px;
            border: none;
            transition: all 0.25s ease;
            box-shadow: 0 4px 12px rgba(15, 57, 43, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-print:hover {
            background-color: var(--primary-hover);
            color: white;
            transform: translateY(-2px);
        }

        .btn-back {
            background-color: #e2e8f0;
            color: var(--text-main);
            font-weight: 600;
            border-radius: 50px;
            padding: 10px 20px;
            border: none;
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-back:hover {
            background-color: #cbd5e1;
            color: var(--text-main);
        }

        /* Footer */
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

        /* ==========================================
           --- PRINT STYLES (CLEAN & DEDICATED) ---
           ========================================== */
        @media print {
            /* Hide Web UI components */
            .no-print, 
            nav, 
            .navbar, 
            footer, 
            .footer, 
            .btn, 
            .btn-back, 
            .btn-print, 
            .navbar-toggler {
                display: none !important;
            }

            @page {
                size: A4 portrait;
                margin: 12mm 15mm;
            }

            body {
                background-color: #ffffff !important;
                color: #000000 !important;
                font-size: 10pt;
                line-height: 1.35;
                padding: 0 !important;
                margin: 0 !important;
                min-height: auto !important;
            }

            .container, 
            .container-fluid, 
            .report-wrapper, 
            .report-card {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                box-shadow: none !important;
                border: none !important;
                background: transparent !important;
            }

            .report-header {
                border-bottom: 2px solid #000000 !important;
                padding-bottom: 12px !important;
                margin-bottom: 18px !important;
            }

            .report-header h2 {
                color: #000000 !important;
                font-size: 1.5rem !important;
            }

            .row.g-3 {
                margin-bottom: 18px !important;
            }

            .summary-stat-box {
                border: 1px solid #000000 !important;
                background: #ffffff !important;
                padding: 8px 12px !important;
                border-radius: 6px !important;
            }

            .summary-stat-box .stat-label {
                font-size: 0.72rem !important;
                color: #333333 !important;
            }

            .summary-stat-box .stat-val {
                font-size: 1.3rem !important;
                color: #000000 !important;
            }

            .table-responsive {
                border: none !important;
                overflow: visible !important;
            }

            .custom-table {
                width: 100% !important;
                margin-bottom: 0 !important;
                border-collapse: collapse !important;
            }

            .custom-table th {
                background-color: #f1f5f9 !important;
                color: #000000 !important;
                border-bottom: 2px solid #000000 !important;
                padding: 6px 10px !important;
                font-size: 8.5pt !important;
            }

            .custom-table td {
                padding: 6px 10px !important;
                border-bottom: 1px solid #cbd5e1 !important;
                font-size: 9pt !important;
                color: #000000 !important;
            }

            .table-cover-thumb {
                width: 32px !important;
                height: 44px !important;
            }

            .table-cover-placeholder {
                width: 32px !important;
                height: 44px !important;
            }

            .badge-status-borrowed, 
            .badge-status-overdue {
                background: none !important;
                color: #000000 !important;
                border: 1px solid #000000 !important;
                padding: 2px 6px !important;
                border-radius: 4px !important;
                font-size: 8pt !important;
            }

            tr, td, th, .summary-stat-box {
                page-break-inside: avoid;
            }

            .mt-5.pt-4.border-top {
                margin-top: 20px !important;
                padding-top: 10px !important;
                border-top: 1px solid #000000 !important;
            }
        }

        @media screen and (min-width: 1800px) {
            html { font-size: clamp(16px, 0.85vw, 30px); }
            body { font-size: 1rem; line-height: 1.55; }
            .container { width: min(94vw, 3200px) !important; max-width: 3200px !important; }
        }
    </style>
</head>
<body>

    <!-- Navigation Bar (Hidden on Print) -->
    <nav class="navbar navbar-expand-lg sticky-top no-print">
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
                    <li class="nav-item">
                        <a class="nav-link fw-bold text-success active" href="admin_dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Admin Panel</a>
                    </li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <a class="btn btn-outline-danger btn-sm rounded-pill px-4" href="logout.php">
                            <i class="bi bi-box-arrow-right me-1"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="report-wrapper">
        <div class="container">

            <!-- Action Header / Navigation Controls (Hidden on Print) -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 no-print">
                <a href="admin_dashboard.php" class="btn-back">
                    <i class="bi bi-arrow-left"></i> Back to Admin Dashboard
                </a>
                <button onclick="window.print()" class="btn-print">
                    <i class="bi bi-printer-fill"></i> Print Official Report
                </button>
            </div>

            <!-- Report Outer Container Card -->
            <div class="report-card">
                
                <!-- Document Header -->
                <div class="report-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-file-earmark-bar-graph fs-3 text-success"></i>
                            <h2 class="fw-bold m-0" style="color: var(--primary-green);">Community Library Management System</h2>
                        </div>
                        <p class="text-muted m-0">Official System Operations & Inventory Status Report</p>
                    </div>
                    <div class="text-md-end">
                        <span class="badge bg-light text-dark border px-3 py-2 fw-normal mb-1 d-inline-block">
                            <i class="bi bi-calendar3 me-1 text-primary"></i> Generated: <strong><?= date("F j, Y, g:i a"); ?></strong>
                        </span>
                        <div class="small text-muted">Authorized Personnel Report</div>
                    </div>
                </div>

                <!-- Live Summary Snapshot Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="summary-stat-box">
                            <div class="stat-label">Active Loans</div>
                            <div class="stat-val"><?= number_format($total_borrowed); ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="summary-stat-box">
                            <div class="stat-label">Overdue Books</div>
                            <div class="stat-val text-danger"><?= number_format($total_overdue); ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="summary-stat-box">
                            <div class="stat-label">Accruing Fines</div>
                            <div class="stat-val text-warning">₱<?= number_format($total_fines, 2); ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="summary-stat-box">
                            <div class="stat-label">Reserved Titles</div>
                            <div class="stat-val text-info"><?= number_format(count($reservations)); ?></div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 1: Active & Overdue Loans -->
                <div class="mb-5">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h4 class="fw-bold m-0" style="color: var(--primary-green);">
                            <i class="bi bi-journal-arrow-up me-2 text-success"></i>Active & Overdue Loans
                        </h4>
                        <span class="badge bg-light text-muted border px-2 py-1 small fw-normal"><?= count($loans); ?> Total Records</span>
                    </div>

                    <div class="table-responsive rounded-3 border">
                        <table class="table custom-table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">Cover</th>
                                    <th>Book Title</th>
                                    <th>Borrower</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th class="text-end">Accruing Fine</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($loans) > 0): ?>
                                    <?php foreach($loans as $loan): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($loan['cover_image']) && file_exists('uploads/covers/' . $loan['cover_image'])): ?>
                                                    <img src="uploads/covers/<?= htmlspecialchars($loan['cover_image']); ?>" alt="Cover" class="table-cover-thumb">
                                                <?php else: ?>
                                                    <div class="table-cover-placeholder">
                                                        <i class="bi bi-book"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-semibold text-dark"><?= htmlspecialchars($loan['title']); ?></td>
                                            <td>
                                                <i class="bi bi-person me-1 text-muted"></i>
                                                <?= htmlspecialchars($loan['firstname'] . ' ' . $loan['lastname']); ?>
                                            </td>
                                            <td><?= date("M d, Y", strtotime($loan['due_at'])); ?></td>
                                            <td>
                                                <?php if ($loan['status'] === 'Overdue'): ?>
                                                    <span class="badge-status-overdue">
                                                        <i class="bi bi-exclamation-circle me-1"></i>Overdue
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge-status-borrowed">
                                                        <i class="bi bi-clock me-1"></i>Borrowed
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end fw-bold <?= ($loan['fine'] > 0) ? 'text-danger' : 'text-muted'; ?>">
                                                ₱<?= number_format($loan['fine'], 2); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="bi bi-check-circle text-success fs-4 d-block mb-1"></i>
                                            No active loans or overdue items registered.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SECTION 2: High-Demand Books (Active Reservations) -->
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h4 class="fw-bold m-0" style="color: var(--primary-green);">
                            <i class="bi bi-people-fill me-2 text-success"></i>High-Demand Books (Active Queue)
                        </h4>
                        <span class="badge bg-light text-muted border px-2 py-1 small fw-normal"><?= count($reservations); ?> Books Reserved</span>
                    </div>

                    <div class="table-responsive rounded-3 border">
                        <table class="table custom-table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">Cover</th>
                                    <th>Book Title</th>
                                    <th class="text-end">Total Members Waiting</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($reservations) > 0): ?>
                                    <?php foreach($reservations as $res): ?>
                                        <tr>
                                            <td>
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
                                            <td class="text-end">
                                                <span class="badge bg-success-subtle text-success fw-bold px-3 py-2 rounded-pill">
                                                    <i class="bi bi-person-lines-fill me-1"></i>
                                                    <?= number_format($res['waiting']); ?> Member(s)
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox text-muted fs-4 d-block mb-1"></i>
                                            No active book reservations in queue.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Print Footer / Sign-off Stamp -->
                <div class="mt-5 pt-4 border-top text-center text-muted small">
                    <p class="mb-1">Community Library Management System &bull; System Generated Operational Summary</p>
                    <p class="mb-0">End of Official Report</p>
                </div>

            </div>
        </div>
    </div>

    <!-- Dynamic Footer (Hidden on Print) -->
    <footer class="footer text-center no-print">
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