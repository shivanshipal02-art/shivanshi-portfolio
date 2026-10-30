<?php
// Path corrected for main folder
include 'include/connection.php';

session_start();
// Allow guests to view the page without redirecting to login immediately
$is_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($product_id === 0) die("Invalid product ID.");

// --- 1. HANDLE REVIEW SUBMISSION ---
$review_msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if ($is_logged_in) {
        $u_id = $_SESSION['user_id'] ?? 0;
        $rating = intval($_POST['rating']);
        $comment = trim($_POST['comment']);

        if ($u_id == 0 && isset($_SESSION['username'])) {
            $u_sql = "SELECT id FROM users WHERE username = ?";
            $u_stmt = $conn->prepare($u_sql);
            $u_stmt->bind_param("s", $_SESSION['username']);
            $u_stmt->execute();
            $u_res = $u_stmt->get_result();
            if ($u_row = $u_res->fetch_assoc()) $u_id = $u_row['id'];
        }

        if ($u_id > 0 && $rating > 0) {
            $checkTable = $conn->query("SHOW TABLES LIKE 'reviews'");
            if ($checkTable->num_rows > 0) {
                $stmt = $conn->prepare("INSERT INTO reviews (product_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->bind_param("iiis", $product_id, $u_id, $rating, $comment);
                if ($stmt->execute()) {
                    $review_msg = "<div class='alert-success'>Thank you! Your review has been posted.</div>";
                } else {
                    $review_msg = "<div class='alert-error'>Error posting review.</div>";
                }
            } else {
                $review_msg = "<div class='alert-error'>Review system unavailable.</div>";
            }
        } else {
            $review_msg = "<div class='alert-error'>Please select a rating.</div>";
        }
    } else {
        $review_msg = "<div class='alert-error'>You must be <a href='login.php'>logged in</a> to post a review.</div>";
    }
}

// --- 2. FETCH PRODUCT & SELLER DETAILS ---
// Fetching pincode to allow delivery date calculation
$sql = "SELECT p.*, s.business_name, s.owner_name AS seller_name, s.pincode AS seller_pincode 
        FROM product p 
        LEFT JOIN sellers s ON p.seller_id = s.id 
        WHERE p.id = ? AND s.status = 'approved'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("<div style='padding:50px; text-align:center; font-family:sans-serif;'><h2>Product not found or seller is not approved.</h2><a href='product.php'>Go Back</a></div>");
}
$product = $result->fetch_assoc();

// Evaluate seller display name
$display_seller = !empty($product['business_name']) ? $product['business_name'] : $product['seller_name'];
if (empty($display_seller)) {
    $display_seller = "Perfume Nest Official"; 
}
// Get the seller pincode
$seller_pincode = $product['seller_pincode'] ?? '000000';

// --- 3. REVIEWS LOGIC ---
$reviews = [];
$checkTable = $conn->query("SHOW TABLES LIKE 'reviews'");
if ($checkTable->num_rows > 0) {
    $r_sql = "SELECT r.*, u.username FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC";
    $r_stmt = $conn->prepare($r_sql);
    $r_stmt->bind_param("i", $product_id);
    $r_stmt->execute();
    $r_res = $r_stmt->get_result();
    while ($row = $r_res->fetch_assoc()) {
        $reviews[] = [
            'user' => $row['username'],
            'rating' => $row['rating'],
            'comment' => $row['comment'],
            'date' => date("d-m-Y, h:i A", strtotime($row['created_at']))
        ];
    }
}

$display_reviews = array_slice($reviews, 0, 3);
$conn->close();

