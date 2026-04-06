<?php
session_start();
include "config.php"; 

if (isset($_POST['register'])) {
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    $errors[] = [];

    //--- VALIDATE DATA --- 

    if (empty($firstname)) {
        $errors['firstname'] = 'First name is required.';
    } elseif (!preg_match("/^[a-zA-Z'-]{2,}$/", $firstname)) {
        $errors['firstname'] = 'Please enter a valid first name.';
    }

    // Last Name Validation
    if (empty($lastname)) {
        $errors['lastname'] = 'Last name is required.';
    } elseif (!preg_match("/^[a-zA-Z'-]{1,}$/", $lastname)) {
        $errors['lastname'] = 'Please enter a valid last name.';
    }

    
    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } else {
        $sql = "SELECT user_id FROM users WHERE email = '$email'";
        $result = mysqli_query($conn, $sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $errors['email'] = "An account with this email already exists.";
        }
    }

    if (empty($phone)) {
        $errors['phone'] = "Phone number is required.";
    } else {
        // Validate phone number format (e.g., 10 digits)
        if (!preg_match("/^[0-9]{10}$/", $phone)) {
            $errors['phone'] = "Please enter a valid 10-digit phone number.";
        }
    }

    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
        $errors['password'] = "Password must be at least 8 characters, with one number and one uppercase letter.";
    }

    if (empty($confirmPassword)) {
        $errors['confirmPassword'] = "Please confirm your password.";
    } else {
        if ($password !== $confirmPassword) {
            $errors['confirmPassword'] = "Passwords do not match.";
        }
    }


    // VALIDATE THE EMAIL IS UNIQUE
    $sql = "SELECT * FROM users WHERE email = '$email'";
    $result = mysqli_query($conn, $sql);
    if($result){
        if(mysqli_num_rows($result) > 0){
            $errors['email'] = "An account with this email already exists.";
        }
    }

    if(empty(array_filter($errors))){
        $sql = "INSERT INTO users(fname, lname, email, phone) VALUES ('$firstname', '$lastname', '$email', '$phone')";
        $result = mysqli_query($conn, $sql);

        if($result){  
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);           
                
            $user_id = mysqli_insert_id($conn); // More efficient way to get the last inserted ID
            $sql_login = "INSERT INTO login(user_id, email, password) VALUES ('$user_id', '$email', '$hashed_password')";

            if(mysqli_query($conn, $sql_login)){
                $_SESSION['email'] = $email;
                $_SESSION['user_id'] = $user_id;
                $_SESSION['is_logged_in'] = true; 
                header('Location: index.php'); // Redirect to a protected page
                exit();

            } else {
                // Handle error inserting into the login table
                $errors['general'] = 'A database error occurred. Please try again later.';
            }
            
        } else {
            // Database query preparation failed
            $errors['general'] = 'A database error occurred. Please try again later.';
        }
    }

    $_SESSION['errors'] = $errors;
    $_SESSION['email'] = $email;
    $_SESSION['firstname'] = $firstname;
    $_SESSION['lastname'] = $lastname;
    $_SESSION['phone'] = $phone;
    $_SESSION['general'] = $errors['general'] ?? ''; // Store the general error specifically
    header('Location: register.php');
    exit();
}

$errors = $_SESSION['errors'] ?? [];
$email = $_SESSION['email'] ?? '';
$firstname = $_SESSION['firstname'] ?? '';
$lastname = $_SESSION['lastname'] ?? '';
$phone = $_SESSION['phone'] ?? '';
$general = $_SESSION['general'] ?? '';

