<?php
session_start();
if (!isset($_SESSION['user_logged_in'])) {
    header('Location: ../login.php');
    exit;
}
include 'include/header.php';
?>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">

    <div style="max-width: 900px; margin: 0 auto;">
        <a href="dashboard.php" style="color: #5E5343; text-decoration: none; font-weight: 600; font-size: 14px;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <div style="text-align: center; margin-top: 30px; margin-bottom: 50px;">
            <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; font-size: 2.8rem; margin-bottom: 15px;">Your Shopping Guide</h1>
            <p style="color: #888; font-size: 1.1rem;">Everything you need to know about shopping at Perfume Nest.</p>
        </div>

        <section class="guide-section">
            <div class="guide-text">
                <span class="step-badge">01</span>
                <h2>Find Your Scent</h2>
                <p>Browse our extensive collection curated for both Men and Women. Use the filters on our <strong>Discover</strong> page to sort by price or category. Once you find a fragrance you love, click 'View Details' to read the notes and see high-resolution images.</p>
                <ul>
                    <li><i class="fas fa-check"></i> Check Top, Heart, and Base notes.</li>
                    <li><i class="fas fa-check"></i> Read real user reviews.</li>
                    <li><i class="fas fa-check"></i> Zoom in on product packaging.</li>
                </ul>
            </div>
            <div class="guide-image">
                <img src="https://images.unsplash.com/photo-1541643600914-78b084683601?auto=format&fit=crop&q=80&w=400" alt="Perfume bottles">
            </div>
        </section>

        <section class="guide-section reversed">
            <div class="guide-text">
                <span class="step-badge">02</span>
                <h2>Safe & Secure Payments</h2>
                <p>We prioritize your financial safety. Perfume Nest uses 256-bit encryption to protect your transaction details. You can choose from multiple payment methods during checkout.</p>
                <div class="payment-icons">
                    <i class="fab fa-cc-visa" title="Visa"></i>
                    <i class="fab fa-cc-mastercard" title="Mastercard"></i>
                    <i class="fas fa-mobile-alt" title="UPI"></i>
                    <i class="fas fa-university" title="Net Banking"></i>
                </div>
                <p><small><i class="fas fa-shield-alt"></i> We never store your full card details or CVV on our servers.</small></p>
            </div>
            <div class="guide-image">
                <img src="https://images.unsplash.com/photo-1563013544-824ae1b704d3?auto=format&fit=crop&q=80&w=400" alt="Secure payment">
            </div>
        </section>

        <section class="guide-section">
            <div class="guide-text">
                <span class="step-badge">03</span>
                <h2>Tracking Your Luxury</h2>
                <p>Once your order is confirmed, you can track its journey in real-time from your <strong>Orders</strong> page. Our logistics partners ensure that your delicate glass bottles are handled with extreme care.</p>
                <div class="status-example">
                    <div class="dot active">Placed</div>
                    <div class="line active"></div>
                    <div class="dot active">Shipped</div>
                    <div class="line"></div>
                    <div class="dot">Delivered</div>
                </div>
            </div>
            <div class="guide-image">
                <img src="https://images.pexels.com/photos/4393668/pexels-photo-4393668.jpeg?auto=compress&cs=tinysrgb&w=400" alt="Package delivery">
            </div>
        </section>

        <div class="tips-box">
            <h3><i class="fas fa-lightbulb"></i> Pro Tip: Perfume Longevity</h3>
            <p>To make your fragrance last longer, store the bottle in a cool, dark place away from direct sunlight and humidity. Apply to pulse points like wrists and neck immediately after a shower.</p>
        </div>
    </div>

</main>

<style>
    .guide-section {
        display: flex;
        align-items: center;
        gap: 50px;
        margin-bottom: 80px;
    }

    .guide-section.reversed {
        flex-direction: row-reverse;
    }

    .guide-text {
        flex: 1.2;
    }

    .guide-text h2 {
        font-family: 'Playfair Display', serif;
        color: #5E5343;
        font-size: 24px;
        margin-bottom: 15px;
    }

    .guide-text p {
        color: #555;
        line-height: 1.7;
        font-size: 15px;
        margin-bottom: 15px;
    }

    .guide-text ul {
        list-style: none;
        padding: 0;
    }

    .guide-text li {
        color: #777;
        font-size: 14px;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .guide-text li i {
        color: #A1937A;
        font-size: 12px;
    }

    .step-badge {
        background: #F5F1E9;
        color: #A1937A;
        padding: 5px 15px;
        border-radius: 4px;
        font-weight: bold;
        font-size: 12px;
        margin-bottom: 10px;
        display: inline-block;
        border-left: 3px solid #A1937A;
    }

    .guide-image {
        flex: 0.8;
    }

    .guide-image img {
        width: 100%;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border: 1px solid #eee;
    }

    .payment-icons {
        font-size: 30px;
        display: flex;
        gap: 20px;
        color: #B2A48C;
        margin: 15px 0;
    }

    .status-example {
        display: flex;
        align-items: center;
        gap: 5px;
        margin-top: 15px;
    }

    .status-example .dot {
        padding: 4px 10px;
        background: #eee;
        border-radius: 20px;
        font-size: 10px;
        color: #999;
    }

    .status-example .dot.active {
        background: #5E5343;
        color: #fff;
    }

    .status-example .line {
        width: 30px;
        height: 2px;
        background: #eee;
    }

    .status-example .line.active {
        background: #5E5343;
    }

    .tips-box {
        background: #F5F1E9;
        padding: 30px;
        border-radius: 12px;
        border-left: 5px solid #5E5343;
        margin-top: 50px;
    }

    .tips-box h3 {
        color: #5E5343;
        margin-bottom: 10px;
    }

    .tips-box p {
        color: #666;
        font-size: 14px;
        line-height: 1.6;
        margin: 0;
    }

    @media (max-width: 768px) {

        .guide-section,
        .guide-section.reversed {
            flex-direction: column;
            gap: 25px;
        }

        .guide-image {
            width: 100%;
        }
    }
</style>

<?php include 'include/footer.php'; ?>