<?php
include '../include/connection.php';
session_start();

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: manage_products.php');
    exit;
}

$product_id = intval($_GET['id']);

// --- HANDLE REVIEW DELETION ---
if (isset($_GET['delete_review'])) {
    $review_id = intval($_GET['delete_review']);
    $del_stmt = $conn->prepare("DELETE FROM reviews WHERE id = ? AND product_id = ?");
    $del_stmt->bind_param("ii", $review_id, $product_id);
    $del_stmt->execute();
    $del_stmt->close();
    header("Location: view_product.php?id=" . $product_id);
    exit;
}

// --- FETCH PRODUCT DETAILS & SELLER INFO ---
$sql = "SELECT p.*, s.owner_name as seller_name, s.business_name 
        FROM product p 
        LEFT JOIN sellers s ON p.seller_id = s.id 
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    die("<div style='padding:50px; text-align:center; font-family:sans-serif;'>Product not found. <a href='manage_products.php'>Go Back</a></div>");
}

// Calculate Average Rating
$avg_rating = 0;
$rev_count = 0;
$rating_sql = "SELECT AVG(rating) as avg_rtg, COUNT(id) as total_rev FROM reviews WHERE product_id = ?";
$r_stmt = $conn->prepare($rating_sql);
$r_stmt->bind_param("i", $product_id);
$r_stmt->execute();
$r_stmt->bind_result($avg_rating, $rev_count);
$r_stmt->fetch();
$r_stmt->close();

// --- FETCH PRODUCT REVIEWS ---
$reviews = [];
$rev_sql = "SELECT r.*, u.username as reviewer_name 
            FROM reviews r 
            LEFT JOIN users u ON r.user_id = u.id 
            WHERE r.product_id = ? 
            ORDER BY r.created_at DESC";
$rev_stmt = $conn->prepare($rev_sql);
$rev_stmt->bind_param("i", $product_id);
$rev_stmt->execute();
$rev_res = $rev_stmt->get_result();
while ($row = $rev_res->fetch_assoc()) {
    $reviews[] = $row;
}
$rev_stmt->close();

