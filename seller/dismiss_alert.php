<?php
session_start();

// Ensure the user is logged in before changing the session
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit();
}

// Set the flag in the PHP session
$_SESSION['stockAlertDismissed'] = true;

// Respond with a success message
header('Content-Type: application/json');
echo json_encode(['status' => 'success']);
?>