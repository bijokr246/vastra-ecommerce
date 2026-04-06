<?php
// Start the session to access login state
session_start();
include "config.php"; // Use ../ to go up one directory to find config.php

// --- 1. CORE CHECKOUT LOGIC & USER AUTHENTICATION ---

// Redirect to login if user is not logged in
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Define the path for image uploads for consistency
define('UPLOAD_PATH', 'images/');

// Get the logged-in user's ID
$user_id = $_SESSION['user_id'];
$is_logged_in = true; // Since we check for login above, this is always true here.

// --- 2. DATA FETCHING (MERGED FROM INDEX.PHP AND CHECKOUT.PHP) ---

// Initialize variables needed for the page and navbar
$user_firstname = '';
$addresses = [];
$cart_items = [];
$total_amount = 0;

// Fetch user's first name and email for the navbar profile card
$sql_user = "SELECT fname, email FROM users WHERE user_id = ?";
if ($stmt_user = mysqli_prepare($conn, $sql_user)) {
    mysqli_stmt_bind_param($stmt_user, "i", $user_id);
    mysqli_stmt_execute($stmt_user);
    $result_user = mysqli_stmt_get_result($stmt_user);
    if ($user_row = mysqli_fetch_assoc($result_user)) {
        $user_firstname = $user_row['fname'];
        $_SESSION['email'] = $user_row['email']; // Ensure email is in session for the navbar
    }
    mysqli_stmt_close($stmt_user);
}


// Fetch User's Saved Addresses for the checkout form
$address_sql = "SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, address_id DESC"; // Show default first
if ($stmt_addr = mysqli_prepare($conn, $address_sql)) {
    mysqli_stmt_bind_param($stmt_addr, "i", $user_id);
    mysqli_stmt_execute($stmt_addr);
    $result_addr = mysqli_stmt_get_result($stmt_addr);
    while ($row = mysqli_fetch_assoc($result_addr)) {
        $addresses[] = $row;
    }
    mysqli_stmt_close($stmt_addr);
}

// Fetch Cart Items and Calculate Total for the order summary
$cart_sql = "SELECT p.product_id, p.product_name, p.price, p.product_image, ci.quantity 
             FROM cart ci 
             JOIN products p ON ci.product_id = p.product_id 
             WHERE ci.user_id = ?";
if ($stmt_cart = mysqli_prepare($conn, $cart_sql)) {
    mysqli_stmt_bind_param($stmt_cart, "i", $user_id);
    mysqli_stmt_execute($stmt_cart);
    $result_cart = mysqli_stmt_get_result($stmt_cart);
    while ($row = mysqli_fetch_assoc($result_cart)) {
        $cart_items[] = $row;
        $total_amount += $row['price'] * $row['quantity'];
    }
    mysqli_stmt_close($stmt_cart);
}