// Unset the session variables so they don't persist on refresh
unset($_SESSION['errors'], $_SESSION['email'], $_SESSION['general'], $_SESSION['fullname'], $_SESSION['phone']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #ff3f6c;
            --primary-dark: #e6395a;
            --secondary: #282c3f;
            --light-gray: #f5f5f6;
            --dark-gray: #696b79;
            --white: #ffffff;
            --black: #000000;
            --error: #c0392b; 
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #fafafa;
            color: var(--secondary);
        }

        .register-container {
            display: flex;
            min-height: 100vh;
        }

        .register-left {
            flex: 1;
            background: linear-gradient(135deg, #ff3f6c 0%, #ff6b8b 100%);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: var(--white);
        }

        @media (min-width: 768px) {
            .register-left {
                display: flex;
            }
        }

        .register-left-content {
            max-width: 400px;
            text-align: center;
        }

        .register-left h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .register-left p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 2rem;
        }

        .register-left img {
            max-width: 300px;
            margin: 0 auto;
        }

        .register-right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .register-form-container {
            max-width: 450px;
            width: 100%;
            background-color: var(--white);
            padding: 2.5rem;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: 1px;
        }

        .register-form h3 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--secondary);
        }

        .form-group {
            margin-bottom: 1.2rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--secondary);
        }

        .input-field {
            position: relative;
        }

        .input-field input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border 0.3s;
        }

        .input-field input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--dark-gray);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--dark-gray);
            cursor: pointer;
        }
        
        /* CSS CHANGE: This prevents the layout from jumping when errors appear/disappear */
        .error-msg {
            color: var(--error);
            font-size: 0.8rem;
            margin-top: 5px;
            display: hidden; 
            min-height: 1rem; /* Ensures consistent height */
        }

        .register-btn {
            width: 100%;
            padding: 12px;
            background-color: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 1rem;
        }

        .register-btn:hover {
            background-color: var(--primary-dark);
        }

        .login-link {
            text-align: center;
            color: var(--dark-gray);
            margin-top: 1.5rem;
        }

        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .general-error { 
            background-color: #ffebee; 
            color: var(--error); 
            border: 1px solid var(--error); 
            border-radius: 5px; 
            padding: 10px; 
            margin-bottom: 1.5rem; 
            text-align: center; 
        }
    </style>
