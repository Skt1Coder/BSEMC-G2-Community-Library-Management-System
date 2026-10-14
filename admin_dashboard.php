<?php
session_start();
require 'includes/db_connect.php';

// STRICT SECURITY: Kick out anyone who is NOT an Admin (role_id 1)
if (!isset($_SESSION['member_id']) || $_SESSION['role_id'] != 1) {
    header("Location: catalog.php");
    exit();
}

// Fetch all active transactions (borrowed books that haven't been returned)
$stmt = $pdo->query("
    SELECT t.transaction_id, b.title, m.firstname, m.lastname, t.borrowed_at, t.due_at 
    FROM transaction t 
    JOIN books b ON t.book_id = b.book_id 
    JOIN members m ON t.member_id = m.member_id 
    WHERE t.status_id = 1 
    ORDER BY t.borrowed_at DESC
");
$active_loans = $stmt->fetchAll();
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

        /* Solid Green Navbar */
        .navbar { background-color: var(--primary-green) !important; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 1rem 0; }
        .navbar-brand { display: flex; align-items: center; gap: 12px; font-weight: 800; color: white !important; font-size: 1.25rem; }
        .logo-box { width: 40px; height: 40px; background: white; color: var(--primary-green); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .nav-link { color: rgba(255,255,255,0.85) !important; font-weight: 500; padding: 0.5rem 1rem !important; border-radius: 8px; }
        .nav-link:hover, .nav-link.active { color: white !important; background-color: rgba(255,255,255,0.15); }

        .card-custom { border-radius: 12px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 10px 30px rgba(0,0,0,0.04); background: white; }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand" href="homepage.php">
            <div class="logo-box"><i class="bi bi-book-half"></i></div>
            CLMS Admin
        </a>
        <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto gap-2 align-items-center">
                <li class="nav-item"><a class="nav-link" href="homepage.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="catalog.php">Catalog</a></li>
                <li class="nav-item"><a class="nav-link active" href="admin_dashboard.php">Admin Panel</a></li>
                <li class="nav-item ms-lg-3"><a class="btn btn-danger text-white fw-semibold px-4 rounded-pill" href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold" style="color: var(--primary-green);">Active Book Loans</h2>
        <!-- Future connection for your colleague's add_book.php -->
        <a href="#" class="btn btn-outline-success">Add New Book</a> 
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
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_loans as $loan): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($loan['title']); ?></td>
                                <td><?= htmlspecialchars($loan['firstname'] . ' ' . $loan['lastname']); ?></td>
                                <td><?= date("M d, Y", strtotime($loan['borrowed_at'])); ?></td>
                                <td class="text-danger"><?= date("M d, Y", strtotime($loan['due_at'])); ?></td>
                                <td>
                                    <!-- Future connection for your colleague's return_process.php -->
                                    <form action="return_process.php" method="POST" class="m-0">
                                        <input type="hidden" name="transaction_id" value="<?= $loan['transaction_id']; ?>">
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>