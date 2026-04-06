<?php
session_start();
include "../config.php";

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid address ID.";
    header("Location: manage-addresses.php");
    exit;
}
$address_id = (int)$_GET['id'];

$sql_fetch = "SELECT * FROM addresses WHERE address_id = ? AND user_id = ?";
$stmt_fetch = mysqli_prepare($conn, $sql_fetch);
mysqli_stmt_bind_param($stmt_fetch, "ii", $address_id, $user_id);
mysqli_stmt_execute($stmt_fetch);
$result = mysqli_stmt_get_result($stmt_fetch);
if (mysqli_num_rows($result) !== 1) {
    $_SESSION['error_message'] = "Address not found.";
    header("Location: manage-addresses.php");
    exit;
}
$address_data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt_fetch);

// --- CHANGED HERE (1 of 4): Updated validation function for separate names ---
function validate_address_data($post_data) {
    $validation_errors = [];
    
    // Validate First Name
    if (empty(trim($post_data['recipient_fname']))) {
        $validation_errors[] = "First name is required.";
    } elseif (!preg_match("/^[A-Z][a-zA-Z\s]*$/", $post_data['recipient_fname'])) {
         $validation_errors[] = "First name must start with a capital letter and contain only alphabets and spaces.";
    }

    // Validate Last Name
    if (empty(trim($post_data['recipient_lname']))) {
        $validation_errors[] = "Last name is required.";
    } elseif (!preg_match("/^[A-Z][a-zA-Z\s]*$/", $post_data['recipient_lname'])) {
         $validation_errors[] = "Last name must start with a capital letter and contain only alphabets and spaces.";
    }

    // Other validations (unchanged)
    if (empty(trim($post_data['mobile_number']))) {
        $validation_errors[] = "Mobile number is required.";
    } elseif (!preg_match("/^[0-9]{10}$/", $post_data['mobile_number'])) {
        $validation_errors[] = "Mobile number must be 10 digits.";
    }
    if (empty(trim($post_data['house_address']))) $validation_errors[] = "House/Flat/Building details are required.";
    if (empty(trim($post_data['locality_or_town']))) $validation_errors[] = "Locality or Town is required.";
    if (empty(trim($post_data['district']))) $validation_errors[] = "District is required.";
    if (empty(trim($post_data['pincode']))) {
        $validation_errors[] = "Pincode is required.";
    } elseif (!preg_match("/^[0-9]{6}$/", $post_data['pincode'])) {
        $validation_errors[] = "Pincode must be 6 digits.";
    }
    if (empty(trim($post_data['state']))) $validation_errors[] = "State is required.";
    if (empty(trim($post_data['address_type']))) $validation_errors[] = "Address type (Home/Office) is required.";
    return $validation_errors;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_address'])) {
    $address_data = array_merge($address_data, $_POST);
    $errors = validate_address_data($_POST);
    
    if (empty($errors)) {
        // --- CHANGED HERE (2 of 4): Updated SQL query and bind parameters ---
        $sql_update = "UPDATE addresses SET recipient_fname=?, recipient_lname=?, mobile_number=?, house_address=?, landmark=?, locality_or_town=?, district=?, pincode=?, state=?, address_type=? WHERE address_id=? AND user_id=?";
        if ($stmt_update = mysqli_prepare($conn, $sql_update)) {
            // Note: The bind param string now starts with "ss" for the two name fields
            mysqli_stmt_bind_param($stmt_update, "ssssssssssii", 
                $_POST['recipient_fname'], 
                $_POST['recipient_lname'], 
                $_POST['mobile_number'], 
                $_POST['house_address'], 
                $_POST['landmark'], 
                $_POST['locality_or_town'], 
                $_POST['district'], 
                $_POST['pincode'], 
                $_POST['state'], 
                $_POST['address_type'], 
                $address_id, 
                $user_id
            );
            if (mysqli_stmt_execute($stmt_update)) {
                $_SESSION['success_message'] = "Address updated successfully!";
            } else {
                $_SESSION['error_message'] = "Error: Could not update address.";
            }
            mysqli_stmt_close($stmt_update);
            header("Location: manage-addresses.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Address | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../index.css">
    <style>
        * { box-sizing: border-box; }
        body { background-color: var(--light-gray); overflow-x: hidden; }
        .form-container { max-width: 800px; margin: 40px auto; background: white; padding: 30px; border-radius: 8px; border: 1px solid #ddd; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .form-container h1 { margin-top: 0; margin-bottom: 25px; color: var(--secondary); font-size: 2.2rem; }
        .form-row { display: flex; flex-wrap: wrap; gap: 20px; }
        .form-row .form-group { flex: 1; min-width: calc(50% - 10px); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        .form-group input.input-error { border-color: var(--danger); }
        .error-message { color: var(--danger); font-size: 0.85rem; margin-top: 5px; display: none; }
        .form-actions { display: flex; gap: 15px; align-items: center; margin-top: 20px; }
        .btn-save { background-color: var(--primary); color: white; border: none; padding: 12px 30px; border-radius: 5px; cursor: pointer; font-weight: 500; }
        .btn-cancel { color: var(--dark-gray); text-decoration: none; }
        .address-type-group { display: flex; gap: 20px; }
        .address-type-group .radio-card { flex: 1; }
        .address-type-group input[type="radio"] { opacity: 0; position: absolute; }
        .address-type-group label { display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; padding: 20px; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; transition: all 0.3s ease; }
        .address-type-group label i { font-size: 1.5rem; margin-bottom: 8px; color: var(--dark-gray); }
        .address-type-group input[type="radio"]:checked+label { border-color: var(--primary); background-color: #f0f8ff; }
        .address-type-group input[type="radio"]:checked+label i, .address-type-group input[type="radio"]:checked+label span { color: var(--primary); }
        .message-box.error-summary { padding: 15px; margin-bottom: 20px; border-radius: 5px; background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .message-box.error-summary ul { list-style: none; padding: 0; margin: 0; }
    </style>
</head>
<body>
    <nav class="navbar">
       <div class="container nav-container">
            <a href="../index.php" class="logo">VASTRA</a>
            <?php include "nav_links.html"; ?>
            <div class="nav-icons"><a href="../dashboard.php" title="My Account"><i class="far fa-user"></i></a></div>
        </div>
    </nav>
    <main class="container">
        <div class="form-container">
            <h1>Edit Address</h1>
            <?php
            if (!empty($errors)) {
                echo '<div class="message-box error-summary"><ul>';
                foreach($errors as $error) echo '<li>' . htmlspecialchars($error) . '</li>';
                echo '</ul></div>';
            }
            ?>
            <form id="addressForm" method="POST" action="edit-address.php?id=<?= $address_id; ?>" novalidate>
                <!-- --- CHANGED HERE (3 of 4): Split "Full Name" into two fields --- -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="recipient_fname">First Name</label>
                        <input type="text" name="recipient_fname" id="recipient_fname" required value="<?= htmlspecialchars($address_data['recipient_fname'] ?? '') ?>">
                        <small class="error-message"></small>
                    </div>
                    <div class="form-group">
                        <label for="recipient_lname">Last Name</label>
                        <input type="text" name="recipient_lname" id="recipient_lname" required value="<?= htmlspecialchars($address_data['recipient_lname'] ?? '') ?>">
                        <small class="error-message"></small>
                    </div>
                </div>
                <!-- Old single input removed -->

                <div class="form-row">
                    <div class="form-group">
                        <label for="mobile_number">10-digit Mobile Number</label>
                        <input type="tel" name="mobile_number" id="mobile_number" required maxlength="10" value="<?= htmlspecialchars($address_data['mobile_number']) ?>">
                        <small class="error-message"></small>
                    </div>
                </div>
                <div class="form-group"><label for="house_address">Flat, House no., Building, etc.</label><input type="text" name="house_address" id="house_address" required value="<?= htmlspecialchars($address_data['house_address']) ?>"><small class="error-message"></small></div>
                <div class="form-group"><label for="landmark">Landmark (Optional)</label><input type="text" name="landmark" id="landmark" value="<?= htmlspecialchars($address_data['landmark']) ?>"></div>
                <div class="form-row">
                    <div class="form-group"><label for="locality_or_town">Locality / Town</label><input type="text" name="locality_or_town" id="locality_or_town" required value="<?= htmlspecialchars($address_data['locality_or_town']) ?>"><small class="error-message"></small></div>
                    <div class="form-group"><label for="district">District</label><input type="text" name="district" id="district" required value="<?= htmlspecialchars($address_data['district']) ?>"><small class="error-message"></small></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="pincode">Pincode</label><input type="text" name="pincode" id="pincode" required maxlength="6" value="<?= htmlspecialchars($address_data['pincode']) ?>"><small class="error-message"></small></div>
                    <div class="form-group"><label for="state">State</label><input type="text" name="state" id="state" required value="<?= htmlspecialchars($address_data['state']) ?>"><small class="error-message"></small></div>
                </div>
                <div class="form-group">
                    <label>Address Type</label>
                    <div class="address-type-group">
                        <div class="radio-card"><input type="radio" id="type_home" name="address_type" value="Home" required <?= ($address_data['address_type'] == 'Home' ? 'checked' : '') ?>><label for="type_home"><i class="fas fa-home"></i> <span>Home</span></label></div>
                        <div class="radio-card"><input type="radio" id="type_office" name="address_type" value="Office" required <?= ($address_data['address_type'] == 'Office' ? 'checked' : '') ?>><label for="type_office"><i class="fas fa-briefcase"></i> <span>Office</span></label></div>
                    </div>
                     <small class="error-message" id="address_type_error"></small>
                </div>
                <div class="form-actions">
                    <button type="submit" name="update_address" class="btn-save">Save Changes</button>
                    <a href="manage-addresses.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </main>
    <?php include "../footer.html"; ?>

    <script>
    // --- CHANGED HERE (4 of 4): Updated client-side validation logic ---
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('addressForm');
        const inputs = form.querySelectorAll('input[required]');
        const showError = (input, message) => {
            const formGroup = input.closest('.form-group');
            const errorEl = formGroup.querySelector('.error-message');
            if (errorEl) { input.classList.add('input-error'); errorEl.textContent = message; errorEl.style.display = 'block'; }
        };
        const clearError = (input) => {
            const formGroup = input.closest('.form-group');
            const errorEl = formGroup.querySelector('.error-message');
            if (errorEl) { input.classList.remove('input-error'); errorEl.style.display = 'none'; errorEl.textContent = ''; }
        };
        const validateInput = (input) => {
            clearError(input);
            let isValid = true;
            const value = input.value.trim();
            if (input.required && value === '') { showError(input, 'This field is required.'); return false; }
            
            switch (input.id) {
                // New cases for first and last name
                case 'recipient_fname':
                case 'recipient_lname':
                    if (value !== '' && !/^[A-Z][a-zA-Z\s]*$/.test(value)) { 
                        showError(input, 'Must start with a capital and contain only letters/spaces.'); 
                        isValid = false; 
                    }
                    break;
                case 'mobile_number':
                    if (!/^\d{10}$/.test(value)) { showError(input, 'Mobile number must be exactly 10 digits.'); isValid = false; }
                    break;
                case 'pincode':
                    if (!/^\d{6}$/.test(value)) { showError(input, 'Pincode must be exactly 6 digits.'); isValid = false; }
                    break;
            }
            return isValid;
        };
        const validateAddressType = () => {
            const errorEl = document.getElementById('address_type_error');
            const isChecked = form.querySelector('input[name="address_type"]:checked');
            if (!isChecked) { errorEl.textContent = 'Please select an address type.'; errorEl.style.display = 'block'; return false; }
            errorEl.style.display = 'none';
            return true;
        };
        inputs.forEach(input => {
            input.addEventListener('input', () => validateInput(input));
            input.addEventListener('blur', () => validateInput(input));
        });
        form.addEventListener('submit', (e) => {
            let isFormValid = true;
            inputs.forEach(input => { if (!validateInput(input)) isFormValid = false; });
            if (!validateAddressType()) isFormValid = false;
            if (!isFormValid) e.preventDefault();
        });
    });
    </script>
</body>
</html>