<?php
include 'include/connection.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['uname']);
    $phone = trim($_POST['phoneno']);
    $dob = $_POST['DOB'];
    $email = trim($_POST['email']);
    $gender = $_POST['gender'] ?? '';
    $address = trim($_POST['Address']);
    $city = trim($_POST['city']);
    $pincode = trim($_POST['pincode']);
    $password = $_POST['password'];
    $confirm = $_POST['cpassword'];
    $sec_question = $_POST['security_question'] ?? '';
    $sec_answer = trim($_POST['security_answer']);

    if ($username && $phone && $dob && $email && $gender && $address && $city && $pincode && $password && $confirm && $sec_question && $sec_answer) {

        // Strict Backend Validation
        $hasUpper = preg_match('@[A-Z]@', $password);
        $hasNumber = preg_match('@[0-9]@', $password);
        $hasSpecial = preg_match('@[^\w]@', $password);
        $isPhoneValid = preg_match('/^[0-9]{10}$/', $phone);
        $isPincodeValid = preg_match('/^[0-9]{6}$/', $pincode);

        if (!preg_match('/^[a-zA-Z0-9 ]{3,}$/', $username)) {
            $message = '<div class="alert-box error">Username must be at least 3 characters (Letters/Numbers only).</div>';
        } elseif (!$isPhoneValid) {
            $message = '<div class="alert-box error">Phone number must be exactly 10 digits.</div>';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '<div class="alert-box error">Invalid email format.</div>';
        } elseif (strlen($address) < 5) {
            $message = '<div class="alert-box error">Address must be at least 5 characters long.</div>';
        } elseif (strlen($city) < 2) {
            $message = '<div class="alert-box error">Please enter a valid city name.</div>';
        } elseif (!$isPincodeValid) {
            $message = '<div class="alert-box error">Pincode must be exactly 6 numeric digits.</div>';
        } elseif ($password !== $confirm) {
            $message = '<div class="alert-box error">Passwords do not match.</div>';
        } elseif (strlen($password) < 6 || !$hasUpper || !$hasNumber || !$hasSpecial) {
            $message = '<div class="alert-box error">Password does not meet security requirements.</div>';
        } elseif (strlen($sec_answer) < 2) {
            $message = '<div class="alert-box error">Security answer must be valid.</div>';
        } else {
            // Check for existing user
            $sql = "SELECT id FROM users WHERE username=? OR email=? OR phone=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('sss', $username, $email, $phone);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $message = '<div class="alert-box error">Username, Email, or Phone already exists.</div>';
            } else {
                $stmt->close();

                // FIX: Removed `role` from the INSERT query since the column was deleted in the DB
                $sql = "INSERT INTO users (username, phone, dob, email, gender, address, city, pincode, password, security_question, security_answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                // FIX: Reduced parameter count from 12 to 11 ('sssssssssss')
                $stmt->bind_param('sssssssssss', $username, $phone, $dob, $email, $gender, $address, $city, $pincode, $password, $sec_question, $sec_answer);

                if ($stmt->execute()) {
                    // SUCCESS POPUP AND REDIRECT INJECTION
                    $message = "
                    <div class='popup-overlay'>
                        <div class='popup-box'>
                            <div class='icon-circle'><i class='fas fa-check'></i></div>
                            <h2>Registration Successful!</h2>
                            <p>Welcome to Perfume Nest. Redirecting to login...</p>
                        </div>
                    </div>
                    <script>
                        setTimeout(function() {
                            window.location.href = 'login.php';
                        }, 1500);
                    </script>";
                } else {
                    $message = '<div class="alert-box error">Registration failed. Try again.</div>';
                }
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
    /* --- 3-COLUMN REGISTRATION STYLES --- */
    .reg-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 90vh;
        padding: 40px 20px;
    }

    .reg-card {
        background-color: #ffffff;
        width: 100%;
        max-width: 1100px;
        padding: 40px;
        border-radius: 20px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }

    .reg-header {
        text-align: center;
        margin-bottom: 30px;
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
        grid-template-columns: repeat(3, 1fr);
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
    }

    .form-group input:focus,
    .form-group select:focus {
        background-color: #fff;
        border-color: #B7AC9B;
    }

    /* === DISABLED CSS FOR LOCKED FIELDS === */
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
        grid-column: span 3;
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

    /* === SUCCESS POPUP STYLES === */
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
        background: #28a745;
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
        0% { transform: scale(0.8); opacity: 0; }
        100% { transform: scale(1); opacity: 1; }
    }

    @media (max-width: 992px) {
        .reg-grid { grid-template-columns: 1fr 1fr; }
        .btn-container { grid-column: span 2; }
    }

    @media (max-width: 600px) {
        .reg-grid { grid-template-columns: 1fr; }
        .btn-container { grid-column: span 1; }
    }
