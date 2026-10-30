<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// FIX: Re-establish the database connection because the parent pages close it!
include '../include/connection.php';

// Dynamically fetch the number of items in the cart
$cart_count = 0;
if (isset($_SESSION['username']) && isset($conn)) {
  $u_name = $_SESSION['username'];
  // Get the total quantity of products in the cart for the logged-in user
  $count_sql = "SELECT SUM(quantity) FROM cart WHERE user_id = (SELECT id FROM users WHERE username = ?)";
  $c_stmt = $conn->prepare($count_sql);
  if ($c_stmt) {
    $c_stmt->bind_param("s", $u_name);
    $c_stmt->execute();
    $c_stmt->bind_result($cart_count);
    $c_stmt->fetch();
    $c_stmt->close();
  }
  $cart_count = $cart_count ?? 0; // Fallback to 0 if the cart is completely empty
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Dashboard - Perfume Nest</title>
  <link rel="apple-touch-icon" sizes="180x180" href="../img/favicons/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="../img/favicons/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="../img/favicons/favicon-16x16.png">
  <link rel="shortcut icon" type="image/x-icon" href="../img/favicons/favicon.ico">
  <link rel="manifest" href="../img/favicons/manifest.json">
  <meta name="msapplication-TileImage" content="../img/favicons/mstile-150x150.png">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">

  <style>
    /* === EXACT COLORS FROM YOUR OLD FILE === */
    :root {
      --header-bg: #5E5343;
      /* Dark Brown */
      --sidebar-bg: #B2A48C;
      /* Beige/Taupe */
      --body-bg: #F5F1E9;
      /* Light Cream */
      --text-color: #5E5343;
      /* Dark Text */
      --text-light: #F5F1E9;
      /* Light Text */
      --hover-bg: #A1937A;
      /* Sidebar Hover */
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }

    body {
      background-color: #ffffff;
      color: var(--text-color);
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    /* === TOP HEADER === */
    header {
      background-color: var(--header-bg);
      color: var(--text-light);
      padding: 20px 40px;
      text-align: center;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .brand {
      font-family: 'Playfair Display', serif;
      font-size: 2.2rem;
      font-weight: 700;
      color: var(--text-light);
    }

    .user-welcome {
      font-size: 1rem;
      font-weight: 500;
    }
  </style>
</head>

<body>

  <header>
    <div class="brand">Perfume Nest</div>
    <div class="user-welcome">
      Welcome, <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?> <i class="fas fa-user-circle"></i>
    </div>
  </header>

  <div style="display: flex; flex: 1;">

    <style>
      /* === SIDEBAR STYLES === */
      .sidebar {
        width: 240px;
        background-color: var(--sidebar-bg);
        color: var(--text-color);
        padding: 30px 20px;
        display: flex;
        flex-direction: column;
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
      }

      .sidebar h3 {
        font-size: 1.5rem;
        margin-bottom: 25px;
        font-weight: 600;
        color: var(--text-color);
        padding-left: 10px;
      }

      .sidebar ul {
        list-style: none;
        padding: 0;
      }

      .sidebar li {
        margin-bottom: 12px;
      }

      .sidebar a {
        text-decoration: none;
        color: var(--text-color);
        font-size: 16px;
        font-weight: 500;
        display: flex;
        align-items: center;
        padding: 12px 15px;
        border-radius: 4px;
        transition: background-color 0.3s ease;
      }

      .sidebar a i {
        width: 25px;
        margin-right: 10px;
      }

      /* Hover & Active Effects */
      .sidebar a:hover,
      .sidebar a.active {
        background-color: var(--hover-bg);
        color: white;
      }

      /* Cart Badge Style */
      .cart-badge {
        background-color: #c0392b;
        /* Red badge to grab attention */
        color: white;
        font-size: 11px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 20px;
        margin-left: auto;
        /* Pushes the badge to the far right */
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
      }

      /* Mobile Responsive */
      @media (max-width: 768px) {
        .sidebar {
          width: 60px;
          padding: 20px 5px;
        }

        .sidebar h3,
        .sidebar span,
        .cart-badge {
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
      <h3>User Panel</h3>
      <ul>
        <?php $page = basename($_SERVER['PHP_SELF']); ?>

        <li>
          <a href="dashboard.php" class="<?= ($page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
          </a>
        </li>

        <li>
          <a href="profile.php" class="<?= ($page == 'profile.php' || $page == 'change_password.php') ? 'active' : ''; ?>">
            <i class="fas fa-user"></i> <span>Profile</span>
          </a>
        </li>

        <li>
          <a href="product.php" class="<?= ($page == 'product.php' || $page == 'product_details.php') ? 'active' : ''; ?>">
            <i class="fas fa-spray-can"></i> <span>Products</span>
          </a>
        </li>

        <li>
          <a href="cart.php" class="<?= ($page == 'cart.php' || $page == 'by.php' || $page == 'payment.php') ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i> <span>My Cart</span>
            <?php if ($cart_count > 0): ?>
              <span class="cart-badge"><?= $cart_count; ?></span>
            <?php endif; ?>
          </a>
        </li>

        <li>
          <a href="wishlist.php" class="<?= ($page == 'wishlist.php') ? 'active' : ''; ?>">
            <i class="fas fa-heart"></i> <span>Wishlist</span>
          </a>
        </li>

        <li>
          <a href="orders.php" class="<?= ($page == 'orders.php' || $page == 'orders_view.php') ? 'active' : ''; ?>">
            <i class="fas fa-box"></i> <span>Order History</span>
          </a>
        </li>

        

        <li style="margin-top: 30px; border-top: 1px solid var(--text-color); padding-top: 10px;">
          <a href="../logout.php" style="color: #c0392b;">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
          </a>
        </li>
      </ul>
    </aside>