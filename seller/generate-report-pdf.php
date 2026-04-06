<?php
// File: /seller/generate-report-pdf.php

session_start();

// This autoloader now loads Dompdf, PHPMailer, and Razorpay
require_once dirname(__DIR__) . '/vendor/autoload.php';

// These 'use' statements are for Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

include "../config.php";

// --- 1. SECURITY AND AUTHENTICATION ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Access denied. Please log in.");
}
$user_id = $_SESSION['user_id'];

// --- 2. GET FILTERS & CHART DATA FROM POST (This part is unchanged) ---
$period = $_POST['period'] ?? 'monthly';
$selected_year = $_POST['year'] ?? date('Y');
$selected_shop_id = $_POST['shop_id'] ?? 'all';
$selected_category_id = $_POST['category_id'] ?? 'all';
$selected_subcategory_id = $_POST['subcategory_id'] ?? 'all';
$chart_image_data = $_POST['chart_image_data'] ?? '';

// --- 3. RE-RUN DATABASE QUERIES TO GET DATA (This part is unchanged) ---
$base_query_from = "FROM order_items oi JOIN products p ON oi.product_id = p.product_id JOIN shops s ON p.shop_id = s.shop_id JOIN orders o ON oi.order_id = o.order_id";
$base_query_where = " WHERE s.user_id = ? AND o.status = 'Delivered' ";
$where_clauses = [];
$param_types = "i";
$param_values = [$user_id];
if ($selected_shop_id !== 'all') { $where_clauses[] = "s.shop_id = ?"; $param_types .= "i"; $param_values[] = (int)$selected_shop_id; }
if ($selected_category_id !== 'all') { $where_clauses[] = "p.category_id = ?"; $param_types .= "i"; $param_values[] = (int)$selected_category_id; }
if ($selected_subcategory_id !== 'all') { $where_clauses[] = "p.subcategory_id = ?"; $param_types .= "i"; $param_values[] = (int)$selected_subcategory_id; }

switch ($period) {
    case 'yearly_comparison':
        $years_to_compare = [date('Y'), date('Y')-1, date('Y')-2, date('Y')-3];
        $where_clauses[] = "YEAR(o.order_date) IN (?, ?, ?, ?)";
        $param_types .= "iiii";
        array_push($param_values, ...$years_to_compare);
        break;
    case 'monthly':
    default:
        $where_clauses[] = "YEAR(o.order_date) = ?";
        $param_types .= "i";
        $param_values[] = (int)$selected_year;
        break;
}
$final_where = $base_query_where . (empty($where_clauses) ? '' : " AND " . implode(" AND ", $where_clauses));

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
$conn->close();

// --- 4. BUILD THE HTML FOR THE PDF (This part is unchanged) ---
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sales Report</title>
    <style>
        /* These styles are designed to work well with Dompdf */
        @page { margin: 20px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10px; color: #333; }
        h1 { color: #0056b3; text-align: center; border-bottom: 2px solid #0056b3; padding-bottom: 10px; }
        .summary-table, .products-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        .summary-table td { border: 1px solid #ddd; padding: 12px; text-align: center; width: 33.33%; }
        .summary-table td h3 { margin: 0 0 5px 0; font-size: 1.1em; color: #555; text-transform: uppercase; }
        .summary-table td p { margin: 0; font-size: 1.8em; font-weight: bold; color: #000; }
        h2 { font-size: 1.4em; color: #333; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-top: 30px; }
        .chart-container { text-align: center; margin-bottom: 25px; page-break-inside: avoid; }
        .chart-container img { max-width: 100%; height: auto; }
        .products-table th, .products-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .products-table th { background-color: #f2f2f2; font-weight: bold; }
        .footer { text-align: center; font-size: 0.8em; color: #777; position: fixed; bottom: -20px; left: 0px; right: 0px; height: 50px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Vastra Sales Report</h1>
        <p style="text-align:center;">Generated on: <?php echo date('F j, Y, g:i a'); ?></p>
    </div>
    <h2>Summary (Filtered)</h2>
    <table class="summary-table">
        <tr>
            <td><h3>Total Revenue</h3><p>₹<?php echo number_format($summary['total_revenue'], 2); ?></p></td>
            <td><h3>Total Orders</h3><p><?php echo $summary['total_orders']; ?></p></td>
            <td><h3>Items Sold</h3><p><?php echo $summary['total_items_sold']; ?></p></td>
        </tr>
    </table>
    <?php if (!empty($chart_image_data)): ?>
        <h2>Revenue Analytics Chart</h2>
        <div class="chart-container"><img src="<?php echo $chart_image_data; ?>" alt="Sales Chart"></div>
    <?php endif; ?>
    <h2>Top Selling Products (Filtered)</h2>
    <table class="products-table">
        <thead><tr><th>Product</th><th>Shop</th><th>Units Sold</th><th>Revenue</th></tr></thead>
        <tbody>
            <?php if ($top_products_result->num_rows > 0): while ($product = $top_products_result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                <td><?php echo htmlspecialchars($product['shop_name']); ?></td>
                <td><?php echo htmlspecialchars($product['total_units_sold']); ?></td>
                <td>₹<?php echo htmlspecialchars(number_format($product['total_revenue'], 2)); ?></td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="4" style="text-align: center;">No sales data found for the selected criteria.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <div class="footer">Vastra - Automated Sales Report</div>
</body>
</html>
<?php
$html = ob_get_clean();

// --- 5. GENERATE AND OUTPUT THE PDF USING DOMPDF (This part is new) ---

// Instantiate Dompdf with options
$options = new Options();
$options->set('isRemoteEnabled', true); // Important for loading images (like your chart)
$dompdf = new Dompdf($options);

// Load the HTML content
$dompdf->loadHtml($html);

// (Optional) Set paper size and orientation
$dompdf->setPaper('A4', 'portrait');

// Render the HTML as PDF
$dompdf->render();

// Output the generated PDF to Browser
$filename = "Vastra-SalesReport-" . date("Y-m-d") . ".pdf";
// The second argument forces a download. Set to false to display in browser.
$dompdf->stream($filename, ["Attachment" => true]);