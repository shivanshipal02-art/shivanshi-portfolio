<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../include/connection.php';

if (!isset($_SESSION['seller_logged_in'])) {
    header('Location: ../login.php');
    exit;
}

$seller_id = $_SESSION['id'];

// --- 1. FETCH SELLER METRICS ---
$myProducts = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM product WHERE seller_id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$stmt->bind_result($myProducts);
$stmt->fetch();
$stmt->close();

$myRevenue = 0;
$stmt = $conn->prepare("SELECT SUM(o.price) FROM orders o JOIN product p ON o.product_id = p.id WHERE p.seller_id = ? AND o.status != 'Cancelled'");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$stmt->bind_result($myRevenue);
$stmt->fetch();
$myRevenue = $myRevenue ?? 0;
$stmt->close();

$myPending = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM orders o JOIN product p ON o.product_id = p.id WHERE p.seller_id = ? AND o.status = 'Pending'");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$stmt->bind_result($myPending);
$stmt->fetch();
$stmt->close();

$myReviews = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM reviews r JOIN product p ON r.product_id = p.id WHERE p.seller_id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$stmt->bind_result($myReviews);
$stmt->fetch();
$stmt->close();

// --- 2. FETCH GRAPH DATA ---
$statusCounts = ['Pending' => 0, 'Shipped' => 0, 'Delivered' => 0, 'Cancelled' => 0];
$stmt = $conn->prepare("SELECT o.status, COUNT(*) FROM orders o JOIN product p ON o.product_id = p.id WHERE p.seller_id = ? GROUP BY o.status");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$stmt->bind_result($st, $ct);
while ($stmt->fetch()) {
    if (isset($statusCounts[$st])) $statusCounts[$st] = $ct;
}
$stmt->close();

// Fetch last 12 months for 2025-2026 data range
$months = [];
$salesDataArr = [];
for ($i = 11; $i >= 0; $i--) {
    $date = date("Y-m", strtotime("-$i months"));
    $monthName = date("M Y", strtotime("-$i months")); // e.g., Jan 2025
    $sql = "SELECT SUM(o.price) as total FROM orders o JOIN product p ON o.product_id = p.id WHERE p.seller_id = $seller_id AND DATE_FORMAT(o.created_at, '%Y-%m') = '$date' AND o.status != 'Cancelled'";
    $res = $conn->query($sql);
    $row = $res->fetch_assoc();
    $months[] = $monthName;
    $salesDataArr[] = $row['total'] ?? 0;
}

// --- 3. FETCH RECENT ORDERS ---
$recentOrders = [];
$sqlRec = "SELECT 
            o.id as order_id, o.created_at, o.shipped_at, o.delivered_at, o.price, o.status, o.courier_name, o.tracking_id, o.courier_address,
            p.name as product_name, p.image, 
            u.username, u.email, u.phone,
            a.delivery_address AS profile_addr, a.city as profile_city,
            pay.payment_method, pay.payment_status, pay.transaction_id
        FROM orders o
        JOIN product p ON o.product_id = p.id
        JOIN users u ON o.user_id = u.id
        LEFT JOIN (
            SELECT user_id, delivery_address, city FROM address t1 
            WHERE id = (SELECT MAX(id) FROM address t2 WHERE t2.user_id = t1.user_id)
        ) a ON u.id = a.user_id
        LEFT JOIN payment pay ON o.id = pay.order_id
        WHERE p.seller_id = ?
        ORDER BY o.created_at DESC LIMIT 5";

$stmtRec = $conn->prepare($sqlRec);
$stmtRec->bind_param("i", $seller_id);
$stmtRec->execute();
$resRec = $stmtRec->get_result();
while ($row = $resRec->fetch_assoc()) {
    $recentOrders[] = $row;
}
$stmtRec->close();

// --- 4. FETCH LOW/OUT OF STOCK ALERTS ---
$lowStockProducts = [];
$sqlLowStock = "SELECT id, name, stock, image FROM product WHERE seller_id = ? AND stock <= 10 ORDER BY stock ASC LIMIT 5";
$stmtLow = $conn->prepare($sqlLowStock);
$stmtLow->bind_param("i", $seller_id);
$stmtLow->execute();
$resLow = $stmtLow->get_result();
while ($row = $resLow->fetch_assoc()) {
    $lowStockProducts[] = $row;
}
$stmtLow->close();

