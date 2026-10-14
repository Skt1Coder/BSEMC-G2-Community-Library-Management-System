<?php
session_start();
require 'includes/db_connect.php';

// SECURITY: Kick out anyone who is not logged in OR is not a Resident (role_id 2)
if (!isset($_SESSION['member_id']) || $_SESSION['role_id'] != 2) {
    header("Location: login.php");
    exit();
}

// Fetch the member's borrowing history
$member_id = $_SESSION['member_id'];
$stmt = $pdo->prepare("
    SELECT b.title, t.borrowed_at, t.due_at, t.fine_amount, s.status 
    FROM transaction t 
    JOIN books b ON t.book_id = b.book_id 
    JOIN status s ON t.status_id = s.status_id 
    WHERE t.member_id = :member_id 
    ORDER BY t.borrowed_at DESC
");
$stmt->execute(['member_id' => $member_id]);
$transactions = $stmt->fetchAll();
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
<div class="container mt-5">
    <h2 class="mb-4 text-theme fw-bold">My Borrowed Books</h2>
    
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
                            <tr>
                                <td><?= htmlspecialchars($row['title']); ?></td>
                                <td><?= date("M d, Y", strtotime($row['borrowed_at'])); ?></td>
                                <td><?= date("M d, Y", strtotime($row['due_at'])); ?></td>
                                <td>
                                    <?php if ($row['status'] == 'Borrowed'): ?>
                                        <span class="badge bg-warning text-dark">Active</span>
                                    <?php elseif ($row['status'] == 'Overdue'): ?>
                                        <span class="badge bg-danger">Overdue</span>
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>