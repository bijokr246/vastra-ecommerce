<?php
// 1. START THE SESSION & CHECK FOR LOGIN
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// 2. INCLUDE DATABASE CONNECTION
include "../config.php";

// --- NEW: HANDLE SORTING AND FILTERING ---
// Define allowed values to prevent manipulation
$allowed_sorts = ['newest', 'oldest'];
$allowed_filters = ['all', '24hr', '5days', '2weeks'];

// Set default values
$sort_order = 'newest';
$filter_period = 'all';

// Check and apply user selections from GET parameters
if (isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sorts)) {
    $sort_order = $_GET['sort'];
}
if (isset($_GET['filter_period']) && in_array($_GET['filter_period'], $allowed_filters)) {
    $filter_period = $_GET['filter_period'];
}

// 3. FETCH DATA DYNAMICALLY
// --- Fetch Admin's Name for the header ---
$result_admin_name = $conn->query("SELECT fname FROM users WHERE user_id = {$_SESSION['admin_id']}");
$admin_name = $result_admin_name->fetch_assoc()['fname'];

// --- Build the SQL query based on filters ---
$messages = [];
$sql = "SELECT cm.message_id, cm.subject, cm.status, cm.created_at, u.fname, u.lname
        FROM contact_messages cm
        JOIN users u ON cm.user_id = u.user_id";

$sql_where = "";
$params = [];
$param_types = "";

// Build WHERE clause for time period filter
switch ($filter_period) {
    case '24hr':
        $sql_where = " WHERE cm.created_at >= ?";
        $params[] = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $param_types .= 's';
        break;
    case '5days':
        $sql_where = " WHERE cm.created_at >= ?";
        $params[] = date('Y-m-d H:i:s', strtotime('-5 days'));
        $param_types .= 's';
        break;
    case '2weeks':
        $sql_where = " WHERE cm.created_at >= ?";
        $params[] = date('Y-m-d H:i:s', strtotime('-2 weeks'));
        $param_types .= 's';
        break;
}

// Build ORDER BY clause for sorting
$sql_order = ($sort_order === 'oldest') ? " ORDER BY cm.created_at ASC" : " ORDER BY cm.created_at DESC";

// Combine the SQL parts
$final_sql = $sql . $sql_where . $sql_order;

// Use a prepared statement to execute the query safely
$stmt = $conn->prepare($final_sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result_messages = $stmt->get_result();

if ($result_messages && $result_messages->num_rows > 0) {
    while ($row = $result_messages->fetch_assoc()) {
        $messages[] = $row;
    }
}
$stmt->close();
$conn->close();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User Messages | Vastra Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <script src="../refresh.js"></script>
  <!-- EMBEDDED CSS FOR THIS PAGE -->
  <style>
    /* For the message status 'unread' and 'read' */
    .status.unread { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
    .status.read { background-color: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }

    /* For action buttons in tables */
    .btn-action { color: #fff !important; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 0.85rem; transition: opacity 0.3s; display: inline-block; }
    .btn-action.view { background-color: #007bff; }
    .btn-action:hover { opacity: 0.8; }

    /* --- NEW: CSS for the filter toolbar --- */
    .filter-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 15px;
      padding: 15px;
      background-color: #f8f9fa;
      border-radius: 6px;
      margin-bottom: 20px;
      border: 1px solid #dee2e6;
    }
    .filter-group {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .filter-toolbar label {
      font-weight: 600;
      color: #495057;
    }
    .filter-toolbar select, .filter-toolbar button {
      padding: 8px 12px;
      font-size: 0.9rem;
      border-radius: 5px;
      border: 1px solid #ced4da;
    }
    .filter-toolbar select:focus {
      border-color: #80bdff;
      outline: 0;
      box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
    }
    .filter-toolbar button {
      background-color: #28a745;
      color: white;
      cursor: pointer;
      border-color: #28a745;
    }
    .filter-toolbar button:hover {
      background-color: #218838;
    }
    .btn-reset {
      background-color: #6c757d;
      color: white !important;
      text-decoration: none;
      padding: 8px 12px;
      font-size: 0.9rem;
      border-radius: 5px;
      border: 1px solid #6c757d;
    }
    .btn-reset:hover {
        background-color: #5a6268;
    }
  </style>
</head>
<body>
  <aside class="sidebar">
    <?php require "header.php"; ?>
  </aside>

  <div class="main-content">
    <header class="header">
      <h1>User Messages</h1>
      <div class="admin-profile"><a href="#"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($admin_name); ?></a></div>
    </header>

    <main>
      <section class="content-card">
        <h2>All Received Messages</h2>

        <!-- NEW: Filter and Sort Toolbar Form -->
        <form action="view-messages.php" method="GET" class="filter-toolbar">
            <div class="filter-group">
                <label for="sort">Sort By:</label>
                <select name="sort" id="sort">
                    <option value="newest" <?php if ($sort_order === 'newest') echo 'selected'; ?>>Newest First</option>
                    <option value="oldest" <?php if ($sort_order === 'oldest') echo 'selected'; ?>>Oldest First</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="filter_period">Period:</label>
                <select name="filter_period" id="filter_period">
                    <option value="all" <?php if ($filter_period === 'all') echo 'selected'; ?>>All Time</option>
                    <option value="24hr" <?php if ($filter_period === '24hr') echo 'selected'; ?>>Past 24 Hours</option>
                    <option value="5days" <?php if ($filter_period === '5days') echo 'selected'; ?>>Past 5 Days</option>
                    <option value="2weeks" <?php if ($filter_period === '2weeks') echo 'selected'; ?>>Past 2 Weeks</option>
                </select>
            </div>
            <div class="filter-group">
                <button type="submit">Apply</button>
                <a href="view-messages.php" class="btn-reset">Reset</a>
            </div>
        </form>

        <table class="data-table">
          <thead>
            <tr>
              <th>From</th>
              <th>Subject</th>
              <th>Date Received</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($messages)): ?>
              <tr>
                <td colspan="5" style="text-align:center;">No messages found for the selected criteria.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($messages as $message): ?>
                <tr>
                  <td><?php echo htmlspecialchars($message['fname'] . ' ' . $message['lname']); ?></td>
                  <td><?php echo htmlspecialchars($message['subject']); ?></td>
                  <td><?php echo date('M d, Y, h:i A', strtotime($message['created_at'])); ?></td>
                  <td>
                    <span class="status <?php echo htmlspecialchars(strtolower($message['status'])); ?>">
                        <?php echo htmlspecialchars(ucfirst($message['status'])); ?>
                    </span>
                  </td>
                  <td>
                    <a href="message-detail.php?id=<?php echo $message['message_id']; ?>" class="btn-action view">
                      <i class="fas fa-eye"></i> View
                    </a>
                  </td>
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