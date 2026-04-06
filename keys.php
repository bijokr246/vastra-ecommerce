<?php
require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

//Razorpay API credentials
define('RAZORPAY_KEY_ID', $_ENV['RAZORPAY_KEY_ID']);
define('RAZORPAY_KEY_SECRET', $_ENV['RAZORPAY_KEY_SECRET']);
?>