<?php
include 'include/connection.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $owner_name = trim($_POST['uname']); 
    $business_name = trim($_POST['business_name']);
    $gst_number = strtoupper(trim($_POST['gst_number'])); 
    $pan_card = strtoupper(trim($_POST['pan_card']));
    $aadhar_card = trim($_POST['aadhar_card']);
    $phone = trim($_POST['phoneno']);
    $education = trim($_POST['education']); // NEW FIELD
    $experience = trim($_POST['experience']); // NEW FIELD
    $email = trim($_POST['email']);
    $address = trim($_POST['Address']);
    $city = trim($_POST['city']);
    $pincode = trim($_POST['pincode']);
    $password = $_POST['password'];
    $confirm = $_POST['cpassword'];
    $sec_question = $_POST['security_question'] ?? '';
    $sec_answer = trim($_POST['security_answer']);
    $status = 'pending'; 

    if ($owner_name && $business_name && $pan_card && $aadhar_card && $phone && $education && $experience && $email && $address && $city && $pincode && $password && $confirm && $sec_question && $sec_answer) {

        // Strict Backend Validation
        $hasUpper = preg_match('@[A-Z]@', $password);
        $hasNumber = preg_match('@[0-9]@', $password);
        $hasSpecial = preg_match('@[^\w]@', $password);

        $isPhoneValid = preg_match('/^[6-9][0-9]{9}$/', $phone);
        $isGstValid = empty($gst_number) || preg_match('/^[A-Z0-9]{15}$/', $gst_number);
        $isPanValid = preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan_card);
        $isAadharValid = preg_match('/^[0-9]{12}$/', $aadhar_card);
        $isPincodeValid = preg_match('/^[1-9][0-9]{5}$/', $pincode);
        $isEmailValid = preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email);

        if (!preg_match('/^[a-zA-Z\s]{3,50}$/', $owner_name)) {
            $message = '<div class="alert-box error">Owner name must be 3-50 characters (Letters only).</div>';
        } elseif (!preg_match('/^[a-zA-Z0-9\s\-\&\'\.]+$/', $business_name) || strlen($business_name) < 3) {
            $message = '<div class="alert-box error">Invalid Business Name format.</div>';
        } elseif (!$isGstValid) {
            $message = '<div class="alert-box error">If provided, GST Number must be exactly 15 alphanumeric characters.</div>';
        } elseif (!$isPanValid) {
            $message = '<div class="alert-box error">Invalid PAN Card format.</div>';
        } elseif (!$isAadharValid) {
            $message = '<div class="alert-box error">Invalid Aadhar Card format (12 digits).</div>';
        } elseif (!$isPhoneValid) {
            $message = '<div class="alert-box error">Invalid Phone number (Must be 10 digits starting with 6-9).</div>';
        } elseif (strlen($education) < 2) {
            $message = '<div class="alert-box error">Please enter a valid education or qualification.</div>';
        } elseif (strlen($experience) < 1) {
            $message = '<div class="alert-box error">Please enter your experience.</div>';
        } elseif (!$isEmailValid || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '<div class="alert-box error">Invalid email format. Must contain "@" and a valid domain (e.g. .com).</div>';
        } elseif (strlen($address) < 5) {
            $message = '<div class="alert-box error">Address must be at least 5 characters long.</div>';
        } elseif (!preg_match('/^[a-zA-Z\s]{3,50}$/', $city)) {
            $message = '<div class="alert-box error">Invalid City name.</div>';
        } elseif (!$isPincodeValid) {
            $message = '<div class="alert-box error">Invalid Pincode (Exactly 6 digits, cannot start with 0).</div>';
        } elseif ($password !== $confirm) {
            $message = '<div class="alert-box error">Passwords do not match.</div>';
        } elseif (strlen($password) < 6 || !$hasUpper || !$hasNumber || !$hasSpecial) {
            $message = '<div class="alert-box error">Password does not meet strict security requirements.</div>';
        } elseif (strlen($sec_answer) < 2) {
            $message = '<div class="alert-box error">Security answer must be valid.</div>';
        } else {
            // Check for existing seller
            $sql = "SELECT id FROM sellers WHERE email=? OR phone=? OR pan_card=? OR aadhar_card=? OR (gst_number=? AND gst_number != '')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sssss', $email, $phone, $pan_card, $aadhar_card, $gst_number);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $message = '<div class="alert-box error">Email, Phone, PAN, Aadhar, or GST already exists.</div>';
            } else {
                $stmt->close();

                // Insert new seller with education and experience
                $sql = "INSERT INTO sellers (owner_name, business_name, gst_number, pan_card, aadhar_card, phone, education, experience, email, address, city, pincode, password, security_question, security_answer, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                // Bind 16 parameters now
                $stmt->bind_param('ssssssssssssssss', $owner_name, $business_name, $gst_number, $pan_card, $aadhar_card, $phone, $education, $experience, $email, $address, $city, $pincode, $password, $sec_question, $sec_answer, $status);

                if ($stmt->execute()) {
                    $message = "
                    <div class='popup-overlay'>
                        <div class='popup-box'>
                            <div class='icon-circle'><i class='fas fa-clock'></i></div>
                            <h2>Application Submitted!</h2>
                            <p>Your seller account is pending admin approval. Redirecting to login...</p>
                        </div>
                    </div>
                    <script>
                        setTimeout(function() {
                            window.location.href = 'slogin.php';
                        }, 1500);
                    </script>";
                } else {
                    $message = '<div class="alert-box error">Registration failed. Try again.</div>';
                }
            }
            if ($stmt) $stmt->close();
        }
    } else {
        $message = '<div class="alert-box error">Please fill all required fields.</div>';
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become a Seller - Perfume Nest</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">
    <link rel="apple-touch-icon" sizes="180x180" href="img/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicons/favicon-16x16.png">
    <link rel="shortcut icon" type="image/x-icon" href="img/favicons/favicon.ico">
    <link rel="manifest" href="img/favicons/manifest.json">
    <meta name="msapplication-TileImage" content="img/favicons/mstile-150x150.png">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f7f3f0;
            margin: 0;
            padding: 0;
            color: #1C1C1B;
        }

        .reg-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 40px 20px;
        }

        .reg-card {
            background-color: #ffffff;
            width: 100%;
            max-width: 900px;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            position: relative;
        }

        .back-to-home {
            position: absolute;
            top: 25px;
            left: 30px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #888;
            font-size: 13px;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .back-to-home:hover {
            color: #6A5D52;
        }

        .reg-header {
            text-align: center;
            margin-bottom: 30px;
            margin-top: 10px;
        }

        .reg-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: #1C1C1B;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .reg-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
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
            font-family: 'Poppins', sans-serif;
            outline: none;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus {
            background-color: #fff;
            border-color: #B7AC9B;
        }

        .form-group input:disabled,
        .form-group select:disabled {
            background-color: #f0f0f0;
            color: #999;
            cursor: not-allowed;
            border-color: #e0e0e0;
        }

        .form-group input.success-border,
        .form-group select.success-border {
            border-color: #28a745;
            background-color: #f4fff5;
        }

        .form-group input.error-border,
        .form-group select.error-border {
            border-color: #d9534f;
            background-color: #fff5f5;
        }

        .validation-msg {
            color: #d9534f;
            font-size: 0.75rem;
            margin-top: 5px;
            display: none;
            font-weight: 500;
        }

        .btn-container {
            grid-column: span 2;
            margin-top: 20px;
        }

        .btn-reg {
            width: 100%;
            background-color: #6A5D52;
            color: #fff;
            font-size: 1.1rem;
            font-weight: 600;
            padding: 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-reg:hover {
            background-color: #554a40;
        }

        .reg-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.95rem;
            color: #666;
        }

        .reg-footer a {
            color: #1C1C1B;
            font-weight: 600;
            text-decoration: none;
        }

        .reg-footer a:hover {
            text-decoration: underline;
            color: #6A5D52;
        }

        .alert-box {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-size: 0.9rem;
            text-align: center;
        }

        .alert-box.error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .alert-box.success {
            background-color: #d4edda;
            color: #155724;
        }

        /* Popup Styles */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(3px);
        }

        .popup-box {
            background: #ffffff;
            padding: 40px 60px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
            animation: popUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .icon-circle {
            width: 70px;
            height: 70px;
            background: #f39c12;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 35px;
            margin: 0 auto 20px;
        }

        .popup-box h2 {
            font-family: 'Playfair Display', serif;
            color: #1C1C1B;
            margin-bottom: 10px;
        }

        .popup-box p {
            color: #666;
            font-size: 14px;
        }

        @keyframes popUp {
            0% {
                transform: scale(0.8);
                opacity: 0;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .reg-grid {
                grid-template-columns: 1fr;
            }

            .btn-container {
                grid-column: span 1;
            }
        }
    </style>
</head>

<body>

    <div class="reg-wrapper">
        <div class="reg-card">

            <a href="slogin.php" class="back-to-home">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>

            <div class="reg-header">
                <h2>Seller Application</h2>
                <p style="color: #666; font-size: 0.9rem;">Partner with Perfume Nest</p>
            </div>

            <?= $message; ?>

            <form method="post" id="regForm" novalidate>
                <div class="reg-grid">

                    <div class="form-group">
                        <label>Owner Full Name</label>
                        <input type="text" name="uname" id="uname" placeholder="Enter Your Name">
                        <div class="validation-msg" id="msg-uname"></div>
                    </div>

                    <div class="form-group">
                        <label>Business / Brand Name</label>
                        <input type="text" name="business_name" id="business_name" placeholder="ABC Fragrances" disabled>
                        <div class="validation-msg" id="msg-bname"></div>
                    </div>

                    <div class="form-group">
                        <label>GST Number (Optional)</label>
                        <input type="text" name="gst_number" id="gst_number" placeholder="15 Digit GSTIN" style="text-transform: uppercase;" maxlength="15" disabled>
                        <div class="validation-msg" id="msg-gst"></div>
                    </div>

                    <div class="form-group">
                        <label>PAN Card Number</label>
                        <input type="text" name="pan_card" id="pan_card" placeholder="10 Char PAN (e.g. ABCDE1234F)" style="text-transform: uppercase;" maxlength="10" disabled>
                        <div class="validation-msg" id="msg-pan"></div>
                    </div>

                    <div class="form-group">
                        <label>Aadhar Card Number</label>
                        <input type="text" name="aadhar_card" id="aadhar_card" placeholder="12 Digit Aadhar No" maxlength="12" disabled>
                        <div class="validation-msg" id="msg-aadhar"></div>
                    </div>

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phoneno" id="phoneno" placeholder="10 Digit Mobile No" maxlength="10" disabled>
                        <div class="validation-msg" id="msg-phone"></div>
                    </div>

                    <div class="form-group">
                        <label>Education / Qualification</label>
                        <input type="text" name="education" id="education" placeholder="Your Education/Qualification Details" disabled>
                        <div class="validation-msg" id="msg-edu"></div>
                    </div>

                    <div class="form-group">
                        <label>Years of Experience</label>
                        <input type="text" name="experience" id="experience" placeholder="e.g. 2 Years, None" disabled>
                        <div class="validation-msg" id="msg-exp"></div>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" id="email" placeholder="contact@domain.com" disabled>
                        <div class="validation-msg" id="msg-email"></div>
                    </div>

                    <div class="form-group">
                        <label>Business Address</label>
                        <input type="text" name="Address" id="Address" placeholder="Complete Business Address" disabled>
                        <div class="validation-msg" id="msg-addr"></div>
                    </div>

                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" id="city" placeholder="City Name" disabled>
                        <div class="validation-msg" id="msg-city"></div>
                    </div>

                    <div class="form-group">
                        <label>Pincode</label>
                        <input type="text" name="pincode" id="pincode" placeholder="6 Digit Pincode" maxlength="6" disabled>
                        <div class="validation-msg" id="msg-pincode"></div>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" id="password" placeholder="Enter Your Password" disabled>
                        <div class="validation-msg" id="msg-pass"></div>
                    </div>

                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="cpassword" id="cpassword" placeholder="Retype Password" disabled>
                        <div class="validation-msg" id="msg-cpass"></div>
                    </div>

                    <div class="form-group">
                        <label>Security Question</label>
                        <select name="security_question" id="sec_q" disabled>
                            <option value="" disabled selected>Select a question</option>
                            <option value="What is your pet's name?">What is your pet's name?</option>
                            <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
                            <option value="What city were you born in?">What city were you born in?</option>
                        </select>
                        <div class="validation-msg" id="msg-secq"></div>
                    </div>

                    <div class="form-group">
                        <label>Security Answer</label>
                        <input type="text" name="security_answer" id="sec_a" placeholder="Your Answer" disabled>
                        <div class="validation-msg" id="msg-seca"></div>
                    </div>

                    <div class="btn-container">
                        <button type="submit" class="btn-reg">Submit Application</button>
                    </div>

                </div>
            </form>

            <div class="reg-footer">
                Already a registered seller? <a href="slogin.php">Login Here</a>
            </div>

        </div>
    </div>

    <script>
        const form = document.getElementById('regForm');

        const inputs = {
            uname: { el: document.getElementById('uname'), msg: document.getElementById('msg-uname') },
            bname: { el: document.getElementById('business_name'), msg: document.getElementById('msg-bname') },
            gst: { el: document.getElementById('gst_number'), msg: document.getElementById('msg-gst') },
            pan: { el: document.getElementById('pan_card'), msg: document.getElementById('msg-pan') },
            aadhar: { el: document.getElementById('aadhar_card'), msg: document.getElementById('msg-aadhar') },
            phone: { el: document.getElementById('phoneno'), msg: document.getElementById('msg-phone') },
            edu: { el: document.getElementById('education'), msg: document.getElementById('msg-edu') }, // NEW
            exp: { el: document.getElementById('experience'), msg: document.getElementById('msg-exp') }, // NEW
            email: { el: document.getElementById('email'), msg: document.getElementById('msg-email') },
            addr: { el: document.getElementById('Address'), msg: document.getElementById('msg-addr') },
            city: { el: document.getElementById('city'), msg: document.getElementById('msg-city') },
            pincode: { el: document.getElementById('pincode'), msg: document.getElementById('msg-pincode') },
            pass: { el: document.getElementById('password'), msg: document.getElementById('msg-pass') },
            cpass: { el: document.getElementById('cpassword'), msg: document.getElementById('msg-cpass') },
            sec_q: { el: document.getElementById('sec_q'), msg: document.getElementById('msg-secq') },
            sec_a: { el: document.getElementById('sec_a'), msg: document.getElementById('msg-seca') }
        };

        function showError(inputObj, text) {
            inputObj.el.classList.remove('success-border');
            inputObj.el.classList.add('error-border');
            inputObj.msg.innerText = text;
            inputObj.msg.style.display = 'block';
        }

        function showSuccess(inputObj) {
            inputObj.el.classList.remove('error-border');
            inputObj.el.classList.add('success-border');
            inputObj.msg.style.display = 'none';
            inputObj.msg.innerText = "";
        }

        function valUname() {
            const val = inputs.uname.el.value.trim();
            if (val === "") { showError(inputs.uname, "Owner name is required."); return false; }
            if (val.length < 3) { showError(inputs.uname, "Must be at least 3 characters."); return false; }
            if (!/^[a-zA-Z\s]+$/.test(val)) { showError(inputs.uname, "Letters and spaces only."); return false; }
            showSuccess(inputs.uname); return true;
        }

        function valBname() {
            const val = inputs.bname.el.value.trim();
            if (val === "") { showError(inputs.bname, "Business name is required."); return false; }
            if (val.length < 3) { showError(inputs.bname, "Must be at least 3 characters."); return false; }
            if (!/^[a-zA-Z0-9\s\-\&\'\.]+$/.test(val)) { showError(inputs.bname, "Invalid characters in business name."); return false; }
            showSuccess(inputs.bname);
            if (!inputs.bname.el.disabled) inputs.pan.el.disabled = false;
            return true;
        }

        function valGst() {
            const val = inputs.gst.el.value.trim();
            if (val === "") { showSuccess(inputs.gst); return true; }
            if (val.length !== 15 || !/^[A-Z0-9]{15}$/.test(val)) { showError(inputs.gst, "GST must be exactly 15 alphanumeric characters."); return false; }
            showSuccess(inputs.gst); return true;
        }

        function valPan() {
            const val = inputs.pan.el.value.trim();
            const regex = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
            if (val === "") { showError(inputs.pan, "PAN card is required."); return false; }
            if (val.length !== 10) { showError(inputs.pan, "PAN card must be exactly 10 characters."); return false; }
            if (!regex.test(val)) { showError(inputs.pan, "Invalid PAN format (e.g. ABCDE1234F)."); return false; }
            showSuccess(inputs.pan); return true;
        }

        function valAadhar() {
            const val = inputs.aadhar.el.value.trim();
            if (val === "") { showError(inputs.aadhar, "Aadhar number is required."); return false; }
            if (val.length !== 12 || !/^[0-9]{12}$/.test(val)) { showError(inputs.aadhar, "Aadhar must be exactly 12 digits."); return false; }
            showSuccess(inputs.aadhar); return true;
        }

        function valPhone() {
            const val = inputs.phone.el.value.trim();
            if (val === "") { showError(inputs.phone, "Phone number is required."); return false; }
            if (val.length !== 10) { showError(inputs.phone, "Must be exactly 10 digits."); return false; }
            if (!/^[6-9][0-9]{9}$/.test(val)) { showError(inputs.phone, "Invalid Indian mobile number."); return false; }
            showSuccess(inputs.phone); return true;
        }

        function valEdu() {
            const val = inputs.edu.el.value.trim();
            if (val === "") { showError(inputs.edu, "Education/Qualification is required."); return false; }
            if (val.length < 2) { showError(inputs.edu, "Please enter a valid qualification."); return false; }
            showSuccess(inputs.edu); return true;
        }

        function valExp() {
            const val = inputs.exp.el.value.trim();
            if (val === "") { showError(inputs.exp, "Experience is required."); return false; }
            showSuccess(inputs.exp); return true;
        }

        function valEmail() {
            const val = inputs.email.el.value.trim();
            if (val === "") { showError(inputs.email, "Email is required."); return false; }
            const atPos = val.indexOf('@');
            if (atPos === -1) { showError(inputs.email, "Email must contain an '@' symbol."); return false; }
            if (atPos === 0) { showError(inputs.email, "Please enter a username before the '@' symbol."); return false; }
            const domainPart = val.substring(atPos + 1);
            if (domainPart.indexOf('.') === -1) { showError(inputs.email, "Email must contain a domain extension (like .com)."); return false; }
            const dotPos = domainPart.lastIndexOf('.');
            const extension = domainPart.substring(dotPos + 1);
            if (dotPos === 0 || extension.length < 2) { showError(inputs.email, "Please enter a valid extension (like .com or .in)."); return false; }
            showSuccess(inputs.email); return true;
        }

        function valAddr() {
            const val = inputs.addr.el.value.trim();
            if (val === "") { showError(inputs.addr, "Address is required."); return false; }
            if (val.length < 5) { showError(inputs.addr, "Must be at least 5 characters for a full address."); return false; }
            showSuccess(inputs.addr); return true;
        }

        function valCity() {
            const val = inputs.city.el.value.trim();
            if (val === "") { showError(inputs.city, "City is required."); return false; }
            if (val.length < 3) { showError(inputs.city, "City name is too short."); return false; }
            if (!/^[a-zA-Z\s]+$/.test(val)) { showError(inputs.city, "Letters and spaces only."); return false; }
            showSuccess(inputs.city); return true;
        }

        function valPincode() {
            const val = inputs.pincode.el.value.trim();
            if (val === "") { showError(inputs.pincode, "Pincode is required."); return false; }
            if (val.length !== 6 || !/^[1-9][0-9]{5}$/.test(val)) { showError(inputs.pincode, "Must be exactly 6 valid digits."); return false; }
            showSuccess(inputs.pincode); return true;
        }

        function valPass() {
            const val = inputs.pass.el.value;
            if (val === "") { showError(inputs.pass, "Password is required."); return false; }
            if (!/[A-Z]/.test(val)) { showError(inputs.pass, "Must include a Capital letter."); return false; }
            if (!/[!@#$%^&*(),.?":{}|<>]/.test(val)) { showError(inputs.pass, "Must include a Special character."); return false; }
            if (val.length < 6) { showError(inputs.pass, "Must be at least 6 characters."); return false; }
            if (!/[a-z]/.test(val)) { showError(inputs.pass, "Must include a Lowercase letter."); return false; }
            if (!/[0-9]/.test(val)) { showError(inputs.pass, "Must include a Number."); return false; }
            showSuccess(inputs.pass);
            if (inputs.cpass.el.value !== "") valCpass();
            return true;
        }

        function valCpass() {
            const val = inputs.cpass.el.value;
            const passVal = inputs.pass.el.value;
            if (val === "") { showError(inputs.cpass, "Please confirm your password."); return false; }
            if (val !== passVal) { showError(inputs.cpass, "Passwords do not match!"); return false; }
            showSuccess(inputs.cpass); return true;
        }

        function valSecQ() {
            if (inputs.sec_q.el.value === "") { showError(inputs.sec_q, "Please select a security question."); return false; }
            showSuccess(inputs.sec_q); return true;
        }

        function valSecA() {
            const val = inputs.sec_a.el.value.trim();
            if (val === "") { showError(inputs.sec_a, "Answer is required."); return false; }
            if (val.length < 2) { showError(inputs.sec_a, "Answer is too short."); return false; }
            showSuccess(inputs.sec_a); return true;
        }

        const sequence = [
            { obj: inputs.uname, valFn: valUname },
            { obj: inputs.bname, valFn: valBname },
            { obj: inputs.gst, valFn: valGst },
            { obj: inputs.pan, valFn: valPan },
            { obj: inputs.aadhar, valFn: valAadhar },
            { obj: inputs.phone, valFn: valPhone },
            { obj: inputs.edu, valFn: valEdu }, // NEW
            { obj: inputs.exp, valFn: valExp }, // NEW
            { obj: inputs.email, valFn: valEmail },
            { obj: inputs.addr, valFn: valAddr },
            { obj: inputs.city, valFn: valCity },
            { obj: inputs.pincode, valFn: valPincode },
            { obj: inputs.pass, valFn: valPass },
            { obj: inputs.cpass, valFn: valCpass },
            { obj: inputs.sec_q, valFn: valSecQ },
            { obj: inputs.sec_a, valFn: valSecA }
        ];

        function lockFromIndex(index) {
            for (let i = index; i < sequence.length; i++) {
                sequence[i].obj.el.disabled = true;
                sequence[i].obj.el.classList.remove('success-border', 'error-border');
                sequence[i].obj.msg.style.display = 'none';
            }
        }

        sequence.forEach((item, index) => {
            item.obj.el.addEventListener('input', () => {
                if (item.valFn()) {
                    if (index + 1 < sequence.length) sequence[index + 1].obj.el.disabled = false;
                } else {
                    lockFromIndex(index + 1);
                }
            });

            item.obj.el.addEventListener('change', () => {
                if (item.valFn()) {
                    if (index + 1 < sequence.length) sequence[index + 1].obj.el.disabled = false;
                } else {
                    lockFromIndex(index + 1);
                }
            });

            item.obj.el.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (item.valFn() && index + 1 < sequence.length) {
                        sequence[index + 1].obj.el.disabled = false;
                        sequence[index + 1].obj.el.focus();
                    }
                }
            });
        });

        // Formatters and special sequential triggers
        inputs.gst.el.addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (valGst()) {
                inputs.pan.el.disabled = false;
                if (this.value.length === 15) { inputs.pan.el.focus(); }
            } else {
                lockFromIndex(3); 
            }
        });

        inputs.pan.el.addEventListener('input', function() {
            let val = this.value.toUpperCase();
            let newVal = '';
            for (let i = 0; i < val.length; i++) {
                if (i < 5) { if (/[A-Z]/.test(val[i])) newVal += val[i]; } 
                else if (i >= 5 && i < 9) { if (/[0-9]/.test(val[i])) newVal += val[i]; } 
                else if (i === 9) { if (/[A-Z]/.test(val[i])) newVal += val[i]; }
            }
            this.value = newVal.substring(0, 10);
            if (this.value.length === 10 && valPan()) {
                inputs.aadhar.el.disabled = false;
                inputs.aadhar.el.focus();
            } else {
                lockFromIndex(4);
            }
        });

        inputs.aadhar.el.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 12 && valAadhar()) {
                inputs.phone.el.disabled = false;
                inputs.phone.el.focus();
            }
        });

        // FIX: Phone now unlocks Education
        inputs.phone.el.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 10 && valPhone()) {
                inputs.edu.el.disabled = false;
                inputs.edu.el.focus();
            }
        });

        inputs.pincode.el.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 6 && valPincode()) {
                inputs.pass.el.disabled = false;
                inputs.pass.el.focus();
            }
        });

        form.addEventListener('submit', function(e) {
            let isValid = true;
            sequence.forEach(item => {
                if (!item.valFn()) isValid = false;
            });
            if (!isValid) {
                e.preventDefault();
            } else {
                sequence.forEach(item => item.obj.el.disabled = false);
            }
        });
    </script>

</body>

</html>