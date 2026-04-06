<?php
// This single file now handles session, DB connection, auth, and subscription checks.
// It defines key variables like $user_id, $conn, $is_subscribed, $is_expired, etc.
require_once 'seller_init.php';

// --- All variables are now available from seller_init.php ---

$low_stock_threshold = 5;

// --- 1. CALCULATE STATS FOR DASHBOARD CARDS ---
// a) Total Products
$stmt_total = $conn->prepare("SELECT COUNT(p.product_id) as total_count FROM products p JOIN shops s ON p.shop_id = s.shop_id WHERE s.user_id = ?");
$stmt_total->bind_param("i", $user_id);
$stmt_total->execute();
$total_products = $stmt_total->get_result()->fetch_assoc()['total_count'] ?? 0;
$stmt_total->close();

// b) Low Stock Products
$stmt_low_stock = $conn->prepare("SELECT COUNT(p.product_id) as low_stock_count FROM products p JOIN shops s ON p.shop_id = s.shop_id WHERE s.user_id = ? AND p.quantity_available > 0 AND p.quantity_available <= ?");
$stmt_low_stock->bind_param("ii", $user_id, $low_stock_threshold);
$stmt_low_stock->execute();
$low_stock_products = $stmt_low_stock->get_result()->fetch_assoc()['low_stock_count'] ?? 0;
$stmt_low_stock->close();

// c) Total Orders
$stmt_orders = $conn->prepare("SELECT COUNT(DISTINCT oi.order_id) as order_count FROM order_items oi JOIN products p ON oi.product_id = p.product_id JOIN shops s ON p.shop_id = s.shop_id WHERE s.user_id = ?");
$stmt_orders->bind_param("i", $user_id);
$stmt_orders->execute();
$total_orders = $stmt_orders->get_result()->fetch_assoc()['order_count'] ?? 0;
$stmt_orders->close();

// d) Pending Product Reports
$stmt_reports = $conn->prepare("SELECT COUNT(pr.report_id) as report_count FROM product_reports pr JOIN products p ON pr.product_id = p.product_id JOIN shops s ON p.shop_id = s.shop_id WHERE s.user_id = ? AND pr.status = 'pending'");
$stmt_reports->bind_param("i", $user_id);
$stmt_reports->execute();
$pending_reports = $stmt_reports->get_result()->fetch_assoc()['report_count'] ?? 0;
$stmt_reports->close();


// --- 2. FETCH DATA FOR TABLES AND ALERTS ---
// a) Recent Products for the table
$stmt_recent = $conn->prepare("SELECT p.product_name, c.category_name, p.price, p.status FROM products p JOIN shops s ON p.shop_id = s.shop_id JOIN category c ON p.category_id = c.category_id WHERE s.user_id = ? ORDER BY p.created_at DESC LIMIT 5");
$stmt_recent->bind_param("i", $user_id);
$stmt_recent->execute();
$recent_products_result = $stmt_recent->get_result();

// b) Low stock products for the alert modal
$stmt_alerts = $conn->prepare("SELECT p.product_name, p.quantity_available FROM products p JOIN shops s ON p.shop_id = s.shop_id WHERE s.user_id = ? AND p.quantity_available <= ? AND p.status = 'active' ORDER BY p.quantity_available ASC");
$stmt_alerts->bind_param("ii", $user_id, $low_stock_threshold);
$stmt_alerts->execute();
$stock_alerts = $stmt_alerts->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_alerts->close();

// Determine if the stock alert popup should be shown
$show_stock_alert_popup = !isset($_SESSION['stockAlertDismissed']) && count($stock_alerts) > 0;

