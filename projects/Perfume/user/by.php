<?php
include '../include/connection.php';
session_start();

// 1. Security Check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

$username = $_SESSION['username'] ?? '';
$userId = 0;
$userEmail = '';

// Fetch User ID & Email
$userSql = "SELECT id, email FROM users WHERE username=?";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param('s', $username);
$userStmt->execute();
$userStmt->bind_result($userId, $userEmail);
$userStmt->fetch();
$userStmt->close();

// --- HANDLE SAVING NEW ADDRESS FROM MODAL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_new_address'])) {
    $category = trim($_POST['category']);
    if ($category === 'Other') {
        $category = trim($_POST['custom_category']);
        if (empty($category)) $category = 'Other'; // Fallback
    }

    $r_name = trim($_POST['receiver_name']);
    $r_phone = trim($_POST['receiver_phone']);
    $r_address = trim($_POST['delivery_address']);
    $r_city = trim($_POST['city']);
    $r_pincode = trim($_POST['pincode']);

    $addr_stmt = $conn->prepare("INSERT INTO address (user_id, name, email, phone, delivery_address, city, pincode, category) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $addr_stmt->bind_param("isssssss", $userId, $r_name, $userEmail, $r_phone, $r_address, $r_city, $r_pincode, $category);
    $addr_stmt->execute();
    $addr_stmt->close();

    // Reload the page with existing GET parameters to keep cart/product data
    $query_string = $_SERVER['QUERY_STRING'];
    header("Location: by.php?$query_string");
    exit;
}

// --- FETCH ALL SAVED ADDRESSES ---
$saved_addresses = [];
$addr_res = $conn->query("SELECT * FROM address WHERE user_id = $userId ORDER BY id DESC");
if ($addr_res) {
    while ($row = $addr_res->fetch_assoc()) {
        $saved_addresses[] = $row;
    }
}

// 3. Determine Checkout Mode
$checkout_mode = (isset($_GET['type']) && $_GET['type'] == 'cart') ? 'cart' : 'single';
$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$items_to_buy = [];
$subtotal = 0;
$cart_ids_string = '';

