<?php
session_start();
include "../config.php";

// --- 1. SECURITY AND AUTHENTICATION ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ../seller-login.php');
    exit();
}
$user_id = $_SESSION['user_id'];

// --- 2. VALIDATE INPUT ---
// Check for a valid shop ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: my-shops.php?error=not_found');
    exit();
}
$shop_id = (int)$_GET['id'];

// Check for a valid action ('activate' or 'deactivate')
$action = $_GET['action'] ?? '';
if (!in_array($action, ['activate', 'deactivate'])) {
    header('Location: my-shops.php?error=invalid_action');
    exit();
}

// --- 3. VERIFY OWNERSHIP (CRITICAL SECURITY CHECK) ---
$stmt_verify = $conn->prepare("SELECT shop_id FROM shops WHERE shop_id = ? AND user_id = ?");
$stmt_verify->bind_param("ii", $shop_id, $user_id);
$stmt_verify->execute();
if ($stmt_verify->get_result()->num_rows === 0) {
    header('Location: my-shops.php?error=access_denied');
    exit();
}
$stmt_verify->close();


// --- 4. DATABASE TRANSACTION TO TOGGLE STATUS ---
$conn->begin_transaction();

try {
    if ($action === 'deactivate') {
        // Step 1: Deactivate the shop
        $stmt_shop = $conn->prepare("UPDATE shops SET shop_status = 'inactive' WHERE shop_id = ?");
        $stmt_shop->bind_param("i", $shop_id);
        $stmt_shop->execute();
        $stmt_shop->close();

        // Step 2: Deactivate all products in that shop
        $stmt_products = $conn->prepare("UPDATE products SET status = 'inactive' WHERE shop_id = ?");
        $stmt_products->bind_param("i", $shop_id);
        $stmt_products->execute();
        $stmt_products->close();
    } 
    elseif ($action === 'activate') {
        // Step 1: Activate the shop
        // Note: We are only activating the shop. Products remain as they are.
        // This gives the seller control to re-activate products individually.
        $stmt_shop = $conn->prepare("UPDATE shops SET shop_status = 'active' WHERE shop_id = ?");
        $stmt_shop->bind_param("i", $shop_id);
        $stmt_shop->execute();
        $stmt_shop->close();
    }

    // If all queries were successful, commit the transaction
    $conn->commit();
    
    // Redirect with the correct success message
    if ($action === 'activate') {
        header('Location: my-shops.php?success=activated');
    } else {
        header('Location: my-shops.php?success=deactivated');
    }

} catch (mysqli_sql_exception $exception) {
    // If any query fails, roll back everything
    $conn->rollback();
    header('Location: my-shops.php?error=action_failed');
}

$conn->close();
exit();
?>