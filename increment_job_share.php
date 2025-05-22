<?php

// Define the path to the job shares data file.
define('JOB_SHARES_DATA_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'job_shares.json');

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
$dataDir = dirname(JOB_SHARES_DATA_PATH);
if (!is_dir($dataDir)) {
    if (!mkdir($dataDir, 0777, true)) {
        error_log("Failed to create directory for shares: " . $dataDir);
        echo json_encode(['success' => false, 'message' => 'Error initializing share data directory.']);
        exit;
    }
}

if (!file_exists(JOB_SHARES_DATA_PATH)) {
    // Initialize with an empty JSON object if file doesn't exist.
    if (file_put_contents(JOB_SHARES_DATA_PATH, json_encode([], JSON_PRETTY_PRINT)) === false) {
        error_log("Failed to create job_shares.json at " . JOB_SHARES_DATA_PATH);
        echo json_encode(['success' => false, 'message' => 'Error initializing share data file.']);
        exit;
    }
}

// Read the current share counts.
$jobSharesJson = file_get_contents(JOB_SHARES_DATA_PATH);
if ($jobSharesJson === false) {
    error_log("Failed to read job_shares.json from " . JOB_SHARES_DATA_PATH);
    echo json_encode(['success' => false, 'message' => 'Error reading share data.']);
    exit;
}

$jobShares = json_decode($jobSharesJson, true);
if ($jobShares === null && json_last_error() !== JSON_ERROR_NONE) { // Handle JSON decoding errors.
    error_log("Error decoding job_shares.json: " . json_last_error_msg() . ". Content: " . $jobSharesJson);
    $jobShares = []; // Reset to empty array if decoding fails.
}

// Increment the share count for the given job ID.
$jobShares[$jobId] = ($jobShares[$jobId] ?? 0) + 1;

// Write the updated data back to the file with exclusive locking.
if (file_put_contents(JOB_SHARES_DATA_PATH, json_encode($jobShares, JSON_PRETTY_PRINT), LOCK_EX)) {
    echo json_encode(['success' => true, 'message' => 'Share count incremented successfully.']);
} else {
    error_log("Failed to write to job_shares.json at " . JOB_SHARES_DATA_PATH);
    echo json_encode(['success' => false, 'message' => 'Error updating share count.']);
}

?>