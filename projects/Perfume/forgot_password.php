<?php
session_start();
// Force PHP to use the correct timezone
date_default_timezone_set('Asia/Kolkata');

include 'include/connection.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$message = '';

// --- HANDLE CANCEL & START OVER (Bypasses Form Validation) ---
if (isset($_GET['reset']) && $_GET['reset'] === 'true') {
    unset($_SESSION['reset_step']);
    unset($_SESSION['reset_email']);
    unset($_SESSION['sec_q']);
    unset($_SESSION['reset_method']);
    header('Location: forgot_password.php');
    exit;
}

// Track which step the user is on (Default is Step 1)
$step = $_SESSION['reset_step'] ?? 1;

// Helper Function to Generate and Send OTP
function generateAndSendOTP($email, $conn)
{
    $otp = sprintf("%06d", mt_rand(1, 999999));
    $expiry = date("Y-m-d H:i:s", strtotime("+25 minutes"));

    $update_otp = "UPDATE users SET reset_otp=?, otp_expiry=? WHERE email=?";
    $stmt_otp = $conn->prepare($update_otp);
    $stmt_otp->bind_param('sss', $otp, $expiry, $email);
    $stmt_otp->execute();

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'perfumenest3012@gmail.com';
        $mail->Password   = 'mfli ckub inlb ggxe';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('perfumenest3012@Gmail.com', 'Perfume Nest Support');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Perfume Nest - Password Reset Verification Code';

        $htmlBody = "
        <div style='background-color: #f7f3f0; padding: 40px 20px; font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 40px 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: center;'>
                <h1 style='color: #1C1C1B; font-family: Georgia, serif; font-size: 28px; margin-bottom: 5px;'>Perfume Nest</h1>
                <h3 style='color: #6A5D52; font-weight: normal; margin-top: 0; margin-bottom: 30px; font-size: 16px;'>Password Reset Request</h3>
                
                <p style='color: #333; font-size: 16px; line-height: 1.6; text-align: left;'>Hello,</p>
                <p style='color: #333; font-size: 16px; line-height: 1.6; text-align: left;'>We received a request to reset the password associated with this email address. For your security, please use the following One-Time Password (OTP) to verify your identity and complete the process.</p>
                
                <div style='margin: 40px 0;'>
                    <span style='display: inline-block; font-size: 32px; font-weight: bold; letter-spacing: 10px; color: #1C1C1B; background-color: #f4fff5; border: 2px dashed #28a745; padding: 20px 40px; border-radius: 8px; user-select: all; cursor: pointer;'>$otp</span>
                </div>
                
                <p style='color: #777; font-size: 13px;'><em>(Double-click the code above to select and copy it)</em></p>
                
                <p style='color: #333; font-size: 15px; line-height: 1.6; text-align: left; margin-top: 30px;'>This code is valid for <strong>25 minutes</strong>. If you did not make this request, please safely ignore this email; your account remains secure.</p>
                
                <hr style='border: none; border-top: 1px solid #eaeaea; margin: 30px 0;'>
                <p style='color: #999; font-size: 12px;'>&copy; " . date('Y') . " Perfume Nest. All rights reserved.</p>
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

    // --- STEP 1: Verify Email Exists ---
    if (isset($_POST['check_email'])) {
        $email = trim($_POST['email']);
        $sql = "SELECT id, security_question FROM users WHERE email=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id, $sec_question);
            $stmt->fetch();

            $_SESSION['reset_email'] = $email;
            $_SESSION['sec_q'] = $sec_question;
            $_SESSION['reset_step'] = 2;
            $step = 2;
        } else {
            $message = '<div class="alert-box error">Email not found in our system.</div>';
        }
        $stmt->close();
    }

    // --- STEP 2: Process Method Selection ---
    elseif (isset($_POST['choose_method'])) {
        $method = $_POST['reset_method'];
        $email = $_SESSION['reset_email'];

        if ($method === 'email_otp') {
            $mail_status = generateAndSendOTP($email, $conn);
            if ($mail_status === true) {
                $_SESSION['reset_method'] = 'email_otp';
                $_SESSION['reset_step'] = 3;
                $step = 3;
                $message = '<div class="alert-box success">OTP sent to your email!</div>';
            } else {
                $message = '<div class="alert-box error">Mailer Error: ' . $mail_status . '</div>';
            }
        } elseif ($method === 'security_q') {
            if (empty($_SESSION['sec_q'])) {
                $message = '<div class="alert-box error">No security question setup for this account.</div>';
            } else {
                $_SESSION['reset_method'] = 'security_q';
                $_SESSION['reset_step'] = 3;
                $step = 3;
            }
        }
    }

    // --- STEP 3a: RESEND OTP ---
    elseif (isset($_POST['resend_otp'])) {
        $email = $_SESSION['reset_email'];
        $mail_status = generateAndSendOTP($email, $conn);
        if ($mail_status === true) {
            $message = '<div class="alert-box success">A new OTP has been sent!</div>';
        } else {
            $message = '<div class="alert-box error">Mailer Error: ' . $mail_status . '</div>';
        }
    }

    // --- STEP 3b: Verify OTP ---
    elseif (isset($_POST['verify_otp'])) {
        $email = $_SESSION['reset_email'];
        $otp_entered = trim($_POST['otp']);

        $sql = "SELECT id FROM users WHERE email=? AND reset_otp=? AND otp_expiry >= NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $email, $otp_entered);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $_SESSION['reset_step'] = 4;
            $step = 4;
            $message = '<div class="alert-box success">Identity verified. Enter your new password.</div>';
        } else {
            $message = '<div class="alert-box error">Invalid or expired OTP.</div>';
        }
    }

    // --- STEP 3c: Verify Security Question ---
    elseif (isset($_POST['verify_sec_q'])) {
        $email = $_SESSION['reset_email'];
        $answer_entered = trim($_POST['sec_answer']);

        $sql = "SELECT id, security_answer FROM users WHERE email=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->bind_result($id, $db_answer);
        $stmt->fetch();

        if (strtolower($answer_entered) === strtolower($db_answer)) {
            $_SESSION['reset_step'] = 4;
            $step = 4;
            $message = '<div class="alert-box success">Identity verified. Enter your new password.</div>';
        } else {
            $message = '<div class="alert-box error">Incorrect security answer.</div>';
        }
    }

    // --- STEP 4: Set New Password ---
    elseif (isset($_POST['set_new_password'])) {
        $email = $_SESSION['reset_email'];
        $new_password = $_POST['new_password'];

        $hasUpper = preg_match('@[A-Z]@', $new_password);
        $hasNumber = preg_match('@[0-9]@', $new_password);
        $hasSpecial = preg_match('@[^\w]@', $new_password);

        if (strlen($new_password) < 6 || !$hasUpper || !$hasNumber || !$hasSpecial) {
            $message = '<div class="alert-box error">Password does not meet security requirements.</div>';
        } else {
            $sql_update = "UPDATE users SET password=?, reset_otp=NULL, otp_expiry=NULL WHERE email=?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param('ss', $new_password, $email);
            $stmt_update->execute();

            session_destroy();
            header('Location: login.php?reset=success');
            exit;
        }
    }
}

