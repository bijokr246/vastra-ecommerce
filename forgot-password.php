<?php
session_start();
include "config.php";
include "mail_config.php"; // <<< --- INCLUDE THE NEW MAIL CONFIG FILE ---

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Make sure this path is correct

$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (empty($errors)) {
        // Check if email exists in the login table
        $sql = "SELECT login_id FROM login WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            // Email exists, generate OTP
            $otp = random_int(100000, 999999);
            $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

            // Delete any old tokens for this email
            $delete_sql = "DELETE FROM password_resets WHERE email = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_sql);
            mysqli_stmt_bind_param($delete_stmt, "s", $email);
            mysqli_stmt_execute($delete_stmt);
            
            // Insert new token
            $insert_sql = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "sss", $email, $otp, $expiry);
            
            if (mysqli_stmt_execute($insert_stmt)) {
                // Send email
                $mail = new PHPMailer(true);
                try {
                    // --- MODIFIED: Use constants from mail_config.php ---
                    $mail->isSMTP();
                    $mail->Host       = MAIL_HOST;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = MAIL_USERNAME;
                    $mail->Password   = MAIL_PASSWORD;
                    $mail->SMTPSecure = MAIL_ENCRYPTION;
                    $mail->Port       = MAIL_PORT;
                    // --- End of modification ---

                    //Recipients
                    $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME); // Use constants here too
                    $mail->addAddress($email);

                    //Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Your Password Reset OTP for Vastra';
                    $mail->Body    = "Hello,<br><br>Your One-Time Password (OTP) for password reset is: <h1>$otp</h1>This code is valid for 10 minutes.<br><br>If you did not request this, please ignore this email.<br><br>Thank you,<br>The Vastra Team";

                    $mail->send();

                    // Redirect to OTP verification page
                    $_SESSION['reset_email'] = $email;
                    header('Location: verify-otp.php');
                    exit();

                } catch (Exception $e) {
                    $errors['general'] = "Message could not be sent. Please contact support. Mailer Error: {$mail->ErrorInfo}";
                }
            } else {
                 $errors['general'] = 'Database error. Please try again.';
            }
        } else {
            // To prevent user enumeration (a security best practice), show a generic message
            $message = 'If an account with that email exists, a password reset OTP has been sent.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password | Vastra</title>
    <link rel="stylesheet" href="style-login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-right" style="margin: auto; width: 50%;"> <!-- Center the form -->
            <div class="login-form-container">
                <div class="logo"><h2>VASTRA</h2></div>
                <form class="login-form" method="post" action="forgot-password.php">
                    <h3>Forgot Password</h3>
                    <p>Enter your email address and we will send you an OTP to reset your password.</p>

                    <?php if (!empty($message)): ?>
                        <div class='general-success'><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($errors['general'])): ?>
                        <div class='general-error'><?php echo htmlspecialchars($errors['general']); ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-field">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" id="email" name="email" placeholder="Enter your registered email" required>
                        </div>
                        <span class="error-msg"><?php echo htmlspecialchars($errors['email'] ?? ''); ?></span>
                    </div>
                    
                    <button type="submit" class="login-btn">Send OTP</button>

                    <div class="signup-link">
                        Remember your password? <a href="login.php">Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>