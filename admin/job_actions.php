<?php

// admin/job_actions.php - Handles Job Management Actions

session_start(); // Start the session

// Include configuration and helper functions
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/job_helpers.php';

// Check if the user is logged in. If not, redirect to login page.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: dashboard.php'); // Redirect to the main page which handles login
    exit;
}

// Initialize status messages (will be stored in session and displayed on dashboard.php)
$_SESSION['admin_status'] = ['message' => '', 'type' => ''];

// Determine the action from GET or POST request
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$jobId = $_GET['id'] ?? $_POST['job_id'] ?? null; // Get job ID from GET or POST

// Load existing jobs data (needed for delete, edit POST, and potentially post POST)
$allJobs = loadJobs($jobsFilename);

// Handle Delete Action (Triggered by GET request from manage_jobs)
if ($action === 'delete_job' && $jobId && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $initialCount = count($allJobs);
    // Filter out the job with the matching ID
    $updatedJobs = array_filter($allJobs, function($job) use ($jobId) {
        return ($job['id'] ?? null) !== $jobId;
    });

    if (count($updatedJobs) < $initialCount) {
        // Re-index the array after filtering
        $updatedJobs = array_values($updatedJobs);

        if (saveJobs($updatedJobs, $jobsFilename)) {
            $_SESSION['admin_status'] = ['message' => 'Success: Job deleted successfully!', 'type' => 'success'];
        } else {
            $_SESSION['admin_status'] = ['message' => 'Error: Could not save updated job data to file.', 'type' => 'error'];
            error_log("Admin Delete Job Error: Could not write to jobs.json after deletion: " . $jobsFilename);
        }
    } else {
        $_SESSION['admin_status'] = ['message' => 'Error: Job with specified ID not found for deletion.', 'type' => 'error'];
        error_log("Admin Delete Job Error: Job ID not found: " . $jobId);
    }

    // Redirect back to manage jobs view
    header('Location: dashboard.php?view=manage_jobs');
    exit;
}

// Handle POST Job Submission (Triggered by POST request from post_job_view.php)
if ($action === 'post_job' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $formData = $_POST;

    // Basic validation
    if (empty($formData['title']) || empty($formData['company']) || empty($formData['location']) || empty($formData['description']) || empty($formData['phones']) || empty($formData['emails'])) {
        $_SESSION['admin_status'] = ['message' => 'Error: Title, Company, Location, Description, Phones, and Emails are required.', 'type' => 'error'];
        // Redirect back to post_job view with form data (handled by dashboard.php reading POST)
        header('Location: dashboard.php?view=post_job'); // Redirect to allow dashboard.php to display errors and old data
        exit;
    } else {
        // Data seems valid, create new job
        $newJobId = time() . '_' . mt_rand(1000, 9999); // Simple unique ID
        $postedOn = date('Y-m-d H:i:s');
        $postedOnUnixTs = time();
        $vacantPositions = filter_var($formData['vacant_positions'] ?? 1, FILTER_VALIDATE_INT);
        $vacantPositions = ($vacantPositions === false || $vacantPositions < 1) ? 1 : $vacantPositions;
        $type = trim($formData['type'] ?? 'Full Time'); // Default to Full Time

        $newJob = [
            'id' => $newJobId,
            'title' => trim($formData['title']),
            'company' => trim($formData['company']),
            'location' => trim($formData['location']),
            'description' => trim($formData['description']),
            'type' => $type, // Save the job type
            'posted_on' => $postedOn,
            'posted_on_unix_ts' => $postedOnUnixTs,
            'phones' => trim($formData['phones']),
            'emails' => trim($formData['emails']),
            'vacant_positions' => $vacantPositions,
            'experience' => trim($formData['experience'] ?? '0'), // Default to "0" (Select Experience)
            'salary' => trim($formData['salary'] ?? ''),
        ];

        // Add the new job to the beginning of the array (most recent first)
        array_unshift($allJobs, $newJob);

        // Save the updated array
        if (saveJobs($allJobs, $jobsFilename)) {
            $_SESSION['admin_status'] = ['message' => 'Success: Job "' . htmlspecialchars($newJob['title']) . '" posted successfully!', 'type' => 'success'];
            // Redirect to manage jobs view after successful post
            header('Location: dashboard.php?view=manage_jobs');
            exit;
        } else {
            $_SESSION['admin_status'] = ['message' => 'Error: Could not save job data to file.', 'type' => 'error'];
            error_log("Admin Post Job Error: Could not write to jobs.json: " . $jobsFilename);
            // Redirect back to post_job view to show error (dashboard.php will handle displaying it)
            header('Location: dashboard.php?view=post_job');
            exit;
        }
    }
}

// Handle Edit Job Submission (Triggered by POST request from edit_job_view.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'save_job') {
    $jobId = trim($_POST['job_id'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $phones = trim($_POST['phones'] ?? '');
    $emails = trim($_POST['emails'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    $salary = trim($_POST['salary'] ?? '');
    $posted_on = trim($_POST['posted_on'] ?? date('Y-m-d H:i:s'));

    // Validate mandatory fields
    if (empty($title)) {
        $_SESSION['admin_status'] = [
            'message' => 'Job title is required.',
            'type' => 'error'
        ];
        header('Location: dashboard.php?view=edit_job&id=' . urlencode($jobId));
        exit();
    }

    if (empty($phones) && empty($emails)) {
        $_SESSION['admin_status'] = [
            'message' => 'At least one contact method (phone or email) is required.',
            'type' => 'error'
        ];
        header('Location: dashboard.php?view=edit_job&id=' . urlencode($jobId));
        exit();
    }

    // Save the job data
    $jobData = [
        'id' => $jobId,
        'title' => $title,
        'company' => trim($_POST['company'] ?? ''),
        'location' => trim($_POST['location'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'type' => trim($_POST['type'] ?? 'Full Time'),
        'experience' => $experience,
        'salary' => $salary,
        'vacant_positions' => intval($_POST['vacant_positions'] ?? 1),
        'phones' => $phones,
        'emails' => $emails,
        'posted_on' => $posted_on,
        'posted_on_unix_ts' => $_POST['posted_on_unix_ts'] ?? time()
    ];

    // Save the updated job data (e.g., to a JSON file)
    $jobsFile = __DIR__ . '/../data/jobs.json';
    $jobs = file_exists($jobsFile) ? json_decode(file_get_contents($jobsFile), true) : [];
    foreach ($jobs as &$job) {
        if ($job['id'] === $jobId) {
            $job = $jobData; // Update the job
            break;
        }
    }
    file_put_contents($jobsFile, json_encode($jobs, JSON_PRETTY_PRINT));

    // Redirect with success message
    $_SESSION['admin_status'] = [
        'message' => 'Job updated successfully!',
        'type' => 'success'
    ];
    header('Location: dashboard.php?view=manage_jobs');
    exit();
}

// If no valid action was provided, redirect to the dashboard
$_SESSION['admin_status'] = ['message' => 'Invalid job action.', 'type' => 'error'];
header('Location: dashboard.php');
exit;

?>
