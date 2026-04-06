<?php
session_start();

// Clear the temporary order details from the session
if (isset($_SESSION['pending_order_details'])) {
    unset($_SESSION['pending_order_details']);
}

// Redirect to the failure page with a clear reason
header("Location: failure.php?reason=" . urlencode("Payment was canceled."));
exit();