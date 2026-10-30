<?php
include '../include/connection.php';
session_start();

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: manage_users.php');
    exit;
}

$user_id = intval($_GET['id']);

// --- 1. HANDLE STATUS UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['user_status'];
    // FIX: Removed `AND role = 'customer'` from the query
    $up_stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $up_stmt->bind_param("si", $new_status, $user_id);
    $up_stmt->execute();
    $up_stmt->close();
    header("Location: view_users.php?id=" . $user_id);
    exit;
}

// --- 2. FETCH USER DETAILS ---
// FIX: Removed `AND role = 'customer'` from the query
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("<div style='padding:50px; text-align:center;'>Customer not found. <a href='manage_users.php'>Go Back</a></div>");
}

// --- 3. FETCH USER STATS (Total Spent & Total Orders) ---
$total_spent = 0;
$total_orders = 0;

$stat_sql = "SELECT SUM(price) as spent, COUNT(id) as ord_cnt FROM orders WHERE user_id = ? AND status != 'Cancelled'";
$st = $conn->prepare($stat_sql);
$st->bind_param("i", $user_id);
$st->execute();
$st->bind_result($total_spent, $total_orders);
$st->fetch();
$st->close();

// --- 4. FETCH USER ORDER HISTORY ---
$orders = [];
$o_sql = "SELECT o.id, o.price, o.status, o.created_at, p.name as product_name, p.image 
          FROM orders o 
          JOIN product p ON o.product_id = p.id 
          WHERE o.user_id = ? ORDER BY o.created_at DESC";
$o_stmt = $conn->prepare($o_sql);
$o_stmt->bind_param("i", $user_id);
$o_stmt->execute();
$o_res = $o_stmt->get_result();
while ($row = $o_res->fetch_assoc()) {
    $orders[] = $row;
}
$o_stmt->close();

include 'include/header.php';
?>

