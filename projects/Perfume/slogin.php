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
            $errorField = 'email';
        } elseif (!$hasUpper || !$hasLower || !$hasNumber || !$hasSpecial || strlen($password) < 6) {
            $message = '<div class="alert-box error">Password does not meet security requirements.</div>';
            $errorField = 'password';
        } else {
            // FIX: Query the new 'sellers' table instead of 'users'
            $sql = "SELECT id, owner_name, password, status FROM sellers WHERE email=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                $stmt->bind_result($id, $username, $db_password, $status);
                $stmt->fetch();

                if ($password === $db_password) {

                    // ==========================================
                    // CHECK SELLER STATUS HERE
                    // ==========================================
                    if ($status === 'pending') {
                        $message = '<div class="alert-box error">Your application is still pending Admin approval.</div>';
                        $errorField = 'email';
                    } elseif ($status === 'rejected') {
                        $message = '<div class="alert-box error">Your seller application has been rejected by the Admin.</div>';
                        $errorField = 'email';
                    } elseif ($status === 'suspended') {
                        $message = '<div class="alert-box error">Your account has been suspended. Please contact the administrator.</div>';
                        $errorField = 'email';
                    } else {
                        // Status is 'approved', allow login
                        $_SESSION['id'] = $id;
                        $_SESSION['username'] = $username; // Mapped from owner_name
                        $_SESSION['email'] = $email;
                        $_SESSION['role'] = 'seller';
                        $_SESSION['seller_logged_in'] = true;

                        header('Location: seller/dashboard.php');
                        exit;
                    }
                } else {
                    $message = '<div class="alert-box error">Invalid password.</div>';
                    $errorField = 'password';
                }
            } else {
                $message = '<div class="alert-box error">Seller account not found.</div>';
                $errorField = 'email';
            }
            $stmt->close();
        }
    } else {
        $message = '<div class="alert-box error">Please fill all fields.</div>';
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Login - Perfume Nest</title>
    <link rel="apple-touch-icon" sizes="180x180" href="img/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicons/favicon-16x16.png">
    <link rel="shortcut icon" type="image/x-icon" href="img/favicons/favicon.ico">
    <link rel="manifest" href="img/favicons/manifest.json">
    <meta name="msapplication-TileImage" content="img/favicons/mstile-150x150.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">

    <style>
        /* Standalone Body Styling */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f7f3f0;
            margin: 0;
            padding: 0;
            color: #1C1C1B;
        }

        /* --- FIXED SIZE STYLES --- */
        .login-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .login-card {
            background-color: #ffffff;
            width: 100%;
            max-width: 380px;
            padding: 35px 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            position: relative;
        }

        /* Back Button Style */
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
            color: #6A5D52;
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
            box-sizing: border-box;
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
            transition: 0.3s;
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
            text-decoration: none;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        .forgot-pass {
            display: block;
            margin-top: 15px;
            color: #6A5D52;
            font-size: 0.85rem;
            text-decoration: none;
        }

        .forgot-pass:hover {
            text-decoration: underline;
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
</head>

<body>

    <div class="login-wrapper">
        <div class="login-card">

            <a href="index.php" class="back-to-home">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>

            <h2>Perfume Nest</h2>
            <p class="subtitle">Seller Portal</p>

            <?= $message; ?>

            <form method="post" id="loginForm" novalidate>

                <div class="form-group">
                    <label>Seller Email</label>
                    <input type="email" name="email" id="email" required placeholder="seller@example.com">
                    <div class="validation-msg" id="emailMsg"></div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" id="password" required placeholder="Enter your password" disabled>
                    <div class="validation-msg" id="passMsg"></div>
                </div>

                <button type="submit" class="btn-login">Login as Seller</button>

                <a href="sforgot_password.php" class="forgot-pass">Forgot Password?</a>

                <div class="login-footer">
                    Want to become a seller? <a href="sregister.php">Sign Up</a>
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

        // 2. Step-by-Step Email Validation
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

            if (isEmailValid) {
                passInput.disabled = false;
            } else {
                passInput.disabled = true;
                passInput.value = "";
                resetValidation(passInput, passMsg);
            }

            return isEmailValid;
        }

        // 3. Password Validation
        function validatePasswordField() {
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
                showError(passInput, passMsg, "Password must include at least 1 Capital letter (A-Z).");
                return false;
            } else if (!hasSpecial) {
                showError(passInput, passMsg, "Password must include a Special character.");
                return false;
            } else if (passValue.length < 6) {
                showError(passInput, passMsg, "Password must be at least 6 characters long.");
                return false;
            } else if (!hasLower) {
                showError(passInput, passMsg, "Password must include at least 1 Lowercase letter (a-z).");
                return false;
            } else if (!hasNumber) {
                showError(passInput, passMsg, "Password must include at least 1 Number (0-9).");
                return false;
            } else {
                showSuccess(passInput, passMsg);
                return true;
            }
        }

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

        emailInput.addEventListener('input', validateEmailField);
        passInput.addEventListener('input', validatePasswordField);

        form.addEventListener('submit', function(e) {
            const isEmailValid = validateEmailField();
            const isPassValid = validatePasswordField();

            if (!isEmailValid || !isPassValid) {
                e.preventDefault();
            }
        });

        window.addEventListener('DOMContentLoaded', () => {
            if (emailInput.value.trim() !== "") {
                validateEmailField();
            }
        });
    </script>

</body>

</html>