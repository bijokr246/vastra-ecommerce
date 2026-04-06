<?php
session_start();
// Assuming config.php includes your Razorpay API key setup and composer autoload
include "../config.php"; 
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

// --- 1. SECURITY CHECKS ---
if (!isset($_SESSION['user_id'])) { die("Access denied."); }
if (empty($_POST['razorpay_payment_id']) || empty($_POST['razorpay_order_id']) || empty($_POST['razorpay_signature'])) {
    $_SESSION['error_message'] = "Payment details are incomplete.";
    header('Location: subscription.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success = false;

// --- 2. VERIFY PAYMENT SIGNATURE ---
try {
    $attributes = [
        'razorpay_order_id' => $_POST['razorpay_order_id'],
        'razorpay_payment_id' => $_POST['razorpay_payment_id'],
        'razorpay_signature' => $_POST['razorpay_signature']
    ];
    $razorpayApi->utility->verifyPaymentSignature($attributes);
    $success = true;
} catch (SignatureVerificationError $e) {
    $_SESSION['error_message'] = "Payment verification failed: " . $e->getMessage();
    header('Location: subscription.php');
    exit();
}

// --- 3. PROCESS THE ORDER IF SIGNATURE IS VALID ---
if ($success === true) {
    try {
        $order = $razorpayApi->order->fetch($_POST['razorpay_order_id']);
        $plan_id = $order['notes']['plan_id'];
        $order_user_id = $order['notes']['user_id'];

        if ($order_user_id != $user_id) {
            throw new Exception("User ID mismatch.");
        }

        $stmt_plan = $conn->prepare("SELECT duration_days FROM subscription_plans WHERE plan_id = ?");
        $stmt_plan->bind_param("i", $plan_id);
        $stmt_plan->execute();
        $plan = $stmt_plan->get_result()->fetch_assoc();
        $duration_days = $plan['duration_days'];
        $stmt_plan->close();
        
        if (!$duration_days) { throw new Exception("Could not find plan duration."); }

        // Determine the subscription start date
        $start_date = new DateTime(); 
        $stmt_current = $conn->prepare("SELECT end_date FROM seller_subscriptions WHERE user_id = ? AND status = 'active' AND end_date > NOW() ORDER BY end_date DESC LIMIT 1");
        $stmt_current->bind_param("i", $user_id);
        $stmt_current->execute();
        $current_plan_result = $stmt_current->get_result();
        if ($current_plan_result->num_rows > 0) {
            $current_plan = $current_plan_result->fetch_assoc();
            $start_date = new DateTime($current_plan['end_date']);
        }
        $stmt_current->close();

        // Calculate the new end date
        $end_date = clone $start_date;
        $end_date->add(new DateInterval("P{$duration_days}D"));
        
        $start_date_db = $start_date->format('Y-m-d H:i:s');
        $end_date_db = $end_date->format('Y-m-d H:i:s');

        // Use a database transaction for safety
        $conn->begin_transaction();

        // Step A: Deactivate any OLD active subscriptions.
        $stmt_deactivate = $conn->prepare("UPDATE seller_subscriptions SET status = 'expired' WHERE user_id = ? AND status = 'active'");
        $stmt_deactivate->bind_param("i", $user_id);
        $stmt_deactivate->execute();
        $stmt_deactivate->close();
        
        // Step B: Insert the new subscription record
        $stmt_insert = $conn->prepare(
            "INSERT INTO seller_subscriptions (user_id, plan_id, start_date, end_date, status, razorpay_payment_id)
             VALUES (?, ?, ?, ?, 'active', ?)"
        );
        $stmt_insert->bind_param("iisss", $user_id, $plan_id, $start_date_db, $end_date_db, $_POST['razorpay_payment_id']);
        $stmt_insert->execute();

        if ($stmt_insert->affected_rows > 0) {
            
            // =========================================================================
            // --- NEW: Step C - Reactivate Seller's Shops and Products
            // =========================================================================
            // This runs only after the new subscription is successfully inserted.

            // C.1: Reactivate the seller's shops that were marked as 'expired'.
            // We specifically target 'expired' status to avoid activating a shop that was manually set to 'inactive' or 'banned'.
            $stmt_reactivate_shops = $conn->prepare("UPDATE shops SET shop_status = 'active' WHERE user_id = ? AND shop_status = 'expired'");
            $stmt_reactivate_shops->bind_param("i", $user_id);
            $stmt_reactivate_shops->execute();
            $stmt_reactivate_shops->close();

            // C.2: Reactivate the seller's products that were marked as 'expired'.
            $stmt_reactivate_products = $conn->prepare(
                "UPDATE products p 
                 JOIN shops s ON p.shop_id = s.shop_id 
                 SET p.status = 'active' 
                 WHERE s.user_id = ? AND p.status = 'expired'"
            );
            $stmt_reactivate_products->bind_param("i", $user_id);
            $stmt_reactivate_products->execute();
            $stmt_reactivate_products->close();
            
            // =========================================================================
            // --- END OF NEW LOGIC ---
            // =========================================================================

            // All updates were successful, so commit the transaction.
            $conn->commit();
            $_SESSION['success_message'] = "Payment successful! Your subscription is now active and your shop has been reactivated.";
            header('Location: subscription.php');
            exit();

        } else {
            // If inserting the subscription failed, roll back everything.
            $conn->rollback();
            throw new Exception("Database update failed. Could not add new subscription.");
        }
        $stmt_insert->close();

    } catch (Exception $e) {
        // If any error occurs during the transaction, roll it back.
        $conn->rollback();
        $_SESSION['error_message'] = "An error occurred after payment. Please contact support. Details: " . $e->getMessage();
        header('Location: subscription.php');
        exit();
    }
}