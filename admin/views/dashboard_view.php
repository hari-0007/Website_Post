<?php

// admin/views/dashboard_view.php - Displays Dashboard Statistics and Chart

// This file is included by dashboard.php when $requestedView is 'dashboard'.
// It assumes $totalViews, $monthlyVisitors, $jobsTodayCount, $jobsMonthlyCount, $graphLabels, $graphData, $visitorGraphLabels, $visitorGraphData are available.

?>
<h3>Website Statistics</h3>
<div class="stats-grid">
    <div class="stat-card">
        <h4>Visitors This Month</h4>
        <p><?= htmlspecialchars($monthlyVisitors) ?></p>
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

<div class="chart-container">
    <h3>Daily Job Posts (Last 30 Days)</h3>
    <canvas id="dailyJobsChart"></canvas>
</div>

<div class="chart-container">
    <h3>Daily Visitors (Last 30 Days)</h3>
    <canvas id="dailyVisitorsChart"></canvas>
</div>

<script>
    // Existing Job Posts Chart
    const jobCtx = document.getElementById('dailyJobsChart').getContext('2d');
    const dailyJobsChart = new Chart(jobCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_values($graphLabels ?? [])); ?>,
            datasets: [{
                label: 'Jobs Posted',
                data: <?= json_encode(array_values($graphData ?? [])); ?>,
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

    // Visitors Chart
    const visitorCtx = document.getElementById('dailyVisitorsChart').getContext('2d');
    const dailyVisitorsChart = new Chart(visitorCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($visitorGraphLabels) ?>,
            datasets: [{
                label: 'Daily Visitors',
                data: <?= json_encode($visitorGraphData) ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1,
                fill: true,
                tension: 0.4 // Smooth curve
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Number of Visitors' },
                    ticks: { callback: function(value) { if (Number.isInteger(value)) { return value; } }, stepSize: 1 }
                },
                x: { title: { display: true, text: 'Date' } }
            },
            plugins: { legend: { display: false }, title: { display: true, text: 'Visitors Per Day' } }
        }
    });
</script>
