<?php

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
require_once __DIR__ . '/includes/feedback_helpers.php';


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
$action = $_POST['action'] ?? $_GET['action'] ?? null; // Use $_REQUEST or check both POST and GET
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
    case 'mark_read':
    case 'mark_unread':
        // These actions are typically triggered by a form submission with multiple IDs
        // They expect to redirect back to the messages view

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_array($messageIds) && !empty($messageIds)) {
            $updatedCount = 0;
            $targetStatus = ($action === 'mark_read'); // true for read, false for unread

            foreach ($feedbackMessages as &$message) { // Use reference to modify original array
                if (isset($message['id']) && in_array($message['id'], $messageIds)) {
                    // Only update if the status is changing
                    if (($message['read'] ?? false) !== $targetStatus) {
                         $message['read'] = $targetStatus;
                         $updatedCount++;
                    }
                }
            }
            unset($message); // Break the reference

            if ($updatedCount > 0) {
                if (saveFeedbackMessages($feedbackMessages, $feedbackFilename)) {
                    $_SESSION['admin_status'] = [
                        'message' => 'Successfully marked ' . $updatedCount . ' message(s) as ' . ($targetStatus ? 'read' : 'unread') . '.',
                        'type' => 'success'
                    ];
                } else {
                    error_log("Admin Message Action Error: Could not write to feedback.json after marking status: " . $feedbackFilename);
                    $_SESSION['admin_status'] = ['message' => 'Error saving updated feedback data.', 'type' => 'error'];
                }
            } else {
                $_SESSION['admin_status'] = ['message' => 'No messages needed status update.', 'type' => 'info'];
            }

        } else {
             // Invalid request for mark_read/unread action
            $_SESSION['admin_status'] = ['message' => 'Invalid request for message status update.', 'type' => 'warning'];
        }

        // Redirect back to the messages view
        header('Location: dashboard.php?view=messages');
        exit;
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
                 $_SESSION['admin_status'] = ['message' => 'No messages were deleted.', 'type' => 'info'];
            }

        } else {
            // Invalid request for delete action
            $_SESSION['admin_status'] = ['message' => 'Invalid request for message deletion.', 'type' => 'warning'];
        }

        // Redirect back to the messages view
        header('Location: dashboard.php?view=messages');
        exit;
        break;

    case 'get_message_detail':
        // This action is triggered by AJAX from footer.php
        // It expects to return JSON response

        // Ensure it's an AJAX request
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
            header('HTTP/1.1 403 Forbidden'); // Send a 403 status
            header('Content-Type: application/json'); // Still return JSON format
            echo json_encode(['success' => false, 'message' => 'Direct access denied.']);
            exit;
        }

        // Ensure Content-Type is application/json for the response
        header('Content-Type: application/json');

        error_log("Admin Message Action Debug: Received get_message_detail request for ID: " . ($messageId ?? 'null')); // Log the requested ID

        if (!$messageId) {
            error_log("Admin Message Action Error: No message ID provided for get_message_detail.");
            echo json_encode(['success' => false, 'message' => 'No message ID provided.']);
            exit;
        }

        // Re-load messages here just in case the array reference from the start of the script is stale
        // Although $feedbackMessages is loaded above, re-loading ensures we have the latest data,
        // especially if a previous action modified the file in the same script execution cycle
        // (unlikely in a single AJAX call, but safer if architecture changes).
        // More importantly, the initial load check ensures it's an array.
        // $feedbackMessages = loadFeedbackMessages($feedbackFilename); // Keep the initial load check above as the primary safeguard

        $foundMessage = null;
        foreach ($feedbackMessages as $message) {
            if (isset($message['id']) && $message['id'] === $messageId) {
                $foundMessage = $message;
                break;
            }
        }

        if ($foundMessage) {
            // Optionally mark the message as read when viewing details via this AJAX call
            // Only mark as read if it's currently unread
            if (!($foundMessage['read'] ?? false)) {
                 // Find the message in the original array by reference to update it
                 $messageUpdatedInArray = false; // Flag to check if we found and updated it
                 foreach ($feedbackMessages as &$message) {
                     if (isset($message['id']) && $message['id'] === $messageId) {
                         $message['read'] = true;
                         $messageUpdatedInArray = true;
                         break; // Stop once updated
                     }
                 }
                 unset($message); // Break the reference

                 // Save the updated messages array if a message was found and updated
                 if ($messageUpdatedInArray) {
                     if (!saveFeedbackMessages($feedbackMessages, $feedbackFilename)) {
                          error_log("Admin Message Action Error: Could not auto-mark message as read (ID: " . $messageId . ") after viewing.");
                          // Continue execution even if save failed, user can still see details
                     } else {
                         // Log auto-read success
                         error_log("Admin Message Action Debug: Message ID " . $messageId . " auto-marked as read after viewing details.");
                         // Update the $foundMessage array to reflect the read status immediately for the JSON response
                         $foundMessage['read'] = true;
                     }
                 }
            }


            // Format the timestamp for display
            $foundMessage['received_on'] = date('Y-m-d H:i', $foundMessage['timestamp'] ?? 0);
            // Determine status text
            $foundMessage['status'] = ($foundMessage['read'] ?? false) ? 'Read' : 'Unread';
            // Include message text separately for clarity
            $foundMessage['message_text'] = $foundMessage['message'] ?? '';

            // Remove raw timestamp and original message content if you only want formatted data
            unset($foundMessage['timestamp']);
            unset($foundMessage['message']); // Remove original message key


            echo json_encode(['success' => true, 'message' => 'Message details fetched.', 'message' => $foundMessage]); // Return the message data
        } else {
            error_log("Admin Message Action Error: Message with ID " . ($messageId ?? 'null') . " not found for get_message_detail.");
            echo json_encode(['success' => false, 'message' => 'Message not found.']);
        }
        exit; // Stop execution after JSON response
        break;

    case 'toggle_read_status':
        // This action is triggered by AJAX from footer.php when the toggle button is clicked
        // It expects to return JSON response

         // Ensure it's an AJAX request
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
            header('HTTP/1.1 403 Forbidden'); // Send a 403 status
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Direct access denied.']);
            exit;
        }

        // Ensure Content-Type is application/json for the response
        header('Content-Type: application/json');

         error_log("Admin Message Action Debug: Received toggle_read_status request for ID: " . ($messageId ?? 'null')); // Log the requested ID


        if (!$messageId) {
            error_log("Admin Message Action Error: No message ID provided for status toggle.");
            echo json_encode(['success' => false, 'message' => 'No message ID provided for status toggle.']);
            exit;
        }

        $messageFoundAndUpdated = false;
        $newStatusText = 'Unknown'; // Default status text

        // Find the message in the array by reference and toggle its status
        foreach ($feedbackMessages as &$message) {
            if (isset($message['id']) && $message['id'] === $messageId) {
                $message['read'] = !($message['read'] ?? false); // Toggle the boolean status
                $newStatusText = ($message['read'] ?? false) ? 'Read' : 'Unread'; // Determine new text
                $messageFoundAndUpdated = true;
                break; // Stop once updated
            }
        }
        unset($message); // Break the reference

        if ($messageFoundAndUpdated) {
            if (saveFeedbackMessages($feedbackMessages, $feedbackFilename)) {
                echo json_encode(['success' => true, 'message' => 'Status updated.', 'new_status' => $newStatusText]);
            } else {
                error_log("Admin Message Action Error: Could not write to feedback.json after toggling status: " . $feedbackFilename);
                echo json_encode(['success' => false, 'message' => 'Error saving updated feedback data.']);
            }
        } else {
             error_log("Admin Message Action Error: Message with ID " . ($messageId ?? 'null') . " not found for status toggle.");
             echo json_encode(['success' => false, 'message' => 'Error: Message with specified ID not found.']);
        }
        exit; // Stop execution after JSON response
        break;

    default:
        // If no valid action was provided or no messages selected for actions (and not an AJAX toggle request)
        // This handles cases where message_actions.php is accessed directly without a valid action,
        // or a form was submitted with no checkboxes selected for mark/delete.
        if (!empty($action)) { // If an action was provided but not handled above
             $_SESSION['admin_status'] = ['message' => 'Invalid or unhandled message action: ' . htmlspecialchars($action), 'type' => 'warning'];
             error_log("Admin Message Action Warning: Invalid or unhandled action received: " . $action);
        } else if ($_SERVER['REQUEST_METHOD'] === 'POST') { // If it was a POST but no action or IDs
             $_SESSION['admin_status'] = ['message' => 'No action specified or no messages selected.', 'type' => 'warning'];
             error_log("Admin Message Action Warning: POST request received with no action.");
        } else { // Generic GET access without action
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