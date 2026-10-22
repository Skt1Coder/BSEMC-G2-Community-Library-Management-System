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
                $_SESSION['success'] = "Role updated.";
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
                $_SESSION['success'] = "Account deleted.";
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
        .text-theme { color: var(--primary-green); }

        .navbar { background-color: var(--primary-green) !important; border-bottom: 1px solid rgba(0,0,0,0.1); padding: 1rem 0; }
        .navbar-brand { display: flex; align-items: center; gap: 12px; font-weight: 800; color: white !important; font-size: 1.25rem; letter-spacing: -0.5px; }
        .logo-box { width: 40px; height: 40px; background: white; color: var(--primary-green); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 4px 10px rgba(0,0,0,0.15); flex-shrink: 0; }
        .nav-link { color: rgba(255,255,255,0.85) !important; font-weight: 500; font-size: 0.95rem; padding: 0.5rem 1rem !important; transition: all 0.3s ease; border-radius: 8px; }
        .nav-link:hover, .nav-link.active { color: white !important; background-color: rgba(255,255,255,0.15); }

        .table-card { background: white; border: 1px solid #e7e5e4; border-radius: 14px; overflow: hidden; }
        .table thead th { background-color: var(--accent-green); color: var(--primary-green); font-weight: 600; border-bottom: none; }
        
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

        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto gap-2 mt-3 mt-lg-0">
                <li class="nav-item"><a class="nav-link" href="homepage.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="catalog.php">Catalog</a></li>
                <li class="nav-item"><a class="nav-link fw-bold" style="color: #dcfce7;" href="admin_dashboard.php">Admin Panel</a></li>
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
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="text-theme fw-bold mb-0">Member Management</h2>
            <p class="text-muted small mb-0"><?= count($members); ?> member(s) found</p>
        </div>
        <div class="col-md-6 mt-3 mt-md-0">
            <form action="manage_members.php" method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="Search by name or email..." value="<?= htmlspecialchars($search_query); ?>">
                <button type="submit" class="btn btn-success" style="background-color: #2b5329;">Search</button>
                <?php if ($search_query): ?>
                    <a href="manage_members.php" class="btn btn-secondary ms-2">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- Members Table -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Active Loans</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($members) > 0): ?>
                        <?php foreach ($members as $m): ?>
                            <?php $is_self = ((int) $m['member_id'] === (int) $_SESSION['member_id']); ?>
                            <tr>
                                <td class="ps-4 fw-semibold">
                                    <?= htmlspecialchars($m['firstname'] . ' ' . $m['lastname']); ?>
                                    <?php if ($is_self): ?><span class="badge bg-secondary ms-1">You</span><?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($m['email']); ?></td>
                                <td>
                                    <form action="manage_members.php<?= $search_query ? '?search=' . urlencode($search_query) : ''; ?>" method="POST" class="d-flex gap-2 m-0">
                                        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf']; ?>">
                                        <input type="hidden" name="action" value="update_role">
                                        <input type="hidden" name="member_id" value="<?= (int) $m['member_id']; ?>">
                                        <select name="role_id" class="form-select form-select-sm" style="width: 130px;" <?= $is_self ? 'disabled' : ''; ?>>
                                            <?php foreach ($roles as $id => $label): ?>
                                                <option value="<?= $id; ?>" <?= $m['role_id'] == $id ? 'selected' : ''; ?>><?= $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-outline-success btn-sm" <?= $is_self ? 'disabled' : ''; ?>>Save</button>
                                    </form>
                                </td>
                                <td><?= (int) $m['active_loans']; ?></td>
                                <td class="text-end pe-4">
                                    <form action="manage_members.php<?= $search_query ? '?search=' . urlencode($search_query) : ''; ?>" method="POST" class="m-0"
                                          onsubmit="return confirm('Delete this account? This cannot be undone.');">
                                        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="member_id" value="<?= (int) $m['member_id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm" <?= $is_self ? 'disabled' : ''; ?>>
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No members found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>