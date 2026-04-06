<?php
// Start session and include database config
session_start();
include "config.php";

define('UPLOAD_PATH', 'images/');

// --- AUTHENTICATION & INITIALIZATION ---
$is_logged_in = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;

if (!$is_logged_in) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user's first name for the navbar
$user_firstname = '';
$sql_user = "SELECT fname, email FROM users WHERE user_id = ?";
if ($stmt_user = mysqli_prepare($conn, $sql_user)) {
    mysqli_stmt_bind_param($stmt_user, "i", $user_id);
    mysqli_stmt_execute($stmt_user);
    $result_user = mysqli_stmt_get_result($stmt_user);
    if ($user_row = mysqli_fetch_assoc($result_user)) {
        $user_firstname = $user_row['fname'];
        $_SESSION['email'] = $user_row['email'];
    }
    mysqli_stmt_close($stmt_user);
}

// Get Order ID from URL and validate it
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: myorders.php");
    exit();
}
$order_id = (int)$_GET['id'];
$feedback_message = '';


// --- FORM SUBMISSION HANDLING (NEW REVIEWS & UPDATES) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['submit_review']) || isset($_POST['update_review']))) {
    $product_id_to_review = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $review_text = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';
    $action = isset($_POST['update_review']) ? 'update_review' : 'submit_review';

    // Basic validation
    if ($product_id_to_review > 0 && $rating >= 1 && $rating <= 5) {
        // SECURITY CHECK: Verify the user actually bought this product in this order
        $verify_sql = "SELECT 1 FROM orders o JOIN order_items oi ON o.order_id = oi.order_id WHERE o.order_id = ? AND o.user_id = ? AND oi.product_id = ?";
        $stmt_verify = mysqli_prepare($conn, $verify_sql);
        mysqli_stmt_bind_param($stmt_verify, "iii", $order_id, $user_id, $product_id_to_review);
        mysqli_stmt_execute($stmt_verify);
        
        if (mysqli_stmt_get_result($stmt_verify)->num_rows > 0) {
            if ($action === 'submit_review') {
                // Insert new review
                $sql = "INSERT INTO product_reviews (user_id, product_id, order_id, rating, review_text) VALUES (?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "iiiis", $user_id, $product_id_to_review, $order_id, $rating, $review_text);
                if (mysqli_stmt_execute($stmt)) {
                    $feedback_message = '<div class="alert alert-success">Thank you! Your review has been submitted.</div>';
                } else {
                    $feedback_message = '<div class="alert alert-danger">Error: You may have already reviewed this product for this order.</div>';
                }
                mysqli_stmt_close($stmt);

            } elseif ($action === 'update_review') {
                // Update existing review
                $sql = "UPDATE product_reviews SET rating = ?, review_text = ? WHERE user_id = ? AND product_id = ? AND order_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "isiii", $rating, $review_text, $user_id, $product_id_to_review, $order_id);
                if (mysqli_stmt_execute($stmt)) {
                    $feedback_message = '<div class="alert alert-success">Your review has been updated.</div>';
                } else {
                    $feedback_message = '<div class="alert alert-danger">Error updating your review.</div>';
                }
                mysqli_stmt_close($stmt);
            }
        }
        mysqli_stmt_close($stmt_verify);
    } else {
        $feedback_message = '<div class="alert alert-danger">Invalid input. Please select 1 to 5 stars.</div>';
    }
}


