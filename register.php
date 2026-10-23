<?php
session_start();
if (isset($_SESSION['member_id'])) {
    $redirect = ($_SESSION['role_id'] == 1) ? 'admin_dashboard.php' : 'member_dashboard.php';
    header("Location: $redirect");
    exit();
}
require 'includes/db_connect.php';

$message = ''; // This will hold our success or error text

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Grab the data from the form
    $username = $_POST['username'];
    $email = $_POST['email'];
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT member_id FROM members WHERE email = :email OR username = :username");
    $stmt->execute(['email' => $email, 'username' => $username]);
    
    if ($stmt->rowCount() > 0) {
        $message = "<div class='alert alert-danger custom-alert alert-dismissible fade show mb-4' role='alert'><i class='bi bi-exclamation-triangle-fill me-2'></i>Error: That username or email is already registered.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $role_id = 2; 

        $insert_stmt = $pdo->prepare("INSERT INTO members (role_id, username, email, firstname, lastname, password) VALUES (:role_id, :username, :email, :firstname, :lastname, :password)");
        
        $success = $insert_stmt->execute([
            'role_id' => $role_id,
            'username' => $username,
            'email' => $email,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'password' => $hashed_password
        ]);

        if ($success) {
            $message = "<div class='alert alert-success custom-alert alert-dismissible fade show mb-4' role='alert'><i class='bi bi-check-circle-fill me-2'></i>Registration successful! You can now <a href='login.php' class='alert-link fw-semibold'>log in here</a>.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            $message = "<div class='alert alert-danger custom-alert alert-dismissible fade show mb-4' role='alert'><i class='bi bi-exclamation-triangle-fill me-2'></i>A database error occurred. Please try again.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Community Library</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">

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
            overflow-x: hidden;
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

        /* --- Main Content Layout --- */
        .auth-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 50px 20px;
            background: radial-gradient(circle at 50% 0%, rgba(16, 185, 129, 0.08) 0%, rgba(248, 250, 252, 0) 70%);
        }

        .auth-card {
            background: var(--card-bg);
            border-radius: 28px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            max-width: 1050px;
            width: 100%;
            border: 1px solid var(--border-color);
        }

        /* Split Left Sidebar */
        .auth-sidebar {
            background: radial-gradient(circle at 20% 20%, #166534 0%, var(--primary-green) 70%, #062319 100%);
            color: white;
            padding: 3.5rem 3rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .auth-sidebar::before {
            content: '';
            position: absolute;
            top: -15%;
            left: -15%;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.25) 0%, rgba(0,0,0,0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .sidebar-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--accent-green);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 600;
            width: fit-content;
        }

        .sidebar-feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 10px 16px;
            border-radius: 14px;
            font-size: 0.88rem;
            color: #f1f5f9;
        }

        /* Form Wrapper */
        .auth-form-wrapper {
            padding: 3.5rem 3rem;
        }

        /* Input Controls */
        .input-group-custom {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.05rem;
            z-index: 10;
        }

        .form-control-custom {
            padding: 12px 16px 12px 46px;
            border-radius: 14px;
            border: 1.5px solid var(--border-color);
            background-color: #f8fafc;
            font-size: 0.95rem;
            transition: all 0.25s ease;
            color: var(--text-main);
        }

        .form-control-custom:focus {
            background-color: #ffffff;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 4px rgba(21, 128, 61, 0.12);
            outline: none;
        }

        .form-label-custom {
            font-weight: 600;
            font-size: 0.88rem;
            color: var(--text-main);
            margin-bottom: 0.4rem;
        }

        /* Primary Button */
        .btn-submit-custom {
            background-color: var(--primary-green);
            color: white;
            font-weight: 700;
            padding: 13px;
            border-radius: 50px;
            border: none;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(15, 57, 43, 0.2);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.98rem;
        }

        .btn-submit-custom:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(15, 57, 43, 0.3);
        }

        .back-link {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--primary-light);
        }

        .custom-alert {
            border-radius: 14px;
            border: none;
            font-size: 0.92rem;
            padding: 14px 18px;
        }

        /* --- Footer --- */
        .footer {
            background-color: var(--dark-bg);
            padding: 50px 0 25px;
            border-top: 4px solid var(--accent-emerald);
            color: #9ca3af;
        }

        .footer-logo {
            font-family: 'Outfit', sans-serif;
            color: white;
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .footer-links a {
            color: #d1d5db;
            text-decoration: none;
            margin: 0 12px;
            font-size: 0.9rem;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: var(--accent-emerald);
        }

        @media (max-width: 991px) {
            .auth-form-wrapper { padding: 2.5rem 2rem; }
        }
    </style>
</head>
<body>

    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand" href="homepage.php">
                <div class="logo-box"><i class="bi bi-book-half"></i></div>
                <span>Community Library</span>
            </a>
            
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto gap-1 mt-3 mt-lg-0 align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link" href="homepage.php"><i class="bi bi-house-door me-1"></i>Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="catalog.php"><i class="bi bi-journal-bookmark me-1"></i>Catalog</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="register.php"><i class="bi bi-person-plus me-1"></i>Membership</a>
                    </li>
                    <li class="nav-item ms-lg-1">
                        <a class="btn btn-sm px-4 rounded-pill text-white fw-semibold" style="background-color: var(--primary-green);" href="login.php">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Login
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Registration Container -->
    <div class="auth-wrapper">
        <div class="auth-card d-flex flex-column flex-lg-row">
            
            <!-- Left Decorative Sidebar -->
            <div class="col-lg-5 auth-sidebar d-none d-lg-flex">
                <div style="position: relative; z-index: 10;">
                    <div class="sidebar-badge mb-4">
                        <i class="bi bi-stars"></i> Member Portal
                    </div>
                    <h2 class="display-6 fw-bold mb-3">Join Our Community Today.</h2>
                    <p style="color: #d1d5db; font-size: 0.98rem; line-height: 1.6;">
                        Register a free member account to start borrowing books, tracking loan due dates, and managing reservation queues online.
                    </p>
                </div>

                <div class="d-flex flex-column gap-2 mt-4" style="position: relative; z-index: 10;">
                    <div class="sidebar-feature-item">
                        <i class="bi bi-shield-check text-warning fs-5"></i>
                        <span>100% Free Library Membership</span>
                    </div>
                    <div class="sidebar-feature-item">
                        <i class="bi bi-clock-history text-info fs-5"></i>
                        <span>7-Day Regular Borrowing Period</span>
                    </div>
                    <div class="sidebar-feature-item">
                        <i class="bi bi-bookmark-check text-success fs-5"></i>
                        <span>Reserve Books in Real-Time</span>
                    </div>
                </div>
            </div>

            <!-- Right Form Section -->
            <div class="col-lg-7 auth-form-wrapper">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <a href="homepage.php" class="back-link">
                        <i class="bi bi-arrow-left"></i> Back to Homepage
                    </a>
                    <span class="badge bg-light text-muted border rounded-pill px-3 py-1 small fw-normal">Step 1 of 1</span>
                </div>
                
                <h3 class="fw-bold mb-1" style="color: var(--primary-green);">Create an Account</h3>
                <p class="text-muted small mb-4">Fill in your details below to activate your member access.</p>

                <!-- Feedback Message -->
                <?= $message; ?>

                <!-- Registration Form -->
                <form action="register.php" method="POST">
                    
                    <!-- Firstname & Lastname Row -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="firstname" class="form-label-custom">First Name</label>
                            <div class="input-group-custom">
                                <i class="bi bi-person input-icon"></i>
                                <input type="text" class="form-control form-control-custom" id="firstname" name="firstname" placeholder="Juan" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="lastname" class="form-label-custom">Last Name</label>
                            <div class="input-group-custom">
                                <i class="bi bi-person input-icon"></i>
                                <input type="text" class="form-control form-control-custom" id="lastname" name="lastname" placeholder="Dela Cruz" required>
                            </div>
                        </div>
                    </div>

                    <!-- Email Address -->
                    <div class="mb-3">
                        <label for="email" class="form-label-custom">Email Address</label>
                        <div class="input-group-custom">
                            <i class="bi bi-envelope input-icon"></i>
                            <input type="email" class="form-control form-control-custom" id="email" name="email" placeholder="juan@example.com" required>
                        </div>
                    </div>

                    <!-- Username -->
                    <div class="mb-3">
                        <label for="username" class="form-label-custom">Username</label>
                        <div class="input-group-custom">
                            <i class="bi bi-at input-icon"></i>
                            <input type="text" class="form-control form-control-custom" id="username" name="username" placeholder="Choose a unique username" required>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="mb-4">
                        <label for="password" class="form-label-custom">Password</label>
                        <div class="input-group-custom">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" class="form-control form-control-custom" id="password" name="password" placeholder="Create a strong password" minlength="8" required>
                        </div>
                        <div class="form-text text-muted mt-2 small">
                            <i class="bi bi-info-circle me-1"></i>Must be at least 8 characters long.
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" name="register_btn" class="btn-submit-custom">
                        <i class="bi bi-person-check-fill"></i> Complete Registration
                    </button>

                    <!-- Login Link -->
                    <div class="text-center mt-4">
                        <span class="text-muted small">Already have a member account?</span> 
                        <a href="login.php" class="text-decoration-none fw-semibold ms-1" style="color: var(--primary-light);">Log in here</a>
                    </div>

                </form>
            </div>

        </div>
    </div>

    <!-- Footer -->
    <footer class="footer text-center">
        <div class="container">
            <div class="footer-logo">
                <i class="bi bi-book-half me-2" style="color: var(--accent-emerald);"></i>Community Library System
            </div>
            <div class="footer-links my-3 d-flex flex-wrap justify-content-center gap-2">
                <a href="homepage.php">Home</a>
                <span class="text-secondary">•</span>
                <a href="catalog.php">Catalog</a>
                <span class="text-secondary">•</span>
                <a href="login.php">Login</a>
            </div>
            <p class="text-secondary small mb-0 mt-3" style="opacity: 0.75;">
                &copy; <?= date('Y'); ?> Community Library Management System.<br>Designed for Student Development.
            </p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>