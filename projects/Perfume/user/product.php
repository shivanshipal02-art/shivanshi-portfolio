<?php
include '../include/connection.php';

session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

$username = $_SESSION['username'] ?? '';
// Get user id
$userId = 0;
$userSql = "SELECT id FROM users WHERE username=?";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param('s', $username);
$userStmt->execute();
$userStmt->bind_result($userId);
$userStmt->fetch();
$userStmt->close();

// Keep add-to-cart backend logic active
if (isset($_GET['action']) && $_GET['action'] === 'add' && isset($_GET['id'])) {
    $productId = intval($_GET['id']);

    // Verify stock before adding to cart (Backend Security)
    $s_check = $conn->query("SELECT stock FROM product WHERE id = $productId");
    $stock_avail = ($s_check && $s_check->num_rows > 0) ? $s_check->fetch_assoc()['stock'] : 0;

    if ($stock_avail > 0) {
        // Check if already in cart
        $checkSql = "SELECT id, quantity FROM cart WHERE user_id=? AND product_id=?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('ii', $userId, $productId);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $checkStmt->bind_result($cartId, $quantity);
            $checkStmt->fetch();
            $checkStmt->close();

            $newQuantity = $quantity + 1;
            $updateSql = "UPDATE cart SET quantity=? WHERE id=?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param('ii', $newQuantity, $cartId);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            $checkStmt->close();
            $insertSql = "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param('ii', $userId, $productId);
            $insertStmt->execute();
            $insertStmt->close();
        }

        echo "<script>
                window.onload = function() {
                    document.getElementById('cartPopup').style.display = 'flex';
                    setTimeout(function() {
                        document.getElementById('cartPopup').style.opacity = '0';
                        setTimeout(function() {
                            document.getElementById('cartPopup').style.display = 'none';
                            document.getElementById('cartPopup').style.opacity = '1';
                        }, 300);
                    }, 2000);
                }
              </script>";
    } else {
        // If user forced URL, show Out of Stock
        echo "<script>window.onload = function() { showOosNotification(); }</script>";
    }
}

// --- FILTER & SORT LOGIC ---
$gender_filter = isset($_GET['gender']) ? $_GET['gender'] : '';
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'desc';

// FIX: Added `p.stock` to fetch stock status
$sql = "SELECT p.id, p.name, p.price, p.image, p.stock 
        FROM product p 
        JOIN sellers s ON p.seller_id = s.id 
        WHERE s.status = 'approved'";

// Apply Gender Filter
if ($gender_filter === 'men' || $gender_filter === 'women' || $gender_filter === 'unisex') {
    $sql .= " AND p.gender = '" . $conn->real_escape_string($gender_filter) . "'";
}

// Apply Sorting
if ($sort_order === 'price_asc') {
    $sql .= " ORDER BY p.price ASC";
} elseif ($sort_order === 'price_desc') {
    $sql .= " ORDER BY p.price DESC";
} else {
    $sql .= " ORDER BY p.id DESC"; // Default Newest
}

$result = $conn->query($sql);
$products = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

include 'include/header.php';
?>

<div id="oosNotification" class="oos-notification">
    <i class="fas fa-exclamation-circle"></i> Product is currently Out of Stock
