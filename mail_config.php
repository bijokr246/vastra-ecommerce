<?php

use PHPMailer\PHPMailer\PHPMailer;

// Prevent direct script access and accidental re-definition
if (defined('MAIL_HOST')) {
    return;
}

// --- SMTP Server Settings ---
define('MAIL_HOST', $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com');
define('MAIL_PORT', (int)($_ENV['MAIL_PORT'] ?? 465));
define('MAIL_USERNAME', $_ENV['MAIL_USERNAME']);     
define('MAIL_PASSWORD', $_ENV['MAIL_PASSWORD']);

// Mapping the ENV string ('SMTPS' or 'STARTTLS') to the PHPMailer constant
// Note: We use a fallback logic here for the encryption type
$encryption_type = $_ENV['MAIL_ENCRYPTION'] ?? 'SMTPS';
if ($encryption_type === 'STARTTLS') {
    define('MAIL_ENCRYPTION', PHPMailer::ENCRYPTION_STARTTLS);
} else {
    define('MAIL_ENCRYPTION', PHPMailer::ENCRYPTION_SMTPS); // Default to SMTPS (465)
}

// --- Email "From" Details ---
define('MAIL_FROM_ADDRESS', $_ENV['MAIL_USERNAME']); 
define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME'] ?? 'Vastra Support');

?>