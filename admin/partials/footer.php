<?php

// admin/partials/footer.php

// This file contains the closing HTML tags and any scripts that should be at the end of the body.
// It assumes the opening tags are in header.php.
// It assumes $currentViewForJS and $isLoggedInForJS are passed from dashboard.php.
// It also assumes $_SESSION['admin_role'] is available for passing to JS.

// Get the user's role from the session, default to a low privilege if not set
$userRoleForJS = $_SESSION['admin_role'] ?? 'user';

?>
    <?php // The conditional closing of </div></div> has been removed as it's not needed with the current dashboard.php structure.
          // dashboard.php now correctly closes .container and .main-content before including this footer.
    ?>

    <?php // Chart.js is now loaded conditionally by header.php for the initial dashboard_overview,
          // or by individual view files (e.g., dashboard_job_stats_view.php) when they are loaded via AJAX.
          // The reExecuteScripts function in this footer will handle those.
          // So, the unconditional <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> is removed from here.
    ?>
    <script>
        // Get the current view, logged-in status, and user role from PHP
        const currentView = <?= json_encode($currentViewForJS) ?>;
        const isLoggedIn = <?= $isLoggedInForJS ? 'true' : 'false' ?>;
        const userRole = <?= json_encode($userRoleForJS) ?>; // Pass user role to JS
        const mainContentDiv = document.getElementById('main-content');

        // --- GLOBAL MODAL CONTROL FUNCTIONS ---
        let currentModal = null; // Keep track of the currently open modal element

        // Function to open a modal element
        function openModal(modalElement) {
            if (modalElement) {
                closeCurrentModal(); // Close any currently open modal first
                currentModal = modalElement;
                currentModal.style.display = 'flex'; // Use flex to maintain centering
                 // Optional: Focus on the first input field in the form when modal opens
                const firstInput = currentModal.querySelector('input, textarea, select');
                if(firstInput) {
                    firstInput.focus();
                }
            }
        }

        // Function to close the currently open modal
        function closeCurrentModal() {
            if (currentModal) {
                currentModal.style.display = 'none';
                 // Optional: Clear the form or reset its state when closing
                 const form = currentModal.querySelector('form');
                 if (form) {
                     form.reset(); // Reset the form fields
                 }
                currentModal = null; // Clear the reference
            }
        }

        // Handle clicks outside the modal (defined globally)
        function handleOutsideClick(event) {
             // Check if a modal is open and if the click target is the modal backdrop itself
             if (currentModal && event.target === currentModal) {
                 closeCurrentModal();
             }
         }

         // Add the global click listener for closing modals by clicking outside
         window.removeEventListener('click', handleOutsideClick);
         window.addEventListener('click', handleOutsideClick);

        // --- SPECIFIC MODAL CONTROL FUNCTIONS (Defined globally) ---

        // Function to open and populate the message detail modal
        function openMessageModal(messageId, listItemElement) { // Added listItemElement
            const message = feedbackMessagesData.find(msg => msg.id === messageId); // feedbackMessagesData from messages_view.php
            const modal = document.getElementById('messageDetailModal');
            const modalStatusArea = document.getElementById('modalStatusArea');

            if (modalStatusArea) modalStatusArea.innerHTML = ''; // Clear previous status

            if (message && modal) {
                document.getElementById('detailName').innerText = message.name || 'N/A';
                document.getElementById('detailEmail').innerText = message.email || 'N/A';
                document.getElementById('detailTimestamp').innerText = message.timestamp ? new Date(message.timestamp * 1000).toLocaleString() : 'N/A';
                document.getElementById('detailMessage').innerText = message.message || 'No message content.';

                const replyLink = document.getElementById('replyLink');
                if (message.email) {
                    replyLink.href = `mailto:${encodeURIComponent(message.email)}?subject=${encodeURIComponent('Reply to your feedback')}&body=${encodeURIComponent('\n\n---\nOriginal Message:\n' + (message.message || ''))}`;
                    replyLink.style.display = 'inline-block';
                } else {
                    replyLink.style.display = 'none';
                }

                populateCommandsList(message.commands || []); // Populate commands in modal

                const addCommandButton = document.getElementById('addCommandBtn');
                if (addCommandButton) {
                    const newAddCommandButton = addCommandButton.cloneNode(true);
                    addCommandButton.parentNode.replaceChild(newAddCommandButton, addCommandButton);
                    newAddCommandButton.onclick = function() { handleAddCommand(message.id); };
                }

                modal.style.display = 'flex';

                if (!message.read) {
                    fetch('message_actions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                        body: `action=mark_read&message_id=${encodeURIComponent(message.id)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('Message marked as read:', data.message);
                            if (listItemElement) {
                                listItemElement.classList.remove('message-unread');
                                listItemElement.classList.add('message-read');
                                const statusBadge = listItemElement.querySelector('.status-badge');
                                if (statusBadge) {
                                    statusBadge.classList.remove('status-unread');
                                    statusBadge.classList.add('status-read');
                                    statusBadge.textContent = 'Read';
                                }
                            }
                            const msgInJsData = feedbackMessagesData.find(m => m.id === message.id);
                            if (msgInJsData) msgInJsData.read = true;

                            const conversationCard = listItemElement.closest('.email-group.card');
                            if (conversationCard) {
                                const email = conversationCard.dataset.email;
                                let allMessagesInConvRead = true;
                                feedbackMessagesData.forEach(msg => {
                                    if (msg.email && msg.email.toLowerCase().trim() === email.toLowerCase().trim() && !msg.read) {
                                        allMessagesInConvRead = false;
                                    }
                                });
                                conversationCard.classList.toggle('read', allMessagesInConvRead);
                                conversationCard.classList.toggle('unread', !allMessagesInConvRead);
                            }
                            // updateOverallUnreadCount(); // Update main nav badge
                        } else {
                            displayModalStatus('Failed to mark as read: ' + (data.message || 'Unknown error'), 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error marking message as read:', error);
                        displayModalStatus('An error occurred while marking as read.', 'error');
                    });
                }
            } else {
                displayModalStatus('Error: Message data not found.', 'error');
                if (modal) modal.style.display = 'flex'; // Still show modal with error
            }
         }


         // Named function for opening the Post Job modal (called by the button listener in manage_jobs_view)
        function handleOpenPostJobModalClick() {
             const postJobModal = document.getElementById('postJobModal'); // Get modal element
             openModal(postJobModal); // Use global open modal function
        }

         // Helper function to escape HTML for safe display in innerHTML
         function escapeHTML(str) {
             if (typeof str !== 'string') return str;
             const div = document.createElement('div');
             div.appendChild(document.createTextNode(str));
             return div.innerHTML;
         }


        // --- EVENT LISTENER ATTACHMENT FUNCTIONS (Called after AJAX load) ---

        // Define setFormAction globally so it's available for forms that might be loaded via AJAX
        // This function is used by the messages_view.php form buttons (if that view is still used in table format)
        function setFormAction(action) {
            const form = document.getElementById('messagesForm'); // This ID might be from an older messages view
            if (form) {
                form.action.value = action;
                form.submit();
            } else {
                console.warn('Messages form (messagesForm) not found. This function might be for an older view.');
            }
        }

        // Attach listeners for the messages view elements (conversation view)
        function attachMessageConversationListeners() {
            // Event delegation for message items if they are dynamically added to a conversation
            const messagesContainer = document.querySelector('.messages-container');
            if (messagesContainer) {
                messagesContainer.removeEventListener('click', handleConversationItemClick); // Remove old before adding
                messagesContainer.addEventListener('click', handleConversationItemClick);
            }
        }

        function handleConversationItemClick(event) {
            const messageItem = event.target.closest('.feedback-message-item');
            if (messageItem && messageItem.dataset.messageId) {
                // Check if the click was on the flag button
                if (event.target.classList.contains('flag-button') || event.target.closest('.flag-button')) {
                    // Flag button click is handled by its own inline onclick, so do nothing here to prevent modal open
                    return;
                }
                openMessageModal(messageItem.dataset.messageId, messageItem);
            }
        }


         // Attach listeners for the manage jobs view after it's loaded (either initially or via AJAX)
         function attachManageJobsListeners() {
              const openPostJobModalBtn = document.getElementById('postNewJobBtn'); // ID from manage_jobs_view.php
              const postJobModal = document.getElementById('postJobModal');

              if (openPostJobModalBtn && postJobModal) {
                  openPostJobModalBtn.removeEventListener('click', handleOpenPostJobModalClick);
                  openPostJobModalBtn.addEventListener('click', handleOpenPostJobModalClick);
               }

               if (postJobModal) {
                   const closePostJobModalBtn = postJobModal.querySelector('#closePostJobModal'); // ID from manage_jobs_view.php
                   if (closePostJobModalBtn) {
                       closePostJobModalBtn.removeEventListener('click', closeCurrentModal);
                       closePostJobModalBtn.addEventListener('click', closeCurrentModal);
                   }
               }
         }


        // Function to handle browser back/forward buttons (Popstate event)
        function handlePopstate(event) {
             console.log('Popstate event:', event.state);
             if (event.state && event.state.view) {
                 const view = event.state.view;
                 const id = event.state.id || null;
                 const username = event.state.username || null;

                 let ajaxUrl = 'fetch_content.php?view=' + encodeURIComponent(view);
                 if (view === 'edit_job' && id) {
                      ajaxUrl += '&id=' + encodeURIComponent(id);
                 } else if ((view === 'manage_users' || view === 'edit_user') && username) {
                      ajaxUrl += '&username=' + encodeURIComponent(username);
                 }

                 if (mainContentDiv) {
                     mainContentDiv.innerHTML = '<p>Loading...</p>';
                 }

                 fetch(ajaxUrl)
                     .then(response => {
                         if (!response.ok) {
                              console.error('HTTP error', response.status);
                              if (response.status === 401 || response.status === 403) {
                                 window.location.href = 'dashboard.php';
                                 return Promise.reject('Unauthorized');
                              }
                             return response.text();
                         }
                         return response.text();
                     })
                     .then(html => {
                          closeCurrentModal();
                         if (mainContentDiv) {
                             mainContentDiv.innerHTML = html;
                         }
                         executePostLoadScripts(view);
                         console.log('Content loaded via Popstate for view:', view);
                     })
                     .catch(error => {
                         console.error('Error fetching history state content:', error);
                         if (error !== 'Unauthorized' && mainContentDiv) {
                            mainContentDiv.innerHTML = '<p class="status-message error">Failed to load content.</p>';
                         }
                     });
             }
         }

         // Function to execute scripts and attach listeners after content is loaded
         function executePostLoadScripts(view) {
              if (mainContentDiv) {
                  reExecuteScripts(mainContentDiv);
              }

             if (view === 'messages') {
                 attachMessageConversationListeners(); // Use new listener for conversation view
             } else if (view === 'manage_jobs' || view === 'post_job') { // post_job might also need this if it has dynamic elements
                  attachManageJobsListeners();
             }
             // Add other view-specific listener attachments here
             // e.g., if 'edit_job_view.php' has its own JS that needs re-binding for regenerateSummary button
             if (view === 'edit_job') {
                const regenerateBtn = document.getElementById('regenerateSummary');
                if (regenerateBtn) {
                    // Re-attach listener for regenerateSummary if it was part of the loaded content
                    // This assumes the regenerateSummary logic is self-contained or globally available
                    // If the function itself is part of the loaded script, reExecuteScripts should handle it.
                    // If it's a global function, just re-attaching the listener is fine.
                    // For simplicity, assuming reExecuteScripts handles defining the function if it's in the view.
                    // If the listener was attached in the view's script block, reExecuteScripts will re-run that.
                }
             }
             if (view === 'logs') {
                // The logs_view.php has its own script block with DOMContentLoaded,
                // reExecuteScripts should handle re-running that.
             }
             if (view === 'whatsapp_profile') {
                // whatsapp_profile_view.php has its own script block.
             }
         }

         function reExecuteScripts(element) {
             const scripts = element.querySelectorAll('script');
             scripts.forEach(script => {
                 try {
                     const newScript = document.createElement('script');
                     script.getAttributeNames().forEach(attrName => {
                         newScript.setAttribute(attrName, script.getAttribute(attrName));
                     });
                     if (script.textContent) {
                         newScript.textContent = script.textContent;
                     }
                     // Append to head or body to execute. Appending to body is common.
                     document.body.appendChild(newScript);
                      if (!script.src && newScript.parentNode) { // Only remove inline scripts that were added to body
                           // Using a timeout to ensure script executes before removal, though often not strictly needed for inline.
                           setTimeout(() => { if (newScript.parentNode) document.body.removeChild(newScript); }, 0);
                      } else if (script.src && newScript.parentNode) {
                           // For external scripts, some browsers might remove them too quickly.
                           // However, the browser typically handles execution once src is set and appended.
                           // If issues arise, consider not removing external script tags re-added this way,
                           // or use a load event on the newScript before removing.
                           // For now, let's not remove external scripts re-added this way to be safe.
                      }
                     console.log('Script re-executed:', script.src || 'inline script (content re-evaluated)');
                 } catch (e) {
                     console.error('Error re-executing script:', script.src || 'inline script', e);
                 }
             });
         }


        // --- Function to handle AJAX navigation link clicks ---
        function handleNavLinkClick(e) {
            const href = this.getAttribute('href');
            if (href && href.startsWith('dashboard.php?view=')) {
                e.preventDefault();

                const url = new URL(href, window.location.origin);
                const view = url.searchParams.get('view');
                const id = url.searchParams.get('id');
                const username = url.searchParams.get('username');

                let ajaxUrl = 'fetch_content.php?view=' + encodeURIComponent(view);
                if (view === 'edit_job' && id) {
                      ajaxUrl += '&id=' + encodeURIComponent(id);
                 } else if ((view === 'manage_users' || view === 'edit_user') && username) {
                      ajaxUrl += '&username=' + encodeURIComponent(username);
                 }

                if (mainContentDiv) {
                    mainContentDiv.innerHTML = '<p>Loading...</p>';
                }

                fetch(ajaxUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }}) // Add X-Requested-With header
                    .then(response => {
                        if (!response.ok) {
                            console.error('HTTP error', response.status);
                            if (response.status === 401 || response.status === 403) {
                                window.location.href = 'dashboard.php';
                                return Promise.reject('Unauthorized');
                            }
                             return response.text();
                         }
                        return response.text();
                    })
                    .then(html => {
                         closeCurrentModal();
                        if (mainContentDiv) {
                            mainContentDiv.innerHTML = html;
                        }
                        history.pushState({ view: view, id: id, username: username }, '', href);
                         executePostLoadScripts(view);
                         console.log('Content loaded via NavLink for view:', view);
                    })
                    .catch(error => {
                        console.error('Error fetching content:', error);
                        if (error !== 'Unauthorized' && mainContentDiv) {
                           mainContentDiv.innerHTML = '<p class="status-message error">Failed to load content.</p>';
                        }
                    });
            }
        }


        document.addEventListener('DOMContentLoaded', () => {
            console.log("DOMContentLoaded event fired."); // DEBUG: Check if DOMContentLoaded fires

             if (isLoggedIn && mainContentDiv) {
                 const initialViewFromPHP = currentView; // Use the JS const currentView
                 const urlParams = new URLSearchParams(window.location.search);
                 const initialJobIdFromUrl = urlParams.get('id');
                 const initialUsernameFromUrl = urlParams.get('username');

                 let initialFetchUrl = `fetch_content.php?view=${encodeURIComponent(initialViewFromPHP)}`;
                 if (initialViewFromPHP === 'edit_job' && initialJobIdFromUrl) {
                      initialFetchUrl += `&id=${encodeURIComponent(initialJobIdFromUrl)}`;
                 } else if ((initialViewFromPHP === 'manage_users' || initialViewFromPHP === 'edit_user') && initialUsernameFromUrl) {
                      initialFetchUrl += `&username=${encodeURIComponent(initialUsernameFromUrl)}`;
                 }

                 mainContentDiv.innerHTML = '<p>Loading...</p>';

                 fetch(initialFetchUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }}) // Add X-Requested-With header
                     .then(response => {
                          if (!response.ok) {
                              console.error('HTTP error', response.status);
                              if (response.status === 401 || response.status === 403) {
                                 window.location.href = 'dashboard.php';
                                 return Promise.reject('Unauthorized');
                              }
                              return response.text().then(text => {
                                  throw new Error(`HTTP error ${response.status}: ${text}`);
                              });
                          }
                          return response.text();
                      })
                     .then(html => {
                         closeCurrentModal();
                         if (mainContentDiv) {
                             mainContentDiv.innerHTML = html;
                         }
                         executePostLoadScripts(initialViewFromPHP);
                         history.replaceState({ view: initialViewFromPHP, id: initialJobIdFromUrl, username: initialUsernameFromUrl }, '', window.location.href);

                         document.querySelectorAll('.admin-sidebar nav a').forEach(link => {
                             link.removeEventListener('click', handleNavLinkClick);
                             link.addEventListener('click', handleNavLinkClick);
                         });
                         console.log('Initial content loaded for view:', initialViewFromPHP);
                     })
                     .catch(error => {
                         console.error('Error fetching initial content:', error);
                          if (error !== 'Unauthorized' && mainContentDiv) { // Check if mainContentDiv still exists
                              let errorMessage = 'Failed to load initial content.';
                               // Make error message construction more robust
                               if (error && typeof error.message === 'string' && error.message.startsWith('HTTP error')) {
                                    errorMessage += ` (${escapeHTML(error.message)})`;
                               } else if (error && typeof error.message === 'string') {
                                   errorMessage += ` (Details: ${escapeHTML(error.message)})`;
                               } else if (typeof error === 'string') {
                                   errorMessage += ` (Details: ${escapeHTML(error)})`;
                               }
                              mainContentDiv.innerHTML = `<p class="status-message error">${errorMessage}</p>`;
                          }
                     });

                 window.removeEventListener('popstate', handlePopstate);
                 window.addEventListener('popstate', handlePopstate);
             } // End of if (isLoggedIn && mainContentDiv)

            // Sidebar Toggle Logic has been removed as the sidebar is now fixed.
            // No JavaScript is needed for toggling or cookie management for the sidebar.

        }); // Close DOMContentLoaded listener

    </script>


</body>
</html>
