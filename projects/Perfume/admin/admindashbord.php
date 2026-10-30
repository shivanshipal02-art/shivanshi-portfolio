<?php
include '../include/connection.php';
// Header includes session check, CSS, and Sidebar
include 'include/header.php';

// --- 1. BASIC COUNTS ---
$totalProducts = 0;
$totalUsers = 0;
$totalSales = 0;
$totalSellers = 0;
$pendingOrders = 0;

$res = $conn->query("SELECT COUNT(*) as total FROM product");
if ($res) $totalProducts = $res->fetch_assoc()['total'];

// FIX: 'users' table is now exclusively for customers, no role check needed
$res = $conn->query("SELECT COUNT(*) as total FROM users");
if ($res) $totalUsers = $res->fetch_assoc()['total'];

// FIX: Target the 'sellers' table
$res = $conn->query("SELECT COUNT(*) as total FROM sellers");
if ($res) $totalSellers = $res->fetch_assoc()['total'];

$res = $conn->query("SELECT SUM(price) as total FROM orders WHERE status != 'Cancelled'");
if ($res) $totalSales = $res->fetch_assoc()['total'] ?? 0;

$res = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status='Pending'");
if ($res) $pendingOrders = $res->fetch_assoc()['total'];

// --- 2. DATA FOR GRAPHS ---
$sellerStatusCounts = ['approved' => 0, 'suspended' => 0, 'pending' => 0];
// FIX: Target 'sellers' table
$res = $conn->query("SELECT status, COUNT(*) as count FROM sellers GROUP BY status");
while ($row = $res->fetch_assoc()) {
    $st = strtolower($row['status']);
    if (isset($sellerStatusCounts[$st])) {
        $sellerStatusCounts[$st] = $row['count'];
    } else {
        $sellerStatusCounts[$st] = $row['count'];
    }
}

// Fetch last 12 months to cover 2025 to current date nicely in the chart
$months = [];
$salesData = [];
for ($i = 11; $i >= 0; $i--) {
    $date = date("Y-m", strtotime("-$i months"));
    $monthName = date("M Y", strtotime("-$i months")); // e.g., Jan 2025
    $sql = "SELECT SUM(price) as total FROM orders WHERE DATE_FORMAT(created_at, '%Y-%m') = '$date' AND status != 'Cancelled'";
    $res = $conn->query($sql);
    $row = $res->fetch_assoc();
    $months[] = $monthName;
    $salesData[] = $row['total'] ?? 0;
}

// --- 3. RECENTLY ADDED PRODUCTS (COLUMN 1) ---
$recentProducts = [];
// FIX: JOIN 'sellers' instead of 'users', and fetch 'owner_name'
$sql_prod = "SELECT p.id, p.name, p.price, p.image, p.created_at, p.seller_id, s.business_name, s.owner_name as seller_name 
             FROM product p 
             LEFT JOIN sellers s ON p.seller_id = s.id 
             ORDER BY p.id DESC LIMIT 5";
$res_prod = $conn->query($sql_prod);
if ($res_prod) {
    while ($row = $res_prod->fetch_assoc()) {
        $recentProducts[] = $row;
    }
}

// --- 4. RECENT CROSS-PLATFORM ORDERS (COLUMN 2) ---
$recentOrders = [];
// FIX: LEFT JOIN 'sellers' instead of 'users', and fetch 'owner_name'
$sql_ord = "SELECT o.id, o.price, o.status, o.created_at, 
                   p.name as product_name, p.image, p.seller_id,
                   c.username as customer_name, 
                   s.business_name, s.owner_name as seller_name 
            FROM orders o 
            JOIN product p ON o.product_id = p.id 
            JOIN users c ON o.user_id = c.id 
            LEFT JOIN sellers s ON p.seller_id = s.id 
            ORDER BY o.created_at DESC LIMIT 5";
$res_ord = $conn->query($sql_ord);
if ($res_ord) {
    while ($row = $res_ord->fetch_assoc()) {
        $recentOrders[] = $row;
    }
}

// --- 5. RECENTLY JOINED CUSTOMERS (NEW COLUMN 3) ---
$recentCustomers = [];
// FIX: Query 'users' directly without checking for role
$sql_cust = "SELECT id, username, email, phone, created_at, status FROM users ORDER BY id DESC LIMIT 5";
$res_cust = $conn->query($sql_cust);
if ($res_cust) {
    while ($row = $res_cust->fetch_assoc()) {
        $recentCustomers[] = $row;
    }
}

