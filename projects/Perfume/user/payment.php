<?php
include '../include/connection.php';
session_start();

// --- HANDLE CANCEL PAYMENT ACTION ---
if (isset($_GET['action']) && $_GET['action'] === 'cancel_checkout') {
    unset($_SESSION['checkout_data']);
    header('Location: product.php');
    exit;
}

// 1. Security Check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

// 2. Validate Checkout Session Data
if (!isset($_SESSION['checkout_data'])) {
    header('Location: product.php');
    exit;
}

$checkout_data = $_SESSION['checkout_data'];
$checkout_mode = $checkout_data['type'];
$productId = $checkout_data['product_id'];
$shipping_address = $checkout_data['shipping_address'];
$cart_ids_string = isset($checkout_data['cart_ids']) ? $checkout_data['cart_ids'] : ''; 

// Fetch the accurate pincode we saved from by.php
$buyer_pincode = isset($checkout_data['buyer_pincode']) ? $checkout_data['buyer_pincode'] : '';

// 3. Fetch User ID
$username = $_SESSION['username'] ?? '';
$userId = 0;
$userSql = "SELECT id FROM users WHERE username=?";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param('s', $username);
$userStmt->execute();
$userStmt->bind_result($userId);
$userStmt->fetch();
$userStmt->close();

// --- BUILD THE CORRECT "BACK" URL TO CHANGE ADDRESS ---
$back_url = "by.php";
if ($checkout_mode === 'cart') {
    $back_url .= "?type=cart&ids=" . urlencode($cart_ids_string);
} else {
    $buy_qty_back = isset($checkout_data['single_qty']) ? intval($checkout_data['single_qty']) : 1; 
    $back_url .= "?type=single&id=" . urlencode($productId) . "&qty=" . urlencode($buy_qty_back);
}

// 4. Calculate Totals & Quantities
$items_to_buy = [];
$subtotal = 0;
$safe_cart_ids = '';

