<?php
// Start the session
session_start();
include "config.php";

// --- VALIDATE SUBCATEGORY ID ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: index.php");
    exit();
}
$subcategory_id = (int)$_GET['id'];

define('UPLOAD_PATH', 'images/');

// --- User/cart/wishlist logic ---
$is_logged_in = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
$user_firstname = ''; $user_wishlist = []; $user_cart_product_ids = [];
if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $sql_user = "SELECT fname, email FROM users WHERE user_id = ?";
    if ($stmt_user = mysqli_prepare($conn, $sql_user)) {
        mysqli_stmt_bind_param($stmt_user, "i", $user_id); mysqli_stmt_execute($stmt_user);
        $result_user = mysqli_stmt_get_result($stmt_user);
        if ($user_row = mysqli_fetch_assoc($result_user)) {
            $user_firstname = $user_row['fname']; $_SESSION['email'] = $user_row['email'];
        }
        mysqli_stmt_close($stmt_user);
    }
    $sql_wishlist = "SELECT product_id FROM wishlist WHERE user_id = ?";
    if ($stmt_wishlist = mysqli_prepare($conn, $sql_wishlist)) {
        mysqli_stmt_bind_param($stmt_wishlist, "i", $user_id); mysqli_stmt_execute($stmt_wishlist);
        $result_wishlist = mysqli_stmt_get_result($stmt_wishlist);
        while ($wishlist_row = mysqli_fetch_assoc($result_wishlist)) { $user_wishlist[] = $wishlist_row['product_id']; }
        mysqli_stmt_close($stmt_wishlist);
    }
    $sql_cart = "SELECT product_id FROM cart WHERE user_id = ?";
    if ($stmt_cart = mysqli_prepare($conn, $sql_cart)) {
        mysqli_stmt_bind_param($stmt_cart, "i", $user_id); mysqli_stmt_execute($stmt_cart);
        $result_cart = mysqli_stmt_get_result($stmt_cart);
        while ($cart_row = mysqli_fetch_assoc($result_cart)) { $user_cart_product_ids[] = $cart_row['product_id']; }
        mysqli_stmt_close($stmt_cart);
    }
} else {
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        $user_cart_product_ids = array_keys($_SESSION['cart']);
    }
}

// --- PAGE-SPECIFIC DATA FETCHING ---

// 1. Fetch Subcategory and Parent Category details
$subcategory_name = ''; $parent_category_name = ''; $parent_category_id = 0;
$sql_details = "SELECT s.subcategory_name, c.category_id, c.category_name FROM subcategory s JOIN category c ON s.category_id = c.category_id WHERE s.subcategory_id = ?";
if ($stmt_details = mysqli_prepare($conn, $sql_details)) {
    mysqli_stmt_bind_param($stmt_details, "i", $subcategory_id);
    mysqli_stmt_execute($stmt_details);
    $result_details = mysqli_stmt_get_result($stmt_details);
    if ($row = mysqli_fetch_assoc($result_details)) {
        $subcategory_name = $row['subcategory_name'];
        $parent_category_name = $row['category_name'];
        $parent_category_id = $row['category_id'];
    } else { echo "Subcategory not found."; exit(); }
    mysqli_stmt_close($stmt_details);
}

// GET SORT AND FILTER PARAMETERS FROM URL
$sort_order = $_GET['sort'] ?? 'default';
$selected_shops = $_GET['shops'] ?? [];
$selected_price_range = $_GET['price'] ?? '';

// FETCH SHOPS AVAILABLE IN THIS SUBCATEGORY
$shops_for_filter = [];
$sql_shops = "SELECT DISTINCT s.shop_id, s.shop_name FROM shops s JOIN products p ON s.shop_id = p.shop_id WHERE p.subcategory_id = ? AND p.status = 'active' ORDER BY s.shop_name";
if ($stmt_shops = mysqli_prepare($conn, $sql_shops)) {
    mysqli_stmt_bind_param($stmt_shops, "i", $subcategory_id);
    mysqli_stmt_execute($stmt_shops);
    $result_shops = mysqli_stmt_get_result($stmt_shops);
    while ($row = mysqli_fetch_assoc($result_shops)) { $shops_for_filter[] = $row; }
    mysqli_stmt_close($stmt_shops);
}

