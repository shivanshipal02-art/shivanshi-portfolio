<?php
include '../include/connection.php';
include 'include/header.php';

// Determine view mode: 'sellers' list OR 'orders' list for a specific seller
$seller_id = isset($_GET['seller_id']) ? intval($_GET['seller_id']) : 0;
$view_mode = ($seller_id > 0) ? 'orders' : 'sellers';

$seller_name = "";

if ($view_mode === 'orders') {
    // Fetch seller details from the 'sellers' table
    $s_stmt = $conn->prepare("SELECT owner_name, business_name FROM sellers WHERE id = ?");
    $s_stmt->bind_param("i", $seller_id);
    $s_stmt->execute();
    $s_res = $s_stmt->get_result();
    if ($s_row = $s_res->fetch_assoc()) {
        $seller_name = !empty($s_row['business_name']) ? $s_row['business_name'] : $s_row['owner_name'];
    }
    $s_stmt->close();

    // FIX: Fetch Orders for this specific seller (Added estimated_delivery_date and shipping_address)
    $sql = "SELECT o.id as order_id, o.created_at, o.shipped_at, o.delivered_at, o.price, o.status, o.courier_name, o.tracking_id, o.courier_address, o.estimated_delivery_date, o.shipping_address as admin_ship_addr,
                   p.name as product_name, p.image, 
                   c.username as customer_name,
                   pay.payment_method, pay.payment_status, pay.transaction_id
            FROM orders o 
            JOIN product p ON o.product_id = p.id 
            JOIN users c ON o.user_id = c.id 
            LEFT JOIN payment pay ON o.id = pay.order_id
            WHERE p.seller_id = ? 
            ORDER BY o.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Fetch ONLY APPROVED Sellers from the 'sellers' table
    $sql = "SELECT id, owner_name as username, business_name, city, gst_number, pan_card FROM sellers WHERE status = 'approved' ORDER BY id DESC";
    $result = $conn->query($sql);
}
?>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">

    <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin: 0; font-size: 26px;">Manage Purchases</h1>
            <p style="color: #999; font-size: 13px; margin-top: 5px;">
                <?= ($view_mode === 'sellers') ? "Select an approved business to view their orders." : "Viewing orders for <b>" . htmlspecialchars($seller_name) . "</b>"; ?>
            </p>
        </div>

        <div style="display: flex; gap: 15px;">
            <?php if ($view_mode === 'sellers'): ?>
                <div class="stat-badge">
                    <i class="fas fa-store"></i> <?= $result->num_rows; ?> Approved Sellers
                </div>
            <?php else: ?>
                <a href="manage_purchase.php" class="btn-back">
                    <i class="fas fa-chevron-left"></i> Back to Sellers
                </a>
                <div class="stat-badge">
                    <i class="fas fa-shopping-bag"></i> <?= $result->num_rows; ?> Orders
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="admin-table">
                <?php if ($view_mode === 'sellers'): ?>
                    <thead>
                        <tr>
                            <th style="width: 70px;">Sr. No.</th>
                            <th>Business / Seller Name</th>
                            <th>Tax/ID Info</th>
                            <th>Location</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php $s_counter = 1;
                            while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong style="color: #5E5343;"><?= $s_counter++; ?></strong></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div class="avatar-circle"><i class="fas fa-store"></i></div>
                                            <div>
                                                <div style="font-weight: 700; color: #5E5343; font-size: 14px;">
                                                    <?= htmlspecialchars($row['business_name'] ?? $row['username']); ?>
                                                </div>
                                                <div style="font-size: 11px; color: #888;">Owner: <?= htmlspecialchars($row['username']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-size: 12px; color: #666; line-height: 1.5;">
                                        GST: <?= htmlspecialchars($row['gst_number'] ?: 'N/A'); ?><br>
                                        PAN: <?= htmlspecialchars($row['pan_card'] ?: 'N/A'); ?>
                                    </td>
                                    <td style="font-size: 13px; color: #666;"><i class="fas fa-map-marker-alt" style="color: #B2A48C;"></i> <?= htmlspecialchars($row['city'] ?: 'Not Set'); ?></td>
                                    <td style="text-align: right;">
                                        <a href="manage_purchase.php?seller_id=<?= $row['id']; ?>" class="btn-manage">
                                            View Orders <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding: 50px; color: #999;">No approved sellers found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <?php else: ?>
                    <thead>
                        <tr>
                            <th style="width: 60px;">Sr. No.</th>
                            <th>Product Details</th>
                            <th>Delivery Receiver</th>
                            <th>Tracking Flow</th>
                            <th>Amount & Payment</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php $o_counter = 1;
                            while ($ord = $result->fetch_assoc()):
                                $status = !empty($ord['status']) ? $ord['status'] : 'Pending';
                                $status_lower = strtolower($status);
                                $cls = ($status_lower == 'delivered' || $status_lower == 'completed') ? 'success' : (($status_lower == 'cancelled') ? 'danger' : (($status_lower == 'shipped') ? 'info' : 'warning'));

                                // Logic for tracking flow display
                                $is_shipped = ($status_lower == 'shipped' || $status_lower == 'delivered' || $status_lower == 'completed');
                                $is_delivered = ($status_lower == 'delivered' || $status_lower == 'completed');
                                $is_cancelled = ($status_lower == 'cancelled');

                                // FIX: Parse Delivery Address Properly
                                $delivery_address = $ord['admin_ship_addr'];
                                $addr_parts = explode("\n", $delivery_address);
                                if (count($addr_parts) >= 2) {
                                    $name_phone = explode(" | ", $addr_parts[0]);
                                    $display_name = trim($name_phone[0]);
                                    $display_phone = str_replace("+91 ", "", trim($name_phone[1] ?? ''));
                                    $display_addr = trim($addr_parts[1]);
                                } else {
                                    $display_name = $ord['customer_name'];
                                    $display_phone = 'N/A';
                                    $display_addr = $delivery_address;
                                }
                            ?>
                                <tr>
                                    <td><strong style="color: #5E5343; font-size: 13px;"><?= $o_counter++; ?></strong></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <?php if (!empty($ord['image'])): ?>
                                                <img src="../<?= htmlspecialchars($ord['image']); ?>" class="product-thumb">
                                            <?php else: ?>
                                                <div class="no-img">No Img</div>
                                            <?php endif; ?>
                                            <div>
                                                <div style="font-weight: 600; color: #5E5343; font-size:13px;"><?= htmlspecialchars($ord['product_name']); ?></div>
                                                <div style="font-size: 11px; color: #999; margin-top:3px;">ID: #<?= $ord['order_id']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-size: 13px; color: #555;">
                                        <div style="font-weight: 600; color: #5E5343; margin-bottom: 3px;">
                                            <?= htmlspecialchars($display_name); ?>
                                        </div>
                                        <div style="font-size: 11px; color: #888;">
                                            <i class="fas fa-phone-alt"></i> <?= htmlspecialchars($display_phone); ?>
                                        </div>
                                        <div style="font-size: 10px; color: #666; margin-top: 4px; padding-left: 8px; border-left: 2px solid #ccc; line-height: 1.4;">
                                            <?= htmlspecialchars(mb_strimwidth($display_addr, 0, 40, "...")); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <?php if (!$is_cancelled): ?>
                                            <div class="tracking-line" style="margin-bottom: 5px;">
                                                <div class="step <?= $status == 'Pending' ? 'current-active' : 'completed' ?>" title="Preparing"><i class="fas fa-box"></i></div>
                                                <div class="line <?= $is_shipped ? 'active' : ''; ?>"></div>
                                                <div class="step <?= $status == 'Shipped' ? 'current-active' : ($is_delivered ? 'completed' : '') ?>" title="Shipped"><i class="fas fa-truck"></i></div>
                                                <div class="line <?= $is_delivered ? 'active' : ''; ?>"></div>
                                                <div class="step <?= $status == 'Delivered' ? 'current-active completed' : '' ?>" title="Delivered"><i class="fas fa-check-circle"></i></div>
                                            </div>

                                            <div style="font-size: 10px; line-height: 1.4; color: #666;">
                                                <?php if ($status == 'Pending'): ?>
                                                    <strong style="color:#f39c12;">Preparing Order</strong><br>
                                                    <span style="color:#888; font-size:9px;">Placed: <?= date('d-m-Y, h:i A', strtotime($ord['created_at'])); ?></span><br>
                                                    <?php if(!empty($ord['estimated_delivery_date'])): ?>
                                                        <span style="color:#3498db; font-size:9px;"><i class="far fa-calendar-check"></i> Exp: <?= date('d-m-Y', strtotime($ord['estimated_delivery_date'])); ?></span>
                                                    <?php endif; ?>

                                                <?php elseif ($status == 'Shipped'): ?>
                                                    <strong style="color:#27ae60;"><i class="fas fa-truck"></i> <?= htmlspecialchars($ord['courier_name']); ?></strong><br>
                                                    <span style="color:#888; font-size:9px;">Shipped: <?= !empty($ord['shipped_at']) ? date('d-m-Y, h:i A', strtotime($ord['shipped_at'])) : 'In Transit'; ?></span><br>
                                                    <?php if(!empty($ord['estimated_delivery_date'])): ?>
                                                        <span style="color:#3498db; font-size:9px;"><i class="far fa-calendar-check"></i> Exp: <?= date('d-m-Y', strtotime($ord['estimated_delivery_date'])); ?></span>
                                                    <?php endif; ?>

                                                <?php elseif ($status == 'Delivered'): ?>
                                                    <strong style="color:#155724;"><i class="fas fa-check-circle"></i> Delivered</strong><br>
                                                    <span style="color:#888; font-size:9px;">On: <?= !empty($ord['delivered_at']) ? date('d-m-Y, h:i A', strtotime($ord['delivered_at'])) : 'Completed'; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #c0392b; font-size: 12px; font-weight: 600;"><i class="fas fa-ban"></i> Cancelled</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div style="font-weight: 700; color: #A1937A;">&#8377;<?= number_format($ord['price'], 2); ?></div>
                                        <div style="font-size: 10px; color: #777; margin-top: 3px; line-height: 1.4;">
                                            <strong style="color: #555;"><?= htmlspecialchars($ord['payment_method'] ?? 'N/A'); ?></strong>
                                            (<span style="color: <?= ($ord['payment_status'] == 'Success' || $ord['payment_status'] == 'Paid') ? '#27ae60' : '#f39c12'; ?>;"><?= htmlspecialchars($ord['payment_status'] ?? 'Pending'); ?></span>)<br>
                                            <span style="font-family: monospace;">ID: <?= htmlspecialchars($ord['transaction_id'] ?? 'N/A'); ?></span>
                                        </div>
                                    </td>

                                    <td><span class="status-badge status-<?= $cls; ?>"><?= ucfirst($status); ?></span></td>

                                    <td style="text-align: right;">
                                        <a href="view_orders.php?id=<?= $ord['order_id']; ?>" class="btn-icon btn-view" title="View Full Order Details"><i class="fas fa-eye"></i> View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding: 50px; color: #999;">This seller has no orders yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>
    </div>

</main>

<style>
    /* Badges & Buttons */
    .stat-badge {
        background: #F5F1E9;
        padding: 10px 20px;
        border-radius: 50px;
        border: 1px solid #e0e0e0;
        font-weight: 600;
        color: #5E5343;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
    }

    .stat-badge i {
        color: #A1937A;
    }

    .btn-back {
        text-decoration: none;
        color: #5E5343;
        font-weight: 600;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: 0.3s;
    }

    .btn-back:hover {
        color: #A1937A;
    }

    .btn-manage {
        background: #5E5343;
        color: #fff;
        text-decoration: none;
        padding: 8px 18px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        transition: 0.3s;
        display: inline-block;
    }

    .btn-manage:hover {
        background: #A1937A;
        transform: translateY(-2px);
    }

    .btn-view {
        background: #3498db;
        color: #fff;
        padding: 8px 15px;
        border-radius: 6px;
        font-size: 12px;
        text-decoration: none;
        font-weight: 600;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .btn-view:hover {
        background: #2980b9;
    }

    /* Table Design */
    .table-card {
        background: #F5F1E9;
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03);
        border: 1px solid #e0e0e0;
        overflow: hidden;
        padding: 25px;
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
    }

    .admin-table th {
        text-align: left;
        padding: 15px;
        color: #5E5343;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-bottom: 2px solid #B2A48C;
        font-weight: 800;
    }

    .admin-table td {
        padding: 15px;
        border-bottom: 1px solid #ddd;
        vertical-align: middle;
        color: #444;
    }

    .admin-table tr:hover {
        background: #fff;
    }

    .admin-table tr:last-child td {
        border-bottom: none;
    }

    /* Visual Components */
    .avatar-circle {
        width: 38px;
        height: 38px;
        background: #fff;
        color: #5E5343;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        border: 1px solid #B2A48C;
    }

    .id-badge {
        background: #fff;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 800;
        border: 1px solid #B2A48C;
        color: #5E5343;
    }

    .product-thumb {
        width: 45px;
        height: 45px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #ccc;
        background: #fff;
    }

    .no-img {
        width: 45px;
        height: 45px;
        background: #eee;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        color: #999;
    }

    /* === TRACKING FLOW WITH ANIMATION === */
    .tracking-line {
        display: flex;
        align-items: center;
        justify-content: space-between;
        max-width: 140px;
        margin-top: 5px;
    }

    .tracking-line .step {
        color: #ddd;
        font-size: 14px;
        position: relative;
    }

    .tracking-line .step.completed {
        color: #27ae60;
    }

    .tracking-line .line {
        flex: 1;
        height: 2px;
        background: #ddd;
        margin: 0 5px;
    }

    .tracking-line .line.active {
        background: #27ae60;
    }

    /* Glowing Animation for Active Step */
    @keyframes pulseGlow {
        0% {
            text-shadow: 0 0 0 rgba(243, 156, 18, 0.7);
            color: #f39c12;
            transform: scale(1);
        }

        50% {
            text-shadow: 0 0 10px rgba(243, 156, 18, 0.9);
            color: #f1c40f;
            transform: scale(1.2);
        }

        100% {
            text-shadow: 0 0 0 rgba(243, 156, 18, 0.7);
            color: #f39c12;
            transform: scale(1);
        }
    }

    @keyframes pulseGlowGreen {
        0% {
            text-shadow: 0 0 0 rgba(39, 174, 96, 0.7);
            color: #27ae60;
            transform: scale(1);
        }

        50% {
            text-shadow: 0 0 10px rgba(39, 174, 96, 0.9);
            color: #2ecc71;
            transform: scale(1.2);
        }

        100% {
            text-shadow: 0 0 0 rgba(39, 174, 96, 0.7);
            color: #27ae60;
            transform: scale(1);
        }
    }

    .tracking-line .step.current-active i {
        animation: pulseGlow 1.5s infinite;
        border-radius: 50%;
    }

    .tracking-line .step.current-active.completed i {
        animation: pulseGlowGreen 1.5s infinite;
    }

    /* Status Badges */
    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: inline-block;
    }

    .status-warning {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeeba;
    }

    .status-info {
        background: #e3f2fd;
        color: #0c5460;
        border: 1px solid #bee5eb;
    }

    .status-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .status-danger {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
</style>

<?php include 'include/footer.php'; ?>