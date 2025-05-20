<?php

// admin/auth.php - Handles Authentication Actions

// Start the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration and helper functions
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/user_helpers.php';

// Initialize status messages (will be stored in session and displayed on dashboard.php)
// Initialize regardless of action, as we always redirect back to dashboard.php
$_SESSION['admin_status'] = ['message' => '', 'type' => ''];

// Handle logout action
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Destroy the session
    session_unset();
    session_destroy();

    // Redirect to the login page
    header('Location: dashboard.php?view=login');
    exit;
}

// Determine the action from the request (can be POST for login/register/forgot, GET for logout link)
$action = $_REQUEST['action'] ?? null; // Use $_REQUEST to get from GET or POST

// Handle Login Attempt
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginInput = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $processedUsername = $loginInput;

    // If login input doesn't contain "@", assume it's a username part for "@jobhunt.top"
    if (strpos($loginInput, '@') === false) {
        $processedUsername = $loginInput . '@jobhunt.top';
    }
    error_log("[AUTH_DEBUG] Login attempt. Input: '$loginInput', Processed: '$processedUsername'");

    $users = loadUsers($usersFilename);
    $authenticatedUser = null;
    foreach ($users as $user) {
        if (isset($user['username']) && strtolower($user['username']) === strtolower($processedUsername)) {
            if (isset($user['password_hash']) && password_verify($password, $user['password_hash'])) {
                // Check if user status is active
                if (isset($user['status']) && $user['status'] === 'active') {
                    $authenticatedUser = $user;
                    break;
                } elseif (isset($user['status']) && $user['status'] === 'pending_approval') {
                    $_SESSION['admin_status'] = ['message' => 'Your account is pending administrator approval.', 'type' => 'warning'];
                    header('Location: dashboard.php'); exit;
                } elseif (isset($user['status']) && $user['status'] === 'disabled') {
                    $_SESSION['admin_status'] = ['message' => 'Your account has been disabled. Please contact an administrator.', 'type' => 'error'];
                    header('Location: dashboard.php'); exit;
                } else {
                    // This case handles if password is correct but status is unrecognized or missing
                    $_SESSION['admin_status'] = ['message' => 'Account status is unrecognized. Please contact an administrator.', 'type' => 'error'];
                    header('Location: dashboard.php'); 
                    exit;
                }
            }
        }
    }

    if ($authenticatedUser) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $authenticatedUser['username'];
        $_SESSION['admin_display_name'] = $authenticatedUser['display_name'] ?? $authenticatedUser['username'];
        // Store the user's role in the session
        $_SESSION['admin_role'] = $authenticatedUser['role'] ?? 'user'; // Default to 'user' if role is missing

        // Redirect to dashboard on success
        header('Location: dashboard.php');
        exit;
    } else {
        // This 'else' is reached if no user matched the username, or if a user matched but password was incorrect.
        // The cases for correct password but wrong status are handled inside the loop with an exit.
        $_SESSION['admin_status'] = ['message' => 'Invalid email/username or password.', 'type' => 'error'];
        error_log("[AUTH_DEBUG] Login failed for processed username: '$processedUsername'");
        header('Location: dashboard.php'); // Redirect back to the main dashboard page
        exit;
    }
}

// Handle Registration Attempt
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailAsUsername = trim($_POST['username'] ?? ''); // Changed from new_username
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $newDisplayName = trim($_POST['new_display_name'] ?? '');

    // Basic validation (can add more checks if needed)
    if (empty($emailAsUsername) || empty($newPassword) || empty($confirmPassword) || empty($newDisplayName)) {
        $_SESSION['admin_status'] = ['message' => 'Please fill in all fields.', 'type' => 'error'];
    } elseif ($newPassword !== $confirmPassword) {
        $_SESSION['admin_status'] = ['message' => 'Passwords do not match.', 'type' => 'error'];
    } elseif (!filter_var($emailAsUsername, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['admin_status'] = ['message' => 'Invalid email format for username.', 'type' => 'error'];
    } else {
        $users = loadUsers($usersFilename);
        $usernameExists = false;
        foreach ($users as $user) {
            // Case-insensitive check for email
            if (isset($user['username']) && strtolower($user['username']) === strtolower($emailAsUsername)) {
                $usernameExists = true;
                break;
            }
        }

        if ($usernameExists) {
            $_SESSION['admin_status'] = ['message' => 'An account with this email already exists.', 'type' => 'error'];
        } else {
            // Use the createUser function from user_manager_helpers.php
            // Ensure user_manager_helpers.php is included if createUser is defined there.
            // For now, assuming createUser is available or we replicate its core logic.
            // The createUser function in user_manager_helpers.php already sets status to 'pending_approval'.
            
            // We need to include user_manager_helpers.php to use createUser
            require_once __DIR__ . '/includes/user_manager_helpers.php'; 

            $newUser = createUser($emailAsUsername, $newPassword, $newDisplayName, "user", $usersFilename);

            if ($newUser) {
                 $_SESSION['admin_status'] = [
                    'message' => 'Registration successful! Your account is now pending administrator approval.',
                    'type' => 'success'
                ];
            } elseif ($newUser === false && findUserByUsername($emailAsUsername, $usersFilename)) {
                // This condition might be redundant if createUser handles existing username check robustly
                $_SESSION['admin_status'] = ['message' => 'An account with this email already exists.', 'type' => 'error'];
            } else {
                $_SESSION['admin_status'] = ['message' => 'Error during registration. Could not save user data.', 'type' => 'error'];
            }
        }
    }
    // Redirect back to dashboard with register form view
    header('Location: dashboard.php?action=register_form');
    exit;
}

// Handle Forgot Password Request (Placeholder)
if ($action === 'forgot_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $_SESSION['admin_status'] = ['message' => 'Please enter your email address.', 'type' => 'error'];
    } else {
        // PLACEHOLDER: Implement secure forgot password logic here
        $_SESSION['admin_status'] = ['message' => 'Forgot password feature is a placeholder. Sending reset emails and processing tokens require further implementation.', 'type' => 'info']; // Changed type to info
    }
    // Redirect back to dashboard with forgot password form view
    header('Location: dashboard.php?action=forgot_password_form');
    exit;
}


// If no valid action was provided, redirect to the dashboard
// This ensures if auth.php is accessed directly without a valid action, it redirects.
if ($action !== 'login' && $action !== 'register' && $action !== 'forgot_password' && $action !== 'logout') {
    $_SESSION['admin_status'] = ['message' => 'Invalid authentication action.', 'type' => 'error'];
}

// Always redirect back to dashboard.php if not already exited by a specific action handler
header('Location: dashboard.php');
exit;


?>
