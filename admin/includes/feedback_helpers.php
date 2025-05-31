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

/**
 * Groups feedback messages from a flat array by email and sorts them.
 *
 * @param array $flatMessages A flat array of feedback message objects.
 * @return array An associative array where keys are email addresses.
 *               Each email address key points to an array containing:
 *               - 'messages': An array of feedback message objects from that email, sorted by timestamp (newest first).
 *               - 'latest_timestamp': The timestamp of the most recent message from this email.
 *               - 'names': An array of unique names associated with this email from the feedback.
 *               The top-level array (email groups) is also sorted by 'latest_timestamp' (newest groups first).
 */
function group_feedback_by_email(array $flatMessages) {
    $groupedByEmail = [];

    foreach ($flatMessages as $submission) {
        if (!is_array($submission) || !isset($submission['email'])) {
            error_log("feedback_helpers.php (group_feedback_by_email): Malformed submission or missing email: " . print_r($submission, true));
            continue;
        }

        $emailKey = strtolower(trim($submission['email']));
        if (empty($emailKey)) {
            $emailKey = 'unknown_email@system.local'; // Group messages with no email
        }

        if (!isset($groupedByEmail[$emailKey])) {
            $groupedByEmail[$emailKey] = ['messages' => [], 'latest_timestamp' => 0, 'names' => []];
        }

        $groupedByEmail[$emailKey]['messages'][] = $submission;

        $currentName = isset($submission['name']) ? trim($submission['name']) : 'Anonymous';
        if (!empty($currentName) && !in_array($currentName, $groupedByEmail[$emailKey]['names'])) {
            $groupedByEmail[$emailKey]['names'][] = $currentName;
        }

        $currentSubmissionTimestamp = (isset($submission['timestamp']) && is_numeric($submission['timestamp'])) ? (int)$submission['timestamp'] : 0;

        if ($currentSubmissionTimestamp > $groupedByEmail[$emailKey]['latest_timestamp']) {
            $groupedByEmail[$emailKey]['latest_timestamp'] = $currentSubmissionTimestamp;
        }
    }

    // Sort messages within each group by timestamp (newest first)
    foreach ($groupedByEmail as &$groupData) {
        usort($groupData['messages'], fn($a, $b) => ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0));
    }
    unset($groupData);

    // Sort email groups by the latest message timestamp (newest group first)
    uasort($groupedByEmail, fn($a, $b) => ($b['latest_timestamp'] ?? 0) <=> ($a['latest_timestamp'] ?? 0));

    return $groupedByEmail;
}
?>
