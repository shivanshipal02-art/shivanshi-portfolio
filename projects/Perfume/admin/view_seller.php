<?php
include '../include/connection.php';

// Import PHPMailer classes for automation
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: manage_sellers.php');
    exit;
}

$seller_id = intval($_GET['id']);

/**
 * Helper Function: Send Professional Seller Notification Emails
 * Matched exactly to manage_sellers.php logic and content
 */
function sendSellerStatusEmail($email, $owner_name, $business_name, $type) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'perfumenest3012@gmail.com'; 
        $mail->Password   = 'mfli ckub inlb ggxe'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('perfumenest3012@gmail.com', 'Perfume Nest Admin');
        $mail->addAddress($email);
        $mail->isHTML(true);

        $b_name_display = !empty($business_name) ? $business_name : $owner_name;

        // --- EMAIL CONTENT BASED ON ACTION TYPE ---
        if ($type === 'approval') {
            $mail->Subject = 'Welcome to Perfume Nest! Your Seller Account is Approved 🎉';
            $mainTitle = "Congratulations!";
            $subTitle = "Your Seller Account has been Approved";
            $messageBody = "We are thrilled to officially welcome <strong>$b_name_display</strong> to the Perfume Nest marketplace! Our team has verified your details and activated your portal.";
            $actionButton = "<a href='http://localhost/BCA/Perfume/slogin.php' style='display: inline-block; background-color: #5E5343; color: #fff; text-decoration: none; padding: 14px 35px; border-radius: 50px; font-weight: bold;'>sLogin to Dashboard</a>";
            $infoSection = "<li><strong>Dashboard Access:</strong> Log in now using your registered credentials.</li><li><strong>Upload Products:</strong> Start listing your premium fragrances immediately.</li><li><strong>Manage Orders:</strong> Receive and ship orders to customers directly.</li>";
        } 
        elseif ($type === 'suspension') {
            $mail->Subject = 'Urgent Account Notice: Your Seller Account is Suspended';
            $mainTitle = "Account Suspended";
            $subTitle = "Access Temporarily Restricted";
            $messageBody = "Please be advised that the seller account for <strong>$b_name_display</strong> has been suspended by the administration due to a review of your recent activity or policy compliance.";
            $actionButton = "";
            $infoSection = "<li><strong>Product Visibility:</strong> Your listings are currently hidden from customers.</li><li><strong>Pending Orders:</strong> You must still fulfill any active orders already placed.</li><li><strong>Next Steps:</strong> Please contact support to appeal this suspension.</li>";
        } 
        elseif ($type === 'activation') {
            $mail->Subject = 'Good News! Your Seller Account has been Re-Activated';
            $mainTitle = "Account Restored";
            $subTitle = "Your Account is Now Active";
            $messageBody = "We are pleased to inform you that the suspension on your account <strong>$b_name_display</strong> has been lifted. Your full access has been restored.";
            $actionButton = "<a href='http://localhost/BCA/Perfume/slogin.php' style='display: inline-block; background-color: #27ae60; color: #fff; text-decoration: none; padding: 14px 35px; border-radius: 50px; font-weight: bold;'>Login Now</a>";
            $infoSection = "<li><strong>Listing Sync:</strong> Your products are being re-indexed and will appear live shortly.</li><li><strong>Dashboard Access:</strong> Your login is now fully functional.</li>";
        }

        $mail->Body = "
        <div style='background-color: #f9f9f9; padding: 40px 20px; font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 40px 30px; border-radius: 12px; border-top: 5px solid #5E5343; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05);'>
                <h1 style='color: #1a1a1a; font-size: 28px; margin-bottom: 5px;'>$mainTitle</h1>
                <h3 style='color: #A1937A; font-weight: normal; margin-top: 0; margin-bottom: 30px;'>$subTitle</h3>
                <p style='color: #333; font-size: 15px; line-height: 1.6; text-align: left;'>Dear <strong>$owner_name</strong>,</p>
                <p style='color: #333; font-size: 15px; line-height: 1.6; text-align: left;'>$messageBody</p>
                <div style='background-color: #F5F1E9; padding: 25px; border-radius: 8px; margin: 30px 0; text-align: left; border: 1px solid #E6E1D8;'>
                    <h4 style='color: #5E5343; margin-top: 0; font-size: 16px;'>Account Details & Instructions:</h4>
                    <ul style='color: #555; line-height: 1.8; padding-left: 20px; font-size: 14px; margin-bottom: 0;'>$infoSection</ul>
                </div>
                $actionButton
                <p style='color: #777; font-size: 13px; margin-top: 30px;'><strong>Best Regards, Perfume Nest Admin Team</strong></p>
            </div>
        </div>";

        $mail->send();
    } catch (Exception $e) { /* Silently fail */ }
}

