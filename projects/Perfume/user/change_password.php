<?php
session_start();
// 1. Security Check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
  header('Location: ../login.php');
  exit;
}

include '../include/connection.php';
$username = $_SESSION['username'] ?? '';
$msg = "";

// 2. Handle Password Change (Strict Backend Validation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_pass'])) {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    if ($newPassword === $confirmPassword) {
        // Rule: AT LEAST 6 characters
        if (strlen($newPassword) >= 6) {
            // Rule: First letter is Capital
            if (preg_match('/^[A-Z]/', $newPassword)) {
                // Rule: Contains at least one special character
                if (preg_match('/[^a-zA-Z0-9]/', $newPassword)) {
                    // Rule: Contains at least one number (digit)
                    if (preg_match('/[0-9]/', $newPassword)) {
                        
                        // Update Password in Database
                        $updateSql = "UPDATE users SET password=? WHERE username=?";
                        $updateStmt = $conn->prepare($updateSql);
                        $updateStmt->bind_param('ss', $newPassword, $username);
                        
                        if ($updateStmt->execute()) {
                            $msg = '<div class="alert-success"><i class="fas fa-check-circle"></i> Password changed successfully!</div>';
                        } else {
                            $msg = '<div class="alert-error"><i class="fas fa-exclamation-circle"></i> Error updating password. Try again.</div>';
                        }
                        $updateStmt->close();

                    } else {
                        $msg = '<div class="alert-error"><i class="fas fa-exclamation-circle"></i> Password must contain at least one number.</div>';
                    }
                } else {
                    $msg = '<div class="alert-error"><i class="fas fa-exclamation-circle"></i> Password must contain at least one special character.</div>';
                }
            } else {
                $msg = '<div class="alert-error"><i class="fas fa-exclamation-circle"></i> The first letter of the password must be capital.</div>';
            }
        } else {
            $msg = '<div class="alert-error"><i class="fas fa-exclamation-circle"></i> Password must be at least 6 characters long.</div>';
        }
    } else {
        $msg = '<div class="alert-error"><i class="fas fa-exclamation-circle"></i> Passwords do not match.</div>';
    }
}
$conn->close();

include 'include/header.php';  
?>

<main style="flex: 1; padding: 40px; background-color: #fff;">
    
    <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin-bottom: 30px;">
        Security Settings
    </h1>

    <div class="password-container">
        
        <h2 style="font-size: 1.5rem; color: #5E5343; margin-bottom: 25px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            Change Password
        </h2>
        
        <?= $msg; ?>

        <form method="post" class="password-form" id="passwordForm">
            
            <div class="form-grid">
                
                <div class="form-group">
                    <label>New Password</label>
                    <div class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="new_password" id="newPassword" placeholder="Enter new password" required>
                    </div>
                    <span class="error-msg" id="newPassError"></span>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <div class="input-icon">
                        <i class="fas fa-check-circle"></i>
                        <input type="password" name="confirm_password" id="confirmPassword" placeholder="Re-enter new password" required>
                    </div>
                    <span class="error-msg" id="confirmPassError"></span>
                </div>

            </div>

            <div class="btn-row">
                <button type="submit" name="change_pass" class="btn-save" id="submitBtn">
                    <i class="fas fa-sync-alt"></i> Update Password
                </button>
                
                <a href="profile.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Profile
                </a>
            </div>

        </form>
    </div>

</main>