</head>
<body>
    <div class="register-container">
        <!-- Left Side (Branding) -->
        <div class="register-left">
            <div class="register-left-content">
                <h1>Join Vastra!</h1>
                <p>Create an account to enjoy personalized recommendations, faster checkout, and exclusive offers</p>
                <img src="https://img.freepik.com/free-vector/online-shopping-concept-illustration_114360-1084.jpg" alt="Vastra Fashion Illustration">
            </div>
        </div>

        <!-- Right Side (Register Form) -->
        <div class="register-right">
            <div class="register-form-container">
                <div class="logo">
                    <h2>VASTRA</h2>
                </div>

                <form class="register-form" id="registerForm" method="post" onsubmit="return validateForm()">
                    <h3>Create your account</h3>

                    <?php if (!empty($general)): ?>
                        <div class='general-error'><?php echo htmlspecialchars($general); ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="firstName">First Name</label>
                        <div class="input-field">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" id="firstName" name="firstname" placeholder="Enter your first name" onkeyup="validateFirstName()" value="<?php echo htmlspecialchars($firstname ?? ''); ?>">
                        </div>
                        <span class="error-msg" id="fname-error"><?php echo htmlspecialchars($errors['firstname'] ?? ''); ?></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="lastName">Last Name</label>
                        <div class="input-field">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" id=lastlName" name="lastname" placeholder="Enter your last name" onkeyup="validateLastName()" value="<?php echo htmlspecialchars($lastname ?? ''); ?>">
                        </div>
                        <span class="error-msg" id="lname-error"><?php echo htmlspecialchars($errors['lastname'] ?? ''); ?></span>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-field">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" id="email" name="email" placeholder="Enter your email" onkeyup="validateEmail()" value="<?php echo htmlspecialchars($email); ?>" >
                        </div>
                        <span class="error-msg" id="email-error"><?php echo htmlspecialchars($errors['email'] ?? '') ?></span>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <div class="input-field">
                            <i class="fas fa-phone input-icon"></i>
                            <input type="tel" id="phone" name="phone" placeholder="Enter your phone number" onkeyup="validatePhone()" value="<?php echo htmlspecialchars($phone); ?>">
                        </div>
                        <span class="error-msg" id="phone-error"><?php echo htmlspecialchars($errors['phone'] ?? ''); ?></span>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-field">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" id="password" name="password" placeholder="Create a password" onkeyup="validatePassword()">
                            <i class="fas fa-eye-slash password-toggle" id="iconHidden"></i>
                        </div>
                        <span class="error-msg" id="password-error"><?php echo htmlspecialchars($errors['password'] ?? ''); ?></span>
                    </div>

                    <div class="form-group">
                        <label for="confirmPassword">Confirm Password</label>
                        <div class="input-field">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm your password" onkeyup="validateConfirmPassword()">
                        </div>
                        <span class="error-msg" id="confirm-password-error"><?php echo htmlspecialchars($errors['confirmPassword'] ?? ''); ?></span>
                    </div>

                    <input type="submit" name="register" value="Create Account" class="register-btn">

                    <div class="login-link">
                        Already have an account? <a href="login.php">Log in</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JAVASCRIPT UPDATED to match the login page validation style -->
    <script>
        // Get all elements needed for validation
        const firstNameInput = document.getElementById('firstName');
        const lastNameInput = document.getElementById('lastName');
        const emailInput = document.getElementById('email');
        const phoneInput = document.getElementById('phone');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirmPassword');

        const fnameError = document.getElementById('fname-error');
        const lnameError = document.getElementById('lname-error');
        const emailError = document.getElementById('email-error');
        const phoneError = document.getElementById('phone-error');
        const passwordError = document.getElementById('password-error');
        const confirmPasswordError = document.getElementById('confirm-password-error');
        
        // --- Individual Validation Functions ---
        function validateFirstName() {
            const fname = firstNameInput.value.trim();
            const nameRegex = /^[A-Za-z]+(([ '-][A-Za-z]+)*)( [A-Za-z]+(([ '-][A-Za-z]+)*))*$/;
            if (fname.value.trim() === '') {
                fnameError.textContent = 'Name is required.';
                return false;
            } else if (!namelRegex.test(lname)) {
                fnameError.textContent = 'Please enter a valid name.';
                return false;
            }
            fnameError.textContent = '';
            return true;
        }

        function validateLastName() {
            const lname = lastNameInput.value.trim();
            const nameRegex = /^[A-Za-z]{3,}/;
            if (lname.value.trim() === '') {
                lnameError.textContent = 'Name is required.';
                return false;
            } else if (!namelRegex.test(lname)) {
                lnameError.textContent = 'Please enter a valid name.';
                return false;
            }
            lnameError.textContent = '';
            return true;
        }

        function validateEmail() {
            const email = emailInput.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email === '') {
                emailError.textContent = 'Email address is required.';
                return false;
            } else if (!emailRegex.test(email)) {
                emailError.textContent = 'Please enter a valid email address.';
                return false;
            }
            emailError.textContent = '';
            return true;
        }

        function validatePhone() {
            const phone = phoneInput.value.trim();
            const phoneRegex = /^[0-9]{10}$/;
            if (phone === '') {
                phoneError.textContent = 'Phone number is required.';
                return false;
            } else if (!phoneRegex.test(phone)) {
                phoneError.textContent = 'Please enter a valid 10 digit phone number.';
                return false;
            }
            phoneError.textContent = '';
            return true;
        }

        function validatePassword() {
            const passwordRegex = /^(?=.*[A-Z])(?=.*\d)[A-Za-z\d]{8,}$/;

            if (!passwordRegex.test(passwordInput.value)) {
                passwordError.textContent = 'Password must be at least 8 characters long and one Upper case.';
                return false;
            }
            passwordError.textContent = '';
            validateConfirmPassword(); // Re-check confirmation if main password changes
            return true;
        }

        function validateConfirmPassword() {
            if (confirmPasswordInput.value !== passwordInput.value) {
                confirmPasswordError.textContent = 'Passwords do not match.';
                return false;
            }
            confirmPasswordError.textContent = '';
            return true;
        }
        
        // --- Main Form Validation Function (called on submit) ---
        function validateForm() {
            // Run all validations and store their results
            const isfNameValid = validateFirstName();
            const islNameVaild =  validateLastName();
            const isEmailValid = validateEmail();
            const isPhoneValid = validatePhone();
            const isPasswordValid = validatePassword();
            const isConfirmPasswordValid = validateConfirmPassword();

            // Return true only if all validations pass
            if (isfNameValid && islNameValid && isEmailValid && isPhoneValid && isPasswordValid && isConfirmPasswordValid) {
                return true; 
            } else {
                // If any validation fails, prevent form submission
                return false;
            }
        }

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