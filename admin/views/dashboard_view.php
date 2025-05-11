<?php

// admin/views/dashboard_view.php - Displays Dashboard Statistics and Chart

// This file is included by dashboard.php when $requestedView is 'dashboard'.
// It assumes $totalViews, $jobsTodayCount, $jobsMonthlyCount, $graphLabels, $graphData are available.

?>
<h3>Website Statistics</h3>
<div class="stats-grid">
    <div class="stat-card">
        <h4>Total Views</h4>
        <p><?= $totalViews ?></p>
    </div>
    <div class="stat-card">
        <h4>Jobs Posted Today</h4>
        <p><?= $jobsTodayCount ?></p>
    </div>
    <div class="stat-card">
        <h4>Jobs Posted This Month</h4>
        <p><?= $jobsMonthlyCount ?></p>
    </div>
</div>

<div class="chart-container">
    <h3>Daily Job Posts (Last 30 Days)</h3>
    <canvas id="dailyJobsChart"></canvas>
</div>

 <script>
    const ctx = document.getElementById('dailyJobsChart').getContext('2d');

    // Data passed from PHP (available because this view block is active)
    const graphLabels = <?= json_encode(array_values($graphLabels ?? [])); ?>;
    const graphData = <?= json_encode(array_values($graphData ?? [])); ?>;

    const dailyJobsChart = new Chart(ctx, {
        type: 'bar', // Or 'line'
        data: {
            labels: graphLabels,
            datasets: [{
                label: 'Jobs Posted',
                data: graphData,
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
             responsive: true,
             maintainAspectRatio: false,
             scales: {
                 y: {
                     beginAtZero: true,
                     title: { display: true, text: 'Number of Jobs' },
                     ticks: { callback: function(value) { if (Number.isInteger(value)) { return value; } }, stepSize: 1 }
                 },
                 x: { title: { display: true, text: 'Date' } }
             },
             plugins: { legend: { display: false }, title: { display: true, text: 'Job Posts Per Day' } }
        }
    });
</script>
