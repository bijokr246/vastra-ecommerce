<?php
date_default_timezone_set('Asia/Kolkata');

$conn = mysqli_connect("localhost", "root", "");
mysqli_select_db($conn, "vastra");

if(!$conn){
    die("Connection failed");
}

require_once __DIR__ . '/vendor/autoload.php';

use Razorpay\Api\Api;

include "keys.php";

$razorpayApi = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
?>