<?php
session_start();
include "../config.php";

// --- 1. SECURITY AND AUTHENTICATION ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ../seller-login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
// (Your role verification is good)
$stmt_role_check = $conn->prepare("SELECT role FROM login WHERE user_id = ? AND role = 'seller'");
$stmt_role_check->bind_param("i", $user_id);
$stmt_role_check->execute();
if ($stmt_role_check->get_result()->num_rows === 0) {
    session_destroy();
    header('Location: ../seller-login.php?error=access_denied');
    exit();
}
$stmt_role_check->close();

// --- 2. INITIALIZATION ---
// Use an array to hold input for easy repopulation on error
$input = [
    'shop_name' => '', 'shop_description' => '', 'business_phone' => '',
    'building' => '', 'landmark' => '', 'locality' => '', 'district' => '', 'pincode' => ''
];
$errors = [];

// --- 3. HANDLE FORM SUBMISSION (POST Request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and retrieve form data
    $input['shop_name'] = trim($_POST['shop_name']);
    $input['shop_description'] = trim($_POST['shop_description']);
    $input['business_phone'] = trim($_POST['business_phone']);
    $input['building'] = trim($_POST['building_name_or_number']);
    $input['landmark'] = trim($_POST['landmark']);
    $input['locality'] = trim($_POST['locality_or_town']);
    $input['district'] = trim($_POST['district']);
    $input['pincode'] = trim($_POST['pincode']);
    $input['state'] = $_POST['state'] ?? 'Kerala';

    // --- Server-Side Validation Logic ---
    if (empty($input['shop_name'])) { $errors['shop_name'] = 'Shop name is required.'; }
    if (empty($input['shop_description'])) { $errors['shop_description'] = 'Shop description is required.'; }
    if (empty($input['building'])) { $errors['building'] = 'Building Name / Number is required.'; }
    if (empty($input['locality'])) { $errors['locality'] = 'Locality / Town is required.'; }
    if (empty($input['district'])) { $errors['district'] = 'District is required.'; }
    if (!preg_match('/^[1-9][0-9]{5}$/', $input['pincode'])) { $errors['pincode'] = 'A valid 6-digit pincode is required.'; }
    if (!empty($input['business_phone']) && !preg_match('/^[0-9]{10}$/', $input['business_phone'])) {
        $errors['business_phone'] = 'Please enter a valid 10-digit phone number.';
    }

    // --- Handle Image Upload (your existing logic is good) ---
    $shop_image_name = null;
    if (isset($_FILES['shop_image']) && $_FILES['shop_image']['error'] === UPLOAD_ERR_OK) {
        $image = $_FILES['shop_image'];
        $target_dir = "../images/";
        $max_file_size = 2 * 1024 * 1024; // 2 MB
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $file_extension = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));

        if (!in_array($file_extension, $allowed_extensions)) {
            $errors['shop_image'] = 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.';
        } elseif ($image['size'] > $max_file_size) {
            $errors['shop_image'] = 'File is too large. Maximum size is 2 MB.';
        } else {
            $shop_image_name = 'shop_' . uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $shop_image_name;
            if (!move_uploaded_file($image['tmp_name'], $target_file)) {
                $errors['shop_image'] = 'Sorry, there was an error uploading your file.';
                $shop_image_name = null;
            }
        }
    }

    // --- If Validation Passes, Insert into Database using a TRANSACTION ---
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Step 1: Insert into `shops` table
            $sql_shop = "INSERT INTO shops (user_id, shop_name, shop_description, shop_image, business_phone, shop_status) VALUES (?, ?, ?, ?, ?, 'active')";
            $stmt_shop = $conn->prepare($sql_shop);
            $stmt_shop->bind_param("issss", $user_id, $input['shop_name'], $input['shop_description'], $shop_image_name, $input['business_phone']);
            $stmt_shop->execute();
            
            // Get the ID of the new shop
            $new_shop_id = $conn->insert_id;
            if (!$new_shop_id) { throw new Exception("Failed to retrieve new shop ID."); }
            $stmt_shop->close();

            // Step 2: Insert into `shop_addresses` table
            $sql_address = "INSERT INTO shop_addresses (shop_id, building_name_or_number, landmark, locality_or_town, district, pincode, state) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt_address = $conn->prepare($sql_address);
            $stmt_address->bind_param("issssss", $new_shop_id, $input['building'], $input['landmark'], $input['locality'], $input['district'], $input['pincode'], $input['state']);
            $stmt_address->execute();
            $stmt_address->close();
            
            // If both succeed, commit the changes
            $conn->commit();
            header('Location: my-shops.php?success=added');
            exit();

        } catch (Exception $e) {
            // If anything fails, roll back all changes
            $conn->rollback();
            $errors['db_error'] = 'Failed to create shop due to a database error. Please try again. Details: ' . $e->getMessage();
        }
    }
}

