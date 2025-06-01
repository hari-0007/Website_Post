<?php
// admin/views/dashboard_service_two_view.php

// Assumes $serverMetricsLabels, $serverCpuData, $serverMemoryData are available
$serverMetricsLabels = $serverMetricsLabels ?? [];
$serverCpuData = $serverCpuData ?? [];
$serverMemoryData = $serverMemoryData ?? [];

$shouldLoadChartJsForServiceTwo = !empty($serverMetricsLabels);
?>
<div class="dashboard-content">
    <h2 class="view-main-title">Server Metrics</h2>

    <?php if ($shouldLoadChartJsForServiceTwo): ?>
    <div class="dashboard-section server-monitoring-charts">
        <h4 class="section-title">Server Monitoring (Last <?= count($serverMetricsLabels) ?> Data Points)</h4>
        <div class="chart-container" style="height: 300px; margin-bottom: 20px;">
            <h5 class="chart-title-inline">CPU Usage (%)</h5>
            <canvas id="serviceCpuChart"></canvas> <!-- Unique ID -->
        </div>
        <div class="chart-container" style="height: 300px;">
            <h5 class="chart-title-inline">Memory Usage (%)</h5>
            <canvas id="serviceMemoryChart"></canvas> <!-- Unique ID -->
        </div>
    </div>
    <?php else: ?>
        <div class="dashboard-section">
            <h4 class="section-title">Server Monitoring</h4>
            <p class="no-data-message">No server monitoring data available yet. Ensure the collection script is running and populating <code>data/server_metrics_history.json</code>.</p>
        </div>
    <?php endif; ?>
</div>

<style>
    .view-main-title {
        margin-top: 0;
        margin-bottom: 25px;
        color: var(--primary-color);
        font-size: 1.75em;
        font-weight: 600;
        padding-bottom: 15px;
        border-bottom: 2px solid var(--primary-color-lighter);
    }
    .section-title {
        margin-top: 0;
        margin-bottom: 15px;
        color: var(--text-color-light);
        font-size: 1.2em;
        font-weight: 500;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border-color);
    }
    .chart-title-inline { /* For titles directly above charts if not using .chart-container h4 */
        margin-top: 0;
        margin-bottom: 10px;
        font-size: 1.1em;
        color: var(--text-color-light);
        font-weight: 500;
    }
    /* .dashboard-section, .chart-container, .no-data-message are assumed to be styled globally */
</style>

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
                        borderColor: 'rgba(239, 68, 68, 1)', /* Theme error color */
                        backgroundColor: 'rgba(239, 68, 68, 0.3)',
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
                        borderColor: 'rgba(59, 130, 246, 1)', /* Theme primary color */
                        backgroundColor: 'rgba(59, 130, 246, 0.3)',
                        fill: true, tension: 0.1
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, max: 100, title: { display: true, text: '%' } }, x: { title: { display: true, text: 'Time' } } }, plugins: { legend: { display: false }, title: { display: false } } }
            });
        }
    });
</script>
<?php endif; ?>