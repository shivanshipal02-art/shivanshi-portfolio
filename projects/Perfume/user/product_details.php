<?php
include '../include/connection.php';

session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($product_id === 0) die("Invalid product ID.");

// Get current user ID and Pincode
$u_name = $_SESSION['username'] ?? '';
$u_id = 0;
$u_pincode = '';
$u_stmt = $conn->prepare("SELECT id, pincode FROM users WHERE username=?");
$u_stmt->bind_param('s', $u_name);
$u_stmt->execute();
$u_stmt->bind_result($u_id, $u_pincode);
$u_stmt->fetch();
$u_stmt->close();

// --- 0. HANDLE ADD TO CART (STAY ON SAME PAGE) ---
if (isset($_GET['action']) && $_GET['action'] === 'add') {
    if ($u_id > 0) {
        $add_qty = isset($_GET['qty']) ? intval($_GET['qty']) : 1;
        if ($add_qty < 1) $add_qty = 1;

        // PREVENT ADDING IF OUT OF STOCK
        $stockCheckStmt = $conn->prepare("SELECT stock FROM product WHERE id=?");
        $stockCheckStmt->bind_param('i', $product_id);
        $stockCheckStmt->execute();
        $stockCheckStmt->bind_result($current_stock);
        $stockCheckStmt->fetch();
        $stockCheckStmt->close();

        if ($current_stock > 0 && $add_qty <= $current_stock) {
            $checkStmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id=? AND product_id=?");
            $checkStmt->bind_param('ii', $u_id, $product_id);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                $checkStmt->bind_result($cartId, $quantity);
                $checkStmt->fetch();
                $checkStmt->close();
                $newQuantity = $quantity + $add_qty; 
                $updateStmt = $conn->prepare("UPDATE cart SET quantity=? WHERE id=?");
                $updateStmt->bind_param('ii', $newQuantity, $cartId);
                $updateStmt->execute();
                $updateStmt->close();
            } else {
                $checkStmt->close();
                $insertStmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $insertStmt->bind_param('iii', $u_id, $product_id, $add_qty);
                $insertStmt->execute();
                $insertStmt->close();
            }
            echo "<script>window.onload = function() { showPopup('cartPopup'); }</script>";
        } else {
            echo "<script>window.onload = function() { alert('Sorry, not enough stock available!'); }</script>";
        }
    }
}

// --- 0.1 HANDLE ADD TO COLLECTION (WISHLIST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_collection'])) {
    if ($u_id > 0) {
        $selected_col = trim($_POST['collection_choice']);
        $new_col = trim($_POST['new_collection_name']);

        $final_collection = !empty($new_col) ? $new_col : $selected_col;
        if (empty($final_collection)) $final_collection = 'Favourites';

        $checkWish = $conn->prepare("SELECT id FROM wishlist WHERE user_id=? AND product_id=? AND IFNULL(collection_name, 'Favourites')=?");
        $checkWish->bind_param('iis', $u_id, $product_id, $final_collection);
        $checkWish->execute();
        $checkWish->store_result();

        if ($checkWish->num_rows == 0) {
            $checkWish->close();
            $addWish = $conn->prepare("INSERT INTO wishlist (user_id, product_id, collection_name) VALUES (?, ?, ?)");
            $addWish->bind_param('iis', $u_id, $product_id, $final_collection);
            $addWish->execute();
            $addWish->close();
        } else {
            $checkWish->close();
        }

        echo "<script>window.onload = function() { showPopup('wishlistPopup'); }</script>";
    }
}

// --- FETCH USER'S EXISTING COLLECTIONS ---
$user_collections = ['Favourites']; 
if ($u_id > 0) {
    $col_stmt = $conn->prepare("SELECT DISTINCT IFNULL(collection_name, 'Favourites') FROM wishlist WHERE user_id = ?");
    $col_stmt->bind_param('i', $u_id);
    $col_stmt->execute();
    $col_res = $col_stmt->get_result();
    while ($row = $col_res->fetch_array()) {
        if (!in_array($row[0], $user_collections)) {
            $user_collections[] = $row[0];
        }
    }
    $col_stmt->close();
}