// c) Fetch user's name for the welcome message
$stmt_user = $conn->prepare("SELECT CONCAT(fname, ' ', lname) full_name FROM users WHERE user_id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_info = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Seller Dashboard | Vastra</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <script src="../refresh.js"></script>
  <style>
    /* General Styles */
    a.card-link { text-decoration: none; color: inherit; }
    .card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
    .card .sub-text { font-size: 0.85rem; color: #6c757d; margin-top: 5px; }

    /* Status & Card Colors */
    .status.active { background: #d4edda; color: #155724; }
    .status.inactive, .status.banned { background: #f8d7da; color: #721c24; }
    .card.warning { background-color: #fff3cd; border-left: 5px solid #ffeeba; }
    .card.warning i { color: #856404; }
    .card.danger { background-color: #f8d7da; border-left: 5px solid #f5c6cb; }
    .card.danger i { color: #721c24; }

    /* Modal Styles */
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); z-index: 1000; display: none; justify-content: center; align-items: center; }
    .modal-overlay.show { display: flex; }
    .alert-modal { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); width: 90%; max-width: 500px; position: relative; text-align: center; }
    .alert-modal .close-btn { position: absolute; top: 10px; right: 15px; font-size: 24px; color: #aaa; cursor: pointer; border: none; background: none; }
    .alert-modal .close-btn:hover { color: #333; }
    .alert-modal h2 { margin-top: 0; margin-bottom: 15px; font-size: 1.8rem; }
    .alert-modal h2.warning-icon { color: #e67e22; }
    .alert-modal h2.danger-icon { color: #c0392b; }
    .alert-modal p { font-size: 1rem; color: #555; margin-bottom: 20px; }
    .alert-modal ul { list-style-type: none; padding: 0; max-height: 150px; overflow-y: auto; text-align: left; border: 1px solid #eee; border-radius: 5px; margin-bottom: 25px; }
    .alert-modal li { padding: 8px 12px; border-bottom: 1px solid #eee; }
    .stock-count { float: right; font-weight: bold; padding: 2px 8px; border-radius: 12px; font-size: 0.8em; }
    .stock-count.low { background: #f1c40f; color: #333; }
    .stock-count.out { background: #e74c3c; color: #fff; }
  </style>
</head>
<body>
  <div class="sidebar"><?php require "sidebar.php"; ?></div>

  <div class="main">
    <div class="header">
      <h1>Welcome, <?php echo htmlspecialchars(explode(' ', $user_info['full_name'])[0]); ?></h1>
    </div>

    <div class="card-container">
        <!-- Subscription Alert Card (shows only for warnings or expiry) -->
        <?php if ($show_expiry_warning || $is_expired): ?>
            <a href="subscription.php" class="card-link">
                <div class="card <?php echo $is_expired ? 'danger' : 'warning'; ?>">
                    <i class="fas fa-hourglass-half"></i>
                    <h3>Subscription Alert</h3>
                    <p>
                        <?php if ($is_expired): ?>
                            Expired
                        <?php else: ?>
                            Expires in <?php echo $days_remaining; ?> day<?php echo ($days_remaining > 1) ? 's' : ''; ?>
                        <?php endif; ?>
                    </p>
                </div>
            </a>
        <?php endif; ?>

        <!-- Current Subscription Card -->
        <a href="subscription.php" class="card-link">
            <div class="card">
                <i class="fas fa-star"></i>
                <h3>Current Plan</h3>
                <?php if ($subscription_info): ?>
                    <p><?php echo htmlspecialchars($subscription_info['plan_name']); ?></p>
                    <p class="sub-text">Ends: <?php echo date('M d, Y', strtotime($subscription_info['end_date'])); ?></p>
                <?php else: ?>
                    <p>No Active Plan</p>
                    <p class="sub-text">Please subscribe</p>
                <?php endif; ?>
            </div>
        </a>

        <!-- Other Statistic Cards -->
        <a href="my-products.php" class="card-link"><div class="card"><i class="fas fa-box"></i><h3>Total Products</h3><p><?php echo $total_products; ?></p></div></a>
        <a href="my-products.php?filter_status=low_stock" class="card-link"><div class="card warning"><i class="fas fa-triangle-exclamation"></i><h3>Low Stock</h3><p><?php echo $low_stock_products; ?></p></div></a>
        <a href="orders.php" class="card-link"><div class="card"><i class="fas fa-receipt"></i><h3>Orders Received</h3><p><?php echo $total_orders; ?></p></div></a>
        <a href="product-reports.php" class="card-link"><div class="card"><i class="fas fa-flag"></i><h3>Pending Reports</h3><p><?php echo $pending_reports; ?></p></div></a>
    </div>

    <div class="content">
      <h2>Recent Products</h2>
      <table>
        <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Status</th></tr></thead>
        <tbody>
          <?php if ($recent_products_result->num_rows > 0): ?>
            <?php while ($product = $recent_products_result->fetch_assoc()): ?>
              <tr>
                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                <td>₹<?php echo htmlspecialchars(number_format($product['price'])); ?></td>
                <td><span class="status <?php echo strtolower(htmlspecialchars($product['status'])); ?>"><?php echo htmlspecialchars(ucfirst($product['status'])); ?></span></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="4" style="text-align: center;">No products found. <a href="add-product.php">Add your first product!</a></td></tr>
          <?php endif; ?>
          <?php $recent_products_result->close(); ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Stock Alert Modal -->
  <div class="modal-overlay" id="stock-alert-overlay">
      <div class="alert-modal">
          <button class="close-btn" id="close-stock-modal">&times;</button>
          <h2 class="warning-icon"><i class="fas fa-exclamation-triangle"></i> Inventory Alert</h2>
          <p>Some of your products are running low on stock. Please update them to avoid losing sales.</p>
          <ul id="stock-alert-list"></ul>
          <a href="my-products.php?filter_status=low_stock" class="btn btn-primary"><i class="fas fa-edit"></i> Manage Products</a>
      </div>
  </div>

  <!-- Subscription Expiry Modal -->
  <div class="modal-overlay" id="expiry-alert-overlay">
      <div class="alert-modal">
          <!-- The close button is only rendered if the plan is NOT expired -->
          <?php if (!$is_expired): ?>
              <button class="close-btn" id="close-expiry-modal">&times;</button>
          <?php endif; ?>
          
          <h2 class="<?php echo $is_expired ? 'danger-icon' : 'warning-icon'; ?>"><i class="fas fa-clock"></i> Subscription Alert</h2>
          
          <?php if ($is_expired): ?>
              <p>Your subscription has <strong>expired</strong>. Please renew now to restore full access to your seller account.</p>
          <?php elseif ($show_expiry_warning && $subscription_info): ?>
              <p>Your <strong><?php echo htmlspecialchars($subscription_info['plan_name']); ?></strong> plan is expiring in <strong><?php echo $days_remaining; ?> day<?php echo ($days_remaining > 1) ? 's' : ''; ?></strong>. Please renew to continue enjoying uninterrupted service.</p>
          <?php endif; ?>
          
          <a href="subscription.php" class="btn btn-primary"><i class="fas fa-rocket"></i> Renew Now</a>
      </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Pass PHP variables to JavaScript ---
        const stockAlerts = <?php echo json_encode($stock_alerts); ?>;
        const showStockAlert = <?php echo json_encode($show_stock_alert_popup); ?>;
        const isExpired = <?php echo json_encode($is_expired); ?>;
        const showExpiryAlert = <?php echo json_encode($show_expiry_alert_popup); ?>;

        // --- Stock Alert Modal Logic ---
        const stockOverlay = document.getElementById('stock-alert-overlay');
        const stockAlertList = document.getElementById('stock-alert-list');
        const closeStockModalBtn = document.getElementById('close-stock-modal');
        
        if (showStockAlert) {
            stockAlerts.forEach(product => {
                const li = document.createElement('li');
                let stockLabel = (product.quantity_available <= 0)
                    ? '<span class="stock-count out">Out of Stock</span>'
                    : `<span class="stock-count low">${product.quantity_available} left</span>`;
                li.innerHTML = `${product.product_name} ${stockLabel}`;
                stockAlertList.appendChild(li);
            });
            stockOverlay.classList.add('show');
        }

        function closeStockModal() {
            stockOverlay.classList.remove('show');
            fetch('dismiss_alert.php', { method: 'POST' }); // Assumes you have dismiss_alert.php for stock
        }
        if(closeStockModalBtn) {
            closeStockModalBtn.addEventListener('click', closeStockModal);
        }

        // --- Subscription Expiry Modal Logic ---
        const expiryOverlay = document.getElementById('expiry-alert-overlay');
        const closeExpiryModalBtn = document.getElementById('close-expiry-modal');

        if (showExpiryAlert) {
            expiryOverlay.classList.add('show');
        }

        function closeExpiryModal() {
            expiryOverlay.classList.remove('show');
            // Only dismiss the alert for the session if it's a warning, not an expiry.
            if (!isExpired) {
                fetch('dismiss_expiry_alert.php', { method: 'POST' });
            }
        }
        
        // The event listener is only added if the close button exists in the DOM
        if(closeExpiryModalBtn) {
            closeExpiryModalBtn.addEventListener('click', closeExpiryModal);
        }

        // --- General Modal Close Logic (Clicking Outside) ---
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) { // If the click is on the overlay itself
                    if(this.id === 'stock-alert-overlay') {
                      closeStockModal();
                    }
                    // Crucially, do not close the expiry modal on outside click if the plan has expired.
                    if(this.id === 'expiry-alert-overlay' && !isExpired) {
                      closeExpiryModal();
                    }
                }
            });
        });
    });
  </script>
</body>
</html>