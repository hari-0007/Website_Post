<?php
// admin/views/messages.php

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: dashboard.php?view=login&error=session_expired');
    exit;
}

// The get_grouped_and_sorted_feedback function is in feedback_helpers.php,
// which should already be included by dashboard.php.

$grouped_feedback = get_grouped_and_sorted_feedback();

// Handle marking messages as read/unread
if (isset($_GET['action'], $_GET['message_id'])) {
    $messageIdToToggle = $_GET['message_id'];
    $action = $_GET['action'];

    if ($action === 'mark_read' || $action === 'mark_unread') {
        $markAsRead = ($action === 'mark_read');
        if (toggle_message_read_status($messageIdToToggle, $markAsRead)) {
            // Refresh the data and redirect to clear GET params
            $_SESSION['admin_status'] = ['message' => 'Message status updated.', 'type' => 'success'];
        } else {
            $_SESSION['admin_status'] = ['message' => 'Failed to update message status.', 'type' => 'error'];
        }
        header('Location: dashboard.php?view=messages');
        exit;
    }
}

// Flatten the grouped feedback into a single array of messages for table display
// This also ensures messages are sorted by timestamp (newest first) across all groups
$allMessagesFlat = [];
foreach ($grouped_feedback as $email => $groupData) {
    // Add user_id to each message for potential future use if needed
    // foreach ($groupData['messages'] as &$message) {
    //     // Note: user_id is not directly available in the $groupData['messages'] array
    //     // as it was flattened from the original structure. If needed, you'd have to
    //     // add it during the flattening process in get_grouped_and_sorted_feedback
    //     // or iterate the original $allSiteFeedbackData structure here.
    // }
    $allMessagesFlat = array_merge($allMessagesFlat, $groupData['messages']);
}

// Sort the flattened list by timestamp (newest first)
usort($allMessagesFlat, function ($a, $b) {
    $timestampA = (isset($a['timestamp']) && is_numeric($a['timestamp'])) ? (int)$a['timestamp'] : 0;
    $timestampB = (isset($b['timestamp']) && is_numeric($b['timestamp'])) ? (int)$b['timestamp'] : 0;
    return $timestampB <=> $timestampA; // Newest first
});

// The $allMessagesFlat variable is kept as it's used by the JavaScript modal
// to look up message details by ID.
?>