// --- 1. HANDLE REVIEW SUBMISSION ---
$review_msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);

    if ($u_id > 0 && $rating > 0) {
        $stmt = $conn->prepare("INSERT INTO reviews (product_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiis", $product_id, $u_id, $rating, $comment);
        if ($stmt->execute()) {
            $review_msg = "<div class='alert-success'>Thank you! Your review has been posted.</div>";
        } else {
            $review_msg = "<div class='alert-error'>Error posting review.</div>";
        }
    }
}

// --- 2. FETCH PRODUCT & SELLER DETAILS ---
$sql = "SELECT p.*, s.business_name, s.owner_name AS seller_name, s.pincode AS seller_pincode 
        FROM product p 
        LEFT JOIN sellers s ON p.seller_id = s.id 
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) die("<div style='padding:50px; text-align:center;'>Product not found.</div>");
$product = $result->fetch_assoc();

$display_seller = !empty($product['business_name']) ? $product['business_name'] : $product['seller_name'];
if (empty($display_seller)) {
    $display_seller = "Perfume Nest Official";
}
// Get the seller pincode
$seller_pincode = $product['seller_pincode'] ?? '000000';

// --- 3. REVIEWS LOGIC ---
$all_reviews = [];
$r_sql = "SELECT r.*, u.username FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC";
$r_stmt = $conn->prepare($r_sql);
$r_stmt->bind_param("i", $product_id);
$r_stmt->execute();
$r_res = $r_stmt->get_result();
while ($row = $r_res->fetch_assoc()) {
    $all_reviews[] = [
        'user' => $row['username'],
        'rating' => $row['rating'],
        'comment' => $row['comment'],
        'date' => date("d-m-Y, h:i A", strtotime($row['created_at']))
    ];
}
$total_reviews_count = count($all_reviews);

$conn->close();
include 'include/header.php';
?>

