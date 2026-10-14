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
        $message = "<div class='alert alert-danger'>Error: That username or email is already registered.</div>";
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
            $message = "<div class='alert alert-success'>Registration successful! You can now <a href='login.php'>log in</a>.</div>";
        } else {
            $message = "<div class='alert alert-danger'>A database error occurred. Please try again.</div>";
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
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
            max-width: 1000px;
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

        /* Abstract shapes matching the landing page */
        .auth-sidebar::before {
            content: '';
            position: absolute;
            top: -20%;
            left: -20%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(220, 252, 231, 0.1) 0%, rgba(255,255,255,0) 70%);
            border-radius: 50%;
        }

        .auth-form-wrapper {
            padding: 3rem;
        }

        .form-control {
            padding: 0.75rem 1rem;
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
            padding: 0.8rem;
            border-radius: 12px;
            border: none;
            width: 100%;
            transition: all 0.3s;
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
                                <i class="bi bi-book-half" style="color: var(--primary-green);"></i>
                            </div>
                            <h2 class="display-6 mb-3">Join the Community.</h2>
                            <p class="text-white-50" style="font-size: 1.1rem; line-height: 1.6;">
                                Create an account to borrow books, reserve digital resources, and track your reading history in one place.
                            </p>
                        </div>
                    </div>

                    <!-- Right Side (Form) -->
                    <div class="col-lg-7 auth-form-wrapper">
                        <a href="homepage.php" class="back-link mb-4">
                            <i class="bi bi-arrow-left"></i> Back to Home
                        </a>
                        
                        <h3 class="mb-1">Create an Account</h3>
                        <p class="text-muted mb-4">Fill in your details below to register.</p>

                        <?= $message; ?>
                        <!-- Registration Form -->
                        <!-- The 'required' attributes and 'type' attributes handle HTML5 validation -->
                        <form action="register.php" method="POST">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="firstname" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="firstname" name="firstname" placeholder="Juan" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="lastname" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="lastname" name="lastname" placeholder="Dela Cruz" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <!-- type="email" ensures the user includes an '@' symbol -->
                                <input type="email" class="form-control" id="email" name="email" placeholder="juan@example.com" required>
                            </div>

                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" placeholder="Choose a unique username" required>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <!-- minlength ensures they cannot create a 1-character password -->
                                <input type="password" class="form-control" id="password" name="password" placeholder="Create a strong password" minlength="8" required>
                                <div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i>Must be at least 8 characters long.</div>
                            </div>

                            <button type="submit" name="register_btn" class="btn btn-primary-custom">
                                Register Account
                            </button>

                            <div class="text-center mt-4">
                                <span class="text-muted">Already have an account?</span> 
                                <a href="login.php" class="text-decoration-none fw-semibold" style="color: var(--primary-green);">Log in here</a>
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