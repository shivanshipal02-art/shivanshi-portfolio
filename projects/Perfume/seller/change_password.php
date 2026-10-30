<?php
session_start();
// 1. Security Check
if (!isset($_SESSION['seller_logged_in']) || $_SESSION['seller_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

include '../include/connection.php';
$seller_id = $_SESSION['id'];
$msg = "";

// 2. Handle Password Change (Strict Backend Validation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_pass'])) {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if ($newPassword === $confirmPassword) {
        if (strlen($newPassword) >= 6) {
            if (preg_match('/^[A-Z]/', $newPassword)) {
                if (preg_match('/[^a-zA-Z0-9]/', $newPassword)) {
                    if (preg_match('/[0-9]/', $newPassword)) {

                        // FIXED: Now updating the 'sellers' table
                        $updateSql = "UPDATE sellers SET password=? WHERE id=?";
                        $updateStmt = $conn->prepare($updateSql);
                        $updateStmt->bind_param('si', $newPassword, $seller_id);

                        if ($updateStmt->execute()) {
                            $msg = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Password updated successfully!</div>";
                        } else {
                            $msg = "<div class='alert alert-error'><i class='fas fa-exclamation-circle'></i> Failed to update password.</div>";
                        }
                    } else {
                        $msg = "<div class='alert alert-error'>Password must contain at least one number.</div>";
                    }
                } else {
                    $msg = "<div class='alert alert-error'>Password must contain at least one special character.</div>";
                }
            } else {
                $msg = "<div class='alert alert-error'>Password must start with a Capital letter.</div>";
            }
        } else {
            $msg = "<div class='alert alert-error'>Password must be at least 6 characters long.</div>";
        }
    } else {
        $msg = "<div class='alert alert-error'>Passwords do not match.</div>";
    }
}

include 'include/header.php';
?>

<main style="flex: 1; padding: 40px; background-color: #fff;">

    <div class="profile-header">
        <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; font-size: 2rem; margin:0;">Change Password</h1>
        <a href="profile.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Profile</a>
    </div>

    <?= $msg; ?>

    <div class="password-card">
        <div class="rules-box">
            <h4><i class="fas fa-shield-alt"></i> Password Requirements:</h4>
            <ul>
                <li>Must be at least <strong>6 characters</strong> long.</li>
                <li>Must start with a <strong>Capital letter</strong> (A-Z).</li>
                <li>Must contain at least one <strong>Number</strong> (0-9).</li>
                <li>Must contain at least one <strong>Special Character</strong> (!@#$%^&*).</li>
            </ul>
        </div>

        <form method="POST" id="passwordForm">

            <div class="form-group">
                <label>New Password</label>
                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="new_password" id="newPass" required placeholder="Enter new password">
                </div>
                <div id="newPassError" class="error-msg"></div>
            </div>

            <div class="form-group">
                <label>Confirm New Password</label>
                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="confirm_password" id="confirmPass" required placeholder="Confirm new password">
                </div>
                <div id="confirmPassError" class="error-msg"></div>
            </div>

            <button type="submit" name="change_pass" class="btn-update">Update Password</button>
        </form>
    </div>
</main>

<style>
    /* Styling matched directly from User Panel change_password.php */
    .profile-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }

    .btn-back {
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

    .btn-back:hover {
        background-color: #5E5343;
        color: #fff;
    }

    .password-card {
        background: #F5F1E9;
        padding: 40px;
        border-radius: 12px;
        border: 1px solid #e0e0e0;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.03);
        max-width: 600px;
    }

    .rules-box {
        background: #fff;
        border-left: 4px solid #A1937A;
        padding: 15px 20px;
        margin-bottom: 25px;
        border-radius: 4px;
    }

    .rules-box h4 {
        color: #5E5343;
        margin-top: 0;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .rules-box ul {
        margin: 0;
        padding-left: 20px;
        color: #666;
        font-size: 13px;
        line-height: 1.6;
    }

    .form-group {
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

    .input-icon input {
        width: 100%;
        padding: 14px 15px 14px 45px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 14px;
        font-family: 'Poppins', sans-serif;
        outline: none;
        transition: 0.3s;
        box-sizing: border-box;
    }

    .input-icon input:focus {
        border-color: #5E5343;
        box-shadow: 0 0 5px rgba(94, 83, 67, 0.1);
    }

    .input-error {
        border-color: red !important;
    }

    .error-msg {
        color: red;
        font-size: 12px;
        margin-top: 5px;
        display: none;
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
        max-width: 600px;
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
</style>

<script>
    const form = document.getElementById('passwordForm');
    const newPass = document.getElementById('newPass');
    const confirmPass = document.getElementById('confirmPass');
    const newPassError = document.getElementById('newPassError');
    const confirmPassError = document.getElementById('confirmPassError');

    function showError(input, msgBox, text) {
        input.classList.add('input-error');
        msgBox.innerText = text;
        msgBox.style.display = 'block';
    }

    function clearError(input, msgBox) {
        input.classList.remove('input-error');
        msgBox.style.display = 'none';
        msgBox.innerText = "";
    }

    function validateNewPassword() {
        const val = newPass.value;
        if (val === '') {
            showError(newPass, newPassError, "Password is required.");
            return false;
        }
        if (!/^[A-Z]/.test(val)) {
            showError(newPass, newPassError, "Must start with a Capital letter.");
            return false;
        }
        if (!/[!@#$%^&*(),.?":{}|<>]/.test(val)) {
            showError(newPass, newPassError, "Must contain a special character.");
            return false;
        }
        if (!/[0-9]/.test(val)) {
            showError(newPass, newPassError, "Must contain a number.");
            return false;
        }
        if (val.length < 6) {
            showError(newPass, newPassError, "Must be at least 6 characters.");
            return false;
        }
        clearError(newPass, newPassError);
        return true;
    }

    function validateConfirmPassword() {
        if (confirmPass.value === '') {
            showError(confirmPass, confirmPassError, "Please confirm password.");
            return false;
        }
        if (confirmPass.value !== newPass.value) {
            showError(confirmPass, confirmPassError, "Passwords do not match.");
            return false;
        }
        clearError(confirmPass, confirmPassError);
        return true;
    }

    newPass.addEventListener('input', () => {
        validateNewPassword();
        if (confirmPass.value !== '') validateConfirmPassword();
    });
    confirmPass.addEventListener('input', validateConfirmPassword);

    form.addEventListener('submit', function(e) {
        if (!validateNewPassword() || !validateConfirmPassword()) {
            e.preventDefault();
        }
    });
</script>

<?php include 'include/footer.php'; ?>