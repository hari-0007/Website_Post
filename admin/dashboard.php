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
$allRegionalAdminRoles = ['India_Admin', 'Middle_East_Admin', 'USA_Admin', 'Europe_Admin']; // Define regional admin roles
$allRegionalManagerRoles = ['India_Manager', 'Middle_East_Manager', 'USA_Manager', 'Europe_Manager']; // Define regional manager roles


error_log("--- [CRITICAL_DEBUG] dashboard.php: Top of script. Raw GET: " . print_r($_GET, true)); // Log raw GET

// Determine the requested view
$requestedView = $_GET['view'] ?? ($loggedIn ? 'dashboard_overview' : 'login'); // Default to dashboard_overview if logged in, login if not
$requestedAction = $_GET['action'] ?? null; // For handling specific actions routed through dashboard.php (like register_form)
error_log("--- [DEBUG] dashboard.php: Page Load Start ---");
error_log("[DEBUG] dashboard.php: Raw GET parameters: " . print_r($_GET, true));
error_log("[DEBUG] dashboard.php: Initial \$loggedIn state: " . ($loggedIn ? 'true' : 'false'));
error_log("[DEBUG] dashboard.php: Initial \$requestedView (from \$_GET['view'] or default based on login) = '" . $requestedView . "'");

// Validate requested view if logged in
$allowedViews = ['dashboard_overview', 'dashboard_service_one', 'dashboard_user_info', 'dashboard_job_stats', 'dashboard_service_two', 'dashboard_visitors_info', 'dashboard_qoe', 'manage_jobs', 'reported_jobs', 'edit_job', 'edit_user', 'profile', 'messages', 'generate_message', 'manage_users', 'post_job', 'achievements', 'server_management', 'whatsapp_profile', 'logs'];
error_log("[CRITICAL_DEBUG] dashboard.php: Just before \$allowedViews check. \$requestedView = '" . $requestedView . "'. \$loggedIn = " . ($loggedIn ? 'true' : 'false'));

if ($loggedIn && $requestedView !== 'login') { // Ensure we don't try to validate 'login' if somehow requested while logged in
    if (!in_array($requestedView, $allowedViews)) {
            error_log("[ERROR_DEBUG] dashboard.php: Invalid view '" . htmlspecialchars($requestedView) . "' requested for logged-in user. Allowed views: " . implode(', ', $allowedViews) . ". Defaulting to 'dashboard_overview'.");
        // If view is invalid for logged-in user, default to dashboard without an error
        $requestedView = 'dashboard_overview';
            // It's possible the "invalid view specified" message comes from a part of your code that *displays* this error
            // if a certain condition isn't met, rather than this specific fallback.
    }
    // Authorization check for manage_users view
    // Super Admin, Regional Admins, and Regional Managers can access manage_users view
    if ($requestedView === 'manage_users' && !($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles) || in_array($loggedInUserRole, $allRegionalManagerRoles))) {
        $_SESSION['admin_status'] = ['message' => 'Access Denied: You do not have permission to manage users.', 'type' => 'error'];
        header('Location: dashboard.php?view=dashboard_overview'); // Redirect to a safe page
        exit;
    }
    // Authorization check for messages view: Super Admin and Regional Admins
    if ($requestedView === 'messages' && !($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles))) {
        $_SESSION['admin_status'] = ['message' => 'Access Denied: You do not have permission to view messages.', 'type' => 'error'];
        header('Location: dashboard.php?view=dashboard_overview'); // Redirect to dashboard overview
        exit;
    }

    // Authorization check for generate_message view: Super Admin and Regional Admins
    if ($requestedView === 'generate_message' && !($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles))) {
        $_SESSION['admin_status'] = ['message' => 'Access Denied: You do not have permission to generate posts.', 'type' => 'error']; // Message for unauthorized access
        header('Location: dashboard.php?view=dashboard_overview');
        exit;
    }
    // Authorization check for achievements view: All logged-in users can see it for now.
    // You might want to restrict this later based on roles if needed.
    // if ($requestedView === 'achievements' && !($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles))) {
    //     $_SESSION['admin_status'] = ['message' => 'Access Denied: You do not have permission to view achievements.', 'type' => 'error'];
    //     header('Location: dashboard.php?view=dashboard_overview');
    //     exit;
    // }
    // Authorization check for server_management (Git/Server Info) and whatsapp_profile views
    if ($requestedView === 'server_management' && !($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles))) {
        $_SESSION['admin_status'] = ['message' => 'Access Denied: You do not have permission to view server management.', 'type' => 'error'];
        header('Location: dashboard.php?view=dashboard_overview');
        exit;
    }
    // Authorization check for logs view: Super Admin ONLY
    if ($requestedView === 'logs' && $loggedInUserRole !== 'super_admin') {
        $_SESSION['admin_status'] = ['message' => 'Access Denied: You do not have permission to view server logs.', 'type' => 'error'];
        header('Location: dashboard.php?view=dashboard_overview');
        exit;
    }
    if ($requestedView === 'whatsapp_profile' && !($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles))) {
        $_SESSION['admin_status'] = ['message' => 'Access Denied: You do not have permission to view server logs.', 'type' => 'error'];
        header('Location: dashboard.php?view=dashboard_overview');
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
        display: grid; /* Changed to grid for better control */
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); /* Responsive columns */
        gap: 20px;
        margin-bottom: 20px;
    }

    .stat-card {
        padding: 15px;
        background-color: #ffffff; /* Cleaner background */
        border: 1px solid #e0e0e0; /* Softer border */
        border-radius: 6px; /* Slightly more rounded */
        text-align: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05); /* Subtle shadow */
    }

    .stat-card h4 {
        margin-bottom: 10px;
        font-size: 1rem; /* Adjusted size */
        color: #555; /* Softer color */
    }

    .stat-card p {
        font-size: 1.8rem; /* Larger number */
        font-weight: bold;
        color: #007bff;
    }

    .chart-container {
        margin-top: 20px;
        padding: 15px;
        background-color: #ffffff;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
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
    /* In your admin_styles.css or embedded style tag */
.sub-nav {
    background-color: #f8f9fa; /* Lighter background */
    padding: 10px 0;
    margin-bottom: 20px;
    text-align: center;
    border-radius: 6px;
    border: 1px solid #dee2e6;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.sub-nav a {
    color: #005fa3;
    padding: 10px 15px;
    text-decoration: none;
    font-weight: 500; /* Medium weight */
    transition: background-color 0.3s ease, color 0.3s ease;
    border-radius: 4px; /* Add radius to individual links for hover effect */
    margin: 0 3px; /* Spacing between links */
}

.sub-nav a:hover {
    background-color: #e2e6ea;
    color: #004a80;
}

.sub-nav a.active {
    background-color: #005fa3; /* Primary color */
    color: white;
}

</style>

    <div class="container">
        <?php
         ?>

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
// If $requestedView is 'dashboard', it implies 'dashboard_overview' for JS content loading purposes
$currentViewForJS = $loggedIn ? 
    (($requestedView === 'dashboard') ? 'dashboard_overview' : $requestedView) : 
    ($requestedAction === 'register_form' ? 'register_form' : ($requestedAction === 'forgot_password_form' ? 'forgot_password_form' : 'login'));

$isLoggedInForJS = $loggedIn;
$userRoleForJS = $_SESSION['admin_role'] ?? 'user'; // Pass user role to JS
?>


<?php
// Include footer (contains closing HTML and scripts)
require_once __DIR__ . '/partials/footer.php';
?>
