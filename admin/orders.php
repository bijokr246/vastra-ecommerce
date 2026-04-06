<?php
// 1. START THE SESSION & CHECK FOR LOGIN
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// 2. INCLUDE DATABASE CONNECTION
include "../config.php";

// 3. GET ALL FILTER/SORT PARAMETERS FROM URL
$search_term = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$filter_status = $_GET['filter_status'] ?? '';
$filter_shop = isset($_GET['filter_shop']) ? (int)$_GET['filter_shop'] : 0;
$filter_category = isset($_GET['filter_category']) ? (int)$_GET['filter_category'] : 0;
$filter_subcategory = isset($_GET['filter_subcategory']) ? (int)$_GET['filter_subcategory'] : 0;
$filter_product = isset($_GET['filter_product']) ? (int)$_GET['filter_product'] : 0;
$min_amount = isset($_GET['min_amount']) && is_numeric($_GET['min_amount']) ? $_GET['min_amount'] : '';
$max_amount = isset($_GET['max_amount']) && is_numeric($_GET['max_amount']) ? $_GET['max_amount'] : '';

// 4. FETCH DATA FOR FILTER DROPDOWNS
$shops = $conn->query("SELECT shop_id, shop_name FROM shops ORDER BY shop_name ASC")->fetch_all(MYSQLI_ASSOC);
$categories = $conn->query("SELECT category_id, category_name FROM category ORDER BY category_name ASC")->fetch_all(MYSQLI_ASSOC);
$products = $conn->query("SELECT product_id, product_name FROM products ORDER BY product_name ASC")->fetch_all(MYSQLI_ASSOC);

// 5. BUILD THE DYNAMIC & SECURE SQL QUERY (REFACTORED)
$sql = "SELECT 
            o.order_id, 
            CONCAT(u.fname, ' ', u.lname) AS buyer_name,
            o.total_amount, 
            o.status, 
            o.order_date,
            COUNT(DISTINCT oi.item_id) AS item_count
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.product_id";

$where_clauses = [];
$params = [];
$types = '';

// Simple text search for Buyer Name or Order ID
if (!empty($search_term)) {
    $where_clauses[] = "(o.order_id = ? OR CONCAT(u.fname, ' ', u.lname) LIKE ?)";
    $like_term = "%" . $search_term . "%";
    array_push($params, $search_term, $like_term); // Order ID must be an exact match
    $types .= 'ss';
}

// Direct filters on the `orders` table
if (!empty($filter_status)) { $where_clauses[] = "o.status = ?"; $params[] = $filter_status; $types .= 's'; }
if ($min_amount !== '') { $where_clauses[] = "o.total_amount >= ?"; $params[] = $min_amount; $types .= 'd'; }
if ($max_amount !== '') { $where_clauses[] = "o.total_amount <= ?"; $params[] = $max_amount; $types .= 'd'; }

// Filters that require the JOINs
if ($filter_shop > 0) { $where_clauses[] = "p.shop_id = ?"; $params[] = $filter_shop; $types .= 'i'; }
if ($filter_category > 0) { $where_clauses[] = "p.category_id = ?"; $params[] = $filter_category; $types .= 'i'; }
if ($filter_subcategory > 0) { $where_clauses[] = "p.subcategory_id = ?"; $params[] = $filter_subcategory; $types .= 'i'; }
if ($filter_product > 0) { $where_clauses[] = "p.product_id = ?"; $params[] = $filter_product; $types .= 'i'; }

// Append WHERE clauses to the main query
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

// Add GROUP BY to ensure one row per order
$sql .= " GROUP BY o.order_id, buyer_name, o.total_amount, o.status, o.order_date";

// Add sorting
switch ($sort) {
    case 'oldest': $sql .= " ORDER BY o.order_date ASC"; break;
    case 'amount_asc': $sql .= " ORDER BY o.total_amount ASC"; break;
    case 'amount_desc': $sql .= " ORDER BY o.total_amount DESC"; break;
    case 'newest': default: $sql .= " ORDER BY o.order_date DESC"; break;
}

