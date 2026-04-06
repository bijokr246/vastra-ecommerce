<?php
// 1. START THE SESSION & CHECK FOR LOGIN
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// 2. INCLUDE DATABASE CONNECTION
include "../config.php";

// 3. GET AND VALIDATE THE ORDER ID FROM THE URL
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    header('Location: orders.php');
    exit();
}

// 4. FETCH ALL ORDER-RELATED DATA USING SECURE PREPARED STATEMENTS

// --- QUERY 1: Get main order details, user info, and shipping address ---
$sql_order_details = "SELECT 
                        o.order_id,
                        o.total_amount,
                        o.status,
                        o.order_date,
                        u.fname,
                        u.lname,
                        u.email,
                        u.phone,
                        a.recipient_fname, -- CHANGED HERE (1/2): Fetching first name
                        a.recipient_lname, -- CHANGED HERE (1/2): Fetching last name
                        a.mobile_number,
                        a.house_address,
                        a.landmark,
                        a.locality_or_town,
                        a.district,
                        a.pincode,
                        a.state
                    FROM orders o
                    JOIN users u ON o.user_id = u.user_id
                    JOIN addresses a ON o.address_id = a.address_id
                    WHERE o.order_id = ?";

$stmt_details = $conn->prepare($sql_order_details);
$stmt_details->bind_param("i", $order_id);
$stmt_details->execute();
$result_details = $stmt_details->get_result();
$order_details = $result_details->fetch_assoc();
$stmt_details->close();

// If order not found, redirect back
if (!$order_details) {
    header('Location: orders.php');
    exit();
}


// --- QUERY 2: Get all items within this order ---
$sql_order_items = "SELECT
                        oi.quantity,
                        oi.price AS price_at_order,
                        p.product_id,
                        p.product_name,
                        p.product_image,
                        s.shop_id,
                        s.shop_name
                    FROM order_items oi
                    JOIN products p ON oi.product_id = p.product_id
                    JOIN shops s ON p.shop_id = s.shop_id
                    WHERE oi.order_id = ?";

$stmt_items = $conn->prepare($sql_order_items);
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();
$order_items = $result_items->fetch_all(MYSQLI_ASSOC);
$stmt_items->close();

$conn->close();

$current_page = 'orders.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Order #<?php echo htmlspecialchars($order_id); ?> Details | Vastra Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .order-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .detail-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }
        .detail-card h3 {
            margin-top: 0;
            border-bottom: 2px solid #f1f1f1;
            padding-bottom: 10px;
            margin-bottom: 15px;
            font-size: 1.1rem;
            color: #343a40;
        }
        .detail-card p {
            margin: 0 0 8px 0;
            line-height: 1.6;
            color: #495057;
        }
        .detail-card p strong {
            color: #212529;
            min-width: 120px;
            display: inline-block;
        }
        .product-list-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .product-list-table th, .product-list-table td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        .product-list-table th {
            background-color: #f8f9fa;
        }
        .product-info {
            display: flex;
            align-items: center;
        }
        .product-info img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 15px;
        }
        .product-info span {
            font-weight: 600;
        }
        .table-footer td {
            font-weight: bold;
            text-align: right;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <aside class="sidebar"><?php require "header.php" ?></aside>

    <main class="main-content">
        <div class="page-header-container">
            <h1 class="page-header">Order Details: #<?php echo htmlspecialchars($order_id); ?></h1>
            <a href="orders.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to All Orders</a>
        </div>
        
        <div class="order-details-grid">
            <div class="detail-card">
                <h3><i class="fas fa-receipt"></i> Order Summary</h3>
                <p><strong>Order Status:</strong> <span class="status <?php echo strtolower(htmlspecialchars($order_details['status'])); ?>"><?php echo htmlspecialchars($order_details['status']); ?></span></p>
                <p><strong>Order Date:</strong> <?php echo date('M d, Y h:i A', strtotime($order_details['order_date'])); ?></p>
                <p><strong>Total Amount:</strong> ₹<?php echo number_format($order_details['total_amount'], 2); ?></p>
            </div>
            <div class="detail-card">
                <h3><i class="fas fa-user"></i> Customer Information</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($order_details['fname'] . ' ' . $order_details['lname']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($order_details['email']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($order_details['phone']); ?></p>
            </div>
            <div class="detail-card">
                <h3><i class="fas fa-shipping-fast"></i> Shipping Address</h3>
                <!-- CHANGED HERE (2/2): Combining fname and lname for display -->
                <p><strong>Recipient:</strong> <?php echo htmlspecialchars($order_details['recipient_fname'] . ' ' . $order_details['recipient_lname']); ?></p>
                <address style="font-style: normal; line-height: 1.6;">
                    <?php echo htmlspecialchars($order_details['house_address']); ?><br>
                    <?php if (!empty($order_details['landmark'])) echo htmlspecialchars($order_details['landmark']) . '<br>'; ?>
                    <?php echo htmlspecialchars($order_details['locality_or_town']); ?><br>
                    <?php echo htmlspecialchars($order_details['district']) . ', ' . htmlspecialchars($order_details['pincode']); ?><br>
                    <?php echo htmlspecialchars($order_details['state']); ?><br>
                    <strong>Mobile:</strong> <?php echo htmlspecialchars($order_details['mobile_number']); ?>
                </address>
            </div>
        </div>

        <section class="content-card">
            <h3><i class="fas fa-box-open"></i> Ordered Items</h3>
            <table class="product-list-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Shop</th>
                        <th style="text-align: right;">Price</th>
                        <th style="text-align: center;">Quantity</th>
                        <th style="text-align: right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($order_items as $item): 
                        $subtotal = $item['price_at_order'] * $item['quantity'];
                    ?>
                    <tr>
                        <td>
                            <div class="product-info">
                                <img src="../images/<?php echo htmlspecialchars($item['product_image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                <span><?php echo htmlspecialchars($item['product_name']); ?></span>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($item['shop_name']); ?></td>
                        <td style="text-align: right;">₹<?php echo number_format($item['price_at_order'], 2); ?></td>
                        <td style="text-align: center;"><?php echo htmlspecialchars($item['quantity']); ?></td>
                        <td style="text-align: right;">₹<?php echo number_format($subtotal, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-footer">
                        <td colspan="4">Grand Total:</td>
                        <td>₹<?php echo number_format($order_details['total_amount'], 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </section>
    </main>
</body>
</html>