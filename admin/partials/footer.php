<?php

// admin/partials/footer.php

// This file contains the closing HTML tags and any scripts that should be at the end of the body.
// It assumes the opening tags are in header.php.
// It assumes $currentViewForJS and $isLoggedInForJS are passed from dashboard.php.
// It now also assumes $_SESSION['admin_role'] is available for passing to JS, but auto-refresh is removed.

// Get the user's role from the session, default to a low privilege if not set
$userRoleForJS = $_SESSION['admin_role'] ?? 'user';

?>
    <?php if ($isLoggedInForJS): // Only close container and body/html if logged in (opened in header) ?>
        </div> </div> <?php endif; ?>

    <script>
        // Get the current view, logged-in status, and user role from PHP
        const currentView = '<?= $currentViewForJS ?>';
        const isLoggedIn = <?= $isLoggedInForJS ? 'true' : 'false' ?>;
        const userRole = '<?= $userRoleForJS ?>'; // Pass user role to JS
        const mainContentDiv = document.getElementById('main-content');

        // --- Auto-refresh functionality removed ---
        // The refreshContent function and setInterval call have been removed.
        // Content will now only load on initial page load or navigation.

        // Define setFormAction globally so it's available for forms that might be loaded via AJAX
        // This function is used by the messages_view.php form buttons
        function setFormAction(action) {
            const form = document.getElementById('messagesForm');
            if (form) {
                form.action.value = action;
                form.submit();
            } else {
                console.error('Messages form not found.');
            }
        }

        // Define openMessageModal and closeMessageModal globally if they are used by the messages view
        // These functions are needed for the message details modal.
         function openMessageModal(messageId) {
             const modal = document.getElementById('messageDetailModal');
             const detailName = document.getElementById('detailName');
             const detailEmail = document.getElementById('detailEmail');
             const detailTimestamp = document.getElementById('detailTimestamp');
             const detailStatus = document.getElementById('detailStatus');
             const detailMessage = document.getElementById('detailMessage');
             const toggleReadStatusBtn = document.getElementById('toggleReadStatusBtn');
             const replyLink = document.getElementById('replyLink');

             // Find the message in the embedded data (assuming feedbackMessagesData is available)
             // NOTE: If the messages view is loaded via AJAX, feedbackMessagesData will NOT be available
             // unless you re-embed it or fetch message details via a separate AJAX call here.
             // For now, this relies on the initial page load embedding the data.
             const message = typeof feedbackMessagesData !== 'undefined' ? feedbackMessagesData.find(msg => msg.id === messageId) : null;


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
                 if (modal) {
                     modal.style.display = 'flex'; // Use flex to maintain centering
                 }
             } else {
                 console.error('Message with ID ' + messageId + ' not found or data not available.');
                 // Optionally display an error message on the page
             }
         }

         function closeMessageModal() {
             const modal = document.getElementById('messageDetailModal');
             if (modal) {
                 modal.style.display = 'none';
             }
         }

        // Add click listener to table rows (using event delegation on tbody) for messages view
        // This needs to be run after the content is loaded into mainContentDiv.
        // If messages view is loaded via AJAX, this listener needs to be re-attached.
        // Since auto-refresh is removed, this listener will work on initial page load.
        // If you re-implement AJAX loading without full page refresh, ensure this is called after content update.
        function attachMessageTableListeners() {
             const messagesTableBody = mainContentDiv.querySelector('.message-table tbody');
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

             // Add click listener to the Toggle Read Status button in the modal
             const toggleReadStatusBtn = document.getElementById('toggleReadStatusBtn');
             if (toggleReadStatusBtn) {
                 // Remove existing listener if any to prevent duplicates after AJAX (though not needed with full page load)
                 // toggleReadStatusBtn.removeEventListener('click', ...); // Need to store the handler to remove

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
                                 const row = mainContentDiv.querySelector(`tr[data-message-id="${messageId}"]`);
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

             // Re-initialize the selectAllMessages functionality
             const selectAllCheckbox = mainContentDiv.querySelector('#selectAllMessages');
             if (selectAllCheckbox) {
                   selectAllCheckbox.onclick = function() {
                       const checkboxes = mainContentDiv.querySelectorAll('input[type="checkbox"][name="message_ids[]"]');
                       for (let checkbox of checkboxes) {
                           checkbox.checked = this.checked;
                       }
                   }
             }
              // Re-attach setFormAction function to buttons if they are replaced
              const markReadButton = mainContentDiv.querySelector('button[onclick*="mark_read"]');
              if (markReadButton) {
                  markReadButton.onclick = function() { setFormAction('mark_read'); };
              }
              const deleteButton = mainContentDiv.querySelector('button[onclick*="delete_messages"]');
              if (deleteButton) {
                  // Re-attach the confirm dialog as well
                  deleteButton.onclick = function() {
                      if (confirm('Are you sure you want to delete selected messages?')) {
                          setFormAction('delete_messages');
                      }
                  };
              }
        }


        // Re-attach event listeners for delete job links in manage_jobs view
        // This needs to be run after the content is loaded into mainContentDiv.
        function attachManageJobsListeners() {
             const deleteLinks = mainContentDiv.querySelectorAll('a.delete[onclick*="confirm"]');
             deleteLinks.forEach(link => {
                  // Remove existing onclick to avoid double confirmation
                  const originalOnclick = link.onclick;
                  link.onclick = null;
                  // Re-add the confirmation logic
                  link.onclick = function() {
                      return confirm('Are you sure you want to delete this job?');
                  };
             });
        }


        // Initial content load on page load
        // This part remains to load the initial content when the page first loads.
        if (isLoggedIn && mainContentDiv) {
             // Display a loading message initially
             mainContentDiv.innerHTML = '<p>Loading content...</p>';
             // Fetch and load the initial content
             const initialFetchUrl = 'fetch_content.php' + window.location.search;
             fetch(initialFetchUrl)
                 .then(response => {
                     if (!response.ok) {
                         console.error('HTTP error!', response.status, response.statusText);
                         if (response.status === 401 || response.status === 403) {
                             console.log('Session expired or unauthorized, redirecting to login.');
                             window.location.href = 'dashboard.php';
                             return Promise.reject('Unauthorized');
                         }
                         mainContentDiv.innerHTML = '<p class="status-message error">Error loading content: ' + response.statusText + '</p>';
                         throw new Error('HTTP error ' + response.status);
                     }
                     return response.text();
                 })
                 .then(html => {
                     mainContentDiv.innerHTML = html;
                     console.log('Initial content loaded successfully.');

                     // Attach listeners after initial content load
                     if (currentView === 'messages') {
                          attachMessageTableListeners();
                     }
                      if (currentView === 'manage_jobs') {
                           attachManageJobsListeners();
                      }
                     // Re-run Chart.js script for dashboard view after initial load
                     if (currentView === 'dashboard') {
                         const chartCanvas = mainContentDiv.querySelector('#dailyJobsChart');
                         if (chartCanvas) {
                              const scripts = mainContentDiv.querySelectorAll('script');
                              scripts.forEach(script => {
                                  if (script.textContent.includes('new Chart(ctx')) {
                                      const newScript = document.createElement('script');
                                      newScript.textContent = script.textContent;
                                      document.body.appendChild(newScript);
                                      document.body.removeChild(newScript);
                                      console.log('Chart.js script re-executed after initial load.');
                                  }
                              });
                         }
                     }

                 })
                 .catch(error => {
                     console.error('Error fetching initial content:', error);
                 });
        }

    </script>

</body>
</html>
