<?php
session_start();
include "../config.php";

// --- 1. SECURITY AND AUTHENTICATION ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ../seller-login.php');
    exit();
}
$user_id = $_SESSION['user_id'];

// --- 2. FETCH CURRENT SUBSCRIPTION ---
$current_plan = null;
$days_remaining = null;

$stmt_current = $conn->prepare(
    "SELECT ss.start_date, ss.end_date, sp.plan_name 
     FROM seller_subscriptions ss 
     JOIN subscription_plans sp ON ss.plan_id = sp.plan_id 
     WHERE ss.user_id = ? AND ss.status = 'active' 
     ORDER BY ss.end_date DESC LIMIT 1"
);
$stmt_current->bind_param("i", $user_id);
$stmt_current->execute();
$result_current = $stmt_current->get_result();
if ($result_current->num_rows > 0) {
    $current_plan = $result_current->fetch_assoc();
    $end_date = new DateTime($current_plan['end_date']);
    $today = new DateTime();
    $days_remaining = ($end_date > $today) ? $today->diff($end_date)->days : 0;
}
$stmt_current->close();

// --- 3. FETCH AVAILABLE PLANS FOR RENEWAL ---
$available_plans = [];
$stmt_available = $conn->prepare("SELECT plan_id, plan_name, duration_days, price FROM subscription_plans WHERE price > 0 AND is_active = 1 ORDER BY price ASC");
$stmt_available->execute();
$available_plans = $stmt_available->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_available->close();
// $conn->close();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Subscription | Vastra</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <style>
    .plan-card {
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        background-color: #fff;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .plan-card.current {
        background-color: #ffffffff;
        border-left: 5px solid var(--primary);
    }
    .plan-card h3 { margin-top: 0; color: #333; }
    .plan-details { list-style: none; padding: 0; margin: 15px 0; }
    .plan-details li { margin-bottom: 8px; color: #555; }
    .plan-details li i { color: #28a745; margin-right: 8px; }
    .plan-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
    .plan-card .price { font-size: 2rem; font-weight: bold; color: var(--primary); margin: 10px 0; }
    .plan-card .duration { color: #6c757d; }
  </style>
</head>
<body>
  <div class="sidebar"><?php require "sidebar.php"; ?></div>
  <div class="main">
    <div class="header"><h1>My Subscription</h1></div>

    <div class="content">
        <h2>Current Plan</h2>
        <?php if ($current_plan): ?>
            <div class="plan-card current">
                <h3><?php echo htmlspecialchars($current_plan['plan_name']); ?></h3>
                <ul class="plan-details">
                    <li><i class="fas fa-play-circle"></i> Started On: <?php echo date('M d, Y', strtotime($current_plan['start_date'])); ?></li>
                    <li><i class="fas fa-stop-circle"></i> Expires On: <?php echo date('M d, Y', strtotime($current_plan['end_date'])); ?></li>
                    <li><i class="fas fa-hourglass-half"></i> 
                        <?php if ($days_remaining > 0): ?>
                            Time Remaining: <strong><?php echo $days_remaining; ?> day<?php echo $days_remaining != 1 ? 's' : ''; ?></strong>
                        <?php else: ?>
                            Status: <strong style="color: #dc3545;">Expired</strong>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        <?php else: ?>
            <div class="message error">You do not have an active subscription. Please choose a plan below to start selling.</div>
        <?php endif; ?>

        <h2>Renew or Upgrade Your Plan</h2>
        <div class="plan-grid">
            <?php foreach ($available_plans as $plan): ?>
            <div class="plan-card">
                <h3><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                <p class="price">₹<?php echo number_format($plan['price']); ?></p>
                <p class="duration"><?php echo htmlspecialchars($plan['duration_days']); ?> Days Access</p>
                <a href="checkout.php?plan_id=<?php echo $plan['plan_id']; ?>" class="btn btn-primary">Choose Plan</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
  </div>
</body>
</html>