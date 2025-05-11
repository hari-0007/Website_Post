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
    if (empty($formData['title']) || empty($formData['company']) || empty($formData['location']) || empty($formData['description'])) {
        $_SESSION['admin_status'] = ['message' => 'Error: Title, Company, Location, and Description are required.', 'type' => 'error'];
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

        $newJob = [
            'id' => $newJobId,
            'title' => trim($formData['title']),
            'company' => trim($formData['company']),
            'location' => trim($formData['location']),
            'description' => trim($formData['description']),
            'posted_on' => $postedOn,
            'posted_on_unix_ts' => $postedOnUnixTs,
            'phones' => trim($formData['phones'] ?? ''),
            'emails' => trim($formData['emails'] ?? ''),
            'vacant_positions' => $vacantPositions,
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
if ($action === 'save_job' && $_SERVER['REQUEST_METHOD'] === 'POST' && $jobId) { // Changed action to save_job
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