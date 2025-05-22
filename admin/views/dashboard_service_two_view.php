<?php
// admin/views/dashboard_service_two_view.php

// Assumes $serverMetricsLabels, $serverCpuData, $serverMemoryData are available
$serverMetricsLabels = $serverMetricsLabels ?? [];
$serverCpuData = $serverCpuData ?? [];
$serverMemoryData = $serverMemoryData ?? [];

$shouldLoadChartJsForServiceTwo = !empty($serverMetricsLabels);
?>
<div class="dashboard-content">
    <h3>Service Metrics</h3>

    <?php if ($shouldLoadChartJsForServiceTwo): ?>
    <div class="dashboard-section server-monitoring-charts">
        <h4>Server Monitoring (Last <?= count($serverMetricsLabels) ?> Data Points)</h4>
        <div class="chart-container" style="height: 300px; margin-bottom: 20px;">
            <h3>CPU Usage (%)</h3>
            <canvas id="serviceCpuChart"></canvas> <!-- Unique ID -->
        </div>
        <div class="chart-container" style="height: 300px;">
            <h3>Memory Usage (%)</h3>
            <canvas id="serviceMemoryChart"></canvas> <!-- Unique ID -->
        </div>
    </div>
    <?php else: ?>
        <p class="dashboard-section">No server monitoring data available yet. Ensure the collection script is running and populating <code>data/server_metrics_history.json</code>.</p>
    <?php endif; ?>
</div>

<?php if ($shouldLoadChartJsForServiceTwo): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const serverMetricsLabels = <?= json_encode($serverMetricsLabels) ?>;
        const serverCpuData = <?= json_encode($serverCpuData) ?>;
        const serverMemoryData = <?= json_encode($serverMemoryData) ?>;

        // CPU Chart
        if (document.getElementById('serviceCpuChart')) {
            const serverCpuCtx = document.getElementById('serviceCpuChart').getContext('2d');
            new Chart(serverCpuCtx, {
                type: 'line',
                data: {
                    labels: serverMetricsLabels,
                    datasets: [{
                        label: 'CPU Usage (%)',
                        data: serverCpuData,
                        borderColor: 'rgba(255, 99, 132, 1)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        fill: true, tension: 0.1
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, max: 100, title: { display: true, text: '%' } }, x: { title: { display: true, text: 'Time' } } }, plugins: { legend: { display: false }, title: { display: false } } }
            });
        }

        // Memory Chart
        if (document.getElementById('serviceMemoryChart')) {
            const serverMemoryCtx = document.getElementById('serviceMemoryChart').getContext('2d');
            new Chart(serverMemoryCtx, {
                type: 'line',
                data: {
                    labels: serverMetricsLabels,
                    datasets: [{
                        label: 'Memory Usage (%)',
                        data: serverMemoryData,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        fill: true, tension: 0.1
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, max: 100, title: { display: true, text: '%' } }, x: { title: { display: true, text: 'Time' } } }, plugins: { legend: { display: false }, title: { display: false } } }
            });
        }
    });
</script>
<?php endif; ?>