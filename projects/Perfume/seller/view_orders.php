<?php
include '../include/connection.php';
session_start();

// Security Check
if (!isset($_SESSION['seller_logged_in']) || $_SESSION['seller_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: my_orders.php');
    exit;
}

$order_id = intval($_GET['id']);
$seller_id = $_SESSION['id'];

// Fetch full detailed order data, securely verifying it belongs to THIS seller
$sql = "SELECT o.id as order_id, o.created_at, o.shipped_at, o.delivered_at, o.price, o.status, o.shipping_address as admin_ship_addr, o.courier_name, o.tracking_id, o.courier_address, o.estimated_delivery_date, 
               p.name as product_name, p.image, p.seller_id,
               c.username as customer_name, c.email as customer_email, c.phone as customer_phone, c.address as customer_profile_addr,
               pay.payment_method, pay.payment_status, pay.transaction_id, pay.amount as paid_amount
        FROM orders o
        JOIN product p ON o.product_id = p.id
        JOIN users c ON o.user_id = c.id
        LEFT JOIN payment pay ON o.id = pay.order_id
        WHERE o.id = ? AND p.seller_id = ?";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("ii", $order_id, $seller_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    die("Database Error: " . $conn->error);
}

if (!$order) {
    die("<div style='padding:50px; text-align:center;'>Order not found or unauthorized. <a href='my_orders.php'>Go Back</a></div>");
}

$status = $order['status'] ?? 'Pending';
$is_shipped = ($status == 'Shipped' || $status == 'Delivered');
$is_delivered = ($status == 'Delivered');
$is_cancelled = ($status == 'Cancelled');

