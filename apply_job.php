<?php
session_start();
header('Content-Type: application/json');

// --- Helper Functions ---
function readJsonFile($filePath) {
    if (!file_exists($filePath)) return [];
    $data = json_decode(file_get_contents($filePath), true);
    return is_array($data) ? $data : [];
}

function writeJsonFile($filePath, $data) {
    file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

// --- Security and Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$isUserLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$userRole = $_SESSION['user_role'] ?? 'guest';
$userId = $_SESSION['user_id'] ?? null;

if (!$isUserLoggedIn) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to apply.']);
    exit;
}

if ($userRole !== 'jobseeker') {
    echo json_encode(['success' => false, 'message' => 'Only jobseekers can apply for jobs.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$jobId = $input['job_id'] ?? null;

if (empty($jobId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid job ID.']);
    exit;
}

// --- Application Logic ---
$applicationsFilePath = __DIR__ . '/data/applications.json';
$applications = readJsonFile($applicationsFilePath);

// Check if user has already applied for this job
foreach ($applications as $application) {
    if ($application['user_id'] === $userId && $application['job_id'] === $jobId) {
        echo json_encode(['success' => false, 'message' => 'You have already applied for this job.']);
        exit;
    }
}


$newApplication = [
    'application_id' => 'app_' . time() . '_' . rand(1000, 9999),
    'job_id' => $jobId,
    'user_id' => $userId,
    'timestamp' => time(),
    'status' => 'applied' // Initial status
];
array_unshift($applications, $newApplication);
writeJsonFile($applicationsFilePath, $applications);


echo json_encode(['success' => true, 'message' => 'Application submitted successfully!']);

?>