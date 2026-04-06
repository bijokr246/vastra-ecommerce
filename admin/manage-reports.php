<?php
// 1. START SESSION AND CHECK FOR LOGIN
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// 2. INCLUDE DATABASE CONNECTION
include "../config.php";

// Build a query string to preserve filters after an action
$query_params = array_filter($_GET);
unset($query_params['action'], $query_params['report_id'], $query_params['product_id']);
$redirect_query_string = http_build_query($query_params);

// 3. HANDLE ACTIONS (Resolve, Review, Ban Product)
if (isset($_GET['action']) && isset($_GET['report_id'])) {
    $action = $_GET['action'];
    $report_id = (int)$_GET['report_id'];

    if ($action === 'review') {
        $stmt = $conn->prepare("UPDATE product_reports SET status = 'reviewed' WHERE report_id = ?");
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
    } elseif ($action === 'resolve') {
        $stmt = $conn->prepare("UPDATE product_reports SET status = 'resolved' WHERE report_id = ?");
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
    } elseif ($action === 'ban_product' && isset($_GET['product_id'])) {
        $product_id = (int)$_GET['product_id'];
        $conn->begin_transaction();
        try {
            $stmt1 = $conn->prepare("UPDATE products SET status = 'banned' WHERE product_id = ?");
            $stmt1->bind_param("i", $product_id);
            $stmt1->execute();
            $stmt2 = $conn->prepare("UPDATE product_reports SET status = 'resolved' WHERE report_id = ?");
            $stmt2->bind_param("i", $report_id);
            $stmt2->execute();
            $conn->commit();
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
        }
    }
    header("Location: manage-reports.php?" . $redirect_query_string);
    exit();
}

// 4. FETCH REPORTS (Main Query with Filtering)
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : 'pending';
$filter_reason = isset($_GET['filter_reason']) ? $_GET['filter_reason'] : '';
$search_query = isset($_GET['search_query']) ? trim($_GET['search_query']) : '';

$sql = "SELECT 
            pr.report_id, pr.reason, pr.comment, pr.status AS report_status, pr.reported_at,
            p.product_id, p.product_name,
            u.user_id, CONCAT(u.fname, ' ', u.lname) AS reporter_name, u.email AS reporter_email,
            s.shop_name
        FROM product_reports pr
        JOIN products p ON pr.product_id = p.product_id
        JOIN users u ON pr.user_id = u.user_id
        JOIN shops s ON p.shop_id = s.shop_id";

