<?php
// Start the session to access login state
session_start();
// The config file is one level up from the 'dashboard' folder
include "../config.php";

// --- SECURITY CHECK ---
// Ensure the user is logged in
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header("Location: ../login.php"); // Go back to the main login page
    exit;
}

// --- INITIAL DATA FETCHING ---
$user_id = $_SESSION['user_id'];
$user_firstname = '';
$user_lastname = '';
$user_email = '';
$user_phone = '';

// Fetch fname, lname, email, and phone
$sql_user = "SELECT fname, lname, email, phone FROM users WHERE user_id = ?";
if ($stmt_user = mysqli_prepare($conn, $sql_user)) {
    mysqli_stmt_bind_param($stmt_user, "i", $user_id);
    mysqli_stmt_execute($stmt_user);
    $result_user = mysqli_stmt_get_result($stmt_user);
    if ($user_row = mysqli_fetch_assoc($result_user)) {
        $user_firstname = $user_row['fname'];
        $user_lastname = $user_row['lname'];
        $user_email = $user_row['email'];
        $user_phone = $user_row['phone'];
    } else {
        die("Error: User not found.");
    }
    mysqli_stmt_close($stmt_user);
}

// --- FORM SUBMISSION & SERVER-SIDE VALIDATION ---
$errors = [];
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    
    // 1. Sanitize and retrieve input data
    $new_fname = trim($_POST['fname']);
    $new_lname = trim($_POST['lname']);
    $new_phone = trim($_POST['phone']);

    // 2. Validate First Name
    if (empty($new_fname)) {
        $errors[] = "First name is required.";
    } elseif (!preg_match("/^[a-zA-Z]+$/", $new_fname)) {
        $errors[] = "First name can only contain letters.";
    }

    // 3. Validate Last Name
    if (empty($new_lname)) {
        $errors[] = "Last name is required.";
    } elseif (!preg_match("/^[a-zA-Z]+$/", $new_lname)) {
        $errors[] = "Last name can only contain letters.";
    }

    // 4. Validate Phone Number
    if (empty($new_phone)) {
        $errors[] = "Phone number is required.";
    } elseif (!preg_match("/^[0-9]{10}$/", $new_phone)) {
        $errors[] = "Phone number must be exactly 10 digits.";
    }

    // 5. If there are no validation errors, proceed to update the database
    if (empty($errors)) {
        $processed_fname = ucfirst(strtolower($new_fname));
        $processed_lname = ucfirst(strtolower($new_lname));

        $sql_update = "UPDATE users SET fname = ?, lname = ?, phone = ? WHERE user_id = ?";
        
        if ($stmt_update = mysqli_prepare($conn, $sql_update)) {
            mysqli_stmt_bind_param($stmt_update, "sssi", $processed_fname, $processed_lname, $new_phone, $user_id);
            
            if (mysqli_stmt_execute($stmt_update)) {
                $_SESSION['success_message'] = "Your profile has been updated successfully!";
                header("Location: ../dashboard.php");
                exit;
            } else {
                $errors[] = "Database error: Could not update your profile. Please try again later.";
            }
            mysqli_stmt_close($stmt_update);
        } else {
             $errors[] = "Database error: Could not prepare the update statement.";
        }
    }
    
    $user_firstname = $new_fname;
    $user_lastname = $new_lname;
    $user_phone = $new_phone;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="../refresh.js"></script>
    <link rel="stylesheet" href="../index.css"> 
    <style>
        :root { 
            --error-bg: #ffebee; 
            --error-border: #e57373; 
            --error-text: #c62828; 
        }
        body { 
            background-color: var(--light-gray); 
        }
        .edit-profile-container {
            max-width: 700px;
            margin: 50px auto;
            padding: 40px;
            background-color: var(--white);
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .edit-profile-container h1 {
            text-align: center;
            margin-bottom: 30px;
            color: var(--secondary);
            font-size: 2rem;
        }
                .form-row {
            display: flex;
            gap: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark-gray);
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 5px rgba(var(--primary-rgb), 0.3);
        }

        .form-group input:disabled {
            background-color: #f2f2f2;
            cursor: not-allowed;
            color: #777;
        }

        .form-actions {
            display: flex;
            justify-content: flex-start;
            gap: 15px;
            align-items: center;
            margin-top: 30px;
        }

        .btn-submit {
            background-color: var(--primary);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-submit:hover {
            background-color: var(--secondary);
        }

        .btn-cancel {
            text-decoration: none;
            color: var(--dark-gray);
            font-weight: 500;
            border: 1px solid var(--primary);
            padding: 8px 16px;
            border-radius: 4px;
        }


        /* --- STYLES FOR REAL-TIME VALIDATION --- */
        .form-group input.input-error {
            border-color: var(--error-text);
            background-color: #fff9f9;
        }
        .form-group input.input-error:focus {
            box-shadow: 0 0 5px rgba(198, 40, 40, 0.4);
        }
        .error-message {
            color: var(--error-text);
            font-size: 0.85rem;
            margin-top: 5px;
            display: none; /* Hidden by default, shown by JS */
            height: 1.2em; /* Reserve space to prevent layout shift */
        }
        /* --- END OF VALIDATION STYLES --- */

        .message-box { padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; }
        .message-box.errors { background-color: var(--error-bg); border-color: var(--error-border); color: var(--error-text); }
        .message-box.errors p { margin: 5px 0; }
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

    <main class="container">
        <div class="edit-profile-container">
            <h1>Edit Profile</h1>

            <?php
            if (!empty($errors)) {
                echo '<div class="message-box errors">';
                foreach ($errors as $error) {
                    echo '<p>' . htmlspecialchars($error) . '</p>';
                }
                echo '</div>';
            }
            ?>
            
            <form id="editProfileForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" novalidate>
                <div class="form-row">
                    <div class="form-group">
                        <label for="fname">First Name</label>
                        <input type="text" id="fname" name="fname" value="<?php echo htmlspecialchars($user_firstname); ?>" required>
                        <div id="fnameError" class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label for="lname">Last Name</label>
                        <input type="text" id="lname" name="lname" value="<?php echo htmlspecialchars($user_lastname); ?>" required>
                        <div id="lnameError" class="error-message"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address (cannot be changed)</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" disabled>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user_phone); ?>" required maxlength="10">
                    <div id="phoneError" class="error-message"></div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="update_profile" class="btn-submit">Save Changes</button>
                    <a href="../dashboard.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </main>
    <?php include "../footer.html"; ?>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- Get Form Elements ---
        const form = document.getElementById('editProfileForm');
        const fnameInput = document.getElementById('fname');
        const lnameInput = document.getElementById('lname');
        const phoneInput = document.getElementById('phone');
        
        const fnameError = document.getElementById('fnameError');
        const lnameError = document.getElementById('lnameError');
        const phoneError = document.getElementById('phoneError');

        // --- Validation Functions ---

        const validateFname = () => {
            const value = fnameInput.value.trim();
            if (value === '') {
                fnameError.textContent = 'First name is required.';
                fnameError.style.display = 'block';
                fnameInput.classList.add('input-error');
                return false;
            } else if (!/^[a-zA-Z]+$/.test(value)) {
                fnameError.textContent = 'First name can only contain letters.';
                fnameError.style.display = 'block';
                fnameInput.classList.add('input-error');
                return false;
            } else {
                fnameError.textContent = '';
                fnameError.style.display = 'none';
                fnameInput.classList.remove('input-error');
                return true;
            }
        };

        const validateLname = () => {
            const value = lnameInput.value.trim();
            if (value === '') {
                lnameError.textContent = 'Last name is required.';
                lnameError.style.display = 'block';
                lnameInput.classList.add('input-error');
                return false;
            } else if (!/^[a-zA-Z]+$/.test(value)) {
                lnameError.textContent = 'Last name can only contain letters.';
                lnameError.style.display = 'block';
                lnameInput.classList.add('input-error');
                return false;
            } else {
                lnameError.textContent = '';
                lnameError.style.display = 'none';
                lnameInput.classList.remove('input-error');
                return true;
            }
        };

        const validatePhone = () => {
            const value = phoneInput.value.trim();
            if (value === '') {
                phoneError.textContent = 'Phone number is required.';
                phoneError.style.display = 'block';
                phoneInput.classList.add('input-error');
                return false;
            } else if (!/^\d{10}$/.test(value)) {
                phoneError.textContent = 'Please enter a valid 10-digit phone number.';
                phoneError.style.display = 'block';
                phoneInput.classList.add('input-error');
                return false;
            } else {
                phoneError.textContent = '';
                phoneError.style.display = 'none';
                phoneInput.classList.remove('input-error');
                return true;
            }
        };

        // --- Attach Event Listeners for Real-Time Validation ---
        // 'input' event fires every time the user types, pastes, or changes the value.
        fnameInput.addEventListener('input', validateFname);
        lnameInput.addEventListener('input', validateLname);
        phoneInput.addEventListener('input', validatePhone);

        // --- Final Validation on Form Submission ---
        form.addEventListener('submit', function(event) {
            // Run all validation functions one last time to be sure.
            const isFnameValid = validateFname();
            const isLnameValid = validateLname();
            const isPhoneValid = validatePhone();

            // If any validation function returns false, prevent the form submission.
            if (!isFnameValid || !isLnameValid || !isPhoneValid) {
                event.preventDefault(); 
            }
        });
    });
    </script>

</body>
</html>