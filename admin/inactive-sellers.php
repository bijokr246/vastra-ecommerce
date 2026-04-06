<?php
// 1. START SESSION, CHECK LOGIN, AND INCLUDE LIBS
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

include "../config.php";
include "../mail_config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

$feedback_message = '';
$feedback_type = '';

// --- 2. HANDLE POST ACTIONS (SEND WARNING OR DEACTIVATE) ---

// ACTION A: Send the warning email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_warning_email'])) {
    $seller_user_id = (int)$_POST['seller_user_id'];
    $seller_email = $_POST['seller_email'];
    $seller_name = $_POST['seller_name'];

    $mail = new PHPMailer(true);
    try {
        // Mailer setup from config
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port = MAIL_PORT;

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($seller_email, $seller_name);
        $mail->isHTML(true);
        $mail->Subject = 'Important: Action Required for Your Vastra Seller Account';
        $mail->Body = "<html><body><p>Hello " . htmlspecialchars($seller_name) . ",</p><p>We are writing to you regarding your seller account on Vastra. Our records indicate that you have not added or updated any products in your shop for over six months.</p><p><strong>Please add new products or update your existing listings within the next 14 days.</strong></p><p>If no action is taken, your seller account may be temporarily deactivated. If you need any assistance, please contact our support team immediately.</p><p>Best regards,<br>The Vastra Admin Team</p></body></html>";
        $mail->send();

        // Record that the warning was sent
        $stmt_update = $conn->prepare("UPDATE shops SET warning_sent_at = NOW() WHERE user_id = ?");
        $stmt_update->bind_param("i", $seller_user_id);
        $stmt_update->execute();
        $stmt_update->close();

        $feedback_message = 'Warning email sent successfully to ' . htmlspecialchars($seller_email);
        $feedback_type = 'success';
    } catch (Exception $e) {
        $feedback_message = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        $feedback_type = 'error';
    }
}

// ACTION B: Deactivate the seller's account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_seller'])) {
    $seller_user_id = (int)$_POST['seller_user_id'];
    $seller_name = $_POST['seller_name'];
    $seller_email = ''; // Initialize email variable

    $conn->begin_transaction();
    try {
        // Step 0: Get the seller's email for the notification
        $stmt_email = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
        $stmt_email->bind_param("i", $seller_user_id);
        $stmt_email->execute();
        $email_result = $stmt_email->get_result()->fetch_assoc();
        if (!$email_result) { throw new Exception("Could not find seller's email."); }
        $seller_email = $email_result['email'];
        $stmt_email->close();

        // Step 1: Inactivate products
        $sql_prods = "UPDATE products SET status = 'inactive' WHERE shop_id IN (SELECT shop_id FROM shops WHERE user_id = ?)";
        $stmt_prods = $conn->prepare($sql_prods);
        $stmt_prods->bind_param("i", $seller_user_id);
        $stmt_prods->execute();
        $stmt_prods->close();

        // Step 2: Inactivate shop and clear warning timestamp
        $sql_shop = "UPDATE shops SET shop_status = 'inactive', warning_sent_at = NULL WHERE user_id = ?";
        $stmt_shop = $conn->prepare($sql_shop);
        $stmt_shop->bind_param("i", $seller_user_id);
        $stmt_shop->execute();
        $stmt_shop->close();

        // Step 3: Demote user status to 'banned' in login table
        $sql_role = "UPDATE login SET status = 'banned' WHERE user_id = ?";
        $stmt_role = $conn->prepare($sql_role);
        $stmt_role->bind_param("i", $seller_user_id);
        $stmt_role->execute();
        $stmt_role->close();

        // If all DB operations succeed, commit.
        $conn->commit();

        // --- NEW: SEND DEACTIVATION EMAIL AFTER SUCCESSFUL COMMIT ---
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = MAIL_ENCRYPTION;
            $mail->Port = MAIL_PORT;

            $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
            $mail->addAddress($seller_email, $seller_name);
            $mail->isHTML(true);
            $mail->Subject = 'Your Vastra Seller Account has been Deactivated';
            $mail->Body    = "<html><body><p>Hello " . htmlspecialchars($seller_name) . ",</p><p>This email is to confirm that your seller account on Vastra has been deactivated due to prolonged inactivity.</p><p>Your shop and all associated products have also been set to inactive and are no longer visible to customers.</p><p>If you wish to reactivate your seller account in the future, please contact the Vastra administration directly. Upon reactivation, you will regain access to your shop and products.</p><p>Regards,<br>The Vastra Admin Team</p></body></html>";

            $mail->send();
            $feedback_message = "Seller account for '" . htmlspecialchars($seller_name) . "' has been deactivated and a notification email has been sent.";
            $feedback_type = 'success';
        } catch (Exception $e) {
            // The deactivation WORKED, but the email FAILED. Inform the admin.
            $feedback_message = "Seller account was deactivated, but the notification email failed to send. Mailer Error: {$mail->ErrorInfo}";
            $feedback_type = 'error';
        }

    } catch (Exception $e) {
        $conn->rollback();
        $feedback_message = "Failed to deactivate account. A database error occurred: " . $e->getMessage();
        $feedback_type = 'error';
    }
}


