<?php
// Start the session to access login state and cart data
session_start();
include "config.php";

// Define the path for image uploads for consistency
define('UPLOAD_PATH', 'images/');

// --- NEW: GET FILTER & SORT PARAMETERS ---
$sort_option = $_GET['sort'] ?? 'newest';
$price_filter = $_GET['price'] ?? 'all';


// --- DATA FETCHING ---

// 1. Check user login state and get user's details (reusing logic from index.php)
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

// --- MODIFIED: DYNAMICALLY BUILD THE PRODUCTS QUERY ---
$base_sql = "SELECT product_id, product_name, price, product_image, created_at FROM products WHERE status = 'active'";
$params = [];       // Array to hold query parameters
$param_types = '';  // String to hold parameter types for bind_param

// Add Price Filtering conditions
switch ($price_filter) {
    case '0-499':
        $base_sql .= " AND price BETWEEN ? AND ?";
        $params[] = 0; $params[] = 499;
        $param_types .= 'ii';
        break;
    case '500-999':
        $base_sql .= " AND price BETWEEN ? AND ?";
        $params[] = 500; $params[] = 999;
        $param_types .= 'ii';
        break;
    case '1000-1999':
        $base_sql .= " AND price BETWEEN ? AND ?";
        $params[] = 1000; $params[] = 1999;
        $param_types .= 'ii';
        break;
    case '2000+':
        $base_sql .= " AND price >= ?";
        $params[] = 2000;
        $param_types .= 'i';
        break;
}

// Add Sorting conditions
switch ($sort_option) {
    case 'price_asc':
        $base_sql .= " ORDER BY price ASC";
        break;
    case 'price_desc':
        $base_sql .= " ORDER BY price DESC";
        break;
    case 'newest':
    default:
        $base_sql .= " ORDER BY created_at DESC";
        break;
}

