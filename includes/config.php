 <?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'legal_platform');

// Razorpay configuration
define('RAZORPAY_KEY_ID', 'rzp_live_jAGVmgwqfTTrPH');
define('RAZORPAY_KEY_SECRET', 'eSUb2lRZwTlZhr7ZuFSp6GmW');

// Site configuration
define('SITE_NAME', 'LegalConnect');
define('SITE_URL', 'http://localhost/legal-platform');

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
