<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Security Check
if (!isset($_SESSION['seller_logged_in']) || $_SESSION['seller_logged_in'] !== true) {
  header('Location: ../login.php');
  exit;
}
$username = $_SESSION['username'] ?? 'Seller';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Seller Panel - Perfume Nest</title>
  <link rel="apple-touch-icon" sizes="180x180" href="../img/favicons/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="../img/favicons/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="../img/favicons/favicon-16x16.png">
  <link rel="shortcut icon" type="image/x-icon" href="../img/favicons/favicon.ico">
  <link rel="manifest" href="../img/favicons/manifest.json">
  <meta name="msapplication-TileImage" content="../img/favicons/mstile-150x150.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">

  <style>
    /* === OLD DESIGN VARIABLES (Seller) === */
    :root {
      --header-bg: #5E5343;
      /* Dark Brown */
      --sidebar-bg: #B2A48C;
      /* Beige */
      --body-bg: #F5F1E9;
      /* Light Cream */
      --text-main: #5E5343;
      /* Dark Text */
      --text-light: #F5F1E9;
      /* Light Text */
      --hover-color: #A1937A;
      /* Sidebar Hover */
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }

    body {
      background-color: var(--body-bg);
      color: var(--text-main);
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    /* === HEADER STYLES === */
    header {
      background-color: var(--header-bg);
      color: var(--text-light);
      padding: 15px 30px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .brand {
      font-family: 'Playfair Display', serif;
      font-size: 24px;
      font-weight: 700;
      letter-spacing: 0.5px;
      color: var(--text-light);
    }

    .brand span {
      font-size: 0.6em;
      color: #ccc;
      text-transform: uppercase;
      letter-spacing: 2px;
      margin-left: 10px;
    }

    .user-info {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 14px;
      font-weight: 500;
      color: var(--text-light);
    }

    .user-info i {
      color: var(--text-light);
      font-size: 20px;
    }
  </style>
</head>

<body>

  <header>
    <div class="brand">Perfume Nest <span>Seller Panel</span></div>
    <div class="user-info">
      <span>Welcome, <?= htmlspecialchars($username); ?></span>
      <i class="fas fa-user-tie"></i>
    </div>
  </header>

  <div style="display: flex; flex: 1;">

    <style>
      /* === SIDEBAR STYLES === */
      .sidebar {
        width: 260px;
        background-color: var(--sidebar-bg);
        color: var(--text-main);
        padding: 30px 20px;
        display: flex;
        flex-direction: column;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.05);
      }

      .sidebar h3 {
        font-family: 'Playfair Display', serif;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 25px;
        color: var(--text-main);
        padding-left: 15px;
        border-bottom: 2px solid var(--text-main);
        padding-bottom: 10px;
      }

      .sidebar ul {
        list-style: none;
        padding: 0;
      }

      .sidebar li {
        margin-bottom: 8px;
      }

      .sidebar a {
        text-decoration: none;
        color: var(--text-main);
        font-size: 15px;
        font-weight: 500;
        display: flex;
        align-items: center;
        padding: 12px 15px;
        border-radius: 8px;
        transition: all 0.3s ease;
      }

      .sidebar a i {
        width: 25px;
        margin-right: 10px;
        font-size: 16px;
        color: var(--text-main);
      }

      .sidebar a:hover,
      .sidebar a.active {
        background-color: var(--hover-color);
        color: #fff;
      }

      .sidebar a:hover i,
      .sidebar a.active i {
        color: #fff;
      }

      @media (max-width: 768px) {
        .sidebar {
          width: 70px;
          padding: 20px 10px;
        }

        .sidebar h3,
        .sidebar span {
          display: none;
        }

        .sidebar a {
          justify-content: center;
          padding: 15px;
        }

        .sidebar a i {
          margin: 0;
          font-size: 20px;
        }
      }
    </style>

    <aside class="sidebar">
      <h3>Management</h3>
      <ul>
        <?php $page = basename($_SERVER['PHP_SELF']); ?>

        <li>
          <a href="dashboard.php" class="<?= ($page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
          </a>
        </li>
        <li>
          <a href="add_product.php" class="<?= ($page == 'add_product.php') ? 'active' : ''; ?>">
            <i class="fas fa-plus-circle"></i> <span>Add Product</span>
          </a>
        </li>
        <li>
          <a href="manage_products.php" class="<?= ($page == 'manage_products.php') ? 'active' : ''; ?>">
            <i class="fas fa-boxes"></i> <span>My Products</span>
          </a>
        </li>
        <li>
          <a href="my_orders.php" class="<?= ($page == 'my_orders.php') ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-list"></i> <span>Orders Received</span>
          </a>
        </li>
        <li>
          <a href="manage_reviews.php" class="<?= ($page == 'manage_reviews.php') ? 'active' : ''; ?>">
            <i class="fas fa-star"></i> <span>Product Reviews</span>
          </a>
        </li>
        <li>
          <a href="reports.php" class="<?= ($page == 'reports.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i> <span>Sales Reports</span>
          </a>
        </li>
        <li>
          <a href="profile.php" class="<?= ($page == 'profile.php' || $page == 'change_password.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i> <span>My Profile</span>
          </a>
        </li>

        <li style="margin-top: 30px; border-top: 1px solid var(--text-main); padding-top: 10px;">
          <a href="../logout.php" style="color: #800000;"> <i class="fas fa-sign-out-alt" style="color: #800000;"></i> <span>Logout</span>
          </a>
        </li>
      </ul>
    </aside>