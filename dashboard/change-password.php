<?php
// Start the session to access login state
session_start();
// Path updated: config.php is one level up
include "../config.php";

// --- SECURITY CHECK ---
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    // Path updated: login.php is one level up
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = []; // Use an array for specific error messages

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Step 1: Fetch and Verify Current Password
    $stmt_pass = $conn->prepare("SELECT password FROM login WHERE user_id = ?");
    if (!$stmt_pass) {
        $errors['form'] = "A database error occurred (prepare failed). Please try again later.";
    } else {
        $stmt_pass->bind_param("i", $user_id);
        $stmt_pass->execute();
        $result = $stmt_pass->get_result();
        $user_login = $result->fetch_assoc();
        $stmt_pass->close();

        if (empty($current_password)) {
            $errors['current_password'] = "Old password is required.";
        } elseif (!$user_login || !password_verify($current_password, $user_login['password'])) {
            $errors['current_password'] = "The old password you entered is incorrect.";
        }
    }

    // Step 2: Validate New Password (only if current password is correct so far)
    if (!isset($errors['current_password']) && !isset($errors['form'])) {
        if (empty($new_password)) {
            $errors['new_password'] = "New password is required.";
        } elseif ($new_password === $current_password) { // <-- SECURITY IMPROVEMENT
            $errors['new_password'] = "New password cannot be the same as the old one.";
        } elseif (strlen($new_password) < 8) {
            $errors['new_password'] = "Password must be at least 8 characters long.";
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            $errors['new_password'] = "Password must contain at least one uppercase letter.";
        } elseif (!preg_match('/[a-z]/', $new_password)) {
            $errors['new_password'] = "Password must contain at least one lowercase letter.";
        } elseif (!preg_match('/[0-9]/', $new_password)) {
            $errors['new_password'] = "Password must contain at least one number.";
        }
    }

    // Step 3: Validate Confirm Password
    if (empty($confirm_password)) {
        $errors['confirm_password'] = "Please confirm your new password.";
    } elseif ($new_password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match.";
    }

    // Step 4: If all validation passes, update the password
    if (empty($errors)) {
        $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt_update = $conn->prepare("UPDATE login SET password = ? WHERE user_id = ?");
        if ($stmt_update) {
            $stmt_update->bind_param("si", $new_password_hashed, $user_id);
            if ($stmt_update->execute()) {
                // --- CORE CHANGE: NO MORE LOGOUT ---
                // Set a success message in the session and redirect to the dashboard.
                $_SESSION['success_message'] = "Your password has been changed successfully!";
                header("Location: ../dashboard.php");
                exit();
            } else {
                $errors['form'] = "Failed to update password due to a server error. Please try again.";
            }
            $stmt_update->close();
        } else {
            $errors['form'] = "A database error occurred (update failed). Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../index.css">
    <script src="../refresh.js"></script>
    <style>
        body { background-color: var(--light-gray); }
        .change-password-container { max-width: 600px; margin: 40px auto; padding-bottom: 50px; }
        .form-card { background: white; padding: 30px 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #eee; }
        .form-card h1 { text-align: center; margin-top: 0; margin-bottom: 30px; color: var(--secondary); font-size: 2rem; }
        
        .form-group { margin-bottom: 20px; position: relative; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 5px; transition: border-color 0.2s; }
        .form-group input.is-invalid { border-color: var(--error); }
        .error-text { color: var(--error); font-size: 0.875em; margin-top: 5px; display: block; min-height: 1em; /* Prevents layout shift */ }
        
        .form-actions { display: flex; gap: 15px; align-items: center; margin-top: 30px; }
        .btn-save { background-color: var(--primary); color: white; border: none; padding: 12px 30px; border-radius: 5px; cursor: pointer; font-weight: 500; font-size: 1rem; }
        .btn-cancel { color: var(--dark-gray); text-decoration: none; }

        #password-strength-status { margin-top: 15px; padding: 15px; border-radius: 5px; background-color: #f8f9fa; border: 1px solid #dee2e6; }
        #password-strength-status p { margin: 8px 0; transition: all 0.3s ease; display: flex; align-items: center; }
        #password-strength-status p i { margin-right: 10px; width: 20px; text-align: center; }
        #password-strength-status p.invalid { color: #721c24; }
        #password-strength-status p.valid { color: #155724; text-decoration: line-through; }
        
        .message-box { padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid #f5c6cb; text-align: center; background-color: #f8d7da; color: #721c24; font-weight: 500; }
    </style>
</head>
<body>
    <nav class="navbar">
       <div class="container nav-container">
            <a href="../index.php" class="logo">VASTRA</a>
            <?php include "nav_links.html"; ?>
            <div class="nav-icons">
                 <a href="../dashboard.php" title="My Account"><i class="far fa-user"></i></a>
                 <a href="../wishlist.php" title="Wishlist"><i class="far fa-heart"></i></a>
                 <a href="../cart.php" title="Shopping Cart"><i class="fas fa-shopping-bag"></i></a>
            </div>
        </div>
    </nav>
    
    <main class="container change-password-container">
        <div class="form-card">
            <h1>Change Your Password</h1>

            <?php if (isset($errors['form'])): ?>
                <div class="message-box"><?= htmlspecialchars($errors['form']); ?></div>
            <?php endif; ?>

            <form id="changePasswordForm" method="POST" action="change-password.php" novalidate>
                <div class="form-group">
                    <label for="current_password">Old Password</label>
                    <input type="password" name="current_password" id="current_password" required class="<?= isset($errors['current_password']) ? 'is-invalid' : '' ?>">
                    <small class="error-text" id="current_error_text"><?= htmlspecialchars($errors['current_password'] ?? '') ?></small>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" name="new_password" id="new_password" required class="<?= isset($errors['new_password']) ? 'is-invalid' : '' ?>">
                    <small class="error-text"><?= htmlspecialchars($errors['new_password'] ?? '') ?></small>
                    
                    <div id="password-strength-status">
                      <p id="length" class="invalid"><i class="fas fa-times-circle"></i>At least 8 characters</p>
                      <p id="lowercase" class="invalid"><i class="fas fa-times-circle"></i>At least one lowercase letter</p>
                      <p id="uppercase" class="invalid"><i class="fas fa-times-circle"></i>At least one uppercase letter</p>
                      <p id="number" class="invalid"><i class="fas fa-times-circle"></i>At least one number</p>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required class="<?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>">
                    <small class="error-text" id="confirm_error_text"><?= htmlspecialchars($errors['confirm_password'] ?? '') ?></small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-save">Update Password</button>
                    <a href="../dashboard.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </main>
    <?php include "../footer.html"; ?>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('changePasswordForm');
        const currentPasswordInput = document.getElementById('current_password');
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const confirmErrorText = document.getElementById('confirm_error_text');
        const currentErrorText = document.getElementById('current_error_text');
        
        let isPasswordValid = false;

        const criteria = {
            length: document.getElementById('length'),
            lowercase: document.getElementById('lowercase'),
            uppercase: document.getElementById('uppercase'),
            number: document.getElementById('number')
        };

        const validateCriterion = (element, isValid) => {
            const icon = element.querySelector('i');
            if (isValid) {
                element.classList.replace('invalid', 'valid');
                icon.classList.replace('fa-times-circle', 'fa-check-circle');
            } else {
                element.classList.replace('valid', 'invalid');
                icon.classList.replace('fa-check-circle', 'fa-times-circle');
            }
        };

        const validatePassword = () => {
            const pass = newPasswordInput.value;
            const hasLength = pass.length >= 8;
            const hasLowercase = /[a-z]/.test(pass);
            const hasUppercase = /[A-Z]/.test(pass);
            const hasNumber = /[0-9]/.test(pass);

            validateCriterion(criteria.length, hasLength);
            validateCriterion(criteria.lowercase, hasLowercase);
            validateCriterion(criteria.uppercase, hasUppercase);
            validateCriterion(criteria.number, hasNumber);
            
            isPasswordValid = hasLength && hasLowercase && hasUppercase && hasNumber;
            return isPasswordValid;
        };

        const validateConfirmPassword = () => {
            const passwordsMatch = newPasswordInput.value === confirmPasswordInput.value;
            if (!passwordsMatch && confirmPasswordInput.value) {
                confirmErrorText.textContent = 'Passwords do not match.';
                confirmPasswordInput.classList.add('is-invalid');
                return false;
            } else {
                // Clear JS-based error, but leave potential PHP error untouched until next input
                if (confirmErrorText.textContent === 'Passwords do not match.') {
                    confirmErrorText.textContent = '';
                }
                confirmPasswordInput.classList.remove('is-invalid');
                return true;
            }
        };

        newPasswordInput.addEventListener('input', () => {
            validatePassword();
            validateConfirmPassword();
        });

        currentPasswordInput.addEventListener('input', () => {
            currentErrorText.textContent = '';
        });
        
        confirmPasswordInput.addEventListener('input', validateConfirmPassword);
        
        // --- UX IMPROVEMENT: Prevent submission if client-side validation fails ---
        form.addEventListener('submit', (event) => {
            const isConfirmPasswordValid = validateConfirmPassword();
            // We re-run validatePassword to be sure, although isPasswordValid should be up to date
            if (!validatePassword() || !isConfirmPasswordValid) {
                event.preventDefault(); // Stop the form from submitting
                alert('Please correct the errors before submitting the form.');
            }
        });
    });
    </script>
</body>
</html>