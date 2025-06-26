<?php
// admin/get_new_feedback.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/includes/config.php'; // For $feedbackFilename
require_once __DIR__ . '/includes/feedback_helpers.php'; // For loadFeedbackMessages, group_feedback_by_email

$lastKnownTimestamp = isset($_GET['last_timestamp']) ? (int)$_GET['last_timestamp'] : 0;

$allMessages = loadFeedbackMessages($feedbackFilename);
$newMessagesFlat = [];

if (is_array($allMessages)) {
    foreach ($allMessages as $message) {
        if (isset($message['timestamp'])) {
            $ts = $message['timestamp'];
            // Handle both Unix timestamps (numeric) and date strings
            $unix_ts = is_numeric($ts) ? (int)$ts : strtotime($ts);
            if ($unix_ts > $lastKnownTimestamp) {
                $newMessagesFlat[] = $message;
            }
        }
    }
}

if (!empty($newMessagesFlat)) {
    // Group new messages by email for easier UI updates
    $newMessagesGrouped = group_feedback_by_email($newMessagesFlat);
    // To get the latest timestamp, we need to handle both string and numeric formats
    $timestamps = array_map(function($msg) {
        $ts = $msg['timestamp'] ?? 0;
        return is_numeric($ts) ? (int)$ts : strtotime($ts);
    }, $newMessagesFlat);

    $latestOverallTimestamp = !empty($timestamps) ? max($timestamps) : $lastKnownTimestamp;

    echo json_encode([
        'success' => true,
        'new_messages_grouped' => $newMessagesGrouped,
        'latest_overall_timestamp' => $latestOverallTimestamp
    ]);
} else {
    echo json_encode(['success' => true, 'new_messages_grouped' => [], 'latest_overall_timestamp' => $lastKnownTimestamp]);
}
exit;
?>
