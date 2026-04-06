<?php
session_start();
include "config.php";
define('UPLOAD_PATH', 'images/');

// --- DATA FETCHING FOR PROFILE CARD & LOGIN STATE ---
$is_logged_in = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
$user_firstname = '';

// --- NEW: Fetch user details for profile card ---
if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];

    // Fetch user's first name and email
    $sql_user = "SELECT fname, email FROM users WHERE user_id = ?";
    if ($stmt_user = mysqli_prepare($conn, $sql_user)) {
        mysqli_stmt_bind_param($stmt_user, "i", $user_id);
        mysqli_stmt_execute($stmt_user);
        $result_user = mysqli_stmt_get_result($stmt_user);
        if ($user_row = mysqli_fetch_assoc($result_user)) {
            $user_firstname = $user_row['fname'];
            $_SESSION['email'] = $user_row['email']; // Ensure email is in session
        }
        mysqli_stmt_close($stmt_user);
    }
}
// --- END NEW SECTION ---


$cart_items = [];
$total_amount = 0; // Only this total is needed now

// --- STEP 1: Fetch cart items from either Database or Session ---
if ($is_logged_in) {
    // For LOGGED-IN USERS, fetch cart items from the database
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT p.product_id, p.product_name, p.price, p.product_image, ci.quantity 
            FROM cart ci 
            JOIN products p ON ci.product_id = p.product_id 
            WHERE ci.user_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $cart_items[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
} else {
    // For GUEST USERS, process the cart from the session
    if (!empty($_SESSION['cart'])) {
        $product_ids = array_keys($_SESSION['cart']);
        if (!empty($product_ids)) {
            $id_string = implode(',', array_map('intval', $product_ids));
            $sql = "SELECT product_id, product_name, price, product_image FROM products WHERE product_id IN ($id_string)";
            
            $result = mysqli_query($conn, $sql);
            $products_data = [];
            if ($result) {
                while($row = mysqli_fetch_assoc($result)){
                    $products_data[$row['product_id']] = $row;
                }
            }
            
            foreach ($_SESSION['cart'] as $product_id => $item) {
                if (isset($products_data[$product_id])) {
                    $product_details = $products_data[$product_id];
                    $product_details['quantity'] = $item['quantity'];
                    $cart_items[] = $product_details;
                }
            }
        }
    }
}

// --- STEP 2: Calculate all totals --- (MODIFIED SECTION)
foreach ($cart_items as $item) {
    $total_amount += $item['price'] * $item['quantity'];
}
$cart_item_count = count($cart_items);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css"> 
    <script src="refresh.js"></script>
    <style>
        /* Your existing cart CSS is perfectly fine and remains unchanged. */
        :root {
            --primary: #ff3f6c;
            --secondary: #282c3f;
            --light-gray: #f5f5f6;
            --dark-gray: #696b79;
            --white: #ffffff;
            --success: #03a685;
            --success-light: #e6f6f3
        }
                * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--light-gray);
            color: var(--secondary);
        }

        .navbar {
            background-color: var(--white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
            padding: 15px 0;
        }

        .nav-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 30px;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--secondary);
            font-weight: 500;
            transition: color .3s;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .nav-icons {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* Added align-items for better vertical alignment */
        .nav-icons a {
            color: var(--secondary);
            font-size: 20px;
            transition: color .3s;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 15px;
        }

        .cart-container {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        @media (min-width:992px) {
            .cart-container {
                flex-direction: row;
                align-items: flex-start;
            }
        }

        .cart-items {
            flex: 1;
        }

        .cart-summary {
            width: 100%;
        }

        @media (min-width:992px) {
            .cart-summary {
                width: 380px;
            }
        }

        .cart-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 30px;
        }

        .cart-item {
            display: flex;
            background-color: var(--white);
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .item-image {
            width: 120px;
            height: 150px;
            flex-shrink: 0;
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-details {
            flex: 1;
            padding: 15px;
            display: flex;
            flex-direction: column;
        }

        .item-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .item-brand {
            color: var(--dark-gray);
            font-size: 14px;
            margin-bottom: 10px;
        }

        .item-price {
            font-weight: 600;
            color: var(--secondary);
            font-size: 16px;
            margin-top: auto;
        }

        .price-original {
            text-decoration: line-through;
            color: var(--dark-gray);
            font-size: 14px;
            margin-left: 8px;
        }

        .item-actions {
            display: flex;
            align-items: center;
            margin-top: 15px;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
        }

        .quantity-input {
            width: 60px;
            text-align: center;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .remove-btn-text {
            background: none;
            border: none;
            color: var(--primary);
            font-weight: 600;
            cursor: pointer;
            margin-left: 15px;
        }

        .summary-card {
            background-color: var(--white);
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            position: sticky;
            top: 100px;
        }

        .summary-divider {
            border: 0;
            border-top: 1px solid #eee;
            margin: 20px 0;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .summary-row span:first-child {
            color: var(--dark-gray);
        }

        .summary-row span:last-child {
            font-weight: 600;
        }

        .total-row {
            font-size: 18px;
            font-weight: 700;
        }

        .total-row span:last-child {
            color: var(--secondary);
        }

        .savings-highlight {
            background-color: var(--success-light);
            color: var(--success);
            padding: 10px;
            border-radius: 4px;
            font-weight: 600;
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }

        .checkout-btn {
            width: 100%;
            background-color: var(--primary);
            color: var(--white);
            border: none;
            padding: 14px;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 700;
            margin-top: 20px;
            cursor: pointer;
            transition: background-color .3s;
        }

        .empty-cart {
            text-align: center;
            padding: 60px 0;
            background-color: var(--white);
            border-radius: 8px;
        }

        .empty-cart i {
            font-size: 60px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-cart h3 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .empty-cart-btn {
            display: inline-block;
            padding: 10px 25px;
            border-radius: 4px;
            font-weight: bold;
            text-decoration: none;
            background-color: var(--primary);
            color: var(--white);
        }

        @media (max-width:767px) {
            .nav-links {
                display: none;
            }

            .cart-item {
                flex-direction: column;
            }

            .item-image {
                width: 100%;
                height: 250px;
            }
        }  
    </style>
</head>
<body>
    
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">VASTRA</a>
            <?php include "nav_links.html"; ?>
            <div class="nav-icons">
                <a href="search.php" title="Search"><i class="fas fa-search"></i></a>
                <a href="wishlist.php" title="Wishlist"><i class="far fa-heart"></i></a>
                <a href="cart.php" class="cart-icon-container" title="Shopping Cart">
                    <i class="fas fa-shopping-bag"></i>
                </a>
                
                <!-- --- NEW: Profile Icon and Card --- -->
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
                        <div id="profile-card" class="profile-card">
                            <div class="profile-header">
                                <h4>Welcome</h4>
                                <p>To access account and manage orders</p>
                            </div>
                            <a href="login.php" class="btn-login-signup">LOGIN / SIGNUP</a>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- --- END NEW SECTION --- -->

            </div>
        </div>
    </nav>
    
    <div class="container">
        
        <?php if (empty($cart_items)): ?>
            <!-- This section shows if the cart is empty -->
            <div class="empty-cart">
                <i class="fas fa-shopping-bag"></i>
                <h3>Your shopping cart is empty!</h3>
                <p>Looks like you haven't added anything to your cart yet.</p>
                <a href="index.php" class="empty-cart-btn">Continue Shopping</a>
            </div>
        <?php else: ?>
            <!-- This section shows if the cart has items -->
            <h2 class="cart-title">Shopping Cart (<?php echo $cart_item_count; ?> Items)</h2>
            <div class="cart-container">
                <!-- Left Column: Cart Items -->
                <div class="cart-items">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div class="item-image"><img src="<?php echo UPLOAD_PATH . htmlspecialchars($item['product_image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>"></div>
                            <div class="item-details">
                                <h3 class="item-title"><?php echo htmlspecialchars($item['product_name']); ?></h3>
                                <div class="item-actions">
                                    <form action="cart_update_handler.php" method="POST" class="quantity-selector">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                        <label for="quantity-<?php echo $item['product_id']; ?>" class="sr-only">Quantity</label>
                                        <input id="quantity-<?php echo $item['product_id']; ?>" type="number" name="quantity" class="quantity-input" value="<?php echo $item['quantity']; ?>" min="1" onchange="this.form.submit()">
                                    </form>
                                    <form action="cart_update_handler.php" method="POST">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                        <button type="submit" class="remove-btn-text">REMOVE</button>
                                    </form>
                                </div>
                                <div class="item-price">₹<?php echo number_format($item['price'], 2); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Right Column: Cart Summary (MODIFIED SECTION) -->
                <div class="cart-summary">
                    <div class="summary-card">
                        <h3 style="font-weight: 600; margin-bottom: 20px;">PRICE DETAILS</h3>
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span>₹<?php echo number_format($total_amount, 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Delivery Charges</span>
                            <span>FREE</span>
                        </div>
                        <hr class="summary-divider">
                        <div class="summary-row total-row">
                            <span>Total Amount</span>
                            <span>₹<?php echo number_format($total_amount, 2); ?></span>
                        </div>
                        <form action="checkout.php" method="POST">
                            <button class="checkout-btn">PLACE ORDER</button>
                        </form>
                    </div>
                </div>

            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.html'; ?>
    
    <!-- --- NEW: JavaScript for Profile Card --- -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- Profile Card Toggle Logic ---
        const profileCardContainer = document.querySelector('.profile-card-container');
        const profileCard = document.getElementById('profile-card');
        
        if (profileCardContainer && profileCard) {
            profileCardContainer.addEventListener('click', (event) => {
                // Stop the click from propagating to the document, which would instantly close it.
                event.stopPropagation();
                // We only toggle if the icon or greeting is clicked, not links inside the card.
                if (event.target.id === 'user-greeting' || event.target.closest('#user-icon-btn')) {
                    profileCard.classList.toggle('active');
                }
            });
        }
        
        // Add a click listener to the whole page to close the card if you click outside of it.
        document.addEventListener('click', (event) => {
            if (profileCard && profileCard.classList.contains('active')) {
                // If the click is outside the profile card container, close the card.
                if (!profileCardContainer.contains(event.target)) {
                    profileCard.classList.remove('active');
                }
            }
        });
    });
    </script>
    <!-- --- END NEW SECTION --- -->

</body>
</html>