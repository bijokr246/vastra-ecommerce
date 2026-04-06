<?php
session_start();
include "../config.php";

// --- 1. SECURITY AND AUTHENTICATION ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ../seller-login.php');
    exit();
}
$user_id = $_SESSION['user_id'];

// Verify they are currently a seller
$stmt_role = $conn->prepare("SELECT role FROM login WHERE user_id = ?");
$stmt_role->bind_param("i", $user_id);
$stmt_role->execute();
$role_result = $stmt_role->get_result();
if (($role_result->num_rows === 0) || ($role_result->fetch_assoc()['role'] !== 'seller')) {
    // If they aren't a seller, they have no business here.
    // Destroy session and redirect.
    session_destroy();
    header('Location: ../seller-login.php?error=access_denied');
    exit();
}
$stmt_role->close();

// --- 2. HANDLE SELLER ACCOUNT CLOSURE ---
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = $_POST['password'];
    $confirm_action = isset($_POST['confirm_action']);

    if (empty($password)) {
        $error_message = "Password is required to close your seller account.";
    } elseif (!$confirm_action) {
        $error_message = "You must check the confirmation box to proceed.";
    } else {
        // Verify the user's password to authorize this action
        $stmt_pass = $conn->prepare("SELECT password FROM login WHERE user_id = ?");
        $stmt_pass->bind_param("i", $user_id);
        $stmt_pass->execute();
        $user_login = $stmt_pass->get_result()->fetch_assoc();
        $stmt_pass->close();

        if ($user_login && password_verify($password, $user_login['password'])) {
            // Password is correct. Proceed with deactivation and demotion.
            $conn->begin_transaction();
            try {
                // Step 1: Find the shop_id
                $stmt_shop_id = $conn->prepare("SELECT shop_id FROM shops WHERE user_id = ?");
                $stmt_shop_id->bind_param("i", $user_id);
                $stmt_shop_id->execute();
                $shop_result = $stmt_shop_id->get_result();
                
                if ($shop_row = $shop_result->fetch_assoc()) {
                    $shop_id = $shop_row['shop_id'];

                    // Step 2: Update all products in the shop to 'inactive'
                    $stmt_update_prods = $conn->prepare("UPDATE products SET status = 'inactive' WHERE shop_id = ?");
                    $stmt_update_prods->bind_param("i", $shop_id);
                    $stmt_update_prods->execute();
                    $stmt_update_prods->close();
                }
                $stmt_shop_id->close();

                // Step 3: Update the shop status to 'inactive'
                $stmt_update_shop = $conn->prepare("UPDATE shops SET shop_status = 'inactive' WHERE user_id = ?");
                $stmt_update_shop->bind_param("i", $user_id);
                $stmt_update_shop->execute();
                $stmt_update_shop->close();

                // Step 4: Demote the user's role to 'buyer'
                // !! IMPORTANT !! Change 'buyer' to 'customer' if that's the name of the role in your DB
                $new_role = 'buyer'; 
                $stmt_demote_user = $conn->prepare("UPDATE login SET role = ? WHERE user_id = ?");
                $stmt_demote_user->bind_param("si", $new_role, $user_id);
                $stmt_demote_user->execute();
                $stmt_demote_user->close();

                // Step 5: Commit the transaction
                $conn->commit();

                // IMPORTANT: Do NOT destroy the session. Just redirect.
                // Redirect to the main customer homepage.
                header('Location: ../index.php?message=seller_account_closed');
                exit();

            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                error_log("Seller account closure failed for user_id $user_id: " . $e->getMessage());
                $error_message = "An unexpected error occurred. Please try again or contact support.";
            }
        } else {
            $error_message = "Incorrect password. Action failed.";
        }
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Close Seller Account | Vastra</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <script src="../refresh.js"></script>
  <style>
    .message { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
    .message.error { background-color: #f8d7da; color: #721c24; }
    .warning-box { border: 2px solid #dc3545; padding: 20px; background-color: #f8d7da; border-radius: 8px; max-width: 600px; }
    .warning-box h2 { color: #721c24; margin-top: 0; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
    .form-group input[type="password"] { width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ccc; box-sizing: border-box; }
    .form-group input[type="checkbox"] { margin-right: 10px; }
    .btn-danger { background-color: #dc3545; color: white; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; font-size: 16px; transition: background-color 0.3s; }
    .btn-danger:hover { background-color: #c82333; }
  </style>
</head>
<body>
  <div class="sidebar">
    <?php require "sidebar.php"; ?>
  </div>

  <div class="main">
    <div class="header">
      <h1>Account Settings</h1>
    </div>
    <div class="content">
      <div class="warning-box">
        <h2><i class="fas fa-store-slash"></i> Close Your Seller Account</h2>
        <p>This action will <strong>permanently close your seller profile</strong>. Your shop and products will be deactivated, and you will lose access to the seller dashboard.</p>
        <p>Your main user account will <strong>remain active as a buyer</strong>, and you will still be able to log in to the main site to make purchases. This action cannot be undone without contacting an administrator.</p>

        <?php if ($error_message): ?>
          <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form action="close-seller-account.php" method="POST">
          <div class="form-group">
            <label for="password">Enter Your Password to Confirm</label>
            <input type="password" id="password" name="password" required>
          </div>
          <div class="form-group">
            <label for="confirm_action">
              <input type="checkbox" id="confirm_action" name="confirm_action" value="1">
              I understand I am closing my seller account and will be demoted to a buyer.
            </label>
          </div>
          <button type="submit" class="btn-danger">Close My Seller Account</button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>