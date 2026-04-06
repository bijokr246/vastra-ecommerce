<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}
include "../config.php";

// --- Filter Handling ---
$selected_year = $_GET['year'] ?? date('Y');
$first_year_query = $conn->query("SELECT MIN(YEAR(start_date)) as first_year FROM seller_subscriptions");
$first_year = $first_year_query->fetch_assoc()['first_year'] ?? date('Y');
$current_year = date('Y');

// --- Fetch Data for Summary Cards ---
$total_revenue = $conn->query("SELECT SUM(p.price) as total FROM seller_subscriptions s JOIN subscription_plans p ON s.plan_id = p.plan_id WHERE s.razorpay_payment_id IS NOT NULL")->fetch_assoc()['total'] ?? 0;
$new_this_month = $conn->query("SELECT COUNT(*) as total FROM seller_subscriptions WHERE YEAR(start_date) = YEAR(CURDATE()) AND MONTH(start_date) = MONTH(CURDATE())")->fetch_assoc()['total'];
$expiring_soon = $conn->query("SELECT COUNT(*) as total FROM seller_subscriptions WHERE status = 'active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 10 DAY)")->fetch_assoc()['total'];
$expired = $conn->query("SELECT COUNT(*) as total FROM seller_subscriptions WHERE status = 'expired'")->fetch_assoc()['total'];

// --- Fetch Data for Chart ---
$chart_data_query = $conn->prepare(
    "SELECT p.plan_name, MONTH(s.start_date) as month_num, SUM(p.price) as monthly_revenue
     FROM seller_subscriptions s
     JOIN subscription_plans p ON s.plan_id = p.plan_id
     WHERE YEAR(s.start_date) = ? AND s.razorpay_payment_id IS NOT NULL
     GROUP BY p.plan_name, month_num
     ORDER BY month_num"
);
$chart_data_query->bind_param("i", $selected_year);
$chart_data_query->execute();
$result = $chart_data_query->get_result();

$plan_revenues = [];
while ($row = $result->fetch_assoc()) {
    $plan_revenues[$row['plan_name']][$row['month_num']] = $row['monthly_revenue'];
}

$labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$datasets = [];
$colors = ['rgba(54, 162, 235, 0.7)', 'rgba(255, 99, 132, 0.7)', 'rgba(75, 192, 192, 0.7)', 'rgba(255, 206, 86, 0.7)'];
$i = 0;
foreach ($plan_revenues as $plan_name => $monthly_data) {
    $data_points = [];
    for ($m = 1; $m <= 12; $m++) {
        $data_points[] = $monthly_data[$m] ?? 0;
    }
    $datasets[] = [
        'label' => $plan_name,
        'data' => $data_points,
        'backgroundColor' => $colors[$i % count($colors)],
        'borderColor' => str_replace('0.7', '1', $colors[$i % count($colors)]),
        'borderWidth' => 1
    ];
    $i++;
}
$chart_labels_json = json_encode($labels);
$chart_datasets_json = json_encode($datasets);

