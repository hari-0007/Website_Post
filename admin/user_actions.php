<?php

// admin/user_actions.php - Handles User Management Actions

session_start(); // Start the session

// Include configuration and helper functions
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/user_helpers.php'; // For load/saveUsers
require_once __DIR__ . '/includes/user_manager_helpers.php'; // For createUser, deleteUser, etc.

// Check if the user is logged in. If not, redirect to login page.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // For AJAX requests, return a JSON response instead of redirecting
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.', 'redirect' => 'dashboard.php']);
        exit;
    } else {
        // For non-AJAX requests, redirect to login page
        header('Location: dashboard.php'); // Redirect to the main page which handles login
        exit;
    }
}

// Initialize status messages (will be stored in session and displayed on dashboard.php)
$_SESSION['admin_status'] = ['message' => '', 'type' => ''];

// Get logged-in user's role and username for authorization checks
$loggedInUserRole = $_SESSION['admin_role'] ?? 'user';
$loggedInUsername = $_SESSION['admin_username'] ?? '';

// Determine the action from the POST or GET request
$action = $_POST['action'] ?? $_GET['action'] ?? null;


// --- Handle Create User Action ---
if ($action === 'create_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $displayName = trim($_POST['display_name'] ?? '');
    $role = $_POST['role'] ?? 'user'; // Default new user role to 'user'

    // Basic validation
    if (empty($username) || empty($password) || empty($confirmPassword) || empty($displayName) || empty($role)) {
        $_SESSION['admin_status'] = ['message' => 'Error: Please fill in all fields.', 'type' => 'error'];
    } elseif ($password !== $confirmPassword) {
        $_SESSION['admin_status'] = ['message' => 'Error: Passwords do not match.', 'type' => 'error'];
    } else {
        // Authorization check: Can the logged-in user create a user with the requested role?
        $canCreate = false;
        if ($loggedInUserRole === 'super_admin') {
            // Super Admin can create any role
            $canCreate = true;
        } elseif ($loggedInUserRole === 'admin') {
            // Admin can create 'admin', 'user_group_manager', or 'user'
            if (in_array($role, ['admin', 'user_group_manager', 'user'])) {
                 $canCreate = true;
            }
        } elseif ($loggedInUserRole === 'user_group_manager') {
            // User Group Manager can only create 'user'
            if ($role === 'user') {
                 $canCreate = true;
            }
        }

        if (!$canCreate) {
            $_SESSION['admin_status'] = ['message' => 'Error: You do not have permission to create a user with this role.', 'type' => 'error'];
            error_log("Admin User Action Error: User '" . $loggedInUsername . "' attempted to create user with unauthorized role '" . $role . "'.");
        } else {
            // Attempt to create the user using the helper function
            $newUser = createUser($username, $password, $displayName, $role, $usersFilename);

            if ($newUser) {
                $_SESSION['admin_status'] = ['message' => 'Success: User "' . htmlspecialchars($username) . '" created successfully!', 'type' => 'success'];
            } elseif ($newUser === false) {
                 // createUser returns false if username exists or save fails
                 $users = loadUsers($usersFilename); // Reload users to check for existence
                 $usernameExists = false;
                 foreach ($users as $user) {
                     if (isset($user['username']) && $user['username'] === $username) {
                         $usernameExists = true;
                         break;
                     }
                 }
                 if ($usernameExists) {
                      $_SESSION['admin_status'] = ['message' => 'Error: Username "' . htmlspecialchars($username) . '" already exists.', 'type' => 'error'];
                 } else {
                      $_SESSION['admin_status'] = ['message' => 'Error: Could not create user. Check logs.', 'type' => 'error'];
                 }
            }
        }
    }

    // Redirect back to the user manager view
    header('Location: dashboard.php?view=manage_users');
    exit;
}

