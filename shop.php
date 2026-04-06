<?php
// shops.php (Updated)

session_start();
include "config.php";

// --- UNIFIED HEADER DATA FETCHING (Copied from index.php) ---

define('UPLOAD_PATH', 'images/'); // Assuming you might use this constant

// 1. Check user login state and get user's details
$is_logged_in = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
$user_firstname = '';

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
            // Store email in session for consistency across pages
            $_SESSION['email'] = $user_row['email'];
        }
        mysqli_stmt_close($stmt_user);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore Shops | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
    <style>
        .shop-page-container { padding: 40px 20px; min-height: 70vh; }
        .page-header { text-align: center; margin-bottom: 20px; }
        .page-header h1 { font-size: 2.5rem; }
        .page-header p { font-size: 1.1rem; color: #666; margin-top: 10px; }

        /* --- NEW: Search Bar Styles (from search.php) --- */
        .search-bar-wrapper {
            position: relative;
            width: 100%;
            max-width: 600px;
            margin: 0 auto 40px; /* Added bottom margin */
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
        #shop-search-suggestions {
            display: none; position: absolute; top: 100%; left: 0; right: 0;
            background: white; border: 1px solid #ddd; border-top: none;
            border-radius: 0 0 8px 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            z-index: 999; max-height: 250px; overflow-y: auto;
        }
        #shop-search-suggestions a {
            display: block; padding: 12px 20px; color: #333;
            text-decoration: none; text-align: left; transition: background-color 0.2s;
        }
        #shop-search-suggestions a:hover { background-color: #f5f5f5; }

        /* --- Shop Grid & Card Styles (Unchanged) --- */
        .shops-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 30px; }
        .shop-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; transition: 0.3s; }
        .shop-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
        .shop-card img { width: 100%; height: 200px; object-fit: cover; }
        .shop-card-content { padding: 15px; flex: 1; display: flex; flex-direction: column; }
        .shop-card-content h3 { margin: 0 0 5px; color: var(--primary, #444); }
        .shop-location { color: #888; font-size: 0.9em; margin-bottom: 10px; }
        .shop-description { flex-grow: 1; margin-bottom: 15px; color: #666; font-size: 0.95em; line-height: 1.5; }
        .card-actions { display: flex; gap: 10px; margin-top: auto; }
        .btn-read-more, .btn-visit-shop { flex: 1; text-align: center; padding: 10px; border-radius: 5px; text-decoration: none; transition: 0.3s; cursor: pointer; border: none; font-size: 0.9em; }
        .btn-read-more { background: #555; color: #fff; }
        .btn-visit-shop { background: var(--primary); color: #fff; }
        .btn-read-more:hover { background: #444; }
        .btn-visit-shop:hover { opacity: 0.9; }

        /* --- Modal Styles (Unchanged) --- */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.7); display: flex; align-items: center;
            justify-content: center; z-index: 1000; opacity: 0; visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-content {
            background: #fff; padding: 30px; border-radius: 10px; width: 90%; max-width: 600px;
            max-height: 90vh; overflow-y: auto; position: relative; transform: scale(0.9);
            transition: transform 0.3s ease;
        }
        .modal-overlay.active .modal-content { transform: scale(1); }
        .modal-close { position: absolute; top: 10px; right: 15px; font-size: 2rem; color: #888; cursor: pointer; }
        .modal-content h2 { margin-top: 0; }
        .modal-content img { width: 100%; height: 250px; object-fit: cover; border-radius: 5px; margin-bottom: 15px; }
        .modal-full-description { line-height: 1.7; color: #555; }
    </style>
</head>
<body>
    <!-- Navigation Bar with FULL Profile Card -->
    <nav class="navbar">
        <div class="container nav-container">
            <button class="mobile-menu-btn" id="mobile-menu-button"><i class="fas fa-bars"></i></button>
            <a href="index.php" class="logo">VASTRA</a>
            <?php include "nav_links.html"; ?>
            <div class="nav-icons">
                <a href="search.php" title="Search"><i class="fas fa-search"></i></a>
                <a href="wishlist.php" title="Wishlist"><i class="far fa-heart"></i></a>
                <a href="cart.php" class="cart-icon-container" title="Shopping Cart"><i class="fas fa-shopping-bag"></i></a>
                
                <!-- NEW: Full Profile Card from index.php -->
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

    <!-- Page Content -->
    <div class="container shop-page-container">
        <div class="page-header">
            <h1>Explore Our Shops</h1>
            <p>Discover unique collections from our talented sellers.</p>
        </div>

        <!-- NEW: Upgraded Search Bar -->
        <div class="search-bar-wrapper">
            <form id="shop-search-form" class="search-bar" action="" method="GET">
                <input type="text" id="shop-search-input" name="q" placeholder="Search by shop name or location..." autocomplete="off">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
            <div id="shop-search-suggestions"></div>
        </div>

        <div id="shopsGrid" class="shops-grid">
            <!-- Shops will be loaded here via AJAX -->
        </div>
    </div>

    <!-- Modal HTML Structure (Unchanged) -->
    <div class="modal-overlay" id="shopModal">
        <div class="modal-content">
            <span class="modal-close" id="modalCloseBtn">&times;</span>
            <img src="" alt="Shop Image" id="modalShopImage">
            <h2 id="modalShopName"></h2>
            <p><i class="fas fa-map-marker-alt"></i> <span id="modalShopAddress"></span></p>
            <p class="modal-full-description" id="modalShopDescription"></p>
        </div>
    </div>

    <?php include "footer.html"; ?>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const searchForm = document.getElementById("shop-search-form");
        const searchInput = document.getElementById("shop-search-input");
        const suggestionsBox = document.getElementById("shop-search-suggestions");
        const shopsGrid = document.getElementById("shopsGrid");
        
        // Modal elements
        const modal = document.getElementById("shopModal");
        const modalCloseBtn = document.getElementById("modalCloseBtn");
        const modalShopImage = document.getElementById("modalShopImage");
        const modalShopName = document.getElementById("modalShopName");
        const modalShopAddress = document.getElementById("modalShopAddress");
        const modalShopDescription = document.getElementById("modalShopDescription");

        // Function to fetch and display the grid of shops
        async function fetchShops(query = "") {
            shopsGrid.innerHTML = '<p style="text-align:center; grid-column: 1 / -1;">Loading shops...</p>'; 
            try {
                const response = await fetch(`ajax_handler_shops.php?action=get_shop_grid&q=${encodeURIComponent(query)}`);
                if (!response.ok) throw new Error('Network response was not ok');
                const html = await response.text();
                shopsGrid.innerHTML = html;
            } catch (error) {
                console.error("Fetch error:", error);
                shopsGrid.innerHTML = '<p style="text-align:center; grid-column: 1 / -1;">Error loading shops. Please try again.</p>';
            }
        }

        // Initial load of all shops
        fetchShops();

        // Handle form submission to filter shops
        searchForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const query = searchInput.value.trim();
            fetchShops(query);
            suggestionsBox.style.display = 'none'; // Hide suggestions on submit
        });

        // --- NEW: Live Search Suggestions Logic ---
        searchInput.addEventListener("input", async () => {
            const query = searchInput.value.trim();
            if (query.length < 2) {
                suggestionsBox.style.display = 'none';
                return;
            }
            try {
                const response = await fetch(`ajax_handler_shops.php?action=get_shop_suggestions&q=${encodeURIComponent(query)}`);
                const suggestions = await response.json();
                suggestionsBox.innerHTML = '';
                if (suggestions.length > 0) {
                    suggestions.forEach(shopName => {
                        const link = document.createElement('a');
                        link.href = '#'; // Let the click event handle it
                        link.textContent = shopName;
                        link.addEventListener('click', (e) => {
                            e.preventDefault();
                            searchInput.value = shopName;
                            suggestionsBox.style.display = 'none';
                            fetchShops(shopName); // Immediately filter
                        });
                        suggestionsBox.appendChild(link);
                    });
                    suggestionsBox.style.display = 'block';
                } else {
                    suggestionsBox.style.display = 'none';
                }
            } catch (error) {
                console.error('Suggestion fetch error:', error);
            }
        });

        // Hide suggestions when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-bar-wrapper')) {
                suggestionsBox.style.display = 'none';
            }
        });

        // --- Modal Logic (Unchanged but still essential) ---
        function openModal(card) {
            modalShopImage.src = card.dataset.shopImage;
            modalShopName.textContent = card.dataset.shopName;
            modalShopAddress.textContent = card.dataset.shopAddress;
            modalShopDescription.textContent = card.dataset.fullDescription;
            modal.classList.add("active");
        }
        function closeModal() { modal.classList.remove("active"); }
        shopsGrid.addEventListener("click", function(event) {
            if (event.target.classList.contains("btn-read-more")) {
                const card = event.target.closest(".shop-card");
                if (card) openModal(card);
            }
        });
        modalCloseBtn.addEventListener("click", closeModal);
        modal.addEventListener("click", (event) => { if (event.target === modal) closeModal(); });

        // --- Profile Card Toggle Logic (from index.php) ---
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