<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Security Check
if (!isset($_SESSION['seller_logged_in']) || $_SESSION['seller_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

include '../include/connection.php';
$seller_id = $_SESSION['id'];

// --- 2. HANDLE ACTIONS (Shipping / Delivery / Cancel) ---

// A. Confirm Shipping (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_shipping'])) {
    $order_id = intval($_POST['order_id']);
    $courier_name = $_POST['courier_name'];
    $tracking_id = $_POST['tracking_id'];
    $courier_address = $_POST['courier_address']; 
    $final_shipping_address = $_POST['final_shipping_address'];

    if (!empty($courier_name) && !empty($tracking_id) && !empty($courier_address) && !empty($final_shipping_address)) {
        // Ownership Check
        $check = $conn->prepare("SELECT o.id FROM orders o JOIN product p ON o.product_id = p.id WHERE o.id = ? AND p.seller_id = ?");
        $check->bind_param("ii", $order_id, $seller_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            // Update courier info, tracking, and set shipped_at = NOW()
            $stmt = $conn->prepare("UPDATE orders SET status = 'Shipped', courier_name = ?, tracking_id = ?, courier_address = ?, shipping_address = ?, shipped_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssssi", $courier_name, $tracking_id, $courier_address, $final_shipping_address, $order_id);
            if ($stmt->execute()) {
                echo "<script>window.location.href='my_orders.php';</script>";
            }
            $stmt->close();
        }
        $check->close();
    }
}

// B. Deliver / Cancel (GET)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $order_id = intval($_GET['id']);
    $action = $_GET['action'];
    $new_status = ($action == 'deliver') ? 'Delivered' : (($action == 'cancel') ? 'Cancelled' : '');

    if ($new_status != '') {
        $check = $conn->prepare("SELECT o.id FROM orders o JOIN product p ON o.product_id = p.id WHERE o.id = ? AND p.seller_id = ?");
        $check->bind_param("ii", $order_id, $seller_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            
            // If Delivered, update delivered_at = NOW()
            if ($new_status == 'Delivered') {
                $stmt = $conn->prepare("UPDATE orders SET status = ?, delivered_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $new_status, $order_id);
                $stmt->execute();
                $stmt->close();

                // FIX: Automatically update COD payment to 'Success' when delivered
                $payStmt = $conn->prepare("UPDATE payment SET payment_status = 'Success' WHERE order_id = ? AND payment_method = 'COD'");
                $payStmt->bind_param("i", $order_id);
                $payStmt->execute();
                $payStmt->close();
                
            } else {
                $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $new_status, $order_id);
                $stmt->execute();
                $stmt->close();
            }
        }
        $check->close();
    }
    header("Location: my_orders.php");
    exit;
}

// --- 3. FETCH DETAILED ORDER DATA (Database Strict Mode Fixed) ---
$sql = "SELECT 
            o.id as order_id, o.created_at, o.shipped_at, o.delivered_at, o.price, o.status, o.shipping_address as courier_ship_addr, o.courier_name, o.tracking_id, o.courier_address,
            p.name as product_name, p.image, p.description as prod_desc,
            u.username as customer_username,
            a.name as reg_name, a.email as reg_email, a.phone as reg_phone, 
            a.delivery_address as profile_addr, a.city as profile_city, a.pincode as profile_pin,
            pay.payment_method, pay.transaction_id, pay.payment_status
        FROM orders o
        JOIN product p ON o.product_id = p.id
        JOIN users u ON o.user_id = u.id
        LEFT JOIN address a ON a.user_id = u.id AND a.id = (SELECT MAX(id) FROM address WHERE user_id = u.id)
        LEFT JOIN payment pay ON o.id = pay.order_id
        WHERE p.seller_id = ?
        ORDER BY o.created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
} else {
    die("Database Error: " . $conn->error);
}

