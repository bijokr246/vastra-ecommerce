<?php
// Start the session to access login state and cart data
session_start();
include "config.php";


// GLOBAL SELLER SUBSCRIPTION STATUS UPDATER

// Part 1: First, mark any overdue subscriptions as 'expired' in the subscriptions table.
$conn->query("UPDATE seller_subscriptions SET status = 'expired' WHERE status = 'active' AND end_date < NOW()");

// Part 2: Find all sellers who DO NOT have an active subscription plan.
$sql_expired_sellers = "
    SELECT l.user_id 
    FROM login l
    WHERE l.role = 'seller' AND l.user_id NOT IN (
        SELECT user_id FROM seller_subscriptions WHERE status = 'active'
    )
";
$result_expired = $conn->query($sql_expired_sellers);

if ($result_expired && $result_expired->num_rows > 0) {
    // Collect the user IDs of all sellers whose plans have expired.
    $expired_seller_ids = [];
    while ($row = $result_expired->fetch_assoc()) {
        $expired_seller_ids[] = $row['user_id'];
    }
    
    // Create a comma-separated string of IDs for the SQL IN() clause.
    $ids_to_deactivate = implode(',', $expired_seller_ids);

    // Part 3: Update the status of their SHOPS from 'active' to 'expired'.
    // We only change shops that are currently 'active' to avoid overriding a 'banned' status.
    $conn->query("UPDATE shops SET shop_status = 'expired' WHERE user_id IN ($ids_to_deactivate) AND shop_status = 'active'");
    
    // Part 4: Update the status of their PRODUCTS from 'active' to 'expired'.
    // This requires a JOIN with the shops table to find the products by user ID.
    $conn->query("
        UPDATE products p 
        JOIN shops s ON p.shop_id = s.shop_id 
        SET p.status = 'expired' 
        WHERE s.user_id IN ($ids_to_deactivate) AND p.status = 'active'
    ");
}

// Define the path for image uploads for consistency
define('UPLOAD_PATH', 'images/');

// --- DATA FETCHING ---

// 1. Check user login state and get user's details
$is_logged_in = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
$user_firstname = '';
$user_wishlist = [];
$user_cart_product_ids = [];

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];

    // Fetch user's first name
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

    // Fetch all product IDs from the user's wishlist
    $sql_wishlist = "SELECT product_id FROM wishlist WHERE user_id = ?";
    if ($stmt_wishlist = mysqli_prepare($conn, $sql_wishlist)) {
        mysqli_stmt_bind_param($stmt_wishlist, "i", $user_id);
        mysqli_stmt_execute($stmt_wishlist);
        $result_wishlist = mysqli_stmt_get_result($stmt_wishlist);
        while ($wishlist_row = mysqli_fetch_assoc($result_wishlist)) {
            $user_wishlist[] = $wishlist_row['product_id'];
        }
        mysqli_stmt_close($stmt_wishlist);
    }

    // Fetch all product IDs from the user's cart (database)
    $sql_cart = "SELECT product_id FROM cart WHERE user_id = ?";
    if ($stmt_cart = mysqli_prepare($conn, $sql_cart)) {
        mysqli_stmt_bind_param($stmt_cart, "i", $user_id);
        mysqli_stmt_execute($stmt_cart);
        $result_cart = mysqli_stmt_get_result($stmt_cart);
        while ($cart_row = mysqli_fetch_assoc($result_cart)) {
            $user_cart_product_ids[] = $cart_row['product_id'];
        }
        mysqli_stmt_close($stmt_cart);
    }
} else {
    // For guests, get product IDs from the session cart
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        $user_cart_product_ids = array_keys($_SESSION['cart']);
    }
}

// 2. Fetch Categories from the database
$categories = [];
$sql_categories = "SELECT category_id, category_name, img_url FROM category";
$result_categories = mysqli_query($conn, $sql_categories);
if ($result_categories) {
    while ($row = mysqli_fetch_assoc($result_categories)) {
        $categories[] = $row;
    }
}

