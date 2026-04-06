<?php
session_start();
include "../config.php";

// --- 1. SECURITY AND AUTHENTICATION ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ../seller-login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
// (Role check code is omitted for brevity)

// --- 2. ADVANCED FILTER HANDLING ---
$period = $_GET['period'] ?? 'monthly';
$selected_year = $_GET['year'] ?? date('Y');
$selected_shop_id = $_GET['shop_id'] ?? 'all';
$selected_category_id = $_GET['category_id'] ?? 'all';
$selected_subcategory_id = $_GET['subcategory_id'] ?? 'all';

// --- 3. FETCH DATA FOR FILTER DROPDOWNS ---
$stmt_shops = $conn->prepare("SELECT shop_id, shop_name FROM shops WHERE user_id = ? AND shop_status = 'active'");
$stmt_shops->bind_param("i", $user_id);
$stmt_shops->execute();
$shops_result = $stmt_shops->get_result();
$categories_result = $conn->query("SELECT category_id, category_name FROM category ORDER BY category_name");
$first_year_query = $conn->query("SELECT MIN(YEAR(o.order_date)) as first_year FROM orders o JOIN order_items oi ON o.order_id = oi.order_id JOIN products p ON oi.product_id = p.product_id JOIN shops s ON p.shop_id = s.shop_id WHERE s.user_id = $user_id");
$first_year = $first_year_query->fetch_assoc()['first_year'] ?? date('Y');
$current_year = date('Y');

// --- 4. BUILD DYNAMIC QUERY COMPONENTS ---
$base_query_from = "FROM order_items oi JOIN products p ON oi.product_id = p.product_id JOIN shops s ON p.shop_id = s.shop_id JOIN orders o ON oi.order_id = o.order_id";
$base_query_where = " WHERE s.user_id = ? AND o.status = 'Delivered' ";
$where_clauses = [];
$param_types = "i";
$param_values = [$user_id];
if ($selected_shop_id !== 'all') {
    $where_clauses[] = "s.shop_id = ?";
    $param_types .= "i";
    $param_values[] = (int)$selected_shop_id;
}
if ($selected_category_id !== 'all') {
    $where_clauses[] = "p.category_id = ?";
    $param_types .= "i";
    $param_values[] = (int)$selected_category_id;
}
if ($selected_subcategory_id !== 'all') {
    $where_clauses[] = "p.subcategory_id = ?";
    $param_types .= "i";
    $param_values[] = (int)$selected_subcategory_id;
}

// --- 5. ADVANCED DATA FETCHING & PROCESSING FOR CHARTS ---
$chart_labels_json = '[]';
$chart_datasets_json = '[]';
$chart_colors = [
    'rgba(54, 162, 235, 0.8)',
    'rgba(255, 99, 132, 0.8)',
    'rgba(75, 192, 192, 0.8)',
    'rgba(255, 206, 86, 0.8)'
];

