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
$jobsDataFilename = __DIR__ . '/data/jobs.json'; // Path to the main jobs data

// Basic input validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['job_id']) || empty(trim($_POST['job_id']))) {
    // Log this attempt before exiting
    @file_put_contents(__DIR__ . '/data/app_activity.log', "[" . date('Y-m-d H:i:s') . "] [TRACK_VIEW_WARNING] Invalid request to track_job_view.php. Job ID missing or wrong method. Request: " . print_r($_REQUEST, true) . PHP_EOL, FILE_APPEND | LOCK_EX);
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
                // Log to system error log if app_activity.log itself can't be written to initially
                @file_put_contents(__DIR__ . '/data/app_activity.log', "[" . date('Y-m-d H:i:s') . "] [TRACK_VIEW_ERROR] Could not create data directory for job views: " . $dir . PHP_EOL, FILE_APPEND | LOCK_EX);
                return []; // Cannot proceed if directory can't be made
            }
        }
        // Attempt to create the file if it doesn't exist with an empty JSON object
        if (file_put_contents($filename, '{}', LOCK_EX) === false) {
            @file_put_contents(__DIR__ . '/data/app_activity.log', "[" . date('Y-m-d H:i:s') . "] [TRACK_VIEW_ERROR] Could not create initial job views file: " . $filename . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
        return []; 
    }
    $jsonData = file_get_contents($filename);
    if ($jsonData === false) {
        @file_put_contents(__DIR__ . '/data/app_activity.log', "[" . date('Y-m-d H:i:s') . "] [TRACK_VIEW_ERROR] Error reading job views file: " . $filename . PHP_EOL, FILE_APPEND | LOCK_EX);
        return []; // Return empty on read error
    }
    $views = json_decode($jsonData, true);
    if ($views === null && json_last_error() !== JSON_ERROR_NONE) {
        @file_put_contents(__DIR__ . '/data/app_activity.log', "[" . date('Y-m-d H:i:s') . "] [TRACK_VIEW_ERROR] Error decoding job_views.json: " . json_last_error_msg() . ". File content (first 1000 chars): " . substr($jsonData, 0, 1000) . PHP_EOL, FILE_APPEND | LOCK_EX);
        return []; // Return empty if JSON is malformed
    }
    return is_array($views) ? $views : []; 
}

function saveJobViews($views, $filename) {
    $jsonData = json_encode($views, JSON_PRETTY_PRINT);
    if ($jsonData === false) {
        @file_put_contents(__DIR__ . '/data/app_activity.log', "[" . date('Y-m-d H:i:s') . "] [TRACK_VIEW_ERROR] Error encoding job views to JSON." . PHP_EOL, FILE_APPEND | LOCK_EX);
        return false;
    }
    // Ensure data directory exists
    $dir = dirname($filename);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            @file_put_contents(__DIR__ . '/data/app_activity.log', "[" . date('Y-m-d H:i:s') . "] [TRACK_VIEW_ERROR] Error creating data directory for job views: " . $dir . PHP_EOL, FILE_APPEND | LOCK_EX);
            return false;
        }
    }
    if (file_put_contents($filename, $jsonData, LOCK_EX) === false) {
        @file_put_contents(__DIR__ . '/data/app_activity.log', "[" . date('Y-m-d H:i:s') . "] [TRACK_VIEW_ERROR] Error writing job views to file: " . $filename . PHP_EOL, FILE_APPEND | LOCK_EX);
        return false;
    }
    return true;
}

