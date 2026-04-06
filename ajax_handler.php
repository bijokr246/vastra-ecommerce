<?php
session_start();
include "config.php";

header('Content-Type: application/json');

// Changed the initial validation to only check for an action
if (!isset($_POST['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request. Action not specified.']);
    exit;
}

$action = $_POST['action'];
$is_logged_in = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;

// Action: toggle_wishlist
if ($action === 'toggle_wishlist') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    if ($product_id === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid product.']);
        exit;
    }

    if (!$is_logged_in) {
        echo json_encode(['status' => 'error', 'message' => 'Please login to use the wishlist.']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $check_sql = "SELECT * FROM wishlist WHERE user_id = ? AND product_id = ?";
    $stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        $delete_sql = "DELETE FROM wishlist WHERE user_id = ? AND product_id = ?";
        $stmt_del = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($stmt_del, "ii", $user_id, $product_id);
        if(mysqli_stmt_execute($stmt_del)){
            echo json_encode(['status' => 'success', 'action' => 'removed']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to remove from wishlist.']);
        }
    } else {
        $insert_sql = "INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)";
        $stmt_ins = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt_ins, "ii", $user_id, $product_id);
        if(mysqli_stmt_execute($stmt_ins)){
            echo json_encode(['status' => 'success', 'action' => 'added']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add to wishlist.']);
        }
    }
    exit;
}

// Action: add_to_cart
if ($action === 'add_to_cart') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    if ($product_id === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid product.']);
        exit;
    }
    
    if ($is_logged_in) {
        $user_id = $_SESSION['user_id'];
        $check_sql = "SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?";
        $stmt_check = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($stmt_check, "ii", $user_id, $product_id);
        mysqli_stmt_execute($stmt_check);
        $result = mysqli_stmt_get_result($stmt_check);
        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $new_quantity = $row['quantity'] + 1;
            $update_sql = "UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?";
            $stmt_update = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($stmt_update, "iii", $new_quantity, $user_id, $product_id);
            mysqli_stmt_execute($stmt_update);
        } else {
            $insert_sql = "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)";
            $stmt_insert = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($stmt_insert, "ii", $user_id, $product_id);
            mysqli_stmt_execute($stmt_insert);
        }
        $count_sql = "SELECT COUNT(*) as total FROM cart WHERE user_id = ?";
        $stmt_count = mysqli_prepare($conn, $count_sql);
        mysqli_stmt_bind_param($stmt_count, "i", $user_id);
        mysqli_stmt_execute($stmt_count);
        $count_result = mysqli_stmt_get_result($stmt_count);
        $cart_count = mysqli_fetch_assoc($count_result)['total'];
        echo json_encode(['status' => 'success', 'message' => 'Product added to cart!', 'cart_count' => $cart_count]);
    } else {
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity']++;
        } else {
            $sql_product = "SELECT product_name, price, product_image FROM products WHERE product_id = ?";
            $stmt = mysqli_prepare($conn, $sql_product);
            mysqli_stmt_bind_param($stmt, "i", $product_id);
            mysqli_stmt_execute($stmt);
            $result_prod = mysqli_stmt_get_result($stmt);
            if ($product = mysqli_fetch_assoc($result_prod)) {
                 $_SESSION['cart'][$product_id] = ['id' => $product_id, 'name' => $product['product_name'], 'price' => $product['price'], 'image' => $product['product_image'], 'quantity' => 1];
            }
        }
        echo json_encode(['status' => 'success', 'message' => 'Product added to bag!', 'cart_count' => count($_SESSION['cart'])]);
    }
    exit;
}