switch ($period) {
    case 'yearly_comparison':
        $years_to_compare = [date('Y'), date('Y')-1, date('Y')-2, date('Y')-3];
        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $chart_labels_json = json_encode($labels);

        $yearlyData = [];
        foreach ($years_to_compare as $year) {
            $yearlyData[$year] = array_fill(1, 12, 0);
        }

        $where_clauses[] = "YEAR(o.order_date) IN (?, ?, ?, ?)";
        $param_types .= "iiii";
        array_push($param_values, ...$years_to_compare);

        $final_where = $base_query_where . " AND " . implode(" AND ", $where_clauses);
        $stmt_graph = $conn->prepare("SELECT YEAR(o.order_date) as year, MONTH(o.order_date) as month_num, SUM(oi.price * oi.quantity) as revenue $base_query_from $final_where GROUP BY year, month_num");
        $stmt_graph->bind_param($param_types, ...$param_values);
        $stmt_graph->execute();
        $result = $stmt_graph->get_result();
        while($row = $result->fetch_assoc()) {
            $yearlyData[$row['year']][$row['month_num']] = $row['revenue'];
        }

        $datasets = [];
        foreach ($years_to_compare as $i => $year) {
            $datasets[] = [
                'label' => $year,
                'data' => array_values($yearlyData[$year]),
                'backgroundColor' => $chart_colors[$i]
            ];
        }
        $chart_datasets_json = json_encode($datasets);
        break;

    case 'weekly_comparison':
        $labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $chart_labels_json = json_encode($labels);

        $end_of_last_week = new DateTime('last Sunday');
        $start_of_period = (clone $end_of_last_week)->modify('-27 days'); // 4 weeks total

        $weeklyData = [];
        $weeks = [];
        for ($i=0; $i<4; $i++) {
            $week_start_date = (clone $end_of_last_week)->modify('-' . (27 - $i*7) . ' days');
            $weeks[] = $week_start_date->format('Y-m-d');
            $weeklyData[$week_start_date->format('Y-m-d')] = array_fill(1, 7, 0);
        }

        $where_clauses[] = "o.order_date BETWEEN ? AND ?";
        $param_types .= "ss";
        $param_values[] = $start_of_period->format('Y-m-d');
        $param_values[] = $end_of_last_week->format('Y-m-d');

        $final_where = $base_query_where . " AND " . implode(" AND ", $where_clauses);
        $stmt_graph = $conn->prepare("SELECT o.order_date, DAYOFWEEK(o.order_date) as day_num, SUM(oi.price * oi.quantity) as revenue $base_query_from $final_where GROUP BY o.order_date");
        $stmt_graph->bind_param($param_types, ...$param_values);
        $stmt_graph->execute();
        $result = $stmt_graph->get_result();

        while($row = $result->fetch_assoc()) {
            $order_date = new DateTime($row['order_date']);
            $day_of_week = $order_date->format('N'); // 1 (for Monday) through 7 (for Sunday)
            $week_start_str = (clone $order_date)->modify('last monday')->format('Y-m-d');
            if(isset($weeklyData[$week_start_str])) {
                $weeklyData[$week_start_str][$day_of_week] += $row['revenue'];
            }
        }

        $datasets = [];
        foreach ($weeks as $i => $week_start_str) {
            $datasets[] = [
                'label' => 'Week of ' . (new DateTime($week_start_str))->format('M d'),
                'data' => array_values($weeklyData[$week_start_str]),
                'backgroundColor' => $chart_colors[$i]
            ];
        }
        $chart_datasets_json = json_encode($datasets);
        break;

    case 'monthly':
    default:
        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $chart_labels_json = json_encode($labels);
        $monthly_revenue = array_fill_keys($labels, 0);

        $where_clauses[] = "YEAR(o.order_date) = ?";
        $param_types .= "i";
        $param_values[] = (int)$selected_year;

        $final_where = $base_query_where . " AND " . implode(" AND ", $where_clauses);
        $stmt_graph = $conn->prepare("SELECT MONTH(o.order_date) as month_num, SUM(oi.price * oi.quantity) as revenue $base_query_from $final_where GROUP BY month_num");
        $stmt_graph->bind_param($param_types, ...$param_values);
        $stmt_graph->execute();
        $result = $stmt_graph->get_result();
        while($row = $result->fetch_assoc()) {
            $monthly_revenue[date('M', mktime(0,0,0,$row['month_num']))] = $row['revenue'];
        }

        $datasets[] = [
            'label' => "Revenue for $selected_year",
            'data' => array_values($monthly_revenue),
            'backgroundColor' => $chart_colors[0]
        ];
        $chart_datasets_json = json_encode($datasets);
        break;
}

// --- SUMMARY & TOP PRODUCTS ---
$stmt_summary = $conn->prepare("SELECT COALESCE(SUM(oi.price * oi.quantity), 0) as total_revenue, COUNT(DISTINCT oi.order_id) as total_orders, COALESCE(SUM(oi.quantity), 0) as total_items_sold $base_query_from $final_where");
$stmt_summary->bind_param($param_types, ...$param_values);
$stmt_summary->execute();
$summary = $stmt_summary->get_result()->fetch_assoc();
$stmt_summary->close();

