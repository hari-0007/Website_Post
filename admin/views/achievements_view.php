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
    <h3>User Achievements</h3>

    <div class="dashboard-section quarterly-user-earnings-section">
        <h4>User Earnings (Last 3 Months)</h4>
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
    /* Add any specific styles for achievements-view-content if needed */
    .achievements-view-content h3 {
        color: #005fa3;
        margin-top: 0;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e0e0e0;
    }
    /* Assuming .dashboard-section, .chart-container, .no-data-message are globally styled */
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
