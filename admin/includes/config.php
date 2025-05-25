<?php

// admin/includes/config.php

// Set timezone
date_default_timezone_set('Asia/Kolkata'); // You can change this to your preferred timezone, e.g., 'Asia/Dubai'

// Include helper files if they are essential for config or early setup
require_once __DIR__ . '/log_helpers.php'; // Assuming this defines logging functions used early or globally

// --- Application Environment and Base URL ---
// Define the current environment: 'development' or 'production'
define('APP_ENV', 'development'); // CHANGE TO 'production' ON YOUR LIVE SERVER

// Define the base URL of your application
// IMPORTANT: Replace 'http://localhost/Job_Post/' with the actual URL of your application.
// This is crucial for generating correct links and for API/AJAX calls.
// Ensure it ends with a trailing slash if your other code expects it,
// or use rtrim(APP_BASE_URL, '/') . '/path' as used in other scripts.
if (APP_ENV === 'production') {
    define('APP_BASE_URL', 'https://localhost:8000/'); // REPLACE with your live domain's base URL, e.g., https://www.yourjobsite.com/
} else {
    define('APP_BASE_URL', 'http://localhost:8000/'); // Base URL for local development
}

// --- Error Reporting ---
// Adjust error reporting based on the environment
if (defined('APP_ENV') && APP_ENV === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0); 
    // For production, it's highly recommended to log errors to a file:
    // ini_set('log_errors', 1);
    // ini_set('error_log', __DIR__ . '/../../logs/php_error.log'); // Ensure this path is writable by the web server
}

// --- File Paths ---
// These paths are relative to the `admin/includes/` directory where this config.php resides.
// Using __DIR__ makes them absolute and more reliable.
$usersFilename = __DIR__ . '/../../data/user.json';         // Path to data/user.json
$jobsFilename = __DIR__ . '/../../data/jobs.json';          // Path to data/jobs.json
$viewCounterFile = __DIR__ . '/../../data/view_count.txt';  // Path to data/view_count.txt
$feedbackFilename = __DIR__ . '/../../data/feedback.json';  // Path to data/feedback.json
// Example for a logs directory if you enable file logging in production:
// $logsDirectory = __DIR__ . '/../../logs';


// --- Site Specifics ---
// Your website URL (used in generated messages, meta tags, etc.)
// This might be the same as APP_BASE_URL without the protocol for some uses, or just the domain.
define('SITE_URL', (APP_ENV === 'production' ? 'https://localhost:8000' : 'http://localhost:8000')); // REPLACE with your website's primary domain name
define('SITE_NAME', 'localhost'); // Example site name

// --- API Keys (Store securely, consider environment variables for production) ---
// Example: define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE');
// It's better to load sensitive keys from environment variables or a non-web-accessible config file in production.
// For now, if you have it hardcoded in post_job.php, ensure it's correct there.

// --- Other Global Settings ---
// define('ITEMS_PER_PAGE', 10); // Example for pagination

// You can add other global configurations your application might need here.

?>
