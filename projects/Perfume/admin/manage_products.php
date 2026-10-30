<?php
include '../include/connection.php';
include 'include/header.php';

// Determine view mode: 'sellers' list OR 'products' list for a specific seller
$seller_id = isset($_GET['seller_id']) ? intval($_GET['seller_id']) : 0;
$view_mode = ($seller_id > 0) ? 'products' : 'sellers';

$seller_name = "";

if ($view_mode === 'products') {
    // FIX: Fetch seller details from the 'sellers' table instead of 'users'
    $s_stmt = $conn->prepare("SELECT owner_name, business_name FROM sellers WHERE id = ?");
    $s_stmt->bind_param("i", $seller_id);
    $s_stmt->execute();
    $s_res = $s_stmt->get_result();
    if ($s_row = $s_res->fetch_assoc()) {
        $seller_name = !empty($s_row['business_name']) ? $s_row['business_name'] : $s_row['owner_name'];
    }
    $s_stmt->close();

    // Fetch Products for this specific seller (Newest first)
    $sql = "SELECT * FROM product WHERE seller_id = ? ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // FIX: Fetch ONLY APPROVED Sellers from the 'sellers' table and alias owner_name to username for the UI
    $sql = "SELECT id, owner_name as username, business_name, gst_number, pan_card, city FROM sellers WHERE status = 'approved' ORDER BY id DESC";
    $result = $conn->query($sql);
}
?>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">

    <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin: 0; font-size: 26px;">Manage Products</h1>
            <p style="color: #999; font-size: 13px; margin-top: 5px;">
                <?= ($view_mode === 'sellers') ? "Select an approved business to manage its inventory." : "Viewing products for <b>" . htmlspecialchars($seller_name) . "</b>"; ?>
            </p>
        </div>

        <div style="display: flex; gap: 15px;">
            <?php if ($view_mode === 'sellers'): ?>
                <div class="stat-badge">
                    <i class="fas fa-store"></i> <?= $result->num_rows; ?> Approved Businesses
                </div>
            <?php else: ?>
                <a href="manage_products.php" class="btn-back">
                    <i class="fas fa-chevron-left"></i> Back to Businesses
                </a>
                <div class="stat-badge">
                    <i class="fas fa-box"></i> <?= $result->num_rows; ?> Items
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
                            <th style="width: 70px;">Sr.</th>
                            <th>Business Details</th>
                            <th>Tax/ID Info</th>
                            <th>Location</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php
                            $counter = 1;
                            while ($row = $result->fetch_assoc()):
                            ?>
                                <tr>
                                    <td><span class="id-badge"><?= $counter++; ?></span></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div class="avatar-circle"><i class="fas fa-briefcase"></i></div>
                                            <div>
                                                <div style="font-weight: 700; color: #5E5343; font-size: 15px;">
                                                    <?= htmlspecialchars($row['business_name'] ?? 'No Brand Name'); ?>
                                                </div>
                                                <div style="font-size: 12px; color: #A1937A;">Owner: <?= htmlspecialchars($row['username']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 12px; line-height: 1.6;">
                                            <div><span style="color:#aaa;">GST:</span> <span style="font-family: monospace; color:#555;"><?= htmlspecialchars($row['gst_number'] ?: 'N/A'); ?></span></div>
                                            <div><span style="color:#aaa;">PAN:</span> <span style="font-family: monospace; color:#555;"><?= htmlspecialchars($row['pan_card'] ?: 'N/A'); ?></span></div>
                                        </div>
                                    </td>
                                    <td style="font-size: 13px; color: #666;">
                                        <i class="fas fa-map-marker-alt" style="color: #B2A48C;"></i> <?= htmlspecialchars($row['city'] ?: 'Not Set'); ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="manage_products.php?seller_id=<?= $row['id']; ?>" class="btn-manage">
                                            Manage Inventory <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding: 50px; color: #999;">No approved sellers found in the system.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                <?php else: ?>
                    <thead>
                        <tr>
                            <th style="width: 70px;">Sr.</th>
                            <th>Image</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php
                            $counter = 1;
                            while ($p = $result->fetch_assoc()):
                            ?>
                                <tr>
                                    <td><span class="id-badge"><?= $counter++; ?></span></td>
                                    <td>
                                        <?php if (!empty($p['image'])): ?>
                                            <img src="../<?= htmlspecialchars($p['image']); ?>" class="product-thumb">
                                        <?php else: ?>
                                            <div class="no-img">No Img</div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight: 600; color: #5E5343;"><?= htmlspecialchars($p['name']); ?></td>
                                    <td style="text-transform: capitalize; font-size: 13px; color: #888;"><?= htmlspecialchars($p['gender']); ?></td>
                                    <td style="font-weight: 700; color: #A1937A;">&#8377;<?= number_format($p['price'], 2); ?></td>
                                    <td style="text-align: right;">
                                        <div style="display: inline-flex; gap: 8px;">
                                            <a href="view_product.php?id=<?= $p['id']; ?>" class="btn-icon btn-view" title="View Product"><i class="fas fa-eye"></i></a>
                                            <a href="delete_product.php?id=<?= $p['id']; ?>" class="btn-icon btn-delete" onclick="return confirm('Delete this product permanently?');" title="Delete Item"><i class="fas fa-trash-alt"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 50px; color: #999;">This business has not uploaded any products yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                <?php endif; ?>
            </table>
        </div>
    </div>

</main>

<style>
    /* UI Badges & Buttons */
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
        font-size: 12px;
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
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #ccc;
        background: #fff;
    }

    .no-img {
        width: 50px;
        height: 50px;
        background: #eee;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        color: #999;
    }

    .btn-icon {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: 0.3s;
        font-size: 13px;
        text-decoration: none;
        color: #fff;
    }

    /* BUTTON STYLING */
    .btn-view {
        background: #3498db;
    }

    .btn-view:hover {
        background: #2980b9;
    }

    .btn-delete {
        background: #c0392b;
    }

    .btn-delete:hover {
        background: #a93226;
    }
</style>

<?php include 'include/footer.php'; ?>