<?php
<?php

// admin/dashboard.php - Main Admin Panel Entry Point and Router

session_start(); // Start the session to access session variables

// Include configuration and helper functions
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/user_helpers.php';
require_once __DIR__ . '/includes/job_helpers.php';
require_once __DIR__ . '/includes/feedback_helpers.php';
require_once __DIR__ . '/includes/user_manager_helpers.php';

// --- Initialize Variables ---
// Read status message from session and clear it
$statusMessage = '';
$statusClass = '';
if (isset($_SESSION['admin_status']['message'], $_SESSION['admin_status']['type'])) {
    $statusMessage = $_SESSION['admin_status']['message'];
    $statusClass = $_SESSION['admin_status']['type'];
    unset($_SESSION['admin_status']); // Clear the session status
}

$loginError = ''; // Specific variable for login errors
$registerMessage = ''; // Specific variable for registration messages
$forgotPasswordMessage = ''; // Specific variable for forgot password messages

// Check login status
$loggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Determine the requested view or action
$requestedView = $_GET['view'] ?? ($loggedIn ? 'dashboard' : 'login');
$requestedAction = $_GET['action'] ?? null;

// Redirect unauthenticated users to the login page
if (!$loggedIn && $requestedView !== 'login') {
    header('Location: dashboard.php?view=login');
    exit();
}

// Handle specific actions for unauthenticated users (e.g., register_form, forgot_password_form)
if (!$loggedIn && in_array($requestedAction, ['register_form', 'forgot_password_form'])) {
    $requestedView = $requestedAction; // Set the view to the action
    if ($statusMessage) {
        if ($requestedAction === 'register_form') {
            $registerMessage = $statusMessage;
        } elseif ($requestedAction === 'forgot_password_form') {
            $forgotPasswordMessage = $statusMessage;
        }
    }
}

// Load data for authenticated users
if ($loggedIn) {
    // Load data for views (e.g., jobs, feedback, users)
    $allJobs = loadJobs($jobsFilename);
    $feedbackMessages = loadFeedbackMessages($feedbackFilename);
    $users = loadUsers($usersFilename);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="styles/main.css"> <!-- Adjust the path if needed -->
</head>
<body>
    <?php require_once __DIR__ . '/partials/header.php'; ?>

    <div class="container">
        <?php if ($statusMessage): ?>
            <div class="status-message <?= htmlspecialchars($statusClass) ?>">
                <?= htmlspecialchars($statusMessage) ?>
            </div>
        <?php endif; ?>

        <div class="main-content" id="main-content">
            <?php
            if (!$loggedIn) {
                // Include the login view for unauthenticated users
                require_once __DIR__ . '/views/login.php';
            } else {
                // Include the appropriate view for authenticated users
                switch ($requestedView) {
                    case 'dashboard':
                        require_once __DIR__ . '/views/dashboard_view.php';
                        break;
                    case 'manage_jobs':
                        require_once __DIR__ . '/views/manage_jobs_view.php';
                        break;
                    case 'messages':
                        require_once __DIR__ . '/views/messages_view.php';
                        break;
                    case 'profile':
                        require_once __DIR__ . '/views/profile_view.php';
                        break;
                    case 'generate_message':
                        require_once __DIR__ . '/views/generate_message_view.php';
                        break;
                    case 'manage_users':
                        require_once __DIR__ . '/views/manage_users_view.php';
                        break;
                    default:
                        echo '<p>Invalid view requested.</p>';
                        break;
                }
            }
            ?>
        </div>
    </div>

    <?php require_once __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
