<?php
include '../include/connection.php';
session_start();

// 1. Security Check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id == 0 && isset($_SESSION['username'])) {
    $u_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $u_stmt->bind_param("s", $_SESSION['username']);
    $u_stmt->execute();
    $u_stmt->bind_result($user_id);
    $u_stmt->fetch();
    $u_stmt->close();
}

// 2. HANDLE ACTIONS (Create Collection, Delete Item)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_collection'])) {
    $new_col = trim($_POST['collection_name']);
    if (!empty($new_col)) {
        // Create an empty collection for this user (product_id is NULL)
        $ins = $conn->prepare("INSERT INTO wishlist (user_id, product_id, collection_name) VALUES (?, NULL, ?)");
        $ins->bind_param("is", $user_id, $new_col);
        $ins->execute();
    }
    header("Location: wishlist.php");
    exit;
}

if (isset($_GET['action'])) {
    if ($_GET['action'] === 'remove' && isset($_GET['id'])) {
        $wish_id = intval($_GET['id']);
        $del = $conn->prepare("DELETE FROM wishlist WHERE id = ? AND user_id = ?");
        $del->bind_param("ii", $wish_id, $user_id);
        $del->execute();

        // Redirect back to the specific collection we were viewing
        $col = isset($_GET['collection']) ? '&collection=' . urlencode($_GET['collection']) : '';
        header("Location: wishlist.php?" . ltrim($col, '&'));
        exit;
    }

    if ($_GET['action'] === 'delete_collection' && isset($_GET['name'])) {
        $del_name = $_GET['name'];
        if ($del_name !== 'Favourites') {
            $del = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND collection_name = ?");
            $del->bind_param("is", $user_id, $del_name);
            $del->execute();
        }
        header("Location: wishlist.php");
        exit;
    }
}

// 3. DETERMINE VIEW MODE (Viewing all Collections vs Viewing inside a Collection)
$active_collection = isset($_GET['collection']) ? $_GET['collection'] : null;

include 'include/header.php';
?>

