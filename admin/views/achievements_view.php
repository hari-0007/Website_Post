<?php
// admin/views/dashboard_achievements_view.php

// Data for User Earnings (Last 3 Months) chart
$quarterlyUserEarningsLabels = $quarterlyUserEarningsLabels ?? [];
$quarterlyUserEarningsData = $quarterlyUserEarningsData ?? [];

// Old achievements chart data is no longer used
// $achievementsChartLabels = $achievementsChartLabels ?? [];
// $achievementsChartData = $achievementsChartData ?? [];

$shouldLoadChartJsForAchievements = !empty($quarterlyUserEarningsLabels) && !empty($quarterlyUserEarningsData);
?>

<div class="dashboard-content achievements-view-content">
    <h2 class="view-main-title">User Achievements</h2>

    <div class="dashboard-section quarterly-user-earnings-section">
        <h4 class="section-title">User Earnings (Last 3 Months)</h4>
        <?php if (!empty($quarterlyUserEarningsData) && !empty($quarterlyUserEarningsLabels)): ?>
            <div class="chart-container" style="height: 400px;"> <!-- Adjust height as needed -->
                <canvas id="achievementsQuarterlyUserEarningsChart"></canvas> <!-- Unique ID for this chart instance -->
            </div>
        <?php else: ?>
            <p class="no-data-message">No user earnings data available for the last 3 months to display a chart.</p>
        <?php endif; ?>
    </div>

    <!-- The old "User Job Posting Achievements (Last 30 Days)" chart section would be removed from here -->
    <!-- Example of what to remove:
    <div class="dashboard-section user-job-posting-achievements-section">
        <h4>User Job Posting Achievements (Last 30 Days)</h4>
        <?php // if (!empty($achievementsChartData)): ?>
            <div class="chart-container" style="height: 350px;">
                <canvas id="userJobPostingAchievementsChart"></canvas>
            </div>
        <?php // else: ?>
            <p class="no-data-message">No achievement data available to display.</p>
        <?php // endif; ?>
    </div>
    -->

</div>

<style>
    .view-main-title { /* Consistent main title for views */
        margin-top: 0;
        margin-bottom: 25px;
        color: var(--primary-color);
        font-size: 1.75em;
        font-weight: 600;
        padding-bottom: 10px;
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
    /* .dashboard-section, .chart-container, .no-data-message are assumed to be styled globally
       or via styles in dashboard_overview_view.php or header.php.
       If not, ensure they use var(--card-bg), var(--border-color), etc.
       For example:
       .dashboard-section { background-color: var(--card-bg); border: 1px solid var(--border-color); padding: 20px; border-radius: var(--border-radius); box-shadow: var(--box-shadow-sm); margin-bottom: 20px; }
       .chart-container { background-color: var(--card-bg); border: 1px solid var(--border-color); padding: 1.5rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow-sm); margin-top: 1.5rem; } */
</style>

<?php if ($shouldLoadChartJsForAchievements): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if (!empty($quarterlyUserEarningsLabels) && !empty($quarterlyUserEarningsData)): ?>
    if (document.getElementById('achievementsQuarterlyUserEarningsChart')) {
        const quarterlyEarningsCtx = document.getElementById('achievementsQuarterlyUserEarningsChart').getContext('2d');
        new Chart(quarterlyEarningsCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($quarterlyUserEarningsLabels) ?>,
                datasets: <?= json_encode($quarterlyUserEarningsData) ?>
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Earnings (Points/Currency)' }
                    },
                    x: {
                        title: { display: true, text: 'Month' }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: { display: false } // Title is in H4
                }
            }
        });
    }
    <?php endif; ?>

    <?php /* Remove the JS for the old chart:
    if (!empty($achievementsChartLabels) && !empty($achievementsChartData)): ?>
    if (document.getElementById('userJobPostingAchievementsChart')) {
        // ... old chart initialization ...
    }
    <?php endif; */ ?>
});
</script>
<?php endif; ?>
