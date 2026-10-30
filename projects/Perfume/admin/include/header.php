<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
  header('Location: ../login.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Admin Panel - Perfume Nest</title>
  <link rel="apple-touch-icon" sizes="180x180" href="../img/favicons/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="../img/favicons/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="../img/favicons/favicon-16x16.png">
  <link rel="shortcut icon" type="image/x-icon" href="../img/favicons/favicon.ico">
  <link rel="manifest" href="../img/favicons/manifest.json">
  <meta name="msapplication-TileImage" content="../img/favicons/mstile-150x150.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="admindashbord.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">

  <style>
    /* === OLD DESIGN VARIABLES (Admin) === */
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
      /* Light Text (for Header) */
      --hover-color: #A1937A;
      /* Gold/Brown Accent */
      --card-bg: #ffffff;
      /* White for cards */
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

    /* === TOP HEADER === */
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
      /* Lighter grey for 'Admin' */
      text-transform: uppercase;
      letter-spacing: 2px;
      margin-left: 10px;
    }

    .admin-info {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 14px;
      font-weight: 500;
      color: var(--text-light);
    }

    .admin-info i {
      color: var(--text-light);
      font-size: 20px;
    }

    /* === SIDEBAR STYLES (Beige) === */
    .sidebar {
      width: 260px;
      background-color: var(--sidebar-bg);
      color: var(--text-main);
      padding: 30px 20px;
      display: flex;
      flex-direction: column;
      transition: all 0.3s ease;
      flex-shrink: 0;
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

    /* Hover & Active Effects */
    .sidebar a:hover,
    .sidebar a.active {
      background-color: var(--hover-color);
      color: #fff;
    }

    .sidebar a:hover i,
    .sidebar a.active i {
      color: #fff;
    }

    /* Mobile Responsive */
    @media (max-width: 900px) {
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
</head>

<body>

  <header>
    <div class="brand">Perfume Nest <span>Admin</span></div>
    <div class="admin-info">
      <span>Administrator</span>
      <i class="fas fa-user-shield"></i>
    </div>
  </header>

  <div style="display: flex; flex: 1; min-height: calc(100vh - 70px);">

    <aside class="sidebar">
      <h3>Admin Controls</h3>
      <ul>
        <?php $page = basename($_SERVER['PHP_SELF']); ?>

        <li>
          <a href="admindashbord.php" class="<?= ($page == 'admindashbord.php') ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
          </a>
        </li>
        <li>
          <a href="manage_users.php" class="<?= ($page == 'manage_users.php') ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> <span>Manage Users</span>
          </a>
        </li>
        <li>
          <a href="manage_sellers.php" class="<?= ($page == 'manage_sellers.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-tie"></i> <span>Manage Sellers</span>
          </a>
        </li>
        <li>
          <a href="manage_products.php" class="<?= ($page == 'manage_products.php') ? 'active' : ''; ?>">
            <i class="fas fa-box-open"></i> <span>Manage Products</span>
          </a>
        </li>
        <li>
          <a href="manage_purchase.php" class="<?= ($page == 'manage_purchase.php') ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i> <span>Manage Purchase</span>
          </a>
        </li>
        <li>
          <a href="reports.php" class="<?= ($page == 'reports.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i> <span>Reports</span>
          </a>
        </li>

        <li style="margin-top: 30px; border-top: 1px solid var(--text-main); padding-top: 10px;">
          <a href="../logout.php" style="color: #800000;"> <i class="fas fa-sign-out-alt" style="color: #800000;"></i> <span>Logout</span>
          </a>
        </li>
      </ul>
    </aside>