<?php
session_start();
include "config.php"; 

if (isset($_SESSION['is_logged_in'])) {
    if (isset($_SESSION['admin_id'])) {
        header('Location: admin/dashboard.php');
        exit();
    }
    if (isset($_SESSION['user_id'])) {
        header('Location: index.php'); // or user profile page
        exit();
    }
}

if (isset($_POST['login'])) {

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $errors = [];

    //--- VALIDATE DATA --- 
    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    }

    // --- PROCESS DATA ---
    if (empty(array_filter($errors))) {
        $sql = "SELECT * FROM login WHERE email = '$email'";
        $result = mysqli_query($conn, $sql);

        if ($result) {
            if (mysqli_num_rows($result) === 1) {
                $row = mysqli_fetch_assoc($result);
                if($row['status'] === 'active') {

                    if (password_verify($password, $row['password'])) {
                        $_SESSION['email'] = $row['email'];
                        $_SESSION['is_logged_in'] = true; 
                        
                        $_SESSION['user_id'] = $row['user_id'];
                        if($row['role'] === 'admin'){
                            $_SESSION['admin_id'] = $row['user_id'];
                            
                            // After setting session, redirect to the admin dashboard.
                            header('Location: admin/dashboard.php');
                            exit();
                        }
                        
                        
                        header('Location: index.php'); // Redirect to a protected page
                        exit();
                    } else {
                        // Password does not match
                        $errors['general'] = 'Invalid password.';
                    }
                } else {
                    if($row['role'] === 'banend'){
                        $errors['general'] = 'Account is Banned.';
                    }else{
                        $errors['general'] = 'Account is unavaliable.';
                    }
                }
            } else {
                // No user found with that email
                $errors['general'] = 'Invalid email or password.';
            }
        } else {
            // Database query preparation failed
            $errors['general'] = 'A database error occurred. Please try again later.';
        }
    }
    
    // If there were any errors, store them in the session and redirect back
    $_SESSION['errors'] = $errors;
    $_SESSION['old_email'] = $email;
    $_SESSION['general'] = $errors['general'] ?? ''; // Store the general error specifically
    header('Location: login.php');
    exit();
}

// --- FRONTEND DISPLAY LOGIC ---
// Store session data in local variables for easier access in HTML
$errors = $_SESSION['errors'] ?? [];
$old_email = $_SESSION['old_email'] ?? '';
$general = $_SESSION['general'] ?? '';

// Unset the session variables so they don't persist on refresh
unset($_SESSION['errors'], $_SESSION['old_email'], $_SESSION['general']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style-login.css">
    <!-- <script src="refresh.js"></script> -->
</head>
<body>
    <div class="login-container">
        <!-- Left Side (Branding) -->
        <div class="login-left">
            <div class="login-left-content">
                <h1>Welcome Back!</h1>
                <p>Login to explore the latest fashion trends and exclusive offers from Vastra</p>
                 <img src="https://img.freepik.com/free-vector/online-shopping-concept-illustration_114360-1084.jpg" alt="Woman shopping online from her laptop">
            </div>
        </div>

        <!-- Right Side (Login Form) -->
        <div class="login-right">
            <div class="login-form-container">
                <div class="logo">
                    <h2>VASTRA</h2>
                </div>

                <form class="login-form" onsubmit="return validate()" method="post" action="login.php">
                    <h3>Login to your account</h3>

                    <!-- Display general error message -->
                    <?php if (!empty($general)): ?>
                        <div class='general-error'><?php echo htmlspecialchars($general); ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-field">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" id="email" name="email" value="test@example.com" placeholder="Enter your email" onkeyup="validateEmail()" value="<?php echo htmlspecialchars($old_email ?? ''); ?>"> 
                        </div>
                        <span class="error-msg" id="email-error"><?php echo htmlspecialchars($errors['email'] ?? ''); ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-field">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" id="password" name="password" value="TestPassword1" placeholder="Enter your password" onkeyup="validatePassword()">
                            <i class="fa-regular fa-eye input-icon-hidden" id="iconHidden"></i>
                        </div>
                        <span class="error-msg" id="password-error"><?php echo htmlspecialchars($errors['password'] ?? ''); ?></span>

                        <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
                    </div>
                    
                    <input type="hidden" id="role" name="role" value="buyer" required>

                    <button type="submit" name="login" class="login-btn">Login</button>

                    <div class="signup-link">
                        Don't have an account? <a href="register.php">Sign up</a>
                    </div>
                    
                    <hr class="section-divider">

                    <div class="user-link">
                        Are you a seller? <a href="seller-login.php">Login as Seller</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const emailError = document.getElementById('email-error');
        const passwordError = document.getElementById('password-error');
        const iconHidden = document.getElementById("iconHidden");

        function validateEmail() {
            const email = emailInput.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email) {
                emailError.textContent = 'Email address is required.';
                return false;
            } else if (!emailRegex.test(email)) {
                emailError.textContent = 'Please enter a valid email address.';
                return false;
            }
            emailError.textContent = ''; // Clear error message
            return true;
        }

        function validatePassword() {
            const password = passwordInput.value.trim();
            if (!password) {
                passwordError.textContent = 'Password is required.';
                return false;
            } else if (password.length < 6) {
                passwordError.textContent = 'Password must be at least 6 characters long.';
                return false;
            }
            passwordError.textContent = ''; // Clear error message
            return true;
        }

        // Main validation function to be called on submit
        function validateForm() {
            const isEmailValid = validateEmail();
            const isPasswordValid = validatePassword();
            // The form is valid only if both fields are valid
            return isEmailValid && isPasswordValid;
        }
        
        // Event Listeners
        iconHidden.addEventListener('click', function() {
            // Toggle between eye and eye-slash icons
            const isPassword = passwordInput.getAttribute('type') === 'password';
            if (isPassword) {
                passwordInput.setAttribute('type', 'text');
                iconHidden.classList.remove('fa-eye-slash');
                iconHidden.classList.add('fa-eye');
            } else {
                passwordInput.setAttribute('type', 'password');
                iconHidden.classList.remove('fa-eye');
                iconHidden.classList.add('fa-eye-slash');
            }
        });
    </script>
</body>
</html>