// --- Fetch Data for Top Plans Table ---
$top_plans_query = $conn->query(
    "SELECT p.plan_name, COUNT(s.subscription_id) as total_subscriptions, SUM(p.price) as total_revenue
     FROM seller_subscriptions s
     JOIN subscription_plans p ON s.plan_id = p.plan_id
     WHERE s.razorpay_payment_id IS NOT NULL
     GROUP BY p.plan_name
     ORDER BY total_revenue DESC"
);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Subscription Analytics | Vastra Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .filter-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .filter-bar form { display: flex; gap: 15px; align-items: center; }
        .cards-grid { grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .content-grid { display: grid; grid-template-columns: 1fr; gap: 25px; margin-top: 25px; }
        @media (min-width: 992px) { .content-grid { grid-template-columns: 3fr 2fr; } }
        .report-section { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .chart-toggle button { background: #f0f0f0; border: 1px solid #ddd; padding: 5px 12px; cursor: pointer; }
        .chart-toggle button.active { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }
        .chart-wrapper { position: relative; height: 400px; }
    </style>
</head>
<body>
<aside class="sidebar"><?php require "header.php"; ?></aside>
<div class="main-content">
    <header class="header"><h1>Subscription Analytics</h1></header>
    <main>
        <div class="filter-bar">
            <form id="analytics-form" method="GET">
                <label for="year">Year:</label>
                <select name="year" id="year" onchange="this.form.submit()">
                    <?php for ($y = $current_year; $y >= $first_year; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php if($selected_year == $y) echo 'selected'; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </form>
            <form id="pdf-download-form" action="generate-subscription-pdf.php" method="POST" target="_blank">
                <input type="hidden" name="year" value="<?php echo htmlspecialchars($selected_year); ?>">
                <input type="hidden" name="chart_image_data" id="chart-image-data">
                <button type="submit" class="btn btn-primary"><i class="fas fa-file-pdf"></i> Download Report</button>
            </form>
        </div>

        <div class="cards-grid">
            <div class="stat-card"><div class="icon"><i class="fas fa-wallet"></i></div><div class="info"><h3>Total Revenue</h3><p>₹<?php echo number_format($total_revenue, 2); ?></p></div></div>
            <div class="stat-card"><div class="icon"><i class="fas fa-user-plus"></i></div><div class="info"><h3>New This Month</h3><p><?php echo $new_this_month; ?></p></div></div>
            <div class="stat-card"><div class="icon"><i class="fas fa-hourglass-half"></i></div><div class="info"><h3>Expiring Soon</h3><p><?php echo $expiring_soon; ?></p></div></div>
            <div class="stat-card"><div class="icon"><i class="fas fa-user-clock"></i></div><div class="info"><h3>Total Expired</h3><p><?php echo $expired; ?></p></div></div>
        </div>

        <div class="content-grid">
            <div class="report-section">
                <div class="chart-header">
                    <h2>Monthly Revenue by Plan (<?php echo $selected_year; ?>)</h2>
                    <div class="chart-toggle">
                        <button id="barChartBtn" class="active"><i class="fas fa-chart-bar"></i> Bar</button>
                        <button id="lineChartBtn"><i class="fas fa-chart-line"></i> Line</button>
                    </div>
                </div>
                <div class="chart-wrapper"><canvas id="revenueChart"></canvas></div>
            </div>
            <div class="report-section">
                <h2>Top Performing Plans (All Time)</h2>
                <table class="data-table">
                    <thead><tr><th>Plan Name</th><th>Subscriptions</th><th>Total Revenue</th></tr></thead>
                    <tbody>
                        <?php if ($top_plans_query->num_rows > 0): while($plan = $top_plans_query->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($plan['plan_name']); ?></td>
                            <td><?php echo $plan['total_subscriptions']; ?></td>
                            <td>₹<?php echo number_format($plan['total_revenue'], 2); ?></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="3" style="text-align:center;">No subscription data found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    let revenueChart;

    function createChart(type) {
        if (revenueChart) revenueChart.destroy();
        revenueChart = new Chart(ctx, {
            type: type,
            data: { labels: <?php echo $chart_labels_json; ?>, datasets: <?php echo $chart_datasets_json; ?> },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { callback: v => '₹' + v.toLocaleString() } } },
                plugins: { tooltip: { callbacks: { label: c => ` ${c.dataset.label}: ₹${c.parsed.y.toLocaleString()}` } } }
            }
        });
    }
    createChart('bar');

    const barBtn = document.getElementById('barChartBtn');
    const lineBtn = document.getElementById('lineChartBtn');
    barBtn.addEventListener('click', () => { createChart('bar'); barBtn.classList.add('active'); lineBtn.classList.remove('active'); });
    lineBtn.addEventListener('click', () => { createChart('line'); lineBtn.classList.add('active'); barBtn.classList.remove('active'); });

    document.getElementById('pdf-download-form').addEventListener('submit', function() {
        document.getElementById('chart-image-data').value = revenueChart.toBase64Image('image/png', 1.0);
    });
});
</script>
</body>
</html>