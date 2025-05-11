<?php

// admin/fetch_content.php - Fetches content for a specific view via AJAX

// Ensure no whitespace or output before session_start()
session_start();

// Include configuration and helper functions
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/user_helpers.php';
require_once __DIR__ . '/includes/job_helpers.php';
require_once __DIR__ . '/includes/feedback_helpers.php';
require_once __DIR__ . '/includes/user_manager_helpers.php'; // Include new helper for user management

// Explicitly define file paths here to ensure they are available
// These variables are also defined in config.php, but redefining here
// ensures they are available in this script's scope.
$feedbackFilename = __DIR__ . '/../data/feedback.json';
$jobsFilename = __DIR__ . '/../data/jobs.json';
$viewCounterFile = __DIR__ . '/../data/view_count.txt';
$siteUrl = $siteUrl; // Get from config.php
$usersFilename = __DIR__ . '/../data/user.json'; // Ensure usersFilename is available


// Check if the user is logged in. If not, return an error message.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Return a simple message indicating login is required
    // The JavaScript on the main dashboard page will handle redirecting if needed.
    echo '<p class="status-message error">You are not logged in. Please log in to access this content.</p>';
    exit; // Stop execution
}

// Get the logged-in user's role from the session for authorization checks
$loggedInUserRole = $_SESSION['admin_role'] ?? 'user';


// Determine the requested view and action from GET parameters
// This script primarily handles 'view' requests for content
$requestedView = $_GET['view'] ?? 'dashboard'; // Default to dashboard
// Note: 'action' requests (like generate_message) are handled by dashboard.php directly on initial load,
// or by specific action files (job_actions.php, message_actions.php, profile_actions.php, user_actions.php) via POST/GET.
// This script is for fetching VIEW content via AJAX.
$requestedAction = $_GET['action'] ?? null; // Keep action for potential future use if needed
$jobId = $_GET['id'] ?? null; // Get job ID for edit view if needed
$username = $_GET['username'] ?? null; // Get username for edit user view if needed


// --- Authorization Check for Views (Basic) ---
// This is a basic check. More granular checks are needed within view files.
$allowedViews = ['dashboard', 'post_job', 'manage_jobs', 'messages', 'profile', 'generate_message']; // Views accessible to all logged-in users

// Views restricted by role
$restrictedViews = [
    'manage_users' => ['super_admin', 'admin'], // User Manager accessible to Super Admins and Admins
    // 'manage_groups' => ['admin'], // Example: User Group Manager accessible to Admins
    // 'edit_user' => ['super_admin', 'admin'], // Example: Edit User accessible to Super Admins and Admins
];

// Combine allowed and restricted views for a full list of valid views
$validViews = array_merge($allowedViews, array_keys($restrictedViews));

// Check if the requested view is in the combined list of valid views
if (!in_array($requestedView, $validViews)) {
    // If the requested view is not in the list of valid views, it's an invalid request.
    echo '<p class="status-message error">Error: Invalid view requested.</p>';
    exit;
}

// Now check permissions for restricted views if the requested view is one of them
if (in_array($requestedView, array_keys($restrictedViews))) {
    // Check if the logged-in user's role is in the allowed roles for this restricted view
    if (!in_array($loggedInUserRole, $restrictedViews[$requestedView])) {
        // User does not have permission, return an error message
        echo '<p class="status-message error">Access Denied: You do not have permission to view this page.</p>';
        exit;
    }
}


// --- Data Loading and View Preparation ---
// Initialize variables that will be used by the view files
$allJobs = [];
$whatsappMessage = null; // Only relevant for generate_message view, which is not auto-refreshed
$totalViews = 0;
$jobsTodayCount = 0;
$jobsMonthlyCount = 0;
$dailyJobCounts = [];
$graphLabels = [];
$graphData = [];
$jobToEdit = [];
$feedbackMessages = [];
$users = []; // Initialize users array for manage_users view


