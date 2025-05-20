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

$loginError = ''; // Specific variable for login errors on the login view
$registerMessage = ''; // Specific variable for registration messages on the login view
$forgotPasswordMessage = ''; // Specific variable for forgot password messages on the login view


// Check login status
$loggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$loggedInUserRole = $_SESSION['admin_role'] ?? 'user'; // Default role if not set
$loggedInUsername = $_SESSION['admin_username'] ?? '';

error_log("--- [CRITICAL_DEBUG] dashboard.php: Top of script. Raw GET: " . print_r($_GET, true)); // Log raw GET

// Determine the requested view
$requestedView = $_GET['view'] ?? ($loggedIn ? 'dashboard' : 'login'); // Default to dashboard if logged in, login if not
$requestedAction = $_GET['action'] ?? null; // For handling specific actions routed through dashboard.php (like register_form)
error_log("--- [DEBUG] dashboard.php: Page Load Start ---");
error_log("[DEBUG] dashboard.php: Raw GET parameters: " . print_r($_GET, true));
error_log("[DEBUG] dashboard.php: Initial \$loggedIn state: " . ($loggedIn ? 'true' : 'false'));
error_log("[DEBUG] dashboard.php: Initial \$requestedView (from \$_GET['view'] or default) = '" . $requestedView . "'");

// Validate requested view if logged in
// 'post_job' view is removed from allowed views as its navigation link is removed, but fetchable via AJAX
$allowedViews = ['dashboard', 'manage_jobs', 'edit_job', 'edit_user', 'profile', 'messages', 'generate_message', 'manage_users', 'post_job'];
error_log("[CRITICAL_DEBUG] dashboard.php: Just before \$allowedViews check. \$requestedView = '" . $requestedView . "'. \$loggedIn = " . ($loggedIn ? 'true' : 'false'));

if ($loggedIn && $requestedView !== 'login') { // Ensure we don't try to validate 'login' if somehow requested while logged in
    if (!in_array($requestedView, $allowedViews)) {
            error_log("[ERROR_DEBUG] dashboard.php: Invalid view '" . $requestedView . "' requested for logged-in user. Allowed views: " . implode(', ', $allowedViews) . ". Defaulting to dashboard.");
        // If view is invalid for logged-in user, default to dashboard without an error
        $requestedView = 'dashboard';
            // It's possible the "invalid view specified" message comes from a part of your code that *displays* this error
            // if a certain condition isn't met, rather than this specific fallback.
    }
    // Authorization check for manage_users view
    if ($requestedView === 'manage_users' && !in_array($loggedInUserRole, ['super_admin', 'admin', 'user_group_manager'])) {
        $_SESSION['admin_status'] = ['message' => 'Access Denied: You do not have permission to manage users.', 'type' => 'error'];
        header('Location: dashboard.php?view=dashboard'); // Redirect to a safe page
        exit;
    }
}

// Calculate unread messages count if logged in (moved after $requestedView is determined and validated)
$unreadMessagesCount = 0;
if ($loggedIn && $requestedView !== 'login') { // Use the now defined $requestedView
    $messagesFilePath = __DIR__ . '/../data/feedback.json';
    $messages = file_exists($messagesFilePath) ? json_decode(file_get_contents($messagesFilePath), true) : [];
    if (!is_array($messages)) $messages = []; // Ensure it's an array

    foreach ($messages as $message) {
        if (isset($message['read']) && $message['read'] === false) {
            $unreadMessagesCount++;
        }
    }
}

// Handle specific actions that might require displaying a form (e.g., login, register, forgot password)
if (!$loggedIn) {
    // If not logged in, check the requested view
    if ($requestedView === 'login' || $requestedAction === 'login') {
        // Include the login view
        require_once __DIR__ . '/views/login.php';
        exit; // Stop further processing
    } elseif ($requestedAction === 'register_form') {
        require_once __DIR__ . '/views/register.php';
        exit;
    } elseif ($requestedAction === 'forgot_password_form') {
        require_once __DIR__ . '/views/forgot_password.php';
        exit;
    } else {
        // Default to login view if no valid action is provided
        require_once __DIR__ . '/views/login.php';
        exit;
    }
}


