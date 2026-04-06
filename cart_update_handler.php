<?php
// cart_update_handler.php

session_start();
include "config.php";

// Ensure the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php'); // Redirect if accessed directly
    exit;
}

// Sanitize and get the POST data
$action = $_POST['action'] ?? '';
$product_id = (int)($_POST['product_id'] ?? 0);
$is_logged_in = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;

// Proceed only if we have a valid product ID
if ($product_id > 0) {

    // --- LOGIC FOR THE 'UPDATE QUANTITY' ACTION ---
    if ($action === 'update') {
        $quantity = (int)($_POST['quantity'] ?? 1);
        if ($quantity < 1) {
            $quantity = 1; // Quantity cannot be less than 1
        }

        if ($is_logged_in) {
            // Update the quantity in the database for the logged-in user
            $user_id = $_SESSION['user_id'];
            $sql = "UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "iii", $quantity, $user_id, $product_id);
                mysqli_stmt_execute($stmt);
            }
        } else {
            // Update the quantity in the session for the guest user
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id]['quantity'] = $quantity;
            }
        }
    }

    // --- LOGIC FOR THE 'REMOVE ITEM' ACTION ---
    if ($action === 'remove') {
        if ($is_logged_in) {
            // Delete the item from the database for the logged-in user
            $user_id = $_SESSION['user_id'];
            $sql = "DELETE FROM cart WHERE user_id = ? AND product_id = ?";
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
                mysqli_stmt_execute($stmt);
            }
        } else {
            // Remove the item from the session for the guest user
            unset($_SESSION['cart'][$product_id]);
        }
    }
}

// After processing, redirect the user back to the cart page to see the changes
header('Location: cart.php');
exit;
?>

