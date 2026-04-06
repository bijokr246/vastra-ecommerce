<?php
// contact.php
session_start();
include "config.php";

// --- 1. MODIFIED AUTHENTICATION CHECK ---
// Instead of redirecting, we just set a flag to know the login state.
$is_logged_in = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;

// --- 2. CONDITIONAL USER DATA FETCHING ---
// Initialize variables for all users (guests and logged-in)
$user_id = null;
$user_fullname = '';
$user_email = '';
$user_firstname = ''; // For navbar

// Only fetch data if the user is actually logged in
if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $sql_user = "SELECT fname, lname, email FROM users WHERE user_id = ?";
    if ($stmt_user = mysqli_prepare($conn, $sql_user)) {
        mysqli_stmt_bind_param($stmt_user, "i", $user_id);
        mysqli_stmt_execute($stmt_user);
        $result_user = mysqli_stmt_get_result($stmt_user);
        if ($user_row = mysqli_fetch_assoc($result_user)) {
            $user_fullname = trim($user_row['fname'] . ' ' . $user_row['lname']);
            $user_email = $user_row['email'];
            $user_firstname = $user_row['fname'];
        }
        mysqli_stmt_close($stmt_user);
    }
}

// --- 3. CONDITIONAL FORM SUBMISSION HANDLING ---
$errors = [];
$success_message = '';

