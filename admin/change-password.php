<?php
// 1. START THE SESSION & CHECK FOR LOGIN
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// 2. INCLUDE DATABASE CONNECTION
include "../config.php";

// 3. INITIALIZE VARIABLES & PROCESS FORM
// --- Initialize variables to hold messages ---
$current_password_err = $new_password_err = $confirm_password_err = "";
$success_msg = "";

// --- Process form data when form is submitted ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // A. VALIDATE CURRENT PASSWORD
    if (empty(trim($_POST["current_password"]))) {
        $current_password_err = "Please enter your current password.";
    } else {
        $current_password = trim($_POST["current_password"]);
    }

    // B. VALIDATE NEW PASSWORD
    if (empty(trim($_POST["new_password"]))) {
        $new_password_err = "Please enter a new password.";
    } elseif (strlen(trim($_POST["new_password"])) < 8) {
        $new_password_err = "Password must have at least 8 characters.";
    } elseif (!preg_match('/[A-Z]/', trim($_POST["new_password"]))) {
        $new_password_err = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[0-9]/', trim($_POST["new_password"]))) {
        $new_password_err = "Password must contain at least one number.";
    } else {
        $new_password = trim($_POST["new_password"]);
    }

    // C. VALIDATE CONFIRM PASSWORD
    if (empty(trim($_POST["confirm_new_password"]))) {
        $confirm_password_err = "Please confirm the new password.";
    } else {
        $confirm_new_password = trim($_POST["confirm_new_password"]);
        if (empty($new_password_err) && ($new_password != $confirm_new_password)) {
            $confirm_password_err = "Passwords did not match.";
        }
    }

    // D. CHECK FOR ERRORS BEFORE UPDATING DATABASE
    if (empty($current_password_err) && empty($new_password_err) && empty($confirm_password_err)) {
        // Prepare a select statement to get the current hashed password
        $sql = "SELECT password FROM login WHERE user_id = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $_SESSION['admin_id']);

            if ($stmt->execute()) {
                $stmt->store_result();

                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($password_db);
                    $stmt->fetch();

                    // Verify the submitted current password against the stored hash
                    if ($current_password === $password_db) {
                        // Password is correct, now hash and update the new password
                        $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);

                        $sql_update = "UPDATE login SET password = ? WHERE user_id = ?";

                        if ($stmt_update = $conn->prepare($sql_update)) {
                            $stmt_update->bind_param("si", $new_password_hashed, $_SESSION['admin_id']);

                            if ($stmt_update->execute()) {
                                $success_msg = "Password changed successfully!";
                            } else {
                                $current_password_err = "Oops! Something went wrong. Please try again later.";
                            }
                            $stmt_update->close();
                        }
                    } else {
                        // Display an error message if the current password is not valid
                        $current_password_err = "The current password you entered was not valid.";
                    }
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }
}

// 4. FETCH DYNAMIC DATA FOR HEADER
$result_admin_name = $conn->query("SELECT fname FROM users WHERE user_id = " . $_SESSION['admin_id']);
$admin_name = $result_admin_name->fetch_assoc()['fname'];

// Close the connection
$conn->close();

// --- Helper for Active Nav Link ---
$current_page = basename($_SERVER['PHP_SELF']);

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Vastra | Change Password</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <script src="../refresh.js"></script>
  <style>
    /* Add styles for form validation messages */
    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
    }
    .form-group input {
        width: 100%;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 5px;
        box-sizing: border-box; /* Important */
    }
    .form-group .error-message, .form-group .password-requirements {
        color: #d9534f;
        font-size: 0.9em;
        margin-top: 5px;
    }
    .form-group .password-requirements {
        color: #666;
    }
    .form-group .success-message {
        color: #5cb85c;
        font-weight: bold;
        text-align: center;
        margin-bottom: 1rem;
        padding: 1rem;
        border: 1px solid #5cb85c;
        background-color: #dff0d8;
        border-radius: 5px;
    }
    .submit-btn {
        background-color: #ff3f6c;
        color: white;
        padding: 12px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 1em;
        transition: background-color 0.3s;
    }
    .submit-btn:hover {
        background-color: #e6395a;
    }
  </style>
