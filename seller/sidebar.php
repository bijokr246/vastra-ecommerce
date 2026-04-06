<?php
// =================================================================================
// VASTRA SELLER SIDEBAR - ALL-IN-ONE AUTHENTICATION & NAVIGATION
// =================================================================================
// IMPORTANT: This file MUST be included at the VERY TOP of your seller PHP files
// (e.g., dashboard.php, my-products.php) BEFORE any HTML is outputted.
// It now handles:
// 1. Session start and Database connection.
// 2. Automatically updating expired subscriptions.
// 3. Checking if the user has an active subscription.
// 4. Redirecting users with expired plans from restricted pages.
// 5. Calculating pending report counts for notifications.
// =================================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . "/../config.php";

// ---  Basic Authentication & User ID ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ../seller-login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);


$is_subscribed = false; // Assume not subscribed by default
$stmt_check_sub = $conn->prepare("SELECT 1 FROM seller_subscriptions WHERE user_id = ? AND status = 'active' LIMIT 1");
$stmt_check_sub->bind_param("i", $user_id);
$stmt_check_sub->execute();
$stmt_check_sub->store_result();
if ($stmt_check_sub->num_rows > 0) {
    $is_subscribed = true;
}
$stmt_check_sub->close();

// --- Enforce Page Access Rules ---
// Define pages that are ALWAYS accessible, even with an expired plan.
$allowed_pages = ['dashboard.php', 'subscription.php', 'logout.php', 'checkout.php', 'payment_handler.php'];

// If the subscription is expired AND the user is trying to access a restricted page...
if (!$is_subscribed && !in_array($current_page, $allowed_pages)) {
    // ...redirect them to the subscription page to renew.
    $_SESSION['error_message'] = "Your plan has expired. Please renew to access this page.";
    header('Location: subscription.php');
    exit();
}

// --- Calculate Pending Report Count (Your Original Code) ---
$pending_reports_count = 0;
if (isset($_SESSION['user_id'])) {
    $seller_id = $_SESSION['user_id']; // Using $seller_id as in your original code
    $stmt_report_count = $conn->prepare("
        SELECT COUNT(pr.report_id) as report_count
        FROM product_reports pr
        JOIN products p ON pr.product_id = p.product_id
        JOIN shops s ON p.shop_id = s.shop_id
        WHERE s.user_id = ? AND pr.status = 'pending'
    ");
    if ($stmt_report_count) {
        $stmt_report_count->bind_param("i", $seller_id);
        $stmt_report_count->execute();
        $result = $stmt_report_count->get_result();
        if ($result) {
            $pending_reports_count = $result->fetch_assoc()['report_count'] ?? 0;
        }
        $stmt_report_count->close();
    }
}
?>
<style>
/* This CSS class will be applied to disabled sidebar links */
.sidebar ul li a.disabled {
    color: #888;           /* Grey out the text */
    pointer-events: none;  /* Make the link unclickable */
    cursor: not-allowed;   /* Show a "not allowed" mouse cursor on hover */
}
/* Optional: Prevents any hover effect on disabled links */
.sidebar ul li a.disabled:hover {
    background-color: transparent;
}
/* Your existing styles for the notification badge and icons */
.notification-badge {
    background-color: #dc3545; color: white; font-size: 11px; font-weight: 600;
    border-radius: 50%; padding: 2px 6px; position: relative; margin-left: 8px; vertical-align: middle;
}
.sidebar ul li a i {
    width: 20px; text-align: center; margin-right: 10px;
}
</style>

<h2>VASTRA</h2>
<ul>
  <!-- Dashboard: Always enabled -->
  <li>
    <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
        <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
    </a>
  </li>

  <!-- The following links will be disabled if subscription has expired -->
  <li>
    <a href="<?php echo $is_subscribed ? 'my-products.php' : '#'; ?>" 
       class="<?php echo ($current_page == 'my-products.php') ? 'active' : ''; ?> <?php if (!$is_subscribed) echo 'disabled'; ?>">
        <i class="fas fa-box"></i><span>My Products</span>
    </a>
  </li>
  <li>
    <a href="<?php echo $is_subscribed ? 'add-product.php' : '#'; ?>" 
       class="<?php echo ($current_page == 'add-product.php') ? 'active' : ''; ?> <?php if (!$is_subscribed) echo 'disabled'; ?>">
        <i class="fas fa-plus-circle"></i><span>Add Product</span>
    </a>
  </li>
  <li>
    <a href="<?php echo $is_subscribed ? 'orders.php' : '#'; ?>" 
       class="<?php echo ($current_page == 'orders.php') ? 'active' : ''; ?> <?php if (!$is_subscribed) echo 'disabled'; ?>">
        <i class="fas fa-clipboard-list"></i><span>Orders</span>
    </a>
  </li>
  <li>
    <a href="<?php echo $is_subscribed ? 'sales-report.php' : '#'; ?>" 
       class="<?php echo ($current_page == 'sales-report.php') ? 'active' : ''; ?> <?php if (!$is_subscribed) echo 'disabled'; ?>">
        <i class="fas fa-chart-line"></i><span>Sales Report</span>
    </a>
  </li>
  <li>
    <a href="<?php echo $is_subscribed ? 'product-reports.php' : '#'; ?>" 
       class="<?php echo ($current_page == 'product-reports.php') ? 'active' : ''; ?> <?php if (!$is_subscribed) echo 'disabled'; ?>">
      <i class="fas fa-flag"></i><span>Product Reports</span>
      <?php if ($pending_reports_count > 0): ?>
        <span class="notification-badge"><?php echo $pending_reports_count; ?></span>
      <?php endif; ?>
    </a>
  </li>

  <!-- My Subscription: Always enabled -->
  <li>
    <a href="subscription.php" class="<?php echo ($current_page == 'subscription.php') ? 'active' : ''; ?>">
      <i class="fas fa-star"></i> My Subscription
    </a>
  </li>

  <!-- More links to disable -->
  <li>
    <a href="<?php echo $is_subscribed ? 'my-shops.php' : '#'; ?>" 
       class="<?php echo ($current_page == 'my-shops.php') ? 'active' : ''; ?> <?php if (!$is_subscribed) echo 'disabled'; ?>">
        <i class="fas fa-store"></i><span>My Shops</span>
    </a>
  </li>
  <li>
    <a href="<?php echo $is_subscribed ? 'change-password.php' : '#'; ?>" 
       class="<?php echo ($current_page == 'change-password.php') ? 'active' : ''; ?> <?php if (!$is_subscribed) echo 'disabled'; ?>">
        <i class="fas fa-key"></i><span>Change Password</span>
    </a>
  </li>
  <li>
    <a href="<?php echo $is_subscribed ? 'close-seller-account.php' : '#'; ?>" 
       class="<?php echo ($current_page == 'close-seller-account.php') ? 'active' : ''; ?> <?php if (!$is_subscribed) echo 'disabled'; ?>">
        <i class="fas fa-store-slash"></i><span>Close Seller Account</span>
    </a>
  </li>
  
  <!-- Logout: Always enabled -->
  <li>
    <a href="../logout.php">
        <i class="fas fa-sign-out-alt"></i><span>Logout</span>
    </a>
  </li>
</ul>