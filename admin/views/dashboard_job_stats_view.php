<?php
// admin/views/dashboard_job_stats_view.php

// Assumes these variables are available from fetch_content.php:
$allJobs = $allJobs ?? [];
$jobsTodayCount = $jobsTodayCount ?? 0;
$jobsMonthlyCount = $jobsMonthlyCount ?? 0;
$graphLabels = $graphLabels ?? []; // For job posts chart
$graphData = $graphData ?? [];   // For job posts chart
$mostViewedJobs = $mostViewedJobs ?? [];

// Lifetime stats from jobs.json (calculated in fetch_content.php)
$totalLifetimeViews = $totalLifetimeViews ?? 0;
$totalLifetimeShares = $totalLifetimeShares ?? 0;

$displayTotalJobsCount = count($allJobs);
$shouldLoadChartJsForJobStats = !empty($graphLabels) && !empty($graphData);
?>
<div class="dashboard-content job-stats-view-content">
    <h2 class="view-main-title">Job Statistics</h2>

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
        <div class="stat-card">
            <h4>Total Lifetime Views (All Jobs)</h4>
            <p><?= htmlspecialchars($totalLifetimeViews) ?></p>
        </div>
        <div class="stat-card">
            <h4>Total Lifetime Shares (All Jobs)</h4>
            <p><?= htmlspecialchars($totalLifetimeShares) ?></p>
        </div>
    </div>

    <?php if ($shouldLoadChartJsForJobStats): ?>
    <div class="dashboard-section">
        <h4 class="section-title">Daily Job Posts (Last 30 Days)</h4>
        <div class="chart-container" style="height: 350px;">
            <canvas id="jobStatsPostsChart"></canvas>
        </div>
    </div>
    <?php else: ?>
    <div class="dashboard-section">
        <h4 class="section-title">Daily Job Posts (Last 30 Days)</h4>
        <p class="no-data-message">No data available to display chart.</p>
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
    .section-title { /* Consistent section titles */
        margin-top: 0;
        margin-bottom: 15px;
        color: var(--text-color-light);
        font-size: 1.2em;
        font-weight: 500;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border-color);
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
                    backgroundColor: 'rgba(59, 130, 246, 0.6)', /* Using a shade of primary blue */
                    borderColor: 'rgba(59, 130, 246, 1)',
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