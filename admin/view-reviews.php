<?php
session_start();
include "../config.php";

// 1. ADMIN SECURITY CHECK
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// 2. VALIDATE PRODUCT ID FROM URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage-products.php?error=not_found');
    exit();
}
$product_id = (int)$_GET['id'];

// 3. FETCH PRODUCT DETAILS (No ownership check needed for admin)
$stmt_prod = $conn->prepare("SELECT product_name, product_image FROM products WHERE product_id = ?");
$stmt_prod->bind_param("i", $product_id);
$stmt_prod->execute();
$product_result = $stmt_prod->get_result();
if ($product_result->num_rows === 0) {
    header('Location: manage-products.php?error=not_found');
    exit();
}
$product = $product_result->fetch_assoc();
$stmt_prod->close();

// 4. FETCH ALL REVIEWS FOR THIS PRODUCT
$reviews = [];
$stmt_reviews = $conn->prepare("
    SELECT pr.rating, pr.review_text, pr.created_at, u.fname, u.lname
    FROM product_reviews pr
    JOIN users u ON pr.user_id = u.user_id
    WHERE pr.product_id = ? AND pr.status = 'approved'
    ORDER BY pr.created_at DESC
");
$stmt_reviews->bind_param("i", $product_id);
$stmt_reviews->execute();
$reviews_result = $stmt_reviews->get_result();
while ($row = $reviews_result->fetch_assoc()) {
    $reviews[] = $row;
}
$stmt_reviews->close();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>View Product Reviews | Vastra Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .product-header { display: flex; align-items: center; gap: 20px; margin-bottom: 30px; padding: 20px; background-color: #f8f9fa; border-radius: 8px; }
        .product-header img { width: 80px; height: 80px; object-fit: cover; border-radius: 4px; }
        .product-header h2 { margin: 0; font-size: 1.8rem; }
        .review-list { display: flex; flex-direction: column; gap: 20px; }
        .review-card { background-color: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .review-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #f0f0f0; padding-bottom: 10px; }
        .reviewer-info h4 { margin: 0; font-size: 1.1rem; }
        .reviewer-info span { font-size: 0.85rem; color: #6c757d; }
        .rating-stars { font-size: 1.1rem; color: #f5b301; }
        .review-card-body p { margin: 0; line-height: 1.6; white-space: pre-wrap; }
        .no-reviews-message { text-align: center; padding: 40px; background-color: #fff; border: 1px dashed #ccc; border-radius: 8px; }
    </style>
</head>
<body>
    <aside class="sidebar"><?php require "header.php"; ?></aside>
    <main class="main-content">
        <div class="page-header">
             <h1>Product Reviews</h1>
             <a href="manage-products.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Products</a>
        </div>
        <section class="content-card">
            <div class="product-header">
                <img src="../images/<?php echo htmlspecialchars($product['product_image']); ?>" alt="Product Image">
                <h2><?php echo htmlspecialchars($product['product_name']); ?></h2>
            </div>
            <div class="review-list">
                <?php if (!empty($reviews)): ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-card">
                            <div class="review-card-header">
                                <div class="reviewer-info">
                                    <h4><?php echo htmlspecialchars($review['fname'] . ' ' . $review['lname']); ?></h4>
                                    <span><?php echo date('F j, Y', strtotime($review['created_at'])); ?></span>
                                </div>
                                <div class="rating-stars">
                                    <?php for ($i = 0; $i < 5; $i++): ?><i class="<?php echo $i < $review['rating'] ? 'fas' : 'far'; ?> fa-star"></i><?php endfor; ?>
                                </div>
                            </div>
                            <div class="review-card-body"><p><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-reviews-message"><h3>No approved reviews found for this product.</h3></div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>