function loadJobsData($filename) {
    if (!file_exists($filename)) {
        @file_put_contents(__DIR__ . '/data/app_activity.log', "[" . date('Y-m-d H:i:s') . "] [TRACK_VIEW_ERROR] jobs.json not found at: " . $filename . PHP_EOL, FILE_APPEND | LOCK_EX);
        return [];
    }
    $jsonData = file_get_contents($filename);
    if ($jsonData === false) {
        @file_put_contents(__DIR__ . '/data/app_activity.log', "[" . date('Y-m-d H:i:s') . "] [TRACK_VIEW_ERROR] Error reading jobs.json file: " . $filename . PHP_EOL, FILE_APPEND | LOCK_EX);
        return [];
    }
    $jobs = json_decode($jsonData, true);
    if ($jobs === null && json_last_error() !== JSON_ERROR_NONE) {
        @file_put_contents(__DIR__ . '/data/app_activity.log', "[" . date('Y-m-d H:i:s') . "] [TRACK_VIEW_ERROR] Error decoding jobs.json: " . json_last_error_msg() . PHP_EOL, FILE_APPEND | LOCK_EX);
        return [];
    }
    return is_array($jobs) ? $jobs : [];
}

function saveJobsData($jobs, $filename) {
    $jsonData = json_encode($jobs, JSON_PRETTY_PRINT);
    if ($jsonData === false) return false;
    return file_put_contents($filename, $jsonData, LOCK_EX) !== false;
}
// --- End Helper functions ---

$jobViews = loadJobViews($jobViewsFilename);

// Increment view count for the job
if (!isset($jobViews[$jobId])) {
    $jobViews[$jobId] = 0;
}
$jobViews[$jobId]++;

if (!saveJobViews($jobViews, $jobViewsFilename)) {
    http_response_code(500); // Internal Server Error
    @file_put_contents(__DIR__ . '/data/app_activity.log', "[" . date('Y-m-d H:i:s') . "] [TRACK_VIEW_FAILURE] Failed to save job_views.json for job_id: '$jobId'." . PHP_EOL, FILE_APPEND | LOCK_EX);
    echo json_encode(['success' => false, 'message' => 'Failed to track view (views file error).']);
    exit;
}

// Now update jobs.json
$allJobs = loadJobsData($jobsDataFilename);
$jobUpdatedInJobsJson = false;
if (!empty($allJobs)) {
    foreach ($allJobs as &$job) { // Use reference to modify directly
        if (isset($job['id']) && (string)$job['id'] === (string)$jobId) {
            $job['views'] = $jobViews[$jobId]; // Update the views count
            $jobUpdatedInJobsJson = true;
            break;
        }
    }
    unset($job); // Unset reference

    if ($jobUpdatedInJobsJson && saveJobsData($allJobs, $jobsDataFilename)) {
        @file_put_contents(__DIR__ . '/data/app_activity.log', "[" . date('Y-m-d H:i:s') . "] [TRACK_VIEW_SUCCESS] Job view tracked for job_id: '$jobId'. New count: " . $jobViews[$jobId] . ". Updated jobs.json." . PHP_EOL, FILE_APPEND | LOCK_EX);
        echo json_encode(['success' => true, 'message' => 'View tracked successfully.', 'job_id' => $jobId, 'new_count' => $jobViews[$jobId]]);
    } else {
        http_response_code(500);
        @file_put_contents(__DIR__ . '/data/app_activity.log', "[" . date('Y-m-d H:i:s') . "] [TRACK_VIEW_FAILURE] Failed to update jobs.json for job_id: '$jobId' (jobUpdated: " . ($jobUpdatedInJobsJson ? 'yes' : 'no') . ")." . PHP_EOL, FILE_APPEND | LOCK_EX);
        echo json_encode(['success' => false, 'message' => 'Failed to track view (jobs data file error).']);
    }
} else {
    http_response_code(500);
    @file_put_contents(__DIR__ . '/data/app_activity.log', "[" . date('Y-m-d H:i:s') . "] [TRACK_VIEW_FAILURE] jobs.json was empty or could not be loaded for job_id: '$jobId'." . PHP_EOL, FILE_APPEND | LOCK_EX);
    echo json_encode(['success' => false, 'message' => 'Failed to track view (jobs data not found).']);
}
exit;
?>
