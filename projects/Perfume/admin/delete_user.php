<?php
include '../include/connection.php';
session_start();

// Security check
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ../login.php');
    exit;
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $type = $_GET['type'] ?? 'user'; // Check if we are deleting a 'seller' or a 'user'

    if ($type === 'seller') {
        // --- DELETE SELLER ---
        // Your DB has ON DELETE CASCADE, so this one query will 
        // automatically delete products, orders, and payments linked to this seller.
        $sql = "DELETE FROM sellers WHERE id = ?";
        $redirect = "manage_sellers.php";
    } else {
        // --- DELETE CUSTOMER/USER ---
        // This will automatically delete the user's addresses, reviews, and cart 
        // due to database cascade rules.
        $sql = "DELETE FROM users WHERE id = ?";
        $redirect = "manage_users.php";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: $redirect");
        exit;
    } else {
        echo "Error deleting $type: " . $conn->error;
    }
    
    $stmt->close();
} else {
    header('Location: manage_sellers.php');
    exit;
}
?>