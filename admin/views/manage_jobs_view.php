<?php

// admin/views/manage_jobs_view.php - Displays the table of all jobs

// This file is included by dashboard.php when $requestedView is 'manage_jobs'.
// It assumes $allJobs is available.

?>
<h3>Manage All Jobs</h3>
<?php if (empty($allJobs)): ?>
    <p class="no-jobs">No jobs found in the data file.</p>
<?php else: ?>
    <table class="job-table">
        <thead>
            <tr>
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
                    <td><?= htmlspecialchars($job['title'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($job['company'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($job['location'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($job['posted_on'] ?? 'N/A') ?></td>
                    <td class="actions">
                        <a href="dashboard.php?view=edit_job&id=<?= urlencode($job['id'] ?? '') ?>" class="edit">Edit</a>
                        <?php if (!empty($job['id'])): // Ensure ID exists before creating delete link ?>
                        <a href="job_actions.php?action=delete_job&id=<?= urlencode($job['id']) ?>" onclick="return confirm('Are you sure you want to delete this job: <?= addslashes(htmlspecialchars($job['title'] ?? '')) ?>?');"
                           class="delete">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
