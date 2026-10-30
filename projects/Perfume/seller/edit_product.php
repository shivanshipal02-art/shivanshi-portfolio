<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Security Check
if (!isset($_SESSION['seller_logged_in']) || $_SESSION['seller_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

include '../include/connection.php';
$seller_id = $_SESSION['id'];
$message = "";
$status = "";

// 2. LIVE APPROVAL CHECK
$chk = $conn->prepare("SELECT status FROM sellers WHERE id = ?");
$chk->bind_param("i", $seller_id);
$chk->execute();
$chk->bind_result($status);
$chk->fetch();
$chk->close();

// 3. Get Product Details
if (!isset($_GET['id'])) {
    header('Location: manage_products.php');
    exit;
}
$product_id = intval($_GET['id']);

$sql = "SELECT * FROM product WHERE id = ? AND seller_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $product_id, $seller_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    echo "<div style='padding:40px; text-align:center;'>Product not found. <a href='manage_products.php'>Back</a></div>";
    exit;
}

// 4. Handle Form Submission
if ($status !== 'approved') {
    $message = "<div class='alert-error'><i class='fas fa-ban'></i> Your account is currently <b>" . strtoupper($status) . "</b>. You cannot edit products until approved by the Admin.</div>";
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name']);
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']); // Fetching Stock Quantity
        $desc = trim($_POST['description']);
        $gender = $_POST['gender'];
        $db_image_path = $product['image']; // Default to existing image

        // Handle Image Upload (Only if user selects a new image)
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $target_dir = "../uploads/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $file_extension = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($file_extension, $allowed_extensions)) {
                $new_img_name = "img_" . uniqid() . "." . $file_extension;
                $target_file = $target_dir . $new_img_name;

                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    $db_image_path = "uploads/" . $new_img_name;

                    // Delete old image from server to save space
                    if (!empty($product['image']) && file_exists("../" . $product['image'])) {
                        unlink("../" . $product['image']);
                    }
                } else {
                    $message = "<div class='alert-error'><i class='fas fa-times'></i> Image upload failed due to server permissions.</div>";
                }
            } else {
                $message = "<div class='alert-error'><i class='fas fa-times'></i> Invalid file format! Only JPG, PNG, WEBP, and GIF are allowed.</div>";
            }
        }

        // Proceed if no image error occurred
        if (empty($message)) {
            // Update query including STOCK
            $update_sql = "UPDATE product SET name=?, price=?, stock=?, description=?, gender=?, image=? WHERE id=? AND seller_id=?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sdisssii", $name, $price, $stock, $desc, $gender, $db_image_path, $product_id, $seller_id);

            if ($update_stmt->execute()) {
                // SUCCESS POPUP AND REDIRECT
                $message = "
                <div class='cart-popup-overlay'>
                    <div class='cart-popup-box animated-box'>
                        <div class='popup-icon icon-cart'><i class='fas fa-check'></i></div>
                        <h2 style='font-family: \"Playfair Display\", serif; color: #5E5343; margin-bottom: 5px;'>Update Successful!</h2>
                        <p style='color: #777; font-size: 14px; margin-bottom: 10px;'>Your product details have been saved.</p>
                        <p style='color: #A1937A; font-size: 12px;'><i class='fas fa-spinner fa-spin'></i> Redirecting to My Products...</p>
                    </div>
                </div>
                <script>setTimeout(() => { window.location.href = 'manage_products.php'; }, 1800);</script>";

                // Update local array so the form shows the new data instantly behind the popup
                $product['name'] = $name;
                $product['price'] = $price;
                $product['stock'] = $stock;
                $product['description'] = $desc;
                $product['gender'] = $gender;
                $product['image'] = $db_image_path;
            } else {
                $message = "<div class='alert-error'><i class='fas fa-exclamation-circle'></i> Database Error: " . $conn->error . "</div>";
            }
            $update_stmt->close();
        }
    }
}
?>

