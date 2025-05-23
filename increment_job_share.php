<?php

// Define paths and include helpers
require_once __DIR__ . '/admin/includes/config.php'; // For $jobsFilename
require_once __DIR__ . '/admin/includes/job_helpers.php'; // For incrementJobShareCountInJobsJson

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

// Increment the total_shares_count in jobs.json
if (function_exists('incrementJobShareCountInJobsJson')) {
    if (incrementJobShareCountInJobsJson($jobId)) {
        echo json_encode(['success' => true, 'message' => 'Share count incremented successfully in jobs.json.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating share count in jobs.json.']);
    }
} else {
    error_log("Function incrementJobShareCountInJobsJson does not exist.");
    echo json_encode(['success' => false, 'message' => 'Server configuration error for share tracking.']);
}

?>