<main style="flex: 1; padding: 40px; background-color: #fff; overflow-y: auto;">
    <div style="max-width: 1100px; margin: 0 auto;">

        <?php if ($active_collection): ?>
            <a href="wishlist.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Collections
            </a>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin: 0;">
                    <?= htmlspecialchars($active_collection); ?>
                    <?= ($active_collection === 'Favourites') ? '<i class="fas fa-heart" style="color: #e74c3c;"></i>' : ''; ?>
                </h1>

                <?php if ($active_collection !== 'Favourites'): ?>
                    <a href="wishlist.php?action=delete_collection&name=<?= urlencode($active_collection); ?>" class="btn-delete-col" onclick="return confirm('Delete this entire collection?');">
                        <i class="fas fa-trash"></i> Delete Collection
                    </a>
                <?php endif; ?>
            </div>

            <?php
            // Fetch items ONLY for this specific collection
            $wishlist_items = [];
            $sql = "SELECT w.id as wish_id, p.* FROM wishlist w 
                    JOIN product p ON w.product_id = p.id 
                    WHERE w.user_id = ? AND IFNULL(w.collection_name, 'Favourites') = ? 
                    ORDER BY w.id DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $user_id, $active_collection);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $wishlist_items[] = $row;
            }
            $stmt->close();
            ?>

            <?php if (count($wishlist_items) > 0): ?>
                <div class="wishlist-grid">
                    <?php foreach ($wishlist_items as $item): ?>
                        <div class="wishlist-card">
                            <div class="wish-img">
                                <img src="../<?= htmlspecialchars($item['image']); ?>" alt="">
                                <a href="wishlist.php?action=remove&id=<?= $item['wish_id']; ?>&collection=<?= urlencode($active_collection); ?>" class="remove-btn" title="Remove">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                            <div class="wish-info">
                                <h3 class="p-name"><?= htmlspecialchars($item['name']); ?></h3>
                                <p class="p-category"><?= ucfirst($item['gender']); ?>'s Collection</p>
                                <div class="p-price">&#8377;<?= number_format($item['price'], 2); ?></div>

                                <div class="wish-actions">
                                    <a href="product_details.php?id=<?= $item['id']; ?>" class="btn-view">View Details</a>
                                    <a href="product_details.php?id=<?= $item['id']; ?>&action=add" class="btn-cart">
                                        <i class="fas fa-shopping-bag"></i> Cart
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-wishlist">
                    <i class="fas fa-box-open"></i>
                    <h2>This collection is empty</h2>
                    <p>Browse our products and add them to this collection.</p>
                    <a href="product.php" class="btn-shop">Explore Perfumes</a>
                </div>
            <?php endif; ?>


        <?php else: ?>
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h1 style="font-family: 'Playfair Display', serif; color: #5E5343; margin-bottom: 5px;">My Collections</h1>
                    <p style="color: #888; margin: 0;">Organize and save your favorite fragrances.</p>
                </div>
                <button class="btn-create-col" onclick="document.getElementById('createColModal').style.display='flex'">
                    <i class="fas fa-plus"></i> New Collection
                </button>
            </div>

            <?php
            // Group user's wishlist into collections using PHP
            $collections = ['Favourites' => ['images' => [], 'count' => 0]]; // Favourites always exists

            $sql = "SELECT IFNULL(w.collection_name, 'Favourites') as c_name, p.image, w.product_id 
                    FROM wishlist w 
                    LEFT JOIN product p ON w.product_id = p.id 
                    WHERE w.user_id = ? ORDER BY w.id DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result();

            while ($row = $res->fetch_assoc()) {
                $cname = $row['c_name'];
                if (!isset($collections[$cname])) {
                    $collections[$cname] = ['images' => [], 'count' => 0];
                }
                if ($row['product_id'] !== null) {
                    $collections[$cname]['count']++;
                    // Only store up to 4 images for the collage preview
                    if ($row['image'] && count($collections[$cname]['images']) < 4) {
                        $collections[$cname]['images'][] = $row['image'];
                    }
                }
            }
            $stmt->close();
            ?>

            <div class="collections-grid">
                <?php foreach ($collections as $c_name => $c_data): ?>
                    <a href="wishlist.php?collection=<?= urlencode($c_name); ?>" class="collection-box">

                        <div class="collage-container">
                            <?php for ($i = 0; $i < 4; $i++): ?>
                                <div class="collage-slot">
                                    <?php if (isset($c_data['images'][$i])): ?>
                                        <img src="../<?= htmlspecialchars($c_data['images'][$i]); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-gem" style="color: #e2e2e2; font-size: 24px; opacity: 0.5;"></i>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <div class="collection-info">
                            <h3>
                                <?= htmlspecialchars($c_name); ?>
                                <?= ($c_name === 'Favourites') ? '<i class="fas fa-heart" style="color: #e74c3c;"></i>' : ''; ?>
                            </h3>
                            <p><?= $c_data['count']; ?> items saved</p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>
</main>

<div id="createColModal" class="modal-overlay" style="display: none;">
    <div class="modal-card">
        <span class="close-modal" onclick="document.getElementById('createColModal').style.display='none'">&times;</span>
        <h2 style="font-family: 'Playfair Display', serif; color: #5E5343; margin-bottom: 20px;">Create Collection</h2>

        <form method="POST">
            <div class="form-group">
                <label>Collection Name</label>
                <input type="text" name="collection_name" placeholder="e.g. Summer Vibes, Gifts..." required>
            </div>
            <button type="submit" name="create_collection" class="btn-submit-modal">Create Board</button>
        </form>
    </div>
</div>

