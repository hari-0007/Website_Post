<?php

// admin/partials/footer.php

// This file contains the closing HTML tags and any scripts that should be at the end of the body.
// It assumes the opening tags are in header.php.
// It assumes $currentViewForJS and $isLoggedInForJS are passed from dashboard.php.
// It also assumes $_SESSION['admin_role'] is available for passing to JS.

// Get the user's role from the session, default to a low privilege if not set
$userRoleForJS = $_SESSION['admin_role'] ?? 'user';

?>
    <?php if ($isLoggedInForJS): // Only close container and body/html if logged in (opened in header) ?>
        </div>
    </div> <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <script>
        // Get the current view, logged-in status, and user role from PHP
        const currentView = '<?= $currentViewForJS ?>';
        const isLoggedIn = <?= $isLoggedInForJS ? 'true' : 'false' ?>;
        const userRole = '<?= $userRoleForJS ?>'; // Pass user role to JS
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
         // This listener is permanent, but the handleOutsideClick function checks if a modal is open.
         // Remove any previous instances to prevent duplicates if this script is ever re-evaluated (though it shouldn't be with standard AJAX).
         window.removeEventListener('click', handleOutsideClick);
         window.addEventListener('click', handleOutsideClick);

        // --- SPECIFIC MODAL CONTROL FUNCTIONS (Defined globally) ---

        // Function to open and populate the message detail modal
        function openMessageModal(messageId) {
             const messageDetailModal = document.getElementById('messageDetailModal');
             const detailContentDiv = messageDetailModal ? messageDetailModal.querySelector('#messageDetailContent') : null;

             if (messageDetailModal && detailContentDiv) {
                 // Clear previous content and show loading state
                 detailContentDiv.innerHTML = '<p>Loading message details...</p>';
                 openModal(messageDetailModal); // Use the global openModal function

                 // Fetch message details via AJAX
                 fetch('message_actions.php', {
                     method: 'POST', // Use POST if fetching specific data
                     headers: {
                         'Content-Type': 'application/x-www-form-urlencoded',
                         'X-Requested-With': 'XMLHttpRequest' // ADD THIS HEADER
                     },
                     body: 'action=get_message_detail&message_id=' + encodeURIComponent(messageId) // Assuming message_actions.php handles 'get_message_detail'
                 })
                 .then(response => {
                      if (!response.ok) {
                          // If response is not OK, read the error message from the response body
                          return response.text().then(text => {
                              throw new Error(`HTTP error ${response.status}: ${text}`);
                          });
                      }
                       return response.json(); // Expect JSON response with message details
                 })
                 .then(data => {
                      if (detailContentDiv) {
                          if (data.success) {
                               // Populate modal with fetched data
                               detailContentDiv.innerHTML = `
                                   <div class="message-detail">
                                       <h4>Message from ${escapeHTML(data.message.name ?? 'N/A')}</h4>
                                       <p><strong>Email:</strong> ${escapeHTML(data.message.email ?? 'N/A')}</p>
                                       <p><strong>Received On:</strong> ${escapeHTML(data.message.received_on ?? 'N/A')}</p>
                                       <p><strong>Status:</strong> <span id="detailStatus">${escapeHTML(data.message.status ?? 'N/A')}</span></p>
                                       <div class="message-text">${escapeHTML(data.message.message_text ?? 'N/A')}</div>
                                       <button id="toggleReadStatusBtn" class="button" data-message-id="${messageId}" style="margin-top: 15px;">Toggle Read Status</button>
                                   </div>
                               `;

                               // After populating, attach listener to the toggle button
                               const toggleReadStatusBtn = document.getElementById('toggleReadStatusBtn');
                               if(toggleReadStatusBtn) {
                                    // Remove existing listener before adding to avoid duplicates
                                     toggleReadStatusBtn.removeEventListener('click', handleToggleReadStatusClick);
                                     toggleReadStatusBtn.addEventListener('click', handleToggleReadStatusClick);
                               }

                          } else {
                                // Handles success: false response from PHP
                                detailContentDiv.innerHTML = '<p class="status-message error">Error loading message details: ' + escapeHTML(data.message) + '</p>';
                                console.error('Error fetching message details:', data.message);
                          }
                      }
                 })
                 .catch(error => {
                     console.error('Error fetching message details AJAX:', error);
                     if (detailContentDiv) {
                          // Display a more informative error if possible
                           let errorMessage = 'Failed to fetch message details.';
                           if (error.message.startsWith('HTTP error')) {
                               errorMessage += ` (${escapeHTML(error.message)})`;
                           }
                           detailContentDiv.innerHTML = `<p class="status-message error">${errorMessage}</p>`;
                     }
                 });

             } else {
                 console.error('Message detail modal or content div not found on the page.');
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

        // Attach listeners for the messages view elements
        function attachMessageTableListeners() {
             const messagesTableBody = document.querySelector('.message-table tbody');
             if (messagesTableBody) {
                 // Remove existing listener before adding to avoid duplicates
                 messagesTableBody.removeEventListener('click', handleMessageRowClick);
                 messagesTableBody.addEventListener('click', handleMessageRowClick);
             }

             // Re-attach listeners for the Select All checkbox
             const selectAllCheckbox = document.getElementById('selectAllMessages');
             if (selectAllCheckbox) {
                  // Remove existing listener before adding
                  selectAllCheckbox.removeEventListener('click', handleSelectAllClick);
                  selectAllCheckbox.addEventListener('click', handleSelectAllClick);
             }

             // Re-attach listeners for the Form Action buttons
              const messagesForm = document.getElementById('messagesForm');
              if (messagesForm) {
                   const actionButtons = messagesForm.querySelectorAll('button[onclick]'); // Find buttons with onclick
                   actionButtons.forEach(button => {
                       const onclickAttr = button.getAttribute('onclick');
                       // WARNING: Parsing onclick is fragile. A better approach is to use data attributes.
                       // For example, data-action="mark_read" and data-confirm="Are you sure...?"
                       // But sticking to existing structure for now.
                       // We need to re-create the click event listener that calls the original onclick logic.

                       // Create a new click handler that executes the original onclick string
                       const newClickHandler = function(event) {
                           try {
                               // This executes the string content like setFormAction('...')
                               // If the original onclick had a confirm and return, we need to handle it.
                               // Example: onclick="if(confirm('...')) setFormAction('delete_messages');"
                               // Eval is dangerous, but necessary if we must replicate complex onclick logic without refactoring HTML.
                               // A safer way would be to pass action and confirm message via data attributes.
                               // Let's assume setFormAction handles the submit and confirms are inline before the call.
                               // Simple case: onclick="setFormAction('mark_read')" -> just call setFormAction
                               // Confirm case: onclick="if(confirm('...')) setFormAction('delete_messages');" -> execute the string as is.
                               // We'll just execute the string content.

                               // Remove the original onclick attribute after capturing its value
                               // This prevents it from firing twice if the element somehow retains it.
                               // button.removeAttribute('onclick'); // Do this carefully if needed elsewhere

                               // Execute the onclick string. Eval is used here because we need to run arbitrary JS from the attribute.
                               // This is a security risk if the onclick content is not trusted.
                               eval(onclickAttr); // Execute the captured onclick string

                           } catch (e) {
                               console.error('Error executing onclick from button:', onclickAttr, e);
                           }
                       };

                        // Remove previous listeners added by this function to avoid duplicates
                       // (This is still hard without named functions for every possible action/confirm combo)
                       // A simpler robust approach: just add the new listener. If the original onclick persists
                       // and you can't remove it reliably, you might get double execution depending on browser.
                       // Assuming removing the attribute works or doesn't cause issues.

                       // A safer approach for this specific case (setFormAction) is to parse the action and call setFormAction.
                       const actionMatch = onclickAttr.match(/setFormAction\('([^']+)'\)/);
                       if (actionMatch && actionMatch[1]) {
                           const action = actionMatch[1];
                           // Check for confirm in the string
                           const needsConfirm = onclickAttr.includes('confirm(');

                           // Remove the original onclick attribute as we are replacing its behavior with an event listener
                           button.removeAttribute('onclick');


                           // Remove any previous listeners added by this logic to this specific button element
                           // (This is still hard without named functions for every possible action/confirm combo)
                           // A simpler robust approach: just add the new listener. If the original onclick persists
                           // and you can't remove it reliably, you might get double execution depending on browser.
                           // Assuming removing the attribute works or doesn't cause issues.

                           // Add the listener using the parsed action
                           if (needsConfirm) {
                                button.removeEventListener('click', handleConfirmActionClick); // Remove existing listener for named function
                                button.addEventListener('click', handleConfirmActionClick.bind(button, action)); // Bind action to handler
                           } else {
                                button.removeEventListener('click', handleSimpleActionClick); // Remove existing listener for named function
                                button.addEventListener('click', handleSimpleActionClick.bind(button, action)); // Bind action to handler
                           }
                       } else {
                           console.warn("Could not parse setFormAction from button onclick:", onclickAttr);
                            // If parsing fails, maybe just add a generic listener that tries eval? Or log error.
                       }
                   });

                    // Define named handler functions for clarity
                    function handleSimpleActionClick(action) {
                         setFormAction(action); // Call the global setFormAction
                    }

                    function handleConfirmActionClick(action) {
                         // 'this' inside here is the button due to bind()
                         const confirmMessageMatch = this.getAttribute('onclick').match(/confirm\('([^']+)'\)/);
                         const confirmMessage = confirmMessageMatch && confirmMessageMatch[1] ? confirmMessageMatch[1] : 'Are you sure?';
                         if (confirm(confirmMessage)) {
                             setFormAction(action); // Call the global setFormAction
                         }
                    }
              }


             // No need to attach modal close listeners here for the message detail modal;
             // the global handleOutsideClick and closeCurrentModal handle it.
             // The toggle read status button listener is attached when modal content is loaded in openMessageModal.
        }

        // Named function for message table row click listener (calls openMessageModal)
        function handleMessageRowClick(event) {
            const row = event.target.closest('tr');
            // Ensure a row was clicked and it has a message ID
            if (row && row.dataset.messageId) {
                const clickedElement = event.target;
                const isCheckbox = clickedElement.tagName === 'INPUT' && clickedElement.type === 'checkbox';
                const isLink = clickedElement.tagName === 'A'; // Check if a link was clicked

                // If not clicking checkbox or a link, open the detail modal
                if (!isCheckbox && !isLink) {
                     const messageId = row.dataset.messageId;
                     openMessageModal(messageId); // Call the global openMessageModal function
                }
            }
        }

        // Named function for toggle read status button click listener (called from openMessageModal)
        function handleToggleReadStatusClick() {
             // 'this' keyword refers to the button element because the listener is attached to the button.
             const messageId = this.dataset.messageId;
             if (messageId) {
                 fetch('message_actions.php', {
                     method: 'POST',
                     headers: {
                          'Content-Type': 'application/x-www-form-urlencoded',
                          'X-Requested-With': 'XMLHttpRequest' // ADD THIS HEADER
                     },
                     body: 'action=toggle_read_status&message_id=' + encodeURIComponent(messageId)
                 })
                 .then(response => {
                     if (!response.ok) {
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
                         const detailStatus = document.getElementById('detailStatus'); // Assuming this is inside the modal
                         if(detailStatus) detailStatus.innerText = data.new_status;

                         // Update the status in the message table row as well
                         const row = document.querySelector(`.message-table tbody tr[data-message-id="${messageId}"]`);
                         if (row) {
                             row.classList.remove('read', 'unread'); // Remove previous classes
                             row.classList.add(data.new_status === 'Read' ? 'read' : 'unread'); // Add new class
                             const statusCell = row.querySelector('td:nth-child(2)'); // Status is the 2nd cell
                             if (statusCell) statusCell.innerText = data.new_status;
                         }
                     } else {
                         console.error('Failed to toggle status:', data.message);
                         // Display an error message in the modal or elsewhere
                         const detailContentDiv = document.getElementById('messageDetailContent');
                          if (detailContentDiv) {
                              const statusMessageDiv = detailContentDiv.querySelector('.status-message');
                              if (statusMessageDiv) statusMessageDiv.remove(); // Remove previous error
                              detailContentDiv.insertAdjacentHTML('afterbegin', '<p class="status-message error">Error: ' + escapeHTML(data.message) + '</p>');
                          } else {
                             alert('Error toggling status: ' + data.message);
                          }
                     }
                 })
                 .catch(error => {
                     console.error('Error during status toggle AJAX:', error);
                      const detailContentDiv = document.getElementById('messageDetailContent');
                       if (detailContentDiv) {
                           const statusMessageDiv = detailContentDiv.querySelector('.status-message');
                           if (statusMessageDiv) statusMessageDiv.remove();
                           let errorMessage = 'An error occurred while toggling status.';
                           if (error.message.startsWith('HTTP error')) {
                                errorMessage += ` (${escapeHTML(error.message)})`;
                           }
                           detailContentDiv.insertAdjacentHTML('afterbegin', `<p class="status-message error">${errorMessage}</p>`);
                       } else {
                          alert('An error occurred while toggling status.');
                       }
                 });
             }
        }

        // Named function for select all checkbox click listener
        function handleSelectAllClick() {
            // 'this' keyword refers to the selectAllCheckbox element
            const checkboxes = document.querySelectorAll('#messagesForm input[type="checkbox"][name="message_ids[]"]');
            for (let checkbox of checkboxes) {
                checkbox.checked = this.checked;
            }
        }


         // Attach listeners for the manage jobs view after it's loaded (either initially or via AJAX)
         function attachManageJobsListeners() {
               // Attach listeners for the "Post New Job" modal button in manage_jobs_view.php
              const openPostJobModalBtn = document.getElementById('openPostJobModalBtn');
              const postJobModal = document.getElementById('postJobModal');

              if (openPostJobModalBtn && postJobModal) {
                  // Remove existing listener before adding
                  openPostJobModalBtn.removeEventListener('click', handleOpenPostJobModalClick); // Use named function
                  openPostJobModalBtn.addEventListener('click', handleOpenPostJobModalClick);
               }

              // Attach listeners for the modal close mechanisms if the modal is loaded
               if (postJobModal) {
                   const closePostJobModalBtn = postJobModal.querySelector('.close');
                   if (closePostJobModalBtn) {
                       // Remove existing listener before adding
                       closePostJobModalBtn.removeEventListener('click', closeCurrentModal); // Use global close function
                       closePostJobModalBtn.addEventListener('click', closeCurrentModal);
                   }
                   // The handleOutsideClick listener is already global and calls closeCurrentModal if currentModal matches
               }

             // If you had specific listeners for edit/delete links using AJAX in manage_jobs_view.php,
             // you would attach them here using event delegation on the job table body.
         }


        // Function to handle browser back/forward buttons (Popstate event)
        function handlePopstate(event) {
             console.log('Popstate event:', event.state);
             // Check if state has view information and if it's an internal dashboard state
             if (event.state && event.state.view) {
                 const view = event.state.view;
                 const id = event.state.id || null; // Get id if exists (for edit_job, etc.)
                 const username = event.state.username || null; // Get username if exists (for manage_users, edit_user)

                 let ajaxUrl = 'fetch_content.php?view=' + encodeURIComponent(view);
                 if (view === 'edit_job' && id) {
                      ajaxUrl += '&id=' + encodeURIComponent(id);
                 } else if ((view === 'manage_users' || view === 'edit_user') && username) {
                      ajaxUrl += '&username=' + encodeURIComponent(username);
                 }
                 // Add other parameters for other views if needed

                 // Add a loading indicator
                 if (mainContentDiv) {
                     mainContentDiv.innerHTML = '<p>Loading...</p>';
                 }

                 fetch(ajaxUrl)
                     .then(response => {
                         if (!response.ok) {
                              console.error('HTTP error', response.status);
                               // If unauthorized (e.g., 401, 403), redirect to login
                              if (response.status === 401 || response.status === 403) {
                                 window.location.href = 'dashboard.php'; // Redirect to handle login
                                 return Promise.reject('Unauthorized'); // Stop processing
                              }
                             return response.text(); // Get text to display error message
                         }
                         return response.text();
                     })
                     .then(html => {
                          // Before injecting new HTML, close any open modal from the previous view
                          closeCurrentModal();

                         if (mainContentDiv) {
                             mainContentDiv.innerHTML = html;
                         }
                         // Re-attach listeners/re-run scripts after popstate load, similar to initial load
                         executePostLoadScripts(view);
                         console.log('Content loaded via Popstate for view:', view);

                     })
                     .catch(error => {
                         console.error('Error fetching history state content:', error);
                         if (error !== 'Unauthorized' && mainContentDiv) { // Don't overwrite if unauthorized redirect happened
                            mainContentDiv.innerHTML = '<p class="status-message error">Failed to load content.</p>';
                         }
                     });
             }
             // If state is null or doesn't have a view, let the browser handle it (e.g., navigating away from dashboard)
         }

         // Function to execute scripts and attach listeners after content is loaded
         function executePostLoadScripts(view) {
             // Always attempt to re-execute scripts found within the loaded HTML first
              // This is crucial for scripts defined inside the view files (like in dashboard_view.php for Chart.js)
              // and ensures view-specific functions are defined before listeners are attached IF they are defined in the view.
              // Since we moved modal functions to footer, this is less critical for those but still important for others.
              if (mainContentDiv) { // Ensure mainContentDiv exists
                  reExecuteScripts(mainContentDiv);
              }

             // Re-attach listeners for views that have dynamic elements or scripts
             // This should happen *after* reExecuteScripts runs the view's own script blocks to ensure elements/functions exist.
             if (view === 'messages') {
                 attachMessageTableListeners(); // Handles message table row clicks, toggle button, select all, message detail modal
             } else if (view === 'manage_jobs') {
                  attachManageJobsListeners(); // Handles post job modal button and other manage jobs specific listeners
             } else if (view === 'dashboard') {
                  // Chart.js script is handled by reExecuteScripts. No separate attach needed unless other dashboard JS exists.
             } else if (view === 'manage_users') {
                 // Re-attach manage users listeners if needed (currently none specified)
             }
             // Add cases for other views if they have specific JS or need listener re-attachment

         }

         // Helper function to re-execute script tags within a given element
         // This is necessary because scripts inserted via innerHTML do not execute automatically
         // This version attempts to remove inline scripts after execution to prevent duplicates.
         function reExecuteScripts(element) {
             const scripts = element.querySelectorAll('script');
             scripts.forEach(script => {
                 try {
                     // Create a new script element
                     const newScript = document.createElement('script');

                     // Copy attributes (like src for external scripts)
                     script.getAttributeNames().forEach(attrName => {
                         newScript.setAttribute(attrName, script.getAttribute(attrName));
                     });

                     // Copy text content (for inline scripts)
                     if (script.textContent) {
                         newScript.textContent = script.textContent;
                     } else if (script.src) {
                         // For external scripts, just setting src is enough; the browser fetches and executes
                         // We don't need to do anything extra here if src is set, just append it.
                     }

                     // Append the new script to the body to execute it
                     // This is a common technique to force re-execution of dynamically added scripts.
                     document.body.appendChild(newScript);

                     // Optional: Remove the script element immediately after execution if it's an inline script
                     // Use a small delay before removing to ensure execution in some browsers
                      if (!script.src) { // Only remove inline scripts
                           setTimeout(() => {
                               if (newScript.parentNode) { // Check if it hasn't been removed already
                                    document.body.removeChild(newScript);
                               }
                           }, 0); // 0ms delay puts it at the end of the current event loop
                      }
                     console.log('Script re-executed:', script.src || 'inline script');

                 } catch (e) {
                     console.error('Error re-executing script:', script.src || 'inline script', e);
                 }
             });
         }


        // --- Function to handle AJAX navigation link clicks ---
        function handleNavLinkClick(e) {
            const href = this.getAttribute('href'); // Use 'this' as the link element
            // Only intercept internal dashboard links starting with dashboard.php?view=
            if (href && href.startsWith('dashboard.php?view=')) {
                e.preventDefault(); // Prevent full page reload

                // Construct the URL for the AJAX request
                const url = new URL(href, window.location.origin); // Use URL object for easier parameter handling
                const view = url.searchParams.get('view');
                const id = url.searchParams.get('id'); // Get id if exists (for edit_job)
                 const username = url.searchParams.get('username'); // Get username if exists (for manage_users, edit_user)

                let ajaxUrl = 'fetch_content.php?view=' + encodeURIComponent(view);
                if (view === 'edit_job' && id) {
                      ajaxUrl += '&id=' + encodeURIComponent(id);
                 } else if ((view === 'manage_users' || view === 'edit_user') && username) {
                      ajaxUrl += '&username=' + encodeURIComponent(username);
                 }
                 // Add other parameters for other views if needed


                // Add a loading indicator
                if (mainContentDiv) {
                    mainContentDiv.innerHTML = '<p>Loading...</p>'; // Simple loading message
                }


                fetch(ajaxUrl)
                    .then(response => {
                        // Check for unauthorized response - fetch_content.php returns error P tag
                        if (!response.ok) {
                            // Handle HTTP errors if any
                            console.error('HTTP error', response.status);
                            // If unauthorized (e.g., 401, 403), redirect to login
                            if (response.status === 401 || response.status === 403) {
                                window.location.href = 'dashboard.php'; // Redirect to handle login
                                return Promise.reject('Unauthorized'); // Stop processing
                            }
                             return response.text(); // Get text to display error message
                         }
                        return response.text();
                    })
                    .then(html => {
                         // Before injecting new HTML, close any open modal from the previous view
                         closeCurrentModal();

                        if (mainContentDiv) {
                            mainContentDiv.innerHTML = html;
                        }

                        // Update browser history (optional, but good for back/forward buttons)
                        history.pushState({ view: view, id: id, username: username }, '', href);

                         // --- Post-Load Script Execution and Event Listener Attachment ---
                         // After loading new content, check the view and attach specific listeners/run scripts
                         executePostLoadScripts(view);
                         console.log('Content loaded via NavLink for view:', view);

                    })
                    .catch(error => {
                        console.error('Error fetching content:', error);
                        if (error !== 'Unauthorized' && mainContentDiv) { // Don't overwrite if unauthorized redirect happened
                           mainContentDiv.innerHTML = '<p class="status-message error">Failed to load content.</p>';
                        }
                    });
            }
            // If the link is not a dashboard view link (e.g., auth.php?action=logout), let the default behavior happen (full page reload)
        }


        // Initial fetch of content on page load
        // This script runs when footer.php is included by dashboard.php, which happens only if logged in.
        // The dashboard.php already determined the initial view ($currentViewForJS).
        // We need to trigger the AJAX fetch for this initial view when the DOM is ready.
        document.addEventListener('DOMContentLoaded', () => {
             if (isLoggedIn && mainContentDiv) {
                 const initialViewFromPHP = '<?= $currentViewForJS ?>';

                 // Get potential ID or username from the initial URL
                 const urlParams = new URLSearchParams(window.location.search);
                 const initialJobIdFromUrl = urlParams.get('id');
                 const initialUsernameFromUrl = urlParams.get('username');

                 let initialFetchUrl = `Workspace_content.php?view=${encodeURIComponent(initialViewFromPHP)}`;
                 if (initialViewFromPHP === 'edit_job' && initialJobIdFromUrl) {
                      initialFetchUrl += `&id=${encodeURIComponent(initialJobIdFromUrl)}`;
                 } else if ((initialViewFromPHP === 'manage_users' || initialViewFromPHP === 'edit_user') && initialUsernameFromUrl) {
                      initialFetchUrl += `&username=${encodeURIComponent(initialUsernameFromUrl)}`;
                 }


                 // Add a loading indicator for the initial load
                 mainContentDiv.innerHTML = '<p>Loading...</p>';


                 fetch(initialFetchUrl)
                     .then(response => {
                          if (!response.ok) {
                              console.error('HTTP error', response.status);
                              // If unauthorized (e.g., 401, 403), redirect to login
                              if (response.status === 401 || response.status === 403) {
                                 window.location.href = 'dashboard.php';
                                 return Promise.reject('Unauthorized');
                              }
                              // Read response text for error details if not OK
                              return response.text().then(text => {
                                  throw new Error(`HTTP error ${response.status}: ${text}`);
                              });
                          }
                          return response.text();
                      })
                     .then(html => {
                         // Before injecting new HTML, close any open modal from previous state if needed
                         closeCurrentModal();

                         if (mainContentDiv) {
                             mainContentDiv.innerHTML = html;
                         }

                         // Attach listeners and run scripts after initial content is loaded
                         executePostLoadScripts(initialViewFromPHP);

                         // Add initial state to history for back/forward navigation
                         history.replaceState({ view: initialViewFromPHP, id: initialJobIdFromUrl, username: initialUsernameFromUrl }, '', window.location.href);

                         // Attach AJAX navigation listeners *after* initial content is loaded and history is set
                         document.querySelectorAll('.admin-nav a').forEach(link => {
                              // Remove existing listeners if any (important for robustness)
                             link.removeEventListener('click', handleNavLinkClick); // Ensure no duplicate listeners
                             // Add the new click event listener
                             link.addEventListener('click', handleNavLinkClick);
                         });
                         console.log('Initial content loaded for view:', initialViewFromPHP);

                     })
                     .catch(error => {
                         console.error('Error fetching initial content:', error);
                          if (error !== 'Unauthorized' && mainContentDiv) {
                              let errorMessage = 'Failed to load initial content.';
                               if (error.message.startsWith('HTTP error')) {
                                    errorMessage += ` (${escapeHTML(error.message)})`;
                               }
                              mainContentDiv.innerHTML = `<p class="status-message error">${errorMessage}</p>`;
                          }
                     });

                 // Add popstate listener for back/forward buttons
                 window.removeEventListener('popstate', handlePopstate); // Remove to prevent duplicates
                 window.addEventListener('popstate', handlePopstate);

             }
        }); // Close DOMContentLoaded listener


    </script>

</body>
</html>