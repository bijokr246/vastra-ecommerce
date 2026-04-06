<?php
// Start the session to access login state
session_start();
include "../config.php";

// --- SECURITY CHECK ---
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];
$input = []; // To repopulate form on error

// --- PRE-CHECKS: Ensure the user is eligible to apply ---
$stmt_role = $conn->prepare("SELECT role FROM login WHERE user_id = ?");
$stmt_role->bind_param("i", $user_id);
$stmt_role->execute();
$current_user = $stmt_role->get_result()->fetch_assoc();
$stmt_role->close();

if ($current_user['role'] !== 'buyer') {
    $_SESSION['info_message'] = "You are already registered as a " . htmlspecialchars($current_user['role']) . ".";
    header("Location: ../dashboard.php");
    exit;
}

$stmt_req = $conn->prepare("SELECT request_id FROM seller_requests WHERE user_id = ? AND status = 'pending'");
$stmt_req->bind_param("i", $user_id);
$stmt_req->execute();
if ($stmt_req->get_result()->num_rows > 0) {
    $_SESSION['info_message'] = "You already have a pending seller application under review.";
    header("Location: ../dashboard.php");
    exit;
}
$stmt_req->close();

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- 1. Sanitize and retrieve form data ---
    $input['shop_name'] = trim($_POST['shop_name'] ?? '');
    $input['shop_description'] = trim($_POST['shop_description'] ?? '');
    $input['business_phone'] = trim($_POST['business_phone'] ?? '');
    $input['building_name_or_number'] = trim($_POST['building_name_or_number'] ?? '');
    $input['landmark'] = trim($_POST['landmark'] ?? '');
    $input['locality_or_town'] = trim($_POST['locality_or_town'] ?? '');
    $input['district'] = trim($_POST['district'] ?? '');
    $input['pincode'] = trim($_POST['pincode'] ?? '');
    $input['state'] = trim($_POST['state'] ?? 'Kerala');
    $input['agree_terms'] = isset($_POST['agree_terms']);


    // --- 2. Server-Side Validation ---
    if (empty($input['shop_name'])) { $errors['shop_name'] = "Shop Name is required."; }
    if (empty($input['shop_description'])) { $errors['shop_description'] = "Shop description is required."; }
    if (empty($input['building_name_or_number'])) { $errors['building_name_or_number'] = "Building Name / Number is required."; }
    if (empty($input['locality_or_town'])) { $errors['locality_or_town'] = "Locality / Town is required."; }
    if (empty($input['district'])) { $errors['district'] = "District is required."; }
    if (!preg_match('/^[1-9][0-9]{5}$/', $input['pincode'])) { $errors['pincode'] = "A valid 6-digit Pincode is required."; }
    if (!empty($input['business_phone']) && !preg_match('/^[0-9]{10}$/', $input['business_phone'])) {
        $errors['business_phone'] = 'Please enter a valid 10-digit phone number.';
    }
    if (!isset($_POST['agree_terms'])) {
        $errors['agree_terms'] = "You must agree to the Terms and Conditions to proceed.";
    }


    // 3. Handle Image Upload
    $shop_image_name = null;
    if (isset($_FILES['shop_image']) && $_FILES['shop_image']['error'] === UPLOAD_ERR_OK) {
        $image = $_FILES['shop_image'];
        $target_dir = "../pending-shop-images/";
        $max_file_size = 2 * 1024 * 1024; // 2 MB
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        $file_extension = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));

        if (!in_array($file_extension, $allowed_extensions)) {
            $errors['shop_image'] = 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.';
        } elseif ($image['size'] > $max_file_size) {
            $errors['shop_image'] = 'File is too large. Maximum size is 2 MB.';
        } else {
            $shop_image_name = 'req_' . uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $shop_image_name;

            if (!move_uploaded_file($image['tmp_name'], $target_file)) {
                $errors['shop_image'] = 'Sorry, there was an error uploading your file.';
                $shop_image_name = null;
            }
        }
    }
    
    // --- 4. If validation passes, insert the new request ---
    if (empty($errors)) {
        $sql_insert_request = "INSERT INTO seller_requests (
                                user_id, shop_name, shop_description, business_phone, shop_image,
                                building_name_or_number, landmark, locality_or_town, district, pincode, state
                               ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_insert = $conn->prepare($sql_insert_request);
        if ($stmt_insert) {
            $stmt_insert->bind_param("issssssssss", 
                $user_id, 
                $input['shop_name'], 
                $input['shop_description'], 
                $input['business_phone'], 
                $shop_image_name,
                $input['building_name_or_number'],
                $input['landmark'],
                $input['locality_or_town'],
                $input['district'],
                $input['pincode'],
                $input['state']
            );
            
            if ($stmt_insert->execute()) {
                $_SESSION['success_message'] = "Your seller application has been submitted! You will be notified once it has been reviewed.";
                header("Location: ../dashboard.php");
                exit();
            } else {
                $errors['form'] = "A server error occurred. Your application could not be submitted.";
            }
            $stmt_insert->close();
        } else {
             $errors['form'] = "A database error occurred.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale-1.0">
    <title>Become a Seller | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../index.css">
    <style>
        body { background-color: var(--light-gray); }
        .seller-application-container { max-width: 700px; margin: 40px auto; padding-bottom: 50px; }
        .form-card { background: white; padding: 30px 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .form-card h1 { text-align: center; margin-top: 0; margin-bottom: 15px; color: var(--secondary); font-size: 2rem; }
        .intro-text { text-align: center; color: var(--dark-gray); margin-bottom: 30px; line-height: 1.6; }
        .form-group { margin-bottom: 25px; position: relative; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 1rem; }
        .form-group textarea { resize: vertical; min-height: 120px; }
        .form-group input:focus, .form-group textarea:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(255, 63, 108, 0.1); }
        .form-group input.is-invalid, .form-group textarea.is-invalid { border-color: var(--error); }
        .error-text { color: var(--error); font-size: 0.875em; margin-top: 5px; display: none; }
        .is-invalid + .error-text, .is-invalid ~ .error-text { display: block; }
        .form-actions { display: flex; gap: 15px; align-items: center; margin-top: 30px; }
        .btn-submit { background-color: var(--primary); color: white; border: none; padding: 12px 30px; border-radius: 5px; cursor: pointer; font-size: 1rem; }
        .btn-cancel { color: var(--dark-gray); text-decoration: none; }
        .message-box { padding: 15px; margin-bottom: 20px; border-radius: 5px; text-align: center; }
        .message-box.error { border: 1px solid #f5c6cb; background-color: #f8d7da; color: #721c24; }
        .image-preview-container { margin-top: 10px; width: 150px; height: 150px; border: 2px dashed #ccc; border-radius: 5px; display: none; align-items: center; justify-content: center; overflow: hidden; background-color: #f9f9f9; }
        .image-preview-container img { width: 100%; height: 100%; object-fit: cover; }
        .file-info { font-size: 0.8rem; color: #666; margin-top: 5px; }
        .terms-box {
            height: 200px;
            overflow-y: scroll;
            border: 1px solid #ccc;
            padding: 15px;
            font-size: 0.9em;
            background-color: #f9f9f9;
            border-radius: 5px;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        .terms-box ol { padding-left: 20px; }
        .terms-box li { margin-bottom: 10px; }
        .terms-box ul { padding-left: 20px; margin-top: 5px; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input[type="checkbox"] { width: auto; margin: 0; accent-color: var(--primary); }
        .form-group input[type="checkbox"].is-invalid + label + .error-text { display: block; }
    </style>
</head>
<body>
    <nav class="navbar">
       <div class="container nav-container">
            <a href="index.php" class="logo">VASTRA</a>
            <?php include "nav_links.html"; ?>
            <div class="nav-icons">
                 <a href="../dashboard.php" title="My Account"><i class="far fa-user"></i></a>
                 <a href="wishlist.php" title="Wishlist"><i class="far fa-heart"></i></a>
                 <a href="cart.php" title="Shopping Cart"><i class="fas fa-shopping-bag"></i></a>
            </div>
        </div>
    </nav>
    
    <main class="container seller-application-container">
        <div class="form-card">
            <h1>Seller Application</h1>
            <p class="intro-text">Ready to sell on Vastra? Fill out the details below to start the application process.</p>

            <?php if (isset($errors['form'])): ?>
                <div class="message-box error"><?= htmlspecialchars($errors['form']); ?></div>
            <?php endif; ?>

            <form id="sellerApplicationForm" method="POST" action="become-seller.php" enctype="multipart/form-data" novalidate>
                <!-- Shop Details -->
                <div class="form-group">
                    <label for="shop_name">Shop Name</label>
                    <input type="text" name="shop_name" id="shop_name" required maxlength="50" value="<?= htmlspecialchars($input['shop_name'] ?? '') ?>" class="<?= isset($errors['shop_name']) ? 'is-invalid' : '' ?>">
                    <small class="error-text"><?= htmlspecialchars($errors['shop_name'] ?? 'Shop Name is required.') ?></small>
                </div>
                <div class="form-group">
                    <label for="shop_description">Shop Description</label>
                    <textarea name="shop_description" id="shop_description" required class="<?= isset($errors['shop_description']) ? 'is-invalid' : '' ?>"><?= htmlspecialchars($input['shop_description'] ?? '') ?></textarea>
                    <small class="error-text"><?= htmlspecialchars($errors['shop_description'] ?? 'Shop Description is required.') ?></small>
                </div>
                <div class="form-group">
                    <label for="business_phone">Business Phone (Optional)</label>
                    <input type="tel" name="business_phone" id="business_phone" placeholder="e.g., 9876543210" value="<?= htmlspecialchars($input['business_phone'] ?? '') ?>" class="<?= isset($errors['business_phone']) ? 'is-invalid' : '' ?>">
                    <small class="error-text"><?= htmlspecialchars($errors['business_phone'] ?? 'Please enter a valid 10-digit phone number.') ?></small>
                </div>
                
                <hr>
                <h4>Shop Address</h4>

                <!-- Address Fields -->
                <div class="form-group">
                    <label for="building_name_or_number">Building Name / Number</label>
                    <input type="text" name="building_name_or_number" id="building_name_or_number" required value="<?= htmlspecialchars($input['building_name_or_number'] ?? '') ?>" class="<?= isset($errors['building_name_or_number']) ? 'is-invalid' : '' ?>">
                    <small class="error-text"><?= htmlspecialchars($errors['building_name_or_number'] ?? 'This field is required.') ?></small>
                </div>
                <div class="form-group">
                    <label for="landmark">Landmark (Optional)</label>
                    <input type="text" name="landmark" id="landmark" value="<?= htmlspecialchars($input['landmark'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="locality_or_town">Locality / Town</label>
                    <input type="text" name="locality_or_town" id="locality_or_town" required value="<?= htmlspecialchars($input['locality_or_town'] ?? '') ?>" class="<?= isset($errors['locality_or_town']) ? 'is-invalid' : '' ?>">
                    <small class="error-text"><?= htmlspecialchars($errors['locality_or_town'] ?? 'This field is required.') ?></small>
                </div>
                <div class="form-group">
                    <label for="district">District</label>
                    <input type="text" name="district" id="district" required value="<?= htmlspecialchars($input['district'] ?? '') ?>" class="<?= isset($errors['district']) ? 'is-invalid' : '' ?>">
                    <small class="error-text"><?= htmlspecialchars($errors['district'] ?? 'This field is required.') ?></small>
                </div>
                <div class="form-group">
                    <label for="pincode">Pincode</label>
                    <input type="text" name="pincode" id="pincode" required maxlength="6" pattern="[1-9][0-9]{5}" value="<?= htmlspecialchars($input['pincode'] ?? '') ?>" class="<?= isset($errors['pincode']) ? 'is-invalid' : '' ?>">
                    <small class="error-text"><?= htmlspecialchars($errors['pincode'] ?? 'A valid 6-digit pincode is required.') ?></small>
                </div>
                <div class="form-group">
                    <label for="state">State</label>
                    <input type="text" name="state" id="state" value="Kerala" readonly>
                </div>
                
                <hr>
                <!-- Shop Image -->
                <div class="form-group">
                    <label for="shop_image">Shop Image (Optional)</label>
                    <input type="file" name="shop_image" id="shop_image" accept="image/jpeg, image/png, image/gif" class="<?= isset($errors['shop_image']) ? 'is-invalid' : '' ?>">
                    <div class="file-info">Max size: 2MB. Allowed types: JPG, PNG, GIF.</div>
                    <div class="image-preview-container" id="imagePreviewContainer">
                        <img src="" alt="Image Preview" id="imagePreview">
                    </div>
                    <small class="error-text"><?= htmlspecialchars($errors['shop_image'] ?? 'Invalid file.') ?></small>
                </div>
                
                <hr>
                
                <!-- Terms and Conditions Section -->
                <div class="form-group">
                    <label>Terms and Conditions</label>
                    <div class="terms-box">
                        <h5>Vastra Seller Terms and Conditions</h5>
                        <p><strong>Last Updated: September 26, 2025</strong></p>
                        <p>Welcome to Vastra! By applying to become a seller on our platform, you agree to comply with and be bound by the following terms and conditions. Please review them carefully.</p>
                        <ol>
                            <li>
                                <strong>Eligibility</strong>
                                <ul>
                                    <li>You must be at least 18 years of age and legally capable of entering into binding contracts.</li>
                                    <li>You must provide accurate and verifiable personal and business details during the seller registration process.</li>
                                </ul>
                            </li>
                            <li>
                                <strong>Subscription Plans</strong>
                                <ul>
                                    <li>New sellers are eligible for a one-month free trial plan, during which they may create shops, list products, and receive orders without any subscription charges.</li>
                                    <li>After the free trial period, sellers must purchase a subscription plan (3 months, 6 months, or 1 year) to continue selling on Vastra.</li>
                                    <li>Subscription fees are non-refundable once payment is processed.</li>
                                    <li>Renewal of the subscription is mandatory for uninterrupted access to seller features.</li>
                                </ul>
                            </li>
                            <li>
                                <strong>Product Listings</strong>
                                <ul>
                                    <li>You agree to provide accurate, complete, and up-to-date information for all listed products.</li>
                                    <li>Prohibited, counterfeit, or illegal items are strictly not allowed and will lead to account suspension.</li>
                                    <li>Vastra reserves the right to review and deactivate products reported by buyers for low quality or violations.</li>
                                </ul>
                            </li>
                            <li>
                                <strong>Seller Conduct</strong>
                                <p>Sellers must maintain high standards of service, including:</p>
                                <ul>
                                    <li>Timely processing and dispatch of orders.</li>
                                    <li>Clear and professional communication with buyers.</li>
                                    <li>Ensuring product quality matches the description.</li>
                                </ul>
                                <p>Misleading or fraudulent practices will result in immediate termination.</p>
                            </li>
                            <li>
                                <strong>Payments</strong>
                                <ul>
                                    <li>Subscription fees must be paid in advance through the available payment options after the completion of the one-month free trial.</li>
                                    <li>Failure to renew the subscription before expiry will result in suspension of selling privileges until renewal.</li>
                                </ul>
                            </li>
                            <li>
                                <strong>Termination</strong>
                                <p>Vastra reserves the right to suspend or terminate any seller account if:</p>
                                <ul>
                                    <li>The seller violates these Terms & Conditions.</li>
                                    <li>Products are reported repeatedly for poor quality or prohibited items.</li>
                                    <li>False or misleading information is provided.</li>
                                </ul>
                                <p>Sellers may voluntarily close their account, but no refund of subscription fees will be provided.</p>
                            </li>
                            <li>
                                <strong>Free Trial Conditions</strong>
                                <ul>
                                    <li>The free trial is available only once per seller account.</li>
                                    <li>Sellers must comply with all platform rules during the free trial.</li>
                                    <li>Abuse of the free trial (e.g., creating multiple accounts) will result in permanent suspension.</li>
                                </ul>
                            </li>
                            <li>
                                <strong>Changes to Terms</strong>
                                <ul>
                                    <li>Vastra reserves the right to modify these Terms & Conditions at any time.</li>
                                    <li>Continued use of the platform after changes implies acceptance of the updated terms.</li>
                                </ul>
                            </li>
                        </ol>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="agree_terms" id="agree_terms" required value="1" class="<?= isset($errors['agree_terms']) ? 'is-invalid' : '' ?>" <?= ($input['agree_terms'] ?? false) ? 'checked' : '' ?>>
                        <label for="agree_terms">I have read and agree to the Terms and Conditions.</label>
                        <small class="error-text"><?= htmlspecialchars($errors['agree_terms'] ?? 'You must agree to the terms.') ?></small>
                    </div>
                </div>


                <div class="form-actions">
                    <button type="submit" class="btn-submit">Submit for Review</button>
                    <a href="../dashboard.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
        </div>
    </main>
    <?php include "../footer.html"; ?>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('sellerApplicationForm');
        const shopName = document.getElementById('shop_name');
        const shopDesc = document.getElementById('shop_description');
        const phone = document.getElementById('business_phone');
        const shopImage = document.getElementById('shop_image');
        const building = document.getElementById('building_name_or_number');
        const locality = document.getElementById('locality_or_town');
        const district = document.getElementById('district');
        const pincode = document.getElementById('pincode');
        const agreeTerms = document.getElementById('agree_terms');
        const imagePreviewContainer = document.getElementById('imagePreviewContainer');
        const imagePreview = document.getElementById('imagePreview');

        const handleValidation = (element, isValid) => {
            let errorElement;
            if (element.type === 'checkbox') {
                errorElement = element.nextElementSibling.nextElementSibling;
            } else {
                errorElement = element.nextElementSibling;
            }

            if (isValid) {
                element.classList.remove('is-invalid');
                if (errorElement && errorElement.classList.contains('error-text')) {
                    errorElement.style.display = 'none';
                }
            } else {
                element.classList.add('is-invalid');
                if (errorElement && errorElement.classList.contains('error-text')) {
                    errorElement.style.display = 'block';
                }
            }
        };

        const validateRequired = (element) => {
            const isValid = element.value.trim() !== '';
            handleValidation(element, isValid);
            return isValid;
        };

        const validatePhone = (element) => {
            if (element.value.trim() === '') {
                handleValidation(element, true);
                return true;
            }
            const isValid = /^[0-9]{10}$/.test(element.value);
            handleValidation(element, isValid);
            return isValid;
        };
        
        const validatePincode = (element) => {
            const isValid = /^[1-9][0-9]{5}$/.test(element.value);
            handleValidation(element, isValid);
            return isValid;
        };

        const validateImage = (element) => {
            if (!element.files || element.files.length === 0) {
                handleValidation(element, true);
                return true;
            }
            const file = element.files[0];
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            const maxSize = 2 * 1024 * 1024;
            const isValid = allowedTypes.includes(file.type) && file.size <= maxSize;
            handleValidation(element, isValid);
            return isValid;
        };

        const validateCheckbox = (element) => {
            const isValid = element.checked;
            handleValidation(element, isValid);
            return isValid;
        };
        
        shopName.addEventListener('input', () => validateRequired(shopName));
        shopDesc.addEventListener('input', () => validateRequired(shopDesc));
        phone.addEventListener('input', () => validatePhone(phone));
        building.addEventListener('input', () => validateRequired(building));
        locality.addEventListener('input', () => validateRequired(locality));
        district.addEventListener('input', () => validateRequired(district));
        pincode.addEventListener('input', () => validatePincode(pincode));
        agreeTerms.addEventListener('change', () => validateCheckbox(agreeTerms));

        shopImage.addEventListener('change', () => {
            if (validateImage(shopImage) && shopImage.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreviewContainer.style.display = 'flex';
                }
                reader.readAsDataURL(shopImage.files[0]);
            } else {
                imagePreviewContainer.style.display = 'none';
            }
        });

        form.addEventListener('submit', (e) => {
            const isNameValid = validateRequired(shopName);
            const isDescValid = validateRequired(shopDesc);
            const isPhoneValid = validatePhone(phone);
            const isImageValid = validateImage(shopImage);
            const isBuildingValid = validateRequired(building);
            const isLocalityValid = validateRequired(locality);
            const isDistrictValid = validateRequired(district);
            const isPincodeValid = validatePincode(pincode);
            const isTermsValid = validateCheckbox(agreeTerms);

            if (!isNameValid || !isDescValid || !isPhoneValid || !isImageValid ||
                !isBuildingValid || !isLocalityValid || !isDistrictValid || !isPincodeValid || !isTermsValid) {
                e.preventDefault();
                console.log('Form submission prevented due to validation errors.');
            }
        });
    });
    </script>
</body>
</html>