<?php
session_start();
include 'include/connection.php';

$message = '';
$errorField = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'] ?? ''; // Handle case where it might be disabled

    if ($email && $password) {

        // Strict Backend Validation
        $hasUpper = preg_match('@[A-Z]@', $password);
        $hasLower = preg_match('@[a-z]@', $password);
        $hasNumber = preg_match('@[0-9]@', $password);
        $hasSpecial = preg_match('@[^\w]@', $password);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '<div class="alert-error">Invalid email format.</div>';
            $errorField = 'email';
        } elseif (!$hasUpper || !$hasLower || !$hasNumber || !$hasSpecial || strlen($password) < 6) {
            $message = '<div class="alert-error">Password does not meet security requirements.</div>';
            $errorField = 'password';
        } else {
            // FIX: Target the new 'admin' table instead of 'users'
            $sql = "SELECT id, username, password FROM admin WHERE email=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                $stmt->bind_result($id, $username, $db_password);
                $stmt->fetch();

                // Verify Password
                if ($password === $db_password) {
                    $_SESSION['id'] = $id;
                    $_SESSION['username'] = $username;
                    $_SESSION['email'] = $email;
                    $_SESSION['role'] = 'admin';
                    $_SESSION['admin_logged_in'] = true;

                    header('Location: admin/admindashbord.php');
                    exit;
                } else {
                    $message = '<div class="alert-error">Invalid password. Access Denied.</div>';
                    $errorField = 'password';
                }
            } else {
                $message = '<div class="alert-error">Admin account not found.</div>';
                $errorField = 'email';
            }
            $stmt->close();
        }
    } else {
        $message = '<div class="alert-error">Please fill all fields.</div>';
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Perfume Nest</title>
    <link rel="apple-touch-icon" sizes="180x180" href="img/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicons/favicon-16x16.png">
    <link rel="shortcut icon" type="image/x-icon" href="img/favicons/favicon.ico">
    <link rel="manifest" href="img/favicons/manifest.json">
    <meta name="msapplication-TileImage" content="img/favicons/mstile-150x150.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">

    <style>
        /* ================= INTERNAL CSS ================= */
        :root {
            --gold: #c5a059;
            --dark: #1a1a1a;
            --white: #ffffff;
            --bg-gradient: radial-gradient(circle at center, #fffbf2, #f1e9df);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Login Card */
        .login-box {
            background: var(--white);
            width: 100%;
            max-width: 420px;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
            border-top: 5px solid var(--gold);
            text-align: center;
            animation: fadeInUp 0.8s ease-out;
            position: relative;
        }

        /* Back Button */
        .back-to-home {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #888;
            font-size: 13px;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
            width: 100%;
            justify-content: flex-start;
            transition: color 0.3s;
        }

        .back-to-home:hover {
            color: var(--gold);
        }

        /* Header */
        .login-header h2 {
            font-family: 'Playfair Display', serif;
            color: var(--dark);
            font-size: 30px;
            margin-bottom: 5px;
        }

        .login-header p {
            color: #888;
            font-size: 14px;
            margin-bottom: 30px;
        }

        /* Inputs */
        .input-wrapper {
            margin-bottom: 20px;
            text-align: left;
        }

        .input-wrapper label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .icon-input {
            position: relative;
        }

        .icon-input i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gold);
            font-size: 16px;
            z-index: 2;
        }

        .icon-input input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid #ddd;
            border-radius: 50px;
            font-size: 14px;
            transition: 0.3s;
            background: #fcfcfc;
            color: #333;
        }

        .icon-input input:focus {
            border-color: var(--gold);
            outline: none;
            box-shadow: 0 0 10px rgba(197, 160, 89, 0.2);
            background: #fff;
        }

        /* === DISABLED STATE CSS === */
        .icon-input input:disabled {
            background-color: #e9e9e9;
            color: #999;
            cursor: not-allowed;
            border-color: #ddd;
        }

        /* Error & Success States */
        .input-error {
            border-color: #e74c3c !important;
            background: #fff6f6 !important;
            animation: shake 0.4s ease-in-out;
        }

        .input-success {
            border-color: #28a745 !important;
            background: #f4fff5 !important;
        }

        .validation-msg {
            color: #e74c3c;
            font-size: 12px;
            margin-top: 6px;
            padding-left: 15px;
            display: none;
            font-weight: 500;
        }

        .alert-error {
            background: #fdecea;
            color: #c0392b;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            border: 1px solid #fadbd8;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-5px);
            }

            75% {
                transform: translateX(5px);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Button */
        button {
            width: 100%;
            padding: 12px;
            background: var(--dark);
            color: var(--gold);
            border: none;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
        }

        button:hover {
            background: var(--gold);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(197, 160, 89, 0.3);
        }

        /* Footer Links */
        .login-footer {
            margin-top: 25px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        .footer-note {
            font-size: 12px;
            color: #999;
        }

        .forgot-link {
            font-size: 13px;
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 15px;
        }

        .forgot-link:hover {
            color: var(--gold);
            text-decoration: underline;
        }
    </style>
</head>

<body>

    <div class="login-box">

        <a href="index.php" class="back-to-home">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>

        <div class="login-header">
            <h2>Admin Portal</h2>
            <p>Secure Access for Management</p>
        </div>

        <?= $message; ?>

        <form method="post" id="adminForm" autocomplete="off" novalidate>

            <div class="input-wrapper">
                <label>Email Address</label>
                <div class="icon-input">
                    <i class="fas fa-user-shield"></i>
                    <input type="email" name="email" id="email"
                        class="<?php echo ($errorField == 'email') ? 'input-error' : ''; ?>"
                        placeholder="admin@perfumenest.com" required>
                </div>
                <div class="validation-msg" id="emailMsg"></div>
            </div>

            <div class="input-wrapper">
                <label>Password</label>
                <div class="icon-input">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password"
                        class="<?php echo ($errorField == 'password') ? 'input-error' : ''; ?>"
                        placeholder="Enter Password" disabled required>
                </div>
                <div class="validation-msg" id="passMsg"></div>
            </div>

            <button type="submit">
                Login Dashboard <i class="fas fa-sign-in-alt"></i>
            </button>
        </form>

        <div class="login-footer">
            <a href="admin_forgot_password.php" class="forgot-link">Forgot Password?</a>
            <p class="footer-note">
                <i class="fas fa-shield-alt" style="color: var(--gold);"></i> Authorized Personnel Only
            </p>
        </div>

    </div>

    <script>
        const form = document.getElementById('adminForm');
        const emailInput = document.getElementById('email');
        const passInput = document.getElementById('password');
        const emailMsg = document.getElementById('emailMsg');
        const passMsg = document.getElementById('passMsg');

        // 1. Move to next field on Enter ONLY if the email is properly filled
        emailInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (validateEmailField()) {
                    passInput.focus();
                }
            }
        });

        // 2. Real-time Email Validation (Handles Locking/Unlocking)
        function validateEmailField() {
            const emailValue = emailInput.value.trim();
            let isEmailValid = false;

            if (emailValue === "") {
                showError(emailInput, emailMsg, "Admin email is required.");
            } else if (!emailValue.includes('@')) {
                showError(emailInput, emailMsg, "Email must contain an '@' symbol.");
            } else if (!emailValue.includes('.')) {
                showError(emailInput, emailMsg, "Email must contain a domain (e.g., .com).");
            } else if (emailValue.indexOf('@') > emailValue.lastIndexOf('.')) {
                showError(emailInput, emailMsg, "The '.' must come after the '@' symbol.");
            } else {
                showSuccess(emailInput, emailMsg);
                isEmailValid = true;
            }

            // === LOCK/UNLOCK LOGIC ===
            if (isEmailValid) {
                passInput.disabled = false; // Unlock password field
                passInput.placeholder = "Enter Password";
            } else {
                passInput.disabled = true; // Lock password field
                passInput.value = ""; // Clear it if they messed up the email
                passInput.placeholder = "Enter Password";
                resetField(passInput, passMsg); // Remove any red errors on password
            }

            return isEmailValid;
        }

        // 3. Real-time Password Validation Step-by-Step (Re-ordered)
        function validatePasswordField() {
            // Only validate if it's enabled
            if (passInput.disabled) return false;

            const passValue = passInput.value;
            const hasUpper = /[A-Z]/.test(passValue);
            const hasLower = /[a-z]/.test(passValue);
            const hasNumber = /[0-9]/.test(passValue);
            const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(passValue);

            if (passValue === "") {
                showError(passInput, passMsg, "Password is required.");
                return false;
            } else if (!hasUpper) {
                showError(passInput, passMsg, "Must include at least 1 Capital letter (A-Z).");
                return false;
            } else if (!hasSpecial) {
                showError(passInput, passMsg, "Must include a Special character (@, #, $, etc.).");
                return false;
            } else if (passValue.length < 6) {
                showError(passInput, passMsg, "Password must be at least 6 characters.");
                return false;
            } else if (!hasLower) {
                showError(passInput, passMsg, "Must include at least 1 Lowercase letter (a-z).");
                return false;
            } else if (!hasNumber) {
                showError(passInput, passMsg, "Must include at least 1 Number (0-9).");
                return false;
            } else {
                showSuccess(passInput, passMsg);
                return true;
            }
        }

        // 4. Helper functions for error/success styling
        function showError(input, msgBox, text) {
            input.classList.remove('input-success');
            input.classList.add('input-error');
            msgBox.innerText = text;
            msgBox.style.display = 'block';
        }

        function showSuccess(input, msgBox) {
            input.classList.remove('input-error');
            input.classList.add('input-success');
            msgBox.style.display = 'none';
            msgBox.innerText = "";
        }

        function resetField(input, msgBox) {
            input.classList.remove('input-error', 'input-success');
            msgBox.style.display = 'none';
            msgBox.innerText = "";
        }

        // 5. Trigger validation dynamically as user types
        emailInput.addEventListener('input', validateEmailField);
        passInput.addEventListener('input', validatePasswordField);

        // 6. Final check on Form Submit
        form.addEventListener('submit', function(e) {
            const isEmailValid = validateEmailField();
            const isPassValid = validatePasswordField();

            if (!isEmailValid || !isPassValid) {
                e.preventDefault(); // Stop submission if errors exist
            }
        });

        // Run validation on page load just in case the browser auto-filled the email
        window.addEventListener('DOMContentLoaded', () => {
            if (emailInput.value.trim() !== "") {
                validateEmailField();
            }
        });
    </script>

</body>

</html>