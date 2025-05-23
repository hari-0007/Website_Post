<?php
// admin/views/dashboard_visitors_info_view.php

// Assumes these variables are available from fetch_content.php:
$totalViews = $totalViews ?? 0;
$monthlyVisitors = $monthlyVisitors ?? 0;
$totalPageRequestsAllTime = $totalPageRequestsAllTime ?? 0; // New variable
$monthlyTotalPageRequests = $monthlyTotalPageRequests ?? 0; // New variable
$visitorGraphLabels = $visitorGraphLabels ?? [];
$visitorGraphData = $visitorGraphData ?? [];
$totalRequestsGraphData = $totalRequestsGraphData ?? []; // New: For daily total requests chart

// Data for Top Searched Keywords chart
$topSearchedKeywordsLabels = $topSearchedKeywordsLabels ?? [];
$topSearchedKeywordsData = $topSearchedKeywordsData ?? [];

// $mostViewedJobs and $mostSharedJobs for 30-day stats are no longer used here.
// The view will rely on $allTimeTopViewedJobs and $allTimeTopSharedJobs.

// All-Time stats from jobs.json (prepared in fetch_content.php)
$allTimeTopViewedJobs = $allTimeTopViewedJobs ?? [];
$allTimeTopSharedJobs = $allTimeTopSharedJobs ?? [];

$shouldLoadChartJsForVisitors = (!empty($visitorGraphLabels) && (!empty($visitorGraphData) || !empty($totalRequestsGraphData))) || (!empty($topSearchedKeywordsLabels) && !empty($topSearchedKeywordsData));
?>
<div class="dashboard-content visitors-info-view-content">
    <h3>Visitor Statistics & Insights</h3>

    <div class="stats-grid">
        <div class="stat-card">
            <h4>Total Unique Visitors (All Time)</h4>
            <p><?= htmlspecialchars($totalViews) ?></p>
        </div>
         <div class="stat-card">
            <h4>Monthly Unique Visitors (Current Month)</h4>
            <p><?= htmlspecialchars($monthlyVisitors) ?></p>
        </div>
        <div class="stat-card">
            <h4>Total Page Loads (All Time)</h4>
            <p><?= htmlspecialchars($totalPageRequestsAllTime) ?></p>
        </div>
         <div class="stat-card">
            <h4>Monthly Page Loads (Current Month)</h4>
            <p><?= htmlspecialchars($monthlyTotalPageRequests) ?></p>
        </div>
    </div>

    <?php if (!empty($visitorGraphLabels) && !empty($visitorGraphData)): ?>
    <div class="dashboard-section">
        <h4>Daily Visitors (Last 30 Days)</h4>
        <div class="chart-container" style="height: 350px;">
            <canvas id="visitorsInfoChart"></canvas>
        </div>
    </div>
    <?php else: ?>
    <div class="dashboard-section">
        <p class="no-data-message">No daily visitor data available to display a chart.</p>
    </div>
    <?php endif; ?>

    <?php if (!empty($visitorGraphLabels) && !empty($totalRequestsGraphData) && count(array_filter($totalRequestsGraphData)) > 0): ?>
    <div class="dashboard-section">
        <h4>Daily Page Loads (Last 30 Days)</h4>
        <div class="chart-container" style="height: 350px;">
            <canvas id="totalPageLoadsChart"></canvas>
        </div>
    </div>
    <?php else: ?>
    <?php endif; ?>

    <?php if (!empty($topSearchedKeywordsLabels) && !empty($topSearchedKeywordsData)): ?>
    <div class="dashboard-section">
        <h4>Top 25 Searched Keywords (All Time)</h4>
        <div class="chart-container" style="height: 450px;"> <!-- Increased height for bar chart with more items -->
            <canvas id="topSearchedKeywordsChart"></canvas>
        </div>
    </div>
    <?php else: ?>
    <div class="dashboard-section">
        <p class="no-data-message">No search keyword data available to display a chart.</p>
    </div>
    
    <?php endif; ?>
    <?php if ($loggedInUserRole === 'super_admin' || in_array($loggedInUserRole, $allRegionalAdminRoles)): ?>
    <!-- All-Time Stats from jobs.json -->