// CRITICAL: Only process the form if the request is POST AND the user is logged in.
if ($_SERVER["REQUEST_METHOD"] == "POST" && $is_logged_in) {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    // Basic Validation
    if (empty($subject)) {
        $errors[] = "Subject is required.";
    }
    if (empty($message)) {
        $errors[] = "Message cannot be empty.";
    } elseif (strlen($message) < 10) {
        $errors[] = "Message must be at least 10 characters long.";
    }

    // If there are no errors, proceed to insert into the database
    if (empty($errors)) {
        $sql_insert = "INSERT INTO contact_messages (user_id, subject, message) VALUES (?, ?, ?)";
        if ($stmt_insert = mysqli_prepare($conn, $sql_insert)) {
            mysqli_stmt_bind_param($stmt_insert, "iss", $user_id, $subject, $message);
            
            if (mysqli_stmt_execute($stmt_insert)) {
                $success_message = "Thank you for contacting us! We have received your message and will get back to you shortly.";
            } else {
                $errors[] = "Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt_insert);
        } else {
            $errors[] = "Database error. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="refresh.js"></script>
    <style>
        body {
            background-color: #f9f9f9;
            font-family: 'Poppins', Arial, sans-serif;
        }

        .page-header-contact {
            background: linear-gradient(135deg, #282c3f 0%, #44495e 100%);
            padding: 80px 20px;
            text-align: center;
            color: white;
        }

        .page-header-contact h1 {
            font-size: 3rem;
            margin-bottom: 10px;
        }

        .page-header-contact p {
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto;
            opacity: 0.9;
        }

        .contact-section {
            padding: 60px 20px;
        }

        .contact-section .container {
            max-width: 1100px;
            margin: 0 auto;
            background-color: #fff;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            overflow: hidden;
        }

        .contact-wrapper {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
        }

        .contact-info {
            background-color: #f7f7f7;
            padding: 40px;
            border-right: 1px solid #e0e0e0;
        }

        .contact-info h2 {
            font-size: 1.8rem;
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
        }

        .contact-info p {
            color: #666;
            line-height: 1.7;
            margin-bottom: 30px;
        }

        .info-item {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            color: #555;
        }

        .info-item i {
            font-size: 1.5rem;
            color: #ff3f6c;
            width: 40px;
            text-align: center;
            margin-right: 15px;
        }

        .info-item span {
            font-size: 1rem;
        }

        .contact-form {
            padding: 40px;
        }

        .contact-form h2 {
            font-size: 1.8rem;
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #444;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ff3f6c;
            box-shadow: 0 0 0 3px rgba(255, 63, 108, 0.2);
        }

        /* --- NEW STYLES for disabled state --- */
        .form-group input[readonly],
        .form-group textarea[readonly] {
            background-color: #f0f0f0;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .btn-submit {
            display: inline-block;
            background-color: #ff3f6c;
            color: white !important;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            text-align: center;
        }

        .btn-submit:hover {
            background-color: #e6395a;
        }

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            border: 1px solid transparent;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        .message.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }

        .message ul {
            margin: 0;
            padding-left: 20px;
        }

        @media (max-width: 992px) {
            .contact-wrapper {
                grid-template-columns: 1fr;
            }

            .contact-info {
                border-right: none;
                border-bottom: 1px solid #e0e0e0;
            }
        }
    </style>
</head>

<body>
    <!-- Navbar will show different states based on login -->
    <nav class="navbar">
        <div class="container nav-container">
            <button class="mobile-menu-btn" id="mobile-menu-button"><i class="fas fa-bars"></i></button>
            <a href="index.php" class="logo">VASTRA</a>
            <?php include "nav_links.html"; ?>
            <div class="nav-icons">
                <a href="search.php"><i class="fas fa-search"></i></a>
                <a href="wishlist.php"><i class="far fa-heart"></i></a>
                <a href="cart.php"><i class="fas fa-shopping-bag"></i></a>
                <div class="profile-card-container">
                    <a href="#" id="user-icon-btn"><i class="far fa-user"></i></a>
                    <?php if ($is_logged_in): ?>
                    <span class="user-greeting-text">Hello,
                        <?php echo htmlspecialchars($user_firstname); ?>
                    </span>
                    <div id="profile-card" class="profile-card">
                        <div class="profile-header">
                            <h4>Hello,
                                <?php echo htmlspecialchars($user_firstname); ?>
                            </h4>
                            <p>
                                <?php echo htmlspecialchars($user_email); ?>
                            </p>
                        </div>
                        <ul class="profile-card-links">
                            <li><a href="dashboard.php"><i class="far fa-user-circle fa-fw"></i> My Profile</a></li>
                            <li><a href="myorders.php"><i class="fas fa-box-open fa-fw"></i> Orders</a></li>
                            <li><a href="wishlist.php"><i class="far fa-heart fa-fw"></i> Wishlist</a></li>
                            <li><a href="logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> Logout</a></li>
                        </ul>
                    </div>
                    <?php else: ?>
                    <div id="profile-card" class="profile-card">
                        <div class="profile-header">
                            <h4>Welcome</h4>
                            <p>To access account and manage orders</p>
                        </div>
                        <a href="login.php" class="btn-login-signup">LOGIN / SIGNUP</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <header class="page-header-contact">
        <h1>Get In Touch</h1>
        <p>We'd love to hear from you! Whether you have a question, feedback, or just want to say hello, our team is
            ready to answer all your questions.</p>
    </header>

    <section class="contact-section">
        <div class="container">
            <div class="contact-wrapper">
                <div class="contact-info">
                    <h2>Contact Information</h2>
                    <p>Fill up the form and our team will get back to you within 24 hours.</p>
                    <div class="info-item"><i class="fas fa-phone-alt"></i><span>+91 12345 67890</span></div>
                    <div class="info-item"><i class="fas fa-envelope"></i><span>support@vastra.com</span></div>
                    <div class="info-item"><i class="fas fa-map-marker-alt"></i><span>Vastra HQ, Infopark, Kochi,
                            Kerala, India</span></div>
                </div>

                <div class="contact-form">
                    <h2>Send us a Message</h2>

                    <?php if (!empty($success_message)): ?>
                    <div class="message success">
                        <?php echo $success_message; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($errors)): ?>
                    <div class="message error"><strong>Please fix the following errors:</strong>
                        <ul>
                            <?php foreach ($errors as $error) echo "<li>$error</li>"; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- --- NEW: Show login prompt for guests --- -->
                    <?php if (!$is_logged_in): ?>
                    <div class="message info"><strong>Please log in to send a message.</strong></div>
                    <?php endif; ?>

                    <form action="contact.php" method="POST">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" placeholder="Your Name"
                                value="<?php echo htmlspecialchars($user_fullname); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" placeholder="your.email@example.com"
                                value="<?php echo htmlspecialchars($user_email); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="subject">Subject</label>
                            <input type="text" id="subject" name="subject" placeholder="e.g., Question about an order"
                                <?php if (!$is_logged_in) echo 'readonly' ; ?> required>
                        </div>
                        <div class="form-group">
                            <label for="message">Your Message</label>
                            <textarea id="message" name="message" placeholder="Type your message here..." <?php if
                                (!$is_logged_in) echo 'readonly' ; ?> required></textarea>
                        </div>

                        <!-- --- NEW: Conditional button --- -->
                        <?php if ($is_logged_in): ?>
                        <button type="submit" class="btn-submit">Send Message</button>
                        <?php else: ?>
                        <a href="login.php" class="btn-submit">Login to Send a Message</a>
                        <?php endif; ?>

                    </form>
                </div>
            </div>
        </div>
    </section>

    <?php include 'footer.html'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const profileCardContainer = document.querySelector('.profile-card-container');
            const profileCard = document.getElementById('profile-card');
            if (profileCardContainer && profileCard) {
                profileCardContainer.addEventListener('click', (event) => {
                    event.stopPropagation();
                    profileCard.classList.toggle('active');
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