include 'include/header.php';
?>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">
    <div style="max-width: 1000px; margin: 0 auto;">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div>
                <a href="manage_products.php<?= $product['seller_id'] ? '?seller_id=' . $product['seller_id'] : '' ?>" class="btn-back-link">
                    <i class="fas fa-arrow-left"></i> Back to Products
                </a>
            </div>

            <div style="display: flex; gap: 10px;">
                <a href="delete_product.php?id=<?= $product['id']; ?>" class="btn-action delete" onclick="return confirm('Are you sure you want to completely delete this product?');"><i class="fas fa-trash-alt"></i> Delete Product</a>
            </div>
        </div>

        <div class="product-showcase">
            <div class="product-image">
                <?php if (!empty($product['image'])): ?>
                    <img src="../<?= htmlspecialchars($product['image']); ?>" alt="<?= htmlspecialchars($product['name']); ?>">
                <?php else: ?>
                    <div style="display:flex; align-items:center; justify-content:center; height:100%; color:#999; background:#eee;">No Image Available</div>
                <?php endif; ?>
            </div>

            <div class="product-info">
                <span class="badge-category"><?= htmlspecialchars(ucfirst($product['gender'])); ?>'s Collection</span>

                <h1 class="prod-title"><?= htmlspecialchars($product['name']); ?></h1>

                <div class="prod-price">&#8377;<?= number_format($product['price'], 2); ?></div>

                <div class="rating-summary">
                    <div class="stars">
                        <?php
                        $full_stars = round($avg_rating);
                        for ($i = 1; $i <= 5; $i++) {
                            echo ($i <= $full_stars) ? '<i class="fas fa-star" style="color:#f39c12;"></i>' : '<i class="far fa-star" style="color:#ccc;"></i>';
                        }
                        ?>
                    </div>
                    <span style="font-size:13px; color:#666;">(<?= number_format($avg_rating, 1); ?> Average based on <?= $rev_count; ?> reviews)</span>
                </div>

                <div style="margin-bottom: 20px;">
                    <?php if ($product['stock'] > 10): ?>
                        <span class="stock-badge stock-in">
                            <i class="fas fa-check-circle"></i> In Stock (<?= $product['stock']; ?> units)
                        </span>
                    <?php elseif ($product['stock'] > 0 && $product['stock'] <= 10): ?>
                        <span class="stock-badge stock-low">
                            <i class="fas fa-exclamation-triangle"></i> Low Stock (Only <?= $product['stock']; ?> left)
                        </span>
                    <?php else: ?>
                        <span class="stock-badge stock-out">
                            <i class="fas fa-times-circle"></i> Out of Stock
                        </span>
                    <?php endif; ?>
                </div>

                <div class="seller-info">
                    <i class="fas fa-store"></i>
                    <strong>Seller:</strong> <?= htmlspecialchars(!empty($product['business_name']) ? $product['business_name'] : $product['seller_name']); ?>
                </div>

                <div class="desc-box">
                    <h4 style="margin-top:0; margin-bottom:8px; font-size:13px; color:#5E5343; text-transform:uppercase;">Description</h4>
                    <p><?= nl2br(htmlspecialchars($product['description'])); ?></p>
                </div>
            </div>
        </div>

        <div class="reviews-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-family: 'Playfair Display', serif; color: #5E5343; margin: 0; font-size:22px;">
                    <i class="fas fa-comments" style="color:#A1937A;"></i> Customer Reviews
                </h2>
                <span style="background: #F5F1E9; padding: 5px 15px; border-radius: 20px; font-size: 13px; font-weight: bold; color: #5E5343; border: 1px solid #B2A48C;">
                    Total Reviews: <?= count($reviews); ?>
                </span>
            </div>

            <?php if (count($reviews) > 0): ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Rating</th>
                                <th>Review Comment</th>
                                <th>Date</th>
                                <th style="text-align: center;">Manage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $rev): ?>
                                <tr>
                                    <td style="font-weight:600; color:#5E5343;">
                                        <i class="fas fa-user-circle" style="color:#ccc; margin-right:5px;"></i> <?= htmlspecialchars($rev['reviewer_name']); ?>
                                    </td>
                                    <td>
                                        <?php for ($i = 1; $i <= 5; $i++) {
                                            echo ($i <= $rev['rating']) ? '<i class="fas fa-star" style="color:#f39c12; font-size:12px;"></i>' : '<i class="far fa-star" style="color:#ccc; font-size:12px;"></i>';
                                        } ?>
                                    </td>
                                    <td style="font-style:italic; color:#555; max-width: 300px; line-height: 1.5;">
                                        "<?= htmlspecialchars($rev['comment']); ?>"
                                    </td>
                                    <td style="font-size:12px; color:#888;">
                                        <?= date('d M Y, h:i A', strtotime($rev['created_at'] ?? 'now')); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="?id=<?= $product_id; ?>&delete_review=<?= $rev['id']; ?>" class="btn-del-review" onclick="return confirm('Are you sure you want to delete this review?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align:center; padding:40px; background:#F5F1E9; border-radius:8px; border:1px dashed #B2A48C;">
                    <i class="far fa-comment-dots" style="font-size:40px; color:#B2A48C; margin-bottom:15px;"></i>
                    <p style="color:#666; margin:0;">No reviews have been submitted for this product yet.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<style>
    /* Buttons */
    .btn-back-link {
        text-decoration: none;
        color: #5E5343;
        font-weight: 600;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: 0.3s;
    }

    .btn-back-link:hover {
        color: #A1937A;
    }

    .btn-action {
        padding: 10px 20px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: 0.3s;
        color: #fff;
    }

    .btn-action.edit {
        background: #5E5343;
    }

    .btn-action.edit:hover {
        background: #A1937A;
    }

    .btn-action.delete {
        background: #e74c3c;
    }

    .btn-action.delete:hover {
        background: #c0392b;
    }

    /* Product Showcase */
    .product-showcase {
        display: flex;
        background: #F5F1E9;
        border-radius: 12px;
        border: 1px solid #e0e0e0;
        padding: 30px;
        gap: 40px;
        margin-bottom: 40px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03);
    }

    .product-image {
        flex: 0 0 350px;
        height: 350px;
        background: #fff;
        border-radius: 8px;
        border: 1px solid #ddd;
        overflow: hidden;
        padding: 10px;
    }

    .product-image img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .product-info {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .badge-category {
        display: inline-block;
        background: #fff;
        padding: 5px 12px;
        border-radius: 50px;
        font-size: 11px;
        font-weight: bold;
        color: #A1937A;
        border: 1px solid #B2A48C;
        margin-bottom: 15px;
        width: max-content;
        text-transform: uppercase;
    }

    .prod-title {
        font-family: 'Playfair Display', serif;
        color: #5E5343;
        font-size: 32px;
        margin: 0 0 10px 0;
    }

    .prod-price {
        font-size: 26px;
        font-weight: 700;
        color: #A1937A;
        margin-bottom: 15px;
    }

    .rating-summary {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 20px;
        background: #fff;
        padding: 10px 15px;
        border-radius: 6px;
        border: 1px solid #eee;
        width: max-content;
    }

    /* STOCK BADGE STYLES */
    .stock-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 13px;
        font-weight: bold;
    }
    .stock-in { background: #e8f5e9; color: #27ae60; border: 1px solid #c8e6c9; }
    .stock-low { background: #fff8e1; color: #f39c12; border: 1px solid #ffeeba; }
    .stock-out { background: #fdf0f0; color: #e74c3c; border: 1px solid #fadbd8; }

    .seller-info {
        font-size: 14px;
        color: #555;
        margin-bottom: 20px;
        background: rgba(94, 83, 67, 0.05);
        padding: 12px 15px;
        border-radius: 6px;
        border-left: 4px solid #5E5343;
    }

    .seller-info i {
        color: #A1937A;
        margin-right: 5px;
    }

    .desc-box {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #ddd;
        font-size: 14px;
        color: #555;
        line-height: 1.6;
        max-height: 120px;
        overflow-y: auto;
    }

    .desc-box p {
        margin: 0;
    }

    /* Reviews Section */
    .reviews-section {
        background: #fff;
        padding: 30px;
        border-radius: 12px;
        border: 1px solid #e0e0e0;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03);
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
    }

    .admin-table th {
        text-align: left;
        padding: 12px;
        background: #F5F1E9;
        color: #5E5343;
        font-size: 12px;
        text-transform: uppercase;
        border-bottom: 2px solid #B2A48C;
        font-weight: 700;
    }

    .admin-table td {
        padding: 15px 12px;
        border-bottom: 1px solid #eee;
        vertical-align: middle;
        font-size: 13px;
    }

    .admin-table tr:hover {
        background: #fafafa;
    }

    .btn-del-review {
        color: #e74c3c;
        background: #fdf5f5;
        padding: 6px 10px;
        border-radius: 4px;
        text-decoration: none;
        border: 1px solid #fadbd8;
        transition: 0.3s;
    }

    .btn-del-review:hover {
        background: #e74c3c;
        color: #fff;
    }

    @media (max-width: 850px) {
        .product-showcase {
            flex-direction: column;
        }

        .product-image {
            flex: unset;
            width: 100%;
        }
    }
</style>

<?php include 'include/footer.php'; ?>