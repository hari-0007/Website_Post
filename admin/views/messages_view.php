<?php

// admin/views/messages_view.php - Displays Feedback Messages and Message Detail Modal

// This file is included by dashboard.php when $requestedView is 'messages'.
// It assumes $feedbackMessages is available.

?>
<?php
$messagesPerPage = 20; // Number of messages per page
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1; // Get current page from URL, default to 1

$totalMessages = count($feedbackMessages); // Total number of messages
$totalPages = ceil($totalMessages / $messagesPerPage); // Calculate total pages

// Slice the messages array to get only the messages for the current page
$startIndex = ($currentPage - 1) * $messagesPerPage;
$pagedMessages = array_slice($feedbackMessages, $startIndex, $messagesPerPage);
?>
<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <h3 style="margin: 0;">Feedback Messages</h3>
    <div style="display: flex; gap: 10px;">
        <button type="button" class="button" onclick="setFormAction('mark_read')">Mark Selected as Read</button>
        <button type="button" class="button delete" onclick="if(confirm('Are you sure you want to delete selected messages?')) setFormAction('delete_messages');">Delete Selected</button>
    </div>
</div>

<?php if (empty($feedbackMessages)): ?>
    <p class="no-messages">No feedback messages found.</p>
<?php else: ?>
    <form method="POST" action="message_actions.php" id="messagesForm">
        <input type="hidden" name="action" value="">

        <table class="message-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAllMessages"></th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Message</th>
                    <th>Received On</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pagedMessages)): ?>
                    <?php foreach ($pagedMessages as $message): ?>
                        <?php
                        $messageId = htmlspecialchars($message['id'] ?? '');
                        $rowClass = ($message['read'] ?? false) ? 'read' : 'unread';
                        ?>
                        <tr class="<?= $rowClass ?>" data-message-id="<?= $messageId ?>" style="cursor: pointer;">
                            <td><input type="checkbox" name="message_ids[]" value="<?= $messageId ?>"></td>
                            <td><?= htmlspecialchars($message['name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($message['email'] ?? 'N/A') ?></td>
                            <td><?= nl2br(htmlspecialchars(substr($message['message'] ?? '', 0, 100))) . (strlen($message['message'] ?? '') > 100 ? '...' : '') ?></td>
                            <td><?= isset($message['timestamp']) ? htmlspecialchars(date('Y-m-d H:i', $message['timestamp'])) : 'N/A' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">No messages found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </form>

    <div class="pagination">
        <?php if ($currentPage > 1): ?>
            <a href="?view=messages&page=<?= $currentPage - 1 ?>">&laquo; Previous</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?view=messages&page=<?= $i ?>" class="<?= $i === $currentPage ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($currentPage < $totalPages): ?>
            <a href="?view=messages&page=<?= $currentPage + 1 ?>">Next &raquo;</a>
        <?php endif; ?>
    </div>

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

    <style>
        /* Table Styles */
        .message-table {
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

        /* Date Column */
        .message-table td:nth-child(6) {
            /* Delete or comment out these styles */
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
        }

        /* Remove styles for the Status column */
        .message-table td:nth-child(2) {
            /* Delete or comment out these styles */
        }

        /* Button Styles */
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

        .button.view {
            background-color: #007bff;
            color: white;
        }

        .button.view:hover {
            background-color: #0056b3;
        }

        /* Modal Styles */
        .modal {
            position: fixed;
            z-index: 1;
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

        /* Pagination Styles */
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
    </style>

    <script>
        // Embed feedback messages data as a JavaScript variable using VAR
        var feedbackMessagesData = <?= json_encode($feedbackMessages ?? []); ?>;

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
                if (detailName) detailName.innerText = message.name || 'N/A'; // Ensure the name is not overwritten
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
                fetch('message_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `action=mark_read&message_id=${encodeURIComponent(message.id)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Message marked as read:', data.message);

                        // Update the row in the table
                        const row = document.querySelector(`.message-table tbody tr[data-message-id="${message.id}"]`);
                        if (row) {
                            row.classList.remove('unread');
                            row.classList.add('read');
                        }

                        // Update the embedded data
                        message.read = true;
                    } else {
                        console.error('Failed to mark message as read:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error marking message as read:', error);
                });
            } else {
                console.error('Message with ID ' + messageId + ' not found.');
                if (modalStatusArea) {
                    modalStatusArea.innerHTML = '<p class="modal-status-message error">Error: Message not found.</p>';
                }
                if (modal) modal.style.display = 'flex';
            }
        }

        function closeMessageModal() {
            const modal = document.getElementById('messageDetailModal');
            if (modal) {
                modal.style.display = 'none';
                const modalStatusArea = document.getElementById('modalStatusArea');
                if (modalStatusArea) modalStatusArea.innerHTML = '';
            }
        }

        const messagesTableBody = document.querySelector('.message-table tbody');
        if (messagesTableBody) {
            messagesTableBody.addEventListener('click', function(event) {
                const row = event.target.closest('tr');
                if (row && row.dataset.messageId) {
                    const clickedElement = event.target;
                    const isCheckbox = clickedElement.tagName === 'INPUT' && clickedElement.type === 'checkbox';
                    const isLink = clickedElement.tagName === 'A';

                    if (!isCheckbox && !isLink) {
                        openMessageModal(row.dataset.messageId);
                    }
                }
            });
        }

        const toggleReadStatusBtn = document.getElementById('toggleReadStatusBtn');
        if (toggleReadStatusBtn) {
            toggleReadStatusBtn.addEventListener('click', function() {
                const messageId = this.dataset.messageId;
                if (messageId) {
                    fetch('message_actions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: 'action=toggle_read_status&message_id=' + encodeURIComponent(messageId)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('Status toggled:', data.message);
                            const modalDetailStatus = document.getElementById('detailStatus');
                            if (modalDetailStatus) modalDetailStatus.innerText = data.new_status;

                            const row = document.querySelector(`.message-table tbody tr[data-message-id="${messageId}"]`);
                            if (row) {
                                row.classList.remove('read', 'unread');
                                row.classList.add(data.new_status === 'Read' ? 'read' : 'unread');
                                const statusCell = row.querySelector('td:nth-child(2)');
                                if (statusCell) statusCell.innerText = data.new_status;
                            }

                            if (typeof feedbackMessagesData !== 'undefined' && Array.isArray(feedbackMessagesData)) {
                                const messageToUpdate = feedbackMessagesData.find(msg => msg.id === messageId);
                                if (messageToUpdate) {
                                    messageToUpdate.read = (data.new_status === 'Read');
                                    console.log(`Embedded data for message ID ${messageId} updated.`);
                                }
                            }

                            displayModalStatus(data.message || 'Status updated successfully!', 'success');
                        } else {
                            console.error('Failed to toggle status:', data.message);
                            displayModalStatus('Error: ' + (data.message || 'Unknown error'), 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error during status toggle AJAX:', error);
                        if (error !== 'Unauthorized') {
                            let errorMessage = 'An error occurred while toggling status.';
                            if (error.message && error.message.startsWith('HTTP error')) {
                                errorMessage += ` (${escapeHTML(error.message)})`;
                            }
                            displayModalStatus(errorMessage, 'error');
                        }
                    });
                }
            });
        }

        const selectAllCheckbox = document.getElementById('selectAllMessages');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('click', function() {
                const checkboxes = document.querySelectorAll('#messagesForm input[type="checkbox"][name="message_ids[]"]');
                for (let checkbox of checkboxes) {
                    checkbox.checked = this.checked;
                }
            });
        }

        function setFormAction(action) {
            const form = document.getElementById('messagesForm');
            if (form) {
                if ((action === 'mark_read' || action === 'delete_messages') && form.querySelectorAll('input[type="checkbox"][name="message_ids[]"]:checked').length === 0) {
                    alert('Please select at least one message.');
                    return;
                }
                form.action.value = action;
                form.submit();
            } else {
                console.error('Messages form not found.');
            }
        }

        function escapeHTML(str) {
            if (typeof str !== 'string') return str;
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    </script>

<?php endif; ?>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $messageId = $_POST['message_id'] ?? null;

    if ($action === 'mark_read' && $messageId) {
        // Find the message by ID and mark it as read
        foreach ($feedbackMessages as &$message) {
            if ($message['id'] === $messageId) {
                $message['read'] = true;

                // Save the updated messages array back to the data source (e.g., a file or database)
                saveMessages($feedbackMessages);

                // Return a success response
                echo json_encode(['success' => true, 'message' => 'Message marked as read.']);
                exit;
            }
        }

        // If the message was not found
        echo json_encode(['success' => false, 'message' => 'Message not found.']);
        exit;
    }
}
?>