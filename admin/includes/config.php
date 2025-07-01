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
// IMPORTANT: Replace 'http://localhost:8000' with the actual URL of your application.
// This is crucial for generating correct links and for API/AJAX calls.
if (APP_ENV === 'production') {
    define('APP_BASE_URL', 'https://jobhunt.top/'); // REPLACE with your live domain's base URL
} else {
    define('APP_BASE_URL', 'http://jobhunt.top/'); // Base URL for local development
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


// --- Cookie Names (Centralized) ---
define('COOKIE_CONSENT_STATUS_NAME', 'cookie_consent_status');
define('USER_INTERESTS_COOKIE_NAME', 'user_job_interests');
define('USER_VIEWED_JOB_IDS_COOKIE_NAME', 'user_viewed_job_ids');
define('USER_UNIQUE_ID_COOKIE_NAME', 'user_unique_site_id'); // For unique user identification
define('MAX_USER_INTERESTS', 5); // Example, if used elsewhere for interests

// --- Site Specifics ---
// Your website URL (used in generated messages, meta tags, etc.)
define('SITE_URL', rtrim(APP_BASE_URL, '/')); // Use the base URL without the trailing slash
define('SITE_NAME', 'Job Hunt'); // Example site name

// --- API Keys (Store securely) ---
// --- Google Gemini API Key ---
// Replace 'YOUR_ACTUAL_API_KEY_HERE' with the key you generated from Google AI Studio.
// IMPORTANT: Keep this key secret. Do not commit it to public repositories.
define('GEMINI_API_KEY', 'AIzaSyCWoj7th8DArYw7PGf83JAVcYsXBJHFjAk');


// --- Other Global Settings ---
// define('ITEMS_PER_PAGE', 10); // Example for pagination

// You can add other global configurations your application might need here.

?>