<style>
    /* Reusing Styles for Consistency */
    .password-container {
        max-width: 600px;
        background: #F5F1E9;
        padding: 40px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
        margin-bottom: 30px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        color: #5E5343;
        margin-bottom: 8px;
        font-size: 0.95rem;
    }

    .input-icon { position: relative; }
    .input-icon i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #B2A48C;
        font-size: 1rem;
    }

    .input-icon input {
        width: 100%;
        padding: 12px 15px 12px 45px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 1rem;
        color: #333;
        background: #fff;
        transition: border-color 0.3s ease;
    }
    .input-icon input:focus { border-color: #5E5343; outline: none; }

    /* REAL-TIME ERROR STYLES */
    .error-msg {
        color: #e74c3c;
        font-size: 12px;
        margin-top: 5px;
        display: none;
        font-weight: 500;
        line-height: 1.4;
    }

    .input-error {
        border-color: #e74c3c !important;
        box-shadow: 0 0 5px rgba(231, 76, 60, 0.2);
    }
    .input-success {
        border-color: #27ae60 !important;
    }

    /* Buttons */
    .btn-row {
        display: flex;
        gap: 20px;
        margin-top: 10px;
    }

    .btn-save {
        background: #5E5343; /* Dark Brown */
        color: #fff;
        border: none;
        padding: 12px 25px;
        border-radius: 6px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        flex: 1;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
    }

    .btn-back {
        background: transparent;
        color: #5E5343;
        text-decoration: none;
        padding: 12px 25px;
        border: 1px solid #5E5343;
        border-radius: 6px;
        font-size: 1rem;
        font-weight: 600;
        flex: 1;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
    }

    .btn-save:hover { background: #4a4033; }
    .btn-back:hover { background: #e0d8cc; }

    /* Alerts */
    .alert-success { background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
    .alert-error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb; }

    /* Responsive */
    @media (max-width: 600px) {
        .form-grid { grid-template-columns: 1fr; }
        .btn-row { flex-direction: column; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('passwordForm');
    const newPass = document.getElementById('newPassword');
    const confirmPass = document.getElementById('confirmPassword');
    const newPassError = document.getElementById('newPassError');
    const confirmPassError = document.getElementById('confirmPassError');

    // Utility function to show error
    function showError(input, errorElement, message) {
        input.classList.add('input-error');
        input.classList.remove('input-success');
        errorElement.innerHTML = message;
        errorElement.style.display = 'block';
    }

    // Utility function to clear error
    function clearError(input, errorElement) {
        input.classList.remove('input-error');
        input.classList.add('input-success');
        errorElement.style.display = 'none';
    }

    // 1. Validate New Password (SEQUENTIAL CHECKS)
    function validateNewPassword() {
        const val = newPass.value;
        
        if (val === '') {
            showError(newPass, newPassError, "Password is required.");
            return false;
        }

        // Check 1: First letter MUST be capital
        if (!/^[A-Z]/.test(val)) {
            showError(newPass, newPassError, "The first letter must be capital.");
            return false;
        }

        // Check 2: Must contain a special character
        if (!/[!@#$%^&*(),.?":{}|<>]/.test(val)) {
            showError(newPass, newPassError, "Must contain at least one special character.");
            return false;
        }

        // Check 3: Must contain a number (digit)
        if (!/[0-9]/.test(val)) {
            showError(newPass, newPassError, "Must contain at least one number.");
            return false;
        }

        // Check 4: AT LEAST 6 characters
        if (val.length < 6) {
            showError(newPass, newPassError, "Password must be at least 6 characters.");
            return false;
        }

        clearError(newPass, newPassError);
        return true;
    }

    // 2. Validate Confirm Password
    function validateConfirmPassword() {
        if (confirmPass.value === '') {
            showError(confirmPass, confirmPassError, "Please confirm your password.");
            return false;
        }

        if (confirmPass.value !== newPass.value) {
            showError(confirmPass, confirmPassError, "Passwords do not match.");
            return false;
        }

        clearError(confirmPass, confirmPassError);
        return true;
    }

    // Trigger validation REAL-TIME as the user types
    newPass.addEventListener('input', () => {
        validateNewPassword();
        if (confirmPass.value !== '') validateConfirmPassword(); // Re-check confirm match
    });

    confirmPass.addEventListener('input', validateConfirmPassword);

    // Final check before form submission
    form.addEventListener('submit', function(e) {
        const isNewValid = validateNewPassword();
        const isConfirmValid = validateConfirmPassword();

        if (!isNewValid || !isConfirmValid) {
            e.preventDefault(); // Stop form submission if invalid
        }
    });
});
</script>

<?php include 'include/footer.php'; ?>