<html>

<head>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>About Us - Perfume Paradise</title>
  <link rel="apple-touch-icon" sizes="180x180" href="img/favicons/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="img/favicons/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="img/favicons/favicon-16x16.png">
  <link rel="shortcut icon" type="image/x-icon" href="img/favicons/favicon.ico">
  <link rel="manifest" href="img/favicons/manifest.json">
  <meta name="msapplication-TileImage" content="img/favicons/mstile-150x150.png">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins&display=swap');

    :root {
      --onyx: #1C1C1B;
      --walnut: #6A5D52;
      --greige: #B7AC9B;
      --ash: #979086;
      --stucco: #E2E2DE;
      --light-shadow: rgba(0, 0, 0, 0.08);
      --gradient-start: #f7f2ec;
      --gradient-end: #e3dfd8;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
      color: var(--onyx);
      min-height: 100vh;
      overflow-x: hidden;
      line-height: 1.7;
    }

    /* Hero Section with unique curved shape */
    .about-hero {
      position: relative;
      height: 420px;
      background: url('images/about-banner.png') center/cover no-repeat;
      display: flex;
      justify-content: center;
      align-items: center;
      overflow: hidden;
      margin-bottom: 80px;
    }

    .about-hero::before {
      content: '';
      position: absolute;
      bottom: -40px;
      left: 0;
      width: 100%;
      height: 160px;
      background: var(--greige);
      border-radius: 70% 70% 0 0 / 100% 100% 0 0;
      transform: scaleX(1.2);
      z-index: 1;
      filter: drop-shadow(0 6px 10px var(--light-shadow));
    }

    .about-hero h2 {
      position: relative;
      z-index: 2;
      font-family: 'Playfair Display', serif;
      font-size: 3.2rem;
      color: white;
      text-shadow: 0 3px 10px rgba(0, 0, 0, 0.6);
      max-width: 90%;
      text-align: center;
      padding: 20px 30px;
      background: rgba(28, 28, 27, 0.5);
      border-radius: 14px;
      font-style: italic;
    }

    /* Content Wrapper */
    .content-section {
      max-width: 960px;
      margin: 0 auto 100px;
      padding: 0 20px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 50px;
    }

    @media (max-width: 820px) {
      .content-section {
        grid-template-columns: 1fr;
        gap: 70px;
      }
    }

    /* Text Section */
    .section-text {
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .section-text h3 {
      font-family: 'Playfair Display', serif;
      font-weight: 700;
      font-size: 2.2rem;
      color: var(--walnut);
      margin-bottom: 18px;
      letter-spacing: 1px;
    }

    .section-text p {
      font-size: 1.1rem;
      color: #444;
      line-height: 1.8;
      letter-spacing: 0.02em;
    }

    /* Image Gallery Section */
    .image-gallery {
      position: relative;
      display: flex;
      flex-wrap: nowrap;
      gap: 25px;
      overflow-x: auto;
      padding-bottom: 15px;
      scrollbar-width: thin;


      scrollbar-color: var(--walnut) transparent;
    }

    /* For WebKit Browsers */
    .image-gallery::-webkit-scrollbar {
      height: 8px;
    }

    .image-gallery::-webkit-scrollbar-thumb {
      background-color: var(--walnut);
      border-radius: 10px;
    }

    .image-gallery img {
      flex: 0 0 auto;
      width: 280px;
      border-radius: 20px;
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.12);
      object-fit: cover;
      transition: transform 0.35s ease;
      cursor: pointer;
      user-select: none;
    }

    .image-gallery img:hover {
      transform: scale(1.07) rotate(1deg);
      box-shadow: 0 16px 30px rgba(0, 0, 0, 0.22);
      z-index: 10;
    }

    footer {
      text-align: center;
      background-color: var(--greige);
      color: var(--onyx);
      padding: 25px 20px;
      font-weight: 600;
      letter-spacing: 0.05em;
      font-size: 0.9rem;
      user-select: none;
      box-shadow: 0 -4px 15px var(--light-shadow);
    }
  </style>
</head>

<body>

  <?php include 'include/header.php'; ?>

  <section class="about-hero">
    <h2>The Essence of Elegance</h2>
  </section>

  <section class="content-section">
    <div class="section-text">
      <h3>Our Story</h3>
      <p>
        Perfume Nest was born out of a passion for timeless fragrances that speak to the soul.
        What started as a small boutique in 2010 has now become a sanctuary for lovers of luxury scents.
      </p>

      <h3 style="margin-top: 4rem;">Our Mission</h3>
      <p>
        We strive to craft and curate perfumes that elevate your everyday experience.
        Every fragrance in our collection is chosen with care, blending tradition and innovation,
        to bring you scents that are captivating and enduring.
      </p>

      <h3 style="margin-top: 4rem;">Ingredients</h3>
      <p>
        From rare oud and sandalwood to delicate rose petals and citrus zest,
        we source only the finest ingredients from around the world.
        Our perfumers are artists, blending these notes into harmonious, unforgettable compositions.
      </p>
    </div>

    <div class="image-gallery" aria-label="Perfume images gallery">
      <img src="img/man4.JPG" alt="Perfume Branding" draggable="false" />
      <img src="img/woman4.JPG" alt="Natural Ingredients" draggable="false" />
      <img src="img/woman1.JPG" alt="Luxury Packaging" draggable="false" />
    </div>
  </section>

  <?php include 'include/footer.php'; ?>
</body>

</html>