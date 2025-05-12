<?php

// admin/auth.php - Handles Authentication Actions

session_start(); // Start the session

// Include configuration and helper functions
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/user_helpers.php';

// Initialize status messages (will be stored in session and displayed on dashboard.php)
// Initialize regardless of action, as we always redirect back to dashboard.php
$_SESSION['admin_status'] = ['message' => '', 'type' => ''];

// Determine the action from the request (can be POST for login/register/forgot, GET for logout link)
$action = $_REQUEST['action'] ?? null; // Use $_REQUEST to get from GET or POST

// Handle Logout Action (Moved here from dashboard.php)
if ($action === 'logout') {
    // Destroy the session
    $_SESSION = array(); // Unset all session variables
    session_unset();
    session_destroy(); // Destroy the session
    // Redirect to the login page (which is now dashboard.php)
    header('Location: dashboard.php?view=login');
    exit; // Stop script execution after redirect
}


// Handle Login Attempt
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $users = loadUsers($usersFilename);
    $authenticatedUser = null;

    foreach ($users as $user) {
        if (isset($user['username']) && $user['username'] === $username) {
            if (isset($user['password_hash']) && password_verify($password, $user['password_hash'])) {
                $authenticatedUser = $user;
                break;
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
        // Store error message in session and redirect back to dashboard (login view)
        $_SESSION['admin_status'] = ['message' => 'Invalid username or password.', 'type' => 'error'];
        header('Location: dashboard.php'); // Redirect back to the main dashboard page
        exit;
    }
}

// Handle Registration Attempt
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $newUsername = trim($_POST['new_username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $newDisplayName = trim($_POST['new_display_name'] ?? '');

    // Basic validation (can add more checks if needed)
    if (empty($newUsername) || empty($newPassword) || empty($confirmPassword) || empty($newDisplayName)) {
        $_SESSION['admin_status'] = ['message' => 'Please fill in all fields.', 'type' => 'error'];
    } elseif ($newPassword !== $confirmPassword) {
        $_SESSION['admin_status'] = ['message' => 'Passwords do not match.', 'type' => 'error'];
    } else {
        $users = loadUsers($usersFilename);
        $usernameExists = false;
        foreach ($users as $user) {
            if (isset($user['username']) && $user['username'] === $newUsername) {
                $usernameExists = true;
                break;
            }
        }

        if ($usernameExists) {
            $_SESSION['admin_status'] = ['message' => 'Username already exists.', 'type' => 'error'];
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            if ($hashedPassword === false) {
                $_SESSION['admin_status'] = ['message' => 'Error creating password hash.', 'type' => 'error'];
                error_log("Admin Registration Error: password_hash failed.");
            } else {
                $newUser = [
                    "username" => $newUsername,
                    "password_hash" => $hashedPassword,
                    "display_name" => $newDisplayName,
                    "role" => "user" // Default role for newly registered users
                ];

                $users[] = $newUser;

                if (saveUsers($users, $usersFilename)) {
                    $_SESSION['admin_status'] = ['message' => 'Registration successful! You can now log in.', 'type' => 'success'];
                } else {
                    $_SESSION['admin_status'] = ['message' => 'Error saving user data.', 'type' => 'error'];
                }
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