// --- ACTION: report_product ---
if ($action === 'report_product') {
    // 1. Check if user is logged in
    if (!$is_logged_in) {
        echo json_encode(['status' => 'error', 'message' => 'You must be logged in to report an item.']);
        exit;
    }
    
    // 2. Get and sanitize input data, including the new order_id
    $user_id = $_SESSION['user_id'];
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0; // <-- ADDED
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : null;
    
    // 3. Validate that all required data is present
    if ($product_id === 0 || $order_id === 0 || empty($reason)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data provided. Please select a reason.']);
        exit;
    }
    
    // 4. CRITICAL SECURITY CHECK: Verify the user owns the order and the product is in that order.
    $sql_verify = "SELECT 1 FROM orders o JOIN order_items oi ON o.order_id = oi.order_id WHERE o.order_id = ? AND o.user_id = ? AND oi.product_id = ?";
    $stmt_verify = mysqli_prepare($conn, $sql_verify);
    mysqli_stmt_bind_param($stmt_verify, "iii", $order_id, $user_id, $product_id);
    mysqli_stmt_execute($stmt_verify);
    $result_verify = mysqli_stmt_get_result($stmt_verify);
    
    if (mysqli_num_rows($result_verify) == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Authorization failed. You cannot report this item.']);
        mysqli_stmt_close($stmt_verify);
        exit;
    }
    mysqli_stmt_close($stmt_verify);

    // 5. Insert the new report. The unique key in the DB will prevent duplicates.
    $sql_insert = "INSERT INTO product_reports (product_id, user_id, order_id, reason, comment) VALUES (?, ?, ?, ?, ?)";
    $stmt_insert = mysqli_prepare($conn, $sql_insert);
    // Note the new "i" for order_id and the order of parameters
    mysqli_stmt_bind_param($stmt_insert, "iiiss", $product_id, $user_id, $order_id, $reason, $comment);
    
    if (mysqli_stmt_execute($stmt_insert)) {
        // 6. AUTO-DEACTIVATION LOGIC (Optional, but kept from your original code)
        // This logic remains the same as it checks the total number of reports for a product.
        $sql_count = "SELECT COUNT(*) AS report_count FROM product_reports WHERE product_id = ?";
        $stmt_count = mysqli_prepare($conn, $sql_count);
        mysqli_stmt_bind_param($stmt_count, "i", $product_id);
        mysqli_stmt_execute($stmt_count);
        $result_count = mysqli_stmt_get_result($stmt_count);
        $row_count = mysqli_fetch_assoc($result_count);
        
        if ($row_count && $row_count['report_count'] >= 10) {
            $sql_deactivate = "UPDATE products SET status = 'inactive' WHERE product_id = ?";
            $stmt_deactivate = mysqli_prepare($conn, $sql_deactivate);
            mysqli_stmt_bind_param($stmt_deactivate, "i", $product_id);
            mysqli_stmt_execute($stmt_deactivate);
            mysqli_stmt_close($stmt_deactivate);
        }
        mysqli_stmt_close($stmt_count);
        echo json_encode(['status' => 'success', 'message' => 'Report submitted successfully. Thank you!']);
    } else {
        // This error will trigger if the unique key constraint is violated (i.e., user already reported this item from this order).
        echo json_encode(['status' => 'error', 'message' => 'You have already reported this item from this order.']);
    }
    mysqli_stmt_close($stmt_insert);
    exit;
}

// --- ACTION: search suggestions ---
if ($action === 'get_search_suggestions') {
    if (isset($_POST['query']) && !empty(trim($_POST['query']))) {
        $query = trim($_POST['query']);
        $search_term = '%' . $query . '%'; // Prepare term for LIKE query
        $suggestions = [];

        // Query the database for product names that match the search term
        // Using DISTINCT to avoid showing the same product name multiple times
        // Limiting to 5 for a clean UI
        $sql = "SELECT DISTINCT product_name FROM products WHERE product_name LIKE ? AND status = 'active' LIMIT 5";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $search_term);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $suggestions[] = $row['product_name']; // Add each name to the suggestions array
            }
            mysqli_stmt_close($stmt);
        }
        
        // Return the array of suggestions as a JSON object
        echo json_encode($suggestions);
    } else {
        // If the query is empty, return an empty array
        echo json_encode([]);
    }
    exit;
}

// Fallback for unknown actions
echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
?>