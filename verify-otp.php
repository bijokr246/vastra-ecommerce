<?php
session_start();
include "config.php";

// If user hasn't started the process, redirect them
if (!isset($_SESSION['reset_email'])) {
    header('Location: forgot-password.php');
    exit();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp']);
    $email = $_SESSION['reset_email'];

    if (empty($otp)) {
        $errors['otp'] = "OTP is required.";
    } elseif (!is_numeric($otp) || strlen($otp) != 6) {
        $errors['otp'] = "Please enter a valid 6-digit OTP.";
    }

    if (empty($errors)) {
        $sql = "SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW()";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $email, $otp);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) === 1) {
            // OTP is correct and not expired
            $_SESSION['otp_verified'] = true;
            header('Location: reset-password.php');
            exit();
        } else {
            $errors['general'] = "Invalid or expired OTP. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP | Vastra</title>
    <link rel="stylesheet" href="style-login.css">
    <script src="refresh.js"></script>
</head>
<body>
    <div class="login-container">
        <div class="login-right" style="margin: auto; width: 50%;">
            <div class="login-form-container">
                <div class="logo"><h2>VASTRA</h2></div>
                <form class="login-form" method="post" action="verify-otp.php">
                    <h3>Verify OTP</h3>
                    <p>An OTP has been sent to <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong>. Please enter it below.</p>

                    <?php if (!empty($errors['general'])): ?>
                        <div class='general-error'><?php echo htmlspecialchars($errors['general']); ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="otp">One-Time Password (OTP)</label>
                        <div class="input-field">
                            <i class="fas fa-key input-icon"></i>
                            <input type="text" id="otp" name="otp" placeholder="Enter 6-digit OTP" required>
                        </div>
                        <span class="error-msg"><?php echo htmlspecialchars($errors['otp'] ?? ''); ?></span>
                    </div>
                    
                    <button type="submit" class="login-btn">Verify</button>
                    
                    <div class="signup-link">
                        Didn't receive the code? <a href="forgot-password.php">Resend</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>