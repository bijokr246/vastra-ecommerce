<?php
session_start();
include "config.php"; // Make sure this path is correct



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
        $sql = "SELECT * FROM login WHERE email = '$email' AND role ='seller'";
        $result = mysqli_query($conn, $sql);

        if ($result) {
            if (mysqli_num_rows($result) === 1) {
                $row = mysqli_fetch_assoc($result);

                if($row['status'] === 'active') {
                    
                    if (password_verify($password, $row['password'])) {
                        // Login successful for seller
                        $_SESSION['email'] = $row['email'];
                        $_SESSION['user_id'] = $row['user_id'];
                        $_SESSION['role'] = 'seller'; // Set role in session
                        
                        header('Location: seller/dashboard.php'); // Redirect to a seller-specific dashboard
                        exit();
                    } else {
                        // Password does not match or role is not 'seller'
                        $errors['general'] = 'Invalid seller credentials.';
                    }
                }else{
                    if($row['role'] === 'banned'){
                        $errors['general'] = 'Account is Banned.';
                    }else{
                        $errors['general'] = 'Account is unavaliable.';
                    }
                }
            } else {
                // No user found with that email
                $errors['general'] = 'Invalid seller credentials.';
            }
        } else {
            // Database query failed
            $errors['general'] = 'A database error occurred. Please try again later.';
        }
    }
    
    // If there were any errors, store them in the session and redirect back
    $_SESSION['errors'] = $errors;
    $_SESSION['old_email'] = $email;
    $_SESSION['general'] = $errors['general'] ?? ''; 
    header('Location: seller-login.php'); // Redirect back to this page
    exit();
}

// --- FRONTEND DISPLAY LOGIC (Same as your user login) ---
$errors = $_SESSION['errors'] ?? [];
$old_email = $_SESSION['old_email'] ?? '';
$general = $_SESSION['general'] ?? '';

unset($_SESSION['errors'], $_SESSION['old_email'], $_SESSION['general']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Login | Vastra Seller Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style-login.css">
    <script src="refresh.js"></script>
</head>

<body>
    <div class="login-container">
        <!-- Left Side (Branding for Sellers) -->
        <div class="login-left">
            <div class="login-left-content">
                <h1>Seller Portal</h1>
                <p>Access your dashboard to manage products, view orders, and grow your business.</p>
                <img src="https://img.freepik.com/free-vector/data-points-concept-illustration_114360-2374.jpg"
                    alt="Person managing business data and charts">
            </div>
        </div>

        <!-- Right Side (Seller Login Form) -->
        <div class="login-right">
            <div class="login-form-container">
                <div class="logo">
                    <h2>VASTRA</h2>
                </div>

                <form class="login-form" onsubmit="return validateForm()" method="post" action="seller-login.php">
                    <h3>Seller Account Login</h3>

                    <!-- Display general error message from PHP -->
                    <?php if (!empty($general)): ?>
                    <div class='general-error'>
                        <?php echo htmlspecialchars($general); ?>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="email">Seller Email Address</label>
                        <div class="input-field">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" id="email" name="email" placeholder="Enter your seller email"
                                onkeyup="validateEmail()" value="<?php echo htmlspecialchars($old_email ?? ''); ?>">
                        </div>
                        <span class="error-msg" id="email-error">
                            <?php echo htmlspecialchars($errors['email'] ?? ''); ?>
                        </span>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-field">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" id="password" name="password" placeholder="Enter your password"
                                onkeyup="validatePassword()">
                            <i class="fa-regular fa-eye-slash input-icon-hidden" id="iconHidden"></i>
                        </div>
                        <span class="error-msg" id="password-error">
                            <?php echo htmlspecialchars($errors['password'] ?? ''); ?>
                        </span>
                        <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
                    </div>

                    <button type="submit" name="login" class="login-btn">Login to Dashboard</button>

                    <hr class="section-divider">

                    <!-- Link back to the customer login page -->
                    <div class="user-link">
                        Not a seller? <a href="login.php">Login as a Customer</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // --- THIS JAVASCRIPT IS IDENTICAL TO YOUR login.php FOR VALIDATION ---
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
            emailError.textContent = ''; // Clear error message if valid
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
            passwordError.textContent = ''; // Clear error message if valid
            return true;
        }

        function validateForm() {
            // Run both validation functions to show all errors at once on submit
            const isEmailValid = validateEmail();
            const isPasswordValid = validatePassword();
            return isEmailValid && isPasswordValid; // Form submits only if both are true
        }

        iconHidden.addEventListener('click', function () {
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