<div class="stats-columns-container" style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px;">
    <div class="stats-column" style="width: 100%;">
        <?php if (!empty($allTimeTopViewedJobs)): ?>
        <div class="dashboard-section alltime-most-viewed-jobs-section">
            <h4>All-Time Top 10 Viewed Jobs</h4>
                <ol class="leaderboard-list">
                    <?php foreach ($allTimeTopViewedJobs as $job): ?>
                        <li>
                            <div class="leaderboard-item-main-content">
                                <span class="leaderboard-name">
                                    <?= htmlspecialchars($job['title'] ?? 'N/A') ?>
                                    <?php if(!empty($job['company'])): ?>
                                        <small>at <?= htmlspecialchars($job['company']) ?></small>
                                    <?php endif; ?>
                                </span>
                                <span class="leaderboard-count"><?= htmlspecialchars($job['total_views_count'] ?? 0) ?> views</span>
                            </div>
                            <?php if (!empty($job['id'])): ?>
                            <a href="../index.php?job_id=<?= htmlspecialchars($job['id']) ?>" target="_blank" class="leaderboard-action-button">View Job</a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
        </div>
        <?php else: ?>
        <div class="dashboard-section alltime-most-viewed-jobs-section">
            <h4>All-Time Top 10 Viewed Jobs</h4><p class="no-data-message">No job view data available.</p></div>
        <?php endif; ?>
    </div>

    <div class="stats-column" style="width: 100%;">
        <?php if (!empty($allTimeTopSharedJobs)): ?>
        <div class="dashboard-section alltime-most-shared-jobs-section">
            <h4>All-Time Top 10 Shared Jobs</h4>
                <ol class="leaderboard-list">
                    <?php foreach ($allTimeTopSharedJobs as $job): ?>
                        <li>
                            <div class="leaderboard-item-main-content">
                                <span class="leaderboard-name">
                                    <?= htmlspecialchars($job['title'] ?? 'N/A') ?>
                                    <?php if(!empty($job['company'])): ?>
                                        <small>at <?= htmlspecialchars($job['company']) ?></small>
                                    <?php endif; ?>
                                </span>
                                <span class="leaderboard-count"><?= htmlspecialchars($job['total_shares_count'] ?? 0) ?> shares</span>
                            </div>
                            <?php if (!empty($job['id'])): ?>
                            <a href="../index.php?job_id=<?= htmlspecialchars($job['id']) ?>" target="_blank" class="leaderboard-action-button">View Job</a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
        </div>
        <?php else: ?>
        <div class="dashboard-section alltime-most-shared-jobs-section">
            <h4>All-Time Top 10 Shared Jobs</h4><p class="no-data-message">No job share data available.</p></div>
        <?php endif; ?>
    </div>
