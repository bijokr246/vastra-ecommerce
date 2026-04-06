<?php
// Start the session to access login state
session_start();
include "config.php";

// Define the path for image uploads for consistency
define('UPLOAD_PATH', 'images/');

// --- USER AUTHENTICATION & DATA FETCHING ---

// 1. Check user login state. This page is for logged-in users only.
$is_logged_in = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
$user_firstname = '';

// SECURITY: If the user is not logged in, redirect them to the login page.
if (!$is_logged_in) {
    // We can add a redirect parameter so they come back here after logging in
    header("Location: login.php?redirect=myorders.php");
    exit();
}

// Since we know the user is logged in, we can proceed.
$user_id = $_SESSION['user_id'];

// 2. Fetch user's first name (for the navbar)
$sql_user = "SELECT fname, email FROM users WHERE user_id = ?";
if ($stmt_user = mysqli_prepare($conn, $sql_user)) {
    mysqli_stmt_bind_param($stmt_user, "i", $user_id);
    mysqli_stmt_execute($stmt_user);
    $result_user = mysqli_stmt_get_result($stmt_user);
    if ($user_row = mysqli_fetch_assoc($result_user)) {
        $user_firstname = $user_row['fname'];
        $_SESSION['email'] = $user_row['email'];
    }
    mysqli_stmt_close($stmt_user);
}

// 3. Fetch all orders and their associated items for the logged-in user
$orders = [];
$sql_orders = "SELECT
                    o.order_id,
                    o.total_amount,
                    o.status,
                    o.order_date,
                    oi.quantity,
                    oi.price AS item_price,
                    p.product_name,
                    p.product_image,
                    p.product_id
                FROM orders AS o
                JOIN order_items AS oi ON o.order_id = oi.order_id
                JOIN products AS p ON oi.product_id = p.product_id
                WHERE o.user_id = ?
                ORDER BY o.order_date DESC, o.order_id DESC";

