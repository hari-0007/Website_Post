<?php

// admin/message_actions.php - Handles Feedback Message Actions

session_start(); // Start the session

// Explicitly define feedback filename here immediately after session_start()
// This ensures it's available before any includes or function calls that might need it.
$feedbackFilename = __DIR__ . '/../data/feedback.json';


// Include configuration and helper functions
require_once __DIR__ . '/includes/config.php'; // This also defines $feedbackFilename, but the explicit definition above takes precedence if needed.
require_once __DIR__ . '/includes/feedback_helpers.php'; // Include the new helper


// Check if the user is logged in. If not, return a JSON error for AJAX calls.
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

// Initialize status messages (will be stored in session and displayed on dashboard.php for non-AJAX actions)
$_SESSION['admin_status'] = ['message' => '', 'type' => ''];

// Determine the action from the POST request (AJAX or form submission)
$action = $_POST['action'] ?? null;
$selectedMessageIds = $_POST['message_ids'] ?? []; // Get array of selected message IDs for bulk actions
$messageId = $_POST['message_id'] ?? null; // Get single message ID for toggle action

// Load existing feedback messages
// $feedbackFilename is now guaranteed to be defined here
$feedbackMessages = loadFeedbackMessages($feedbackFilename);

// --- Handle Actions ---

// Handle Mark Selected as Read Action (Bulk Action)
if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($selectedMessageIds)) {
    $messagesUpdated = 0;
    foreach ($feedbackMessages as &$message) { // Pass by reference to modify the original array
        if (in_array(($message['id'] ?? null), $selectedMessageIds) && !($message['read'] ?? false)) {
            $message['read'] = true;
            $messagesUpdated++;
        }
    }
    unset($message); // Unset the reference

    if ($messagesUpdated > 0) {
        // $feedbackFilename is guaranteed here
        if (saveFeedbackMessages($feedbackMessages, $feedbackFilename)) {
            $_SESSION['admin_status'] = ['message' => "Success: Marked {$messagesUpdated} message(s) as read.", 'type' => 'success'];
        } else {
            $_SESSION['admin_status'] = ['message' => 'Error: Could not save updated feedback data.', 'type' => 'error'];
            error_log("Admin Message Action Error: Could not write to feedback.json after marking read: " . $feedbackFilename);
        }
    } else {
        $_SESSION['admin_status'] = ['message' => 'No unread messages selected to mark as read.', 'type' => 'info'];
    }

    // Redirect back to messages view after bulk action
    header('Location: dashboard.php?view=messages');
    exit;
}

// Handle Delete Selected Action (Bulk Action)
if ($action === 'delete_messages' && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($selectedMessageIds)) {
    $initialCount = count($feedbackMessages);
    // Filter out messages with IDs in the selectedMessageIds array
    $updatedMessages = array_filter($feedbackMessages, function($message) use ($selectedMessageIds) {
        return !in_array(($message['id'] ?? null), $selectedMessageIds);
    });

    if (count($updatedMessages) < $initialCount) {
         // Re-index the array after filtering
         $updatedMessages = array_values($updatedMessages);
         $deletedCount = $initialCount - count($updatedMessages);

        // $feedbackFilename is guaranteed here
        if (saveFeedbackMessages($updatedMessages, $feedbackFilename)) {
            $_SESSION['admin_status'] = ['message' => "Success: Deleted {$deletedCount} message(s).", 'type' => 'success'];
        } else {
            $_SESSION['admin_status'] = ['message' => 'Error: Could not save updated feedback data after deletion.', 'type' => 'error'];
            error_log("Admin Message Action Error: Could not write to feedback.json after deletion: " . $feedbackFilename);
        }
    } else {
         $_SESSION['admin_status'] = ['message' => 'No messages selected for deletion or selected IDs not found.', 'type' => 'info'];
    }

    // Redirect back to messages view after bulk action
    header('Location: dashboard.php?view=messages');
    exit;
}

// Handle Toggle Read Status Action (Single Message - AJAX)
if ($action === 'toggle_read_status' && $_SERVER['REQUEST_METHOD'] === 'POST' && $messageId) {
    header('Content-Type: application/json'); // Indicate JSON response

    $messageFoundAndUpdated = false;
    $newStatusText = '';

    foreach ($feedbackMessages as &$message) { // Pass by reference
        if (($message['id'] ?? null) === $messageId) {
            // Toggle the read status
            $message['read'] = !($message['read'] ?? false);
            $newStatusText = ($message['read'] ?? false) ? 'Read' : 'Unread';
            $messageFoundAndUpdated = true;
            break;
        }
    }
    unset($message); // Unset the reference

    if ($messageFoundAndUpdated) {
        if (saveFeedbackMessages($feedbackMessages, $feedbackFilename)) {
            echo json_encode(['success' => true, 'message' => 'Status updated.', 'new_status' => $newStatusText]);
        } else {
            error_log("Admin Message Action Error: Could not write to feedback.json after toggling status: " . $feedbackFilename);
            echo json_encode(['success' => false, 'message' => 'Error saving updated feedback data.']);
        }
    } else {
         error_log("Admin Message Action Error: Message ID not found for status toggle: " . $messageId);
         echo json_encode(['success' => false, 'message' => 'Error: Message with specified ID not found.']);
    }
    exit; // Stop execution after JSON response
}


// If no valid action was provided or no messages selected for actions (and not an AJAX toggle request)
// This handles cases where message_actions.php is accessed directly without a valid action
if (!($action === 'toggle_read_status' && $_SERVER['REQUEST_METHOD'] === 'POST')) {
     $_SESSION['admin_status'] = ['message' => 'Invalid message action.', 'type' => 'warning'];
     header('Location: dashboard.php?view=messages');
     exit;
}


?>
