<?php
session_start();
// FIX: Force PHP to use the correct timezone so it matches MySQL's NOW() function
date_default_timezone_set('Asia/Kolkata');

include 'include/connection.php';

// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$message = '';
$errorField = '';
$step = $_SESSION['admin_reset_step'] ?? 1;

// Helper Function to Generate and Send OTP
function generateAndSendAdminOTP($email, $conn)
{
    $otp = sprintf("%06d", mt_rand(1, 999999));
    $expiry = date("Y-m-d H:i:s", strtotime("+25 minutes"));

    // FIX: Update the 'admin' table instead of 'users'
    $update_otp = "UPDATE admin SET reset_otp=?, otp_expiry=? WHERE email=?";
    $stmt_otp = $conn->prepare($update_otp);
    $stmt_otp->bind_param('sss', $otp, $expiry, $email);
    $stmt_otp->execute();

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'perfumenest3012@gmail.com'; # email Your Account
        $mail->Password   = 'mfli ckub inlb ggxe';       # Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('perfumenest3012@gmail.com', 'Perfume Nest Admin Support');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Admin Portal - Password Reset OTP';

        $htmlBody = "
        <div style='background-color: #f1e9df; padding: 40px 20px; font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 40px 30px; border-radius: 12px; border-top: 5px solid #c5a059; text-align: center;'>
                <h1 style='color: #1a1a1a; font-family: Georgia, serif; font-size: 28px; margin-bottom: 5px;'>Admin Portal</h1>
                <h3 style='color: #888; font-weight: normal; margin-top: 0; margin-bottom: 30px; font-size: 16px;'>Secure Password Recovery</h3>
                
                <p style='color: #333; font-size: 16px; line-height: 1.6; text-align: left;'>Admin User,</p>
                <p style='color: #333; font-size: 16px; line-height: 1.6; text-align: left;'>A password reset was requested for your administrative account. Please use the following One-Time Password (OTP) to verify your identity.</p>
                
                <div style='margin: 40px 0;'>
                    <span style='display: inline-block; font-size: 32px; font-weight: bold; letter-spacing: 10px; color: #1a1a1a; background-color: #fffbf2; border: 2px dashed #c5a059; padding: 20px 40px; border-radius: 8px; user-select: all; cursor: pointer;'>$otp</span>
                </div>
                
                <p style='color: #333; font-size: 15px; line-height: 1.6; text-align: left; margin-top: 30px;'>This code will expire in <strong>25 minutes</strong>. If you did not initiate this request, please review your account security immediately.</p>
            </div>
        </div>";

        $mail->Body = $htmlBody;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return $mail->ErrorInfo;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // START OVER
    if (isset($_POST['start_over'])) {
        unset($_SESSION['admin_reset_step']);
        unset($_SESSION['admin_reset_email']);
        header('Location: admin_forgot_password.php');
        exit;
    }

    // --- STEP 1: VERIFY ADMIN EMAIL ---
    if (isset($_POST['request_reset'])) {
        $email = trim($_POST['email']);

        // FIX: Select from the 'admin' table instead of 'users'
        $sql = "SELECT id FROM admin WHERE email=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $mail_status = generateAndSendAdminOTP($email, $conn);
            if ($mail_status === true) {
                $_SESSION['admin_reset_email'] = $email;
                $_SESSION['admin_reset_step'] = 2;
                $step = 2;
                $message = '<div class="alert-success">OTP successfully sent to your admin email.</div>';
            } else {
                $message = '<div class="alert-error">Mailer Error: ' . $mail_status . '</div>';
            }
        } else {
            $message = '<div class="alert-error">Admin account not found.</div>';
            $errorField = 'email';
        }
        $stmt->close();
    }

    // --- STEP 2: RESEND OTP ---
    elseif (isset($_POST['resend_otp'])) {
        $email = $_SESSION['admin_reset_email'];
        $mail_status = generateAndSendAdminOTP($email, $conn);
        if ($mail_status === true) {
            $message = '<div class="alert-success">A new OTP has been sent!</div>';
        } else {
            $message = '<div class="alert-error">Mailer Error: ' . $mail_status . '</div>';
        }
    }

    // --- STEP 3: VERIFY OTP AND UPDATE PASSWORD ---
    elseif (isset($_POST['verify_and_update'])) {
        $email = $_SESSION['admin_reset_email'];
        $otp_entered = trim($_POST['otp']);
        $new_password = $_POST['new_password'];

        // Backend Password Validation
        $hasUpper = preg_match('@[A-Z]@', $new_password);
        $hasLower = preg_match('@[a-z]@', $new_password);
        $hasNumber = preg_match('@[0-9]@', $new_password);
        $hasSpecial = preg_match('@[^\w]@', $new_password);

        if (strlen($new_password) < 6 || !$hasUpper || !$hasLower || !$hasNumber || !$hasSpecial) {
            $message = '<div class="alert-error">Password does not meet security requirements.</div>';
        } else {
            // FIX: Select from the 'admin' table
            $sql = "SELECT id FROM admin WHERE email=? AND reset_otp=? AND otp_expiry >= NOW()";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $email, $otp_entered);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                // OTP matches, update password in the admin table
                $sql_update = "UPDATE admin SET password=?, reset_otp=NULL, otp_expiry=NULL WHERE email=?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param('ss', $new_password, $email);
                $stmt_update->execute();

                unset($_SESSION['admin_reset_step']);
                unset($_SESSION['admin_reset_email']);

                header('Location: adminlogin.php?reset=success');
                exit;
            } else {
                $message = '<div class="alert-error">Invalid or expired OTP.</div>';
                $errorField = 'otp';
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Recovery - Perfume Nest</title>
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
        }

        .login-header h2 {
            font-family: 'Playfair Display', serif;
            color: var(--dark);
            font-size: 28px;
            margin-bottom: 5px;
        }

        .login-header p {
            color: #888;
            font-size: 14px;
            margin-bottom: 30px;
        }

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

        /* Disabled Input Style */
        .icon-input input:disabled {
            background-color: #e9e9e9;
            color: #999;
            cursor: not-allowed;
            border-color: #ddd;
        }

        /* Validation Colors */
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

        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }

        .readonly-text {
            background-color: #f9f9f9;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #eee;
            color: #555;
            font-size: 13px;
            margin-bottom: 20px;
            text-align: left;
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

        /* Buttons */
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

        .btn-resend {
            background: transparent;
            color: var(--dark);
            border: 2px solid var(--dark);
            margin-top: 15px;
        }

        .btn-resend:hover {
            background: var(--dark);
            color: var(--white);
        }

        .btn-cancel {
            background: transparent;
            color: #e74c3c;
            font-size: 12px;
            margin-top: 15px;
            border: none;
            text-decoration: underline;
            box-shadow: none;
            text-transform: none;
            font-weight: 500;
        }

        .btn-cancel:hover {
            background: transparent;
            color: #c0392b;
            transform: none;
            box-shadow: none;
            text-decoration: none;
        }

        .login-footer {
            margin-top: 25px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        .footer-note {
            font-size: 13px;
            color: #666;
        }

        .back-link {
            color: var(--dark);
            text-decoration: none;
            font-weight: 600;
        }

        .back-link:hover {
            color: var(--gold);
            text-decoration: underline;
        }
    </style>
</head>

<body>

    <div class="login-box">

        <div class="login-header">
            <h2>Admin Recovery</h2>
            <p>Secure OTP Verification</p>
        </div>

        <?= $message; ?>

        <?php if ($step === 1): ?>
            <form method="post" id="step1Form" autocomplete="off" novalidate>

                <div class="input-wrapper">
                    <label>Admin Email</label>
                    <div class="icon-input">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" id="email"
                            class="<?php echo ($errorField == 'email') ? 'input-error' : ''; ?>"
                            placeholder="admin@perfumenest.com" required>
                    </div>
                    <div class="validation-msg" id="emailMsg"></div>
                </div>

                <button type="submit" name="request_reset">
                    Send OTP <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        <?php endif; ?>


        <?php if ($step === 2): ?>
            <form method="post" id="step2Form" autocomplete="off" novalidate>

                <div class="readonly-text">Resetting for: <br><b><?= $_SESSION['admin_reset_email']; ?></b></div>

                <div class="input-wrapper">
                    <label>Enter 6-Digit OTP</label>
                    <div class="icon-input">
                        <i class="fas fa-shield-alt"></i>
                        <input type="text" name="otp" id="otp" maxlength="6"
                            class="<?php echo ($errorField == 'otp') ? 'input-error' : ''; ?>"
                            placeholder="000000" required>
                    </div>
                    <div class="validation-msg" id="otpMsg"></div>
                </div>

                <div class="input-wrapper">
                    <label>New Password</label>
                    <div class="icon-input">
                        <i class="fas fa-key"></i>
                        <input type="password" name="new_password" id="new_password"
                            placeholder="Complete OTP first" disabled required>
                    </div>
                    <div class="validation-msg" id="passMsg"></div>
                </div>

                <button type="submit" name="verify_and_update">
                    Secure Update <i class="fas fa-check-circle"></i>
                </button>
            </form>

            <form method="post">
                <button type="submit" name="resend_otp" class="btn-resend">Resend Code</button>
                <button type="submit" name="start_over" class="btn-cancel">Cancel & Start Over</button>
            </form>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <div class="login-footer">
                <p class="footer-note">
                    Remembered it? <a href="adminlogin.php" class="back-link">Back to Login</a>
                </p>
            </div>
        <?php endif; ?>

    </div>

    <script>
        // Real-Time UI Helpers
        function showError(input, msgBox, text) {
            input.classList.remove('input-success');
            input.classList.add('input-error');
            if (msgBox) {
                msgBox.innerText = text;
                msgBox.style.display = 'block';
            }
        }

        function showSuccess(input, msgBox) {
            input.classList.remove('input-error');
            input.classList.add('input-success');
            if (msgBox) {
                msgBox.style.display = 'none';
                msgBox.innerText = "";
            }
        }

        function resetField(input, msgBox) {
            input.classList.remove('input-error', 'input-success');
            if (msgBox) {
                msgBox.style.display = 'none';
                msgBox.innerText = "";
            }
        }

        // --- STEP 1 LOGIC ---
        const step1Form = document.getElementById('step1Form');
        if (step1Form) {
            const emailInput = document.getElementById('email');
            const emailMsg = document.getElementById('emailMsg');

            function valEmail() {
                const val = emailInput.value.trim();
                if (val === "") {
                    showError(emailInput, emailMsg, "Admin email required.");
                    return false;
                }
                if (!val.includes('@') || !val.includes('.')) {
                    showError(emailInput, emailMsg, "Invalid email format.");
                    return false;
                }
                showSuccess(emailInput, emailMsg);
                return true;
            }

            emailInput.addEventListener('input', valEmail);
            step1Form.addEventListener('submit', (e) => {
                if (!valEmail()) e.preventDefault();
            });
        }

        // --- STEP 2 LOGIC (SEQUENTIAL LOCKING) ---
        const step2Form = document.getElementById('step2Form');
        if (step2Form) {
            const otpInput = document.getElementById('otp');
            const otpMsg = document.getElementById('otpMsg');
            const passInput = document.getElementById('new_password');
            const passMsg = document.getElementById('passMsg');

            // Force Numbers Only and Unlock Password Field at 6 digits
            otpInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');

                if (this.value.length === 6) {
                    showSuccess(otpInput, otpMsg);
                    passInput.disabled = false;
                    passInput.placeholder = "Create new password";
                    passInput.focus();
                } else {
                    if (this.value.length > 0) showError(otpInput, otpMsg, "OTP must be exactly 6 digits.");
                    else resetField(otpInput, otpMsg);

                    passInput.disabled = true;
                    passInput.value = "";
                    passInput.placeholder = "Complete OTP first";
                    resetField(passInput, passMsg);
                }
            });

            // Strict Password Checking
            function valPass() {
                if (passInput.disabled) return false;
                const val = passInput.value;
                const hasUpper = /[A-Z]/.test(val);
                const hasLower = /[a-z]/.test(val);
                const hasNum = /[0-9]/.test(val);
                const hasSpec = /[!@#$%^&*(),.?":{}|<>]/.test(val);

                if (val === "") {
                    showError(passInput, passMsg, "Password required.");
                    return false;
                }
                if (!hasUpper) {
                    // Error 1: Capital Letter First
                    showError(passInput, passMsg, "Needs an uppercase letter.");
                    return false;
                }
                if (!hasSpec) {
                    // Error 2: Special Character Second
                    showError(passInput, passMsg, "Needs a special character.");
                    return false;
                }
                if (val.length < 6) {
                    // Error 3: Length
                    showError(passInput, passMsg, "Must be at least 6 characters.");
                    return false;
                }
                if (!hasLower) {
                    // Error 4: Lowercase
                    showError(passInput, passMsg, "Needs a lowercase letter.");
                    return false;
                }
                if (!hasNum) {
                    // Error 5: Number
                    showError(passInput, passMsg, "Needs a number.");
                    return false;
                }

                showSuccess(passInput, passMsg);
                return true;
            }

            passInput.addEventListener('input', valPass);

            step2Form.addEventListener('submit', function(e) {
                let isValid = true;
                if (otpInput.value.length !== 6) isValid = false;
                if (!valPass()) isValid = false;

                if (!isValid) e.preventDefault();
                else passInput.disabled = false; // Safety catch before submit
            });
        }
    </script>

</body>

</html>