</div>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 20px;">
        <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin: 0;">
            Discover Fragrances
        </h1>

        <form method="GET" action="product.php" class="filter-form">
            <div class="filter-group">
                <label for="gender">Gender:</label>
                <select name="gender" id="gender">
                    <option value="">All Fragrances</option>
                    <option value="men" <?= ($gender_filter == 'men') ? 'selected' : '' ?>>Men</option>
                    <option value="women" <?= ($gender_filter == 'women') ? 'selected' : '' ?>>Women</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="sort">Sort By:</label>
                <select name="sort" id="sort">
                    <option value="desc" <?= ($sort_order == 'desc') ? 'selected' : '' ?>>Newest First</option>
                    <option value="price_asc" <?= ($sort_order == 'price_asc') ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="price_desc" <?= ($sort_order == 'price_desc') ? 'selected' : '' ?>>Price: High to Low</option>
                </select>
            </div>

            <button type="submit" class="btn-filter">Apply</button>
            <a href="product.php" class="btn-clear">Clear</a>
        </form>
    </div>

    <?php if (count($products) > 0): ?>
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-card">

                    <a href="product_details.php?id=<?= $product['id']; ?>" class="product-link">
                        <div class="product-img">
                            <?php if (!empty($product['image'])): ?>
                                <img src="../<?= htmlentities($product['image']); ?>" alt="Product">
                            <?php else: ?>
                                <div class="no-img">No Image</div>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h3 class="p-name"><?= htmlentities($product['name']); ?></h3>
                            <div class="p-price">&#8377;<?= htmlentities($product['price']); ?></div>
                        </div>
                    </a>

                    <div class="product-actions">
                        <?php if ($product['stock'] > 0): ?>
                            <a href="product.php?action=add&id=<?= $product['id']; ?>&gender=<?= urlencode($gender_filter); ?>&sort=<?= urlencode($sort_order); ?>" class="btn-cart">
                                <i class="fas fa-shopping-bag"></i> Cart
                            </a>
                            <a href="by.php?id=<?= $product['id']; ?>" class="btn-buy">
                                <i class="fas fa-bolt"></i> Buy
                            </a>
                        <?php else: ?>
                            <a href="javascript:void(0);" onclick="showOosNotification()" class="btn-cart" style="opacity: 0.5; cursor: not-allowed;">
                                <i class="fas fa-shopping-bag"></i> Cart
                            </a>
                            <a href="javascript:void(0);" onclick="showOosNotification()" class="btn-buy" style="border-color: #e74c3c; color: #e74c3c;">
                                Out of Stock
                            </a>
                        <?php endif; ?>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-search" style="font-size: 50px; color: #B2A48C; margin-bottom: 20px;"></i>
            <h3 style="color: #5E5343;">No Products Found</h3>
            <p style="color: #777;">Try adjusting your filters to see more results.</p>
            <a href="product.php" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: #5E5343; color: #fff; text-decoration: none; border-radius: 5px;">View All</a>
        </div>
    <?php endif; ?>

    <div id="cartPopup" class="cart-popup-overlay" style="display: none;">
        <div class="cart-popup-box">
            <div class="icon-circle">
                <i class="fas fa-check"></i>
            </div>
            <h2>Added to Cart!</h2>
            <p>The fragrance has been added to your shopping bag.</p>
            <div style="margin-top: 25px; display: flex; gap: 15px; justify-content: center;">
                <a href="cart.php" class="btn-view-cart">View Cart</a>
                <button onclick="document.getElementById('cartPopup').style.display='none'" class="btn-continue">Continue</button>
            </div>
        </div>
    </div>

</main>

