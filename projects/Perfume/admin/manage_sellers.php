<?php
include '../include/connection.php';

// Import PHPMailer classes for automation
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

/**
 * Helper Function: Send Professional Seller Notification Emails
 * Types: 'approval', 'rejection', 'deletion', 'suspension', 'activation'
 */
function sendSellerStatusEmail($email, $owner_name, $business_name, $type)
{
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
        } elseif ($type === 'rejection') {
            $mail->Subject = 'Update Regarding Your Seller Application - Perfume Nest';
            $mainTitle = "Application Status";
            $subTitle = "Account Application Not Approved";
            $messageBody = "Thank you for your interest. After review, we regret to inform you that your application for <strong>$b_name_display</strong> cannot be approved at this time.";
            $actionButton = "";
            $infoSection = "<li><strong>Verification Failure:</strong> Provided documents did not match our standards.</li><li><strong>Policy Violation:</strong> Your business model does not align with our current niche.</li>";
        } elseif ($type === 'deletion') {
            $mail->Subject = 'Important Account Notice: Account Permanently Removed';
            $mainTitle = "Account Deleted";
            $subTitle = "Your Seller Account has been Terminated";
            $messageBody = "This is a formal notification that the seller account associated with <strong>$b_name_display</strong> has been permanently removed from our platform.";
            $actionButton = "";
            $infoSection = "<li><strong>Data Removal:</strong> All product listings and inventory records have been wiped.</li><li><strong>Access Revoked:</strong> You no longer have access to the seller portal.</li>";
        } elseif ($type === 'suspension') {
            $mail->Subject = 'Urgent Account Notice: Your Seller Account is Suspended';
            $mainTitle = "Account Suspended";
            $subTitle = "Access Temporarily Restricted";
            $messageBody = "Please be advised that the seller account for <strong>$b_name_display</strong> has been suspended by the administration due to a review of your recent activity or policy compliance.";
            $actionButton = "";
            $infoSection = "<li><strong>Product Visibility:</strong> Your listings are currently hidden from customers.</li><li><strong>Pending Orders:</strong> You must still fulfill any active orders already placed.</li><li><strong>Next Steps:</strong> Please contact support to appeal this suspension.</li>";
        } elseif ($type === 'activation') {
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
    } catch (Exception $e) { /* Silently continue */
    }
}

// ==========================================
// HANDLE APPROVE / SUSPEND / REJECT ACTIONS
// ==========================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $user_id = intval($_GET['id']);

    // Fetch details BEFORE action for email accuracy
    $res = $conn->query("SELECT email, owner_name, business_name, status FROM sellers WHERE id = $user_id");
    $seller = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;

    if ($seller) {
        if (($action === 'approve' || $action === 'activate')) {
            $conn->query("UPDATE sellers SET status = 'approved' WHERE id = $user_id");
            // Only send approval email if they were pending/suspended
            if ($seller['status'] !== 'approved') {
                $mailType = ($seller['status'] === 'suspended') ? 'activation' : 'approval';
                sendSellerStatusEmail($seller['email'], $seller['owner_name'], $seller['business_name'], $mailType);
            }
        } elseif ($action === 'suspend') {
            $conn->query("UPDATE sellers SET status = 'suspended' WHERE id = $user_id");
            if ($seller['status'] !== 'suspended') {
                sendSellerStatusEmail($seller['email'], $seller['owner_name'], $seller['business_name'], 'suspension');
            }
        } elseif ($action === 'reject' && $seller['status'] !== 'rejected') {
            $conn->query("UPDATE sellers SET status = 'rejected' WHERE id = $user_id");
            sendSellerStatusEmail($seller['email'], $seller['owner_name'], $seller['business_name'], 'rejection');
        }
    }
    header("Location: manage_sellers.php");
    exit;
}

// ==========================================
// HANDLE DELETE ACTION
// ==========================================
if (isset($_GET['id']) && isset($_GET['type']) && $_GET['type'] === 'seller') {
    $del_id = intval($_GET['id']);
    $res = $conn->query("SELECT email, owner_name, business_name FROM sellers WHERE id = $del_id");
    if ($res && $res->num_rows > 0) {
        $seller = $res->fetch_assoc();
        sendSellerStatusEmail($seller['email'], $seller['owner_name'], $seller['business_name'], 'deletion');
    }
}

include 'include/header.php';

