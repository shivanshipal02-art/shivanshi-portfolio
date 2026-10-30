<?php
session_start();
include '../include/connection.php';

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    exit('Unauthorized');
}

// Ensure a seller ID is passed
if (!isset($_GET['seller_id']) || empty($_GET['seller_id']) || $_GET['seller_id'] === 'all') {
    exit('<p style="padding:20px; font-family:sans-serif; color:red;">Error: Report requires a specific seller selection.</p>');
}

$seller_id = intval($_GET['seller_id']);

// 1. Fetch Seller Information (FIXED: Querying 'sellers' table and aliasing owner_name)
$s_stmt = $conn->prepare("SELECT owner_name as username, business_name, email, phone, gst_number, pan_card, city FROM sellers WHERE id = ?");
$s_stmt->bind_param("i", $seller_id);
$s_stmt->execute();
$seller_info = $s_stmt->get_result()->fetch_assoc();
$s_stmt->close();

if (!$seller_info) {
    exit('<p style="padding:20px; font-family:sans-serif; color:red;">Seller not found.</p>');
}

// 2. Fetch Seller Sales Summary
$sales = ['total_sales' => 0, 'total_orders' => 0];
$stmtSum = $conn->prepare("SELECT SUM(o.price) as total_sales, COUNT(o.id) as total_orders FROM orders o JOIN product p ON o.product_id = p.id WHERE p.seller_id = ? AND o.status != 'Cancelled'");
$stmtSum->bind_param("i", $seller_id);
$stmtSum->execute();
$resSum = $stmtSum->get_result();
if ($resSum) $sales = $resSum->fetch_assoc();
$stmtSum->close();

// 3. Fetch Seller Status Breakdown
$statusCounts = ['Pending' => 0, 'Shipped' => 0, 'Delivered' => 0, 'Cancelled' => 0];
$stmtStatus = $conn->prepare("SELECT o.status, COUNT(o.id) as count FROM orders o JOIN product p ON o.product_id = p.id WHERE p.seller_id = ? GROUP BY o.status");
$stmtStatus->bind_param("i", $seller_id);
$stmtStatus->execute();
$resStatus = $stmtStatus->get_result();
if ($resStatus) {
    while ($row = $resStatus->fetch_assoc()) $statusCounts[$row['status']] = $row['count'];
}
$stmtStatus->close();

// 4. Fetch Top 5 Products for this Seller
$topProducts = [];
$stmtTop = $conn->prepare("SELECT p.name, p.price, COUNT(o.id) as sold_count, SUM(o.price) as revenue FROM orders o JOIN product p ON o.product_id = p.id WHERE p.seller_id = ? AND o.status != 'Cancelled' GROUP BY o.product_id ORDER BY sold_count DESC LIMIT 5");
$stmtTop->bind_param("i", $seller_id);
$stmtTop->execute();
$resTop = $stmtTop->get_result();
if ($resTop) {
    while ($row = $resTop->fetch_assoc()) $topProducts[] = $row;
}
$stmtTop->close();

// 5. Fetch Recent Transactions for this Seller
$recentOrders = [];
$stmtRec = $conn->prepare("SELECT o.id, o.created_at, o.price, o.status, p.name as product_name, pay.payment_method FROM orders o JOIN product p ON o.product_id = p.id LEFT JOIN payment pay ON o.id = pay.order_id WHERE p.seller_id = ? ORDER BY o.created_at DESC LIMIT 5");
$stmtRec->bind_param("i", $seller_id);
$stmtRec->execute();
$resRecent = $stmtRec->get_result();
if ($resRecent) {
    while ($row = $resRecent->fetch_assoc()) $recentOrders[] = $row;
}
$stmtRec->close();
?>

