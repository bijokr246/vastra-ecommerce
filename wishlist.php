<?php
// Start the session to access user and cart data
session_start();
include "config.php";

// --- AUTHENTICATION CHECK ---
// If the user is not logged in, redirect them to the login page.
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    // Save the page they were trying to access to redirect them back after login
    $_SESSION['redirect_url'] = 'wishlist.php';
    header("Location: login.php?message=Please login to view your wishlist.");
    exit;
}

// Define the path for product images
define('UPLOAD_PATH', 'images/');

// --- DATA FETCHING ---

// 1. Get user details from session
$user_id = $_SESSION['user_id'];
$user_firstname = '';
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

// 2. Get all product IDs that are currently in the user's cart
$cart_product_ids = [];
$sql_cart_ids = "SELECT product_id FROM cart WHERE user_id = ?";
if ($stmt_cart_ids = mysqli_prepare($conn, $sql_cart_ids)) {
    mysqli_stmt_bind_param($stmt_cart_ids, "i", $user_id);
    mysqli_stmt_execute($stmt_cart_ids);
    $result_cart_ids = mysqli_stmt_get_result($stmt_cart_ids);
    while ($row_cart = mysqli_fetch_assoc($result_cart_ids)) {
        $cart_product_ids[] = $row_cart['product_id'];
    }
    mysqli_stmt_close($stmt_cart_ids);
}

// 3. Fetch all products from the user's wishlist
$wishlist_items = [];
$sql_wishlist = "SELECT p.product_id, p.product_name, p.price, p.product_image
                 FROM products p
                 JOIN wishlist w ON p.product_id = w.product_id
                 WHERE w.user_id = ?";
