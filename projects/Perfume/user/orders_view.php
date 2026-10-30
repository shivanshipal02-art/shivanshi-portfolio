<?php
include '../include/connection.php';
session_start();

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Support both ?txn_id=XYZ and ?id=XYZ
if (isset($_GET['txn_id'])) {
    $txn_id = $_GET['txn_id'];
} elseif (isset($_GET['id'])) {
    $o_id = intval($_GET['id']);
    $t_res = $conn->query("SELECT transaction_id FROM payment WHERE order_id = $o_id");
    $txn_id = ($t_res && $t_res->num_rows > 0) ? $t_res->fetch_assoc()['transaction_id'] : '';
} else {
    header('Location: orders.php');
    exit;
}

$user_id = $_SESSION['id'];

// --- HANDLE CANCEL ENTIRE TRANSACTION ACTION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    
    $cancel_reason = trim($_POST['cancel_reason'] ?? '');
    if ($cancel_reason === 'Other') {
        $cancel_reason = trim($_POST['other_reason'] ?? 'No reason provided');
    }

    $fetchStmt = $conn->prepare("SELECT o.id, o.product_id, o.price AS order_price, p.price AS unit_price FROM orders o JOIN payment pay ON o.id = pay.order_id JOIN product p ON o.product_id = p.id WHERE pay.transaction_id = ? AND o.user_id = ? AND o.status = 'Pending'");
    $fetchStmt->bind_param('si', $txn_id, $user_id);
    $fetchStmt->execute();
    $fetchRes = $fetchStmt->get_result();

    while ($orderData = $fetchRes->fetch_assoc()) {
        $o_id = $orderData['id'];
        $pid = $orderData['product_id'];
        
        $qty = round($orderData['order_price'] / ($orderData['unit_price'] * 1.08));
        if ($qty < 1) $qty = 1; 

        $conn->query("UPDATE orders SET status = 'Cancelled', cancel_reason = '$cancel_reason', cancelled_at = NOW() WHERE id = $o_id");
        $conn->query("UPDATE product SET stock = stock + $qty WHERE id = $pid");
    }
    $fetchStmt->close();

    header("Location: orders_view.php?txn_id=" . urlencode($txn_id));
    exit;
}

// 2. Fetch Detailed Orders for the Entire Transaction (FIX: Fetching Unit Price & Estimated Delivery Date)
$sql = "SELECT o.*, 
        p.name as product_name, p.image, p.description, p.price as unit_price,
        u.username as customer_name, u.phone as u_phone, u.email as u_email,
        s.business_name as seller_brand, s.owner_name as seller_name,
        addr.delivery_address as profile_addr, addr.city as profile_city, addr.pincode as profile_pin,
        pay.payment_method, pay.transaction_id, pay.payment_status
        FROM orders o 
        JOIN product p ON o.product_id = p.id 
        JOIN users u ON o.user_id = u.id
        LEFT JOIN sellers s ON p.seller_id = s.id
        LEFT JOIN (
            SELECT user_id, delivery_address, city, pincode 
            FROM address t1 
            WHERE id = (SELECT MAX(id) FROM address t2 WHERE t2.user_id = t1.user_id)
        ) addr ON u.id = addr.user_id
        JOIN payment pay ON o.id = pay.order_id
        WHERE pay.transaction_id = ? AND o.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $txn_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();

$order_items = [];
$total_order_price = 0;

while($row = $res->fetch_assoc()) {
    // FIX: Calculate precise quantity purchased
    $qty = round($row['price'] / ($row['unit_price'] * 1.08));
    if ($qty < 1) $qty = 1;
    $row['quantity'] = $qty;

    $order_items[] = $row;
    $total_order_price += $row['price'];
}

if (empty($order_items)) {
    echo "<div style='padding:40px; text-align:center;'>Order details not found. <a href='orders.php'>Go Back</a></div>";
    exit;
}

// Use first item for shared transaction info
$first_item = $order_items[0];
$status = $first_item['status'] ?: 'Pending';
$is_shipped = ($status == 'Shipped' || $status == 'Delivered');
$is_delivered = ($status == 'Delivered');
$is_cancelled = ($status == 'Cancelled');
$cancel_reason = $first_item['cancel_reason'] ?? null;

