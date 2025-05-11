<?php

// admin/fetch_content.php - Fetches content for a specific view via AJAX

// Ensure no whitespace or output before session_start()
// Check if session is already active before starting
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration and helper functions
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/user_helpers.php';
require_once __DIR__ . '/includes/job_helpers.php';
require_once __DIR__ . '/includes/feedback_helpers.php';
require_once __DIR__ . '/includes/user_manager_helpers.php'; // Include new helper for user management


// Explicitly define file paths here to ensure they are available
// These variables are also defined in config.php, but redefining here
// ensures they are available in this script's scope when included by dashboard.php
// Or more accurately, they are needed here when fetch_content.php is called directly via AJAX
$feedbackFilename = $feedbackFilename; // Get from config.php
$jobsFilename = $jobsFilename;     // Get from config.php
$viewCounterFile = $viewCounterFile; // Get from config.php
$siteUrl = $siteUrl;         // Get from config.php
$usersFilename = $usersFilename;   // Get from config.php


// Check if the user is logged in. If not, return an error message.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Return a simple message indicating login is required
    // The JavaScript on the main dashboard page will handle redirecting if needed.
    // Return JSON for AJAX requests expecting it (like toggle status)
     if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
         header('Content-Type: application/json');
         echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.', 'redirect' => 'dashboard.php']);
         exit;
     } else {
         // For general content fetches via AJAX when not logged in
         // Note: This block might not be reached if dashboard.php redirects when not logged in.
         // The login view should be loaded directly by dashboard.php in that case.
         // Keeping this here as a fallback.
         echo '<p class="status-message error">Authentication required. Please log in.</p>';
         exit; // Stop execution
     }
}

// Get the requested view from the query string
$requestedView = $_GET['view'] ?? 'dashboard'; // Default to dashboard view

// Load Data Needed for Views
// Replicate necessary data loading from dashboard.php for relevant views
$allJobs = loadJobs($jobsFilename); // Needed for manage_jobs, dashboard_view stats, edit_job
$feedbackMessages = loadFeedbackMessages($feedbackFilename); // Needed for messages_view
$users = loadUsers($usersFilename); // Needed for manage_users_view
$jobToEdit = null; // For edit_job
$whatsappMessage = null; // For generate_message

// Load data specific to the current requested view
switch ($requestedView) {
    case 'dashboard':
        // Data like totalViews, jobsTodayCount, graphData, etc., calculated in dashboard.php
        // These variables need to be made available here if fetch_content.php is called directly
        // This is a potential complication with the current AJAX approach.
        // A simpler method might be for dashboard.php to pre-calculate all data needed by ANY view
        // and pass it, or for views to load their own specific data using helper functions.
        // Let's assume helper functions are used within the views if needed, or pass necessary file paths.
        // Re-calculating stats for dashboard_view if needed here:
         $totalViews = (int)file_get_contents($viewCounterFile);
         $today = date('Y-m-d');
         $thisMonth = date('Y-m');
         $jobsTodayCount = 0;
         $jobsMonthlyCount = 0;
         $dailyJobCounts = array_fill(0, 30, 0);
         $graphLabels = [];
         $now = time();
         $oneDay = 24 * 60 * 60;
         for ($i = 29; $i >= 0; $i--) {
             $graphLabels[] = date('Y-m-d', $now - $i * $oneDay);
         }
         foreach ($allJobs as $job) {
             $postedDate = date('Y-m-d', $job['posted_on_unix_ts'] ?? strtotime($job['posted_on'] ?? ''));
             if ($postedDate === $today) $jobsTodayCount++;
             if (strpos($postedDate, $thisMonth) === 0) $jobsMonthlyCount++;
             $dateIndex = array_search($postedDate, $graphLabels);
             if ($dateIndex !== false) $dailyJobCounts[$dateIndex]++;
         }
         $graphData = $dailyJobCounts;
        break;
    case 'edit_job':
         $jobId = $_GET['id'] ?? null;
         if ($jobId) {
              // Find the job in $allJobs loaded above
              foreach ($allJobs as $job) {
                   if (isset($job['id']) && (string)$job['id'] === (string)$jobId) {
                        $jobToEdit = $job;
                        break;
                   }
              }
             // If job not found, the view should handle displaying a "not found" message
         }
        break;
    case 'generate_message':
         // Replicate message generation logic from dashboard.php
         ob_start();
         // Adjust path if generate_whatsapp_message.php is not directly in admin folder
         // Assuming it's in the admin folder based on previous context
         require __DIR__ . '/views/generate_message_view.php'; // Include the message generator
         $whatsappMessage = ob_get_clean();
          if (empty($whatsappMessage) || trim($whatsappMessage) === "Error: Job data file not found.\n") {
               $whatsappMessage = "Could not generate message.";
          }
        break;
    case 'post_job':
        // No extra data loading needed for the post_job view itself,
        // but this case needs to exist to allow fetch_content to include post_job_view.php
        break;
    // No extra data loading needed within fetch_content.php for 'manage_jobs', 'profile', 'messages', 'manage_users'
    // These views either don't need extra data or rely on data already loaded ($allJobs, $feedbackMessages, $users)
}


// Start output buffering to capture the view content
ob_start();

// Require the requested view file
$viewFilePath = __DIR__ . '/views/' . $requestedView . '_view.php';

// List of views allowed to be fetched via AJAX
// ADDED 'post_job' back to this list
$allowedFetchViews = ['dashboard', 'manage_jobs', 'edit_job', 'profile', 'messages', 'generate_message', 'manage_users', 'post_job'];

if (!in_array($requestedView, $allowedFetchViews)) {
     // If requested view is not in the allowed list for AJAX fetch
     echo '<p class="status-message error">Error: Access denied or view not available.</p>';
     error_log("Admin Error: Attempted to fetch unauthorized view via AJAX: " . $requestedView);
} elseif (file_exists($viewFilePath)) {
    // Include the view file. Variables loaded above ($allJobs, etc.) will be available to it.
    require $viewFilePath;
} else {
    // Fallback for non-existent view file (should be caught by dashboard.php validation, but for safety)
    echo '<p class="status-message error">Error: View file not found.</p>';
    error_log("Admin Error: Requested view file not found: " . $viewFilePath);
}


// Get the buffered output and clean the buffer
$viewContent = ob_get_clean();

// Output the captured content - this is all that should be sent back
echo $viewContent;

exit; // Ensure nothing else is outputted

?>