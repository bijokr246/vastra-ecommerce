<?php
// Start the session to access login state
session_start();
include "config.php";

// --- SECURITY CHECK ---
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// --- DATA FETCHING ---
$user_id = $_SESSION['user_id'];
$user_firstname = '';
$user_email = '';
$user_phone = '';
$user_type = ''; // To store if user is 'buyer' or 'seller'
$is_logged_in = true;

// 1. Get Logged-In User's Details
$sql_user = "SELECT u.fname, CONCAT(fname, ' ', lname) full_name, u.email, u.phone, l.role 
             FROM users u
             JOIN login l ON u.user_id = l.user_id
             WHERE u.user_id = ?";

if ($stmt_user = mysqli_prepare($conn, $sql_user)) {
    mysqli_stmt_bind_param($stmt_user, "i", $user_id);
    mysqli_stmt_execute($stmt_user);
    $result_user = mysqli_stmt_get_result($stmt_user);
    if ($user_row = mysqli_fetch_assoc($result_user)) {
        $user_firstname = $user_row['fname'];
        $user_fullname = $user_row['full_name'];
        $user_email = $user_row['email'];
        $user_phone = $user_row['phone'];
        $user_type = $user_row['role'];
        $_SESSION['email'] = $user_row['email'];
    }
    mysqli_stmt_close($stmt_user);
}

// --- CHANGED HERE (1 of 2): Updated the SQL query to fetch the new columns ---
// 2. Fetch ALL of the User's Addresses
$user_addresses = [];
$sql_addresses = "SELECT recipient_fname, recipient_lname, mobile_number, house_address, landmark, locality_or_town, district, pincode, state, address_type, is_default 
                  FROM addresses 
                  WHERE user_id = ? 
                  ORDER BY is_default DESC, address_id ASC";
// OLD QUERY: SELECT recipient_name, ...

if ($stmt_addresses = mysqli_prepare($conn, $sql_addresses)) {
    mysqli_stmt_bind_param($stmt_addresses, "i", $user_id);
    mysqli_stmt_execute($stmt_addresses);
    $result_addresses = mysqli_stmt_get_result($stmt_addresses);
    while ($address_row = mysqli_fetch_assoc($result_addresses)) {
        $user_addresses[] = $address_row;
    }
    mysqli_stmt_close($stmt_addresses);
}

