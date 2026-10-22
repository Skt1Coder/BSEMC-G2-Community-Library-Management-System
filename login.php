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

        /* Background Image */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: -2;
            background-image: url("data:image/svg+xml,%3Csvg%20xmlns=%22http://www.w3.org/2000/svg%22%20width=%22960%22%20height=%22260%22%20viewBox=%220%200%20960%20260%22%3E%3Crect%20x=%220%22%20y=%22107%22%20width=%2230%22%20height=%22139%22%20rx=%222%22%20fill=%22%23d6d3d1%22/%3E%3Crect%20x=%220%22%20y=%22129%22%20width=%2230%22%20height=%224%22%20fill=%22%23dcfce7%22%20opacity=%22.7%22/%3E%3Crect%20x=%229.0%22%20y=%22165%22%20width=%2212.0%22%20height=%2219%22%20rx=%221%22%20fill=%22%23dcfce7%22%20opacity=%22.45%22/%3E%3Crect%20x=%2230%22%20y=%22115%22%20width=%2222%22%20height=%22131%22%20rx=%222%22%20fill=%22%23d6d3d1%22/%3E%3Crect%20x=%2230%22%20y=%22130%22%20width=%2222%22%20height=%224%22%20fill=%22%23e7e5e4%22%20opacity=%22.7%22/%3E%3Crect%20x=%2252%22%20y=%22111%22%20width=%2240%22%20height=%22135%22%20rx=%222%22%20fill=%22%233f6212%22/%3E%3Crect%20x=%2252%22%20y=%22131%22%20width=%2240%22%20height=%224%22%20fill=%22%23dcfce7%22%20opacity=%22.7%22/%3E%3Crect%20x=%2264.0%22%20y=%22158%22%20width=%2216.0%22%20height=%2219%22%20rx=%221%22%20fill=%22%23dcfce7%22%20opacity=%22.45%22/%3E%3Crect%20x=%2292%22%20y=%2273%22%20width=%2230%22%20height=%22173%22%20rx=%222%22%20fill=%22%2315803d%22/%3E%3Crect%20x=%2292%22%20y=%2295%22%20width=%2230%22%20height=%224%22%20fill=%22%23dcfce7%22%20opacity=%22.7%22/%3E%3Crect%20x=%22122%22%20y=%2252%22%20width=%2222%22%20height=%22194%22%20rx=%222%22%20fill=%22%2386efac%22/%3E%3Crect%20x=%22122%22%20y=%2274%22%20width=%2222%22%20height=%224%22%20fill=%22%23fafaf9%22%20opacity=%22.7%22/%3E%3Crect%20x=%22144%22%20y=%22100%22%20width=%2240%22%20height=%22146%22%20rx=%222%22%20fill=%22%2378716c%22/%3E%3Crect%20x=%22156.0%22%20y=%22169%22%20width=%2216.0%22%20height=%2232%22%20rx=%221%22%20fill=%22%23e7e5e4%22%20opacity=%22.45%22/%3E%3Crect%20x=%22186%22%20y=%2295%22%20width=%2230%22%20height=%22151%22%20rx=%222%22%20fill=%22%2315803d%22/%3E%3Crect%20x=%22186%22%20y=%22113%22%20width=%2230%22%20height=%224%22%20fill=%22%23fafaf9%22%20opacity=%22.7%22/%3E%3Crect%20x=%22218%22%20y=%2269%22%20width=%2246%22%20height=%22177%22%20rx=%222%22%20fill=%22%234d7c0f%22/%3E%3Crect%20x=%22218%22%20y=%2289%22%20width=%2246%22%20height=%224%22%20fill=%22%23dcfce7%22%20opacity=%22.7%22/%3E%3Crect%20x=%22231.8%22%20y=%22119%22%20width=%2218.4%22%20height=%2222%22%20rx=%221%22%20fill=%22%23dcfce7%22%20opacity=%22.45%22/%3E%3Crect%20x=%22267%22%20y=%22121%22%20width=%2234%22%20height=%22125%22%20rx=%222%22%20fill=%22%2357534e%22/%3E%3Crect%20x=%22303%22%20y=%2238%22%20width=%2230%22%20height=%22208%22%20rx=%222%22%20fill=%22%23a8a29e%22/%3E%3Crect%20x=%22303%22%20y=%2259%22%20width=%2230%22%20height=%224%22%20fill=%22%23e7e5e4%22%20opacity=%22.7%22/%3E%3Crect%20x=%22312.0%22%20y=%2280%22%20width=%2212.0%22%20height=%2226%22%20rx=%221%22%20fill=%22%23e7e5e4%22%20opacity=%22.45%22/%3E%3Crect%20x=%22336%22%20y=%2241%22%20width=%2246%22%20height=%22205%22%20rx=%222%22%20fill=%22%23166534%22/%3E%3Crect%20x=%22349.8%22%20y=%2299%22%20width=%2218.4%22%20height=%2232%22%20rx=%221%22%20fill=%22%23dcfce7%22%20opacity=%22.45%22/%3E%3Crect%20x=%22384%22%20y=%2277%22%20width=%2246%22%20height=%22169%22%20rx=%222%22%20fill=%22%2357534e%22/%3E%3Crect%20x=%22384%22%20y=%2298%22%20width=%2246%22%20height=%224%22%20fill=%22%23bbf7d0%22%20opacity=%22.7%22/%3E%3Crect%20x=%22397.8%22%20y=%22136%22%20width=%2218.4%22%20height=%2221%22%20rx=%221%22%20fill=%22%23bbf7d0%22%20opacity=%22.45%22/%3E%3Crect%20x=%22433%22%20y=%2299%22%20width=%2222%22%20height=%22147%22%20rx=%222%22%20fill=%22%234d7c0f%22/%3E%3Crect%20x=%22439.6%22%20y=%22168%22%20width=%228.8%22%20height=%2233%22%20rx=%221%22%20fill=%22%23fafaf9%22%20opacity=%22.45%22/%3E%3Crect%20x=%22455%22%20y=%2269%22%20width=%2226%22%20height=%22177%22%20rx=%222%22%20fill=%22%23d6d3d1%22/%3E%3Crect%20x=%22483%22%20y=%2273%22%20width=%2246%22%20height=%22173%22%20rx=%222%22%20fill=%22%23a8a29e%22/%3E%3Crect%20x=%22496.8%22%20y=%22118%22%20width=%2218.4%22%20height=%2222%22%20rx=%221%22%20fill=%22%23e7e5e4%22%20opacity=%22.45%22/%3E%3Crect%20x=%22529%22%20y=%2297%22%20width=%2246%22%20height=%22149%22%20rx=%222%22%20fill=%22%2314532d%22/%3E%3Crect%20x=%22542.8%22%20y=%22146%22%20width=%2218.4%22%20height=%2218%22%20rx=%221%22%20fill=%22%23e7e5e4%22%20opacity=%22.45%22/%3E%3Crect%20x=%22575%22%20y=%2258%22%20width=%2234%22%20height=%22188%22%20rx=%222%22%20fill=%22%23a8a29e%22/%3E%3Crect%20x=%22609%22%20y=%2239%22%20width=%2234%22%20height=%22207%22%20rx=%222%22%20fill=%22%23bbf7d0%22/%3E%3Crect%20x=%22609%22%20y=%2259%22%20width=%2234%22%20height=%224%22%20fill=%22%23e7e5e4%22%20opacity=%22.7%22/%3E%3Crect%20x=%22619.2%22%20y=%2299%22%20width=%2213.6%22%20height=%2230%22%20rx=%221%22%20fill=%22%23e7e5e4%22%20opacity=%22.45%22/%3E%3Crect%20x=%22643%22%20y=%22118%22%20width=%2226%22%20height=%22128%22%20rx=%222%22%20fill=%22%233f6212%22/%3E%3Crect%20x=%22643%22%20y=%22137%22%20width=%2226%22%20height=%224%22%20fill=%22%23e7e5e4%22%20opacity=%22.7%22/%3E%3Crect%20x=%22669%22%20y=%2254%22%20width=%2222%22%20height=%22192%22%20rx=%222%22%20fill=%22%2315803d%22/%3E%3Crect%20x=%22691%22%20y=%2248%22%20width=%2226%22%20height=%22198%22%20rx=%222%22%20fill=%22%23d6d3d1%22/%3E%3Crect%20x=%22691%22%20y=%2267%22%20width=%2226%22%20height=%224%22%20fill=%22%23fafaf9%22%20opacity=%22.7%22/%3E%3Crect%20x=%22720%22%20y=%22112%22%20width=%2222%22%20height=%22134%22%20rx=%222%22%20fill=%22%2378716c%22/%3E%3Crect%20x=%22720%22%20y=%22130%22%20width=%2222%22%20height=%224%22%20fill=%22%23e7e5e4%22%20opacity=%22.7%22/%3E%3Crect%20x=%22726.6%22%20y=%22155%22%20width=%228.8%22%20height=%2228%22%20rx=%221%22%20fill=%22%23e7e5e4%22%20opacity=%22.45%22/%3E%3Crect%20x=%22744%22%20y=%2238%22%20width=%2234%22%20height=%22208%22%20rx=%222%22%20fill=%22%2315803d%22/%3E%3Crect%20x=%22744%22%20y=%2260%22%20width=%2234%22%20height=%224%22%20fill=%22%23dcfce7%22%20opacity=%22.7%22/%3E%3Crect%20x=%22754.2%22%20y=%22100%22%20width=%2213.6%22%20height=%2218%22%20rx=%221%22%20fill=%22%23dcfce7%22%20opacity=%22.45%22/%3E%3Crect%20x=%22780%22%20y=%22115%22%20width=%2246%22%20height=%22131%22%20rx=%222%22%20fill=%22%234d7c0f%22/%3E%3Crect%20x=%22793.8%22%20y=%22162%22%20width=%2218.4%22%20height=%2234%22%20rx=%221%22%20fill=%22%23bbf7d0%22%20opacity=%22.45%22/%3E%3Crect%20x=%22828%22%20y=%2298%22%20width=%2246%22%20height=%22148%22%20rx=%222%22%20fill=%22%2386efac%22/%3E%3Crect%20x=%22874%22%20y=%2260%22%20width=%2226%22%20height=%22186%22%20rx=%222%22%20fill=%22%2378716c%22/%3E%3Crect%20x=%22902%22%20y=%2293%22%20width=%2234%22%20height=%22153%22%20rx=%222%22%20fill=%22%233f6212%22/%3E%3Crect%20x=%22902%22%20y=%22118%22%20width=%2234%22%20height=%224%22%20fill=%22%23bbf7d0%22%20opacity=%22.7%22/%3E%3Crect%20x=%22938%22%20y=%2298%22%20width=%2222%22%20height=%22148%22%20rx=%222%22%20fill=%22%23166534%22/%3E%3Crect%20x=%22938%22%20y=%22117%22%20width=%2222%22%20height=%224%22%20fill=%22%23fafaf9%22%20opacity=%22.7%22/%3E%3Crect%20x=%22944.6%22%20y=%22157%22%20width=%228.8%22%20height=%2218%22%20rx=%221%22%20fill=%22%23fafaf9%22%20opacity=%22.45%22/%3E%3Crect%20x=%220%22%20y=%22246%22%20width=%22960%22%20height=%2214%22%20fill=%22%2344403c%22/%3E%3Crect%20x=%220%22%20y=%22246%22%20width=%22960%22%20height=%223%22%20fill=%22%2378716c%22/%3E%3C/svg%3E");
            background-size: 960px 260px;
            background-repeat: repeat;
            background-position: center bottom;
            opacity: 0.4;
        }

        body::after {
            content: '';
            position: fixed;
            inset: 0;
            z-index: -1;
            background:
                radial-gradient(ellipse at center,
                    rgba(250, 250, 249, 0.92) 0%,
                    rgba(250, 250, 249, 0.75) 45%,
                    rgba(250, 250, 249, 0.45) 100%);
        }

        h2, h3 {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
        }

        .auth-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 24px 60px rgba(20, 83, 45, 0.16);
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

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="auth-card d-flex flex-column flex-lg-row">
                    
                    <!-- Left Sidebar -->
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