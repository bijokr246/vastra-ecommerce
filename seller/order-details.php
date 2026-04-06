<?php
session_start();
include "../config.php";

// --- 1. SECURITY AND AUTHENTICATION ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ../seller-login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$stmt_role = $conn->prepare("SELECT role FROM login WHERE user_id = ?");
$stmt_role->bind_param("i", $user_id);
$stmt_role->execute();
$role_result = $stmt_role->get_result()->fetch_assoc();
if (!$role_result || $role_result['role'] !== 'seller') {
    header('Location: ../seller-login.php?error=access_denied');
    exit();
}
$stmt_role->close();

// --- 2. VALIDATE INPUT AND AUTHORIZATION ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid Order ID.");
}
$order_id = (int)$_GET['id'];
$back_link = 'orders.php';
if (isset($_GET['back_params']) && !empty($_GET['back_params'])) {
    $back_link = 'orders.php?' . $_GET['back_params'];
}
$auth_sql = "
    SELECT COUNT(*) as item_count
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    JOIN shops s ON p.shop_id = s.shop_id
    WHERE oi.order_id = ? AND s.user_id = ?
";
$stmt_auth = $conn->prepare($auth_sql);
$stmt_auth->bind_param("ii", $order_id, $user_id);
$stmt_auth->execute();
$auth_result = $stmt_auth->get_result()->fetch_assoc();
$stmt_auth->close();
if ($auth_result['item_count'] == 0) {
    die("Order not found or you do not have permission to view this order.");
}

// --- 3. HANDLE STATUS UPDATE (POST REQUEST) ---
$update_message = '';
$order_statuses = ['Pending', 'Processing', 'Shipped', 'Delivered'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    if (in_array($new_status, $order_statuses)) {
        $update_sql = "UPDATE orders SET status = ? WHERE order_id = ?";
        $stmt_update = $conn->prepare($update_sql);
        $stmt_update->bind_param("si", $new_status, $order_id);
        if ($stmt_update->execute()) {
            $update_message = "<p class='message success'>Order status updated successfully!</p>";
        } else {
            $update_message = "<p class='message error'>Failed to update status.</p>";
        }
        $stmt_update->close();
    } else {
        $update_message = "<p class='message error'>Invalid status selected.</p>";
    }
}

// --- 4. FETCH ORDER DETAILS ---
$order_details_sql = "
    SELECT
        o.order_id, o.order_date, o.status AS order_status,
        c.fname, c.lname, a.recipient_fname, a.recipient_lname, a.mobile_number, a.house_address, 
        a.landmark, a.locality_or_town, a.district, a.pincode, a.state
    FROM orders o
    JOIN users c ON o.user_id = c.user_id
    JOIN addresses a ON o.address_id = a.address_id
    WHERE o.order_id = ?
";
$stmt_details = $conn->prepare($order_details_sql);
$stmt_details->bind_param("i", $order_id);
$stmt_details->execute();
$order = $stmt_details->get_result()->fetch_assoc();
$stmt_details->close();
if (!$order) { die("Could not retrieve order details."); }

$seller_items_sql = "
    SELECT p.product_name, p.product_image, oi.quantity, oi.price AS price_at_purchase
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    JOIN shops s ON p.shop_id = s.shop_id
    WHERE oi.order_id = ? AND s.user_id = ?
";
$stmt_items = $conn->prepare($seller_items_sql);
$stmt_items->bind_param("ii", $order_id, $user_id);
$stmt_items->execute();
$seller_items_result = $stmt_items->get_result();

