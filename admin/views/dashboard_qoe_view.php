<?php
// admin/views/dashboard_qoe_view.php

// Assumes $qoeMetrics is available from fetch_content.php (even if it's placeholder data)
$qoeMetrics = $qoeMetrics ?? [
    'avg_load_time' => 'N/A',
    'error_rate_percent' => 'N/A',
    'user_feedback_summary' => 'No QOE data available yet.'
];

// Assumes $mostViewedJobs is available from fetch_content.php for this view
$mostViewedJobs = $mostViewedJobs ?? [];

// QOE Chart data from fetch_content.php
$qoeChartLabels = $qoeChartLabels ?? [];
$qoeDnsResolutionData = $qoeDnsResolutionData ?? [];
$qoeServerResponseData = $qoeServerResponseData ?? [];
$shouldLoadChartJsForQoe = $shouldLoadChartJsForQoe ?? false;

?>
<div class="dashboard-content qoe-view-content">
    <h2 class="view-main-title">Quality of Experience (QOE) Metrics</h2>

    <div class="dashboard-section">
        <h4 class="section-title">Key Performance Indicators (Placeholder)</h4>
        <ul class="info-list styled-info-list">
            <li><strong>Average Page Load Time:</strong> <?= htmlspecialchars($qoeMetrics['avg_load_time']) ?></li>
            <li><strong>Application Error Rate:</strong> <?= htmlspecialchars($qoeMetrics['error_rate_percent']) ?></li>
            <li><strong>User Feedback Summary:</strong> <?= htmlspecialchars($qoeMetrics['user_feedback_summary']) ?></li>
        </ul>
        <p style="margin-top: 10px; font-size: 0.9em;"><em>The metrics above are placeholders. The charts below require data to be populated in <code>data/qoe_metrics_history.json</code> by an external monitoring process.</em></p>
    </div>

    <?php if ($shouldLoadChartJsForQoe): ?>
    <div class="qoe-charts-grid">
        <?php if (!empty($qoeDnsResolutionData) && count(array_filter($qoeDnsResolutionData, fn($value) => $value !== null)) > 0 ): ?>
        <div class="chart-container">
            <h4 class="chart-title">Daily Average DNS Resolution Time (Last 30 Days)</h4>
            <canvas id="qoeDnsResolutionChart"></canvas>
        </div>
        <?php else: ?>
        <div class="chart-container no-data-chart">
            <h4 class="chart-title">Daily Average DNS Resolution Time (Last 30 Days)</h4>
            <p class="no-data-message">No DNS resolution time data available for the last 30 days.</p>
        </div>
        <?php endif; ?>

        <?php if (!empty($qoeServerResponseData) && count(array_filter($qoeServerResponseData, fn($value) => $value !== null)) > 0): ?>
        <div class="chart-container">
            <h4 class="chart-title">Daily Average Server Response Time (Last 30 Days)</h4>
            <canvas id="qoeServerResponseChart"></canvas>
        </div>
        <?php else: ?>
        <div class="chart-container no-data-chart">
            <h4 class="chart-title">Daily Average Server Response Time (Last 30 Days)</h4>
            <p class="no-data-message">No server response time data available for the last 30 days.</p>
        </div>
        <?php endif; ?>
        
        <!-- Add more chart containers here for other QOE metrics -->
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
    .styled-info-list { /* Specific styling for info lists if needed beyond global */
        list-style: none;
        padding-left: 0;
    }
    .styled-info-list li {
        padding: 8px 0;
        border-bottom: 1px dashed var(--border-color);
        font-size: 0.95rem;
    }
    .styled-info-list li:last-child {
        border-bottom: none;
    }
    /* .info-list, .dashboard-section, .no-data-message styles are assumed to be global or in dashboard_overview_view.php */
    
    /* Styles for the data table, can be moved to a global CSS */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    .data-table th, .data-table td {
        border: 1px solid #ddd;
        padding: 8px 10px; /* Adjusted padding */
        text-align: left;
    }
    .data-table th { background-color: var(--body-bg); font-weight: 600; color: var(--text-color-light); }
    .data-table tr:nth-child(even) { background-color: var(--body-bg); }
    .data-table tr:hover { background-color: #e9e9e9; }

    .qoe-charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    /* .chart-container is assumed to be styled globally or via dashboard_overview_view.php styles */
    /* Specific overrides if needed: */
    .qoe-charts-grid .chart-container {
        padding: 15px;
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .chart-title { /* Renamed from .chart-container h4 for clarity */
        margin-top: 0;
        margin-bottom: 15px;
        font-size: 1.1rem;
        color: var(--text-color-light);
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 10px;
        font-weight: 500;
    }
    .chart-container canvas {
        max-height: 300px; /* Control chart height */
        width: 100% !important;
    }
    .no-data-message {
        color: #777;
        font-style: italic;
        padding: 20px;
        text-align: center;
    }
    .no-data-chart { /* Style for chart containers when there's no data */
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 250px; /* Ensure it has some height */
    }
</style>

<?php if ($shouldLoadChartJsForQoe): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const qoeChartLabels = <?= json_encode($qoeChartLabels) ?>;

    <?php if (!empty($qoeDnsResolutionData) && count(array_filter($qoeDnsResolutionData, fn($value) => $value !== null)) > 0): ?>
    if (document.getElementById('qoeDnsResolutionChart')) {
        const qoeDnsData = <?= json_encode($qoeDnsResolutionData) ?>;
        const dnsCtx = document.getElementById('qoeDnsResolutionChart').getContext('2d');
        new Chart(dnsCtx, {
            type: 'line',
            data: {
                labels: qoeChartLabels,
                datasets: [{
                    label: 'Avg DNS Resolution (ms)',
                    data: qoeDnsData,
                    borderColor: 'rgba(22, 163, 74, 1)', /* Greenish */
                    backgroundColor: 'rgba(22, 163, 74, 0.3)',
                    tension: 0.1,
                    fill: true,
                    spanGaps: true // Connect lines even if there are null data points
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, title: { display: true, text: 'Time (ms)' } } },
                plugins: { legend: { display: true }, title: { display: false } }
            }
        });
    }
    <?php endif; ?>

    <?php if (!empty($qoeServerResponseData) && count(array_filter($qoeServerResponseData, fn($value) => $value !== null)) > 0): ?>
    if (document.getElementById('qoeServerResponseChart')) {
        const qoeServerData = <?= json_encode($qoeServerResponseData) ?>;
        const serverCtx = document.getElementById('qoeServerResponseChart').getContext('2d');
        new Chart(serverCtx, {
            type: 'line',
            data: {
                labels: qoeChartLabels,
                datasets: [{
                    label: 'Avg Server Response (ms)',
                    data: qoeServerData,
                    borderColor: 'rgba(245, 158, 11, 1)', /* Amber/Orange */
                    backgroundColor: 'rgba(245, 158, 11, 0.3)',
                    tension: 0.1,
                    fill: true,
                    spanGaps: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, title: { display: true, text: 'Time (ms)' } } },
                plugins: { legend: { display: true }, title: { display: false } }
            }
        });
    }
    <?php endif; ?>

    // Initialize more charts here if you add them
});
</script>
<?php endif; ?>