<style>
    /* ==================================================== */
    /* COLLECTIONS GRID CSS (The 4-Image Collage View)      */
    /* ==================================================== */
    .collections-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 30px;
    }

    .collection-box {
        background: #fff;
        border-radius: 15px;
        border: 1px solid #e0e0e0;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03);
        overflow: hidden;
        text-decoration: none;
        transition: 0.3s;
        display: block;
    }

    .collection-box:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        border-color: #B2A48C;
    }

    /* 2x2 Collage Grid inside the card */
    .collage-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        grid-template-rows: 1fr 1fr;
        gap: 2px;
        height: 260px;
        background: #eee;
    }

    .collage-slot {
        background: #F5F1E9;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .collage-slot img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: 0.3s;
    }

    .collection-box:hover .collage-slot img {
        transform: scale(1.05);
    }

    .collection-info {
        padding: 20px;
        text-align: center;
        background: #fff;
    }

    .collection-info h3 {
        font-family: 'Playfair Display', serif;
        font-size: 18px;
        color: #5E5343;
        margin: 0 0 5px 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .collection-info p {
        margin: 0;
        font-size: 13px;
        color: #888;
    }

    .btn-create-col {
        background: #5E5343;
        color: #fff;
        border: none;
        padding: 10px 20px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: 0.3s;
    }

    .btn-create-col:hover {
        background: #A1937A;
    }

    /* ==================================================== */
    /* INNER WISHLIST GRID CSS (The Product View)           */
    /* ==================================================== */
    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #5E5343;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 25px;
        transition: 0.3s;
    }

    .back-link:hover {
        color: #A1937A;
    }

    .btn-delete-col {
        background: #ffebee;
        color: #c0392b;
        border: 1px solid #ffcdd2;
        padding: 8px 15px;
        border-radius: 50px;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .btn-delete-col:hover {
        background: #c0392b;
        color: #fff;
    }

    .wishlist-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 30px;
    }

    .wishlist-card {
        background: #F5F1E9;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #e0e0e0;
        transition: 0.3s;
    }

    .wishlist-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
        border-color: #B2A48C;
    }

    .wish-img {
        position: relative;
        height: 250px;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        border-bottom: 1px solid #eee;
    }

    .wish-img img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .remove-btn {
        position: absolute;
        top: 15px;
        right: 15px;
        width: 30px;
        height: 30px;
        background: rgba(255, 255, 255, 0.9);
        color: #c0392b;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: 0.3s;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .remove-btn:hover {
        background: #c0392b;
        color: #fff;
    }

    .wish-info {
        padding: 20px;
    }

    .p-name {
        font-family: 'Playfair Display', serif;
        font-size: 16px;
        color: #5E5343;
        margin-bottom: 5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .p-category {
        font-size: 12px;
        color: #888;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 15px;
    }

    .p-price {
        font-size: 18px;
        font-weight: 700;
        color: #A1937A;
        margin-bottom: 20px;
    }

    .wish-actions {
        display: flex;
        gap: 10px;
    }

    .btn-view,
    .btn-cart {
        flex: 1;
        padding: 10px;
        border-radius: 50px;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
        text-align: center;
        transition: 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }

    .btn-view {
        border: 1px solid #5E5343;
        color: #5E5343;
    }

    .btn-view:hover {
        background: #5E5343;
        color: #fff;
    }

    .btn-cart {
        background: #5E5343;
        color: #fff;
        border: 1px solid #5E5343;
    }

    .btn-cart:hover {
        background: #A1937A;
        border-color: #A1937A;
    }

    .empty-wishlist {
        text-align: center;
        padding: 60px 20px;
        background: #F5F1E9;
        border-radius: 12px;
        border: 2px dashed #B2A48C;
        margin-top: 20px;
    }

    .empty-wishlist i {
        font-size: 50px;
        color: #B2A48C;
        margin-bottom: 20px;
        opacity: 0.5;
    }

    .empty-wishlist h2 {
        font-family: 'Playfair Display', serif;
        color: #5E5343;
        margin-bottom: 10px;
    }

    .btn-shop {
        display: inline-block;
        padding: 12px 30px;
        background: #5E5343;
        color: #fff;
        text-decoration: none;
        border-radius: 50px;
        font-weight: 600;
        margin-top: 15px;
        transition: 0.3s;
    }

    .btn-shop:hover {
        background: #A1937A;
    }

    /* Modal */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(3px);
    }

    .modal-card {
        background: #fff;
        padding: 30px;
        border-radius: 12px;
        width: 90%;
        max-width: 400px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        position: relative;
        animation: popIn 0.3s;
        border: 2px solid #B2A48C;
    }

    .close-modal {
        position: absolute;
        top: 20px;
        right: 20px;
        font-size: 24px;
        cursor: pointer;
        color: #999;
    }

    .close-modal:hover {
        color: #c0392b;
    }

    .form-group label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #5E5343;
        margin-bottom: 8px;
        text-transform: uppercase;
    }

    .form-group input {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 14px;
        font-family: 'Poppins';
        outline: none;
        box-sizing: border-box;
        margin-bottom: 20px;
    }

    .form-group input:focus {
        border-color: #5E5343;
    }

    .btn-submit-modal {
        width: 100%;
        background: #5E5343;
        color: #fff;
        border: none;
        padding: 12px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 15px;
        cursor: pointer;
        transition: 0.3s;
    }

    .btn-submit-modal:hover {
        background: #A1937A;
    }

    @keyframes popIn {
        from {
            transform: scale(0.9);
            opacity: 0;
        }

        to {
            transform: scale(1);
            opacity: 1;
        }
    }
</style>

<?php include 'include/footer.php'; ?>