$seller_total = 0;
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Order Details #V-<?php echo $order_id; ?> | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="../refresh.js"></script>
    <style>
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .header h1 { margin-bottom: 0; }
        .back-button { display: inline-block; padding: 8px 16px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px; font-size: 14px; transition: background-color 0.2s; }
        .back-button:hover { background-color: #5a6268; }
        .back-button i { margin-right: 5px; }
        .order-details-container { display: flex; flex-wrap: wrap; gap: 20px; }
        .details-card { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); flex: 1; min-width: 350px; }
        .details-card h3 { border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-top: 0; margin-bottom: 15px; font-size: 1.2em; }
        .details-card p { margin: 5px 0; line-height: 1.6; }
        .details-card p strong { display: inline-block; min-width: 120px; color: #555; }
        .address-block { margin-top: 10px; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .items-table th, .items-table td { text-align: left; padding: 12px; border-bottom: 1px solid #eee; }
        .items-table th { background-color: #f8f8f8; }
        .items-table img { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; margin-right: 10px; }
        .product-cell { display: flex; align-items: center; }
        .total-summary { text-align: right; font-size: 1.2em; font-weight: bold; margin-top: 20px; }
        .status-update-form { display: flex; gap: 10px; align-items: center; margin-top: 15px; }
        .status-update-form select { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .status-update-form button { padding: 8px 15px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .message { padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="sidebar">
        <?php require "sidebar.php"; ?>
    </div>
    <div class="main">
        <div class="header">
            <h1>Order Details <span style="color: #007bff;">#V-<?php echo htmlspecialchars($order['order_id']); ?></span></h1>
            <a href="<?php echo htmlspecialchars($back_link); ?>" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>
        </div>
        <div class="content">
            <?php echo $update_message; ?>
            <div class="order-details-container">
                <div class="details-card">
                    <h3>Order Summary</h3>
                    <p><strong>Order ID:</strong> #V-<?php echo htmlspecialchars($order['order_id']); ?></p>
                    <p><strong>Order Date:</strong> <?php echo date('d M Y, h:i A', strtotime($order['order_date'])); ?></p>
                    <p><strong>Current Status:</strong> 
                        <span class="status <?php echo strtolower(htmlspecialchars($order['order_status'])); ?>">
                            <?php echo htmlspecialchars(ucfirst($order['order_status'])); ?>
                        </span>
                    </p>
                    <form action="" method="POST" class="status-update-form">
                        <label for="status"><strong>Update Status:</strong></label>
                        <select name="status" id="status">
                            <?php foreach ($order_statuses as $status): ?>
                                <option value="<?php echo $status; ?>" <?php if ($order['order_status'] == $status) echo 'selected'; ?>>
                                    <?php echo $status; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="update_status">Update</button>
                    </form>
                </div>
                <div class="details-card">
                    <h3>Shipping Address</h3>
                     <p><strong>Customer Name:</strong> <?php echo htmlspecialchars($order['fname'] . ' ' . $order['lname']); ?></p>
                     <!-- CHANGED HERE: Combine fname and lname for the recipient -->
                    <p><strong>Recipient:</strong> <?php echo htmlspecialchars($order['recipient_fname'] . ' ' . $order['recipient_lname']); ?></p>
                    <p><strong>Mobile:</strong> <?php echo htmlspecialchars($order['mobile_number']); ?></p>
                    <div class="address-block">
                        <?php
                            echo htmlspecialchars($order['house_address']) . "<br>";
                            if (!empty($order['landmark'])) {
                                echo htmlspecialchars($order['landmark']) . "<br>";
                            }
                            echo htmlspecialchars($order['locality_or_town']) . ", " . htmlspecialchars($order['district']) . "<br>";
                            echo htmlspecialchars($order['state']) . " - " . htmlspecialchars($order['pincode']);
                        ?>
                    </div>
                </div>
            </div>
            <div class="details-card" style="margin-top: 20px;">
                <h3>Your Items in this Order</h3>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $seller_items_result->fetch_assoc()): ?>
                            <?php 
                                $subtotal = $item['price_at_purchase'] * $item['quantity'];
                                $seller_total += $subtotal;
                            ?>
                            <tr>
                                <td>
                                    <div class="product-cell">
                                        <img src="../images/<?php echo htmlspecialchars($item['product_image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                    </div>
                                </td>
                                <td>x <?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td>₹<?php echo htmlspecialchars(number_format($item['price_at_purchase'], 2)); ?></td>
                                <td>₹<?php echo htmlspecialchars(number_format($subtotal, 2)); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <div class="total-summary">
                    Your Total: ₹<?php echo htmlspecialchars(number_format($seller_total, 2)); ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
$stmt_items->close();
$conn->close();
?>