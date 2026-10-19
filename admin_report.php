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