</head>

<body>
  <aside class="sidebar">
    <?php require "header.php" ?>
  </aside>

  <div class="main-content">
    <header class="header">
      <h1>Change Password</h1>
      <div class="admin-profile"><a href="#"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($admin_name); ?></a></div>
    </header>

    <main>
        <section class="content-card">
            <h2>Update Your Password</h2>

            <form id="changePasswordForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" novalidate>

                <?php if(!empty($success_msg)): ?>
                    <div class="form-group">
                        <p class="success-message"><?php echo $success_msg; ?></p>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                    <span id="currentPasswordError" class="error-message"><?php echo $current_password_err; ?></span>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required>
                    <p class="password-requirements">Must be at least 8 characters, with 1 uppercase letter and 1 number.</p>
                    <span id="newPasswordError" class="error-message"><?php echo $new_password_err; ?></span>
                </div>

                <div class="form-group">
                    <label for="confirm_new_password">Confirm New Password</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" required>
                    <span id="confirmPasswordError" class="error-message"><?php echo $confirm_password_err; ?></span>
                </div>

                <div class="form-group">
                    <button type="submit" class="submit-btn">Change Password</button>
                </div>
            </form>

        </section>
    </main>
  </div>

  <script>
     document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('changePasswordForm');
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_new_password');
        const currentPasswordInput = document.getElementById('current_password');
        const newPasswordError = document.getElementById('newPasswordError');
        const confirmPasswordError = document.getElementById('confirmPasswordError');
        const currentPasswordError = document.getElementById('currentPasswordError');
        
        // --- Real-time Validation Functions ---

        function validateCurrentPassword(){

        }

        function validateNewPassword() {
            const newPassword = newPasswordInput.value;
            let errorMessage = '';

            if (newPassword.length > 0) { // Only validate if there's input
                if (newPassword.length < 8) {
                    errorMessage = 'Password must be at least 8 characters long.';
                } else if (!/[A-Z]/.test(newPassword)) {
                    errorMessage = 'Password must contain at least one uppercase letter.';
                } else if (!/[0-9]/.test(newPassword)) {
                    errorMessage = 'Password must contain at least one number.';
                }
            }

            newPasswordError.textContent = errorMessage;
            return errorMessage === ''; // Return true if valid, false if not
        }

        function validateConfirmPassword() {
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            let errorMessage = '';

            // Only show error if the confirm field has text and it doesn't match
            if (confirmPassword.length > 0 && newPassword !== confirmPassword) {
                errorMessage = 'Passwords do not match.';
            }

            confirmPasswordError.textContent = errorMessage;
            return errorMessage === ''; // Return true if valid, false if not
        }
        
        function validateCurrentPassword() {
            const currentPassword = currentPasswordInput.value;
            let errorMessage = '';

            // Only show error if the confirm field has text and it doesn't match
            if (currentPassword.length < 0) {
                errorMessage = 'Passwords should be enter.';
            }

            currentPasswordError.textContent = errorMessage;
            return errorMessage === ''; // Return true if valid, false if not
        }


        // --- Event Listeners for Real-time Feedback ---

        // When user types in the "New Password" field
        newPasswordInput.addEventListener('input', () => {
            validateNewPassword();
            // Also re-validate the confirmation field, as it might now match or mismatch
            validateConfirmPassword();
        });

        // When user types in the "Confirm New Password" field
        confirmPasswordInput.addEventListener('input', validateConfirmPassword);
        currentPasswordInput.addEventListener('input', validateCurrentPassword);


        // --- Final Validation on Form Submit ---

        form.addEventListener('submit', function(event) {
            // Run all validations one last time to be absolutely sure
            const isCurrentPassword = validateCurrentPassword();
            const isNewPasswordValid = validateNewPassword();
            const isConfirmPasswordValid = validateConfirmPassword();

            // If either validation fails, prevent the form from submitting
            if (!isNewPasswordValid || !isConfirmPasswordValid) {
                event.preventDefault(); // Stop the form submission
                console.log("Form submission prevented due to validation errors.");
            }
        });
    });
  </script>

</body>
</html>