<?php
// 1. START SESSION, CHECK LOGIN, AND INCLUDE LIBS
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

include "../config.php";
include "../mail_config.php"; // Include the central mail configuration

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php'; // Make sure this path is correct

// A reusable function to send notification emails
function sendNotificationEmail($toEmail, $toName, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log error or handle it as needed
        error_log("Mailer Error for {$toEmail}: {$mail->ErrorInfo}");
        return $mail->ErrorInfo;
    }
}


// --- 2. Get filter and sort parameters from URL ---
$search_query = isset($_GET['search_query']) ? trim($_GET['search_query']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$filter_role = isset($_GET['filter_role']) ? $_GET['filter_role'] : '';
$query_params = [];
if (!empty($search_query)) $query_params['search_query'] = $search_query;
if (!empty($sort)) $query_params['sort'] = $sort;
if (!empty($filter_role)) $query_params['filter_role'] = $filter_role;
$redirect_query_string = http_build_query($query_params);


// --- 3. HANDLE ACTIONS (ACTIVATE/DEACTIVATE/BAN USER) ---
if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $action = $_GET['action'];
    $user_id = (int)$_GET['user_id'];

    // Get the user's details needed for actions and emails
    $stmt_user = $conn->prepare("SELECT u.fname, u.email, l.role FROM users u JOIN login l ON u.user_id = l.user_id WHERE u.user_id = ?");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $user_data = $stmt_user->get_result()->fetch_assoc();
    $stmt_user->close();

    if ($user_data) {
        $user_role = $user_data['role'];
        $user_name = $user_data['fname'];
        $user_email = $user_data['email'];

        $conn->begin_transaction();
        try {
            if ($action === 'deactivate') { // "Ban" action
                // DB updates
                $conn->execute_query("UPDATE login SET status = 'banned' WHERE user_id = ?", [$user_id]);
                if ($user_role === 'seller') {
                    $conn->execute_query("UPDATE shops SET shop_status = 'banned' WHERE user_id = ?", [$user_id]);
                    $conn->execute_query("UPDATE products SET status = 'banned' WHERE shop_id IN (SELECT shop_id FROM shops WHERE user_id = ?)", [$user_id]);
                }
                $conn->commit();

                // Send Email
                $subject = "Your Vastra Account Has Been Banned";
                $body = "<html><body><p>Hello " . htmlspecialchars($user_name) . ",</p><p>This is a notification that your account on Vastra has been banned by an administrator due to a violation of our terms of service. You will no longer be able to log in.</p><p>If you believe this is an error, please contact our support team.</p><p>Regards,<br>The Vastra Admin Team</p></body></html>";
                $email_result = sendNotificationEmail($user_email, $user_name, $subject, $body);

                $_SESSION['flash_message'] = "User has been banned. " . ($email_result === true ? "Notification email sent." : "Failed to send email.");
                $_SESSION['flash_type'] = ($email_result === true ? 'success' : 'error');

            } elseif ($action === 'activate') { // "Activate" action
                // DB updates
                $conn->execute_query("UPDATE login SET status = 'active' WHERE user_id = ?", [$user_id]);
                if ($user_role === 'seller') {
                    $conn->execute_query("UPDATE shops SET shop_status = 'active' WHERE user_id = ?", [$user_id]);
                    $conn->execute_query("UPDATE products SET status = 'active' WHERE shop_id IN (SELECT shop_id FROM shops WHERE user_id = ?)", [$user_id]);
                }
                $conn->commit();

                // Send Email
                $subject = "Your Vastra Account Has Been Reactivated";
                $body = "<html><body><p>Hello " . htmlspecialchars($user_name) . ",</p><p>Good news! Your account on Vastra has been reactivated by an administrator. You can now log in and access all features as before.</p><p>Welcome back!</p><p>Regards,<br>The Vastra Team</p></body></html>";
                $email_result = sendNotificationEmail($user_email, $user_name, $subject, $body);
                
                $_SESSION['flash_message'] = "User has been activated. " . ($email_result === true ? "Notification email sent." : "Failed to send email.");
                $_SESSION['flash_type'] = ($email_result === true ? 'success' : 'error');
            }
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $_SESSION['flash_message'] = "Database action failed. Please try again.";
            $_SESSION['flash_type'] = 'error';
            error_log("User management action failed: " . $e->getMessage());
        }
    } else {
        $_SESSION['flash_message'] = "User not found.";
        $_SESSION['flash_type'] = 'error';
    }

    header('Location: manage-users.php?' . $redirect_query_string);
    exit();
}


// --- 4. FETCH USERS (with dynamic search, filter, and sort) ---
$sql = "SELECT u.user_id, CONCAT(u.fname, ' ', u.lname) AS fullname, u.email, l.role, l.status
        FROM users u JOIN login l ON u.user_id = l.user_id";