if ($stmt_orders = mysqli_prepare($conn, $sql_orders)) {
    mysqli_stmt_bind_param($stmt_orders, "i", $user_id);
    mysqli_stmt_execute($stmt_orders);
    $result = mysqli_stmt_get_result($stmt_orders);
    
    // Group the flat results into a structured array of orders
    while ($row = mysqli_fetch_assoc($result)) {
        $order_id = $row['order_id'];
        
        // If this is the first time we've seen this order_id, create the main order entry
        if (!isset($orders[$order_id])) {
            $orders[$order_id] = [
                'order_id' => $order_id,
                'total_amount' => $row['total_amount'],
                'status' => $row['status'],
                'order_date' => $row['order_date'],
                'items' => []
            ];
        }
        
        // Add the current item to this order's 'items' array
        $orders[$order_id]['items'][] = [
            'product_id' => $row['product_id'],
            'product_name' => $row['product_name'],
            'product_image' => $row['product_image'],
            'quantity' => $row['quantity'],
            'price' => $row['item_price']
        ];
    }
    mysqli_stmt_close($stmt_orders);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
    <script src="refresh.js"></script>
    <style>
        .my-orders-section {
            padding: 3rem 1rem;
            background-color: #f9f9f9;
            min-height: 80vh;
        }
        .page-title {
            text-align: center;
            margin-bottom: 2.5rem;
            font-size: 2.5rem;
        }
        .orders-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .order-card {
            background-color: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow: hidden; /* To contain the border-radius */
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap; /* For responsiveness */
            gap: 1rem;
            padding: 1rem 1.5rem;
            background-color: #fcfcfc;
            border-bottom: 1px solid #e0e0e0;
        }
        .order-header-info h3 {
            margin: 0;
            font-size: 1.2rem;
        }
        .order-header-info p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        .order-status {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        /* Dynamic Status Colors */
        .status-pending { background-color: #ffeccf; color: #f39c12; }
        .status-processing { background-color: #d6eaff; color: #3498db; }
        .status-shipped { background-color: #d1f7e8; color: #2ecc71; }
        .status-delivered { background-color: #d4edda; color: #155724; }
        .status-canceled { background-color: #f8d7da; color: #721c24; }
        .status-returned { background-color: #e2e3e5; color: #383d41; }
        
        .order-items-list {
            padding: 1rem 1.5rem;
        }
        .order-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 0;
        }
        .order-item:not(:last-child) {
            border-bottom: 1px solid #f0f0f0;
        }
        .order-item-image {
            width: 70px;
            height: 70px;
            border-radius: 4px;
            object-fit: cover;
        }
        .order-item-details {
            flex-grow: 1;
        }
        .order-item-details h4 {
            margin: 0 0 0.3rem 0;
            font-size: 1rem;
        }
        .order-item-details a { text-decoration: none; color: #333; }
        .order-item-details a:hover { color: var(--primary); }
        .order-item-details p {
            margin: 0;
            font-size: 0.9rem;
            color: #777;
        }
        .order-item-price {
            font-weight: 600;
            font-size: 1rem;
        }

        /* .order-footer {
            text-align: right;
            padding: 1rem 1.5rem;
            background-color: #fcfcfc;
            border-top: 1px solid #e0e0e0;
            font-weight: bold;
            font-size: 1.2rem;
        } */

        /* "No Orders" State */
        .no-orders-container {
            text-align: center; padding: 4rem 1rem;
            background-color: #fff; border: 1px dashed #ddd; border-radius: 8px;
        }
        .no-orders-container i {
            font-size: 4rem; color: #e0e0e0; margin-bottom: 1.5rem;
        }
        .no-orders-container h2 {
            font-size: 1.8rem; margin-bottom: 1rem;
        }
        .no-orders-container p {
            color: #666; margin-bottom: 2rem;
        }

        .order-footer {
            display: flex; /* Use flexbox for alignment */
            justify-content: space-between; /* Space items out */
            align-items: center; /* Vertically align items */
            text-align: right;
            padding: 1rem 1.5rem;
            background-color: #fcfcfc;
            border-top: 1px solid #e0e0e0;
        }
        .order-footer span {
            font-weight: bold;
            font-size: 1.2rem;
        }
        .btn-view-details {
            display: inline-block;
            padding: 0.5rem 1rem;
            background-color: var(--primary); /* Use your theme's primary color */
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: background-color 0.3s;
        }
        .btn-view-details:hover {
            background-color: var(--primary-dark); /* A darker shade for hover */
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container nav-container">
            <button class="mobile-menu-btn" id="mobile-menu-button"><i class="fas fa-bars"></i></button>
            <a href="index.php" class="logo">VASTRA</a>
            <?php include 'nav_links.html'; ?>
            <div class="nav-icons">
                <a href="search.php" title="Search"><i class="fas fa-search"></i></a>
                <a href="wishlist.php" title="Wishlist"><i class="far fa-heart"></i></a>
                <a href="cart.php" class="cart-icon-container" title="Shopping Cart">
                    <i class="fas fa-shopping-bag"></i>
                </a>
                <div class="profile-card-container">
                    <a href="#" id="user-icon-btn"><i class="far fa-user"></i></a>
                    <?php if ($is_logged_in): ?>
                        <span id="user-greeting" class="user-greeting-text">Hello, <?php echo htmlspecialchars($user_firstname); ?></span>
                        <div id="profile-card" class="profile-card">
                            <div class="profile-header">
                                <h4>Hello, <?php echo htmlspecialchars($user_firstname); ?></h4>
                                <p><?php echo htmlspecialchars($_SESSION['email']); ?></p>
                            </div>
                            <ul class="profile-card-links">
                                <li><a href="dashboard.php"><i class="far fa-user-circle fa-fw"></i> My Profile</a></li>
                                <li><a href="myorders.php"><i class="fas fa-box-open fa-fw"></i> Orders</a></li>
                                <li><a href="wishlist.php"><i class="far fa-heart fa-fw"></i> Wishlist</a></li>
                                <li><a href="logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <!-- This part will not be shown due to the redirect, but it's good practice to keep the template consistent -->
                        <div id="profile-card" class="profile-card">
                            <div class="profile-header">
                                <h4>Welcome</h4>
                                <p>To access account and manage orders</p>
                            </div>
                            <a href="login.php" class="btn-login-signup">LOGIN / SIGNUP</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- MAIN ORDERS SECTION -->
    <main class="my-orders-section">
        <div class="container">
            <h1 class="page-title">My Orders</h1>
            <div class="orders-container">
                <?php if (empty($orders)): ?>
                    <div class="no-orders-container">
                        <i class="fas fa-receipt"></i>
                        <h2>No Orders Yet</h2>
                        <p>You haven't placed any orders with us. Let's change that!</p>
                        <a href="products.php" class="btn btn-primary">Start Shopping</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div class="order-header-info">
                                    <h3>Order #<?php echo htmlspecialchars($order['order_id']); ?></h3>
                                    <p>Placed on: <?php echo date('F j, Y', strtotime($order['order_date'])); ?></p>
                                </div>
                                <div class="order-status status-<?php echo strtolower(htmlspecialchars($order['status'])); ?>">
                                    <?php echo htmlspecialchars($order['status']); ?>
                                </div>
                            </div>
                            <div class="order-items-list">
                                <?php foreach ($order['items'] as $item): ?>
                                    <div class="order-item">
                                        <img src="<?php echo UPLOAD_PATH . htmlspecialchars($item['product_image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="order-item-image">
                                        <div class="order-item-details">
                                            <h4><a href="product-detail.php?id=<?php echo $item['product_id']; ?>"><?php echo htmlspecialchars($item['product_name']); ?></a></h4>
                                            <p>Quantity: <?php echo htmlspecialchars($item['quantity']); ?></p>
                                        </div>
                                        <div class="order-item-price">
                                            ₹<?php echo htmlspecialchars(number_format($item['price'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="order-footer">
                                <span>Order Total: ₹<?php echo htmlspecialchars(number_format($order['total_amount'])); ?></span>
                                <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn-view-details">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer (Copied from index.php) -->
    <?php include 'footer.html'; ?>

    <!-- JavaScript for Profile Card (Copied from index.php) -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- Profile Card Toggle Logic ---
        const profileCardContainer = document.querySelector('.profile-card-container');
        const profileCard = document.getElementById('profile-card');
        if (profileCardContainer && profileCard) {
            profileCardContainer.addEventListener('click', (event) => {
                event.stopPropagation();
                if (event.target.id === 'user-greeting' || event.target.closest('#user-icon-btn')) {
                    profileCard.classList.toggle('active');
                }
            });
        }
        document.addEventListener('click', (event) => {
            if (profileCard && profileCard.classList.contains('active')) {
                if (!profileCardContainer.contains(event.target)) {
                    profileCard.classList.remove('active');
                }
            }
        });
    });
    </script>
</body>
</html>