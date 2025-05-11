<?php

// admin/includes/feedback_helpers.php

/**
 * Loads feedback messages from the JSON file.
 *
 * @param string $filename The path to the feedback data file.
 * @return array An array of feedback message objects.
 */
function loadFeedbackMessages($filename) {
    // Cast to string to prevent "Passing null" deprecation warning if $filename is null
    $filename = (string) $filename;

    if (!file_exists($filename)) {
        // If file doesn't exist or path is invalid, return empty array (no messages yet)
        return [];
    }

    $jsonData = file_get_contents($filename);
    if ($jsonData === false) {
        error_log("Admin Error: Could not read feedback data file: " . $filename);
        return []; // Return empty array on read error
    }

    $messages = json_decode($jsonData, true);
    if ($messages === null) { // Handle malformed JSON
        error_log("Admin Error: Error decoding feedback.json: " . json_last_error_msg());
        return []; // Return empty array on decode error
    }

    if (!is_array($messages)) {
         error_log("Admin Error: Feedback data is not an array.");
         return []; // Return empty array if data is not an array
    }

    return $messages;
}

/**
 * Saves feedback messages to the JSON file.
 *
 * @param array $messages The array of feedback message objects to save.
 * @param string $filename The path to the feedback data file.
 * @return bool True on success, false on failure.
 */
function saveFeedbackMessages($messages, $filename) {
     // Cast to string to prevent potential issues if $filename is not a string
    $filename = (string) $filename;

    $jsonData = json_encode($messages, JSON_PRETTY_PRINT);
    if ($jsonData === false) {
         error_log("Admin Error: Could not encode feedback data to JSON: " . json_last_error_msg());
         return false;
    }
    // Use LOCK_EX to prevent concurrent writes from corrupting the file
    if (file_put_contents($filename, $jsonData, LOCK_EX) === false) {
        error_log("Admin Error: Could not write feedback data to file: " . $filename);
        return false;
    }
    return true;
}

?>
