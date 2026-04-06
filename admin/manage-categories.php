<?php
// 1. START SESSION AND CHECK FOR LOGIN
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// 2. INCLUDE DATABASE CONNECTION AND HELPERS
include "../config.php";
define('UPLOAD_PATH', '../images/');
if (!is_dir(UPLOAD_PATH)) { mkdir(UPLOAD_PATH, 0777, true); }

// Get filter/search parameters from the URL
$cat_search = isset($_GET['cat_search']) ? trim($_GET['cat_search']) : '';
$subcat_search = isset($_GET['subcat_search']) ? trim($_GET['subcat_search']) : '';
$filter_parent_cat = isset($_GET['filter_parent_cat']) ? (int)$_GET['filter_parent_cat'] : 0;

// Build a query string for state preservation
$query_params = array_filter(['cat_search' => $cat_search, 'subcat_search' => $subcat_search, 'filter_parent_cat' => $filter_parent_cat]);
$redirect_query_string = http_build_query($query_params);

// 3. INITIALIZE VARIABLES FOR MESSAGES
$message = ''; $error = '';
$sub_message = ''; $sub_error = '';

// 4. HANDLE FORM SUBMISSIONS (POST REQUESTS)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- Add New Category ---
    if (isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name']);
        if (empty($category_name)) { $error = "Category name is required."; }
        elseif (empty($_FILES["category_image"]["name"])) { $error = "Category image is required."; }
        else {
            $stmt_check = $conn->prepare("SELECT category_id FROM category WHERE LOWER(category_name) = LOWER(?)");
            $stmt_check->bind_param("s", $category_name); $stmt_check->execute(); $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) { $error = "A category with this name already exists."; }
            else {
                $target_file = UPLOAD_PATH . basename($_FILES["category_image"]["name"]);
                $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                $new_filename = uniqid('cat_', true) . '.' . $imageFileType;
                $destination = UPLOAD_PATH . $new_filename;
                $check = getimagesize($_FILES["category_image"]["tmp_name"]);
                if ($check === false) { $error = "File is not an image."; }
                elseif (!in_array($imageFileType, ['jpg', 'png', 'jpeg'])) { $error = "Sorry, only JPG, JPEG, & PNG files are allowed."; }
                elseif (!move_uploaded_file($_FILES["category_image"]["tmp_name"], $destination)) { $error = "Sorry, there was an error uploading your file."; }
                else {
                    $stmt = $conn->prepare("INSERT INTO category (category_name, img_url) VALUES (?, ?)");
                    $stmt->bind_param("ss", $category_name, $new_filename);
                    if ($stmt->execute()) { $_SESSION['message'] = "Category added successfully!"; }
                    else { $_SESSION['error'] = "Error adding category: " . $stmt->error; unlink($destination); }
                    $stmt->close();
                    header("Location: manage-categories.php?" . $redirect_query_string); exit();
                }
            }
            $stmt_check->close();
        }
    }

    // --- Add New Subcategory ---
    if (isset($_POST['add_subcategory'])) {
        $subcategory_name = trim($_POST['subcategory_name']);
        $parent_category_id = $_POST['parent_category'];
        if (empty($subcategory_name) || empty($parent_category_id)) { 
            $_SESSION['sub_error'] = "Both subcategory name and parent category are required.";
        } else {
            $stmt_check = $conn->prepare("SELECT subcategory_id FROM subcategory WHERE LOWER(subcategory_name) = LOWER(?) AND category_id = ?");
            $stmt_check->bind_param("si", $subcategory_name, $parent_category_id); $stmt_check->execute(); $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $_SESSION['sub_error'] = "This subcategory already exists in the selected parent category.";
            } else {
                $stmt = $conn->prepare("INSERT INTO subcategory (category_id, subcategory_name) VALUES (?, ?)");
                $stmt->bind_param("is", $parent_category_id, $subcategory_name);
                if ($stmt->execute()) { $_SESSION['sub_message'] = "Subcategory added successfully!"; }
                else { $_SESSION['sub_error'] = "Error adding subcategory: " . $stmt->error; }
                $stmt->close();
            }
            $stmt_check->close();
        }
        header("Location: manage-categories.php?" . $redirect_query_string . "#subcategory-section");
        exit();
    }
}