// --- DATA FETCHING FOR THE PAGE (INCLUDES REVIEW AND REPORT STATUS) ---
$order_details = [];
$sql = "SELECT
            o.order_id, o.total_amount, o.status, o.order_date,
            a.recipient_fname, a.recipient_lname, a.house_address, a.landmark, a.locality_or_town, a.district, a.pincode, a.state,
            oi.quantity, oi.price AS item_price,
            p.product_id, p.product_name, p.product_image,
            pr.review_id, pr.rating, pr.review_text,
            preport.report_id
        FROM orders AS o
        JOIN addresses AS a ON o.address_id = a.address_id
        JOIN order_items AS oi ON o.order_id = oi.order_id
        JOIN products AS p ON oi.product_id = p.product_id
        LEFT JOIN product_reviews pr ON o.user_id = pr.user_id AND oi.product_id = pr.product_id AND o.order_id = pr.order_id
        LEFT JOIN product_reports preport ON o.user_id = preport.user_id AND oi.product_id = preport.product_id AND o.order_id = preport.order_id
        WHERE o.order_id = ? AND o.user_id = ?";

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) == 0) {
        echo "Order not found.";
        exit();
    }

    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        if (empty($order_details)) {
            $recipient_full_name = htmlspecialchars($row['recipient_fname'] . ' ' . $row['recipient_lname']);
            $order_details = [
                'order_id' => $row['order_id'],
                'total_amount' => $row['total_amount'],
                'status' => $row['status'],
                'order_date' => date('F j, Y', strtotime($row['order_date'])),
                'address' => "{$recipient_full_name}<br>{$row['house_address']}<br>{$row['locality_or_town']}, {$row['district']}<br>{$row['state']} - {$row['pincode']}" . ($row['landmark'] ? "<br>Landmark: {$row['landmark']}" : "")
            ];
        }
        $items[$row['product_id']] = [
            'product_id' => $row['product_id'],
            'product_name' => $row['product_name'],
            'product_image' => $row['product_image'],
            'quantity' => $row['quantity'],
            'price' => $row['item_price'],
            'review' => $row['review_id'] ? ['rating' => $row['rating'], 'review_text' => $row['review_text']] : null,
            'report_id' => $row['report_id']
        ];
    }
    $order_details['items'] = $items;
    mysqli_stmt_close($stmt);

} else {
    die("Error preparing the statement.");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details | Vastra</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="index.css">
    <style>
        .order-details-section { padding: 3rem 1rem; background-color: #f9f9f9; }
        .page-title { text-align: center; margin-bottom: 2.5rem; font-size: 2.5rem; }
        .order-details-container { max-width: 900px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); overflow: hidden; }
        .order-summary-header { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 1rem; padding: 1.5rem; background-color: #f7f7f7; border-bottom: 1px solid #e0e0e0; }
        .order-summary-header div { flex: 1; min-width: 200px; }
        .order-summary-header h4 { margin: 0 0 0.5rem; color: #555; font-size: 0.9rem; text-transform: uppercase; }
        .order-summary-header p { margin: 0; font-size: 1rem; }
        .order-items-list { padding: 1rem; }
        .order-item { display: flex; gap: 1.5rem; padding: 1.5rem; border-bottom: 1px solid #f0f0f0; }
        .order-item:last-child { border-bottom: none; }
        .order-item-image { width: 100px; height: 100px; border-radius: 4px; object-fit: cover; }
        .order-item-info { flex-grow: 1; }
        .order-item-info h3 { margin: 0 0 0.5rem; font-size: 1.1rem; }
        .order-item-info a { text-decoration: none; color: #333; }
        .order-item-info p { margin: 0; color: #777; }
        .order-item-price { font-weight: 600; font-size: 1.1rem; text-align: right; }
        .review-section { margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px dashed #ddd; }
        .rating-stars { display: flex; flex-direction: row-reverse; justify-content: flex-end; }
        .rating-stars input[type="radio"] { display: none; }
        .rating-stars label { font-size: 1.8rem; color: #ddd; cursor: pointer; transition: color 0.2s; }
        .rating-stars input[type="radio"]:checked ~ label, .rating-stars label:hover, .rating-stars label:hover ~ label { color: #f5b301; }
        .review-form textarea { width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 4px; min-height: 100px; margin-top: 1rem; resize: vertical; }
        .review-form .btn, .review-form button { margin-top: 1rem; }
        .submitted-review h5 { margin-bottom: 0.5rem; }
        .submitted-review .stars-display { color: #f5b301; font-size: 1.2rem; }
        .submitted-review p { background-color: #f0f0f0; padding: 1rem; border-radius: 4px; white-space: pre-wrap; word-wrap: break-word; }
        .review-actions { margin-top: 0.5rem; }
        .btn-edit-review, .btn-cancel-edit { background: none; border: none; color: var(--primary); cursor: pointer; font-size: 0.9rem; padding: 0; }
        .btn-cancel-edit { color: #777; margin-left: 1rem; }
        /* CSS FIX: No longer hide the edit block by default */
        /* .review-edit-block { display: none; } */
        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 4px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .report-product-link { display: inline-block; font-size: .9rem; color: #777; text-decoration: none; margin-top: 1rem; }
        .report-product-link:hover { color: var(--primary); text-decoration: underline; }
        .modal-overlay{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background-color:rgba(0,0,0,.6);justify-content:center;align-items:center}.modal-overlay.active{display:flex}.report-modal-content{position:relative;background-color:#fff;padding:2rem 2.5rem;border-radius:8px;text-align:left;max-width:500px;width:90%;box-shadow:0 5px 15px rgba(0,0,0,.3)}.report-modal-content .close-button{position:absolute;top:10px;right:15px;font-size:1.8rem;color:#aaa;cursor:pointer;transition:color .3s}.report-modal-content h3{margin-top:0;margin-bottom:1.5rem;color:#333;text-align:center}.report-form .form-group{margin-bottom:1rem}.report-form label{display:block;font-weight:500;margin-bottom:.5rem}.report-form select,.report-form textarea{width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;font-size:1rem}.report-form textarea{resize:vertical;min-height:100px}.report-form .btn-submit-report{display:block;width:100%;padding:12px;border:none;border-radius:5px;background-color:var(--primary);color:#fff;font-size:1.1rem;font-weight:600;cursor:pointer;transition:opacity .3s}.report-form .btn-submit-report:hover{opacity:.9}.report-feedback{text-align:center;margin-top:1rem;font-weight:500}.report-feedback.success{color:#28a745}.report-feedback.error{color:#dc3545}
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container nav-container">
            <button class="mobile-menu-btn" id="mobile-menu-button"><i class="fas fa-bars"></i></button>
            <a href="index.php" class="logo">VASTRA</a>
            <?php include 'nav_links.html'; ?>
            <div class="nav-icons">
                <a href="search.php" title="Search"><i class="fas fa-search"></i></a>
                <a href="wishlist.php" title="Wishlist"><i class="far fa-heart"></i></a>
                <a href="cart.php" class="cart-icon-container" title="Shopping Cart"><i class="fas fa-shopping-bag"></i></a>
                <div class="profile-card-container">
                    <a href="#" id="user-icon-btn"><i class="far fa-user"></i></a>
                    <span id="user-greeting" class="user-greeting-text">Hello, <?php echo htmlspecialchars($user_firstname); ?></span>
                    <div id="profile-card" class="profile-card">
                        <div class="profile-header"><h4>Hello, <?php echo htmlspecialchars($user_firstname); ?></h4><p><?php echo htmlspecialchars($_SESSION['email']); ?></p></div>
                        <ul class="profile-card-links">
                            <li><a href="dashboard.php"><i class="far fa-user-circle fa-fw"></i> My Profile</a></li>
                            <li><a href="myorders.php"><i class="fas fa-box-open fa-fw"></i> Orders</a></li>
                            <li><a href="wishlist.php"><i class="far fa-heart fa-fw"></i> Wishlist</a></li>
                            <li><a href="logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main class="order-details-section">
        <div class="container">
            <h1 class="page-title">Order Details</h1>
            <?php echo $feedback_message; ?>
            <div class="order-details-container">
                <div class="order-summary-header">
                    <div><h4>Order Number</h4><p>#<?php echo htmlspecialchars($order_details['order_id']); ?></p></div>
                    <div><h4>Date Placed</h4><p><?php echo htmlspecialchars($order_details['order_date']); ?></p></div>
                    <div><h4>Total Amount</h4><p>₹<?php echo htmlspecialchars(number_format($order_details['total_amount'])); ?></p></div>
                    <div><h4>Shipping Address</h4><p><?php echo $order_details['address']; ?></p></div>
                </div>

                <div class="order-items-list">
                    <?php foreach ($order_details['items'] as $item): ?>
                        <div class="order-item" id="item-<?php echo $item['product_id']; ?>">
                            <img src="<?php echo UPLOAD_PATH . htmlspecialchars($item['product_image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="order-item-image">
                            <div class="order-item-info">
                                <h3><a href="product-detail.php?id=<?php echo $item['product_id']; ?>"><?php echo htmlspecialchars($item['product_name']); ?></a></h3>
                                <p>Quantity: <?php echo htmlspecialchars($item['quantity']); ?></p>
                                <p>Price: ₹<?php echo htmlspecialchars(number_format($item['price'])); ?></p>

                                <?php if ($order_details['status'] === 'Delivered'): ?>
                                    <div class="review-section">
                                        <!-- Display Block for Existing Review -->
                                        <div class="review-display-block" id="review-display-<?php echo $item['product_id']; ?>" <?php if (!$item['review']) echo 'style="display:none;"'; ?>>
                                            <div class="submitted-review">
                                                <h5>Your Review</h5>
                                                <div class="stars-display">
                                                    <?php 
                                                    if($item['review']) {
                                                        for ($i = 0; $i < $item['review']['rating']; $i++) echo '<i class="fas fa-star"></i>';
                                                        for ($i = 0; $i < 5 - $item['review']['rating']; $i++) echo '<i class="far fa-star"></i>';
                                                    }
                                                    ?>
                                                </div>
                                                <p><?php echo $item['review'] ? nl2br(htmlspecialchars($item['review']['review_text'])) : ''; ?></p>
                                                <div class="review-actions">
                                                    <button class="btn-edit-review" data-product-id="<?php echo $item['product_id']; ?>">Edit Review</button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- FIX: The form block is now controlled by PHP's inline style to ensure it shows for new reviews -->
                                        <div class="review-edit-block" id="review-edit-<?php echo $item['product_id']; ?>" style="<?php echo $item['review'] ? 'display: none;' : 'display: block;'; ?>">
                                            <form method="POST" action="" class="review-form">
                                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                                <h5><?php echo $item['review'] ? 'Edit Your Review' : 'Write a Review'; ?></h5>
                                                <div class="rating-stars">
                                                    <?php for ($i = 5; $i >= 1; $i--): 
                                                        $checked = ($item['review'] && $item['review']['rating'] == $i) ? 'checked' : '';
                                                    ?>
                                                        <input type="radio" id="star<?php echo $i; ?>_<?php echo $item['product_id']; ?>" name="rating" value="<?php echo $i; ?>" <?php echo $checked; ?> required><label for="star<?php echo $i; ?>_<?php echo $item['product_id']; ?>"><i class="fas fa-star"></i></label>
                                                    <?php endfor; ?>
                                                </div>
                                                <textarea name="review_text" placeholder="Share your thoughts..."><?php echo $item['review'] ? htmlspecialchars($item['review']['review_text']) : ''; ?></textarea>
                                                
                                                <?php if ($item['review']): ?>
                                                    <button type="submit" name="update_review" class="btn btn-primary">Update Review</button>
                                                    <button type="button" class="btn-cancel-edit" data-product-id="<?php echo $item['product_id']; ?>">Cancel</button>
                                                <?php else: ?>
                                                     <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Report Product Link -->
                                    <div class="report-section">
                                        <?php if ($item['report_id']): ?>
                                            <p class="report-product-link" style="color: #28a745; cursor: default; text-decoration: none;"><i class="fas fa-check-circle"></i> You have reported this item</p>
                                        <?php else: ?>
                                            <a href="#" class="report-product-link" data-product-id="<?php echo $item['product_id']; ?>" data-order-id="<?php echo $order_id; ?>"><i class="fas fa-flag"></i> Report this item</a>
                                        <?php endif; ?>
                                    </div>

                                <?php endif; ?>
                            </div>
                            <div class="order-item-price">
                                ₹<?php echo htmlspecialchars(number_format($item['price'] * $item['quantity'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include 'footer.html'; ?>
    
    <!-- REPORT PRODUCT MODAL (No changes needed here) -->
    <div id="report-modal" class="modal-overlay">
        <!-- Modal content is the same -->
        <div class="report-modal-content">
            <span class="close-button">&times;</span>
            <h3>Report Item</h3>
            <form id="report-form" class="report-form"><input type="hidden" id="report-product-id" name="product_id" value=""><input type="hidden" id="report-order-id" name="order_id" value=""><div class="form-group"><label for="report-reason">Reason for reporting:</label><select id="report-reason" name="reason" required><option value="">-- Please select a reason --</option><option value="Incorrect Item">Incorrect Item Received</option><option value="Low Quality">Poor Quality / Damaged</option><option value="Misleading Description">Misleading Description</option><option value="Inappropriate Content">Inappropriate Content</option><option value="Other">Other</option></select></div><div class="form-group"><label for="report-comment">Comments (optional):</label><textarea id="report-comment" name="comment" placeholder="Provide more details..."></textarea></div><button type="submit" class="btn-submit-report">Submit Report</button><div class="report-feedback" id="report-feedback"></div></form>
        </div>
    </div>

    <script>
    // The JavaScript remains the same and will work with the corrected HTML structure.
    document.addEventListener('DOMContentLoaded', () => {
        const profileCardContainer = document.querySelector('.profile-card-container');
        const profileCard = document.getElementById('profile-card');
        if (profileCardContainer && profileCard) {
            profileCardContainer.addEventListener('click', (event) => {
                event.stopPropagation();
                if (event.target.id === 'user-greeting' || event.target.closest('#user-icon-btn')) {
                    profileCard.classList.toggle('active');
                }
            });
            document.addEventListener('click', (event) => {
                if (profileCard.classList.contains('active') && !profileCardContainer.contains(event.target)) {
                    profileCard.classList.remove('active');
                }
            });
        }
        document.querySelectorAll('.btn-edit-review').forEach(button => {
            button.addEventListener('click', (e) => {
                const productId = e.target.dataset.productId;
                document.getElementById(`review-display-${productId}`).style.display = 'none';
                document.getElementById(`review-edit-${productId}`).style.display = 'block';
            });
        });
        document.querySelectorAll('.btn-cancel-edit').forEach(button => {
            button.addEventListener('click', (e) => {
                const productId = e.target.dataset.productId;
                document.getElementById(`review-display-${productId}`).style.display = 'block';
                document.getElementById(`review-edit-${productId}`).style.display = 'none';
            });
        });
        const reportModal = document.getElementById('report-modal');
        if (reportModal) {
            const closeReportBtn = reportModal.querySelector('.close-button');
            const reportForm = document.getElementById('report-form');
            const reportFeedback = document.getElementById('report-feedback');
            const modalProductIdInput = document.getElementById('report-product-id');
            const modalOrderIdInput = document.getElementById('report-order-id');
            function showReportModal(productId, orderId) {
                modalProductIdInput.value = productId;
                modalOrderIdInput.value = orderId;
                reportModal.classList.add('active');
            }
            function hideReportModal() {
                reportModal.classList.remove('active');
                reportFeedback.textContent = '';
                reportFeedback.className = 'report-feedback';
                reportForm.reset();
            }
            document.querySelectorAll('.report-product-link').forEach(link => {
                if(link.getAttribute('href') === '#') {
                    link.addEventListener('click', e => {
                        e.preventDefault();
                        const productId = link.dataset.productId;
                        const orderId = link.dataset.orderId;
                        showReportModal(productId, orderId);
                    });
                }
            });
            closeReportBtn.addEventListener('click', hideReportModal);
            reportModal.addEventListener('click', (e) => { if (e.target === reportModal) hideReportModal(); });
            reportForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submitBtn = reportForm.querySelector('.btn-submit-report');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Submitting...';
                reportFeedback.textContent = '';
                const formData = new FormData(reportForm);
                formData.append('action', 'report_product');
                try {
                    const response = await fetch('ajax_handler.php', { method: 'POST', body: formData });
                    const result = await response.json();
                    if (result.status === 'success') {
                        reportFeedback.className = 'report-feedback success';
                        reportFeedback.textContent = result.message;
                        const originalLink = document.querySelector(`.report-product-link[data-product-id='${modalProductIdInput.value}']`);
                        if (originalLink) {
                            originalLink.outerHTML = '<p class="report-product-link" style="color: #28a745; cursor: default; text-decoration: none;"><i class="fas fa-check-circle"></i> Item reported</p>';
                        }
                        setTimeout(hideReportModal, 2500);
                    } else {
                        throw new Error(result.message || 'An unknown error occurred.');
                    }
                } catch (error) {
                    reportFeedback.className = 'report-feedback error';
                    reportFeedback.textContent = 'Error: ' + error.message;
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit Report';
                }
            });
        }
    });
    </script>

</body>
</html>