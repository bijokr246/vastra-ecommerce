<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    exit('Access Denied');
}

// Include Composer's autoloader
require '../vendor/autoload.php'; // Adjust path if your vendor folder is elsewhere
include "../config.php";

use Dompdf\Dompdf;
use Dompdf\Options;

// --- Get Data (Same logic as analytics page) ---
$selected_year = $_POST['year'] ?? date('Y');
$chart_image_data = $_POST['chart_image_data'] ?? '';

// Summary Data
$total_revenue = $conn->query("SELECT SUM(p.price) as total FROM seller_subscriptions s JOIN subscription_plans p ON s.plan_id = p.plan_id WHERE s.razorpay_payment_id IS NOT NULL")->fetch_assoc()['total'] ?? 0;
$new_this_month = $conn->query("SELECT COUNT(*) as total FROM seller_subscriptions WHERE YEAR(start_date) = YEAR(CURDATE()) AND MONTH(start_date) = MONTH(CURDATE())")->fetch_assoc()['total'];
$expiring_soon = $conn->query("SELECT COUNT(*) as total FROM seller_subscriptions WHERE status = 'active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 10 DAY)")->fetch_assoc()['total'];

// Top Plans Data
$top_plans_result = $conn->query(
    "SELECT p.plan_name, COUNT(s.subscription_id) as total_subscriptions, SUM(p.price) as total_revenue
     FROM seller_subscriptions s
     JOIN subscription_plans p ON s.plan_id = p.plan_id
     WHERE s.razorpay_payment_id IS NOT NULL GROUP BY p.plan_name ORDER BY total_revenue DESC"
);
$conn->close();

// --- Build HTML for PDF ---
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Subscription Analytics Report</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h1 { text-align: center; color: #333; }
        h2 { border-bottom: 1px solid #ccc; padding-bottom: 5px; }
        .summary-table, .plans-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .summary-table td, .plans-table th, .plans-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .plans-table th { background-color: #f2f2f2; }
        .chart { text-align: center; margin-bottom: 20px; }
        .chart img { max-width: 100%; }
    </style>
</head>
<body>
    <h1>Vastra - Subscription Analytics Report</h1>
    <p style="text-align:center;"><strong>Report for Year: ' . $selected_year . '</strong></p>

    <h2>Summary</h2>
    <table class="summary-table">
        <tr>
            <td><strong>Total Subscription Revenue:</strong> Rs. ' . number_format($total_revenue, 2) . '</td>
            <td><strong>New Subscriptions This Month:</strong> ' . $new_this_month . '</td>
            <td><strong>Subscriptions Expiring Soon:</strong> ' . $expiring_soon . '</td>
        </tr>
    </table>';

if (!empty($chart_image_data)) {
    $html .= '
    <h2>Monthly Revenue by Plan (' . $selected_year . ')</h2>
    <div class="chart">
        <img src="' . $chart_image_data . '">
    </div>';
}

$html .= '
    <h2>Top Performing Plans (All Time)</h2>
    <table class="plans-table">
        <thead>
            <tr>
                <th>Plan Name</th>
                <th>Subscriptions Sold</th>
                <th>Total Revenue</th>
            </tr>
        </thead>
        <tbody>';

if ($top_plans_result->num_rows > 0) {
    while ($row = $top_plans_result->fetch_assoc()) {
        $html .= '
            <tr>
                <td>' . htmlspecialchars($row['plan_name']) . '</td>
                <td>' . $row['total_subscriptions'] . '</td>
                <td>Rs. ' . number_format($row['total_revenue'], 2) . '</td>
            </tr>';
    }
} else {
    $html .= '<tr><td colspan="3" style="text-align:center;">No data available.</td></tr>';
}

$html .= '
        </tbody>
    </table>
</body>
</html>';

// --- Instantiate and use the dompdf class ---
$options = new Options();
$options->set('isRemoteEnabled', true); // Important for loading images
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// (Optional) Setup the paper size and orientation
$dompdf->setPaper('A4', 'portrait');

// Render the HTML as PDF
$dompdf->render();

// Output the generated PDF to Browser
$filename = 'Vastra_Subscription_Report_' . $selected_year . '.pdf';
$dompdf->stream($filename, ["Attachment" => true]); // true to download, false to view in browser