// --- Handle Delete User Action ---
if ($action === 'delete_user' && $_SERVER['REQUEST_METHOD'] === 'GET') { // Triggered by GET link
    $usernameToDelete = $_GET['username'] ?? null;

    if (empty($usernameToDelete)) {
        $_SESSION['admin_status'] = ['message' => 'Error: No username specified for deletion.', 'type' => 'error'];
    } elseif ($usernameToDelete === $loggedInUsername) {
        $_SESSION['admin_status'] = ['message' => 'Error: You cannot delete your own account.', 'type' => 'error'];
    } else {
        // Authorization check: Can the logged-in user delete this user?
        $canDelete = false;
        $users = loadUsers($usersFilename); // Load users to check the role of the user to delete
        $userToDelete = null;
        foreach ($users as $user) {
             if (isset($user['username']) && $user['username'] === $usernameToDelete) {
                 $userToDelete = $user;
                 break;
             }
        }

        if ($userToDelete) {
            $roleToDelete = $userToDelete['role'] ?? 'user';

            if ($loggedInUserRole === 'super_admin') {
                // Super Admin can delete any role except themselves (already checked)
                $canDelete = true;
            } elseif ($loggedInUserRole === 'admin') {
                // Admin can delete 'user_group_manager' or 'user'
                if (in_array($roleToDelete, ['user_group_manager', 'user'])) {
                    $canDelete = true;
                }
            }
            // User Group Managers and Users cannot delete anyone (implicitly handled as canDelete remains false)

            if (!$canDelete) {
                $_SESSION['admin_status'] = ['message' => 'Error: You do not have permission to delete this user.', 'type' => 'error'];
                 error_log("Admin User Action Error: User '" . $loggedInUsername . "' attempted to delete user '" . $usernameToDelete . "' with unauthorized role '" . $roleToDelete . "'.");
            } else {
                // Attempt to delete the user using the helper function
                if (deleteUser($usernameToDelete, $usersFilename)) {
                    $_SESSION['admin_status'] = ['message' => 'Success: User "' . htmlspecialchars($usernameToDelete) . '" deleted successfully!', 'type' => 'success'];
                } else {
                    $_SESSION['admin_status'] = ['message' => 'Error: Could not delete user. Check logs.', 'type' => 'error'];
                }
            }
        } else {
             $_SESSION['admin_status'] = ['message' => 'Error: User with specified username not found for deletion.', 'type' => 'error'];
             error_log("Admin User Action Error: User ID not found for deletion: " . $usernameToDelete);
        }
    }

    // Redirect back to the user manager view
    header('Location: dashboard.php?view=manage_users');
    exit;
}


// --- Handle Edit User Action (Placeholder - requires an edit view) ---
// If you implement an edit user view (e.g., dashboard.php?view=edit_user&username=...),
// you would add a POST handler here for the form submission.
/*
if ($action === 'update_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameToUpdate = $_POST['username_to_update'] ?? null;
    $updateData = $_POST; // Get all post data

    // Remove action and username_to_update from updateData
    unset($updateData['action']);
    unset($updateData['username_to_update']);

    // Basic validation (add more checks as needed)
    if (empty($usernameToUpdate)) {
         $_SESSION['admin_status'] = ['message' => 'Error: No username specified for update.', 'type' => 'error'];
    } else {
        // Authorization check: Can the logged-in user update this user?
        // This would involve checking the role of the user being updated
        // and comparing it to the logged-in user's role and permissions.
        // (Similar logic to delete_user authorization)

        // if (authorized to update) {
             // Attempt to update the user using the helper function
             if (updateUser($usernameToUpdate, $updateData, $usersFilename)) {
                 $_SESSION['admin_status'] = ['message' => 'Success: User "' . htmlspecialchars($usernameToUpdate) . '" updated successfully!', 'type' => 'success'];
             } else {
                 // updateUser returns false if user not found or save fails
                 $_SESSION['admin_status'] = ['message' => 'Error: Could not update user or user not found. Check logs.', 'type' => 'error'];
             }
        // } else {
        //      $_SESSION['admin_status'] = ['message' => 'Error: You do not have permission to update this user.', 'type' => 'error'];
        // }
    }
    // Redirect back to the user manager view (or edit view on error)
    header('Location: dashboard.php?view=manage_users');
    exit;
}
*/


// If no valid action was provided, redirect to the user manager view
if (!in_array($action, ['create_user', 'delete_user'])) { // Add 'update_user' here when implemented
     $_SESSION['admin_status'] = ['message' => 'Invalid user action.', 'type' => 'warning'];
}

// Always redirect back to the user manager view if not already exited by a specific action handler
header('Location: dashboard.php?view=manage_users');
exit;

?>