<script>
    function showPopup(id) {
        const popup = document.getElementById(id);
        popup.style.display = 'flex';
    }

    function closePopup(id) {
        const popup = document.getElementById(id);
        popup.style.display = 'none';
    }

    function openModal(src) {
        document.getElementById('imageModal').style.display = "block";
        document.getElementById('fullScreenImage').src = src;
    }

    function closeModal() {
        document.getElementById('imageModal').style.display = "none";
    }

    function openReviewModal(btn) {
        const review = JSON.parse(btn.getAttribute('data-review'));
        document.getElementById('modalRevAvatar').innerText = review.user.charAt(0).toUpperCase();
        document.getElementById('modalRevName').innerText = review.user;
        document.getElementById('modalRevDate').innerText = review.date;

        let starsHtml = '';
        for (let i = 0; i < 5; i++) {
            starsHtml += (i < review.rating) ? '★' : '☆';
        }
        document.getElementById('modalRevStars').innerHTML = starsHtml;
        document.getElementById('modalRevComment').innerText = '"' + review.comment + '"';

        document.getElementById('reviewModal').style.display = 'flex';
    }

    function closeReviewModal() {
        document.getElementById('reviewModal').style.display = 'none';
    }

    function loadMoreReviews() {
        const hiddenReviews = document.querySelectorAll('.hidden-review');
        hiddenReviews.forEach(review => {
            review.style.display = 'block';
            review.style.animation = 'zoomIn 0.3s ease forwards';
        });
        document.getElementById('loadMoreBtnContainer').style.display = 'none';
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

    // Auto-check delivery on page load if pincode is available
    document.addEventListener("DOMContentLoaded", function() {
        if(document.getElementById('buyer_pin') && document.getElementById('buyer_pin').value.trim() !== "") {
            checkDelivery();
        }
    });

    window.onclick = function(event) {
        var imgModal = document.getElementById('imageModal');
        var colModal = document.getElementById('collectionModal');
        var revModal = document.getElementById('reviewModal');
        var cPopup = document.getElementById('cartPopup');
        var wPopup = document.getElementById('wishlistPopup');

        if (event.target == imgModal) imgModal.style.display = "none";
        if (event.target == colModal) colModal.style.display = "none";
        if (event.target == revModal) revModal.style.display = "none";
        if (event.target == cPopup) cPopup.style.display = "none";
        if (event.target == wPopup) wPopup.style.display = "none";
    }
</script>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">

    <div style="max-width: 1100px; margin: 0 auto; width: 100%;">
        <a href="product.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Collection
        </a>

        <div class="details-container">
            <div class="details-img">
                <?php if (!empty($product['image'])): ?>
                    <img src="../<?= htmlentities($product['image']); ?>" alt="<?= htmlentities($product['name']); ?>" onclick="openModal(this.src)" title="Click to enlarge" style="cursor: pointer;">
                <?php else: ?>
                    <div class="no-img-placeholder">No Image Available</div>
                <?php endif; ?>
            </div>

            <div class="details-info">
                <h1 class="d-name"><?= htmlentities($product['name']); ?></h1>
                <div class="d-price">&#8377;<?= htmlentities($product['price']); ?></div>

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
                    <button type="button" class="btn-main btn-wishlist" onclick="document.getElementById('collectionModal').style.display='flex'">
                        <i class="fas fa-heart"></i> Wishlist
                    </button>

                    <?php if ($product['stock'] > 0): ?>
                        <a href="#" onclick="window.location.href='product_details.php?id=<?= $product_id; ?>&action=add&qty=' + document.getElementById('p_qty').value; return false;" class="btn-main btn-add">
                            <i class="fas fa-shopping-bag"></i> Add to Cart
                        </a>
                        <a href="#" onclick="window.location.href='by.php?id=<?= $product_id; ?>&qty=' + document.getElementById('p_qty').value; return false;" class="btn-main btn-buy-now">
                            Buy Now
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn-main" style="background: #e0e0e0; color: #888; border: 2px solid #e0e0e0; cursor: not-allowed;" disabled>
                            <i class="fas fa-box-open"></i> Out of Stock
                        </button>
                    <?php endif; ?>
                </div>

                <?php if ($product['stock'] > 0): ?>
                    <div class="delivery-check-box" style="margin-top: 25px;">
                        <label><i class="fas fa-map-marker-alt" style="color: #A1937A;"></i> Check Delivery Time</label>
                        <div style="display: flex; gap: 10px; margin-top: 8px;">
                            <input type="text" id="buyer_pin" placeholder="Enter 6-digit Pincode" maxlength="6" value="<?= htmlspecialchars($u_pincode); ?>">
                            <button type="button" onclick="checkDelivery()">Check</button>
                        </div>
                        <div id="delivery_result" class="delivery-result"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="reviews-section">
            <h2 class="section-title">Customer Reviews (<?= $total_reviews_count; ?>)</h2>

            <?php if ($total_reviews_count > 0): ?>
                <div class="reviews-grid" id="reviewsContainer">
                    <?php foreach ($all_reviews as $index => $rev): ?>
                        <div class="review-card <?= ($index >= 6) ? 'hidden-review' : ''; ?>"
                            <?= ($index >= 6) ? 'style="display: none;"' : ''; ?>
                            data-review='<?= htmlspecialchars(json_encode($rev), ENT_QUOTES, 'UTF-8'); ?>'
                            onclick="openReviewModal(this)">

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
                            <p class="r-comment">"<?= htmlspecialchars(mb_strimwidth($rev['comment'], 0, 90, "...")); ?>"</p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_reviews_count > 6): ?>
                    <div id="loadMoreBtnContainer" style="text-align: center; margin-bottom: 40px;">
                        <button type="button" class="btn-main btn-buy-now" onclick="loadMoreReviews()" style="background: transparent; border: 2px solid #A1937A; color: #A1937A;">
                            <i class="fas fa-chevron-down"></i> Load More Reviews
                        </button>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <p style="color: #777; margin-bottom: 30px;">No reviews yet. Be the first to review this fragrance!</p>
            <?php endif; ?>

            <div class="write-review-box">
                <h3>Write a Review</h3>
                <?= $review_msg; ?>
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
            </div>
        </div>
    </div>

    <div id="reviewModal" class="cart-popup-overlay" style="display: none;">
        <div class="collection-modal-box animated-box" style="max-width: 500px; padding: 35px 30px; border-radius: 15px;">
            <span class="close-modal-icon" onclick="closeReviewModal()" style="top: 15px; right: 20px;">&times;</span>
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px; border-bottom: 1px solid #e0e0e0; padding-bottom: 20px;">
                <div id="modalRevAvatar" class="avatar" style="width: 55px; height: 55px; font-size: 24px; box-shadow: 0 4px 10px rgba(94, 83, 67, 0.2);"></div>
                <div>
                    <h3 id="modalRevName" style="margin: 0; color: #5E5343; font-family: 'Playfair Display', serif; font-size: 20px;"></h3>
                    <div id="modalRevDate" style="font-size: 12px; color: #888; margin-top: 3px; font-weight: 500;"></div>
                </div>
            </div>
            <div id="modalRevStars" class="stars" style="margin-bottom: 15px; font-size: 20px;"></div>
            <div style="background: #F9F9F9; padding: 20px; border-radius: 8px; border: 1px dashed #ccc;">
                <p id="modalRevComment" style="margin: 0; color: #555; font-size: 15px; line-height: 1.8; font-style: italic;"></p>
            </div>
        </div>
    </div>

    <div id="collectionModal" class="cart-popup-overlay" style="display: none;">
        <div class="collection-modal-box animated-box">
            <span class="close-modal-icon" onclick="document.getElementById('collectionModal').style.display='none'">&times;</span>
            <div class="popup-icon" style="background: #F5F1E9; color: #5E5343; width: 60px; height: 60px; font-size: 24px; margin-bottom: 15px;"><i class="fas fa-bookmark"></i></div>
            <h2 style="font-family: 'Playfair Display', serif; color: #5E5343; margin: 0 0 25px 0; font-size: 22px;">Save to Collection</h2>
            <form method="post">
                <div class="form-group" style="text-align: left; margin-bottom: 15px;">
                    <label class="premium-label">Select Existing Collection</label>
                    <select name="collection_choice" class="premium-input">
                        <?php foreach ($user_collections as $col): ?>
                            <option value="<?= htmlspecialchars($col); ?>"><?= htmlspecialchars($col); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="divider-or"><span>OR</span></div>
                <div class="form-group" style="text-align: left; margin-bottom: 25px;">
                    <label class="premium-label">Create New Collection</label>
                    <input type="text" name="new_collection_name" class="premium-input" placeholder="e.g. Summer Scents, Gifts...">
                </div>
                <button type="submit" name="add_to_collection" class="btn-main" style="width: 100%; background: #5E5343; color: #fff; justify-content: center; border: none; font-size: 15px; padding: 14px; cursor: pointer;">
                    <i class="fas fa-save"></i> Save Item
                </button>
            </form>
        </div>
    </div>

    <div id="cartPopup" class="cart-popup-overlay" style="display: none;">
        <div class="cart-popup-box animated-box">
            <div class="popup-icon icon-cart"><i class="fas fa-check"></i></div>
            <h2 style="font-family: 'Playfair Display', serif; color: #5E5343; margin-bottom: 5px;">Added to Cart!</h2>
            <p style="color: #777; font-size: 14px; margin-bottom: 25px;">The fragrance has been successfully added to your shopping bag.</p>
            <div style="display: flex; gap: 15px; justify-content: center;">
                <button type="button" onclick="closePopup('cartPopup')" class="btn-continue">Continue</button>
                <a href="cart.php" class="btn-view-action cart-btn-action">View Cart</a>
            </div>
        </div>
    </div>

    <div id="wishlistPopup" class="cart-popup-overlay" style="display: none;">
        <div class="cart-popup-box animated-box" style="border-color: #fce4e4; background: #fffafb;">
            <div class="popup-icon icon-wishlist"><i class="fas fa-heart"></i></div>
            <h2 style="font-family: 'Playfair Display', serif; color: #e74c3c; margin-bottom: 5px;">Saved to Collection!</h2>
            <p style="color: #777; font-size: 14px; margin-bottom: 25px;">This item is now saved in your wishlist collections.</p>
            <div style="display: flex; gap: 15px; justify-content: center;">
                <button type="button" onclick="closePopup('wishlistPopup')" class="btn-continue" style="border-color:#e74c3c; color:#e74c3c;">Close</button>
                <a href="wishlist.php" class="btn-view-action wish-btn-action">View Wishlist</a>
            </div>
        </div>
    </div>

