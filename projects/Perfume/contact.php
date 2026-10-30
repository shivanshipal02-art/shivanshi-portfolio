<?php
// Ensure session/connection if needed by header
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// include 'include/connection.php'; // Uncomment if you need DB connection

$messageSent = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $name = htmlspecialchars($_POST["name"]);
  $email = htmlspecialchars($_POST["email"]);
  $subject = htmlspecialchars($_POST["subject"]);
  $message = htmlspecialchars($_POST["message"]);

  // TODO: Store in DB or send email
  $messageSent = true;
}

include 'include/header.php'; 
?>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">

  <div class="contact-container">
    
    <div class="contact-header">
      <h1>Get in Touch</h1>
      <p>We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
    </div>

    <?php if ($messageSent): ?>
      <div class="success-box">
        <i class="fas fa-check-circle"></i> 
        <h3>Message Sent!</h3>
        <p>Thank you for contacting us. We will get back to you shortly.</p>
      </div>
    <?php else: ?>
      
      <div class="form-card">
        <form method="POST" action="">
          
          <div class="form-group">
            <label for="name">Your Name</label>
            <div class="input-wrapper">
              <i class="fas fa-user"></i>
              <input type="text" id="name" name="name" placeholder="Enter Your Name" required>
            </div>
          </div>

          <div class="form-group">
            <label for="email">Your Email</label>
            <div class="input-wrapper">
              <i class="fas fa-envelope"></i>
              <input type="email" id="email" name="email" placeholder="Enter Your Email" required>
            </div>
          </div>

          <div class="form-group">
            <label for="subject">Subject</label>
            <div class="input-wrapper">
              <i class="fas fa-tag"></i>
              <input type="text" id="subject" name="subject" placeholder="General Inquiry" required>
            </div>
          </div>

          <div class="form-group">
            <label for="message">Your Message</label>
            <div class="input-wrapper textarea-wrapper">
              <i class="fas fa-comment-alt"></i>
              <textarea id="message" name="message" placeholder="Write your message here..." required></textarea>
            </div>
          </div>

          <div style="text-align: center; margin-top: 30px;">
            <button type="submit" class="btn-send">
              Send Message <i class="fas fa-paper-plane"></i>
            </button>
          </div>

        </form>
      </div>

    <?php endif; ?>
  </div>

</main>

<style>
    /* === OLD DESIGN VARIABLES === */
    /* Handled via inline styles mostly, but using specific classes here */

    .contact-container {
        max-width: 700px;
        margin: 0 auto;
    }

    .contact-header {
        text-align: center;
        margin-bottom: 35px;
    }
    .contact-header h1 {
        font-family: 'Playfair Display', serif;
        font-size: 32px;
        color: #5E5343; /* Dark Brown */
        margin-bottom: 10px;
    }
    .contact-header p {
        color: #999;
        font-size: 15px;
    }

    /* Form Card */
    .form-card {
        background: #F5F1E9; /* Cream Background */
        padding: 40px;
        border-radius: 12px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.04);
        border: 1px solid #e0e0e0;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #5E5343;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Input Wrapper for Icons */
    .input-wrapper {
        position: relative;
    }
    
    .input-wrapper i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #B2A48C; /* Beige Icon */
        font-size: 16px;
    }
    .textarea-wrapper i {
        top: 20px;
        transform: none;
    }

    /* Inputs */
    input, textarea {
        width: 100%;
        box-sizing: border-box; /* Fixes layout squeezing */
        padding: 12px 15px 12px 45px; /* Padding for Icon */
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 14px;
        background-color: #fff;
        font-family: 'Poppins', sans-serif;
        outline: none;
        transition: 0.3s;
        color: #333;
    }

    textarea {
        resize: vertical;
        height: 120px;
    }

    input:focus, textarea:focus {
        border-color: #5E5343; /* Dark Brown Focus */
        box-shadow: 0 0 5px rgba(94, 83, 67, 0.1);
    }

    /* Button */
    .btn-send {
        background-color: #5E5343; /* Dark Brown */
        color: white;
        border: none;
        padding: 14px 40px;
        border-radius: 50px;
        cursor: pointer;
        font-weight: 600;
        font-size: 15px;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .btn-send:hover {
        background-color: #A1937A; /* Gold/Beige Hover */
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }

    /* Success Message */
    .success-box {
        background: #F5F1E9;
        border: 2px solid #B2A48C;
        padding: 40px;
        border-radius: 12px;
        text-align: center;
        color: #5E5343;
    }
    .success-box i {
        font-size: 50px;
        color: #27ae60;
        margin-bottom: 15px;
    }
    .success-box h3 {
        font-family: 'Playfair Display', serif;
        font-size: 24px;
        margin-bottom: 10px;
    }

    @media (max-width: 600px) {
        .form-card { padding: 25px; }
        .btn-send { width: 100%; justify-content: center; }
    }
</style>

<?php include 'include/footer.php'; ?>