<style>
    /* === OUT OF STOCK NOTIFICATION === */
    .oos-notification {
        position: fixed;
        top: -100px;
        left: 50%;
        transform: translateX(-50%);
        background-color: #e74c3c;
        color: #fff;
        padding: 12px 25px;
        border-radius: 50px;
        font-family: 'Poppins', sans-serif;
        font-size: 14px;
        font-weight: 600;
        box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
        z-index: 10000;
        transition: top 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .oos-notification.show {
        top: 20px;
    }

    /* === FILTER STYLES === */
    .filter-form { display: flex; gap: 15px; align-items: center; background: #F5F1E9; padding: 10px 20px; border-radius: 10px; border: 1px solid #e0e0e0; flex-wrap: wrap; }
    .filter-group { display: flex; align-items: center; gap: 8px; }
    .filter-group label { font-weight: 600; color: #5E5343; font-size: 14px; }
    .filter-group select { padding: 8px 15px; border-radius: 6px; border: 1px solid #ccc; outline: none; font-family: 'Poppins', sans-serif; color: #333; background: #fff; }
    .btn-filter { padding: 8px 20px; background: #5E5343; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.3s; }
    .btn-filter:hover { background: #A1937A; }
    .btn-clear { color: #777; text-decoration: none; font-size: 14px; font-weight: 500; }
    .btn-clear:hover { color: #d9534f; text-decoration: underline; }

    /* === PRODUCT GRID & CARD STYLES === */
    .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 30px; margin-top: 20px; }
    .product-card { background: #F5F1E9; border-radius: 12px; overflow: hidden; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03); border: 1px solid #e0e0e0; transition: 0.3s; display: flex; flex-direction: column; }
    .product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08); border-color: #B2A48C; }
    .product-link { text-decoration: none; color: inherit; display: block; }
    .product-img { width: 100%; height: 220px; background: #fff; display: flex; align-items: center; justify-content: center; border-bottom: 1px solid #eee; padding: 15px; }
    .product-img img { max-width: 100%; max-height: 100%; object-fit: contain; transition: transform 0.3s; }
    .product-card:hover .product-img img { transform: scale(1.05); }
    .no-img { color: #999; font-size: 14px; }
    .product-info { padding: 20px 20px 10px 20px; text-align: center; }
    .p-name { font-size: 16px; color: #5E5343; margin-bottom: 8px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: color 0.3s; }
    .product-card:hover .p-name { color: #A1937A; }
    .p-price { font-size: 18px; font-weight: 700; color: #A1937A; }

    /* === ACTION BUTTONS STYLES === */
    .product-actions { display: flex; gap: 10px; padding: 0 20px 20px 20px; margin-top: auto; }
    .btn-cart, .btn-buy { flex: 1; padding: 10px; text-align: center; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 6px; }
    .btn-cart { background: #5E5343; color: #fff; border: 1px solid #5E5343; }
    .btn-cart:hover { background: #A1937A; border-color: #A1937A; }
    .btn-buy { background: transparent; color: #5E5343; border: 1px solid #5E5343; }
    .btn-buy:hover { background: #5E5343; color: #fff; }
    .empty-state { text-align: center; margin-top: 50px; }

    /* Popup Animations */
    .cart-popup-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); z-index: 9999; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(3px); transition: opacity 0.3s ease; }
    .cart-popup-box { background: #F5F1E9; padding: 40px 60px; border-radius: 15px; text-align: center; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15); border: 2px solid #B2A48C; animation: popUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    .icon-circle { width: 70px; height: 70px; background: #27ae60; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 35px; margin: 0 auto 20px; }
    .cart-popup-box h2 { font-family: 'Playfair Display', serif; color: #5E5343; margin-bottom: 10px; }
    .cart-popup-box p { color: #666; font-size: 14px; }
    .btn-view-cart { padding: 10px 25px; background: #5E5343; color: #fff; text-decoration: none; border-radius: 50px; font-weight: 600; transition: 0.3s; }
    .btn-view-cart:hover { background: #A1937A; }
    .btn-continue { padding: 10px 25px; background: transparent; color: #5E5343; border: 1px solid #5E5343; border-radius: 50px; cursor: pointer; font-weight: 600; font-family: 'Poppins'; transition: 0.3s; }
    .btn-continue:hover { background: #5E5343; color: #fff; }

    @keyframes popUp { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
    @media (max-width: 768px) { .filter-form { justify-content: center; width: 100%; } .filter-group { flex-direction: column; align-items: flex-start; flex: 1; } .filter-group select { width: 100%; } }
</style>

<script>
    // JavaScript to trigger Out of Stock Notification
    function showOosNotification() {
        const notif = document.getElementById('oosNotification');
        notif.classList.add('show');
        
        // Hide after 3 seconds
        setTimeout(() => {
            notif.classList.remove('show');
        }, 3000);
    }
</script>

<?php include 'include/footer.php'; ?>