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

// --- 2. INITIALIZATION ---
$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$errors = [];
$product_name = $product_description = $price = $quantity_available = $category_id = $shop_id = $subcategory_id = $status = $current_image = '';

// Redirect if no product ID is provided or it's invalid
if (!$product_id) {
    header('Location: my-products.php?error=not_found');
    exit();
}

// --- 3. HANDLE FORM SUBMISSION (POST REQUEST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $product_name = trim($_POST['product_name']);
    $product_description = trim($_POST['product_description']);
    $price = trim($_POST['price']);
    $quantity_available = trim($_POST['quantity_available']);
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $subcategory_id = isset($_POST['subcategory_id']) && !empty($_POST['subcategory_id']) ? (int)$_POST['subcategory_id'] : null;
    $shop_id = filter_input(INPUT_POST, 'shop_id', FILTER_VALIDATE_INT);
    $status = trim($_POST['status']);
    $current_image = $_POST['current_image'];

    // --- VALIDATION ---
    if (empty($product_name)) { $errors['product_name'] = "Product name is required."; }
    if (empty($product_description)) { $errors['product_description'] = "Description is required."; }
    if (empty($price)) { $errors['price'] = "Price is required."; }
    elseif (!is_numeric($price) || $price <= 0) { $errors['price'] = "Price must be a positive number."; }
    if (empty($quantity_available) && $quantity_available !== '0') { $errors['quantity_available'] = "Stock quantity is required."; }
    elseif (!is_numeric($quantity_available) || $quantity_available < 0) { $errors['quantity_available'] = "Stock must be a non-negative number."; }
    if (empty($category_id)) { $errors['category_id'] = "Category is required."; }
    if (empty($shop_id)) { $errors['shop_id'] = "Shop is required."; }
    if (!in_array($status, ['active', 'inactive'])) { $errors['status'] = "Invalid status selected."; }

    // FILE UPLOAD VALIDATION
    $new_image_name = $current_image;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "../images/";
        $image_info = getimagesize($_FILES["product_image"]["tmp_name"]);
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!$image_info || !in_array($image_info['mime'], $allowed_types)) {
            $errors['product_image'] = "Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.";
        } elseif ($_FILES["product_image"]["size"] > 5000000) {
            $errors['product_image'] = "Sorry, your file is too large (max 5MB).";
        } else {
            $new_image_name = uniqid('prod_', true) . '-' . basename($_FILES["product_image"]["name"]);
            $target_file = $target_dir . $new_image_name;
            if (!move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
                $errors['product_image'] = "Sorry, there was an error uploading your file.";
                $new_image_name = $current_image;
            }
        }
    }

    // --- IF NO ERRORS, UPDATE DATABASE ---
    if (empty($errors)) {
        $sql = "UPDATE products p
                JOIN shops s ON p.shop_id = s.shop_id
                SET
                    p.product_name = ?,
                    p.product_description = ?,
                    p.price = ?,
                    p.quantity_available = ?,
                    p.category_id = ?,
                    p.subcategory_id = ?,
                    p.shop_id = ?,
                    p.status = ?,
                    p.product_image = ?,
                    p.last_update = NOW()
                WHERE p.product_id = ? AND s.user_id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssdiiisssii",
            $product_name,
            $product_description,
            $price,
            $quantity_available,
            $category_id,
            $subcategory_id,
            $shop_id,
            $status,
            $new_image_name,
            $product_id,
            $user_id
        );

        if ($stmt->execute()) {
            if ($new_image_name !== $current_image && !empty($current_image) && file_exists("../images/" . $current_image)) {
                unlink("../images/" . $current_image);
            }
            header("Location: my-products.php?success=updated");
            exit();
        } else {
            $errors['db'] = "Database update failed. Please try again. " . $stmt->error;
        }
        $stmt->close();
    }
} else {
    // --- 4. FETCH EXISTING DATA FOR THE FORM (GET REQUEST) ---
    $sql = "SELECT p.* FROM products p
            JOIN shops s ON p.shop_id = s.shop_id
            WHERE p.product_id = ? AND s.user_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $product_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $product = $result->fetch_assoc();
        $product_name = $product['product_name'];
        $product_description = $product['product_description'];
        $price = $product['price'];
        $quantity_available = $product['quantity_available'];
        $category_id = $product['category_id'];
        $shop_id = $product['shop_id'];
        $status = $product['status'];
        $current_image = $product['product_image'];
        $subcategory_id = $product['subcategory_id'];
    } else {
        header("Location: my-products.php?error=not_found");
        exit();
    }
    $stmt->close();
}

// Fetch categories and seller's shops for dropdowns
$categories_result = $conn->query("SELECT category_id, category_name FROM category ORDER BY category_name");
$shops_result = $conn->execute_query("SELECT shop_id, shop_name FROM shops WHERE user_id = ? ORDER BY shop_name", [$user_id]);