<?php include 'include/header.php'; ?>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">

    <div style="max-width: 950px; margin: 0 auto;">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin: 0;">
                Edit Fragrance
            </h1>
            <a href="manage_products.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Products
            </a>
        </div>

        <div class="form-card">
            <?= $message; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="layout-split">
                    <div class="image-column">
                        <div class="form-group" style="height: 100%; display: flex; flex-direction: column; margin-bottom: 0;">
                            <label>Product Image</label>
                            <div class="file-upload-box" id="upload-box">
                                <input type="file" name="image" id="file" accept="image/*" onchange="previewImage(event)" <?= ($status !== 'approved') ? 'disabled' : '' ?>>
                                <div class="upload-content" id="upload-content" style="display: none;">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Click to Change Image</span>
                                </div>
                                <img id="img-preview" src="../<?= htmlspecialchars($product['image']); ?>" alt="Current Image">

                                <div class="edit-overlay">
                                    <i class="fas fa-camera"></i> Change Image
                                </div>
                            </div>
                            <p style="font-size: 11px; color: #999; margin-top: 10px; text-align: center;">Leave empty to keep the current image.</p>
                        </div>
                    </div>

                    <div class="info-column">
                        <div class="form-group">
                            <label>Perfume Name</label>
                            <div class="input-icon">
                                <i class="fas fa-tag"></i>
                                <input type="text" name="name" required value="<?= htmlspecialchars($product['name']); ?>" <?= ($status !== 'approved') ? 'disabled' : '' ?>>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col">
                                <label>Price (₹)</label>
                                <div class="input-icon">
                                    <i class="fas fa-rupee-sign"></i>
                                    <input type="number" name="price" step="0.01" min="1" required value="<?= htmlspecialchars($product['price']); ?>" <?= ($status !== 'approved') ? 'disabled' : '' ?>>
                                </div>
                            </div>
                            <div class="form-group col">
                                <label>Stock Qty</label>
                                <div class="input-icon">
                                    <i class="fas fa-cubes"></i>
                                    <input type="number" name="stock" min="0" required value="<?= htmlspecialchars($product['stock'] ?? 0); ?>" <?= ($status !== 'approved') ? 'disabled' : '' ?>>
                                </div>
                            </div>
                            <div class="form-group col">
                                <label>Category</label>
                                <div class="input-icon">
                                    <i class="fas fa-venus-mars"></i>
                                    <select name="gender" required <?= ($status !== 'approved') ? 'disabled' : '' ?>>
                                        <option value="men" <?= ($product['gender'] === 'men') ? 'selected' : ''; ?>>Men</option>
                                        <option value="women" <?= ($product['gender'] === 'women') ? 'selected' : ''; ?>>Women</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="flex-grow: 1; display: flex; flex-direction: column;">
                            <label>Description</label>
                            <textarea name="description" required style="flex-grow: 1; min-height: 120px;" <?= ($status !== 'approved') ? 'disabled' : '' ?>><?= htmlspecialchars($product['description']); ?></textarea>
                        </div>

                        <div style="display: flex; gap: 15px; margin-top: 10px;">
                            <button type="submit" class="btn-submit" <?= ($status !== 'approved') ? 'disabled style="background:#ccc; cursor:not-allowed;"' : '' ?>>
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <a href="manage_products.php" class="btn-cancel">Cancel</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>

