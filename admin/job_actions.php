<?php
session_start();

// Define the path to your jobs data file using __DIR__ for robustness
define('JOBS_FILE_PATH', __DIR__ . '/../data/jobs.json');
// define('OPENAI_API_KEY', 'your_openai_api_key_here'); // Example: For a real AI service

// --- Helper Functions ---

/**
 * Loads jobs from the JSON file.
 * @return array An array of jobs, or an empty array on failure.
 */
function loadJobs() {
    if (!file_exists(JOBS_FILE_PATH)) {
        return [];
    }
    $json = file_get_contents(JOBS_FILE_PATH);
    if ($json === false) {
        return []; // Or handle error more explicitly
    }
    $jobs = json_decode($json, true);
    return is_array($jobs) ? $jobs : [];
}

/**
 * Saves jobs to the JSON file.
 * @param array $jobs The array of jobs to save.
 * @return bool True on success, false on failure.
 */
function saveJobs(array $jobs) {
    $json = json_encode($jobs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false; // JSON encoding error
    }
    if (file_put_contents(JOBS_FILE_PATH, $json) === false) {
        // Attempt to create the directory if it doesn't exist
        $dir = dirname(JOBS_FILE_PATH);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) { // Check if mkdir failed
                 error_log('Failed to create directory: ' . $dir);
                 return false;
            }
        }
        // Try saving again after ensuring directory exists
        if (file_put_contents(JOBS_FILE_PATH, $json) === false) {
            error_log('Failed to write to jobs file: ' . JOBS_FILE_PATH);
            return false;
        }
    }
    return true;
}

/**
 * Generates a unique ID for a new job.
 * @return string A unique ID.
 */
function generateUniqueId() {
    return uniqid('job_', true);
}

/**
 * Generates an AI summary for job details.
 * (Mock implementation - replace with actual AI API call)
 * @param array $jobDetails Associative array of job details.
 * @return array ['success' => bool, 'ai_summary' => string (if success), 'error' => string (if failure)]
 */
function generateAISummary(array $jobDetails) {
    // Basic validation
    if (empty($jobDetails['title'])) {
        return ['success' => false, 'error' => 'Job title is required to generate AI summary.'];
    }

    // Construct a simple prompt/summary
    $summaryParts = [];
    $summaryParts[] = "We are looking for a talented " . htmlspecialchars($jobDetails['title'] ?? 'individual');
    if (!empty($jobDetails['company'])) {
        $summaryParts[] = "to join our team at " . htmlspecialchars($jobDetails['company']) . ".";
    } else {
        $summaryParts[] = "to join our team.";
    }
    if (!empty($jobDetails['location'])) {
        $summaryParts[] = "The role is based in " . htmlspecialchars($jobDetails['location']) . ".";
    }
    $summaryParts[] = "This is a " . htmlspecialchars(strtolower($jobDetails['type'] ?? 'challenging')) . " position.";
    if (!empty($jobDetails['experience']) && $jobDetails['experience'] !== '0') {
        $summaryParts[] = "Ideal candidates will have " . htmlspecialchars($jobDetails['experience']) . " of experience.";
    }
    if (!empty($jobDetails['description'])) {
        $descriptionPreview = substr(strip_tags($jobDetails['description']), 0, 150);
        $summaryParts[] = "Key responsibilities will involve: " . htmlspecialchars($descriptionPreview) . (strlen($jobDetails['description']) > 150 ? "..." : ".");
    }
    if (!empty($jobDetails['salary']) && strtolower($jobDetails['salary']) !== 'not disclosed') {
        $summaryParts[] = "The offered salary is " . htmlspecialchars($jobDetails['salary']) . ".";
    }
    $summaryParts[] = "If you are passionate and meet the criteria, we encourage you to apply.";

    // In a real scenario, you would send these details to an AI API.
    // e.g., callOpenAI($prompt, OPENAI_API_KEY);
    // For now, we just concatenate them.
    $generatedSummary = implode(" ", $summaryParts);

    return ['success' => true, 'ai_summary' => $generatedSummary];
}


// --- Request Handling ---

// Simplified admin check for demonstration. Implement robust authentication.
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized access. Please log in.']);
        exit;
    } else {
        $_SESSION['admin_status'] = ['type' => 'error', 'message' => 'Please log in to perform this action.'];
        header('Location: dashboard.php'); // Or login.php
        exit;
    }
}


