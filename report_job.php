<?php
header('Content-Type: application/json');

// Include configuration for file path if needed, though not strictly for this script yet
// require_once __DIR__ . '/admin/includes/config.php';

$reportedJobsFilename = __DIR__ . '/data/reported_jobs.json';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$jobId = $input['job_id'] ?? null;
$reporterName = trim($input['reporter_name'] ?? '');
$reporterEmail = trim($input['reporter_email'] ?? '');
$reason = trim($input['reason'] ?? '');

if (empty($jobId)) {
    echo json_encode(['success' => false, 'message' => 'Job ID is missing. Cannot submit report.']);
    exit;
}

if (empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Reason for reporting is required.']);
    exit;
}

// Optional: Validate email format if provided
if (!empty($reporterEmail) && !filter_var($reporterEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format provided.']);
    exit;
}

$reports = [];
if (file_exists($reportedJobsFilename)) {
    $jsonData = file_get_contents($reportedJobsFilename);
    if ($jsonData) {
        $reports = json_decode($jsonData, true);
        if (!is_array($reports)) {
            $reports = []; // Reset if JSON is invalid
        }
    }
}

$newReport = [
    'report_id' => time() . '_' . bin2hex(random_bytes(4)),
    'job_id' => $jobId,
    'reporter_name' => htmlspecialchars($reporterName, ENT_QUOTES, 'UTF-8'),
    'reporter_email' => htmlspecialchars($reporterEmail, ENT_QUOTES, 'UTF-8'),
    'reason' => htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'),
    'report_timestamp' => time(),
    'status' => 'pending_review' // Initial status
];

array_unshift($reports, $newReport); // Add new report to the beginning

if (file_put_contents($reportedJobsFilename, json_encode($reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX)) {
    echo json_encode(['success' => true, 'message' => 'Report submitted successfully.']);
} else {
    error_log("Failed to write to reported_jobs.json");
    echo json_encode(['success' => false, 'message' => 'Could not save the report. Please try again later.']);
}
?>