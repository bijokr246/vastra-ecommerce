<?php
// search.php (Updated)

// Start the session to access login state and cart data
session_start();
include "config.php";

// Define the path for image uploads for consistency
define('UPLOAD_PATH', 'images/');

// --- UNIFIED HEADER DATA FETCHING ---

// 1. Check user login state and get user's details
$is_logged_in = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
$user_firstname = '';
$user_wishlist = [];
$user_cart_product_ids = []; // NEW: Added to track cart items

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

    // NEW: Fetch all product IDs from the user's cart (database)
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
    // NEW: For guests, get product IDs from the session cart
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        $user_cart_product_ids = array_keys($_SESSION['cart']);
    }
}


// --- SEARCH & SUGGESTIONS LOGIC ---

$search_query = '';
$search_results = [];
$suggested_products = [];
$search_performed = false;

if (isset($_GET['query']) && !empty(trim($_GET['query']))) {
    $search_performed = true;
    $search_query = trim($_GET['query']);
    $search_term = '%' . $search_query . '%';

    // Query for search results
    $sql_search = "SELECT product_id, product_name, price, product_image 
                   FROM products 
                   WHERE (product_name LIKE ? OR product_description LIKE ?) AND status = 'active'";
    
    if ($stmt_search = mysqli_prepare($conn, $sql_search)) {
        mysqli_stmt_bind_param($stmt_search, "ss", $search_term, $search_term);
        mysqli_stmt_execute($stmt_search);
        $result = mysqli_stmt_get_result($stmt_search);
        while ($row = mysqli_fetch_assoc($result)) {
            $search_results[] = $row;
        }
        mysqli_stmt_close($stmt_search);
    }
} else {
    // NEW: Fetch suggested products if no search has been performed
    $sql_suggest = "SELECT product_id, product_name, price, product_image FROM products WHERE status = 'active' ORDER BY RAND() LIMIT 4";
    $result_suggest = mysqli_query($conn, $sql_suggest);
    if ($result_suggest) {
        while ($row = mysqli_fetch_assoc($result_suggest)) {
            $suggested_products[] = $row;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css"> 
    <script src="refresh.js"></script>
    <style>
        /* Styles from your index.php for button consistency */
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

        /* Styles for the search page */
        .search-page-container {
            padding: 40px 20px;
            min-height: 70vh;
        }
        .search-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .search-bar-wrapper { /* NEW: Wrapper for positioning suggestions */
            position: relative;
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
        }
        .search-bar {
            width: 100%;
            display: flex;
            border: 1px solid #ccc;
            border-radius: 50px;
            overflow: hidden;
        }
        .search-bar input {
            flex-grow: 1; border: none; padding: 15px 25px; font-size: 16px; outline: none;
        }
        .search-bar button {
            border: none; background-color: var(--primary); color: white; padding: 0 30px;
            cursor: pointer; font-size: 18px; transition: background-color 0.3s;
        }
        .search-bar button:hover { background-color: #A52A2A; }
        
        /* NEW: Styles for Search Suggestions */
        #search-suggestions {
            display: none; /* Hidden by default */
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            z-index: 999;
            max-height: 250px;
            overflow-y: auto;
        }
        #search-suggestions a {
            display: block;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            text-align: left;
            transition: background-color 0.2s;
        }
        #search-suggestions a:hover {
            background-color: #f5f5f5;
        }

        .results-info {
            text-align: center; margin-top: 30px; font-size: 18px; color: #555;
        }
        .suggestions-title {
            text-align: left; margin-bottom: 20px; font-size: 1.5rem;
        }

        /* Reusing product grid styles from index.css */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        /* Login Modal Styles (Copied from index.php) */
        .modal-overlay {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0;
            width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6);
            justify-content: center; align-items: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-content {
            position: relative; background-color: #fff; padding: 2rem 2.5rem;
            border-radius: 8px; text-align: center; max-width: 400px;
            width: 90%; box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .modal-content .close-button {
            position: absolute; top: 10px; right: 15px; font-size: 1.8rem;
            color: #aaa; cursor: pointer; transition: color 0.3s;
        }
        .modal-content .close-button:hover { color: #333; }
        .modal-content h3 { margin-top: 0; margin-bottom: 1rem; color: #333; }
        .modal-content p { margin-bottom: 1.5rem; color: #666; }
        .btn-modal-login {
            display: inline-block; background-color: var(--primary); color: white;
            padding: 10px 25px; border-radius: 5px; text-decoration: none;
            font-weight: 500; transition: opacity 0.3s;
        }
        .btn-modal-login:hover { opacity: 0.9; }
    </style>
</head>
<body>

    <!-- Navigation (With FULL Profile Card) -->
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

                <!-- UPDATED: Full Profile Card from index.php -->
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

    <!-- Main Search Content -->
    <div class="container search-page-container">
        <div class="search-header">
            <h1>Search for Products</h1>
            <p>Find your favorite clothing and fabrics from our collection.</p>
            <div class="search-bar-wrapper">
                <form action="search.php" method="GET" class="search-bar">
                    <input type="text" name="query" id="search-input" placeholder="Search for shirts, dresses, etc..." value="<?php echo htmlspecialchars($search_query); ?>" autocomplete="off" required>
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
                <!-- NEW: Search Suggestions Container -->
                <div id="search-suggestions"></div>
            </div>
        </div>

        <!-- Display either search results or suggestions -->
        <div class="products-container">
            <?php if ($search_performed): ?>
                <div class="results-info">
                    <?php
                        $result_count = count($search_results);
                        echo $result_count > 0 
                            ? "Showing " . $result_count . " result(s) for '<strong>" . htmlspecialchars($search_query) . "</strong>'"
                            : "No results found for '<strong>" . htmlspecialchars($search_query) . "</strong>'. Try another search term.";
                    ?>
                </div>
                <?php if (!empty($search_results)): ?>
                    <div class="products-grid" data-is-logged-in="<?php echo $is_logged_in ? 'true' : 'false'; ?>">
                        <?php foreach ($search_results as $product): ?>
                            <!-- Product Card HTML (now with correct cart button logic) -->
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
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif (!empty($suggested_products)): ?>
                <!-- NEW: "You Might Like" Section -->
                <h2 class="suggestions-title">You Might Like</h2>
                <div class="products-grid" data-is-logged-in="<?php echo $is_logged_in ? 'true' : 'false'; ?>">
                    <?php foreach ($suggested_products as $product): ?>
                         <!-- Product Card HTML (same structure as above) -->
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
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'footer.html'; ?>

    <!-- NEW: LOGIN PROMPT MODAL (Copied from index.php) -->
    <div id="login-modal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3>Login Required</h3>
            <p>You need to be logged in to add items to your cart or wishlist.</p>
            <a href="login.php" class="btn-modal-login">Login or Sign Up</a>
        </div>
    </div>

    <!-- UPDATED: Full JavaScript from index.php + Search Suggestions logic -->
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

        if(closeModalBtn) closeModalBtn.addEventListener('click', hideLoginModal);
        if(loginModal) loginModal.addEventListener('click', (e) => {
            if (e.target === loginModal) hideLoginModal();
        });
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && loginModal && loginModal.classList.contains('active')) hideLoginModal();
        });

        // --- Product Grids Logic (works for both search results and suggestions) ---
        document.querySelectorAll('.products-grid').forEach(productsGrid => {
            const isLoggedIn = productsGrid.dataset.isLoggedIn === 'true';
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
                if (isWishlistClick) toggleWishlist(productId, e.target);
                if (isAddToCartClick) addToCart(productId, e.target);
            });
        });

        // --- Reusable AJAX Functions (Identical to index.php) ---
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

        // --- NEW: Search Suggestions Logic ---
        const searchInput = document.getElementById('search-input');
        const suggestionsBox = document.getElementById('search-suggestions');

        searchInput.addEventListener('input', async () => {
            const query = searchInput.value.trim();

            if (query.length < 2) { // Only search for 2 or more characters
                suggestionsBox.style.display = 'none';
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'get_search_suggestions');
                formData.append('query', query);

                const response = await fetch('ajax_handler.php', { method: 'POST', body: formData });
                const suggestions = await response.json();

                suggestionsBox.innerHTML = ''; // Clear old suggestions
                if (suggestions.length > 0) {
                    suggestions.forEach(item => {
                        const link = document.createElement('a');
                        // This link submits the form with the suggestion
                        link.href = `search.php?query=${encodeURIComponent(item)}`;
                        link.textContent = item;
                        suggestionsBox.appendChild(link);
                    });
                    suggestionsBox.style.display = 'block';
                } else {
                    suggestionsBox.style.display = 'none';
                }
            } catch (error) {
                console.error('Search suggestion error:', error);
                suggestionsBox.style.display = 'none';
            }
        });

        // Hide suggestions when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-bar-wrapper')) {
                suggestionsBox.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>