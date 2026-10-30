<?php
include '../include/connection.php';

// ==========================================
// HANDLE SUSPEND / ACTIVATE ACTIONS
// ==========================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $user_id = intval($_GET['id']);

    // FIX: Removed `AND role = 'customer'` from the query
    if ($action === 'suspend') {
        $conn->query("UPDATE users SET status = 'suspended' WHERE id = $user_id");
    } elseif ($action === 'activate') {
        $conn->query("UPDATE users SET status = 'approved' WHERE id = $user_id");
    }

    // Redirect to clear the URL parameters
    header("Location: manage_users.php");
    exit;
}

include 'include/header.php';

// Fetch all customers - DESCENDING ORDER (Newest user first)
// FIX: Removed `WHERE role = 'customer'` from the query
$sql = "SELECT * FROM users ORDER BY id DESC";
$result = $conn->query($sql);
?>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">

    <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin: 0; font-size: 24px;">Manage Customers</h1>
            <p style="color: #999; font-size: 13px; margin-top: 5px;">View, suspend, and manage registered customers</p>
        </div>
        <div class="stat-badge">
            <i class="fas fa-users"></i> <?= $result->num_rows; ?> Customers
        </div>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">Sr. No.</th>
                        <th>Customer Name</th>
                        <th>Contact Info</th>
                        <th>Status</th>
                        <th>Joined Date & Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php
                        $counter = 1; // Initialize counter
                        while ($row = $result->fetch_assoc()):
                            $status = $row['status'] ?? 'approved'; // Default to approved if empty
                        ?>
                            <tr>
                                <td><span class="id-badge"><?= $counter++; ?></span></td>

                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div class="avatar-circle"><?= strtoupper(substr($row['username'], 0, 1)); ?></div>
                                        <span style="font-weight: 600; color: #5E5343;"><?= htmlspecialchars($row['username']); ?></span>
                                    </div>
                                </td>

                                <td>
                                    <div style="font-size: 13px; color: #666;">
                                        <div style="margin-bottom: 3px;">
                                            <i class="fas fa-envelope" style="width: 15px; color: #B2A48C;"></i> <?= htmlspecialchars($row['email']); ?>
                                        </div>
                                        <div>
                                            <i class="fas fa-phone" style="width: 15px; color: #B2A48C;"></i> <?= htmlspecialchars($row['phone'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <?php if ($status === 'suspended'): ?>
                                        <span class="badge-status badge-suspended">Suspended</span>
                                    <?php else: ?>
                                        <span class="badge-status badge-active">Active</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div style="font-size: 13px; color: #666; font-weight: 500;">
                                        <?= date('d-m-Y, h:i A', strtotime($row['created_at'])); ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="action-group">
                                        <a href="view_users.php?id=<?= $row['id']; ?>" class="btn-icon btn-view" title="View Full Details">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <?php if ($status === 'suspended'): ?>
                                            <a href="?action=activate&id=<?= $row['id']; ?>" class="btn-icon btn-activate" title="Activate User" onclick="return confirm('Are you sure you want to activate this user?');">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="?action=suspend&id=<?= $row['id']; ?>" class="btn-icon btn-suspend" title="Suspend User" onclick="return confirm('Are you sure you want to suspend this user? They will not be able to log in.');">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        <?php endif; ?>

                                        <a href="delete_user.php?id=<?= $row['id']; ?>&type=user" class="btn-icon btn-delete" title="Remove User permanently" onclick="return confirm('Are you sure? This will delete the user and their order history permanently.');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding: 40px; color: #999;">No customers found.</td>
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
        gap: 10px;
        font-size: 14px;
    }

    .stat-badge i {
        color: #A1937A;
    }

    .table-card {
        background: #F5F1E9;
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03);
        border: 1px solid #e0e0e0;
        overflow: hidden;
        padding: 20px;
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
    }

    .admin-table th {
        text-align: left;
        padding: 15px;
        background: transparent;
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

    .admin-table tr:hover {
        background: #fff;
    }

    .admin-table tr:last-child td {
        border-bottom: none;
    }

    .id-badge {
        background: #fff;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid #B2A48C;
        color: #5E5343;
        display: inline-block;
        min-width: 30px;
        text-align: center;
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
        font-size: 14px;
        border: 1px solid #B2A48C;
    }

    /* === STATUS BADGES === */
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

    .badge-suspended {
        background: #fdecea;
        color: #c0392b;
        border: 1px solid #f8c9c4;
    }

    /* === BUTTONS & ACTIONS === */
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
        border: none;
        cursor: pointer;
    }

    .btn-view {
        background: #3498db;
    }

    .btn-view:hover {
        background: #2980b9;
    }

    .btn-suspend {
        background: #f39c12;
    }

    .btn-suspend:hover {
        background: #d68910;
    }

    .btn-activate {
        background: #27ae60;
    }

    .btn-activate:hover {
        background: #2ecc71;
    }

    .btn-delete {
        background: #c0392b;
    }

    .btn-delete:hover {
        background: #a93226;
    }
</style>

<?php include 'include/footer.php'; ?>