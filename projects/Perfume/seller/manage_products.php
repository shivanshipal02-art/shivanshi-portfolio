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

// Fetch products with review counts and average ratings (Newest First)
$sql = "SELECT p.*, 
               COUNT(r.id) as review_count, 
               ROUND(IFNULL(AVG(r.rating), 0), 1) as avg_rating 
        FROM product p 
        LEFT JOIN reviews r ON p.id = r.product_id 
        WHERE p.seller_id = ? 
        GROUP BY p.id 
        ORDER BY p.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();

$total_products = count($products);

include 'include/header.php';
?>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">

    <div class="page-header-row">
        <h1>My Products <span class="total-count">(<?= $total_products; ?> Total)</span></h1>
        <a href="add_product.php" class="btn-add"><i class="fas fa-plus"></i> Add New</a>
    </div>

    <div class="table-card">
        <?php if ($total_products > 0): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Stock Qty</th>
                            <th>Gender</th>
                            <th>Ratings & Reviews</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $serial_no = 1; // Initialize counter
                        foreach ($products as $p):
                            $stock = isset($p['stock']) ? (int)$p['stock'] : 0;
                        ?>
                            <tr>
                                <td><span class="product-number"><?= $serial_no++; ?></span></td>

                                <td>
                                    <?php if (!empty($p['image'])): ?>
                                        <img src="../<?= htmlspecialchars($p['image']); ?>" class="thumb">
                                    <?php else: ?>
                                        <span class="no-img">No Img</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($p['name']); ?></strong></td>
                                <td style="color: #A1937A; font-weight: 600;">₹<?= htmlspecialchars($p['price']); ?></td>
                                
                                <td>
                                    <?php if ($stock > 0): ?>
                                        <span class="stock-badge in-stock"><?= $stock; ?> Units</span>
                                    <?php else: ?>
                                        <span class="stock-badge out-of-stock">Out of Stock</span>
                                    <?php endif; ?>
                                </td>

                                <td><?= ucfirst($p['gender']); ?></td>

                                <td>
                                    <div class="rating-box" title="Average Rating: <?= $p['avg_rating']; ?>">
                                        <?php
                                        $avg = round($p['avg_rating']);
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $avg) {
                                                echo '<i class="fas fa-star" style="color: #A1937A;"></i>';
                                            } else {
                                                echo '<i class="far fa-star" style="color: #ccc;"></i>';
                                            }
                                        }
                                        ?>
                                        <span style="font-size: 13px; color: #777; margin-left: 5px; font-weight: 600;">
                                            (<?= $p['review_count']; ?>)
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <div class="action-row">
                                        <a href="edit_product.php?id=<?= $p['id']; ?>" class="btn-icon edit" title="Edit"><i class="fas fa-pen"></i></a>
                                        <a href="delete_product.php?id=<?= $p['id']; ?>" class="btn-icon delete" title="Delete" onclick="return confirm('Delete this product?');"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p>No products found. Start selling now!</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
    /* === DESIGN STYLES === */
    .page-header-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }

    h1 {
        font-family: 'Playfair Display', serif;
        color: #5E5343;
        margin: 0;
        font-size: 2rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Style for the total count badge */
    .total-count {
        font-family: 'Poppins', sans-serif;
        font-size: 1.1rem;
        color: #A1937A;
        font-weight: 600;
        background: #F5F1E9;
        padding: 5px 12px;
        border-radius: 20px;
        border: 1px solid #B2A48C;
    }

    .btn-add {
        background: #5E5343;
        color: #fff;
        padding: 10px 25px;
        border-radius: 30px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: 0.3s;
    }

    .btn-add:hover {
        background: #A1937A;
    }

    /* Table Container (Cream) */
    .table-card {
        background: #F5F1E9;
        border-radius: 12px;
        border: 1px solid #e0e0e0;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03);
        overflow: hidden;
        padding: 30px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th {
        text-align: left;
        padding: 15px;
        background: transparent;
        color: #5E5343;
        font-size: 13px;
        text-transform: uppercase;
        border-bottom: 2px solid #B2A48C;
        font-weight: 700;
    }

    td {
        padding: 15px;
        border-bottom: 1px solid #ddd;
        vertical-align: middle;
        color: #555;
        font-size: 14px;
    }

    tr:last-child td {
        border-bottom: none;
    }

    tr:hover {
        background: #fff;
    }

    /* Product Number Style */
    .product-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        background: #e2e2de;
        color: #5E5343;
        border-radius: 50%;
        font-weight: bold;
        font-size: 13px;
    }

    .thumb {
        width: 50px;
        height: 50px;
        border-radius: 6px;
        object-fit: cover;
        border: 1px solid #ccc;
    }

    .no-img {
        font-size: 11px;
        color: #999;
        border: 1px solid #ccc;
        padding: 5px;
        border-radius: 4px;
        display: inline-block;
        text-align: center;
        width: 50px;
        height: 50px;
        line-height: 40px;
    }

    /* STOCK BADGE STYLES */
    .stock-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-block;
    }

    .in-stock {
        background: #eafaf1;
        color: #27ae60;
        border: 1px solid #bce8ce;
    }

    .out-of-stock {
        background: #fdecea;
        color: #c0392b;
        border: 1px solid #f8c9c4;
    }

    /* RATING STYLES */
    .rating-box {
        display: flex;
        align-items: center;
        letter-spacing: 1px;
    }

    /* Actions */
    .action-row {
        display: flex;
        gap: 10px;
    }

    .btn-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: 0.3s;
        color: #fff;
        font-size: 13px;
        text-decoration: none;
    }

    .btn-icon.edit {
        background: #B2A48C;
    }

    .btn-icon.edit:hover {
        background: #5E5343;
    }

    .btn-icon.delete {
        background: #c0392b;
    }

    .btn-icon.delete:hover {
        background: #a93226;
    }

    .empty-state {
        text-align: center;
        padding: 50px;
        color: #999;
    }

    .empty-state i {
        font-size: 40px;
        margin-bottom: 15px;
        color: #B2A48C;
    }
</style>

<?php include 'include/footer.php'; ?>