<main style="flex: 1; padding: 30px; background-color: #fff; overflow-y: auto;">

    <div style="max-width: 1000px; margin: 0 auto;">

        <a href="manage_users.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Customers</a>

        <div class="user-header-card">
            <div style="display: flex; align-items: center; gap: 20px;">
                <div class="user-avatar">
                    <?= strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                <div>
                    <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin: 0; font-size: 28px;">
                        <?= htmlspecialchars($user['username']); ?>
                    </h1>
                    <p style="color: #777; margin: 5px 0 0 0; font-size: 13px;">Customer ID: #<?= $user['id']; ?> | Joined: <?= date('d-m-Y, h:i A', strtotime($user['created_at'])); ?></p>
                </div>
            </div>

            <div class="status-action-box">
                <form method="POST" style="display: flex; gap: 10px; align-items: center;">
                    <span style="font-size: 13px; font-weight: 600; color: #5E5343;">Account Status:</span>
                    <select name="user_status" class="status-select">
                        <option value="approved" <?= ($user['status'] == 'approved' || empty($user['status'])) ? 'selected' : ''; ?>>Active / Approved</option>
                        <option value="suspended" <?= $user['status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                    <button type="submit" name="update_status" class="btn-update">Update</button>
                </form>
            </div>
        </div>

        <div class="stats-row">
            <div class="perf-stat">
                <i class="fas fa-shopping-bag" style="color:#3498db;"></i>
                <div><span>Total Orders</span><strong><?= $total_orders; ?> Purchases</strong></div>
            </div>
            <div class="perf-stat">
                <i class="fas fa-rupee-sign" style="color:#27ae60;"></i>
                <div><span>Total Lifetime Spent</span><strong>&#8377;<?= number_format($total_spent ?? 0, 2); ?></strong></div>
            </div>
        </div>

        <div class="info-card">
            <h3><i class="fas fa-address-card"></i> Personal Information & Address</h3>
            <div class="info-grid">
                <div>
                    <table class="info-table">
                        <tr>
                            <th>Email Address:</th>
                            <td><?= htmlspecialchars($user['email']); ?></td>
                        </tr>
                        <tr>
                            <th>Phone Number:</th>
                            <td><?= htmlspecialchars($user['phone'] ?? 'Not Provided'); ?></td>
                        </tr>
                        <tr>
                            <th>Date of Birth:</th>
                            <td><?= !empty($user['dob']) ? date('d-m-Y', strtotime($user['dob'])) : 'Not Provided'; ?></td>
                        </tr>
                        <tr>
                            <th>Gender:</th>
                            <td style="text-transform: capitalize;"><?= htmlspecialchars($user['gender'] ?? 'Not Provided'); ?></td>
                        </tr>
                    </table>
                </div>
                <div>
                    <div style="background: #fdfdfd; padding: 15px; border: 1px dashed #B2A48C; border-radius: 8px; height: 100%;">
                        <strong style="color:#5E5343; font-size:13px; display:block; margin-bottom:5px;">Saved Delivery Address:</strong>
                        <p style="color:#555; font-size:14px; margin:0; line-height:1.6;">
                            <?= nl2br(htmlspecialchars($user['address'] ?? 'No address saved on profile.')); ?>
                            <br><br>
                            <strong>City:</strong> <?= htmlspecialchars($user['city'] ?? '-'); ?> <br>
                            <strong>Pincode:</strong> <?= htmlspecialchars($user['pincode'] ?? '-'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="full-card">
            <h3><i class="fas fa-box-open"></i> Customer's Order History</h3>

            <?php if (count($orders) > 0): ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Product Details</th>
                                <th>Amount Paid</th>
                                <th>Status</th>
                                <th>Date & Time Placed</th> </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $ord): ?>
                                <tr>
                                    <td style="font-weight:bold; color:#5E5343;">#<?= $ord['id']; ?></td>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:10px;">
                                            <img src="../<?= htmlspecialchars($ord['image']); ?>" style="width:40px; height:40px; border-radius:6px; object-fit:cover; border:1px solid #ccc;">
                                            <span style="font-weight:600; color:#5E5343;"><?= htmlspecialchars($ord['product_name']); ?></span>
                                        </div>
                                    </td>
                                    <td style="font-weight:bold; color:#A1937A;">&#8377;<?= number_format($ord['price'], 2); ?></td>
                                    <td>
                                        <?php
                                        $st = strtolower($ord['status']);
                                        $cls = ($st == 'delivered' || $st == 'completed') ? 'success' : (($st == 'cancelled') ? 'danger' : (($st == 'shipped') ? 'info' : 'warning'));
                                        ?>
                                        <span class="badge badge-<?= $cls; ?>"><?= ucfirst($st); ?></span>
                                    </td>
                                    <td style="font-size:13px; color:#666; font-weight: 500;"><?= date('d-m-Y, h:i A', strtotime($ord['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; background: #F5F1E9; border-radius: 12px; border: 1px dashed #B2A48C; color: #999;">
                    <i class="fas fa-shopping-cart" style="font-size: 40px; color: #B2A48C; margin-bottom: 15px;"></i>
                    <p>This customer hasn't placed any orders yet.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<style>
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #5E5343;
        text-decoration: none;
        margin-bottom: 20px;
        font-size: 14px;
        font-weight: 600;
    }

    .back-link:hover {
        color: #A1937A;
        text-decoration: underline;
    }

    /* Top Card */
    .user-header-card {
        background: #F5F1E9;
        padding: 30px;
        border-radius: 12px;
        border: 1px solid #B2A48C;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 20px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.04);
    }

    .user-avatar {
        width: 65px;
        height: 65px;
        background: #5E5343;
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        font-weight: bold;
        box-shadow: 0 4px 10px rgba(94, 83, 67, 0.3);
    }

    .status-action-box {
        background: #fff;
        padding: 12px 20px;
        border-radius: 8px;
        border: 1px solid #ddd;
    }

    .status-select {
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px solid #ccc;
        font-family: 'Poppins', sans-serif;
        outline: none;
        background: #F5F1E9;
        color: #5E5343;
        font-weight: 600;
    }

    .btn-update {
        background: #5E5343;
        color: #fff;
        border: none;
        padding: 6px 15px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: 0.3s;
    }

    .btn-update:hover {
        background: #A1937A;
    }

    /* Performance Row */
    .stats-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 30px;
    }

    .perf-stat {
        background: #fff;
        border: 1px solid #e0e0e0;
        padding: 20px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        gap: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
    }

    .perf-stat i {
        font-size: 30px;
        background: #F5F1E9;
        width: 60px;
        height: 60px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #eee;
    }

    .perf-stat span {
        display: block;
        font-size: 12px;
        color: #888;
        text-transform: uppercase;
        margin-bottom: 5px;
    }

    .perf-stat strong {
        font-size: 24px;
        color: #5E5343;
    }

    /* Info Grid */
    .info-card {
        background: #fff;
        padding: 30px;
        border-radius: 10px;
        border: 1px solid #e0e0e0;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
        margin-bottom: 30px;
    }

    .info-card h3 {
        font-size: 16px;
        color: #5E5343;
        margin-top: 0;
        margin-bottom: 20px;
        border-bottom: 2px solid #F5F1E9;
        padding-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .info-card h3 i {
        color: #A1937A;
    }

    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }

    .info-table {
        width: 100%;
        border-collapse: collapse;
    }

    .info-table th {
        text-align: left;
        padding: 12px 0;
        color: #777;
        font-weight: 500;
        font-size: 13px;
        width: 35%;
        border-bottom: 1px dashed #eee;
    }

    .info-table td {
        padding: 12px 0;
        color: #333;
        font-weight: 600;
        font-size: 14px;
        border-bottom: 1px dashed #eee;
    }

    /* Tables */
    .full-card {
        background: #fff;
        padding: 25px;
        border-radius: 10px;
        border: 1px solid #e0e0e0;
        margin-bottom: 30px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
    }

    .full-card h3 {
        font-size: 16px;
        color: #5E5343;
        margin-top: 0;
        margin-bottom: 15px;
        border-bottom: 2px solid #F5F1E9;
        padding-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .full-card h3 i {
        color: #A1937A;
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
        border-bottom: 2px solid #B2A48C;
        background: #fdfdfd;
        font-weight: 700;
    }

    .admin-table td {
        padding: 15px 12px;
        border-bottom: 1px solid #eee;
        vertical-align: middle;
        color: #444;
        font-size: 14px;
    }

    .admin-table tr:hover {
        background: #F5F1E9;
    }

    /* Badges */
    .badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 11px;
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

    @media (max-width: 800px) {

        .info-grid,
        .stats-row {
            grid-template-columns: 1fr;
            gap: 15px;
        }
    }
</style>

<?php include 'include/footer.php'; ?>