$stmt_top_products = $conn->prepare("SELECT p.product_name, s.shop_name, SUM(oi.quantity) as total_units_sold, SUM(oi.price * oi.quantity) as total_revenue $base_query_from $final_where GROUP BY p.product_id ORDER BY total_units_sold DESC LIMIT 10");
$stmt_top_products->bind_param($param_types, ...$param_values);
$stmt_top_products->execute();
$top_products_result = $stmt_top_products->get_result();
$stmt_top_products->close();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Comparative Sales Report | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .filter-bar{background-color:#fff;padding:15px 20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.05);margin-bottom:25px}
        .filter-bar form{display:flex;flex-wrap:wrap;gap:15px;align-items:flex-end}
        .filter-group{display:flex;flex-direction:column;flex-grow:1;min-width:150px}
        .filter-group label{font-size:.8em;font-weight:600;color:#777;margin-bottom:5px}
        .filter-group select,.filter-group input{width:100%;box-sizing:border-box;padding:10px;border-radius:6px;border:1px solid #ddd;background-color:#f9f9f9;font-size:.9em}
        .clear-button{padding:10px 20px;font-size:.9em;font-weight:600;border-radius:6px;text-decoration:none;display:inline-block;text-align:center;background-color:#6c757d;color:#fff}
        .card-container.report-grid .card{display:flex;align-items:center;background:linear-gradient(135deg,#f8f9fa,#fff);border-left:5px solid var(--primary);padding:20px}
        .card-container.report-grid .card i{font-size:2.5em;color:var(--primary);opacity:.7;margin-right:20px}
        .card-container.report-grid .card p{font-size:1.8em;font-weight:700}
        .content-grid{display:grid;grid-template-columns:1fr;gap:25px;margin-top:25px}
        @media (min-width:992px){.content-grid{grid-template-columns:3fr 2fr}}
        .report-section{background:#fff;padding:20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.05)}
        .report-section h2{margin-top:0;border-bottom:1px solid #eee;padding-bottom:10px;margin-bottom:20px;font-size:1.2em}
        .chart-header{display:flex;justify-content:space-between;align-items:center}
        .chart-toggle{display:flex;border:1px solid #ddd;border-radius:6px;overflow:hidden}
        .chart-toggle button{background:#f9f9f9;border:none;padding:5px 12px;cursor:pointer;color:#666;font-size:.8em}
        .chart-toggle button.active{background:var(--primary);color:#fff;font-weight:600}
        .chart-wrapper{position:relative;height:400px}
        .top-products-table{width:100%;border-collapse:collapse}
        .top-products-table th,.top-products-table td{padding:12px 10px;text-align:left;border-bottom:1px solid #f0f0f0}
        .top-products-table th{font-size:.8em;font-weight:600;text-transform:uppercase;color:#888}
    </style>
</head>
<body>
    <div class="sidebar">
        <?php require "sidebar.php"; ?>
    </div>
    <div class="main">
        <div class="header">
            <h1>Comparative Sales Report</h1>
        </div>
        <div class="filter-bar">
            <form id="sales-report-form" action="sales-report.php" method="GET">
                <div class="filter-group">
                    <label for="period">Report View</label>
                    <select name="period" id="period-select" class="auto-submit">
                        <option value="monthly" <?php if($period == 'monthly') echo 'selected'; ?>>Monthly (Single Year)</option>
                        <option value="yearly_comparison" <?php if($period == 'yearly_comparison') echo 'selected'; ?>>Yearly Comparison (Last 4 Years)</option>
                        <option value="weekly_comparison" <?php if($period == 'weekly_comparison') echo 'selected'; ?>>Weekly Comparison (Last 4 Weeks)</option>
                    </select>
                </div>
                <div class="filter-group" id="year-filter-group">
                    <label for="year">Select Year</label>
                    <select name="year" id="year" class="auto-submit">
                        <?php for ($y = $current_year; $y >= $first_year; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php if($selected_year == $y) echo 'selected'; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="shop_id">Shop</label>
                    <select name="shop_id" class="auto-submit">
                        <option value="all">All Shops</option>
                        <?php while ($shop = $shops_result->fetch_assoc()): ?>
                        <option value="<?php echo $shop['shop_id']; ?>" <?php if($selected_shop_id == $shop['shop_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($shop['shop_name']); ?>
                        </option>
                        <?php endwhile; $shops_result->data_seek(0); ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="category_id">Category</label>
                    <select name="category_id" id="category_id" class="auto-submit">
                        <option value="all">All Categories</option>
                        <?php while ($category = $categories_result->fetch_assoc()): ?>
                        <option value="<?php echo $category['category_id']; ?>" <?php if($selected_category_id == $category['category_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </option>
                        <?php endwhile; $categories_result->data_seek(0); ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="subcategory_id">Subcategory</label>
                    <select name="subcategory_id" id="subcategory_id" class="auto-submit">
                        <option value="all">All</option>
                    </select>
                </div>
                <a href="sales-report.php" class="clear-button"><i class="fas fa-times"></i> Clear</a>
            </form>
        </div>
        <div class="card-container report-grid">
            <div class="card">
                <i class="fas fa-wallet"></i>
                <div>
                    <h3>Total Revenue (Filtered)</h3>
                    <p>₹<?php echo number_format($summary['total_revenue'], 2); ?></p>
                </div>
            </div>
            <div class="card">
                <i class="fas fa-receipt"></i>
                <div>
                    <h3>Total Orders (Filtered)</h3>
                    <p><?php echo $summary['total_orders']; ?></p>
                </div>
            </div>
            <div class="card">
                <i class="fas fa-box-open"></i>
                <div>
                    <h3>Items Sold (Filtered)</h3>
                    <p><?php echo $summary['total_items_sold']; ?></p>
                </div>
            </div>
        </div>
        <div class="content-grid">
            <div class="report-section chart-container">
                <div class="chart-header">
                    <h2>Revenue Analytics</h2>
                    <div class="chart-toggle">
                        <button id="barChartBtn" class="active"><i class="fas fa-chart-bar"></i> Bar</button>
                        <button id="lineChartBtn"><i class="fas fa-chart-line"></i> Line</button>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
            <div class="report-section top-products-container">
                <h2>Top Selling Products (Filtered)</h2>
                <table class="top-products-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Units Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($top_products_result->num_rows > 0): while ($product = $top_products_result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($product['product_name']); ?><br>
                                <small style="color:#888;"><?php echo htmlspecialchars($product['shop_name']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($product['total_units_sold']); ?></td>
                            <td>₹<?php echo htmlspecialchars(number_format($product['total_revenue'], 2)); ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 20px;">No sales data found for the selected criteria.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- LIVE FILTERING ---
        document.querySelectorAll('.auto-submit').forEach(el => el.addEventListener('change', () => document.getElementById('sales-report-form').submit()));

        // --- CONDITIONAL FILTER VISIBILITY ---
        const periodSelect = document.getElementById('period-select');
        const yearFilter = document.getElementById('year-filter-group');
        function toggleFilters() {
            if (periodSelect.value === 'monthly') {
                yearFilter.style.display = 'block';
            } else {
                yearFilter.style.display = 'none';
            }
        }
        toggleFilters();
        periodSelect.addEventListener('change', toggleFilters);

        // --- DYNAMIC SUBCATEGORY ---
        const categorySelect = document.getElementById('category_id');
        const subcategorySelect = document.getElementById('subcategory_id');
        function fetchSubcategories(categoryId) {
            if (!categoryId || categoryId === 'all') {
                subcategorySelect.innerHTML = '<option value="all">All</option>';
                return;
            }
            fetch(`get_subcategories.php?category_id=${categoryId}`).then(r => r.json()).then(data => {
                let opts = '<option value="all">All</option>';
                const selectedSubId = '<?php echo $selected_subcategory_id; ?>';
                data.forEach(sub => {
                    opts += `<option value="${sub.subcategory_id}" ${sub.subcategory_id == selectedSubId ? 'selected' : ''}>${sub.subcategory_name}</option>`;
                });
                subcategorySelect.innerHTML = opts;
            });
        }
        if (categorySelect.value) {
            fetchSubcategories(categorySelect.value);
        }
        categorySelect.addEventListener('change', function() {
            fetchSubcategories(this.value);
        });

        // --- CHART.JS ---
        const ctx = document.getElementById('salesChart').getContext('2d');
        let salesChart;
        function createChart(type) {
            if (salesChart) { salesChart.destroy(); }
            salesChart = new Chart(ctx, {
                type: type,
                data: { labels: <?php echo $chart_labels_json; ?>, datasets: <?php echo $chart_datasets_json; ?> },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, ticks: { callback: value => '₹' + value.toLocaleString() } }, x: { barPercentage: 0.8, categoryPercentage: 0.7 } },
                    plugins: { tooltip: { callbacks: { label: context => ` ${context.dataset.label}: ₹${context.parsed.y.toLocaleString()}` } } }
                }
            });
        }
        createChart('bar');
        const barBtn = document.getElementById('barChartBtn');
        const lineBtn = document.getElementById('lineChartBtn');
        barBtn.addEventListener('click', () => { createChart('bar'); barBtn.classList.add('active'); lineBtn.classList.remove('active'); });
        lineBtn.addEventListener('click', () => { createChart('line'); lineBtn.classList.add('active'); barBtn.classList.remove('active'); });
    });
    </script>
</body>
</html>
<?php $conn->close(); ?>