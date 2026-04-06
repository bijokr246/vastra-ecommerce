<?php
// Start the session to access login state and cart data
session_start();
include "config.php";

// Define the path for image uploads for consistency
define('UPLOAD_PATH', 'images/');

// --- INITIAL DATA FETCHING AND VALIDATION ---

// 1. Get and validate the Product ID from the URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;
$reviews_summary = ['average_rating' => 0, 'total_reviews' => 0, 'rating_count' => 0];
$reviews_list = [];

if ($product_id > 0) {
    // 2. Fetch the specific product's details along with the shop name
    $sql_product = "SELECT 
                        p.product_id, p.product_name, p.product_description, 
                        p.price, p.quantity_available, p.product_image,
                        p.status, s.shop_name
                    FROM products p
                    JOIN shops s ON p.shop_id = s.shop_id
                    WHERE p.product_id = ?";

    if ($stmt_product = mysqli_prepare($conn, $sql_product)) {
        mysqli_stmt_bind_param($stmt_product, "i", $product_id);
        mysqli_stmt_execute($stmt_product);
        $result_product = mysqli_stmt_get_result($stmt_product);
        $product = mysqli_fetch_assoc($result_product);
        mysqli_stmt_close($stmt_product);
        
        if ($product && $product['status'] !== 'active') {
            $product = null; 
        }
    }

    // --- FETCH PRODUCT REVIEWS AND RATINGS ---
    if ($product) {
        // Query to get average rating, total reviews count, and unique rating count
        $sql_reviews_summary = "SELECT 
                                    AVG(rating) as average_rating, 
                                    COUNT(review_id) as total_reviews,
                                    COUNT(DISTINCT user_id) as rating_count
                                FROM product_reviews 
                                WHERE product_id = ? AND status = 'approved'";
        
        if ($stmt_summary = mysqli_prepare($conn, $sql_reviews_summary)) {
            mysqli_stmt_bind_param($stmt_summary, "i", $product_id);
            mysqli_stmt_execute($stmt_summary);
            $result_summary = mysqli_stmt_get_result($stmt_summary);
            $summary_data = mysqli_fetch_assoc($result_summary);
            if ($summary_data && $summary_data['total_reviews'] > 0) {
                $reviews_summary = $summary_data;
            }
            mysqli_stmt_close($stmt_summary);
        }

        // Query to get the list of all approved reviews with user names
        $sql_reviews_list = "SELECT 
                                r.rating, r.review_text, r.created_at, u.fname 
                             FROM product_reviews r
                             JOIN users u ON r.user_id = u.user_id
                             WHERE r.product_id = ? AND r.status = 'approved'
                             ORDER BY r.created_at DESC";

        if ($stmt_reviews = mysqli_prepare($conn, $sql_reviews_list)) {
            mysqli_stmt_bind_param($stmt_reviews, "i", $product_id);
            mysqli_stmt_execute($stmt_reviews);
            $result_reviews = mysqli_stmt_get_result($stmt_reviews);
            while ($row = mysqli_fetch_assoc($result_reviews)) {
                $reviews_list[] = $row;
            }
            mysqli_stmt_close($stmt_reviews);
        }
    }
}

// --- Helper function to generate star ratings ---
function generate_stars($rating) {
    $stars_html = '';
    $full_stars = floor($rating);
    $half_star = $rating - $full_stars >= 0.5;
    $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);

    for ($i = 0; $i < $full_stars; $i++) $stars_html .= '<i class="fas fa-star"></i>';
    if ($half_star) $stars_html .= '<i class="fas fa-star-half-alt"></i>';
    for ($i = 0; $i < $empty_stars; $i++) $stars_html .= '<i class="far fa-star"></i>';
    
    return $stars_html;
}


