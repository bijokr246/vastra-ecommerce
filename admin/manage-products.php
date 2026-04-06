<?php
// 1. START SESSION AND CHECK FOR ADMIN LOGIN
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// 2. INCLUDE DATABASE CONNECTION
include "../config.php";

// Get all filter/sort parameters from URL
$search_query = isset($_GET['search_query']) ? trim($_GET['search_query']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$filter_category = isset($_GET['filter_category']) ? (int)$_GET['filter_category'] : '';
$filter_subcategory = isset($_GET['filter_subcategory']) ? (int)$_GET['filter_subcategory'] : '';
$filter_shop = isset($_GET['filter_shop']) ? (int)$_GET['filter_shop'] : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? $_GET['min_price'] : '';
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? $_GET['max_price'] : '';
$filter_rating = isset($_GET['filter_rating']) ? $_GET['filter_rating'] : ''; // New rating filter

// Build a query string to preserve state on redirects
$query_params = array_filter(['search_query' => $search_query, 'sort' => $sort, 'filter_category' => $filter_category, 'filter_subcategory' => $filter_subcategory, 'filter_shop' => $filter_shop, 'filter_status' => $filter_status, 'min_price' => $min_price, 'max_price' => $max_price, 'filter_rating' => $filter_rating]);
$redirect_query_string = http_build_query($query_params);
$is_advanced_filter_active = !empty($filter_category) || !empty($filter_subcategory) || !empty($filter_shop) || !empty($filter_status) || $min_price !== '' || $max_price !== '' || !empty($filter_rating);

// 3. HANDLE ACTIONS (ACTIVATE/BAN PRODUCT)
if (isset($_GET['action']) && isset($_GET['product_id'])) {
    $action = $_GET['action'];
    $product_id = (int)$_GET['product_id'];
    if ($action === 'activate' || $action === 'deactivate') {
        $new_status = ($action === 'activate') ? 'active' : 'banned';
        $stmt_action = $conn->prepare("UPDATE products SET status = ? WHERE product_id = ?");
        $stmt_action->bind_param("si", $new_status, $product_id);
        $stmt_action->execute();
        $stmt_action->close();
        header("Location: manage-products.php?" . $redirect_query_string);
        exit();
    }
}

// Fetch data for filter dropdowns
$categories = $conn->query("SELECT category_id, category_name FROM category ORDER BY category_name ASC")->fetch_all(MYSQLI_ASSOC);
$shops = $conn->query("SELECT shop_id, shop_name FROM shops WHERE shop_status = 'active' ORDER BY shop_name ASC")->fetch_all(MYSQLI_ASSOC);

// 4. FETCH PRODUCTS (Main Query with Reports & Ratings)
$sql = "SELECT 
            p.product_id, p.product_name, p.price, p.status, 
            c.category_name, sub.subcategory_name, s.shop_name,
            COALESCE(reports.report_count, 0) as report_count,
            reviews.avg_rating,
            reviews.review_count
        FROM products p 
        JOIN category c ON p.category_id = c.category_id 
        JOIN subcategory sub ON p.subcategory_id = sub.subcategory_id 
        JOIN shops s ON p.shop_id = s.shop_id
        LEFT JOIN (
            SELECT product_id, COUNT(report_id) as report_count 
            FROM product_reports 
            GROUP BY product_id
        ) reports ON p.product_id = reports.product_id
        LEFT JOIN (
            SELECT product_id, AVG(rating) as avg_rating, COUNT(review_id) as review_count
            FROM product_reviews
            WHERE status = 'approved'
            GROUP BY product_id
        ) reviews ON p.product_id = reviews.product_id";

$where_clauses = []; $params = []; $types = '';
if (!empty($search_query)) { $where_clauses[] = "(p.product_name LIKE ? OR c.category_name LIKE ? OR s.shop_name LIKE ?)"; $search_param = "%" . $search_query . "%"; array_push($params, $search_param, $search_param, $search_param); $types .= 'sss'; }
if (!empty($filter_category)) { $where_clauses[] = "p.category_id = ?"; $params[] = $filter_category; $types .= 'i'; }
if (!empty($filter_subcategory)) { $where_clauses[] = "p.subcategory_id = ?"; $params[] = $filter_subcategory; $types .= 'i'; }
if (!empty($filter_shop)) { $where_clauses[] = "p.shop_id = ?"; $params[] = $filter_shop; $types .= 'i'; }
if (!empty($filter_status)) { $where_clauses[] = "p.status = ?"; $params[] = $filter_status; $types .= 's'; }
if ($min_price !== '') { $where_clauses[] = "p.price >= ?"; $params[] = $min_price; $types .= 'd'; }
if ($max_price !== '') { $where_clauses[] = "p.price <= ?"; $params[] = $max_price; $types .= 'd'; }

// New Rating Filter Logic
if (!empty($filter_rating)) {
    switch ($filter_rating) {
        case 'excellent': $where_clauses[] = "reviews.avg_rating >= 4.5"; break;
        case 'good': $where_clauses[] = "reviews.avg_rating >= 3.5 AND reviews.avg_rating < 4.5"; break;
        case 'average': $where_clauses[] = "reviews.avg_rating >= 2 AND reviews.avg_rating < 3.5"; break;
        case 'poor': $where_clauses[] = "reviews.avg_rating < 2"; break;
        case 'not_rated': $where_clauses[] = "reviews.review_count IS NULL"; break;
    }
}

if (!empty($where_clauses)) { $sql .= " WHERE " . implode(" AND ", $where_clauses); }
switch ($sort) { case 'oldest': $sql .= " ORDER BY p.product_id ASC"; break; case 'price_asc': $sql .= " ORDER BY p.price ASC"; break; case 'price_desc': $sql .= " ORDER BY p.price DESC"; break; case 'name_asc': $sql .= " ORDER BY p.product_name ASC"; break; case 'name_desc': $sql .= " ORDER BY p.product_name DESC"; break; case 'newest': default: $sql .= " ORDER BY p.product_id DESC"; break; }

$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$products_result = $stmt->get_result();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Products | Vastra Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="../refresh.js"></script>
    <style>
        .filter-container {
            padding: 20px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .top-bar {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 15px;
            align-items: flex-end;
        }

        .top-bar .form-group {
            margin: 0;
        }

        .top-bar label {
            display: block;
            font-size: 0.85rem;
            margin-bottom: 5px;
            font-weight: 600;
            color: #495057;
        }

        .top-bar select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }

        .input-with-icon {
            display: flex;
            align-items: center;
        }

        .input-with-icon input {
            flex-grow: 1;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
            border-right: none;
            padding: 8px 12px;
            border: 1px solid #ced4da;
        }

        .input-with-icon .icon-btn {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            background-color: #e9ecef;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            cursor: pointer;
            line-height: 1.5;
        }

        #toggle-filters-btn {
            padding: 9px 15px;
        }

        .advanced-filters {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-out, margin-top 0.4s ease-out;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .advanced-filters.show {
            max-height: 500px;
            margin-top: 20px;
        }

        .advanced-filters .form-group {
            display: flex;
            flex-direction: column;
        }

        .advanced-filters label {
            font-size: 0.85rem;
            margin-bottom: 5px;
            font-weight: 600;
            color: #495057;
        }

        .advanced-filters select,
        .advanced-filters input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .advanced-filters .price-range {
            grid-column: span 2;
            display: flex;
            gap: 10px;
        }

        .advanced-filters .actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .rating-display {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 150px;
        }

        .rating-bar-container {
            flex-grow: 1;
            height: 10px;
            background-color: #e9ecef;
            border-radius: 5px;
            overflow: hidden;
        }

        .rating-bar {
            height: 100%;
            border-radius: 5px;
            transition: width 0.5s ease-in-out;
        }

        .rating-display span {
            font-weight: bold;
            font-size: 0.9em;
        }

        .review-count-link {
            font-size: 0.8em;
            color: #007bff;
            text-decoration: none;
        }

        .review-count-link:hover {
            text-decoration: underline;
        }

        .no-data {
            color: #6c757d;
            font-style: italic;
            text-align: center;
        }

        .bg-success {
            background-color: #28a745;
        }

        .bg-warning {
            background-color: #ffc107;
        }

        .bg-danger {
            background-color: #dc3545;
        }
    </style>
</head>

<body>
    <aside class="sidebar">
        <?php require "header.php" ?>
    </aside>

    <main class="main-content">
        <h1 class="page-title">Product Management</h1>
        <section class="content-card">
            <div class="page-header">
                <h2>All Products</h2>
            </div>
            <form action="manage-products.php" method="GET" class="filter-container">
                <div class="top-bar">
                    <div class="form-group search-group"><label for="search_query">Search:</label>
                        <div class="input-with-icon"><input type="text" name="search_query" id="search_query"
                                placeholder="Product, Category, Shop..."
                                value="<?php echo htmlspecialchars($search_query); ?>"><button type="submit"
                                class="icon-btn" title="Search"><i class="fas fa-search"></i></button></div>
                    </div>
                    <div class="form-group"><label for="sort">Sort By:</label><select name="sort" id="sort"
                            onchange="this.form.submit()">
                            <option value="newest" <?php if ($sort=='newest' ) echo 'selected' ; ?>>Newest First
                            </option>
                            <option value="oldest" <?php if ($sort=='oldest' ) echo 'selected' ; ?>>Oldest First
                            </option>
                            <option value="price_asc" <?php if ($sort=='price_asc' ) echo 'selected' ; ?>>Price: Low to
                                High</option>
                            <option value="price_desc" <?php if ($sort=='price_desc' ) echo 'selected' ; ?>>Price: High
                                to Low</option>
                            <option value="name_asc" <?php if ($sort=='name_asc' ) echo 'selected' ; ?>>Name (A-Z)
                            </option>
                            <option value="name_desc" <?php if ($sort=='name_desc' ) echo 'selected' ; ?>>Name (Z-A)
                            </option>
                        </select></div>
                    <button type="button" id="toggle-filters-btn" class="btn btn-secondary"
                        title="Toggle Advanced Filters"><i class="fas fa-filter"></i><span
                            class="btn-text">Filter</span></button>
                </div>
                <div class="advanced-filters <?php if ($is_advanced_filter_active) echo 'show'; ?>"
                    id="advanced-filters-content">
                    <div class="form-group"><label for="filter_category">Category:</label><select name="filter_category"
                            id="filter_category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>" <?php if
                                ($filter_category==$cat['category_id']) echo 'selected' ; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select></div>
                    <div class="form-group"><label for="filter_subcategory">Subcategory:</label><select
                            name="filter_subcategory" id="filter_subcategory">
                            <option value="">All Subcategories</option>
                        </select></div>
                    <div class="form-group">
                        <label for="filter_shop">Shop:</label><select name="filter_shop" id="filter_shop">
                            <option value="">All Shops</option>
                            <?php foreach ($shops as $shop): ?>
                            <option value="<?php echo $shop['shop_id']; ?>" <?php if ($filter_shop==$shop['shop_id'])
                                echo 'selected' ; ?>>
                                <?php echo htmlspecialchars($shop['shop_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="filter_status">Status:</label><select name="filter_status" id="filter_status">
                            <option value="">All Statuses</option>
                            <option value="active" <?php if ($filter_status=='active' ) echo 'selected' ; ?>>Active
                            </option>
                            <option value="banned" <?php if ($filter_status=='banned' ) echo 'selected' ; ?>>Banned
                            </option>
                            <option value="out-of-stock" <?php if ($filter_status=='out-of-stock' ) echo 'selected' ; ?>
                                >Out of Stock</option>
                            <option value="inactive" <?php if ($filter_status=='inactive' ) echo 'selected' ; ?>
                                >Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="filter_rating">Rating:</label><select name="filter_rating" id="filter_rating">
                            <option value="">Any Rating</option>
                            <option value="excellent" <?php if ($filter_rating=='excellent' ) echo 'selected' ; ?>
                                >Excellent (90-100%)</option>
                            <option value="good" <?php if ($filter_rating=='good' ) echo 'selected' ; ?>>Good (70-89%)
                            </option>
                            <option value="average" <?php if ($filter_rating=='average' ) echo 'selected' ; ?>>Average
                                (40-69%)</option>
                            <option value="poor" <?php if ($filter_rating=='poor' ) echo 'selected' ; ?>>Poor (&lt;40%)
                            </option>
                            <option value="not_rated" <?php if ($filter_rating=='not_rated' ) echo 'selected' ; ?>>Not
                                Rated</option>
                        </select>
                    </div>
                    <div class="form-group price-range">
                        <div>
                            <label for="min_price">Min Price:</label><input type="number" name="min_price"
                                id="min_price" placeholder="e.g., 100"
                                value="<?php echo htmlspecialchars($min_price); ?>" step="0.01">
                        </div>
                        <div>
                            <label for="max_price">Max Price:</label><input type="number" name="max_price"
                                id="max_price" placeholder="e.g., 5000"
                                value="<?php echo htmlspecialchars($max_price); ?>" step="0.01">
                        </div>
                    </div>
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">Apply Filters</button><a
                            href="manage-products.php" class="btn btn-secondary">Clear All</a>
                    </div>
                </div>
            </form>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product Name</th>
                        <th>Seller (Shop)</th>
                        <th>Category</th>
                        <th>Reports</th>
                        <th>Rating</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($products_result && $products_result->num_rows > 0): ?>
                    <?php while ($product = $products_result->fetch_assoc()): ?>
                    <tr>
                        <td>#
                            <?php echo htmlspecialchars($product['product_id']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($product['product_name']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($product['shop_name']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($product['category_name']); ?>
                        </td>
                        <td
                            style="text-align: center; <?php echo ($product['report_count'] > 0) ? 'font-weight: bold; color: #dc3545;' : ''; ?>">
                            <?php echo $product['report_count']; ?>
                        </td>
                        <td>
                            <?php if (isset($product['review_count']) && $product['review_count'] > 0): ?>
                            <?php $rating_percentage = ($product['avg_rating'] / 5) * 100; $bar_color = 'success'; if ($rating_percentage < 70) $bar_color = 'warning'; if ($rating_percentage < 40) $bar_color = 'danger'; ?>
                            <div class="rating-display">
                                <div class="rating-bar-container">
                                    <div class="rating-bar bg-<?php echo $bar_color; ?>"
                                        style="width: <?php echo $rating_percentage; ?>%;"></div>
                                </div>
                                <span>
                                    <?php echo number_format($rating_percentage, 0); ?>%
                                </span>
                                <a href="view-reviews.php?id=<?php echo $product['product_id']; ?>"
                                    class="review-count-link" title="View Reviews">(
                                    <?php echo $product['review_count']; ?>)
                                </a>
                            </div>
                            <?php else: ?>
                            <div class="no-data">N/A</div>
                            <?php endif; ?>
                        </td>
                        <td>₹
                            <?php echo number_format($product['price'], 2); ?>
                        </td>
                        <td><span class="status <?php echo htmlspecialchars($product['status']); ?>">
                                <?php echo htmlspecialchars(ucfirst(str_replace('-', ' ', $product['status']))); ?>
                            </span></td>
                        <td class="action-buttons">
                            <?php $action_link_base = 'manage-products.php?' . $redirect_query_string . '&'; ?>
                            <?php if ($product['status'] === 'active' || $product['status'] === 'out-of-stock'): ?><a
                                class="btn btn-sm btn-danger"
                                href="<?php echo $action_link_base; ?>action=deactivate&product_id=<?php echo $product['product_id']; ?>"
                                onclick="return confirm('Are you sure you want to BAN this product?');">Ban</a>
                            <?php elseif ($product['status'] === 'banned' || $product['status'] === 'inactive'): ?><a
                                class="btn btn-sm btn-success"
                                href="<?php echo $action_link_base; ?>action=activate&product_id=<?php echo $product['product_id']; ?>"
                                onclick="return confirm('Are you sure you want to ACTIVATE this product?');">Activate</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align:center;">No products found matching your criteria.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const categorySelect = document.getElementById('filter_category');
            const subcategorySelect = document.getElementById('filter_subcategory');
            const initialSubcategoryId = '<?php echo $filter_subcategory; ?>';
            function updateSubcategories(categoryId) {
                subcategorySelect.innerHTML = '<option value="">All Subcategories</option>';
                if (!categoryId) return;
                fetch(`get_subcategories.php?category_id=${categoryId}`)
                    .then(response => response.json()).then(data => {
                        data.forEach(subcategory => {
                            const option = document.createElement('option');
                            option.value = subcategory.subcategory_id;
                            option.textContent = subcategory.subcategory_name;
                            if (subcategory.subcategory_id == initialSubcategoryId) { option.selected = true; }
                            subcategorySelect.appendChild(option);
                        });
                    }).catch(error => console.error('Error fetching subcategories:', error));
            }
            categorySelect.addEventListener('change', function () { updateSubcategories(this.value); });
            updateSubcategories(categorySelect.value);
        });
        document.getElementById('toggle-filters-btn').addEventListener('click', function () { document.getElementById('advanced-filters-content').classList.toggle('show'); });
    </script>
</body>

</html>