<div class="view-container">
    <h3>Feedback Conversations</h3>

    <?php if (empty($grouped_feedback)): ?>
        <p>No feedback messages found.</p>
    <?php else: ?>
        <?php foreach ($grouped_feedback as $email => $groupData): ?>
            <div class="email-group card">
                <div class="card-header">
                    Conversation with: <?php echo htmlspecialchars($email); ?>
                    <?php if (!empty($groupData['names'])): ?>
                        <br><small class="text-muted">(Associated Names: <?php echo htmlspecialchars(implode(', ', $groupData['names'])); ?>)</small>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <p class="card-subtitle mb-2 text-muted">
                        Total Messages: <?php echo count($groupData['messages']); ?> | 
                        Last Message: <?php echo date('Y-m-d H:i:s', $groupData['latest_timestamp']); ?>
                    </p>
                    
                    <?php foreach ($groupData['messages'] as $message): ?>
                        <div class="feedback-message-item <?php echo (isset($message['read']) && $message['read']) ? 'message-read' : 'message-unread'; ?>" onclick="openMessageModal('<?php echo htmlspecialchars($message['id'] ?? ''); ?>')" style="cursor:pointer;">
                            <p>
                                <strong>From:</strong> <?php echo htmlspecialchars($message['name'] ?? 'N/A'); ?><br>
                                <strong>Date:</strong> <?php echo isset($message['timestamp']) ? date('Y-m-d H:i:s', $message['timestamp']) : 'N/A'; ?><br>
                                <strong>Status:</strong> 
                                <span class="status-badge <?php echo (isset($message['read']) && $message['read']) ? 'status-read' : 'status-unread'; ?>">
                                    <?php echo (isset($message['read']) && $message['read']) ? 'Read' : 'Unread'; ?>
                                </span>
                                <a href="dashboard.php?view=messages&action=<?php echo (isset($message['read']) && $message['read']) ? 'mark_unread' : 'mark_read'; ?>&message_id=<?php echo htmlspecialchars($message['id']); ?>" class="btn btn-sm <?php echo (isset($message['read']) && $message['read']) ? 'btn-secondary' : 'btn-primary'; ?> action-link" onclick="event.stopPropagation();">
                                    Mark as <?php echo (isset($message['read']) && $message['read']) ? 'Unread' : 'Read'; ?>
                                </a>
                            </p>
                            <div class="message-content">
                                <?php echo nl2br(htmlspecialchars(substr($message['message'] ?? '', 0, 150))) . (strlen($message['message'] ?? '') > 150 ? '...' : ''); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Message Detail Modal HTML -->
        <div id="messageDetailModal" class="modal" style="display: none;">
            <div class="modal-content">
                <h3>Message Details</h3>
                <div id="modalStatusArea"></div>
                <div class="message-details-content">
                    <p><strong>From:</strong> <span id="detailName"></span> &lt;<span id="detailEmail"></span>&gt;</p>
                    <p><strong>Received On:</strong> <span id="detailTimestamp"></span></p>
                    <div class="message-body-container">
                        <h4>Message:</h4>
                        <p id="detailMessage" class="message-body"></p>
                    </div>
                </div>
                <div class="modal-actions">
                    <a id="replyLink" href="#" class="button" style="background-color: #007bff;">Reply via Email</a>
                    <button class="button" style="background-color: #6c757d;" onclick="closeMessageModal()">Close</button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    /* Styles for Conversation View */
    .email-group.card { margin-bottom: 20px; border: 1px solid #ddd; border-radius: 5px; }
    .email-group .card-header { background-color: #f7f7f7; padding: 10px 15px; border-bottom: 1px solid #ddd; font-weight: bold; }
    .email-group .card-body { padding: 15px; }
    .email-group .card-subtitle { font-size: 0.9em; color: #666; margin-bottom: 15px; }

    .feedback-message-item { 
        border: 1px solid #e0e0e0; 
        background-color: #fff; 
        padding: 10px; 
        margin-top: 10px; 
        border-radius: 4px; 
        transition: background-color 0.2s ease;
    }
    .feedback-message-item:hover {
        background-color: #f9f9f9;
    }
    .feedback-message-item.message-unread { border-left: 4px solid #007bff; } /* Blue for unread */
    .feedback-message-item.message-read { border-left: 4px solid #6c757d; } /* Grey for read */
    .message-content { 
        background-color: #f8f9fa; 
        padding: 8px; 
        border-radius: 3px; 
        white-space: pre-wrap; 
        word-wrap: break-word; 
        font-size: 0.95em; 
        margin-top: 5px; 
        max-height: 100px; /* Limit initial display height */
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .status-badge { 
        padding: .25em .4em; 
        font-size: 0.8em; /* Slightly smaller */
        font-weight: 700; 
        line-height: 1; 
        text-align: center; 
        white-space: nowrap; 
        vertical-align: baseline; 
        border-radius: .25rem; 
    }
    .status-unread { color: #fff; background-color: #007bff; }
    /* Styles from messages_view.php */
    /* Table Styles */
    /* ... (Keep all table styles from messages_view.php here) ... */
    /* Button Styles */
    /* ... (Keep all button styles from messages_view.php here) ... */
    /* Modal Styles */
    /* ... (Keep all modal styles from messages_view.php here) ... */
    /* Pagination Styles */
    /* ... (Keep all pagination styles from messages_view.php here) ... */
    /* Responsive Design */
    /* ... (Keep all responsive styles from messages_view.php here) ... */
    .status-read { color: #fff; background-color: #6c757d; }
    .action-link { margin-left: 10px; font-size: 0.85em; }

    /* Table Styles (Kept for potential future use or if other views use .message-table) */
    /* .message-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        font-size: 0.9rem;
        color: #333;
    }

    .message-table thead {
        background-color: #f8f9fa;
        text-align: left;
    }

    .message-table thead th {
        padding: 12px 15px;
        border-bottom: 2px solid #dee2e6;
        font-weight: bold;
    }

    .message-table tbody tr {
        border-bottom: 1px solid #dee2e6;
    }

    .message-table tbody tr:nth-child(even) {
        background-color: #f9f9f9; /* Alternating row colors */
    }

    .message-table tbody tr:hover {
        background-color: #f1f1f1; /* Highlight row on hover */
    }

    .message-table tbody td {
        padding: 10px 15px;
    }

    /* Checkbox Column */
    .message-table td:nth-child(1) {
        text-align: center;
        width: 40px;
    }

    /* Unread Row Highlight */
    .message-table tr.unread {
        background-color: #fff; /* Default white */
        font-weight: bold; /* Highlight unread */
    }

    .message-table tr.read {
        background-color: #f9f9f9; /* Slightly grey for read */
        font-weight: normal;
        color: #555; /* Dimmer text for read */
    } */

    /* Button Styles (for Mark Selected/Delete Selected) */
    .button {
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.85rem;
        transition: background-color 0.3s ease;
    }

    .button:hover {
        opacity: 0.9;
    }

    .button.delete {
        background-color: #dc3545;
        color: white;
    }

    .button.delete:hover {
        background-color: #c82333;
    }

    /* Modal Styles */
    .modal {
        position: fixed;
        z-index: 1050; /* Ensure modal is on top */
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background-color: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-content h3 {
        margin-top: 0;
        color: #0056b3;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }

    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }

    .modal-actions .button {
        padding: 8px 15px;
        font-size: 0.85rem;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .modal-content {
            width: 95%;
        }

        .modal-actions {
            flex-direction: column;
            gap: 10px;
        }

        .modal-actions .button {
            width: 100%;
        }
    }
    /* Pagination Styles (Kept for potential future use or if other views use .pagination) */
    .pagination {
        margin-top: 20px;
        text-align: center;
    }

    .pagination a {
        display: inline-block;
        margin: 0 5px;
        padding: 8px 12px;
        text-decoration: none;
        color: #007bff;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .pagination a.active {
        background-color: #007bff;
        color: #fff;
        border-color: #007bff;
    }

    .pagination a:hover {
        background-color: #0056b3;
        color: #fff;
    }
    /* Additional styles for modal status area if needed */
    .modal-status-message {
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 4px;
        font-size: 0.9em;
        text-align: center;
    }
</style>

<script>
    // Embed the flattened feedback messages data as a JavaScript variable
    // This is used by the modal to find message details by ID
    var feedbackMessagesData = <?= json_encode($allMessagesFlat ?? []); ?>;
    // JavaScript from messages_view.php

    // Get the modal status area element reference
    const modalStatusArea = document.getElementById('modalStatusArea');

    // Function to display status messages inside the modal
    function displayModalStatus(message, type) {
        if (modalStatusArea) {
            modalStatusArea.innerHTML = '';

            const statusMessageElement = document.createElement('p');
            statusMessageElement.classList.add('modal-status-message');
            statusMessageElement.classList.add(type);
            statusMessageElement.innerText = message;

            modalStatusArea.appendChild(statusMessageElement);
        } else {
            console.error('Could not find modal status area element.');
            if (type === 'error') {
                console.error('Modal Status:', message);
            } else {
                console.log('Modal Status:', message);
            }
        }
    }

    function openMessageModal(messageId) {
        // Find the message in the embedded flattened data
        const message = feedbackMessagesData.find(msg => msg.id === messageId);

        // Get modal elements inside the function
        const modal = document.getElementById('messageDetailModal');
        const detailName = document.getElementById('detailName');
        const detailEmail = document.getElementById('detailEmail');
        const detailTimestamp = document.getElementById('detailTimestamp');
        const detailMessage = document.getElementById('detailMessage');
        const replyLink = document.getElementById('replyLink');

        // Clear previous status messages when opening a new message
        const modalStatusArea = document.getElementById('modalStatusArea');
        if (modalStatusArea) modalStatusArea.innerHTML = '';

        if (message) {
            // Populate modal with message details
            if (detailName) detailName.innerText = message.name || 'N/A';
            if (detailEmail) detailEmail.innerText = message.email || 'N/A';
            if (detailTimestamp) detailTimestamp.innerText = message.timestamp
                ? new Date(message.timestamp * 1000).toLocaleString()
                : 'N/A';
            if (detailMessage) detailMessage.innerText = message.message || 'No message content.';

            // Set the mailto link for reply
            if (replyLink) {
                if (message.email) {
                    replyLink.href = `mailto:${encodeURIComponent(message.email)}?subject=${encodeURIComponent('Reply to your feedback')}&body=${encodeURIComponent('\n\n---\nOriginal Message:\n' + (message.message || ''))}`;
                    replyLink.style.display = 'inline-block';
                } else {
                    replyLink.style.display = 'none';
                }
            }

            // Display the modal
            if (modal) modal.style.display = 'flex';

            // Mark the message as read via AJAX
            // Note: This AJAX call goes to message_actions.php, which you might need to implement
            // or adapt from the logic previously in messages_view.php's POST block.
            // The form also submits to message_actions.php for bulk actions.
            fetch('message_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest' // Indicate it's an AJAX request
                },
                body: `action=mark_read&message_id=${encodeURIComponent(message.id)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Message marked as read:', data.message);

                    // Update the row in the table visually
                    const row = document.querySelector(`.message-table tbody tr[data-message-id="${message.id}"]`);
                    if (row) {
                        row.classList.remove('unread');
                        row.classList.add('read');
                    }

                    // Update the embedded data (optional, but keeps JS state consistent)
                    const messageToUpdate = feedbackMessagesData.find(msg => msg.id === message.id);
                    if (messageToUpdate) {
                        messageToUpdate.read = true;
                    }

                } else {
                    console.error('Failed to mark message as read:', data.message);
                    displayModalStatus('Failed to mark as read: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error marking message as read:', error);
                 displayModalStatus('An error occurred while marking as read.', 'error');
            });
        } else {
            console.error('Message with ID ' + messageId + ' not found in embedded data.');
            if (modalStatusArea) {
                modalStatusArea.innerHTML = '<p class="modal-status-message error">Error: Message data not found.</p>';
            }
            if (modal) modal.style.display = 'flex'; // Still show modal with error
        }
    }

    function closeMessageModal() {
        const modal = document.getElementById('messageDetailModal');
        if (modal) {
            modal.style.display = 'none';
            // Clear status area when closing
            const modalStatusArea = document.getElementById('modalStatusArea');
            if (modalStatusArea) modalStatusArea.innerHTML = '';
        }
    }

    // Event listener for table rows to open modal
    const messagesTableBody = document.querySelector('.message-table tbody');
    if (messagesTableBody) {
        messagesTableBody.addEventListener('click', function(event) {
            const row = event.target.closest('tr');
            if (row && row.dataset.messageId) {
                // This event listener was for table rows.
                // For the new conversation view, clicks are handled by inline onclick on .feedback-message-item
                // const clickedElement = event.target;
                // const isCheckbox = clickedElement.tagName === 'INPUT' && clickedElement.type === 'checkbox';
                // const isLink = clickedElement.tagName === 'A'; 
                // if (!isCheckbox && !isLink) {
                //     openMessageModal(row.dataset.messageId);
                // }
            }
        });
    }

    // Event listener for "Select All" checkbox
    const selectAllCheckbox = document.getElementById('selectAllMessages');
    if (selectAllCheckbox) {
        // This was for the table view. Not applicable to conversation view directly.
        // selectAllCheckbox.addEventListener('click', function() {
        //     const checkboxes = document.querySelectorAll('#messagesForm input[type="checkbox"][name="message_ids[]"]');
        //     for (let checkbox of checkboxes) {
        //         checkbox.checked = this.checked;
        //     }
        // });
    } // This element might not exist anymore if the table is removed.

    // Function to set form action and submit for bulk actions
    function setFormAction(action) {
        const form = document.getElementById('messagesForm');
        if (form) {
            const selectedCheckboxes = form.querySelectorAll('input[type="checkbox"][name="message_ids[]"]:checked');
            if ((action === 'mark_read' || action === 'delete_messages') && selectedCheckboxes.length === 0) {
                alert('Please select at least one message.');
                return;
            }
            form.action.value = action;
            // The form action was set to message_actions.php in the HTML
            form.submit();
        } else {
            console.error('Messages form not found.');
        }
    } // This function was for the table form, may not be used in conversation view.


    // ToggleReadStatusBtn logic (from messages_view.php, if needed, though individual mark read is via modal)
    // This button might not exist in the new consolidated view unless you add it to the modal.
    // The current `messages.php` handles individual mark read/unread via GET parameters on page reload
    // and the modal AJAX handles it on modal open.
    // If you have a specific "Toggle Read Status" button in the modal, its ID would be 'toggleReadStatusBtn'.
    // For now, this part of the JS from messages_view.php might be redundant if that button isn't present.
    /*
    const toggleReadStatusBtn = document.getElementById('toggleReadStatusBtn');
    if (toggleReadStatusBtn) {
        toggleReadStatusBtn.addEventListener('click', function() {
            const messageId = this.dataset.messageId; // Assuming this button has data-message-id
            if (messageId) {
                // ... AJAX call to message_actions.php with action=toggle_read_status ...
                // ... then update UI and feedbackMessagesData ...
            }
        });
    }
    */

    // Close modal when clicking outside of it
    const messageDetailModal = document.getElementById('messageDetailModal');
    if (messageDetailModal) {
        messageDetailModal.addEventListener('click', function(event) {
            if (event.target === messageDetailModal) {
                closeMessageModal();
            }
        });
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && messageDetailModal && messageDetailModal.style.display === 'flex') {
            closeMessageModal();
        }
    });

    // Helper function to escape HTML for display (used in displayModalStatus)
    function escapeHTML(str) {
        if (typeof str !== 'string') return str;
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

</script>
