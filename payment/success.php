<?php
session_start();
// Security check: only show if the last successful order ID is in the session.
if (!isset($_SESSION['success_order_id'])) {
    header("Location: ../index.php");
    exit;
}
$order_id = $_SESSION['success_order_id'];

// Clear the session variable to prevent re-accessing this page for the same order
unset($_SESSION['success_order_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed! | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: 'Segoe UI', sans-serif; background: #f0fdf8; color: #333; margin: 0; padding: 20px; box-sizing: border-box; }
        .success-container { width: 100%; max-width: 500px; text-align: center; padding: 40px; background: #fff; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); border-top: 5px solid #03a685; }
        .success-icon { width: 80px; height: 80px; line-height: 80px; background-color: #e6f6f3; color: #03a685; border-radius: 50%; font-size: 40px; margin: 0 auto 20px auto; }
        h1 { margin-bottom: 15px; color: #03a685; }
        .order-id-box { background: #f5f5f6; padding: 10px; border-radius: 8px; margin: 25px auto; display: inline-block; font-size: 1.1rem; }
        .order-id-box span { color: #696b79; }
        .order-id-box strong { color: #282c3f; }
        p { color: #696b79; line-height: 1.6; }
        .actions { margin-top: 30px; display: flex; flex-direction: column; gap: 15px; }
        .btn { display: inline-block; width: 100%; padding: 15px; font-size: 16px; font-weight: bold; border-radius: 8px; cursor: pointer; text-decoration: none; transition: all 0.3s; border: none; box-sizing: border-box; }
        .btn-shop { background-color: #ff3f6c; color: #fff; }
        .btn-shop:hover { background-color: #e0355e; }
        .btn-orders { background-color: transparent; color: #696b79; border: 1px solid #ddd; }
        .btn-orders:hover { background-color: #f5f5f6; color: #282c3f; }
    </style>
    <script src="../refresh.js"></script>
</head>
<body>
    <div class="success-container">
        <div class="success-icon"><i class="fas fa-check"></i></div>
        <h1>Order Confirmed!</h1>
        <p>Thank you for shopping with Vastra. Your order has been placed successfully.</p>
        <div class="order-id-box"><span>Order ID:</span> <strong>#<?php echo htmlspecialchars($order_id); ?></strong></div>
        <p>A confirmation email has been sent to you with the order details.</p>
        <div class="actions">
            <a href="../index.php" class="btn btn-shop">Continue Shopping</a>
            <a href="../myorders.php" class="btn btn-orders">View My Orders</a>
        </div>
    </div>
</body>
</html>