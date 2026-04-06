<?php
// Start the session to access login state
session_start();
include "../config.php"; // Use ../ to go up one directory to find config.php

// --- 1. SECURITY CHECK ---
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header("Location: ../login.php"); // Redirect to the main login page
    exit;
}

$user_id = $_SESSION['user_id'];
$error_message = '';

// --- CHANGED HERE (1 of 3): Fetch user's name AND role ---
$user_firstname = '';
$user_role = ''; // Variable to store the user's role
$sql_user = "SELECT u.fname, l.role 
             FROM users u
             JOIN login l ON u.user_id = l.user_id
             WHERE u.user_id = ?";
if ($stmt_user = mysqli_prepare($conn, $sql_user)) {
    mysqli_stmt_bind_param($stmt_user, "i", $user_id);
    mysqli_stmt_execute($stmt_user);
    $result_user = mysqli_stmt_get_result($stmt_user);
    if($user_row = mysqli_fetch_assoc($result_user)) {
        $user_firstname = $user_row['fname'];
        $user_role = $user_row['role']; // Store the role
    }
    mysqli_stmt_close($stmt_user);
}

// --- CHANGED HERE (2 of 3): Handle POST request only if the user is NOT a seller ---
if ($user_role !== 'seller' && $_SERVER["REQUEST_METHOD"] == "POST") {
    
    $submitted_password = $_POST['current_password'] ?? '';

    if (empty($submitted_password)) {
        $error_message = "Password is required to confirm deactivation.";
    } else {
        // --- PASSWORD VERIFICATION STEP ---
        $stored_password = '';
        $sql_get_pass = "SELECT password FROM login WHERE user_id = ?";

        if ($stmt_pass = mysqli_prepare($conn, $sql_get_pass)) {
            mysqli_stmt_bind_param($stmt_pass, "i", $user_id);
            mysqli_stmt_execute($stmt_pass);
            $result_pass = mysqli_stmt_get_result($stmt_pass);
            if ($row_pass = mysqli_fetch_assoc($result_pass)) {
                $stored_password = $row_pass['password'];
            }
            mysqli_stmt_close($stmt_pass);
        }

        if ($stored_password && password_verify($submitted_password, $stored_password)) {
            // --- PASSWORD IS CORRECT, PROCEED WITH DEACTIVATION ---
            $sql_deactivate = "UPDATE login SET status = 'inactive' WHERE user_id = ?";

            if ($stmt_deactivate = mysqli_prepare($conn, $sql_deactivate)) {
                mysqli_stmt_bind_param($stmt_deactivate, "i", $user_id);
                
                if (mysqli_stmt_execute($stmt_deactivate) && mysqli_stmt_affected_rows($stmt_deactivate) > 0) {
                    $_SESSION['success_message'] = "Your account has been successfully deactivated.";
                    session_unset();
                    session_destroy();
                    header("Location: ../index.php");
                    exit;
                } else {
                    $error_message = "An error occurred during deactivation. Please try again.";
                }
                mysqli_stmt_close($stmt_deactivate);
            } else {
                $error_message = "A database error occurred. Please try again.";
            }
        } else {
            $error_message = "Incorrect password. Deactivation failed.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deactivate Account | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../index.css"> 
    <style>
        :root { --error: #c0392b; --error-bg: #ffebee; --info: #1565c0; --info-bg: #e3f2fd;}
        body { background-color: var(--light-gray); }
        .dashboard-card { background-color: var(--white); border-radius: 8px; padding: 30px 40px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); border: 1px solid #eee; max-width: 650px; margin: 40px auto 60px; }
        .deactivate-header { text-align: center; margin-bottom: 25px; }
        .deactivate-header i { font-size: 3rem; margin-bottom: 15px; }
        .deactivate-header i.fa-exclamation-triangle { color: var(--error); } /* For buyers */
        .deactivate-header i.fa-store-slash { color: var(--info); } /* For sellers */
        .deactivate-header h1 { font-size: 2.2rem; color: var(--secondary); margin-bottom: 10px; }
        .deactivate-content p, .deactivate-content ul { color: var(--dark-gray); line-height: 1.7; font-size: 1.05rem; text-align: left; }
        .deactivate-content ul { list-style-position: inside; padding-left: 10px; margin-bottom: 20px;}
        .deactivate-content strong { color: var(--secondary); }
        .confirmation-section { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
        .form-group { text-align: center; }
        .form-group label { display: block; font-weight: 600; color: var(--secondary); margin-bottom: 10px; font-size: 1.1rem; }
        .form-group input[type="password"] { width: 100%; max-width: 350px; padding: 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 1rem; text-align: center; }
        .deactivate-actions { margin-top: 30px; display: flex; justify-content: center; align-items: center; gap: 20px; }
        .btn { padding: 12px 28px; text-decoration: none; border-radius: 5px; font-weight: 600; font-size: 1rem; border: none; cursor: pointer; transition: all 0.3s; }
        .btn-secondary { background-color: #f0f0f0; color: #555; border: 1px solid #ddd; }
        .btn-secondary:hover { background-color: #e0e0e0; }
        .btn-danger { background-color: var(--error); color: white; }
        .btn-danger:hover { background-color: #a93226; }
        .error-message { text-align: center; margin-top: 20px; padding: 10px; background-color: var(--error-bg); color: var(--error); border: 1px solid var(--error); border-radius: 5px; font-weight: 500; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container nav-container">
            <a href="../index.php" class="logo">VASTRA</a>
            <?php include "../nav_links.html"; ?>
            <div class="nav-icons">
                 <a href="../wishlist.php" title="Wishlist"><i class="far fa-heart"></i></a>
                 <a href="../cart.php" title="Shopping Cart"><i class="fas fa-shopping-bag"></i></a>
                <div class="profile-card-container">
                    <a href="#" id="user-icon-btn"><i class="far fa-user"></i></a>
                    <span id="user-greeting">Hello, <?php echo htmlspecialchars($user_firstname); ?></span>
                </div>
            </div>
        </div>
    </nav>

    <main class="container">
        <div class="dashboard-card">

            <!-- --- CHANGED HERE (3 of 3): Conditional display based on user role --- -->
            <?php if ($user_role === 'seller'): ?>
                <!-- CONTENT FOR SELLERS -->
                <div class="deactivate-header">
                    <i class="fas fa-store-slash"></i>
                    <h1>Seller Account Active</h1>
                </div>
                <div class="deactivate-content">
                    <p>You cannot deactivate your main account while you have an active seller profile. This is to ensure all customer orders and inquiries are handled.</p>
                    <p><strong>To proceed, please contact customer support and request the closure of your seller account first.</strong> Once your seller profile is closed, you will be able to deactivate your user account.</p>
                </div>
                <div class="deactivate-actions">
                    <a href="../dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                </div>
            
            <?php else: // This block is for buyers or any other non-seller role ?>
                <!-- ORIGINAL CONTENT FOR BUYERS -->
                <div class="deactivate-header">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h1>Deactivate Your Account</h1>
                </div>
                <div class="deactivate-content">
                    <p>You are about to deactivate your Vastra account. Please read the following carefully:</p>
                    <ul>
                        <li>You will be logged out immediately.</li>
                        <li>You will <strong>not</strong> be able to log in again.</li>
                        <li>Your personal information and order history will not be deleted.</li>
                        <li>To reactivate your account, you will need to contact customer support.</li>
                    </ul>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <form method="POST" action="deactivate.php" class="confirmation-section">
                    <div class="form-group">
                        <label for="current_password">Enter Your Password to Confirm</label>
                        <input type="password" name="current_password" id="current_password" placeholder="••••••••" required>
                    </div>
                    <div class="deactivate-actions">
                        <a href="../dashboard.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-danger">Confirm & Deactivate Account</button>
                    </div>
                </form>

            <?php endif; ?>
            <!-- End of conditional display -->
        </div>
    </main>
    
    <?php include '../footer.html'; ?>
    <script>
    // No changes needed in JavaScript
    document.addEventListener('DOMContentLoaded', () => {
        const profileCardContainer = document.querySelector('.profile-card-container');
        const profileCard = document.getElementById('profile-card');
        if (profileCardContainer && profileCard) {
            profileCardContainer.addEventListener('click', (event) => {
                event.stopPropagation();
                if (event.target.id === 'user-greeting' || event.target.closest('#user-icon-btn')) {
                    profileCard.classList.toggle('active');
                }
            });
        }
        document.addEventListener('click', (event) => {
            if (profileCard && profileCard.classList.contains('active')) {
                if (!profileCardContainer.contains(event.target)) {
                    profileCard.classList.remove('active');
                }
            }
        });
    });
    </script>
</body>
</html>