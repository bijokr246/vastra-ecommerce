<?php
session_start();
include '../config.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

// Security checks
if (!isset($_SESSION['is_logged_in']) || empty($_POST['razorpay_payment_id']) || !isset($_SESSION['pending_order_details'])) {
    header("Location: ../index.php");
    exit();
}

$success = true;
$error = "Payment Failed";

if (empty($_POST['razorpay_payment_id']) === false) {
    $payment_id = $_POST['razorpay_payment_id'];
    $order_id = $_POST['razorpay_order_id'];
    $signature = $_POST['razorpay_signature'];

    try {
        $attributes = ['razorpay_order_id' => $order_id, 'razorpay_payment_id' => $payment_id, 'razorpay_signature' => $signature];
        $razorpayApi->utility->verifyPaymentSignature($attributes);
    } catch(SignatureVerificationError $e) {
        $success = false;
        $error = 'Razorpay Error : ' . $e->getMessage();
    }
}

// --- If payment signature is verified, process the order ---
if ($success === true) {
    // Retrieve order details from session
    $order_details = $_SESSION['pending_order_details'];
    
    // Start a transaction for all-or-nothing database operations
    mysqli_begin_transaction($conn);
    try {
        // 1. Insert the main order into the 'orders' table
        $order_status = 'Delivered'; // Status is 'Delivered' right away
        $sql_order = "INSERT INTO orders (user_id, address_id, total_amount, status, razorpay_payment_id, razorpay_order_id) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_order = mysqli_prepare($conn, $sql_order);
        mysqli_stmt_bind_param($stmt_order, "iidsss", $order_details['user_id'], $order_details['address_id'], $order_details['total_amount'], $order_status, $payment_id, $order_id);
        mysqli_stmt_execute($stmt_order);
        $internal_order_id = mysqli_insert_id($conn); // Get the new order ID

        // 2. Insert order items and UPDATE product stock
        $stmt_items = mysqli_prepare($conn, "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        $stmt_stock = mysqli_prepare($conn, "UPDATE products SET quantity_available = quantity_available - ? WHERE product_id = ?");
        
        foreach ($order_details['cart_items'] as $item) {
            // Insert item into order_items
            mysqli_stmt_bind_param($stmt_items, "iiid", $internal_order_id, $item['product_id'], $item['quantity'], $item['price']);
            mysqli_stmt_execute($stmt_items);
            
            // Decrement product stock
            mysqli_stmt_bind_param($stmt_stock, "ii", $item['quantity'], $item['product_id']);
            mysqli_stmt_execute($stmt_stock);
        }

        // 3. Check and update product status to 'out-of-stock' if quantity is 0
        $stmt_check_stock = mysqli_prepare($conn, "UPDATE products SET status = 'out-of-stock' WHERE product_id = ? AND quantity_available <= 0");
        foreach ($order_details['cart_items'] as $item) {
            mysqli_stmt_bind_param($stmt_check_stock, "i", $item['product_id']);
            mysqli_stmt_execute($stmt_check_stock);
        }
        
        // 4. Clear the user's cart
        $stmt_clear_cart = mysqli_prepare($conn, "DELETE FROM cart WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt_clear_cart, "i", $order_details['user_id']);
        mysqli_stmt_execute($stmt_clear_cart);
        
        // If all queries succeed, commit the transaction
        mysqli_commit($conn);
        
        // Unset the temporary order session data
        unset($_SESSION['pending_order_details']);
        
        // Set session variable for the success page
        $_SESSION['success_order_id'] = $internal_order_id;
        
        // Redirect to success page
        header("Location: success.php");
        exit();

    } catch (Exception $e) {
        // If any DB operation fails, roll back the entire transaction
        mysqli_rollback($conn);
        // You can log the error for debugging
        // error_log("Order processing failed: " . $e->getMessage());
        header("Location: failure.php?reason=database_error");
        exit();
    }

} else {
    // If signature verification fails, redirect to failure page. No DB changes were made.
    header("Location: failure.php?reason=invalid_signature");
    exit();
}