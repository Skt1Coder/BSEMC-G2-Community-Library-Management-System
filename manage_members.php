<?php
session_start();
require 'includes/db_connect.php';

// Admins only
if (!isset($_SESSION['member_id']) || $_SESSION['role_id'] != 1) {
    header("Location: login.php");
    exit();
}

$roles = [1 => 'Admin', 2 => 'Resident'];

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Handle actions (update role / delete account)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        die('Invalid request.');
    }

    $target_id = (int) ($_POST['member_id'] ?? 0);
    $action    = $_POST['action'] ?? '';

    if ($target_id === (int) $_SESSION['member_id']) {
        $_SESSION['error'] = "You can't change or delete your own account here.";
    } elseif ($action === 'update_role') {
        $new_role = (int) ($_POST['role_id'] ?? 0);
        if (!isset($roles[$new_role])) {
            $_SESSION['error'] = "Invalid role selected.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE members SET role_id = :role_id WHERE member_id = :member_id");
                $stmt->execute(['role_id' => $new_role, 'member_id' => $target_id]);
                $_SESSION['success'] = "Role updated successfully.";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Couldn't update the role: " . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $check = $pdo->prepare("
            SELECT COUNT(*) FROM transaction t
            JOIN status s ON t.status_id = s.status_id
            WHERE t.member_id = :member_id AND s.status IN ('Borrowed', 'Overdue')
        ");
        $check->execute(['member_id' => $target_id]);

        if ($check->fetchColumn() > 0) {
            $_SESSION['error'] = "This member still has borrowed books. Have them returned first.";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM members WHERE member_id = :member_id");
                $stmt->execute(['member_id' => $target_id]);
                $_SESSION['success'] = "Account deleted successfully.";
            } catch (PDOException $e) {
                if ($e->getCode() == '23000') {
                    $_SESSION['error'] = "Can't delete this member because they have past borrowing records.";
                } else {
                    $_SESSION['error'] = "Couldn't delete the account: " . $e->getMessage();
                }
            }
        }
    }

    header("Location: manage_members.php" . (!empty($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''));
    exit();
}

// Search + list members
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "
    SELECT m.member_id, m.firstname, m.lastname, m.email, m.role_id,
           (SELECT COUNT(*) FROM transaction t
            JOIN status s ON t.status_id = s.status_id
            WHERE t.member_id = m.member_id AND s.status IN ('Borrowed', 'Overdue')) AS active_loans
    FROM members m
