<?php

// admin/views/manage_jobs_view.php - Displays the table of all jobs and includes the Post New Job modal

// This file is included by dashboard.php or fetch_content.php when $requestedView is 'manage_jobs'.
// It assumes $allJobs is available.

// Ensure $allJobs is an array, even if loadJobs failed
$allJobs = $allJobs ?? [];

// Get search and filter inputs
$search = $_GET['search'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// Filter jobs based on search and date range
$filteredJobs = array_filter($allJobs, function ($job) use ($search, $startDate, $endDate) {
    $matchesSearch = empty($search) || stripos($job['title'] ?? '', $search) !== false || stripos($job['company'] ?? '', $search) !== false || stripos($job['location'] ?? '', $search) !== false;

    $matchesDate = true;
    if (!empty($startDate)) {
        $matchesDate = $matchesDate && (strtotime($job['posted_at'] ?? $job['posted_on'] ?? '') >= strtotime($startDate));
    }
    if (!empty($endDate)) {
        $matchesDate = $matchesDate && (strtotime($job['posted_at'] ?? $job['posted_on'] ?? '') <= strtotime($endDate));
    }

    return $matchesSearch && $matchesDate;
});

// Sort filtered jobs by posted_at or posted_on_unix_ts in descending order
usort($filteredJobs, function ($a, $b) {
    return strtotime($b['posted_at'] ?? $b['posted_on'] ?? 0) - strtotime($a['posted_at'] ?? $a['posted_on'] ?? 0);
});

// Pagination logic
$jobsPerPage = 20;
$totalJobs = count($filteredJobs);
$totalPages = ceil($totalJobs / $jobsPerPage);
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Slice the filtered and sorted jobs array for the current page
$startIndex = ($currentPage - 1) * $jobsPerPage;
$pagedJobs = array_slice($filteredJobs, $startIndex, $jobsPerPage);

?>
<header class="manage-jobs-header">
    <h2 class="view-main-title manage-jobs-title">Manage Jobs</h2>
    <div class="manage-jobs-header-actions">
        <form method="GET" action="dashboard.php" class="search-filter-form">
            <input type="hidden" name="view" value="manage_jobs">
            <input type="text" name="search" placeholder="Search jobs..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" class="search-input form-input-control" >
            <input type="date" name="start_date" value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>" class="filter-input form-input-control" aria-label="Start Date">
            <input type="date" name="end_date" value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>" class="filter-input form-input-control" aria-label="End Date">
            <button type="submit" class="button button-secondary filter-button">Filter</button>
        </form>
        <!-- <button id="postNewJobBtn" class="button post-job-btn">
            + Post New Job
        </button> -->
        <a href="dashboard.php?view=post_job" class="button post-new-job-button">+ Post New Job</a>
    </div>
</header>

<table>
    <thead>
        <tr>
            <th>Title</th>
            <th class="optional-column">Company</th>
            <th>Location</th>
            <th class="optional-column">Posted On</th>
            <th class="actions-column">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($pagedJobs)): ?>
            <?php foreach ($pagedJobs as $job): ?>
                <tr>
                    <td><?= htmlspecialchars($job['title'] ?? 'N/A') ?></td>
                    <td class="optional-column"><?= htmlspecialchars($job['company'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($job['location'] ?? 'N/A') ?></td>
                    <td class="optional-column">
                        <?= htmlspecialchars(date('M d, Y', strtotime($job['posted_at'] ?? $job['posted_on'] ?? 'now'))) ?>
                    </td>
                    <td class="actions-column">
                        <a href="dashboard.php?view=edit_job&id=<?= urlencode($job['id']) ?>" class="button button-small button-edit">Edit</a>
                        <a href="job_actions.php?action=delete_job&id=<?= urlencode($job['id']) ?>" class="button button-small button-danger" onclick="return confirm('Are you sure you want to delete this job? This action cannot be undone.')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="4" style="text-align: center;">No jobs found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<!-- Pagination Controls -->
<div class="pagination">
    <?php if ($currentPage > 1): ?>
        <a href="dashboard.php?view=manage_jobs&page=<?= $currentPage - 1 ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>">&laquo; Previous</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="dashboard.php?view=manage_jobs&page=<?= $i ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="<?= $i === $currentPage ? 'active' : '' ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>

    <?php if ($currentPage < $totalPages): ?>
        <a href="dashboard.php?view=manage_jobs&page=<?= $currentPage + 1 ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>">Next &raquo;</a>
    <?php endif; ?>
</div>

<div id="postJobModal" class="modal">
    <div class="modal-content">
        <span class="close" id="closePostJobModal">&times;</span>
        <h3 class="modal-title">Post New Job</h3>
        <div id="postJobFormContainer" class="modal-body-content">
            <p>Loading form...</p>
        </div>
    </div>
</div>

<style>
    .view-main-title.manage-jobs-title { /* Specific for this view's title */
        margin-top: 0;
        margin-bottom: 0; /* Header will handle bottom margin */
        color: var(--primary-color);
        font-size: 1.75em;
        font-weight: 600;
        padding-bottom: 0; /* No border/padding here, part of header */
        border-bottom: none;
    }
    /* Styles for the Manage Jobs header section */
    .manage-jobs-header {
        margin-bottom: 25px; /* Consistent bottom margin */
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap; /* Allow wrapping for responsiveness */
        gap: 15px; /* Add some gap for wrapped items */
        padding-bottom: 15px; /* Space below header content */
        border-bottom: 1px solid var(--border-color); /* Separator line */
    }

    .manage-jobs-header-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap; /* Allow actions to wrap as well */
    }
    /* Table styles - aiming for .professional-table look from header.php */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        font-size: 0.9rem;
        color: var(--text-color-light); /* Use theme variable */
        background-color: var(--card-bg); /* Use theme variable */
        border: 1px solid var(--border-color); /* Use theme variable */
        border-radius: var(--border-radius); /* Use theme variable */
        box-shadow: var(--box-shadow-sm); /* Use theme variable */
    }

    table thead {
        background-color: var(--body-bg); /* Use theme variable */
        text-align: left;
    }

    table thead th {
        padding: 12px 15px;
        border-bottom: 2px solid var(--border-color); /* Use theme variable */
        font-weight: 600;
        color: var(--text-color); /* Use theme variable */
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    table tbody tr {
        border-bottom: 1px solid var(--border-color); /* Use theme variable */
    }
    table tbody tr:last-child {
        border-bottom: none;
    }

    /* table tbody tr:nth-child(even) { background-color: #f9f9f9; } */ /* Optional: remove for cleaner look if card has bg */

    table tbody tr:hover {
        background-color: #f5f7f8; /* Use theme variable or a very light hover */
    }

    table tbody td {
        padding: 10px 15px;
        vertical-align: middle;
    }

    /* Style the action links */
    table tbody td a.button-small { /* Target specific action buttons */
        /* color: var(--primary-color); Use button styles */
        text-decoration: none;
        margin-right: 10px;
        padding: .375rem .75rem; /* Smaller padding for action buttons */
        font-size: 0.85rem;
    }
    table tbody td a.button-edit { background-color: var(--secondary-color); border-color: var(--secondary-color); }
    table tbody td a.button-edit:hover { background-color: var(--secondary-color-darker); border-color: var(--secondary-color-darker); }

    .actions-column {
        text-align: right; /* Align actions to the right */
        white-space: nowrap;
    }

    /* Pagination styles */
    .pagination {
        margin-top: 20px;
        text-align: center;
    }

    .pagination a {
        display: inline-block;
        margin: 0 5px;
        padding: 8px 12px;
        text-decoration: none;
        color: var(--primary-color); /* Use theme variable */
        border: 1px solid var(--border-color); /* Use theme variable */
        border-radius: var(--border-radius); /* Use theme variable */
    }

    .pagination a.active {
        background-color: var(--primary-color); /* Use theme variable */
        color: #fff;
        border-color: var(--primary-color); /* Use theme variable */
    }

    .pagination a:hover {
        background-color: var(--primary-color-darker); /* Use theme variable */
        color: #fff;
    }

    /* Basic Modal Styles (copy these to header.php or your main CSS if not already there) */
    .modal {
        position: fixed;
        z-index: 1000; /* High z-index to ensure it's on top */
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.4); /* Default to hidden, JS will show it */
        display: none; /* Use flexbox for centering when shown by JS */
        align-items: center; /* Center vertically */
        justify-content: center; /* Center horizontally */
        padding: 20px;
        box-sizing: border-box;
    }

    .modal-content {
        background-color: var(--card-bg); /* Use theme variable */
        margin: auto;
        padding: 20px;
        border: 1px solid var(--border-color); /* Use theme variable */
        width: 90%;
        max-width: 600px;
        border-radius: var(--border-radius); /* Use theme variable */
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        position: relative;
        display: flex;
        flex-direction: column;
        max-height: 95vh;
        overflow-y: auto; /* Add scroll to modal content */
    }

    .modal-title { /* For h3 inside modal */
        margin-top: 0;
        color: var(--primary-color-darker); /* Use theme variable */
        border-bottom: 1px solid var(--border-color); /* Use theme variable */
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
    /* These styles target elements inside #postJobFormContainer */
    #postJobFormContainer label {
        display: block;
        margin-bottom: .5rem;
        font-weight: 500; color: var(--text-color-light); /* Match global form label */
    }

    #postJobFormContainer input[type="text"],
    #postJobFormContainer input[type="email"],
    #postJobFormContainer input[type="number"],
    #postJobFormContainer input[type="password"], /* Added for completeness */
    #postJobFormContainer textarea,
    #postJobFormContainer select { /* Added for completeness */
        /* Fully align with global form input styles from header.php */
        display: block;
        width: 100%;
        padding: .5rem .75rem;
        font-size: 0.95rem;
        font-weight: 400; /* Match global */
        line-height: 1.5; /* Match global */
        color: var(--text-color); /* Match global */
        background-color: #fff; /* Match global */
        background-clip: padding-box; /* Match global */
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        appearance: none; /* Match global */
        transition: border-color .2s ease-in-out, box-shadow .2s ease-in-out; /* Match global */
        margin-bottom: 1rem;
    }
    #postJobFormContainer input:focus,
    #postJobFormContainer textarea:focus,
    #postJobFormContainer select:focus { /* Added focus styles */
        border-color: var(--primary-color-lighter);
        outline: 0;
        box-shadow: 0 0 0 .2rem var(--primary-color-lighter);
    }
    #postJobFormContainer button[type="submit"] { /* Match global .button style */
        display: inline-block;
        font-weight: 500;
        color: white;
        text-align: center;
        background-color: var(--primary-color);
        border: 1px solid var(--primary-color);
        padding: .5rem 1rem;
        font-size: 0.9rem;
        line-height: 1.5;
        border-radius: var(--border-radius);
        text-decoration: none;
        cursor: pointer;
        margin-top: 10px;
    }
    #postJobFormContainer button[type="submit"]:hover {
        background-color: var(--primary-color-darker);
        border-color: var(--primary-color-darker);
    }

    /* .button is styled globally in header.php */

    /* Search and Filter Form */
    .search-filter-form {
        display: flex;
        gap: 10px; /* Increased gap */
        align-items: center;
    }

    .form-input-control { /* Class for search/filter inputs to match global form style */
        padding: .5rem .75rem;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        font-size: 0.9rem; /* Consistent font size */
    }
    .search-input { width: 180px; } /* Specific width for search */
    .filter-input { width: 130px; } /* Specific width for date filters */

    .filter-button { /* Match global .button.button-secondary */
        /* padding: 6px 12px; font-size: 0.85rem; */ /* Already styled by .button */
    }

    /* Post New Job Button */
    .post-new-job-button { /* Match global .button style, potentially with a success color */
        /* background-color: var(--success-color); 
           border-color: var(--success-color); */
        /* Default .button style is primary, which is fine too */
        font-weight: 500; /* Ensure consistent weight */
    }
    .post-new-job-button:hover {
        /* background-color: var(--success-color-darker); 
           border-color: var(--success-color-darker); */
    }

    /* Responsive Design for Search and Filter Section */
    @media (max-width: 992px) { /* Adjust breakpoint if needed */
        .manage-jobs-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .search-filter-form {
            flex-wrap: wrap; 
            width: 100%; /* Take full width on smaller screens */
        }
        .search-input, .filter-input, .filter-button, .post-new-job-button {
            width: calc(50% - 5px); /* Two items per row approx */
            margin-bottom: 10px;
        }
        .search-input { width: 100%; } /* Search full width */
    }
    @media (max-width: 768px) {
        .search-input, .filter-input, .filter-button, .post-new-job-button {
            width: 100%; /* Full width for smaller screens */
        }
        .optional-column {
            display: none; /* Hide less critical columns on small screens */
        }
    }