<style>
    /* === BACK LINK FIX === */
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        color: #888;
        font-size: 13px;
        font-weight: 600;
        transition: color 0.3s ease;
    }

    .back-link:hover {
        color: #5E5343;
    }

    /* === LAYOUT SPLIT STYLES === */
    .layout-split {
        display: flex;
        gap: 40px;
        align-items: stretch;
    }

    .image-column {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .info-column {
        flex: 1.5;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    /* === DESIGN STYLES === */
    .form-card {
        background: #F5F1E9;
        padding: 40px;
        border-radius: 12px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.03);
        border: 1px solid #e0e0e0;
    }

    .form-group {
        margin-bottom: 22px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        color: #5E5343;
        margin-bottom: 8px;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .input-icon {
        position: relative;
    }

    .input-icon i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #B2A48C;
    }

    .input-icon input,
    .input-icon select {
        width: 100%;
        padding: 14px 15px 14px 45px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 14px;
        background: #fff;
        outline: none;
        transition: 0.3s;
        box-sizing: border-box;
    }

    .input-icon input:focus,
    .input-icon select:focus,
    textarea:focus {
        border-color: #5E5343;
        background: #fff;
        box-shadow: 0 0 5px rgba(94, 83, 67, 0.1);
    }

    .input-icon input:disabled,
    .input-icon select:disabled,
    textarea:disabled {
        background-color: #f0f0f0;
        cursor: not-allowed;
        color: #999;
    }

    textarea {
        width: 100%;
        padding: 15px;
        border: 1px solid #ccc;
        border-radius: 8px;
        background: #fff;
        font-family: 'Poppins', sans-serif;
        font-size: 14px;
        outline: none;
        box-sizing: border-box;
        resize: vertical;
    }

    .form-row {
        display: flex;
        gap: 15px;
    }

    .col {
        flex: 1;
        min-width: 0;
    }

    /* File Upload */
    .file-upload-box {
        position: relative;
        flex-grow: 1;
        min-height: 350px;
        border: 2px dashed #B2A48C;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #fff;
        cursor: pointer;
        transition: 0.3s;
        overflow: hidden;
    }

    .file-upload-box:hover {
        border-color: #5E5343;
    }

    .file-upload-box input {
        position: absolute;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
        z-index: 10;
    }

    .file-upload-box input:disabled {
        cursor: not-allowed;
    }

    #img-preview {
        width: 100%;
        height: 100%;
        object-fit: cover;
        position: absolute;
        top: 0;
        left: 0;
        z-index: 2;
    }

    .edit-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        background: rgba(94, 83, 67, 0.8);
        color: #fff;
        text-align: center;
        padding: 10px 0;
        font-size: 13px;
        font-weight: 600;
        z-index: 5;
        transform: translateY(100%);
        transition: transform 0.3s ease;
    }

    .file-upload-box:hover .edit-overlay {
        transform: translateY(0);
    }

    .upload-content {
        text-align: center;
        color: #5E5343;
        pointer-events: none;
        position: absolute;
        z-index: 5;
    }

    .upload-content i {
        font-size: 36px;
        display: block;
        margin-bottom: 10px;
        color: #B2A48C;
    }

    /* Buttons */
    .btn-submit {
        flex: 2;
        padding: 15px;
        background: #5E5343;
        color: #fff;
        border: none;
        border-radius: 50px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        font-size: 15px;
    }

    .btn-submit:hover:not(:disabled) {
        background: #A1937A;
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .btn-cancel {
        flex: 1;
        padding: 15px;
        background: transparent;
        color: #5E5343;
        border: 2px solid #5E5343;
        border-radius: 50px;
        font-weight: 600;
        text-decoration: none;
        transition: 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-cancel:hover {
        background: #5E5343;
        color: #fff;
    }

    .alert-error {
        background: #f8d7da;
        color: #721c24;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 25px;
        border: 1px solid #f5c6cb;
    }

    /* === PREMIUM POPUP OVERLAYS === */
    .cart-popup-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(5px);
        z-index: 10000;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .cart-popup-box {
        background: #fff;
        padding: 40px;
        border-radius: 20px;
        text-align: center;
        width: 100%;
        max-width: 420px;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        border: 2px solid #e0e0e0;
    }

    .animated-box {
        animation: popUp 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }

    @keyframes popUp {
        0% {
            transform: scale(0.8);
            opacity: 0;
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .popup-icon {
        width: 75px;
        height: 75px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        color: #fff;
        margin: 0 auto 20px;
    }

    .icon-cart {
        background: #27ae60;
        box-shadow: 0 0 0 rgba(39, 174, 96, 0.4);
        animation: pulseCart 1.5s infinite;
    }

    @keyframes pulseCart {
        0% {
            box-shadow: 0 0 0 0 rgba(39, 174, 96, 0.7);
        }

        70% {
            box-shadow: 0 0 0 15px rgba(39, 174, 96, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(39, 174, 96, 0);
        }
    }

    /* Responsive Details */
    @media (max-width: 850px) {
        .layout-split {
            flex-direction: column;
            gap: 20px;
        }

        .file-upload-box {
            min-height: 280px;
        }

        .form-row {
            flex-direction: column;
            gap: 0;
        }

        .form-row .form-group {
            margin-bottom: 15px;
        }
    }
</style>

<script>
    function previewImage(event) {
        const fileInput = event.target;
        const output = document.getElementById('img-preview');
        const uploadContent = document.getElementById('upload-content');

        if (fileInput.files && fileInput.files[0]) {
            const reader = new FileReader();

            reader.onload = function(e) {
                output.src = e.target.result;
                output.style.display = 'block';
                if (uploadContent) uploadContent.style.display = 'none';
            }

            reader.readAsDataURL(fileInput.files[0]);
        }
    }
</script>

<?php include 'include/footer.php'; ?>