<style>
      /* Enhanced Styles for Leaderboard-like lists (Most Viewed/Shared Jobs) */
    .leaderboard-list {
        list-style-type: decimal;
        padding-left: 30px; /* More space for list numbers */
        margin-top: 0; /* h4 margin-bottom provides top spacing */
        margin-bottom: 0; /* Remove default bottom margin from ol */
        padding-top: 0;
    }

    .leaderboard-list li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 5px 12px 0; /* Vertical padding, right padding for content, no left (handled by number) */
        border-bottom: 1px solid #f0f0f0; /* Lighter separator */
        font-size: 0.95rem;
        transition: background-color 0.2s ease-in-out;
        line-height: 1.4;
    }
    
    .leaderboard-item-main-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-grow: 1;
        margin-right: 15px; /* Space between text group and button */
    }

    .leaderboard-list li:last-child {
        border-bottom: none;
    }

    .leaderboard-list li:hover {
        background-color: #f8f9fa; /* Light hover effect */
    }

    .leaderboard-name {
        flex-grow: 1;
        margin-right: 10px; /* Space between name and count badge */
        color: #343a40; /* Darker text for title */
        font-weight: 500;
    }

    .leaderboard-name small {
        display: block; /* Company on a new line */
        font-size: 0.85em;
        color: #6c757d; /* Muted color for company */
        margin-top: 4px;
        font-weight: 400; /* Normal weight for company */
    }

    .leaderboard-count {
        font-size: 0.88em; /* Slightly smaller, clear text */
        font-weight: 600;
        color: #005fa3; /* Theme primary blue */
        background-color: #e6f0f7; /* Light blue background for the badge */
        padding: 5px 10px;
        border-radius: 16px; /* Pill shape */
        min-width: 75px; /* Min width for consistency */
        text-align: center;
        white-space: nowrap; /* Prevent "views" or "shares" from wrapping */
    }

    .leaderboard-action-button {
        padding: 6px 12px;
        font-size: 0.85em;
        color: #fff;
        background-color: #007bff; /* Primary button color */
        border: none;
        border-radius: 4px;
        text-decoration: none;
        text-align: center;
        cursor: pointer;
        transition: background-color 0.2s ease-in-out;
        white-space: nowrap;
        flex-shrink: 0; /* Prevent button from shrinking */
    }
    .leaderboard-action-button:hover {
        background-color: #0056b3; /* Darker shade on hover */
        color: #fff;
    }
    /* Styles specific to visitors-info-view-content or rely on global dashboard styles */
    .visitors-info-view-content h3 {
        color: #005fa3;
        margin-top: 0;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e0e0e0;
    }
    /* .stats-grid, .stat-card, .dashboard-section, .chart-container, .no-data-message
       are assumed to be styled globally or in dashboard_overview_view.php styles.
       If .data-table is not global, you can add its styles here or to a central CSS file.
    */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    .data-table th, .data-table td {
        border: 1px solid #ddd;
        padding: 10px;
        text-align: left;
        font-size: 0.9em;
    }
    .data-table th { 
        background-color: #f2f2f2; 
        font-weight: bold; 
    }
    .data-table tr:nth-child(even) { background-color: #f9f9f9; }
    .data-table tr:hover { background-color: #e9e9e9; }
            .dashboard-section {
            background-color: #ffffff;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px; // Or use gap from parent container
        }
        .dashboard-section h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #005fa3;
            font-size: 1.1rem;
            font-weight: 500;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
            margin-bottom: 15px;
        }
        .leaderboard-list {
            list-style-type: decimal;
            padding-left: 25px;
            margin-top: 0;
        }
        .leaderboard-list li {
            margin-bottom: 8px;
            font-size: 0.95rem;
            display: flex;
            justify-content: space-between;
            padding: 5px 0; // Added for consistency
            border-bottom: 1px solid #f0f0f0; // Added for consistency
        } */ /* Commenting out redundant/conflicting styles below as they are now more specific */
        .leaderboard-list li:last-child {
           /* border-bottom: none; */ /* Handled by more specific .leaderboard-list li:last-child above */
        }
        .leaderboard-list small { /* Style for 'at company' */
            color: #555;
            font-size: 0.9em; /* Adjust as needed */
        }
        .leaderboard-name { font-weight: 500; color: #333; }
        .leaderboard-count { color: #007bff; font-weight: bold; }
        .no-data-message {
            color: #777;
            font-style: italic;
            padding: 10px 0;
        }
        .stats-columns-container {
            /* display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px; */
        }
        .stats-column {
            /* width: 100%; */ /* Ensures they stack vertically */
        }

</style>
<?php endif; // Closes the if ($loggedInUserRole === 'super_admin' || ...) ?>

<?php if ($shouldLoadChartJsForVisitors): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if (!empty($visitorGraphLabels) && !empty($visitorGraphData)): ?>
    if (document.getElementById('visitorsInfoChart')) {
        const visitorGraphLabels = <?= json_encode($visitorGraphLabels) ?>;
        const visitorGraphData = <?= json_encode($visitorGraphData) ?>;
        const visitorsCtx = document.getElementById('visitorsInfoChart').getContext('2d');
        new Chart(visitorsCtx, {
            type: 'line',
            data: {
                labels: visitorGraphLabels,
                datasets: [{
                    label: 'Daily Visitors',
                    data: visitorGraphData,
                    backgroundColor: 'rgba(0, 123, 255, 0.5)',
                    borderColor: 'rgba(0, 123, 255, 1)',
                    borderWidth: 1, fill: true, tension: 0.1
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } }, x: {} }, plugins: { legend: { display: true }, title: { display: false } } }
        });
    }
    <?php endif; ?>

    <?php if (!empty($visitorGraphLabels) && !empty($totalRequestsGraphData) && count(array_filter($totalRequestsGraphData)) > 0): ?>
    if (document.getElementById('totalPageLoadsChart')) {
        const pageLoadsLabels = <?= json_encode($visitorGraphLabels) ?>; // Same labels as unique visitors
        const pageLoadsData = <?= json_encode($totalRequestsGraphData) ?>;
        const pageLoadsCtx = document.getElementById('totalPageLoadsChart').getContext('2d');
        new Chart(pageLoadsCtx, {
            type: 'line',
            data: {
                labels: pageLoadsLabels,
                datasets: [{
                    label: 'Daily Page Loads',
                    data: pageLoadsData,
                    backgroundColor: 'rgba(255, 159, 64, 0.5)', // Orange color
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1, fill: true, tension: 0.1
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } }, x: {} }, plugins: { legend: { display: true }, title: { display: false } } }
        });
    }
    <?php endif; ?>


    <?php if (!empty($topSearchedKeywordsLabels) && !empty($topSearchedKeywordsData)): ?>
    if (document.getElementById('topSearchedKeywordsChart')) {
        const keywordsLabels = <?= json_encode($topSearchedKeywordsLabels) ?>;
        const keywordChartColors = [
            'rgba(255, 99, 132, 0.6)', 'rgba(54, 162, 235, 0.6)',
            'rgba(255, 206, 86, 0.6)', 'rgba(75, 192, 192, 0.6)',
            'rgba(153, 102, 255, 0.6)', 'rgba(255, 159, 64, 0.6)',
            'rgba(201, 203, 207, 0.6)', 'rgba(26, 188, 156, 0.6)',
            'rgba(231, 76, 60, 0.6)', 'rgba(142, 68, 173, 0.6)',
            'rgba(241, 196, 15, 0.6)', 'rgba(46, 204, 113, 0.6)'
            // Add more colors if you anticipate more than 12 top keywords frequently
        ];
        const keywordsData = <?= json_encode($topSearchedKeywordsData) ?>;
        const keywordsCtx = document.getElementById('topSearchedKeywordsChart').getContext('2d');
        new Chart(keywordsCtx, {
            type: 'bar',
            data: {
                labels: keywordsLabels,
                datasets: [{
                    label: 'Search Count',
                    data: keywordsData,
                    backgroundColor: keywordChartColors, // Use the array of colors
                    borderColor: keywordChartColors.map(color => color.replace('0.6', '1')), // Make border solid
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y', // Horizontal bar chart for better readability of keywords
                responsive: true, maintainAspectRatio: false,
                scales: { 
                    x: { 
                        beginAtZero: true, 
                        ticks: { 
                            // Ensure keywordsData is not empty before trying to use Math.max on it
                            stepSize: (keywordsData && keywordsData.length > 0) ? Math.max(1, Math.ceil(Math.max(...keywordsData) / 10)) : 1
                        } 
                    },
                    y: {
                        ticks: {
                            autoSkip: false // Ensure all keyword labels are shown
                        }
                    }
                },
                plugins: { 
                    legend: { display: false }, 
                    title: { display: true, text: 'Top Searched Keywords' } 
                }
            }
        });
    }
    <?php endif; ?>
});
</script>
<?php endif; ?>
