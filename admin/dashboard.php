<?php

// admin/dashboard.php - Main Admin Panel Entry Point and Router

// Ensure no whitespace or output before this tag
session_start(); // Make sure session is started

// Include configuration and helper functions
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/user_helpers.php';
require_once __DIR__ . '/includes/job_helpers.php';
require_once __DIR__ . '/includes/feedback_helpers.php';
require_once __DIR__ . '/includes/user_manager_helpers.php'; // Include new helper for user management


// --- Initialize Variables ---
// Read status message from session and clear it
$statusMessage = '';
$statusClass = '';
if (isset($_SESSION['admin_status']['message'], $_SESSION['admin_status']['type'])) {
    $statusMessage = $_SESSION['admin_status']['message'];
    $statusClass = $_SESSION['admin_status']['type']; // Should be 'success', 'error', 'warning', or 'info'
    // Clear the session status immediately after reading it
    unset($_SESSION['admin_status']);
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: auth.php?action=logout");
    exit();
// }
// Redirect logged-in users away from the login page
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && $_GET['view'] === 'login') {
    header("Location: dashboard.php?view=dashboard");
    exit();
}


    // Handle the login view
if ($_GET['view'] === 'login') {
    require_once __DIR__ . '/partials/login.php'; // Include the login page
    exit(); // Stop further execution to prevent loading other views
}

// Redirect to login if the user is not logged in and no valid view is provided
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: dashboard.php?view=login");
    exit();
}

// Load other views based on the `view` parameter
$view = $_GET['view'] ?? 'dashboard';
switch ($view) {
    case 'dashboard':
        require_once __DIR__ . '/partials/dashboard.php';
        break;
    case 'manage_jobs':
        require_once __DIR__ . '/partials/manage_jobs.php';
        break;
    // Add other views as needed
    default:
        header("Location: dashboard.php?view=dashboard");
        exit();
}
// Redirect to login if the user is not logged in
// if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
//     if ($_GET['view'] !== 'login') { // Prevent redirect loop by checking the current view
//         header("Location: dashboard.php?view=login");
//         exit();
//     }
// }
// Redirect logged-in users away from the login page
// if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && $_GET['view'] === 'login') {
//     header("Location: dashboard.php?view=dashboard");
//     exit();
// }

$loginError = ''; // Specific variable for login errors on the login view
$registerMessage = ''; // Specific variable for registration messages on the login view
$forgotPasswordMessage = ''; // Specific variable for forgot password messages on the login view


// Check login status
$loggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$loggedInUserRole = $_SESSION['admin_role'] ?? 'user'; // Default role if not set

// Determine the requested view
$requestedView = $_GET['view'] ?? ($loggedIn ? 'dashboard' : 'login'); // Default to dashboard if logged in, login if not
$requestedAction = $_GET['action'] ?? null; // For handling specific actions routed through dashboard.php (like register_form)

// Validate requested view if logged in
// 'post_job' view is removed from allowed views as its navigation link is removed, but fetchable via AJAX
$allowedViews = ['dashboard', 'manage_jobs', 'edit_job', 'profile', 'messages', 'generate_message', 'manage_users'];
if ($loggedIn && !in_array($requestedView, $allowedViews)) {
    // If view is invalid for logged in user, redirect to dashboard
    $_SESSION['admin_status'] = ['message' => 'Error: Invalid view requested.', 'type' => 'error'];
    header('Location: dashboard.php?view=dashboard');
    exit;
}

// Handle specific actions that might require displaying a form (e.g., register, forgot password)
// These are typically only accessible when NOT logged in or handled before logging in
if (!$loggedIn && in_array($requestedAction, ['register_form', 'forgot_password_form'])) {
     // If status message was set by auth.php after a failed attempt,
     // transfer it to the specific view variables if needed.
     // The status message is already read into $statusMessage and $statusClass above,
     // we just need to make it available to the login view if required there.
    if ($statusMessage) {
        if ($requestedAction === 'register_form') {
            $registerMessage = $statusMessage;
        } elseif ($requestedAction === 'forgot_password_form') {
             $forgotPasswordMessage = $statusMessage;
        }
         // Note: $loginError is handled specifically by auth.php setting a message containing 'Invalid username'
         // We could integrate that here if auth.php sets a specific type like 'login_error'
    }
    // We will include the login.php view below which uses these variables
    // Set the requestedView to the action name so login.php knows which form to show
     $requestedView = $requestedAction;

} else if (!$loggedIn && $requestedView !== 'login') {
    // If not logged in and requesting a view other than 'login', redirect to login
     header('Location: dashboard.php?view=login');
     exit;
}


