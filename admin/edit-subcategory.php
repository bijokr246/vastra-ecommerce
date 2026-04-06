<?php
// 1. START THE SESSION & CHECK FOR LOGIN
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// 2. INCLUDE DATABASE CONNECTION
include "../config.php";

// 3. INITIALIZE VARIABLES
$error = '';
$subcategory = null;
$categories = []; // To hold all parent categories for the dropdown
$subcategory_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 4. HANDLE FORM SUBMISSION (POST REQUEST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_subcategory'])) {
    if (!isset($_SESSION['admin_id'])) {
      header('Location: ../login.php');
      exit();
  }
    $subcategory_id = intval($_POST['subcategory_id']);
    $subcategory_name = trim($_POST['subcategory_name']);
    $parent_category_id = intval($_POST['parent_category']);

    // Basic validation
    if (empty($subcategory_name)) {
        $error = "Subcategory name cannot be empty.";
    } elseif (empty($parent_category_id)) {
        $error = "You must select a parent category.";
    } else {
        // --- START: DUPLICATE CHECK ON EDIT ---
        // Check if another subcategory with this name already exists in the selected parent category.
        // We must exclude the current subcategory from the check using "subcategory_id != ?"
        $stmt_check = $conn->prepare("SELECT subcategory_id FROM subcategory WHERE LOWER(subcategory_name) = LOWER(?) AND category_id = ? AND subcategory_id != ?");
        $stmt_check->bind_param("sii", $subcategory_name, $parent_category_id, $subcategory_id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $error = "Another subcategory with this name already exists in the selected parent category.";
        } else {
            // --- END: DUPLICATE CHECK ON EDIT ---

            // No duplicate found, proceed with the update
            $stmt_update = $conn->prepare("UPDATE subcategory SET subcategory_name = ?, category_id = ? WHERE subcategory_id = ?");
            $stmt_update->bind_param("sii", $subcategory_name, $parent_category_id, $subcategory_id);

            if ($stmt_update->execute()) {
                $_SESSION['sub_message'] = "Subcategory updated successfully!";
                header("Location: manage-categories.php#subcategory-section");
                exit();
            } else {
                $error = "Error updating subcategory: " . $stmt_update->error;
            }
            $stmt_update->close();
        }
        $stmt_check->close();
    }
}


// 5. FETCH EXISTING DATA FOR DISPLAY (GET REQUEST)
if ($subcategory_id > 0) {
    // --- Get the specific subcategory to edit ---
    $stmt_sub = $conn->prepare("SELECT subcategory_id, subcategory_name, category_id FROM subcategory WHERE subcategory_id = ?");
    $stmt_sub->bind_param("i", $subcategory_id);
    $stmt_sub->execute();
    $result_sub = $stmt_sub->get_result();
    if ($result_sub->num_rows === 1) {
        $subcategory = $result_sub->fetch_assoc();
    } else {
        // If an error occurred during POST, we still need the subcategory data to re-display the form
        if (empty($error)) {
             $error = "Subcategory not found.";
        }
    }
    $stmt_sub->close();

    // --- Get all parent categories for the dropdown menu ---
    $result_cats = $conn->query("SELECT category_id, category_name FROM category ORDER BY category_name ASC");
    if ($result_cats) {
        while ($row = $result_cats->fetch_assoc()) {
            $categories[] = $row;
        }
    }

} else {
    $error = "Invalid subcategory ID specified.";
}

// If an error occurred during POST, we must repopulate the $subcategory array
// with the submitted (but failed) data so the user doesn't lose their changes.
if (!empty($error) && isset($_POST['update_subcategory'])) {
    $subcategory = [
        'subcategory_id' => $_POST['subcategory_id'],
        'subcategory_name' => $_POST['subcategory_name'],
        'category_id' => $_POST['parent_category']
    ];
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
  <title>Edit Subcategory | Vastra Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <script src="../refresh.js"></script>
</head>
<body>
  
  <!-- Include your standard sidebar -->
  <aside class="sidebar">
    <?php require "header.php" ?>
  </aside>

  <main class="main-content">
    <h1 class="page-header">Edit Subcategory</h1>
    
    <a href="manage-categories.php" class="btn" style="margin-bottom: 20px;">← Back to Categories</a>
    
    <!-- Show form only if subcategory was found -->
    <?php if ($subcategory): ?>
        <section class="content-card">
          <!-- Display any errors from form submission -->
          <?php if (!empty($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
          <?php endif; ?>
          
          <form action="edit-subcategory.php?id=<?php echo $subcategory_id; ?>" method="POST">
            <input type="hidden" name="subcategory_id" value="<?php echo htmlspecialchars($subcategory['subcategory_id']); ?>">
            
            <div class="form-group">
              <label>Subcategory Name</label>
              <!-- Pre-filled with data from the database -->
              <input type="text" name="subcategory_name" placeholder="e.g., T-Shirts" value="<?php echo htmlspecialchars($subcategory['subcategory_name']); ?>" required />
            </div>
            
            <div class="form-group">
              <label>Parent Category</label>
              <select name="parent_category" required>
                <option value="">Select a parent category</option>
                <?php foreach ($categories as $category): ?>
                    <!-- 
                      This PHP block checks if the current category in the loop matches the subcategory's parent.
                      If it matches, it adds the 'selected' attribute to the option tag.
                    -->
                    <option value="<?php echo $category['category_id']; ?>" <?php echo ($category['category_id'] == $subcategory['category_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['category_name']); ?>
                    </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <button class="btn btn-primary" type="submit" name="update_subcategory">Update Subcategory</button>
          </form>
        </section>
    <?php else: ?>
        <!-- Show error if subcategory was not found -->
        <section class="content-card">
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        </section>
    <?php endif; ?>
  </main>
</body>
</html>