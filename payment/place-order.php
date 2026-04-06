<?php
session_start();
include "../config.php"; // Path goes UP one level to the root

// --- SECURITY CHECKS ---
// Redirect if user not logged in or no address is selected from checkout
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true || !isset($_POST['address_id'])) {
    header("Location: ../checkout.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$address_id = (int)$_POST['address_id'];
$total_amount = 0;
$cart_items = [];

// --- 1. Fetch Cart Items and Recalculate Total Amount from DB ---
// This is a crucial security step to prevent price manipulation from the client-side.
$cart_sql = "SELECT p.product_id, p.price, p.product_name, ci.quantity FROM cart ci JOIN products p ON ci.product_id = p.product_id WHERE ci.user_id = ?";
if ($stmt_cart = mysqli_prepare($conn, $cart_sql)) {
    mysqli_stmt_bind_param($stmt_cart, "i", $user_id);
    mysqli_stmt_execute($stmt_cart);
    $result_cart = mysqli_stmt_get_result($stmt_cart);
    while ($row = mysqli_fetch_assoc($result_cart)) {
        $cart_items[] = $row;
        $total_amount += $row['price'] * $row['quantity'];
    }
    mysqli_stmt_close($stmt_cart);
} else {
    // Handle potential DB error
    die("Error: Could not retrieve cart details.");
}

// Redirect if cart is empty
if (empty($cart_items)) {
    header("Location: ../cart.php");
    exit;
}

// --- 2. NEW LOGIC: Store All Order Details in the Session ---
// The order will only be created in the database AFTER successful payment.
$_SESSION['pending_order_details'] = [
    'user_id'       => $user_id,
    'address_id'    => $address_id,
    'total_amount'  => $total_amount,
    'cart_items'    => $cart_items // This array contains all product info needed
];

// --- 3. Create a Razorpay Order ---
$razorpay_amount = $total_amount * 100; // Amount in paise
// Create a temporary receipt ID. The real order ID will be generated in handler.php
$receipt_id = 'RCPT_' . $user_id . '_' . time();

$orderData = [
    'receipt'         => $receipt_id,
    'amount'          => $razorpay_amount,
    'currency'        => 'INR'
    // 'notes' are optional, not strictly needed with session handling
];

$razorpayOrder = $razorpayApi->order->create($orderData);
$razorpayOrderId = $razorpayOrder['id'];

// --- 4. Prepare Data for Frontend Razorpay Checkout ---
$user_info_sql = "SELECT fname, lname, email, phone FROM users WHERE user_id = ?";
$stmt_user = mysqli_prepare($conn, $user_info_sql);
mysqli_stmt_bind_param($stmt_user, "i", $user_id);
mysqli_stmt_execute($stmt_user);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
mysqli_stmt_close($stmt_user);
mysqli_close($conn); // All DB operations for this page are done.

$checkout_data = [
    "key"               => RAZORPAY_KEY_ID,
    "amount"            => $razorpay_amount,
    "name"              => "Vastra",
    "description"       => "Complete payment for your order",
    "image"             => "../images/logo.png", // Ensure this path is correct
    "prefill"           => [
        "name"    => trim($user['fname'] . ' ' . $user['lname']), 
        "email"   => $user['email'], 
        "contact" => $user['phone']
    ],
    "theme"             => ["color" => "#ff3f6c"],
    "order_id"          => $razorpayOrderId,
];

$json_data = json_encode($checkout_data);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Payment | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: 'Segoe UI', sans-serif; background: #f5f5f6; margin: 0; padding: 20px; box-sizing: border-box; }
        .payment-card { width: 100%; max-width: 400px; text-align: center; padding: 30px; background: #fff; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.08); }
        .secure-lock { color: #aaa; font-size: 1.5rem; margin-bottom: 10px; }
        .payment-card h2 { margin-top: 0; margin-bottom: 10px; color: #282c3f; }
        .payment-card p { color: #696b79; margin-bottom: 25px; line-height: 1.6; }
        .amount-display { font-size: 2.5rem; font-weight: 700; color: #282c3f; margin-bottom: 25px; }
        .amount-display sup { font-size: 1.5rem; font-weight: 500; }
        .btn { display: inline-block; width: 100%; padding: 15px; font-size: 16px; font-weight: bold; border-radius: 8px; cursor: pointer; text-decoration: none; transition: all 0.3s; border: none; box-sizing: border-box; }
        .btn-pay { background-color: #ff3f6c; color: #fff; margin-bottom: 15px; }
        .btn-pay:hover { background-color: #e0355e; }
        .btn-cancel { background-color: transparent; color: #696b79; border: 1px solid #ddd; }
        .btn-cancel:hover { background-color: #f5f5f6; color: #282c3f; }
        .secure-info { margin-top: 25px; font-size: 0.8rem; color: #aaa; }
        .secure-info i { color: #03a685; }
    </style>
    <script src="../refresh.js"></script>
</head>
<body>
    <div class="payment-card">
        <div class="secure-lock"><i class="fas fa-lock"></i></div>
        <h2>Final Step: Secure Payment</h2>
        <p>You are about to pay the following amount for your order at Vastra.</p>
        <div class="amount-display"><sup>₹</sup><?php echo number_format($total_amount, 2); ?></div>
        
        <button id="rzp-button" class="btn btn-pay">PAY SECURELY</button>
        
        <!-- MODIFIED: The cancel link no longer needs an order_id -->
        <a href="cancel.php" class="btn btn-cancel">Cancel Payment</a>
        
        <div class="secure-info"><i class="fas fa-shield-alt"></i> All transactions are secure and encrypted.</div>
    </div>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        var options = <?php echo $json_data; ?>;

        // This function is called when payment is successful
        options.handler = function (response){
            document.getElementById('rzp_paymentid').value = response.razorpay_payment_id;
            document.getElementById('rzp_orderid').value = response.razorpay_order_id;
            document.getElementById('rzp_signature').value = response.razorpay_signature;
            // Submit the form to handler.php for server-side verification
            document.getElementById('razorpay-form').submit();
        };

        var rzp = new Razorpay(options);
        
        // This function is called when the user closes the payment window or payment fails
        rzp.on('payment.failed', function (response){
            // MODIFIED: No order_id to pass here. Just redirect to failure page with a reason.
            window.location.href = `failure.php?reason=${encodeURIComponent(response.error.description)}`;
        });
        
        // Binds the payment popup to our button
        document.getElementById('rzp-button').onclick = function(e){
            rzp.open();
            e.preventDefault();
        }
    </script>

    <!-- MODIFIED: This form no longer needs the internal_order_id -->
    <form id="razorpay-form" action="handler.php" method="POST" style="display: none;">
        <input type="hidden" name="razorpay_payment_id" id="rzp_paymentid">
        <input type="hidden" name="razorpay_order_id" id="rzp_orderid">
        <input type="hidden" name="razorpay_signature" id="rzp_signature">
    </form>
</body>
</html>