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
        if (isset($message['timestamp']) && $message['timestamp'] > $lastKnownTimestamp) {
            $newMessagesFlat[] = $message;
        }
    }
}

if (!empty($newMessagesFlat)) {
    // Group new messages by email for easier UI updates
    $newMessagesGrouped = group_feedback_by_email($newMessagesFlat);
    $latestOverallTimestamp = 0;
    if (!empty($newMessagesFlat)) {
         // Get the max timestamp from the new flat messages
        $timestamps = array_column($newMessagesFlat, 'timestamp');
        $latestOverallTimestamp = max($timestamps);
    }

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
