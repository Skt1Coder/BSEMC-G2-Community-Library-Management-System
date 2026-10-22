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

// Fetch all open loans (Borrowed + Overdue) with a live fine for late ones
$now = date('Y-m-d H:i:s');

$stmt = $pdo->prepare("
    SELECT t.transaction_id, b.title, m.firstname, m.lastname, t.borrowed_at, t.due_at,
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


// Active reservations grouped by book, with the waiting line in order
// Each queue entry is "reservation_id::Full Name" so every person can be cancelled individually
$res_rows = $pdo->query("
    SELECT b.book_id, b.title, b.available_copies,
           COUNT(r.reservation_id) AS waiting,
           GROUP_CONCAT(CONCAT(r.reservation_id, '::', m.firstname, ' ', m.lastname)
                        ORDER BY r.reservation_id SEPARATOR '||') AS queue
    FROM reservations r
    JOIN books b ON r.book_id = b.book_id
    JOIN members m ON r.member_id = m.member_id
    WHERE r.status = 'Active'
    GROUP BY b.book_id, b.title, b.available_copies
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
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-green: #14532d;
            --accent-green: #dcfce7;
            --light-bg: #fafaf9;
            --text-main: #292524;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--light-bg); color: var(--text-main); }
        h1, h2, h3, h4, h5, .navbar-brand { font-family: 'Outfit', sans-serif; }

        /* Navbar Desings */
        .navbar { background-color: var(--primary-green) !important; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 1rem 0; }
        .navbar-brand { display: flex; align-items: center; gap: 12px; font-weight: 800; color: white !important; font-size: 1.25rem; }
        .logo-box { width: 40px; height: 40px; background: white; color: var(--primary-green); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .nav-link { color: rgba(255,255,255,0.85) !important; font-weight: 500; padding: 0.5rem 1rem !important; border-radius: 8px; }
        .nav-link:hover, .nav-link.active { color: white !important; background-color: rgba(255,255,255,0.15); }

        .card-custom { border-radius: 12px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 10px 30px rgba(0,0,0,0.04); background: white; }
        .stat-number { font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.75rem; line-height: 1.1; }

            @media screen and (min-width: 1800px) {
        html {
            font-size: clamp(16px, 0.85vw, 30px);
        }

        body {
            font-size: 1rem;
            line-height: 1.55;
        }

        .container {
            width: min(94vw, 3200px) !important;
            max-width: 3200px !important;
        }

        .container-fluid {
            padding-left: clamp(1rem, 2vw, 4rem);
            padding-right: clamp(1rem, 2vw, 4rem);
        }

        .row {
            --bs-gutter-x: clamp(1rem, 1.35vw, 3rem);
            --bs-gutter-y: clamp(0.75rem, 0.9vw, 2rem);
        }

        .navbar {
            padding-top: clamp(0.65rem, 0.75vw, 1.5rem) !important;
            padding-bottom: clamp(0.65rem, 0.75vw, 1.5rem) !important;
        }

        .navbar-brand {
            font-size: clamp(1.1rem, 0.8vw, 1.75rem) !important;
            gap: clamp(0.5rem, 0.65vw, 1.25rem);
            letter-spacing: normal;
            line-height: 1.25;
            white-space: normal;
            max-width: 60%;
        }

        .nav-link {
            font-size: clamp(0.9rem, 0.55vw, 1.2rem) !important;
            padding: 0.55em 0.9em !important;
        }

        .logo-box {
            width: clamp(40px, 1.8vw, 68px);
            height: clamp(40px, 1.8vw, 68px);
            font-size: clamp(1.1rem, 0.75vw, 1.8rem);
        }

        /* Headings */
        h1 {
            font-size: clamp(2rem, 2vw, 4.5rem);
            line-height: 1.15;
        }

        h2 {
            font-size: clamp(1.65rem, 1.45vw, 3.1rem);
            line-height: 1.2;
        }

        h3 {
            font-size: clamp(1.35rem, 1.1vw, 2.4rem);
            line-height: 1.25;
        }

        h4 {
            font-size: clamp(1.15rem, 0.9vw, 2rem);
        }

        .form-control,
        .form-select {
            font-size: clamp(0.9rem, 0.55vw, 1.2rem);
            min-height: clamp(2.5rem, 2.2vw, 3.8rem);
            padding: 0.65em 0.85em;
            min-width: 0;
        }

        .form-label {
            font-size: clamp(0.9rem, 0.55vw, 1.15rem);
        }

        .btn,
        .btn-primary-custom,
        .btn-outline-custom {
            font-size: clamp(0.85rem, 0.52vw, 1.2rem);
            line-height: 1.35;
            white-space: normal;
        }

        .card,
        .card-custom,
        .feature-card {
            border-radius: clamp(12px, 0.75vw, 24px);
        }

        .card-body,
        .card-custom,
        .feature-card {
            min-width: 0;
        }

        .stat-number {
            font-size: clamp(1.75rem, 1.35vw, 3rem) !important;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .icon-circle {
            width: clamp(48px, 1.8vw, 68px);
            height: clamp(48px, 1.8vw, 68px);
            font-size: clamp(1.2rem, 0.8vw, 2rem);
        }

        .card-title {
            font-size: clamp(1rem, 0.68vw, 1.45rem);
        }

        .card-text {
            font-size: clamp(0.9rem, 0.58vw, 1.2rem);
            overflow-wrap: anywhere;
        }

        .table {
            font-size: clamp(0.9rem, 0.55vw, 1.2rem);
            vertical-align: middle;
        }

        .table > :not(caption) > * > * {
            padding: clamp(0.55rem, 0.55vw, 1.2rem);
            vertical-align: middle;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .hero-section {
            padding: clamp(100px, 4.8vw, 200px)
                    clamp(20px, 2vw, 64px)
                    clamp(85px, 4vw, 165px);
        }

        .hero-title {
            font-size: clamp(3rem, 3vw, 6.5rem);
            line-height: 1.12;
        }

        .hero-subtitle {
            font-size: clamp(1rem, 0.85vw, 1.65rem);
            max-width: min(72vw, 1200px);
            line-height: 1.6;
        }

        .features-wrapper {
            padding-bottom: clamp(3rem, 4vw, 8rem);
        }

        .feature-card {
            height: 100%;
        }

        .auth-card {
            width: min(88vw, 3000px) !important;
            max-width: min(88vw, 3000px) !important;
        }

        .auth-sidebar,
        .auth-form-wrapper {
            padding: clamp(2rem, 2.6vw, 5rem);
        }

        .header {
            margin-bottom: clamp(1.5rem, 2vw, 3.5rem);
            padding-bottom: clamp(0.5rem, 0.7vw, 1.25rem);
        }

        .no-print-btn {
            font-size: clamp(0.9rem, 0.55vw, 1.2rem);
            padding: 0.7em 1.15em;
        }
    }

    @media screen and (min-width: 1200px) {
        .table td,
        .table th {
            overflow-wrap: anywhere;
        }

        .table form,
        .table .d-flex {
            flex-wrap: wrap;
        }
    }

    @media screen and (max-width: 767.98px) {
        .navbar-brand {
            max-width: calc(100% - 64px);
            font-size: 1rem !important;
            white-space: normal;
        }

        .hero-title {
            font-size: clamp(2rem, 8vw, 2.8rem);
        }

        .hero-section {
            padding: 80px 16px 72px;
            border-bottom-left-radius: 24px;
            border-bottom-right-radius: 24px;
        }

        .auth-card {
            max-width: 100%;
        }

        .auth-sidebar,
        .auth-form-wrapper {
            padding: 1.5rem;
        }

        .table-responsive {
            overflow-x: auto;
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
            Community Library Management System
        </a>
        <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto gap-2 align-items-center">
                <li class="nav-item"><a class="nav-link" href="homepage.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="catalog.php">Catalog</a></li>
                <li class="nav-item"><a class="nav-link active" href="admin_dashboard.php">Admin Panel</a></li>
                <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                    <a class="btn fw-semibold px-4 rounded-pill" 
                    style="background-color: transparent; border: 1.5px solid white; color: white; transition: all 0.2s;" 
                    onmouseover="this.style.backgroundColor='var(--accent-green)'; this.style.borderColor='var(--accent-green)'; this.style.color='var(--primary-green)';" 
                    onmouseout="this.style.backgroundColor='transparent'; this.style.borderColor='white'; this.style.color='white';" 
                    href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container mt-5 mb-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <h2 class="fw-bold mb-0" style="color: var(--primary-green);">Active Book Loans</h2>
        <div class="d-flex gap-2">
            <a href="manage_members.php" class="btn btn-outline-success">
                <i class="bi bi-people"></i> Manage Members
            </a>
            <a href="add_book.php" class="btn btn-outline-success">
                <i class="bi bi-plus-lg"></i> Add New Book
            </a>
            <a href="admin_report.php" class="btn btn-outline-success">
                <i class="bi bi-printer"></i> Print Report
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Summary -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card card-custom p-3">
                <div class="text-muted small">Books currently out</div>
                <div class="stat-number"><?= $total_loans; ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-custom p-3">
                <div class="text-muted small">Overdue books</div>
                <div class="stat-number <?= $overdue_count > 0 ? 'text-danger' : 'text-success'; ?>"><?= $overdue_count; ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-custom p-3">
                <div class="text-muted small">Fines accruing (₱<?= number_format(FINE_PER_DAY, 2); ?>/day per book)</div>
                <div class="stat-number <?= $accruing_fines > 0 ? 'text-danger' : 'text-success'; ?>">₱<?= number_format($accruing_fines, 2); ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-custom p-3">
                <div class="text-muted small">Active reservations</div>
                <div class="stat-number"><?= $total_reservations; ?></div>
            </div>
        </div>
    </div>

    <div class="card card-custom p-4">
        <?php if (count($active_loans) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Book Title</th>
                            <th>Borrower</th>
                            <th>Borrowed Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Fine</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_loans as $loan): ?>
                            <tr class="<?= $loan['is_late'] ? 'table-danger' : ''; ?>">
                                <td class="fw-bold"><?= htmlspecialchars($loan['title']); ?></td>
                                <td><?= htmlspecialchars($loan['firstname'] . ' ' . $loan['lastname']); ?></td>
                                <td><?= date("M d, Y", strtotime($loan['borrowed_at'])); ?></td>
                                <td class="<?= $loan['is_late'] ? 'text-danger fw-bold' : ''; ?>"><?= date("M d, Y", strtotime($loan['due_at'])); ?></td>
                                <td>
                                    <?php if ($loan['is_late']): ?>
                                        <span class="badge bg-danger">Overdue</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td class="<?= $loan['current_fine'] > 0 ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                    ₱<?= number_format($loan['current_fine'], 2); ?>
                                </td>
                                <td>
                                    <form action="return_process.php" method="POST" class="m-0">
                                        <!-- csrf -->
                                        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf']; ?>">  
                                        <input type="hidden" name="transaction_id" value="<?= (int) $loan['transaction_id']; ?>">
                                        <button type="submit" class="btn btn-success btn-sm">Mark Returned</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info border-0 text-muted">No active book loans at the moment.</div>
        <?php endif; ?>
    </div>

    <!-- Book Reservations -->
    <h4 class="mt-5 mb-3 fw-bold" style="color: var(--primary-green);">Book Reservations</h4>
    <div class="card card-custom p-4">
        <?php if (count($res_rows) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Book Title</th>
                            <th>Copies Available</th>
                            <th>People Waiting</th>
                            <th>Waiting Line</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($res_rows as $row): ?>
                            <?php $names = explode('||', $row['queue']); ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($row['title']); ?></td>
                                <td class="<?= $row['available_copies'] > 0 ? 'text-success fw-bold' : 'text-muted'; ?>">
                                    <?= (int) $row['available_copies']; ?>
                                </td>
                                <td><span class="badge bg-warning text-dark fs-6"><?= (int) $row['waiting']; ?></span></td>
                                <td>
                                    <ol class="mb-0 ps-3" style="min-width: 280px;">
                                        <?php foreach ($names as $entry): ?>
                                            <?php [$reservation_id, $name] = explode('::', $entry, 2); ?>
                                            <li>
                                                <div class="d-flex justify-content-between align-items-center gap-3 py-1">
                                                    <span><?= htmlspecialchars($name); ?></span>
                                                    <form action="reserve_process.php" method="POST" class="m-0"
                                                        onsubmit="return confirm(<?= htmlspecialchars(json_encode('Cancel the reservation for ' . $name . '?'), ENT_QUOTES); ?>);">
                                                        <!-- csrf -->
                                                        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf']; ?>">  
                                                        <input type="hidden" name="action" value="cancel">
                                                        <input type="hidden" name="from" value="admin_dashboard">
                                                        <input type="hidden" name="reservation_id" value="<?= (int) $reservation_id; ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm">Cancel</button>
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
            <div class="alert alert-info border-0 text-muted mb-0">No active reservations at the moment.</div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>