include 'include/header.php';
?>

<style>
    .login-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 80vh;
        padding: 20px;
    }

    .login-card {
        background: #fff;
        width: 100%;
        max-width: 400px;
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

    .form-group input,
    .form-group select {
        width: 100%;
        background-color: #E8E8E8;
        border: 2px solid transparent;
        border-radius: 8px;
        padding: 12px;
        font-size: 0.95rem;
        color: #333;
        outline: none;
        font-family: 'Poppins', sans-serif;
        transition: 0.3s;
        box-sizing: border-box;
    }

    .form-group input:focus,
    .form-group select:focus {
        background-color: #fff;
        border-color: #B7AC9B;
    }

    .form-group input.error-border {
        border-color: #d9534f;
        background-color: #fdf0f0;
    }

    .form-group input.success-border {
        border-color: #28a745;
        background-color: #f4fff5;
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

    .btn-resend {
        background: none;
        border: none;
        color: #6A5D52;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: underline;
        margin-top: 15px;
        width: 100%;
        text-align: center;
    }

    .btn-resend:hover {
        color: #1C1C1B;
    }

    .btn-cancel {
        display: block;
        color: #d9534f;
        font-size: 0.85rem;
        text-decoration: underline;
        margin-top: 15px;
        width: 100%;
        text-align: center;
        cursor: pointer;
    }

    .btn-cancel:hover {
        color: #c9302c;
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

    .alert-box {
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 5px;
        font-size: 0.85rem;
        text-align: left;
    }

    .alert-box.error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .alert-box.success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .readonly-text {
        background-color: #f9f9f9;
        padding: 12px;
        border-radius: 5px;
        border: 1px solid #eee;
        color: #555;
        font-size: 0.9rem;
        margin-bottom: 20px;
        text-align: left;
    }
</style>

<div class="login-wrapper">
    <div class="login-card">

        <h2>Perfume Nest</h2>
        <p class="subtitle">Reset Password</p>

        <?= $message; ?>

        <?php if ($step === 1): ?>
            <form method="post" id="step1Form" novalidate>
                <div class="form-group">
                    <label>Registered Email</label>
                    <input type="email" name="email" id="email" placeholder="example@mail.com" required>
                    <div class="validation-msg" id="emailMsg"></div>
                </div>
                <button type="submit" name="check_email" class="btn-login">Next Step</button>
            </form>
        <?php endif; ?>

        <?php if ($step === 2): ?>
            <form method="post" id="step2Form" novalidate>
                <div class="readonly-text">Account found: <br><b><?= $_SESSION['reset_email']; ?></b></div>
                <div class="form-group">
                    <label>Choose Reset Method</label>
                    <select name="reset_method" id="reset_method" required>
                        <option value="" disabled selected>Select an option</option>
                        <option value="email_otp">Send OTP to Email</option>
                        <option value="security_q">Answer Security Question</option>
                    </select>
                    <div class="validation-msg" id="methodMsg"></div>
                </div>
                <button type="submit" name="choose_method" class="btn-login">Send Verification</button>
                <a href="forgot_password.php?reset=true" class="btn-cancel">Cancel & Start Over</a>
            </form>
        <?php endif; ?>

        <?php if ($step === 3): ?>
            <div class="readonly-text">Verifying: <b><?= $_SESSION['reset_email']; ?></b></div>

            <?php if ($_SESSION['reset_method'] === 'email_otp'): ?>
                <form method="post" id="otpForm" novalidate>
                    <div class="form-group">
                        <label>Enter 6-Digit OTP</label>
                        <input type="text" name="otp" id="otp" placeholder="000000" maxlength="6" required>
                        <div class="validation-msg" id="otpMsg"></div>
                    </div>
                    <button type="submit" name="verify_otp" class="btn-login">Verify OTP</button>
                </form>

                <form method="post">
                    <button type="submit" name="resend_otp" class="btn-resend">Didn't receive code? Resend OTP</button>
                </form>
                <a href="forgot_password.php?reset=true" class="btn-cancel">Cancel & Start Over</a>

            <?php elseif ($_SESSION['reset_method'] === 'security_q'): ?>
                <form method="post" id="secqForm" novalidate>
                    <div class="form-group">
                        <label>Security Question</label>
                        <div class="readonly-text" style="margin-bottom:0; background: #fff;"><i><?= $_SESSION['sec_q']; ?></i></div>
                    </div>
                    <div class="form-group">
                        <label>Your Answer</label>
                        <input type="text" name="sec_answer" id="sec_answer" placeholder="Enter your answer" required>
                        <div class="validation-msg" id="ansMsg"></div>
                    </div>
                    <button type="submit" name="verify_sec_q" class="btn-login">Verify Answer</button>
                    <a href="forgot_password.php?reset=true" class="btn-cancel">Cancel & Start Over</a>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($step === 4): ?>
            <form method="post" id="newPassForm" novalidate>
                <div class="readonly-text">Identity verified for: <br><b><?= $_SESSION['reset_email']; ?></b></div>

                <div class="form-group">
                    <label>Create New Password</label>
                    <input type="password" name="new_password" id="new_password" placeholder="Min 6 Chars, A-Z, 0-9, @#$" required>
                    <div class="validation-msg" id="passMsg"></div>
                </div>

                <button type="submit" name="set_new_password" class="btn-login">Set New Password</button>
            </form>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <div class="login-footer">
                Remembered your password? <a href="login.php">Login Here</a>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
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

    const emailInput = document.getElementById('email');
    if (emailInput) {
        const emailMsg = document.getElementById('emailMsg');

        function valEmail() {
            const val = emailInput.value.trim();
            if (val === "") {
                showError(emailInput, emailMsg, "Email is required.");
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
        document.getElementById('step1Form').addEventListener('submit', (e) => {
            if (!valEmail()) e.preventDefault();
        });
    }

    const methodSelect = document.getElementById('reset_method');
    if (methodSelect) {
        const methodMsg = document.getElementById('methodMsg');

        function valMethod() {
            if (methodSelect.value === "") {
                showError(methodSelect, methodMsg, "Please select a method.");
                return false;
            }
            showSuccess(methodSelect, methodMsg);
            return true;
        }
        methodSelect.addEventListener('change', valMethod);
        document.getElementById('step2Form').addEventListener('submit', (e) => {
            if (!valMethod()) e.preventDefault();
        });
    }

    const otpInput = document.getElementById('otp');
    if (otpInput) {
        const otpMsg = document.getElementById('otpMsg');
        otpInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 6) showSuccess(otpInput, otpMsg);
            else showError(otpInput, otpMsg, "OTP must be 6 digits.");
        });
        document.getElementById('otpForm').addEventListener('submit', (e) => {
            if (otpInput.value.length !== 6) e.preventDefault();
        });
    }

    const ansInput = document.getElementById('sec_answer');
    if (ansInput) {
        const ansMsg = document.getElementById('ansMsg');
        ansInput.addEventListener('input', function() {
            if (this.value.trim() !== "") showSuccess(ansInput, ansMsg);
            else showError(ansInput, ansMsg, "Answer required.");
        });
        document.getElementById('secqForm').addEventListener('submit', (e) => {
            if (ansInput.value.trim() === "") e.preventDefault();
        });
    }

    const passInput = document.getElementById('new_password');
    if (passInput) {
        const passMsg = document.getElementById('passMsg');

        function valPass() {
            const val = passInput.value;
            if (val === "") {
                showError(passInput, passMsg, "Password is required.");
                return false;
            }
            if (!/[A-Z]/.test(val)) {
                showError(passInput, passMsg, "Must include a Capital letter.");
                return false;
            }
            if (!/[!@#$%^&*(),.?":{}|<>]/.test(val)) {
                showError(passInput, passMsg, "Must include a Special character.");
                return false;
            }
            if (val.length < 6) {
                showError(passInput, passMsg, "Must be at least 6 characters.");
                return false;
            }
            if (!/[0-9]/.test(val)) {
                showError(passInput, passMsg, "Must include a Number.");
                return false;
            }
            showSuccess(passInput, passMsg);
            return true;
        }
        passInput.addEventListener('input', valPass);
        document.getElementById('newPassForm').addEventListener('submit', (e) => {
            if (!valPass()) e.preventDefault();
        });
    }
</script>

<?php include 'include/footer.php'; ?>