// Load Data for Views - Only if logged in
$allJobs = [];
$feedbackMessages = [];
$users = [];
$jobToEdit = null;
$userToEdit = null; // Initialize for edit_user view
$jobId = null; // Initialize jobId for edit_job view
// $whatsappMessage = null; // Initialize for generate_message view
// $telegramMessage = null; // Initialize for generate_message view
// This data loading is now handled by fetch_content.php

// The following variables are still needed for the header and navigation logic in dashboard.php
// $loggedIn (already set)
// $requestedView (already set)
// $unreadMessagesCount (already set)
// $loggedInUserRole (already set)
// $_SESSION['admin_display_name'] / $_SESSION['admin_username'] (used in nav)
// $statusMessage, $statusClass (used for displaying status)

// Include header (contains opening HTML, head, and navigation)
// Pass $unreadMessagesCount and $loggedInUserRole to header.php
$userRole = $loggedInUserRole; // Make it available with a simpler name if header expects $userRole
// The $unreadMessagesCount is already calculated above and will be in scope for header.php
error_log("[DEBUG] dashboard.php: Final Unread messages before including header: " . $unreadMessagesCount . " | LoggedIn: " . ($loggedIn ? 'Yes' : 'No')); // DETAILED DEBUG
require_once __DIR__ . '/partials/header.php';

?>

<style>
    .stats-grid {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
    }

    .stat-card {
        flex: 1;
        padding: 15px;
        background-color: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 5px;
        text-align: center;
    }

    .stat-card h4 {
        margin-bottom: 10px;
        font-size: 1.2rem;
        color: #333;
    }

    .stat-card p {
        font-size: 1.5rem;
        font-weight: bold;
        color: #007bff;
    }

    .chart-container {
        margin-top: 20px;
        padding: 15px;
        background-color: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 5px;
    }

    .chart-container h3 {
        margin-bottom: 10px;
        font-size: 1.5rem;
        color: #333;
    }

    canvas {
        max-width: 100%;
        height: auto;
    }
</style>

    <div class="container">
        <h2>Admin Dashboard</h2>
        <div class="admin-nav">
            <?php $displayName = $_SESSION['admin_display_name'] ?? $_SESSION['admin_username'] ?? 'Admin'; ?>
            <a href="?view=dashboard" class="<?= $loggedIn && $requestedView === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
            <?php /* Removed Post New Job Tab: <a href="dashboard.php?view=post_job" class="<?= $loggedIn && $requestedView === 'post_job' ? 'active' : '' ?>">Post New Job</a> */ ?>
            <a href="?view=manage_jobs" class="<?= $loggedIn && ($requestedView === 'manage_jobs' || $requestedView === 'edit_job') ? 'active' : '' ?>">Manage Jobs</a>
                            <a href="?view=messages">
                    Messages
                    <?php if (isset($unreadMessagesCount) && $unreadMessagesCount > 0): ?>
                        <span class="unread-badge"><?= htmlspecialchars($unreadMessagesCount) ?></span>
                    <?php endif; ?>
                </a>
             <a href="?view=generate_message" class="<?= $loggedIn && $requestedView === 'generate_message' ? 'active' : '' ?>">Generate Post</a>
            <div class="profile-dropdown">
                <a href="javascript:void(0);"><?= htmlspecialchars($displayName) ?> ▼</a>
                <div class="profile-dropdown-content">
                    <a href="dashboard.php?view=profile" class="<?= $loggedIn && $requestedView === 'profile' ? 'active' : '' ?>">Manage Profile</a>
                    <?php if ($loggedInUserRole === 'super_admin' || $loggedInUserRole === 'admin' || $loggedInUserRole === 'user_group_manager'): ?>
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
                 // Note: Variables like $allJobs, $feedbackMessages, $users, $jobToEdit, $whatsappMessage, $telegramMessage
                 // loaded above (or rather, made available by fetch_content.php) are available in the scope of fetch_content.php when included here.
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
                 require_once __DIR__ . '/views/login.php';
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
