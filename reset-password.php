<?php
session_start();
include "config.php";

// Security check: ensure user has verified OTP
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['otp_verified'])) {
    header('Location: login.php');
    exit();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors['password'] = "Password must be at least 6 characters long.";
    }

    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match.";
    }

    if (empty($errors)) {
        $email = $_SESSION['reset_email'];
        // Hash the new password before storing it
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $sql = "UPDATE login SET password = ? WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $hashed_password, $email);

        if (mysqli_stmt_execute($stmt)) {
            // Clean up: delete the token from password_resets
            $delete_sql = "DELETE FROM password_resets WHERE email = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_sql);
            mysqli_stmt_bind_param($delete_stmt, "s", $email);
            mysqli_stmt_execute($delete_stmt);

            // Unset session variables and redirect to login with a success message
            unset($_SESSION['reset_email'], $_SESSION['otp_verified']);
            $_SESSION['success_message'] = "Your password has been reset successfully. Please login.";
            header('Location: login.php');
            exit();
        } else {
            $errors['general'] = "Failed to update password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password | Vastra</title>
    <link rel="stylesheet" href="style-login.css">
    <script src="refresh.js"></script>
</head>
<body>
    <div class="login-container">
        <div class="login-right" style="margin: auto; width: 50%;">
            <div class="login-form-container">
                <div class="logo"><h2>VASTRA</h2></div>
                <form class="login-form" method="post" action="reset-password.php">
                    <h3>Create New Password</h3>
                    
                    <?php if (!empty($errors['general'])): ?>
                        <div class='general-error'><?php echo htmlspecialchars($errors['general']); ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="password">New Password</label>
                        <div class="input-field">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" id="password" name="password" placeholder="Enter new password" required>
                        </div>
                        <span class="error-msg"><?php echo htmlspecialchars($errors['password'] ?? ''); ?></span>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="input-field">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                        </div>
                        <span class="error-msg"><?php echo htmlspecialchars($errors['confirm_password'] ?? ''); ?></span>
                    </div>
                    
                    <button type="submit" class="login-btn">Reset Password</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>