// 3. Check user login state and get user's details
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

    // Fetch wishlist and cart data
    $sql_wishlist = "SELECT product_id FROM wishlist WHERE user_id = ?";
    if ($stmt_wishlist = mysqli_prepare($conn, $sql_wishlist)) {
        mysqli_stmt_bind_param($stmt_wishlist, "i", $user_id);
        mysqli_stmt_execute($stmt_wishlist);
        $result_wishlist = mysqli_stmt_get_result($stmt_wishlist);
        while ($wishlist_row = mysqli_fetch_assoc($result_wishlist)) $user_wishlist[] = $wishlist_row['product_id'];
        mysqli_stmt_close($stmt_wishlist);
    }
    
    $sql_cart = "SELECT product_id FROM cart WHERE user_id = ?";
    if ($stmt_cart = mysqli_prepare($conn, $sql_cart)) {
        mysqli_stmt_bind_param($stmt_cart, "i", $user_id);
        mysqli_stmt_execute($stmt_cart);
        $result_cart = mysqli_stmt_get_result($stmt_cart);
        while ($cart_row = mysqli_fetch_assoc($result_cart)) $user_cart_product_ids[] = $cart_row['product_id'];
        mysqli_stmt_close($stmt_cart);
    }
} else {
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) $user_cart_product_ids = array_keys($_SESSION['cart']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product ? htmlspecialchars($product['product_name']) : 'Product Not Found'; ?> | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
    <script src="refresh.js"></script>
    <style>
        /* --- Product Detail Specific Styles --- */
        .product-detail-section{padding:3rem 1rem;background-color:#f9f9f9}.product-detail-container{display:flex;flex-wrap:wrap;gap:2rem;max-width:1200px;margin:0 auto;background-color:#fff;padding:2rem;border-radius:8px;box-shadow:0 4px 15px rgba(0,0,0,.05)}.product-image-gallery{flex:1 1 400px}.product-image-gallery img{width:100%;height:auto;border-radius:8px;border:1px solid #e0e0e0;}.product-details-content{flex:1 1 500px}.product-details-content .product-name{font-size:2.5rem;font-weight:600;margin-top:0;margin-bottom:.5rem;line-height:1.2}.product-details-content .shop-name{font-size:1.1rem;color:#555;margin-bottom:1.5rem}.product-details-content .shop-name a{color:var(--primary);text-decoration:none;font-weight:500}.product-details-content .shop-name a:hover{text-decoration:underline}.product-details-content .price{font-size:2.2rem;font-weight:700;color:#222;margin-bottom:1.5rem}.product-details-content .description-wrapper{margin-bottom:2rem}.description-wrapper h3{margin-bottom:.5rem;font-size:1.2rem}.description-wrapper p{font-size:1rem;line-height:1.7;color:#444;margin:0}.product-actions{display:flex;gap:1rem;align-items:stretch}.btn-add-to-bag,.btn-go-to-cart{padding:14px 28px;border:none;border-radius:5px;font-weight:600;cursor:pointer;transition:all .3s ease;text-align:center;text-decoration:none;font-size:1.1rem;flex-grow:1;display:flex;align-items:center;justify-content:center}.btn-add-to-bag{background-color:var(--primary);color:#fff}.btn-add-to-bag:hover{opacity:.85;transform:translateY(-2px)}.btn-add-to-bag:disabled{background-color:#b0b0b0;cursor:not-allowed;transform:none}.btn-go-to-cart{background-color:#28a745;color:#fff}.btn-go-to-cart:hover{opacity:.9}.btn-wishlist{background-color:#f0f0f0;border:1px solid #ddd;border-radius:5px;font-size:1.5rem;cursor:pointer;color:#333;padding:0 18px;display:flex;align-items:center;justify-content:center;transition:all .3s ease}.btn-wishlist:hover{background-color:#e0e0e0}.btn-wishlist .fas.fa-heart{color:var(--primary)}
        .not-found-container{text-align:center;padding:5rem 1rem}.not-found-container i{font-size:5rem;color:#e0e0e0;margin-bottom:1.5rem}.not-found-container h1{font-size:2.5rem;margin-bottom:1rem}.not-found-container p{color:#666;font-size:1.1rem;margin-bottom:2rem}
        .modal-overlay{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background-color:rgba(0,0,0,.6);justify-content:center;align-items:center}.modal-overlay.active{display:flex}.modal-content{position:relative;background-color:#fff;padding:2rem 2.5rem;border-radius:8px;text-align:center;max-width:400px;width:90%;box-shadow:0 5px 15px rgba(0,0,0,.3)}.modal-content .close-button{position:absolute;top:10px;right:15px;font-size:1.8rem;color:#aaa;cursor:pointer;transition:color .3s}.modal-content .close-button:hover{color:#333}.modal-content h3{margin-top:0;margin-bottom:1rem;color:#333}.modal-content p{margin-bottom:1.5rem;color:#666}.btn-modal-login{display:inline-block;background-color:var(--primary);color:#fff;padding:10px 25px;border-radius:5px;text-decoration:none;font-weight:500;transition:opacity .3s}.btn-modal-login:hover{opacity:.9}
        /* --- RATINGS AND REVIEWS SECTION STYLES --- */
        .reviews-section{max-width:1200px;margin:2rem auto 0;padding:2rem;background-color:#fff;border-radius:8px;box-shadow:0 4px 15px rgba(0,0,0,.05)}.reviews-section h2{font-size:1.8rem;margin-bottom:1.5rem;padding-bottom:1rem;border-bottom:1px solid #e0e0e0}.reviews-summary{display:flex;align-items:center;gap:1.5rem;margin-bottom:2rem;flex-wrap:wrap}.reviews-summary .average-rating-value{font-size:2.5rem;font-weight:700}.reviews-summary .rating-stars{font-size:1.3rem;color:#f39c12}.reviews-summary .reviews-count{font-size:1rem;color:#555}.reviews-list{margin-top:1.5rem}.review-item{border-bottom:1px solid #eee;padding:1.5rem 0}.review-item:last-child{border-bottom:none}.review-header{display:flex;align-items:center;gap:1rem;margin-bottom:.5rem}.review-header .reviewer-name{font-weight:600}.review-header .review-stars{color:#f39c12}.review-date{font-size:.9rem;color:#888;margin-bottom:.8rem}.review-text{font-size:1rem;line-height:1.6;color:#333}.no-reviews{text-align:center;padding:2rem;color:#777}
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container nav-container">
            <button class="mobile-menu-btn" id="mobile-menu-button"><i class="fas fa-bars"></i></button>
            <a href="index.php" class="logo">VASTRA</a>
            <?php include 'nav_links.html'; ?>
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
                            <div class="profile-header"><h4>Welcome</h4><p>To access account and manage orders</p></div>
                            <a href="login.php" class="btn-login-signup">LOGIN / SIGNUP</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- MAIN PRODUCT DETAIL SECTION -->
    <main class="product-detail-section">
        <div class="container">
            <?php if ($product): ?>
                <div class="product-detail-container" data-is-logged-in="<?php echo $is_logged_in ? 'true' : 'false'; ?>" data-product-id="<?php echo $product['product_id']; ?>">
                    <div class="product-image-gallery">
                        <img src="<?php echo UPLOAD_PATH . htmlspecialchars($product['product_image']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                    </div>
                    <div class="product-details-content">
                        <h1 class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                        <p class="shop-name">Sold by: <a href="#"><?php echo htmlspecialchars($product['shop_name']); ?></a></p>
                        <div class="price">₹<?php echo htmlspecialchars(number_format($product['price'])); ?></div>
                        <div class="description-wrapper">
                            <h3>Description</h3>
                            <p><?php echo nl2br(htmlspecialchars($product['product_description'])); ?></p>
                        </div>
                        <div class="product-actions">
                            <?php
                                $is_in_cart = in_array($product['product_id'], $user_cart_product_ids);
                                if ($product['quantity_available'] <= 0) {
                                    echo '<button class="btn-add-to-bag" disabled><i class="fas fa-times-circle fa-fw"></i> Out of Stock</button>';
                                } elseif ($is_in_cart) {
                                    echo '<a href="cart.php" class="btn-go-to-cart"><i class="fas fa-shopping-bag fa-fw"></i> Go to Cart</a>';
                                } else {
                                    echo '<button class="btn-add-to-bag"><i class="fas fa-plus fa-fw"></i> Add to Bag</button>';
                                }
                                $is_in_wishlist = $is_logged_in && in_array($product['product_id'], $user_wishlist);
                                $wishlist_class = $is_in_wishlist ? 'fas' : 'far';
                            ?>
                            <button class="btn-wishlist" title="Add to Wishlist"><i class="<?php echo $wishlist_class; ?> fa-heart"></i></button>
                        </div>
                    </div>
                </div>

                <!-- RATINGS AND REVIEWS SECTION -->
                <section class="reviews-section">
                    <h2>Ratings & Reviews</h2>
                    <?php if ($reviews_summary['total_reviews'] > 0): ?>
                        <div class="reviews-summary">
                            <div class="average-rating-value"><?php echo number_format($reviews_summary['average_rating'], 1); ?></div>
                            <div>
                                <div class="rating-stars"><?php echo generate_stars($reviews_summary['average_rating']); ?></div>
                                <div class="reviews-count">
                                    <?php echo (int)$reviews_summary['rating_count']; ?> Ratings & 
                                    <?php echo (int)$reviews_summary['total_reviews']; ?> Reviews
                                </div>
                            </div>
                        </div>

                        <div class="reviews-list">
                            <?php foreach ($reviews_list as $review): ?>
                                <div class="review-item">
                                    <div class="review-header">
                                        <div class="review-stars"><?php echo generate_stars($review['rating']); ?></div>
                                        <span class="reviewer-name"><?php echo htmlspecialchars($review['fname']); ?></span>
                                    </div>
                                    <div class="review-date"><?php echo date('F d, Y', strtotime($review['created_at'])); ?></div>
                                    <p class="review-text"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-reviews">
                            <p>This product has no reviews yet. Be the first to review!</p>
                        </div>
                    <?php endif; ?>
                </section>

            <?php else: ?>
                <div class="not-found-container">
                    <i class="fas fa-search-minus"></i>
                    <h1>Product Not Found</h1>
                    <p>Sorry, the product you are looking for does not exist or has been removed.</p>
                    <a href="products.php" class="btn btn-primary">Continue Shopping</a>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include 'footer.html'; ?>

    <!-- LOGIN PROMPT MODAL -->
    <div id="login-modal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h3>Login Required</h3>
            <p>You need to be logged in for this action.</p>
            <a href="login.php" class="btn-modal-login">Login or Sign Up</a>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const detailContainer = document.querySelector('.product-detail-container');
        if (!detailContainer) return;

        // --- COMMON MODAL LOGIC ---
        const loginModal = document.getElementById('login-modal');
        function showLoginModal() { if (loginModal) loginModal.classList.add('active'); }
        function hideLoginModal() { if (loginModal) loginModal.classList.remove('active'); }
        if (loginModal) {
            loginModal.querySelector('.close-button').addEventListener('click', hideLoginModal);
            loginModal.addEventListener('click', (e) => { if (e.target === loginModal) hideLoginModal(); });
        }
        
        // --- PRODUCT ACTION LOGIC ---
        const isLoggedIn = detailContainer.dataset.isLoggedIn === 'true';
        const productId = detailContainer.dataset.productId;

        detailContainer.addEventListener('click', (e) => {
            const isWishlistClick = e.target.closest('.btn-wishlist');
            const isAddToCartClick = e.target.closest('.btn-add-to-bag');
            if (!isLoggedIn && (isWishlistClick || isAddToCartClick)) {
                e.preventDefault();
                showLoginModal();
                return;
            }
            if (isWishlistClick) toggleWishlist(productId, isWishlistClick.querySelector('i'));
            if (isAddToCartClick && !isAddToCartClick.disabled) addToCart(productId, isAddToCartClick);
        });
        
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
                    } else {
                        iconElement.classList.replace('fas', 'far');
                    }
                } else { alert(result.message); }
            } catch (error) { console.error('Wishlist Error:', error); alert('An error occurred.'); }
        }

        async function addToCart(productId, buttonElement) {
            buttonElement.disabled = true;
            buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
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
                    goToCartLink.innerHTML = '<i class="fas fa-shopping-bag fa-fw"></i> Go to Cart';
                    
                    const wishlistButton = buttonElement.parentElement.querySelector('.btn-wishlist');
                    const actionsContainer = buttonElement.parentElement;

                    actionsContainer.innerHTML = ''; // Clear container
                    actionsContainer.appendChild(goToCartLink);
                    actionsContainer.appendChild(wishlistButton); // Re-add wishlist button
                } else { throw new Error(result.message || 'An unknown error occurred.'); }
            } catch (error) {
                console.error('Cart Error:', error);
                alert('Error: ' + error.message);
                buttonElement.disabled = false;
                buttonElement.innerHTML = '<i class="fas fa-plus fa-fw"></i> Add to Bag';
            }
        }

        // --- PROFILE CARD TOGGLE LOGIC ---
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