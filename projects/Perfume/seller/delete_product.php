<?php
session_start();
include '../include/connection.php';

// 1. Security Check
if (!isset($_SESSION['seller_logged_in']) || $_SESSION['seller_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

// 2. Check if ID is set
if (isset($_GET['id'])) {
    $product_id = intval($_GET['id']);
    $seller_id = $_SESSION['id'];

    // First, verify that this product actually belongs to the logged-in seller, and get the image path!
    $check_sql = "SELECT id, image FROM product WHERE id = ? AND seller_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $product_id, $seller_id);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        $check_stmt->bind_result($pid, $img_path);
        $check_stmt->fetch();
        
        // Because our database has ON DELETE CASCADE, deleting the product will automatically 
        // delete all associated cart items, wishlists, orders, payments, and reviews safely!
        
        $sql_prod = "DELETE FROM product WHERE id = ? AND seller_id = ?";
        $stmt_prod = $conn->prepare($sql_prod);
        $stmt_prod->bind_param("ii", $product_id, $seller_id);

        if ($stmt_prod->execute()) {
            // Delete the image file from the server to save space
            if (!empty($img_path) && file_exists("../" . $img_path)) {
                unlink("../" . $img_path);
            }
            
            // Success: Alert and Redirect back to the list
            echo "<script>
                    alert('Product deleted successfully!');
                    window.location.href='manage_products.php';
                  </script>";
        } else {
            echo "<script>
                    alert('Error deleting product.');
                    window.location.href='manage_products.php';
                  </script>";
        }
        $stmt_prod->close();
    } else {
        // Security trigger: Product doesn't belong to this seller or doesn't exist
        echo "<script>
                alert('Unauthorized action or product not found.');
                window.location.href='manage_products.php';
              </script>";
    }
    $check_stmt->close();
} else {
    header('Location: manage_products.php');
}
?>