</style>

<div class="reg-wrapper">
    <div class="reg-card">

        <div class="reg-header">
            <h2>Create Account</h2>
        </div>

        <?= $message; ?>

        <form method="post" id="regForm" novalidate>
            <div class="reg-grid">

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="uname" id="uname" placeholder="Enter Your Name">
                    <div class="validation-msg" id="msg-uname"></div>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phoneno" id="phoneno" placeholder="10 Digit Mobile No" maxlength="10" disabled>
                    <div class="validation-msg" id="msg-phone"></div>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" id="email" placeholder="example@mail.com" disabled>
                    <div class="validation-msg" id="msg-email"></div>
                </div>

                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="DOB" id="DOB" disabled>
                    <div class="validation-msg" id="msg-dob"></div>
                </div>

                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" id="gender" disabled>
                        <option value="" disabled selected>Select Gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                    <div class="validation-msg" id="msg-gender"></div>
                </div>

                <div class="form-group">
                    <label>Street Address</label>
                    <input type="text" name="Address" id="Address" placeholder="e.g. 123 Main Street, Area" disabled>
                    <div class="validation-msg" id="msg-addr"></div>
                </div>

                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" id="city" placeholder="e.g. Surat" disabled>
                    <div class="validation-msg" id="msg-city"></div>
                </div>

                <div class="form-group">
                    <label>Pincode</label>
                    <input type="text" name="pincode" id="pincode" placeholder="e.g. 395003" maxlength="6" disabled>
                    <div class="validation-msg" id="msg-pincode"></div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" id="password" placeholder="Create a strong password" disabled>
                    <div class="validation-msg" id="msg-pass"></div>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="cpassword" id="cpassword" placeholder="Retype your password" disabled>
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
                    <input type="text" name="security_answer" id="sec_a" placeholder="Your Security Answer" disabled>
                    <div class="validation-msg" id="msg-seca"></div>
                </div>

                <div class="btn-container">
                    <button type="submit" class="btn-reg">Register</button>
                </div>

            </div>
        </form>

        <div class="reg-footer">
            Already have an account? <a href="login.php">Login Here</a>
        </div>

    </div>
</div>