// --- 6. RECENTLY JOINED SELLERS (NEW COLUMN 4) ---
$recentNewSellers = [];
// FIX: Query 'sellers' table and map 'owner_name' to 'username' for the UI
$sql_nsell = "SELECT id, owner_name as username, business_name, email, created_at, status FROM sellers ORDER BY id DESC LIMIT 5";
$res_nsell = $conn->query($sql_nsell);
if ($res_nsell) {
    while ($row = $res_nsell->fetch_assoc()) {
        $recentNewSellers[] = $row;
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<main style="flex: 1; padding: 25px; background-color: #fff; overflow-y: auto;">

    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin: 0; font-size: 24px;">Admin Dashboard</h1>
            <p style="color: #999; font-size: 13px; margin: 0;">Platform Overview & Activity Tracking</p>
        </div>
        <div class="date-badge">
            <i class="fas fa-calendar-alt"></i> <?= date("d M Y"); ?>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="stat-card">
            <div class="icon-wrap" style="color: #3498db;"><i class="fas fa-users"></i></div>
            <div class="stat-det">
                <h3>Total Customers</h3>
                <div class="num"><?= $totalUsers; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon-wrap" style="color: #9b59b6;"><i class="fas fa-user-tie"></i></div>
            <div class="stat-det">
                <h3>Total Sellers</h3>
                <div class="num"><?= $totalSellers; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon-wrap" style="color: #27ae60;"><i class="fas fa-boxes"></i></div>
            <div class="stat-det">
                <h3>Total Products</h3>
                <div class="num"><?= $totalProducts; ?></div>
            </div>
        </div>
        <div class="stat-card alert-card">
            <div class="icon-wrap"><i class="fas fa-rupee-sign"></i></div>
            <div class="stat-det">
                <h3>Platform Revenue</h3>
                <div class="num">&#8377;<?= number_format($totalSales); ?></div>
            </div>
        </div>
    </div>

    <div class="charts-row">
        <div class="chart-container sales-chart">
            <h3>Platform Revenue (Last 12 Months)</h3>
            <div class="chart-wrapper"><canvas id="salesChart"></canvas></div>
        </div>
        <div class="chart-container status-chart">
            <h3>Seller Account Status</h3>
            <div class="chart-wrapper"><canvas id="statusChart"></canvas></div>
        </div>
    </div>

    <div class="dual-columns">

        <div class="data-card">
            <div class="card-header">
                <h3>Recently Added Products</h3>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th width="8%">Sr. No.</th>
                            <th>Product Details</th>
                            <th>Price</th>
                            <th>Seller Info</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recentProducts) > 0): ?>
                            <?php $p_count = 1;
                            foreach ($recentProducts as $prod): $seller_display = !empty($prod['business_name']) ? $prod['business_name'] : $prod['seller_name']; ?>
                                <tr>
                                    <td><strong style="color: #5E5343; font-size: 13px;"><?= $p_count++; ?></strong></td>
                                    <td>
                                        <div class="flex-item">
                                            <?php if (!empty($prod['image'])): ?><img src="../<?= htmlspecialchars($prod['image']); ?>" alt="img"><?php else: ?><div class="no-img"><i class="fas fa-image"></i></div><?php endif; ?>
                                            <div>
                                                <div style="font-weight: 600; color: #5E5343;"><?= htmlspecialchars($prod['name']); ?></div>
                                                <div style="font-size: 11px; color: #999;"><?= date('d-m-Y, h:i A', strtotime($prod['created_at'])); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-weight: 600; color: #A1937A;">&#8377;<?= number_format($prod['price']); ?></td>
                                    <td style="font-size: 11px; color: #555;">
                                        <i class="fas fa-store" style="color:#B2A48C;"></i> <?= htmlspecialchars($seller_display ?? 'Admin'); ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="view_product.php?id=<?= $prod['id']; ?>" class="btn-view-action" title="View Product">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="empty-text">No products found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="data-card">
            <div class="card-header">
                <h3>Recent Platform Orders</h3>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th width="8%">Sr. No.</th>
                            <th>Order Info</th>
                            <th>Parties Involved</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recentOrders) > 0): ?>
                            <?php $o_count = 1;
                            foreach ($recentOrders as $ord): $seller_display = !empty($ord['business_name']) ? $ord['business_name'] : $ord['seller_name']; ?>
                                <tr>
                                    <td><strong style="color: #5E5343; font-size: 13px;"><?= $o_count++; ?></strong></td>
                                    <td>
                                        <div style="font-weight: 600; color: #5E5343;">#<?= $ord['id']; ?> - <?= htmlspecialchars(mb_strimwidth($ord['product_name'], 0, 20, "...")); ?></div>
                                        <div style="font-size: 11px; color: #999;"><?= date('d-m-Y, h:i A', strtotime($ord['created_at'])); ?> • <span style="color:#A1937A; font-weight:bold;">&#8377;<?= number_format($ord['price']); ?></span></div>
                                    </td>
                                    <td>
                                        <div style="font-size: 11px; color: #555; line-height: 1.6;">
                                            <strong>Buyer:</strong> <i class="fas fa-user" style="color:#ccc;"></i> <?= htmlspecialchars($ord['customer_name']); ?><br>
                                            <strong>Seller:</strong> <i class="fas fa-store" style="color:#ccc;"></i> <?= htmlspecialchars($seller_display ?? 'Admin'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $st = strtolower($ord['status']);
                                        $cls = ($st == 'delivered' || $st == 'completed') ? 'success' : (($st == 'cancelled') ? 'danger' : (($st == 'shipped') ? 'info' : 'warning'));
                                        ?>
                                        <span class="badge badge-<?= $cls; ?>"><?= ucfirst($st); ?></span>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="view_orders.php?id=<?= $ord['id']; ?>" class="btn-view-action" title="View Order Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="empty-text">No orders placed yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div class="dual-columns" style="margin-top: 20px;">

        <div class="data-card">
            <div class="card-header">
                <h3>Recently Joined Customers</h3>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th width="8%">Sr. No.</th>
                            <th>User Info</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recentCustomers) > 0): ?>
                            <?php $c_count = 1;
                            foreach ($recentCustomers as $cust): ?>
                                <tr>
                                    <td><strong style="color: #5E5343; font-size: 13px;"><?= $c_count++; ?></strong></td>
                                    <td>
                                        <div style="font-weight: 600; color: #5E5343;"><?= htmlspecialchars($cust['username']); ?></div>
                                        <div style="font-size: 11px; color: #999;">Joined: <?= date('d-m-Y, h:i A', strtotime($cust['created_at'])); ?></div>
                                    </td>
                                    <td style="font-size: 11px; color: #555;">
                                        <?= htmlspecialchars($cust['email']); ?><br>
                                        <i class="fas fa-phone-alt" style="color:#ccc;"></i> <?= htmlspecialchars($cust['phone'] ?? 'N/A'); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $c_st = strtolower($cust['status']);
                                        $c_cls = ($c_st == 'approved' || $c_st == 'active') ? 'success' : 'danger';
                                        ?>
                                        <span class="badge badge-<?= $c_cls; ?>"><?= ucfirst($cust['status']); ?></span>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="view_users.php?id=<?= $cust['id']; ?>" class="btn-view-action" title="View Customer">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="empty-text">No recent customers found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="data-card">
            <div class="card-header">
                <h3>Recently Joined Sellers</h3>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th width="8%">Sr. No.</th>
                            <th>Business Info</th>
                            <th>Owner Info</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recentNewSellers) > 0): ?>
                            <?php $s_count = 1;
                            foreach ($recentNewSellers as $sell): ?>
                                <tr>
                                    <td><strong style="color: #5E5343; font-size: 13px;"><?= $s_count++; ?></strong></td>
                                    <td>
                                        <div style="font-weight: 600; color: #5E5343;"><?= htmlspecialchars($sell['business_name'] ?? 'Not Set'); ?></div>
                                        <div style="font-size: 11px; color: #999;">Joined: <?= date('d-m-Y, h:i A', strtotime($sell['created_at'])); ?></div>
                                    </td>
                                    <td style="font-size: 11px; color: #555;">
                                        <strong><?= htmlspecialchars($sell['username']); ?></strong><br>
                                        <?= htmlspecialchars($sell['email']); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $s_st = strtolower($sell['status']);
                                        $s_cls = ($s_st == 'approved') ? 'success' : (($s_st == 'pending') ? 'warning' : 'danger');
                                        ?>
                                        <span class="badge badge-<?= $s_cls; ?>"><?= ucfirst($sell['status']); ?></span>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="view_seller.php?id=<?= $sell['id']; ?>" class="btn-view-action" title="View Seller Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="empty-text">No recent sellers found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</main>

