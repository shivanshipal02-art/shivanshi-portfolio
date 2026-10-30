<?php
include '../include/connection.php';
session_start();

// FIX: Expecting txn_id instead of id
if (!isset($_SESSION['user_logged_in']) || !isset($_GET['txn_id'])) {
    exit('Unauthorized');
}

$txn_id = $_GET['txn_id'];
$user_id = $_SESSION['id'];

// FIX: Fetch ALL orders associated with this specific transaction
$sql = "SELECT o.*, 
        p.name as prod_name, p.image as prod_img, p.description as prod_desc, p.price as unit_price,
        u.username as cust_name, u.email as cust_email, u.phone as cust_phone,
        s.business_name as seller_brand, s.owner_name as seller_username,
        pay.payment_method, pay.transaction_id, pay.payment_status
        FROM orders o 
        JOIN product p ON o.product_id = p.id 
        JOIN users u ON o.user_id = u.id
        LEFT JOIN sellers s ON p.seller_id = s.id
        JOIN payment pay ON o.id = pay.order_id
        WHERE pay.transaction_id = ? AND o.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $txn_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();

$orders = [];
$total_paid = 0;

while($row = $res->fetch_assoc()) {
    // FIX: Calculate precise quantity purchased dynamically
    $qty = round($row['price'] / ($row['unit_price'] * 1.08));
    if ($qty < 1) $qty = 1;
    $row['qty'] = $qty;

    $orders[] = $row;
    $total_paid += $row['price'];
}

if (empty($orders)) {
    exit('<div style="text-align:center; padding: 20px; color:#c0392b;">Order data missing or Unauthorized</div>');
}

