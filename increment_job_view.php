<?php

// Define the path to the job views data file.
// Assumes this script (increment_job_view.php) is in c:\Users\Public\Job_Post\
require_once __DIR__ . '/admin/includes/config.php'; // For $jobsFilename
require_once __DIR__ . '/admin/includes/job_helpers.php'; // For incrementJobViewCountInJobsJson

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

// Increment the total_views_count in jobs.json
if (function_exists('incrementJobViewCountInJobsJson')) {
    if (incrementJobViewCountInJobsJson($jobId)) {
        echo json_encode(['success' => true, 'message' => 'View count incremented successfully in jobs.json.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating view count in jobs.json.']);
    }
} else {
    error_log("Function incrementJobViewCountInJobsJson does not exist.");
    echo json_encode(['success' => false, 'message' => 'Server configuration error for view tracking.']);
}

?>