<style>
    /* Exact UI Styling Preserved */
    .date-badge {
        background: #F5F1E9;
        color: #5E5343;
        padding: 6px 15px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 600;
        border: 1px solid #e0e0e0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
        margin-bottom: 20px;
    }

    .stat-card {
        background: #F5F1E9;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        display: flex;
        align-items: center;
        gap: 15px;
        transition: transform 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        border-color: #B2A48C;
    }

    .alert-card {
        border-right: 4px solid #A1937A;
    }

    .icon-wrap {
        width: 40px;
        height: 40px;
        background: #fff;
        color: #5E5343;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        border: 1px solid #ccc;
        flex-shrink: 0;
    }

    .stat-det h3 {
        font-size: 11px;
        color: #777;
        margin: 0 0 3px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-det .num {
        font-size: 20px;
        font-weight: 700;
        color: #5E5343;
        line-height: 1;
    }

    .charts-row {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
    }

    .sales-chart {
        flex: 2;
    }

    .status-chart {
        flex: 1;
    }

    .chart-container {
        background: #F5F1E9;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        height: 100%;
    }

    .chart-container h3 {
        font-size: 14px;
        margin: 0 0 15px 0;
        color: #5E5343;
        border-bottom: 1px solid #ddd;
        padding-bottom: 5px;
    }

    .chart-wrapper {
        position: relative;
        height: 200px;
        width: 100%;
    }

    .dual-columns {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .data-card {
        background: #F5F1E9;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        border-bottom: 1px solid #ddd;
        padding-bottom: 8px;
    }

    .card-header h3 {
        font-size: 15px;
        margin: 0;
        color: #5E5343;
        font-weight: 700;
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
    }

    .admin-table th {
        text-align: left;
        padding: 10px;
        background: rgba(0, 0, 0, 0.02);
        color: #5E5343;
        font-size: 11px;
        border-bottom: 1px solid #ccc;
        text-transform: uppercase;
    }

    .admin-table td {
        padding: 12px 10px;
        border-bottom: 1px solid #e0e0e0;
        color: #444;
        font-size: 13px;
        vertical-align: middle;
    }

    .flex-item {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .flex-item img {
        width: 35px;
        height: 35px;
        border-radius: 4px;
        object-fit: cover;
        border: 1px solid #ccc;
    }

    .no-img {
        width: 35px;
        height: 35px;
        border-radius: 4px;
        background: #eee;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #999;
        font-size: 12px;
    }

    .empty-text {
        text-align: center;
        padding: 30px;
        color: #999;
        font-style: italic;
    }

    .badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        display: inline-block;
    }

    .badge-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .badge-warning {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeeba;
    }

    .badge-danger {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .badge-info {
        background: #d1ecf1;
        color: #0c5460;
        border: 1px solid #bee5eb;
    }

    .btn-view-action {
        background: #fff;
        color: #5E5343;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        border: 1px solid #B2A48C;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: 0.3s;
    }

    .btn-view-action:hover {
        background: #5E5343;
        color: #fff;
        border-color: #5E5343;
    }

    @media (max-width: 1100px) {
        .dual-columns {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 992px) {
        .dashboard-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .charts-row {
            flex-direction: column;
        }

        .chart-wrapper {
            height: 250px;
        }
    }

    @media (max-width: 500px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    const ctxSales = document.getElementById('salesChart').getContext('2d');
    new Chart(ctxSales, {
        type: 'line',
        data: {
            labels: <?= json_encode($months); ?>,
            datasets: [{
                label: 'Platform Revenue (₹)',
                data: <?= json_encode($salesData); ?>,
                borderColor: '#5E5343',
                backgroundColor: 'rgba(94, 83, 67, 0.1)',
                borderWidth: 2,
                pointBackgroundColor: '#A1937A',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        font: {
                            size: 10
                        }
                    },
                    grid: {
                        color: '#e0e0e0'
                    }
                },
                x: {
                    ticks: {
                        font: {
                            size: 10
                        }
                    },
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });

    const ctxStatus = document.getElementById('statusChart').getContext('2d');
    new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: ['Approved', 'Suspended', 'Pending'],
            datasets: [{
                data: [<?= $sellerStatusCounts['approved']; ?>, <?= $sellerStatusCounts['suspended']; ?>, <?= $sellerStatusCounts['pending']; ?>],
                backgroundColor: ['#27ae60', '#c0392b', '#f39c12'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 10,
                        font: {
                            size: 10
                        },
                        color: '#5E5343'
                    }
                }
            }
        }
    });
</script>

<?php include 'include/footer.php'; ?>