";
$params = [];
if ($search_query) {
    $sql .= " WHERE CONCAT(m.firstname, ' ', m.lastname) LIKE :search_name OR m.email LIKE :search_email";
    $params['search_name']  = "%$search_query%";
    $params['search_email'] = "%$search_query%";
}
$sql .= " ORDER BY m.firstname ASC, m.lastname ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Members - Community Library</title>

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

        /* --- Navbar --- */
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

        /* --- Admin Header Banner --- */
        .admin-header {
            background: radial-gradient(circle at 50% 20%, #166534 0%, var(--primary-green) 70%, #062319 100%);
            padding: 50px 0 75px;
            color: white;
            border-bottom-left-radius: 32px;
            border-bottom-right-radius: 32px;
            box-shadow: 0 10px 30px rgba(15, 57, 43, 0.15);
            margin-bottom: -45px;
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

        /* --- Section Cards & Forms --- */
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

        /* Form Control Custom Styling */
        .form-control, .form-select {
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 0.6rem 1rem;
            font-size: 0.92rem;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent-emerald);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
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
            padding: 16px 20px;
            font-size: 0.92rem;
            color: var(--text-main);
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
        }

        /* Badges */
        .role-badge {
            font-size: 0.78rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .role-badge.admin {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .role-badge.resident {
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
            padding: 8px 20px;
            transition: all 0.2s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
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
            font-size: 0.82rem;
            transition: all 0.2s ease;
            background: transparent;
        }

        .btn-theme-outline-danger:hover:not(:disabled) {
            background-color: #fef2f2;
            border-color: #dc2626;
            color: #dc2626;
        }

        .btn-theme-outline-success {
            border: 1.5px solid #86efac;
            color: #16a34a;
            font-weight: 600;
            border-radius: 50px;
            padding: 4px 14px;
            font-size: 0.82rem;
            transition: all 0.2s ease;
            background: transparent;
        }

        .btn-theme-outline-success:hover:not(:disabled) {
            background-color: #f0fdf4;
            border-color: #16a34a;
            color: #16a34a;
        }

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

        /* --- Responsive / 4K Scaling Media Queries --- */
        @media screen and (min-width: 1800px) {
            html { font-size: clamp(16px, 0.85vw, 30px); }
            body { font-size: 1rem; line-height: 1.55; }
            .container { width: min(94vw, 3200px) !important; max-width: 3200px !important; }
            .navbar { padding-top: clamp(0.65rem, 0.75vw, 1.5rem) !important; padding-bottom: clamp(0.65rem, 0.75vw, 1.5rem) !important; }
            .navbar-brand { font-size: clamp(1.1rem, 0.8vw, 1.75rem) !important; gap: clamp(0.5rem, 0.65vw, 1.25rem); }
            .nav-link { font-size: clamp(0.9rem, 0.55vw, 1.2rem) !important; padding: 0.55em 0.9em !important; }
            .logo-box { width: clamp(40px, 1.8vw, 68px); height: clamp(40px, 1.8vw, 68px); font-size: clamp(1.1rem, 0.75vw, 1.8rem); }
            h2 { font-size: clamp(1.65rem, 1.45vw, 3.1rem); line-height: 1.2; }
            h3 { font-size: clamp(1.35rem, 1.1vw, 2.4rem); }
            .form-control, .form-select { font-size: clamp(0.9rem, 0.55vw, 1.2rem); min-height: clamp(2.5rem, 2.2vw, 3.8rem); padding: 0.65em 0.85em; }
            .btn { font-size: clamp(0.85rem, 0.52vw, 1.2rem); }
            .card-custom { border-radius: clamp(12px, 0.75vw, 24px); }
            .table { font-size: clamp(0.9rem, 0.55vw, 1.2rem); }
            .table > :not(caption) > * > * { padding: clamp(0.55rem, 0.55vw, 1.2rem); }
        }

        @media screen and (max-width: 767.98px) {
            .admin-header { padding: 40px 0 65px; border-radius: 0 0 24px 24px; }
            .table-responsive { overflow-x: auto; }
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
            <span>Community Library</span>
        </a>

        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Nav Links -->
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
                            <a class="nav-link active fw-bold text-success" href="admin_dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Admin Panel</a>
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
        <h2><i class="bi bi-people me-2"></i>Member Management</h2>
        <p>Manage registered accounts, update user access roles, and monitor active borrowings.</p>
    </div>
</header>

<!-- Main Container -->
<div class="container mb-5" style="position: relative; z-index: 30;">

    <!-- Search Card -->
    <div class="card card-custom mb-4" style="box-shadow: var(--shadow-lg);">
        <div class="card-body p-4">
            <form action="manage_members.php" method="GET" class="row g-3 align-items-center">
                <div class="col-md-8 col-lg-9">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search member by full name or email address..." value="<?= htmlspecialchars($search_query); ?>">
                    </div>
                </div>
                <div class="col-md-4 col-lg-3 d-flex gap-2">
                    <button type="submit" class="btn btn-theme-success w-100">
                        <i class="bi bi-search me-1"></i> Search
                    </button>
                    <?php if ($search_query): ?>
                        <a href="manage_members.php" class="btn btn-outline-secondary rounded-pill px-3 d-inline-flex align-items-center">
                            <i class="bi bi-x-circle me-1"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
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

    <!-- Members Directory Table Card -->
    <div class="card card-custom">
        <div class="card-header-custom">
            <h5 class="card-header-title">
                <i class="bi bi-person-lines-fill"></i> Member Directory
            </h5>
            <span class="badge bg-light text-dark border rounded-pill px-3 py-2 fw-semibold">
                <?= count($members); ?> Member(s) Found
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Full Name</th>
                            <th>Email Address</th>
                            <th>Current Role & Update</th>
                            <th>Active Loans</th>
                            <th class="text-end pe-4">Account Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($members) > 0): ?>
                            <?php foreach ($members as $m): ?>
                                <?php $is_self = ((int) $m['member_id'] === (int) $_SESSION['member_id']); ?>
                                <tr>
                                    <td class="ps-4 fw-semibold text-dark">
                                        <?= htmlspecialchars($m['firstname'] . ' ' . $m['lastname']); ?>
                                        <?php if ($is_self): ?>
                                            <span class="badge bg-secondary ms-1 rounded-pill small">You</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-secondary">
                                        <i class="bi bi-envelope text-muted me-1"></i><?= htmlspecialchars($m['email']); ?>
                                    </td>
                                    <td>
                                        <form action="manage_members.php<?= $search_query ? '?search=' . urlencode($search_query) : ''; ?>" method="POST" class="d-flex align-items-center gap-2 m-0">
                                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf']; ?>">
                                            <input type="hidden" name="action" value="update_role">
                                            <input type="hidden" name="member_id" value="<?= (int) $m['member_id']; ?>">
                                            
                                            <select name="role_id" class="form-select form-select-sm" style="width: 130px;" <?= $is_self ? 'disabled' : ''; ?>>
                                                <?php foreach ($roles as $id => $label): ?>
                                                    <option value="<?= $id; ?>" <?= $m['role_id'] == $id ? 'selected' : ''; ?>><?= $label; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            
                                            <button type="submit" class="btn btn-theme-outline-success" <?= $is_self ? 'disabled' : ''; ?>>
                                                Save
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <?php if ($m['active_loans'] > 0): ?>
                                            <span class="badge bg-warning text-dark font-monospace py-1 px-3 rounded-pill">
                                                <?= (int) $m['active_loans']; ?> borrowed
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">0 active loans</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <form action="manage_members.php<?= $search_query ? '?search=' . urlencode($search_query) : ''; ?>" method="POST" class="m-0"
                                              onsubmit="return confirm('Delete this account? This cannot be undone.');">
                                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="member_id" value="<?= (int) $m['member_id']; ?>">
                                            
                                            <button type="submit" class="btn btn-theme-outline-danger" <?= $is_self ? 'disabled' : ''; ?>>
                                                <i class="bi bi-trash me-1"></i> Delete Account
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="bi bi-person-x fs-1 opacity-50 d-block mb-2"></i>
                                    No members found matching your search.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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