<script>
    const form = document.getElementById('regForm');

    const inputs = {
        uname: { el: document.getElementById('uname'), msg: document.getElementById('msg-uname') },
        phone: { el: document.getElementById('phoneno'), msg: document.getElementById('msg-phone') },
        email: { el: document.getElementById('email'), msg: document.getElementById('msg-email') },
        dob: { el: document.getElementById('DOB'), msg: document.getElementById('msg-dob') },
        gender: { el: document.getElementById('gender'), msg: document.getElementById('msg-gender') },
        addr: { el: document.getElementById('Address'), msg: document.getElementById('msg-addr') },
        city: { el: document.getElementById('city'), msg: document.getElementById('msg-city') },
        pincode: { el: document.getElementById('pincode'), msg: document.getElementById('msg-pincode') },
        pass: { el: document.getElementById('password'), msg: document.getElementById('msg-pass') },
        cpass: { el: document.getElementById('cpassword'), msg: document.getElementById('msg-cpass') },
        sec_q: { el: document.getElementById('sec_q'), msg: document.getElementById('msg-secq') },
        sec_a: { el: document.getElementById('sec_a'), msg: document.getElementById('msg-seca') }
    };

    // Helper to show errors
    function showError(inputObj, text) {
        inputObj.el.classList.remove('success-border');
        inputObj.el.classList.add('error-border');
        inputObj.msg.innerText = text;
        inputObj.msg.style.display = 'block';
    }

    // Helper to show success
    function showSuccess(inputObj) {
        inputObj.el.classList.remove('error-border');
        inputObj.el.classList.add('success-border');
        inputObj.msg.style.display = 'none';
        inputObj.msg.innerText = "";
    }

    // --- INDIVIDUAL VALIDATION FUNCTIONS ---
    function valUname() {
        const val = inputs.uname.el.value.trim();
        const regex = /^[a-zA-Z0-9 ]+$/;
        if (val === "") { showError(inputs.uname, "Username is required."); return false; }
        if (val.length < 3) { showError(inputs.uname, "Must be at least 3 characters."); return false; }
        if (!regex.test(val)) { showError(inputs.uname, "Letters and numbers only."); return false; }
        showSuccess(inputs.uname);
        return true;
    }

    function valPhone() {
        const val = inputs.phone.el.value.trim();
        if (val === "") { showError(inputs.phone, "Phone number is required."); return false; }
        if (val.length !== 10) { showError(inputs.phone, "Must be exactly 10 digits."); return false; }
        showSuccess(inputs.phone);
        return true;
    }

    function valEmail() {
        const val = inputs.email.el.value.trim();
        if (val === "") { showError(inputs.email, "Email is required."); return false; }
        if (!val.includes('@')) { showError(inputs.email, "Must contain an '@' symbol."); return false; }
        if (!val.includes('.')) { showError(inputs.email, "Must contain a domain (like .com)."); return false; }
        if (val.indexOf('@') > val.lastIndexOf('.')) { showError(inputs.email, "Invalid email format."); return false; }
        showSuccess(inputs.email);
        return true;
    }

    function valDob() {
        const val = inputs.dob.el.value;
        if (val === "") { showError(inputs.dob, "Date of Birth is required."); return false; }
        showSuccess(inputs.dob);
        return true;
    }

    function valGender() {
        if (inputs.gender.el.value === "") { showError(inputs.gender, "Please select a gender."); return false; }
        showSuccess(inputs.gender);
        return true;
    }

    function valAddr() {
        const val = inputs.addr.el.value.trim();
        if (val === "") { showError(inputs.addr, "Address is required."); return false; }
        if (val.length < 5) { showError(inputs.addr, "Must be at least 5 characters."); return false; }
        showSuccess(inputs.addr);
        return true;
    }

    function valCity() {
        const val = inputs.city.el.value.trim();
        if (val === "") { showError(inputs.city, "City is required."); return false; }
        if (val.length < 2) { showError(inputs.city, "Enter a valid city name."); return false; }
        showSuccess(inputs.city);
        return true;
    }

    function valPincode() {
        const val = inputs.pincode.el.value.trim();
        if (val === "") { showError(inputs.pincode, "Pincode is required."); return false; }
        if (val.length !== 6) { showError(inputs.pincode, "Must be exactly 6 digits."); return false; }
        showSuccess(inputs.pincode);
        return true;
    }

    function valPass() {
        const val = inputs.pass.el.value;
        if (val === "") { showError(inputs.pass, "Password is required."); return false; }
        if (!/[A-Z]/.test(val)) { showError(inputs.pass, "Must include a Capital letter."); return false; }
        if (!/[!@#$%^&*(),.?":{}|<>]/.test(val)) { showError(inputs.pass, "Must include a Special character."); return false; }
        if (val.length < 6) { showError(inputs.pass, "Must be at least 6 characters."); return false; }
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
        showSuccess(inputs.cpass);
        return true;
    }

    function valSecQ() {
        if (inputs.sec_q.el.value === "") { showError(inputs.sec_q, "Please select a security question."); return false; }
        showSuccess(inputs.sec_q);
        return true;
    }

    function valSecA() {
        const val = inputs.sec_a.el.value.trim();
        if (val === "") { showError(inputs.sec_a, "Answer is required."); return false; }
        if (val.length < 2) { showError(inputs.sec_a, "Answer is too short."); return false; }
        showSuccess(inputs.sec_a);
        return true;
    }

    // --- SEQUENTIAL LOGIC AND LOCKING ---
    const sequence = [
        { obj: inputs.uname, valFn: valUname },
        { obj: inputs.phone, valFn: valPhone },
        { obj: inputs.email, valFn: valEmail },
        { obj: inputs.dob, valFn: valDob },
        { obj: inputs.gender, valFn: valGender },
        { obj: inputs.addr, valFn: valAddr },
        { obj: inputs.city, valFn: valCity },
        { obj: inputs.pincode, valFn: valPincode },
        { obj: inputs.pass, valFn: valPass },
        { obj: inputs.cpass, valFn: valCpass },
        { obj: inputs.sec_q, valFn: valSecQ },
        { obj: inputs.sec_a, valFn: valSecA }
    ];

    // Function to lock all fields coming after the current one
    function lockFromIndex(index) {
        for (let i = index; i < sequence.length; i++) {
            sequence[i].obj.el.disabled = true;
            sequence[i].obj.el.classList.remove('success-border', 'error-border');
            sequence[i].obj.msg.style.display = 'none';
        }
    }

    // Attach listeners to all fields in the sequence
    sequence.forEach((item, index) => {

        // As you type, check if it's correct. If yes, unlock the next field.
        item.obj.el.addEventListener('input', () => {
            if (item.valFn()) {
                if (index + 1 < sequence.length) sequence[index + 1].obj.el.disabled = false;
            } else {
                lockFromIndex(index + 1); // Lock everything after it if they make a mistake
            }
        });

        // For selects and dates, 'change' is better
        item.obj.el.addEventListener('change', () => {
            if (item.valFn()) {
                if (index + 1 < sequence.length) sequence[index + 1].obj.el.disabled = false;
            } else {
                lockFromIndex(index + 1);
            }
        });

        // Press 'Enter' to auto-move to the next unlocked field
        item.obj.el.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (item.valFn() && index + 1 < sequence.length) {
                    sequence[index + 1].obj.el.disabled = false;
                    sequence[index + 1].obj.el.focus(); // Jump to next
                }
            }
        });
    });

    // Special Feature: Prevent text in Phone Number & Auto-Advance at 10 digits
    inputs.phone.el.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length === 10 && valPhone()) {
            inputs.email.el.disabled = false;
            inputs.email.el.focus();
        }
    });

    // Special Feature: Prevent text in Pincode & Auto-Advance at 6 digits
    inputs.pincode.el.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length === 6 && valPincode()) {
            inputs.pass.el.disabled = false;
            inputs.pass.el.focus();
        }
    });

    // --- FINAL CHECK ON SUBMIT ---
    form.addEventListener('submit', function(e) {
        const isValid = 
            valUname() && valPhone() && valEmail() && valDob() && 
            valGender() && valAddr() && valCity() && valPincode() && 
            valPass() && valCpass() && valSecQ() && valSecA();

        if (!isValid) {
            e.preventDefault();
        } else {
            // CRITICAL: Must enable all fields before submit, or PHP won't receive the data!
            sequence.forEach(item => item.obj.el.disabled = false);
        }
    });
</script>

<?php include 'include/footer.php'; ?>