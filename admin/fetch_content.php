<?php

// admin/fetch_content.php - Fetches content for a specific view via AJAX

// Start output buffering to prevent premature output
ob_start();

// Ensure no whitespace or output before session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Start session only if not already started
}

// Include configuration and helper functions
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/user_helpers.php';
require_once __DIR__ . '/includes/job_helpers.php';
require_once __DIR__ . '/includes/feedback_helpers.php';
require_once __DIR__ . '/includes/user_manager_helpers.php'; // Include new helper for user management

// Check if the user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Set HTTP response code and redirect to login
    if (!headers_sent()) {
        http_response_code(401); // Unauthorized
        header('Location: dashboard.php?view=login');
    }
    exit;
}

// Get the requested view from the GET parameters
$requestedView = $_GET['view'] ?? 'dashboard'; // Default to dashboard view

// Log the requested view for debugging
// error_log("Admin Fetch: Requested view: " . $requestedView);

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
$userToEdit = null; // For edit_user view
$whatsappMessage = null; // Initialize for generate_message view
$telegramMessage = null; // Initialize for generate_message view

// Filepath for the daily visitor counter
$visitorCounterFile = __DIR__ . '/../data/daily_visitors.json';

// Function to retrieve daily visitor data
function getDailyVisitorData($filePath) {
    if (!file_exists($filePath)) {
        return [];
    }

    $visitorData = json_decode(file_get_contents($filePath), true);
    if (!is_array($visitorData)) {
        return [];
    }

    return $visitorData;
}

// Get the daily visitor data
$dailyVisitorData = getDailyVisitorData($visitorCounterFile);

// Prepare data for the visitors graph (last 30 days)
$visitorGraphLabels = [];
$visitorGraphData = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $visitorGraphLabels[] = date('M d', strtotime($date)); // Format as "Jan 01"
    $visitorGraphData[] = $dailyVisitorData[$date] ?? 0; // Use 0 if no data for the date
}

// Calculate total views (unique visitors)
$totalViews = array_sum($dailyVisitorData);

// Calculate monthly visitors (current month only)
$currentMonth = date('Y-m');
$monthlyVisitors = 0;
foreach ($dailyVisitorData as $date => $count) {
    if (strpos($date, $currentMonth) === 0) {
        $monthlyVisitors += $count;
    }
}

