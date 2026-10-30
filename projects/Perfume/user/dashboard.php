<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

include '../include/connection.php';

$userId = $_SESSION['id'];
$username = $_SESSION['username'];

// --- 1. FETCH COUNTS ---
$orderCount = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id=?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($orderCount);
$stmt->fetch();
$stmt->close();

$cartCount = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM cart WHERE user_id=?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$stmt->bind_result($cartCount);
$stmt->fetch();
$stmt->close();

// --- 2. FETCH WOMEN'S COLLECTION (Latest 4 from Approved Sellers) ---
$women_products = [];
$w_sql = "SELECT p.id, p.name, p.price, p.image 
          FROM product p 
          JOIN sellers s ON p.seller_id = s.id 
          WHERE p.gender = 'women' AND s.status = 'approved' 
          ORDER BY p.id DESC LIMIT 4";
$w_res = $conn->query($w_sql);
if ($w_res) {
    while ($row = $w_res->fetch_assoc()) {
        $women_products[] = $row;
    }
}

// --- 3. FETCH MEN'S COLLECTION (Latest 4 from Approved Sellers) ---
$men_products = [];
$m_sql = "SELECT p.id, p.name, p.price, p.image 
          FROM product p 
          JOIN sellers s ON p.seller_id = s.id 
          WHERE p.gender = 'men' AND s.status = 'approved' 
          ORDER BY p.id DESC LIMIT 4";
$m_res = $conn->query($m_sql);
if ($m_res) {
    while ($row = $m_res->fetch_assoc()) {
        $men_products[] = $row;
    }
}

include 'include/header.php';
?>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">

    <div class="dashboard-header">
        <div>
            <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; font-size: 2.2rem; margin-bottom: 5px;">
                Welcome, <?php echo htmlspecialchars($username); ?>!
            </h1>
            <p style="color: #888; font-size: 1.1rem; margin: 0;">Welcome to your personal fragrance haven.</p>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-box-open"></i></div>
            <div class="stat-content">
                <h3>My Orders</h3>
                <div class="number"><?php echo $orderCount; ?></div>
                <a href="orders.php">Track & Manage <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: #F5F1E9; color: #A1937A;"><i class="fas fa-shopping-bag"></i></div>
            <div class="stat-content">
                <h3>Shopping Bag</h3>
                <div class="number"><?php echo $cartCount; ?></div>
                <a href="cart.php">View Cart <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <div class="collection-section">
        <div class="section-heading">
            <div class="title-wrap">
                <i class="fas fa-venus" style="color: #e91e63; font-size: 24px;"></i>
                <h2>Women's Collection</h2>
            </div>
            <a href="product.php?gender=women" class="view-all-link">View All Women's <i class="fas fa-chevron-right"></i></a>
        </div>

        <?php if (count($women_products) > 0): ?>
            <div class="product-mini-grid">
                <?php foreach ($women_products as $p): ?>
                    <div class="mini-card">
                        <div class="img-box">
                            <img src="../<?= htmlspecialchars($p['image']); ?>" alt="<?= htmlspecialchars($p['name']); ?>">
                        </div>
                        <div class="info-box">
                            <h4><?= htmlspecialchars($p['name']); ?></h4>
                            <p>₹<?= number_format($p['price'], 2); ?></p>
                            <a href="product_details.php?id=<?= $p['id']; ?>" class="btn-view">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: #999; font-style: italic;">No products available in this collection yet.</p>
        <?php endif; ?>
    </div>

    <div class="collection-section">
        <div class="section-heading">
            <div class="title-wrap">
                <i class="fas fa-mars" style="color: #2196f3; font-size: 24px;"></i>
                <h2>Men's Collection</h2>
            </div>
            <a href="product.php?gender=men" class="view-all-link">View All Men's <i class="fas fa-chevron-right"></i></a>
        </div>

        <?php if (count($men_products) > 0): ?>
            <div class="product-mini-grid">
                <?php foreach ($men_products as $p): ?>
                    <div class="mini-card">
                        <div class="img-box">
                            <img src="../<?= htmlspecialchars($p['image']); ?>" alt="<?= htmlspecialchars($p['name']); ?>">
                        </div>
                        <div class="info-box">
                            <h4><?= htmlspecialchars($p['name']); ?></h4>
                            <p>₹<?= number_format($p['price'], 2); ?></p>
                            <a href="product_details.php?id=<?= $p['id']; ?>" class="btn-view">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: #999; font-style: italic;">No products available in this collection yet.</p>
        <?php endif; ?>
    </div>

    <div class="guide-banner">
        <div class="guide-content">
            <h3><i class="fas fa-shield-alt"></i> New to Perfume Nest?</h3>
            <p>Learn how to find your signature scent, secure your payments, and track your luxury deliveries.</p>
            <a href="guide.php" class="btn-guide">Read the Shopping Guide</a>
        </div>
        <div class="guide-img">
            <i class="fas fa-book-open"></i>
        </div>
    </div>