include 'include/header.php';
?>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">
    <div style="max-width: 900px; margin: 0 auto;">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <a href="my_orders.php" class="btn-back-link">
                <i class="fas fa-arrow-left"></i> Back to My Orders
            </a>
        </div>

        <div class="view-card">
            <div class="card-header">
                <h2>Order Tracking #<?= $order['order_id']; ?></h2>
                <?php
                $cls = ($status == 'Delivered' || $status == 'Completed') ? 'success' : (($status == 'Cancelled') ? 'danger' : (($status == 'Shipped') ? 'info' : 'warning'));
                ?>
                <span class="status-badge status-<?= $cls; ?>"><?= ucfirst($status); ?></span>
            </div>

            <?php if ($is_cancelled): ?>
                <div class="cancelled-state">
                    <i class="fas fa-times-circle"></i>
                    <h3>This Order was Cancelled</h3>
                    <p>No further tracking information is available.</p>
                </div>
            <?php else: ?>
                <div class="tracking-container">
                    <div class="track-line"></div>
                    
                    <div class="step active <?= $status == 'Pending' ? 'current-pulse' : '' ?>">
                        <div class="icon"><i class="fas fa-box"></i></div>
                        <div class="text">Placed & Preparing<br>
                            <small><?= date('d-m-Y, h:i A', strtotime($order['created_at'])); ?></small>
                        </div>
                    </div>
                    
                    <div class="step <?= $is_shipped ? 'active' : ''; ?> <?= $status == 'Shipped' ? 'current-pulse' : '' ?>">
                        <div class="icon"><i class="fas fa-truck"></i></div>
                        <div class="text">Shipped<br>
                            <small>
                                <?php if ($is_shipped): ?>
                                    <?= htmlspecialchars($order['courier_name']); ?><br>
                                    <span style="font-size:10px; color:#aaa; font-weight:normal;">
                                        <?= !empty($order['shipped_at']) ? date('d-m-Y, h:i A', strtotime($order['shipped_at'])) : 'In Transit'; ?>
                                    </span>
                                <?php else: ?>
                                    Waiting...
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                    
                    <div class="step <?= $is_delivered ? 'active current-pulse' : ''; ?>">
                        <div class="icon"><i class="fas fa-home"></i></div>
                        <div class="text">Delivered<br>
                            <small>
                                <?php if ($is_delivered): ?>
                                    <?= !empty($order['delivered_at']) ? date('d-m-Y, h:i A', strtotime($order['delivered_at'])) : 'Completed'; ?>
                                <?php else: ?>
                                    <?= !empty($order['estimated_delivery_date']) ? 'Exp: ' . date('d-m-Y', strtotime($order['estimated_delivery_date'])) : 'Pending'; ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="view-grid">
                <div class="detail-box">
                    <h3><i class="fas fa-shopping-bag"></i> Product Details</h3>
                    <div style="display: flex; gap: 15px; align-items: center; margin-top: 15px;">
                        <?php if (!empty($order['image'])): ?>
                            <img src="../<?= htmlspecialchars($order['image']); ?>" style="width: 70px; height: 70px; border-radius: 8px; object-fit: cover; border: 1px solid #eee;">
                        <?php else: ?>
                            <div style="width: 70px; height: 70px; border-radius: 8px; background: #eee; display:flex; align-items:center; justify-content:center; color:#999; font-size:12px;">No Img</div>
                        <?php endif; ?>
                        <div>
                            <div style="font-weight: 600; color: #5E5343; font-size: 16px; margin-bottom: 5px;"><?= htmlspecialchars($order['product_name']); ?></div>
                            <div style="color: #A1937A; font-weight: 700; font-size: 15px;">&#8377;<?= number_format($order['price'], 2); ?></div>
                        </div>
                    </div>
                </div>

                <div class="detail-box">
                    <h3><i class="fas fa-user-circle"></i> Buyer Account</h3>
                    <div style="margin-top: 15px; font-size: 13px; color: #555; line-height: 1.8;">
                        <div><strong style="color:#5E5343;">Account:</strong> <?= htmlspecialchars($order['customer_name']); ?></div>
                        <div><strong style="color:#5E5343;">Email:</strong> <?= htmlspecialchars($order['customer_email']); ?></div>
                        <div><strong style="color:#5E5343;">Phone:</strong> <?= htmlspecialchars($order['customer_phone'] ?? 'N/A'); ?></div>
                    </div>
                </div>

                <div class="detail-box">
                    <h3><i class="fas fa-credit-card"></i> Transaction Details</h3>
                    <div style="margin-top: 15px; font-size: 13px; color: #555; line-height: 1.8;">
                        <div><strong style="color:#5E5343;">Payment Method:</strong> <?= htmlspecialchars($order['payment_method'] ?? 'Online'); ?></div>
                        <div><strong style="color:#5E5343;">Transaction ID:</strong> <span style="font-family: monospace; background: #f9f9f9; padding: 2px 6px; border-radius: 4px; border: 1px solid #eee;"><?= htmlspecialchars($order['transaction_id'] ?? 'N/A'); ?></span></div>
                        <div><strong style="color:#5E5343;">Amount Paid:</strong> <span style="color: #A1937A; font-weight: 700;">&#8377;<?= number_format($order['paid_amount'] ?? $order['price'], 2); ?></span></div>
                        <div>
                            <strong style="color:#5E5343;">Status:</strong> 
                            <span style="font-weight: 600; color: <?= ($order['payment_status'] == 'Success' || $order['payment_status'] == 'Paid') ? '#27ae60' : (($order['payment_status'] == 'Failed') ? '#c0392b' : '#f39c12'); ?>;">
                                <?= htmlspecialchars($order['payment_status'] ?? 'Pending'); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="detail-box">
                    <h3><i class="fas fa-shipping-fast"></i> Shipping Details</h3>
                    <div style="font-size: 13px; color: #555; line-height: 1.6; margin-top:10px;">
                        <strong style="color:#5E5343;">Receiver & Address:</strong><br>
                        <?= nl2br(htmlspecialchars($order['admin_ship_addr'])); ?>
                    </div>

                    <?php if ($is_shipped): ?>
                        <div style="margin-top: 15px; padding-top: 10px; border-top: 1px dashed #ccc; background: #fdfdfd; padding: 12px; border-radius: 6px; border: 1px solid #eee;">
                            <div style="color: #27ae60; font-size: 13px; line-height: 1.6;">
                                <strong style="color:#5E5343;">Courier Service:</strong> <?= htmlspecialchars($order['courier_name']); ?><br>
                                <strong style="color:#5E5343;">Tracking ID:</strong> <span style="font-family: monospace; background: #eee; padding: 2px 6px; border-radius: 4px; color:#333;"><?= htmlspecialchars($order['tracking_id']); ?></span><br>
                                <strong style="color:#5E5343;">Courier Office:</strong> <?= nl2br(htmlspecialchars($order['courier_address'] ?? 'N/A')); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </div>
