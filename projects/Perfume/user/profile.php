<?php
session_start();
// 1. Security Check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

include '../include/connection.php';
$username = $_SESSION['username'] ?? '';

// 2. Fetch User Data
$userSql = "SELECT username, email, phone, gender, address, city, pincode, dob FROM users WHERE username=?";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param('s', $username);
$userStmt->execute();
$userStmt->bind_result($uname, $email, $phone, $gender, $address, $city, $pincode, $dob);
$userStmt->fetch();
$userStmt->close();

// 3. Handle Profile Update
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $newUsername = $_POST['username'] ?? '';
    $newPhone = $_POST['phone'] ?? '';
    $newGender = $_POST['gender'] ?? '';
    $newAddress = $_POST['address'] ?? '';
    $newCity = $_POST['city'] ?? '';
    $newPincode = $_POST['pincode'] ?? '';
    $newDob = $_POST['dob'] ?? '';

    $updateSql = "UPDATE users SET username=?, phone=?, gender=?, address=?, city=?, pincode=?, dob=? WHERE username=?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param('ssssssss', $newUsername, $newPhone, $newGender, $newAddress, $newCity, $newPincode, $newDob, $username);

    if ($updateStmt->execute()) {
        // Update Session & Variables
        $_SESSION['username'] = $newUsername;
        $username = $newUsername;
        $uname = $newUsername;
        $phone = $newPhone;
        $gender = $newGender;
        $address = $newAddress;
        $city = $newCity;
        $pincode = $newPincode;
        $dob = $newDob;

        echo '<script>alert("Profile updated successfully!"); window.location.href="profile.php";</script>';
    } else {
        echo '<script>alert("Update failed. Try again.");</script>';
    }
    $updateStmt->close();
}
$conn->close();

include 'include/header.php';
?>

<main style="flex: 1; padding: 40px; background-color: #fff;">

    <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin-bottom: 30px;">
        My Profile
    </h1>

    <div class="profile-container">

        <form method="post" class="profile-form" id="profileForm">

            <h2 style="font-size: 1.5rem; color: #5E5343; margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                Edit Profile
            </h2>

            <div class="form-grid">

                <div class="form-group">
                    <label>Username</label>
                    <div class="input-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" value="<?= htmlspecialchars($uname) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address <span style="font-size: 11px; color: #888; font-weight: normal;">(Cannot be changed)</span></label>
                    <div class="input-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" id="emailInput" value="<?= htmlspecialchars($email) ?>" readonly style="background-color: #f0f0f0; color: #888; cursor: not-allowed;" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <div class="input-icon">
                        <i class="fas fa-phone"></i>
                        <input type="text" name="phone" id="phoneInput" value="<?= htmlspecialchars($phone) ?>" maxlength="10">
                    </div>
                    <span class="error-msg" id="phoneError"></span>
                </div>

                <div class="form-group">
                    <label>Date of Birth</label>
                    <div class="input-icon">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="date" name="dob" value="<?= htmlspecialchars($dob) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Gender</label>
                    <div class="input-icon">
                        <i class="fas fa-venus-mars"></i>
                        <select name="gender">
                            <option value="" disabled <?= empty($gender) ? 'selected' : '' ?>>Select Gender</option>
                            <option value="male" <?= $gender === 'male' ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= $gender === 'female' ? 'selected' : '' ?>>Female</option>
                            <option value="other" <?= $gender === 'other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>City</label>
                    <div class="input-icon">
                        <i class="fas fa-city"></i>
                        <input type="text" name="city" value="<?= htmlspecialchars($city ?? '') ?>" placeholder="Your City">
                    </div>
                </div>

                <div class="form-group">
                    <label>Pincode</label>
                    <div class="input-icon">
                        <i class="fas fa-map-pin"></i>
                        <input type="text" name="pincode" id="pincodeInput" value="<?= htmlspecialchars($pincode ?? '') ?>" maxlength="6" placeholder="6 Digit Pincode">
                    </div>
                </div>

                <div class="form-group full-width">
                    <label>Shipping Address</label>
                    <div class="input-icon">
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" name="address" value="<?= htmlspecialchars($address) ?>" placeholder="Your Full Address">
                    </div>
                </div>

            </div>

            <div class="btn-row">
                <button type="submit" name="update_profile" class="btn-update">
                    <i class="fas fa-save"></i> Update Profile
                </button>

                <a href="change_password.php" class="btn-password">
                    <i class="fas fa-key"></i> Change Password
                </a>

                <a href="address.php" class="btn-address">
                    <i class="fas fa-map-marked-alt"></i> Manage Addresses
                </a>
            </div>

        </form>
    </div>

</main>

<style>
    .profile-container {
        max-width: 800px;
        background: #F5F1E9;
        padding: 40px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
        margin-bottom: 30px;
    }

    .full-width {
        grid-column: span 2;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        color: #5E5343;
        margin-bottom: 8px;
        font-size: 0.95rem;
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
        font-size: 1rem;
    }

    .input-icon input,
    .input-icon select {
        width: 100%;
        padding: 12px 15px 12px 45px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 1rem;
        color: #333;
        background: #fff;
        transition: border-color 0.3s ease;
    }

    .input-icon input:focus:not([readonly]),
    .input-icon select:focus {
        border-color: #5E5343;
        outline: none;
    }

    .error-msg {
        color: #e74c3c;
        font-size: 12px;
        margin-top: 5px;
        display: none;
        font-weight: 500;
    }

    .input-error {
        border-color: #e74c3c !important;
        box-shadow: 0 0 5px rgba(231, 76, 60, 0.2);
    }

    .btn-row {
        display: flex;
        flex-wrap: wrap; /* Allows wrapping on smaller screens */
        gap: 15px;
        margin-top: 10px;
    }

    .btn-update, .btn-password, .btn-address {
        flex: 1 1 30%; /* Makes all 3 buttons equal width */
        padding: 12px 20px;
        border-radius: 6px;
        font-size: 0.95rem;
        font-weight: 600;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        transition: 0.3s;
        text-decoration: none;
        border: none;
        cursor: pointer;
    }

    .btn-update {
        background: #1abc9c;
        color: #fff;
    }
    .btn-update:hover { background: #16a085; }

    .btn-password {
        background: #A1937A;
        color: #fff;
    }
    .btn-password:hover { background: #8e7f65; }

    .btn-address {
        background: #5E5343;
        color: #fff;
    }
    .btn-address:hover { background: #4a4134; }

    @media (max-width: 768px) {
        .form-grid { grid-template-columns: 1fr; }
        .full-width { grid-column: span 1; }
        .btn-row { flex-direction: column; }
        .btn-update, .btn-password, .btn-address { width: 100%; }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('profileForm');
        const phoneInput = document.getElementById('phoneInput');
        const phoneError = document.getElementById('phoneError');
        const pincodeInput = document.getElementById('pincodeInput');

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
            } else {
                phoneError.style.display = 'none';
                phoneInput.classList.remove('input-error');
                return true;
            }
        }

        phoneInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            validatePhone();
        });

        if (pincodeInput) {
            pincodeInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        }

        form.addEventListener('submit', function(e) {
            let isPhoneValid = validatePhone();
            if (!isPhoneValid) {
                e.preventDefault();
            }
        });
    });
</script>

<?php include 'include/footer.php'; ?>