// Redirect to cart if it's empty
if (empty($cart_items)) {
    header("Location: cart.php?status=empty");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css"> <!-- Main stylesheet that styles navbar and footer -->
    <style>
        /* --- General Checkout Styles --- */
        body { background-color: #f5f5f6; font-family: 'Segoe UI', sans-serif; }
        .checkout-container { display: flex; flex-direction: column; max-width: 1200px; margin: 40px auto; padding: 0 15px; gap: 30px; }
        @media (min-width: 992px) {
            .checkout-container { flex-direction: row; align-items: flex-start; }
        }

        /* --- Left Column: Address Selection --- */
        .address-container { flex: 2; background: #fff; border: 1px solid #eee; border-radius: 8px; padding: 25px; }
        .address-container h2 { margin-top: 0; font-size: 1.5rem; }

        /* --- Redesigned Address Cards --- */
        .address-card-list { display: grid; grid-template-columns: 1fr; gap: 15px; }
        @media (min-width: 768px) {
            .address-card-list { grid-template-columns: repeat(2, 1fr); }
        }
        .address-card { border: 2px solid #ddd; border-radius: 8px; padding: 15px; cursor: pointer; transition: border-color 0.3s, box-shadow 0.3s; position: relative; }
        .address-card.selected { border-color: var(--primary); box-shadow: 0 0 8px rgba(255, 63, 108, 0.4); }
        .address-card input[type="radio"] { display: none; }
        .address-card .address-type { font-size: 0.8rem; font-weight: bold; color: #666; background: #f0f0f0; padding: 3px 8px; border-radius: 4px; display: inline-block; margin-bottom: 8px; }
        .address-card p { margin: 2px 0; color: #555; }
        .address-card strong { color: #282c3f; }
        .address-card .default-badge { position: absolute; top: 15px; right: 15px; font-size: 0.8rem; color: var(--success); }
        .add-new-address-card { border: 2px dashed #ccc; display: flex; align-items: center; justify-content: center; text-align: center; color: var(--primary); text-decoration: none; transition: all 0.3s; min-height: 150px; }
        .add-new-address-card:hover { border-color: var(--primary); background-color: #fff8fa; }
        .add-new-address-card .fa-plus { font-size: 1.5rem; margin-bottom: 8px; }

        /* --- Redesigned 'No Address' Section --- */
        .no-address-found { text-align: center; padding: 40px 20px; border: 2px dashed #ddd; border-radius: 8px; }
        .no-address-found .fa-map-marker-alt { font-size: 3rem; color: #ccc; margin-bottom: 15px; }
        .no-address-found h3 { margin-bottom: 10px; color: #333; }
        .no-address-found p { color: #666; margin-bottom: 20px; }
        .btn-add-address { display: inline-block; background-color: var(--primary); color: white; padding: 12px 25px; border-radius: 5px; text-decoration: none; font-weight: 500; transition: opacity 0.3s; }
        .btn-add-address:hover { opacity: 0.9; }

        /* --- Right Column: Order Summary --- */
        .summary-container { flex: 1; position: sticky; top: 100px; }
        .order-summary { background: #fff; border: 1px solid #eee; border-radius: 8px; padding: 25px; }
        .order-summary h3 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px; }
        .summary-item { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; font-size: 0.9rem; }
        .summary-item img { width: 50px; height: 60px; object-fit: cover; border-radius: 4px; }
        .summary-item-details { flex-grow: 1; }
        .summary-item-details .item-name { display: block; font-weight: 500; }
        .summary-item-details .item-qty { color: #666; }
        .summary-item .item-price { font-weight: 500; }
        .summary-total { border-top: 1px solid #eee; padding-top: 15px; margin-top: 15px; display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: bold; }
        .proceed-btn { display: block; width: 100%; padding: 15px; background-color: #ff3f6c; color: white; border: none; border-radius: 5px; font-size: 16px; font-weight: bold; cursor: pointer; text-align: center; text-decoration: none; margin-top: 20px; transition: background-color 0.3s; }
        .proceed-btn:disabled { background-color: #ccc; cursor: not-allowed; }
    </style>
</head>
<body>
    <!-- ======================= NAVIGATION BAR ======================= -->
    <nav class="navbar">
        <div class="container nav-container">
            <button class="mobile-menu-btn" id="mobile-menu-button"><i class="fas fa-bars"></i></button>
            <a href="index.php" class="logo">VASTRA</a>
            <?php include "nav_links.html"; ?>
            <div class="nav-icons">
                <a href="search.php" title="Search"><i class="fas fa-search"></i></a>
                <a href="wishlist.php" title="Wishlist"><i class="far fa-heart"></i></a>
                <a href="cart.php" class="cart-icon-container" title="Shopping Cart">
                    <i class="fas fa-shopping-bag"></i>
                </a>
                <div class="profile-card-container">
                    <a href="#" id="user-icon-btn"><i class="far fa-user"></i></a>
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
                </div>
            </div>
        </div>
    </nav>
    <!-- ======================= END NAVIGATION BAR ======================= -->


    <form action="payment/place-order.php" method="POST" id="checkoutForm">
        <div class="checkout-container">
            <!-- LEFT COLUMN: ADDRESSES -->
            <div class="address-container">
                <h2>Select Delivery Address</h2>

                <?php if (!empty($addresses)): ?>
                    <div class="address-card-list">
                        <?php foreach ($addresses as $index => $address): ?>
                            <label class="address-card <?php echo ($index == 0) ? 'selected' : ''; ?>">
                                <input type="radio" name="address_id" value="<?php echo $address['address_id']; ?>" <?php echo ($index == 0) ? 'checked' : ''; ?>>
                                <span class="address-type"><?php echo htmlspecialchars($address['address_type']); ?></span>
                                <?php if ($address['is_default']): ?>
                                    <span class="default-badge"><i class="fas fa-check-circle"></i> Default</span>
                                <?php endif; ?>
                                
                                <!-- === CHANGED HERE: Combined first and last names for display === -->
                                <p><strong><?php echo htmlspecialchars($address['recipient_fname'] . ' ' . $address['recipient_lname']); ?></strong></p>
                                <!-- OLD CODE: <p><strong><?php echo htmlspecialchars($address['recipient_name']); ?></strong></p> -->
                                
                                <p><?php echo htmlspecialchars($address['house_address']); ?></p>
                                <p><?php echo htmlspecialchars($address['locality_or_town']); ?>, <?php echo htmlspecialchars($address['district']); ?></p>
                                <p><?php echo htmlspecialchars($address['state']); ?> - <?php echo htmlspecialchars($address['pincode']); ?></p>
                                <p>Mobile: <strong><?php echo htmlspecialchars($address['mobile_number']); ?></strong></p>
                            </label>
                        <?php endforeach; ?>
                        
                        <!-- Add New Address Card -->
                        <a href="dashboard/add-address.php?redirect_url=../checkout.php" class="add-new-address-card">
                            <div>
                                <i class="fas fa-plus"></i>
                                <p>Add New Address</p>
                            </div>
                        </a>
                    </div>
                <?php else: ?>
                    <!-- REDESIGNED 'NO ADDRESS' SECTION -->
                    <div class="no-address-found">
                        <i class="fas fa-map-marker-alt"></i>
                        <h3>Where should we send your order?</h3>
                        <p>You haven't saved any addresses yet. Let's add one now!</p>
                        <a href="dashboard/add-address.php?redirect_url=../checkout.php" class="btn-add-address">Add a New Address</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- RIGHT COLUMN: ORDER SUMMARY -->
            <div class="summary-container">
                <div class="order-summary">
                    <h3>Order Summary</h3>
                    <?php foreach ($cart_items as $item): ?>
                        <div class="summary-item">
                            <img src="<?php echo UPLOAD_PATH . htmlspecialchars($item['product_image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                            <div class="summary-item-details">
                                <span class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                <span class="item-qty">Qty: <?php echo $item['quantity']; ?></span>
                            </div>
                            <span class="item-price">₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="summary-total">
                        <span>Total Amount</span>
                        <span>₹<?php echo number_format($total_amount, 2); ?></span>
                    </div>
                    <button type="submit" class="proceed-btn" <?php if (empty($addresses)) echo 'disabled'; ?>>
                        PROCEED TO PAYMENT
                    </button>
                </div>
            </div>
        </div>
    </form>
    
    <!-- ======================= FOOTER ======================= -->
    <?php include 'footer.html'; ?>
    <!-- ======================= END FOOTER ======================= -->

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- Logic for Address Card Selection ---
        const addressCards = document.querySelectorAll('.address-card');
        if (addressCards.length > 0) {
            addressCards.forEach(card => {
                card.addEventListener('click', () => {
                    addressCards.forEach(c => c.classList.remove('selected'));
                    card.classList.add('selected');
                    const radio = card.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                    }
                });
            });
        }
        
        // --- Logic for Profile Card Dropdown in Navbar ---
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