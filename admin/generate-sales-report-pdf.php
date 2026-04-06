<?php
session_start();
if (!isset($_SESSION['admin_id'])) { exit('Access Denied'); }

require '../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;
include "../config.php";

$date_from = $_POST['date_from'];
$date_to = $_POST['date_to'];
$seller_id = $_POST['seller_id'];
$shop_id = $_POST['shop_id'];
$category_id = $_POST['category_id'];
$subcategory_id = $_POST['subcategory_id'];
$chart_image_data = $_POST['chart_image_data'];

$base_from = "FROM orders o JOIN order_items oi ON o.order_id = oi.order_id JOIN products p ON oi.product_id = p.product_id JOIN shops s ON p.shop_id = s.shop_id JOIN users u ON s.user_id = u.user_id LEFT JOIN category c ON p.category_id = c.category_id LEFT JOIN subcategory sc ON p.subcategory_id = sc.subcategory_id";
$where_clauses = ["o.status = 'Delivered'", "o.order_date BETWEEN ? AND ?"];
$param_types = "ss";
$params = [$date_from, $date_to . ' 23:59:59'];

if ($seller_id !== 'all') { $where_clauses[] = "u.user_id = ?"; $param_types .= "i"; $params[] = (int)$seller_id; }
if ($shop_id !== 'all') { $where_clauses[] = "s.shop_id = ?"; $param_types .= "i"; $params[] = (int)$shop_id; }
if ($category_id !== 'all') { $where_clauses[] = "p.category_id = ?"; $param_types .= "i"; $params[] = (int)$category_id; }
if ($subcategory_id !== 'all') { $where_clauses[] = "p.subcategory_id = ?"; $param_types .= "i"; $params[] = (int)$subcategory_id; }
$final_where = " WHERE " . implode(" AND ", $where_clauses);

// Re-run all queries
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

function generate_table_html($title, $headers, $data) {
    $html = "<h2>{$title}</h2><table class='report-table'><thead><tr>";
    foreach ($headers as $header) { $html .= "<th>{$header}</th>"; }
    $html .= "</tr></thead><tbody>";
    if (empty($data)) {
        $html .= "<tr><td colspan='" . count($headers) . "'>No data available for the selected filters.</td></tr>";
    } else {
        foreach ($data as $row) {
            $html .= "<tr>";
            foreach ($row as $key => $cell) {
                if (strpos(strtolower($key), 'revenue') !== false) { $cell = '₹' . number_format($cell, 2); }
                $html .= "<td>" . htmlspecialchars($cell) . "</td>";
            }
            $html .= "</tr>";
        }
    }
    $html .= "</tbody></table>";
    return $html;
}

$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Platform Sales Report</title>
<style> body { font-family: sans-serif; font-size: 10px; } h1 { text-align: center; color: #333; } h2 { border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-top: 25px; font-size: 14px; } .report-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; } .report-table th, .report-table td { border: 1px solid #ddd; padding: 6px; text-align: left; } .report-table th { background-color: #f2f2f2; } .chart { text-align: center; margin-bottom: 20px; } .chart img { max-width: 100%; } </style></head><body>
<h1>Vastra - Platform Sales Report</h1>
<p style="text-align:center;"><strong>Date Range:</strong> ' . date('M d, Y', strtotime($date_from)) . ' to ' . date('M d, Y', strtotime($date_to)) . '</p>';
if (!empty($chart_image_data)) { $html .= '<div class="chart"><img src="' . $chart_image_data . '"></div>'; }
$html .= generate_table_html('Sales by Shop', ['Shop Name', 'Orders', 'Revenue'], $shop_report);
$html .= generate_table_html('Sales by Seller', ['Seller Name', 'Orders', 'Revenue'], $seller_report);
$html .= generate_table_html('Sales by Category', ['Category', 'Items Sold', 'Revenue'], $category_report);
$html .= generate_table_html('Top 20 Selling Products', ['Product', 'Shop', 'Units Sold', 'Revenue'], $product_report);
$html .= '</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$filename = 'Vastra_Sales_Report_' . date('Y-m-d') . '.pdf';
$dompdf->stream($filename, ["Attachment" => true]);