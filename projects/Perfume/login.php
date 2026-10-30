<?php
session_start();
include 'include/connection.php';

$message = '';
$errorField = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'] ?? '';

    if ($email && $password) {

        // Strict Backend Validation
        $hasUpper = preg_match('@[A-Z]@', $password);
        $hasLower = preg_match('@[a-z]@', $password);
        $hasNumber = preg_match('@[0-9]@', $password);
        $hasSpecial = preg_match('@[^\w]@', $password);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '<div class="alert-box error">Invalid email format.</div>';
        } elseif (!$hasUpper || !$hasLower || !$hasNumber || !$hasSpecial || strlen($password) < 6) {
            $message = '<div class="alert-box error">Password does not meet security requirements.</div>';
        } else {
            // FIX: Removed `role` from SELECT query and WHERE clause
            $sql = "SELECT id, username, password, status FROM users WHERE email=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                // FIX: Removed $role from bind_result
                $stmt->bind_result($id, $username, $db_password, $status);
                $stmt->fetch();

                if ($password === $db_password) {

                    // --- NEW SUSPENSION CHECK ---
                    if (strtolower($status) === 'suspended') {
                        $message = '<div class="alert-box error">Your account has been suspended.</div>';
                    } else {
                        // Only user login performed
                        $_SESSION['id'] = $id;
                        $_SESSION['username'] = $username;
                        $_SESSION['email'] = $email;
                        $_SESSION['role'] = 'customer'; // FIX: Explicitly set role to customer
                        $_SESSION['user_logged_in'] = true;

                        header('Location: user/dashboard.php');
                        exit;
                    }
                } else {
                    $message = '<div class="alert-box error">Invalid password.</div>';
                }
            } else {
                // Admins and sellers will now fall into this block if they try to use customer login
                $message = '<div class="alert-box error">Account not found. <a href="registration.php" style="color: #721c24; text-decoration: underline; font-weight: 600;">Register Here</a></div>';
            }
            $stmt->close();
        }
    } else {
        $message = '<div class="alert-box error">Please fill all fields.</div>';
    }
    $conn->close();
}

include 'include/header.php';
?>

<style>
    /* --- FIXED SIZE STYLES --- */
    .login-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 80vh;
        padding: 20px;
    }

    .login-card {
        background-color: #ffffff;
        width: 100%;
        max-width: 380px;
        padding: 35px 30px;
        border-radius: 15px;
        text-align: center;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }

    .login-card h2 {
        font-family: 'Playfair Display', serif;
        font-size: 1.8rem;
        color: #1C1C1B;
        margin-bottom: 5px;
        font-weight: 700;
    }

    .login-card p.subtitle {
        color: #6A5D52;
        font-size: 0.95rem;
        margin-bottom: 30px;
    }

    .form-group {
        margin-bottom: 20px;
        text-align: left;
    }

    .form-group label {
        display: block;
        font-size: 0.9rem;
        color: #1C1C1B;
        margin-bottom: 6px;
        font-weight: 600;
    }

    .form-group input {
        width: 100%;
        background-color: #E8E8E8;
        border: 2px solid transparent;
        border-radius: 8px;
        padding: 12px;
        font-size: 0.95rem;
        color: #333;
        font-family: 'Poppins', sans-serif;
        outline: none;
        transition: border-color 0.3s;
    }

    .form-group input:focus {
        background-color: #fff;
        border-color: #B7AC9B;
    }

    /* === NEW DISABLED CSS === */
    .form-group input:disabled {
        background-color: #f0f0f0;
        color: #999;
        cursor: not-allowed;
        border-color: #e0e0e0;
    }

    /* Green border for correct validation */
    .form-group input.success-border {
        border-color: #28a745;
        background-color: #f4fff5;
    }

    .form-group input.error-border {
        border-color: #d9534f;
        background-color: #fdf0f0;
    }

    .validation-msg {
        color: #d9534f;
        font-size: 0.8rem;
        margin-top: 4px;
        display: none;
        font-weight: 500;
    }

    .btn-login {
        width: 100%;
        background-color: #6A5D52;
        color: #fff;
        font-size: 1rem;
        font-weight: 600;
        padding: 12px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        margin-top: 5px;
    }

    .btn-login:hover {
        background-color: #554a40;
    }

    .login-footer {
        margin-top: 20px;
        font-size: 0.9rem;
        color: #666;
    }

    .login-footer a {
        color: #1C1C1B;
        font-weight: 600;
    }

    .forgot-pass {
        display: block;
        margin-top: 15px;
        color: #6A5D52;
        font-size: 0.85rem;
    }

    .alert-box {
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 5px;
        font-size: 0.85rem;
    }

    .alert-box.error {
        background-color: #f8d7da;
        color: #721c24;
    }
</style>

