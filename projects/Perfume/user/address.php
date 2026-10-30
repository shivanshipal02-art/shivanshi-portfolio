<?php
session_start();
// 1. Security Check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

include '../include/connection.php';

// 2. Fetch User ID & Email
$username = $_SESSION['username'] ?? '';
$userSql = "SELECT id, email FROM users WHERE username=?";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param('s', $username);
$userStmt->execute();
$userStmt->bind_result($userId, $userEmail);
$userStmt->fetch();
$userStmt->close();

// --- EDIT MODE LOGIC ---
$edit_mode = false;
$edit_id = 0;
$edit_data = [
    'category' => 'Home', 'custom_category' => '', 'name' => '', 'phone' => '',
    'delivery_address' => '', 'city' => '', 'pincode' => ''
];

if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_stmt = $conn->prepare("SELECT * FROM address WHERE id=? AND user_id=?");
    $edit_stmt->bind_param("ii", $edit_id, $userId);
    $edit_stmt->execute();
    $res = $edit_stmt->get_result();
    if ($res->num_rows > 0) {
        $edit_mode = true;
        $edit_data = $res->fetch_assoc();
        
        // Check if category is standard or custom
        if (!in_array($edit_data['category'], ['Home', 'Work'])) {
            $edit_data['custom_category'] = $edit_data['category'];
            $edit_data['category'] = 'Other';
        }
    }
    $edit_stmt->close();
}

// 3. Handle Add & Update Address
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_address']) || isset($_POST['update_address'])) {
        
        $category = trim($_POST['category']);
        // If "Other" is selected, use the custom typed category
        if ($category === 'Other') {
            $category = trim($_POST['custom_category']);
            if (empty($category)) $category = 'Other'; // Fallback
        }

        $r_name = trim($_POST['name']);
        $r_phone = trim($_POST['phone']);
        $r_address = trim($_POST['delivery_address']);
        $r_city = trim($_POST['city']);
        $r_pincode = trim($_POST['pincode']);

        if (isset($_POST['update_address'])) {
            $u_id_post = intval($_POST['address_id']);
            $update_stmt = $conn->prepare("UPDATE address SET name=?, phone=?, delivery_address=?, city=?, pincode=?, category=? WHERE id=? AND user_id=?");
            $update_stmt->bind_param("ssssssii", $r_name, $r_phone, $r_address, $r_city, $r_pincode, $category, $u_id_post, $userId);
            
            if ($update_stmt->execute()) {
                echo '<script>alert("Address updated successfully!"); window.location.href="address.php";</script>';
            } else {
                echo '<script>alert("Failed to update address. Try again.");</script>';
            }
            $update_stmt->close();

        } else {
            $addr_stmt = $conn->prepare("INSERT INTO address (user_id, name, email, phone, delivery_address, city, pincode, category) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $addr_stmt->bind_param("isssssss", $userId, $r_name, $userEmail, $r_phone, $r_address, $r_city, $r_pincode, $category);
            
            if ($addr_stmt->execute()) {
                echo '<script>alert("Address added successfully!"); window.location.href="address.php";</script>';
            } else {
                echo '<script>alert("Failed to add address. Try again.");</script>';
            }
            $addr_stmt->close();
        }
    }
}

// 4. Handle Delete Address
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $del_stmt = $conn->prepare("DELETE FROM address WHERE id=? AND user_id=?");
    $del_stmt->bind_param("ii", $del_id, $userId);
    if ($del_stmt->execute()) {
        header('Location: address.php');
        exit;
    }
    $del_stmt->close();
}

// 5. Fetch All Saved Addresses
$addresses = [];
$fetch_addr = $conn->query("SELECT * FROM address WHERE user_id=$userId ORDER BY id DESC");
if ($fetch_addr) {
    while ($row = $fetch_addr->fetch_assoc()) {
        $addresses[] = $row;
    }
}

include 'include/header.php';
?>