include 'include/header.php';
?>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">

    <div style="max-width: 1100px; margin: 0 auto; width: 100%;">
        <a href="product.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Collection
        </a>

        <div class="details-container">

            <div class="details-img">
                <?php if (!empty($product['image'])): ?>
                    <img src="./<?= htmlentities($product['image']); ?>" alt="<?= htmlentities($product['name']); ?>" onclick="openModal(this.src)" title="Click to enlarge">
                <?php else: ?>
                    <div class="no-img-placeholder">No Image Available</div>
                <?php endif; ?>
            </div>

            <div class="details-info">
                <h1 class="d-name"><?= htmlentities($product['name']); ?></h1>
                
                <div class="d-price" id="dynamic-price">&#8377;<?= htmlentities($product['price']); ?></div>

                <?php if ($product['stock'] <= 0): ?>
                    <div style="color: #e74c3c; font-weight: 600; margin-bottom: 15px; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-times-circle"></i> Out of Stock
                    </div>
                <?php elseif ($product['stock'] > 0 && $product['stock'] <= 10): ?>
                    <div style="color: #f39c12; font-weight: 600; margin-bottom: 15px; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-exclamation-triangle"></i> Limited Stock Available (Only <?= $product['stock']; ?> left!)
                    </div>
                <?php endif; ?>

                <div class="seller-info">
                    <i class="fas fa-store"></i> Sold by: <strong><?= htmlentities($display_seller); ?></strong>
                </div>

                <div class="divider"></div>

                <h3 class="desc-label">Description</h3>
                <p class="d-desc"><?= nl2br(htmlentities($product['description'])); ?></p>

                <?php if ($product['stock'] > 0): ?>
                    <div style="margin-bottom: 20px; display: inline-flex; align-items: center; border: 1px solid #B2A48C; border-radius: 8px; padding: 6px 15px; background: #fff; width: fit-content; box-shadow: 0 2px 5px rgba(0,0,0,0.02);">
                        <label for="p_qty" style="font-size: 13px; font-weight: 600; color: #5E5343; margin: 0; padding-right: 10px;">Quantity:</label>
                        <select id="p_qty" style="border: none; font-family: 'Poppins', sans-serif; font-size: 14px; outline: none; background: transparent; cursor: pointer; color: #5E5343; font-weight: 600;">
                            <?php
                            $max_qty = min($product['stock'], 5); // Allow max 5 per order
                            for ($i = 1; $i <= $max_qty; $i++) {
                                echo "<option value='$i'>$i</option>";
                            }
                            ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="d-actions">
                    <?php if ($is_logged_in): ?>
                        <a href="user/product.php?action=wishlist&id=<?= urlencode($product['id']); ?>" class="btn-main btn-wishlist">
                            <i class="fas fa-heart"></i> Wishlist
                        </a>
                        <?php if ($product['stock'] > 0): ?>
                            <a href="#" onclick="window.location.href='user/product.php?action=add&id=<?= $product['id']; ?>&qty=' + document.getElementById('p_qty').value; return false;" class="btn-main btn-add">
                                <i class="fas fa-shopping-bag"></i> Add to Cart
                            </a>
                            <a href="#" onclick="window.location.href='user/by.php?id=<?= $product_id; ?>&qty=' + document.getElementById('p_qty').value; return false;" class="btn-main btn-buy-now">
                                Buy Now
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn-main" style="background: #e0e0e0; color: #888; border: 2px solid #e0e0e0; cursor: not-allowed;" disabled>
                                <i class="fas fa-box-open"></i> Out of Stock
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="login.php" class="btn-main btn-wishlist">
                            <i class="fas fa-heart"></i> Wishlist
                        </a>
                        <?php if ($product['stock'] > 0): ?>
                            <a href="login.php" class="btn-main btn-add">
                                <i class="fas fa-shopping-bag"></i> Add to Cart
                            </a>
                            <a href="login.php" class="btn-main btn-buy-now">
                                Buy Now
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn-main" style="background: #e0e0e0; color: #888; border: 2px solid #e0e0e0; cursor: not-allowed;" disabled>
                                <i class="fas fa-box-open"></i> Out of Stock
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if ($product['stock'] > 0): ?>
                    <div class="delivery-check-box" style="margin-top: 25px;">
                        <label><i class="fas fa-map-marker-alt" style="color: #A1937A;"></i> Check Delivery Time</label>
                        <div style="display: flex; gap: 10px; margin-top: 8px;">
                            <input type="text" id="buyer_pin" placeholder="Enter 6-digit Pincode" maxlength="6">
                            <button type="button" onclick="checkDelivery()">Check</button>
                        </div>
                        <div id="delivery_result" class="delivery-result"></div>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <div class="reviews-section">
            <h2 class="section-title">Customer Reviews</h2>

            <div class="reviews-grid">
                <?php if (count($display_reviews) > 0): ?>
                    <?php foreach ($display_reviews as $rev): ?>
                        <div class="review-card">
                            <div class="r-header">
                                <div class="r-user">
                                    <div class="avatar"><?= strtoupper(substr($rev['user'], 0, 1)); ?></div>
                                    <div>
                                        <span class="name"><?= htmlspecialchars($rev['user']); ?></span>
                                        <div class="stars">
                                            <?php for ($k = 0; $k < 5; $k++) echo ($k < $rev['rating']) ? '★' : '☆'; ?>
                                        </div>
                                    </div>
                                </div>
                                <span class="r-date"><?= $rev['date']; ?></span>
                            </div>
                            <p class="r-comment">"<?= htmlspecialchars($rev['comment']); ?>"</p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #777;">No reviews yet. Be the first to review this fragrance!</p>
                <?php endif; ?>
            </div>

            <div class="write-review-box">
                <h3>Write a Review</h3>
                <?= $review_msg; ?>

                <?php if ($is_logged_in): ?>
                    <form method="post">
                        <div class="rating-input">
                            <label>Your Rating:</label>
                            <div class="star-rating">
                                <input type="radio" id="5-stars" name="rating" value="5" checked />
                                <label for="5-stars" class="star">&#9733;</label>
                                <input type="radio" id="4-stars" name="rating" value="4" />
                                <label for="4-stars" class="star">&#9733;</label>
                                <input type="radio" id="3-stars" name="rating" value="3" />
                                <label for="3-stars" class="star">&#9733;</label>
                                <input type="radio" id="2-stars" name="rating" value="2" />
                                <label for="2-stars" class="star">&#9733;</label>
                                <input type="radio" id="1-star" name="rating" value="1" />
                                <label for="1-star" class="star">&#9733;</label>
                            </div>
                        </div>
                        <textarea name="comment" rows="3" placeholder="Share your thoughts..." required></textarea>
                        <button type="submit" name="submit_review" class="btn-submit-review">Submit Review</button>
                    </form>
                <?php else: ?>
                    <div style="padding: 15px; background: #F5F1E9; border-radius: 8px; color: #5E5343; font-weight: 500;">
                        Please <a href="login.php" style="color: #A1937A; text-decoration: underline;">log in</a> to write a review.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</main>