$where_clauses = []; $params = []; $types = '';
if (!empty($search_query)) {
    $where_clauses[] = "(u.fname LIKE ? OR u.lname LIKE ? OR u.email LIKE ?)";
    $search_param = "%" . $search_query . "%";
    array_push($params, $search_param, $search_param, $search_param);
    $types .= 'sss';
}
if (!empty($filter_role)) {
    $where_clauses[] = "l.role = ?";
    $params[] = $filter_role;
    $types .= 's';
}
if (!empty($where_clauses)) $sql .= " WHERE " . implode(" AND ", $where_clauses);
switch ($sort) {
    case 'oldest': $sql .= " ORDER BY u.user_id ASC"; break;
    case 'name_asc': $sql .= " ORDER BY fullname ASC"; break;
    case 'name_desc': $sql .= " ORDER BY fullname DESC"; break;
    default: $sql .= " ORDER BY u.user_id DESC"; break;
}
$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$users_result = $stmt->get_result();
$conn->close();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Users | Vastra Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <style>
    .status.banned { background-color: #212529; color: #f8f9fa; border: 1px solid #dc3545; }
    .status.inactive { background-color: #6c757d; color: #fff; }
    .status.active { background-color: #d4edda; color: #155724; }
    .filter-controls { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; margin-bottom: 20px; background-color: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6; }
    .filter-controls .form-group { display: flex; flex-direction: column; }
    .filter-controls label { font-size: 0.85rem; margin-bottom: 5px; font-weight: 600; color: #495057; }
    .filter-controls select, .filter-controls input[type="text"] { padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 0.9rem; }
    .filter-controls .search-group { display: flex; align-items: flex-end; }
    .filter-controls .search-group input { border-top-right-radius: 0; border-bottom-right-radius: 0; }
    .filter-controls .btn-search { padding: 10px 12px; border: 1px solid #ced4da; border-left: none; background-color: #e9ecef; border-top-right-radius: 4px; border-bottom-right-radius: 4px; cursor: pointer; }
    .filter-controls .btn { align-self: flex-end; padding: 9px 15px; }
    /* New styles for flash messages */
    .flash-message { padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; }
    .flash-message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
    .flash-message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
  </style>
</head>
<body>
  <aside class="sidebar"><?php require "header.php" ?></aside>
  <main class="main-content">
    <h1 class="page-title">User Management</h1>
    
    <!-- NEW: Display Flash Message -->
    <?php if(isset($_SESSION['flash_message'])): ?>
        <div class="flash-message <?php echo $_SESSION['flash_type']; ?>">
            <?php echo $_SESSION['flash_message']; ?>
        </div>
        <?php 
            // Unset the message so it doesn't show again
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_type']);
        ?>
    <?php endif; ?>

    <section class="content-card">
      <div class="page-header">
        <h2>All Registered Users</h2>
        <form action="manage-users.php" method="GET" class="filter-controls">
            <div class="form-group"><label for="search_query">Search Name/Email:</label><div class="search-group"><input type="text" id="search_query" name="search_query" placeholder="Search..." value="<?php echo htmlspecialchars($search_query); ?>" /><button type="submit" class="btn-search"><i class="fas fa-search"></i></button></div></div>
            <div class="form-group"><label for="filter_role">Filter by Role:</label><select name="filter_role" id="filter_role" onchange="this.form.submit()"><option value="" <?php if ($filter_role == '') echo 'selected'; ?>>All Roles</option><option value="buyer" <?php if ($filter_role == 'buyer') echo 'selected'; ?>>Buyers</option><option value="seller" <?php if ($filter_role == 'seller') echo 'selected'; ?>>Sellers</option><option value="admin" <?php if ($filter_role == 'admin') echo 'selected'; ?>>Admins</option></select></div>
            <div class="form-group"><label for="sort">Sort by:</label><select name="sort" id="sort" onchange="this.form.submit()"><option value="newest" <?php if ($sort == 'newest') echo 'selected'; ?>>Newest Users</option><option value="oldest" <?php if ($sort == 'oldest') echo 'selected'; ?>>Oldest Users</option><option value="name_asc" <?php if ($sort == 'name_asc') echo 'selected'; ?>>Name (A-Z)</option><option value="name_desc" <?php if ($sort == 'name_desc') echo 'selected'; ?>>Name (Z-A)</option></select></div>
            <?php if (!empty($search_query) || !empty($filter_role) || $sort !== 'newest'): ?><a href="manage-users.php" class="btn btn-sm btn-secondary">Clear Filters</a><?php endif; ?>
        </form>
      </div>
      <table class="data-table">
        <thead><tr><th>User ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php if ($users_result && $users_result->num_rows > 0): ?>
            <?php while ($user = $users_result->fetch_assoc()): ?>
              <tr>
                <td>#<?php echo htmlspecialchars($user['user_id']); ?></td>
                <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                <td><span class="status <?php echo htmlspecialchars(strtolower($user['status'])); ?>"><?php echo htmlspecialchars(ucfirst($user['status'])); ?></span></td>
                <td class="action-buttons">
                  <?php $action_link_base = 'manage-users.php?' . $redirect_query_string . (empty($redirect_query_string) ? '' : '&'); ?>
                  <?php if ($user['status'] === 'active'): ?>
                    <a class="btn btn-sm btn-primary" href="<?php echo $action_link_base; ?>action=deactivate&user_id=<?php echo $user['user_id']; ?>" onclick="return confirm('Are you sure you want to BAN this user? A notification email will be sent.');">Ban</a>
                  <?php elseif ($user['status'] === 'banned'): ?>
                    <a class="btn btn-sm btn-primary" href="<?php echo $action_link_base; ?>action=activate&user_id=<?php echo $user['user_id']; ?>" onclick="return confirm('Are you sure you want to ACTIVATE this user? A notification email will be sent.');">Activate</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="6" style="text-align:center;">No users found matching your criteria.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>
  </main>
</body>
</html>