</main>

<style>
    /* Header & Stats */
    .dashboard-header {
        margin-bottom: 40px;
    }

    .stats-grid {
        display: flex;
        gap: 30px;
        flex-wrap: wrap;
        margin-bottom: 50px;
    }

    .stat-card {
        flex: 1;
        min-width: 250px;
        background: #fff;
        padding: 25px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 20px;
        border: 1px solid #e0e0e0;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.04);
        transition: 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        border-color: #B2A48C;
    }

    .stat-icon {
        width: 65px;
        height: 65px;
        border-radius: 12px;
        background: #f0f7f4;
        color: #27ae60;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
    }

    .stat-content h3 {
        font-size: 13px;
        color: #888;
        text-transform: uppercase;
        margin: 0;
        letter-spacing: 1px;
    }

    .stat-content .number {
        font-size: 30px;
        font-weight: 700;
        color: #5E5343;
        margin: 5px 0;
    }

    .stat-content a {
        font-size: 13px;
        color: #A1937A;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .stat-content a:hover {
        color: #5E5343;
    }

    /* Collection Sections */
    .collection-section {
        margin-bottom: 60px;
    }

    .section-heading {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 25px;
        border-bottom: 2px solid #F5F1E9;
        padding-bottom: 10px;
    }

    .title-wrap {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .title-wrap h2 {
        font-family: 'Playfair Display', serif;
        color: #5E5343;
        margin: 0;
        font-size: 24px;
    }

    .view-all-link {
        color: #A1937A;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .view-all-link:hover {
        color: #5E5343;
    }

    /* Modern Product Cards */
    .product-mini-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 25px;
    }

    .mini-card {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        overflow: hidden;
        text-align: center;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
    }

    .mini-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
        border-color: #B2A48C;
    }

    .img-box {
        background: #F9F9F9;
        height: 220px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        position: relative;
    }

    .img-box img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        transition: transform 0.4s ease;
    }

    .mini-card:hover .img-box img {
        transform: scale(1.1);
    }

    .info-box {
        padding: 20px;
    }

    .info-box h4 {
        font-size: 16px;
        color: #5E5343;
        margin-bottom: 5px;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .info-box p {
        font-weight: 700;
        color: #A1937A;
        font-size: 15px;
        margin-bottom: 15px;
    }

    .btn-view {
        display: block;
        padding: 10px;
        background: transparent;
        border: 1px solid #5E5343;
        color: #5E5343;
        text-decoration: none;
        border-radius: 50px;
        font-size: 13px;
        font-weight: 600;
        transition: 0.3s;
    }

    .btn-view:hover {
        background: #5E5343;
        color: #fff;
    }

    /* Guide Banner */
    .guide-banner {
        background: linear-gradient(135deg, #5E5343 0%, #3e362b 100%);
        border-radius: 15px;
        padding: 40px 50px;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        overflow: hidden;
        position: relative;
        box-shadow: 0 10px 30px rgba(94, 83, 67, 0.3);
    }

    .guide-content {
        max-width: 60%;
        z-index: 2;
    }

    .guide-content h3 {
        font-family: 'Playfair Display', serif;
        font-size: 26px;
        margin-bottom: 15px;
        color: #F5F1E9;
    }

    .guide-content p {
        font-size: 15px;
        color: #ddd;
        margin-bottom: 25px;
        line-height: 1.6;
    }

    .btn-guide {
        padding: 14px 35px;
        background: #A1937A;
        color: #fff;
        text-decoration: none;
        border-radius: 50px;
        font-weight: 600;
        transition: 0.3s;
        display: inline-block;
    }

    .btn-guide:hover {
        background: #fff;
        color: #5E5343;
        transform: translateY(-2px);
    }

    .guide-img {
        font-size: 140px;
        color: rgba(255, 255, 255, 0.05);
        position: absolute;
        right: 30px;
        top: 50%;
        transform: translateY(-50%);
    }

    @media (max-width: 768px) {
        .guide-banner {
            flex-direction: column;
            text-align: center;
            padding: 30px 20px;
        }

        .guide-content {
            max-width: 100%;
        }

        .guide-img {
            display: none;
        }

        .section-heading {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
    }
</style>

<?php include 'include/footer.php'; ?>