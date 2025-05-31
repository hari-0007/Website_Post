<?php
// filepath: c:\Users\Public\Job_Post\admin\message_actions.php

// admin/message_actions.php - Handles Feedback Message Actions

session_start(); // Start the session

// Explicitly define feedback filename here immediately after session_start()
// This ensures it's available before any includes or function calls that might need it.
// Use __DIR__ for a reliable path relative to the script's location.
$feedbackFilename = __DIR__ . '/../data/feedback.json';


// Include configuration and helper functions
// config.php defines file paths ($feedbackFilename will be overridden if defined there)
// feedback_helpers.php contains load/saveFeedbackMessages functions
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/feedback_helpers.php'; // Assuming this contains toggle_message_read_status


// CORS (only for development - restrict origin in production!)
// header('Access-Control-Allow-Origin: *');
// header('Content-Type: application/json'); // Default content type for AJAX responses

// Check if the user is logged in. If not, return a JSON error for AJAX calls.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // For AJAX requests, return a JSON response instead of redirecting
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        // Send a 401 Unauthorized status code along with JSON
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.', 'redirect' => 'dashboard.php']);
        exit;
    } else {
        // For non-AJAX requests, redirect to login page
        header('Location: dashboard.php'); // Redirect to the main page which handles login
        exit;
    }
}

// Initialize status messages (will be stored in session and displayed on dashboard.php if redirecting)
// This is mainly for non-AJAX actions, but initialized globally.
$_SESSION['admin_status'] = ['message' => '', 'type' => ''];

// Get the action and potentially message IDs from the request
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$messageIds = $_POST['message_ids'] ?? null; // Array of IDs for bulk actions
$messageId = $_POST['message_id'] ?? $_GET['id'] ?? null; // Single ID for detail or toggle

// Load existing feedback messages (needed for most actions)
$feedbackMessages = loadFeedbackMessages($feedbackFilename);

// Ensure $feedbackMessages is an array before proceeding with actions that need it
if (!is_array($feedbackMessages)) {
    error_log("Admin Message Action Error: loadFeedbackMessages did not return an array from " . $feedbackFilename);
     // For AJAX requests expecting JSON, return error here
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
         header('Content-Type: application/json');
         // Return a 500 Internal Server Error status if data loading fails critically
         header('HTTP/1.1 500 Internal Server Error');
         echo json_encode(['success' => false, 'message' => 'Error: Could not load messages data. Check server logs.']);
         exit;
    } else {
         // For non-AJAX, set status and redirect
         $_SESSION['admin_status'] = ['message' => 'Error loading messages data.', 'type' => 'error'];
         header('Location: dashboard.php?view=messages');
         exit;
    }
}