// Fetch subcategories for the product's CURRENT category to populate the dropdown on page load
$subcategories_result = null;
if ($category_id) {
    $subcategories_result = $conn->execute_query("SELECT subcategory_id, subcategory_name FROM subcategory WHERE category_id = ? ORDER BY subcategory_name", [$category_id]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit Product | Vastra</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <script src="../refresh.js"></script>
  <style>
    .error-message { color: #d9534f; font-size: 0.9em; margin-top: 5px; display: block; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: bold; }
    .form-group input, .form-group select, .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
    }
    .current-img-preview { max-width: 150px; margin-top: 10px; border-radius: 5px; border: 1px solid #ddd; }
  </style>
</head>
<body>
  <div class="sidebar">
    <?php require "sidebar.php"; ?>
  </div>
  <div class="main">
    <div class="header">
      <h1>Edit Product</h1>
      <a href="my-products.php" class="btn"><i class="fas fa-arrow-left"></i> Back to Products</a>
    </div>
    <div class="content">
      <form action="edit-product.php?id=<?php echo $product_id; ?>" method="POST" enctype="multipart/form-data" class="form-container">
        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product_id); ?>">
        <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($current_image); ?>">

        <?php if (!empty($errors['db'])): ?>
            <p class="error-message" style="background-color: #f2dede; padding: 15px; border-radius: 5px;"><?php echo $errors['db']; ?></p>
        <?php endif; ?>

        <div class="form-group">
          <label for="product_name">Product Name</label>
          <input type="text" id="product_name" name="product_name" value="<?php echo htmlspecialchars($product_name); ?>" required>
          <?php if (!empty($errors['product_name'])): ?><span class="error-message"><?php echo $errors['product_name']; ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label for="product_description">Product Description</label>
          <textarea id="product_description" name="product_description" rows="5" required><?php echo htmlspecialchars($product_description); ?></textarea>
          <?php if (!empty($errors['product_description'])): ?><span class="error-message"><?php echo $errors['product_description']; ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label for="shop_id">Shop</label>
          <select id="shop_id" name="shop_id" required>
            <option value="">-- Select a Shop --</option>
            <?php mysqli_data_seek($shops_result, 0); ?>
            <?php while($shop = $shops_result->fetch_assoc()): ?>
              <option value="<?php echo $shop['shop_id']; ?>" <?php if($shop_id == $shop['shop_id']) echo 'selected'; ?>>
                <?php echo htmlspecialchars($shop['shop_name']); ?>
              </option>
            <?php endwhile; ?>
          </select>
          <?php if (!empty($errors['shop_id'])): ?><span class="error-message"><?php echo $errors['shop_id']; ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label for="category_id">Category</label>
          <select id="category_id" name="category_id" required>
            <option value="">-- Select a Category --</option>
            <?php mysqli_data_seek($categories_result, 0); ?>
            <?php while($category = $categories_result->fetch_assoc()): ?>
              <option value="<?php echo $category['category_id']; ?>" <?php if($category_id == $category['category_id']) echo 'selected'; ?>>
                <?php echo htmlspecialchars($category['category_name']); ?>
              </option>
            <?php endwhile; ?>
          </select>
          <?php if (!empty($errors['category_id'])): ?><span class="error-message"><?php echo $errors['category_id']; ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label for="subcategory_id">Subcategory</label>
          <select id="subcategory_id" name="subcategory_id">
              <option value="">-- Select a Subcategory --</option>
              <?php if ($subcategories_result && $subcategories_result->num_rows > 0): ?>
                  <?php while($subcat = $subcategories_result->fetch_assoc()): ?>
                      <option value="<?php echo $subcat['subcategory_id']; ?>" <?php if($subcategory_id == $subcat['subcategory_id']) echo 'selected'; ?>>
                          <?php echo htmlspecialchars($subcat['subcategory_name']); ?>
                      </option>
                  <?php endwhile; ?>
              <?php else: ?>
                  <option value="">-- Select Category to see options --</option>
              <?php endif; ?>
          </select>
          <?php if (!empty($errors['subcategory_id'])): ?><span class="error-message"><?php echo $errors['subcategory_id']; ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label for="price">Price (₹)</label>
          <input type="number" id="price" name="price" step="0.01" value="<?php echo htmlspecialchars($price); ?>" required>
          <?php if (!empty($errors['price'])): ?><span class="error-message"><?php echo $errors['price']; ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label for="quantity_available">Stock Quantity</label>
          <input type="number" id="quantity_available" name="quantity_available" value="<?php echo htmlspecialchars($quantity_available); ?>" required>
          <?php if (!empty($errors['quantity_available'])): ?><span class="error-message"><?php echo $errors['quantity_available']; ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label for="status">Status</label>
          <select id="status" name="status" required>
            <option value="active" <?php if($status == 'active') echo 'selected'; ?>>Active</option>
            <option value="inactive" <?php if($status == 'inactive') echo 'selected'; ?>>Inactive</option>
          </select>
           <?php if (!empty($errors['status'])): ?><span class="error-message"><?php echo $errors['status']; ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label for="product_image">Product Image (optional, leave blank to keep current image)</label>
          <p>Current Image:</p>
          <?php if (!empty($current_image) && file_exists("../images/" . $current_image)): ?>
            <img src="../images/<?php echo htmlspecialchars($current_image); ?>" alt="Current Product Image" class="current-img-preview">
          <?php else: ?>
            <p>No image available.</p>
          <?php endif; ?>
          <br><br>
          <input type="file" id="product_image" name="product_image" accept="image/png, image/jpeg, image/gif, image/webp">
          <?php if (!empty($errors['product_image'])): ?><span class="error-message"><?php echo $errors['product_image']; ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </div>
  <?php $conn->close(); ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        const categorySelect = document.getElementById('category_id');
        const subcategorySelect = document.getElementById('subcategory_id');

        categorySelect.addEventListener('change', function() {
            const categoryId = this.value;
            subcategorySelect.innerHTML = '<option value="">Loading...</option>';

            if (!categoryId) {
                subcategorySelect.innerHTML = '<option value="">-- Select a Category First --</option>';
                return;
            }

            fetch(`get_subcategories.php?category_id=${categoryId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
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
                    subcategorySelect.innerHTML = '<option value="">-- Error Loading Subcategories --</option>';
                });
        });
    });
  </script>
</body>
</html>