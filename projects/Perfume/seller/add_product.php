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

// 2. LIVE APPROVAL CHECK (Prevents suspended sellers from adding products)
$chk = $conn->prepare("SELECT status FROM sellers WHERE id = ?");
$chk->bind_param("i", $seller_id);
$chk->execute();
$chk->bind_result($status);
$chk->fetch();
$chk->close();

if ($status !== 'approved') {
    $message = "<div class='alert-error'><i class='fas fa-ban'></i> Your account is currently <b>" . strtoupper($status) . "</b>. You cannot add products until approved by the Admin.</div>";
} else {
    // 3. Handle Form Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name']);
        $price = floatval($_POST['price']);
        $stock = intval($_POST['stock']); // Fetching Stock Quantity
        $desc = trim($_POST['description']);
        $gender = $_POST['gender'];

        // Secure Image Upload
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
                $db_image_path = "uploads/" . $new_img_name;

                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {

                    // Database insert query including STOCK
                    $sql = "INSERT INTO product (name, price, stock, description, gender, image, seller_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    // Bind parameters: s=string, d=double, i=integer
                    $stmt->bind_param("sdisssi", $name, $price, $stock, $desc, $gender, $db_image_path, $seller_id);

                    if ($stmt->execute()) {
                        $message = "<div class='alert-success'><i class='fas fa-check-circle'></i> Product added successfully! Redirecting...</div>";
                        $message .= "<script>setTimeout(() => { window.location.href = 'manage_products.php'; }, 1500);</script>";
                    } else {
                        $message = "<div class='alert-error'><i class='fas fa-exclamation-circle'></i> Database Error: " . $conn->error . "</div>";
                    }
                    $stmt->close();
                } else {
                    $message = "<div class='alert-error'><i class='fas fa-times'></i> Image upload failed due to server permissions.</div>";
                }
            } else {
                $message = "<div class='alert-error'><i class='fas fa-times'></i> Invalid file format! Only JPG, PNG, WEBP, and GIF are allowed.</div>";
            }
        } else {
            $message = "<div class='alert-error'><i class='fas fa-times'></i> Please select a valid image file.</div>";
        }
    }
}
?>

<?php include 'include/header.php'; ?>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">

    <div style="max-width: 950px; margin: 0 auto;">
        <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin-bottom: 25px; text-align: center;">
            Add New Fragrance
        </h1>

        <div class="form-card">
            <?= $message; ?>

            <form method="post" enctype="multipart/form-data">

                <div class="layout-split">

                    <div class="image-column">
                        <div class="form-group" style="height: 100%; display: flex; flex-direction: column; margin-bottom: 0;">
                            <label>Product Image</label>
                            <div class="file-upload-box" id="upload-box">
                                <input type="file" name="image" id="file" accept="image/*" required onchange="previewImage(event)" <?= ($status !== 'approved') ? 'disabled' : '' ?>>
                                <div class="upload-content" id="upload-content">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Click to Upload Image</span>
                                </div>
                                <img id="img-preview" src="#" alt="Preview" style="display: none;">
                            </div>
                        </div>
                    </div>

                    <div class="info-column">
                        <div class="form-group">
                            <label>Perfume Name</label>
                            <div class="input-icon">
                                <i class="fas fa-tag"></i>
                                <input type="text" name="name" required placeholder="e.g. Royal Oudh" <?= ($status !== 'approved') ? 'disabled' : '' ?>>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col">
                                <label>Price (₹)</label>
                                <div class="input-icon">
                                    <i class="fas fa-rupee-sign"></i>
                                    <input type="number" name="price" step="0.01" min="1" required placeholder="1500" <?= ($status !== 'approved') ? 'disabled' : '' ?>>
                                </div>
                            </div>
                            <div class="form-group col">
                                <label>Stock Qty</label>
                                <div class="input-icon">
                                    <i class="fas fa-cubes"></i>
                                    <input type="number" name="stock" min="1" required placeholder="e.g. 50" <?= ($status !== 'approved') ? 'disabled' : '' ?>>
                                </div>
                            </div>
                            <div class="form-group col">
                                <label>Category</label>
                                <div class="input-icon">
                                    <i class="fas fa-venus-mars"></i>
                                    <select name="gender" required <?= ($status !== 'approved') ? 'disabled' : '' ?>>
                                        <option value="" disabled selected>Select Gender</option>
                                        <option value="men">Men</option>
                                        <option value="women">Women</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="flex-grow: 1; display: flex; flex-direction: column;">
                            <label>Description</label>
                            <textarea name="description" required placeholder="Describe notes (Top, Heart, Base)..." style="flex-grow: 1; min-height: 120px;" <?= ($status !== 'approved') ? 'disabled' : '' ?>></textarea>
                        </div>

                        <button type="submit" class="btn-submit" <?= ($status !== 'approved') ? 'disabled style="background:#ccc; cursor:not-allowed; color:#666;"' : '' ?>>
                            <i class="fas fa-paper-plane"></i> Submit Product
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>
</main>

<style>
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
        background: #fffdf5;
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

    #img-preview {
        width: 100%;
        height: 100%;
        object-fit: cover;
        position: absolute;
        top: 0;
        left: 0;
        z-index: 2;
    }

    .btn-submit {
        width: 100%;
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
        margin-top: 10px;
        font-size: 15px;
    }

    .btn-submit:hover:not(:disabled) {
        background: #A1937A;
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 25px;
        border: 1px solid #c3e6cb;
    }

    .alert-error {
        background: #f8d7da;
        color: #721c24;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 25px;
        border: 1px solid #f5c6cb;
    }

    @media (max-width: 850px) {
        .layout-split {
            flex-direction: column;
            gap: 20px;
        }

        .file-upload-box {
            min-height: 250px;
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
                uploadContent.style.display = 'none';
            }

            reader.readAsDataURL(fileInput.files[0]);
        }
    }
</script>

<?php include 'include/footer.php'; ?>