// 5. HANDLE DELETE REQUESTS (GET REQUESTS)
if (isset($_GET['delete_cat']) || isset($_GET['delete_subcat'])) {
    if (isset($_GET['delete_cat'])) {
        $category_id = intval($_GET['delete_cat']);
        $stmt_img = $conn->prepare("SELECT img_url FROM category WHERE category_id = ?");
        $stmt_img->bind_param("i", $category_id); $stmt_img->execute(); $result_img = $stmt_img->get_result();
        if ($row_img = $result_img->fetch_assoc()) { $image_to_delete = UPLOAD_PATH . $row_img['img_url']; if (file_exists($image_to_delete)) { unlink($image_to_delete); } }
        $stmt_img->close();
        $stmt_sub = $conn->prepare("DELETE FROM subcategory WHERE category_id = ?");
        $stmt_sub->bind_param("i", $category_id); $stmt_sub->execute(); $stmt_sub->close();
        $stmt_cat = $conn->prepare("DELETE FROM category WHERE category_id = ?");
        $stmt_cat->bind_param("i", $category_id);
        if ($stmt_cat->execute()) { $_SESSION['message'] = "Category and its subcategories have been deleted."; } else { $_SESSION['error'] = "Error deleting category."; }
        $stmt_cat->close();
        header("Location: manage-categories.php?" . $redirect_query_string);
        exit();
    }
    if (isset($_GET['delete_subcat'])) {
        $subcategory_id = intval($_GET['delete_subcat']);
        $stmt = $conn->prepare("DELETE FROM subcategory WHERE subcategory_id = ?");
        $stmt->bind_param("i", $subcategory_id);
        if ($stmt->execute()) { $_SESSION['sub_message'] = "Subcategory deleted successfully."; }
        else { $_SESSION['sub_error'] = "Error deleting subcategory."; }
        $stmt->close();
        header("Location: manage-categories.php?" . $redirect_query_string . "#subcategory-section");
        exit();
    }
}

// Set messages from session
if (isset($_SESSION['message'])) { $message = $_SESSION['message']; unset($_SESSION['message']); }
if (isset($_SESSION['error'])) { $error = $_SESSION['error']; unset($_SESSION['error']); }
if (isset($_SESSION['sub_message'])) { $sub_message = $_SESSION['sub_message']; unset($_SESSION['sub_message']); }
if (isset($_SESSION['sub_error'])) { $sub_error = $_SESSION['sub_error']; unset($_SESSION['sub_error']); }

// 6. FETCH DATA FOR DISPLAY
$categories = [];
$sql_cats = "SELECT * FROM category";
if (!empty($cat_search)) { $sql_cats .= " WHERE category_name LIKE ?"; }
$sql_cats .= " ORDER BY category_name ASC";
$stmt_cats = $conn->prepare($sql_cats);
if (!empty($cat_search)) { $search_param_cat = "%" . $cat_search . "%"; $stmt_cats->bind_param("s", $search_param_cat); }
$stmt_cats->execute();
$result_cats = $stmt_cats->get_result();
if ($result_cats) { $categories = $result_cats->fetch_all(MYSQLI_ASSOC); }

