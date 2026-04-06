<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}
include "../config.php";

// --- 1. ADVANCED FILTER HANDLING ---
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$seller_id = $_GET['seller_id'] ?? 'all';
$shop_id = $_GET['shop_id'] ?? 'all';
$category_id = $_GET['category_id'] ?? 'all';
$subcategory_id = $_GET['subcategory_id'] ?? 'all';

// --- 2. FETCH DATA FOR FILTER DROPDOWNS ---
$sellers = $conn->query("SELECT u.user_id, CONCAT(u.fname, ' ', u.lname) as name FROM users u JOIN login l ON u.user_id = l.user_id WHERE l.role = 'seller' ORDER BY u.fname")->fetch_all(MYSQLI_ASSOC);
$shops = $conn->query("SELECT shop_id, shop_name FROM shops ORDER BY shop_name")->fetch_all(MYSQLI_ASSOC);
$categories = $conn->query("SELECT category_id, category_name FROM category ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);

// --- 3. DYNAMIC QUERY BUILDING ---
$base_from = "FROM orders o JOIN order_items oi ON o.order_id = oi.order_id JOIN products p ON oi.product_id = p.product_id JOIN shops s ON p.shop_id = s.shop_id JOIN users u ON s.user_id = u.user_id LEFT JOIN category c ON p.category_id = c.category_id LEFT JOIN subcategory sc ON p.subcategory_id = sc.subcategory_id";
$where_clauses = ["o.status = 'Delivered'", "o.order_date BETWEEN ? AND ?"];
$param_types = "ss";
$params = [$date_from, $date_to . ' 23:59:59'];

if ($seller_id !== 'all') { $where_clauses[] = "u.user_id = ?"; $param_types .= "i"; $params[] = (int)$seller_id; }
if ($shop_id !== 'all') { $where_clauses[] = "s.shop_id = ?"; $param_types .= "i"; $params[] = (int)$shop_id; }
if ($category_id !== 'all') { $where_clauses[] = "p.category_id = ?"; $param_types .= "i"; $params[] = (int)$category_id; }
if ($subcategory_id !== 'all') { $where_clauses[] = "p.subcategory_id = ?"; $param_types .= "i"; $params[] = (int)$subcategory_id; }
$final_where = " WHERE " . implode(" AND ", $where_clauses);

// --- 4. FETCH DATA FOR REPORTS & CHART ---
$chart_sql = "SELECT DATE(o.order_date) as sales_date, SUM(oi.price * oi.quantity) as daily_revenue {$base_from} {$final_where} GROUP BY sales_date ORDER BY sales_date ASC";
$stmt_chart = $conn->prepare($chart_sql); $stmt_chart->bind_param($param_types, ...$params); $stmt_chart->execute();
$chart_result = $stmt_chart->get_result();
$chart_labels = []; $chart_data = [];
while ($row = $chart_result->fetch_assoc()) {
    $chart_labels[] = date('M d', strtotime($row['sales_date']));
    $chart_data[] = $row['daily_revenue'];
}
$shop_sql = "SELECT s.shop_name, COUNT(DISTINCT o.order_id) as order_count, SUM(oi.price * oi.quantity) as total_revenue {$base_from} {$final_where} GROUP BY s.shop_id, s.shop_name ORDER BY total_revenue DESC";
$stmt_shop = $conn->prepare($shop_sql); $stmt_shop->bind_param($param_types, ...$params); $stmt_shop->execute();
$shop_report = $stmt_shop->get_result()->fetch_all(MYSQLI_ASSOC);
$seller_sql = "SELECT CONCAT(u.fname, ' ', u.lname) AS seller_name, COUNT(DISTINCT o.order_id) as order_count, SUM(oi.price * oi.quantity) as total_revenue {$base_from} {$final_where} GROUP BY u.user_id, seller_name ORDER BY total_revenue DESC";
$stmt_seller = $conn->prepare($seller_sql); $stmt_seller->bind_param($param_types, ...$params); $stmt_seller->execute();
$seller_report = $stmt_seller->get_result()->fetch_all(MYSQLI_ASSOC);
$cat_sql = "SELECT c.category_name, SUM(oi.quantity) as items_sold, SUM(oi.price * oi.quantity) as total_revenue {$base_from} {$final_where} GROUP BY c.category_id, c.category_name ORDER BY total_revenue DESC";
$stmt_cat = $conn->prepare($cat_sql); $stmt_cat->bind_param($param_types, ...$params); $stmt_cat->execute();
$category_report = $stmt_cat->get_result()->fetch_all(MYSQLI_ASSOC);
$prod_sql = "SELECT p.product_name, s.shop_name, SUM(oi.quantity) as units_sold, SUM(oi.price * oi.quantity) as total_revenue {$base_from} {$final_where} GROUP BY p.product_id, p.product_name, s.shop_name ORDER BY total_revenue DESC LIMIT 20";
$stmt_prod = $conn->prepare($prod_sql); $stmt_prod->bind_param($param_types, ...$params); $stmt_prod->execute();
$product_report = $stmt_prod->get_result()->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Sales Reports | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .filter-bar { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,.05); margin-bottom: 25px; }
        .filter-bar .filters { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; min-width: 180px;}
        .filter-group label { font-size: 0.8em; font-weight: 600; color: #777; margin-bottom: 5px; }
        .filter-group input, .filter-group select { padding: 8px; border-radius: 4px; border: 1px solid #ddd; }
        .actions { display: flex; gap: 10px; align-items: flex-end; margin-top: 15px; }
        .tabs { display: flex; border-bottom: 2px solid #eee; margin-bottom: 20px; }
        .tab-link { padding: 10px 20px; cursor: pointer; border: none; background: none; font-size: 1rem; color: #555; position: relative; }
        .tab-link.active { font-weight: 600; color: var(--primary-color); }
        .tab-link.active::after { content: ''; position: absolute; bottom: -2px; left: 0; width: 100%; height: 2px; background: var(--primary-color); }
        .tab-content { display: none; } .tab-content.active { display: block; }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .chart-toggle button { background: #f0f0f0; border: 1px solid #ddd; padding: 5px 12px; cursor: pointer; }
        .chart-toggle button.active { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }
        .chart-wrapper { position: relative; height: 350px; background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 25px; }
        .select2-container { width: 100% !important; }
        .select2-selection__rendered { line-height: 35px !important; }
        .select2-selection { height: 37px !important; }
        .select2-selection__arrow { height: 35px !important; }
    </style>
</head>
<body>
<aside class="sidebar"><?php require "header.php"; ?></aside>
<div class="main-content">
    <header class="header"><h1>Platform Sales Reports</h1></header>
    <main>
        <div class="filter-bar">
            <form id="filter-form" method="GET">
                <div class="filters">
                    <div class="filter-group"><label>From Date</label><input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"></div>
                    <div class="filter-group"><label>To Date</label><input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"></div>
                    <div class="filter-group"><label>Seller</label><select name="seller_id" class="searchable-select">
                        <option value="all">All Sellers</option>
                        <?php foreach($sellers as $s): ?><option value="<?php echo $s['user_id']; ?>" <?php if($seller_id == $s['user_id']) echo 'selected'; ?>><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="filter-group"><label>Shop</label><select name="shop_id" class="searchable-select">
                        <option value="all">All Shops</option>
                        <?php foreach($shops as $sh): ?><option value="<?php echo $sh['shop_id']; ?>" <?php if($shop_id == $sh['shop_id']) echo 'selected'; ?>><?php echo htmlspecialchars($sh['shop_name']); ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="filter-group"><label>Category</label><select name="category_id" id="category_id">
                        <option value="all">All Categories</option>
                        <?php foreach($categories as $c): ?><option value="<?php echo $c['category_id']; ?>" <?php if($category_id == $c['category_id']) echo 'selected'; ?>><?php echo htmlspecialchars($c['category_name']); ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="filter-group"><label>Subcategory</label><select name="subcategory_id" id="subcategory_id"><option value="all">All</option></select></div>
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-sm">Apply Filters</button>
                    <a href="sales-reports.php" class="btn btn-sm btn-secondary">Clear</a>
                </div>
            </form>
            <form id="pdf-form" action="generate-sales-report-pdf.php" method="POST" target="_blank" style="margin-top: -38px; text-align: right;">
                <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                <input type="hidden" name="seller_id" value="<?php echo htmlspecialchars($seller_id); ?>">
                <input type="hidden" name="shop_id" value="<?php echo htmlspecialchars($shop_id); ?>">
                <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($category_id); ?>">
                <input type="hidden" name="subcategory_id" value="<?php echo htmlspecialchars($subcategory_id); ?>">
                <input type="hidden" name="chart_image_data" id="chart-image-data">
                <button type="submit" class="btn btn-primary"><i class="fas fa-file-pdf"></i> Download PDF</button>
            </form>
        </div>

        <div class="chart-wrapper">
            <div class="chart-header">
                <h3>Revenue Trend</h3>
                <div class="chart-toggle">
                    <button id="barChartBtn"><i class="fas fa-chart-bar"></i> Bar</button>
                    <button id="lineChartBtn" class="active"><i class="fas fa-chart-line"></i> Line</button>
                </div>
            </div>
            <canvas id="salesChart"></canvas>
        </div>

        <div class="content-card">
            <div class="tabs">
                <button class="tab-link active" onclick="openTab(event, 'by-shop')">By Shop</button>
                <button class="tab-link" onclick="openTab(event, 'by-seller')">By Seller</button>
                <button class="tab-link" onclick="openTab(event, 'by-category')">By Category</button>
                <button class="tab-link" onclick="openTab(event, 'top-products')">Top Products</button>
            </div>
            <div id="by-shop" class="tab-content active"><table class="data-table"><thead><tr><th>Shop Name</th><th>Orders</th><th>Revenue</th></tr></thead><tbody><?php if (empty($shop_report)) { echo '<tr><td colspan="3" style="text-align:center;">No data for this filter.</td></tr>'; } else { foreach($shop_report as $row): ?><tr><td><?php echo htmlspecialchars($row['shop_name']); ?></td><td><?php echo $row['order_count']; ?></td><td>₹<?php echo number_format($row['total_revenue'], 2); ?></td></tr><?php endforeach; } ?></tbody></table></div>
            <div id="by-seller" class="tab-content"><table class="data-table"><thead><tr><th>Seller Name</th><th>Orders</th><th>Revenue</th></tr></thead><tbody><?php if (empty($seller_report)) { echo '<tr><td colspan="3" style="text-align:center;">No data for this filter.</td></tr>'; } else { foreach($seller_report as $row): ?><tr><td><?php echo htmlspecialchars($row['seller_name']); ?></td><td><?php echo $row['order_count']; ?></td><td>₹<?php echo number_format($row['total_revenue'], 2); ?></td></tr><?php endforeach; } ?></tbody></table></div>
            <div id="by-category" class="tab-content"><table class="data-table"><thead><tr><th>Category</th><th>Items Sold</th><th>Revenue</th></tr></thead><tbody><?php if (empty($category_report)) { echo '<tr><td colspan="3" style="text-align:center;">No data for this filter.</td></tr>'; } else { foreach($category_report as $row): ?><tr><td><?php echo htmlspecialchars($row['category_name']); ?></td><td><?php echo $row['items_sold']; ?></td><td>₹<?php echo number_format($row['total_revenue'], 2); ?></td></tr><?php endforeach; } ?></tbody></table></div>
            <div id="top-products" class="tab-content"><table class="data-table"><thead><tr><th>Product</th><th>Shop</th><th>Units Sold</th><th>Revenue</th></tr></thead><tbody><?php if (empty($product_report)) { echo '<tr><td colspan="4" style="text-align:center;">No data for this filter.</td></tr>'; } else { foreach($product_report as $row): ?><tr><td><?php echo htmlspecialchars($row['product_name']); ?></td><td><?php echo htmlspecialchars($row['shop_name']); ?></td><td><?php echo $row['units_sold']; ?></td><td>₹<?php echo number_format($row['total_revenue'], 2); ?></td></tr><?php endforeach; } ?></tbody></table></div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
function openTab(evt, tabName) {
    document.querySelectorAll('.tab-content, .tab-link').forEach(el => el.classList.remove('active'));
    document.getElementById(tabName).classList.add('active');
    evt.currentTarget.classList.add('active');
}
document.addEventListener('DOMContentLoaded', function() {
    $('.searchable-select').select2();
    const categorySelect = document.getElementById('category_id');
    const subcategorySelect = document.getElementById('subcategory_id');
    function fetchSubcategories(categoryId, selectedSubId = 'all') {
        if (!categoryId || categoryId === 'all') { subcategorySelect.innerHTML = '<option value="all">All</option>'; return; }
        fetch(`../get_subcategories.php?category_id=${categoryId}`)
            .then(response => response.json())
            .then(data => {
                let options = '<option value="all">All</option>';
                data.forEach(sub => { options += `<option value="${sub.subcategory_id}" ${sub.subcategory_id == selectedSubId ? 'selected' : ''}>${sub.subcategory_name}</option>`; });
                subcategorySelect.innerHTML = options;
            });
    }
    categorySelect.addEventListener('change', () => fetchSubcategories(categorySelect.value));
    const initialCatId = '<?php echo $category_id; ?>';
    const initialSubId = '<?php echo $subcategory_id; ?>';
    if(initialCatId !== 'all') { fetchSubcategories(initialCatId, initialSubId); }
    
    const ctx = document.getElementById('salesChart').getContext('2d');
    let salesChart;
    function createChart(type) {
        if (salesChart) salesChart.destroy();
        salesChart = new Chart(ctx, {
            type: type,
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Daily Revenue', data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: type === 'line' ? 'rgba(238, 50, 94, 0.1)' : 'rgba(238, 50, 94, 0.7)',
                    borderColor: 'rgba(238, 50, 94, 1)',
                    borderWidth: 2, tension: 0.3, fill: type === 'line'
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { callback: v => '₹' + v.toLocaleString() } } } }
        });
    }
    
    createChart('line');

    // --- THIS IS THE CORRECTED PART ---
    const barBtn = document.getElementById('barChartBtn');
    const lineBtn = document.getElementById('lineChartBtn');
    
    barBtn.addEventListener('click', () => { 
        createChart('bar'); 
        barBtn.classList.add('active'); 
        lineBtn.classList.remove('active'); 
    });
    
    lineBtn.addEventListener('click', () => { 
        createChart('line'); 
        lineBtn.classList.add('active'); 
        barBtn.classList.remove('active'); 
    });
    // --- END OF CORRECTION ---

    document.getElementById('pdf-form').addEventListener('submit', function() {
        document.getElementById('chart-image-data').value = salesChart.toBase64Image('image/png', 1.0);
    });
});
</script>
</body>
</html>