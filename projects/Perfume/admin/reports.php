<?php
session_start();

// Security Check: Ensure only admin can access this page
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

include '../include/connection.php';
include 'include/header.php';

// Determine View Mode: List Sellers OR Show Specific Seller Report
$seller_id = isset($_GET['seller_id']) ? intval($_GET['seller_id']) : 0;
$view_mode = ($seller_id > 0) ? 'detail' : 'list';

$sellerInfo = null;
$sales = ['total_sales' => 0, 'total_orders' => 0];
$topProducts = [];
$recentOrders = [];

if ($view_mode === 'detail') {
    // --- FETCH SPECIFIC SELLER DATA (FIXED: Querying sellers table) ---
    $stmt = $conn->prepare("SELECT id, owner_name as username, business_name, email, phone, city, gst_number, pan_card FROM sellers WHERE id = ?");
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $sellerInfo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$sellerInfo) {
        echo "<script>window.location.href='reports.php';</script>";
        exit;
    }

    // Fetch Seller Sales
    $stmtSum = $conn->prepare("SELECT SUM(o.price) as total_sales, COUNT(o.id) as total_orders FROM orders o JOIN product p ON o.product_id = p.id WHERE p.seller_id = ? AND o.status != 'Cancelled'");
    $stmtSum->bind_param("i", $seller_id);
    $stmtSum->execute();
    $resSum = $stmtSum->get_result();
    if ($resSum) $sales = $resSum->fetch_assoc();
    $stmtSum->close();

    // Fetch Top Products for this Seller
    $stmtTop = $conn->prepare("SELECT p.name, p.image, p.price, COUNT(o.id) as sold_count, SUM(o.price) as revenue FROM orders o JOIN product p ON o.product_id = p.id WHERE p.seller_id = ? AND o.status != 'Cancelled' GROUP BY o.product_id ORDER BY sold_count DESC LIMIT 5");
    $stmtTop->bind_param("i", $seller_id);
    $stmtTop->execute();
    $resTop = $stmtTop->get_result();
    if ($resTop) {
        while ($row = $resTop->fetch_assoc()) $topProducts[] = $row;
    }
    $stmtTop->close();

    // Fetch Recent Transactions for this Seller
    $stmtRec = $conn->prepare("SELECT o.id, o.created_at, o.price, o.status, p.name as product_name, pay.payment_method FROM orders o JOIN product p ON o.product_id = p.id LEFT JOIN payment pay ON o.id = pay.order_id WHERE p.seller_id = ? ORDER BY o.created_at DESC LIMIT 5");
    $stmtRec->bind_param("i", $seller_id);
    $stmtRec->execute();
    $resRecent = $stmtRec->get_result();
    if ($resRecent) {
        while ($row = $resRecent->fetch_assoc()) $recentOrders[] = $row;
    }
    $stmtRec->close();
} else {
    // --- FETCH ALL APPROVED SELLERS FOR LIST VIEW (FIXED: Querying sellers table) ---
    $sellersList = [];
    $resSellers = $conn->query("SELECT id, owner_name as username, business_name, city, gst_number, pan_card FROM sellers WHERE status = 'approved' ORDER BY id DESC");
    if ($resSellers) {
        while ($row = $resSellers->fetch_assoc()) $sellersList[] = $row;
    }
}
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">

    <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin-bottom: 5px; font-size: 26px;">
                <?= ($view_mode === 'list') ? 'Select a Seller' : 'Seller Performance Report' ?>
            </h1>
            <p style="color: #999; font-size: 13px;">
                <?= ($view_mode === 'list') ? 'Select an approved business to view its detailed sales report and download PDF.' : 'Viewing report for <strong>' . htmlspecialchars($sellerInfo['business_name'] ?? $sellerInfo['username']) . '</strong>'; ?>
            </p>
        </div>

        <div style="display: flex; gap: 15px; align-items: center;">
            <?php if ($view_mode === 'detail'): ?>
                <a href="reports.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Sellers</a>
                <button onclick="downloadReport()" id="downloadBtn" class="btn-print">
                    <i class="fas fa-file-pdf"></i> Download PDF Report
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($view_mode === 'list'): ?>
        <div class="seller-list-card">
            <table class="seller-list-table">
                <thead>
                    <tr>
                        <th width="8%">Sr. No.</th>
                        <th width="35%">BUSINESS DETAILS</th>
                        <th width="25%">TAX/ID INFO</th>
                        <th width="15%">LOCATION</th>
                        <th style="text-align: right;">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($sellersList) > 0): ?>
                        <?php $counter = 1;
                        foreach ($sellersList as $seller): ?>
                            <tr>
                                <td>
                                    <div class="sr-box"><?= $counter++; ?></div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <div class="business-icon-box"><i class="fas fa-briefcase"></i></div>
                                        <div>
                                            <div style="font-weight: 700; color: #5E5343; font-size: 14px;">
                                                <?= htmlspecialchars($seller['business_name'] ?? 'Not Set'); ?>
                                            </div>
                                            <div style="font-size: 11px; color: #888; margin-top: 3px;">
                                                Owner: <?= htmlspecialchars($seller['username']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-size: 11px; color: #666; line-height: 1.6;">
                                    GST: <?= htmlspecialchars($seller['gst_number'] ?: 'N/A'); ?><br>
                                    PAN: <?= htmlspecialchars($seller['pan_card'] ?: 'N/A'); ?>
                                </td>
                                <td style="font-size: 12px; color: #666;">
                                    <i class="fas fa-map-marker-alt" style="color: #A1937A;"></i> <?= htmlspecialchars($seller['city'] ?: 'N/A'); ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="reports.php?seller_id=<?= $seller['id']; ?>" class="action-btn">
                                        View Report <i class="fas fa-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: #999;">No approved sellers found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>
        <div style="background: #F9F9F9; border: 1px solid #EAE3D9; padding: 20px; border-radius: 8px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="margin: 0 0 5px 0; color: #5E5343; font-size: 18px;"><i class="fas fa-store"></i> <?= htmlspecialchars($sellerInfo['business_name'] ?? $sellerInfo['username']); ?></h2>
                <p style="margin: 0; font-size: 12px; color: #777;">Owner: <?= htmlspecialchars($sellerInfo['username']); ?> | Email: <?= htmlspecialchars($sellerInfo['email']); ?></p>
            </div>
            <div style="text-align: right; font-size: 12px; color: #555;">
                <p style="margin: 0 0 5px 0;"><strong>GST:</strong> <?= htmlspecialchars($sellerInfo['gst_number'] ?: 'N/A'); ?></p>
                <p style="margin: 0;"><strong>PAN:</strong> <?= htmlspecialchars($sellerInfo['pan_card'] ?: 'N/A'); ?></p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon-circle" style="color: #A1937A;"><i class="fas fa-rupee-sign"></i></div>
                <div>
                    <h3>Total Profit/Revenue</h3>
                    <div class="stat-value">&#8377;<?= number_format($sales['total_sales'] ?? 0, 2); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon-circle" style="color: #5E5343;"><i class="fas fa-shopping-basket"></i></div>
                <div>
                    <h3>Total Orders Processed</h3>
                    <div class="stat-value"><?= number_format($sales['total_orders'] ?? 0); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon-circle" style="color: #B2A48C;"><i class="fas fa-chart-line"></i></div>
                <div>
                    <h3>Avg. Order Value</h3>
                    <div class="stat-value">&#8377;<?= ($sales['total_orders'] > 0) ? number_format($sales['total_sales'] / $sales['total_orders'], 2) : '0.00'; ?></div>
                </div>
            </div>
        </div>

        <div class="table-card">
            <h2 class="section-title"><i class="fas fa-box-open" style="color: #A1937A;"></i> Top Selling Products for this Seller</h2>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th width="10%">Sr. No.</th>
                            <th>Product</th>
                            <th>Sold</th>
                            <th>Generated Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($topProducts) > 0): ?>
                            <?php $rank = 1;
                            foreach ($topProducts as $product): ?>
                                <tr>
                                    <td><span class="rank-badge rank-<?= $rank; ?>">#<?= $rank; ?></span></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <?php if (!empty($product['image'])): ?>
                                                <img src="../<?= htmlspecialchars($product['image']); ?>" class="product-thumb">
                                            <?php endif; ?>
                                            <span style="font-weight: 600; color: #5E5343; font-size:13px;"><?= htmlspecialchars($product['name']); ?></span>
                                        </div>
                                    </td>
                                    <td style="font-weight: 700; color: #A1937A;"><?= $product['sold_count']; ?></td>
                                    <td style="font-weight: 600;">&#8377;<?= number_format($product['revenue'], 2); ?></td>
                                </tr>
                            <?php $rank++;
                            endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 40px; color: #999;">No product data available for this seller.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="table-card" style="margin-top: 25px;">
            <h2 class="section-title"><i class="fas fa-receipt" style="color: #A1937A;"></i> Recent Transactions</h2>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Sr. No.</th>
                            <th>Date & Time</th>
                            <th>Order ID</th>
                            <th>Product</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th style="text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recentOrders) > 0): ?>
                            <?php $ro_count = 1;
                            foreach ($recentOrders as $ro):
                                $st = strtolower($ro['status']);
                                $cls = ($st == 'delivered' || $st == 'completed') ? 'success' : (($st == 'cancelled') ? 'danger' : (($st == 'shipped') ? 'info' : 'warning'));
                            ?>
                                <tr>
                                    <td><strong style="color: #5E5343; font-size: 13px;"><?= $ro_count++; ?></strong></td>
                                    <td style="font-size: 12px; color: #555; font-weight: 500;"><?= date('d-m-Y, h:i A', strtotime($ro['created_at'])) ?></td>
                                    <td><span style="font-weight: bold; color: #5E5343;">#<?= $ro['id'] ?></span></td>
                                    <td><?= htmlspecialchars(mb_strimwidth($ro['product_name'], 0, 30, "...")) ?></td>
                                    <td><span style="background: #f0f0f0; padding: 2px 8px; border-radius: 4px; font-size: 11px;"><?= $ro['payment_method'] ?? 'Online' ?></span></td>
                                    <td><span class="status-badge status-<?= $cls; ?>"><?= ucfirst($ro['status']); ?></span></td>
                                    <td style="text-align: right; font-weight: bold; color: #A1937A;">&#8377;<?= number_format($ro['price'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #999;">No recent transactions available for this seller.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