include 'include/header.php';
?>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">
    <div style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin-bottom: 5px;">Manage Orders</h1>
            <p style="color: #999; font-size: 13px; margin-top: 5px;">Process shipments and delivery updates</p>
        </div>
        <div class="stat-badge">
            <i class="fas fa-shopping-bag"></i> <?= count($orders); ?> Total Orders
        </div>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">Sr. No.</th>
                        <th width="20%">Customer Profile</th>
                        <th width="24%">Product & Payment</th>
                        <th width="22%">Tracking Flow</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $counter = 1;
                    foreach ($orders as $row):
                        $status = !empty($row['status']) ? $row['status'] : 'Pending';
                        $registered_address = $row['profile_addr'] . ", " . $row['profile_city'] . " - " . $row['profile_pin'];
                        $courier_ship_addr = $row['courier_ship_addr'];
                        
                        $is_shipped = ($status == 'Shipped' || $status == 'Delivered');
                        $is_delivered = ($status == 'Delivered');
                        $is_cancelled = ($status == 'Cancelled');
                    ?>
                        <tr>
                            <td><span class="id-badge"><?= $counter++; ?></span></td>

                            <td>
                                <div style="font-weight: 600; color: #5E5343; margin-bottom: 3px;">
                                    <?= htmlspecialchars($row['reg_name'] ?? $row['customer_username']); ?>
                                </div>
                                <div style="font-size: 11px; color: #888;">
                                    <i class="fas fa-phone-alt"></i> <?= htmlspecialchars($row['reg_phone']); ?>
                                </div>
                                <div class="addr-block">
                                    <span class="addr-label"><i class="fas fa-user"></i> Registered Addr:</span>
                                    <span style="font-size: 10px;"><?= htmlspecialchars(mb_strimwidth($registered_address, 0, 40, "...")); ?></span>
                                </div>
                            </td>

                            <td>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                    <img src="../<?= htmlspecialchars($row['image']); ?>" style="width: 40px; height: 40px; border-radius: 6px; object-fit: cover; border: 1px solid #ccc;">
                                    <div>
                                        <div style="font-weight: 600; font-size: 13px; color: #5E5343;"><?= htmlspecialchars($row['product_name']); ?></div>
                                        <div style="color: #A1937A; font-size: 12px; font-weight: 700;">&#8377;<?= number_format($row['price']); ?></div>
                                    </div>
                                </div>
                                <div style="font-size: 10px; color: #777; line-height: 1.4; border-top: 1px dashed #eee; padding-top: 6px;">
                                    <strong style="color: #555;"><?= htmlspecialchars($row['payment_method'] ?? 'N/A'); ?></strong> 
                                    (<span style="font-weight: 600; color: <?= ($row['payment_status'] == 'Success' || $row['payment_status'] == 'Paid') ? '#27ae60' : (($row['payment_status'] == 'Failed') ? '#c0392b' : '#f39c12'); ?>;"><?= htmlspecialchars($row['payment_status'] ?? 'Pending'); ?></span>)<br>
                                    <span style="font-family: monospace;">ID: <?= htmlspecialchars($row['transaction_id'] ?? 'N/A'); ?></span>
                                </div>
                            </td>

                            <td>
                                <?php if (!$is_cancelled): ?>
                                    <div class="tracking-line" style="margin-bottom: 8px;">
                                        <div class="step completed" title="Preparing"><i class="fas fa-box"></i></div>
                                        <div class="line <?= $is_shipped ? 'active' : ''; ?>"></div>
                                        <div class="step <?= $is_shipped ? 'completed' : '' ?>" title="Shipped"><i class="fas fa-truck"></i></div>
                                        <div class="line <?= $is_delivered ? 'active' : ''; ?>"></div>
                                        <div class="step <?= $is_delivered ? 'completed' : '' ?>" title="Delivered"><i class="fas fa-check-circle"></i></div>
                                    </div>
                                    
                                    <div style="font-size: 10px; line-height: 1.4; color: #666;">
                                        <?php if ($status == 'Pending'): ?>
                                            <strong style="color:#f39c12;"><i class="fas fa-spinner fa-spin"></i> Preparing Order</strong><br>
                                            <span style="color:#888; font-size:9px;">Placed: <?= date('d-m-Y, h:i A', strtotime($row['created_at'])); ?></span>
                                        
                                        <?php elseif ($status == 'Shipped'): ?>
                                            <strong style="color:#27ae60;"><i class="fas fa-truck"></i> <?= htmlspecialchars($row['courier_name']); ?></strong><br>
                                            <span style="color:#888; font-size:9px;">Shipped: <?= !empty($row['shipped_at']) ? date('d-m-Y, h:i A', strtotime($row['shipped_at'])) : 'In Transit'; ?></span>
                                        
                                        <?php elseif ($status == 'Delivered'): ?>
                                            <strong style="color:#155724;"><i class="fas fa-home"></i> Delivered To:</strong><br>
                                            <span style="color:#888; font-size:9px;">On: <?= !empty($row['delivered_at']) ? date('d-m-Y, h:i A', strtotime($row['delivered_at'])) : 'Completed'; ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #c0392b; font-size: 11px; font-weight: 600;"><i class="fas fa-ban"></i> Cancelled</span>
                                <?php endif; ?>
                            </td>

                            <td><span class="status-badge status-<?= strtolower($status); ?>"><?= ucfirst($status); ?></span></td>

                            <td>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <a href="view_orders.php?id=<?= $row['order_id']; ?>" class="btn-action-outline"><i class="fas fa-eye"></i> View</a>
                                    
                                    <?php if ($status == 'Pending'): ?>
                                        <button type="button" class="btn-ship" 
                                            data-id="<?= $row['order_id']; ?>" 
                                            data-cust-name="<?= htmlspecialchars($row['reg_name']); ?>"
                                            data-cust-phone="<?= htmlspecialchars($row['reg_phone']); ?>"
                                            data-cust-addr="<?= htmlspecialchars($registered_address); ?>" 
                                            onclick="openShipModal(this)"><i class="fas fa-truck"></i> Ship</button>
                                        <a href="my_orders.php?action=cancel&id=<?= $row['order_id']; ?>" class="btn-cancel" onclick="return confirm('Cancel Order?');"><i class="fas fa-times"></i> Cancel</a>
                                    
                                    <?php elseif ($status == 'Shipped'): ?>
                                        <a href="my_orders.php?action=deliver&id=<?= $row['order_id']; ?>" class="btn-deliver" onclick="return confirm('Confirm Delivery?');"><i class="fas fa-check-circle"></i> Deliver</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div id="shipModal" class="modal-overlay">
    <div class="modal-card">
        <span class="close-modal" onclick="closeShipModal()">&times;</span>
        <h2 style="font-family: 'Playfair Display', serif; color: #5E5343; border-bottom: 1px solid #B2A48C; padding-bottom: 15px; margin-bottom: 20px; font-size: 20px;">Enter Shipment Details</h2>
        
        <div id="modal_user_details" style="background: #fdfdfd; padding: 12px; border-radius: 8px; border: 1px solid #eee; margin-bottom: 15px;">
            <p style="margin:0; font-size: 13px; color: #5E5343;"><strong>Customer:</strong> <span id="v_modal_name"></span></p>
            <p style="margin:0; font-size: 13px; color: #5E5343;"><strong>Contact:</strong> <span id="v_modal_phone"></span></p>
        </div>

        <form method="post">
            <input type="hidden" name="order_id" id="modal_order_id">
            
            <div class="form-group">
                <label>User's Registered Address (Reference)</label>
                <textarea id="modal_cust_addr" rows="2" readonly style="background: #f9f9f9; color: #777; font-size: 12px; border-color: #eee;"></textarea>
            </div>

            <div class="form-group">
                <label style="color: #A1937A;"><i class="fas fa-map-pin"></i> Courier / Dispatch Office Location</label>
                <textarea name="courier_address" rows="2" required placeholder="Enter the courier office address..."></textarea>
            </div>

            <div class="form-group">
                <label style="color: #A1937A;"><i class="fas fa-truck-loading"></i> Final Shipping Destination</label>
                <textarea name="final_shipping_address" id="modal_ship_addr" rows="3" required placeholder="Verify exactly where to deliver..."></textarea>
            </div>

            <div style="display: flex; gap: 15px;">
                <div class="form-group" style="flex:1;"><label>Courier Name</label><input type="text" name="courier_name" placeholder="e.g. BlueDart" required></div>
                <div class="form-group" style="flex:1;"><label>Tracking ID</label><input type="text" name="tracking_id" placeholder="TRK-9900" required></div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 5px;">
                <button type="button" class="btn-cancel-modal" onclick="closeShipModal()">Back</button>
                <button type="submit" name="confirm_shipping" class="btn-confirm-modal">Confirm & Ship</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openShipModal(btn) {
        document.getElementById('modal_order_id').value = btn.getAttribute('data-id');
        document.getElementById('v_modal_name').innerText = btn.getAttribute('data-cust-name');
        document.getElementById('v_modal_phone').innerText = btn.getAttribute('data-cust-phone');
        document.getElementById('modal_cust_addr').value = btn.getAttribute('data-cust-addr');
        document.getElementById('modal_ship_addr').value = btn.getAttribute('data-cust-addr');
        document.getElementById('shipModal').style.display = 'flex';
    }
    function closeShipModal() { document.getElementById('shipModal').style.display = 'none'; }

    window.onclick = function(e) {
        if (e.target.className == 'modal-overlay') { closeShipModal(); }
    }