</main>

<div id="imageModal" class="img-modal">
    <span class="close-modal" onclick="closeModal()">&times;</span>
    <img class="img-modal-content" id="fullScreenImage">
</div>

<style>
    body { font-family: 'Poppins', sans-serif; background-color: #E2E2DE; margin: 0; }
    .back-link { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #5E5343; font-size: 14px; margin-bottom: 25px; font-weight: 600; }
    .back-link:hover { color: #A1937A; }
    .details-container { display: flex; gap: 50px; background: #F5F1E9; padding: 40px; border-radius: 12px; box-shadow: 0 5px 25px rgba(0, 0, 0, 0.03); border: 1px solid #e0e0e0; margin-bottom: 40px; width: 100%; box-sizing: border-box; }
    .details-img { flex: 1; display: flex; align-items: center; justify-content: center; background: #fff; border-radius: 12px; padding: 30px; border: 1px solid #ddd; min-height: 350px; }
    .details-img img { max-width: 100%; max-height: 400px; object-fit: contain; cursor: pointer; transition: transform 0.3s; }
    .details-img img:hover { transform: scale(1.03); }
    .details-info { flex: 1.2; display: flex; flex-direction: column; justify-content: center; }
    .d-name { font-family: 'Playfair Display', serif; font-size: 32px; color: #5E5343; margin-bottom: 10px; line-height: 1.2; }
    .d-price { font-size: 24px; font-weight: 700; color: #A1937A; margin-bottom: 15px; }
    .seller-info { font-size: 14px; color: #6A5D52; margin-bottom: 25px; display: inline-flex; align-items: center; gap: 8px; background: #fff; padding: 8px 15px; border-radius: 8px; border: 1px solid #e0e0e0; width: fit-content; }
    .seller-info i { color: #A1937A; }
    .seller-info strong { color: #5E5343; font-weight: 600; }
    .divider { height: 1px; background: #B2A48C; width: 100%; margin-bottom: 25px; opacity: 0.3; }
    .desc-label { font-size: 13px; text-transform: uppercase; color: #5E5343; letter-spacing: 1px; margin-bottom: 10px; font-weight: 600; }
    .d-desc { color: #555; line-height: 1.8; font-size: 15px; margin-bottom: 25px; }
    
    /* Delivery Check CSS */
    .delivery-check-box { background: #fff; padding: 15px; border-radius: 8px; border: 1px dashed #B2A48C; margin-bottom: 25px; max-width: 350px; }
    .delivery-check-box label { font-size: 12px; font-weight: 600; text-transform: uppercase; color: #5E5343; }
    .delivery-check-box input { flex: 1; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; outline: none; font-family: 'Poppins', sans-serif; font-size: 13px; }
    .delivery-check-box button { background: #5E5343; color: #fff; border: none; padding: 8px 15px; border-radius: 4px; font-weight: 600; cursor: pointer; transition: 0.3s; font-size: 13px;}
    .delivery-check-box button:hover { background: #A1937A; }
    .delivery-result { margin-top: 10px; font-size: 13px; font-weight: 500; }

    .d-actions { display: flex; gap: 15px; flex-wrap: wrap; }
    .btn-main { padding: 14px 30px; border-radius: 50px; text-decoration: none; font-weight: 600; font-size: 15px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: 0.3s; }
    .btn-wishlist { background: #fff; color: #e74c3c; border: 2px solid #e74c3c; }
    .btn-wishlist:hover { background: #e74c3c; color: #fff; }
    .btn-add { background: #5E5343; color: #fff; border: 2px solid #5E5343; }
    .btn-add:hover { background: #A1937A; border-color: #A1937A; }
    .btn-buy-now { background: transparent; border: 2px solid #5E5343; color: #5E5343; }
    .btn-buy-now:hover { background: #5E5343; color: #fff; }
    .reviews-section { width: 100%; box-sizing: border-box; }
    .section-title { font-family: 'Playfair Display', serif; font-size: 24px; color: #5E5343; margin-bottom: 20px; border-bottom: 2px solid #B2A48C; padding-bottom: 10px; display: inline-block; }
    .reviews-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .review-card { background: #F5F1E9; padding: 20px; border-radius: 12px; border: 1px solid #e0e0e0; cursor: pointer; transition: transform 0.3s, box-shadow 0.3s; }
    .review-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); border-color: #B2A48C; }
    .r-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
    .avatar { width: 40px; height: 40px; background: #5E5343; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
    .name { font-weight: 600; color: #5E5343; display: block; font-size: 14px; }
    .stars { color: #A1937A; font-size: 14px; letter-spacing: 1px; }
    .r-date { font-size: 12px; color: #999; font-weight: 500; }
    .r-comment { color: #555; font-size: 14px; line-height: 1.6; font-style: italic; }
    .write-review-box { background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #ddd; }
    .write-review-box h3 { font-family: 'Playfair Display', serif; margin-bottom: 20px; color: #5E5343; }
    .rating-input { margin-bottom: 15px; }
    .star-rating { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 5px; }
    .star-rating input { display: none; }
    .star-rating label { font-size: 24px; color: #ccc; cursor: pointer; transition: 0.2s; }
    .star-rating input:checked~label, .star-rating label:hover, .star-rating label:hover~label { color: #A1937A; }
    textarea { width: 100%; box-sizing: border-box; padding: 15px; border: 1px solid #ccc; border-radius: 8px; font-family: 'Poppins', sans-serif; resize: vertical; margin-bottom: 15px; }
    textarea:focus { border-color: #5E5343; outline: none; }
    .btn-submit-review { background: #5E5343; color: #fff; border: none; padding: 12px 30px; border-radius: 50px; font-weight: 600; cursor: pointer; transition: 0.3s; }
    .btn-submit-review:hover { background: #A1937A; }
    .alert-success { padding: 10px; background: #d4edda; color: #155724; border-radius: 6px; margin-bottom: 15px; border: 1px solid #c3e6cb; }
    .alert-error { padding: 10px; background: #f8d7da; color: #721c24; border-radius: 6px; margin-bottom: 15px; border: 1px solid #f5c6cb; }
    .cart-popup-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(5px); z-index: 1000; display: flex; justify-content: center; align-items: center; }
    .collection-modal-box { background: #fff; padding: 35px 30px; border-radius: 12px; text-align: center; width: 100%; max-width: 400px; box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2); position: relative; }
    .premium-label { font-size: 12px; font-weight: 600; color: #5E5343; margin-bottom: 8px; display: block; letter-spacing: 0.5px; text-transform: uppercase; }
    .premium-input { width: 100%; padding: 12px 15px; border: 1px solid #ccc; border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: 14px; outline: none; transition: 0.3s; background: #fdfdfd; box-sizing: border-box; color: #333; }
    .premium-input:focus { border-color: #A1937A; box-shadow: 0 0 0 3px rgba(161, 147, 122, 0.1); background: #fff; }
    .divider-or { display: flex; align-items: center; text-align: center; color: #999; font-size: 12px; font-weight: 600; margin: 20px 0; }
    .divider-or::before, .divider-or::after { content: ''; flex: 1; border-bottom: 1px solid #eee; }
    .divider-or span { padding: 0 15px; }
    .cart-popup-box { background: #fff; padding: 40px; border-radius: 20px; text-align: center; width: 100%; max-width: 420px; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15); position: relative; border: 2px solid #e0e0e0; }
    .animated-box { transform: scale(0.8); opacity: 0; animation: popUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
    @keyframes popUp { to { transform: scale(1); opacity: 1; } }
    .popup-icon { width: 75px; height: 75px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; color: #fff; margin: 0 auto 20px; }
    .icon-cart { background: #27ae60; box-shadow: 0 0 0 rgba(39, 174, 96, 0.4); animation: pulseCart 1.5s infinite; }
    .icon-wishlist { background: #e74c3c; box-shadow: 0 0 0 rgba(231, 76, 60, 0.4); animation: pulseWishlist 1.5s infinite; }
    @keyframes pulseCart { 0% { box-shadow: 0 0 0 0 rgba(39, 174, 96, 0.7); } 70% { box-shadow: 0 0 0 15px rgba(39, 174, 96, 0); } 100% { box-shadow: 0 0 0 0 rgba(39, 174, 96, 0); } }
    @keyframes pulseWishlist { 0% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.7); } 70% { box-shadow: 0 0 0 15px rgba(231, 76, 60, 0); } 100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); } }
    .btn-continue { background: transparent; color: #5E5343; border: 2px solid #5E5343; padding: 12px 25px; border-radius: 50px; font-weight: 600; font-size: 14px; cursor: pointer; transition: 0.3s; }
    .btn-continue:hover { background: #5E5343; color: #fff; }
    .btn-view-action { color: #fff; text-decoration: none; padding: 12px 25px; border-radius: 50px; font-weight: 600; font-size: 14px; transition: 0.3s; border: 2px solid transparent; }
    .cart-btn-action { background: #5E5343; border-color: #5E5343; }
    .cart-btn-action:hover { background: #A1937A; border-color: #A1937A; }
    .wish-btn-action { background: #e74c3c; border-color: #e74c3c; }
    .wish-btn-action:hover { background: #c0392b; border-color: #c0392b; }
    .img-modal { display: none; position: fixed; z-index: 9999; padding-top: 60px; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.8); backdrop-filter: blur(5px); }
    .img-modal-content { margin: auto; display: block; max-width: 90%; max-height: 80vh; border-radius: 8px; box-shadow: 0 5px 25px rgba(0, 0, 0, 0.5); animation: zoomIn 0.3s; }
    @keyframes zoomIn { from { transform: scale(0.8); opacity: 0 } to { transform: scale(1); opacity: 1 } }
    .close-modal { position: absolute; top: 30px; right: 40px; color: #f1f1f1; font-size: 40px; font-weight: bold; transition: 0.3s; cursor: pointer; }
    .close-modal-icon { position: absolute; top: 15px; right: 15px; font-size: 26px; color: #999; cursor: pointer; transition: 0.3s; }
    .close-modal:hover, .close-modal:focus, .close-modal-icon:hover { color: #A1937A; text-decoration: none; }
    @media (max-width: 900px) { .details-container { flex-direction: column; padding: 25px; } .d-actions { width: 100%; } .btn-main { flex: 1; } }
</style>

<?php include 'include/footer.php'; ?>