// Load Data for Views - Only if logged in
$allJobs = [];
$feedbackMessages = [];
$users = [];
$jobToEdit = null;
$jobId = null; // Initialize jobId for edit_job view
$whatsappMessage = null; // Initialize for generate_message view

if ($loggedIn) {
    // Load data needed for multiple views
    $allJobs = loadJobs($jobsFilename); // Needed for manage_jobs, dashboard stats, generate_message
    $feedbackMessages = loadFeedbackMessages($feedbackFilename); // Needed for messages view
    $users = loadUsers($usersFilename); // Needed for manage_users view

    // Load data specific to the current view
    switch ($requestedView) {
        case 'dashboard':
            // Calculate dashboard stats (total views, jobs today/month, graph data)
            $totalViews = (int)file_get_contents($viewCounterFile);
            $today = date('Y-m-d');
            $thisMonth = date('Y-m');

            $jobsTodayCount = 0;
            $jobsMonthlyCount = 0;
            $dailyJobCounts = array_fill(0, 30, 0); // Initialize counts for last 30 days
            $graphLabels = []; // Dates for the graph

            $now = time();
            $oneDay = 24 * 60 * 60; // Seconds in a day

            // Populate labels for the last 30 days
            for ($i = 29; $i >= 0; $i--) {
                $graphLabels[] = date('Y-m-d', $now - $i * $oneDay);
            }

            foreach ($allJobs as $job) {
                $postedDate = date('Y-m-d', $job['posted_on_unix_ts'] ?? strtotime($job['posted_on'] ?? ''));

                if ($postedDate === $today) {
                    $jobsTodayCount++;
                }
                if (strpos($postedDate, $thisMonth) === 0) {
                    $jobsMonthlyCount++;
                }

                // Increment daily counts for the graph
                $dateIndex = array_search($postedDate, $graphLabels);
                if ($dateIndex !== false) {
                    $dailyJobCounts[$dateIndex]++;
                }
            }
            $graphData = $dailyJobCounts; // Data points for the graph

            break;
        case 'edit_job':
            $jobId = $_GET['id'] ?? null;
            if ($jobId) {
                $jobToEdit = null;
                 // Find the job to edit in the $allJobs array
                 foreach ($allJobs as $job) {
                      if (isset($job['id']) && (string)$job['id'] === (string)$jobId) {
                           $jobToEdit = $job;
                           break;
                      }
                 }
                 // If job not found, redirect to manage jobs with an error
                 if (!$jobToEdit) {
                      $_SESSION['admin_status'] = ['message' => 'Error: Job not found.', 'type' => 'error'];
                      header('Location: dashboard.php?view=manage_jobs');
                      exit;
                 }
            } else {
                // If no job ID provided, redirect to manage jobs with a warning
                 $_SESSION['admin_status'] = ['message' => 'Warning: No job ID specified for editing.', 'type' => 'warning'];
                 header('Location: dashboard.php?view=manage_jobs');
                 exit;
            }
            break;
        case 'generate_message':
            // This section attempts to include the message generation logic.
            // *** THE FATAL ERROR OCCURS ON THE NEXT LINE (around 160) IF generate_whatsapp_message.php IS NOT FOUND ***
            ob_start(); // Start output buffering
            // Include the script that generates the WhatsApp message content
            require __DIR__ . '/views/generate_message_view.php'; // <-- This line causes the error if the file is missing
            $whatsappMessage = ob_get_clean(); // Capture the output

             // Check if the included script outputted an error indicating data file not found
             if (empty($whatsappMessage) || trim($whatsappMessage) === "Error: Job data file not found.\n" || trim($whatsappMessage) === "Could not generate message. Job data is empty or missing.") {
                  $whatsappMessage = "Could not generate message. Check job data file or logs."; // More informative default message on failure
             }

            break;
        // No extra data loading needed for 'post_job', 'manage_jobs', 'profile', 'messages', 'manage_users' views
    }
}


