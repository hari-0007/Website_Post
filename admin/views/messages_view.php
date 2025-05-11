<?php

// admin/views/messages_view.php - Displays Feedback Messages and Message Detail Modal

// This file is included by dashboard.php when $requestedView is 'messages'.
// It assumes $feedbackMessages is available.

?>
<h3>Feedback Messages</h3>

<?php if (empty($feedbackMessages)): ?>
    <p class="no-messages">No feedback messages found.</p>
<?php else: ?>
    <form method="POST" action="message_actions.php" id="messagesForm">
        <input type="hidden" name="action" value=""> <div style="margin-bottom: 15px;">
            <button type="button" class="button" onclick="setFormAction('mark_read')">Mark Selected as Read</button>
            <button type="button" class="button delete" onclick="setFormAction('delete_messages'); return confirm('Are you sure you want to delete selected messages?');">Delete Selected</button>
        </div>

        <table class="message-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAllMessages"></th>
                    <th>Status</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Message</th>
                    <th>Received On</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($feedbackMessages as $message): ?>
                    <?php
                    // Ensure message ID exists and is safe for data attribute
                    $messageId = htmlspecialchars($message['id'] ?? '');
                    // Add data attribute for message ID and make row clickable
                    ?>
                    <tr class="<?= ($message['read'] ?? false) ? 'read' : 'unread' ?>" data-message-id="<?= $messageId ?>" style="cursor: pointer;">
                        <td><input type="checkbox" name="message_ids[]" value="<?= $messageId ?>"></td>
                        <td><?= ($message['read'] ?? false) ? 'Read' : 'Unread' ?></td>
                        <td><?= htmlspecialchars($message['name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($message['email'] ?? 'N/A') ?></td>
                        <td><?= nl2br(htmlspecialchars(substr($message['message'] ?? '', 0, 150))) ?>...</td> <td><?= isset($message['timestamp']) ? date('Y-m-d H:i', $message['timestamp']) : 'N/A' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>

    <div id="messageDetailModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeMessageModal()">&times;</span>
            <h3>Message Details</h3>
            <div class="message-details-content">
                <p><strong>From:</strong> <span id="detailName"></span> &lt;<span id="detailEmail"></span>&gt;</p>
                <p><strong>Received On:</strong> <span id="detailTimestamp"></span></p>
                <p><strong>Status:</strong> <span id="detailStatus"></span></p>
                <div class="message-body-container">
                    <h4>Message:</h4>
                    <p id="detailMessage" class="message-body"></p>
                </div>
            </div>
            <div class="modal-actions">
                <button id="toggleReadStatusBtn" class="button">Toggle Read Status</button>
                <a id="replyLink" href="#" class="button" style="background-color: #007bff;">Reply via Email</a>
                <button class="button" style="background-color: #6c757d;" onclick="closeMessageModal()">Close</button>
            </div>
        </div>
    </div>


    <style>
        .message-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .message-table th, .message-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            vertical-align: top; /* Align content to top */
        }
        .message-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .message-table tr.unread {
            background-color: #fff; /* Default white */
            font-weight: bold; /* Highlight unread */
        }
         .message-table tr.read {
             background-color: #f9f9f9; /* Slightly grey for read */
             font-weight: normal;
             color: #555; /* Dimmer text for read */
         }
        .message-table tr:hover {
            background-color: #e9e9e9;
        }
         .no-messages {
             text-align: center;
             margin-top: 20px;
         }
         .message-table td:nth-child(1) { width: 30px; text-align: center; } /* Checkbox column */
         .message-table td:nth-child(2) { width: 60px; } /* Status column */
         .message-table td:nth-child(6) { width: 120px; } /* Date column */

        /* Modal styles */
        .modal {
            position: fixed; /* Stay in place */
            z-index: 1; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            display: flex; /* Use flexbox for centering */
            align-items: center; /* Center vertically */
            justify-content: center; /* Center horizontally */
            padding-top: 60px; /* Location of the box */
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%; /* Could be responsive */
            max-width: 600px; /* Max width for larger screens */
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative; /* Needed for absolute positioning of close button */
            display: flex; /* Use flexbox for content and actions layout */
            flex-direction: column;
            max-height: 90vh; /* Limit height to viewport height */
        }

        .modal-content h3 {
            margin-top: 0;
            color: #005fa3;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute; /* Position relative to modal-content */
            top: 10px;
            right: 20px;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }

        .message-details-content {
            flex-grow: 1; /* Allow content to take available space */
            overflow-y: auto; /* Add scroll to message details if they overflow */
            margin-bottom: 15px;
        }

        .message-body-container {
            margin-top: 15px;
            padding: 10px;
            border: 1px solid #eee;
            background-color: #f9f9f9;
            border-radius: 4px;
        }

        .message-body {
            white-space: pre-wrap; /* Preserve whitespace and wrap text */
            word-wrap: break-word; /* Break long words */
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .modal-actions .button {
             padding: 8px 15px; /* Adjust padding for modal buttons */
             font-size: 0.9em; /* Adjust font size */
        }

        /* Responsive adjustments for modal */
        @media (max-width: 600px) {
            .modal-content {
                width: 95%; /* Wider on small screens */
                padding: 15px;
            }
            .close {
                top: 5px;
                right: 10px;
                font-size: 24px;
            }
             .modal-actions {
                 flex-direction: column; /* Stack buttons vertically */
                 gap: 8px;
             }
             .modal-actions .button {
                 width: 100%; /* Full width buttons */
                 text-align: center;
             }
        }

    </style>

    <script>
        // Embed feedback messages data as a JavaScript variable
        const feedbackMessagesData = <?= json_encode($feedbackMessages ?? []); ?>;

        // Get the modal and the elements to display message details
        const modal = document.getElementById('messageDetailModal');
        const detailName = document.getElementById('detailName');
        const detailEmail = document.getElementById('detailEmail');
        const detailTimestamp = document.getElementById('detailTimestamp');
        const detailStatus = document.getElementById('detailStatus');
        const detailMessage = document.getElementById('detailMessage');
        const toggleReadStatusBtn = document.getElementById('toggleReadStatusBtn');
        const replyLink = document.getElementById('replyLink');

        // Function to open the modal and populate it
        function openMessageModal(messageId) {
            // Find the message in the embedded data
            const message = feedbackMessagesData.find(msg => msg.id === messageId);

            if (message) {
                // Populate modal with message details
                detailName.innerText = message.name || 'N/A';
                detailEmail.innerText = message.email || 'N/A';
                detailTimestamp.innerText = message.timestamp ? new Date(message.timestamp * 1000).toLocaleString() : 'N/A'; // Convert timestamp to readable date/time
                detailStatus.innerText = (message.read ?? false) ? 'Read' : 'Unread';
                detailMessage.innerText = message.message || 'No message content.'; // Use innerText to handle line breaks from nl2br if needed, or pre-wrap CSS

                // Set the data-message-id on the toggle button
                toggleReadStatusBtn.dataset.messageId = message.id;

                // Set the mailto link for reply
                if (message.email) {
                    replyLink.href = `mailto:${encodeURIComponent(message.email)}?subject=${encodeURIComponent('Reply to your feedback')}&body=${encodeURIComponent('\n\n---\nOriginal Message:\n' + (message.message || ''))}`;
                    replyLink.style.display = 'inline-block'; // Show the reply button
                } else {
                    replyLink.style.display = 'none'; // Hide if no email
                }

                // Display the modal
                modal.style.display = 'flex'; // Use flex to maintain centering
            } else {
                console.error('Message with ID ' + messageId + ' not found.');
                // Optionally display an error message on the page
            }
        }

        // Function to close the modal
        function closeMessageModal() {
            modal.style.display = 'none';
        }

        // Add click listener to table rows (using event delegation on tbody)
        const messagesTableBody = document.querySelector('.message-table tbody');
        if (messagesTableBody) {
            messagesTableBody.addEventListener('click', function(event) {
                // Find the closest row (tr) to the clicked element
                const row = event.target.closest('tr');
                if (row && row.dataset.messageId) {
                    // Check if the click was on the checkbox or a link inside the row
                    const clickedElement = event.target;
                    const isCheckbox = clickedElement.tagName === 'INPUT' && clickedElement.type === 'checkbox';
                    const isLink = clickedElement.tagName === 'A';

                    // If not a checkbox or link, open the modal
                    if (!isCheckbox && !isLink) {
                        openMessageModal(row.dataset.messageId);
                    }
                    // If it's a checkbox, let the default action happen (checking/unchecking)
                    // If it's a link, let the default action happen (navigation)
                }
            });
        }


        // Add click listener to the Toggle Read Status button in the modal
        if (toggleReadStatusBtn) {
            toggleReadStatusBtn.addEventListener('click', function() {
                const messageId = this.dataset.messageId;
                if (messageId) {
                    // Send AJAX request to toggle status
                    fetch('message_actions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=toggle_read_status&message_id=' + encodeURIComponent(messageId)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('Status toggled:', data.message);
                            // Update the status text in the modal
                            detailStatus.innerText = data.new_status;
                            // Find the corresponding row in the table and update its class and status text
                            const row = messagesTableBody.querySelector(`tr[data-message-id="${messageId}"]`);
                            if (row) {
                                row.classList.toggle('read');
                                row.classList.toggle('unread');
                                const statusCell = row.querySelector('td:nth-child(2)');
                                if (statusCell) {
                                    statusCell.innerText = data.new_status;
                                }
                            }
                             // Optionally display a temporary success message in the modal or on the page
                             // alert(data.message); // Simple alert for confirmation
                        } else {
                            console.error('Failed to toggle status:', data.message);
                            // Display an error message
                            alert('Error toggling status: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error during status toggle AJAX:', error);
                        alert('An error occurred while toggling status.');
                    });
                }
            });
        }


        // Select All Checkbox functionality (Keep existing)
        const selectAllCheckbox = document.getElementById('selectAllMessages');
        if (selectAllCheckbox) {
            selectAllCheckbox.onclick = function() {
                const checkboxes = document.querySelectorAll('#messagesForm input[type="checkbox"][name="message_ids[]"]');
                for (let checkbox of checkboxes) {
                    checkbox.checked = this.checked;
                }
            }
        }

        // Function to set the form action and submit (Keep existing)
        function setFormAction(action) {
            const form = document.getElementById('messagesForm');
            if (form) {
                form.action.value = action;
                form.submit();
            } else {
                console.error('Messages form not found.');
            }
        }
    </script>

<?php endif; ?>
