<?php

// admin/views/manage_jobs_view.php - Displays the table of all jobs and includes the Post New Job modal

// This file is included by dashboard.php or fetch_content.php when $requestedView is 'manage_jobs'.
// It assumes $allJobs is available.

// Ensure $allJobs is an array, even if loadJobs failed
$allJobs = $allJobs ?? [];

?>
<h3>Manage All Jobs</h3>

<div style="margin-bottom: 15px;">
    <button type="button" class="button" id="postNewJobBtn">Post New Job</button>
</div>


<?php if (empty($allJobs)): ?>
    <p class="no-jobs">No jobs found in the data file.</p>
<?php else: ?>
    <form method="POST" action="job_actions.php" id="manageJobsForm">
        <input type="hidden" name="action" value="">
        <?php /*
        <div style="margin-bottom: 15px;">
            <button type="button" class="button" onclick="setJobFormAction('delete_selected_jobs'); return confirm('Are you sure you want to delete selected jobs?');">Delete Selected</button>
        </div>
        */ ?>
        <table class="job-table">
            <thead>
                <tr>
                    <?php /* <th><input type="checkbox" id="selectAllJobs"></th> */ // Add if implementing bulk actions ?>
                    <th>Title</th>
                    <th>Company</th>
                    <th>Location</th>
                    <th>Posted On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allJobs as $job): ?>
                    <tr>
                        <?php /* <td><input type="checkbox" name="job_ids[]" value="<?= htmlspecialchars($job['id'] ?? '') ?>"></td> */ // Add if implementing bulk actions ?>
                        <td><?= htmlspecialchars($job['title'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($job['company'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($job['location'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($job['posted_on'] ?? 'N/A') ?></td>
                        <td class="actions">
                            <a href="dashboard.php?view=edit_job&id=<?= urlencode($job['id'] ?? '') ?>" class="button edit">Edit</a>
                            <?php if (!empty($job['id'])): // Ensure ID exists before creating delete link ?>
                            <a href="job_actions.php?action=delete_job&id=<?= urlencode($job['id']) ?>" onclick="return confirm('Are you sure you want to delete this job: <?= addslashes(htmlspecialchars($job['title'] ?? '')) ?>?');"
                               class="button delete">Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>
<?php endif; ?>

<div id="postJobModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" id="closePostJobModal">&times;</span>
        <h3>Post New Job</h3>
        <div id="postJobFormContainer" class="modal-body-content">
            <p>Loading form...</p>
        </div>
    </div>
</div>

<style>
    /* Basic Modal Styles (copy these to header.php or your main CSS if not already there) */
    .modal {
        position: fixed;
        z-index: 1000; /* High z-index to ensure it's on top */
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.4);
        display: flex; /* Use flexbox for centering */
        align-items: center; /* Center vertically */
        justify-content: center; /* Center horizontally */
        padding: 20px;
        box-sizing: border-box;
    }

    .modal-content {
        background-color: #fefefe;
        margin: auto;
        padding: 20px;
        border: 1px solid #888;
        width: 90%;
        max-width: 600px;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        position: relative;
        display: flex;
        flex-direction: column;
        max-height: 95vh;
        overflow-y: auto; /* Add scroll to modal content */
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
        position: absolute;
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

    .modal-body-content {
        flex-grow: 1;
        overflow-y: auto;
    }


    /* Styles for elements *within* the loaded form */
    /* These styles target elements inside the container where the form is loaded */
    #postJobFormContainer label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }

    #postJobFormContainer input[type="text"],
    #postJobFormContainer input[type="email"],
    #postJobFormContainer input[type="number"],
    #postJobFormContainer textarea {
        width: calc(100% - 18px); /* Adjust for padding and border */
        padding: 8px;
        margin-bottom: 15px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    #postJobFormContainer button[type="submit"] {
        background-color: #007bff;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 1em;
        margin-top: 10px; /* Add some space above the submit button */
    }

    #postJobFormContainer button[type="submit"]:hover {
        background-color: #0056b3;
    }

    /* Add styles for any status messages within the form if job_actions returns HTML with messages */
    /* Or rely on the main page status area if job_actions redirects */
     .status-message { /* Reuse status message styles if defined globally */
         padding: 10px;
         margin-bottom: 10px;
         border-radius: 4px;
         font-weight: bold;
     }

     .status-message.success {
         background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;
     }
     .status-message.error {
         background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;
     }


</style>

<script>
    // Get references to the button and modal elements
    const postNewJobBtn = document.getElementById('postNewJobBtn');
    const postJobModal = document.getElementById('postJobModal');
    const closePostJobModalBtn = document.getElementById('closePostJobModal');
    const postJobFormContainer = document.getElementById('postJobFormContainer');

    // Function to open the Post New Job modal and load the form
    function openPostJobModal() {
        if (postJobModal && postJobFormContainer) {
            // Clear previous content and show loading message
            postJobFormContainer.innerHTML = '<p>Loading form...</p>';
            postJobModal.style.display = 'flex'; // Show the modal using flex for centering

            // Fetch the job post form content via AJAX
            fetch('fetch_content.php?view=post_job', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest' // Indicate it's an AJAX request
                }
            })
            .then(response => {
                if (!response.ok) {
                     // Check for unauthorized response (401/403) and redirect
                     if (response.status === 401 || response.status === 403) {
                          console.error('Unauthorized: Redirecting to login.');
                          window.location.href = 'dashboard.php'; // Redirect to handle login
                          return Promise.reject('Unauthorized'); // Stop processing
                     }
                     // Otherwise, throw an error with response text
                     return response.text().then(text => {
                          throw new Error(`HTTP error ${response.status}: ${text}`);
                      });
                }
                return response.text(); // Get the HTML content as text
            })
            .then(html => {
                // Insert the fetched HTML (the form) into the container
                if (postJobFormContainer) {
                    postJobFormContainer.innerHTML = html;

                    // Note: Scripts *within* the loaded HTML won't execute automatically by default.
                    // If post_job_view.php had inline scripts needed for the form (e.g., validation),
                    // they would need to be handled here by extracting and executing them.
                    // Based on your post_job_view.php, it seems to be just the form HTML.

                    // If the form itself needs event listeners attached *after* loading,
                    // they would need to be attached here. E.g.,
                    // const jobPostForm = postJobFormContainer.querySelector('form');
                    // if (jobPostForm) {
                    //     jobPostForm.addEventListener('submit', handleJobPostSubmit); // Assuming handleJobPostSubmit is defined
                    // }
                }
            })
            .catch(error => {
                console.error('Error loading job post form:', error);
                if (postJobFormContainer) {
                    // Display error message inside the form container
                    // Use escapeHTML for safety when inserting user/error messages into innerHTML
                    postJobFormContainer.innerHTML = '<p class="status-message error">Error loading form: ' + escapeHTML(error.message || 'Unknown error') + '</p>';
                }
                 // No need for alert here, error is shown in modal
            });
        } else {
            console.error('Post Job Modal or Form Container element not found.');
        }
    }

    // Function to close the Post New Job modal
    function closePostJobModal() {
        if (postJobModal) {
            postJobModal.style.display = 'none';
            // Optional: Clear the form content when closing
             if (postJobFormContainer) {
                  postJobFormContainer.innerHTML = ''; // Clear content
             }
        }
    }

    // Add click listener to the Post New Job button
    // Use dataset to track if listener was already added to prevent duplicates with reExecuteScripts
    if (postNewJobBtn) {
        if (!postNewJobBtn.dataset.listenerAdded) {
            postNewJobBtn.addEventListener('click', openPostJobModal);
            postNewJobBtn.dataset.listenerAdded = 'true'; // Mark as added
            console.log('PostNewJobBtn listener added.'); // For debugging
        } else {
             console.log('PostNewJobBtn listener already added.'); // For debugging
        }
    }

    // Add click listener to the modal close button
     if (closePostJobModalBtn) {
          // Use dataset to track if listener was already added
          if (!closePostJobModalBtn.dataset.listenerAdded) {
              closePostJobModalBtn.addEventListener('click', closePostJobModal);
               closePostJobModalBtn.dataset.listenerAdded = 'true'; // Mark as added
               console.log('PostJobModal close listener added.'); // For debugging
           } else {
               console.log('PostJobModal close listener already added.'); // For debugging
           }
     }

     // Add click listener to close modal if clicking outside modal content
     if (postJobModal) {
          // Use dataset to track if listener is already added
          if (!postJobModal.dataset.listenerAdded) {
               postJobModal.addEventListener('click', function(event) {
                   // If the click target is the modal backdrop itself (not the modal-content or its children)
                   if (event.target === postJobModal) {
                       closePostJobModal();
                       console.log('Modal backdrop clicked, closed.'); // For debugging
                   }
               });
               postJobModal.dataset.listenerAdded = 'true'; // Mark as added
                console.log('PostJobModal backdrop listener added.'); // For debugging
          } else {
               console.log('PostJobModal backdrop listener already added.'); // For debugging
          }
     }


    // Helper function to escape HTML for safe display in innerHTML (copy from footer.php if needed)
    // Define this function only if it doesn't already exist globally (e.g., in footer.php)
    // This helps prevent re-declaration errors with reExecuteScripts.
    if (typeof escapeHTML === 'undefined') {
        window.escapeHTML = function(str) {
             if (typeof str !== 'string') return str;
             const div = document.createElement('div');
             div.appendChild(document.createTextNode(str));
             return div.innerHTML;
        }
         console.log('escapeHTML function defined in manage_jobs_view.php script.'); // For debugging
    } else {
         console.log('escapeHTML function already exists globally.'); // For debugging
    }


    // Note: The job post form inside the modal will submit to job_actions.php.
    // job_actions.php will process the form and redirect back to dashboard.php.
    // The loadContent function in footer.php should handle this redirect and reload the manage_jobs view.
    // The status message from the session will be displayed at the top of the main page (using the status-area div).
    // If you need client-side validation or AJAX submission for the form in the modal,
    // additional JavaScript would be required here to intercept the form submit event.

</script>