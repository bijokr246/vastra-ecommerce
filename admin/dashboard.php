<?php
// 1. START THE SESSION & CHECK FOR LOGIN
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// 2. INCLUDE DATABASE CONNECTION
include "../config.php";

// 3. FETCH DYNAMIC DATA
// --- Fetch Admin's Name ---
$result_admin_name = $conn->query("SELECT fname FROM users WHERE user_id = {$_SESSION['admin_id']}");
$admin_name = $result_admin_name->fetch_assoc()['fname'];

// --- Fetch Stats for the Cards ---
// Get Total Users
$result_users = $conn->query("SELECT COUNT(user_id) as total_users FROM login WHERE role != 'admin'");
$total_users = $result_users->fetch_assoc()['total_users'];

// Get Pending Seller Requests
$result_sellers = $conn->query("SELECT COUNT(request_id) as pending_sellers FROM seller_requests WHERE status = 'pending'");
$pending_sellers = $result_sellers->fetch_assoc()['pending_sellers'];

// Get Total Products
$result_products = $conn->query("SELECT COUNT(product_id) as total_products FROM products");
$total_products = $result_products->fetch_assoc()['total_products'];

// Get Unread User Messages
$result_messages = $conn->query("SELECT COUNT(message_id) as unread_messages FROM contact_messages WHERE status = 'unread'");
$unread_messages = $result_messages->fetch_assoc()['unread_messages'];

// Get Pending Product Reports
$result_reports = $conn->query("SELECT COUNT(report_id) as pending_reports FROM product_reports WHERE status = 'pending'");
$pending_reports = $result_reports->fetch_assoc()['pending_reports'];


// --- Fetch Recent Orders for the Table ---
$recent_orders = [];
$sql = "SELECT o.order_id, CONCAT(u.fname, ' ', u.lname) AS fullname, o.total_amount, o.status, o.order_date
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        ORDER BY o.order_date DESC
        LIMIT 5";
$result_orders = $conn->query($sql);
if ($result_orders && $result_orders->num_rows > 0) {
    while ($row = $result_orders->fetch_assoc()) {
        $recent_orders[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Vastra | Admin Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <script src="../refresh.js"></script>
  <style>
    /* This makes the grid have 5 columns now */
    .cards-grid {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 25px;
    }
    .cards-grid a { text-decoration: none; color: inherit; display: block; }
    .stat-card { height: 100%; transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; display: flex; align-items: center; }
    .cards-grid a:hover .stat-card { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
  </style>
</head>
<body>
  <aside class="sidebar">
    <?php require "header.php" ?>
  </aside>

  <div class="main-content">
    <header class="header">
      <h1>Welcome Back, <?php echo htmlspecialchars($admin_name); ?></h1>
      <div class="admin-profile"><a href="#"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($admin_name); ?></a></div>
    </header>

    <main>
      <div class="cards-grid">
        <a href="manage-users.php">
          <div class="stat-card"><div class="icon"><i class="fas fa-users"></i></div><div class="info"><h3>Total Users</h3><p><?php echo number_format($total_users); ?></p></div></div>
        </a>
        <a href="seller-requests.php">
          <div class="stat-card"><div class="icon"><i class="fas fa-store"></i></div><div class="info"><h3>Pending Sellers</h3><p><?php echo number_format($pending_sellers); ?></p></div></div>
        </a>
        <a href="manage-products.php">
          <div class="stat-card"><div class="icon"><i class="fas fa-boxes-stacked"></i></div><div class="info"><h3>Total Products</h3><p><?php echo number_format($total_products); ?></p></div></div>
        </a>
        <a href="view-messages.php">
          <div class="stat-card"><div class="icon"><i class="fas fa-envelope"></i></div><div class="info"><h3>Unread Messages</h3><p><?php echo number_format($unread_messages); ?></p></div></div>
        </a>
        <a href="manage-reports.php">
          <div class="stat-card">
            <div class="icon"><i class="fas fa-flag"></i></div>
            <div class="info">
              <h3>Pending Reports</h3>
              <p><?php echo number_format($pending_reports); ?></p>
            </div>
          </div>
        </a>
      </div>
      <section class="content-card">
        <h2>Recent Orders</h2>
        <table class="data-table">
          <thead>
            <tr>
              <th>Order ID</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recent_orders)): ?>
              <tr><td colspan="5" style="text-align:center;">No recent orders found.</td></tr>
            <?php else: ?>
              <?php foreach ($recent_orders as $order): ?>
                <tr>
                  <td>#<?php echo htmlspecialchars($order['order_id']); ?></td>
                  <td><?php echo htmlspecialchars($order['fullname']); ?></td>
                  <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                  <td><span class="status <?php echo htmlspecialchars(strtolower($order['status'])); ?>"><?php echo htmlspecialchars(ucfirst($order['status'])); ?></span></td>
                  <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </section>
    </main>
  </div>
</body>
</html>