<main style="flex: 1; padding: 40px; background-color: #fff;">

    <div style="max-width: 1000px; margin: 0 auto; display: flex; flex-wrap: wrap; gap: 40px; align-items: flex-start;">
        
        <div class="profile-container" style="flex: 1; min-width: 350px;">
            <h2 style="font-family: 'Playfair Display', serif; font-size: 1.5rem; color: #5E5343; margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                <?= $edit_mode ? 'Edit Address' : 'Add New Address'; ?>
            </h2>

            <form method="post" class="profile-form">
                <?php if($edit_mode): ?>
                    <input type="hidden" name="address_id" value="<?= $edit_id; ?>">
                <?php endif; ?>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label>Save As (Category)</label>
                    <div class="input-icon">
                        <i class="fas fa-tag"></i>
                        <select name="category" id="categorySelect" required onchange="toggleCustomCategory()">
                            <option value="Home" <?= ($edit_data['category'] == 'Home') ? 'selected' : ''; ?>>Home</option>
                            <option value="Work" <?= ($edit_data['category'] == 'Work') ? 'selected' : ''; ?>>Work / Office</option>
                            <option value="Other" <?= ($edit_data['category'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="customCategoryDiv" style="display: <?= ($edit_data['category'] == 'Other') ? 'block' : 'none'; ?>; margin-bottom: 18px;">
                    <label>Custom Name <span style="font-size:11px; color:#888; font-weight:normal;">(e.g. Gym, Friend's House)</span></label>
                    <div class="input-icon">
                        <i class="fas fa-pencil-alt"></i>
                        <input type="text" name="custom_category" id="customCategoryInput" placeholder="Enter custom name..." value="<?= htmlspecialchars($edit_data['custom_category']); ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label>Receiver Name</label>
                    <div class="input-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" name="name" required placeholder="Full Name" value="<?= htmlspecialchars($edit_data['name']); ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label>Phone Number</label>
                    <div class="input-icon">
                        <i class="fas fa-phone"></i>
                        <input type="text" name="phone" id="phoneInput" required maxlength="10" placeholder="10-digit number" value="<?= htmlspecialchars($edit_data['phone']); ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label>Street Address / Area</label>
                    <div class="input-icon">
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" name="delivery_address" required placeholder="House No, Street, Landmark" value="<?= htmlspecialchars($edit_data['delivery_address']); ?>">
                    </div>
                </div>

                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 18px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>City</label>
                        <div class="input-icon">
                            <i class="fas fa-city"></i>
                            <input type="text" name="city" required placeholder="City" value="<?= htmlspecialchars($edit_data['city']); ?>">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Pincode</label>
                        <div class="input-icon">
                            <i class="fas fa-map-pin"></i>
                            <input type="text" name="pincode" id="pincodeInput" required maxlength="6" placeholder="6 Digits" value="<?= htmlspecialchars($edit_data['pincode']); ?>">
                        </div>
                    </div>
                </div>

                <?php if($edit_mode): ?>
                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                        <button type="submit" name="update_address" class="btn-update" style="flex: 1;">
                            <i class="fas fa-save"></i> Update
                        </button>
                        <a href="address.php" class="btn-update" style="flex: 1; background: transparent; border: 2px solid #5E5343; color: #5E5343; text-decoration: none; text-align: center;">
                            Cancel
                        </a>
                    </div>
                <?php else: ?>
                    <button type="submit" name="add_address" class="btn-update" style="width: 100%; margin-top: 10px;">
                        <i class="fas fa-plus-circle"></i> Save Address
                    </button>
                <?php endif; ?>
            </form>
        </div>

        <div style="flex: 1.2; min-width: 350px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin: 0;">Saved Addresses</h1>
                <a href="profile.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Profile</a>
            </div>

            <?php if(count($addresses) > 0): ?>
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <?php foreach($addresses as $addr): 
                        // Determine badge color class based on category
                        $badgeClass = 'cat-other';
                        $cat_lower = strtolower($addr['category'] ?? 'home');
                        if ($cat_lower === 'home') $badgeClass = 'cat-home';
                        if ($cat_lower === 'work' || $cat_lower === 'office') $badgeClass = 'cat-work';
                    ?>
                        <div class="address-card">
                            <div class="card-header">
                                <span class="badge <?= $badgeClass; ?>">
                                    <?= htmlspecialchars($addr['category'] ?? 'Home'); ?>
                                </span>
                                <div style="display: flex; gap: 10px;">
                                    <a href="address.php?edit=<?= $addr['id']; ?>" class="btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="address.php?delete=<?= $addr['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this address?');">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                </div>
                            </div>
                            <h3 style="margin: 0 0 5px 0; color: #5E5343; font-size: 16px;"><?= htmlspecialchars($addr['name']); ?></h3>
                            <p style="margin: 0 0 10px 0; color: #666; font-size: 13px;">
                                <i class="fas fa-phone-alt" style="color: #A1937A; font-size: 11px; margin-right: 5px;"></i> +91 <?= htmlspecialchars($addr['phone']); ?>
                            </p>
                            <p style="margin: 0; color: #555; font-size: 14px; line-height: 1.5;">
                                <?= htmlspecialchars($addr['delivery_address']); ?><br>
                                <?= htmlspecialchars($addr['city']); ?> - <strong><?= htmlspecialchars($addr['pincode']); ?></strong>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 50px 20px; background: #fdfdfd; border: 1px dashed #ccc; border-radius: 12px; color: #999;">
                    <i class="fas fa-map-marked-alt" style="font-size: 40px; color: #E2E2DE; margin-bottom: 15px;"></i>
                    <p>No addresses saved yet.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<style>
    /* Reuse Profile Form Styles */
    .profile-container { background: #F5F1E9; padding: 35px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); }
    .form-group label { display: block; font-weight: 600; color: #5E5343; margin-bottom: 8px; font-size: 0.95rem; }
    .input-icon { position: relative; }
    .input-icon i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #B2A48C; font-size: 1rem; }
    .input-icon input, .input-icon select { width: 100%; padding: 12px 15px 12px 45px; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem; color: #333; background: #fff; transition: 0.3s ease; box-sizing: border-box; }
    .input-icon input:focus, .input-icon select:focus { border-color: #5E5343; outline: none; }
    .btn-update { background: #1abc9c; color: #fff; border: none; padding: 12px 25px; border-radius: 6px; font-size: 1rem; font-weight: 600; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 10px; transition: 0.3s; }
    .btn-update:hover { background: #16a085; }
    
    /* Saved Address Card Styles */
    .address-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); transition: 0.3s; }
    .address-card:hover { border-color: #A1937A; box-shadow: 0 5px 15px rgba(161, 147, 122, 0.1); }
    .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px dashed #eee; padding-bottom: 10px; }
    
    /* Category Badges */
    .badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
    .cat-home { background: #e3f2fd; color: #2980b9; border: 1px solid #bbdefb; }
    .cat-work, .cat-office { background: #fdf2e9; color: #f39c12; border: 1px solid #fdebd0; }
    .cat-other { background: #f4f6f6; color: #7f8c8d; border: 1px solid #eaeded; }

    /* Action Buttons */
    .btn-edit { color: #3498db; text-decoration: none; font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 5px; transition: 0.3s; padding: 5px 10px; border-radius: 4px; }
    .btn-edit:hover { background: #ebf5fb; }
    .btn-delete { color: #e74c3c; text-decoration: none; font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 5px; transition: 0.3s; padding: 5px 10px; border-radius: 4px; }
    .btn-delete:hover { background: #fce4e4; }
    .back-link { color: #999; text-decoration: none; font-size: 13px; font-weight: 600; transition: 0.3s; }
    .back-link:hover { color: #5E5343; }
</style>

<script>
    // Show/Hide Custom Category Input
    function toggleCustomCategory() {
        const select = document.getElementById('categorySelect');
        const customDiv = document.getElementById('customCategoryDiv');
        const customInput = document.getElementById('customCategoryInput');

        if (select.value === 'Other') {
            customDiv.style.display = 'block';
            customInput.required = true;
        } else {
            customDiv.style.display = 'none';
            customInput.required = false;
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const phoneInput = document.getElementById('phoneInput');
        const pincodeInput = document.getElementById('pincodeInput');

        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        }
        if (pincodeInput) {
            pincodeInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        }
    });
</script>

<?php include 'include/footer.php'; ?>