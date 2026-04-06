<?php
session_start();
include "../config.php";

// 1. SECURITY AND AUTHENTICATION
if (!isset($_SESSION['user_id'])) {
    header('Location: ../seller-login.php');
    exit();
}
$user_id = $_SESSION['user_id'];

// 2. FETCH REPORTS FOR THE LOGGED-IN SELLER'S PRODUCTS
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : 'pending';

$sql = "SELECT 
            p.product_name,
            pr.reason,
            pr.comment,
            pr.reported_at,
            pr.status AS report_status
        FROM product_reports pr
        JOIN products p ON pr.product_id = p.product_id
        JOIN shops s ON p.shop_id = s.shop_id
        WHERE s.user_id = ?";

if (!empty($filter_status)) {
    $sql .= " AND pr.status = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $filter_status);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$reports_result = $stmt->get_result();
// $stmt->close(); // Close statement here
// $conn->close(); // DO NOT CLOSE CONNECTION HERE - MOVED TO THE BOTTOM

// Helper for Active Nav Link
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Product Reports | Vastra Seller</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="../refresh.js"></script>
    <style>
        /* ... (styles remain the same) ... */
        .filter-bar { margin-bottom: 20px; }
        .filter-bar label { font-weight: 600; margin-right: 10px; }
        .filter-bar select { padding: 8px; border-radius: 6px; border: 1px solid #ddd; }
        .report-comment { max-width: 350px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; }
        .status.pending, .status.reviewed { background: #fef3c7; color: #92400e; }
        .status.resolved { background: #d1fae5; color: #065f46; }
        .modal-overlay { display: none; position: fixed; z-index: 1050; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-content { background: #fff; padding: 25px; border-radius: 8px; max-width: 600px; width: 90%; position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .modal-content h3 { margin-top: 0; }
        .modal-content p { line-height: 1.6; white-space: pre-wrap; word-wrap: break-word; max-height: 60vh; overflow-y: auto; }
        .modal-close-btn { position: absolute; top: 10px; right: 15px; font-size: 1.8rem; color: #aaa; cursor: pointer; }
    </style>
</head>
<body>
    <div class="sidebar">
        <?php require "sidebar.php"; ?>
    </div>

    <div class="main">
        <div class="header"><h1>Product Reports</h1></div>
        <div class="content">
            <div class="filter-bar">
                <form action="product-reports.php" method="GET">
                    <label for="filter_status">Show reports that are:</label>
                    <select name="filter_status" id="filter_status" onchange="this.form.submit()">
                        <option value="pending" <?php if ($filter_status == 'pending') echo 'selected'; ?>>Pending</option>
                        <option value="reviewed" <?php if ($filter_status == 'reviewed') echo 'selected'; ?>>Reviewed by Admin</option>
                        <option value="resolved" <?php if ($filter_status == 'resolved') echo 'selected'; ?>>Resolved</option>
                        <option value="" <?php if ($filter_status == '') echo 'selected'; ?>>Show All</option>
                    </select>
                </form>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Product Name</th><th>Reason for Report</th><th>User Comment</th><th>Status</th><th>Date Reported</th>
                    </tr>
                </thead>
                <tbody id="reports-table-body">
                    <?php if ($reports_result->num_rows > 0): ?>
                        <?php while ($report = $reports_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($report['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($report['reason']); ?></td>
                                <td class="report-comment" data-full-comment="<?php echo htmlspecialchars($report['comment']); ?>">
                                    <?php echo htmlspecialchars($report['comment'] ?: 'N/A'); ?>
                                </td>
                                <td>
                                    <span class="status <?php echo strtolower(htmlspecialchars($report['report_status'])); ?>">
                                        <?php echo htmlspecialchars(ucfirst($report['report_status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($report['reported_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center;">No reports found matching your criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal for Viewing Full Comment -->
    <div class="modal-overlay" id="comment-modal">
        <div class="modal-content">
            <span class="modal-close-btn" id="modal-close">&times;</span>
            <h3>Full Comment from User</h3>
            <p id="modal-comment-text"></p>
        </div>
    </div>

    <script>
    // ... (Your JavaScript remains the same) ...
    document.addEventListener('DOMContentLoaded', function() {
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
    <?php
        // PROPERLY CLOSE ALL STATEMENTS AND THE CONNECTION AT THE END
        $stmt->close();
        // The connection object '$conn' was passed to sidebar.php, so we close it here.
        // Note: The connection object from sidebar.php is the same one.
        // We can just use $conn here because sidebar.php already declared it as global or used the same scope.
        // It's safe to close it once at the very end.
        global $conn; // Re-affirm scope just in case, though it's likely not needed
        if ($conn) { $conn->close(); }
    ?>
</body>
</html>