// We use the first item to get common shipping/transaction data
$first_order = $orders[0];
$subtotal = $total_paid / 1.08;
$tax = $total_paid - $subtotal;
?>
<div style="padding: 30px; font-family: 'Segoe UI', Arial, sans-serif; color: #333; background: #fff;">
    <table style="width: 100%; border-bottom: 2px solid #5E5343; padding-bottom: 15px; margin-bottom: 20px;">
        <tr>
            <td style="width: 50%;">
                <h1 style="margin: 0; color: #5E5343; font-size: 26px;">PERFUME NEST</h1>
                <p style="margin: 5px 0; font-size: 10px; color: #888;">Transaction ID: <span style="font-family: monospace;"><?= htmlspecialchars($first_order['transaction_id']) ?></span></p>
            </td>
            <td style="width: 50%; text-align: right; vertical-align: top;">
                <h2 style="margin: 0; color: #A1937A; font-size: 18px;">DIGITAL INVOICE</h2>
                <p style="margin: 5px 0; font-size: 11px;">Date & Time: <?= date('d-m-Y, h:i A', strtotime($first_order['created_at'])) ?></p>
            </td>
        </tr>
    </table>

    <table style="width: 100%; margin-bottom: 25px; font-size: 11px;">
        <tr>
            <td style="width: 48%; vertical-align: top; background: #F9F9F9; padding: 15px; border-radius: 8px;">
                <h4 style="margin: 0 0 10px; color: #5E5343; border-bottom: 1px solid #ddd; padding-bottom: 5px;">SHIPPING DESTINATION</h4>
                <p style="margin: 0; line-height: 1.6;">
                    <?= nl2br(htmlspecialchars($first_order['shipping_address'])) ?>
                </p>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%; vertical-align: top; background: #F9F9F9; padding: 15px; border-radius: 8px;">
                <h4 style="margin: 0 0 10px; color: #5E5343; border-bottom: 1px solid #ddd; padding-bottom: 5px;">PAYMENT DETAILS</h4>
                <p style="margin: 3px 0;"><strong>Method:</strong> <?= htmlspecialchars($first_order['payment_method']) ?></p>
                <p style="margin: 3px 0;"><strong>Status:</strong> <?= htmlspecialchars($first_order['payment_status']) ?></p>
            </td>
        </tr>
    </table>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 12px;">
        <thead>
            <tr style="background: #5E5343; color: #fff; text-align: left;">
                <th style="padding: 12px; width: 80px;">Image</th>
                <th style="padding: 12px;">Description</th>
                <th style="padding: 12px; text-align: center;">Qty</th>
                <th style="padding: 12px; text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($orders as $item): ?>
            <tr>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">
                    <img src="../<?= htmlspecialchars($item['prod_img']) ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">
                </td>
                <td style="padding: 10px; border-bottom: 1px solid #eee;">
                    <strong style="font-size: 13px;"><?= htmlspecialchars($item['prod_name']) ?></strong><br>
                    <span style="font-size: 10px; color: #777;">Sold by: <?= htmlspecialchars($item['seller_brand'] ?? $item['seller_username']) ?></span>
                </td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: center;"><?= $item['qty'] ?></td>
                <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right;">&#8377;<?= number_format($item['price'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="float: right; width: 250px;">
        <table style="width: 100%; font-size: 12px;">
            <tr>
                <td style="padding: 4px 0; color: #888;">Subtotal</td>
                <td style="text-align: right;">&#8377;<?= number_format($subtotal, 2) ?></td>
            </tr>
            <tr>
                <td style="padding: 4px 0; color: #888;">GST (8%)</td>
                <td style="text-align: right;">&#8377;<?= number_format($tax, 2) ?></td>
            </tr>
            <tr>
                <td colspan="2" style="border-top: 1px solid #5E5343; padding: 10px 0;"></td>
            </tr>
            <tr style="font-weight: bold; font-size: 16px; color: #5E5343;">
                <td>Total Paid</td>
                <td style="text-align: right;">&#8377;<?= number_format($total_paid, 2) ?></td>
            </tr>
        </table>
    </div>

    <div style="clear: both;"></div>

    <?php 
    // Check if ANY item in this transaction has been shipped to display tracking block
    $has_shipping = false;
    foreach($orders as $item) {
        if (!empty($item['courier_name'])) {
            $has_shipping = true;
            break;
        }
    }
    
    if ($has_shipping): ?>
        <div style="margin-top: 30px; padding: 15px; border: 1px dashed #A1937A; border-radius: 5px; background: #F5F1E9;">
            <h4 style="margin: 0 0 10px; font-size: 12px; color: #5E5343;">TRACKING & SHIPPING INFORMATION</h4>
            <?php foreach($orders as $item): 
                if(!empty($item['courier_name'])):
            ?>
            <table style="width: 100%; font-size: 11px; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px;">
                <tr>
                    <td style="width: 50%; vertical-align: top;">
                        <p style="margin: 0 0 4px 0; color:#5E5343; font-size: 12px;"><strong><?= htmlspecialchars($item['prod_name']) ?></strong></p>
                        <p style="margin: 0 0 4px 0;"><strong>Courier:</strong> <?= htmlspecialchars($item['courier_name']) ?></p>
                        <p style="margin: 0 0 4px 0;"><strong>Tracking ID:</strong> <span style="font-family: monospace; background: #fff; padding: 2px 6px; border: 1px solid #ddd; border-radius: 3px;"><?= htmlspecialchars($item['tracking_id']) ?></span></p>
                    </td>
                    <td style="width: 50%; vertical-align: top; text-align: right;">
                        <p style="margin: 0 0 4px 0;"><strong>Shipped:</strong> <?= !empty($item['shipped_at']) ? date('d-m-Y, h:i A', strtotime($item['shipped_at'])) : 'In Transit' ?></p>
                        <p style="margin: 0; color: <?= ($item['status'] == 'Delivered') ? '#27ae60' : '#888' ?>;"><strong>Delivered:</strong> <?= !empty($item['delivered_at']) ? date('d-m-Y, h:i A', strtotime($item['delivered_at'])) : 'Pending Delivery' ?></p>
                    </td>
                </tr>
            </table>
            <?php 
                endif;
            endforeach; ?>
        </div>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 50px; font-size: 9px; color: #aaa;">
        <p>This is a system-generated invoice. Thank you for choosing Perfume Nest.</p>
    </div>
</div>