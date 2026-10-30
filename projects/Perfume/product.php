<?php
include 'include/connection.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- GUEST LOGIN CHECK ---
$is_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

// ==========================================
// LOGIC FOR LIST VIEW
// ==========================================
function getProducts($conn, $gender)
{
    // JOIN with the sellers table to check if their status is 'approved'
    $sql = "SELECT p.* FROM product p 
            INNER JOIN sellers s ON p.seller_id = s.id 
            WHERE p.gender = ? AND s.status = 'approved' 
            ORDER BY p.id DESC";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param("s", $gender);
    $stmt->execute();
    $res = $stmt->get_result();
    $products = array();

    while ($row = $res->fetch_assoc()) {
        $products[] = $row;
    }
    $stmt->close();
    return $products;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Perfume Collection - Perfume Nest</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

    <style>
        /* === GLOBAL COLOR VARIABLES === */
        :root {
            --bg-page: #E2E2DE;
            --bg-card: #F5F1E9;
            --text-dark: #5E5343;
            --text-light: #6A5D52;
            --primary-gold: #A1937A;
            --white: #ffffff;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-page);
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            color: var(--text-dark);
        }

        .selector-container {
            text-align: center;
            padding: 60px 20px;
            animation: fadeIn 1s ease;
        }

        .circles-wrapper {
            display: flex;
            justify-content: center;
            gap: 50px;
            flex-wrap: wrap;
        }

        .circle-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .circle-item:hover {
            transform: translateY(-10px);
        }

        .circle-img {
            width: 180px;
            height: 180px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            transition: 0.3s;
        }

        .circle-item:hover .circle-img {
            border-color: var(--text-dark);
            box-shadow: 0 15px 30px rgba(94, 83, 67, 0.2);
        }

        .circle-label {
            margin-top: 15px;
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            letter-spacing: 1px;
        }

        .collection {
            display: none;
            padding: 40px 20px;
            max-width: 1200px;
            margin: 0 auto;
            flex-direction: column;
            align-items: center;
            animation: slideUp 0.5s ease-out;
            width: 100%;
            box-sizing: border-box;
        }

        .collection-title {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--primary-gold);
            padding-bottom: 10px;
            display: inline-block;
        }

        .grid-layout {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            width: 100%;
        }

        .card {
            background-color: var(--bg-card);
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border-color: var(--primary-gold);
        }

        .img-box {
            height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            border-radius: 8px;
            margin-bottom: 15px;
            padding: 10px;
        }

        .img-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .card-link {
            text-decoration: none;
            color: inherit;
        }

        .card h3 {
            font-family: 'Playfair Display', serif;
            font-size: 18px;
            margin: 10px 0 5px;
            color: var(--text-dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .price {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary-gold);
            margin-bottom: 10px;
        }

        .desc {
            font-size: 13px;
            color: var(--text-light);
            line-height: 1.4;
            height: 40px;
            overflow: hidden;
            margin-bottom: 15px;
        }

        .card-actions {
            margin-top: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .action-row {
            display: flex;
            gap: 10px;
        }

        .btn-action {
            flex: 1;
            padding: 10px 0;
            text-align: center;
            text-decoration: none;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            transition: 0.3s;
            border: 2px solid var(--text-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-cart {
            background-color: var(--text-dark);
            color: #fff;
        }

        .btn-cart:hover {
            background-color: var(--primary-gold);
            border-color: var(--primary-gold);
        }

        .btn-buy {
            background-color: transparent;
            color: var(--text-dark);
        }

        .btn-buy:hover {
            background-color: var(--text-dark);
            color: #fff;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1024px) {
            .grid-layout {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .grid-layout {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .circles-wrapper {
                flex-direction: column;
                align-items: center;
                gap: 30px;
            }

            .collection {
                padding: 20px;
            }

            .grid-layout {
                grid-template-columns: 1fr;
            }

            .action-row {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

    <?php include 'include/header.php'; ?>

    <div class="selector-container">
        <div class="circles-wrapper">
            <div class="circle-item" onclick="showCollection('men')">
                <img src="img/bb.JPG" alt="Men's Collection" class="circle-img">
                <div class="circle-label">For Him</div>
            </div>
            <div class="circle-item" onclick="showCollection('women')">
                <img src="img/aa.JPG" alt="Women's Collection" class="circle-img">
                <div class="circle-label">For Her</div>
            </div>
        </div>
    </div>

    <div id="men" class="collection">
        <h2 class="collection-title">Men's Collection</h2>
        <div class="grid-layout">
            <?php
            $menProducts = getProducts($conn, 'men');
            if (count($menProducts) > 0) {
                foreach ($menProducts as $prod) {
                    $id = $prod['id'];
                    $name = htmlspecialchars($prod['name']);
                    $price = number_format($prod['price']);
                    $desc = htmlspecialchars(mb_strimwidth($prod['description'], 0, 60, "..."));
                    $img = htmlspecialchars($prod['image']);

                    $cart_href = $is_logged_in ? "user/products.php?action=add&id=$id" : "login.php";
                    $buy_href  = $is_logged_in ? "user/by.php?action=buy&id=$id" : "login.php";

                    echo "
                    <div class='card'>
                        <a href='product_details.php?id=$id' class='card-link'>
                            <div class='img-box'><img src='./$img' alt='$name'></div>
                            <h3>$name</h3>
                            <div class='price'>&#8377;$price</div>
                            <div class='desc'>$desc</div>
                        </a>
                        <div class='card-actions'>
                            <div class='action-row'>
                                <a href='$cart_href' class='btn-action btn-cart'>
                                    <i class='fas fa-shopping-bag'></i> Cart
                                </a>
                                <a href='$buy_href' class='btn-action btn-buy'>
                                    Buy Now
                                </a>
                            </div>
                        </div>
                    </div>";
                }
            } else {
                echo "<p style='text-align:center; color:#777; width:100%; grid-column: 1 / -1;'>No products found in this category.</p>";
            }
            ?>
        </div>
    </div>

    <div id="women" class="collection">
        <h2 class="collection-title">Women's Collection</h2>
        <div class="grid-layout">
            <?php
            $womenProducts = getProducts($conn, 'women');
            if (count($womenProducts) > 0) {
                foreach ($womenProducts as $prod) {
                    $id = $prod['id'];
                    $name = htmlspecialchars($prod['name']);
                    $price = number_format($prod['price']);
                    $desc = htmlspecialchars(mb_strimwidth($prod['description'], 0, 60, "..."));
                    $img = htmlspecialchars($prod['image']);

                    $cart_href = $is_logged_in ? "user/products.php?action=add&id=$id" : "login.php";
                    $buy_href  = $is_logged_in ? "user/by.php?action=buy&id=$id" : "login.php";

                    echo "
                    <div class='card'>
                        <a href='product_details.php?id=$id' class='card-link'>
                            <div class='img-box'><img src='./$img' alt='$name'></div>
                            <h3>$name</h3>
                            <div class='price'>&#8377;$price</div>
                            <div class='desc'>$desc</div>
                        </a>
                        <div class='card-actions'>
                            <div class='action-row'>
                                <a href='$cart_href' class='btn-action btn-cart'>
                                    <i class='fas fa-shopping-bag'></i> Cart
                                </a>
                                <a href='$buy_href' class='btn-action btn-buy'>
                                    Buy Now
                                </a>
                            </div>
                        </div>
                    </div>";
                }
            } else {
                echo "<p style='text-align:center; color:#777; width:100%; grid-column: 1 / -1;'>No products found in this category.</p>";
            }
            ?>
        </div>
    </div>

    <script>
        function showCollection(type) {
            document.getElementById('men').style.display = 'none';
            document.getElementById('women').style.display = 'none';
            const selected = document.getElementById(type);
            selected.style.display = 'flex';
            selected.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    </script>

    <?php include 'include/footer.php'; ?>
</body>

</html>