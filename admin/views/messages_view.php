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
        <input type="hidden" name="action" value="">
        <div style="margin-bottom: 15px;">
            <button type="button" class="button" onclick="setFormAction('mark_read')">Mark Selected as Read</button>
            <button type="button" class="button delete" onclick="if(confirm('Are you sure you want to delete selected messages?')) setFormAction('delete_messages');">Delete Selected</button>
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
                    $isRead = $message['read'] ?? false;
                    $statusText = $isRead ? 'Read' : 'Unread';
                    $rowClass = $isRead ? 'read' : 'unread';
                    ?>
                    <tr class="<?= $rowClass ?>" data-message-id="<?= $messageId ?>" style="cursor: pointer;">
                        <td><input type="checkbox" name="message_ids[]" value="<?= $messageId ?>"></td>
                        <td><?= htmlspecialchars($statusText) ?></td>
                        <td><?= htmlspecialchars($message['name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($message['email'] ?? 'N/A') ?></td>
                        <td><?= nl2br(htmlspecialchars(substr($message['message'] ?? '', 0, 100))) . (strlen($message['message'] ?? '') > 100 ? '...' : '') ?></td>
                        <td><?= isset($message['timestamp']) ? htmlspecialchars(date('Y-m-d H:i', $message['timestamp'])) : 'N/A' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>

    <div id="messageDetailModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeMessageModal()">&times;</span>
            <h3>Message Details</h3>
            <div id="modalStatusArea"></div>
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
        /* Existing styles */
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

        /* Styles for status messages inside the modal */
        #modalStatusArea {
            margin-bottom: 15px;
        }

        .modal-status-message {
            padding: 10px;
            border-radius: 4px;
            font-weight: bold;
            margin-bottom: 10px; /* Spacing between message and content */
        }

        .modal-status-message.success {
            background-color: #d4edda; /* Light green */
            color: #155724; /* Dark green */
            border: 1px solid #c3e6cb;
        }

        .modal-status-message.error {
            background-color: #f8d7da; /* Light red */
            color: #721c24; /* Dark red */
            border: 1px solid #f5c6cb;
        }


    </style>

    <script>
        // Embed feedback messages data as a JavaScript variable using VAR
        // VAR allows re-declaration without SyntaxError, needed due to reExecuteScripts
        var feedbackMessagesData = <?= json_encode($feedbackMessages ?? []); ?>;

        // Get the modal and the elements to display message details - GET ELEMENTS INSIDE openMessageModal
        // const modal = document.getElementById('messageDetailModal'); // Get this inside function
        // const detailName = document.getElementById('detailName'); // Get this inside function
        // const detailEmail = document.getElementById('detailEmail'); // Get this inside function
        // const detailTimestamp = document.getElementById('detailTimestamp'); // Get this inside function
        // const detailStatus = document.getElementById('detailStatus'); // Get this inside function
        // const detailMessage = document.getElementById('detailMessage'); // Get this inside function
        // const toggleReadStatusBtn = document.getElementById('toggleReadStatusBtn'); // Get this inside function
        // const replyLink = document.getElementById('replyLink'); // Get this inside function

        // Get the modal status area element reference
        const modalStatusArea = document.getElementById('modalStatusArea');

        // Function to display status messages inside the modal
        function displayModalStatus(message, type) {
            if (modalStatusArea) {
                // Clear previous status messages
                modalStatusArea.innerHTML = '';

                const statusMessageElement = document.createElement('p');
                statusMessageElement.classList.add('modal-status-message');
                statusMessageElement.classList.add(type); // 'success' or 'error'
                statusMessageElement.innerText = message; // Use innerText for safety

                // Append the new message
                modalStatusArea.appendChild(statusMessageElement);

                // Optional: Auto-remove the message after a few seconds
                // setTimeout(() => {
                //     if (modalStatusArea.contains(statusMessageElement)) {
                //         modalStatusArea.removeChild(statusMessageElement);
                //     }
                // }, 5000); // Remove after 5 seconds
            } else {
                console.error('Could not find modal status area element.');
                // Fallback to console logging
                if (type === 'error') {
                    console.error('Modal Status:', message);
                } else {
                    console.log('Modal Status:', message);
                }
            }
        }


        // Function to open the modal and populate it - GET ELEMENTS INSIDE THIS FUNCTION
        function openMessageModal(messageId) {
            // Find the message in the embedded data (now using var feedbackMessagesData)
            const message = feedbackMessagesData.find(msg => msg.id === messageId);

            // Get modal elements inside the function where they should exist in the DOM
            const modal = document.getElementById('messageDetailModal');
            const detailName = document.getElementById('detailName');
            const detailEmail = document.getElementById('detailEmail');
            const detailTimestamp = document.getElementById('detailTimestamp');
            const detailStatus = document.getElementById('detailStatus');
            const detailMessage = document.getElementById('detailMessage');
            const toggleReadStatusBtn = document.getElementById('toggleReadStatusBtn');
            const replyLink = document.getElementById('replyLink');
             const modalStatusArea = document.getElementById('modalStatusArea'); // Get status area here too

            // Clear previous status messages when opening a new message
             if(modalStatusArea) modalStatusArea.innerHTML = '';


            if (message) {
                // Populate modal with message details, checking if elements were found
                if (detailName) detailName.innerText = message.name || 'N/A';
                if (detailEmail) detailEmail.innerText = message.email || 'N/A';
                if (detailTimestamp) detailTimestamp.innerText = message.timestamp ? new Date(message.timestamp * 1000).toLocaleString() : 'N/A'; // Convert timestamp to readable date/time
                if (detailStatus) detailStatus.innerText = (message.read ?? false) ? 'Read' : 'Unread';
                if (detailMessage) detailMessage.innerText = message.message || 'No message content.'; // Use innerText for safety

                // Set the data-message-id on the toggle button, checking if element was found
                if (toggleReadStatusBtn) toggleReadStatusBtn.dataset.messageId = message.id;

                // Set the mailto link for reply, checking if element was found
                if (replyLink) {
                    if (message.email) {
                        replyLink.href = `mailto:${encodeURIComponent(message.email)}?subject=${encodeURIComponent('Reply to your feedback')}&body=${encodeURIComponent('\n\n---\nOriginal Message:\n' + (message.message || ''))}`;
                        replyLink.style.display = 'inline-block'; // Show the reply button
                    } else {
                        replyLink.style.display = 'none'; // Hide if no email
                    }
                }

                // Display the modal, checking if it was found
                if(modal) modal.style.display = 'flex'; // Use flex to maintain centering

            } else {
                console.error('Message with ID ' + messageId + ' not found.');
                // Display an error message in the modal placeholder
                 if (modalStatusArea) {
                     displayModalStatus('Error: Message data not found.', 'error');
                 } else {
                    const detailContentDiv = document.querySelector('#messageDetailModal .message-details-content');
                    if(detailContentDiv) detailContentDiv.innerHTML = '<p>Error: Message not found.</p>';
                 }
                 if(modal) modal.style.display = 'flex'; // Open modal to show error

            }
        }

        // Function to close the modal
        function closeMessageModal() {
             const modal = document.getElementById('messageDetailModal'); // Get modal reference here too
            if(modal) {
                modal.style.display = 'none';
                // Optional: Clear status message when closing
                 const modalStatusArea = document.getElementById('modalStatusArea');
                 if(modalStatusArea) modalStatusArea.innerHTML = '';
             }
        }


        // Add click listener to table rows (using event delegation on tbody) - This listener should ideally be attached in footer.php
        const messagesTableBody = document.querySelector('.message-table tbody');
        if (messagesTableBody) {
            // Ensure we don't add duplicate listeners if this script is re-executed
             // A robust way needs managing listeners in footer.php, but for this script block:
             // You could add a flag or remove previous listeners if possible.
             // For now, keeping it as is, acknowledging potential duplicates with reExecuteScripts.

            messagesTableBody.addEventListener('click', function(event) {
                // Find the closest row (tr) to the clicked element
                const row = event.target.closest('tr');
                if (row && row.dataset.messageId) {
                    const clickedElement = event.target;
                    const isCheckbox = clickedElement.tagName === 'INPUT' && clickedElement.type === 'checkbox';
                    const isLink = clickedElement.tagName === 'A';

                    // If not a checkbox or link, open the modal
                    if (!isCheckbox && !isLink) {
                        openMessageModal(row.dataset.messageId); // Calls the function defined in this script block
                    }
                    // If it's a checkbox, let the default action happen (checking/unchecking)
                    // If it's a link, let the default action happen (navigation)
                }
            });
        }


        // Add click listener to the Toggle Read Status button in the modal - This listener should ideally be attached in footer.php
        // This listener will be added every time the script runs due to reExecuteScripts, potentially causing duplicates.
        const toggleReadStatusBtn = document.getElementById('toggleReadStatusBtn'); // Need to get reference here for listener attachment
        if (toggleReadStatusBtn) {
            // Add event listener (potential duplicate if reExecuteScripts is used without removal)
             toggleReadStatusBtn.addEventListener('click', function() { // Using anonymous function here
                 const messageId = this.dataset.messageId;
                 if (messageId) {
                      // Ensure the fetch call includes the X-Requested-With header for server-side AJAX check
                     fetch('message_actions.php', {
                         method: 'POST',
                         headers: {
                             'Content-Type': 'application/x-www-form-urlencoded',
                              'X-Requested-With': 'XMLHttpRequest' // Add this header
                         },
                         body: 'action=toggle_read_status&message_id=' + encodeURIComponent(messageId)
                     })
                     .then(response => {
                          if (!response.ok) {
                             // Check for unauthorized response (401/403) and redirect
                             if (response.status === 401 || response.status === 403) {
                                  window.location.href = 'dashboard.php'; // Redirect to handle login
                                  return Promise.reject('Unauthorized'); // Stop processing
                             }
                             // Read response text for error details if not OK
                              return response.text().then(text => {
                                  throw new Error(`HTTP error ${response.status}: ${text}`);
                              });
                          }
                         return response.json(); // Expect JSON response
                     })
                     .then(data => {
                         if (data.success) {
                             console.log('Status toggled:', data.message);
                             // Update the status text in the modal - Get the element here as it might be new
                             const modalDetailStatus = document.getElementById('detailStatus');
                             if(modalDetailStatus) modalDetailStatus.innerText = data.new_status;

                             // Find the corresponding row in the table and update its class and status text
                             // messagesTableBody needs to be accessible, it's defined outside this listener but inside the script
                             const row = document.querySelector(`.message-table tbody tr[data-message-id="${messageId}"]`);
                             if (row) {
                                 row.classList.remove('read', 'unread'); // Remove previous classes
                                 row.classList.add(data.new_status === 'Read' ? 'read' : 'unread'); // Add new class
                                 const statusCell = row.querySelector('td:nth-child(2)'); // Status is the 2nd cell
                                 if (statusCell) statusCell.innerText = data.new_status;
                             }

                              // Update the status in the embedded feedbackMessagesData array
                              if (typeof feedbackMessagesData !== 'undefined' && Array.isArray(feedbackMessagesData)) {
                                  const messageToUpdate = feedbackMessagesData.find(msg => msg.id === messageId);
                                  if (messageToUpdate) {
                                       messageToUpdate.read = (data.new_status === 'Read');
                                       console.log(`Embedded data for message ID ${messageId} updated.`);
                                  }
                              }

                             // Display success message inside the modal status area
                             displayModalStatus(data.message || 'Status updated successfully!', 'success');


                         } else {
                             console.error('Failed to toggle status:', data.message);
                             // Display error message inside the modal status area
                              displayModalStatus('Error: ' + (data.message || 'Unknown error'), 'error');
                         }
                     })
                     .catch(error => {
                         console.error('Error during status toggle AJAX:', error);
                         if (error !== 'Unauthorized') { // Don't show generic error if unauthorized redirect happened
                              let errorMessage = 'An error occurred while toggling status.';
                               if (error.message && error.message.startsWith('HTTP error')) {
                                    errorMessage += ` (${escapeHTML(error.message)})`;
                               }
                              // Display error message inside the modal status area
                              displayModalStatus(errorMessage, 'error');
                         }

                     });
                 }
             });
         }

        // Select All Checkbox functionality (Keep existing)
        const selectAllCheckbox = document.getElementById('selectAllMessages'); // Need to get reference here for listener attachment
        if (selectAllCheckbox) {
             // Add event listener (potential duplicate if reExecuteScripts is used without removal)
            selectAllCheckbox.addEventListener('click', function() { // Using anonymous function
                const checkboxes = document.querySelectorAll('#messagesForm input[type="checkbox"][name="message_ids[]"]');
                for (let checkbox of checkboxes) {
                    checkbox.checked = this.checked;
                }
            });
         }


        // Function to set the form action and submit (Keep existing) - This function can remain here or be global in footer.php
        function setFormAction(action) {
            const form = document.getElementById('messagesForm');
            if (form) {
                 // Check if any checkboxes are checked before submitting for mark/delete
                 if ((action === 'mark_read' || action === 'delete_messages') && form.querySelectorAll('input[type="checkbox"][name="message_ids[]"]:checked').length === 0) {
                      alert('Please select at least one message.');
                      return; // Stop if no messages are selected for bulk actions
                }
                form.action.value = action;
                form.submit();
            } else {
                console.error('Messages form not found.');
            }
        }

         // Helper function to escape HTML for safe display in innerHTML (copy from footer.php if needed)
         function escapeHTML(str) {
              if (typeof str !== 'string') return str;
              const div = document.createElement('div');
              div.appendChild(document.createTextNode(str));
              return div.innerHTML;
         }


    </script>

<?php endif; ?>