</main>

<style>
    /* === SELLER LIST CSS === */
    .seller-list-card {
        background-color: #F8F5F0;
        border-radius: 12px;
        border: 1px solid #EAE3D9;
        padding: 20px 30px;
    }

    .seller-list-table {
        width: 100%;
        border-collapse: collapse;
    }

    .seller-list-table th {
        text-align: left;
        padding: 15px 10px;
        color: #7A6A56;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #D6CEBE;
    }

    .seller-list-table td {
        padding: 20px 10px;
        border-bottom: 1px solid #EAE3D9;
        vertical-align: middle;
    }

    .seller-list-table tr:last-child td {
        border-bottom: none;
    }

    .sr-box {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border: 1px solid #ccc;
        background: #fff;
        border-radius: 4px;
        font-size: 12px;
        color: #555;
        font-weight: 600;
    }

    .business-icon-box {
        background: #fff;
        border: 1px solid #D6CEBE;
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #5E5343;
        font-size: 16px;
    }

    .action-btn {
        background-color: #5E5343;
        color: #fff;
        padding: 10px 20px;
        border-radius: 4px;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: 0.3s;
    }

    .action-btn:hover {
        background-color: #A1937A;
    }

    /* === DETAIL VIEW CSS === */
    .btn-back {
        text-decoration: none;
        color: #5E5343;
        font-weight: 600;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-print {
        background: transparent;
        border: 1px solid #5E5343;
        color: #5E5343;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: 0.3s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-print:hover {
        background: #5E5343;
        color: #fff;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 25px;
        margin-bottom: 25px;
    }

    .stat-card {
        background: #F5F1E9;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03);
        border: 1px solid #e0e0e0;
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .icon-circle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        background: #fff;
        border: 1px solid #ccc;
    }

    .stat-card h3 {
        font-size: 12px;
        color: #777;
        text-transform: uppercase;
        margin-bottom: 5px;
        letter-spacing: 0.5px;
    }

    .stat-value {
        font-size: 24px;
        font-weight: 700;
        color: #5E5343;
    }

    .table-card {
        background: #F5F1E9;
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03);
        border: 1px solid #e0e0e0;
        padding: 25px;
    }

    .section-title {
        font-family: 'Playfair Display', serif;
        font-size: 18px;
        margin-bottom: 20px;
        color: #5E5343;
        border-bottom: 1px solid #B2A48C;
        padding-bottom: 15px;
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
    }

    .admin-table th {
        text-align: left;
        padding: 12px;
        color: #5E5343;
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 600;
        border-bottom: 2px solid #B2A48C;
    }

    .admin-table td {
        padding: 12px;
        border-bottom: 1px solid #ddd;
        vertical-align: middle;
        color: #555;
        font-size: 13px;
    }

    .product-thumb {
        width: 40px;
        height: 40px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #ccc;
    }

    .rank-badge {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 11px;
        background: #fff;
        border: 1px solid #ccc;
    }

    .rank-1 {
        background: #A1937A;
        color: #fff;
        border-color: #A1937A;
    }

    .rank-2 {
        background: #C0C0C0;
        color: #fff;
        border-color: #999;
    }

    .rank-3 {
        background: #CD7F32;
        color: #fff;
        border-color: #A0522D;
    }

    .status-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        display: inline-block;
    }

    .status-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .status-warning {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeeba;
    }

    .status-danger {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .status-info {
        background: #d1ecf1;
        color: #0c5460;
        border: 1px solid #bee5eb;
    }
</style>

<script>
    async function downloadReport() {
        const btn = document.getElementById('downloadBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        btn.disabled = true;

        const sellerId = "<?= $seller_id ?>";

        try {
            const response = await fetch('print_report.php?seller_id=' + sellerId);
            const htmlContent = await response.text();

            const opt = {
                margin: 0.4,
                filename: 'Seller_Report_' + sellerId + '_<?= date("Y-m-d") ?>.pdf',
                image: {
                    type: 'jpeg',
                    quality: 0.98
                },
                html2canvas: {
                    scale: 2,
                    useCORS: true
                },
                jsPDF: {
                    unit: 'in',
                    format: 'a4',
                    orientation: 'portrait'
                }
            };

            html2pdf().set(opt).from(htmlContent).save().then(() => {
                btn.innerHTML = '<i class="fas fa-file-pdf"></i> Download PDF Report';
                btn.disabled = false;
            });
        } catch (e) {
            console.error(e);
            btn.innerHTML = 'Error!';
            btn.disabled = false;
        }
    }
</script>

<?php include 'include/footer.php'; ?>