// --- 1. HANDLE STATUS UPDATE WITH EMAIL AUTOMATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['seller_status'];
    
    // Fetch details first to check current status and send appropriate email
    $res = $conn->query("SELECT email, owner_name, business_name, status FROM sellers WHERE id = $seller_id");
    $seller_data = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;

    if ($seller_data && !empty($new_status)) {
        // Trigger Email Logic only for Approval and Suspension
        if ($new_status === 'approved' && $seller_data['status'] !== 'approved') {
            $mailType = ($seller_data['status'] === 'suspended') ? 'activation' : 'approval';
            sendSellerStatusEmail($seller_data['email'], $seller_data['owner_name'], $seller_data['business_name'], $mailType);
        } elseif ($new_status === 'suspended' && $seller_data['status'] !== 'suspended') {
            sendSellerStatusEmail($seller_data['email'], $seller_data['owner_name'], $seller_data['business_name'], 'suspension');
        }

        $up_stmt = $conn->prepare("UPDATE sellers SET status = ? WHERE id = ?");
        $up_stmt->bind_param("si", $new_status, $seller_id);
        $up_stmt->execute();
        $up_stmt->close();
    }
    header("Location: view_seller.php?id=" . $seller_id);
    exit;
}

// --- 2. FETCH SELLER DETAILS FOR DISPLAY ---
$sql = "SELECT * FROM sellers WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$seller = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$seller) {
    die("<div style='padding:50px; text-align:center;'>Seller not found. <a href='manage_sellers.php'>Go Back</a></div>");
}

// --- 3. FETCH PERFORMANCE STATS ---
$total_revenue = 0; $total_orders = 0; $avg_rating = 0;
$stat_res = $conn->query("SELECT SUM(o.price) as rev, COUNT(o.id) as ord_cnt FROM orders o JOIN product p ON o.product_id = p.id WHERE p.seller_id = $seller_id AND o.status != 'Cancelled'")->fetch_assoc();
$total_revenue = $stat_res['rev'] ?? 0;
$total_orders = $stat_res['ord_cnt'] ?? 0;

$rating_res = $conn->query("SELECT AVG(r.rating) as avg_r FROM reviews r JOIN product p ON r.product_id = p.id WHERE p.seller_id = $seller_id")->fetch_assoc();
$avg_rating = $rating_res['avg_r'] ?? 0;

// --- 4. FETCH PRODUCTS FOR INVENTORY ---
$products = [];
$p_res = $conn->query("SELECT p.*, COUNT(DISTINCT o.id) as units_sold, IFNULL(AVG(r.rating), 0) as prod_rating FROM product p LEFT JOIN orders o ON p.id = o.product_id AND o.status != 'Cancelled' LEFT JOIN reviews r ON p.id = r.product_id WHERE p.seller_id = $seller_id GROUP BY p.id ORDER BY p.id DESC");
if($p_res) { while ($row = $p_res->fetch_assoc()) { $products[] = $row; } }

// --- 5. FETCH RECENT ORDERS ---
$orders = [];
$o_res = $conn->query("SELECT o.id, o.price, o.status, o.created_at, p.name as product_name, u.username as buyer_name, u.phone as buyer_phone FROM orders o JOIN product p ON o.product_id = p.id JOIN users u ON o.user_id = u.id WHERE p.seller_id = $seller_id ORDER BY o.created_at DESC LIMIT 15");
if($o_res) { while ($row = $o_res->fetch_assoc()) { $orders[] = $row; } }

// --- 6. FETCH RECENT REVIEWS ---
$reviews = [];
$r_res = $conn->query("SELECT r.rating, r.comment, r.created_at, p.name as product_name, u.username as reviewer_name FROM reviews r JOIN product p ON r.product_id = p.id JOIN users u ON r.user_id = u.id WHERE p.seller_id = $seller_id ORDER BY r.created_at DESC LIMIT 15");
if($r_res) { while ($row = $r_res->fetch_assoc()) { $reviews[] = $row; } }

