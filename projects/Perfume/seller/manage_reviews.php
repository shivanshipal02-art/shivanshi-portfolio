<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['seller_logged_in']) || $_SESSION['seller_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

include '../include/connection.php';
$seller_id = $_SESSION['id'];

// --- 1. HANDLE DELETE ACTION ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $review_id = intval($_GET['id']);
    $p_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

    // Security check: Make sure this review belongs to a product owned by THIS seller
    $check_sql = "SELECT r.id FROM reviews r JOIN product p ON r.product_id = p.id WHERE r.id = ? AND p.seller_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $review_id, $seller_id);
    $check_stmt->execute();

    if ($check_stmt->get_result()->num_rows > 0) {
        $del_sql = "DELETE FROM reviews WHERE id = ?";
        $del_stmt = $conn->prepare($del_sql);
        $del_stmt->bind_param("i", $review_id);
        $del_stmt->execute();
        $del_stmt->close();
    }
    $check_stmt->close();

    // Redirect back to the same product's review page
    $redirect = $p_id > 0 ? "manage_reviews.php?product_id=$p_id" : "manage_reviews.php";
    echo "<script>window.location.href='$redirect';</script>";
    exit;
}

// Determine if we are viewing all products or reviews for a specific product
$view_product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

include 'include/header.php';
?>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">

    <?php if ($view_product_id > 0): ?>
        <?php
        // Fetch Product Info
        $p_sql = "SELECT * FROM product WHERE id = ? AND seller_id = ?";
        $p_stmt = $conn->prepare($p_sql);
        $p_stmt->bind_param("ii", $view_product_id, $seller_id);
        $p_stmt->execute();
        $product = $p_stmt->get_result()->fetch_assoc();
        $p_stmt->close();

        if (!$product) {
            echo "<div style='text-align:center; padding: 50px;'>Product not found or unauthorized access.</div>";
            exit;
        }

        // Fetch Reviews for this product
        $r_sql = "SELECT r.*, u.username FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC";
        $r_stmt = $conn->prepare($r_sql);
        $r_stmt->bind_param("i", $view_product_id);
        $r_stmt->execute();
        $reviews_result = $r_stmt->get_result();
        ?>

        <a href="manage_reviews.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Products
        </a>

        <div class="product-summary-card">
            <img src="../<?= htmlspecialchars($product['image']); ?>" alt="Product Image">
            <div class="product-info">
                <h2><?= htmlspecialchars($product['name']); ?></h2>
                <div class="price">&#8377;<?= number_format($product['price']); ?></div>
                <p><?= htmlspecialchars(mb_strimwidth($product['description'], 0, 100, "...")); ?></p>
            </div>
            <div class="stat-badge">
                <i class="fas fa-comment-dots"></i> <?= $reviews_result->num_rows; ?> Reviews
            </div>
        </div>

        <h3 style="font-family: 'Playfair Display', serif; color: #5E5343; margin-bottom: 20px;">User Reviews</h3>

        <div class="table-card">
            <?php if ($reviews_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 5%;">Sr. No.</th>
                                <th style="width: 20%;">Customer</th>
                                <th style="width: 15%;">Rating</th>
                                <th style="width: 40%;">Review Comment</th>
                                <th style="width: 15%;">Date & Time</th>
                                <th style="width: 5%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $r_counter = 1; while ($rev = $reviews_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong style="color: #5E5343; font-size: 13px;"><?= $r_counter++; ?></strong>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: #5E5343;"><i class="fas fa-user-circle" style="color: #B2A48C;"></i> <?= htmlspecialchars($rev['username']); ?></div>
                                    </td>
                                    <td>
                                        <div class="stars-display">
                                            <?php
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo ($i <= $rev['rating']) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star" style="color:#ccc;"></i>';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="comment-text">"<?= htmlspecialchars($rev['comment']); ?>"</div>
                                    </td>
                                    <td>
                                        <div style="font-size: 11px; color: #777; font-weight: 500;">
                                            <?= date('d-m-Y, h:i A', strtotime($rev['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="manage_reviews.php?action=delete&id=<?= $rev['id']; ?>&product_id=<?= $view_product_id; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this review?');" title="Delete Review">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <i class="fas fa-comment-slash" style="font-size: 40px; color: #B2A48C; margin-bottom: 15px;"></i>
                    <p>No reviews have been posted for this product yet.</p>
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <?php
        // Fetch products along with their review count and average rating
        $sql = "SELECT p.id, p.name, p.image, p.price, p.gender,
                       COUNT(r.id) as review_count, 
                       ROUND(IFNULL(AVG(r.rating), 0), 1) as avg_rating 
                FROM product p 
                LEFT JOIN reviews r ON p.id = r.product_id 
                WHERE p.seller_id = ? 
                GROUP BY p.id 
                ORDER BY review_count DESC, p.id DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $seller_id);
        $stmt->execute();
        $products_result = $stmt->get_result();
        ?>

        <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin: 0; font-size: 24px;">Manage Product Reviews</h1>
                <p style="color: #999; font-size: 13px; margin-top: 5px;">Select a product to view and manage its customer reviews.</p>
            </div>
            <div class="stat-badge">
                <i class="fas fa-boxes"></i> <?= $products_result->num_rows; ?> Products
            </div>
        </div>

        <div class="table-card">
            <?php if ($products_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Sr. No.</th>
                                <th style="width: 80px;">Image</th>
                                <th>Product Details</th>
                                <th>Avg. Rating</th>
                                <th>Total Reviews</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $p_counter = 1; while ($row = $products_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong style="color: #5E5343; font-size: 13px;"><?= $p_counter++; ?></strong>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['image'])): ?>
                                            <img src="../<?= htmlspecialchars($row['image']); ?>" style="width: 50px; height: 50px; border-radius: 6px; object-fit: cover; border: 1px solid #ccc;">
                                        <?php else: ?>
                                            <div style="width: 50px; height: 50px; background: #eee; border-radius: 6px;"></div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <div style="font-weight: 600; color: #5E5343; font-size: 14px;">
                                            <?= htmlspecialchars($row['name']); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #777; margin-top: 3px;">
                                            <span style="color: #A1937A; font-weight: 600;">&#8377;<?= number_format($row['price']); ?></span> | <?= ucfirst($row['gender']); ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="stars-display" title="Average Rating: <?= $row['avg_rating']; ?>">
                                            <?php
                                            $avg = round($row['avg_rating']);
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo ($i <= $avg) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star" style="color:#ccc;"></i>';
                                            }
                                            ?>
                                            <span style="font-size: 12px; color: #5E5343; margin-left: 5px; font-weight: 600;">(<?= $row['avg_rating']; ?>)</span>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="count-badge"><?= $row['review_count']; ?> Reviews</span>
                                    </td>

                                    <td>
                                        <a href="manage_reviews.php?product_id=<?= $row['id']; ?>" class="btn-action-view">
                                            <i class="fas fa-eye"></i> View Reviews
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 50px; color: #999;">
                    <i class="fas fa-box-open" style="font-size: 40px; color: #B2A48C; margin-bottom: 15px;"></i>
                    <p>You have not added any products yet.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</main>

<style>
    /* === GENERAL STYLES === */
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        color: #5E5343;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 25px;
        transition: 0.3s;
    }

    .back-link:hover {
        color: #A1937A;
    }

    .stat-badge {
        background: #F5F1E9;
        color: #5E5343;
        padding: 8px 15px;
        border-radius: 50px;
        border: 1px solid #B2A48C;
        font-weight: 600;
        font-size: 13px;
    }

    .stat-badge i {
        color: #A1937A;
        margin-right: 5px;
    }

    .count-badge {
        background: #e8f5e9;
        color: #27ae60;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        border: 1px solid #c8e6c9;
    }

    /* === PRODUCT SUMMARY CARD (Mode 2) === */
    .product-summary-card {
        background: #F5F1E9;
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 30px;
    }

    .product-summary-card img {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 8px;
        border: 2px solid #fff;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }

    .product-info {
        flex: 1;
    }

    .product-info h2 {
        margin: 0 0 5px 0;
        color: #5E5343;
        font-family: 'Playfair Display', serif;
        font-size: 22px;
    }

    .product-info .price {
        color: #A1937A;
        font-weight: 700;
        font-size: 18px;
        margin-bottom: 5px;
    }

    .product-info p {
        margin: 0;
        color: #666;
        font-size: 13px;
        line-height: 1.5;
    }

    /* === TABLE STYLES === */
    .table-card {
        background: #fff;
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
        color: #5E5343;
        font-size: 12px;
        text-transform: uppercase;
        border-bottom: 2px solid #B2A48C;
        font-weight: 700;
    }

    .admin-table td {
        padding: 15px;
        border-bottom: 1px solid #eee;
        vertical-align: middle;
        color: #555;
        font-size: 14px;
    }

    .admin-table tr:hover {
        background: #F5F1E9;
    }

    .admin-table tr:last-child td {
        border-bottom: none;
    }

    /* === REVIEW SPECIFIC STYLES === */
    .stars-display {
        color: #A1937A;
        font-size: 14px;
        letter-spacing: 2px;
        display: flex;
        align-items: center;
    }

    .comment-text {
        font-style: italic;
        color: #666;
        line-height: 1.5;
        font-size: 13px;
    }

    /* === BUTTONS === */
    .btn-action-view {
        background: #fff;
        color: #5E5343;
        padding: 8px 15px;
        border-radius: 50px;
        border: 1px solid #5E5343;
        font-size: 12px;
        cursor: pointer;
        transition: 0.3s;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .btn-action-view:hover {
        background: #5E5343;
        color: #fff;
    }

    .btn-delete {
        color: #c0392b;
        background: #ffebee;
        border: 1px solid #ffcdd2;
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        text-decoration: none;
        transition: 0.3s;
        font-size: 13px;
    }

    .btn-delete:hover {
        background: #c0392b;
        color: #fff;
    }
</style>

<?php include 'include/footer.php'; ?>