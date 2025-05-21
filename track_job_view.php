<?php
// File: track_job_view.php (Place in your project's root or a suitable public directory)

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow requests from any origin, adjust if needed for security
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Handle preflight request for CORS
    exit(0);
}

// Define the path to the job views data file
$jobViewsFilename = __DIR__ . '/data/job_views.json'; // Assumes this script is in the project root

// Basic input validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['job_id']) || empty(trim($_POST['job_id']))) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid request. Job ID is required and method must be POST.']);
    exit;
}

$jobId = trim($_POST['job_id']);

// --- Helper functions for loading/saving job views (could be in a shared helper if used elsewhere) ---
function loadJobViews($filename) {
    if (!file_exists($filename)) {
        // Ensure data directory exists before attempting to create the file
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) { // Create data directory if it doesn't exist
                error_log("Error: Could not create data directory for job views: " . $dir . " in loadJobViews.");
                return []; // Cannot proceed if directory can't be made
            }
        }
        // Attempt to create the file if it doesn't exist with an empty JSON object
        if (file_put_contents($filename, '{}', LOCK_EX) === false) {
            error_log("Error: Could not create initial job views file: " . $filename . " in loadJobViews.");
        }
        return []; 
    }
    $jsonData = file_get_contents($filename);
    if ($jsonData === false) {
        error_log("Error reading job views file: " . $filename);
        return []; // Return empty on read error
    }
    $views = json_decode($jsonData, true);
    if ($views === null && json_last_error() !== JSON_ERROR_NONE) {
        error_log("Error decoding job_views.json: " . json_last_error_msg() . ". File content: " . $jsonData);
        return []; // Return empty if JSON is malformed
    }
    return is_array($views) ? $views : []; 
}

function saveJobViews($views, $filename) {
    $jsonData = json_encode($views, JSON_PRETTY_PRINT);
    if ($jsonData === false) {
        error_log("Error encoding job views to JSON.");
        return false;
    }
    // Ensure data directory exists
    $dir = dirname($filename);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            error_log("Error creating data directory for job views: " . $dir);
            return false;
        }
    }
    if (file_put_contents($filename, $jsonData, LOCK_EX) === false) {
        error_log("Error writing job views to file: " . $filename);
        return false;
    }
    return true;
}
// --- End Helper functions ---

$jobViews = loadJobViews($jobViewsFilename);

// Increment view count for the job
if (!isset($jobViews[$jobId])) {
    $jobViews[$jobId] = 0;
}
$jobViews[$jobId]++;

if (saveJobViews($jobViews, $jobViewsFilename)) {
       echo json_encode(['success' => true, 'message' => 'View tracked successfully.', 'job_id' => $jobId, 'new_count' => $jobViews[$jobId]]);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Failed to track view due to a server error.']);
}
exit;
?>