if ($stmt_wishlist = mysqli_prepare($conn, $sql_wishlist)) {
    mysqli_stmt_bind_param($stmt_wishlist, "i", $user_id);
    mysqli_stmt_execute($stmt_wishlist);
    $result_wishlist = mysqli_stmt_get_result($stmt_wishlist);
    if ($result_wishlist) {
        while ($row = mysqli_fetch_assoc($result_wishlist)) {
            // Check if this wishlist item is also in the cart
            $row['in_cart'] = in_array($row['product_id'], $cart_product_ids);
            $wishlist_items[] = $row;
        }
    }
    mysqli_stmt_close($stmt_wishlist);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
    <style>
        .btn-move-to-bag, .btn-go-to-bag {
            width: 100%;
            display: block;
            text-align: center;
            border: none;
            padding: 10px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 700;
            margin-top: 10px;
            cursor: pointer;
            transition: background-color 0.3s, border-color 0.3s;
            text-decoration: none;
        }
        .btn-move-to-bag {
            background-color: var(--secondary);
            color: var(--white);
        }
        .btn-move-to-bag:hover {
            background-color: var(--primary);
        }
        .btn-move-to-bag:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        .btn-go-to-bag {
            background-color: #f0f0f0;
            color: #333;
            border: 1px solid #ccc;
        }
        .btn-go-to-bag:hover {
            background-color: #e0e0e0;
        }
        .wishlist-remove-icon { position: absolute; top: 10px; right: 10px; font-size: 20px; color: #ccc; cursor: pointer; transition: color 0.3s; }
        .wishlist-remove-icon:hover { color: var(--primary); }
        .product-image { position: relative; }
        .empty-state-container { text-align: center; padding: 80px 20px; background-color: #fff; border-radius: 8px; margin-top: 40px; }
        .empty-state-container i { font-size: 60px; color: var(--primary); margin-bottom: 20px; }
        .empty-state-container h3 { font-size: 24px; margin-bottom: 10px; }
    </style>
    <script src="refresh.js"></script>
</head>
<body>

    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="container nav-container">
            <a href="index.php" class="logo">VASTRA</a>
            <?php include "nav_links.html"; ?>
            <div class="nav-icons">
                 <a href="wishlist.php" title="Wishlist"><i class="far fa-heart"></i></a>
                 <a href="cart.php" title="Shopping Cart"><i class="fas fa-shopping-bag"></i></a>
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
                            <li><a href="logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Wishlist Content -->
    <main class="container" style="padding-top: 40px; padding-bottom: 40px;">
        <h2 class="section-title">My Wishlist</h2>

        <?php if (!empty($wishlist_items)): ?>
            <div class="products-grid" id="wishlist-grid">
                <?php foreach ($wishlist_items as $item): ?>
                    <div class="product-card" data-product-id="<?php echo $item['product_id']; ?>">
                        <div class="product-image">
                            <a href="product-detail.php?id=<?php echo $item['product_id']; ?>">
                                <img src="<?php echo UPLOAD_PATH . htmlspecialchars($item['product_image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                            </a>
                            <i class="fas fa-times wishlist-remove-icon" title="Remove from Wishlist"></i>
                        </div>
                        <div class="product-info">
                            <h3 class="product-title"><?php echo htmlspecialchars($item['product_name']); ?></h3>
                            <div class="product-price">
                                <span class="current-price">₹<?php echo htmlspecialchars(number_format($item['price'], 2)); ?></span>
                            </div>
                            
                            <!-- Conditional button: "Go to Bag" if in cart, otherwise "Move to Bag" -->
                            <?php if ($item['in_cart']): ?>
                                <a href="cart.php" class="btn-go-to-bag">Go to Bag</a>
                            <?php else: ?>
                                <button class="btn-move-to-bag">Move to Bag</button>
                            <?php endif; ?>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state-container">
                <i class="far fa-heart"></i>
                <h3>Your wishlist is empty</h3>
                <p>Looks like you haven't added anything yet. Let's change that!</p>
                <a href="shop.php" class="btn btn-primary" style="margin-top: 20px;">Continue Shopping</a>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <?php include 'footer.html'; ?>
    
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const wishlistGrid = document.getElementById('wishlist-grid');

        if (wishlistGrid) {
            wishlistGrid.addEventListener('click', async (e) => {
                const productCard = e.target.closest('.product-card');
                if (!productCard) return;

                const productId = productCard.dataset.productId;

                // Handles click on the 'x' icon to remove item completely from page and DB
                if (e.target.classList.contains('wishlist-remove-icon')) {
                    await removeFromWishlist(productId, productCard, false, true);
                }

                // Handles click on the "Move to Bag" button
                if (e.target.classList.contains('btn-move-to-bag')) {
                    const buttonElement = e.target;
                    buttonElement.disabled = true;
                    buttonElement.textContent = 'Moving...';

                    // Step 1: Add item to the cart
                    const addedSuccessfully = await addToCart(productId);

                    if (addedSuccessfully) {
                        // Step 2: Remove item from wishlist DB but NOT from the page
                        await removeFromWishlist(productId, productCard, true, false);

                        // Step 3: Dynamically replace the button with a "Go to Bag" link
                        const newLink = document.createElement('a');
                        newLink.href = 'cart.php';
                        newLink.className = 'btn-go-to-bag';
                        newLink.textContent = 'Go to Bag';
                        buttonElement.replaceWith(newLink);
                        
                    } else {
                        // If adding to cart failed, re-enable the button
                        buttonElement.disabled = false;
                        buttonElement.textContent = 'Move to Bag';
                    }
                }
            });
        }

        /**
         * Removes an item from the wishlist database and optionally from the page.
         * @param {string} productId - The ID of the product.
         * @param {HTMLElement} cardElement - The product card DOM element.
         * @param {boolean} silent - If true, suppress failure alerts.
         * @param {boolean} removeFromDOM - If true, remove the card from the page visually.
         */
        async function removeFromWishlist(productId, cardElement, silent = false, removeFromDOM = true) {
            const formData = new FormData();
            formData.append('action', 'toggle_wishlist');
            formData.append('product_id', productId);

            try {
                const response = await fetch('ajax_handler.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.status === 'success' && result.action === 'removed') {
                    if (removeFromDOM) {
                        cardElement.style.transition = 'opacity 0.5s';
                        cardElement.style.opacity = '0';
                        setTimeout(() => {
                            cardElement.remove();
                            if (wishlistGrid.children.length === 0) {
                                wishlistGrid.innerHTML = `<div class="empty-state-container" style="grid-column: 1 / -1;"><h3>Your wishlist is now empty</h3><a href="shop.php" class="btn btn-primary" style="margin-top: 20px;">Continue Shopping</a></div>`;
                            }
                        }, 500);
                    }
                } else if (!silent) {
                    alert(result.message || 'Could not remove item from wishlist.');
                }
            } catch (error) {
                if (!silent) alert('An error occurred while updating wishlist.');
            }
        }
        
        /**
         * Adds an item to the cart and returns true on success, false on failure.
         * @param {string} productId - The ID of the product.
         * @returns {boolean} - True if successful, false otherwise.
         */
        async function addToCart(productId) {
            const formData = new FormData();
            formData.append('action', 'add_to_cart');
            formData.append('product_id', productId);
            
            try {
                const response = await fetch('ajax_handler.php', { method: 'POST', body: formData });
                if (!response.ok) {
                    alert("A server error occurred. Please try again.");
                    return false;
                }
                
                const result = await response.json();

                if (result.status === 'success') {
                    const cartCountElement = document.getElementById('cart-count'); 
                    if (cartCountElement) {
                        cartCountElement.textContent = result.cart_count;
                        cartCountElement.style.display = result.cart_count > 0 ? 'flex' : 'none';
                    }
                    return true;
                } else {
                    alert(result.message || "Could not add item to bag.");
                    return false;
                }
            } catch (error) {
                alert('An unexpected error occurred. Please try again.');
                return false;
            }
        }

        // Profile Card Dropdown Logic
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