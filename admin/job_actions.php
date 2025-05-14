<?php

// admin/job_actions.php - Handles job-related actions (e.g., posting a job)

session_start(); // Start the session to access session variables

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'post_job') {
    // Retrieve and sanitize form inputs
    $title = trim($_POST['title'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    $salary = trim($_POST['salary'] ?? '');
    $phones = trim($_POST['phones'] ?? '');
    $emails = trim($_POST['emails'] ?? '');
    $vacant_positions = intval($_POST['vacant_positions'] ?? 0);

    // Validate required fields
    if (empty($title) || empty($company) || empty($location) || empty($description) || empty($experience) || empty($salary) || empty($phones) || empty($emails) || $vacant_positions <= 0) {
        $_SESSION['admin_status'] = [
            'message' => 'All fields are required. Please fill out the form completely.',
            'type' => 'error'
        ];
        header('Location: dashboard.php?view=post_job');
        exit();
    }

    // Save the job data (e.g., to a database or JSON file)
    $jobData = [
        'title' => $title,
        'company' => $company,
        'location' => $location,
        'description' => $description,
        'experience' => $experience,
        'salary' => $salary,
        'phones' => $phones,
        'emails' => $emails,
        'vacant_positions' => $vacant_positions,
        'posted_at' => date('Y-m-d H:i:s')
    ];

    // Example: Save to a JSON file
    $jobsFile = __DIR__ . '/data/jobs.json';
    $jobs = file_exists($jobsFile) ? json_decode(file_get_contents($jobsFile), true) : [];
    $jobs[] = $jobData;
    file_put_contents($jobsFile, json_encode($jobs, JSON_PRETTY_PRINT));

    // Set success message and redirect to the dashboard
    $_SESSION['admin_status'] = [
        'message' => 'Job posted successfully!',
        'type' => 'success'
    ];
    header('Location: dashboard.php?view=manage_jobs');
    exit();
}

// Handle Edit Job Submission (Triggered by POST request from edit_job_view.php)
if ($action === 'save_job' && $_SERVER['REQUEST_METHOD'] === 'POST' && $jobId) {
    // Get form data
    $formData = $_POST;

    // Basic validation
    if (empty($formData['title']) || empty($formData['company']) || empty($formData['location']) || empty($formData['description'])) {
        $_SESSION['admin_status'] = ['message' => 'Error: Title, Company, Location, and Description are required.', 'type' => 'error'];
        // Redirect back to edit_job view with form data (handled by dashboard.php reading POST)
        header('Location: dashboard.php?view=edit_job&id=' . urlencode($jobId)); // Redirect back to edit view
        exit;
    } else {
        // Data seems valid, find job by ID and update
        $jobFoundAndUpdated = false;
        foreach ($allJobs as &$job) { // Use & for passing by reference to modify the original array
            if (($job['id'] ?? null) === $jobId) {
                $vacantPositions = filter_var($formData['vacant_positions'] ?? 1, FILTER_VALIDATE_INT);
                $vacantPositions = ($vacantPositions === false || $vacantPositions < 1) ? 1 : $vacantPositions;

                // Update job details - keep original posted_on unless specifically edited
                $job['title'] = trim($formData['title']);
                $job['company'] = trim($formData['company']);
                $job['location'] = trim($formData['location']);
                $job['description'] = trim($formData['description']);
                $job['phones'] = trim($formData['phones'] ?? '');
                $job['emails'] = trim($formData['emails'] ?? '');
                $job['vacant_positions'] = $vacantPositions;
                $job['experience'] = trim($formData['experience'] ?? '0'); // Default to "0" (Select Experience)
                $job['salary'] = trim($formData['salary'] ?? '');
                // Do NOT update posted_on/timestamp unless you add fields for them

                $jobFoundAndUpdated = true;
                break;
            }
        }
        unset($job); // Unset the reference

        if ($jobFoundAndUpdated) {
            // Save the updated array
            if (saveJobs($allJobs, $jobsFilename)) {
                $_SESSION['admin_status'] = ['message' => 'Success: Job "' . htmlspecialchars($formData['title']) . '" updated successfully!', 'type' => 'success'];
                // Redirect to manage jobs view after successful edit
                header('Location: dashboard.php?view=manage_jobs');
                exit;
            } else {
                $_SESSION['admin_status'] = ['message' => 'Error: Could not save updated job data to file.', 'type' => 'error'];
                error_log("Admin Edit Job Error: Could not write to jobs.json after update: " . $jobsFilename);
                // Redirect back to edit_job view to show error (dashboard.php will handle displaying it)
                header('Location: dashboard.php?view=edit_job&id=' . urlencode($jobId));
                exit;
            }
        } else {
            // Job ID not found during update POST request
            $_SESSION['admin_status'] = ['message' => 'Error: Job with specified ID not found during update.', 'type' => 'error'];
            error_log("Admin Edit Job Error: Job ID not found during update POST: " . $jobId);
            // Redirect as the job isn't valid anymore
            header('Location: dashboard.php?view=manage_jobs');
            exit;
        }
    }
}

// If no valid action was provided, redirect to the dashboard
$_SESSION['admin_status'] = ['message' => 'Invalid job action.', 'type' => 'error'];
header('Location: dashboard.php');
exit;

?>