$where_clauses = []; $params = []; $types = '';
if (!empty($filter_status)) { $where_clauses[] = "pr.status = ?"; $params[] = $filter_status; $types .= 's'; }
if (!empty($filter_reason)) { $where_clauses[] = "pr.reason = ?"; $params[] = $filter_reason; $types .= 's'; }
if (!empty($search_query)) {
    $where_clauses[] = "(p.product_name LIKE ? OR s.shop_name LIKE ?)";
    $search_param = "%" . $search_query . "%";
    array_push($params, $search_param, $search_param);
    $types .= 'ss';
}
if (!empty($where_clauses)) { $sql .= " WHERE " . implode(" AND ", $where_clauses); }
$sql .= " ORDER BY pr.reported_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$reports_result = $stmt->get_result();
$conn->close();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Product Reports | Vastra Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="../refresh.js"></script>
    <style>
        .filter-container { padding: 20px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 20px; display: grid; grid-template-columns: 1fr repeat(3, auto); gap: 15px; align-items: end; }
        .filter-container .form-group { margin: 0; }
        .filter-container label { display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 600; color: #495057; }
        .filter-container select, .filter-container input { width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; height: 38px; box-sizing: border-box;}
        .report-comment { max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; }
        .input-with-icon { display: flex; align-items: center; }
        .input-with-icon input { flex-grow: 1; border-top-right-radius: 0; border-bottom-right-radius: 0; border-right: none; }
        .input-with-icon .icon-btn { padding: 8px 12px; border: 1px solid #ced4da; background-color: #e9ecef; border-top-left-radius: 0; border-bottom-left-radius: 0; cursor: pointer; line-height: 1.5; height: 38px; }
        .data-table td a { color: inherit; text-decoration: none; font-weight: 600; }
        .data-table td a:hover { color: var(--primary); text-decoration: underline; }
        .modal-overlay { display: none; position: fixed; z-index: 1050; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-content { background: #fff; padding: 25px; border-radius: 8px; max-width: 600px; width: 90%; position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .modal-content h3 { margin-top: 0; color: #333; }
        .modal-content p { color: #555; line-height: 1.6; white-space: pre-wrap; word-wrap: break-word; max-height: 60vh; overflow-y: auto; }
        .modal-close-btn { position: absolute; top: 10px; right: 15px; font-size: 1.8rem; color: #aaa; cursor: pointer; transition: color 0.2s; }
        .modal-close-btn:hover { color: #333; }

        /* --- NEW/MODIFIED: Action Button Styles --- */
        .btn-success { background-color: var(--success); color: var(--white); }
        .btn-success:hover { background-color: #218838; }
        .btn-warning { background-color: var(--warning); color: var(--secondary); } /* Yellow button with dark text */
        .btn-warning:hover { background-color: #e0a800; }
        .btn-danger { background-color: var(--danger); color: var(--white); }
        .btn-danger:hover { background-color: #c82333; }
        .action-buttons { display: flex; gap: 8px; }
    </style>
</head>
<body>
    <aside class="sidebar"><?php require "header.php" ?></aside>

    <main class="main-content">
        <h1 class="page-title">Product Report Management</h1>
        <section class="content-card">
            <div class="page-header"><h2>All Reports</h2></div>
            <form action="manage-reports.php" method="GET" class="filter-container">
                <div class="form-group search-group">
                    <label for="search_query">Search Product/Shop:</label>
                    <div class="input-with-icon">
                        <input type="text" name="search_query" id="search_query" placeholder="Product or Shop Name..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <button type="submit" class="icon-btn" title="Search"><i class="fas fa-search"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="filter_status">Status:</label>
                    <select name="filter_status" id="filter_status" onchange="this.form.submit()">
                        <option value="">All</option>
                        <option value="pending" <?php if ($filter_status == 'pending') echo 'selected'; ?>>Pending</option>
                        <option value="reviewed" <?php if ($filter_status == 'reviewed') echo 'selected'; ?>>Reviewed</option>
                        <option value="resolved" <?php if ($filter_status == 'resolved') echo 'selected'; ?>>Resolved</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filter_reason">Reason:</label>
                    <select name="filter_reason" id="filter_reason" onchange="this.form.submit()">
                        <option value="">All Reasons</option>
                        <option value="Misleading Description" <?php if ($filter_reason == 'Misleading Description') echo 'selected'; ?>>Misleading Description</option>
                        <option value="Incorrect Item" <?php if ($filter_reason == 'Incorrect Item') echo 'selected'; ?>>Incorrect Item</option>
                        <option value="Low Quality" <?php if ($filter_reason == 'Low Quality') echo 'selected'; ?>>Low Quality</option>
                        <option value="Inappropriate Content" <?php if ($filter_reason == 'Inappropriate Content') echo 'selected'; ?>>Inappropriate Content</option>
                        <option value="Other" <?php if ($filter_reason == 'Other') echo 'selected'; ?>>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <a href="manage-reports.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th><th>Product</th><th>Reported By</th><th>Reason</th><th>Comment</th><th>Status</th><th>Date</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody id="reports-table-body">
                <?php if ($reports_result && $reports_result->num_rows > 0): ?>
                    <?php while ($report = $reports_result->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $report['report_id']; ?></td>
                        <td>
                            <a href="../product-detail.php?id=<?php echo $report['product_id']; ?>" target="_blank" title="View Product Page"><?php echo htmlspecialchars($report['product_name']); ?></a><br>
                            <small>Shop: <?php echo htmlspecialchars($report['shop_name']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($report['reporter_name']); ?></td>
                        <td><?php echo htmlspecialchars($report['reason']); ?></td>
                        <td class="report-comment" data-full-comment="<?php echo htmlspecialchars($report['comment']); ?>">
                            <?php echo htmlspecialchars($report['comment'] ?: 'N/A'); ?>
                        </td>
                        <td><span class="status <?php echo htmlspecialchars($report['report_status']); ?>"><?php echo htmlspecialchars(ucfirst($report['report_status'])); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($report['reported_at'])); ?></td>
                        <td class="action-buttons">
                        <?php $action_link_base = 'manage-reports.php?' . $redirect_query_string . '&report_id=' . $report['report_id']; ?>
                        <?php if ($report['report_status'] === 'pending'): ?>
                            <!-- MODIFIED: Changed button classes for clarity -->
                            <a class="btn btn-sm btn-warning" href="<?php echo $action_link_base . '&action=review'; ?>">Review</a>
                            <a class="btn btn-sm btn-success" href="<?php echo $action_link_base . '&action=resolve'; ?>">Resolve</a>
                            <a class="btn btn-sm btn-danger" href="<?php echo $action_link_base . '&action=ban_product&product_id=' . $report['product_id']; ?>" onclick="return confirm('Are you sure you want to BAN this product and resolve the report?');">Ban</a>
                        <?php elseif ($report['report_status'] === 'reviewed'): ?>
                             <a class="btn btn-sm btn-success" href="<?php echo $action_link_base . '&action=resolve'; ?>">Resolve</a>
                             <a class="btn btn-sm btn-danger" href="<?php echo $action_link_base . '&action=ban_product&product_id=' . $report['product_id']; ?>" onclick="return confirm('Are you sure you want to BAN this product and resolve the report?');">Ban</a>
                        <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center;">No reports found matching your criteria.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>

    <!-- Modal for Viewing Full Comment -->
    <div class="modal-overlay" id="comment-modal">
        <div class="modal-content">
            <span class="modal-close-btn" id="modal-close">&times;</span>
            <h3>Full Comment Details</h3>
            <p id="modal-comment-text"></p>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // All JavaScript for the modal and event listeners remains the same
        const tableBody = document.getElementById('reports-table-body');
        const commentModal = document.getElementById('comment-modal');
        const modalText = document.getElementById('modal-comment-text');
        const closeModalBtn = document.getElementById('modal-close');
        function showModal(content) { modalText.textContent = content || 'No comment provided.'; commentModal.classList.add('active'); }
        function hideModal() { commentModal.classList.remove('active'); }
        if (tableBody) {
            tableBody.addEventListener('click', function(event) {
                const commentCell = event.target.closest('td.report-comment');
                if (commentCell) {
                    const fullComment = commentCell.getAttribute('data-full-comment');
                    showModal(fullComment);
                }
            });
        }
        closeModalBtn.addEventListener('click', hideModal);
        commentModal.addEventListener('click', function(event) { if (event.target === commentModal) { hideModal(); } });
        window.addEventListener('keydown', function(event) { if (event.key === 'Escape' && commentModal.classList.contains('active')) { hideModal(); } });
    });
    </script>
</body>
</html>