if ($checkout_mode === 'cart') {
    if (!empty($cart_ids_string)) {
        $ids_array = array_map('intval', explode(',', $cart_ids_string));
        $safe_cart_ids = implode(',', $ids_array);

        // Fetch cart items WITH Seller Pincode
        $c_res = $conn->query("SELECT c.quantity, p.id as product_id, p.name, p.image, p.price, s.pincode AS seller_pincode 
                               FROM cart c 
                               JOIN product p ON c.product_id = p.id 
                               LEFT JOIN sellers s ON p.seller_id = s.id 
                               WHERE c.user_id = $userId AND c.id IN ($safe_cart_ids)");
        while ($row = $c_res->fetch_assoc()) {
            $items_to_buy[] = $row;
            $subtotal += ($row['price'] * $row['quantity']);
        }
    } else {
        header('Location: cart.php');
        exit;
    }
} else {
    $buy_qty = isset($checkout_data['single_qty']) ? intval($checkout_data['single_qty']) : 1; 

    // Fetch single item WITH Seller Pincode
    $p_res = $conn->query("SELECT p.id as product_id, p.name, p.image, p.price, s.pincode AS seller_pincode 
                           FROM product p 
                           LEFT JOIN sellers s ON p.seller_id = s.id 
                           WHERE p.id=$productId");
    if ($p_res && $p_res->num_rows > 0) {
        $product = $p_res->fetch_assoc();
        $product['quantity'] = $buy_qty; 
        $items_to_buy[] = $product;
        $subtotal = $product['price'] * $buy_qty; 
    }
}

$tax = $subtotal * 0.08;
$total = $subtotal + $tax;
$paymentSuccess = false;

// --- 5. HANDLE PAYMENT CONFIRMATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method'])) {
    $pay_method = $conn->real_escape_string($_POST['payment_method']);

    if ($pay_method === 'UPI_QR') {
        $raw_utr = $conn->real_escape_string($_POST['utr_number']);
        $txn_id = "TXN-" . $raw_utr;
        $payment_status = 'Success'; 
    } else {
        $txn_id = "TXN-" . strtoupper(uniqid()) . rand(100, 999);
        $payment_status = ($pay_method === 'COD') ? 'Pending' : 'Success';
    }

    foreach ($items_to_buy as $item) {
        $pid = $item['product_id'];
        $qty = $item['quantity'];
        $line_total = ($item['price'] * $qty) * 1.08;

        // CALCULATE DELIVERY DAYS
        $seller_pincode = $item['seller_pincode'] ?? '';
        $delivery_days = 7; // Default National Shipping
        
        if (!empty($buyer_pincode) && !empty($seller_pincode)) {
            if ($seller_pincode === $buyer_pincode) {
                $delivery_days = 2; // Same Pincode
            } elseif (substr($seller_pincode, 0, 2) === substr($buyer_pincode, 0, 2)) {
                $delivery_days = 4; // Same State/Region
            }
        }

        // Save order WITH Estimated Delivery Date
        $stmt = $conn->prepare("INSERT INTO orders (user_id, product_id, price, shipping_address, status, estimated_delivery_date) VALUES (?, ?, ?, ?, 'Pending', DATE_ADD(NOW(), INTERVAL ? DAY))");
        $stmt->bind_param('iidsi', $userId, $pid, $line_total, $shipping_address, $delivery_days);
        if ($stmt->execute()) {
            $order_id = $stmt->insert_id;

            $p_stmt = $conn->prepare("INSERT INTO payment (order_id, payment_method, payment_status, transaction_id, amount) VALUES (?, ?, ?, ?, ?)");
            $p_stmt->bind_param('isssd', $order_id, $pay_method, $payment_status, $txn_id, $line_total);
            $p_stmt->execute();
            $p_stmt->close();

            $stock_stmt = $conn->prepare("UPDATE product SET stock = stock - ? WHERE id = ?");
            $stock_stmt->bind_param('ii', $qty, $pid);
            $stock_stmt->execute();
            $stock_stmt->close();
        }
        $stmt->close();
    }

    if ($checkout_mode === 'cart' && !empty($safe_cart_ids)) {
        $conn->query("DELETE FROM cart WHERE user_id = $userId AND id IN ($safe_cart_ids)");
    }
    
    unset($_SESSION['checkout_data']);
    $paymentSuccess = true;
}

include 'include/header.php';

// Generate Dynamic UPI Link
$upi_id = "your_actual_upi_id@bank";
$merchant_name = "Perfume Nest";
$upi_link = "upi://pay?pa={$upi_id}&pn=" . urlencode($merchant_name) . "&am=" . number_format($total, 2, '.', '') . "&cu=INR";
$qr_image_url = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($upi_link);
?>

<main style="flex: 1; padding: 40px; background-color: #E2E2DE; overflow-y: auto;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin-bottom: 30px;">Step 2: Secure Payment</h1>

        <div class="checkout-layout">
            <div class="checkout-forms">
                <div class="checkout-step">
                    <form method="POST" id="checkoutForm">
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #B2A48C; padding-bottom: 10px; margin-bottom: 20px;">
                            <h3 style="font-family: 'Playfair Display', serif; color: #5E5343; margin: 0;">Select Payment Method</h3>
                            <a href="<?= $back_url ?>" class="btn-change-address" onclick="isLeavingSafely = true;">
                                <i class="fas fa-edit"></i> Change Address
                            </a>
                        </div>

                        <div class="payment-options">
                            <label class="pay-option"><input type="radio" name="payment_method" value="Card" onchange="togglePaymentFields('Card')" checked><i class="fas fa-credit-card"></i> Card</label>
                            <label class="pay-option"><input type="radio" name="payment_method" value="UPI" onchange="togglePaymentFields('UPI')"><i class="fas fa-mobile-alt"></i> UPI ID</label>
                            
                            <label class="pay-option"><input type="radio" name="payment_method" value="COD" onchange="togglePaymentFields('COD')"><i class="fas fa-money-bill-wave"></i> COD</label>
                        </div>

                        <div id="card_fields" class="pay-fields" style="display:block;">
                            <input type="text" id="card_number" placeholder="Card Number (16 Digits)" maxlength="16">
                            <div class="form-row" style="margin-top:15px;">
                                <input type="text" id="card_expiry" placeholder="MM/YY" maxlength="5">
                                <input type="password" id="card_cvv" placeholder="CVV" maxlength="3">
                            </div>
                            <input type="text" id="card_name" placeholder="Name on Card" style="margin-top:15px;">
                        </div>

                        <div id="upi_fields" class="pay-fields" style="display:none;">
                            <input type="text" id="upi_id" placeholder="Enter UPI ID (e.g. user@okhdfc)">
                        </div>

                        <div id="upi_qr_fields" class="pay-fields" style="display:none; text-align: center;">
                            <p style="color: #5E5343; font-weight: 600; margin-bottom: 15px;">Scan with Google Pay, PhonePe, or Paytm</p>
                            <div style="background: #fff; padding: 15px; display: inline-block; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin-bottom: 20px;">
                                <img src="<?= $qr_image_url; ?>" alt="UPI QR Code" style="width: 200px; height: 200px;">
                            </div>
                            <h3 style="color: #A1937A; margin-bottom: 20px;">Total: &#8377;<?= number_format($total, 2); ?></h3>
                            <div style="text-align: left;">
                                <label style="display: block; font-weight: 600; color: #5E5343; margin-bottom: 8px; font-size: 13px;">Enter 12-Digit UTR / Reference No. *</label>
                                <input type="text" id="utr_number" name="utr_number" placeholder="e.g. 312345678901" maxlength="12">
                            </div>
                        </div>

                        <div id="cod_fields" class="pay-fields" style="display:none;">
                            <p style="text-align: center; color: #5E5343; font-size: 14px; margin: 0;">
                                <i class="fas fa-box" style="font-size: 24px; color: #A1937A; margin-bottom: 10px; display: block;"></i>
                                <strong>Cash on Delivery selected.</strong><br> Pay when your order is delivered.
                            </p>
                        </div>

                        <button type="submit" name="place_order" class="btn-action" id="btn-submit">
                            <i class="fas fa-lock"></i> Place Order & Pay &#8377;<?= number_format($total, 2); ?>
                        </button>

                        <div style="text-align: center; margin-top: 20px;">
                            <a href="payment.php?action=cancel_checkout" class="btn-cancel-payment" onclick="if(confirm('Are you sure you want to cancel this payment? Your order will not be placed.')) { isLeavingSafely = true; return true; } else { return false; }">
                                <i class="fas fa-times-circle"></i> Cancel Payment & Return
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="checkout-summary">
                <h3>Order Summary</h3>
                <div class="item-list">
                    <?php foreach ($items_to_buy as $item): 
                        // SHOW LIVE ESTIMATED DELIVERY DATE IN SUMMARY
                        $seller_pin = $item['seller_pincode'] ?? '';
                        $est_days = 7;
                        if (!empty($buyer_pincode) && !empty($seller_pin)) {
                            if ($seller_pin === $buyer_pincode) {
                                $est_days = 2;
                            } elseif (substr($seller_pin, 0, 2) === substr($buyer_pincode, 0, 2)) {
                                $est_days = 4;
                            }
                        }
                        $formatted_est_date = date('D, j M', strtotime("+$est_days days"));
                    ?>
                        <div class="summary-item">
                            <img src="../<?= htmlspecialchars($item['image']); ?>">
                            <div class="item-info">
                                <h4><?= htmlspecialchars($item['name']); ?></h4>
                                <p>Qty: <?= $item['quantity']; ?></p>
                                <div style="font-size: 11px; color: #27ae60; font-weight: 600; margin-top: 4px;">
                                    <i class="fas fa-truck"></i> Expected by <?= $formatted_est_date; ?>
                                </div>
                            </div>
                            <div class="item-price">&#8377;<?= number_format($item['price'] * $item['quantity'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="totals-area">
                    <div class="tot-row"><span>Subtotal</span><span>&#8377;<?= number_format($subtotal, 2); ?></span></div>
                    <div class="tot-row"><span>Tax (8%)</span><span>&#8377;<?= number_format($tax, 2); ?></span></div>
                    <div class="tot-row grand-total"><span>Total</span><span>&#8377;<?= number_format($total, 2); ?></span></div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php if ($paymentSuccess): ?>
    <div class="cart-popup-overlay" id="successPopup">
        <div class="cart-popup-box">
            <div class="icon-circle" style="background:#27ae60;"><i class="fas fa-check"></i></div>
            <h2>Payment Successful!</h2>
            <p>Your order has been placed successfully. Redirecting to your orders...</p>
        </div>
    </div>
    <script>
        // Disable warning popup on successful payment
        isLeavingSafely = true;
        setTimeout(function() {
            window.location.href = 'orders.php';
        }, 3000);
    </script>
<?php endif; ?>

<style>
    .checkout-layout {
        display: flex;
        gap: 40px;
        align-items: flex-start;
    }

    .checkout-forms {
        flex: 2;
    }

    .checkout-summary {
        flex: 1;
        background: #fff;
        padding: 25px;
        border-radius: 12px;
        border: 1px solid #ddd;
        position: sticky;
        top: 100px;
    }

    .checkout-step {
        background: #fff;
        border-radius: 12px;
        padding: 40px;
        border: 1px solid #e0e0e0;
    }

    .payment-options {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 25px;
    }

    .pay-option {
        background: #F5F1E9;
        border: 1px solid #ccc;
        padding: 18px 20px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
        font-size: 14px;
    }

    .pay-fields {
        padding: 25px;
        border-radius: 8px;
        margin-bottom: 25px;
        border: 2px dashed #B2A48C;
    }

    .pay-fields input,
    .std-select {
        width: 100%;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 8px;
        outline: none;
        box-sizing: border-box;
    }

    .form-row {
        display: flex;
        gap: 10px;
    }

    .btn-action {
        background: #5E5343;
        color: #fff;
        border: none;
        padding: 16px;
        border-radius: 50px;
        font-weight: 600;
        width: 100%;
        cursor: pointer;
        transition: 0.3s;
        margin-top: 10px;
    }

    .btn-action:hover {
        background: #A1937A;
    }

    /* === NEW BUTTON STYLES === */
    .btn-change-address {
        font-size: 13px;
        color: #777;
        text-decoration: none;
        font-weight: 600;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .btn-change-address:hover {
        color: #5E5343;
    }

    .btn-cancel-payment {
        font-size: 14px;
        color: #e74c3c;
        text-decoration: none;
        font-weight: 600;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .btn-cancel-payment:hover {
        color: #c0392b;
        text-decoration: underline;
    }

    .item-list {
        border-bottom: 1px solid #eee;
        margin-bottom: 15px;
    }

    .summary-item {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
    }

    .summary-item img {
        width: 50px;
        height: 50px;
        border-radius: 6px;
        border: 1px solid #ddd;
        object-fit: cover;
    }

    .item-info h4 {
        margin: 0;
        font-size: 14px;
        color: #5E5343;
    }

    .item-info p {
        margin: 0;
        font-size: 11px;
        color: #999;
    }

    .item-price {
        margin-left: auto;
        font-weight: 700;
        color: #A1937A;
    }

    .tot-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        font-size: 13px;
        color: #666;
    }

    .grand-total {
        font-size: 18px;
        font-weight: 700;
        color: #5E5343;
        border-top: 1px dashed #ccc;
        padding-top: 10px;
    }

    .cart-popup-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(3px);
    }

    .cart-popup-box {
        background: #fff;
        padding: 40px;
        border-radius: 12px;
        text-align: center;
        border: 2px solid #5E5343;
        max-width: 400px;
        width: 100%;
    }

    .icon-circle {
        width: 60px;
        height: 60px;
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        margin: 0 auto 15px;
    }
</style>

<script>
    // --- LEAVE PAGE WARNING LOGIC ---
    let isLeavingSafely = false;

    // Warn user if they click outside (sidebar, back button, refresh)
    window.addEventListener('beforeunload', function (e) {
        if (!isLeavingSafely && <?= $paymentSuccess ? 'false' : 'true' ?>) {
            e.preventDefault();
            e.returnValue = 'You have an active payment session. Are you sure you want to leave without completing your order?';
        }
    });

    function togglePaymentFields(method) {
        const ids = ['card_fields', 'upi_fields', 'upi_qr_fields', 'cod_fields'];
        ids.forEach(id => document.getElementById(id).style.display = (id === method.toLowerCase() + '_fields') ? 'block' : 'none');
    }

    document.getElementById('utr_number').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    const form = document.getElementById('checkoutForm');
    form.addEventListener('submit', function(event) {
        const method = document.querySelector('input[name="payment_method"]:checked').value;
        if (method === "UPI_QR") {
            const utr = document.getElementById('utr_number').value.trim();
            if (utr.length !== 12 || isNaN(utr)) {
                alert("Please enter a valid 12-digit UTR/Transaction Number.");
                event.preventDefault();
                return;
            }
        }
        if (method === "UPI" && !document.getElementById('upi_id').value.includes("@")) {
            alert("Enter valid UPI ID");
            event.preventDefault();
            return;
        }
        if (method === "Card") {
            if (!document.getElementById('card_number').value || !document.getElementById('card_expiry').value || !document.getElementById('card_cvv').value) {
                alert("Please fill all card details");
                event.preventDefault();
                return;
            }
        }
        
        // If validation passes, allow safe exit
        isLeavingSafely = true;
        document.getElementById('btn-submit').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    });
</script>
<?php include 'include/footer.php'; ?>