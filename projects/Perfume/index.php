<?php include 'include/header.php'; ?>

<style>
    /* ===========================
       GLOBAL HOMEPAGE STYLES
       =========================== */
    body {
        background: #f7f3f0; /* Soft background to match your theme */
        overflow-x: hidden; /* Prevents horizontal scrollbar during animation */
    }

    .section-title {
        font-family: 'Playfair Display', serif;
        font-size: 2.5rem;
        color: #1C1C1B;
        text-align: center;
        margin-bottom: 40px;
        font-weight: 700;
    }

    /* ===========================
       ANIMATION CLASSES (NEW)
       =========================== */
    .hidden-left {
        opacity: 0;
        transform: translateX(-100px);
        transition: all 0.8s ease-out;
    }

    .hidden-right {
        opacity: 0;
        transform: translateX(100px);
        transition: all 0.8s ease-out;
    }

    .hidden-bottom {
        opacity: 0;
        transform: translateY(50px);
        transition: all 0.8s ease-out;
    }

    /* The class added by JavaScript when element is on screen */
    .show {
        opacity: 1;
        transform: translate(0, 0);
    }

    /* Add slight delays for grid items to make them pop in one by one */
    .delay-1 { transition-delay: 0.1s; }
    .delay-2 { transition-delay: 0.3s; }
    .delay-3 { transition-delay: 0.5s; }

    /* ===========================
       1. HERO SECTION
       =========================== */
    .hero-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 40px 80px;
        min-height: 80vh;
    }

    .hero-text { max-width: 50%; }

    .hero-text h1 {
        font-family: 'Playfair Display', serif;
        font-size: 4rem;
        line-height: 1.1;
        margin-bottom: 25px;
        color: #1C1C1B;
        font-weight: 700;
    }

    .hero-text p {
        font-size: 1.2rem;
        margin-bottom: 35px;
        color: #6A5D52;
        line-height: 1.6;
    }

    .btn-hero {
        background-color: #6A5D52;
        color: #E2E2DE;
        padding: 15px 40px;
        border-radius: 30px;
        font-weight: 600;
        display: inline-block;
        border: none;
        text-decoration: none;
        transition: background-color 0.3s ease, color 0.3s ease; /* Kept hover transition */
    }

    .btn-hero:hover {
        background-color: #1C1C1B;
        color: #E2E2DE;
    }

    .hero-image img {
        max-width: 100%;
        height: auto;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    }

    /* ===========================
       2. FEATURES SECTION
       =========================== */
    .features-section {
        background-color: #ffffff;
        padding: 60px 80px;
        display: flex;
        justify-content: space-around;
        gap: 20px;
        text-align: center;
    }

    .feature-box {
        max-width: 250px;
    }

    .feature-box i {
        font-size: 2.5rem;
        color: #B7AC9B;
        margin-bottom: 15px;
    }

    .feature-box h3 {
        font-size: 1.1rem;
        color: #1C1C1B;
        margin-bottom: 10px;
    }

    .feature-box p {
        font-size: 0.9rem;
        color: #6A5D52;
    }

    /* ===========================
       3. CATEGORIES SECTION
       =========================== */
    .categories-section {
        padding: 80px;
    }

    .category-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 30px;
    }

    .category-card {
        position: relative;
        border-radius: 15px;
        overflow: hidden;
        cursor: pointer;
        height: 350px;
    }

    .category-card img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }

    .category-card:hover img {
        transform: scale(1.05);
    }

    .category-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        background: linear-gradient(to top, rgba(28,28,27,0.8), transparent);
        padding: 30px 20px 20px;
        color: white;
        text-align: center;
    }

    .category-overlay h3 {
        font-family: 'Playfair Display', serif;
        font-size: 1.8rem;
        margin-bottom: 5px;
    }

    /* ===========================
       4. ABOUT SNIPPET SECTION
       =========================== */
    .about-snippet {
        display: flex;
        align-items: center;
        background-color: #E2E2DE;
        padding: 80px;
        gap: 50px;
    }

    .about-snippet-img {
        flex: 1;
    }

    .about-snippet-img img {
        width: 100%;
        border-radius: 15px;
    }

    .about-snippet-text {
        flex: 1;
    }

    .about-snippet-text h2 {
        font-family: 'Playfair Display', serif;
        font-size: 2.5rem;
        color: #1C1C1B;
        margin-bottom: 20px;
    }

    .about-snippet-text p {
        font-size: 1.1rem;
        color: #6A5D52;
        margin-bottom: 30px;
        line-height: 1.7;
    }

    /* ===========================
       RESPONSIVE DESIGN
       =========================== */
    @media (max-width: 992px) {
        .hero-section, .about-snippet { 
            flex-direction: column; 
            text-align: center; 
            padding: 40px 20px; 
            gap: 30px;
        }
        .hero-text { max-width: 100%; }
        .hero-text h1 { font-size: 3rem; }
        .features-section { flex-direction: column; align-items: center; padding: 40px 20px; }
        .category-grid { grid-template-columns: 1fr; }
        .categories-section { padding: 40px 20px; }
    }
</style>

<main class="hero-section">
    <div class="hero-text hidden-left">
        <h1>Discover the <br> Fragrance of <br> Elegance</h1>
        <p>
            Discover the beauty of fragrance with our collection of premium perfumes.
            Immerse yourself in the aroma of luxury and elevate your style.
        </p>
        <a href="product.php" class="btn-hero">Explore Collection</a>
    </div>

    <div class="hero-image hidden-right">
        <img src="img/home.jpg" alt="Perfume Luxury Bottle">
    </div>
</main>

<section class="features-section">
    <div class="feature-box hidden-bottom delay-1">
        <i class="fas fa-shipping-fast"></i>
        <h3>Fast Delivery</h3>
        <p>Reliable and tracked shipping directly to your doorstep.</p>
    </div>
    <div class="feature-box hidden-bottom delay-2">
        <i class="fas fa-gem"></i>
        <h3>Premium Quality</h3>
        <p>Crafted with the finest and most luxurious ingredients.</p>
    </div>
    <div class="feature-box hidden-bottom delay-3">
        <i class="fas fa-shield-alt"></i>
        <h3>Secure Checkout</h3>
        <p>100% safe and secure payments for your peace of mind.</p>
    </div>
</section>

<section class="about-snippet">
    <div class="about-snippet-img hidden-left">
        <img src="img/woman1.JPG" alt="About Perfume Nest" onerror="this.src='https://via.placeholder.com/600x400?text=Our+Story'">
    </div>
    <div class="about-snippet-text hidden-right">
        <h2>The Essence of Elegance</h2>
        <p>
            Perfume Nest was born out of a passion for timeless fragrances that speak to the soul. 
            We strive to craft and curate perfumes that elevate your everyday experience, blending tradition and innovation.
        </p>
        <a href="about.php" class="btn-hero" style="padding: 12px 30px; font-size: 0.9rem;">Read Our Story</a>
    </div>
</section>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Creates an observer that checks when elements enter the screen
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    // Add the 'show' class to trigger the CSS animation
                    entry.target.classList.add('show');
                    // Optional: Stop observing once it has animated in
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.15 // Triggers when 15% of the element is visible
        });

        // Select all elements with the hidden classes and observe them
        const hiddenElements = document.querySelectorAll('.hidden-left, .hidden-right, .hidden-bottom');
        hiddenElements.forEach((el) => observer.observe(el));
    });
</script>

<?php include 'include/footer.php'; ?>