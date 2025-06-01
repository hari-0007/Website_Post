<?php
// admin/views/reported_jobs_view.php

$reportedJobsData = $reportedJobsData ?? []; // From fetch_content.php

?>
<div class="dashboard-content reported-jobs-view-content">
    <h2 class="view-main-title">Reported Jobs</h2>

    <?php if (empty($reportedJobsData)): ?>
        <p class="no-data-message" style="text-align: center; padding: 20px;">No jobs have been reported yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table reported-jobs-table">
                <thead>
                    <tr>
                        <th>Job Title</th>
                        <th>Job ID</th>
                        <th>Reported By</th>
                        <th>Reporter Email</th>
                        <th>Reason</th>
                        <th>Reported On</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportedJobsData as $report): ?>
                        <tr>
                            
                            <td>
                                <?= htmlspecialchars($report['job_title']) ?>
                                <?php if (!empty($report['job_company'])): ?>
                                    <small>(<?= htmlspecialchars($report['job_company']) ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="../index.php?job_id=<?= htmlspecialchars($report['job_id']) ?>" target="_blank" title="View Job">
                                    <?= htmlspecialchars($report['job_id']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($report['reporter_name'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($report['reporter_email'] ?: 'N/A') ?></td>
                            <td style="max-width: 300px; overflow-wrap: break-word;"><?= nl2br(htmlspecialchars($report['reason'])) ?></td>
                            <td><?= htmlspecialchars(date('Y-m-d H:i:s', $report['report_timestamp'])) ?></td>
                            <td>
                                <span class="status-badge status-<?= str_replace('_', '-', htmlspecialchars($report['status'])) ?>">
                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $report['status']))) ?>
                                </span>
                            </td>
                            <td>
                                <button class="action-button review-report-btn" data-report-id="<?= htmlspecialchars($report['report_id']) ?>">Review</button>
                                <!-- Add more actions like "Delete Report", "Take Action on Job" later -->
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
    .view-main-title { /* Consistent main title for views */
        margin-top: 0;
        margin-bottom: 25px;
        color: var(--primary-color);
        font-size: 1.75em;
        font-weight: 600;
        padding-bottom: 15px;
        border-bottom: 2px solid var(--primary-color-lighter);
    }
    .table-responsive { /* Wrapper for the table */
        overflow-x: auto;
        background-color: var(--card-bg); /* Match professional-table wrapper */
        border-radius: var(--border-radius);
        border: 1px solid var(--border-color);
        margin-top: 1.5rem;
        box-shadow: var(--box-shadow-sm);
    }
    .data-table.reported-jobs-table { /* Align with .professional-table */
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
        color: var(--text-color-light);
        margin-top: 0; /* Remove margin as wrapper handles it */
        border: none; /* Remove individual table border if wrapper has one */
        box-shadow: none; /* Remove individual shadow if wrapper has one */
        border-radius: 0; /* Wrapper handles radius */
    }
    .data-table.reported-jobs-table th,
    .data-table.reported-jobs-table td {
        font-size: 0.88em;
        vertical-align: middle;
        padding: .75rem 1rem; /* Match professional-table */
        border-bottom: 1px solid var(--border-color); /* Match professional-table */
        text-align: left;
    }
    .data-table.reported-jobs-table thead th {
        background-color: var(--body-bg); /* Match professional-table */
        color: var(--text-color-light); /* Match professional-table */
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        border-bottom-width: 2px; /* Match professional-table */
    }
    .data-table.reported-jobs-table td small {
        display: block;
        font-size: 0.9em;
        color: var(--text-muted); /* Use theme variable */
    }
    .status-badge {
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: bold;
        color: white;
        text-transform: capitalize;
        display: inline-block; /* Ensure proper display */
    }
    .status-pending-review { background-color: var(--warning-color); color: #fff; } /* Use theme warning */
    .status-reviewed { background-color: var(--info-color); } /* Use theme info */
    .status-action-taken { background-color: var(--success-color); } /* Use theme success */
    .status-dismissed { background-color: var(--secondary-color); } /* Use theme secondary */

    .action-button.review-report-btn { /* Align with global .button */
        /* Basic button styles are inherited from global .button if class is added */
        /* This specific class can be used for color overrides if needed */
        background-color: var(--primary-color); 
        color:white; 
        padding: .375rem .75rem; /* Smaller for table actions */
        font-size: 0.85rem;
        border-radius: var(--border-radius);
        border: none;
        cursor: pointer;
    }
    .action-button.review-report-btn:hover {
        background-color: var(--primary-color-darker);
    }
    /* Add more styles for other action buttons */
</style>

<script>
// Basic JS for future actions, e.g., handling the "Review" button click
document.querySelectorAll('.review-report-btn').forEach(button => {
    button.addEventListener('click', function() {
        const reportId = this.dataset.reportId;
        alert('Reviewing report ID: ' + reportId + '. Implement AJAX to update status.');
        // TODO: Implement AJAX call to a PHP script to update report status
        // e.g., mark as "reviewed", then refresh or update UI.
    });
});
</script>