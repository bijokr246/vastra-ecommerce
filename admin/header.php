<?php
// This line is needed at the top of header.php if it's not already there
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
  .sidebar {
    width: 260px;
    background-color: var(--white);
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    display: flex;
    flex-direction: column;
    border-right: 1px solid var(--border-color);
    z-index: 100;
}

.sidebar-header {
    padding: 20px;
    text-align: center;
    font-size: 24px;
    font-weight: 700;
    letter-spacing: 1px;
    flex-shrink: 0; /* Prevents the header from shrinking */
}

.sidebar-header a {
    color: var(--primary);
    text-decoration: none;
}

.sidebar-nav {
    list-style: none;
    flex-grow: 1; /* Allows this list to take up available space */
    margin-top: 20px;
    overflow-y: auto; /* Adds scrollbar ONLY when needed */
}

.sidebar-nav li a {
    display: flex;
    align-items: center;
    padding: 15px 25px;
    color: var(--dark-gray);
    text-decoration: none;
    font-weight: 500;
    transition: background-color 0.3s, color 0.3s;
    border-left: 4px solid transparent;
}

.sidebar-nav li a .fa-fw {
    width: 25px;
    margin-right: 15px;
    font-size: 18px;
    color: #9295a3;
    transition: color 0.3s;
}

.sidebar-nav li a:hover {
    background-color: var(--light-gray);
    color: var(--secondary);
}

.sidebar-nav li a:hover .fa-fw {
    color: var(--primary);
}

.sidebar-nav li a.active {
    background-color: var(--primary-light-bg);
    color: var(--primary);
    font-weight: 600;
    border-left-color: var(--primary);
}

.sidebar-nav li a.active .fa-fw {
    color: var(--primary);
}

/* --- NEW: Pinned Logout Button Styles --- */
.sidebar-logout {
    list-style: none;
    padding: 0;
    margin: 0;
    flex-shrink: 0; /* Prevents the logout from shrinking */
    border-top: 1px solid var(--border-color);
}

.sidebar-logout li a {
    display: flex;
    align-items: center;
    padding: 18px 25px;
    color: var(--dark-gray);
    text-decoration: none;
    font-weight: 600;
    transition: background-color 0.3s, color 0.3s;
}

.sidebar-logout li a .fa-fw {
    width: 25px;
    margin-right: 15px;
    font-size: 18px;
    color: #9295a3;
    transition: color 0.3s;
}

.sidebar-logout li a:hover {
    background-color: #fee2e2; /* Light red background on hover */
    color: var(--danger);
}

.sidebar-logout li a:hover .fa-fw {
    color: var(--danger);
}
</style>

<div class="sidebar-header"><a href="#">VASTRA</a></div>

<!-- Main navigation links (this part will be scrollable) -->
<ul class="sidebar-nav">
  <li><a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt fa-fw"></i> <span>Dashboard</span></a></li>
  <li><a href="manage-users.php" class="<?php echo ($current_page == 'manage-users.php') ? 'active' : ''; ?>"><i class="fas fa-users fa-fw"></i> <span>Manage Users</span></a></li>
  <li><a href="seller-requests.php" class="<?php echo ($current_page == 'seller-requests.php') ? 'active' : ''; ?>"><i class="fas fa-store fa-fw"></i> <span>Seller Requests</span></a></li>
  <li><a href="subscription-plans.php" class="<?php echo ($current_page == 'subscription-plans.php') ? 'active' : ''; ?>"><i class="fas fa-tags fa-fw"></i> <span>Subscription Plans</span></a></li>
  <li><a href="subscription-analytics.php" class="<?php echo ($current_page == 'subscription-analytics.php') ? 'active' : ''; ?>"><i class="fas fa-chart-pie fa-fw"></i> <span>Plan Analytics</span></a></li>
  <li><a href="sales-reports.php" class="<?php echo ($current_page == 'sales-reports.php') ? 'active' : ''; ?>"><i class="fas fa-file-invoice-dollar fa-fw"></i> <span>Sales Reports</span></a></li>
  <li><a href="manage-reports.php" class="<?php echo ($current_page == 'manage-reports.php') ? 'active' : ''; ?>"><i class="fas fa-flag fa-fw"></i> <span>Product Reports</span></a></li>
  <li><a href="manage-products.php" class="<?php echo ($current_page == 'manage-products.php') ? 'active' : ''; ?>"><i class="fas fa-box-open fa-fw"></i> <span>Manage Products</span></a></li>
  <li><a href="manage-categories.php" class="<?php echo ($current_page == 'manage-categories.php') ? 'active' : ''; ?>"><i class="fas fa-sitemap fa-fw"></i> <span>Manage Categories</span></a></li>
  <li><a href="orders.php" class="<?php echo ($current_page == 'orders.php') ? 'active' : ''; ?>"><i class="fas fa-receipt fa-fw"></i> <span>All Orders</span></a></li>
  <li><a href="view-messages.php" class="<?php echo ($current_page == 'view-messages.php' || $current_page == 'message-detail.php') ? 'active' : ''; ?>"><i class="fas fa-envelope fa-fw"></i> <span>User Messages</span></a></li>
  <li><a href="change-password.php" class="<?php echo ($current_page == 'change-password.php') ? 'active' : ''; ?>"><i class="fas fa-key fa-fw"></i> <span>Change Password</span></a></li>
</ul>

<!-- Logout link (this part will be pinned to the bottom) -->
<ul class="sidebar-logout">
  <li>
    <a href="../logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> <span>Logout</span></a>
  </li>
</ul>