$current_page = 'my-shops.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Add New Shop | Vastra</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <style>
    .form-container { max-width: 800px; margin: 0 auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #333; }
    .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 16px; }
    .form-group textarea { resize: vertical; min-height: 120px; }
    .form-group .error-text { color: #d9534f; font-size: 14px; margin-top: 5px; display: none; }
    .form-group input.is-invalid, .form-group textarea.is-invalid { border-color: #d9534f; }
    .form-group input.is-invalid + .error-text, .form-group textarea.is-invalid + .error-text { display: block; }
    .form-actions { display: flex; justify-content: flex-end; gap: 15px; margin-top: 20px; }
    .btn-cancel { background-color: #6c757d; } .btn-cancel:hover { background-color: #5a6268; }
  </style>
</head>
<body>
  <div class="sidebar"><?php require "sidebar.php"; ?></div>
  <div class="main">
    <div class="header"><h1>Add a New Shop</h1></div>
    <div class="content">
      <div class="form-container">
        <form id="addShopForm" action="add-shop.php" method="POST" enctype="multipart/form-data" novalidate>
          <?php if (isset($errors['db_error'])): ?>
            <p class="error-text" style="display:block; background-color: #f2dede; padding: 10px; border-radius: 4px; margin-bottom: 15px;"><?php echo $errors['db_error']; ?></p>
          <?php endif; ?>
          
          <div class="form-group">
            <label for="shop_name">Shop Name</label>
            <input type="text" id="shop_name" name="shop_name" value="<?php echo htmlspecialchars($input['shop_name']); ?>" required class="<?php echo isset($errors['shop_name']) ? 'is-invalid' : ''; ?>">
            <p class="error-text"><?php echo $errors['shop_name'] ?? 'Shop name is required.'; ?></p>
          </div>
          <div class="form-group">
            <label for="shop_description">Shop Description</label>
            <textarea id="shop_description" name="shop_description" required class="<?php echo isset($errors['shop_description']) ? 'is-invalid' : ''; ?>"><?php echo htmlspecialchars($input['shop_description']); ?></textarea>
            <p class="error-text"><?php echo $errors['shop_description'] ?? 'Shop description is required.'; ?></p>
          </div>
          <div class="form-group">
            <label for="business_phone">Business Phone (Optional)</label>
            <input type="tel" id="business_phone" name="business_phone" value="<?php echo htmlspecialchars($input['business_phone']); ?>" placeholder="e.g., 9876543210" class="<?php echo isset($errors['business_phone']) ? 'is-invalid' : ''; ?>">
            <p class="error-text"><?php echo $errors['business_phone'] ?? 'Enter a valid 10-digit phone number.'; ?></p>
          </div>
          
          <hr><h4>Shop Address</h4>

          <div class="form-group">
            <label for="building_name_or_number">Building Name / Number</label>
            <input type="text" id="building_name_or_number" name="building_name_or_number" required value="<?php echo htmlspecialchars($input['building']); ?>" class="<?php echo isset($errors['building']) ? 'is-invalid' : ''; ?>">
            <p class="error-text"><?php echo $errors['building'] ?? 'This field is required.'; ?></p>
          </div>
          <div class="form-group">
            <label for="landmark">Landmark (Optional)</label>
            <input type="text" id="landmark" name="landmark" value="<?php echo htmlspecialchars($input['landmark']); ?>">
          </div>
          <div class="form-group">
            <label for="locality_or_town">Locality / Town</label>
            <input type="text" id="locality_or_town" name="locality_or_town" required value="<?php echo htmlspecialchars($input['locality']); ?>" class="<?php echo isset($errors['locality']) ? 'is-invalid' : ''; ?>">
            <p class="error-text"><?php echo $errors['locality'] ?? 'This field is required.'; ?></p>
          </div>
          <div class="form-group">
            <label for="district">District</label>
            <input type="text" id="district" name="district" required value="<?php echo htmlspecialchars($input['district']); ?>" class="<?php echo isset($errors['district']) ? 'is-invalid' : ''; ?>">
            <p class="error-text"><?php echo $errors['district'] ?? 'This field is required.'; ?></p>
          </div>
          <div class="form-group">
            <label for="pincode">Pincode</label>
            <input type="text" id="pincode" name="pincode" required maxlength="6" value="<?php echo htmlspecialchars($input['pincode']); ?>" class="<?php echo isset($errors['pincode']) ? 'is-invalid' : ''; ?>">
            <p class="error-text"><?php echo $errors['pincode'] ?? 'A valid 6-digit pincode is required.'; ?></p>
          </div>
          <div class="form-group">
            <label for="state">State</label>
            <input type="text" id="state" name="state" value="Kerala" readonly>
          </div>

          <hr>

          <div class="form-group">
            <label for="shop_image">Shop Image (Optional)</label>
            <input type="file" id="shop_image" name="shop_image" accept="image/png, image/jpeg, image/gif">
            <p style="font-size: 12px; color: #666; margin-top: 5px;">Max file size: 2MB. Allowed types: JPG, PNG, GIF.</p>
            <?php if (isset($errors['shop_image'])): ?>
              <p class="error-text" style="display:block;"><?php echo $errors['shop_image']; ?></p>
            <?php endif; ?>
          </div>
          
          <div class="form-actions">
            <a href="my-shops.php" class="btn btn-cancel">Cancel</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Create Shop</button>
          </div>
        </form>
      </div>
    </div>
  </div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('addShopForm');
    const shopName = document.getElementById('shop_name');
    const shopDesc = document.getElementById('shop_description');
    const phone = document.getElementById('business_phone');
    const building = document.getElementById('building_name_or_number');
    const locality = document.getElementById('locality_or_town');
    const district = document.getElementById('district');
    const pincode = document.getElementById('pincode');

    const handleValidation = (element, isValid) => {
        if (isValid) { element.classList.remove('is-invalid'); } 
        else { element.classList.add('is-invalid'); }
    };

    const validateRequired = (element) => {
        const isValid = element.value.trim() !== '';
        handleValidation(element, isValid);
        return isValid;
    };

    const validatePhone = (element) => {
        if (element.value.trim() === '') { handleValidation(element, true); return true; }
        const regex = /^[0-9]{10}$/;
        const isValid = regex.test(element.value);
        handleValidation(element, isValid);
        return isValid;
    };

    const validatePincode = (element) => {
        const regex = /^[1-9][0-9]{5}$/;
        const isValid = regex.test(element.value);
        handleValidation(element, isValid);
        return isValid;
    };

    form.addEventListener('submit', (e) => {
        const isNameValid = validateRequired(shopName);
        const isDescValid = validateRequired(shopDesc);
        const isPhoneValid = validatePhone(phone);
        const isBuildingValid = validateRequired(building);
        const isLocalityValid = validateRequired(locality);
        const isDistrictValid = validateRequired(district);
        const isPincodeValid = validatePincode(pincode);

        if (!isNameValid || !isDescValid || !isPhoneValid || !isBuildingValid || !isLocalityValid || !isDistrictValid || !isPincodeValid) {
            e.preventDefault();
        }
    });
});
</script>
</body>
</html>