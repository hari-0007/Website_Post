<?php
header('Content-Type: application/json');

// Include configuration for file path if needed, though not strictly for this script yet
// require_once __DIR__ . '/admin/includes/config.php';

$reportedJobsFilename = __DIR__ . '/data/reported_jobs.json';

// --- Google reCAPTCHA Verification ---
function verifyRecaptcha($recaptchaResponse, $secretKey) { // Consider moving this to a shared include file
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret'   => $secretKey,
        'response' => $recaptchaResponse,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null // Optional
    ];
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'timeout' => 5 // 5 second timeout for the request
        ],
    ];
    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context); // Use @ to suppress errors if request fails, check $result

    if ($result === FALSE) {
        // Log error or handle failure to connect to Google's API
        error_log("reCAPTCHA verification request failed for report_job form.");
        return false;
    }
    $responseKeys = json_decode($result, true);
    // error_log("reCAPTCHA verification response for report_job: " . print_r($responseKeys, true)); // For debugging
    return ($responseKeys && isset($responseKeys["success"]) && $responseKeys["success"]);
}

$input_data = json_decode(file_get_contents('php://input'), true);
$recaptchaSecretKey = '6LcF92ErAAAAAHO38liOFIgrapN-KriFuVxK3zwq'; // <-- REPLACE WITH YOUR ACTUAL SECRET KEY
$userRecaptchaResponse = $input_data['g-recaptcha-response'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyRecaptcha($userRecaptchaResponse, $recaptchaSecretKey)) {
    echo json_encode(['success' => false, 'message' => 'reCAPTCHA verification failed. Please try again.', 'captcha_error' => true]);
    exit;
}
// --- End Google reCAPTCHA Verification ---


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// $input_data is already decoded from above
$jobId = $input_data['job_id'] ?? null;
$reporterName = trim($input_data['reporter_name'] ?? '');
$reporterEmail = trim($input_data['reporter_email'] ?? '');
$reason = trim($input_data['reason'] ?? '');

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
            error_log("Report Job Error: Could not decode reported_jobs.json or file is empty/invalid.");
        }
    }
} else {
    // Ensure the data directory exists if reported_jobs.json didn't exist
    $dir = dirname($reportedJobsFilename);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            error_log("Report Job Error: Could not create data directory: " . $dir);
            echo json_encode([
                'success' => false,
                'message' => 'Error submitting report. Please try again later.'
            ]);
            exit;
        }
    }
}

$newReport = [
    'report_id' => time() . '_' . bin2hex(random_bytes(4)), // Simple unique ID
    'job_id' => $jobId,
    'reporter_name' => htmlspecialchars($reporterName, ENT_QUOTES, 'UTF-8'), // Sanitize output
    'reporter_email' => htmlspecialchars($reporterEmail, ENT_QUOTES, 'UTF-8'), // Sanitize output
    'reason' => htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'), // Sanitize output
    'report_timestamp' => time(), // Unix timestamp
    'status' => 'pending_review' // Initial status
];

array_unshift($reports, $newReport); // Add new report to the beginning

$jsonDataToSave = json_encode($reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($jsonDataToSave === false) {
    error_log("Report Job Error: Could not encode report data to JSON: " . json_last_error_msg());
    echo json_encode([
        'success' => false,
        'message' => 'Error submitting report. Please try again later.'
    ]);
    exit;
}

// Use LOCK_EX to prevent concurrent writes from corrupting the file
if (file_put_contents($reportedJobsFilename, $jsonDataToSave, LOCK_EX) === false) {
    error_log("Report Job Error: Could not write report data to file: " . $reportedJobsFilename);
    echo json_encode(['success' => false, 'message' => 'Could not save the report. Please try again later.']);
} else {
    echo json_encode(['success' => true, 'message' => 'Report submitted successfully.']);
}
?>