// DYNAMICALLY BUILD THE PRODUCTS QUERY
$sql_products = "SELECT p.product_id, p.product_name, p.price, p.product_image, p.created_at FROM products p WHERE p.subcategory_id = ? AND p.status = 'active'";
$params = [$subcategory_id];
$types = 'i';

if (!empty($selected_shops) && is_array($selected_shops)) {
    $placeholders = implode(',', array_fill(0, count($selected_shops), '?'));
    $sql_products .= " AND p.shop_id IN ($placeholders)";
    foreach ($selected_shops as $shop_id) { $params[] = $shop_id; $types .= 'i'; }
}
if (!empty($selected_price_range)) {
    $price_parts = explode('-', $selected_price_range);
    if (count($price_parts) == 2) {
        $sql_products .= " AND p.price BETWEEN ? AND ?";
        $params[] = $price_parts[0]; $params[] = $price_parts[1]; $types .= 'dd';
    } elseif (count($price_parts) == 1 && is_numeric($price_parts[0])) {
        $sql_products .= " AND p.price >= ?";
        $params[] = $price_parts[0]; $types .= 'd';
    }
}
switch ($sort_order) {
    case 'price_asc':  $sql_products .= " ORDER BY p.price ASC"; break;
    case 'price_desc': $sql_products .= " ORDER BY p.price DESC"; break;
    case 'newest':     $sql_products .= " ORDER BY p.created_at DESC"; break;
}

