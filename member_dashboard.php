<?php
session_start();
require 'includes/db_connect.php';
require 'includes/overdue.php';

// SECURITY: Kick out anyone who is not logged in OR is not a Resident (role_id 2)
if (!isset($_SESSION['member_id']) || $_SESSION['role_id'] != 2) {
    header("Location: login.php");
    exit();
}

// Flip any past-due loans from 'Borrowed' to 'Overdue'
update_overdue_statuses($pdo);

// Fetch the member's borrowing history.
// For unreturned late books the fine is calculated live; returned books use the saved fine.
$member_id = $_SESSION['member_id'];
$now = date('Y-m-d H:i:s');

$stmt = $pdo->prepare("
    SELECT b.title, t.borrowed_at, t.due_at, s.status,
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

// CSRF token for the Cancel Reservation buttons
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Active reservations with queue position (people ahead of this member)
$res_stmt = $pdo->prepare("
    SELECT r.reservation_id, b.title, r.reserved_at, b.available_copies,
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
foreach ($transactions as $row) {
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

        h1, h2, h3, h4, h5, .navbar-brand { font-family: 'Outfit', sans-serif; }

        .text-theme { color: var(--primary-green); }

        /* Solid Green Navbar - Matches catalog.php */
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
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            flex-shrink: 0;
        }

        .nav-link {
            color: rgba(255,255,255,0.85) !important;
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

        .card-custom { 
            border-radius: 12px; 
            border: 1px solid rgba(0,0,0,0.05); 
            box-shadow: 0 10px 30px rgba(0,0,0,0.04); 
            background: white; 
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
        <button class="navbar-toggler border-0 shadow-none text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
        </button>
        
        <!-- Nav Links -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto gap-2 mt-3 mt-lg-0 align-items-center">
                <li class="nav-item">
                    <a class="nav-link" href="homepage.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="catalog.php">Catalog</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="member_dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                    <a class="btn btn-danger text-white fw-semibold px-4 rounded-pill" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container mt-5 mb-5">
    <h2 class="mb-4 text-theme fw-bold">My Borrowed Books</h2>

    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Reservation Ready Alert -->
    <?php if ($ready_count > 0): ?>
        <div class="alert alert-success d-flex align-items-start gap-3" role="alert">
            <i class="bi bi-bookmark-check-fill fs-4"></i>
            <div>
                <strong><?= $ready_count; ?> book<?= $ready_count > 1 ? 's' : ''; ?> you reserved <?= $ready_count > 1 ? 'are' : 'is'; ?> ready to borrow.</strong><br>
                Go to the <a href="catalog.php" class="alert-link">catalog</a> and borrow <?= $ready_count > 1 ? 'them' : 'it'; ?> before someone else grabs the copy.
            </div>
        </div>
    <?php endif; ?>

    <!-- Overdue Alert -->
    <?php if ($overdue_count > 0): ?>
        <div class="alert alert-danger d-flex align-items-start gap-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-4"></i>
            <div>
                <strong>You have <?= $overdue_count; ?> overdue book<?= $overdue_count > 1 ? 's' : ''; ?>.</strong><br>
                Fines so far: <strong>₱<?= number_format($accruing_fines, 2); ?></strong>
                (₱<?= number_format(FINE_PER_DAY, 2); ?> per day for each book). Please return them to the library as soon as possible, because the fine keeps growing every day.
            </div>
        </div>
    <?php endif; ?>
    
    <div class="card card-custom p-4">
        <?php if (count($transactions) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Book Title</th>
                            <th>Borrowed On</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Fines</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $row): ?>
                            <tr class="<?= $row['is_late'] ? 'table-danger' : ''; ?>">
                                <td><?= htmlspecialchars($row['title']); ?></td>
                                <td><?= date("M d, Y", strtotime($row['borrowed_at'])); ?></td>
                                <td><?= date("M d, Y", strtotime($row['due_at'])); ?></td>
                                <td>
                                    <?php if ($row['is_late'] || $row['status'] == 'Overdue'): ?>
                                        <span class="badge bg-danger">Overdue</span>
                                    <?php elseif ($row['status'] == 'Borrowed'): ?>
                                        <span class="badge bg-warning text-dark">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Returned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="<?= $row['fine_amount'] > 0 ? 'text-danger fw-bold' : 'text-success' ?>">
                                    ₱<?= number_format($row['fine_amount'], 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info bg-light border-0 text-muted">
                You haven't borrowed any books yet. Head over to the catalog to find your next read!
            </div>
        <?php endif; ?>
    </div>

    <!-- My Reservations -->
    <h4 class="mt-5 mb-3 text-theme fw-bold">My Reservations</h4>
    <div class="card card-custom p-4">
        <?php if (count($reservations) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Book Title</th>
                            <th>Reserved On</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $res): ?>
                            <?php $is_ready = ((int) $res['ahead'] < (int) $res['available_copies']); ?>
                            <tr>
                                <td><?= htmlspecialchars($res['title']); ?></td>
                                <td><?= date("M d, Y", strtotime($res['reserved_at'])); ?></td>
                                <td>
                                    <?php if ($is_ready): ?>
                                        <span class="badge bg-success">Ready to borrow</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">#<?= (int) $res['ahead'] + 1; ?> in line</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <?php if ($is_ready): ?>
                                            <a href="catalog.php?search=<?= urlencode($res['title']); ?>" class="btn btn-success btn-sm">Borrow</a>
                                        <?php endif; ?>
                                        <form action="reserve_process.php" method="POST" class="m-0"
                                              onsubmit="return confirm('Cancel this reservation?');">
                                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf']; ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="from" value="dashboard">
                                            <input type="hidden" name="reservation_id" value="<?= (int) $res['reservation_id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Cancel</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-muted">
                You have no active reservations. When a book is out of stock, tap <strong>Reserve</strong> in the catalog to join the line.
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>