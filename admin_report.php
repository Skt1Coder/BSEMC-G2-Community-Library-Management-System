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

// Fetch Loans
$stmt = $pdo->prepare("
    SELECT b.title, m.firstname, m.lastname, t.due_at, s.status,
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

// Fetch Reservations
$res_stmt = $pdo->query("
    SELECT b.title, COUNT(r.reservation_id) as waiting
    FROM reservations r
    JOIN books b ON r.book_id = b.book_id
    WHERE r.status = 'Active'
    GROUP BY b.book_id
    ORDER BY waiting DESC
");
$reservations = $res_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Library System Report - <?= date('Y-m-d'); ?></title>
    <style>
        body { font-family: Arial, sans-serif; color: #000; line-height: 1.5; margin: 40px; }
        .header { text-align: center; margin-bottom: 30px; padding-bottom: 10px; border-bottom: 2px solid #000; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th, td { padding: 8px 12px; border-bottom: 1px solid #ccc; text-align: left; }
        th { background-color: #f4f4f4; font-weight: bold; }
        .text-right { text-align: right; }
        .no-print-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #14532d; color: white; text-decoration: none; border-radius: 5px; }
        
        /* Print-specific styling */
        @media print {
            body { margin: 0; }
            .no-print { display: none !important; }
            @page { margin: 1cm; }
        }
        
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

<div class="no-print" style="text-align: right;">
    <button onclick="window.print()" class="no-print-btn">🖨️ Print Report</button>
    <a href="admin_dashboard.php" class="no-print-btn" style="background: #666;">Back to Dashboard</a>
</div>

<div class="header">
    <h1>Community Library Management System</h1>
    <p>Official Status Report | Generated on: <?= date("F j, Y, g:i a"); ?></p>
</div>

<h2>Active & Overdue Loans</h2>
<table>
    <thead>
        <tr>
            <th>Book Title</th>
            <th>Borrower</th>
            <th>Due Date</th>
            <th>Status</th>
            <th class="text-right">Accruing Fine</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($loans as $loan): ?>
            <tr>
                <td><?= htmlspecialchars($loan['title']); ?></td>
                <td><?= htmlspecialchars($loan['firstname'] . ' ' . $loan['lastname']); ?></td>
                <td><?= date("M d, Y", strtotime($loan['due_at'])); ?></td>
                <td><?= $loan['status']; ?></td>
                <td class="text-right">₱<?= number_format($loan['fine'], 2); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h2>High-Demand Books (Active Reservations)</h2>
<table>
    <thead>
        <tr>
            <th>Book Title</th>
            <th class="text-right">Total Members Waiting</th>
        </tr>
    </thead>
    <tbody>
        <?php if(count($reservations) > 0): ?>
            <?php foreach($reservations as $res): ?>
                <tr>
                    <td><?= htmlspecialchars($res['title']); ?></td>
                    <td class="text-right"><?= $res['waiting']; ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="2" style="text-align:center;">No active reservations.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<div style="margin-top: 50px; text-align: center; font-size: 0.9em; color: #555;">
    <p>Report End</p>
</div>

</body>
</html>