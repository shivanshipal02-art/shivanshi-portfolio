<?php
session_start();
// 1. Security Check
if (!isset($_SESSION['seller_logged_in']) || $_SESSION['seller_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

include '../include/connection.php';
$seller_id = $_SESSION['id'];

// 2. Fetch Seller Data (FIXED: Added education and experience)
$userSql = "SELECT owner_name, email, phone, business_name, address, city, pincode, pan_card, aadhar_card, gst_number, education, experience FROM sellers WHERE id=?";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param('i', $seller_id);
$userStmt->execute();
$userStmt->bind_result($uname, $email, $phone, $b_name, $address, $city, $pincode, $pan, $aadhar, $gst, $education, $experience);
$userStmt->fetch();
$userStmt->close();

// 3. Handle Profile Update
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $newUsername = $_POST['username'] ?? '';
    $newBusinessName = $_POST['business_name'] ?? '';
    $newPhone = $_POST['phone'] ?? '';
    $newAddress = $_POST['address'] ?? '';
    $newCity = $_POST['city'] ?? '';
    $newPincode = $_POST['pincode'] ?? '';
    $newEducation = $_POST['education'] ?? '';
    $newExperience = $_POST['experience'] ?? '';

    // FIXED: Added education and experience to the UPDATE query
    $updateSql = "UPDATE sellers SET owner_name=?, business_name=?, phone=?, address=?, city=?, pincode=?, education=?, experience=? WHERE id=?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param('ssssssssi', $newUsername, $newBusinessName, $newPhone, $newAddress, $newCity, $newPincode, $newEducation, $newExperience, $seller_id);

    if ($updateStmt->execute()) {
        $msg = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Profile updated successfully!</div>";
        // Refresh values
        $uname = $newUsername;
        $b_name = $newBusinessName;
        $phone = $newPhone;
        $address = $newAddress;
        $city = $newCity;
        $pincode = $newPincode;
        $education = $newEducation;
        $experience = $newExperience;
        $_SESSION['username'] = $uname; // Update session
    } else {
        $msg = "<div class='alert alert-error'><i class='fas fa-exclamation-circle'></i> Failed to update profile.</div>";
    }
}

include 'include/header.php';
?>

<main style="flex: 1; padding: 40px; background-color: #fff;">

    <div class="profile-header">
        <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; font-size: 2rem; margin:0;">My Profile</h1>
        <a href="change_password.php" class="btn-change-pass"><i class="fas fa-lock"></i> Change Password</a>
    </div>

    <?= $msg; ?>

    <div class="profile-card">
        <form method="POST" id="profileForm">

            <h3 class="section-title">Personal & Business Details</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($uname ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Business Name</label>
                    <input type="text" name="business_name" value="<?= htmlspecialchars($b_name ?? ''); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Email Address (Read Only)</label>
                    <input type="email" value="<?= htmlspecialchars($email ?? ''); ?>" readonly style="background-color: #eee; cursor: not-allowed;">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" id="phoneInput" value="<?= htmlspecialchars($phone ?? ''); ?>" maxlength="10" required>
                    <div id="phoneError" class="error-msg" style="display:none; color:red; font-size:12px; margin-top:5px;"></div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Education</label>
                    <input type="text" name="education" value="<?= htmlspecialchars($education ?? ''); ?>" placeholder="e.g. BBA, MBA, Graduate">
                </div>
                <div class="form-group">
                    <label>Experience</label>
                    <input type="text" name="experience" value="<?= htmlspecialchars($experience ?? ''); ?>" placeholder="e.g. 5 Years">
                </div>
            </div>

            <h3 class="section-title" style="margin-top: 20px;">Identity & Taxes (Read Only)</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>PAN Card</label>
                    <input type="text" value="<?= htmlspecialchars($pan ?? ''); ?>" readonly style="background-color: #eee;">
                </div>
                <div class="form-group">
                    <label>Aadhar Card</label>
                    <input type="text" value="<?= htmlspecialchars($aadhar ?? ''); ?>" readonly style="background-color: #eee;">
                </div>
                <div class="form-group">
                    <label>GST Number</label>
                    <input type="text" value="<?= !empty($gst) ? htmlspecialchars($gst) : 'N/A'; ?>" readonly style="background-color: #eee;">
                </div>
            </div>

            <h3 class="section-title" style="margin-top: 20px;">Store Address</h3>
            <div class="form-group">
                <label>Address</label>
                <textarea name="address" rows="3" required><?= htmlspecialchars($address ?? ''); ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" value="<?= htmlspecialchars($city ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Pincode</label>
                    <input type="text" name="pincode" value="<?= htmlspecialchars($pincode ?? ''); ?>" maxlength="6" required>
                </div>
            </div>

            <button type="submit" name="update_profile" class="btn-update">Save Changes</button>
        </form>
    </div>
</main>

<style>
    /* Styling matched directly from User Panel profile.php */
    .profile-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }

    .btn-change-pass {
        background-color: transparent;
        color: #5E5343;
        border: 2px solid #5E5343;
        padding: 10px 20px;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 600;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-change-pass:hover {
        background-color: #5E5343;
        color: #fff;
    }

    .profile-card {
        background: #F5F1E9;
        padding: 40px;
        border-radius: 12px;
        border: 1px solid #e0e0e0;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.03);
        max-width: 800px;
    }

    .section-title {
        font-family: 'Playfair Display', serif;
        color: #A1937A;
        margin-bottom: 15px;
        border-bottom: 1px solid #ddd;
        padding-bottom: 5px;
        font-size: 1.2rem;
    }

    .form-row {
        display: flex;
        gap: 20px;
    }

    .form-group {
        flex: 1;
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        color: #5E5343;
        margin-bottom: 8px;
        font-size: 13px;
        text-transform: uppercase;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 14px;
        font-family: 'Poppins', sans-serif;
        outline: none;
        transition: 0.3s;
        box-sizing: border-box;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        border-color: #5E5343;
    }

    .input-error {
        border-color: red !important;
    }

    .btn-update {
        background-color: #5E5343;
        color: white;
        border: none;
        padding: 14px 30px;
        border-radius: 50px;
        cursor: pointer;
        font-weight: 600;
        font-size: 15px;
        transition: 0.3s;
        width: 100%;
        margin-top: 10px;
    }

    .btn-update:hover {
        background-color: #A1937A;
        transform: translateY(-2px);
    }

    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 25px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
        max-width: 800px;
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    @media (max-width: 768px) {
        .form-row {
            flex-direction: column;
            gap: 0;
        }

        .profile-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const phoneInput = document.getElementById('phoneInput');
        const phoneError = document.getElementById('phoneError');
        const form = document.getElementById('profileForm');

        function validatePhone() {
            if (phoneInput.value !== '') {
                if (phoneInput.value.length !== 10) {
                    phoneError.style.display = 'block';
                    phoneError.innerText = "Phone number must be exactly 10 digits.";
                    phoneInput.classList.add('input-error');
                    return false;
                } else {
                    phoneError.style.display = 'none';
                    phoneInput.classList.remove('input-error');
                    return true;
                }
            }
            return true;
        }

        phoneInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            validatePhone();
        });

        form.addEventListener('submit', function(e) {
            if (!validatePhone()) e.preventDefault();
        });
    });
</script>

<?php include 'include/footer.php'; ?>