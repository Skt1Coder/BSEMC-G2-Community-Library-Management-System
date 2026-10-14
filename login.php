<?php
session_start();
if (isset($_SESSION['member_id'])) {
    $redirect = ($_SESSION['role_id'] == 1) ? 'admin_dashboard.php' : 'member_dashboard.php';
    header("Location: $redirect");
    exit();
}
require 'includes/db_connect.php';

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM members WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {

        $_SESSION['member_id'] = $user['member_id'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['firstname'] = $user['firstname'];

        if ($user['role_id'] == 1) {
            header("Location: admin_dashboard.php");
        } else {
            header("Location: member_dashboard.php");
        }
        exit(); 
    } else {
        $message = "<div class='alert alert-danger'>Invalid email or password.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Community Library</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            /* Matching our exact theme */
            --primary-green: #14532d;
            --primary-hover: #166534;
            --accent-green: #dcfce7;
            --light-bg: #fafaf9;
            --text-main: #292524;
            --text-muted: #78716c;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }

        h2, h3 {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
        }

        .auth-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(20, 83, 45, 0.08);
            overflow: hidden;
            max-width: 900px; /* Slightly narrower than register page since there are fewer fields */
            width: 100%;
            border: 1px solid rgba(20, 83, 45, 0.05);
        }

        .auth-sidebar {
            background: linear-gradient(135deg, var(--primary-green) 0%, #166534 100%);
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }

        /* Abstract shapes */
        .auth-sidebar::before {
            content: '';
            position: absolute;
            bottom: -20%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(220, 252, 231, 0.1) 0%, rgba(255,255,255,0) 70%);
            border-radius: 50%;
        }

        .auth-form-wrapper {
            padding: 3.5rem;
        }

        .form-control {
            padding: 0.8rem 1rem;
            border-radius: 12px;
            border: 1px solid #e7e5e4;
            background-color: #fafaf9;
            transition: all 0.2s;
        }

        .form-control:focus {
            background-color: white;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 4px rgba(20, 83, 45, 0.1);
        }

        .form-label {
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--text-main);
            margin-bottom: 0.4rem;
        }

        .btn-primary-custom {
            background-color: var(--primary-green);
            color: white;
            font-weight: 600;
            padding: 0.85rem;
            border-radius: 12px;
            border: none;
            width: 100%;
            transition: all 0.3s;
            margin-top: 1rem;
        }

        .btn-primary-custom:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(20, 83, 45, 0.15);
        }

        .back-link {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--primary-green);
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="auth-card d-flex flex-column flex-lg-row">
                    
                    <!-- Left Sidebar (Branding) -->
                    <div class="col-lg-5 auth-sidebar d-none d-lg-flex">
                        <div style="position: relative; z-index: 10;">
                            <div class="d-inline-flex align-items-center justify-content-center bg-white text-success rounded-3 mb-4" style="width: 50px; height: 50px; font-size: 1.5rem;">
                                <i class="bi bi-person-badge" style="color: var(--primary-green);"></i>
                            </div>
                            <h2 class="display-6 mb-3">Welcome Back.</h2>
                            <p class="text-white-50" style="font-size: 1.1rem; line-height: 1.6;">
                                Log in to access your library dashboard, check due dates, and explore the catalog.
                            </p>
                        </div>
                    </div>

                    <!-- Right Side (Form) -->
                    <div class="col-lg-7 auth-form-wrapper">
                        <a href="homepage.php" class="back-link mb-4">
                            <i class="bi bi-arrow-left"></i> Back to Home
                        </a>
                        
                        <h3 class="mb-1">Log In</h3>
                        <p class="text-muted mb-4">Please enter your credentials to continue.</p>

                        <?= $message; ?>
                        <!-- Login Form -->
                        <form action="login.php" method="POST">
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="juan@example.com" required>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                            </div>

                            <button type="submit" name="login_btn" class="btn btn-primary-custom">
                                Login
                            </button>

                            <div class="text-center mt-4">
                                <span class="text-muted">Don't have an account?</span> 
                                <a href="register.php" class="text-decoration-none fw-semibold" style="color: var(--primary-green);">Register here</a>
                            </div>

                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>