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
<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <h3 style="margin: 0;">Manage Jobs</h3>
    <div style="display: flex; gap: 10px; align-items: center;">
        <form method="GET" action="dashboard.php" class="search-filter-form">
            <input type="hidden" name="view" value="manage_jobs">
            <input type="text" name="search" placeholder="Search jobs" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" class="search-input">
            <input type="date" name="start_date" value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>" class="filter-input">
            <input type="date" name="end_date" value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>" class="filter-input">
            <button type="submit" class="button filter-button">Filter</button>
        </form>
        <button id="postNewJobBtn" class="button post-job-btn">
            + Post New Job
        </button>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Title</th>
            <th>Company</th>
            <th>Location</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($pagedJobs)): ?>
            <?php foreach ($pagedJobs as $job): ?>
                <tr>
                    <td><?= htmlspecialchars($job['title'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($job['company'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($job['location'] ?? 'N/A') ?></td>
                    <td>
                        <a href="dashboard.php?view=edit_job&id=<?= urlencode($job['id']) ?>">Edit</a>
                        <a href="job_actions.php?action=delete_job&id=<?= urlencode($job['id']) ?>" onclick="return confirm('Are you sure?')">Delete</a>
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
        <a href="dashboard.php?view=manage_jobs&page=<?= $currentPage - 1 ?>">&laquo; Previous</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="dashboard.php?view=manage_jobs&page=<?= $i ?>" class="<?= $i === $currentPage ? 'active' : '' ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>

    <?php if ($currentPage < $totalPages): ?>
        <a href="dashboard.php?view=manage_jobs&page=<?= $currentPage + 1 ?>">Next &raquo;</a>
    <?php endif; ?>
</div>

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
    /* Enhance the table appearance */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        font-size: 0.9rem;
        color: #333;
    }

    table thead {
        background-color: #f8f9fa;
        text-align: left;
    }

    table thead th {
        padding: 12px 15px;
        border-bottom: 2px solid #dee2e6;
        font-weight: bold;
    }

    table tbody tr {
        border-bottom: 1px solid #dee2e6;
    }

    table tbody tr:nth-child(even) {
        background-color: #f9f9f9; /* Alternating row colors */
    }

    table tbody tr:hover {
        background-color: #f1f1f1; /* Highlight row on hover */
    }

    table tbody td {
        padding: 10px 15px;
    }

    /* Style the action links */
    table tbody td a {
        color: #007bff;
        text-decoration: none;
        margin-right: 10px;
    }

    table tbody td a:hover {
        text-decoration: underline;
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

    .button {
        background-color: #007bff;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 1rem;
    }

    .button:hover {
        background-color: #0056b3;
    }

    /* Search and Filter Form */
    .search-filter-form {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .search-input, .filter-input {
        padding: 6px 8px; /* Smaller padding */
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 0.85rem; /* Smaller font size */
        width: 120px; /* Small width */
    }

    .filter-button {
        background-color: #007bff;
        color: white;
        padding: 6px 12px; /* Smaller padding */
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.85rem; /* Smaller font size */
        transition: background-color 0.3s ease;
    }

    .filter-button:hover {
        background-color: #0056b3;
    }

    /* Post New Job Button */
    .post-job-btn {
        background-color: #28a745; /* Professional green color */
        color: white;
        padding: 8px 15px; /* Smaller padding */
        border: none;
        border-radius: 5px; /* Rounded corners */
        cursor: pointer;
        font-size: 0.85rem; /* Smaller font size */
        font-weight: bold;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Subtle shadow for depth */
        transition: all 0.3s ease; /* Smooth transition for hover effects */
    }

    .post-job-btn:hover {
        background-color: #218838; /* Darker green on hover */
        box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15); /* Slightly larger shadow */
        transform: translateY(-2px); /* Lift the button slightly */
    }

    .post-job-btn:active {
        background-color: #1e7e34; /* Even darker green when clicked */
        box-shadow: 0 3px 5px rgba(0, 0, 0, 0.2); /* Smaller shadow */
        transform: translateY(1px); /* Push the button down slightly */
    }

    /* Responsive Design for Search and Filter Section */
    @media (max-width: 768px) {
        .search-filter-form {
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
            gap: 10px;
        }

        .search-input, .filter-input, .filter-button, .post-job-btn {
            width: 100%; /* Full width for smaller screens */
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
            postJobModal.style.display = 'flex';

            // Fetch the job post form content via AJAX
            fetch('fetch_content.php?view=post_job', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.text())
            .then(html => {
                postJobFormContainer.innerHTML = html;
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
    document.getElementById('postNewJobBtn').addEventListener('click', openPostJobModal);
    document.getElementById('closePostJobModal').addEventListener('click', closePostJobModal);
</script>