// --- NEW CHANGE: 3. Check for a pending seller request ---
$has_pending_request = false;
if ($user_type === 'buyer') {
    $sql_check_request = "SELECT request_id FROM seller_requests WHERE user_id = ? AND status = 'pending'";
    if ($stmt_check = mysqli_prepare($conn, $sql_check_request)) {
        mysqli_stmt_bind_param($stmt_check, "i", $user_id);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $has_pending_request = true;
        }
        mysqli_stmt_close($stmt_check);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css"> 
    <style>
        /* --- DASHBOARD STYLES (Existing) --- */
        :root { --error: #c0392b; }
        body { background-color: var(--light-gray); }
        .dashboard-container { padding-top: 40px; padding-bottom: 60px; }
        .dashboard-header { text-align: center; margin-bottom: 40px; }
        .dashboard-header h1 { font-size: 2.5rem; color: var(--secondary); margin-bottom: 5px; }
        .dashboard-header p { font-size: 1.1rem; color: var(--dark-gray); }
        .dashboard-grid { display: grid; grid-template-columns: 1fr; gap: 25px; }
        .dashboard-card { background-color: var(--white); border-radius: 8px; padding: 25px 30px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); border: 1px solid #eee; }
        .dashboard-card-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .dashboard-card-header h2 { font-size: 1.25rem; color: var(--secondary); margin: 0; }
        .btn-manage { font-size: 0.9rem; color: var(--primary); text-decoration: none; font-weight: 500; }
        .btn-manage:hover { text-decoration: underline; }
        .quick-actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; }
        .quick-action-btn { padding: 12px 15px; text-decoration: none; background-color: #fafafa; border: 1px solid #eee; border-radius: 8px; color: var(--secondary); font-weight: 500; text-align: center; transition: all 0.2s ease-in-out; }
        .quick-action-btn:hover { transform: translateY(-3px); box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08); border-color: var(--primary); color: var(--primary); }
        
        /* --- NEW CHANGE: Styles for the disabled button --- */
        .quick-action-btn.disabled {
            background-color: #f5f5f5;
            color: #999;
            cursor: not-allowed;
            border-color: #eee;
        }
        .quick-action-btn.disabled:hover {
            transform: none;
            box-shadow: none;
            color: #999;
        }

        /* Profile Details Section */
        .profile-details p { font-size: 1rem; color: var(--dark-gray); margin-bottom: 18px; line-height: 1.6; }
        .profile-details p strong { color: var(--secondary); font-weight: 600; display: inline-block; width: 100px; }
        /* Address List Section */
        .address-list-container { display: flex; flex-direction: row; flex-wrap: wrap; gap: 20px; }
        .address-item { position: relative; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; line-height: 1.7; transition: all 0.3s; flex-basis: 100%; }
        @media (min-width: 768px) { .address-item { flex-basis: calc(50% - 10px); } }
        .address-item.is-default-address { border: 2px solid var(--primary); box-shadow: 0 0 10px rgba(var(--primary-rgb), 0.1); }
        .address-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .recipient-name { font-weight: 600; color: var(--secondary); font-size: 1.05rem; }
        .address-type-label { font-size: 0.75rem; font-weight: 600; padding: 3px 10px; border-radius: 12px; text-transform: uppercase; }
        .address-type-label.home { background-color: #e3f2fd; color: #1e88e5; }
        .address-type-label.office { background-color: #fff3e0; color: #fb8c00; }
        .address-body .address-line { display: block; color: var(--dark-gray); }
        .address-body .address-phone { margin-top: 10px; font-weight: 500; color: var(--secondary); }
        .address-body .address-phone i { margin-right: 8px; color: var(--dark-gray); }
        .default-badge { position: absolute; top: -1px; right: 20px; background-color: var(--primary); color: var(--white); padding: 4px 12px; font-size: 0.8rem; font-weight: 500; border-radius: 0 0 8px 8px; }
        .no-address-message { text-align: center; padding: 20px 0; color: var(--dark-gray); width: 100%; }
        .no-address-message p { margin-bottom: 20px; }
        .btn-add-address { display: inline-block; background-color: var(--secondary); color: var(--white); padding: 10px 25px; text-decoration: none; border-radius: 5px; font-weight: 500; transition: opacity 0.3s; }
        .btn-add-address:hover { opacity: 0.9; }
        /* Account Actions Section */
        .action-links a { display: flex; align-items: center; padding: 15px; margin: 0 -15px; text-decoration: none; color: var(--secondary); border-radius: 6px; transition: background-color 0.2s, color 0.2s; font-weight: 500; }
        .action-links a:hover { background-color: var(--light-gray); color: var(--primary); }
        .action-links i { margin-right: 15px; width: 20px; text-align: center; color: var(--dark-gray); }
        .action-separator { border-top: 1px solid #eee; margin: 10px 0; }
        .action-links a.logout-link { color: var(--primary); }
        .action-links a.logout-link i { color: var(--primary); }
        .action-links a.deactivate-link { color: var(--error); }
        .action-links a.deactivate-link i { color: var(--error); }
        .action-links a.deactivate-link:hover { background-color: #ffebee; color: #b71c1c; }
        /* Message Box Styles */
        .message-box { padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; font-weight: 500; }
        .message-box.success { background-color: #e8f5e9; border-color: #81c784; color: #2e7d32; }
        .message-box.info { background-color: #e3f2fd; border-color: #64b5f6; color: #1565c0; }
    </style>
</head>
<body>

    <nav class="navbar">
        <!-- Your Navbar HTML -->
        <div class="container nav-container">
            <button class="mobile-menu-btn" id="mobile-menu-button"><i class="fas fa-bars"></i></button>
            <a href="index.php" class="logo">VASTRA</a>
            <?php include "nav_links.html"; ?>
            <div class="nav-icons">
                <a href="search.php" title="Search"><i class="fas fa-search"></i></a>
                <a href="wishlist.php" title="Wishlist"><i class="far fa-heart"></i></a>
                <a href="cart.php" title="Shopping Cart"><i class="fas fa-shopping-bag"></i></a>
                <div class="profile-card-container">
                    <a href="#" id="user-icon-btn"><i class="far fa-user"></i></a>
                    <span id="user-greeting" class="user-greeting-text">Hello, <?php echo htmlspecialchars($user_firstname); ?></span>
                    <div id="profile-card" class="profile-card">
                        <div class="profile-header"><h4>Hello, <?php echo htmlspecialchars($user_firstname); ?></h4><p><?php echo htmlspecialchars($user_email); ?></p></div>
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

    <!-- MAIN DASHBOARD CONTENT -->
    <main class="container dashboard-container">
        <header class="dashboard-header">
            <h1>Welcome, <?php echo htmlspecialchars($user_firstname); ?>!</h1>
            <p>From here you can manage your orders, addresses, and other account settings.</p>
        </header>

        <?php
        // Display success/info messages if they exist in the session
        if (isset($_SESSION['success_message'])) {
            echo '<div class="message-box success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['info_message'])) {
            echo '<div class="message-box info">' . htmlspecialchars($_SESSION['info_message']) . '</div>';
            unset($_SESSION['info_message']);
        }
        ?>

        <div class="dashboard-grid">
            
            <div class="dashboard-card">
                <div class="quick-actions-grid">
                    <a href="wishlist.php" class="quick-action-btn">My Wishlist</a>
                    <a href="cart.php" class="quick-action-btn">My Cart</a>
                    <a href="myorders.php" class="quick-action-btn">My Orders</a>

                    <?php if ($user_type === 'buyer'): ?>
                        <?php if ($has_pending_request): ?>
                            <span class="quick-action-btn disabled" title="Your application is under review.">
                                Application Pending
                            </span>
                        <?php else: ?>
                            <a href="dashboard/become-seller.php" class="quick-action-btn">Become a Seller</a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <!-- End of change -->

                </div>
            </div>

            <!-- PROFILE DETAILS CARD -->
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h2>Profile Details</h2>
                    <a href="dashboard/edit-profile.php" class="btn-manage">Edit</a>
                </div>
                <div class="profile-details">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($user_fullname); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user_email); ?></p>
                    <p><strong>Phone:</strong> <?php echo !empty($user_phone) ? htmlspecialchars($user_phone) : '<span style="color: #999;">Not Provided</span>'; ?></p>
                </div>
            </div>

            <!-- ALL ADDRESSES CARD -->
            <div class="dashboard-card">
                 <div class="dashboard-card-header">
                    <h2>My Addresses</h2>
                    <a href="dashboard/manage-addresses.php" class="btn-manage">Add / Manage</a>
                </div>
                <div class="address-list-container">
                    <?php if (!empty($user_addresses)): ?>
                        <?php foreach ($user_addresses as $address): ?>
                            <div class="address-item <?php if ($address['is_default']) echo 'is-default-address'; ?>">
                                <?php if ($address['is_default']) echo '<div class="default-badge">Default</div>'; ?>
                                <div class="address-header">
                                    <p class="recipient-name"><?php echo htmlspecialchars($address['recipient_fname'] . ' ' . $address['recipient_lname']); ?></p>

                                    <span class="address-type-label <?php echo strtolower($address['address_type']); ?>"><?php echo htmlspecialchars($address['address_type']); ?></span>
                                </div>
                                <div class="address-body">
                                    <span class="address-line"><?php echo htmlspecialchars($address['house_address']); ?></span>
                                    <?php if (!empty($address['landmark'])): ?><span class="address-line">Landmark: <?php echo htmlspecialchars($address['landmark']); ?></span><?php endif; ?>
                                    <span class="address-line"><?php echo htmlspecialchars($address['locality_or_town']); ?>, <?php echo htmlspecialchars($address['district']); ?></span>
                                    <span class="address-line"><?php echo htmlspecialchars($address['state']); ?> - <?php echo htmlspecialchars($address['pincode']); ?></span>
                                    <p class="address-phone"><i class="fas fa-mobile-alt"></i> <?php echo htmlspecialchars($address['mobile_number']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-address-message">
                            <p>You haven't added any shipping addresses yet.</p>
                            <a href="dashboard/manage-addresses.php" class="btn-add-address">Add New Address</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ACCOUNT ACTIONS CARD -->
            <div class="dashboard-card">
                 <div class="dashboard-card-header">
                    <h2>Account Actions</h2>
                </div>
                <div class="action-links">
                    <a href="dashboard/change-password.php"><i class="fas fa-key fa-fw"></i> Change Password</a>
                    <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt fa-fw"></i> Logout</a>
                    <div class="action-separator"></div>
                    <a href="dashboard/deactivate.php" class="deactivate-link">
                        <i class="fas fa-user-slash fa-fw"></i> 
                        Deactivate Account
                    </a>
                </div>
            </div>

        </div>
    </main>
    
    <?php include 'footer.html'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
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