include 'include/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin: 0;">Dashboard Overview</h1>
        <div class="stat-badge" style="background: #F5F1E9; padding: 8px 15px; border-radius: 30px; border: 1px solid #e0e0e0; font-weight: 600; color: #5E5343; font-size: 13px;">
            <i class="fas fa-calendar-alt" style="color: #A1937A; margin-right: 5px;"></i> <?= date('d M Y'); ?>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="stat-card">
            <div class="icon-box" style="color: #3498db; background: #ebf5fb;"><i class="fas fa-box-open"></i></div>
            <div class="stat-info">
                <h3>My Products</h3>
                <div class="number"><?= number_format($myProducts); ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon-box" style="color: #27ae60; background: #e9f7ef;"><i class="fas fa-rupee-sign"></i></div>
            <div class="stat-info">
                <h3>Total Revenue</h3>
                <div class="number">₹<?= number_format($myRevenue); ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon-box" style="color: #f39c12; background: #fef5e7;"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <h3>Pending Orders</h3>
                <div class="number"><?= number_format($myPending); ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon-box" style="color: #9b59b6; background: #f4ecf8;"><i class="fas fa-star"></i></div>
            <div class="stat-info">
                <h3>Reviews</h3>
                <div class="number"><?= number_format($myReviews); ?></div>
            </div>
        </div>
    </div>

    <div class="graphs-grid">
        <div class="chart-card">
            <h3>Monthly Sales Trend</h3>
            <div class="canvas-container"><canvas id="salesChart"></canvas></div>
        </div>
        <div class="chart-card">
            <h3>Orders Overview</h3>
            <div class="canvas-container"><canvas id="statusChart"></canvas></div>
        </div>
    </div>

    <div class="bottom-tables-grid">
        <div class="table-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 0 10px;">
                <h3 style="font-family: 'Playfair Display', serif; color: #5E5343; margin: 0; font-size: 20px;">Recent Orders</h3>
                <a href="my_orders.php" style="color: #A1937A; text-decoration: none; font-weight: 600; font-size: 14px;">View All <i class="fas fa-arrow-right"></i></a>
            </div>

            <?php if (count($recentOrders) > 0): ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th width="8%">Sr. No.</th>
                                <th>Order Info</th>
                                <th>Customer</th>
                                <th>Status & Shipment</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; foreach ($recentOrders as $row):
                                $status = !empty($row['status']) ? $row['status'] : 'Pending';
                            ?>
                                <tr>
                                    <td><strong style="color: #5E5343; font-size: 13px;"><?= $counter++; ?></strong></td>
                                    <td>
                                        <div style="font-weight: 600; color: #5E5343;">#<?= $row['order_id']; ?> - <?= htmlspecialchars($row['product_name']); ?></div>
                                        <div style="font-size: 11px; color: #999;"><?= date('d-m-Y, h:i A', strtotime($row['created_at'])); ?> • <span style="color:#A1937A; font-weight:bold;">&#8377;<?= number_format($row['price']); ?></span></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: #555;"><i class="fas fa-user" style="color:#ccc;"></i> <?= htmlspecialchars($row['username']); ?></div>
                                        <div style="font-size: 11px; color: #888;">Ph: <?= htmlspecialchars($row['phone']); ?></div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= strtolower($status); ?>"><?= ucfirst($status); ?></span>
                                        <?php if ($row['courier_name']): ?>
                                            <div style="font-size: 10px; color: #27ae60; margin-top: 4px; font-weight: 600;">
                                                <i class="fas fa-truck"></i> <?= htmlspecialchars($row['courier_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="view_orders.php?id=<?= $row['order_id']; ?>" class="btn-action-outline">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <p>No recent orders found.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="table-card inventory-alerts">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 0 10px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-exclamation-triangle" style="color: #e74c3c; font-size: 18px;"></i>
                    <h3 style="font-family: 'Playfair Display', serif; color: #5E5343; margin: 0; font-size: 20px;">Inventory Alerts</h3>
                </div>
                <a href="manage_products.php" style="color: #A1937A; text-decoration: none; font-weight: 600; font-size: 14px;">View All <i class="fas fa-arrow-right"></i></a>
            </div>

            <?php if (count($lowStockProducts) > 0): ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Product Details</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lowStockProducts as $p): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <img src="../<?= htmlspecialchars($p['image']); ?>" style="width: 45px; height: 45px; border-radius: 6px; object-fit: cover; border: 1px solid #ddd;">
                                            <div>
                                                <div style="font-weight: 600; color: #5E5343; font-size: 13px; margin-bottom: 3px;"><?= htmlspecialchars($p['name']); ?></div>
                                                <?php if ($p['stock'] == 0): ?>
                                                    <span style="color: #e74c3c; font-size: 11px; font-weight: 700;"><i class="fas fa-times-circle"></i> Out of Stock</span>
                                                <?php else: ?>
                                                    <span style="color: #f39c12; font-size: 11px; font-weight: 700;"><i class="fas fa-arrow-down"></i> Only <?= $p['stock']; ?> Left</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="edit_product.php?id=<?= $p['id']; ?>" class="btn-action-outline">
                                            <i class="fas fa-plus"></i> Add
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px 10px; color: #27ae60;">
                    <i class="fas fa-check-circle" style="font-size: 40px; margin-bottom: 15px; opacity: 0.8;"></i>
                    <p style="margin: 0; font-weight: 600; color: #5E5343;">Stock levels look great!</p>
                    <p style="font-size: 12px; color: #888; margin-top: 5px;">No products are currently running low.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</main>

<style>
    /* Design preserved from your UI files */
    .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-card { background: #F5F1E9; padding: 25px; border-radius: 12px; display: flex; align-items: center; gap: 20px; border: 1px solid #e0e0e0; transition: transform 0.3s; }
    .stat-card:hover { transform: translateY(-5px); }
    .icon-box { width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
    .stat-info .number { font-size: 24px; font-weight: 700; color: #5E5343; }

    .graphs-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
    .chart-card { background: #fff; padding: 25px; border-radius: 12px; border: 1px solid #e0e0e0; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02); }
    .canvas-container { height: 300px; }

    /* LAYOUT FOR BOTTOM TABLES */
    .bottom-tables-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 30px; }

    .table-card { background: #F5F1E9; border-radius: 12px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03); border: 1px solid #e0e0e0; padding: 20px; }
    
    .admin-table { width: 100%; border-collapse: collapse; }
    .admin-table th { text-align: left; padding: 15px; border-bottom: 2px solid #B2A48C; color: #5E5343; font-size: 11px; text-transform: uppercase; font-weight: 700; }
    .admin-table td { padding: 15px; border-bottom: 1px solid #ddd; font-size: 13px; color: #555; vertical-align: middle; }

    .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; display: inline-block; }
    .status-pending { background: #fff3cd; color: #856404; }
    .status-shipped { background: #e3f2fd; color: #0c5460; }
    .status-delivered { background: #d4edda; color: #155724; }
    .status-cancelled { background: #f8d7da; color: #721c24; }

    .btn-action-outline { display: inline-flex; align-items: center; gap: 5px; background: #fff; color: #5E5343; border: 1px solid #5E5343; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer; text-decoration: none; transition: 0.3s; }
    .btn-action-outline:hover { background: #5E5343; color: #fff; }

    @media (max-width: 1024px) {
        .graphs-grid { grid-template-columns: 1fr; }
        .bottom-tables-grid { grid-template-columns: 1fr; }
    }
</style>

<script>
    // Chart logic
    const labels = <?= json_encode($months); ?>;
    const salesData = <?= json_encode($salesDataArr); ?>;
    const statusData = [<?= $statusCounts['Pending']; ?>, <?= $statusCounts['Shipped']; ?>, <?= $statusCounts['Delivered']; ?>, <?= $statusCounts['Cancelled']; ?>];

    new Chart(document.getElementById('salesChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue (₹)',
                data: salesData,
                borderColor: '#A1937A',
                backgroundColor: 'rgba(161, 147, 122, 0.1)',
                borderWidth: 3,
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }
        }
    });

    new Chart(document.getElementById('statusChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Shipped', 'Delivered', 'Cancelled'],
            datasets: [{
                data: statusData,
                backgroundColor: ['#f39c12', '#3498db', '#27ae60', '#c0392b'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: { legend: { position: 'bottom' } }
        }
    });
</script>

<?php include 'include/footer.php'; ?>