</script>

<style>
    /* PRESERVED DESIGN */
    .stat-badge { background: #F5F1E9; padding: 8px 15px; border-radius: 30px; border: 1px solid #e0e0e0; font-weight: 600; color: #5E5343; display: flex; align-items: center; gap: 8px; font-size: 14px; }
    .table-card { background: #F5F1E9; border-radius: 12px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03); border: 1px solid #e0e0e0; padding: 20px; }
    .admin-table { width: 100%; border-collapse: collapse; }
    .admin-table th { text-align: left; padding: 18px 15px; border-bottom: 2px solid #B2A48C; color: #5E5343; font-size: 12px; text-transform: uppercase; font-weight: 700; }
    .admin-table td { padding: 15px; border-bottom: 1px solid #ddd; vertical-align: top; color: #555; font-size: 14px; }
    .id-badge { background: #fff; padding: 3px 8px; border-radius: 4px; border: 1px solid #B2A48C; font-weight: 700; }
    .addr-block { font-size: 11px; color: #666; margin-top: 4px; padding-left: 8px; border-left: 2px solid #ccc; line-height: 1.4; }
    .addr-label { font-size: 9px; text-transform: uppercase; color: #999; display: block; }
    .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; display: inline-block; }
    .status-pending { background: #fff3cd; color: #856404; }
    .status-shipped { background: #e3f2fd; color: #0c5460; }
    .status-delivered { background: #d4edda; color: #155724; }
    .status-cancelled { background: #f8d7da; color: #721c24; }
    .btn-action-outline { display: inline-flex; align-items: center; gap: 5px; background: #fff; color: #5E5343; border: 1px solid #5E5343; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer; text-decoration: none; }
    .btn-action-outline:hover { background: #5E5343; color: #fff; }
    .btn-ship { background: #5E5343; color: #fff; border: none; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer; }
    .btn-deliver { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; text-decoration: none; }
    .btn-cancel { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; text-decoration: none; }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6); align-items: center; justify-content: center; z-index: 1000; }
    .modal-card { background: #F5F1E9; width: 500px; padding: 30px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2); border: 2px solid #B2A48C; position: relative; }
    .close-modal { position: absolute; right: 20px; top: 15px; font-size: 24px; cursor: pointer; color: #999; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-size: 11px; font-weight: 600; color: #5E5343; margin-bottom: 5px; }
    .form-group input, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; outline: none; }
    .btn-confirm-modal { background: #5E5343; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; }
    .btn-cancel-modal { background: #fff; border: 1px solid #5E5343; padding: 10px 20px; border-radius: 6px; font-size: 12px; cursor: pointer; }
    
    /* TABLE TRACKING SYSTEM */
    .tracking-line { display: flex; align-items: center; justify-content: space-between; max-width: 140px; }
    .tracking-line .line { flex: 1; height: 2px; background: #ddd; margin: 0 5px; }
    .tracking-line .line.active { background: #27ae60; }
    .tracking-line .step { color: #ddd; font-size: 15px; }
    .tracking-line .step.completed { color: #27ae60; }
</style>

<?php include 'include/footer.php'; ?><?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Security Check
if (!isset($_SESSION['seller_logged_in']) || $_SESSION['seller_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

include '../include/connection.php';
$seller_id = $_SESSION['id'];

// --- 2. HANDLE ACTIONS (Shipping / Delivery / Cancel) ---

// A. Confirm Shipping (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_shipping'])) {
    $order_id = intval($_POST['order_id']);
    $courier_name = $_POST['courier_name'];
    $tracking_id = $_POST['tracking_id'];
    $courier_address = $_POST['courier_address']; 
    $final_shipping_address = $_POST['final_shipping_address'];

    if (!empty($courier_name) && !empty($tracking_id) && !empty($courier_address) && !empty($final_shipping_address)) {
        // Ownership Check
        $check = $conn->prepare("SELECT o.id FROM orders o JOIN product p ON o.product_id = p.id WHERE o.id = ? AND p.seller_id = ?");
        $check->bind_param("ii", $order_id, $seller_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            // Update courier info, tracking, and set shipped_at = NOW()
            $stmt = $conn->prepare("UPDATE orders SET status = 'Shipped', courier_name = ?, tracking_id = ?, courier_address = ?, shipping_address = ?, shipped_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssssi", $courier_name, $tracking_id, $courier_address, $final_shipping_address, $order_id);
            if ($stmt->execute()) {
                echo "<script>window.location.href='my_orders.php';</script>";
            }
            $stmt->close();
        }
        $check->close();
    }
}

// B. Deliver / Cancel (GET)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $order_id = intval($_GET['id']);
    $action = $_GET['action'];
    $new_status = ($action == 'deliver') ? 'Delivered' : (($action == 'cancel') ? 'Cancelled' : '');

    if ($new_status != '') {
        $check = $conn->prepare("SELECT o.id FROM orders o JOIN product p ON o.product_id = p.id WHERE o.id = ? AND p.seller_id = ?");
        $check->bind_param("ii", $order_id, $seller_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            
            // If Delivered, update delivered_at = NOW()
            if ($new_status == 'Delivered') {
                $stmt = $conn->prepare("UPDATE orders SET status = ?, delivered_at = NOW() WHERE id = ?");
                $stmt->bind_param("si", $new_status, $order_id);
                $stmt->execute();
                $stmt->close();

                // FIX: Automatically update COD payment to 'Success' when delivered
                $payStmt = $conn->prepare("UPDATE payment SET payment_status = 'Success' WHERE order_id = ? AND payment_method = 'COD'");
                $payStmt->bind_param("i", $order_id);
                $payStmt->execute();
                $payStmt->close();
                
            } else {
                $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $new_status, $order_id);
                $stmt->execute();
                $stmt->close();
            }
        }
        $check->close();
    }
    header("Location: my_orders.php");
    exit;
}

// --- 3. FETCH DETAILED ORDER DATA (Database Strict Mode Fixed) ---
$sql = "SELECT 
            o.id as order_id, o.created_at, o.shipped_at, o.delivered_at, o.price, o.status, o.shipping_address as courier_ship_addr, o.courier_name, o.tracking_id, o.courier_address,
            p.name as product_name, p.image, p.description as prod_desc,
            u.username as customer_username,
            a.name as reg_name, a.email as reg_email, a.phone as reg_phone, 
            a.delivery_address as profile_addr, a.city as profile_city, a.pincode as profile_pin,
            pay.payment_method, pay.transaction_id, pay.payment_status
        FROM orders o
        JOIN product p ON o.product_id = p.id
        JOIN users u ON o.user_id = u.id
        LEFT JOIN address a ON a.user_id = u.id AND a.id = (SELECT MAX(id) FROM address WHERE user_id = u.id)
        LEFT JOIN payment pay ON o.id = pay.order_id
        WHERE p.seller_id = ?
        ORDER BY o.created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
} else {
    die("Database Error: " . $conn->error);
}

include 'include/header.php';
?>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">
    <div style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin-bottom: 5px;">Manage Orders</h1>
            <p style="color: #999; font-size: 13px; margin-top: 5px;">Process shipments and delivery updates</p>
        </div>
        <div class="stat-badge">
            <i class="fas fa-shopping-bag"></i> <?= count($orders); ?> Total Orders
        </div>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">Sr. No.</th>
                        <th width="20%">Customer Profile</th>
                        <th width="24%">Product & Payment</th>
                        <th width="22%">Tracking Flow</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $counter = 1;
                    foreach ($orders as $row):
                        $status = !empty($row['status']) ? $row['status'] : 'Pending';
                        $registered_address = $row['profile_addr'] . ", " . $row['profile_city'] . " - " . $row['profile_pin'];
                        $courier_ship_addr = $row['courier_ship_addr'];
                        
                        $is_shipped = ($status == 'Shipped' || $status == 'Delivered');
                        $is_delivered = ($status == 'Delivered');
                        $is_cancelled = ($status == 'Cancelled');
                    ?>
                        <tr>
                            <td><span class="id-badge"><?= $counter++; ?></span></td>

                            <td>
                                <div style="font-weight: 600; color: #5E5343; margin-bottom: 3px;">
                                    <?= htmlspecialchars($row['reg_name'] ?? $row['customer_username']); ?>
                                </div>
                                <div style="font-size: 11px; color: #888;">
                                    <i class="fas fa-phone-alt"></i> <?= htmlspecialchars($row['reg_phone']); ?>
                                </div>
                                <div class="addr-block">
                                    <span class="addr-label"><i class="fas fa-user"></i> Registered Addr:</span>
                                    <span style="font-size: 10px;"><?= htmlspecialchars(mb_strimwidth($registered_address, 0, 40, "...")); ?></span>
                                </div>
                            </td>

                            <td>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                    <img src="../<?= htmlspecialchars($row['image']); ?>" style="width: 40px; height: 40px; border-radius: 6px; object-fit: cover; border: 1px solid #ccc;">
                                    <div>
                                        <div style="font-weight: 600; font-size: 13px; color: #5E5343;"><?= htmlspecialchars($row['product_name']); ?></div>
                                        <div style="color: #A1937A; font-size: 12px; font-weight: 700;">&#8377;<?= number_format($row['price']); ?></div>
                                    </div>
                                </div>
                                <div style="font-size: 10px; color: #777; line-height: 1.4; border-top: 1px dashed #eee; padding-top: 6px;">
                                    <strong style="color: #555;"><?= htmlspecialchars($row['payment_method'] ?? 'N/A'); ?></strong> 
                                    (<span style="font-weight: 600; color: <?= ($row['payment_status'] == 'Success' || $row['payment_status'] == 'Paid') ? '#27ae60' : (($row['payment_status'] == 'Failed') ? '#c0392b' : '#f39c12'); ?>;"><?= htmlspecialchars($row['payment_status'] ?? 'Pending'); ?></span>)<br>
                                    <span style="font-family: monospace;">ID: <?= htmlspecialchars($row['transaction_id'] ?? 'N/A'); ?></span>
                                </div>
                            </td>

                            <td>
                                <?php if (!$is_cancelled): ?>
                                    <div class="tracking-line" style="margin-bottom: 8px;">
                                        <div class="step completed" title="Preparing"><i class="fas fa-box"></i></div>
                                        <div class="line <?= $is_shipped ? 'active' : ''; ?>"></div>
                                        <div class="step <?= $is_shipped ? 'completed' : '' ?>" title="Shipped"><i class="fas fa-truck"></i></div>
                                        <div class="line <?= $is_delivered ? 'active' : ''; ?>"></div>
                                        <div class="step <?= $is_delivered ? 'completed' : '' ?>" title="Delivered"><i class="fas fa-check-circle"></i></div>
                                    </div>
                                    
                                    <div style="font-size: 10px; line-height: 1.4; color: #666;">
                                        <?php if ($status == 'Pending'): ?>
                                            <strong style="color:#f39c12;"><i class="fas fa-spinner fa-spin"></i> Preparing Order</strong><br>
                                            <span style="color:#888; font-size:9px;">Placed: <?= date('d-m-Y, h:i A', strtotime($row['created_at'])); ?></span>
                                        
                                        <?php elseif ($status == 'Shipped'): ?>
                                            <strong style="color:#27ae60;"><i class="fas fa-truck"></i> <?= htmlspecialchars($row['courier_name']); ?></strong><br>
                                            <span style="color:#888; font-size:9px;">Shipped: <?= !empty($row['shipped_at']) ? date('d-m-Y, h:i A', strtotime($row['shipped_at'])) : 'In Transit'; ?></span>
                                        
                                        <?php elseif ($status == 'Delivered'): ?>
                                            <strong style="color:#155724;"><i class="fas fa-home"></i> Delivered To:</strong><br>
                                            <span style="color:#888; font-size:9px;">On: <?= !empty($row['delivered_at']) ? date('d-m-Y, h:i A', strtotime($row['delivered_at'])) : 'Completed'; ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #c0392b; font-size: 11px; font-weight: 600;"><i class="fas fa-ban"></i> Cancelled</span>
                                <?php endif; ?>
                            </td>

                            <td><span class="status-badge status-<?= strtolower($status); ?>"><?= ucfirst($status); ?></span></td>

                            <td>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <a href="view_orders.php?id=<?= $row['order_id']; ?>" class="btn-action-outline"><i class="fas fa-eye"></i> View</a>
                                    
                                    <?php if ($status == 'Pending'): ?>
                                        <button type="button" class="btn-ship" 
                                            data-id="<?= $row['order_id']; ?>" 
                                            data-cust-name="<?= htmlspecialchars($row['reg_name']); ?>"
                                            data-cust-phone="<?= htmlspecialchars($row['reg_phone']); ?>"
                                            data-cust-addr="<?= htmlspecialchars($registered_address); ?>" 
                                            onclick="openShipModal(this)"><i class="fas fa-truck"></i> Ship</button>
                                        <a href="my_orders.php?action=cancel&id=<?= $row['order_id']; ?>" class="btn-cancel" onclick="return confirm('Cancel Order?');"><i class="fas fa-times"></i> Cancel</a>
                                    
                                    <?php elseif ($status == 'Shipped'): ?>
                                        <a href="my_orders.php?action=deliver&id=<?= $row['order_id']; ?>" class="btn-deliver" onclick="return confirm('Confirm Delivery?');"><i class="fas fa-check-circle"></i> Deliver</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div id="shipModal" class="modal-overlay">
    <div class="modal-card">
        <span class="close-modal" onclick="closeShipModal()">&times;</span>
        <h2 style="font-family: 'Playfair Display', serif; color: #5E5343; border-bottom: 1px solid #B2A48C; padding-bottom: 15px; margin-bottom: 20px; font-size: 20px;">Enter Shipment Details</h2>
        
        <div id="modal_user_details" style="background: #fdfdfd; padding: 12px; border-radius: 8px; border: 1px solid #eee; margin-bottom: 15px;">
            <p style="margin:0; font-size: 13px; color: #5E5343;"><strong>Customer:</strong> <span id="v_modal_name"></span></p>
            <p style="margin:0; font-size: 13px; color: #5E5343;"><strong>Contact:</strong> <span id="v_modal_phone"></span></p>
        </div>

        <form method="post">
            <input type="hidden" name="order_id" id="modal_order_id">
            
            <div class="form-group">
                <label>User's Registered Address (Reference)</label>
                <textarea id="modal_cust_addr" rows="2" readonly style="background: #f9f9f9; color: #777; font-size: 12px; border-color: #eee;"></textarea>
            </div>

            <div class="form-group">
                <label style="color: #A1937A;"><i class="fas fa-map-pin"></i> Courier / Dispatch Office Location</label>
                <textarea name="courier_address" rows="2" required placeholder="Enter the courier office address..."></textarea>
            </div>

            <div class="form-group">
                <label style="color: #A1937A;"><i class="fas fa-truck-loading"></i> Final Shipping Destination</label>
                <textarea name="final_shipping_address" id="modal_ship_addr" rows="3" required placeholder="Verify exactly where to deliver..."></textarea>
            </div>

            <div style="display: flex; gap: 15px;">
                <div class="form-group" style="flex:1;"><label>Courier Name</label><input type="text" name="courier_name" placeholder="e.g. BlueDart" required></div>
                <div class="form-group" style="flex:1;"><label>Tracking ID</label><input type="text" name="tracking_id" placeholder="TRK-9900" required></div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 5px;">
                <button type="button" class="btn-cancel-modal" onclick="closeShipModal()">Back</button>
                <button type="submit" name="confirm_shipping" class="btn-confirm-modal">Confirm & Ship</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openShipModal(btn) {
        document.getElementById('modal_order_id').value = btn.getAttribute('data-id');
        document.getElementById('v_modal_name').innerText = btn.getAttribute('data-cust-name');
        document.getElementById('v_modal_phone').innerText = btn.getAttribute('data-cust-phone');
        document.getElementById('modal_cust_addr').value = btn.getAttribute('data-cust-addr');
        document.getElementById('modal_ship_addr').value = btn.getAttribute('data-cust-addr');
        document.getElementById('shipModal').style.display = 'flex';
    }
    function closeShipModal() { document.getElementById('shipModal').style.display = 'none'; }

    window.onclick = function(e) {
        if (e.target.className == 'modal-overlay') { closeShipModal(); }
    }
</script>

<style>
    /* PRESERVED DESIGN */
    .stat-badge { background: #F5F1E9; padding: 8px 15px; border-radius: 30px; border: 1px solid #e0e0e0; font-weight: 600; color: #5E5343; display: flex; align-items: center; gap: 8px; font-size: 14px; }
    .table-card { background: #F5F1E9; border-radius: 12px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03); border: 1px solid #e0e0e0; padding: 20px; }
    .admin-table { width: 100%; border-collapse: collapse; }
    .admin-table th { text-align: left; padding: 18px 15px; border-bottom: 2px solid #B2A48C; color: #5E5343; font-size: 12px; text-transform: uppercase; font-weight: 700; }
    .admin-table td { padding: 15px; border-bottom: 1px solid #ddd; vertical-align: top; color: #555; font-size: 14px; }
    .id-badge { background: #fff; padding: 3px 8px; border-radius: 4px; border: 1px solid #B2A48C; font-weight: 700; }
    .addr-block { font-size: 11px; color: #666; margin-top: 4px; padding-left: 8px; border-left: 2px solid #ccc; line-height: 1.4; }
    .addr-label { font-size: 9px; text-transform: uppercase; color: #999; display: block; }
    .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; display: inline-block; }
    .status-pending { background: #fff3cd; color: #856404; }
    .status-shipped { background: #e3f2fd; color: #0c5460; }
    .status-delivered { background: #d4edda; color: #155724; }
    .status-cancelled { background: #f8d7da; color: #721c24; }
    .btn-action-outline { display: inline-flex; align-items: center; gap: 5px; background: #fff; color: #5E5343; border: 1px solid #5E5343; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer; text-decoration: none; }
    .btn-action-outline:hover { background: #5E5343; color: #fff; }
    .btn-ship { background: #5E5343; color: #fff; border: none; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; cursor: pointer; }
    .btn-deliver { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; text-decoration: none; }
    .btn-cancel { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: 600; text-decoration: none; }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6); align-items: center; justify-content: center; z-index: 1000; }
    .modal-card { background: #F5F1E9; width: 500px; padding: 30px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2); border: 2px solid #B2A48C; position: relative; }
    .close-modal { position: absolute; right: 20px; top: 15px; font-size: 24px; cursor: pointer; color: #999; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-size: 11px; font-weight: 600; color: #5E5343; margin-bottom: 5px; }
    .form-group input, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; outline: none; }
    .btn-confirm-modal { background: #5E5343; color: #fff; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; }
    .btn-cancel-modal { background: #fff; border: 1px solid #5E5343; padding: 10px 20px; border-radius: 6px; font-size: 12px; cursor: pointer; }
    
    /* TABLE TRACKING SYSTEM */
    .tracking-line { display: flex; align-items: center; justify-content: space-between; max-width: 140px; }
    .tracking-line .line { flex: 1; height: 2px; background: #ddd; margin: 0 5px; }
    .tracking-line .line.active { background: #27ae60; }
    .tracking-line .step { color: #ddd; font-size: 15px; }
    .tracking-line .step.completed { color: #27ae60; }
</style>

<?php include 'include/footer.php'; ?>