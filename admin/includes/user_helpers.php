<?php

// admin/includes/user_helpers.php

/**
 * Loads user data from the JSON file.
 * Initializes the file with a default admin if it doesn't exist.
 *
 * @param string $filename The path to the user data file.
 * @return array An array of user objects.
 */
function loadUsers($filename) {
    if (!file_exists($filename)) {
        // If user file doesn't exist, create it with a placeholder admin
        $defaultUsers = [
            [
                "username" => "admin", // Default username
                // IMPORTANT: Replace 'password123' with a secure default password AND generate a bcrypt hash for it!
                // You MUST change this default hash before deploying!
                "password_hash" => password_hash('password123', PASSWORD_BCRYPT), // Generate hash for a default password
                "display_name" => "Administrator"
            ]
        ];
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) { // Create data directory if it doesn't exist
                 error_log("Admin Error: Could not create data directory: " . $dir);
                 return []; // Return empty array if directory creation fails
            }
        }
        $jsonData = json_encode($defaultUsers, JSON_PRETTY_PRINT);
        if ($jsonData === false) {
             error_log("Admin Error: Could not encode initial user data to JSON: " . json_last_error_msg());
             return [];
        }
        if (file_put_contents($filename, $jsonData, LOCK_EX) === false) {
            error_log("Admin Error: Could not write initial user data to file: " . $filename);
            return []; // Return empty array if initial write fails
        }
        error_log("Admin Info: Created initial user.json with default admin.");
         return $defaultUsers; // Return the created default users
    }

    $jsonData = file_get_contents($filename);
    if ($jsonData === false) {
        error_log("Admin Error: Could not read user data file: " . $filename);
        return []; // Return empty array on read error
    }

    $users = json_decode($jsonData, true);
    if ($users === null) { // Handle malformed JSON
        error_log("Admin Error: Error decoding user.json: " . json_last_error_msg());
        return []; // Return empty array on decode error
    }

    if (!is_array($users)) {
         error_log("Admin Error: User data is not an array.");
         return []; // Return empty array if data is not an array
    }

    return $users;
}

/**
 * Saves user data to the JSON file.
 *
 * @param array $users The array of user objects to save.
 * @param string $filename The path to the user data file.
 * @return bool True on success, false on failure.
 */
function saveUsers($users, $filename) {
    $jsonData = json_encode($users, JSON_PRETTY_PRINT);
    if ($jsonData === false) {
         error_log("Admin Error: Could not encode user data to JSON: " . json_last_error_msg());
         return false;
    }
    // Use LOCK_EX to prevent concurrent writes from corrupting the file
    if (file_put_contents($filename, $jsonData, LOCK_EX) === false) {
        error_log("Admin Error: Could not write user data to file: " . $filename);
        return false;
    }
    return true;
}

?>