$products = [];
if ($stmt_prod = mysqli_prepare($conn, $sql_products)) {
    if(!empty($types)) mysqli_stmt_bind_param($stmt_prod, $types, ...$params);
    mysqli_stmt_execute($stmt_prod);
    $result_products = mysqli_stmt_get_result($stmt_prod);
    while ($row = mysqli_fetch_assoc($result_products)) { $products[] = $row; }
    mysqli_stmt_close($stmt_prod);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($subcategory_name); ?> | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
    <script src="refresh.js"></script>
    <style>
        .btn-add-to-bag, .btn-go-to-cart { width: 100%; padding: 8px 0; border: none; border-radius: 4px; font-weight: 500; cursor: pointer; transition: background-color 0.3s, color 0.3s, opacity 0.3s; text-align: center; display: inline-block; text-decoration: none; font-size: 1rem; color: #333; }
        .btn-add-to-bag { background-color: var(--light-gray); } .btn-add-to-bag:hover { background-color: #e0e0e0; } .btn-add-to-bag:disabled { cursor: not-allowed; background-color: #ccc; }
        .btn-go-to-cart { background-color: var(--primary); color: white; } .btn-go-to-cart:hover { opacity: 0.9; }
        .modal-overlay { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-content { position: relative; background-color: #fff; padding: 2rem 2.5rem; border-radius: 8px; text-align: center; max-width: 400px; width: 90%; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .modal-content .close-button { position: absolute; top: 10px; right: 15px; font-size: 1.8rem; color: #aaa; cursor: pointer; transition: color 0.3s; }
        .modal-content .close-button:hover { color: #333; }
        .modal-content h3 { margin-top: 0; margin-bottom: 1rem; color: #333; } .modal-content p { margin-bottom: 1.5rem; color: #666; }
        .btn-modal-login { display: inline-block; background-color: var(--primary); color: white; padding: 10px 25px; border-radius: 5px; text-decoration: none; font-weight: 500; transition: opacity 0.3s; }
        .breadcrumbs { padding: 1.5rem 0; font-size: 0.9rem; color: #666; }
        .breadcrumbs a { color: var(--primary); text-decoration: none; } .breadcrumbs a:hover { text-decoration: underline; } .breadcrumbs span { margin: 0 0.5rem; }
        .main-content-area { display: flex; gap: 2rem; align-items: flex-start; margin-top: 1rem; }
        .filter-sidebar { flex: 0 0 250px; }
        .filter-section { padding-bottom: 1.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid #eee; }
        .filter-section:last-child { border-bottom: none; margin-bottom: 0; }
        .filter-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem; }
        .filter-options label { display: block; margin-bottom: 0.75rem; cursor: pointer; }
        .filter-options input { margin-right: 0.5rem; }
        .btn-apply-filters { width: 100%; padding: 10px; background-color: var(--primary); color: white; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer; margin-top: 1rem; }
        .reset-filters { display: block; text-align: center; margin-top: 1rem; color: var(--primary); text-decoration: none; }
        .products-area { flex: 1; }
        .products-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .product-count { color: #555; }
        .sort-select { width: 100%; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; }
        @media (max-width: 768px) {
            .main-content-area { flex-direction: column; }
            .filter-sidebar { width: 100%; border-right: none; padding-right: 0; border-bottom: 1px solid #eee; margin-bottom: 2rem; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <!-- ... Paste your entire <nav> block from index.php here ... -->
        <div class="container nav-container">
            <button class="mobile-menu-btn" id="mobile-menu-button"><i class="fas fa-bars"></i></button>
            <a href="index.php" class="logo">VASTRA</a>
            <?php include "nav_links.html"; ?>
            <div class="nav-icons">
                <a href="search.php" title="Search"><i class="fas fa-search"></i></a>
                <a href="wishlist.php" title="Wishlist"><i class="far fa-heart"></i></a>
                <a href="cart.php" class="cart-icon-container" title="Shopping Cart"><i class="fas fa-shopping-bag"></i></a>
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
                                <h4>Welcome</h4><p>To access account and manage orders</p>
                            </div>
                            <a href="login.php" class="btn-login-signup">LOGIN / SIGNUP</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="breadcrumbs">
            <a href="index.php">Home</a><span>/</span>
            <a href="category-products.php?id=<?php echo $parent_category_id; ?>"><?php echo htmlspecialchars($parent_category_name); ?></a><span>/</span>
            <strong><?php echo htmlspecialchars($subcategory_name); ?></strong>
        </div>

        <div class="main-content-area">
            <aside class="filter-sidebar">
                <h1 style="font-size: 1.5rem; margin-top:0; margin-bottom: 2rem;"><?php echo htmlspecialchars($subcategory_name); ?></h1>
                <form id="filter-form" method="GET" action="subcategory-products.php">
                    <input type="hidden" name="id" value="<?php echo $subcategory_id; ?>">
                    <div class="filter-section">
                         <h4 class="filter-title">Sort By</h4>
                         <select name="sort" class="sort-select" onchange="this.form.submit()">
                            <option value="default" <?php if ($sort_order == 'default') echo 'selected'; ?>>Recommended</option>
                            <option value="newest" <?php if ($sort_order == 'newest') echo 'selected'; ?>>Newest First</option>
                            <option value="price_asc" <?php if ($sort_order == 'price_asc') echo 'selected'; ?>>Price: Low to High</option>
                            <option value="price_desc" <?php if ($sort_order == 'price_desc') echo 'selected'; ?>>Price: High to Low</option>
                        </select>
                    </div>
                    <div class="filter-section">
                        <h4 class="filter-title">Price</h4>
                        <div class="filter-options">
                             <label><input type="radio" name="price" value="" onchange="this.form.submit()" <?php if (empty($selected_price_range)) echo 'checked'; ?>> All</label>
                            <label><input type="radio" name="price" value="0-499" onchange="this.form.submit()" <?php if ($selected_price_range == '0-499') echo 'checked'; ?>> Under ₹500</label>
                            <label><input type="radio" name="price" value="500-999" onchange="this.form.submit()" <?php if ($selected_price_range == '500-999') echo 'checked'; ?>> ₹500 - ₹999</label>
                            <label><input type="radio" name="price" value="1000-1999" onchange="this.form.submit()" <?php if ($selected_price_range == '1000-1999') echo 'checked'; ?>> ₹1000 - ₹1999</label>
                            <label><input type="radio" name="price" value="2000" onchange="this.form.submit()" <?php if ($selected_price_range == '2000') echo 'checked'; ?>> ₹2000 & Over</label>
                        </div>
                    </div>
                    <?php if (!empty($shops_for_filter)): ?>
                    <div class="filter-section">
                        <h4 class="filter-title">Shop</h4>
                        <div class="filter-options">
                            <?php foreach ($shops_for_filter as $shop): ?>
                                <label><input type="checkbox" name="shops[]" value="<?php echo $shop['shop_id']; ?>" <?php if (in_array($shop['shop_id'], $selected_shops)) echo 'checked'; ?>> <?php echo htmlspecialchars($shop['shop_name']); ?></label>
                            <?php endforeach; ?>
                        </div>
                         <button type="submit" class="btn-apply-filters">Apply</button>
                    </div>
                    <?php endif; ?>
                    <a href="subcategory-products.php?id=<?php echo $subcategory_id; ?>" class="reset-filters">Reset All</a>
                </form>
            </aside>
            <div class="products-area">
                <div class="products-toolbar"><div class="product-count"><strong>Showing <?php echo count($products); ?> Products</strong></div></div>
                <section class="products-section" style="padding-top: 0;">
                    <div class="products-grid" data-is-logged-in="<?php echo $is_logged_in ? 'true' : 'false'; ?>">
                        <?php if (!empty($products)): foreach ($products as $product): ?>
                            <!-- Product Card HTML is identical to other pages -->
                            <div class="product-card" data-product-id="<?php echo $product['product_id']; ?>">
                                <div class="product-image">
                                    <a href="product-detail.php?id=<?php echo $product['product_id']; ?>"><img src="<?php echo UPLOAD_PATH . htmlspecialchars($product['product_image']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>"></a>
                                    <?php $is_in_wishlist = $is_logged_in && in_array($product['product_id'], $user_wishlist);
                                          $wishlist_class = $is_in_wishlist ? 'fas' : 'far';
                                          $wishlist_color = $is_in_wishlist ? 'style="color: var(--primary);"' : ''; ?>
                                    <i class="<?php echo $wishlist_class; ?> fa-heart wishlist-icon" title="Add to Wishlist" <?php echo $wishlist_color; ?>></i>
                                </div>
                                <div class="product-info">
                                    <h3 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                                    <div class="product-price"><span class="current-price">₹<?php echo htmlspecialchars($product['price']); ?></span></div>
                                    <?php if (in_array($product['product_id'], $user_cart_product_ids)): ?>
                                        <a href="cart.php" class="btn-go-to-cart">Go to Cart</a>
                                    <?php else: ?>
                                        <button class="btn-add-to-bag">Add to Bag</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; else: ?>
                            <p style="grid-column: 1 / -1; text-align: center; color: #666; padding: 3rem 0;">No products match your criteria. Try removing some filters.</p>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </div>
    
    
    <!-- Footer (Copied from index.php) -->
    <?php include 'footer.html'; ?>

    <!-- Login Prompt Modal (Copied from index.php) -->
    <div id="login-modal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3>Login Required</h3>
            <p>You need to be logged in to add items to your cart or wishlist.</p>
            <a href="login.php" class="btn-modal-login">Login or Sign Up</a>
        </div>
    </div>

    <!-- JavaScript (Copied from index.php) -->
    <script>
        // ... Paste your entire <script> block from index.php here ...
        // It is fully reusable and will work without any changes.
    document.addEventListener('DOMContentLoaded', () => {
        const loginModal = document.getElementById('login-modal');
        const closeModalBtn = loginModal.querySelector('.close-button');
        function showLoginModal() { if (loginModal) loginModal.classList.add('active'); }
        function hideLoginModal() { if (loginModal) loginModal.classList.remove('active'); }
        closeModalBtn.addEventListener('click', hideLoginModal);
        loginModal.addEventListener('click', (e) => { if (e.target === loginModal) { hideLoginModal(); } });
        window.addEventListener('keydown', (e) => { if (e.key === 'Escape' && loginModal.classList.contains('active')) { hideLoginModal(); } });
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
                if (isWishlistClick) { toggleWishlist(productId, e.target); }
                if (isAddToCartClick) { addToCart(productId, e.target); }
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