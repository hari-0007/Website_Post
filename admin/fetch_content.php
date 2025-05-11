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


// Check if the user is logged in. If not, return an error message.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Return a simple message indicating login is required
    // The JavaScript on the main dashboard page will handle redirecting if needed.
    echo '<p class="status-message error">Unauthorized: Please log in.</p>';
    // It's better to return a 401 status for AJAX as well
    http_response_code(401); // Set 401 Unauthorized status code
    exit; // Stop execution
}

// Get the requested view from the GET parameters
$requestedView = $_GET['view'] ?? 'dashboard'; // Default to dashboard view

// Log the requested view for debugging
// error_log("Admin Fetch: Requested view: " + $requestedView); // Corrected concatenation

// Initialize variables that might be needed by the views
$allJobs = [];
$feedbackMessages = [];
$totalViews = 0;
$jobsTodayCount = 0;
$jobsMonthlyCount = 0;
$graphLabels = [];
$graphData = [];
$users = [];
$jobToEdit = null; // For edit_job view
$whatsappMessage = null; // Initialize for generate_message view


// Load necessary data or perform actions based on the requested view
switch ($requestedView) {
    case 'dashboard':
        // Load data for dashboard view
        $allJobs = loadJobs($jobsFilename); // Load jobs for calculating counts
        $feedbackMessages = loadFeedbackMessages($feedbackFilename); // Load messages for counts
        $users = loadUsers($usersFilename); // Load users for counts (optional, but good to have data loaded consistently)

        // Calculate statistics (logic potentially moved from dashboard.php)
        // Read view count
        if (file_exists($viewCounterFile)) {
            $totalViews = (int)file_get_contents($viewCounterFile);
        } else {
            $totalViews = 0;
            error_log("Admin Dashboard Error: View counter file not found: " . $viewCounterFile);
        }

        // Calculate jobs posted today and this month
        $jobsTodayCount = 0;
        $jobsMonthlyCount = 0;
        $now = time();
        $startOfToday = strtotime('today midnight'); // Start of today's timestamp
        $startOfMonth = strtotime('first day of this month midnight'); // Start of this month's timestamp

        if (!empty($allJobs)) {
            foreach ($allJobs as $job) {
                // Use 'posted_on_unix_ts' if available, otherwise try to parse 'posted_on'
                 $postedTimestamp = 0; // Default to 0 or a value that won't match the condition
                 if (isset($job['posted_on_unix_ts']) && is_numeric($job['posted_on_unix_ts'])) {
                      $postedTimestamp = (int)$job['posted_on_unix_ts'];
                 } elseif (isset($job['posted_on']) && is_string($job['posted_on']) && !empty($job['posted_on'])) {
                      $parsedTime = strtotime($job['posted_on']);
                      if ($parsedTime !== false) {
                          $postedTimestamp = $parsedTime;
                      } else {
                          // Log error if date parsing fails
                          error_log("Admin Dashboard Error: Could not parse date for job ID " . ($job['id'] ?? 'N/A') . ": " . ($job['posted_on'] ?? 'N/A')); // Log job ID and posted_on if available
                      }
                 }


                if ($postedTimestamp > 0 && $postedTimestamp >= $startOfToday) {
                    $jobsTodayCount++;
                }
                if ($postedTimestamp > 0 && $postedTimestamp >= $startOfMonth) {
                    $jobsMonthlyCount++;
                }
            }
        }

         // Prepare data for the daily job posts chart (last 30 days)
         $jobCountsByDay = array_fill(0, 30, 0); // Initialize counts for the last 30 days
         $graphLabels = [];

         // Generate labels for the last 30 days (from 29 days ago to today)
         for ($i = 29; $i >= 0; $i--) {
             $graphLabels[] = date('M d', strtotime("-$i days"));
         }

         if (!empty($allJobs)) {
             $now = time(); // Get current time once
             foreach ($allJobs as $job) {
                  $postedTimestamp = 0; // Default to 0 or a value that won't match the condition
                  if (isset($job['posted_on_unix_ts']) && is_numeric($job['posted_on_unix_ts'])) {
                       $postedTimestamp = (int)$job['posted_on_unix_ts'];
                  } elseif (isset($job['posted_on']) && is_string($job['posted_on']) && !empty($job['posted_on'])) {
                       $parsedTime = strtotime($job['posted_on']);
                       if ($parsedTime !== false) {
                           $postedTimestamp = $parsedTime;
                       } else {
                           // Log error if date parsing fails - already logged above, but can add here too if needed
                       }
                  }


                  if ($postedTimestamp > 0) { // Ensure timestamp is valid and positive
                      // Calculate the number of days ago the job was posted
                      $daysAgo = floor(($now - $postedTimestamp) / (24 * 60 * 60));

                      // If posted within the last 30 days (0 to 29 days ago)
                      if ($daysAgo >= 0 && $daysAgo < 30) {
                          // Increment the count for the corresponding day (index 0 is today, index 29 is 29 days ago)
                          $jobCountsByDay[29 - $daysAgo]++;
                      }
                  }
             }
         }
         $graphData = $jobCountsByDay;

        break;
    case 'manage_jobs':
    case 'edit_job':
        // Load all jobs for manage or specific job for edit
        $allJobs = loadJobs($jobsFilename);
        if ($requestedView === 'edit_job' && isset($_GET['id'])) {
            $jobId = $_GET['id'];
            // Find the job to edit
            $jobToEdit = null;
            if (!empty($allJobs)) {
                foreach ($allJobs as $job) {
                    if (isset($job['id']) && (string)$job['id'] === (string)$jobId) {
                        $jobToEdit = $job;
                        break;
                    }
                }
            }
            // If job not found, set status message and exit
            if ($jobToEdit === null) {
                 // Setting a session status here is less effective for AJAX fetch,
                 // but we can output an error message directly.
                 echo '<p class="status-message error">Error: Job not found for editing.</p>';
                 // Log the error
                 error_log("Admin Edit Job Error: Job ID not found for editing via AJAX: " . ($jobId ?? 'N/A'));
                 // Exit to prevent including the edit_job_view.php which expects $jobToEdit
                 exit;
            }
             // $jobToEdit is now available for the view
        }
        break;
    case 'profile':
         // User data is already in session, no extra loading needed here
        break;
    case 'messages':
        // Load feedback messages
        $feedbackMessages = loadFeedbackMessages($feedbackFilename);
        // Sort messages by timestamp descending (newest first)
        if (!empty($feedbackMessages)) {
            usort($feedbackMessages, function($a, $b) {
                $ts_a = $a['timestamp'] ?? 0;
                $ts_b = $b['timestamp'] ?? 0;
                return $ts_b - $ts_a; // Descending order (newest first)
            });
        }
        break;

    case 'generate_message':
        // --- Logic to generate the WhatsApp message ---

        // Load job data (Ensure $allJobs is loaded, it's loaded globally or re-loaded here)
        $allJobs = loadJobs($jobsFilename); // Re-load to ensure latest data

        // Filter jobs posted in the last 24 hours
        $jobsToday = [];
        $cutoffTime = time() - (24 * 60 * 60); // Calculate the Unix timestamp 24 hours ago

        if (!empty($allJobs)) {
            foreach ($allJobs as $job) {
                // Use 'posted_on_unix_ts' if available, otherwise try to parse 'posted_on'
                 $postedTimestamp = 0; // Default to 0 or a value that won't match the condition
                 if (isset($job['posted_on_unix_ts']) && is_numeric($job['posted_on_unix_ts'])) {
                      $postedTimestamp = (int)$job['posted_on_unix_ts'];
                 } elseif (isset($job['posted_on']) && is_string($job['posted_on']) && !empty($job['posted_on'])) {
                      $parsedTime = strtotime($job['posted_on']);
                      if ($parsedTime !== false) {
                          $postedTimestamp = $parsedTime;
                      } else {
                          // Log error if date parsing fails
                          error_log("Admin Generate Message Error: Could not parse date for job ID " . ($job['id'] ?? 'N/A') . ": " . ($job['posted_on'] ?? 'N/A')); // Log job ID and posted_on if available
                      }
                 }


                // Check if the job was posted within the last 24 hours and has a valid timestamp
                if ($postedTimestamp > 0 && $postedTimestamp >= $cutoffTime) {
                    $jobsToday[] = $job;
                }
            }
        }

        $countToday = count($jobsToday);
        $message = "🎯 Daily UAE Job Update\n\n"; // Start the message with the requested header

        if ($countToday === 0) {
            // Format for no new jobs
            $message .= "No new jobs were posted in the last 24 hours.\n";
        } else {
            // Format for existing new jobs
            $message .= "Total jobs posted in the last 24 hours: " . $countToday . "\n\n";

            // Sort jobs by posted_on descending (newest first)
            usort($jobsToday, function($a, $b) {
                $ts_a = 0;
                if (isset($a['posted_on_unix_ts']) && is_numeric($a['posted_on_unix_ts'])) {
                     $ts_a = (int)$a['posted_on_unix_ts'];
                } elseif (isset($a['posted_on']) && is_string($a['posted_on']) && !empty($a['posted_on'])) {
                     $parsedTime_a = strtotime($a['posted_on']);
                     if ($parsedTime_a !== false) $ts_a = $parsedTime_a;
                }

                $ts_b = 0;
                if (isset($b['posted_on_unix_ts']) && is_numeric($b['posted_on_unix_ts'])) {
                     $ts_b = (int)$b['posted_on_unix_ts'];
                } elseif (isset($b['posted_on']) && is_string($b['posted_on']) && !empty($b['posted_on'])) {
                     $parsedTime_b = strtotime($b['posted_on']);
                     if ($parsedTime_b !== false) $ts_b = $parsedTime_b;
                }

                return $ts_b - $ts_a; // Descending order (newest first)
            });

            foreach ($jobsToday as $job) {
                $title = $job['title'] ?? 'N/A';
                $company = $job['company'] ?? 'N/A';

                // Assume 'vacant_positions' field exists, default to 1 if not found or not numeric
                $vacantPositions = $job['vacant_positions'] ?? 1;
                $vacantPositions = is_numeric($vacantPositions) ? (int)$vacantPositions : 1;

                // Format: "• Title (Count) at Company"
                $message .= "• " . $title;
                if ($vacantPositions > 1) {
                    $message .= " (" . $vacacantPositions . ")"; // Corrected variable name here
                }
                $message .= " at " . $company; // Added " at Company"
                $message .= "\n"; // New line after each job
            }
        }

        // Add an extra newline before the URL section, regardless of job count
         $message .= "\n";

        // Add the website URL section at the end
        // Ensure $siteUrl is available from config.php
        // The format is "Explore all jobs on our website!" followed by the URL
        $message .= "Explore all jobs on our website!\n" . ($siteUrl ?? 'Website URL Not Configured') . "\n"; // Use $siteUrl from config.php

        // Assign the generated message to the variable expected by the view
        $whatsappMessage = $message;

        // Now include the view file as it expects $whatsappMessage
        require __DIR__ . '/views/generate_message_view.php';
        exit; // Exit after including the view for this case

    case 'manage_users':
         // Load user data
         $users = loadUsers($usersFilename);
         // $users is now available for the view
         break;

    case 'post_job':
        // post_job view doesn't strictly need data loaded on fetch, but it expects $formData if there were errors.
        // However, error handling and pre-filling is typically done via redirect POST-after-redirect GET.
        // When fetched via AJAX, $_POST won't be available here unless the AJAX call was POST, which is not the case for fetching views.
        // The post_job form itself submits to job_actions.php.
        // For simplicity in the AJAX fetch, we just include the view. Any pre-filling on error would be handled by the full page load redirect flow.
        break;


    default:
        // If requested view is not recognized or not allowed via AJAX
        echo '<p class="status-message error">Error: Invalid view specified.</p>';
        error_log("Admin Error: Invalid view requested via AJAX: " . $requestedView);
        exit; // Stop execution
}

// Start output buffering to capture the view content
ob_start();

// Require the requested view file
$viewFilePath = __DIR__ . '/views/' . $requestedView . '_view.php';

// List of views allowed to be fetched via AJAX
// ADDED 'post_job' and 'generate_message' to this list
$allowedFetchViews = ['dashboard', 'manage_jobs', 'edit_job', 'profile', 'messages', 'generate_message', 'manage_users', 'post_job'];

if (!in_array($requestedView, $allowedFetchViews)) {
     // If requested view is not in the allowed list for AJAX fetch
     echo '<p class="status-message error">Error: Access denied or view not available.</p>';
     error_log("Admin Error: Attempted to fetch unauthorized view via AJAX: " . $requestedView);
} elseif (file_exists($viewFilePath)) {
    // Include the view file. Variables loaded above ($allJobs, etc.) will be available to it.
    // The generate_message case already included its view and exited, so this won't be reached for that case.
    // However, for other views, this is where the inclusion happens.
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

// Ensure nothing else is outputted after this
exit;

?>