// Load necessary data or perform actions based on the requested view
switch ($requestedView) {
    case 'dashboard':
        // Load data for dashboard view
        $allJobs = loadJobs($jobsFilename); // Load jobs for calculating job counts
        $feedbackMessages = loadFeedbackMessages($feedbackFilename); // Load messages for counts
        $users = loadUsers($usersFilename); // Load users for counts (optional, but good to have data loaded consistently)

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
                 echo '<p class="status-message error">Error: Job not found for editing.</p>';
                 error_log("Admin Edit Job Error: Job ID not found for editing via AJAX: " . ($jobId ?? 'N/A'));
                 exit;
            }
        }
        break;
    case 'edit_user':
        // Load user data for editing
        $usernameToEdit = $_GET['username'] ?? null;
        if ($usernameToEdit) {
            $userToEdit = findUserByUsername($usernameToEdit, $usersFilename);
            if (!$userToEdit) {
                echo '<p class="status-message error">Error: User not found for editing.</p>';
                error_log("Admin Fetch Content Error: User '" . $usernameToEdit . "' not found for editing via AJAX.");
                exit;
            }
            $loggedInUserRole = $_SESSION['admin_role'] ?? 'user';
            $loggedInUsername = $_SESSION['admin_username'] ?? '';
            $targetUserRole = $userToEdit['role'] ?? 'user';

            $canAccessView = false;
            if ($loggedInUserRole === 'super_admin') {
                $canAccessView = true;
            } elseif ($loggedInUserRole === 'admin') {
                if ($targetUserRole !== 'super_admin') {
                    $canAccessView = true;
                }
            } elseif ($loggedInUserRole === 'user_group_manager') {
                if ($targetUserRole === 'user') {
                    $canAccessView = true;
                }
            }

            if (!$canAccessView) {
                echo '<p class="status-message error">Access Denied: You do not have permission to view the edit page for this user.</p>';
                error_log("Admin Fetch Content Access Denied: User '" . $loggedInUsername . "' (role: " . $loggedInUserRole . ") attempted to load edit page for user '" . ($userToEdit['username'] ?? 'N/A') . "' (role: " . $targetUserRole . ") via AJAX.");
                exit;
            }
        } else {
            echo '<p class="status-message error">Error: No username specified for editing.</p>';
            exit;
        }
        break;
    case 'profile':
         // User data is already in session, no extra loading needed here
        break;
    case 'messages':
        // Load feedback messages
        $feedbackMessages = loadFeedbackMessages($feedbackFilename);
        if (!empty($feedbackMessages)) {
            usort($feedbackMessages, function($a, $b) {
                $ts_a = $a['timestamp'] ?? 0;
                $ts_b = $b['timestamp'] ?? 0;
                return $ts_b - $ts_a;
            });
        }
        break;

    case 'generate_message':
        // --- Generate WhatsApp Message ---
        $whatsappMessage = null; // Ensure it's null before attempting generation
        $whatsappLogMessage = "[WHATSAPP_GEN_DEBUG] Starting WhatsApp message generation.";
        ob_start(); // Start output buffering for WhatsApp message
        if (file_exists(__DIR__ . '/generate_whatsapp_message.php')) {
            $whatsappLogMessage .= " Found generate_whatsapp_message.php.";
            require __DIR__ . '/generate_whatsapp_message.php';
        } else {
            $whatsappLogMessage .= " generate_whatsapp_message.php NOT FOUND in " . __DIR__ . ".";
            // echo "Error: WhatsApp message generation script not found."; // Optionally output error for view
        }
        $whatsappMessageContent = ob_get_clean();
        $whatsappLogMessage .= " Raw output: '" . preg_replace('/\s+/', ' ', trim($whatsappMessageContent)) . "'."; // Log trimmed raw output

        // Check if the script outputted a valid message (not an error string from the script itself)
        if (empty(trim($whatsappMessageContent))) {
            $whatsappLogMessage .= " Output is empty. \$whatsappMessage remains null.";
        } elseif (strpos(strtolower($whatsappMessageContent), 'error') !== false || strpos(strtolower($whatsappMessageContent), 'could not generate') !== false) {
            $whatsappLogMessage .= " Output contains error keywords. \$whatsappMessage remains null.";
        } else {
            $whatsappMessage = $whatsappMessageContent;
            $whatsappLogMessage .= " Output seems valid. \$whatsappMessage SET.";
        }
        error_log($whatsappLogMessage);

        // --- Generate Telegram Message ---
        $telegramMessage = null; // Ensure it's null
        $telegramLogMessage = "[TELEGRAM_GEN_DEBUG] Starting Telegram message generation.";
        ob_start(); // Start output buffering for Telegram message
        if (file_exists(__DIR__ . '/generate_telegram_message.php')) {
            $telegramLogMessage .= " Found generate_telegram_message.php.";
            require __DIR__ . '/generate_telegram_message.php';
        } else {
            $telegramLogMessage .= " generate_telegram_message.php NOT FOUND in " . __DIR__ . ".";
            // echo "Error: Telegram message generation script not found."; // Optionally output error for view
        }
        $telegramMessageContent = ob_get_clean();
        $telegramLogMessage .= " Raw output: '" . preg_replace('/\s+/', ' ', trim($telegramMessageContent)) . "'."; // Log trimmed raw output

        // Check if the script outputted a valid message
        if (empty(trim($telegramMessageContent))) {
            $telegramLogMessage .= " Output is empty. \$telegramMessage remains null.";
        } elseif (strpos(strtolower($telegramMessageContent), 'error') !== false || strpos(strtolower($telegramMessageContent), 'could not generate') !== false) {
            $telegramLogMessage .= " Output contains error keywords. \$telegramMessage remains null.";
        } else {
            $telegramMessage = $telegramMessageContent;
            $telegramLogMessage .= " Output seems valid. \$telegramMessage SET.";
        }
        error_log($telegramLogMessage);
        break;

    case 'manage_users':
         $users = loadUsers($usersFilename);
         break;

    case 'post_job':
        break;

    default:
        echo '<p class="status-message error">Error: Invalid view specified.</p>';
        error_log("Admin Error: Invalid view requested via AJAX: " . $requestedView);
        exit;
}

// Start output buffering again to capture the specific view's content
// Note: ob_start() was called at the very top. If any of the cases above did an echo and exit,
// this part won't be reached. The generate_message case was modified to not exit.
// If ob_get_clean() was called in a case, we might need to ob_start() again if we want to capture the view.
// However, the standard flow is: top ob_start, switch prepares vars, then view is included, then final ob_get_clean.
// The ob_start/ob_get_clean within generate_message case is for capturing *those specific script outputs* into variables.

// The main output buffer is still active from the top of the script.
// We will now include the view file into this buffer.

$viewFileSuffix = '_view.php';
$viewFilePath = __DIR__ . '/views/' . $requestedView . $viewFileSuffix;

$allowedFetchViews = ['dashboard', 'manage_jobs', 'edit_job', 'edit_user', 'profile', 'messages', 'generate_message', 'manage_users', 'post_job'];

if (!in_array($requestedView, $allowedFetchViews)) {
     echo '<p class="status-message error">Error: Access denied or view not available.</p>';
     error_log("Admin Error: fetch_content.php - View '" . $requestedView . "' not in \$allowedFetchViews. Path attempted: " . $viewFilePath);
} elseif (file_exists($viewFilePath)) {
    require $viewFilePath;
} else {
    echo '<p class="status-message error">Error: View file not found.</p>';
    error_log("Admin Error: fetch_content.php - Requested view file NOT FOUND: " . $viewFilePath . " (Actual \$requestedView value: '" . $requestedView . "')");
}

// Get the buffered output (which now includes the view's content) and clean the buffer
$viewContent = ob_get_clean();

// Output the captured content
echo $viewContent;

// Flush the output buffer (though ob_get_clean also does this)
// ob_end_flush(); // Not strictly necessary after ob_get_clean if that's the last buffer op

exit;

?>
