<?php
// admin/views/dashboard_job_stats_view.php

// Assumes these variables are available from fetch_content.php:
$allJobs = $allJobs ?? [];
$jobsTodayCount = $jobsTodayCount ?? 0;
$jobsMonthlyCount = $jobsMonthlyCount ?? 0;
$graphLabels = $graphLabels ?? []; // For job posts chart
$graphData = $graphData ?? [];   // For job posts chart
$mostViewedJobs = $mostViewedJobs ?? [];

$displayTotalJobsCount = count($allJobs);
$shouldLoadChartJsForJobStats = !empty($graphLabels) && !empty($graphData);
?>
<div class="dashboard-content job-stats-view-content">
    <h3>Job Statistics</h3>

    <div class="stats-grid">
        <div class="stat-card">
            <h4>Total Jobs (All Time)</h4>
            <p><?= htmlspecialchars($displayTotalJobsCount) ?></p>
        </div>
        <div class="stat-card">
            <h4>Jobs Posted Today</h4>
            <p><?= htmlspecialchars($jobsTodayCount) ?></p>
        </div>
        <div class="stat-card">
            <h4>Jobs Posted This Month</h4>
            <p><?= htmlspecialchars($jobsMonthlyCount) ?></p>
        </div>
    </div>

    <?php if ($shouldLoadChartJsForJobStats): ?>
    <div class="dashboard-section">
        <h4>Daily Job Posts (Last 30 Days)</h4>
        <div class="chart-container" style="height: 350px;">
            <canvas id="jobStatsPostsChart"></canvas>
        </div>
    </div>
    <?php else: ?>
    <div class="dashboard-section">
        <p class="no-data-message">No daily job posting data available to display a chart.</p>
    </div>
    <?php endif; ?>

    </div>
</div>

<style>
    .job-stats-view-content h3 {
        color: #005fa3; margin-top: 0; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #e0e0e0;
    }
    /* .stats-grid, .stat-card, .dashboard-section, .chart-container, .no-data-message, .data-table
       are assumed to be styled globally or in dashboard_overview_view.php styles */
    .job-stats-table th, .job-stats-table td {
        font-size: 0.9em; /* Slightly smaller for tables if needed */
    }
</style>

<?php if ($shouldLoadChartJsForJobStats): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('jobStatsPostsChart')) {
        const jobGraphLabels = <?= json_encode($graphLabels) ?>;
        const jobGraphData = <?= json_encode($graphData) ?>;
        const jobPostsCtx = document.getElementById('jobStatsPostsChart').getContext('2d');
        new Chart(jobPostsCtx, {
            type: 'bar',
            data: {
                labels: jobGraphLabels,
                datasets: [{
                    label: 'Daily Job Posts',
                    data: jobGraphData,
                    backgroundColor: 'rgba(40, 167, 69, 0.7)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } }, x: {} },
                plugins: { legend: { display: true }, title: { display: false } }
            }
        });
    }
});
</script>
<?php endif; ?>