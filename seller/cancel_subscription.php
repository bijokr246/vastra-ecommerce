<?php
session_start();

// Security check: ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../seller-login.php");
    exit;
}

// Set a user-friendly message to display on the subscription page
$_SESSION['info_message'] = "Your plan purchase has been cancelled.";

// Redirect the user back to the main subscription management page
header("Location: subscription.php");
exit;