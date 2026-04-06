<?php
session_start();
include "../config.php";

// --- 1. SECURITY AND AUTHENTICATION ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ../seller-login.php');
    exit();
}
$user_id = $_SESSION['user_id'];

// Verify seller role
$stmt_role = $conn->prepare("SELECT role FROM login WHERE user_id = ?");
$stmt_role->bind_param("i", $user_id);
$stmt_role->execute();
$role_result = $stmt_role->get_result();
if (($role_result->num_rows === 0) || ($role_result->fetch_assoc()['role'] !== 'seller')) {
    session_destroy();
    header('Location: ../seller-login.php?error=access_denied');
    exit();
}
$stmt_role->close();

// --- 2. HANDLE FORM SUBMISSION WITH STRONG VALIDATION ---
$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Define password validation rules
    $has_uppercase = preg_match('/[A-Z]/', $new_password);
    $has_lowercase = preg_match('/[a-z]/', $new_password);
    $has_number    = preg_match('/[0-9]/', $new_password);
    $has_length    = strlen($new_password) >= 8;

    // Server-side Validation Logic
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New password and confirm password do not match.";
    } elseif (!$has_length || !$has_uppercase || !$has_lowercase || !$has_number) {
        $error_message = "New password does not meet the security requirements.";
    } else {
        // Fetch current password hash from the database
        $stmt_pass = $conn->prepare("SELECT password FROM login WHERE user_id = ?");
        $stmt_pass->bind_param("i", $user_id);
        $stmt_pass->execute();
        $pass_result = $stmt_pass->get_result();
        $user_login = $pass_result->fetch_assoc();
        $stmt_pass->close();

        // Verify the current password
        if ($user_login && password_verify($current_password, $user_login['password'])) {
          $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);

          $stmt_update = $conn->prepare("UPDATE login SET password = ? WHERE user_id = ?");
          $stmt_update->bind_param("si", $hashed_new_password, $user_id);
          if ($stmt_update->execute()) {
              $success_message = "Password updated successfully! You will be logged out for security.";
          } else {
              $error_message = "An error occurred. Please try again.";
          }
          $stmt_update->close();
        } else {
            $error_message = "Incorrect current password.";
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
  <title>Change Password | Vastra Seller</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <script src="../refresh.js"></script>
  <style>
    .message { padding: 15px; margin-bottom: 20px; border-radius: 5px; font-weight: 500;}
    .message.error { background-color: #f8d7da; color: #721c24; }
    .message.success { background-color: #d4edda; color: #155724; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
    .form-group input { width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ccc; box-sizing: border-box; }
    .form-container { max-width: 600px; margin: 20px 0; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    #password-strength-status { margin-top: 10px; padding: 10px; border-radius: 5px; background-color: #f8f9fa; border: 1px solid #dee2e6; }
    #password-strength-status p { margin: 5px 0; transition: color 0.3s ease; }
    #password-strength-status p.invalid { color: #dc3545; }
    #password-strength-status p.valid { color: #28a745; text-decoration: line-through; }
    #password-strength-status p.valid::before { content: '✔ '; font-weight: bold; }
    #password-strength-status p.invalid::before { content: '✖ '; font-weight: bold; }
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
      <h2>Change Your Password</h2>
      <div class="form-container">
        <?php if ($error_message): ?>
          <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
          <div class="message success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <form action="change-password.php" method="POST">
          <div class="form-group">
            <label for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password" required>
          </div>
          <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" required>
            <div id="password-strength-status">
              <p id="length" class="invalid">At least 8 characters long</p>
              <p id="lowercase" class="invalid">At least one lowercase letter (a-z)</p>
              <p id="uppercase" class="invalid">At least one uppercase letter (A-Z)</p>
              <p id="number" class="invalid">At least one number (0-9)</p>
            </div>
          </div>
          <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
          </div>
          <button type="submit" class="btn primary-btn">Update Password</button>
        </form>
      </div>
    </div>
  </div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const newPasswordInput = document.getElementById('new_password');
    const length = document.getElementById('length');
    const lowercase = document.getElementById('lowercase');
    const uppercase = document.getElementById('uppercase');
    const number = document.getElementById('number');
    newPasswordInput.addEventListener('keyup', function() {
        const pass = newPasswordInput.value;
        length.classList.toggle('valid', pass.length >= 8);
        length.classList.toggle('invalid', pass.length < 8);
        lowercase.classList.toggle('valid', /[a-z]/.test(pass));
        lowercase.classList.toggle('invalid', !/[a-z]/.test(pass));
        uppercase.classList.toggle('valid', /[A-Z]/.test(pass));
        uppercase.classList.toggle('invalid', !/[A-Z]/.test(pass));
        number.classList.toggle('valid', /[0-9]/.test(pass));
        number.classList.toggle('invalid', !/[0-9]/.test(pass));
    });
});
</script>
</body>
</html>