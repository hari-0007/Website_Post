<?php

// admin/post_job.php - Handles the job posting form submission

session_start(); // Start the session to access session variables

// Include configuration and helper functions
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/job_helpers.php';

// Check if the form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize form inputs
    $title = trim($_POST['title'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $experience = trim($_POST['experience'] ?? '0'); // Default to "No Experience"
    if ($experience === 'other') {
        $experience = trim($_POST['custom_experience'] ?? ''); // Use the custom input value
    }
    $type = trim($_POST['type'] ?? 'Full Time'); // Default to Full Time
    $salary = trim($_POST['salary'] ?? '0'); // Default to 0
    $phones = trim($_POST['phones'] ?? '');
    $emails = trim($_POST['emails'] ?? '');
    $vacant_positions = intval($_POST['vacant_positions'] ?? 1); // Default to 1

    // Validate required fields
    if (empty($title)) {
        $_SESSION['admin_status'] = [
            'message' => 'Job title is required.',
            'type' => 'error'
        ];
        header('Location: dashboard.php?view=post_job');
        exit();
    }

    if (empty($phones) && empty($emails)) {
        $_SESSION['admin_status'] = [
            'message' => 'At least one contact method (phone or email) is required.',
            'type' => 'error'
        ];
        header('Location: dashboard.php?view=post_job');
        exit();
    }

    // Save the job data (e.g., to a database or JSON file)
    $jobData = [
        'id' => time() . '_' . rand(1000, 9999), // Generate a unique ID
        'title' => $title,
        'company' => $company,
        'location' => $location,
        'description' => $description,
        'experience' => $experience,
        'type' => $type, // Add the job type
        'salary' => $salary,
        'phones' => $phones,
        'emails' => $emails,
        'vacant_positions' => $vacant_positions,
        'posted_on' => date('Y-m-d H:i:s'),
        'posted_on_unix_ts' => time() // Add a Unix timestamp for easier sorting
    ];

    // Example: Save to a JSON file
    $jobsFile = __DIR__ . '/../data/jobs.json';
    $jobs = file_exists($jobsFile) ? json_decode(file_get_contents($jobsFile), true) : [];
    array_unshift($jobs, $jobData); // Add the new job to the beginning of the array
    file_put_contents($jobsFile, json_encode($jobs, JSON_PRETTY_PRINT));

    // Set success message and redirect to the dashboard
    $_SESSION['admin_status'] = [
        'message' => 'Job posted successfully!',
        'type' => 'success'
    ];
    header('Location: dashboard.php?view=manage_jobs');
    exit();
} else {
    // Invalid request method
    $_SESSION['admin_status'] = [
        'message' => 'Invalid job action.',
        'type' => 'error'
    ];
    header('Location: dashboard.php?view=post_job');
    exit();
}

?>