<div class="login-wrapper">
    <div class="login-card">

        <h2>Perfume Nest</h2>
        <p class="subtitle">Login</p>

        <?= $message; ?>

        <form method="post" id="loginForm" novalidate>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="email" required placeholder="Enter your email">
                <div class="validation-msg" id="emailMsg"></div>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" id="password" required placeholder="Enter your password" disabled>
                <div class="validation-msg" id="passMsg"></div>
            </div>

            <button type="submit" class="btn-login">Login</button>

            <a href="forgot_password.php" class="forgot-pass">Forgot Password?</a>

            <div class="login-footer">
                Don't have an account? <a href="registration.php">Sign Up</a>
            </div>

        </form>
    </div>
</div>

<script>
    const form = document.getElementById('loginForm');
    const emailInput = document.getElementById('email');
    const passInput = document.getElementById('password');
    const emailMsg = document.getElementById('emailMsg');
    const passMsg = document.getElementById('passMsg');

    // 1. Move to password field on Enter key ONLY if email is fully valid
    emailInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (validateEmailField()) {
                passInput.focus();
            }
        }
    });

    // 2. Step-by-Step Email Validation (Handles Locking/Unlocking)
    function validateEmailField() {
        const emailValue = emailInput.value.trim();
        let isEmailValid = false;

        if (emailValue === "") {
            showError(emailInput, emailMsg, "Email is required.");
        } else if (!emailValue.includes('@')) {
            showError(emailInput, emailMsg, "Email must contain an '@' symbol.");
        } else if (!emailValue.includes('.')) {
            showError(emailInput, emailMsg, "Email must contain a domain (like .com or .in).");
        } else if (emailValue.indexOf('@') > emailValue.lastIndexOf('.')) {
            showError(emailInput, emailMsg, "The '.' must come after the '@' symbol.");
        } else {
            showSuccess(emailInput, emailMsg);
            isEmailValid = true;
        }

        // Logic to Lock/Unlock the password field
        if (isEmailValid) {
            passInput.disabled = false; // Unlock password field
        } else {
            passInput.disabled = true; // Lock password field
            passInput.value = ""; // Clear it if they mess up the email
            resetValidation(passInput, passMsg); // Clear any password errors
        }

        return isEmailValid;
    }

    // 3. Step-by-Step Password Validation
    function validatePasswordField() {
        if (passInput.disabled) return false; // Don't validate if disabled

        const passValue = passInput.value;
        const hasUpper = /[A-Z]/.test(passValue);
        const hasLower = /[a-z]/.test(passValue);
        const hasNumber = /[0-9]/.test(passValue);
        const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(passValue);

        if (passValue === "") {
            showError(passInput, passMsg, "Password is required.");
            return false;
        } else if (!hasUpper) {
            // Error 1: Capital Letter First
            showError(passInput, passMsg, "Password must include at least 1 Capital letter (A-Z).");
            return false;
        } else if (!hasSpecial) {
            // Error 2: Special Character Second
            showError(passInput, passMsg, "Password must include a Special character.");
            return false;
        } else if (passValue.length < 6) {
            // Error 3: Length
            showError(passInput, passMsg, "Password must be at least 6 characters long.");
            return false;
        } else if (!hasLower) {
            // Error 4: Lowercase
            showError(passInput, passMsg, "Password must include at least 1 Lowercase letter (a-z).");
            return false;
        } else if (!hasNumber) {
            // Error 5: Number
            showError(passInput, passMsg, "Password must include at least 1 Number (0-9).");
            return false;
        } else {
            showSuccess(passInput, passMsg);
            return true;
        }
    }

    // Helper functions to show errors and success
    function showError(input, msgBox, text) {
        input.classList.remove('success-border');
        input.classList.add('error-border');
        msgBox.innerText = text;
        msgBox.style.display = 'block';
    }

    function showSuccess(input, msgBox) {
        input.classList.remove('error-border');
        input.classList.add('success-border');
        msgBox.style.display = 'none';
        msgBox.innerText = "";
    }

    function resetValidation(input, msgBox) {
        input.classList.remove('error-border', 'success-border');
        msgBox.style.display = 'none';
        msgBox.innerText = "";
    }

    // Trigger validation REAL-TIME as the user types
    emailInput.addEventListener('input', validateEmailField);
    passInput.addEventListener('input', validatePasswordField);

    // Final check before form submission
    form.addEventListener('submit', function(e) {
        const isEmailValid = validateEmailField();
        const isPassValid = validatePasswordField();

        if (!isEmailValid || !isPassValid) {
            e.preventDefault(); // Stop form from submitting if errors exist
        }
    });

    // Run validation on page load in case the browser auto-filled the email
    window.addEventListener('DOMContentLoaded', () => {
        if (emailInput.value.trim() !== "") {
            validateEmailField();
        }
    });
</script>

<?php include 'include/footer.php'; ?>