</style>

<script>
    // Function to open the Post New Job modal
    function openPostJobModal() {
        const postJobModal = document.getElementById('postJobModal');
        const postJobFormContainer = document.getElementById('postJobFormContainer');

        if (postJobModal && postJobFormContainer) {
            postJobFormContainer.innerHTML = '<p>Loading form...</p>';
            postJobModal.style.display = 'flex'; // JS will change display to flex when opening

            // Fetch the job post form content via AJAX
            fetch('fetch_content.php?view=post_job', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.text())
            .then(html => {
                postJobFormContainer.innerHTML = html;
                // If the loaded form has its own JS, it might need re-execution or specific handling
            })
            .catch(error => {
                console.error('Error loading job post form:', error);
                postJobFormContainer.innerHTML = '<p class="status-message error">Error loading form.</p>';
            });
        }
    }

    // Function to close the Post New Job modal
    function closePostJobModal() {
        const postJobModal = document.getElementById('postJobModal');
        if (postJobModal) {
            postJobModal.style.display = 'none';
        }
    }

    // Add event listeners for the button and modal close
    // The button with id="postNewJobBtn" was removed in favor of a direct link.
    // If you reinstate a button with that ID, uncomment the next line.
    // document.getElementById('postNewJobBtn').addEventListener('click', openPostJobModal); 
    document.getElementById('closePostJobModal').addEventListener('click', closePostJobModal);

    // Close modal if clicked outside of modal-content
    window.addEventListener('click', function(event) {
        const postJobModal = document.getElementById('postJobModal');
        if (event.target == postJobModal) {
            closePostJobModal();
        }
    });
</script>