<div style="padding: 30px; font-family: 'Segoe UI', Arial, sans-serif; color: #333; background: #fff;">

    <table style="width: 100%; border-bottom: 3px solid #5E5343; padding-bottom: 15px; margin-bottom: 25px;">
        <tr>
            <td style="width: 60%;">
                <h1 style="margin: 0; color: #5E5343; font-size: 28px;">PERFUME NEST</h1>
                <p style="margin: 5px 0; font-size: 12px; color: #888;">Certified Seller Performance Report</p>
            </td>
            <td style="width: 40%; text-align: right; vertical-align: top;">
                <h2 style="margin: 0; color: #A1937A; font-size: 16px;">SELLER REPORT</h2>
                <p style="margin: 5px 0; font-size: 11px;">Generated on: <?= date('d-m-Y, h:i A') ?></p>
            </td>
        </tr>
    </table>

    <div style="background: #F8F5F0; padding: 20px; border-radius: 8px; border-left: 5px solid #5E5343; margin-bottom: 25px;">
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <h3 style="margin: 0 0 8px 0; color: #5E5343; font-size: 16px;"><?= htmlspecialchars($seller_info['business_name'] ?? $seller_info['username']) ?></h3>
                    <p style="margin: 3px 0; font-size: 12px;"><strong>Owner:</strong> <?= htmlspecialchars($seller_info['username']) ?></p>
                    <p style="margin: 3px 0; font-size: 12px;"><strong>Location:</strong> <?= htmlspecialchars($seller_info['city'] ?: 'N/A') ?></p>
                </td>
                <td style="width: 50%; vertical-align: top; text-align: right;">
                    <p style="margin: 0 0 5px 0; font-size: 12px;"><strong>Contact:</strong> <?= htmlspecialchars($seller_info['phone'] ?: 'N/A') ?></p>
                    <p style="margin: 0 0 5px 0; font-size: 12px;"><strong>Email:</strong> <?= htmlspecialchars($seller_info['email']) ?></p>
                    <p style="margin: 0 0 5px 0; font-size: 12px; color: #777;"><strong>GST No:</strong> <?= htmlspecialchars($seller_info['gst_number'] ?: 'Not Provided') ?></p>
                    <p style="margin: 0; font-size: 12px; color: #777;"><strong>PAN:</strong> <?= htmlspecialchars($seller_info['pan_card'] ?: 'Not Provided') ?></p>
                </td>
            </tr>
        </table>
    </div>

    <table style="width: 100%; margin-bottom: 25px; font-size: 12px;">
        <tr>
            <td style="width: 48%; vertical-align: top; background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #ccc;">
                <h4 style="margin: 0 0 10px; color: #5E5343; border-bottom: 1px dashed #ddd; padding-bottom: 5px;">REVENUE SUMMARY</h4>
                <p style="margin: 5px 0;"><strong>Total Sales Generated:</strong> <span style="color:#27ae60; font-weight:bold; font-size:14px;">&#8377;<?= number_format($sales['total_sales'] ?? 0, 2) ?></span></p>
                <p style="margin: 5px 0;"><strong>Total Orders Processed:</strong> <?= number_format($sales['total_orders'] ?? 0) ?></p>
                <p style="margin: 5px 0;"><strong>Avg. Value Per Order:</strong> &#8377;<?= ($sales['total_orders'] > 0) ? number_format($sales['total_sales'] / $sales['total_orders'], 2) : '0.00' ?></p>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%; vertical-align: top; background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #ccc;">
                <h4 style="margin: 0 0 10px; color: #5E5343; border-bottom: 1px dashed #ddd; padding-bottom: 5px;">ORDER STATUS METRICS</h4>
                <table style="width: 100%; font-size: 11px;">
                    <tr>
                        <td style="padding:3px 0;">Delivered Successfully:</td>
                        <td style="text-align:right; font-weight:bold; color:#27ae60;"><?= $statusCounts['Delivered'] ?></td>
                    </tr>
                    <tr>
                        <td style="padding:3px 0;">Currently Shipped:</td>
                        <td style="text-align:right; font-weight:bold; color:#3498db;"><?= $statusCounts['Shipped'] ?></td>
                    </tr>
                    <tr>
                        <td style="padding:3px 0;">Pending Processing:</td>
                        <td style="text-align:right; font-weight:bold; color:#f39c12;"><?= $statusCounts['Pending'] ?></td>
                    </tr>
                    <tr>
                        <td style="padding:3px 0; color:#c0392b;">Cancelled / Failed:</td>
                        <td style="text-align:right; font-weight:bold; color:#c0392b;"><?= $statusCounts['Cancelled'] ?></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <h4 style="margin: 0 0 10px; font-size: 14px; color: #5E5343; border-bottom: 2px solid #A1937A; padding-bottom: 5px;">SELLER'S TOP PRODUCTS</h4>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 11px;">
        <tr style="background: #f9f9f9; color: #5E5343;">
            <th style="padding: 10px; border: 1px solid #eee; text-align: center;">Sr. No.</th>
            <th style="padding: 10px; border: 1px solid #eee; text-align: left;">Product Name</th>
            <th style="padding: 10px; border: 1px solid #eee; text-align: center;">Price</th>
            <th style="padding: 10px; border: 1px solid #eee; text-align: center;">Units Sold</th>
            <th style="padding: 10px; border: 1px solid #eee; text-align: right;">Total Generated Revenue</th>
        </tr>
        <?php if (count($topProducts) > 0): ?>
            <?php $p_counter = 1; foreach ($topProducts as $prod): ?>
                <tr>
                    <td style="padding: 10px; border: 1px solid #eee; text-align: center;"><strong><?= $p_counter++ ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #eee;"><strong><?= htmlspecialchars($prod['name']) ?></strong></td>
                    <td style="padding: 10px; border: 1px solid #eee; text-align: center;">&#8377;<?= number_format($prod['price'], 2) ?></td>
                    <td style="padding: 10px; border: 1px solid #eee; text-align: center;"><?= $prod['sold_count'] ?></td>
                    <td style="padding: 10px; border: 1px solid #eee; text-align: right; color:#27ae60; font-weight:bold;">&#8377;<?= number_format($prod['revenue'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" style="padding: 15px; text-align: center; border: 1px solid #eee; color: #999;">No sales records found for this seller.</td>
            </tr>
        <?php endif; ?>
    </table>

    <h4 style="margin: 0 0 10px; font-size: 14px; color: #5E5343; border-bottom: 2px solid #A1937A; padding-bottom: 5px;">RECENT TRANSACTIONS</h4>
    <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
        <tr style="background: #f9f9f9; color: #5E5343;">
            <th style="padding: 10px; border: 1px solid #eee; text-align: center;">Sr. No.</th>
            <th style="padding: 10px; border: 1px solid #eee; text-align: left;">Date & Time</th>
            <th style="padding: 10px; border: 1px solid #eee; text-align: left;">Order ID</th>
            <th style="padding: 10px; border: 1px solid #eee; text-align: left;">Product</th>
            <th style="padding: 10px; border: 1px solid #eee; text-align: center;">Method</th>
            <th style="padding: 10px; border: 1px solid #eee; text-align: center;">Status</th>
            <th style="padding: 10px; border: 1px solid #eee; text-align: right;">Amount</th>
        </tr>
        <?php if (count($recentOrders) > 0): ?>
            <?php $t_counter = 1; foreach ($recentOrders as $ro): ?>
                <tr>
                    <td style="padding: 8px 10px; border: 1px solid #eee; text-align: center;"><strong><?= $t_counter++ ?></strong></td>
                    <td style="padding: 8px 10px; border: 1px solid #eee;"><?= date('d-m-Y, h:i A', strtotime($ro['created_at'])) ?></td>
                    <td style="padding: 8px 10px; border: 1px solid #eee;">#<?= $ro['id'] ?></td>
                    <td style="padding: 8px 10px; border: 1px solid #eee;"><?= htmlspecialchars(mb_strimwidth($ro['product_name'], 0, 30, "...")) ?></td>
                    <td style="padding: 8px 10px; border: 1px solid #eee; text-align: center;"><?= $ro['payment_method'] ?? 'Online' ?></td>
                    <td style="padding: 8px 10px; border: 1px solid #eee; text-align: center; font-weight:bold; color: <?= ($ro['status'] == 'Cancelled') ? '#c0392b' : (($ro['status'] == 'Delivered') ? '#27ae60' : '#555') ?>;"><?= strtoupper($ro['status']) ?></td>
                    <td style="padding: 8px 10px; border: 1px solid #eee; text-align: right; font-weight:bold;">&#8377;<?= number_format($ro['price'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="7" style="padding: 15px; text-align: center; border: 1px solid #eee; color: #999;">No transaction records found.</td>
            </tr>
        <?php endif; ?>
    </table>

    <div style="text-align: center; margin-top: 40px; font-size: 9px; color: #aaa; border-top: 1px dashed #eee; padding-top:10px;">
        <p>This document is a certified system-generated report strictly for administrative review.</p>
    </div>
</div>