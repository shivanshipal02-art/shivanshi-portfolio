<?php
include '../include/connection.php';
session_start();

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {

    // 0. Fetch seller_id and image path so we can clean up files and redirect properly
    $seller_id = 0;
    $imagePath = "";
    
    $sel_stmt = $conn->prepare("SELECT seller_id, image FROM product WHERE id=?");
    $sel_stmt->bind_param('i', $id);
    $sel_stmt->execute();
    $sel_stmt->bind_result($seller_id, $imagePath);
    $sel_stmt->fetch();
    $sel_stmt->close();

    // 1. Delete associated orders first (Database Integrity)
    $sql_orders = "DELETE FROM orders WHERE product_id=?";
    $stmt = $conn->prepare($sql_orders);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    // 2. Delete associated cart entries
    $sql_cart = "DELETE FROM cart WHERE product_id=?";
    $stmt = $conn->prepare($sql_cart);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    // 3. Delete associated reviews
    $sql_reviews = "DELETE FROM reviews WHERE product_id=?";
    $stmt = $conn->prepare($sql_reviews);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    // 4. Finally, delete the product itself
    $sql = "DELETE FROM product WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        // 5. Delete the physical image file from the server to save space
        if (!empty($imagePath) && file_exists("../" . $imagePath)) {
            unlink("../" . $imagePath);
        }

        // Redirect back to the specific seller's product list if we know the seller ID
        if ($seller_id > 0) {
            header('Location: manage_products.php?seller_id=' . $seller_id . '&msg=deleted');
        } else {
            header('Location: manage_products.php?msg=deleted');
        }
        exit;
    } else {
        echo "<div style='padding:50px; text-align:center;'>Error deleting product: " . $conn->error . "</div>";
    }
    $stmt->close();
} else {
    header('Location: manage_products.php');
}
?>