// Fetch all sellers from separate table
$sql = "SELECT * FROM sellers ORDER BY id DESC";
$result = $conn->query($sql);
?>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">

    <div style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin: 0; font-size: 24px;">Manage Sellers</h1>
            <p style="color: #999; font-size: 13px; margin-top: 5px;">Review applications and oversee seller status</p>
        </div>
        <div class="stat-badge">
            <i class="fas fa-user-tie"></i> <?= $result->num_rows; ?> Sellers
        </div>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">Sr. No.</th>
                        <th>Business Info</th>
                        <th>Contact Info</th>
                        <th>Status</th>
                        <th>Joined Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php
                        $counter = 1;
                        while ($row = $result->fetch_assoc()):
                            $status = strtolower($row['status'] ?? 'approved');
                        ?>
                            <tr>
                                <td><span class="id-badge"><?= $counter++; ?></span></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="avatar-circle seller-av"><i class="fas fa-store"></i></div>
                                        <div>
                                            <div style="font-weight: 600; color: #5E5343;"><?= htmlspecialchars($row['business_name'] ?? $row['owner_name']); ?></div>
                                            <div style="font-size: 12px; color: #888;">Owner: <?= htmlspecialchars($row['owner_name']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 13px; color: #666;">
                                        <div style="margin-bottom:2px;"><i class="fas fa-envelope" style="color: #B2A48C; width:15px;"></i> <?= htmlspecialchars($row['email']); ?></div>
                                        <div><i class="fas fa-phone" style="color: #B2A48C; width:15px;"></i> <?= htmlspecialchars($row['phone'] ?? 'N/A'); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($status === 'pending'): ?>
                                        <span class="badge-status badge-pending">Pending Approval</span>
                                    <?php elseif ($status === 'suspended'): ?>
                                        <span class="badge-status badge-suspended">Suspended</span>
                                    <?php elseif ($status === 'rejected'): ?>
                                        <span class="badge-status badge-rejected">Rejected</span>
                                    <?php else: ?>
                                        <span class="badge-status badge-active">Approved</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 13px; color: #666; font-weight: 500;"><?= date('d-m-Y, h:i A', strtotime($row['created_at'])); ?></div>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <a href="view_seller.php?id=<?= $row['id']; ?>" class="btn-icon btn-view" title="View Details"><i class="fas fa-eye"></i></a>

                                        <?php if ($status === 'pending'): ?>
                                            <a href="?action=approve&id=<?= $row['id']; ?>" class="btn-icon btn-activate" title="Approve" onclick="return confirm('Approve this seller account?');"><i class="fas fa-check"></i></a>
                                            <a href="?action=reject&id=<?= $row['id']; ?>" class=\"btn-icon btn-suspend\" title=\"Reject\" onclick=\"return confirm('Reject this seller application?');\"><i class=\"fas fa-times\"></i></a>
                                        <?php elseif ($status === 'suspended' || $status === 'rejected'): ?>
                                            <a href="?action=activate&id=<?= $row['id']; ?>" class="btn-icon btn-activate" title="Activate" onclick="return confirm('Activate this seller account?');"><i class="fas fa-check-circle"></i></a>
                                        <?php else: ?>
                                            <a href="?action=suspend&id=<?= $row['id']; ?>" class="btn-icon btn-suspend" title="Suspend" onclick="return confirm('Suspend this seller?');"><i class="fas fa-ban"></i></a>
                                        <?php endif; ?>

                                        <a href="delete_user.php?id=<?= $row['id']; ?>&type=seller" class="btn-icon btn-delete" title="Delete" onclick="return confirm('Delete ALL data for this seller?');"><i class="fas fa-trash-alt"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding: 40px; color: #999;">No sellers found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<style>
    .stat-badge {
        background: #F5F1E9;
        padding: 8px 20px;
        border-radius: 30px;
        border: 1px solid #e0e0e0;
        font-weight: 600;
        color: #5E5343;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }

    .table-card {
        background: #F5F1E9;
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03);
        border: 1px solid #e0e0e0;
        padding: 20px;
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
    }

    .admin-table th {
        text-align: left;
        padding: 15px;
        color: #5E5343;
        font-size: 13px;
        text-transform: uppercase;
        border-bottom: 2px solid #B2A48C;
        font-weight: 700;
    }

    .admin-table td {
        padding: 15px;
        border-bottom: 1px solid #ddd;
        vertical-align: middle;
        color: #555;
        font-size: 14px;
    }

    .id-badge {
        background: #fff;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid #B2A48C;
        color: #5E5343;
    }

    .avatar-circle {
        width: 35px;
        height: 35px;
        background: #fff;
        color: #5E5343;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        border: 1px solid #B2A48C;
    }

    .badge-status {
        padding: 5px 12px;
        border-radius: 50px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-block;
    }

    .badge-active {
        background: #eafaf1;
        color: #27ae60;
        border: 1px solid #bce8ce;
    }

    .badge-pending {
        background: #fef9e7;
        color: #d4ac0d;
        border: 1px solid #fad7a1;
    }

    .badge-suspended {
        background: #fdecea;
        color: #e67e22;
        border: 1px solid #f8c9c4;
    }

    .badge-rejected {
        background: #f2d7d5;
        color: #c0392b;
        border: 1px solid #e6b0aa;
    }

    .action-group {
        display: flex;
        gap: 8px;
    }

    .btn-icon {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: 0.3s;
        color: #fff;
        font-size: 13px;
        text-decoration: none;
        cursor: pointer;
    }

    .btn-view {
        background: #3498db;
    }

    .btn-suspend {
        background: #f39c12;
    }

    .btn-activate {
        background: #27ae60;
    }

    .btn-delete {
        background: #c0392b;
    }
</style>

<?php include 'include/footer.php'; ?>