// --- Handle Actions ---
switch ($action) {
    case 'toggle_flag':
        // This action is triggered by AJAX when the flag button is clicked
        // It expects to return JSON response
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Direct access denied.']);
            exit;
        }
        header('Content-Type: application/json');

        if (!$messageId) {
            echo json_encode(['success' => false, 'message' => 'No message ID provided for flag toggle.']);
            exit;
        }

        $messageFoundAndUpdated = false;
        $isFlagged = false;

        foreach ($feedbackMessages as &$msg) { // Iterate through the flat array
            if (isset($msg['id']) && $msg['id'] === $messageId) {
                $msg['flagged'] = !($msg['flagged'] ?? false); // Toggle
                $isFlagged = $msg['flagged'];
                $messageFoundAndUpdated = true;
                break;
            }
        }
        unset($msg);

        if ($messageFoundAndUpdated) {
            if (saveFeedbackMessages($feedbackMessages, $feedbackFilename)) {
                echo json_encode(['success' => true, 'message' => 'Flag status updated.', 'flagged' => $isFlagged]);
            } else {
                error_log("Admin Message Action Error: Could not write to feedback.json after toggling flag: " . $feedbackFilename);
                echo json_encode(['success' => false, 'message' => 'Error saving updated feedback data.']);
            }
        } else {
             // Enhanced logging for when a message ID is not found during AJAX flag toggle
            $sampleIds = [];
            if (is_array($feedbackMessages) && !empty($feedbackMessages)) {
                $sampleIds = array_slice(array_map(fn($m) => $m['id'] ?? 'NO_ID', array_slice($feedbackMessages, 0, 10)), 0, 5);
            }
            error_log("[MESSAGE_ACTION_ERROR] AJAX {$action}: Message ID '{$messageId}' not found. Searched in " . count($feedbackMessages) . " messages. Sample loaded IDs: [" . implode(", ", $sampleIds) . "]");
            echo json_encode(['success' => false, 'message' => 'Error: Message with specified ID not found.']);
        }
        exit;

    case 'add_command':
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Direct access denied.']);
            exit;
        }
        header('Content-Type: application/json');

        $commandText = trim($_POST['command_text'] ?? '');

        if (!$messageId || empty($commandText)) {
            echo json_encode(['success' => false, 'message' => 'Message ID or command text missing.']);
            exit;
        }

        $messageFound = false;
        $updatedCommands = [];

        foreach ($feedbackMessages as &$msg) {
            if (isset($msg['id']) && $msg['id'] === $messageId) {
                if (!isset($msg['commands']) || !is_array($msg['commands'])) {
                    $msg['commands'] = []; // Initialize if not present or not an array
                }
                $msg['commands'][] = $commandText; // Add the new command
                $updatedCommands = $msg['commands'];
                $messageFound = true;
                break;
            }
        }
        unset($msg);

        if ($messageFound) {
            if (saveFeedbackMessages($feedbackMessages, $feedbackFilename)) {
                echo json_encode(['success' => true, 'message' => 'Command added successfully.', 'commands' => $updatedCommands]);
            } else {
                error_log("Admin Message Action Error: Could not write to feedback.json after adding command: " . $feedbackFilename);
                echo json_encode(['success' => false, 'message' => 'Error saving updated feedback data.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Message not found.']);
        }
        exit;

    case 'mark_read':
    case 'mark_unread':
        // This can be called via AJAX (from modal open) or form submission (bulk)
        $isAjaxRequest = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
        $targetStatus = ($action === 'mark_read'); // true for read, false for unread
        $updatedCount = 0;

        if ($isAjaxRequest && $messageId) { // Single message update via AJAX
            header('Content-Type: application/json');
            $messageFound = false;
            foreach ($feedbackMessages as &$message) {
                if (isset($message['id']) && $message['id'] === $messageId) {
                    if (($message['read'] ?? false) !== $targetStatus) {
                        $message['read'] = $targetStatus;
                        $updatedCount++;
                    }
                    $messageFound = true;
                    break;
                }
            }
            unset($message);
            if ($messageFound && $updatedCount > 0) {
                if (saveFeedbackMessages($feedbackMessages, $feedbackFilename)) {
                    echo json_encode(['success' => true, 'message' => 'Message status updated.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error saving message status.']);
                }
            } elseif ($messageFound) { // Found but no change needed
                echo json_encode(['success' => true, 'message' => 'Message status already set.']);
            } else { // Message not found
                // Enhanced logging for when a message ID is not found during AJAX mark_read/unread
                $sampleIds = [];
                if (is_array($feedbackMessages) && !empty($feedbackMessages)) {
                    $sampleIds = array_slice(array_map(fn($m) => $m['id'] ?? 'NO_ID', array_slice($feedbackMessages, 0, 10)), 0, 5);
                }
                error_log("[MESSAGE_ACTION_ERROR] AJAX {$action}: Message ID '{$messageId}' not found. Searched in " . count($feedbackMessages) . " messages. Sample loaded IDs: [" . implode(", ", $sampleIds) . "]");
                echo json_encode(['success' => false, 'message' => 'Message not found.']);
            }
            exit;
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && is_array($messageIds) && !empty($messageIds)) { // Bulk update via form
            foreach ($feedbackMessages as &$message) {
                if (isset($message['id']) && in_array($message['id'], $messageIds)) {
                    if (($message['read'] ?? false) !== $targetStatus) {
                         $message['read'] = $targetStatus;
                         $updatedCount++;
                    }
                }
            }
            unset($message);

            if ($updatedCount > 0) {
                if (saveFeedbackMessages($feedbackMessages, $feedbackFilename)) {
                    $_SESSION['admin_status'] = [
                        'message' => 'Successfully marked ' . $updatedCount . ' message(s) as ' . ($targetStatus ? 'read' : 'unread') . '.',
                        'type' => 'success'
                    ];
                } else {
                    $_SESSION['admin_status'] = ['message' => 'Error saving updated feedback data.', 'type' => 'error'];
                }
            } else {
                $_SESSION['admin_status'] = ['message' => 'No messages needed status update.', 'type' => 'info'];
            }
        } else {
            $_SESSION['admin_status'] = ['message' => 'Invalid request for message status update.', 'type' => 'warning'];
        }

        if (!$isAjaxRequest) {
            header('Location: dashboard.php?view=messages');
            exit;
        }
        break; 

    case 'delete_messages':
        // This action is typically triggered by a form submission with multiple IDs
        // It expects to redirect back to the messages view

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_array($messageIds) && !empty($messageIds)) {
            $initialCount = count($feedbackMessages);
            $messagesDeleted = 0;

            // Filter out messages whose IDs are in the $messageIds array
            $newFeedbackMessages = array_filter($feedbackMessages, function($message) use ($messageIds) {
                // Keep the message if its ID is NOT in the list to delete
                return !isset($message['id']) || !in_array($message['id'], $messageIds);
            });

            // Re-index the array after filtering
            $newFeedbackMessages = array_values($newFeedbackMessages);

            $messagesDeleted = $initialCount - count($newFeedbackMessages);

            if ($messagesDeleted > 0) {
                 if (saveFeedbackMessages($newFeedbackMessages, $feedbackFilename)) {
                     $_SESSION['admin_status'] = ['message' => 'Successfully deleted ' . $messagesDeleted . ' message(s).', 'type' => 'success'];
                 } else {
                     error_log("Admin Message Action Error: Could not write to feedback.json after deleting messages: " . $feedbackFilename);
                     $_SESSION['admin_status'] = ['message' => 'Error saving updated feedback data after deletion.', 'type' => 'error'];
                 }
            } else {
                 $_SESSION['admin_status'] = ['message' => 'No messages were deleted (perhaps they were already removed or IDs were invalid).', 'type' => 'info'];
            }

        } else {
            // Invalid request for delete action
            $_SESSION['admin_status'] = ['message' => 'Invalid request for message deletion. No messages selected or invalid method.', 'type' => 'warning'];
        }

        // Redirect back to the messages view
        header('Location: dashboard.php?view=messages');
        exit;
        break;

    // The 'get_message_detail' and 'toggle_read_status' cases were from an older structure
    // and are likely redundant if the current 'mark_read'/'mark_unread' handles AJAX for single messages
    // and the modal in messages_view.php populates details from the client-side `feedbackMessagesData`.
    // If they are still needed for a specific reason, they would need to be adapted to the flat $feedbackMessages structure.

    default:
        // If no valid action was provided or no messages selected for actions (and not an AJAX toggle request)
        if (!empty($action)) { 
             $_SESSION['admin_status'] = ['message' => 'Invalid or unhandled message action: ' . htmlspecialchars($action), 'type' => 'warning'];
             error_log("Admin Message Action Warning: Invalid or unhandled action received: " . $action);
        } else if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
             $_SESSION['admin_status'] = ['message' => 'No action specified or no messages selected.', 'type' => 'warning'];
             error_log("Admin Message Action Warning: POST request received with no action.");
        } else { 
             $_SESSION['admin_status'] = ['message' => 'Invalid access to message actions.', 'type' => 'warning'];
             error_log("Admin Message Action Warning: Direct GET access without action.");
        }

        // Redirect back to the messages view for non-AJAX requests
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
             header('Location: dashboard.php?view=messages');
        } else {
             // For AJAX, return a JSON error if no specific AJAX action was matched
             header('Content-Type: application/json');
             echo json_encode(['success' => false, 'message' => 'Invalid AJAX message action.']);
        }
        exit;
        break;
}

// Fallback redirect if somehow execution reaches here without exiting
// This should ideally not happen with the exit calls in each case.
header('Location: dashboard.php?view=messages');
exit;

?>