include 'include/header.php';
?>

<main style="flex: 1; padding: 30px; background-color: #fff; overflow-y: auto;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <a href="manage_sellers.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Sellers</a>

        <div class="seller-header-card">
            <div style="display: flex; align-items: center; gap: 20px;">
                <div class="seller-avatar"><i class="fas fa-store"></i></div>
                <div>
                    <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin: 0; font-size: 28px;">
                        <?= htmlspecialchars(!empty($seller['business_name']) ? $seller['business_name'] : $seller['owner_name']); ?>
                    </h1>
                    <p style="color: #777; margin: 5px 0 0 0; font-size: 13px;">Owner: <?= htmlspecialchars($seller['owner_name']); ?> | Joined: <?= date('d-m-Y, h:i A', strtotime($seller['created_at'])); ?></p>
                </div>
            </div>

            <div class="status-action-box">
                <form method="POST" style="display: flex; gap: 10px; align-items: center;">
                    <span style="font-size: 12px; font-weight: 600; color: #888;">Update:</span>
                    <select name="seller_status" class="status-select" required>
                        <option value="" disabled selected>Current: <?= ucfirst($seller['status']); ?></option>
                        <option value="approved" <?= $seller['status'] == 'approved' ? 'hidden' : ''; ?>>Approved</option>
                        <option value="suspended" <?= $seller['status'] == 'suspended' ? 'hidden' : ''; ?>>Suspended</option>
                    </select>
                    <button type="submit" name="update_status" class="btn-update">Confirm</button>
                </form>
            </div>
        </div>

        <div class="stats-row">
            <div class="perf-stat"><i class="fas fa-rupee-sign" style="color:#27ae60;"></i>
                <div><span>Total Revenue Generated</span><strong>&#8377;<?= number_format($total_revenue); ?></strong></div>
            </div>
            <div class="perf-stat"><i class="fas fa-box-open" style="color:#3498db;"></i>
                <div><span>Products Sold</span><strong><?= $total_orders; ?> Units</strong></div>
            </div>
            <div class="perf-stat"><i class="fas fa-star" style="color:#f39c12;"></i>
                <div><span>Average Seller Rating</span><strong><?= number_format($avg_rating, 1); ?> / 5.0</strong></div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="info-card">
                <h3><i class="fas fa-user-circle"></i> Contact Information</h3>
                <table class="info-table">
                    <tr><th>Email:</th><td><?= htmlspecialchars($seller['email']); ?></td></tr>
                    <tr><th>Phone:</th><td><?= htmlspecialchars($seller['phone'] ?? 'N/A'); ?></td></tr>
                    <tr><th>City:</th><td><?= htmlspecialchars($seller['city'] ?? 'N/A'); ?> - <?= htmlspecialchars($seller['pincode'] ?? 'N/A'); ?></td></tr>
                    <tr><th>Address:</th><td><?= nl2br(htmlspecialchars($seller['address'] ?? 'Not provided')); ?></td></tr>
                </table>
            </div>

            <div class="info-card">
                <h3><i class="fas fa-file-invoice-dollar"></i> Business Documents</h3>
                <table class="info-table">
                    <tr><th>GST Number:</th><td><span class="doc-badge"><?= htmlspecialchars($seller['gst_number'] ?: 'N/A'); ?></span></td></tr>
                    <tr><th>PAN Card:</th><td><span class="doc-badge"><?= htmlspecialchars($seller['pan_card'] ?: 'N/A'); ?></span></td></tr>
                    <tr><th>Aadhar Card:</th><td><span class="doc-badge"><?= htmlspecialchars($seller['aadhar_card'] ?: 'N/A'); ?></span></td></tr>
                </table>
            </div>

            <div class="info-card">
                <h3><i class="fas fa-graduation-cap"></i> Professional Background</h3>
                <table class="info-table">
                    <tr><th>Education / Qualification:</th><td><?= htmlspecialchars($seller['education'] ?: 'Not Provided'); ?></td></tr>
                    <tr><th>Years of Experience:</th><td><?= htmlspecialchars($seller['experience'] ?: 'Not Provided'); ?></td></tr>
                </table>
            </div>
        </div>

        <div class="data-sections">
            
            <div class="full-card">
                <h3><i class="fas fa-spray-can"></i> Product Inventory</h3>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Image</th><th>Product Name</th><th>Added On</th><th>Stock</th><th>Units Sold</th><th>Avg Rating</th></tr>
                        </thead>
                        <tbody>
                            <?php if (count($products) > 0): foreach ($products as $p): ?>
                                <tr>
                                    <td><img src="../<?= htmlspecialchars($p['image']); ?>" style="width:40px; height:40px; border-radius:4px; object-fit:cover;"></td>
                                    <td style="font-weight:600; color:#5E5343;"><?= htmlspecialchars($p['name']); ?><br><span style="font-size:11px; color:#999; font-weight:normal;">&#8377;<?= number_format($p['price']); ?> | <?= ucfirst($p['gender']); ?></span></td>
                                    <td style="font-size:12px; color:#555; font-weight:500;"><i class="far fa-clock" style="color:#A1937A;"></i> <?= date('d-m-Y, h:i A', strtotime($p['created_at'])); ?></td>
                                    <td style="font-weight:600; color: <?= $p['stock'] > 0 ? '#3498db' : '#e74c3c' ?>;"><?= $p['stock'] > 0 ? $p['stock'] . ' left' : 'Out of Stock' ?></td>
                                    <td style="font-weight:bold; color:#27ae60;"><?= $p['units_sold']; ?></td>
                                    <td style="color:#f39c12; font-size:12px;"><i class="fas fa-star"></i> <?= number_format($p['prod_rating'], 1); ?></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="6" style="text-align: center; padding: 30px; color: #999;">No products uploaded yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="full-card">
                <h3><i class="fas fa-shopping-cart"></i> Recent Buyer Orders</h3>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Order ID</th><th>Buyer Details</th><th>Purchased Item</th><th>Amount</th><th>Status</th><th>Date & Time</th></tr>
                        </thead>
                        <tbody>
                            <?php if (count($orders) > 0): foreach ($orders as $o): ?>
                                <tr>
                                    <td style="font-weight:bold; color:#5E5343;">#<?= $o['id']; ?></td>
                                    <td><i class="fas fa-user" style="color:#ccc;"></i> <?= htmlspecialchars($o['buyer_name']); ?><br><span style="font-size:11px; color:#888;">Ph: <?= htmlspecialchars($o['buyer_phone'] ?? 'N/A'); ?></span></td>
                                    <td style="font-weight:600; color:#A1937A;"><?= htmlspecialchars($o['product_name']); ?></td>
                                    <td style="font-weight:bold; color:#5E5343;">&#8377;<?= number_format($o['price'], 2); ?></td>
                                    <td><span class="badge-status badge-<?= strtolower($o['status']) == 'delivered' ? 'active' : (strtolower($o['status']) == 'cancelled' ? 'rejected' : 'pending'); ?>"><?= $o['status']; ?></span></td>
                                    <td style="font-size:12px; color:#666; font-weight:500;"><i class="far fa-calendar-alt"></i> <?= date('d-m-Y, h:i A', strtotime($o['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="6" style="text-align:center; padding:20px; color:#999;">No orders received yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="full-card">
                <h3><i class="fas fa-comments"></i> Product Reviews</h3>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Reviewer</th><th>Product</th><th>Rating</th><th>Comment</th><th>Date & Time</th></tr>
                        </thead>
                        <tbody>
                            <?php if (count($reviews) > 0): foreach ($reviews as $r): ?>
                                <tr>
                                    <td style="font-weight:600; color:#5E5343;"><i class="fas fa-user-circle" style="color:#ccc;"></i> <?= htmlspecialchars($r['reviewer_name']); ?></td>
                                    <td style="font-size:13px; color:#A1937A; font-weight:bold;"><?= htmlspecialchars($r['product_name']); ?></td>
                                    <td style="color:#f39c12; font-size:12px;">
                                        <?php for ($i = 1; $i <= 5; $i++) { echo ($i <= $r['rating']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star" style="color:#ccc;"></i>'; } ?>
                                    </td>
                                    <td style="font-style:italic; color:#555; font-size:13px;">"<?= htmlspecialchars($r['comment']); ?>"</td>
                                    <td style="font-size:12px; color:#666; font-weight:500;"><i class="far fa-clock"></i> <?= date('d-m-Y, h:i A', strtotime($r['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="5" style="text-align:center; padding:20px; color:#999;">No reviews posted yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</main>

<style>
    .back-link { display: inline-flex; align-items: center; gap: 8px; color: #5E5343; text-decoration: none; margin-bottom: 20px; font-size: 14px; font-weight: 600; }
    .back-link:hover { color: #A1937A; text-decoration: underline; }
    .seller-header-card { background: #F5F1E9; padding: 30px; border-radius: 12px; border: 1px solid #B2A48C; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap:20px;}
    .seller-avatar { width: 60px; height: 60px; background: #5E5343; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 26px; }
    .status-action-box { background: #fff; padding: 10px 15px; border-radius: 8px; border: 1px solid #ddd; }
    .status-select { padding: 8px; border-radius: 6px; border: 1px solid #ccc; font-family: 'Poppins'; font-size: 13px; font-weight: 600; background: #fdfdfd; cursor: pointer; outline: none; }
    .btn-update { background: #5E5343; color: #fff; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; transition: 0.3s; font-weight: 600; font-size: 13px; }
    .btn-update:hover { background: #A1937A; }
    
    .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
    .perf-stat { background: #fff; border: 1px solid #e0e0e0; padding: 20px; border-radius: 10px; display: flex; align-items: center; gap: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.02);}
    .perf-stat i { font-size: 30px; background: #F5F1E9; width: 60px; height: 60px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
    .perf-stat span { display: block; font-size: 11px; color: #888; text-transform: uppercase; margin-bottom: 3px; }
    .perf-stat strong { font-size: 20px; color: #5E5343; }
    
    /* Responsive Grid for Info Cards */
    .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .info-card { background: #fff; padding: 25px; border-radius: 10px; border: 1px solid #e0e0e0; box-shadow: 0 2px 10px rgba(0,0,0,0.02);}
    .info-card h3 { font-size: 15px; color: #5E5343; border-bottom: 1px solid #eee; padding-bottom: 8px; margin-top: 0; display:flex; align-items:center; gap:8px;}
    .info-card h3 i { color: #A1937A; }
    .info-table { width: 100%; border-collapse: collapse; }
    .info-table th { text-align: left; padding: 10px 0; color: #777; width: 45%; border-bottom: 1px dashed #eee; font-size: 12px; font-weight:500;}
    .info-table td { padding: 10px 0; color: #333; font-weight: 600; border-bottom: 1px dashed #eee; font-size: 13px; }
    .doc-badge { background: #f9f9f9; border: 1px dashed #A1937A; padding: 3px 8px; border-radius: 4px; font-family: monospace; letter-spacing: 1px;}
    
    .full-card { background: #fff; padding: 25px; border-radius: 10px; border: 1px solid #e0e0e0; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.02);}
    .full-card h3 { font-size: 16px; color: #5E5343; margin-top: 0; margin-bottom: 15px; border-bottom: 2px solid #F5F1E9; padding-bottom: 10px; display: flex; align-items: center; gap: 10px; }
    .full-card h3 i { color: #A1937A; }
    
    .table-responsive { overflow-x: auto; }
    .admin-table { width: 100%; border-collapse: collapse; }
    .admin-table th { text-align: left; padding: 12px; color: #5E5343; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #B2A48C; background: #fdfdfd; }
    .admin-table td { padding: 12px; border-bottom: 1px solid #eee; font-size: 13px; vertical-align: middle;}
    .admin-table tr:hover { background: #F5F1E9; }

    .badge-status { padding: 4px 10px; border-radius: 50px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
    .badge-active { background: #eafaf1; color: #27ae60; border: 1px solid #bce8ce; }
    .badge-pending { background: #fef9e7; color: #d4ac0d; border: 1px solid #fad7a1; }
    .badge-rejected { background: #fdecea; color: #e67e22; border: 1px solid #f8c9c4; }

    @media (max-width: 900px) {
        .stats-row { grid-template-columns: 1fr; }
    }
</style>

<?php include 'include/footer.php'; ?>