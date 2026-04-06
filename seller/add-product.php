<?php
session_start();
include "../config.php";

// --- 1. SECURITY AND AUTHENTICATION ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ../seller-login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$stmt_role = $conn->prepare("SELECT role FROM login WHERE user_id = ?");
$stmt_role->bind_param("i", $user_id);
$stmt_role->execute();
$role_result = $stmt_role->get_result()->fetch_assoc();
if (!$role_result || $role_result['role'] !== 'seller') {
    session_destroy();
    header('Location: ../seller-login.php?error=access_denied');
    exit();
}
$stmt_role->close();

// --- 2. INITIAL DATA FETCHING ---
$shops_result = $conn->execute_query("SELECT shop_id, shop_name FROM shops WHERE user_id = ?", [$user_id]);
$categories_result = $conn->query("SELECT category_id, category_name FROM category ORDER BY category_name ASC");

$errors = [];
$success_message = '';

// --- 3. FORM SUBMISSION PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../seller-login.php');
        exit();
    }
    // Sanitize and retrieve form data
    $product_name = trim($_POST['product_name']);
    $shop_id = (int)($_POST['shop_id']);
    $category_id = (int)($_POST['category_id']);
    $subcategory_id = isset($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
    $price = trim($_POST['price']);
    $quantity = trim($_POST['quantity']);
    $description = trim($_POST['product_desc']);
    $product_image = $_FILES['product_image'];

    // --- 4. SERVER-SIDE VALIDATION ---
    if (empty($product_name)) $errors['product_name'] = "Product name is required.";
    if (empty($shop_id)) $errors['shop_id'] = "Please select a shop.";
    if (empty($category_id)) $errors['category_id'] = "Please select a category.";
    if (empty($price) || !is_numeric($price) || $price <= 0) $errors['price'] = "Please enter a valid price.";
    if (empty($quantity) || !ctype_digit($quantity) || $quantity <= 0) $errors['quantity'] = "Please enter a valid stock quantity.";
    if (empty($description)) $errors['product_desc'] = "Description cannot be empty.";

    // Image validation
    if (isset($product_image) && $product_image['error'] == 0) {
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $file_extension = strtolower(pathinfo($product_image['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_extensions)) $errors['product_image'] = "Invalid file type. Only JPG, PNG, GIF, WEBP are allowed.";
        if ($product_image['size'] > 5097152) $errors['product_image'] = "File is too large. Maximum size is 5MB.";
    } else {
        $errors['product_image'] = "Product image is required.";
    }

    // --- 5. PROCESS DATA IF NO ERRORS ---
    if (empty($errors)) {
        $image_filename = uniqid('prod_', true) . '.' . $file_extension;
        $upload_path = '../images/' . $image_filename;

        if (move_uploaded_file($product_image['tmp_name'], $upload_path)) {
            $sql = "INSERT INTO products (shop_id, category_id, subcategory_id, product_name, product_description, price, quantity_available, product_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiissdis", $shop_id, $category_id, $subcategory_id, $product_name, $description, $price, $quantity, $image_filename);

            if ($stmt->execute()) {
                $success_message = "Product added successfully! It is now pending admin approval.";
                $_POST = array();
            } else {
                $errors['general'] = "Database error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errors['general'] = "Failed to upload image.";
        }
    }
}

// --- Helper for Active Nav Link ---
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Add Product | Vastra</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <script src="../refresh.js"></script>
</head>
<body>
  <div class="sidebar">
    <?php require "sidebar.php"; ?>
  </div>
  <div class="main">
    <div class="header">
      <h1>Add New Product</h1>
    </div>
    <div class="content">
      <form id="addProductForm" action="add-product.php" method="POST" enctype="multipart/form-data" novalidate>
        <?php if (!empty($success_message)): ?>
          <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if (isset($errors['general'])): ?>
          <div class="message error"><?php echo $errors['general']; ?></div>
        <?php endif; ?>

        <div class="form-group">
          <label for="product_name">Product Name</label>
          <input type="text" id="product_name" name="product_name" placeholder="e.g., Men's Slim Fit Shirt" value="<?php echo htmlspecialchars($_POST['product_name'] ?? ''); ?>" required>
          <small class="error-text"><?php echo $errors['product_name'] ?? ''; ?></small>
        </div>

        <div class="form-group">
          <label for="shop_id">Select Shop</label>
          <select id="shop_id" name="shop_id" required>
            <option value="">-- Select a Shop --</option>
            <?php mysqli_data_seek($shops_result, 0); ?>
            <?php while($shop = $shops_result->fetch_assoc()): ?>
              <option value="<?php echo $shop['shop_id']; ?>" <?php echo (isset($_POST['shop_id']) && $_POST['shop_id'] == $shop['shop_id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($shop['shop_name']); ?>
              </option>
            <?php endwhile; ?>
          </select>
          <small class="error-text"><?php echo $errors['shop_id'] ?? ''; ?></small>
        </div>

        <div class="form-group">
          <label for="category_id">Category</label>
          <select id="category_id" name="category_id" required>
            <option value="">-- Select a Category --</option>
            <?php mysqli_data_seek($categories_result, 0); ?>
            <?php while($category = $categories_result->fetch_assoc()): ?>
              <option value="<?php echo $category['category_id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($category['category_name']); ?>
              </option>
            <?php endwhile; ?>
          </select>
          <small class="error-text"><?php echo $errors['category_id'] ?? ''; ?></small>
        </div>

        <div class="form-group">
          <label for="subcategory_id">Subcategory</label>
          <select id="subcategory_id" name="subcategory_id">
            <option value="">-- Select a Subcategory --</option>
            <!-- Subcategories will be populated by JavaScript -->
          </select>
          <small class="error-text"><?php echo $errors['subcategory_id'] ?? ''; ?></small>
        </div>

        <div class="form-group">
          <label for="price">Price (₹)</label>
          <input type="number" id="price" name="price" placeholder="e.g., 999" step="0.01" min="1" value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" required>
          <small class="error-text"><?php echo $errors['price'] ?? ''; ?></small>
        </div>

        <div class="form-group">
          <label for="quantity">Stock Quantity</label>
          <input type="number" id="quantity" name="quantity" placeholder="e.g., 100" min="0" value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>" required>
          <small class="error-text"><?php echo $errors['quantity'] ?? ''; ?></small>
        </div>

        <div class="form-group">
          <label for="product_desc">Product Description</label>
          <textarea id="product_desc" name="product_desc" rows="4" placeholder="Add a detailed description..."><?php echo htmlspecialchars($_POST['product_desc'] ?? ''); ?></textarea>
          <small class="error-text"><?php echo $errors['product_desc'] ?? ''; ?></small>
        </div>

        <div class="form-group">
          <label for="product_image">Product Image (Max 2MB)</label>
          <input type="file" id="product_image" name="product_image" accept="image/png, image/jpeg, image/gif, image/webp" required>
          <small class="error-text"><?php echo $errors['product_image'] ?? ''; ?></small>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Submit for Approval</button>
          <a href="my-products.php" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const categorySelect = document.getElementById('category_id');
      const subcategorySelect = document.getElementById('subcategory_id');

      categorySelect.addEventListener('change', function() {
        const categoryId = this.value;
        subcategorySelect.innerHTML = '<option value="">Loading...</option>';
        if (!categoryId) {
          subcategorySelect.innerHTML = '<option value="">-- Select Category First --</option>';
          return;
        }
        fetch(`get_subcategories.php?category_id=${categoryId}`)
          .then(response => response.json())
          .then(data => {
            subcategorySelect.innerHTML = '<option value="">-- Select a Subcategory --</option>';
            if (data.length > 0) {
              data.forEach(subcategory => {
                const option = document.createElement('option');
                option.value = subcategory.subcategory_id;
                option.textContent = subcategory.subcategory_name;
                subcategorySelect.appendChild(option);
              });
            } else {
              subcategorySelect.innerHTML = '<option value="">-- No Subcategories Found --</option>';
            }
          })
          .catch(error => {
            console.error('Error fetching subcategories:', error);
            subcategorySelect.innerHTML = '<option value="">-- Error Loading --</option>';
          });
      });

      // Client-side validation
      const form = document.getElementById('addProductForm');
      form.addEventListener('submit', function(event) {
        let isValid = true;
        document.querySelectorAll('.error-text').forEach(el => el.textContent = '');

        const productName = document.getElementById('product_name');
        if (productName.value.trim() === '') { showError(productName, 'Product name is required.'); isValid = false; }

        const shopId = document.getElementById('shop_id');
        if (shopId.value === '') { showError(shopId, 'Please select a shop.'); isValid = false; }

        const categoryId = document.getElementById('category_id');
        if (categoryId.value === '') { showError(categoryId, 'Please select a category.'); isValid = false; }

        const price = document.getElementById('price');
        if (price.value.trim() === '' || parseFloat(price.value) <= 0) { showError(price, 'Please enter a valid price.'); isValid = false; }

        const quantity = document.getElementById('quantity');
        if (quantity.value.trim() === '' || parseInt(quantity.value) < 0) { showError(quantity, 'Please enter a valid stock quantity.'); isValid = false; }

        const productImage = document.getElementById('product_image');
        if (productImage.files.length === 0) { showError(productImage, 'Product image is required.'); isValid = false; }

        if (!isValid) {
          event.preventDefault();
        }
      });

      function showError(inputElement, message) {
        const errorElement = inputElement.nextElementSibling;
        if (errorElement && errorElement.classList.contains('error-text')) {
          errorElement.textContent = message;
        }
      }
    });
  </script>
</body>
</html>