// Include header (contains opening HTML, head, and navigation)
require_once __DIR__ . '/partials/header.php';

?>

    <div class="container">
        <h2>Admin Dashboard</h2>
        <div class="admin-nav">
            <?php $displayName = $_SESSION['admin_display_name'] ?? $_SESSION['admin_username'] ?? 'Admin'; ?>
            <a href="dashboard.php?view=dashboard" class="<?= $loggedIn && $requestedView === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
            <?php /* Removed Post New Job Tab: <a href="dashboard.php?view=post_job" class="<?= $loggedIn && $requestedView === 'post_job' ? 'active' : '' ?>">Post New Job</a> */ ?>
            <a href="dashboard.php?view=manage_jobs" class="<?= $loggedIn && ($requestedView === 'manage_jobs' || $requestedView === 'edit_job') ? 'active' : '' ?>">Manage Jobs</a>
            <a href="dashboard.php?view=messages" class="<?= $loggedIn && $requestedView === 'messages' ? 'active' : '' ?>">Messages</a>
             <a href="dashboard.php?view=generate_message" class="<?= $loggedIn && $requestedView === 'generate_message' ? 'active' : '' ?>">Generate Post</a>
            <div class="profile-dropdown">
                <a href="javascript:void(0);"><?= htmlspecialchars($displayName) ?> ▼</a>
                <div class="profile-dropdown-content">
                    <a href="dashboard.php?view=profile" class="<?= $loggedIn && $requestedView === 'profile' ? 'active' : '' ?>">Manage Profile</a>
                    <?php if ($loggedInUserRole === 'super_admin' || $loggedInUserRole === 'admin'): ?>
                         <a href="dashboard.php?view=manage_users" class="<?= $loggedIn && $requestedView === 'manage_users' ? 'active' : '' ?>">User Manager</a>
                    <?php endif; ?>
                    <a href="auth.php?action=logout">Logout</a>
                </div>
            </div>
        </div>

        <?php
        // --- Status Message Area ---
        // Display session status message if set after a redirect
        if (!empty($statusMessage)):
        ?>
            <div class="status-area status-message <?= htmlspecialchars($statusClass) ?>">
                <?= htmlspecialchars($statusMessage) ?>
            </div>
        <?php
        endif;
        // --- End Status Message Area ---
        ?>

        <div class="main-content" id="main-content">
            <?php
            // This area will be populated by AJAX or on initial load by fetch_content.php
            // On initial load, fetch_content.php is included directly here.
            // Check if it's an AJAX request by looking for the X-Requested-With header
            if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
                 // Not an AJAX request, load content directly
                 // fetch_content.php will output the HTML for the requested view
                 // Pass necessary variables to fetch_content.php via include scope
                 // Note: Variables like $allJobs, $feedbackMessages, $users, $jobToEdit, $whatsappMessage
                 // loaded above are available in the scope of fetch_content.php when included here.
                 require_once __DIR__ . '/fetch_content.php';
                 // The output from fetch_content.php (which includes the view file)
                 // will go directly into this div on the initial page load.
            } else {
                 // For AJAX requests, the fetch_content.php script is called separately
                 // by the JavaScript loadContent function in footer.php,
                 // and its output is handled by that function.
                 // So, nothing to do here for AJAX requests on the initial page load within this block.
                 // The AJAX request is a separate HTTP request handled by fetch_content.php itself.
            }

            // If the user is NOT logged in, display the login/registration/forgot password view directly
            // This happens on initial page load before AJAX takes over, OR if fetch_content redirects here.
            if (!$loggedIn) {
                 // The login view uses $loginError, $registerMessage, $forgotPasswordMessage
                 require_once __DIR__ . '/partials/login.php';
            }

            ?>
        </div>
    </div>

<?php
// Pass variables to footer.php for JavaScript
$currentViewForJS = $loggedIn ? $requestedView : ($requestedAction === 'register_form' ? 'register_form' : ($requestedAction === 'forgot_password_form' ? 'forgot_password_form' : 'login'));
$isLoggedInForJS = $loggedIn;
$userRoleForJS = $_SESSION['admin_role'] ?? 'user'; // Pass user role to JS
?>


<?php
// Include footer (contains closing HTML and scripts)
require_once __DIR__ . '/partials/footer.php';
?>
