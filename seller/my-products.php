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

// --- 2. GET ALL FILTER/SORT PARAMETERS FROM URL ---
$search_query = isset($_GET['search_query']) ? trim($_GET['search_query']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$filter_category = isset($_GET['filter_category']) ? (int)$_GET['filter_category'] : '';
$filter_subcategory = isset($_GET['filter_subcategory']) ? (int)$_GET['filter_subcategory'] : '';
$filter_shop = isset($_GET['filter_shop']) ? (int)$_GET['filter_shop'] : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? $_GET['min_price'] : '';
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? $_GET['max_price'] : '';

// Build a query string to preserve filter state on redirects
$query_params = array_filter([
    'search_query' => $search_query,
    'sort' => $sort,
    'filter_category' => $filter_category,
    'filter_subcategory' => $filter_subcategory,
    'filter_shop' => $filter_shop,
    'filter_status' => $filter_status,
    'min_price' => $min_price,
    'max_price' => $max_price,
]);
$redirect_query_string = http_build_query($query_params);

// Check if any advanced filters are active
$is_advanced_filter_active = !empty($filter_category) || !empty($filter_subcategory) || !empty($filter_shop) || !empty($filter_status) || $min_price !== '' || $max_price !== '';

// --- 3. FETCH DATA FOR FILTER DROPDOWNS ---
$categories = $conn->query("SELECT category_id, category_name FROM category ORDER BY category_name ASC")->fetch_all(MYSQLI_ASSOC);
$seller_shops_stmt = $conn->prepare("SELECT shop_id, shop_name FROM shops WHERE user_id = ? AND shop_status = 'active' ORDER BY shop_name ASC");
$seller_shops_stmt->bind_param("i", $user_id);
$seller_shops_stmt->execute();
$seller_shops = $seller_shops_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$has_multiple_shops = count($seller_shops) > 1;

// --- 4. BUILD AND EXECUTE THE MAIN PRODUCT QUERY (WITH RATING DATA) ---
$low_stock_threshold = 5;
$sql = "
    SELECT
        p.product_id, p.product_name, p.price, p.quantity_available, p.product_image, p.status,
        c.category_name, sc.subcategory_name, s.shop_name,
        pr.avg_rating, pr.review_count
    FROM products p
    JOIN shops s ON p.shop_id = s.shop_id
    JOIN category c ON p.category_id = c.category_id
    LEFT JOIN subcategory sc ON p.subcategory_id = sc.subcategory_id
    LEFT JOIN (
        SELECT
            product_id,
            AVG(rating) as avg_rating,
            COUNT(review_id) as review_count
        FROM product_reviews
        WHERE status = 'approved'
        GROUP BY product_id
    ) pr ON p.product_id = pr.product_id
";

$where_clauses = ["s.user_id = ?"];
$params = [$user_id];
$types = 'i';

if (!empty($search_query)) {
    $where_clauses[] = "(p.product_name LIKE ? OR c.category_name LIKE ? OR sc.subcategory_name LIKE ?)";
    $search_param = "%" . $search_query . "%";
    array_push($params, $search_param, $search_param, $search_param);
    $types .= 'sss';
}
if (!empty($filter_category)) { $where_clauses[] = "p.category_id = ?"; $params[] = $filter_category; $types .= 'i'; }
if (!empty($filter_subcategory)) { $where_clauses[] = "p.subcategory_id = ?"; $params[] = $filter_subcategory; $types .= 'i'; }
if (!empty($filter_shop)) { $where_clauses[] = "p.shop_id = ?"; $params[] = $filter_shop; $types .= 'i'; }
// UPDATED: Handle new filter status
if (!empty($filter_status)) {
    if ($filter_status === 'out_of_stock') {
        $where_clauses[] = "p.quantity_available <= 0";
    } elseif ($filter_status === 'low_stock') {
        $where_clauses[] = "p.quantity_available < ?";
        $params[] = $low_stock_threshold;
        $types .= 'i';
    } else {
        $where_clauses[] = "p.status = ?";
        $params[] = $filter_status;
        $types .= 's';
    }
}
if ($min_price !== '') { $where_clauses[] = "p.price >= ?"; $params[] = $min_price; $types .= 'd'; }
if ($max_price !== '') { $where_clauses[] = "p.price <= ?"; $params[] = $max_price; $types .= 'd'; }

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

switch ($sort) {
    case 'oldest': $sql .= " ORDER BY p.created_at ASC"; break;
    case 'alpha_asc': $sql .= " ORDER BY p.product_name ASC"; break;
    case 'alpha_desc': $sql .= " ORDER BY p.product_name DESC"; break;
    case 'newest': default: $sql .= " ORDER BY p.created_at DESC"; break;
}

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products_result = $stmt->get_result();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Products | Vastra</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <script src="../refresh.js"></script>
  <style>
    /* Add new style for low-stock status */
    .status.low-stock { background: #f1c40f; color: #333; }
    /* Existing Styles */
    .success-message, .error-message { padding: 15px; margin-bottom: 20px; border-radius: 4px; border: 1px solid transparent; }
    .success-message { background-color: #dff0d8; color: #3c763d; border-color: #d6e9c6; }
    .error-message { background-color: #f2dede; color: #a94442; border-color: #ebccd1; }

    .filter-container { padding: 20px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 20px; }
    .top-bar { display: grid; grid-template-columns: 1fr auto auto; gap: 15px; align-items: flex-end; }
    .top-bar .form-group { margin: 0; }
    .top-bar label { display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 600; color: #495057; }
    .top-bar select { width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; }
    .input-with-icon { display: flex; align-items: center; }
    .input-with-icon input { flex-grow: 1; border-top-right-radius: 0; border-bottom-right-radius: 0; border-right: none; padding: 8px 12px; border: 1px solid #ced4da; }
    .input-with-icon .icon-btn { padding: 8px 12px; border: 1px solid #ced4da; background-color: #e9ecef; border-top-left-radius: 0; border-bottom-left-radius: 0; cursor: pointer; line-height: 1.5; }
    #toggle-filters-btn { padding: 9px 15px; }
    .advanced-filters { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-out, margin-top 0.4s ease-out; display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; align-items: center; }
    .advanced-filters.show { max-height: 500px; margin-top: 20px; }
    .advanced-filters .form-group { display: flex; flex-direction: column; }
    .advanced-filters label { font-size: 0.85rem; margin-bottom: 5px; font-weight: 600; color: #495057; }
    .advanced-filters select, .advanced-filters input { width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 0.9rem; }
    .advanced-filters .price-range { display: flex; gap: 10px; }
    .advanced-filters .actions { grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;}
    .btn.btn-sm { font-size: 13px; padding: 5px 10px; }

    /* --- RATING & REVIEW STYLES --- */
    .rating-info { font-size: 0.9rem; text-align: center; min-width: 120px; }
    .avg-rating { display: block; font-weight: bold; font-size: 1.1rem; color: #f5b301; margin-bottom: 5px; }
    .review-link { display: inline-block; font-size: 12px; font-weight: 600; color: #fff; background-color: #007bff; padding: 4px 10px; border-radius: 20px; text-decoration: none; transition: background-color 0.2s ease, transform 0.2s ease; }
    .review-link:hover { background-color: #0056b3; transform: translateY(-1px); text-decoration: none; color: #fff; }
    .review-link .fa-eye { margin-right: 4px; }
    .no-reviews { font-size: 0.8rem; color: #6c757d; font-style: italic; }
  </style>
</head>
<body>
  <div class="sidebar"><?php require "sidebar.php"; ?></div>

  <div class="main">
    <div class="header">
      <h1>My Products</h1>
      <a href="add-product.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Product</a>
    </div>
    <div class="content">
      <?php if (isset($_GET['success'])): ?>
          <p class="success-message">Action completed successfully!</p>
      <?php endif; ?>

      <form action="my-products.php" method="GET" class="filter-container">
          <div class="top-bar">
              <div class="form-group search-group">
                  <label for="search_query">Search:</label>
                  <div class="input-with-icon">
                      <input type="text" name="search_query" id="search_query" placeholder="Product, Category..." value="<?php echo htmlspecialchars($search_query); ?>">
                      <button type="submit" class="icon-btn" title="Search"><i class="fas fa-search"></i></button>
                  </div>
              </div>
              <div class="form-group">
                  <label for="sort">Sort By:</label>
                  <select name="sort" id="sort" onchange="this.form.submit()">
                      <option value="newest" <?php if ($sort == 'newest') echo 'selected'; ?>>Newest First</option>
                      <option value="oldest" <?php if ($sort == 'oldest') echo 'selected'; ?>>Oldest First</option>
                      <option value="alpha_asc" <?php if ($sort == 'alpha_asc') echo 'selected'; ?>>Name (A-Z)</option>
                      <option value="alpha_desc" <?php if ($sort == 'alpha_desc') echo 'selected'; ?>>Name (Z-A)</option>
                  </select>
              </div>
              <button type="button" id="toggle-filters-btn" class="btn btn-secondary" title="Toggle Advanced Filters">
                  <i class="fas fa-filter"></i><span class="btn-text"> Filter</span>
              </button>
          </div>

          <div class="advanced-filters <?php if ($is_advanced_filter_active) echo 'show'; ?>" id="advanced-filters-content">
              <?php if ($has_multiple_shops): ?>
              <div class="form-group">
                  <label for="filter_shop">Shop:</label>
                  <select name="filter_shop" id="filter_shop">
                      <option value="">All My Shops</option>
                      <?php foreach ($seller_shops as $shop): ?>
                          <option value="<?php echo $shop['shop_id']; ?>" <?php if ($filter_shop == $shop['shop_id']) echo 'selected'; ?>><?php echo htmlspecialchars($shop['shop_name']); ?></option>
                      <?php endforeach; ?>
                  </select>
              </div>
              <?php endif; ?>
              <div class="form-group">
                  <label for="filter_category">Category:</label>
                  <select name="filter_category" id="filter_category">
                      <option value="">All Categories</option>
                      <?php foreach ($categories as $cat): ?>
                          <option value="<?php echo $cat['category_id']; ?>" <?php if ($filter_category == $cat['category_id']) echo 'selected'; ?>><?php echo htmlspecialchars($cat['category_name']); ?></option>
                      <?php endforeach; ?>
                  </select>
              </div>
              <div class="form-group">
                  <label for="filter_subcategory">Subcategory:</label>
                  <select name="filter_subcategory" id="filter_subcategory">
                      <option value="">All Subcategories</option>
                      <!-- Populated by JavaScript -->
                  </select>
              </div>
              <div class="form-group">
                  <label for="filter_status">Status:</label>
                  <!-- UPDATED: Added new Low Stock option -->
                  <select name="filter_status" id="filter_status">
                      <option value="">All Statuses</option>
                      <option value="active" <?php if ($filter_status == 'active') echo 'selected'; ?>>Active</option>
                      <option value="inactive" <?php if ($filter_status == 'inactive') echo 'selected'; ?>>Inactive</option>
                      <option value="out_of_stock" <?php if ($filter_status == 'out_of_stock') echo 'selected'; ?>>Out of Stock</option>
                      <option value="low_stock" <?php if ($filter_status == 'low_stock') echo 'selected'; ?>>Low Stock (&lt; 5)</option>
                  </select>
              </div>
              <div class="form-group price-range">
                  <div>
                      <label for="min_price">Min Price:</label>
                      <input type="number" name="min_price" id="min_price" placeholder="e.g., 100" value="<?php echo htmlspecialchars($min_price); ?>" step="0.01">
                  </div>
                  <div>
                      <label for="max_price">Max Price:</label>
                      <input type="number" name="max_price" id="max_price" placeholder="e.g., 5000" value="<?php echo htmlspecialchars($max_price); ?>" step="0.01">
                  </div>
              </div>
              <div class="actions">
                  <a href="my-products.php" class="btn btn-secondary">Clear All</a>
                  <button type="submit" class="btn btn-primary">Apply Filters</button>
              </div>
          </div>
      </form>

      <table>
        <thead>
          <tr>
            <th>Image</th><th>Product</th><?php if ($has_multiple_shops) echo '<th>Shop</th>'; ?><th>Category</th><th>Subcategory</th><th>Price</th><th>Stock</th><th>Rating</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($products_result && $products_result->num_rows > 0): ?>
            <?php while ($product = $products_result->fetch_assoc()): ?>
              <tr>
                <td><img src="../images/<?php echo htmlspecialchars($product['product_image']); ?>" class="product-img"></td>
                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                <?php if ($has_multiple_shops): ?><td><?php echo htmlspecialchars($product['shop_name']); ?></td><?php endif; ?>
                <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                <td><?php echo htmlspecialchars($product['subcategory_name'] ?? 'N/A'); ?></td>
                <td>₹<?php echo htmlspecialchars(number_format($product['price'])); ?></td>
                <td><?php echo htmlspecialchars($product['quantity_available']); ?></td>
                <td>
                    <?php if ($product['review_count'] > 0): ?>
                        <div class="rating-info">
                            <span class="avg-rating">
                                <i class="fas fa-star"></i> <?php echo number_format($product['avg_rating'], 1); ?>
                            </span>
                            <a href="view-reviews.php?id=<?php echo $product['product_id']; ?>" class="review-link" title="View Reviews">
                                <i class="fas fa-eye"></i> View (<?php echo $product['review_count']; ?>)
                            </a>
                        </div>
                    <?php else: ?>
                        <span class="no-reviews">No reviews yet</span>
                    <?php endif; ?>
                </td>
                <td>
                  <?php
                    // UPDATED: Logic to determine status text and class
                    $status_class = strtolower($product['status']);
                    $status_text = ucfirst($product['status']);
                    if ($product['status'] === 'active') {
                        if ($product['quantity_available'] <= 0) {
                            $status_class = 'out-of-stock';
                            $status_text = 'Out of Stock';
                        } elseif ($product['quantity_available'] < $low_stock_threshold) {
                            $status_class = 'low-stock';
                            $status_text = 'Low Stock';
                        }
                    }
                  ?>
                  <span class="status <?php echo $status_class; ?>">
                    <?php echo $status_text; ?>
                  </span>
                </td>
                <td class="actions">
                  <a href="edit-product.php?id=<?php echo $product['product_id']; ?>" title="Edit" class="btn btn-sm btn-secondary"><i class="fas fa-edit"></i></a>
                  <a href="delete-product.php?id=<?php echo $product['product_id']; ?>&<?php echo $redirect_query_string; ?>" title="Delete" onclick="return confirm('Are you sure?');" class="btn btn-sm btn-danger"><i class="fas fa-trash-alt"></i></a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="<?php echo $has_multiple_shops ? '10' : '9'; ?>" style="text-align: center;">No products found.</td></tr>
          <?php endif; $stmt->close(); $conn->close(); ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
      document.addEventListener('DOMContentLoaded', function() {
          const categorySelect = document.getElementById('filter_category');
          const subcategorySelect = document.getElementById('filter_subcategory');
          const initialSubcategoryId = '<?php echo $filter_subcategory; ?>';

          function updateSubcategories(categoryId) {
              if (!categoryId) {
                  subcategorySelect.innerHTML = '<option value="">All Subcategories</option>';
                  return;
              }
              
              fetch(`get_subcategories.php?category_id=${categoryId}`)
                  .then(response => response.json())
                  .then(data => {
                      subcategorySelect.innerHTML = '<option value="">All Subcategories</option>';
                      data.forEach(subcategory => {
                          const option = document.createElement('option');
                          option.value = subcategory.subcategory_id;
                          option.textContent = subcategory.subcategory_name;
                          if (subcategory.subcategory_id == initialSubcategoryId) {
                              option.selected = true;
                          }
                          subcategorySelect.appendChild(option);
                      });
                  })
                  .catch(error => console.error('Error fetching subcategories:', error));
          }

          categorySelect.addEventListener('change', function() {
              updateSubcategories(this.value);
          });
          
          if (categorySelect.value) {
            updateSubcategories(categorySelect.value);
          }
      });
      
      document.getElementById('toggle-filters-btn').addEventListener('click', function() {
          document.getElementById('advanced-filters-content').classList.toggle('show');
      });
  </script>
</body>
</html>