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