// Prepare and execute the final, dynamic query
$products = [];
if ($stmt_products = mysqli_prepare($conn, $base_sql)) {
    // Bind parameters only if there are any to bind
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt_products, $param_types, ...$params);
    }
    mysqli_stmt_execute($stmt_products);
    $result_products = mysqli_stmt_get_result($stmt_products);
    while ($row = mysqli_fetch_assoc($result_products)) {
        $products[] = $row;
    }
    mysqli_stmt_close($stmt_products);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Products | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
    <script src="refresh.js"></script>
    <style>
        /* Re-using styles from your index/category page for consistency */
        .page-header {
            text-align: center;
            padding: 2rem 1rem;
            background-color: #f9f9f9;
            border-bottom: 1px solid #eee;
        }
        .page-header h1 {
            margin: 0;
            font-size: 2.5rem;
            color: #333;
        }
        .products-section {
            padding: 0 0 3rem 0; /* Adjusted padding */
        }

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

        /* --- LOGIN MODAL STYLES --- */
        .modal-overlay { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-content { position: relative; background-color: #fff; padding: 2rem 2.5rem; border-radius: 8px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .modal-content .close-button { position: absolute; top: 10px; right: 15px; font-size: 1.8rem; color: #aaa; cursor: pointer; transition: color 0.3s; }
        .modal-content .close-button:hover { color: #333; }
        .modal-content h3 { margin-top: 0; margin-bottom: 1rem; color: #333; }
        .modal-content p { margin-bottom: 1.5rem; color: #666; }
        .btn-modal-login { display: inline-block; background-color: var(--primary); color: white; padding: 10px 25px; border-radius: 5px; text-decoration: none; font-weight: 500; transition: opacity 0.3s; }
        .btn-modal-login:hover { opacity: 0.9; }

        /* --- NEW: FILTER & SORT BAR STYLES --- */
        .filter-sort-bar {
            background-color: #fff;
            padding: 15px 0; /* Adjusted padding */
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .filter-group, .sort-group { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .filter-group strong, .sort-group label { font-weight: 600; color: #333; font-size: 0.95rem; }
        .filter-group .price-options { display: flex; gap: 15px; align-items: center; }
        .filter-group .price-options label { font-weight: 400; cursor: pointer; display: flex; align-items: center; gap: 5px; }
        .sort-group select {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #fff;
            font-size: 1rem;
            cursor: pointer;
        }
        .product-count {
            font-size: 1rem;
            color: #555;
            font-weight: 500;
        }
        .no-products-message {
            grid-column: 1 / -1; /* Make it span all columns in the grid */
            text-align: center;
            color: #666;
            padding: 60px 20px;
            font-size: 1.2rem;
            border: 2px dashed #ddd;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>

    <!-- Navigation (Copied from index.php) -->
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

    <!-- Page Header -->
    <header class="page-header">
        <h1>All Products</h1>
    </header>

    <!-- DYNAMIC PRODUCTS SECTION -->
    <section class="products-section">
        <div class="container">
            <!-- NEW: FILTER & SORT FORM -->
            <form action="" method="get" id="filter-sort-form" class="filter-sort-bar">
                <div class="filter-group">
                    <strong>Filter by Price:</strong>
                    <div class="price-options">
                        <label><input type="radio" name="price" value="all" <?php if ($price_filter == 'all') echo 'checked'; ?>> All</label>
                        <label><input type="radio" name="price" value="0-499" <?php if ($price_filter == '0-499') echo 'checked'; ?>> ₹0-499</label>
                        <label><input type="radio" name="price" value="500-999" <?php if ($price_filter == '500-999') echo 'checked'; ?>> ₹500-999</label>
                        <label><input type="radio" name="price" value="1000-1999" <?php if ($price_filter == '1000-1999') echo 'checked'; ?>> ₹1000-1999</label>
                        <label><input type="radio" name="price" value="2000+" <?php if ($price_filter == '2000+') echo 'checked'; ?>> ₹2000+</label>
                    </div>
                </div>
                
                <div class="product-count">
                    Showing <?php echo count($products); ?> Product(s)
                </div>

                <div class="sort-group">
                    <label for="sort-select">Sort by:</label>
                    <select name="sort" id="sort-select">
                        <option value="newest" <?php if ($sort_option == 'newest') echo 'selected'; ?>>Newest</option>
                        <option value="price_asc" <?php if ($sort_option == 'price_asc') echo 'selected'; ?>>Price: Low to High</option>
                        <option value="price_desc" <?php if ($sort_option == 'price_desc') echo 'selected'; ?>>Price: High to Low</option>
                    </select>
                </div>
            </form>

            <!-- Product Grid -->
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
                    <div class="no-products-message">
                        <p>No products found matching your criteria. Try adjusting the filters!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer (Copied from index.php) -->
    <?php include 'footer.html'; ?>

    <!-- LOGIN PROMPT MODAL (Copied from index.php) -->
    <div id="login-modal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3>Login Required</h3>
            <p>You need to be logged in to add items to your cart or wishlist.</p>
            <a href="login.php" class="btn-modal-login">Login or Sign Up</a>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- NEW: AUTO-SUBMIT FORM ON FILTER/SORT CHANGE ---
        const filterForm = document.getElementById('filter-sort-form');
        if (filterForm) {
            const formInputs = filterForm.querySelectorAll('input[type="radio"], select');
            formInputs.forEach(input => {
                input.addEventListener('change', () => {
                    filterForm.submit();
                });
            });
        }

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
        loginModal.addEventListener('click', (e) => {
            if (e.target === loginModal) hideLoginModal();
        });
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && loginModal.classList.contains('active')) hideLoginModal();
        });

        // --- Product Grid Logic (Wishlist, Add to Cart) ---
        const productsGrid = document.querySelector('.products-grid');
        const isLoggedIn = productsGrid.dataset.isLoggedIn === 'true';

        if (productsGrid) {
            productsGrid.addEventListener('click', (e) => {
                const productCard = e.target.closest('.product-card');
                if (!productCard) return;

                const isWishlistClick = e.target.classList.contains('wishlist-icon');
                const isAddToCartClick = e.target.classList.contains('btn-add-to-bag');
                
                if (!isLoggedIn && (isWishlistClick || isAddToCartClick)) {
                    e.preventDefault();
                    showLoginModal();
                    return;
                }
                
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