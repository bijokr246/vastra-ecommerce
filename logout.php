<?php
session_start();

// Unset all session variables
$_SESSION = array();
// session_unset();

session_destroy();

header("Location: index.php");
exit();
?>