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
    $emailAsUsername = trim($_POST['username'] ?? ''); // This is now an email
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $displayName = trim($_POST['display_name'] ?? '');
    $role = $_POST['role'] ?? 'user'; // Default new user role to 'user'

    // Basic validation
    if (empty($emailAsUsername) || empty($password) || empty($confirmPassword) || empty($displayName) || empty($role)) {
        $_SESSION['admin_status'] = ['message' => 'Error: Please fill in all fields.', 'type' => 'error'];
    } elseif (!filter_var($emailAsUsername, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['admin_status'] = ['message' => 'Error: Invalid email format for username.', 'type' => 'error'];
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
            // createUser in user_manager_helpers.php already sets status to 'pending_approval'
            $newUser = createUser($emailAsUsername, $password, $displayName, $role, $usersFilename);

            if ($newUser) {
                // The message here is for the admin performing the action
                $_SESSION['admin_status'] = ['message' => 'Success: User "' . htmlspecialchars($emailAsUsername) . '" created. They are pending approval.', 'type' => 'success'];
            } elseif ($newUser === false && findUserByUsername($emailAsUsername, $usersFilename)) {
                 $_SESSION['admin_status'] = ['message' => 'Error: A user with email "' . htmlspecialchars($emailAsUsername) . '" already exists.', 'type' => 'error'];
            } else {
                 $_SESSION['admin_status'] = ['message' => 'Error: Could not create user. Check logs.', 'type' => 'error'];
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
        $userToDeleteData = findUserByUsername($usernameToDelete, $usersFilename); // Use helper

        if ($userToDeleteData) {
            $roleToDelete = $userToDeleteData['role'] ?? 'user';

            if ($loggedInUserRole === 'super_admin') {
                // Super Admin can delete any role except themselves (already checked)
                $canDelete = true;
            } elseif ($loggedInUserRole === 'admin') {
                // Admin can delete 'user_group_manager' or 'user'
                // Admins cannot delete other admins or super_admins
                if (in_array($roleToDelete, ['user_group_manager', 'user'])) {
                    $canDelete = true; 
                }
            }
            // User Group Managers and Users cannot delete anyone (implicitly handled as canDelete remains false)

            if (!$canDelete) {
                $_SESSION['admin_status'] = ['message' => 'Error: You do not have permission to delete this user.', 'type' => 'error'];
                 error_log("Admin User Action Error: User '" . $loggedInUsername . "' (role: ".$loggedInUserRole.") attempted to delete user '" . $usernameToDelete . "' (role: " . $roleToDelete . ") - DENIED.");
            } else {
                // The deleteUser helper itself doesn't need role checks if we do them here.
                // The last two params to deleteUser in context were $loggedInUserRole, $loggedInUsername, but the helper only takes 2.
                if (deleteUser($usernameToDelete, $usersFilename)) { 
                    $_SESSION['admin_status'] = ['message' => 'Success: User "' . htmlspecialchars($usernameToDelete) . '" deleted successfully!', 'type' => 'success'];
                } else {
                    $_SESSION['admin_status'] = ['message' => 'Error: Could not delete user. Check logs.', 'type' => 'error'];
                }
            }
        } else {
             $_SESSION['admin_status'] = ['message' => 'Error: User with specified username not found for deletion.', 'type' => 'error'];
             error_log("Admin User Action Error: User not found for deletion: " . $usernameToDelete);
        }
    }

    // Redirect back to the user manager view
    header('Location: dashboard.php?view=manage_users');
    exit;
}


// --- Handle Update User Action ---
if ($action === 'update_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameToUpdate = $_POST['username_to_update'] ?? null;
    $newDisplayName = trim($_POST['display_name'] ?? '');
    $newPassword = $_POST['password'] ?? ''; // Optional
    $confirmPassword = $_POST['confirm_password'] ?? ''; // Optional
    $newRole = $_POST['role'] ?? null; // Optional, might not be submitted if not changeable

    if (empty($usernameToUpdate)) {
        $_SESSION['admin_status'] = ['message' => 'Error: Username to update is missing.', 'type' => 'error'];
    } elseif (empty($newDisplayName)) {
        $_SESSION['admin_status'] = ['message' => 'Error: Display name cannot be empty.', 'type' => 'error'];
    } elseif (!empty($newPassword) && $newPassword !== $confirmPassword) {
        $_SESSION['admin_status'] = ['message' => 'Error: New passwords do not match.', 'type' => 'error'];
    } else {
        $userToUpdateData = findUserByUsername($usernameToUpdate, $usersFilename);
        if (!$userToUpdateData) {
            $_SESSION['admin_status'] = ['message' => 'Error: User to update not found.', 'type' => 'error'];
        } else {
            $targetUserCurrentRole = $userToUpdateData['role'] ?? 'user';
            $updatePayload = ['display_name' => $newDisplayName]; // Changed variable name for clarity

            if (!empty($newPassword)) {
                $updatePayload['password'] = $newPassword;
            }

            // Authorization for role change
            $canChangeRole = false;
            if ($newRole !== null && $newRole !== $targetUserCurrentRole) {
                if ($usernameToUpdate === $loggedInUsername) {
                    $_SESSION['admin_status'] = ['message' => 'Error: You cannot change your own role.', 'type' => 'error'];
                    header('Location: dashboard.php?view=manage_users'); // Or profile view
                    exit;
                }

                if ($loggedInUserRole === 'super_admin') {
                    $canChangeRole = true;
                } elseif ($loggedInUserRole === 'admin') {
                    if ($targetUserCurrentRole !== 'super_admin' && $newRole !== 'super_admin') {
                        if (in_array($newRole, ['admin', 'user_group_manager', 'user'])) {
                            $canChangeRole = true;
                        }
                    }
                } elseif ($loggedInUserRole === 'user_group_manager') {
                    if ($targetUserCurrentRole === 'user' && $newRole === 'user') { // UGM can only manage 'user' roles
                        $canChangeRole = true;
                    }
                }

                if ($canChangeRole) {
                    $updatePayload['role'] = $newRole;
                } elseif ($newRole !== $targetUserCurrentRole) { 
                    $_SESSION['admin_status'] = ['message' => 'Error: You do not have permission to assign the selected role or change this user\'s role.', 'type' => 'error'];
                    error_log("Admin User Action (Update): User '" . $loggedInUsername . "' (role: " . $loggedInUserRole . ") attempted to change role of '" . $usernameToUpdate . "' from '" . $targetUserCurrentRole . "' to '" . $newRole . "' - DENIED.");
                    header('Location: dashboard.php?view=edit_user&username=' . urlencode($usernameToUpdate));
                    exit;
                }
            }
            
            $canEditThisUser = false;
            if ($loggedInUserRole === 'super_admin') $canEditThisUser = true;
            else if ($loggedInUserRole === 'admin' && $targetUserCurrentRole !== 'super_admin') $canEditThisUser = true;
            else if ($loggedInUserRole === 'user_group_manager' && $targetUserCurrentRole === 'user') $canEditThisUser = true;
            else if ($usernameToUpdate === $loggedInUsername) $canEditThisUser = true; 

            if (!$canEditThisUser) {
                 $_SESSION['admin_status'] = ['message' => 'Error: You do not have permission to edit this user.', 'type' => 'error'];
            } else {
                $updatedUser = updateUser($usernameToUpdate, $updatePayload, $usersFilename);
                if ($updatedUser) {
                    $_SESSION['admin_status'] = ['message' => 'Success: User "' . htmlspecialchars($usernameToUpdate) . '" updated successfully!', 'type' => 'success'];
                    if ($usernameToUpdate === $loggedInUsername && isset($updatePayload['display_name'])) {
                        $_SESSION['admin_display_name'] = $updatePayload['display_name'];
                    }
                } else {
                    $_SESSION['admin_status'] = ['message' => 'Error: Could not update user. User might not exist or save failed. Check logs.', 'type' => 'error'];
                }
            }
        }
    }
    header('Location: dashboard.php?view=manage_users');
    exit;
}

// --- Handle Approve User Action ---
if ($action === 'approve_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameToApprove = $_POST['username_to_action'] ?? null;

    if (!in_array($loggedInUserRole, ['admin', 'super_admin'])) {
        $_SESSION['admin_status'] = ['message' => 'Error: You do not have permission to approve users.', 'type' => 'error'];
        error_log("Admin User Action (Approve): User '" . $loggedInUsername . "' (role: " . $loggedInUserRole . ") attempted to approve user - DENIED.");
    } elseif (empty($usernameToApprove)) {
        $_SESSION['admin_status'] = ['message' => 'Error: Username to approve is missing.', 'type' => 'error'];
    } else {
        $userToApproveData = findUserByUsername($usernameToApprove, $usersFilename);
        if ($userToApproveData && ($userToApproveData['status'] ?? '') === 'pending_approval') {
            // The updateUser function is used here, it's defined in user_manager_helpers.php
            if (updateUser($usernameToApprove, ['status' => 'active'], $usersFilename)) {
                $_SESSION['admin_status'] = ['message' => 'Success: User "' . htmlspecialchars($usernameToApprove) . '" has been approved and is now active.', 'type' => 'success'];
            } else {
                $_SESSION['admin_status'] = ['message' => 'Error: Could not approve user. Save error occurred.', 'type' => 'error'];
            }
        } else {
             $_SESSION['admin_status'] = ['message' => 'Error: User cannot be approved. They may not exist or are not pending approval.', 'type' => 'error'];
        }
    }
    header('Location: dashboard.php?view=manage_users');
    exit;
}

// --- Handle Reject User Action ---
if ($action === 'reject_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameToReject = $_POST['username_to_action'] ?? null;
    $userToRejectData = findUserByUsername($usernameToReject, $usersFilename);

    if (!in_array($loggedInUserRole, ['admin', 'super_admin'])) {
        $_SESSION['admin_status'] = ['message' => 'Error: You do not have permission to reject users.', 'type' => 'error'];
        error_log("Admin User Action (Reject): User '" . $loggedInUsername . "' (role: " . $loggedInUserRole . ") attempted to reject user - DENIED.");
    } elseif (empty($usernameToReject)) {
        $_SESSION['admin_status'] = ['message' => 'Error: Username to reject is missing.', 'type' => 'error'];
    } elseif (!$userToRejectData || ($userToRejectData['status'] ?? '') !== 'pending_approval') {
        $_SESSION['admin_status'] = ['message' => 'Error: User cannot be rejected. They may not exist or are not pending approval.', 'type' => 'error'];
    } else {
        // The deleteUser helper function takes 2 arguments as per user_manager_helpers.php
        if (deleteUser($usernameToReject, $usersFilename)) {
            $_SESSION['admin_status'] = ['message' => 'Success: User "' . htmlspecialchars($usernameToReject) . '" has been rejected and their registration data removed.', 'type' => 'success'];
        } else {
            $_SESSION['admin_status'] = ['message' => 'Error: Could not reject user. An error occurred during deletion.', 'type' => 'error'];
        }
    }
    header('Location: dashboard.php?view=manage_users');
    exit;
}

// --- Handle Disable User Action ---
if ($action === 'disable_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameToDisable = $_POST['username_to_action'] ?? null;
    $userToDisableData = findUserByUsername($usernameToDisable, $usersFilename);

    if (!in_array($loggedInUserRole, ['admin', 'super_admin'])) {
        $_SESSION['admin_status'] = ['message' => 'Error: You do not have permission to disable users.', 'type' => 'error'];
    } elseif (empty($usernameToDisable)) {
        $_SESSION['admin_status'] = ['message' => 'Error: Username to disable is missing.', 'type' => 'error'];
    } elseif ($usernameToDisable === $loggedInUsername) {
        $_SESSION['admin_status'] = ['message' => 'Error: You cannot disable your own account.', 'type' => 'error'];
    } elseif ($userToDisableData && $userToDisableData['role'] === 'super_admin' && $loggedInUserRole !== 'super_admin') {
        $_SESSION['admin_status'] = ['message' => 'Error: Admins cannot disable Super Admin accounts.', 'type' => 'error'];
    } elseif ($userToDisableData && ($userToDisableData['status'] ?? '') === 'active') {
        if (updateUser($usernameToDisable, ['status' => 'disabled'], $usersFilename)) {
            $_SESSION['admin_status'] = ['message' => 'Success: User "' . htmlspecialchars($usernameToDisable) . '" has been disabled.', 'type' => 'success'];
        } else {
            $_SESSION['admin_status'] = ['message' => 'Error: Could not disable user. Save error occurred.', 'type' => 'error'];
        }
    } else {
        $_SESSION['admin_status'] = ['message' => 'Error: User is not active or does not exist.', 'type' => 'error'];
    }
    header('Location: dashboard.php?view=manage_users');
    exit;
}

// --- Handle Enable User Action ---
if ($action === 'enable_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameToEnable = $_POST['username_to_action'] ?? null;
    $userToEnableData = findUserByUsername($usernameToEnable, $usersFilename);

    if (!in_array($loggedInUserRole, ['admin', 'super_admin'])) {
        $_SESSION['admin_status'] = ['message' => 'Error: You do not have permission to enable users.', 'type' => 'error'];
    } elseif (empty($usernameToEnable)) {
        $_SESSION['admin_status'] = ['message' => 'Error: Username to enable is missing.', 'type' => 'error'];
    } elseif ($userToEnableData && ($userToEnableData['status'] ?? '') === 'disabled') {
        if (updateUser($usernameToEnable, ['status' => 'active'], $usersFilename)) {
            $_SESSION['admin_status'] = ['message' => 'Success: User "' . htmlspecialchars($usernameToEnable) . '" has been enabled.', 'type' => 'success'];
        } else {
            $_SESSION['admin_status'] = ['message' => 'Error: Could not enable user. Save error occurred.', 'type' => 'error'];
        }
    } else {
        $_SESSION['admin_status'] = ['message' => 'Error: User is not disabled or does not exist.', 'type' => 'error'];
    }
    header('Location: dashboard.php?view=manage_users');
    exit;
}

// --- Handle Activate/Deactivate User from Edit Page ---
if (($action === 'activate_user_from_edit' || $action === 'deactivate_user_from_edit') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameToToggle = $_POST['username_to_toggle'] ?? null;
    $userToToggleData = findUserByUsername($usernameToToggle, $usersFilename);
    $redirectToEdit = isset($_POST['redirect_to_edit']) && $_POST['redirect_to_edit'] === 'true';

    $newStatus = ($action === 'activate_user_from_edit') ? 'active' : 'disabled';
    $currentStatusActionWord = ($action === 'activate_user_from_edit') ? 'disabled' : 'active'; // For error messages

    // Authorization checks
    if (!in_array($loggedInUserRole, ['admin', 'super_admin'])) {
        $_SESSION['admin_status'] = ['message' => 'Error: You do not have permission to change user status.', 'type' => 'error'];
    } elseif (empty($usernameToToggle)) {
        $_SESSION['admin_status'] = ['message' => 'Error: Username to toggle status is missing.', 'type' => 'error'];
    } elseif ($usernameToToggle === $loggedInUsername) {
        $_SESSION['admin_status'] = ['message' => 'Error: You cannot change your own account status.', 'type' => 'error'];
    } elseif (!$userToToggleData) {
        $_SESSION['admin_status'] = ['message' => 'Error: User not found.', 'type' => 'error'];
    } elseif ($userToToggleData['role'] === 'super_admin' && $loggedInUserRole !== 'super_admin') {
        $_SESSION['admin_status'] = ['message' => 'Error: Admins cannot change the status of Super Admin accounts.', 'type' => 'error'];
    } elseif (($userToToggleData['status'] ?? 'unknown') !== $currentStatusActionWord && ($userToToggleData['status'] ?? 'unknown') !== 'inactive' && $action === 'activate_user_from_edit') {
        // Special handling for 'inactive' if it exists, allowing activation
        // If trying to activate, current status must be 'disabled' or 'inactive'
        $_SESSION['admin_status'] = ['message' => 'Error: User is not currently ' . $currentStatusActionWord . ' or inactive.', 'type' => 'error'];
    } elseif (($userToToggleData['status'] ?? 'unknown') !== $currentStatusActionWord && $action === 'deactivate_user_from_edit') {
        // If trying to deactivate, current status must be 'active'
        $_SESSION['admin_status'] = ['message' => 'Error: User is not currently ' . $currentStatusActionWord . '.', 'type' => 'error'];
    }
    else {
        if (updateUser($usernameToToggle, ['status' => $newStatus], $usersFilename)) {
            $_SESSION['admin_status'] = ['message' => 'Success: User "' . htmlspecialchars($usernameToToggle) . '" status changed to ' . $newStatus . '.', 'type' => 'success'];
        } else {
            $_SESSION['admin_status'] = ['message' => 'Error: Could not change user status. Save error occurred.', 'type' => 'error'];
        }
    }

    if ($redirectToEdit && !empty($usernameToToggle)) {
        header('Location: dashboard.php?view=edit_user&username=' . urlencode($usernameToToggle));
    } else {
        header('Location: dashboard.php?view=manage_users');
    }
    exit;
}


// If no valid action was provided, redirect to the user manager view
if (!in_array($action, [
    'create_user', 'delete_user', 'update_user', 
    'approve_user', 'reject_user', 
    'disable_user', 'enable_user', // These are from manage_users_view.php buttons
    'activate_user_from_edit', 'deactivate_user_from_edit' // New actions from edit_user_view.php
    ])) {
     $_SESSION['admin_status'] = ['message' => 'Invalid user action.', 'type' => 'warning'];
}

// Always redirect back to the user manager view if not already exited by a specific action handler
header('Location: dashboard.php?view=manage_users');
exit;

?>
