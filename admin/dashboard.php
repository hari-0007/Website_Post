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
$statusMessage = '';
$statusClass = '';
$loginError = '';
$registerMessage = '';
$forgotPasswordMessage = '';
$profileStatusMessage = '';

if (isset($_SESSION['admin_status'])) {
    if (strpos($_SESSION['admin_status']['message'], 'Invalid username') !== false) {
        $loginError = $_SESSION['admin_status']['message'];
    } elseif (strpos($_SESSION['admin_status']['message'], 'Registration') !== false || strpos($_SESSION['admin_status']['message'], 'Username already exists') !== false || strpos($_SESSION['admin_status']['message'], 'Passwords do not match') !== false || strpos($_SESSION['admin_status']['message'], 'fill in all fields') !== false) {
         $registerMessage = $_SESSION['admin_status']['message'];
         $statusClass = $_SESSION['admin_status']['type'];
    } elseif (strpos($_SESSION['admin_status']['message'], 'Forgot password') !== false || strpos($_SESSION['admin_status']['message'], 'email address') !== false) {
         $forgotPasswordMessage = $_SESSION['admin_status']['message'];
         $statusClass = $_SESSION['admin_status']['type'];
    } elseif (strpos($_SESSION['admin_status']['message'], 'display name') !== false || strpos($_SESSION['admin_status']['message'], 'password') !== false || strpos($_SESSION['admin_status']['message'], 'User not logged in') !== false || strpos($_SESSION['admin_status']['message'], 'Could not find user') !== false) {
        $profileStatusMessage = $_SESSION['admin_status']['message'];
        $statusClass = $_SESSION['admin_status']['type'];
    } elseif (strpos($_SESSION['admin_status']['message'], 'user created successfully') !== false || strpos($_SESSION['admin_status']['message'], 'user deleted successfully') !== false || strpos($_SESSION['admin_status']['message'], 'username already exists') !== false || strpos($_SESSION['admin_status']['message'], 'permission to create') !== false || strpos($_SESSION['admin_status']['message'], 'permission to delete') !== false || strpos($_SESSION['admin_status']['message'], 'username specified') !== false || strpos($_SESSION['admin_status']['message'], 'cannot delete your own account') !== false || strpos($_SESSION['admin_status']['message'], 'user with specified username not found') !== false || strpos($_SESSION['admin_status']['message'], 'Invalid user action') !== false) {
         $statusMessage = $_SESSION['admin_status']['message'];
         $statusClass = $_SESSION['admin_status']['type'];
    } else {
        $statusMessage = $_SESSION['admin_status']['message'];
        $statusClass = $_SESSION['admin_status']['type'];
    }
    unset($_SESSION['admin_status']);
}

$requestedView = $_GET['view'] ?? 'dashboard';
$requestedAction = $_GET['action'] ?? null;

$loggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$loggedInUserRole = $_SESSION['admin_role'] ?? 'user';

if (!$loggedIn && !in_array($requestedAction, ['register_form', 'forgot_password_form'])) {
    $requestedView = 'login';
    $requestedAction = null;
}

$allowedViews = ['dashboard', 'post_job', 'manage_jobs', 'messages', 'profile', 'generate_message'];
$restrictedViews = [
    'manage_users' => ['super_admin', 'admin'],
];

if ($loggedIn) {
    $validViews = array_merge($allowedViews, array_keys($restrictedViews));

    if (in_array($requestedView, array_keys($restrictedViews))) {
        if (!in_array($loggedInUserRole, $restrictedViews[$requestedView])) {
            $_SESSION['admin_status'] = ['message' => 'Access Denied: You do not have permission to view this page.', 'type' => 'error'];
            header('Location: dashboard.php?view=dashboard');
            exit;
        }
    } elseif (!in_array($requestedView, $allowedViews) && $requestedAction === null) {
         if (!in_array($requestedView, $validViews)) {
             $_SESSION['admin_status'] = ['message' => 'Invalid view requested, showing dashboard.', 'type' => 'error'];
             header('Location: dashboard.php?view=dashboard');
             exit;
         }
    }
}

$allJobs = [];
$whatsappMessage = null;
$totalViews = 0;
$jobsTodayCount = 0;
$jobsMonthlyCount = 0;
$dailyJobCounts = [];
$graphLabels = [];
$graphData = [];
$jobToEdit = [];
$formData = [];
$jobId = $_GET['id'] ?? null;
$feedbackMessages = [];
$users = [];

if ($loggedIn) {
    switch ($requestedView) {
        case 'dashboard':
            $allJobs = loadJobs($jobsFilename);
            $totalViews = (int)@file_get_contents($viewCounterFile);
            $currentTime = time();
            $startOfToday = strtotime('today midnight');
            $startOfMonth = strtotime('first day of this month midnight');
            for ($i = 0; $i < 30; $i++) {
                $date = date('Y-m-d', $startOfToday - ($i * 24 * 60 * 60));
                $dailyJobCounts[$date] = 0;
            }
            krsort($dailyJobCounts);
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
            $allJobs = loadJobs($jobsFilename);
            break;
        case 'messages':
            $feedbackMessages = loadFeedbackMessages($feedbackFilename);
            break;
         case 'manage_users':
             // Data loading for manage_users might occur here or be fully handled by fetch_content.php
             $users = loadUsers($usersFilename); // Ensure this function and $usersFilename are correct
             break;
        case 'generate_message':
            // Data for generate_message is primarily handled by fetch_content.php
            break;
    }
}

require_once __DIR__ . '/partials/header.php';

if ($statusMessage): ?>
    <div class="status-message <?= htmlspecialchars($statusClass) ?>">
        <?= htmlspecialchars($statusMessage) ?>
    </div>
<?php endif; ?>
<?php if ($profileStatusMessage): ?>
    <div class="status-message <?= htmlspecialchars($statusClass) ?>">
        <?= htmlspecialchars($profileStatusMessage) ?>
    </div>
<?php endif; ?>

<?php if ($loggedIn): ?>
    <div class="container">
        <h2>Admin Dashboard</h2>
        <div class="admin-nav">
            <a href="dashboard.php?view=dashboard">Dashboard</a>
            <a href="dashboard.php?view=post_job">Post New Job</a>
            <a href="dashboard.php?view=manage_jobs">Manage Jobs</a>
            <a href="dashboard.php?view=messages">Messages</a>
            <a href="dashboard.php?view=generate_message">Generate Post</a>
            <div class="profile-dropdown">
                <?php $displayName = $_SESSION['admin_display_name'] ?? $_SESSION['admin_username'] ?? 'Admin'; ?>
                <a href="javascript:void(0);"><?= htmlspecialchars($displayName) ?> ▼</a>
                <div class="profile-dropdown-content">
                    <a href="dashboard.php?view=profile">Manage Profile</a>
                    <?php if ($loggedInUserRole === 'super_admin' || $loggedInUserRole === 'admin'): ?>
                         <a href="dashboard.php?view=manage_users">User Manager</a>
                    <?php endif; ?>
                    <a href="auth.php?action=logout">Logout</a>
                </div>
            </div>
        </div>
        <div class="main-content" id="main-content">
            </div>
    </div>
<?php else: ?>
    <?php require_once __DIR__ . '/views/login.php'; ?>
<?php endif; ?>

<?php
$currentViewForJS = $loggedIn ? $requestedView : 'login';
$isLoggedInForJS = $loggedIn;
$userRoleForJS = $loggedInUserRole;
require_once __DIR__ . '/partials/footer.php';
?>