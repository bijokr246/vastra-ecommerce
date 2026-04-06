<?php
session_start();
include "../config.php";

// --- 1. SECURITY AND AUTHENTICATION ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ../seller-login.php');
    exit();
}
$user_id = $_SESSION['user_id'];

$stmt_role = $conn->prepare("SELECT role FROM login WHERE user_id = ?");
$stmt_role->bind_param("i", $user_id);
$stmt_role->execute();
$role_result = $stmt_role->get_result()->fetch_assoc();
if (!$role_result || $role_result['role'] !== 'seller') {
    session_destroy();
    header('Location: ../seller-login.php?error=access_denied');
    exit();
}
$stmt_role->close();

// --- 2. HANDLE FILTERS AND SORTING ---
// Get values from URL (GET parameters), with defaults
$sort_by = $_GET['sort'] ?? 'date_desc';
$status_filter = $_GET['status'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$min_total = $_GET['min_total'] ?? '';
$max_total = $_GET['max_total'] ?? '';


// --- 3. DYNAMICALLY BUILD THE SQL QUERY ---

// Base query
$sql = "
    SELECT 
        o.order_id,
        o.order_date,
        o.status AS order_status,
        CONCAT(c.fname, ' ' ,c.lname) AS customer_name,
        SUM(oi.price * oi.quantity) AS seller_total_in_order
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    JOIN shops s ON p.shop_id = s.shop_id
    JOIN orders o ON oi.order_id = o.order_id
    JOIN users c ON o.user_id = c.user_id
";

// --- WHERE conditions ---
$where_conditions = [];
$params = [];
$types = '';

// This condition is always applied for the logged-in seller
$where_conditions[] = "s.user_id = ?";
$params[] = $user_id;
$types .= 'i';

// Add status filter if a specific status is chosen
if ($status_filter !== 'all' && !empty($status_filter)) {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

// Add date range filters
if (!empty($start_date)) {
    $where_conditions[] = "o.order_date >= ?";
    $params[] = $start_date . " 00:00:00"; // Start of the day
    $types .= 's';
}
if (!empty($end_date)) {
    $where_conditions[] = "o.order_date <= ?";
    $params[] = $end_date . " 23:59:59"; // End of the day
    $types .= 's';
}

// Append WHERE clause to the main query
if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

// GROUP BY is always needed
$sql .= " GROUP BY o.order_id";

// --- HAVING conditions (for aggregated columns like SUM) ---
$having_conditions = [];
if (!empty($min_total)) {
    $having_conditions[] = "seller_total_in_order >= ?";
    $params[] = $min_total;
    $types .= 'd'; // 'd' for double/decimal
}
if (!empty($max_total)) {
    $having_conditions[] = "seller_total_in_order <= ?";
    $params[] = $max_total;
    $types .= 'd';
}

if (!empty($having_conditions)) {
    $sql .= " HAVING " . implode(" AND ", $having_conditions);
}

// --- ORDER BY clause ---
// Whitelist allowed sort options to prevent SQL injection
$order_by_options = [
    'date_desc' => 'o.order_date DESC',
    'date_asc' => 'o.order_date ASC',
    'total_desc' => 'seller_total_in_order DESC',
    'total_asc' => 'seller_total_in_order ASC'
];

// Use the selected sort option, or default to 'date_desc' if invalid
$order_by_clause = $order_by_options[$sort_by] ?? 'o.order_date DESC';
$sql .= " ORDER BY " . $order_by_clause;


// --- 4. EXECUTE THE QUERY ---
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing query: " . $conn->error);
}

// Bind parameters dynamically
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$orders_result = $stmt->get_result();


// --- Helper for Active Nav Link ---
$current_page = basename($_SERVER['PHP_SELF']);

// Possible order statuses for the filter dropdown
$order_statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Canceled', 'Returned'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Orders | Vastra</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <script src="../refresh.js"></script>
  <style>
    /* Add this CSS to your style.css file */
    .filters-container {
        background-color: #f9f9f9;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        align-items: flex-end;
    }
    .filter-group {
        display: flex;
        flex-direction: column;
    }
    .filter-group label {
        font-size: 14px;
        color: #555;
        margin-bottom: 5px;
    }
    .filter-group select,
    .filter-group input {
        padding: 8px 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 14px;
    }
    .filter-buttons {
        display: flex;
        gap: 10px;
    }
    .filter-buttons button,
    .filter-buttons a {
        padding: 8px 15px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        text-decoration: none;
        display: inline-block;
        text-align: center;
    }
    .filter-buttons button {
        background-color: #007bff;
        color: white;
    }
    .filter-buttons a {
        background-color: #6c757d;
        color: white;
    }
  </style>
</head>
<body>
  <div class="sidebar">
      <?php require "sidebar.php"; ?>
  </div>

  <div class="main">
    <div class="header"><h1>Orders</h1></div>
    <div class="content">
      <!-- Filter and Sort Form -->
      <form action="orders.php" method="GET" class="filters-container">
          <div class="filter-group">
              <label for="sort">Sort By</label>
              <select name="sort" id="sort">
                  <option value="date_desc" <?php if ($sort_by == 'date_desc') echo 'selected'; ?>>Newest First</option>
                  <option value="date_asc" <?php if ($sort_by == 'date_asc') echo 'selected'; ?>>Oldest First</option>
                  <option value="total_desc" <?php if ($sort_by == 'total_desc') echo 'selected'; ?>>Total: High to Low</option>
                  <option value="total_asc" <?php if ($sort_by == 'total_asc') echo 'selected'; ?>>Total: Low to High</option>
              </select>
          </div>
          <div class="filter-group">
              <label for="status">Filter by Status</label>
              <select name="status" id="status">
                  <option value="all">All Statuses</option>
                  <?php foreach ($order_statuses as $status): ?>
                  <option value="<?php echo $status; ?>" <?php if ($status_filter == $status) echo 'selected'; ?>>
                      <?php echo $status; ?>
                  </option>
                  <?php endforeach; ?>
              </select>
          </div>
          <div class="filter-group">
              <label for="start_date">From Date</label>
              <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
          </div>
          <div class="filter-group">
              <label for="end_date">To Date</label>
              <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
          </div>
          <div class="filter-group">
              <label for="min_total">Min Total (₹)</label>
              <input type="number" name="min_total" id="min_total" placeholder="e.g., 500" step="0.01" value="<?php echo htmlspecialchars($min_total); ?>">
          </div>
          <div class="filter-group">
              <label for="max_total">Max Total (₹)</label>
              <input type="number" name="max_total" id="max_total" placeholder="e.g., 2000" step="0.01" value="<?php echo htmlspecialchars($max_total); ?>">
          </div>
          <div class="filter-buttons">
              <button type="submit">Apply Filters</button>
              <a href="orders.php">Reset</a>
          </div>
      </form>

      <table>
        <thead>
          <tr>
            <th>Order ID</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Your Items Total</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($orders_result->num_rows > 0): ?>
            <?php while($order = $orders_result->fetch_assoc()): ?>
              <tr>
                <td>#V-<?php echo htmlspecialchars($order['order_id']); ?></td>
                <td><?php echo date('d-m-Y', strtotime($order['order_date'])); ?></td>
                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                <td>₹<?php echo htmlspecialchars(number_format($order['seller_total_in_order'], 2)); ?></td>
                <td>
                  <span class="status <?php echo strtolower(htmlspecialchars($order['order_status'])); ?>">
                    <?php echo htmlspecialchars(ucfirst($order['order_status'])); ?>
                  </span>
                </td>
                <td class="actions">
                  <a href="order-details.php?id=<?php echo $order['order_id']; ?>" title="View Details">
                    <i class="fas fa-eye"></i>
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" style="text-align: center;">No orders found matching your criteria.</td>
            </tr>
          <?php endif; ?>
          <?php $stmt->close(); $conn->close(); ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>