include 'include/header.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<main style="flex: 1; padding: 30px; background-color: #fdfdfd; overflow-y: auto;">
    <div style="max-width: 1100px; margin: 0 auto;">

        <a href="orders.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Orders</a>

        <div class="order-container">
            <div class="details-pane">
                <div class="pane-header">
                    <div>
                        <h1 class="order-id-title">Transaction Details</h1>
                        <p class="order-timestamp">Placed on <?= date('d-m-Y, h:i A', strtotime($first_item['created_at'])); ?></p>
                    </div>
                    <div class="action-btns">
                        <?php if ($status === 'Pending'): ?>
                            <button onclick="openCancelModal()" class="cancel-btn">Cancel Order</button>
                        <?php endif; ?>
                        <button onclick="downloadInvoice('<?= $txn_id; ?>')" class="invoice-btn" id="downloadBtn">Get Invoice</button>
                    </div>
                </div>

                <div style="margin-bottom: 25px;">
                    <h3 style="font-size: 14px; color:#5E5343; margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">Purchased Items (<?= count($order_items); ?>)</h3>
                    <?php foreach ($order_items as $item): ?>
                        <div class="snapshot-card">
                            <img src="../<?= htmlentities($item['image']); ?>" class="snapshot-img">
                            <div class="snapshot-info">
                                <h2><?= htmlentities($item['product_name']); ?></h2>
                                <p class="snapshot-price">&#8377;<?= number_format($item['price'], 2); ?> <span style="font-size:12px; color:#888;">(Qty: <?= $item['quantity']; ?>)</span></p>
                                <div class="seller-badge"><i class="fas fa-store"></i> Seller: <strong><?= htmlspecialchars($item['seller_brand'] ?? $item['seller_name']); ?></strong></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="info-grid">
                    <div class="info-card">
                        <h3><i class="fas fa-truck-loading"></i> Final Shipping Destination</h3>
                        <p class="addr-data"><?= nl2br(htmlentities($first_item['shipping_address'] ?: 'Processing shipment details...')); ?></p>
                    </div>

                    <div class="info-card">
                        <h3><i class="fas fa-credit-card"></i> Transaction Summary</h3>
                        <p class="small-text">Method: <?= $first_item['payment_method']; ?></p>
                        <p class="small-text">ID: <span style="font-family: monospace;"><?= $first_item['transaction_id']; ?></span></p>
                        <p class="small-text" style="font-size: 13px; margin-top: 6px; color:#5E5343;">Total Paid: <strong>&#8377;<?= number_format($total_order_price, 2); ?></strong></p>
                        <p class="small-text" style="margin-top: 5px;">Status: <strong style="color:<?= ($first_item['payment_status'] == 'Failed' || $first_item['payment_status'] == 'Refunded' || $is_cancelled) ? '#c0392b' : '#27ae60'; ?>;"><?= $is_cancelled ? 'Refunded/Failed' : $first_item['payment_status']; ?></strong></p>
                    </div>
                </div>
            </div>

            <div class="tracking-pane">
                <h3 class="pane-subtitle">Tracking</h3>
                
                <div class="tracking-v-line">
                    
                    <div class="track-step active">
                        <div class="t-dot"></div>
                        <?php if ($status === 'Pending'): ?>
                            <i class="fas fa-chevron-down moving-arrow"></i>
                        <?php endif; ?>
                        <div class="t-content">
                            <strong>Order Received</strong>
                            <span>Seller is preparing your items</span><br>
                            <span style="font-size: 10px; color: #5E5343; font-weight: 500; margin-top: 3px; display: inline-block;">
                                <?= date('d-m-Y, h:i A', strtotime($first_item['created_at'])); ?>
                            </span>
                        </div>
                    </div>

                    <?php if (!$is_cancelled || !empty($first_item['shipped_at'])): ?>
                    <div class="track-step <?= !empty($first_item['shipped_at']) ? 'active' : '' ?>">
                        <div class="t-dot"></div>
                        <?php if ($status === 'Shipped'): ?>
                            <i class="fas fa-chevron-down moving-arrow"></i>
                        <?php endif; ?>
                        <div class="t-content">
                            <strong>Shipped</strong>
                            <?php if (!empty($first_item['shipped_at'])): ?>
                                <span class="courier-tag"><?= htmlspecialchars($first_item['courier_name']); ?></span>
                                <p class="small-text" style="color:#888; font-style:italic; margin-top:5px; margin-bottom:0;">
                                    <i class="fas fa-map-marker-alt"></i> Office: <?= nl2br(htmlspecialchars($first_item['courier_address'])); ?>
                                </p>
                                <p class="small-text" style="margin-top:2px;">
                                    <i class="fas fa-barcode"></i> Tracking ID: <span style="font-family: monospace; font-weight: bold; color: #333;"><?= htmlspecialchars($first_item['tracking_id']); ?></span>
                                </p>
                                <span style="font-size: 10px; color: #5E5343; font-weight: 500; margin-top: 3px; display: inline-block;">
                                    <?= date('d-m-Y, h:i A', strtotime($first_item['shipped_at'])); ?>
                                </span>
                            <?php else: ?>
                                <span>Waiting for dispatch...</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($is_cancelled): ?>
                        <div class="track-step active cancelled-step" style="padding-bottom: 0;">
                            <div class="t-dot" style="background: #e74c3c; box-shadow: 0 0 0 4px rgba(231, 76, 60, 0.15);"></div>
                            <div class="t-content">
                                <strong style="color: #c0392b;">Order Cancelled</strong>
                                <?php if ($cancel_reason): ?>
                                    <span style="color: #e74c3c; font-style: italic; font-weight: 500; margin-top: 5px; display: block;">
                                        User Reason: <?= htmlspecialchars($cancel_reason); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($first_item['cancelled_at'])): ?>
                                    <span style="font-size: 10px; color: #c0392b; font-weight: 500; margin-top: 3px; display: block;">
                                        <i class="far fa-clock"></i> <?= date('d-m-Y, h:i A', strtotime($first_item['cancelled_at'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="track-step <?= $is_delivered ? 'active' : '' ?>" style="padding-bottom: 0;">
                            <div class="t-dot"></div>
                            <div class="t-content">
                                <strong>Delivered</strong>
                                <?php if ($is_delivered): ?>
                                    <p class="small-text" style="color:#27ae60; font-weight:600; margin-top:5px; margin-bottom: 0;">
                                        <i class="fas fa-check-circle"></i> Items delivered safely
                                    </p>
                                    <?php if (!empty($first_item['delivered_at'])): ?>
                                        <span style="font-size: 10px; color: #5E5343; font-weight: 500; margin-top: 3px; display: inline-block;">
                                            <?= date('d-m-Y, h:i A', strtotime($first_item['delivered_at'])); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span>Expected at doorstep</span>
                                    <?php if (!empty($first_item['estimated_delivery_date'])): ?>
                                        <span style="font-size: 11px; color: #3498db; font-weight: 600; margin-top: 5px; display: block;">
                                            <i class="far fa-calendar-check"></i> Expected by: <?= date('d M, Y', strtotime($first_item['estimated_delivery_date'])); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <div id="cancelModal" class="modal-overlay" style="display: none;">
        <div class="modal-box">
            <h3 style="margin-top: 0; color: #5E5343; font-family: 'Playfair Display', serif; font-size: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Cancel Order</h3>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">Please let us know why you are cancelling this transaction. Your refund will be initiated automatically.</p>
            
            <form method="POST" action="orders_view.php?txn_id=<?= urlencode($txn_id) ?>">
                <input type="hidden" name="action" value="cancel">
                
                <select name="cancel_reason" id="cancel_reason" required onchange="toggleOtherReason()" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-family: 'Poppins', sans-serif; font-size: 13px; outline: none; background: #fafafa;">
                    <option value="" disabled selected>-- Select a reason --</option>
                    <option value="Ordered by mistake">Ordered by mistake</option>
                    <option value="Found a better price elsewhere">Found a better price elsewhere</option>
                    <option value="Delivery is taking too long">Delivery is taking too long</option>
                    <option value="Changed my mind">Changed my mind</option>
                    <option value="Payment / Discount issue">Payment / Discount issue</option>
                    <option value="Other">Other (Please specify)</option>
                </select>

                <textarea name="other_reason" id="other_reason" style="display: none; width: 100%; box-sizing: border-box; margin-top: 10px; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-family: 'Poppins', sans-serif; font-size: 13px; resize: vertical;" rows="3" placeholder="Write your specific reason here..."></textarea>

                <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeCancelModal()" style="background: #eee; color: #555; border: none; padding: 8px 15px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer;">Keep Order</button>
                    <button type="submit" style="background: #e74c3c; color: #fff; border: none; padding: 8px 15px; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer;">Confirm Cancel</button>
                </div>
            </form>
        </div>
    </div>

</main>

<style>
    .back-link { display: inline-flex; align-items: center; gap: 8px; color: #999; text-decoration: none; margin-bottom: 20px; font-size: 13px; font-weight: 600; }
    .order-container { display: grid; grid-template-columns: 1fr 340px; gap: 30px; }
    .details-pane { background: #fff; border-radius: 12px; border: 1px solid #eee; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02); }
    .pane-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; border-bottom: 1px solid #f9f9f9; padding-bottom: 15px; }
    .order-id-title { font-family: 'Playfair Display', serif; font-size: 20px; color: #5E5343; margin: 0; }
    .order-timestamp { font-size: 12px; color: #999; font-weight: 500; margin-top: 3px; }
    .snapshot-card { display: flex; gap: 15px; background: #F5F1E9; padding: 15px; border-radius: 8px; align-items: center; border: 1px solid #E6E1D8; margin-bottom: 10px;}
    .snapshot-img { width: 60px; height: 60px; border-radius: 5px; object-fit: cover; background: #fff; border: 1px solid #ddd; }
    .snapshot-info h2 { font-size: 14px; color: #5E5343; margin: 0; }
    .snapshot-price { font-weight: 700; color: #A1937A; font-size: 14px; }
    .seller-badge { font-size: 10px; color: #999; margin-top: 5px; }
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
    .info-card { background: #fafafa; padding: 15px; border-radius: 8px; border: 1px solid #f3f3f3; }
    .info-card h3 { font-size: 9px; text-transform: uppercase; color: #aaa; margin-top: 0; margin-bottom: 10px; display: flex; align-items: center; gap: 5px; }
    .addr-data { font-size: 11px; color: #666; line-height: 1.5; }
    .small-text { font-size: 11px; color: #666; margin: 3px 0; }
    .action-btns { display: flex; gap: 8px; }
    .invoice-btn { background: #5E5343; color: #fff; border: none; padding: 8px 15px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer; }
    .cancel-btn { background: transparent; border: 1px solid #e74c3c; color: #e74c3c; padding: 8px 15px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer; transition: 0.3s; }
    .cancel-btn:hover { background: #e74c3c; color: #fff; }
    
    .tracking-pane { background: #fff; border-radius: 12px; border: 1px solid #eee; padding: 25px; height: fit-content; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.02); }
    .pane-subtitle { font-size: 12px; margin-top: 0; margin-bottom: 20px; color: #5E5343; text-transform: uppercase; letter-spacing: 1px; }
    .tracking-v-line { border-left: 2px solid #f0f0f0; margin-left: 10px; padding-left: 25px; position: relative; }
    .track-step { position: relative; padding-bottom: 35px; }
    .t-dot { position: absolute; left: -31px; top: 0; width: 10px; height: 10px; border-radius: 50%; background: #f0f0f0; }
    .track-step.active .t-dot { background: #5E5343; box-shadow: 0 0 0 3px rgba(94, 83, 67, 0.1); }
    
    .track-step.cancelled-step .t-dot { background: #e74c3c; box-shadow: 0 0 0 4px rgba(231, 76, 60, 0.15); }
    
    .t-content strong { display: block; font-size: 12px; color: #ccc; }
    .track-step.active .t-content strong { color: #333; }
    .t-content span { font-size: 10px; color: #999; }
    .courier-tag { display: inline-block; background: #e8f5e9; color: #2e7d32; padding: 1px 8px; border-radius: 3px; font-weight: 600; font-size: 9px; margin-top: 5px; text-transform: uppercase; }
    
    .moving-arrow {
        position: absolute;
        left: -29.5px;
        top: 15px;
        color: #A1937A;
        font-size: 11px;
        animation: slideDownArrow 1.5s infinite;
    }
    @keyframes slideDownArrow {
        0% { transform: translateY(0); opacity: 1; }
        80% { transform: translateY(15px); opacity: 0; }
        100% { transform: translateY(15px); opacity: 0; }
    }

    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
    .modal-box { background: #fff; padding: 30px; border-radius: 12px; width: 90%; max-width: 400px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); animation: popIn 0.3s ease; }
    @keyframes popIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }

    @media (max-width: 900px) { .order-container { grid-template-columns: 1fr; } }
</style>

<script>
    async function downloadInvoice(txnId) {
        const btn = document.getElementById('downloadBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        btn.disabled = true;
        try {
            const response = await fetch('invoice.php?txn_id=' + txnId);
            const htmlContent = await response.text();
            const opt = {
                margin: 0.4,
                filename: 'Invoice_#' + txnId + '.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(htmlContent).save().then(() => {
                btn.innerHTML = 'Get Invoice';
                btn.disabled = false;
            });
        } catch (e) {
            btn.innerHTML = 'Error!';
            btn.disabled = false;
        }
    }

    function openCancelModal() {
        document.getElementById('cancelModal').style.display = 'flex';
    }

    function closeCancelModal() {
        document.getElementById('cancelModal').style.display = 'none';
        document.getElementById('cancel_reason').value = "";
        document.getElementById('other_reason').style.display = 'none';
    }

    function toggleOtherReason() {
        const select = document.getElementById('cancel_reason');
        const otherText = document.getElementById('other_reason');
        if (select.value === 'Other') {
            otherText.style.display = 'block';
            otherText.required = true;
        } else {
            otherText.style.display = 'none';
            otherText.required = false;
        }
    }
</script>
<?php include 'include/footer.php'; ?>