<?php
// admin/views/reported_jobs_view.php

$reportedJobsData = $reportedJobsData ?? []; // From fetch_content.php

?>
<div class="dashboard-content reported-jobs-view-content">
    <h3>Reported Jobs</h3>

    <?php if (empty($reportedJobsData)): ?>
        <p class="no-data-message">No jobs have been reported yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table reported-jobs-table">
                <thead>
                    <tr>
                        <th>Report ID</th>
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
    .reported-jobs-view-content h3 {
        color: #005fa3; margin-top: 0; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #e0e0e0;
    }
    .data-table.reported-jobs-table th,
    .data-table.reported-jobs-table td {
        font-size: 0.88em;
        vertical-align: middle;
    }
    .data-table.reported-jobs-table td small {
        display: block;
        font-size: 0.9em;
        color: #666;
    }
    .status-badge {
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: bold;
        color: white;
        text-transform: capitalize;
    }
    .status-pending-review { background-color: #ffc107; color: #333; }
    .status-reviewed { background-color: #17a2b8; }
    .status-action-taken { background-color: #28a745; }
    .status-dismissed { background-color: #6c757d; }

    .action-button.review-report-btn { background-color: #007bff; color:white; }
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