// --- 3. FETCH INACTIVE SELLERS FROM DATABASE ---
$inactive_sellers = [];
$sql = "SELECT
            u.user_id, u.fname, u.lname, u.email, s.shop_name, s.warning_sent_at, lp.last_product_update
        FROM users u
        JOIN shops s ON u.user_id = s.user_id
        JOIN (
            SELECT shop_id, MAX(last_update) AS last_product_update
            FROM products
            GROUP BY shop_id
        ) AS lp ON s.shop_id = lp.shop_id
        WHERE lp.last_product_update < DATE_SUB(NOW(), INTERVAL 6 MONTH) AND s.shop_status = 'active'
        ORDER BY lp.last_product_update ASC";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $inactive_sellers[] = $row;
    }
}

$result_admin_name = $conn->query("SELECT fname FROM users WHERE user_id = {$_SESSION['admin_id']}");
$admin_name = $result_admin_name->fetch_assoc()['fname'];
$conn->close();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Inactive Sellers | Vastra Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <style>
    .btn-action.warning { background-color: #ffc107; color: #212529 !important; }
    .btn-action.warning:hover { background-color: #e0a800; }
    .btn-action.deactivate { background-color: #dc3545; color: white !important; }
    .btn-action.deactivate:hover { background-color: #c82333; }
    .btn-action { padding: 6px 12px; border: none; cursor: pointer; border-radius: 4px; font-size: 0.9em; }
    .message-box { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
    .message-box.success { background-color: #d4edda; color: #155724; }
    .message-box.error { background-color: #f8d7da; color: #721c24; }
  </style>
</head>
<body>
  <aside class="sidebar"><?php require "header.php"; ?></aside>
  <div class="main-content">
    <header class="header"><h1>Inactive Seller Management</h1><div class="admin-profile"><a href="#"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($admin_name); ?></a></div></header>
    <main>
      <section class="content-card">
        <h2>Sellers Inactive for 6+ Months</h2>
        <p>This list shows sellers who have not updated products in over six months. First, send a warning. If they remain inactive, you can deactivate their seller account.</p>
        
        <?php if ($feedback_message): ?>
            <div class="message-box <?php echo $feedback_type; ?>"><?php echo $feedback_message; ?></div>
        <?php endif; ?>

        <table class="data-table">
          <thead><tr><th>Seller Name</th><th>Email</th><th>Shop Name</th><th>Last Update</th><th>Warning Sent</th><th>Action</th></tr></thead>
          <tbody>
            <?php if (empty($inactive_sellers)): ?>
              <tr><td colspan="6" style="text-align:center;">No inactive sellers found. Great!</td></tr>
            <?php else: ?>
              <?php foreach ($inactive_sellers as $seller): ?>
                <tr>
                  <td><?php echo htmlspecialchars($seller['fname'] . ' ' . $seller['lname']); ?></td>
                  <td><?php echo htmlspecialchars($seller['email']); ?></td>
                  <td><?php echo htmlspecialchars($seller['shop_name']); ?></td>
                  <td><?php echo date('M d, Y', strtotime($seller['last_product_update'])); ?></td>
                  <td><?php echo $seller['warning_sent_at'] ? date('M d, Y', strtotime($seller['warning_sent_at'])) : 'No'; ?></td>
                  <td>
                    <?php if (is_null($seller['warning_sent_at'])): ?>
                        <form method="POST" action="">
                            <input type="hidden" name="seller_user_id" value="<?php echo $seller['user_id']; ?>">
                            <input type="hidden" name="seller_email" value="<?php echo htmlspecialchars($seller['email']); ?>">
                            <input type="hidden" name="seller_name" value="<?php echo htmlspecialchars($seller['fname']); ?>">
                            <button type="submit" name="send_warning_email" class="btn-action warning">
                                <i class="fas fa-exclamation-triangle"></i> Send Warning
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to deactivate this seller account? This action will demote them to a buyer and send a notification email.');">
                            <input type="hidden" name="seller_user_id" value="<?php echo $seller['user_id']; ?>">
                            <input type="hidden" name="seller_name" value="<?php echo htmlspecialchars($seller['fname']); ?>">
                            <button type="submit" name="deactivate_seller" class="btn-action deactivate">
                                <i class="fas fa-store-slash"></i> Deactivate
                            </button>
                        </form>
                    <?php endif; ?>
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