<div id="imageModal" class="img-modal">
    <span class="close-modal" onclick="closeModal()">&times;</span>
    <img class="img-modal-content" id="fullScreenImage">
</div>

<style>
    /* === DESIGN COLORS === */
    body {
        font-family: 'Poppins', sans-serif;
        background-color: #E2E2DE;
        margin: 0;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        color: #5E5343;
        font-size: 14px;
        margin-bottom: 25px;
        font-weight: 600;
    }

    .back-link:hover {
        color: #A1937A;
    }

    .details-container {
        display: flex;
        gap: 50px;
        background: #F5F1E9;
        padding: 40px;
        border-radius: 12px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.03);
        border: 1px solid #e0e0e0;
        margin-bottom: 40px;
        width: 100%;
        box-sizing: border-box;
    }

    .details-img {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #fff;
        border-radius: 12px;
        padding: 30px;
        border: 1px solid #ddd;
        min-height: 350px;
    }

    .details-img img {
        max-width: 100%;
        max-height: 400px;
        object-fit: contain;
        cursor: pointer;
        transition: transform 0.3s;
    }

    .details-img img:hover {
        transform: scale(1.03);
    }

    .details-info {
        flex: 1.2;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .d-name {
        font-family: 'Playfair Display', serif;
        font-size: 32px;
        color: #5E5343;
        margin-bottom: 10px;
        line-height: 1.2;
    }

    .d-price {
        font-size: 24px;
        font-weight: 700;
        color: #A1937A;
        margin-bottom: 15px;
    }

    /* SELLER INFO CSS */
    .seller-info {
        font-size: 14px;
        color: #6A5D52;
        margin-bottom: 25px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #fff;
        padding: 8px 15px;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        width: fit-content;
    }

    .seller-info i {
        color: #A1937A;
    }

    .seller-info strong {
        color: #5E5343;
        font-weight: 600;
    }

    .divider {
        height: 1px;
        background: #B2A48C;
        width: 100%;
        margin-bottom: 25px;
        opacity: 0.3;
    }

    .desc-label {
        font-size: 13px;
        text-transform: uppercase;
        color: #5E5343;
        letter-spacing: 1px;
        margin-bottom: 10px;
        font-weight: 600;
    }

    .d-desc {
        color: #555;
        line-height: 1.8;
        font-size: 15px;
        margin-bottom: 25px;
    }

    /* Delivery Check CSS */
    .delivery-check-box { background: #fff; padding: 15px; border-radius: 8px; border: 1px dashed #B2A48C; margin-bottom: 25px; max-width: 350px; }
    .delivery-check-box label { font-size: 12px; font-weight: 600; text-transform: uppercase; color: #5E5343; }
    .delivery-check-box input { flex: 1; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; outline: none; font-family: 'Poppins', sans-serif; font-size: 13px; }
    .delivery-check-box button { background: #5E5343; color: #fff; border: none; padding: 8px 15px; border-radius: 4px; font-weight: 600; cursor: pointer; transition: 0.3s; font-size: 13px;}
    .delivery-check-box button:hover { background: #A1937A; }
    .delivery-result { margin-top: 10px; font-size: 13px; font-weight: 500; }

    .d-actions {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .btn-main {
        padding: 14px 30px;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 600;
        font-size: 15px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: 0.3s;
    }

    /* WISHLIST BUTTON CSS */
    .btn-wishlist {
        background: #fff;
        color: #e74c3c;
        border: 2px solid #e74c3c;
    }

    .btn-wishlist:hover {
        background: #e74c3c;
        color: #fff;
    }

    .btn-add {
        background: #5E5343;
        color: #fff;
        border: 2px solid #5E5343;
    }

    .btn-add:hover {
        background: #A1937A;
        border-color: #A1937A;
    }

    .btn-buy-now {
        background: transparent;
        border: 2px solid #5E5343;
        color: #5E5343;
    }

    .btn-buy-now:hover {
        background: #5E5343;
        color: #fff;
    }

    .reviews-section {
        width: 100%;
        box-sizing: border-box;
    }

    .section-title {
        font-family: 'Playfair Display', serif;
        font-size: 24px;
        color: #5E5343;
        margin-bottom: 20px;
        border-bottom: 2px solid #B2A48C;
        padding-bottom: 10px;
        display: inline-block;
    }

    .reviews-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }

    .review-card {
        background: #F5F1E9;
        padding: 20px;
        border-radius: 12px;
        border: 1px solid #e0e0e0;
    }

    .r-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }

    .avatar {
        width: 40px;
        height: 40px;
        background: #5E5343;
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }

    .name {
        font-weight: 600;
        color: #5E5343;
        display: block;
        font-size: 14px;
    }

    .stars {
        color: #A1937A;
        font-size: 14px;
        letter-spacing: 1px;
    }

    .r-date {
        font-size: 12px;
        color: #999;
        font-weight: 500;
    }

    .r-comment {
        color: #555;
        font-size: 14px;
        line-height: 1.6;
        font-style: italic;
    }

    .write-review-box {
        background: #fff;
        padding: 30px;
        border-radius: 12px;
        border: 1px solid #ddd;
    }

    .write-review-box h3 {
        font-family: 'Playfair Display', serif;
        margin-bottom: 20px;
        color: #5E5343;
    }

    .rating-input {
        margin-bottom: 15px;
    }

    .star-rating {
        display: flex;
        flex-direction: row-reverse;
        justify-content: flex-end;
        gap: 5px;
    }

    .star-rating input {
        display: none;
    }

    .star-rating label {
        font-size: 24px;
        color: #ccc;
        cursor: pointer;
        transition: 0.2s;
    }

    .star-rating input:checked~label,
    .star-rating label:hover,
    .star-rating label:hover~label {
        color: #A1937A;
    }

    textarea {
        width: 100%;
        box-sizing: border-box;
        padding: 15px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-family: 'Poppins', sans-serif;
        resize: vertical;
        margin-bottom: 15px;
    }

    textarea:focus {
        border-color: #5E5343;
        outline: none;
    }

    .btn-submit-review {
        background: #5E5343;
        color: #fff;
        border: none;
        padding: 12px 30px;
        border-radius: 50px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.3s;
    }

    .btn-submit-review:hover {
        background: #A1937A;
    }

    .alert-success {
        padding: 10px;
        background: #d4edda;
        color: #155724;
        border-radius: 6px;
        margin-bottom: 15px;
    }

    .alert-error {
        padding: 10px;
        background: #f8d7da;
        color: #721c24;
        border-radius: 6px;
        margin-bottom: 15px;
    }

    /* IMAGE MODAL CSS */
    .img-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        padding-top: 60px;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(5px);
    }

    .img-modal-content {
        margin: auto;
        display: block;
        max-width: 90%;
        max-height: 80vh;
        border-radius: 8px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.5);
        animation: zoomIn 0.3s;
    }

    @keyframes zoomIn {
        from {
            transform: scale(0.8);
            opacity: 0
        }

        to {
            transform: scale(1);
            opacity: 1
        }
    }

    .close-modal {
        position: absolute;
        top: 30px;
        right: 40px;
        color: #f1f1f1;
        font-size: 40px;
        font-weight: bold;
        transition: 0.3s;
        cursor: pointer;
    }

    .close-modal:hover,
    .close-modal:focus {
        color: #A1937A;
        text-decoration: none;
    }

    @media (max-width: 900px) {
        .details-container {
            flex-direction: column;
            padding: 25px;
        }

        .d-actions {
            width: 100%;
        }

        .btn-main {
            flex: 1;
        }
    }
</style>

<script>
    function openModal(src) {
        document.getElementById('imageModal').style.display = "block";
        document.getElementById('fullScreenImage').src = src;
    }

    function closeModal() {
        document.getElementById('imageModal').style.display = "none";
    }

    // Close modal if user clicks anywhere outside the image
    window.onclick = function(event) {
        var modal = document.getElementById('imageModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    // --- DELIVERY CALCULATOR JAVASCRIPT ---
    function checkDelivery() {
        const buyerPin = document.getElementById('buyer_pin').value.trim();
        const sellerPin = "<?= htmlspecialchars($seller_pincode); ?>";
        const resultDiv = document.getElementById('delivery_result');

        if(buyerPin.length !== 6 || isNaN(buyerPin)) {
            resultDiv.innerHTML = "<span style='color: #e74c3c;'><i class='fas fa-times-circle'></i> Enter a valid 6-digit Pincode.</span>";
            return;
        }

        let days = 7; // Default National Shipping
        if (buyerPin === sellerPin) {
            days = 2; // Same Pincode
        } else if (buyerPin.substring(0, 2) === sellerPin.substring(0, 2)) {
            days = 4; // Same State/Region
        }

        // Calculate Future Date
        const deliveryDate = new Date();
        deliveryDate.setDate(deliveryDate.getDate() + days);
        const options = { weekday: 'short', day: 'numeric', month: 'short' };
        const formattedDate = deliveryDate.toLocaleDateString('en-IN', options);

        resultDiv.innerHTML = "<span style='color: #27ae60;'><i class='fas fa-truck'></i> Expected Delivery by <strong>" + formattedDate + "</strong></span>";
    }

    document.addEventListener("DOMContentLoaded", function() {
        // Live Price Update based on Quantity
        const qtySelect = document.getElementById('p_qty');
        if (qtySelect) {
            const basePrice = <?= $product['price']; ?>;
            qtySelect.addEventListener('change', function() {
                const newPrice = basePrice * parseInt(this.value);
                document.getElementById('dynamic-price').innerHTML = '&#8377;' + newPrice.toFixed(2);
            });
        }
    });
</script>

<?php include 'include/footer.php'; ?>