// 3. Fetch Top Products RANDOMLY from the database
$products = [];
$sql_products = "SELECT product_id, product_name, price, product_image FROM products WHERE status = 'active' ORDER BY RAND() LIMIT 8";
$result_products = mysqli_query($conn, $sql_products);
if ($result_products) {
    while ($row = mysqli_fetch_assoc($result_products)) {
        $products[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
    <script src="refresh.js"></script>
    <style>
        /* --- BUTTON STYLES --- */
        .btn-add-to-bag, .btn-go-to-cart {
            width: 100%; padding: 8px 0; border: none; border-radius: 4px; font-weight: 500;
            cursor: pointer; transition: background-color 0.3s, color 0.3s, opacity 0.3s;
            text-align: center; display: inline-block; text-decoration: none; font-size: 1rem; color: #333;
        }
        .btn-add-to-bag { background-color: var(--light-gray); }
        .btn-add-to-bag:hover { background-color: #e0e0e0; }
        .btn-add-to-bag:disabled { cursor: not-allowed; background-color: #ccc; }
        .btn-go-to-cart { background-color: var(--primary); color: white; }
        .btn-go-to-cart:hover { opacity: 0.9; }

        /* --- NEW: LOGIN MODAL STYLES --- */
        .modal-overlay {
            display: none; /* Hidden by default */
            position: fixed;
            z-index: 1000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            /* Use flexbox to center the modal content */
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.active {
            display: flex; /* Show the modal */
        }
        .modal-content {
            position: relative;
            background-color: #fff;
            padding: 2rem 2.5rem;
            border-radius: 8px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .modal-content .close-button {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 1.8rem;
            color: #aaa;
            cursor: pointer;
            transition: color 0.3s;
        }
        .modal-content .close-button:hover {
            color: #333;
        }
        .modal-content h3 {
            margin-top: 0;
            margin-bottom: 1rem;
            color: #333;
        }
        .modal-content p {
            margin-bottom: 1.5rem;
            color: #666;
        }
        .btn-modal-login {
            display: inline-block;
            background-color: var(--primary);
            color: white;
            padding: 10px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.3s;
        }
        .btn-modal-login:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>

    <!-- Navigation -->
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
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1>A Trusted Community for Quality Fabrics & Fashion</h1>
            <p>Explore collections from hand-picked local textile shops. Vastra connects buyers with quality clothing and fabrics — all under one community brand.</p>
            <a href="products.php" class="btn btn-primary">Shop Now</a>
        </div>
    </section>

    <!-- DYNAMIC CATEGORIES SECTION -->
    <section class="container">
        <h2 class="section-title">Shop By Category</h2>
        <div class="categories-grid">
            <?php if (!empty($categories)): foreach ($categories as $category): ?>
                <a href="category-products.php?id=<?php echo $category['category_id']; ?>" class="category-card">
                    <img src="<?php echo UPLOAD_PATH . htmlspecialchars($category['img_url']); ?>" alt="<?php echo htmlspecialchars($category['category_name']); ?>">
                    <div class="category-overlay">
                        <span><?php echo htmlspecialchars($category['category_name']); ?></span>
                    </div>
                </a>
            <?php endforeach; else: ?>
                <p style="text-align: center; color: #666;">No categories found.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- DYNAMIC PRODUCTS SECTION -->
    <section class="products-section">
        <div class="container">
            <div class="products-header">
                <h2 class="section-title" style="text-align: left; margin: 0;">Top Picks For You</h2>
                <a href="products.php" class="btn btn-view-all">View All</a>
            </div>
            <!-- Pass login state to JS via a data attribute -->
            <div class="products-grid" data-is-logged-in="<?php echo $is_logged_in ? 'true' : 'false'; ?>">
                <?php if (!empty($products)): foreach ($products as $product): ?>
                    <div class="product-card" data-product-id="<?php echo $product['product_id']; ?>">
                        <div class="product-image">
                            <a href="product-detail.php?id=<?php echo $product['product_id']; ?>">
                                <img src="<?php echo UPLOAD_PATH . htmlspecialchars($product['product_image']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                            </a>
                            <?php
                                $is_in_wishlist = $is_logged_in && in_array($product['product_id'], $user_wishlist);
                                $wishlist_class = $is_in_wishlist ? 'fas' : 'far';
                                $wishlist_color = $is_in_wishlist ? 'style="color: var(--primary);"' : '';
                            ?>
                            <i class="<?php echo $wishlist_class; ?> fa-heart wishlist-icon" title="Add to Wishlist" <?php echo $wishlist_color; ?>></i>
                        </div>
                        <div class="product-info">
                            <h3 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                            <div class="product-price">
                                <span class="current-price">₹<?php echo htmlspecialchars($product['price']); ?></span>
                            </div>
                            <?php
                                $is_in_cart = in_array($product['product_id'], $user_cart_product_ids);
                                if ($is_in_cart):
                            ?>
                                <a href="cart.php" class="btn-go-to-cart">Go to Cart</a>
                            <?php else: ?>
                                <button class="btn-add-to-bag">Add to Bag</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <p style="grid-column: 1 / -1; text-align: center; color: #666;">No top picks available at the moment.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'footer.html'; ?>

    <!-- NEW: LOGIN PROMPT MODAL -->
    <div id="login-modal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3>Login Required</h3>
            <p>You need to be logged in to add items to your cart or wishlist.</p>
            <a href="login.php" class="btn-modal-login">Login or Sign Up</a>
        </div>
    </div>


    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- Modal Elements and Logic ---
        const loginModal = document.getElementById('login-modal');
        const closeModalBtn = loginModal.querySelector('.close-button');

        function showLoginModal() {
            if (loginModal) loginModal.classList.add('active');
        }
        function hideLoginModal() {
            if (loginModal) loginModal.classList.remove('active');
        }

        closeModalBtn.addEventListener('click', hideLoginModal);
        // Close modal if user clicks on the dark overlay
        loginModal.addEventListener('click', (e) => {
            if (e.target === loginModal) {
                hideLoginModal();
            }
        });
        // Close modal if user presses 'Escape' key
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && loginModal.classList.contains('active')) {
                hideLoginModal();
            }
        });

        // --- Product Grid Logic ---
        const productsGrid = document.querySelector('.products-grid');
        const isLoggedIn = productsGrid.dataset.isLoggedIn === 'true';

        if (productsGrid) {
            productsGrid.addEventListener('click', (e) => {
                const productCard = e.target.closest('.product-card');
                if (!productCard) return;

                const isWishlistClick = e.target.classList.contains('wishlist-icon');
                const isAddToCartClick = e.target.classList.contains('btn-add-to-bag');
                
                // If the user is NOT logged in and clicks a protected action
                if (!isLoggedIn && (isWishlistClick || isAddToCartClick)) {
                    e.preventDefault(); // Prevent any default action
                    showLoginModal();   // Show the centered login modal
                    return; // Stop further execution
                }
                
                // If we reach here, the user is logged in. Proceed with actions.
                const productId = productCard.dataset.productId;
                if (isWishlistClick) {
                    toggleWishlist(productId, e.target);
                }
                if (isAddToCartClick) {
                    addToCart(productId, e.target);
                }
            });
        }

        async function toggleWishlist(productId, iconElement) {
            const formData = new FormData();
            formData.append('action', 'toggle_wishlist');
            formData.append('product_id', productId);
            try {
                const response = await fetch('ajax_handler.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.status === 'success') {
                    if (result.action === 'added') {
                        iconElement.classList.replace('far', 'fas');
                        iconElement.style.color = 'var(--primary)';
                    } else {
                        iconElement.classList.replace('fas', 'far');
                        iconElement.style.color = '';
                    }
                } else { alert(result.message); }
            } catch (error) { console.error('Wishlist Error:', error); alert('An error occurred.'); }
        }

        async function addToCart(productId, buttonElement) {
            buttonElement.disabled = true;
            buttonElement.textContent = 'Adding...';
            const formData = new FormData();
            formData.append('action', 'add_to_cart');
            formData.append('product_id', productId);

            try {
                const response = await fetch('ajax_handler.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.status === 'success') {
                    const goToCartLink = document.createElement('a');
                    goToCartLink.href = 'cart.php';
                    goToCartLink.className = 'btn-go-to-cart';
                    goToCartLink.textContent = 'Go to Cart';
                    buttonElement.parentNode.replaceChild(goToCartLink, buttonElement);
                } else { throw new Error(result.message || 'An unknown error occurred.'); }
            } catch (error) {
                console.error('Cart Error:', error);
                alert('Error: ' + error.message);
                buttonElement.disabled = false;
                buttonElement.textContent = 'Add to Bag';
            }
        }

        // --- Profile Card Toggle Logic ---
        const profileCardContainer = document.querySelector('.profile-card-container');
        const profileCard = document.getElementById('profile-card');
        if (profileCardContainer && profileCard) {
            profileCardContainer.addEventListener('click', (event) => {
                // Stop the click from propagating to the document
                event.stopPropagation();
                if (event.target.id === 'user-greeting' || event.target.closest('#user-icon-btn')) {
                    profileCard.classList.toggle('active');
                }
            });
        }
        document.addEventListener('click', (event) => {
            if (profileCard && profileCard.classList.contains('active')) {
                // If the click is outside the profile card container, close it
                if (!profileCardContainer.contains(event.target)) {
                    profileCard.classList.remove('active');
                }
            }
        });
    });
    </script>
</body>
</html>