</main>

<style>
    /* PRESERVED DESIGN STYLES */
    .btn-back-link { text-decoration: none; color: #5E5343; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; transition: 0.3s; }
    .btn-back-link:hover { color: #A1937A; }
    .view-card { background: #F5F1E9; border-radius: 12px; padding: 35px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04); border: 1px solid #e0e0e0; }
    .card-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #B2A48C; padding-bottom: 15px; margin-bottom: 30px; }
    .card-header h2 { margin: 0; font-family: 'Playfair Display', serif; color: #5E5343; font-size: 24px; }
    
    .status-badge { padding: 6px 15px; border-radius: 50px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .status-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
    .status-info { background: #e3f2fd; color: #0c5460; border: 1px solid #bee5eb; }
    .status-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .status-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

    .tracking-container { display: flex; justify-content: space-between; position: relative; margin: 20px 0 50px; }
    .tracking-container .track-line { position: absolute; top: 22px; left: 15%; width: 70%; height: 4px; background: #ddd; z-index: 1; }
    .tracking-container .step { text-align: center; position: relative; z-index: 2; flex: 1; }
    .tracking-container .step .icon { width: 48px; height: 48px; background: #ddd; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 20px; margin: 0 auto 10px; border: 5px solid #F5F1E9; transition: 0.4s; }
    .tracking-container .step .text { font-size: 14px; font-weight: 600; color: #999; }
    .tracking-container .step .text small { display: block; font-weight: normal; color: #aaa; margin-top: 3px; font-size: 12px; }

    .tracking-container .step.active .icon { background: #5E5343; box-shadow: 0 0 0 4px rgba(94, 83, 67, 0.1); }
    .tracking-container .step.active .text { color: #5E5343; }
    .tracking-container .step.active .text small { color: #A1937A; font-weight: 600; }

    @keyframes bigPulseGlow {
        0% { box-shadow: 0 0 0 0 rgba(161, 147, 122, 0.7); transform: scale(1); }
        50% { box-shadow: 0 0 0 15px rgba(161, 147, 122, 0); transform: scale(1.1); }
        100% { box-shadow: 0 0 0 0 rgba(161, 147, 122, 0); transform: scale(1); }
    }

    .tracking-container .step.current-pulse .icon {
        animation: bigPulseGlow 2s infinite;
        background: #A1937A; 
    }

    .cancelled-state { text-align: center; padding: 25px; background: #ffebee; color: #c0392b; border-radius: 8px; margin-bottom: 35px; border: 1px solid #ffcdd2; }
    .cancelled-state i { font-size: 30px; margin-bottom: 10px; }
    .cancelled-state h3 { margin: 0 0 5px 0; font-size: 18px; }

    .view-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
    .detail-box { background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02); }
    .detail-box h3 { font-size: 14px; color: #5E5343; margin-top: 0; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; text-transform: uppercase; }
    .detail-box h3 i { color: #A1937A; margin-right: 5px; font-size: 16px; }

    @media (max-width: 768px) {
        .view-grid { grid-template-columns: 1fr; }
        .tracking-container .step .text { font-size: 11px; }
    }
</style>

<?php include 'include/footer.php'; ?>