if ($checkout_mode === 'cart') {
    $selected_ids = isset($_GET['ids']) ? $_GET['ids'] : '';
    
    if (empty($selected_ids)) {
        header('Location: cart.php');
        exit;
    }

    $ids_array = array_map('intval', explode(',', $selected_ids));
    $cart_ids_string = implode(',', $ids_array);

    $c_res = $conn->query("SELECT c.id as cart_id, c.quantity, p.id as product_id, p.name, p.price, p.image, s.pincode AS seller_pincode 
                           FROM cart c 
                           JOIN product p ON c.product_id = p.id 
                           LEFT JOIN sellers s ON p.seller_id = s.id 
                           WHERE c.user_id = $userId AND c.id IN ($cart_ids_string)");
    
    while ($row = $c_res->fetch_assoc()) {
        $items_to_buy[] = $row;
        $subtotal += ($row['price'] * $row['quantity']);
    }
    
    if (empty($items_to_buy)) {
        header('Location: cart.php');
        exit;
    }
} else {
    $buy_qty = isset($_GET['qty']) ? intval($_GET['qty']) : 1; 
    if ($buy_qty < 1) $buy_qty = 1;

    $p_res = $conn->query("SELECT p.id as product_id, p.name, p.price, p.image, s.pincode AS seller_pincode 
                           FROM product p 
                           LEFT JOIN sellers s ON p.seller_id = s.id 
                           WHERE p.id=$productId");
    if ($p_res && $p_res->num_rows > 0) {
        $product = $p_res->fetch_assoc();
        $product['quantity'] = $buy_qty; 
        $items_to_buy[] = $product;
        $subtotal = $product['price'] * $buy_qty; 
    } else {
        die("<div style='padding:50px; text-align:center;'>Product not found. <a href='product.php'>Go Back</a></div>");
    }
}

$tax = $subtotal * 0.08;
$total = $subtotal + $tax;

// --- 4. PROCEED TO PAYMENT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proceed_to_payment'])) {

    if(empty($_POST['selected_address'])) {
        echo "<script>alert('Please select a delivery address to proceed.');</script>";
    } else {
        $selected_address_id = intval($_POST['selected_address']);

        // Fetch selected address details
        $sel_stmt = $conn->prepare("SELECT name, phone, delivery_address, city, pincode FROM address WHERE id=? AND user_id=?");
        $sel_stmt->bind_param("ii", $selected_address_id, $userId);
        $sel_stmt->execute();
        $sel_stmt->bind_result($r_name, $r_phone, $r_address, $r_city, $r_pincode);
        $sel_stmt->fetch();
        $sel_stmt->close();

        // Format for database 
        $formatted_shipping = $r_name . " | +91 " . $r_phone . "\n" . $r_address . ", " . $r_city . " - " . $r_pincode;

        $_SESSION['checkout_data'] = [
            'type' => $checkout_mode,
            'product_id' => $productId,
            'single_qty' => ($checkout_mode === 'single') ? $buy_qty : null, 
            'cart_ids' => $cart_ids_string,
            'shipping_address' => $formatted_shipping,
            'buyer_pincode' => $r_pincode 
        ];
        header('Location: payment.php');
        exit;
    }
}

include 'include/header.php';
?>

<main style="flex: 1; padding: 40px; background-color: #E2E2DE; overflow-y: auto;">
    <div style="max-width: 1200px; margin: 0 auto;">

        <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin-bottom: 30px;">Step 1: Delivery Details</h1>

        <div class="checkout-layout">

            <div class="checkout-forms">
                <form method="POST">
                    <div class="checkout-step">
                        <h3><span class="step-num">1</span> Select Delivery Address</h3>

                        <div class="address-grid">
                            <?php if (count($saved_addresses) > 0): ?>
                                <?php foreach ($saved_addresses as $index => $addr): 
                                    $is_checked = ($index === 0) ? 'checked' : '';
                                    
                                    $badgeClass = 'cat-other';
                                    $cat_lower = strtolower($addr['category'] ?? 'home');
                                    if ($cat_lower === 'home') $badgeClass = 'cat-home';
                                    if ($cat_lower === 'work' || $cat_lower === 'office') $badgeClass = 'cat-work';
                                ?>
                                    <label class="address-card-radio">
                                        <input type="radio" name="selected_address" value="<?= $addr['id']; ?>" data-pin="<?= $addr['pincode']; ?>" onchange="updateDeliveryEst()" required <?= $is_checked; ?>>
                                        <div class="address-details">
                                            <div class="check-icon"><i class="fas fa-check-circle"></i></div>
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                                <strong style="color: #5E5343; font-size: 15px;"><?= htmlspecialchars($addr['name']); ?></strong>
                                                <span class="badge <?= $badgeClass; ?>"><?= htmlspecialchars($addr['category'] ?? 'Home'); ?></span>
                                            </div>
                                            <p style="margin: 0 0 5px; color: #555; font-size: 13px; line-height: 1.5;">
                                                <?= htmlspecialchars($addr['delivery_address']); ?><br>
                                                <?= htmlspecialchars($addr['city']); ?> - <strong><?= htmlspecialchars($addr['pincode']); ?></strong>
                                            </p>
                                            <p style="margin: 0; color: #777; font-size: 12px;"><i class="fas fa-phone-alt"></i> +91 <?= htmlspecialchars($addr['phone']); ?></p>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <div class="address-details add-new-card" onclick="openNewAddressModal()">
                                <i class="fas fa-plus-circle"></i>
                                <strong>Add New Address</strong>
                            </div>
                        </div>

                        <button type="submit" name="proceed_to_payment" class="btn-action" style="margin-top: 25px;">
                            Continue to Payment <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </form>
            </div>

            <div class="checkout-summary">
                <h3>Order Summary</h3>

                <div class="item-list">
                    <?php foreach ($items_to_buy as $item): ?>
                        <div class="summary-item">
                            <img src="../<?= htmlspecialchars($item['image']); ?>" alt="">
                            <div class="item-info">
                                <h4><?= htmlspecialchars($item['name']); ?></h4>
                                <p>Qty: <?= $item['quantity']; ?></p>
                                <div class="delivery-est" data-seller-pin="<?= htmlspecialchars($item['seller_pincode'] ?? '000000'); ?>" style="font-size: 11px; color: #27ae60; font-weight: 600; margin-top: 4px;"></div>
                            </div>
                            <div class="item-price">&#8377;<?= number_format($item['price'] * $item['quantity'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="totals-area">
                    <div class="tot-row">
                        <span>Subtotal</span>
                        <span>&#8377;<?= number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="tot-row">
                        <span>Tax (8%)</span>
                        <span>&#8377;<?= number_format($tax, 2); ?></span>
                    </div>
                    <div class="tot-row grand-total">
                        <span>Total to Pay</span>
                        <span>&#8377;<?= number_format($total, 2); ?></span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div id="newAddressModal" class="modal-overlay" style="display: none;">
        <div class="modal-box animated-box" style="max-width: 550px;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                <h2 style="font-family: 'Playfair Display', serif; color: #5E5343; margin: 0; font-size: 20px;">
                    <i class="fas fa-map-marked-alt" style="color: #A1937A;"></i> Add New Address
                </h2>
                <span class="close-modal-icon" onclick="closeNewAddressModal()" style="font-size: 24px; cursor: pointer; color: #999;">&times;</span>
            </div>

            <form method="POST">
                <div class="form-row">
                    <div class="form-group" style="flex: 1.5;">
                        <label>Receiver Name</label>
                        <input type="text" name="receiver_name" required placeholder="Full Name">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Save As</label>
                        <select name="category" id="modalCategory" onchange="toggleCustomCategoryModal()" style="width: 100%; padding: 12px 15px; border: 1px solid #ccc; border-radius: 8px; font-size: 14px; font-family: 'Poppins', sans-serif;">
                            <option value="Home">Home</option>
                            <option value="Work">Work / Office</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="modalCustomCategoryDiv" style="display: none;">
                    <label>Custom Name <span style="font-size:11px; color:#888; font-weight:normal;">(e.g. Gym, Friend's House)</span></label>
                    <input type="text" name="custom_category" id="modalCustomCategory" placeholder="Enter custom name...">
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="receiver_phone" required placeholder="10-digit mobile number" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                </div>

                <div class="form-group">
                    <label>Street Address / Area</label>
                    <textarea name="delivery_address" rows="2" required placeholder="House No, Street, Landmark..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" required placeholder="e.g. Surat">
                    </div>
                    <div class="form-group">
                        <label>Pincode</label>
                        <input type="text" name="pincode" required placeholder="e.g. 395006" maxlength="6" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                </div>

                <button type="submit" name="save_new_address" class="btn-action" style="width: 100%; margin-top: 10px; padding: 12px;">
                    <i class="fas fa-save"></i> Save & Use Address
                </button>
            </form>
        </div>
    </div>
</main>

<style>
    /* Layout */
    .checkout-layout { display: flex; gap: 40px; align-items: flex-start; }
    .checkout-forms { flex: 2; display: flex; flex-direction: column; gap: 25px; }
    .checkout-summary { flex: 1; background: #fff; padding: 25px; border-radius: 12px; border: 1px solid #ddd; box-shadow: 0 5px 25px rgba(0, 0, 0, 0.03); position: sticky; top: 100px; }
    .checkout-step { background: #fff; border-radius: 12px; padding: 30px; border: 1px solid #e0e0e0; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02); }
    .checkout-step h3 { font-family: 'Playfair Display', serif; color: #5E5343; margin-top: 0; margin-bottom: 25px; border-bottom: 2px solid #B2A48C; padding-bottom: 10px; display: flex; align-items: center; gap: 10px; }
    .step-num { background: #5E5343; color: #fff; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 14px; font-family: 'Poppins', sans-serif; }
    
    /* Address Grid & Radio Cards */
    .address-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
    .address-card-radio { display: block; cursor: pointer; height: 100%; }
    .address-card-radio input[type="radio"] { display: none; }
    
    .address-details { border: 2px solid #e0e0e0; border-radius: 10px; padding: 20px; transition: 0.3s; background: #fff; position: relative; height: 100%; box-sizing: border-box; }
    .address-card-radio input[type="radio"]:checked + .address-details { border-color: #27ae60; background: #fafffb; box-shadow: 0 4px 15px rgba(39, 174, 96, 0.08); }
    
    .check-icon { display: none; position: absolute; top: 15px; right: 15px; color: #27ae60; font-size: 18px; }
    .address-card-radio input[type="radio"]:checked + .address-details .check-icon { display: block; }
    
    .add-new-card { display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; border: 2px dashed #B2A48C; background: #fdfdfd; min-height: 140px; text-align: center; }
    .add-new-card:hover { background: #F5F1E9; border-color: #5E5343; }
    .add-new-card i { font-size: 28px; color: #A1937A; margin-bottom: 10px; transition: 0.3s; }
    .add-new-card:hover i { transform: scale(1.1); color: #5E5343; }
    .add-new-card strong { color: #5E5343; font-size: 15px; }
    
    .badge { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 600; text-transform: uppercase; }
    .cat-home { background: #e3f2fd; color: #2980b9; border: 1px solid #bbdefb; }
    .cat-work, .cat-office { background: #fdf2e9; color: #f39c12; border: 1px solid #fdebd0; }
    .cat-other { background: #f4f6f6; color: #7f8c8d; border: 1px solid #eaeded; }

    .form-row { display: flex; gap: 15px; }
    .form-row>* { flex: 1; }
    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; font-size: 12px; font-weight: 600; color: #5E5343; margin-bottom: 6px; text-transform: uppercase; }
    .form-group input, .form-group textarea { width: 100%; padding: 12px 15px; border: 1px solid #ccc; border-radius: 8px; font-size: 14px; font-family: 'Poppins', sans-serif; box-sizing: border-box; outline: none; transition: border 0.3s; background: #fff; }
    .form-group input:focus, .form-group textarea:focus { border-color: #5E5343; box-shadow: 0 0 5px rgba(94, 83, 67, 0.1); }
    
    .btn-action { background: #5E5343; color: #fff; border: none; padding: 14px 25px; border-radius: 50px; font-weight: 600; font-size: 15px; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; width: 100%; justify-content: center; }
    .btn-action:hover { background: #A1937A; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); }
    
    /* Summary */
    .checkout-summary h3 { font-family: 'Playfair Display', serif; color: #5E5343; margin-top: 0; border-bottom: 2px solid #B2A48C; padding-bottom: 10px; margin-bottom: 20px; }
    .item-list { border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px; max-height: 300px; overflow-y: auto; }
    .summary-item { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
    .summary-item img { width: 50px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd; }
    .item-info h4 { margin: 0 0 3px; font-size: 14px; color: #5E5343; }
    .item-info p { margin: 0; font-size: 12px; color: #999; }
    .item-price { margin-left: auto; font-weight: 600; color: #A1937A; font-size: 14px; }
    .totals-area .tot-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; color: #666; }
    .totals-area .grand-total { font-size: 18px; font-weight: 700; color: #5E5343; border-top: 1px dashed #ccc; padding-top: 15px; margin-top: 5px; }
    
    /* Modal Styles */
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(5px); z-index: 10000; display: flex; justify-content: center; align-items: center; }
    .modal-box { background: #fff; padding: 30px; border-radius: 12px; width: 95%; max-width: 500px; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15); }
    .animated-box { animation: popUp 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
    @keyframes popUp { 0% { transform: scale(0.9); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }

    @media (max-width: 900px) { .checkout-layout { flex-direction: column-reverse; } .address-grid { grid-template-columns: 1fr; } }
</style>

<script>
    // --- MODAL LOGIC ---
    function openNewAddressModal() {
        document.getElementById('newAddressModal').style.display = 'flex';
    }

    function closeNewAddressModal() {
        document.getElementById('newAddressModal').style.display = 'none';
        document.getElementById('modalCustomCategoryDiv').style.display = 'none';
    }

    function toggleCustomCategoryModal() {
        const select = document.getElementById('modalCategory');
        const customDiv = document.getElementById('modalCustomCategoryDiv');
        const customInput = document.getElementById('modalCustomCategory');

        if (select.value === 'Other') {
            customDiv.style.display = 'block';
            customInput.required = true;
        } else {
            customDiv.style.display = 'none';
            customInput.required = false;
        }
    }

    // --- LIVE CHECKOUT DELIVERY DATE CALCULATOR ---
    function updateDeliveryEst() {
        const selectedRadio = document.querySelector('input[name="selected_address"]:checked');
        if (!selectedRadio) return; // Exit if nothing is selected

        const buyerPin = selectedRadio.getAttribute('data-pin');
        const estElements = document.querySelectorAll('.delivery-est');

        if(buyerPin.length !== 6 || isNaN(buyerPin)) {
            estElements.forEach(el => el.innerHTML = "");
            return;
        }

        estElements.forEach(el => {
            const sellerPin = el.getAttribute('data-seller-pin');
            let days = 7; // Default National
            if (buyerPin === sellerPin) {
                days = 2; // Same exact pincode
            } else if (buyerPin.substring(0, 2) === sellerPin.substring(0, 2)) {
                days = 4; // Same State/Region
            }

            const deliveryDate = new Date();
            deliveryDate.setDate(deliveryDate.getDate() + days);
            const options = { weekday: 'short', day: 'numeric', month: 'short' };
            const formattedDate = deliveryDate.toLocaleDateString('en-IN', options);

            el.innerHTML = "<i class='fas fa-truck'></i> Expected by " + formattedDate;
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        // Trigger delivery date calculation on load for pre-selected address
        updateDeliveryEst();
    });
</script>

<?php include 'include/footer.php'; ?>