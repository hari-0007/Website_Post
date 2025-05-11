<?php

// admin/profile_actions.php - Handles Profile Management Actions

session_start(); // Start the session

// Include configuration and helper functions
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/user_helpers.php';

// Check if the user is logged in. If not, redirect to login page.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: dashboard.php'); // Redirect to the main page which handles login
    exit;
}

// Initialize status messages (will be stored in session and displayed on dashboard.php)
$_SESSION['admin_status'] = ['message' => '', 'type' => '']; // Use general status for profile actions too

// Determine the action from the POST request
$action = $_POST['action'] ?? null;
$currentUsername = $_SESSION['admin_username'] ?? null; // Get current username from session

if ($currentUsername === null) {
    $_SESSION['admin_status'] = ['message' => 'Error: User not logged in or session missing.', 'type' => 'error'];
    header('Location: dashboard.php?view=profile'); // Redirect back to profile view
    exit;
}

// Handle Change Display Name Attempt
if ($action === 'change_display_name' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $newDisplayName = trim($_POST['new_display_name'] ?? '');

    if (empty($newDisplayName)) {
        $_SESSION['admin_status'] = ['message' => 'New display name cannot be empty.', 'type' => 'error'];
    } else {
        $users = loadUsers($usersFilename);
        $userFoundAndUpdated = false;
        foreach ($users as &$user) { // Pass by reference to modify the user object in the array
            if (isset($user['username']) && $user['username'] === $currentUsername) {
                $user['display_name'] = $newDisplayName;
                $userFoundAndUpdated = true;
                break;
            }
        }
        unset($user); // Unset the reference

        if ($userFoundAndUpdated) {
            if (saveUsers($users, $usersFilename)) {
                $_SESSION['admin_display_name'] = $newDisplayName; // Update session
                $_SESSION['admin_status'] = ['message' => 'Your display name has been updated.', 'type' => 'success'];
            } else {
                $_SESSION['admin_status'] = ['message' => 'Error saving updated user data.', 'type' => 'error'];
            }
        } else {
             $_SESSION['admin_status'] = ['message' => 'Error: Could not find user in data file to update display name.', 'type' => 'error'];
             error_log("Admin Profile Error: User not found in user.json for display name update: " . $currentUsername);
        }
    }
    // Redirect back to profile view
    header('Location: dashboard.php?view=profile');
    exit;
}

// Handle Change Password Attempt
if ($action === 'change_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $_SESSION['admin_status'] = ['message' => 'Please fill in all password fields.', 'type' => 'error'];
    } elseif ($newPassword !== $confirmPassword) {
        $_SESSION['admin_status'] = ['message' => 'New passwords do not match.', 'type' => 'error'];
    } else {
        $users = loadUsers($usersFilename);
        $userFound = null;
        foreach ($users as &$user) { // Pass by reference to modify the user object
             if (isset($user['username']) && $user['username'] === $currentUsername) {
                 $userFound = &$user; // Keep a reference to the found user
                 break;
             }
        }
        unset($user); // Unset the loop reference

        if ($userFound && isset($userFound['password_hash'])) {
             // Securely verify the current password
            if (password_verify($currentPassword, $userFound['password_hash'])) {
                // Hash the new password securely
                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                if ($hashedPassword === false) {
                    $_SESSION['admin_status'] = ['message' => 'Error creating new password hash.', 'type' => 'error'];
                     error_log("Admin Profile Error: password_hash failed during password change.");
                } else {
                    $userFound['password_hash'] = $hashedPassword; // Update the password hash

                    if (saveUsers($users, $usersFilename)) {
                        $_SESSION['admin_status'] = ['message' => 'Your password has been updated.', 'type' => 'success'];
                        // Clear password fields on success - no need, just display message
                    } else {
                        $_SESSION['admin_status'] = ['message' => 'Error saving updated user data.', 'type' => 'error'];
                    }
                }
            } else {
                $_SESSION['admin_status'] = ['message' => 'Incorrect current password.', 'type' => 'error'];
            }
        } else {
             $_SESSION['admin_status'] = ['message' => 'Error: Could not find user in data file or password hash missing.', 'type' => 'error'];
             error_log("Admin Profile Error: User not found or password_hash missing in user.json for password change: " . $currentUsername);
        }
    }
     // Redirect back to profile view
     header('Location: dashboard.php?view=profile');
     exit;
}


// If no valid action was provided, redirect to the profile view
$_SESSION['admin_status'] = ['message' => 'Invalid profile action.', 'type' => 'error'];
header('Location: dashboard.php?view=profile');
exit;

?>