$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$inputData = [];
$action = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if content type is JSON
    if (isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $jsonPayload = file_get_contents('php://input');
        $inputData = json_decode($jsonPayload, true) ?: [];
    } else {
        // Assume form-data
        $inputData = $_POST;
    }
    $action = $inputData['action'] ?? $_POST['action'] ?? ''; // Get action from payload or POST
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $inputData = $_GET;
    $action = $_GET['action'] ?? '';
}

if ($isAjax) {
    header('Content-Type: application/json');
}

switch ($action) {
    case 'generate_ai_summary_for_new_job':
    case 'regenerate_summary': // Handles both new job summary and edit job summary regeneration
        if (!$isAjax) {
            echo json_encode(['success' => false, 'error' => 'Invalid request method for summary generation.']);
            exit;
        }
        // Extract details needed for summary generation
        $jobDetailsForSummary = [
            'title'       => $inputData['title'] ?? '',
            'company'     => $inputData['company'] ?? '',
            'location'    => $inputData['location'] ?? '',
            'description' => $inputData['description'] ?? '',
            'experience'  => $inputData['experience'] ?? '',
            'type'        => $inputData['type'] ?? '',
            'salary'      => $inputData['salary'] ?? '',
        ];
        $summaryResult = generateAISummary($jobDetailsForSummary);
        echo json_encode($summaryResult);
        break;

    case 'save_new_job':
        if (!$isAjax || $_SERVER['REQUEST_METHOD'] !== 'POST') {
             echo json_encode(['success' => false, 'error' => 'Invalid request method for saving job.']);
            exit;
        }
        // Validate required fields
        if (empty($inputData['title'])) {
            echo json_encode(['success' => false, 'error' => 'Job title is required.']);
            exit;
        }
        if (empty($inputData['phones']) && empty($inputData['emails'])) {
            echo json_encode(['success' => false, 'error' => 'At least one contact method (phone or email) is required.']);
            exit;
        }

        $jobs = loadJobs();
        $newJob = [
            'id'                => generateUniqueId(),
            'title'             => trim($inputData['title']),
            'company'           => trim($inputData['company'] ?? ''),
            'location'          => trim($inputData['location'] ?? ''),
            'description'       => trim($inputData['description'] ?? ''),
            'experience'        => trim($inputData['experience'] ?? '0'),
            'type'              => trim($inputData['type'] ?? 'Full Time'),
            'salary'            => trim($inputData['salary'] ?? 'Not Disclosed'),
            'phones'            => trim($inputData['phones'] ?? ''),
            'emails'            => trim($inputData['emails'] ?? ''),
            'vacant_positions'  => (int)($inputData['vacant_positions'] ?? 1),
            'ai_summary'        => trim($inputData['ai_summary'] ?? ''),
            'posted_on'         => date('Y-m-d H:i:s'),
            'posted_on_unix_ts' => time(),
            // 'posted_by' => $_SESSION['admin_username'] ?? 'admin' // Optional: track who posted
        ];

        $jobs[] = $newJob;

        if (saveJobs($jobs)) {
            echo json_encode(['success' => true, 'message' => 'Job posted successfully!']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save job. Check server logs.']);
        }
        break;

    case 'save_job': // For editing existing jobs (traditional form post or AJAX)
        $jobId = $inputData['job_id'] ?? null;
        if (empty($jobId)) {
            if ($isAjax) echo json_encode(['success' => false, 'error' => 'Job ID is missing.']);
            else { $_SESSION['admin_status'] = ['type' => 'error', 'message' => 'Job ID is missing.']; header('Location: dashboard.php?view=manage_jobs'); }
            exit;
        }
        if (empty($inputData['title'])) {
             if ($isAjax) echo json_encode(['success' => false, 'error' => 'Job title is required.']);
             else { $_SESSION['admin_status'] = ['type' => 'error', 'message' => 'Job title is required.']; header('Location: dashboard.php?view=edit_job&id=' . urlencode($jobId)); }
            exit;
        }
         if (empty($inputData['phones']) && empty($inputData['emails'])) {
            if ($isAjax) echo json_encode(['success' => false, 'error' => 'At least one contact method (phone or email) is required.']);
            else { $_SESSION['admin_status'] = ['type' => 'error', 'message' => 'At least one contact method (phone or email) is required.']; header('Location: dashboard.php?view=edit_job&id=' . urlencode($jobId)); }
            exit;
        }


        $jobs = loadJobs();
        $jobUpdated = false;
        foreach ($jobs as $index => &$job) { // Use reference &$job
            if ($job['id'] === $jobId) {
                $job['title']             = trim($inputData['title']);
                $job['company']           = trim($inputData['company'] ?? $job['company']);
                $job['location']          = trim($inputData['location'] ?? $job['location']);
                $job['description']       = trim($inputData['description'] ?? $job['description']);
                $job['type']              = trim($inputData['type'] ?? $job['type']);
                $job['experience']        = trim($inputData['experience'] ?? $job['experience']);
                $job['salary']            = trim($inputData['salary'] ?? $job['salary']);
                $job['vacant_positions']  = (int)($inputData['vacant_positions'] ?? $job['vacant_positions']);
                $job['phones']            = trim($inputData['phones'] ?? $job['phones']);
                $job['emails']            = trim($inputData['emails'] ?? $job['emails']);
                $job['ai_summary']        = trim($inputData['ai_summary'] ?? $job['ai_summary']);
                // posted_on and posted_on_unix_ts usually don't change on edit, unless specified
                // If 'posted_on' is submitted and different, update it.
                if (isset($inputData['posted_on']) && $inputData['posted_on'] !== $job['posted_on']) {
                    $newTimestamp = strtotime($inputData['posted_on']);
                    if ($newTimestamp) {
                        $job['posted_on'] = date('Y-m-d H:i:s', $newTimestamp);
                        $job['posted_on_unix_ts'] = $newTimestamp;
                    }
                }
                // Or, add an 'updated_at' field
                // $job['updated_at'] = date('Y-m-d H:i:s');
                $jobUpdated = true;
                break;
            }
        }

        if ($jobUpdated && saveJobs($jobs)) {
            if ($isAjax) echo json_encode(['success' => true, 'message' => 'Job updated successfully!']);
            else { $_SESSION['admin_status'] = ['type' => 'success', 'message' => 'Job updated successfully!']; header('Location: dashboard.php?view=manage_jobs'); }
        } else {
            if ($isAjax) echo json_encode(['success' => false, 'error' => $jobUpdated ? 'Failed to save updated job.' : 'Job not found.']);
            else { $_SESSION['admin_status'] = ['type' => 'error', 'message' => $jobUpdated ? 'Failed to save updated job.' : 'Job not found.']; header('Location: dashboard.php?view=edit_job&id=' . urlencode($jobId)); }
        }
        break;

    case 'delete_job':
        $jobId = $inputData['id'] ?? null;
        if (empty($jobId)) {
            if ($isAjax) echo json_encode(['success' => false, 'error' => 'Job ID is missing.']);
            else { $_SESSION['admin_status'] = ['type' => 'error', 'message' => 'Job ID is missing.']; header('Location: dashboard.php?view=manage_jobs'); }
            exit;
        }

        $jobs = loadJobs();
        $initialCount = count($jobs);
        $jobs = array_filter($jobs, function ($job) use ($jobId) {
            return $job['id'] !== $jobId;
        });

        if (count($jobs) < $initialCount && saveJobs(array_values($jobs))) { // Re-index array
             if ($isAjax) echo json_encode(['success' => true, 'message' => 'Job deleted successfully!']);
             else { $_SESSION['admin_status'] = ['type' => 'success', 'message' => 'Job deleted successfully!']; header('Location: dashboard.php?view=manage_jobs'); }
        } else {
            if ($isAjax) echo json_encode(['success' => false, 'error' => (count($jobs) < $initialCount) ? 'Failed to save after deletion.' : 'Job not found or already deleted.']);
            else { $_SESSION['admin_status'] = ['type' => 'error', 'message' => (count($jobs) < $initialCount) ? 'Failed to save after deletion.' : 'Job not found or already deleted.']; header('Location: dashboard.php?view=manage_jobs'); }
        }
        break;

    default:
        if ($isAjax) {
            echo json_encode(['success' => false, 'error' => 'Invalid action specified.']);
        } else {
            // For non-AJAX, redirect or show error page
            $_SESSION['admin_status'] = ['type' => 'error', 'message' => 'Invalid action.'];
            header('Location: dashboard.php');
        }
        break;
}
exit;
?>