$subcategories = [];
$sql_subcats = "SELECT s.subcategory_id, s.subcategory_name, c.category_name FROM subcategory s JOIN category c ON s.category_id = c.category_id";
$where_clauses_sub = []; $params_sub = []; $types_sub = '';
if ($filter_parent_cat > 0) { $where_clauses_sub[] = "s.category_id = ?"; $params_sub[] = $filter_parent_cat; $types_sub .= 'i'; }
if (!empty($subcat_search)) { $where_clauses_sub[] = "(s.subcategory_name LIKE ? OR c.category_name LIKE ?)"; $search_param_sub = "%" . $subcat_search . "%"; $params_sub[] = $search_param_sub; $params_sub[] = $search_param_sub; $types_sub .= 'ss'; }
if (!empty($where_clauses_sub)) { $sql_subcats .= " WHERE " . implode(" AND ", $where_clauses_sub); }
$sql_subcats .= " ORDER BY c.category_name, s.subcategory_name ASC";
$stmt_subcats = $conn->prepare($sql_subcats);
if (!empty($params_sub)) { $stmt_subcats->bind_param($types_sub, ...$params_sub); }
$stmt_subcats->execute();
$result_subcats = $stmt_subcats->get_result();
if ($result_subcats) { $subcategories = $result_subcats->fetch_all(MYSQLI_ASSOC); }

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Categories | Vastra Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="../refresh.js"></script>
    <style>
        .filter-bar { display: flex; gap: 10px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; }
        .filter-bar input[type="text"], .filter-bar select { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .filter-bar .search-group { flex-grow: 1; }
        .form-error { color: #dc3545; font-size: 0.875em; margin-top: 5px; display: block; height: 1em; /* Reserve space */ }
    </style>
</head>
<body>
    <aside class="sidebar"><?php require "header.php" ?></aside>

    <main class="main-content">
        <h1 class="page-header">Category Management</h1>

        <?php if (!empty($message)): ?><div class="message success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="message error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <section class="content-card category-layout">
            <div>
                <h2>Category List</h2>
                <form action="manage-categories.php" method="GET" class="filter-bar">
                    <div class="search-group"><input type="text" name="cat_search" placeholder="Search categories..." value="<?php echo htmlspecialchars($cat_search); ?>" style="width: 100%;"></div>
                    <button type="submit" class="btn btn-sm btn-secondary">Search</button>
                    <?php if (!empty($cat_search)): ?><a href="manage-categories.php?<?php echo http_build_query(array_merge($query_params, ['cat_search' => ''])); ?>" class="btn btn-sm btn-primary">Clear</a><?php endif; ?>
                </form>
                <table class="data-table">
                    <thead><tr><th>Image</th><th>Category</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($categories)): ?><tr><td colspan="3" style="text-align: center;">No categories found.</td></tr>
                        <?php else: foreach ($categories as $category): ?>
                            <tr>
                                <td><img src="<?php echo UPLOAD_PATH . htmlspecialchars($category['img_url']); ?>" alt="<?php echo htmlspecialchars($category['category_name']); ?>" class="table-image"></td>
                                <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                                <td class="action-buttons">
                                    <a href="edit-category.php?id=<?php echo $category['category_id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                    <a class="btn btn-sm btn-primary" href="manage-categories.php?delete_cat=<?php echo $category['category_id']; ?>&<?php echo $redirect_query_string; ?>" onclick="return confirm('Are you sure? This will delete ALL related subcategories.');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div>
                <h2>Add New Category</h2>
                <form id="add-category-form" action="manage-categories.php?<?php echo $redirect_query_string; ?>" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Category Name</label><input type="text" name="category_name" name="category_name"  required />
                    </div>
                    <div class="form-group">
                        <label>Category Image</label>
                        <input type="file" id="category-image-input" name="category_image" accept=".jpg, .jpeg, .png" required />
                        <span id="image-error-message" class="form-error"></span>
                    </div>
                    <button class="btn btn-primary" type="submit" name="add_category">Add Category</button>
                </form>
            </div>
        </section>

        <section class="content-card category-layout" id="subcategory-section">
            <div>
                <h2>Subcategory List</h2>
                <?php if (!empty($sub_message)): ?><div class="message success"><?php echo htmlspecialchars($sub_message); ?></div><?php endif; ?>
                <?php if (!empty($sub_error)): ?><div class="message error"><?php echo htmlspecialchars($sub_error); ?></div><?php endif; ?>
                <form action="manage-categories.php#subcategory-section" method="GET" class="filter-bar">
                    <div class="search-group"><input type="text" name="subcat_search" placeholder="Search subcategories..." value="<?php echo htmlspecialchars($subcat_search); ?>" style="width: 100%;"></div>
                    <select name="filter_parent_cat" onchange="this.form.submit()">
                        <option value="">Filter by Parent...</option>
                        <?php
                            $all_cats_result = $conn->query("SELECT category_id, category_name FROM category ORDER BY category_name ASC");
                            while($cat_row = $all_cats_result->fetch_assoc()) {
                                $selected = ($filter_parent_cat == $cat_row['category_id']) ? 'selected' : '';
                                echo "<option value='{$cat_row['category_id']}' {$selected}>" . htmlspecialchars($cat_row['category_name']) . "</option>";
                            }
                        ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-secondary">Go</button>
                    <?php if (!empty($subcat_search) || $filter_parent_cat > 0): ?>
                        <a href="manage-categories.php?<?php echo http_build_query(array_merge($query_params, ['subcat_search' => '', 'filter_parent_cat' => ''])); ?>#subcategory-section" class="btn btn-sm btn-primary">Clear</a>
                    <?php endif; ?>
                </form>
                <table class="data-table">
                    <thead><tr><th>Subcategory</th><th>Parent Category</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($subcategories)): ?><tr><td colspan="3" style="text-align: center;">No subcategories found.</td></tr>
                        <?php else: foreach ($subcategories as $subcategory): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subcategory['subcategory_name']); ?></td>
                                <td><?php echo htmlspecialchars($subcategory['category_name']); ?></td>
                                <td>
                                    <a href="edit-subcategory.php?id=<?php echo $subcategory['subcategory_id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                    <a class="btn btn-sm btn-primary" href="manage-categories.php?delete_subcat=<?php echo $subcategory['subcategory_id']; ?>&<?php echo $redirect_query_string; ?>#subcategory-section" onclick="return confirm('Are you sure?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div>
                <h2>Add New Subcategory</h2>
                <form action="manage-categories.php?<?php echo $redirect_query_string; ?>#subcategory-section" method="POST">
                    <div class="form-group"><label>Subcategory Name</label><input type="text" name="subcategory_name" required /></div>
                    <div class="form-group"><label>Parent Category</label>
                        <select name="parent_category" required>
                            <option value="" disabled selected>Select a parent category</option>
                            <?php
                                $all_cats_result->data_seek(0);
                                while($cat_row = $all_cats_result->fetch_assoc()) {
                                    echo "<option value='{$cat_row['category_id']}'>" . htmlspecialchars($cat_row['category_name']) . "</option>";
                                }
                                $all_cats_result->close();
                                $conn->close();
                            ?>
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit" name="add_subcategory">Add Subcategory</button>
                </form>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const categoryForm = document.getElementById('add-category-form');
            const imageInput = document.getElementById('category-image-input');
            const errorMessageSpan = document.getElementById('image-error-message');
            const allowedExtensions = /\.(jpg|jpeg|png)$/i; // Regular expression for allowed extensions

            /**
             * A reusable function to validate the image file.
             * Returns true if valid, false if invalid.
             */
            function validateImageFile() {
                const file = imageInput.files[0];
                if (file) {
                    const fileName = file.name;
                    if (!allowedExtensions.test(fileName)) {
                        errorMessageSpan.textContent = 'Invalid file type. Please select a JPG, JPEG, or PNG file.';
                        imageInput.value = ''; // Clear the invalid file selection
                        return false;
                    }
                }
                errorMessageSpan.textContent = ''; // Clear any previous error message
                return true;
            }

            // --- RUNTIME VALIDATION ---
            // Listen for the 'change' event on the file input.
            // This fires immediately after the user selects a file.
            imageInput.addEventListener('change', function() {
                validateImageFile();
            });

            // --- SUBMIT-TIME VALIDATION ---
            // Listen for the 'submit' event on the form.
            // This is a final check before the form is sent to the server.
            categoryForm.addEventListener('submit', function(event) {
                // If the validation function returns false, prevent the form submission.
                if (!validateImageFile()) {
                    event.preventDefault();
                }
            });
        });
    </script>
</body>
</html>