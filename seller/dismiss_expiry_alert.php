<?php
session_start();

// Set a session variable to indicate the user has dismissed the alert for this session.
$_SESSION['expiryAlertDismissed'] = true;

// Respond with a success status for the JavaScript fetch API.
header('Content-Type: application/json');
echo json_encode(['status' => 'success']);