// Prepare, bind, and execute
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$all_orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();
$conn->close();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>All Orders | Vastra Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="../refresh.js"></script>
    <style>
        /* Your existing CSS styles can remain the same */
        .filter-container { padding: 20px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 20px; }
        .top-bar { display: grid; grid-template-columns: 1fr auto auto; gap: 15px; align-items: flex-end; }
        .top-bar .form-group { margin: 0; }
        .top-bar label { display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 600; color: #495057; }
        .top-bar select { width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; }
        .input-with-icon { display: flex; align-items: center; }
        .input-with-icon input { flex-grow: 1; border-top-right-radius: 0; border-bottom-right-radius: 0; border-right: none; padding: 8px 12px; border: 1px solid #ced4da; }
        .input-with-icon .icon-btn { padding: 8px 12px; border: 1px solid #ced4da; background-color: #e9ecef; border-top-left-radius: 0; border-bottom-left-radius: 0; cursor: pointer; line-height: 1.5; }
        #toggle-filters-btn { padding: 9px 15px; }
        .advanced-filters { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-out, margin-top 0.4s ease-out; display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; align-items: end; }
        .advanced-filters.show { max-height: 500px; margin-top: 20px; }
        .advanced-filters .form-group { display: flex; flex-direction: column; }
        .advanced-filters label { font-size: 0.85rem; margin-bottom: 5px; font-weight: 600; color: #495057; }
        .advanced-filters select, .advanced-filters input { width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 0.9rem; }
        .advanced-filters .price-range { grid-column: span 2; display: flex; gap: 10px; }
        .advanced-filters .actions { grid-column: 1 / -1; display: flex; gap: 10px; margin-top: 10px;}
    </style>
</head>
<body>
    <aside class="sidebar"><?php require "header.php" ?></aside>

    <main class="main-content">
        <h1 class="page-header">Order History</h1>
        <section class="content-card">
            <form action="orders.php" method="GET" class="filter-container">
                <div class="top-bar">
                    <div class="form-group">
                        <label for="search">Search Buyer/Order ID:</label>
                        <div class="input-with-icon">
                            <input type="text" name="search" id="search" placeholder="Search by name or order #" value="<?php echo htmlspecialchars($search_term); ?>">
                            <button type="submit" class="icon-btn" title="Search"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="sort">Sort By:</label>
                        <select name="sort" id="sort" onchange="this.form.submit()">
                            <option value="newest" <?php if ($sort == 'newest') echo 'selected'; ?>>Newest First</option>
                            <option value="oldest" <?php if ($sort == 'oldest') echo 'selected'; ?>>Oldest First</option>
                            <option value="amount_desc" <?php if ($sort == 'amount_desc') echo 'selected'; ?>>Amount: High to Low</option>
                            <option value="amount_asc" <?php if ($sort == 'amount_asc') echo 'selected'; ?>>Amount: Low to High</option>
                        </select>
                    </div>
                    <button type="button" id="toggle-filters-btn" class="btn btn-secondary"><i class="fas fa-filter"></i> More Filters</button>
                </div>

                <div class="advanced-filters" id="advanced-filters-content">
                    <!-- Dropdowns for status, shop, category, etc. are the same -->
                    <div class="form-group"><label for="filter_status">Order Status:</label><select name="filter_status" id="filter_status"><option value="">All</option><option value="Pending" <?php if($filter_status=='Pending') echo 'selected';?>>Pending</option><option value="Processing" <?php if($filter_status=='Processing') echo 'selected';?>>Processing</option><option value="Shipped" <?php if($filter_status=='Shipped') echo 'selected';?>>Shipped</option><option value="Delivered" <?php if($filter_status=='Delivered') echo 'selected';?>>Delivered</option><option value="Canceled" <?php if($filter_status=='Canceled') echo 'selected';?>>Canceled</option><option value="Returned" <?php if($filter_status=='Returned') echo 'selected';?>>Returned</option></select></div>
                    <div class="form-group"><label for="filter_shop">Shop:</label><select name="filter_shop" id="filter_shop"><option value="">All Shops</option><?php foreach($shops as $shop):?><option value="<?php echo $shop['shop_id'];?>" <?php if($filter_shop==$shop['shop_id']) echo 'selected';?>><?php echo htmlspecialchars($shop['shop_name']);?></option><?php endforeach;?></select></div>
                    <div class="form-group"><label for="filter_category">Product Category:</label><select name="filter_category" id="filter_category"><option value="">All Categories</option><?php foreach($categories as $cat):?><option value="<?php echo $cat['category_id'];?>" <?php if($filter_category==$cat['category_id']) echo 'selected';?>><?php echo htmlspecialchars($cat['category_name']);?></option><?php endforeach;?></select></div>
                    <div class="form-group"><label for="filter_subcategory">Product Subcategory:</label><select name="filter_subcategory" id="filter_subcategory"><option value="">All Subcategories</option></select></div>
                    <div class="form-group"><label for="filter_product">Specific Product:</label><select name="filter_product" id="filter_product"><option value="">All Products</option><?php foreach($products as $prod):?><option value="<?php echo $prod['product_id'];?>" <?php if($filter_product==$prod['product_id']) echo 'selected';?>><?php echo htmlspecialchars($prod['product_name']);?></option><?php endforeach;?></select></div>
                    <div class="form-group price-range">
                        <div><label for="min_amount">Min Total Amount:</label><input type="number" name="min_amount" id="min_amount" value="<?php echo htmlspecialchars($min_amount);?>" step="0.01" placeholder="e.g. 500"></div>
                        <div><label for="max_amount">Max Total Amount:</label><input type="number" name="max_amount" id="max_amount" value="<?php echo htmlspecialchars($max_amount);?>" step="0.01" placeholder="e.g. 2000"></div>
                    </div>
                    <div class="actions"><button type="submit" class="btn btn-primary">Apply Filters</button><a href="orders.php" class="btn btn-secondary">Clear All</a></div>
                </div>
            </form>

            <table class="data-table">
                <!-- Table headers and body are the same -->
                <thead><tr><th>Order ID</th><th>Buyer</th><th>Items</th><th>Amount</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($all_orders)): ?><tr><td colspan="7" style="text-align:center;">No orders found matching your criteria.</td></tr>
                    <?php else: foreach ($all_orders as $order): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($order['order_id']); ?></td>
                            <td><?php echo htmlspecialchars($order['buyer_name']); ?></td>
                            <td><?php echo htmlspecialchars($order['item_count']); ?></td>
                            <td>₹<?php echo number_format($order['total_amount'], 2); ?></td>
                            <td><span class="status <?php echo htmlspecialchars(strtolower($order['status'])); ?>"><?php echo htmlspecialchars(ucfirst($order['status'])); ?></span></td>
                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                            <td><a href="order-details.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-secondary">View Details</a></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </section>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Logic for collapsible filter bar is the same
            const toggleBtn = document.getElementById('toggle-filters-btn');
            const advancedFilters = document.getElementById('advanced-filters-content');
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('filter_status') || urlParams.get('filter_shop') || urlParams.get('filter_category') || urlParams.get('filter_product') || urlParams.get('min_amount')) {
                advancedFilters.classList.add('show');
            }
            toggleBtn.addEventListener('click', () => advancedFilters.classList.toggle('show'));

            // Dependent subcategory dropdown logic is the same, but now it uses the new get_subcategories.php file
            const categorySelect = document.getElementById('filter_category');
            const subcategorySelect = document.getElementById('filter_subcategory');
            const initialSubcategoryId = '<?php echo $filter_subcategory; ?>';

            function updateSubcategories(categoryId) {
                subcategorySelect.innerHTML = '<option value="">All Subcategories</option>';
                if (!categoryId) return; // Stop if no category is selected

                fetch(`get_subcategories.php?category_id=${categoryId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(sub => {
                            const option = document.createElement('option');
                            option.value = sub.subcategory_id;
                            option.textContent = sub.subcategory_name;
                            if (sub.subcategory_id == initialSubcategoryId) {
                                option.selected = true;
                            }
                            subcategorySelect.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error fetching subcategories:', error));
            }
            
            categorySelect.addEventListener('change', () => updateSubcategories(categorySelect.value));
            
            // Initial load
            updateSubcategories(categorySelect.value);
        });
    </script>
</body>
</html>