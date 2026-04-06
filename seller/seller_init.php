<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . "/../config.php";

// --- 1. SECURITY AND AUTHENTICATION ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ../seller-login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);

// --- 2. AUTOMATICALLY UPDATE EXPIRED SUBSCRIPTIONS ---
$stmt_update = $conn->prepare(
    "UPDATE seller_subscriptions SET status = 'expired' 
     WHERE user_id = ? AND status = 'active' AND end_date < NOW()"
);
$stmt_update->bind_param("i", $user_id);
$stmt_update->execute();
$stmt_update->close();


// --- 3. FETCH CURRENT SUBSCRIPTION STATUS ---
$subscription_info = null;
$days_remaining = null;
$is_subscribed = false;

$stmt_sub = $conn->prepare(
    "SELECT ss.end_date, sp.plan_name 
     FROM seller_subscriptions ss 
     JOIN subscription_plans sp ON ss.plan_id = sp.plan_id 
     WHERE ss.user_id = ? AND ss.status = 'active' 
     ORDER BY ss.end_date DESC LIMIT 1"
);
$stmt_sub->bind_param("i", $user_id);
$stmt_sub->execute();
$subscription_result = $stmt_sub->get_result();

if ($subscription_result->num_rows > 0) {
    $is_subscribed = true;
    $subscription_info = $subscription_result->fetch_assoc();
    $end_date = new DateTime($subscription_info['end_date']);
    $today = new DateTime();
    $interval = $today->diff($end_date);
    $days_remaining = $interval->invert ? 0 : $interval->days; // Handles expired dates correctly
    if($days_remaining == 0 && $today->format('Y-m-d') > $end_date->format('Y-m-d')) {
        $is_subscribed = false;
    }
} else {
    $days_remaining = 0;
}
$stmt_sub->close();


// --- 4. ENFORCE ACCESS CONTROL ---
$allowed_pages = ['dashboard.php', 'subscription.php', 'logout.php'];
if (!$is_subscribed && !in_array($current_page, $allowed_pages)) {
    $_SESSION['error_message'] = "Your subscription has expired. Please renew to access this page.";
    header('Location: subscription.php');
    exit();
}

// --- 5. DEFINE THRESHOLDS FOR WARNINGS ---
$expiry_warning_threshold = 7; 
$is_expired = !$is_subscribed && $days_remaining === 0;
$show_expiry_warning = ($is_subscribed && $days_remaining !== null && $days_remaining <= $expiry_warning_threshold);
$show_expiry_alert_popup = ($show_expiry_warning || $is_expired) && !isset($_SESSION['expiryAlertDismissed']);

