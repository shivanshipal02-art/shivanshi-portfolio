<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfume Nest | Luxury Fragrances</title>
    <link rel="apple-touch-icon" sizes="180x180" href="img/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicons/favicon-16x16.png">
    <link rel="shortcut icon" type="image/x-icon" href="img/favicons/favicon.ico">
    <link rel="manifest" href="img/favicons/manifest.json">
    <meta name="msapplication-TileImage" content="img/favicons/mstile-150x150.png">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* ===========================
           1. GLOBAL VARIABLES & RESET
           =========================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            /* Global Gradient Background */
            background: linear-gradient(120deg, #E2E2DE, #B7AC9B);
            color: #1C1C1B;
            /* Dark Text */
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        ul {
            list-style: none;
        }

        /* ===========================
           2. HEADER & NAVIGATION
           =========================== */
        nav {
            background-color: transparent;
            padding: 30px 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 700;
            color: #1C1C1B;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-links a {
            margin-left: 20px;
            font-size: 16px;
            font-weight: 500;
            color: #1C1C1B;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            /* Space between icon and text */
        }

        .nav-links a:hover {
            color: #6A5D52;
            /* Brown text on hover */
            text-decoration: none;
            /* Removed underline for cleaner icon look */
        }

        /* Icon Styling */
        .nav-links i {
            font-size: 1.1rem;
            color: #6A5D52;
            /* Make icons slightly brown for style */
        }

        /* Active/Hover Icon Color */
        .nav-links a:hover i {
            color: #1C1C1B;
        }

        /* Register Button Style */
        .btn-register {
            background-color: #6A5D52;
            color: #E2E2DE !important;
            padding: 10px 25px;
            border-radius: 25px;
        }

        /* Ensure icon inside register button is light */
        .btn-register i {
            color: #E2E2DE !important;
        }

        .btn-register:hover {
            background-color: #1C1C1B;
            color: #E2E2DE !important;
        }

        .btn-register:hover i {
            color: #E2E2DE !important;
        }

        /* Responsive Nav */
        @media (max-width: 992px) {
            nav {
                padding: 20px;
                flex-direction: column;
                gap: 15px;
            }

            .nav-links {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 15px;
            }

            .nav-links a {
                margin: 0;
            }
        }
    </style>
</head>

<body>

    <nav>
        <a href="index.php" class="logo">
            <i style="color: #6A5D52;"></i> Perfume Nest
        </a>

        <div class="nav-links">
            <a href="index.php"><i class="fas fa-home"></i> Home</a>

            <a href="product.php"><i class="fas fa-bag-shopping"></i> Product</a>

            <a href="about.php"><i class="fas fa-info-circle"></i> About</a>

            <a href="contact.php"><i class="fas fa-envelope"></i> Contact</a>

            <a href="login.php" style="margin-left: 10px;"><i class="fas fa-sign-in-alt"></i> Login</a>

            <a href="registration.php" class="btn-register"><i class="fas fa-user-plus"></i> Register</a>
        </div>
    </nav>