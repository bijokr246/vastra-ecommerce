<?php
// 1. START THE SESSION & CHECK FOR LOGIN
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// 2. INCLUDE DATABASE CONNECTION AND HELPERS
include "../config.php";

// Define the path for image uploads, consistent with manage-categories.php
define('UPLOAD_PATH', '../images/');

// 3. INITIALIZE VARIABLES
$error = '';
$category = null;
$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 4. HANDLE FORM SUBMISSION (POST REQUEST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_category'])) {
    $category_id = intval($_POST['category_id']);
    $category_name = trim($_POST['category_name']);
    $has_new_image = isset($_FILES['category_image']) && $_FILES['category_image']['error'] == UPLOAD_ERR_OK;

    if (empty($category_name)) {
        $error = "Category name cannot be empty.";
    } else {
        // --- START: DUPLICATE CHECK ON EDIT ---
        // Check if another category with this name already exists.
        // We must exclude the current category from the check using "category_id != ?"
        $stmt_check = $conn->prepare("SELECT category_id FROM category WHERE LOWER(category_name) = LOWER(?) AND category_id != ?");
        $stmt_check->bind_param("si", $category_name, $category_id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $error = "A category with this name already exists.";
        } else {
            // --- END: DUPLICATE CHECK ON EDIT ---
            
            // No duplicate found, proceed with update logic
            $stmt_update = null; // Initialize statement variable

            // --- If a new image is uploaded, handle it ---
            if ($has_new_image) {
                $target_file = UPLOAD_PATH . basename($_FILES["category_image"]["name"]);
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                $new_filename = uniqid('cat_', true) . '.' . $imageFileType;
                $destination = UPLOAD_PATH . $new_filename;

                // Image validation
                $check = getimagesize($_FILES["category_image"]["tmp_name"]);
                if ($check === false) {
                    $error = "File is not an image.";
                } elseif (!in_array($imageFileType, ['jpg', 'png', 'jpeg'])) {
                    $error = "Sorry, only JPG, JPEG, & PNG files are allowed.";
                } elseif (move_uploaded_file($_FILES["category_image"]["tmp_name"], $destination)) {
                    // New image uploaded successfully, get old image filename to delete it
                    $stmt_old_img = $conn->prepare("SELECT img_url FROM category WHERE category_id = ?");
                    $stmt_old_img->bind_param("i", $category_id);
                    $stmt_old_img->execute();
                    $result_old_img = $stmt_old_img->get_result();
                    if ($row = $result_old_img->fetch_assoc()) {
                        // **FIXED BUG**: was $row['image'], changed to $row['img_url']
                        $old_image_path = UPLOAD_PATH . $row['img_url'];
                        if (file_exists($old_image_path) && !is_dir($old_image_path)) {
                            unlink($old_image_path); // Delete the old file
                        }
                    }
                    $stmt_old_img->close();

                    // Prepare to update the database with the new name and new image
                    $stmt_update = $conn->prepare("UPDATE category SET category_name = ?, img_url = ? WHERE category_id = ?");
                    $stmt_update->bind_param("ssi", $category_name, $new_filename, $category_id);
                } else {
                    $error = "Sorry, there was an error uploading your new image.";
                }
            } else {
                // --- If no new image, just prepare to update the name ---
                $stmt_update = $conn->prepare("UPDATE category SET category_name = ? WHERE category_id = ?");
                $stmt_update->bind_param("si", $category_name, $category_id);
            }

            // Execute the update query if no errors occurred and statement is prepared
            if (empty($error) && $stmt_update) {
                if ($stmt_update->execute()) {
                    $_SESSION['message'] = "Category updated successfully!";
                    header("Location: manage-categories.php");
                    exit();
                } else {
                    $error = "Error updating category: " . $stmt_update->error;
                }
                $stmt_update->close();
            }
        }
        $stmt_check->close();
    }
}


// 5. FETCH EXISTING CATEGORY DATA FOR DISPLAY (GET REQUEST)
if ($category_id > 0) {
    // Using a prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT category_id, category_name, img_url FROM category WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $category = $result->fetch_assoc();
    } else {
         if (empty($error)) { // Only show this if no POST error occurred
            $error = "Category not found.";
        }
    }
    $stmt->close();
} else {
    $error = "Invalid category ID specified.";
}

// If an error occurred during POST, repopulate the name field with the user's failed attempt
if (!empty($error) && isset($_POST['update_category'])) {
    if($category) { // Make sure category data was loaded
       $category['category_name'] = htmlspecialchars($_POST['category_name']);
    }
}

$conn->close();

// --- Helper for Active Nav Link ---
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Edit Category | Vastra Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <script src="../refresh.js"></script>
</head>
<body>
  
  <aside class="sidebar">
    <?php require "header.php" ?>
  </aside>

  <main class="main-content">
    <h1 class="page-header">Edit Category</h1>
    
    <a href="manage-categories.php" class="btn" style="margin-bottom: 20px;">← Back to Categories</a>
    
    <?php if ($category): ?>
        <section class="content-card">
          <?php if (!empty($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
          <?php endif; ?>

          <form action="edit-category.php?id=<?php echo $category_id; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($category['category_id']); ?>">
            
            <div class="form-group">
              <label>Category Name</label>
              <input type="text" name="category_name" placeholder="e.g., Footwear" value="<?php echo htmlspecialchars($category['category_name']); ?>" required />
            </div>
            
            <div class="form-group">
              <label>Current Image</label>
              <img src="<?php echo UPLOAD_PATH . htmlspecialchars($category['img_url']); ?>" alt="Current Image" style="max-width: 100px; display: block; margin-bottom: 10px; border-radius: 4px;">
            </div>
            
            <div class="form-group">
              <label>Upload New Image (optional)</label>
              <input type="file" name="category_image" accept="image/png, image/jpeg, image/jpg" />
              <small>Only choose a file if you want to replace the current image.</small>
            </div>
            
            <button class="btn btn-primary" type="submit" name="update_category">Update Category</button>
          </form>
        </section>
    <?php else: ?>
        <section class="content-card">
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        </section>
    <?php endif; ?>
  </main>
</body>
</html>