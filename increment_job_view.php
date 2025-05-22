<?php

// Define the path to the job views data file.
// Assumes this script (increment_job_view.php) is in c:\Users\Public\Job_Post\
define('JOB_VIEWS_DATA_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'job_views.json');

header('Content-Type: application/json');

// Ensure the request method is POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Only POST is accepted.']);
    exit;
}

// Get the job_id from the JSON payload sent in the request body.
$input = json_decode(file_get_contents('php://input'), true);
$jobId = $input['job_id'] ?? null;

if (empty($jobId)) {
    echo json_encode(['success' => false, 'message' => 'Job ID is missing.']);
    exit;
}

// Ensure the data directory and file exist, create them if not.
$dataDir = dirname(JOB_VIEWS_DATA_PATH);
if (!is_dir($dataDir)) {
    if (!mkdir($dataDir, 0777, true)) {
        error_log("Failed to create directory: " . $dataDir);
        echo json_encode(['success' => false, 'message' => 'Error initializing view data directory.']);
        exit;
    }
}

if (!file_exists(JOB_VIEWS_DATA_PATH)) {
    // Initialize with an empty JSON object if file doesn't exist.
    if (file_put_contents(JOB_VIEWS_DATA_PATH, json_encode([], JSON_PRETTY_PRINT)) === false) {
        error_log("Failed to create job_views.json at " . JOB_VIEWS_DATA_PATH);
        echo json_encode(['success' => false, 'message' => 'Error initializing view data file.']);
        exit;
    }
}

// Read the current view counts.
$jobViewsJson = file_get_contents(JOB_VIEWS_DATA_PATH);
if ($jobViewsJson === false) {
    error_log("Failed to read job_views.json from " . JOB_VIEWS_DATA_PATH);
    echo json_encode(['success' => false, 'message' => 'Error reading view data.']);
    exit;
}

$jobViews = json_decode($jobViewsJson, true);
if ($jobViews === null && json_last_error() !== JSON_ERROR_NONE) { // Handle JSON decoding errors.
    error_log("Error decoding job_views.json: " . json_last_error_msg() . ". Content: " . $jobViewsJson);
    $jobViews = []; // Reset to empty array if decoding fails to prevent further errors.
}

// Increment the view count for the given job ID.
$jobViews[$jobId] = ($jobViews[$jobId] ?? 0) + 1;

// Write the updated data back to the file with exclusive locking.
if (file_put_contents(JOB_VIEWS_DATA_PATH, json_encode($jobViews, JSON_PRETTY_PRINT), LOCK_EX)) {
    echo json_encode(['success' => true, 'message' => 'View count incremented successfully.']);
} else {
    error_log("Failed to write to job_views.json at " . JOB_VIEWS_DATA_PATH);
    echo json_encode(['success' => false, 'message' => 'Error updating view count.']);
}

?>