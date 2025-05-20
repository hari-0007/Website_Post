<?php

// admin/includes/user_manager_helpers.php - Helper functions for User Management

require_once __DIR__ . '/config.php'; // Include config for $usersFilename
require_once __DIR__ . '/user_helpers.php'; // Include general user helpers (load/save)

/**
 * Creates a new user.
 *
 * @param string $username
 * @param string $password
 * @param string $displayName
 * @param string $role
 * @param string $usersFilename
 * @return array|false The new user array if successful, false otherwise (e.g., email exists, save fails).
 */
function createUser($username, $password, $displayName, $role, $usersFilename) {
    $users = loadUsers($usersFilename);

    // Check if username already exists (case-insensitive for email)
    foreach ($users as $user) {
        if (isset($user['username']) && strtolower($user['username']) === strtolower($username)) {
            return false; // Username already exists
        }
    }

    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    if ($hashedPassword === false) {
        error_log("Admin User Manager Error: password_hash failed during user creation.");
        return false; // Hashing failed
    }

    // Create new user object
    $newUser = [
        "username" => $username, // Store as provided (original case)
        "password_hash" => $hashedPassword,
        "display_name" => $displayName,
        "role" => $role, // Assign the specified role
        "status" => "pending_approval" // New users default to pending
    ];

    // Add the new user
    $users[] = $newUser;

    // Save the updated users array
    if (saveUsers($users, $usersFilename)) {
        return $newUser; // Return the newly created user array
    } else {
        error_log("Admin User Manager Error: Could not save user data after creating user.");
        return false; // Save failed
    }
}

/**
 * Updates an existing user's details (display name, password, role, status).
 *
 * @param string $usernameToUpdate The username of the user to update.
 * @param array $updateData An associative array of fields to update (e.g., ['display_name' => 'New Name', 'password' => 'newpass', 'role' => 'admin', 'status' => 'active']).
 * @param string $usersFilename
 * @return array|false The updated user array on success, false on failure (e.g., user not found, save failed).
 */
function updateUser($usernameToUpdate, $updateData, $usersFilename) {
    $users = loadUsers($usersFilename);
    $userFoundAndUpdated = false;
    $updatedUser = null;

    foreach ($users as &$user) { // Pass by reference to modify the original array
        // Case-insensitive comparison for username
        if (isset($user['username']) && strtolower($user['username']) === strtolower($usernameToUpdate)) {
            // Update display name if provided
            if (isset($updateData['display_name'])) {
                $user['display_name'] = $updateData['display_name'];
            }
            // Update password if provided and not empty
            if (isset($updateData['password']) && !empty($updateData['password'])) {
                 $hashedPassword = password_hash($updateData['password'], PASSWORD_BCRYPT);
                 if ($hashedPassword === false) {
                     error_log("Admin User Manager Error: password_hash failed during user update for user: " . $usernameToUpdate);
                     return false; // Hashing failed
                 }
                 $user['password_hash'] = $hashedPassword;
            }
            // Update role if provided
            if (isset($updateData['role'])) {
                $user['role'] = $updateData['role'];
            }
            // Update status if provided
            if (isset($updateData['status'])) {
                $user['status'] = $updateData['status'];
            }

            $updatedUser = $user; // Capture the updated user data
            $userFoundAndUpdated = true;
            break; // User found and updated, exit loop
        }
    }
    unset($user); // Unset the reference

    if ($userFoundAndUpdated) {
        if (saveUsers($users, $usersFilename)) {
            return $updatedUser; // Return the updated user array
        } else {
            error_log("Admin User Manager Error: Could not save user data after updating user: " . $usernameToUpdate);
            return false; // Save failed
        }
    } else {
        error_log("Admin User Manager Error: User not found for update: " . $usernameToUpdate);
        return false; // User not found
    }
}

/**
 * Deletes a user by username.
 *
 * @param string $usernameToDelete The username of the user to delete.
 * @param string $usersFilename
 * @return bool True on success, false on failure (e.g., user not found, save failed).
 */
function deleteUser($usernameToDelete, $usersFilename) {
    $users = loadUsers($usersFilename);
    $initialCount = count($users);

    // Filter out the user with the matching username (case-insensitive)
    $updatedUsers = array_filter($users, function($user) use ($usernameToDelete) {
        return !isset($user['username']) || strtolower($user['username']) !== strtolower($usernameToDelete);
    });

    if (count($updatedUsers) < $initialCount) {
         // Re-index the array after filtering
         $updatedUsers = array_values($updatedUsers);

        // Save the updated users array
        if (saveUsers($updatedUsers, $usersFilename)) {
            return true; // Deletion successful
        } else {
            error_log("Admin User Manager Error: Could not save user data after deleting user: " . $usernameToDelete);
            return false; // Save failed
        }
    } else {
        error_log("Admin User Manager Error: User not found for deletion: " . $usernameToDelete);
        return false; // User not found
    }
}

/**
 * Finds a user by their username from the users data file.
 *
 * @param string $username The username to search for.
 * @param string $usersFilename The path to the users JSON file.
 * @return array|null The user array if found, null otherwise.
 */
function findUserByUsername($username, $usersFilename) {
    $users = loadUsers($usersFilename); 

    if (is_array($users)) {
        foreach ($users as $user) {
            // Case-insensitive comparison for username
            if (isset($user['username']) && strtolower($user['username']) === strtolower($username)) {
                return $user; // User found
            }
        }
    }
    return null; // User not found or error loading users
}


?>
