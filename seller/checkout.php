<?php
session_start();
include "../config.php"; // Handles DB connection, constants, Razorpay library

// --- 1. SECURITY & VALIDATION ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ../seller-login.php');
    exit();
}
$user_id = $_SESSION['user_id'];

if (!isset($_GET['plan_id']) || !filter_var($_GET['plan_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid subscription plan selected.";
    header('Location: subscription.php');
    exit();
}
$plan_id = intval($_GET['plan_id']);

// --- 2. FETCH DATA FROM DATABASE ---
$stmt_user = $conn->prepare("SELECT fname, lname, email, phone FROM users WHERE user_id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();

$stmt_plan = $conn->prepare("SELECT plan_name, price, duration_days FROM subscription_plans WHERE plan_id = ? AND is_active = 1");
$stmt_plan->bind_param("i", $plan_id);
$stmt_plan->execute();
$plan_result = $stmt_plan->get_result();
if ($plan_result->num_rows === 0) {
    $_SESSION['error_message'] = "The selected plan is not available.";
    header('Location: subscription.php');
    exit();
}
$plan = $plan_result->fetch_assoc();
$stmt_plan->close();

// --- 3. CREATE RAZORPAY ORDER ---
$amount_in_paise = $plan['price'] * 100;
$receipt_id = 'plan_' . uniqid();

$orderData = [
    'receipt'         => $receipt_id,
    'amount'          => $amount_in_paise,
    'currency'        => 'INR',
    'notes'           => ['user_id' => $user_id, 'plan_id' => $plan_id]
];

try {
    $razorpayOrder = $razorpayApi->order->create($orderData);
    $razorpayOrderId = $razorpayOrder['id'];
} catch (Exception $e) {
    die('Razorpay Error: ' . $e->getMessage());
}

$current_page = 'checkout.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Checkout | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .checkout-container { max-width: 500px; margin: 40px auto; }
        .order-summary { background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 25px; }
        .summary-item { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 1rem; }
        .summary-item .label { color: #6c757d; }
        .summary-item .value { font-weight: 600; color: #343a40; }
        .total-row { border-top: 2px solid #dee2e6; margin-top: 20px; padding-top: 20px; }
        .total-row .value { font-size: 1.5rem; color: var(--primary); }
        .btn { text-align: center; } /* Ensure text is centered in the anchor tag */
        .btn-pay { width: 100%; margin-top: 20px; margin-bottom: 10px; padding: 12px; font-size: 1.1rem; }
        .btn-cancel { display: block; width: 100%; color: #6c757d; padding: 10px; text-decoration: none; border-radius: 5px; transition: background-color 0.2s; }
        .btn-cancel:hover { background-color: #f1f3f5; }
    </style>
</head>
<body>
    <div class="sidebar"><?php require "sidebar.php"; ?></div>
    <div class="main">
        <div class="header"><h1>Secure Checkout</h1></div>
        
        <div class="checkout-container">
            <div class="content-card">
                <h2>Order Summary</h2>
                <div class="order-summary">
                    <div class="summary-item">
                        <span class="label">Plan</span>
                        <span class="value"><?php echo htmlspecialchars($plan['plan_name']); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="label">Duration</span>
                        <span class="value"><?php echo htmlspecialchars($plan['duration_days']); ?> Days</span>
                    </div>
                    <div class="summary-item total-row">
                        <span class="label">Amount Payable</span>
                        <span class="value">₹<?php echo number_format($plan['price'], 2); ?></span>
                    </div>
                </div>

                <button id="pay-btn" class="btn btn-primary btn-pay">Pay Securely</button>
                <div class="button-group">
                    <!-- <button id="pay-btn" class="btn btn-primary">Pay Securely</button> -->
                    <a href="cancel_subscription.php" class="btn btn-secondary btn-cancel">Cancel</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        // Razorpay script remains the same
        document.getElementById('pay-btn').onclick = function(e) {
            var options = {
                "key": "<?php echo RAZORPAY_KEY_ID; ?>",
                "amount": "<?php echo $amount_in_paise; ?>",
                "currency": "INR",
                "name": "Vastra Seller Program",
                "description": "Subscription Payment",
                "order_id": "<?php echo $razorpayOrderId; ?>",
                "handler": function(response) {
                    document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
                    document.getElementById('razorpay_order_id').value = response.razorpay_order_id;
                    document.getElementById('razorpay_signature').value = response.razorpay_signature;
                    document.getElementById('payment-form').submit();
                },
                "prefill": {
                    "name": "<?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?>",
                    "email": "<?php echo htmlspecialchars($user['email']); ?>",
                    "contact": "<?php echo htmlspecialchars($user['phone']); ?>"
                },
                "theme": {"color": "#ff3f6c"}
            };
            var rzp1 = new Razorpay(options);
            rzp1.open();
            e.preventDefault();
        };
    </script>

    <form id="payment-form" action="payment_handler.php" method="POST" style="display: none;">
        <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
        <input type="hidden" name="razorpay_order_id" id="razorpay_order_id">
        <input type="hidden" name="razorpay_signature" id="razorpay_signature">
    </form>
</body>
</html>
<?php $conn->close(); ?>