// Load Data based on the requested view
switch ($requestedView) {
    case 'dashboard':
        // Load Job Data for stats
        $allJobs = loadJobs($jobsFilename);
        // Prepare data for Dashboard View (Stats & Graph)
        $totalViews = (int)@file_get_contents($viewCounterFile);

        $currentTime = time();
        $startOfToday = strtotime('today midnight');
        $startOfMonth = strtotime('first day of this month midnight');

        // Initialize daily counts for the last 30 days to zero
        for ($i = 0; $i < 30; $i++) {
            $date = date('Y-m-d', $startOfToday - ($i * 24 * 60 * 60));
            $dailyJobCounts[$date] = 0;
        }
        krsort($dailyJobCounts); // Sort dates ascending

        foreach ($allJobs as $job) {
            $jobTimestamp = $job['posted_on_unix_ts'] ?? (strtotime($job['posted_on'] ?? '') ?: 0);
            if ($jobTimestamp > 0) {
                if ($jobTimestamp >= $currentTime - (24 * 60 * 60)) { $jobsTodayCount++; }
                if ($jobTimestamp >= $startOfMonth) { $jobsMonthlyCount++; }
                $jobDateYmd = date('Y-m-d', $jobTimestamp);
                if (isset($dailyJobCounts[$jobDateYmd])) { $dailyJobCounts[$jobDateYmd]++; }
            }
        }
        $graphLabels = array_keys($dailyJobCounts);
        $graphData = array_values($dailyJobCounts);
        break;

    case 'manage_jobs':
        // Load Job Data for the table
        $allJobs = loadJobs($jobsFilename);
        break;

    case 'post_job':
        // No specific data loading needed for the empty form in this AJAX request
        break;

    case 'edit_job':
        // Load Job Data to find the specific job
        $allJobs = loadJobs($jobsFilename);
        if ($jobId) {
            $foundJob = null;
            foreach ($allJobs as $job) {
                if (($job['id'] ?? null) === $jobId) {
                    $foundJob = $job;
                    break;
                }
            }
            if ($foundJob) {
                 $jobToEdit = $foundJob;
            } else {
                 // Job ID not found - return an error message
                 echo '<p class="status-message error">Error: Job not found for editing.</p>';
                 exit; // Stop execution if job not found
            }
        } else {
             // No Job ID provided for edit - return an error message
             echo '<p class="status-message error">Error: No job ID provided for editing.</p>';
             exit; // Stop execution if no ID
        }
        break;

    case 'profile':
         // User info is in session, no file data load needed here.
         break;

    case 'messages':
        // Load Feedback Messages for the table
        $feedbackMessages = loadFeedbackMessages($feedbackFilename);
        break;

    case 'generate_message':
        // This view is typically accessed via an action link, not auto-refreshed.
        // However, if requested via AJAX, we can generate the message.
        $allJobs = loadJobs($jobsFilename);
        $jobsToday = [];
        $currentTime = time();
        $cutoffTime = $currentTime - (24 * 60 * 60);

        foreach ($allJobs as $job) {
            $jobTimestamp = $job['posted_on_unix_ts'] ?? (strtotime($job['posted_on'] ?? '') ?: 0);
            if ($jobTimestamp > 0 && $jobTimestamp >= $cutoffTime) {
                $jobsToday[] = $job;
            }
        }

        $countToday = count($jobsToday);
        $message = "🎯 Daily UAE Job Update\n\n";
        if ($countToday === 0) {
            $message .= "No new jobs were posted in the last 24 hours.\n";
        } else {
            $message .= "Total jobs posted in the last 24 hours: " . $countToday . "\n\n";
            usort($jobsToday, function($a, $b) {
                $ts_a = $a['posted_on_unix_ts'] ?? (strtotime($a['posted_on'] ?? '') ?: 0);
                $ts_b = $b['posted_on_unix_ts'] ?? (strtotime($b['posted_on'] ?? '') ?: 0);
                return $ts_b - $ts_a;
            });
            foreach ($jobsToday as $job) {
                $title = $job['title'] ?? 'N/A';
                $company = $job['company'] ?? 'N/A';
                $vacantPositions = $job['vacant_positions'] ?? 1;
                $message .= "• " . $title;
                if ($vacantPositions > 1) { $message .= " (" . $vacantPositions . ")"; }
                $message .= " at " . $company . "\n";
            }
        }
        $message .= "\nExplore all jobs on our website!\n";
        $message .= $siteUrl . "\n";
        $whatsappMessage = $message;
        // Set the view to the generate message view
        $requestedView = 'generate_message';
        break;

    case 'manage_users':
         // Load User Data for the table
         $users = loadUsers($usersFilename);
         // Note: Filtering of users based on 'user_group_manager' role needs to happen here or in the view
         // if you want to restrict what a user group manager sees.
         break;


    default:
        // Invalid view handled above, but a fallback here
        echo '<p class="status-message error">Error: Unhandled view.</p>';
        exit; // Stop execution for invalid view
}


// --- Include the appropriate View File (Outputting only the content HTML) ---
// Start output buffering to capture the view content
ob_start();

switch ($requestedView) {
    case 'dashboard':
        require __DIR__ . '/views/dashboard_view.php';
        break;
    case 'manage_jobs':
        require __DIR__ . '/views/manage_jobs_view.php';
        break;
    case 'post_job':
        require __DIR__ . '/views/post_job_view.php';
        break;
    case 'edit_job':
        // Ensure $jobToEdit is available for this view
        if (isset($jobToEdit) && !empty($jobToEdit)) {
             require __DIR__ . '/views/edit_job_view.php';
        } else {
             // This case should ideally not be reached due to the check above,
             // but included as a safeguard.
             echo '<p class="status-message error">Error: Job data not prepared for editing.</p>';
        }
        break;
    case 'profile':
        // User info is in session, view file just needs session access
        require __DIR__ . '/views/profile_view.php';
        break;
    case 'messages': // New case for messages view
         if (isset($feedbackMessages)) {
             require __DIR__ . '/views/messages_view.php';
         } else {
             echo '<p class="status-message error">Error: Feedback data not loaded.</p>';
         }
        break;
    case 'generate_message':
         // Ensure $whatsappMessage is available for this view
         if (isset($whatsappMessage)) {
             require __DIR__ . '/views/generate_message_view.php';
         } else {
              echo '<p class="status-message error">Error: Message could not be generated.</p>';
         }
        break;
     case 'manage_users': // New case for User Manager view
          if (isset($users)) {
              require __DIR__ . '/views/manage_users_view.php';
          } else {
              echo '<p class="status-message error">Error: User data could not be loaded.</p>';
          }
          break;
    default:
        // Invalid view handled above, but a fallback here
        echo '<p class="status-message error">Error: Unhandled view.</p>';
        break;
}

// Get the buffered output and clean the buffer
$viewContent = ob_get_clean();

// Output the captured content - this is all that should be sent back
echo $viewContent;

// Ensure nothing else is outputted after this
exit;

?>
