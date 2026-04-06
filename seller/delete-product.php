<?php
session_start();
include "../config.php"; // Ensure this path to your config file is correct

// --- 1. SECURITY AND AUTHENTICATION ---
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../seller-login.php');
    exit();
}

// Check if the user has the 'seller' role
$user_id = $_SESSION['user_id'];
$stmt_role = $conn->prepare("SELECT role FROM login WHERE user_id = ?");
$stmt_role->bind_param("i", $user_id);
$stmt_role->execute();
$role_result = $stmt_role->get_result()->fetch_assoc();
if (!$role_result || $role_result['role'] !== 'seller') {
    session_destroy();
    header('Location: ../seller-login.php?error=access_denied');
    exit();
}
$stmt_role->close();

// --- 2. GET AND VALIDATE INPUT ---
// Check if the product ID is provided and is a valid integer
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header('Location: my-products.php?error=invalid_request');
    exit();
}
$product_id_to_delete = (int)$_GET['id'];

// Preserve the filter/sort query string for redirection
$query_params = $_GET;
unset($query_params['id']); // Remove the ID from the params for the redirect URL
$redirect_query_string = http_build_query($query_params);

// --- 3. AUTHORIZATION CHECK ---
// Verify that the product to be deleted belongs to a shop owned by the current user
$auth_stmt = $conn->prepare("
    SELECT p.product_image 
    FROM products p
    JOIN shops s ON p.shop_id = s.shop_id
    WHERE p.product_id = ? AND s.user_id = ?
");
$auth_stmt->bind_param("ii", $product_id_to_delete, $user_id);
$auth_stmt->execute();
$auth_result = $auth_stmt->get_result();

if ($auth_result->num_rows !== 1) {
    // If no row is found, it means the product either doesn't exist or doesn't belong to this seller.
    $auth_stmt->close();
    $conn->close();
    header('Location: my-products.php?error=unauthorized&' . $redirect_query_string);
    exit();
}

// Fetch the image filename before we delete the record
$product_data = $auth_result->fetch_assoc();
$image_filename = $product_data['product_image'];
$auth_stmt->close();

// --- 4. DELETE PRODUCT IMAGE FROM SERVER ---
// It's good practice to remove the file to save space and keep things clean.
if (!empty($image_filename)) {
    $image_path = "../images/" . $image_filename; // Adjust this path if your images are stored elsewhere
    if (file_exists($image_path)) {
        unlink($image_path); // The unlink() function deletes a file
    }
}

// --- 5. DELETE DATABASE RECORD ---
$delete_stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
$delete_stmt->bind_param("i", $product_id_to_delete);

if ($delete_stmt->execute() && $delete_stmt->affected_rows > 0) {
    // Deletion was successful
    $delete_stmt->close();
    $conn->close();
    header('Location: my-products.php?success=deleted&' . $redirect_query_string);
    exit();
} else {
    // Deletion failed
    $delete_stmt->close();
    $conn->close();
    header('Location: my-products.php?error=delete_failed&' . $redirect_query_string);
    exit();
}